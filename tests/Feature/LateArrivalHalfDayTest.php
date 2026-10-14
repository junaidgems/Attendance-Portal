<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\MonthlyLock;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Attendance Policy "Half day if check-in is later than N minutes after the shift start".
 */
class LateArrivalHalfDayTest extends TestCase
{
    use RefreshDatabase;

    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createSchema();
        MonthlyLock::forgetCache();

        // 10:00–19:00 shift, 15 min grace; check-in after 12:00 (120 min) = half day
        DB::table('attendance_settings')->insert([
            'standard_shift_start' => '10:00:00',
            'standard_shift_end' => '19:00:00',
            'late_arrival_grace_minutes' => 15,
            'early_departure_grace_minutes' => 0,
            'working_days' => 'Mon,Tue,Wed,Thu,Fri',
            'half_day_after_late_minutes' => 120,
            'late_arrivals_per_casual_leave' => 3,
            'half_days_per_casual_leave' => 2,
            'early_outs_per_casual_leave' => 3,
        ]);

        $this->employee = Employee::create(['employee_id' => 'UID-1', 'zkteco_uid' => '1', 'first_name' => 'Qazi', 'last_name' => 'Naeem', 'employment_status' => 'active']);
    }

    protected function tearDown(): void
    {
        MonthlyLock::forgetCache();

        parent::tearDown();
    }

    public function test_check_in_after_the_cut_off_is_a_half_day_even_after_a_full_day_of_work(): void
    {
        $record = $this->day('2026-10-05', '12:01:00', '19:00:00'); // Monday, 7 hours worked
        $settings = DB::table('attendance_settings')->first();

        $this->assertSame('Half Day', $this->employee->dayStatus('2026-10-05', $record, $settings)['status']);

        $summary = $this->employee->penaltySummary('2026-09-26', '2026-10-25', $settings);
        $this->assertSame(1, $summary['counts']['half_day']);
        $this->assertSame(0, $summary['counts']['late']);
    }

    public function test_check_in_within_the_cut_off_is_only_late(): void
    {
        $record = $this->day('2026-10-05', '12:00:00', '19:00:00');
        $settings = DB::table('attendance_settings')->first();

        $this->assertSame('Late', $this->employee->dayStatus('2026-10-05', $record, $settings)['status']);

        $summary = $this->employee->penaltySummary('2026-09-26', '2026-10-25', $settings);
        $this->assertSame(0, $summary['counts']['half_day']);
        $this->assertSame(1, $summary['counts']['late']);
    }

    public function test_rule_is_off_when_the_setting_is_empty(): void
    {
        DB::table('attendance_settings')->update(['half_day_after_late_minutes' => null]);
        $record = $this->day('2026-10-05', '13:00:00', '19:00:00');
        $settings = DB::table('attendance_settings')->first();

        $this->assertSame('Late', $this->employee->dayStatus('2026-10-05', $record, $settings)['status']);
        $this->assertSame(0, $this->employee->penaltySummary('2026-09-26', '2026-10-25', $settings)['counts']['half_day']);
    }

    public function test_days_before_count_penalties_from_keep_their_old_status(): void
    {
        DB::table('attendance_settings')->update(['penalty_policy_from' => '2026-10-09']);
        $before = $this->day('2026-10-05', '12:30:00', '19:00:00');
        $after = $this->day('2026-10-12', '12:30:00', '19:00:00');
        $settings = DB::table('attendance_settings')->first();

        $this->assertSame('Late', $this->employee->dayStatus('2026-10-05', $before, $settings)['status']);
        $this->assertSame('Half Day', $this->employee->dayStatus('2026-10-12', $after, $settings)['status']);

        $summary = $this->employee->penaltySummary('2026-09-26', '2026-10-25', $settings);
        $this->assertSame(1, $summary['counts']['half_day']);
        $this->assertSame(0, $summary['counts']['late']);
    }

    public function test_short_day_is_still_a_half_day_as_before(): void
    {
        $record = $this->day('2026-10-05', '10:00:00', '14:00:00'); // 4 hours of a 9-hour shift
        $settings = DB::table('attendance_settings')->first();

        $this->assertSame('Half Day', $this->employee->dayStatus('2026-10-05', $record, $settings)['status']);
    }

    public function test_late_arrival_without_a_check_out_is_not_a_half_day_yet(): void
    {
        $record = $this->day('2026-10-05', '13:00:00', null);
        $settings = DB::table('attendance_settings')->first();

        $this->assertSame('Missing Punch', $this->employee->dayStatus('2026-10-05', $record, $settings)['status']);
        $this->assertSame(0, $this->employee->penaltySummary('2026-09-26', '2026-10-25', $settings)['counts']['half_day']);
    }

    private function day(string $date, ?string $in, ?string $out): object
    {
        $id = DB::table('device_attendance')->insertGetId([
            'uid' => '1', 'name' => 'Qazi Naeem', 'date' => $date, 'check_in' => $in, 'check_out' => $out, 'status' => 'Late',
        ]);

        return DB::table('device_attendance')->find($id);
    }

    /**
     * The portal tables have no migrations in this repository, so the columns used here are created for the test.
     */
    private function createSchema(): void
    {
        foreach (['shifts', 'grace_policies'] as $name) {
            Schema::create($name, function (Blueprint $table) {
                $table->id();
                $table->string('name')->nullable();
                $table->time('start_time')->nullable();
                $table->time('end_time')->nullable();
                $table->time('saturday_start_time')->nullable();
                $table->time('saturday_end_time')->nullable();
                $table->integer('late_grace_minutes')->nullable();
                $table->integer('early_grace_minutes')->nullable();
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
            $table->string('first_name');
            $table->string('last_name')->nullable();
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
            $table->integer('half_day_after_late_minutes')->nullable();
            $table->integer('late_arrivals_per_casual_leave')->nullable();
            $table->integer('half_days_per_casual_leave')->nullable();
            $table->integer('early_outs_per_casual_leave')->nullable();
            $table->date('penalty_policy_from')->nullable();
        });

        Schema::create('device_attendance', function (Blueprint $table) {
            $table->id();
            $table->string('uid');
            $table->string('name')->nullable();
            $table->date('date');
            $table->time('check_in')->nullable();
            $table->time('check_out')->nullable();
            $table->string('status')->nullable();
            $table->integer('late_minutes')->default(0);
            $table->integer('early_out_minutes')->default(0);
            $table->string('waived_types')->nullable();
            $table->string('adjustment')->nullable();
            $table->timestamps();
        });

        Schema::create('holidays', function (Blueprint $table) {
            $table->id();
            $table->date('from_date');
            $table->date('to_date')->nullable();
        });

        Schema::create('leave_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->date('from_date');
            $table->date('to_date');
            $table->boolean('is_penalty')->default(false);
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
    }
}
