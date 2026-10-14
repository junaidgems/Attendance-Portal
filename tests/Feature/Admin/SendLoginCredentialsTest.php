<?php

namespace Tests\Feature\Admin;

use App\Models\Employee;
use App\Models\User;
use App\Notifications\LoginCredentials;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SendLoginCredentialsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private int $adminRoleId;

    private int $employeeRoleId;

    private int $departmentId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createSchema();
        Notification::fake();

        $this->adminRoleId = DB::table('roles')->insertGetId(['name' => 'administrator', 'display_name' => 'Administrator']);
        $this->employeeRoleId = DB::table('roles')->insertGetId(['name' => 'employee', 'display_name' => 'Employee']);
        $this->departmentId = DB::table('departments')->insertGetId(['name' => 'IT']);

        $this->admin = User::factory()->create(['name' => 'Admin User', 'role_id' => $this->adminRoleId, 'status' => 'Active']);
    }

    public function test_new_employee_receives_the_password_typed_by_admin(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.employee.store'), $this->newEmployeeData(['send_credentials' => '1']))
            ->assertSessionHas('success', 'Employee created successfully! Login details emailed to ali@example.com.');

        $user = User::where('email', 'ali@example.com')->first();
        $this->assertNotNull($user->credentials_sent_at);

        Notification::assertSentTo($user, LoginCredentials::class, function (LoginCredentials $notification) {
            $mail = $notification->toMail($notification);

            return $notification->email === 'ali@example.com'
                && $notification->password === 'Secret123'
                && in_array('**Password:** Secret123', $mail->introLines, true)
                && in_array('**Portal:** '.route('login'), $mail->introLines, true);
        });

        $this->assertDatabaseHas('audit_logs', ['action' => 'Login details emailed', 'detail' => 'Ali Khan (ali@example.com)']);
    }

    public function test_new_employee_gets_no_email_when_the_box_is_unticked(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.employee.store'), $this->newEmployeeData())
            ->assertSessionHas('success', 'Employee created successfully!');

        Notification::assertNothingSent();
        $this->assertNull(User::where('email', 'ali@example.com')->first()->credentials_sent_at);
    }

    public function test_edit_with_a_new_password_emails_it_when_ticked(): void
    {
        $employee = $this->createEmployee('sara@example.com');

        $this->actingAs($this->admin)
            ->put(route('admin.employee.update', $employee->id), $this->updateData($employee, ['password' => 'NewPass456', 'send_credentials' => '1']))
            ->assertSessionHas('success');

        Notification::assertSentTo($employee->user, LoginCredentials::class, fn (LoginCredentials $n) => $n->password === 'NewPass456');
        $this->assertTrue(Hash::check('NewPass456', $employee->user->fresh()->password));
    }

    public function test_edit_without_a_new_password_sends_nothing_even_when_ticked(): void
    {
        $employee = $this->createEmployee('sara@example.com');

        $this->actingAs($this->admin)
            ->put(route('admin.employee.update', $employee->id), $this->updateData($employee, ['send_credentials' => '1']))
            ->assertSessionHas('success', 'Employee updated successfully!');

        Notification::assertNothingSent();
    }

    public function test_single_send_sets_a_new_password_and_emails_it(): void
    {
        $employee = $this->createEmployee('sara@example.com');

        $this->actingAs($this->admin)
            ->post(route('admin.employee.send-credentials', $employee->id))
            ->assertSessionHas('success', 'New login details emailed to Sara Ahmed (sara@example.com).');

        $user = $employee->user->fresh();
        $this->assertFalse(Hash::check('old-password', $user->password));
        $this->assertNotNull($user->credentials_sent_at);

        Notification::assertSentTo($user, LoginCredentials::class, function (LoginCredentials $notification) use ($user) {
            return strlen($notification->password) === 10 && Hash::check($notification->password, $user->password);
        });
    }

    public function test_single_send_is_refused_for_the_logged_in_admin_and_inactive_accounts(): void
    {
        $adminEmployee = Employee::create([
            'employee_id' => 'GP-1000', 'user_id' => $this->admin->id, 'first_name' => 'Admin', 'last_name' => 'User',
            'email' => $this->admin->email, 'employment_status' => 'active',
        ]);
        $inactive = $this->createEmployee('old@example.com', userStatus: 'Inactive');

        $this->actingAs($this->admin)->post(route('admin.employee.send-credentials', $adminEmployee->id))->assertSessionHas('error');
        $this->actingAs($this->admin)->post(route('admin.employee.send-credentials', $inactive->id))->assertSessionHas('error');

        Notification::assertNothingSent();
        $this->assertTrue(Hash::check('old-password', $inactive->user->fresh()->password));
    }

    public function test_send_to_all_only_emails_active_employees_who_have_not_received_details(): void
    {
        $first = $this->createEmployee('first@example.com');
        $second = $this->createEmployee('second@example.com');
        $alreadySent = $this->createEmployee('sent@example.com', sentAt: now()->subDay());
        $terminated = $this->createEmployee('gone@example.com', employmentStatus: 'terminated');
        $otherAdmin = $this->createEmployee('admin2@example.com', roleId: $this->adminRoleId);

        $this->actingAs($this->admin)
            ->post(route('admin.employee.send-credentials.all'))
            ->assertSessionHas('success', 'Login details emailed to 2 employee(s).');

        Notification::assertSentTo([$first->user, $second->user], LoginCredentials::class);
        Notification::assertNotSentTo([$alreadySent->user, $terminated->user, $otherAdmin->user, $this->admin], LoginCredentials::class);

        $this->assertTrue(Hash::check('old-password', $alreadySent->user->fresh()->password));
        $this->assertTrue(Hash::check('old-password', $otherAdmin->user->fresh()->password));
    }

    public function test_send_to_all_twice_does_not_reset_passwords_again(): void
    {
        $employee = $this->createEmployee('first@example.com');

        $this->actingAs($this->admin)->post(route('admin.employee.send-credentials.all'));
        $passwordAfterFirstSend = $employee->user->fresh()->password;

        $this->actingAs($this->admin)
            ->post(route('admin.employee.send-credentials.all'))
            ->assertSessionHas('info');

        Notification::assertSentToTimes($employee->user, LoginCredentials::class, 1);
        $this->assertSame($passwordAfterFirstSend, $employee->user->fresh()->password);
    }

    public function test_non_admin_cannot_send_login_details(): void
    {
        $employee = $this->createEmployee('first@example.com');

        $this->actingAs($employee->user)->post(route('admin.employee.send-credentials.all'))->assertForbidden();
        $this->actingAs($employee->user)->post(route('admin.employee.send-credentials', $employee->id))->assertForbidden();

        Notification::assertNothingSent();
    }

    public function test_the_email_is_encrypted_while_queued(): void
    {
        $this->assertInstanceOf(ShouldBeEncrypted::class, new LoginCredentials('a@example.com', 'secret'));
        $this->assertInstanceOf(ShouldQueue::class, new LoginCredentials('a@example.com', 'secret'));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function newEmployeeData(array $overrides = []): array
    {
        return $overrides + [
            'first_name' => 'Ali',
            'last_name' => 'Khan',
            'email' => 'ali@example.com',
            'password' => 'Secret123',
            'cnic' => '35202-1234567-1',
            'employee_id' => 'GP-1001',
            'department_id' => $this->departmentId,
            'job_title' => 'Developer',
            'joining_date' => '2026-10-01',
            'role_id' => $this->employeeRoleId,
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function updateData(Employee $employee, array $overrides = []): array
    {
        return $overrides + [
            'first_name' => $employee->first_name,
            'last_name' => $employee->last_name,
            'email' => $employee->email,
            'employee_id' => $employee->employee_id,
            'cnic' => $employee->cnic,
            'role_id' => $this->employeeRoleId,
            'department_id' => $this->departmentId,
            'job_title' => 'Developer',
            'joining_date' => '2026-10-01',
            'employment_status' => 'active',
        ];
    }

    private function createEmployee(string $email, string $employmentStatus = 'active', string $userStatus = 'Active', ?int $roleId = null, $sentAt = null): Employee
    {
        static $number = 2000;
        $number++;
        $roleId ??= $this->employeeRoleId;

        $user = User::factory()->create([
            'name' => $email === 'sara@example.com' ? 'Sara Ahmed' : 'User '.$number,
            'email' => $email,
            'password' => Hash::make('old-password'),
            'role_id' => $roleId,
            'status' => $userStatus,
        ]);
        $user->forceFill(['credentials_sent_at' => $sentAt])->save();

        return Employee::create([
            'employee_id' => 'GP-'.$number,
            'user_id' => $user->id,
            'first_name' => $email === 'sara@example.com' ? 'Sara' : 'User',
            'last_name' => $email === 'sara@example.com' ? 'Ahmed' : (string) $number,
            'email' => $email,
            'cnic' => '35202-'.$number,
            'department_id' => $this->departmentId,
            'role_id' => $roleId,
            'job_title' => 'Developer',
            'employment_status' => $employmentStatus,
        ])->load('user');
    }

    /**
     * The portal tables have no migrations in this repository, so the columns used here are created for the test.
     */
    private function createSchema(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('role_id')->nullable();
            $table->string('status')->nullable();
            $table->timestamp('credentials_sent_at')->nullable();
        });

        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('display_name')->nullable();
            $table->timestamps();
        });

        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
        });

        Schema::create('grace_policies', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
        });

        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->string('employee_id')->nullable();
            $table->string('zkteco_uid')->nullable();
            $table->unsignedBigInteger('shift_id')->nullable();
            $table->unsignedBigInteger('grace_policy_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('first_name');
            $table->string('last_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('cnic')->nullable();
            $table->unsignedBigInteger('department_id')->nullable();
            $table->unsignedBigInteger('role_id')->nullable();
            $table->unsignedBigInteger('reports_to_id')->nullable();
            $table->string('job_title')->nullable();
            $table->date('joining_date')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('gender')->nullable();
            $table->string('address')->nullable();
            $table->string('emergency_contact')->nullable();
            $table->string('employment_status')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('actor_name')->nullable();
            $table->string('category');
            $table->string('action');
            $table->text('detail')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }
}
