<?php

namespace Tests\Feature;

use App\Console\Commands\SyncDeviceAttendance;
use App\Models\Employee;
use App\Models\MonthlyLock;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * A day with only one device punch late in the shift (the morning punch was missed or the device was down)
 * is a check-out with a missing check-in, not a late check-in with a missing check-out.
 */
class MissingCheckInTest extends TestCase
{
    use RefreshDatabase;

    private Employee $employee;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createSchema();
        MonthlyLock::forgetCache();

        // 09:00–18:00 shift: a single punch from 13:30 on is the check-out
        DB::table('attendance_settings')->insert([
            'standard_shift_start' => '09:00:00',
            'standard_shift_end' => '18:00:00',
            'late_arrival_grace_minutes' => 15,
            'early_departure_grace_minutes' => 0,
            'working_days' => 'Mon,Tue,Wed,Thu,Fri',
            'missing_punch_enabled' => true,
            'missing_punch_correction_days' => 1,
            'missing_punch_after_deadline' => 'half_day',
            'missing_punch_policy_from' => '2026-09-01',
            'missing_punch_notify_manager' => false,
            'missing_punch_notify_admin' => false,
        ]);

        $this->user = User::factory()->create();
        $this->employee = Employee::create([
            'employee_id' => 'UID-1', 'zkteco_uid' => '1', 'first_name' => 'Qazi', 'last_name' => 'Naeem',
            'employment_status' => 'active', 'user_id' => $this->user->id,
        ]);

        $this->travelTo(Carbon::parse('2026-10-06 10:00:00')); // Tuesday
    }

    protected function tearDown(): void
    {
        MonthlyLock::forgetCache();

        parent::tearDown();
    }

    public function test_single_evening_punch_is_saved_as_the_check_out(): void
    {
        $this->device([['1', '2026-10-05 17:34:31', 'CheckIn']]);

        $this->artisan('device:sync-attendance')->assertSuccessful();

        $row = DB::table('device_attendance')->where('uid', '1')->where('date', '2026-10-05')->sole();
        $this->assertNull($row->check_in);
        $this->assertSame('17:34:31', $row->check_out);
        $this->assertSame('On Time', $row->status);
        $this->assertSame(0, (int) $row->late_minutes);
    }

    public function test_single_punch_from_the_middle_of_the_shift_on_is_the_check_out(): void
    {
        $this->device([['1', '2026-10-05 13:30:00', 'CheckIn']]);

        $this->artisan('device:sync-attendance')->assertSuccessful();

        $row = DB::table('device_attendance')->sole();
        $this->assertNull($row->check_in);
        $this->assertSame('13:30:00', $row->check_out);
    }

    public function test_single_morning_punch_is_still_the_check_in(): void
    {
        $this->device([['1', '2026-10-05 09:50:00', 'CheckIn']]);

        $this->artisan('device:sync-attendance')->assertSuccessful();

        $row = DB::table('device_attendance')->sole();
        $this->assertSame('09:50:00', $row->check_in);
        $this->assertNull($row->check_out);
        $this->assertSame('Late', $row->status);
    }

    public function test_two_punches_are_not_changed(): void
    {
        $this->device([['1', '2026-10-05 09:50:00', 'CheckIn'], ['1', '2026-10-05 17:34:31', 'CheckIn']]);

        $this->artisan('device:sync-attendance')->assertSuccessful();

        $row = DB::table('device_attendance')->sole();
        $this->assertSame('09:50:00', $row->check_in);
        $this->assertSame('17:34:31', $row->check_out);
    }

    public function test_day_with_only_a_check_out_is_a_missing_punch_not_absent_or_late(): void
    {
        DB::table('device_attendance')->insert([
            'uid' => '1', 'name' => 'Qazi Naeem', 'date' => '2026-10-05', 'check_in' => null, 'check_out' => '17:34:31', 'status' => 'On Time',
        ]);
        $settings = DB::table('attendance_settings')->first();
        $employee = $this->employee->fresh()->load(['shift', 'gracePolicy']);

        $day = $employee->attendanceDays('2026-10-05', '2026-10-05', $settings)->get('2026-10-05');
        $this->assertSame('Missing Punch', $day['status']);

        $row = $employee->breakdownRow($day, $settings);
        $this->assertSame('Missing Punch', $row['in']);
        $this->assertSame('05:34 PM', $row['out']);
        $this->assertSame('—', $row['late']);
        $this->assertSame('—', $row['worked']);
        $this->assertTrue($row['missing_punch']);

        $summary = $employee->penaltySummary('2026-09-26', '2026-10-25', $settings);
        $this->assertSame(0, $summary['counts']['late']);
        $this->assertSame(0, $summary['counts']['early_out']);
        $this->assertSame(0, $summary['counts']['half_day']);
    }

    public function test_my_attendance_page_shows_a_missing_check_in_not_absent(): void
    {
        DB::table('device_attendance')->insert([
            'uid' => '1', 'name' => 'Qazi Naeem', 'date' => '2026-10-05', 'check_in' => null, 'check_out' => '17:34:31', 'status' => 'On Time',
        ]);
        $this->user->update(['role_id' => DB::table('roles')->insertGetId(['name' => 'employee']), 'status' => 'Active']);

        $day = collect($this->actingAs($this->user)->getJson(route('employee.my.attendance.data', ['month' => 10, 'year' => 2026]))->assertOk()->json())
            ->firstWhere('full_date', '2026-10-05');

        $this->assertSame('Present', $day['status']);
        $this->assertSame('Missing Punch', $day['in']);
        $this->assertSame('05:34 PM', $day['out']);
        $this->assertSame('Missing Punch', $day['timeline']);

        $this->actingAs($this->user)->get(route('employee.my.attendance', ['month' => 10, 'year' => 2026]))
            ->assertOk()
            ->assertSee('type=forgot-check-in', false)
            ->assertSee('1 missing');
    }

    public function test_a_director_is_never_absent_or_present_in_attendance(): void
    {
        $directorRoleId = DB::table('roles')->insertGetId(['name' => 'director']);
        $director = Employee::create(['employee_id' => 'GP-DIR', 'zkteco_uid' => '9', 'first_name' => 'Asad', 'last_name' => 'Ali', 'role_id' => $directorRoleId, 'employment_status' => 'active']);
        DB::table('device_attendance')->insert(['uid' => '9', 'name' => 'Asad Ali', 'date' => '2026-10-05', 'check_in' => '11:00:00', 'check_out' => '17:00:00', 'status' => 'Late']);
        $settings = DB::table('attendance_settings')->first();

        $days = $director->attendanceDays('2026-10-01', '2026-10-05', $settings);

        $this->assertSame(['', '', '', '', ''], $days->pluck('status')->all());

        // An ordinary employee with no punches is still absent
        $this->assertSame('Absent', $this->employee->attendanceDays('2026-10-05', '2026-10-05', $settings)->get('2026-10-05')['status']);
    }

    public function test_missing_check_in_is_notified_and_counted_as_a_half_day_after_the_deadline(): void
    {
        // Friday 2 Oct: only the evening punch; the 1-day correction deadline (3 Oct) has passed
        DB::table('device_attendance')->insert([
            'uid' => '1', 'name' => 'Qazi Naeem', 'date' => '2026-10-02', 'check_in' => null, 'check_out' => '17:30:00', 'status' => 'On Time',
        ]);

        $this->artisan('attendance:missing-punches')->assertSuccessful();

        $notice = DB::table('portal_notifications')->where('user_id', $this->user->id)->where('title', 'Missing check-in on 02 Oct')->first();
        $this->assertNotNull($notice);
        $this->assertStringContainsString('there is no check-in', $notice->message);
        $this->assertStringContainsString('type=forgot-check-in', $notice->link);

        // Half day ending at the real check-out: 4h30 of a 9h shift, and no late minutes
        $row = DB::table('device_attendance')->sole();
        $this->assertSame('13:00:00', $row->check_in);
        $this->assertSame('17:30:00', $row->check_out);
        $this->assertSame(0, (int) $row->late_minutes);
        $this->assertSame('On Time', $row->status);
        $this->assertSame('missing_punch_half_day', $row->adjustment);
        $this->assertSame('Missing check-in not corrected within 1 day(s): counted as Half Day', $row->adjustment_note);
        $this->assertNull($row->original_check_in);
        $this->assertSame('17:30:00', $row->original_check_out);

        $settings = DB::table('attendance_settings')->first();
        $this->assertSame('Half Day', $this->employee->fresh()->dayStatus('2026-10-02', $row, $settings)['status']);
    }

    public function test_missing_check_out_notice_is_unchanged(): void
    {
        DB::table('device_attendance')->insert([
            'uid' => '1', 'name' => 'Qazi Naeem', 'date' => '2026-10-05', 'check_in' => '09:05:00', 'check_out' => null, 'status' => 'On Time',
        ]);

        $this->artisan('attendance:missing-punches')->assertSuccessful();

        $notice = DB::table('portal_notifications')->where('user_id', $this->user->id)->sole();
        $this->assertSame('Missing check-out on 05 Oct', $notice->title);
        $this->assertStringContainsString('type=forgot-check-out', $notice->link);
        $this->assertNull(DB::table('device_attendance')->sole()->adjustment);
    }

    public function test_half_day_for_a_missing_check_in_is_replaced_when_the_device_later_has_both_punches(): void
    {
        DB::table('device_attendance')->insert([
            'uid' => '1', 'name' => 'Qazi Naeem', 'date' => '2026-10-02', 'check_in' => '13:00:00', 'check_out' => '17:30:00',
            'status' => 'On Time', 'adjustment' => 'missing_punch_half_day', 'adjustment_note' => 'Policy',
            'adjusted_by' => 'System', 'adjusted_at' => now(), 'original_check_in' => null, 'original_check_out' => '17:30:00',
        ]);
        $this->device([['1', '2026-10-02 09:05:00', 'CheckIn'], ['1', '2026-10-02 17:30:00', 'CheckIn']]);

        $this->artisan('device:sync-attendance')->assertSuccessful();

        $row = DB::table('device_attendance')->sole();
        $this->assertSame('09:05:00', $row->check_in);
        $this->assertSame('17:30:00', $row->check_out);
        $this->assertNull($row->adjustment);
        $this->assertNull($row->original_check_out);
    }

    /**
     * Fake ZKTeco device with these punches: [uid, recorded at, state].
     *
     * @param  array<int, array{0: string, 1: string, 2: string}>  $punches
     */
    private function device(array $punches): void
    {
        $records = array_map(fn ($p) => (object) [
            'userId' => $p[0],
            'recordedAt' => Carbon::parse($p[1]),
            'punchState' => (object) ['name' => $p[2]],
        ], $punches);

        $this->app->bind(SyncDeviceAttendance::class, fn () => new class($records) extends SyncDeviceAttendance
        {
            public function __construct(private array $fakePunches)
            {
                parent::__construct();
            }

            protected function readDevice(): array
            {
                return [[(object) ['userId' => '1', 'name' => 'Qazi Naeem']], $this->fakePunches];
            }

            protected function readDeviceClock(): \DateTimeImmutable
            {
                return new \DateTimeImmutable(Carbon::now(self::DEVICE_TIMEZONE)->format('Y-m-d H:i:s'));
            }

            protected function writeDeviceClock(\DateTimeImmutable $time): void {}
        });
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

        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->time('saturday_start_time')->nullable();
            $table->time('saturday_end_time')->nullable();
            $table->timestamps();
        });

        Schema::create('grace_policies', function (Blueprint $table) {
            $table->id();
            $table->integer('late_grace_minutes')->nullable();
            $table->integer('early_grace_minutes')->nullable();
            $table->timestamps();
        });

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
            $table->date('joining_date')->nullable();
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
            $table->boolean('missing_punch_enabled')->default(false);
            $table->integer('missing_punch_correction_days')->default(0);
            $table->string('missing_punch_after_deadline')->nullable();
            $table->date('missing_punch_policy_from')->nullable();
            $table->boolean('missing_punch_notify_manager')->default(false);
            $table->boolean('missing_punch_notify_admin')->default(false);
            $table->boolean('attendance_alerts_enabled')->default(false);
            $table->boolean('penalty_policy_enabled')->default(false);
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
            $table->string('source')->nullable();
            $table->text('work_summary')->nullable();
            $table->string('check_in_ip')->nullable();
            $table->string('check_out_ip')->nullable();
            $table->string('waived_types')->nullable();
            $table->string('adjustment')->nullable();
            $table->string('adjustment_note')->nullable();
            $table->string('adjusted_by')->nullable();
            $table->timestamp('adjusted_at')->nullable();
            $table->time('original_check_in')->nullable();
            $table->time('original_check_out')->nullable();
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
            $table->timestamps();
        });

        Schema::create('leave_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->unsignedBigInteger('leave_type_id')->nullable();
            $table->date('from_date');
            $table->date('to_date');
            $table->boolean('is_half_day')->default(false);
            $table->boolean('is_penalty')->default(false);
            $table->string('penalty_key')->nullable();
            $table->string('status');
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

        Schema::create('correction_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->date('date');
            $table->string('change_type')->nullable();
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
