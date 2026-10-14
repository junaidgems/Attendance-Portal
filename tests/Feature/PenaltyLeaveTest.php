<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\MonthlyLock;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PenaltyLeaveTest extends TestCase
{
    use RefreshDatabase;

    private Employee $employee;

    private int $casualLeaveId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createSchema();
        MonthlyLock::forgetCache();
        Notification::fake();

        DB::table('attendance_settings')->insert([
            'standard_shift_start' => '09:00:00',
            'standard_shift_end' => '18:00:00',
            'late_arrival_grace_minutes' => 0,
            'early_departure_grace_minutes' => 0,
            'working_days' => 'Mon,Tue,Wed,Thu,Fri',
            'cycle_start_day' => 1,
            'cycle_end_day' => 31,
            'penalty_policy_enabled' => 1,
            'late_arrivals_per_casual_leave' => 3,
            'early_outs_per_casual_leave' => 3,
            'half_days_per_casual_leave' => 2,
        ]);

        $this->casualLeaveId = DB::table('leave_types')->insertGetId(['name' => 'Casual Leave', 'days_per_year' => 12]);

        $user = User::factory()->create(['status' => 'Active']);
        $this->employee = Employee::create([
            'employee_id' => 'GP-1001',
            'zkteco_uid' => '101',
            'user_id' => $user->id,
            'first_name' => 'Ali',
            'last_name' => 'Khan',
            'email' => $user->email,
            'employment_status' => 'active',
        ]);
    }

    protected function tearDown(): void
    {
        MonthlyLock::forgetCache();

        parent::tearDown();
    }

    public function test_three_late_arrivals_deduct_half_a_casual_leave(): void
    {
        $this->punches(['2026-10-01', '2026-10-02', '2026-10-05'], '09:30:00', '18:00:00');

        $summary = $this->employee->penaltySummary('2026-10-01', '2026-10-31');
        $this->assertSame(1, $summary['due']['late']);
        $this->assertEquals(0.5, $summary['cl_due']);

        $result = $this->employee->syncPenaltyLeaves('2026-10-05');

        $this->assertSame(1, $result['created']);
        $leave = DB::table('leave_requests')->where('is_penalty', 1)->sole();
        $this->assertEquals(0.5, $leave->days);
        $this->assertSame('2026-10-01:late:1', $leave->penalty_key);
        $this->assertDatabaseHas('portal_notifications', ['title' => 'Casual Leave deducted']);
        $this->assertStringStartsWith('0.5 Casual Leave was deducted', DB::table('portal_notifications')->value('message'));
    }

    public function test_six_late_arrivals_deduct_two_half_days_and_two_late_arrivals_deduct_nothing(): void
    {
        $this->punches(['2026-10-01', '2026-10-02'], '09:30:00', '18:00:00');
        $this->employee->syncPenaltyLeaves('2026-10-02');
        $this->assertSame(0, DB::table('leave_requests')->count());

        $this->punches(['2026-10-05', '2026-10-06', '2026-10-07', '2026-10-08'], '09:30:00', '18:00:00');
        $this->employee->syncPenaltyLeaves('2026-10-08');

        $this->assertSame(2, DB::table('leave_requests')->where('is_penalty', 1)->count());
        $this->assertEquals(1.0, (float) DB::table('leave_requests')->where('is_penalty', 1)->sum('days'));
        $this->assertEquals(1.0, $this->employee->penaltySummary('2026-10-01', '2026-10-31')['cl_due']);
    }

    public function test_three_early_outs_deduct_half_a_casual_leave(): void
    {
        $this->punches(['2026-10-01', '2026-10-02', '2026-10-05'], '09:00:00', '17:00:00');

        $this->employee->syncPenaltyLeaves('2026-10-05');

        $leave = DB::table('leave_requests')->where('is_penalty', 1)->sole();
        $this->assertEquals(0.5, $leave->days);
        $this->assertSame('2026-10-01:early_out:1', $leave->penalty_key);
    }

    public function test_two_half_days_still_deduct_a_full_casual_leave(): void
    {
        $this->punches(['2026-10-01', '2026-10-02'], '09:00:00', '12:00:00');

        $this->assertEquals(1.0, $this->employee->penaltySummary('2026-10-01', '2026-10-31')['cl_due']);

        $this->employee->syncPenaltyLeaves('2026-10-02');

        $leave = DB::table('leave_requests')->where('is_penalty', 1)->sole();
        $this->assertEquals(1.0, $leave->days);
        $this->assertSame('2026-10-01:half_day:1', $leave->penalty_key);
    }

    public function test_absent_days_are_not_deducted_unless_the_policy_says_so(): void
    {
        $this->travelTo('2026-10-09 12:00:00');
        $this->punches(['2026-10-01', '2026-10-02'], '09:00:00', '18:00:00');
        // 5, 6, 7, 8 Oct: no punch at all (3 and 4 Oct are the weekend)

        $summary = $this->employee->penaltySummary('2026-10-01', '2026-10-31');
        $this->assertSame(0, $summary['counts']['absent']);
        $this->assertEquals(0, $summary['cl_due']);
        $this->assertSame(0, $this->employee->syncPenaltyLeaves('2026-10-08')['created']);
    }

    public function test_each_absent_day_deducts_one_casual_leave_and_today_is_not_counted(): void
    {
        $this->travelTo('2026-10-09 12:00:00');
        DB::table('attendance_settings')->update(['absent_days_per_casual_leave' => 1]);
        DB::table('holidays')->insert(['from_date' => '2026-10-07', 'to_date' => '2026-10-07']);
        $this->punches(['2026-10-01', '2026-10-02'], '09:00:00', '18:00:00');
        // 5, 6 and 8 Oct absent; 7 Oct holiday; 9 Oct is today (still in progress)

        $summary = $this->employee->penaltySummary('2026-10-01', '2026-10-31');
        $this->assertSame(['2026-10-08', '2026-10-06', '2026-10-05'], $summary['items']->where('type', 'absent')->pluck('date')->all());
        $this->assertSame(3, $summary['counts']['absent']);
        $this->assertEquals(3.0, $summary['cl_due']);

        $result = $this->employee->syncPenaltyLeaves('2026-10-08');

        $this->assertSame(3, $result['created']);
        $leaves = DB::table('leave_requests')->where('is_penalty', 1)->orderBy('from_date')->get();
        $this->assertSame(['2026-10-05', '2026-10-06', '2026-10-08'], $leaves->pluck('from_date')->all());
        $this->assertSame('2026-10-01:absent:1', $leaves[0]->penalty_key);
        $this->assertEquals(1.0, $leaves[0]->days);
        $this->assertSame('Penalty: 1 absent day(s) (05 Oct)', $leaves[0]->reason);
    }

    public function test_absent_days_before_count_penalties_from_or_on_approved_leave_are_not_counted(): void
    {
        $this->travelTo('2026-10-09 12:00:00');
        DB::table('attendance_settings')->update(['absent_days_per_casual_leave' => 1, 'penalty_policy_from' => '2026-10-06']);
        DB::table('leave_requests')->insert([
            'employee_id' => $this->employee->id, 'leave_type_id' => $this->casualLeaveId, 'from_date' => '2026-10-08', 'to_date' => '2026-10-08',
            'days' => 1, 'status' => 'approved', 'is_penalty' => 0,
        ]);
        // 1, 2, 5 Oct absent but before 6 Oct; 8 Oct on approved leave → only 6 and 7 Oct count

        $summary = $this->employee->penaltySummary('2026-10-01', '2026-10-31');
        $this->assertSame(['2026-10-07', '2026-10-06'], $summary['items']->where('type', 'absent')->pluck('date')->all());
    }

    public function test_an_approved_absent_waiver_returns_the_casual_leave(): void
    {
        $this->travelTo('2026-10-09 12:00:00');
        DB::table('attendance_settings')->update(['absent_days_per_casual_leave' => 1]);
        $this->punches(['2026-10-01', '2026-10-02', '2026-10-06', '2026-10-07', '2026-10-08'], '09:00:00', '18:00:00');
        // Only 5 Oct absent
        $this->employee->syncPenaltyLeaves('2026-10-08');
        $this->assertSame(1, DB::table('leave_requests')->where('is_penalty', 1)->count());

        DB::table('deduction_waivers')->insert(['employee_id' => $this->employee->id, 'date' => '2026-10-05', 'type' => 'absent', 'status' => 'approved']);

        $result = $this->employee->syncPenaltyLeaves('2026-10-08');

        $this->assertSame(1, $result['reversed']);
        $this->assertSame(0, DB::table('leave_requests')->where('is_penalty', 1)->count());
        $this->assertSame(0, $this->employee->penaltySummary('2026-10-01', '2026-10-31')['counts']['absent']);
    }

    public function test_days_before_count_penalties_from_are_not_counted(): void
    {
        DB::table('attendance_settings')->update(['penalty_policy_from' => '2026-10-05']);
        $this->punches(['2026-10-01', '2026-10-02', '2026-10-05', '2026-10-06'], '09:30:00', '17:00:00');

        $summary = $this->employee->penaltySummary('2026-10-01', '2026-10-31');

        // Only 5 and 6 Oct count: 2 late arrivals and 2 early outs, below the limit of 3
        $this->assertSame(2, $summary['counts']['late']);
        $this->assertSame(2, $summary['counts']['early_out']);
        $this->assertEquals(0, $summary['cl_due']);
        $this->assertSame(['2026-10-06', '2026-10-06', '2026-10-05', '2026-10-05'], $summary['items']->pluck('date')->all());

        $this->assertSame(0, $this->employee->syncPenaltyLeaves('2026-10-06')['created']);
    }

    public function test_setting_the_date_returns_a_penalty_that_was_already_deducted(): void
    {
        $this->punches(['2026-10-01', '2026-10-02', '2026-10-05'], '09:30:00', '18:00:00');
        $this->employee->syncPenaltyLeaves('2026-10-05');
        $this->assertSame(1, DB::table('leave_requests')->where('is_penalty', 1)->count());

        DB::table('attendance_settings')->update(['penalty_policy_from' => '2026-10-09']);
        $result = $this->employee->syncPenaltyLeaves('2026-10-05');

        $this->assertSame(1, $result['reversed']);
        $this->assertSame(0, DB::table('leave_requests')->where('is_penalty', 1)->count());
        $this->assertDatabaseHas('portal_notifications', ['title' => 'Penalty leave returned']);
    }

    public function test_days_from_the_date_onwards_count_as_before(): void
    {
        DB::table('attendance_settings')->update(['penalty_policy_from' => '2026-10-05']);
        $this->punches(['2026-10-05', '2026-10-06', '2026-10-07'], '09:30:00', '18:00:00');

        $this->assertSame(1, $this->employee->syncPenaltyLeaves('2026-10-07')['created']);
    }

    public function test_running_the_sync_again_does_not_deduct_twice(): void
    {
        $this->punches(['2026-10-01', '2026-10-02', '2026-10-05'], '09:30:00', '18:00:00');

        $this->employee->syncPenaltyLeaves('2026-10-05');
        $this->employee->syncPenaltyLeaves('2026-10-05');

        $this->assertSame(1, DB::table('leave_requests')->where('is_penalty', 1)->count());
    }

    public function test_half_a_day_of_balance_is_enough_for_a_late_penalty(): void
    {
        DB::table('leave_types')->where('id', $this->casualLeaveId)->update(['days_per_year' => 0.5]);
        $this->punches(['2026-10-01', '2026-10-02', '2026-10-05'], '09:30:00', '18:00:00');

        $result = $this->employee->syncPenaltyLeaves('2026-10-05');

        $this->assertSame(['created' => 1, 'reversed' => 0, 'shortfall' => 0], $result);
    }

    public function test_no_balance_left_becomes_a_shortfall(): void
    {
        DB::table('leave_types')->where('id', $this->casualLeaveId)->update(['days_per_year' => 0]);
        $this->punches(['2026-10-01', '2026-10-02', '2026-10-05'], '09:30:00', '18:00:00');

        $result = $this->employee->syncPenaltyLeaves('2026-10-05');

        $this->assertSame(['created' => 0, 'reversed' => 0, 'shortfall' => 1], $result);
    }

    /**
     * @param  array<int, string>  $dates
     */
    private function punches(array $dates, string $checkIn, string $checkOut): void
    {
        foreach ($dates as $date) {
            DB::table('device_attendance')->insert([
                'uid' => '101', 'name' => 'Ali Khan', 'date' => $date, 'check_in' => $checkIn, 'check_out' => $checkOut,
            ]);
        }
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

        foreach (['shifts', 'grace_policies'] as $name) {
            Schema::create($name, function (Blueprint $table) {
                $table->id();
                $table->timestamps();
            });
        }

        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('employee_id');
            $table->string('zkteco_uid')->nullable();
            $table->unsignedBigInteger('shift_id')->nullable();
            $table->unsignedBigInteger('grace_policy_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('role_id')->nullable();
            $table->string('first_name');
            $table->string('last_name')->nullable();
            $table->string('email')->nullable();
            $table->string('employment_status')->nullable();
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
            $table->integer('absent_days_per_casual_leave')->nullable();
            $table->boolean('email_notifications_enabled')->default(false);
            $table->date('penalty_policy_from')->nullable();
        });

        Schema::create('wfh_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->date('from_date');
            $table->date('to_date');
            $table->string('status');
            $table->timestamps();
        });

        Schema::create('device_attendance', function (Blueprint $table) {
            $table->id();
            $table->string('uid');
            $table->string('name')->nullable();
            $table->date('date');
            $table->time('check_in')->nullable();
            $table->time('check_out')->nullable();
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
    }
}
