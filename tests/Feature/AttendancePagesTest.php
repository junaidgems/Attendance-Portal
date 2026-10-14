<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\MonthlyLock;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * The My Attendance calendars (Employee / Manager / Line Manager) and the Team Attendance pages show every kind of
 * day the same way. These pin the rows each page produces.
 */
class AttendancePagesTest extends TestCase
{
    use RefreshDatabase;

    private Employee $manager;

    private Employee $lineManager;

    private Employee $sara;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createSchema();
        MonthlyLock::forgetCache();
        Employee::forgetScheduleCache();
        Employee::forgetProbationCache();
        $this->travelTo('2026-10-14 10:00:00'); // Wednesday

        DB::table('attendance_settings')->insert([
            'standard_shift_start' => '09:00:00', 'standard_shift_end' => '18:00:00',
            'late_arrival_grace_minutes' => 0, 'early_departure_grace_minutes' => 0,
            'working_days' => 'Mon,Tue,Wed,Thu,Fri',
        ]);
        DB::table('holidays')->insert(['name' => 'Eid', 'from_date' => '2026-10-07', 'to_date' => '2026-10-07']);
        $casual = DB::table('leave_types')->insertGetId(['name' => 'Casual Leave', 'days_per_year' => 12]);

        $roles = [];
        foreach (['manager', 'line_manager', 'employee'] as $role) {
            $roles[$role] = DB::table('roles')->insertGetId(['name' => $role, 'display_name' => ucwords(str_replace('_', ' ', $role))]);
        }
        $department = DB::table('departments')->insertGetId(['name' => 'IT']);

        $this->manager = $this->person('Mani', 'Manager', $roles['manager'], $department, null, '201');
        $this->lineManager = $this->person('Lina', 'Lead', $roles['line_manager'], $department, $this->manager->id, '202');
        $this->sara = $this->person('Sara', 'Khan', $roles['employee'], $department, $this->lineManager->id, '2');

        // Sara's October: one of every kind of day
        foreach ([
            ['2026-10-01', '09:00:00', '18:00:00', 'On Time'],  // Thu: present
            ['2026-10-02', '09:30:00', '18:00:00', 'Late'],     // Fri: late
            ['2026-10-05', '09:00:00', '12:00:00', 'On Time'],  // Mon: half day (3h of 9h)
            ['2026-10-06', '09:00:00', '16:00:00', 'On Time'],  // Tue: early out
            ['2026-10-09', '09:00:00', null, 'On Time'],        // Fri: missing check-out
            ['2026-10-12', null, '17:00:00', 'On Time'],        // Mon: missing check-in
            ['2026-10-14', '09:10:00', null, 'Late'],           // Wed (today, 10:00): late, still at work
        ] as [$date, $in, $out, $status]) {
            DB::table('device_attendance')->insert(['uid' => '2', 'name' => 'Sara Khan', 'date' => $date, 'check_in' => $in, 'check_out' => $out, 'status' => $status, 'source' => 'device']);
        }
        // 2026-10-07 holiday (Eid), 2026-10-08 approved leave, 2026-10-13 approved WFH without a check-in
        DB::table('leave_requests')->insert(['employee_id' => $this->sara->id, 'leave_type_id' => $casual, 'from_date' => '2026-10-08', 'to_date' => '2026-10-08', 'days' => 1, 'status' => 'approved', 'is_penalty' => 0]);
        DB::table('wfh_requests')->insert(['employee_id' => $this->sara->id, 'from_date' => '2026-10-13', 'to_date' => '2026-10-13', 'status' => 'approved']);
    }

    protected function tearDown(): void
    {
        MonthlyLock::forgetCache();
        Employee::forgetScheduleCache();
        Employee::forgetProbationCache();

        parent::tearDown();
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function calendarRows(): array
    {
        return [
            '2026-10-01' => ['status' => 'Present', 'in' => '09:00 AM', 'out' => '06:00 PM', 'net' => 9.0, 'timeline' => 'On Time'],
            '2026-10-02' => ['status' => 'Late', 'in' => '09:30 AM', 'out' => '06:00 PM', 'net' => 8.5, 'timeline' => 'Late'],
            '2026-10-03' => ['status' => 'Weekend', 'in' => '—', 'out' => '—', 'net' => '—', 'timeline' => '—'],
            '2026-10-05' => ['status' => 'Half Day', 'in' => '09:00 AM', 'out' => '12:00 PM', 'net' => 3.0, 'timeline' => 'Half Day'],
            '2026-10-06' => ['status' => 'Early Out', 'in' => '09:00 AM', 'out' => '04:00 PM', 'net' => 7.0, 'timeline' => 'Early Out'],
            '2026-10-07' => ['status' => 'Holiday', 'in' => '—', 'out' => '—', 'net' => '—', 'timeline' => 'Eid'],
            '2026-10-08' => ['status' => 'On Leave', 'in' => '—', 'out' => '—', 'net' => '—', 'timeline' => '—'],
            '2026-10-09' => ['status' => 'Present', 'in' => '09:00 AM', 'out' => 'Missing Punch', 'net' => '—', 'timeline' => 'Missing Punch'],
            '2026-10-12' => ['status' => 'Present', 'in' => 'Missing Punch', 'out' => '05:00 PM', 'net' => '—', 'timeline' => 'Missing Punch'],
            '2026-10-13' => ['status' => 'Absent', 'in' => '—', 'out' => '—', 'net' => '—', 'timeline' => 'WFH approved – no check-in', 'wfh' => 'WFH approved – no check-in'],
            '2026-10-14' => ['status' => 'Late', 'in' => '09:10 AM', 'out' => '—', 'net' => '—', 'timeline' => 'In Progress'],
            '2026-10-15' => ['status' => '', 'in' => '—', 'out' => '—', 'net' => '—', 'timeline' => '—'],
        ];
    }

    public function test_employee_my_attendance_calendar_rows(): void
    {
        $rows = collect($this->actingAs($this->sara->user)->getJson(route('employee.my.attendance.data', ['month' => 10, 'year' => 2026]))->assertOk()->json())->keyBy('full_date');

        $this->assertCount(31, $rows);
        foreach (self::calendarRows() as $date => $expected) {
            $this->assertEquals($expected, array_intersect_key($rows[$date], $expected), "row of {$date}");
            $this->assertSame((int) substr($date, 8, 2), $rows[$date]['date']);
        }

        $page = $this->actingAs($this->sara->user)->get(route('employee.my.attendance', ['month' => 10, 'year' => 2026]))->assertOk();
        $this->assertSame(['present' => 7, 'absent' => 1, 'late' => 2, 'early' => 1, 'half' => 1, 'missing' => 2], [
            'present' => $page->viewData('present'), 'absent' => $page->viewData('absent'), 'late' => $page->viewData('late'),
            'early' => $page->viewData('early'), 'half' => $page->viewData('half'), 'missing' => $page->viewData('missing'),
        ]);
        $this->assertEquals(self::calendarRows()['2026-10-09'], array_intersect_key(collect($page->viewData('data'))->firstWhere('full_date', '2026-10-09'), self::calendarRows()['2026-10-09']));
    }

    public function test_manager_and_line_manager_my_attendance_use_the_same_rows(): void
    {
        // The Line Manager has Sara's October punches too
        foreach (DB::table('device_attendance')->where('uid', '2')->get() as $r) {
            DB::table('device_attendance')->insert(['uid' => '202', 'name' => 'Lina Lead', 'date' => $r->date, 'check_in' => $r->check_in, 'check_out' => $r->check_out, 'status' => $r->status, 'source' => 'device']);
        }

        $rows = collect($this->actingAs($this->lineManager->user)->get(route('line_manager.my.attendance', ['month' => 10, 'year' => 2026]))->assertOk()->viewData('data'))->keyBy('full_date');
        foreach (['2026-10-01', '2026-10-02', '2026-10-05', '2026-10-06', '2026-10-09', '2026-10-12', '2026-10-14', '2026-10-15'] as $date) {
            $this->assertEquals(self::calendarRows()[$date], array_intersect_key($rows[$date], self::calendarRows()[$date]), "LM row of {$date}");
        }

        $page = $this->actingAs($this->manager->user)->get(route('manager.my.attendance', ['month' => 10, 'year' => 2026]))->assertOk();
        $this->assertSame('Absent', collect($page->viewData('data'))->firstWhere('full_date', '2026-10-13')['status']);
    }

    public function test_team_attendance_day_and_month_rows(): void
    {
        $day = $this->actingAs($this->lineManager->user)->get(route('line_manager.team.attendance', ['date' => '2026-10-02']))->assertOk();
        $row = collect($day->viewData('rows'))->firstWhere('emp_id', $this->sara->employee_id);
        $this->assertEquals(['status' => 'Late', 'tags' => ['present', 'late'], 'in' => '09:30', 'out' => '18:00', 'net' => '8h 30m', 'net_minutes' => 510, 'timeline' => 'Late', 'timeline_class' => 'timeline-late', 'date' => 'Fri 02 Oct'],
            array_intersect_key($row, array_flip(['status', 'tags', 'in', 'out', 'net', 'net_minutes', 'timeline', 'timeline_class', 'date'])));
        $this->assertSame('Working day', $day->viewData('dayLabel'));

        $month = $this->actingAs($this->lineManager->user)->get(route('line_manager.team.attendance', ['date' => '2026-10-14', 'member' => $this->sara->id]))->assertOk();
        $rows = collect($month->viewData('rows'))->keyBy('date');
        $expected = [
            'Thu 01 Oct' => ['Present', ['present'], 'On Time', 'timeline-on-time', '09:00', '18:00'],
            'Sat 03 Oct' => ['Weekend', [], '—', '', '—', '—'],
            'Mon 05 Oct' => ['Half Day', ['present', 'half-day'], 'Half Day', 'timeline-late', '09:00', '12:00'],
            'Tue 06 Oct' => ['Early Out', ['present', 'early-out'], 'Early Out', 'timeline-late', '09:00', '16:00'],
            'Wed 07 Oct' => ['Holiday', [], 'Eid', '', '—', '—'],
            'Thu 08 Oct' => ['On Leave', [], '—', '', '—', '—'],
            'Fri 09 Oct' => ['Present', ['present', 'absent'], 'Missing Punch', 'timeline-absent', '09:00', 'Missing Punch'],
            'Mon 12 Oct' => ['Present', ['present', 'absent'], 'Missing Punch', 'timeline-absent', 'Missing Punch', '17:00'],
            'Tue 13 Oct' => ['Absent', ['absent'], 'WFH approved – no check-in', 'timeline-absent', '—', '—'],
        ];
        foreach ($expected as $label => [$status, $tags, $timeline, $class, $in, $out]) {
            $this->assertSame([$status, $tags, $timeline, $class, $in, $out], [$rows[$label]['status'], $rows[$label]['tags'], $rows[$label]['timeline'], $rows[$label]['timeline_class'], $rows[$label]['in'], $rows[$label]['out']], "team row {$label}");
        }
        $this->assertSame('In Progress', $rows['Wed 14 Oct']['timeline']);
        $this->assertArrayNotHasKey('Thu 15 Oct', $rows->all(), 'future days are not listed');
        $this->assertSame(['all' => 14, 'present' => 7, 'late' => 2, 'early-out' => 1, 'half-day' => 1, 'absent' => 3], $month->viewData('counts'));

        // The Manager's team page (Lina is in it) builds the same rows
        $managerDay = $this->actingAs($this->manager->user)->get(route('manager.team.attendance', ['date' => '2026-10-02']))->assertOk();
        $this->assertSame('Absent', collect($managerDay->viewData('rows'))->firstWhere('emp_id', $this->lineManager->employee_id)['status']);
    }

    private function person(string $first, string $last, int $roleId, int $departmentId, ?int $reportsTo, string $uid): Employee
    {
        $user = User::factory()->create(['name' => "{$first} {$last}", 'role_id' => $roleId, 'status' => 'Active']);

        return Employee::create([
            'employee_id' => 'GP-'.$uid, 'zkteco_uid' => $uid, 'user_id' => $user->id, 'role_id' => $roleId, 'department_id' => $departmentId,
            'reports_to_id' => $reportsTo, 'first_name' => $first, 'last_name' => $last, 'email' => $user->email, 'joining_date' => '2026-09-01', 'employment_status' => 'active',
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
            $table->unsignedBigInteger('department_id')->nullable();
            $table->unsignedBigInteger('role_id')->nullable();
            $table->unsignedBigInteger('reports_to_id')->nullable();
            $table->string('first_name');
            $table->string('last_name')->nullable();
            $table->string('email')->nullable();
            $table->string('cnic')->nullable();
            $table->string('job_title')->nullable();
            $table->date('joining_date')->nullable();
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
            $table->integer('half_day_after_late_minutes')->nullable();
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
            $table->string('waived_types')->nullable();
            $table->string('adjustment')->nullable();
            $table->string('adjustment_note')->nullable();
            $table->timestamps();
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
            $table->timestamps();
        });
        Schema::create('leave_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->unsignedBigInteger('leave_type_id')->nullable();
            $table->date('from_date');
            $table->date('to_date');
            $table->decimal('days', 5, 1)->default(1);
            $table->boolean('is_half_day')->default(false);
            $table->boolean('is_penalty')->default(false);
            $table->string('penalty_key')->nullable();
            $table->string('status');
            $table->string('manager_status')->nullable();
            $table->timestamps();
        });
        Schema::create('wfh_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->date('from_date');
            $table->date('to_date');
            $table->string('status');
            $table->string('manager_status')->nullable();
            $table->timestamps();
        });
        Schema::create('correction_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->date('date');
            $table->string('status');
            $table->string('lm_status')->nullable();
            $table->timestamps();
        });
        Schema::create('deduction_waivers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->date('date');
            $table->string('type');
            $table->string('status');
            $table->string('lm_status')->nullable();
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
        });
    }
}
