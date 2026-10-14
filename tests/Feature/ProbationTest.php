<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\LeaveType;
use App\Models\MonthlyLock;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Probation: no paid leave until the "probation until" date; deductions become salary days.
 */
class ProbationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private int $employeeRoleId;

    private int $departmentId;

    private int $casualLeaveId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createSchema();
        MonthlyLock::forgetCache();
        Employee::forgetProbationCache();
        Employee::forgetScheduleCache();
        $this->travelTo('2026-10-14 10:00:00');

        DB::table('attendance_settings')->insert([
            'standard_shift_start' => '09:00:00', 'standard_shift_end' => '18:00:00',
            'late_arrival_grace_minutes' => 0, 'early_departure_grace_minutes' => 0,
            'working_days' => 'Mon,Tue,Wed,Thu,Fri', 'cycle_start_day' => 1, 'cycle_end_day' => 31,
            'penalty_policy_enabled' => 1, 'late_arrivals_per_casual_leave' => 3, 'early_outs_per_casual_leave' => 3, 'half_days_per_casual_leave' => 2,
        ]);
        $this->casualLeaveId = DB::table('leave_types')->insertGetId(['name' => 'Casual Leave', 'days_per_year' => 12]);
        DB::table('leave_types')->insert(['name' => 'Annual Leave', 'days_per_year' => 14]);

        $adminRoleId = DB::table('roles')->insertGetId(['name' => 'administrator', 'display_name' => 'Administrator']);
        $this->employeeRoleId = DB::table('roles')->insertGetId(['name' => 'employee', 'display_name' => 'Employee']);
        $this->departmentId = DB::table('departments')->insertGetId(['name' => 'IT']);
        $this->admin = User::factory()->create(['role_id' => $adminRoleId, 'status' => 'Active']);
    }

    protected function tearDown(): void
    {
        MonthlyLock::forgetCache();
        Employee::forgetProbationCache();
        Employee::forgetScheduleCache();

        parent::tearDown();
    }

    public function test_probation_is_decided_by_the_date_only(): void
    {
        $this->assertTrue($this->employee('2026-12-31')->isOnProbation());
        $this->assertTrue($this->employee('2026-10-14')->isOnProbation(), 'on probation through the last day');
        $this->assertFalse($this->employee('2026-10-13')->isOnProbation());
        $this->assertFalse($this->employee(null)->isOnProbation());
    }

    public function test_no_paid_leave_and_no_leave_requests_while_on_probation(): void
    {
        $sara = $this->employee('2026-12-31');

        $this->actingAs($sara->user)->get(route('employee.leave'))
            ->assertOk()
            ->assertSee('probation until 31 Dec 2026');

        $this->actingAs($sara->user)
            ->post(route('employee.leave.store'), ['leave_type_id' => $this->casualLeaveId, 'from_date' => '2026-10-20', 'to_date' => '2026-10-20', 'reason' => 'Family'])
            ->assertSessionHas('error', 'You are on probation until 31 Dec 2026. Leave can be applied after that.');
        $this->assertSame(0, DB::table('leave_requests')->count());

        $casual = LeaveType::find($this->casualLeaveId);
        $this->assertEquals(0, $sara->leaveAllowance($casual));
        $this->assertEquals(12, $this->employee(null)->leaveAllowance($casual));

        // After the date the full quota applies and requests go through
        $this->travelTo('2027-01-02 10:00:00');
        $this->assertFalse($sara->fresh()->isOnProbation());
        $this->actingAs($sara->user)
            ->post(route('employee.leave.store'), ['leave_type_id' => $this->casualLeaveId, 'from_date' => '2027-01-11', 'to_date' => '2027-01-11', 'reason' => 'Family'])
            ->assertSessionHas('success');
    }

    public function test_penalties_on_probation_become_salary_days_instead_of_casual_leave(): void
    {
        $sara = $this->employee('2026-12-31');
        foreach (['2026-10-05', '2026-10-06', '2026-10-07'] as $date) {
            DB::table('device_attendance')->insert(['uid' => '2', 'name' => 'Sara', 'date' => $date, 'check_in' => '09:30:00', 'check_out' => '18:00:00']);
        }

        $result = $sara->syncPenaltyLeaves('2026-10-07');

        $this->assertSame(0, $result['created']);
        $this->assertSame(1, $result['shortfall']);
        $this->assertSame(0, DB::table('leave_requests')->count());
    }

    public function test_admin_sets_and_extends_the_probation_date(): void
    {
        $sara = $this->employee('2026-12-31');

        $this->actingAs($this->admin)->get(route('admin.employee'))->assertOk()->assertSee('Probation');

        $this->actingAs($this->admin)
            ->put(route('admin.employee.update', $sara->id), $this->updateData($sara) + ['employment_type' => 'probation', 'probation_until' => '2027-03-31'])
            ->assertSessionHas('success');
        $this->assertSame('2027-03-31', $sara->fresh()->probation_until);
        $this->assertDatabaseHas('audit_logs', ['action' => 'Probation changed']);

        // Confirmed: date cleared
        $this->actingAs($this->admin)->put(route('admin.employee.update', $sara->id), $this->updateData($sara))->assertSessionHas('success');
        $this->assertNull($sara->fresh()->probation_until);
        $this->assertFalse($sara->fresh()->isOnProbation());
    }

    public function test_admin_is_reminded_a_week_before_probation_ends(): void
    {
        $this->employee('2026-10-21'); // ends in 7 days
        $this->employee('2026-10-25', 'GP-3', '3'); // later

        $this->artisan('employees:probation-ending')->assertSuccessful();

        $this->assertSame(1, DB::table('portal_notifications')->where('user_id', $this->admin->id)->count());
        $this->assertStringContainsString('21 Oct 2026', DB::table('portal_notifications')->value('message'));
    }

    private function employee(?string $probationUntil, string $employeeId = 'GP-2', string $uid = '2'): Employee
    {
        $user = User::factory()->create(['role_id' => $this->employeeRoleId, 'status' => 'Active']);

        return Employee::create([
            'employee_id' => $employeeId, 'zkteco_uid' => $uid, 'user_id' => $user->id, 'role_id' => $this->employeeRoleId,
            'department_id' => $this->departmentId, 'first_name' => 'Sara', 'last_name' => 'Khan', 'email' => $user->email,
            'cnic' => $employeeId, 'joining_date' => '2026-10-01', 'employment_status' => 'active', 'probation_until' => $probationUntil,
        ])->load('user');
    }

    /**
     * @return array<string, mixed>
     */
    private function updateData(Employee $employee): array
    {
        return [
            'first_name' => $employee->first_name, 'last_name' => $employee->last_name, 'email' => $employee->email,
            'employee_id' => $employee->employee_id, 'cnic' => $employee->cnic, 'role_id' => $this->employeeRoleId,
            'department_id' => $this->departmentId, 'job_title' => 'Developer', 'joining_date' => '2026-10-01', 'employment_status' => 'active',
        ];
    }

    /**
     * The portal tables have no migrations in this repository, so the columns used here are created for the test.
     */
    private function createSchema(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('role_id')->nullable();
            $table->string('status')->nullable();
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
        foreach (['shifts', 'grace_policies'] as $name) {
            Schema::create($name, function (Blueprint $table) {
                $table->id();
                $table->string('name')->nullable();
                $table->timestamps();
            });
        }
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('employee_id')->nullable();
            $table->string('zkteco_uid')->nullable();
            $table->unsignedBigInteger('shift_id')->nullable();
            $table->unsignedBigInteger('grace_policy_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('department_id')->nullable();
            $table->unsignedBigInteger('role_id')->nullable();
            $table->unsignedBigInteger('reports_to_id')->nullable();
            $table->string('first_name');
            $table->string('last_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('cnic')->nullable();
            $table->string('job_title')->nullable();
            $table->date('joining_date')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('gender')->nullable();
            $table->string('address')->nullable();
            $table->string('emergency_contact')->nullable();
            $table->string('employment_status')->nullable();
            $table->string('off_days', 30)->nullable();
            $table->string('wfh_days', 30)->nullable();
            $table->date('probation_until')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('attendance_settings', function (Blueprint $table) {
            $table->id();
            $table->time('standard_shift_start')->nullable();
            $table->time('standard_shift_end')->nullable();
            $table->integer('late_arrival_grace_minutes')->nullable();
            $table->integer('early_departure_grace_minutes')->nullable();
            $table->string('working_days')->nullable();
            $table->integer('cycle_start_day')->nullable();
            $table->integer('cycle_end_day')->nullable();
            $table->boolean('penalty_policy_enabled')->default(false);
            $table->integer('late_arrivals_per_casual_leave')->nullable();
            $table->integer('early_outs_per_casual_leave')->nullable();
            $table->integer('half_days_per_casual_leave')->nullable();
            $table->date('penalty_policy_from')->nullable();
        });
        Schema::create('device_attendance', function (Blueprint $table) {
            $table->id();
            $table->string('uid');
            $table->string('name')->nullable();
            $table->date('date');
            $table->time('check_in')->nullable();
            $table->time('check_out')->nullable();
            $table->string('waived_types')->nullable();
            $table->timestamps();
        });
        Schema::create('holidays', function (Blueprint $table) {
            $table->id();
            $table->date('from_date');
            $table->date('to_date')->nullable();
        });
        Schema::create('leave_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->decimal('days_per_year', 5, 1);
            $table->string('color')->nullable();
            $table->decimal('max_days_per_month', 4, 1)->nullable();
            $table->decimal('min_days_per_request', 4, 1)->nullable();
            $table->boolean('requires_document')->default(false);
            $table->decimal('document_required_from_days', 4, 1)->nullable();
            $table->timestamps();
        });
        Schema::create('leave_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->unsignedBigInteger('leave_type_id');
            $table->date('from_date');
            $table->date('to_date');
            $table->decimal('days', 5, 1);
            $table->boolean('is_half_day')->default(false);
            $table->string('half_day_session')->nullable();
            $table->text('reason')->nullable();
            $table->string('status');
            $table->text('admin_note')->nullable();
            $table->string('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->unsignedBigInteger('manager_id')->nullable();
            $table->string('manager_status')->nullable();
            $table->text('manager_note')->nullable();
            $table->timestamp('manager_reviewed_at')->nullable();
            $table->boolean('is_penalty')->default(false);
            $table->string('penalty_key')->nullable();
            $table->string('document_path')->nullable();
            $table->timestamps();
        });
        Schema::create('wfh_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->date('from_date');
            $table->date('to_date');
            $table->string('status');
            $table->timestamps();
        });
        Schema::create('deduction_waivers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->date('date');
            $table->string('type');
            $table->string('status');
            $table->timestamps();
        });
        Schema::create('monthly_locks', function (Blueprint $table) {
            $table->id();
            $table->string('month_year');
            $table->boolean('is_locked')->default(false);
            $table->timestamps();
        });
        Schema::create('portal_notifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('type');
            $table->string('title');
            $table->text('message');
            $table->string('link')->nullable();
            $table->string('dedupe_key')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'dedupe_key']);
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
