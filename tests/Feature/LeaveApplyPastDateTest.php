<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\LeaveType;
use App\Models\MonthlyLock;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LeaveApplyPastDateTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Employee $employee;

    private int $annualLeaveId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createSchema();
        MonthlyLock::forgetCache();
        Notification::fake();

        $roleId = DB::table('roles')->insertGetId(['name' => 'employee', 'display_name' => 'Employee']);
        DB::table('roles')->insert(['name' => 'administrator', 'display_name' => 'Administrator']);
        DB::table('attendance_settings')->insert(['working_days' => 'Mon,Tue,Wed,Thu,Fri']);
        $this->annualLeaveId = DB::table('leave_types')->insertGetId(['name' => 'Annual Leave', 'days_per_year' => 14]);

        $this->user = User::factory()->create(['role_id' => $roleId, 'status' => 'Active']);
        $this->employee = Employee::create([
            'employee_id' => 'GP-1001', 'user_id' => $this->user->id, 'first_name' => 'Ali', 'last_name' => 'Khan',
            'email' => $this->user->email, 'role_id' => $roleId, 'employment_status' => 'active',
        ]);
    }

    protected function tearDown(): void
    {
        MonthlyLock::forgetCache();

        parent::tearDown();
    }

    public function test_leave_can_be_applied_for_past_dates(): void
    {
        // Wed 15 – Fri 17 July 2026: 3 working days, months before today
        $this->actingAs($this->user)
            ->post(route('employee.leave.store'), $this->leaveData('2026-07-15', '2026-07-17'))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $leave = DB::table('leave_requests')->sole();
        $this->assertSame('2026-07-15', $leave->from_date);
        $this->assertEquals(3, $leave->days);
        $this->assertSame('pending', $leave->status);
    }

    public function test_past_leave_in_a_payroll_locked_month_is_still_blocked(): void
    {
        DB::table('monthly_locks')->insert(['month_year' => '2026-07', 'is_locked' => true]);

        $this->actingAs($this->user)
            ->post(route('employee.leave.store'), $this->leaveData('2026-07-15', '2026-07-15'))
            ->assertSessionHas('error');

        $this->assertSame(0, DB::table('leave_requests')->count());
    }

    public function test_to_date_before_from_date_is_rejected(): void
    {
        $this->actingAs($this->user)
            ->post(route('employee.leave.store'), $this->leaveData('2026-07-17', '2026-07-15'))
            ->assertSessionHasErrors('to_date');
    }

    public function test_the_same_past_days_cannot_be_requested_twice(): void
    {
        $this->actingAs($this->user)->post(route('employee.leave.store'), $this->leaveData('2026-07-15', '2026-07-15'));

        $this->actingAs($this->user)
            ->post(route('employee.leave.store'), $this->leaveData('2026-07-15', '2026-07-15'))
            ->assertSessionHas('error');

        $this->assertSame(1, DB::table('leave_requests')->count());
    }

    public function test_leave_year_runs_26_june_to_25_june_by_default(): void
    {
        [$from, $to] = LeaveType::leaveYear('2026-10-07');
        $this->assertSame(['2026-06-26', '2027-06-25'], [$from->toDateString(), $to->toDateString()]);

        [$from, $to] = LeaveType::leaveYear('2026-06-25');
        $this->assertSame(['2025-06-26', '2026-06-25'], [$from->toDateString(), $to->toDateString()]);

        [$from] = LeaveType::leaveYear('2026-06-26');
        $this->assertSame('2026-06-26', $from->toDateString());
    }

    public function test_leave_year_start_comes_from_the_attendance_policy(): void
    {
        DB::table('attendance_settings')->update(['leave_year_start' => '01-01']);

        [$from, $to] = LeaveType::leaveYear('2026-10-07');
        $this->assertSame(['2026-01-01', '2026-12-31'], [$from->toDateString(), $to->toDateString()]);

        // An invalid value falls back to 26 June
        DB::table('attendance_settings')->update(['leave_year_start' => '02-30']);
        [$from] = LeaveType::leaveYear('2026-10-07');
        $this->assertSame('2026-06-26', $from->toDateString());
    }

    public function test_balance_comes_back_in_full_when_a_new_leave_year_starts(): void
    {
        $this->approvedLeave('2026-07-06', 10);
        $this->approvedLeave('2026-05-04', 3); // previous leave year (26 Jun 2025 – 25 Jun 2026)

        $this->travelTo('2026-10-07');
        $this->actingAs($this->user)->get(route('employee.leave'))
            ->assertOk()
            ->assertSee('26 Jun 2026 – 25 Jun 2027')
            ->assertSee('Annual Leave · 4 left');

        $this->travelTo('2027-06-26');
        $this->actingAs($this->user)->get(route('employee.leave'))
            ->assertOk()
            ->assertSee('26 Jun 2027 – 25 Jun 2028')
            ->assertSee('Annual Leave · 14 left');
    }

    public function test_balance_check_uses_the_leave_year_of_the_requested_dates(): void
    {
        $this->travelTo('2026-10-07');
        $this->approvedLeave('2026-05-04', 14); // whole allowance used in the previous leave year

        // July 2026 is in the new leave year: allowed
        $this->actingAs($this->user)
            ->post(route('employee.leave.store'), $this->leaveData('2026-07-15', '2026-07-15'))
            ->assertSessionHas('success');

        // June 2026 (before 26 June) is in the previous leave year: no balance left
        $this->actingAs($this->user)
            ->post(route('employee.leave.store'), $this->leaveData('2026-06-10', '2026-06-10'))
            ->assertSessionHas('error');
    }

    public function test_leave_form_allows_picking_past_dates(): void
    {
        $this->actingAs($this->user)
            ->get(route('employee.leave'))
            ->assertOk()
            ->assertDontSee('id="fromDate" min=', false);
    }

    private function approvedLeave(string $from, float $days): void
    {
        DB::table('leave_requests')->insert([
            'employee_id' => $this->employee->id, 'leave_type_id' => $this->annualLeaveId,
            'from_date' => $from, 'to_date' => $from, 'days' => $days, 'reason' => 'Earlier leave',
            'status' => 'approved', 'manager_status' => 'not_required',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function leaveData(string $from, string $to): array
    {
        return [
            'leave_type_id' => $this->annualLeaveId,
            'from_date' => $from,
            'to_date' => $to,
            'reason' => 'Family trip in July',
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

        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('employee_id');
            $table->string('zkteco_uid')->nullable();
            $table->unsignedBigInteger('shift_id')->nullable();
            $table->unsignedBigInteger('grace_policy_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('first_name');
            $table->string('last_name')->nullable();
            $table->string('email')->nullable();
            $table->unsignedBigInteger('department_id')->nullable();
            $table->unsignedBigInteger('role_id')->nullable();
            $table->unsignedBigInteger('reports_to_id')->nullable();
            $table->string('employment_status')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('attendance_settings', function (Blueprint $table) {
            $table->id();
            $table->string('working_days')->nullable();
            $table->integer('cycle_start_day')->nullable();
            $table->integer('cycle_end_day')->nullable();
            $table->boolean('email_notifications_enabled')->default(false);
            $table->string('leave_year_start', 5)->default('06-26');
        });

        Schema::create('holidays', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->date('from_date');
            $table->date('to_date')->nullable();
        });

        Schema::create('leave_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->decimal('days_per_year', 5, 1);
            $table->string('color')->nullable();
            $table->decimal('max_days_per_month', 5, 1)->nullable();
            $table->decimal('min_days_per_request', 5, 1)->nullable();
            $table->boolean('requires_document')->default(false);
            $table->decimal('document_required_from_days', 5, 1)->nullable();
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
            $table->integer('days')->default(1);
            $table->string('status');
            $table->unsignedBigInteger('manager_id')->nullable();
            $table->string('manager_status')->nullable();
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
            $table->timestamp('cleared_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'dedupe_key']);
        });
    }
}
