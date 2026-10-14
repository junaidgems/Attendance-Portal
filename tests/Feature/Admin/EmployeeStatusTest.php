<?php

namespace Tests\Feature\Admin;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class EmployeeStatusTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private int $employeeRoleId;

    private int $departmentId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createSchema();

        $adminRoleId = DB::table('roles')->insertGetId(['name' => 'administrator']);
        $this->employeeRoleId = DB::table('roles')->insertGetId(['name' => 'employee']);
        $this->departmentId = DB::table('departments')->insertGetId(['name' => 'IT']);

        $this->admin = User::factory()->create(['name' => 'Admin User', 'role_id' => $adminRoleId, 'status' => 'Active']);
    }

    public function test_deactivating_blocks_login_and_keeps_the_employee(): void
    {
        $employee = $this->createEmployee();

        $this->actingAs($this->admin)
            ->patch(route('admin.employee.toggle-status', $employee->id))
            ->assertSessionHas('success', 'Ali Khan has been deactivated and can no longer log in.');

        $employee->refresh();
        $this->assertSame('inactive', $employee->employment_status);
        $this->assertSame('Inactive', $employee->user->status);
        $this->assertNull($employee->deleted_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'Employee deactivated', 'detail' => 'Ali Khan (GP-1001)']);

        // Already logged in: logged out on the next click
        $this->actingAs($employee->user)
            ->get(route('employee.dashboard'))
            ->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_activating_again_allows_login(): void
    {
        $employee = $this->createEmployee(employmentStatus: 'inactive', userStatus: 'Inactive');

        $this->actingAs($this->admin)
            ->patch(route('admin.employee.toggle-status', $employee->id))
            ->assertSessionHas('success', 'Ali Khan is active again and can log in.');

        $employee->refresh();
        $this->assertSame('active', $employee->employment_status);
        $this->assertSame('Active', $employee->user->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'Employee activated']);
    }

    public function test_admin_cannot_deactivate_their_own_account(): void
    {
        $own = $this->createEmployee(user: $this->admin);

        $this->actingAs($this->admin)
            ->patch(route('admin.employee.toggle-status', $own->id))
            ->assertSessionHas('error', 'You cannot deactivate your own account.');

        $this->actingAs($this->admin)
            ->put(route('admin.employee.update', $own->id), $this->updateData($own, 'inactive'))
            ->assertSessionHas('error', 'You cannot deactivate your own account.');

        $this->assertSame('Active', $this->admin->fresh()->status);
        $this->assertSame('active', $own->fresh()->employment_status);
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function employmentStatuses(): array
    {
        return [
            'inactive blocks login' => ['inactive', 'Inactive'],
            'terminated blocks login' => ['terminated', 'Inactive'],
            'on leave can still log in' => ['on_leave', 'Active'],
            'active can log in' => ['active', 'Active'],
        ];
    }

    #[DataProvider('employmentStatuses')]
    public function test_edit_form_status_also_sets_the_login_status(string $employmentStatus, string $expectedUserStatus): void
    {
        $employee = $this->createEmployee(employmentStatus: $employmentStatus === 'active' ? 'inactive' : 'active');

        $this->actingAs($this->admin)
            ->put(route('admin.employee.update', $employee->id), $this->updateData($employee, $employmentStatus))
            ->assertSessionHas('success');

        $this->assertSame($employmentStatus, $employee->fresh()->employment_status);
        $this->assertSame($expectedUserStatus, $employee->user->fresh()->status);
    }

    public function test_edit_without_cnic_shows_a_validation_error_instead_of_failing(): void
    {
        $employee = $this->createEmployee();

        $this->actingAs($this->admin)
            ->put(route('admin.employee.update', $employee->id), ['cnic' => ''] + $this->updateData($employee, 'active'))
            ->assertSessionHasErrors('cnic');

        $this->assertSame('GP-1001', $employee->fresh()->cnic);
    }

    public function test_non_admin_cannot_change_status(): void
    {
        $employee = $this->createEmployee();
        $other = $this->createEmployee('sara@example.com', 'GP-1002');

        $this->actingAs($other->user)
            ->patch(route('admin.employee.toggle-status', $employee->id))
            ->assertForbidden();

        $this->assertSame('active', $employee->fresh()->employment_status);
    }

    /**
     * @return array<string, mixed>
     */
    private function updateData(Employee $employee, string $employmentStatus): array
    {
        return [
            'first_name' => $employee->first_name,
            'last_name' => $employee->last_name,
            'email' => $employee->email,
            'employee_id' => $employee->employee_id,
            'cnic' => $employee->cnic,
            'role_id' => $this->employeeRoleId,
            'department_id' => $this->departmentId,
            'job_title' => 'Developer',
            'joining_date' => '2026-10-01',
            'employment_status' => $employmentStatus,
        ];
    }

    private function createEmployee(string $email = 'ali@example.com', string $employeeId = 'GP-1001', string $employmentStatus = 'active', string $userStatus = 'Active', ?User $user = null): Employee
    {
        $user ??= User::factory()->create([
            'email' => $email,
            'role_id' => $this->employeeRoleId,
            'status' => $userStatus,
        ]);

        return Employee::create([
            'employee_id' => $employeeId,
            'user_id' => $user->id,
            'first_name' => $email === 'ali@example.com' ? 'Ali' : 'Sara',
            'last_name' => $email === 'ali@example.com' ? 'Khan' : 'Ahmed',
            'email' => $user->email,
            'cnic' => $employeeId,
            'department_id' => $this->departmentId,
            'role_id' => $user->role_id,
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

        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->default(1);
            $table->string('employee_id', 50)->unique();
            $table->integer('zkteco_uid')->nullable()->unique();
            $table->unsignedBigInteger('shift_id')->nullable();
            $table->unsignedBigInteger('grace_policy_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('first_name');
            $table->string('last_name')->nullable();
            $table->string('email')->unique();
            $table->string('phone')->nullable();
            $table->string('cnic', 40)->unique();
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
