<?php

namespace Tests\Feature\Admin;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DeleteEmployeeTest extends TestCase
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

        $this->admin = User::factory()->create(['role_id' => $adminRoleId, 'status' => 'Active']);
    }

    public function test_deleting_frees_email_cnic_employee_id_and_device_uid_but_keeps_the_row(): void
    {
        $this->actingAs($this->admin)->post(route('admin.employee.store'), $this->employeeData());
        $employee = Employee::where('email', 'ali@example.com')->firstOrFail();

        $this->actingAs($this->admin)
            ->delete(route('admin.employee.destroy', $employee->id))
            ->assertSessionHas('success');

        $deleted = Employee::withTrashed()->find($employee->id);
        $tag = 'DEL'.$employee->id.'-';

        $this->assertNotNull($deleted->deleted_at);
        $this->assertSame($tag.'ali@example.com', $deleted->email);
        $this->assertSame($tag.'35202-1234567-1', $deleted->cnic);
        $this->assertSame($tag.'GP-1001', $deleted->employee_id);
        $this->assertNull($deleted->zkteco_uid);
        $this->assertDatabaseMissing('users', ['email' => 'ali@example.com']);
    }

    public function test_the_same_person_can_be_added_again_after_being_deleted(): void
    {
        $this->actingAs($this->admin)->post(route('admin.employee.store'), $this->employeeData());
        $employee = Employee::where('email', 'ali@example.com')->firstOrFail();
        $this->actingAs($this->admin)->delete(route('admin.employee.destroy', $employee->id));

        $this->actingAs($this->admin)
            ->post(route('admin.employee.store'), $this->employeeData())
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $this->assertSame(1, Employee::where('email', 'ali@example.com')->count());
        $this->assertSame(2, Employee::withTrashed()->count());
        $this->assertSame('101', (string) Employee::where('email', 'ali@example.com')->value('zkteco_uid'));
    }

    public function test_no_login_account_is_left_behind_when_the_employee_cannot_be_saved(): void
    {
        // Make saving the employee row fail at the database (as an unexpected column error would on live)
        Schema::table('employees', fn (Blueprint $table) => $table->dropColumn('job_title'));

        $this->actingAs($this->admin)
            ->post(route('admin.employee.store'), $this->employeeData())
            ->assertSessionHas('error');

        $this->assertDatabaseMissing('users', ['email' => 'ali@example.com']);
        $this->assertSame(0, Employee::withTrashed()->count());
    }

    public function test_an_existing_active_employee_still_blocks_a_duplicate_email(): void
    {
        $this->actingAs($this->admin)->post(route('admin.employee.store'), $this->employeeData());

        $this->actingAs($this->admin)
            ->post(route('admin.employee.store'), $this->employeeData(['cnic' => '35202-7654321-1', 'employee_id' => 'GP-1002', 'zkteco_uid' => 102]))
            ->assertSessionHasErrors('email');
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function employeeData(array $overrides = []): array
    {
        return $overrides + [
            'first_name' => 'Ali',
            'last_name' => 'Khan',
            'email' => 'ali@example.com',
            'password' => 'Secret123',
            'cnic' => '35202-1234567-1',
            'employee_id' => 'GP-1001',
            'zkteco_uid' => 101,
            'department_id' => $this->departmentId,
            'job_title' => 'Developer',
            'joining_date' => '2026-10-01',
            'role_id' => $this->employeeRoleId,
        ];
    }

    /**
     * The portal tables have no migrations in this repository; the employees columns mirror the live
     * table (unique, NOT NULL email / cnic / employee_id, cnic widened to 40 characters).
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
    }
}
