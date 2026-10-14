<?php

namespace Tests\Feature;

use App\Console\Commands\SyncDeviceAttendance;
use App\Models\Employee;
use App\Models\MonthlyLock;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SyncDeviceAttendanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createSchema();
        MonthlyLock::forgetCache();

        DB::table('attendance_settings')->insert([
            'standard_shift_start' => '09:00:00',
            'standard_shift_end' => '18:00:00',
            'late_arrival_grace_minutes' => 15,
            'early_departure_grace_minutes' => 0,
            'working_days' => 'Mon,Tue,Wed,Thu,Fri',
        ]);

        Employee::create(['employee_id' => 'UID-1', 'zkteco_uid' => '1', 'first_name' => 'Qazi', 'last_name' => 'Naeem', 'employment_status' => 'active']);
    }

    protected function tearDown(): void
    {
        MonthlyLock::forgetCache();

        parent::tearDown();
    }

    public function test_evening_punch_stored_as_check_in_is_saved_as_check_out(): void
    {
        $this->device([
            ['1', '2026-10-01 09:45:13', 'CheckIn'],
            ['1', '2026-10-01 17:34:31', 'CheckIn'],
        ]);

        $this->artisan('device:sync-attendance')->assertSuccessful();

        $row = DB::table('device_attendance')->where('uid', '1')->where('date', '2026-10-01')->sole();
        $this->assertSame('09:45:13', $row->check_in);
        $this->assertSame('17:34:31', $row->check_out);
        $this->assertSame('Late', $row->status);
        $this->assertSame('Qazi Naeem', $row->name);
    }

    public function test_missing_punch_half_day_is_replaced_by_the_real_device_check_out(): void
    {
        // What the missing-punch policy did on live: check-out filled at check-in + 4h, marked as a half day
        DB::table('device_attendance')->insert([
            'uid' => '1', 'name' => 'Qazi Naeem', 'date' => '2026-10-01', 'check_in' => '09:45:13', 'check_out' => '13:45:13',
            'status' => 'Late', 'adjustment' => 'missing_punch_half_day', 'adjustment_note' => 'Missing check-out not corrected within 3 day(s): counted as Half Day',
            'adjusted_by' => 'System', 'adjusted_at' => now(), 'original_check_in' => '09:45:13',
        ]);
        $this->device([
            ['1', '2026-10-01 09:45:13', 'CheckIn'],
            ['1', '2026-10-01 17:34:31', 'CheckIn'],
        ]);

        $this->artisan('device:sync-attendance')->assertSuccessful();

        $row = DB::table('device_attendance')->where('uid', '1')->sole();
        $this->assertSame('17:34:31', $row->check_out);
        $this->assertNull($row->adjustment);
        $this->assertNull($row->adjustment_note);
        $this->assertNull($row->original_check_in);
        $this->assertDatabaseHas('audit_logs', ['action' => 'Missing punch restored from device']);
    }

    public function test_approved_correction_and_admin_mark_are_never_overwritten(): void
    {
        foreach (['correction' => '2026-10-01', 'manual' => '2026-10-02'] as $adjustment => $date) {
            DB::table('device_attendance')->insert([
                'uid' => '1', 'name' => 'Qazi Naeem', 'date' => $date, 'check_in' => '09:00:00', 'check_out' => '18:00:00',
                'status' => 'On Time', 'adjustment' => $adjustment, 'adjustment_note' => 'Kept',
            ]);
        }
        $this->device([
            ['1', '2026-10-01 09:45:13', 'CheckIn'], ['1', '2026-10-01 17:34:31', 'CheckIn'],
            ['1', '2026-10-02 10:30:00', 'CheckIn'], ['1', '2026-10-02 16:00:00', 'CheckOut'],
        ]);

        $this->artisan('device:sync-attendance')->assertSuccessful();

        foreach (['2026-10-01' => 'correction', '2026-10-02' => 'manual'] as $date => $adjustment) {
            $row = DB::table('device_attendance')->where('date', $date)->sole();
            $this->assertSame('09:00:00', $row->check_in);
            $this->assertSame('18:00:00', $row->check_out);
            $this->assertSame($adjustment, $row->adjustment);
        }
    }

    public function test_missing_punch_day_without_a_device_check_out_stays_as_the_policy_set_it(): void
    {
        DB::table('device_attendance')->insert([
            'uid' => '1', 'name' => 'Qazi Naeem', 'date' => '2026-10-01', 'check_in' => '09:45:13', 'check_out' => '13:45:13',
            'status' => 'Late', 'adjustment' => 'missing_punch_half_day', 'adjustment_note' => 'Policy',
        ]);
        $this->device([['1', '2026-10-01 09:45:13', 'CheckIn']]);

        $this->artisan('device:sync-attendance')->assertSuccessful();

        $row = DB::table('device_attendance')->sole();
        $this->assertSame('13:45:13', $row->check_out);
        $this->assertSame('missing_punch_half_day', $row->adjustment);
    }

    public function test_locked_month_is_not_changed(): void
    {
        DB::table('monthly_locks')->insert(['month_year' => '2026-10', 'is_locked' => true]);
        $this->device([['1', '2026-10-01 09:45:13', 'CheckIn'], ['1', '2026-10-01 17:34:31', 'CheckIn']]);

        $this->artisan('device:sync-attendance')->assertSuccessful();

        $this->assertSame(0, DB::table('device_attendance')->count());
    }

    public function test_device_clock_twelve_hours_off_is_set_to_pakistan_time(): void
    {
        $this->travelTo(Carbon::parse('2026-10-08 04:00:00', 'UTC')); // 09:00 AM in Pakistan
        $adminRole = DB::table('roles')->insertGetId(['name' => 'administrator']);
        $adminId = DB::table('users')->insertGetId(['name' => 'Admin', 'email' => 'admin@example.com', 'password' => 'x', 'role_id' => $adminRole, 'status' => 'Active']);

        // Device shows 09:00 PM instead of 09:00 AM
        $this->device([], '2026-10-08 21:00:00');

        $this->artisan('device:sync-attendance --check-clock')->assertSuccessful();

        $this->assertSame('2026-10-08 09:00:00', self::$clockWritten?->format('Y-m-d H:i:s'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'Device clock corrected']);
        $this->assertStringContainsString('12h 0m ahead', DB::table('audit_logs')->where('action', 'Device clock corrected')->value('detail'));
        $this->assertDatabaseHas('portal_notifications', ['user_id' => $adminId, 'title' => 'Attendance device clock corrected']);
    }

    public function test_a_few_minutes_of_drift_is_left_alone(): void
    {
        $this->travelTo(Carbon::parse('2026-10-08 12:02:00', 'UTC')); // 05:02 PM in Pakistan
        $this->device([], '2026-10-08 16:59:00');

        $this->artisan('device:sync-attendance --check-clock')->assertSuccessful();

        $this->assertNull(self::$clockWritten);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'Device clock corrected']);
    }

    public function test_the_scheduled_sync_never_touches_the_clock(): void
    {
        $this->travelTo(Carbon::parse('2026-10-08 04:00:00', 'UTC')); // 09:00 AM in Pakistan
        $this->device([['1', '2026-10-01 09:45:13', 'CheckIn']], '2026-10-08 21:00:00'); // device 12h off

        $this->artisan('device:sync-attendance')->assertSuccessful();

        $this->assertNull(self::$clockWritten);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'Device clock corrected']);
        $this->assertSame(1, DB::table('device_attendance')->count());
    }

    public function test_attendance_still_syncs_when_the_clock_cannot_be_read(): void
    {
        $this->device([['1', '2026-10-01 09:45:13', 'CheckIn'], ['1', '2026-10-01 17:34:31', 'CheckIn']], null);

        $this->artisan('device:sync-attendance --check-clock')->assertSuccessful();

        $this->assertSame('17:34:31', DB::table('device_attendance')->value('check_out'));
    }

    /**
     * Time written to the fake device clock (null when the clock was not changed).
     */
    public static ?\DateTimeImmutable $clockWritten = null;

    /**
     * Fake ZKTeco device with these punches: [uid, recorded at, state], and its clock
     * (default: correct Pakistan time; null: reading the clock fails).
     *
     * @param  array<int, array{0: string, 1: string, 2: string}>  $punches
     */
    private function device(array $punches, ?string $clock = 'correct'): void
    {
        self::$clockWritten = null;

        $records = array_map(fn ($p) => (object) [
            'userId' => $p[0],
            'recordedAt' => Carbon::parse($p[1]),
            'punchState' => (object) ['name' => $p[2]],
        ], $punches);

        $this->app->bind(SyncDeviceAttendance::class, fn () => new class($records, $clock) extends SyncDeviceAttendance
        {
            public function __construct(private array $fakePunches, private ?string $fakeClock)
            {
                parent::__construct();
            }

            protected function readDevice(): array
            {
                return [[(object) ['userId' => '1', 'name' => 'Qazi Naeem']], $this->fakePunches];
            }

            protected function readDeviceClock(): \DateTimeImmutable
            {
                return match ($this->fakeClock) {
                    null => throw new \RuntimeException('Device did not answer'),
                    'correct' => new \DateTimeImmutable(Carbon::now(self::DEVICE_TIMEZONE)->format('Y-m-d H:i:s')),
                    default => new \DateTimeImmutable($this->fakeClock),
                };
            }

            protected function writeDeviceClock(\DateTimeImmutable $time): void
            {
                SyncDeviceAttendanceTest::$clockWritten = $time;
            }
        });
    }

    /**
     * The portal tables have no migrations in this repository, so the columns used here are created for the test.
     */
    private function createSchema(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('role_id')->nullable();
            $table->string('status')->nullable();
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

        Schema::create('leave_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->date('from_date');
            $table->date('to_date');
            $table->boolean('is_half_day')->default(false);
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
