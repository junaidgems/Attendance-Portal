<?php

namespace Tests\Feature;

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
 * Weekly schedule set by Admin on the employee: fixed off days (own weekend) and fixed Work From Home days.
 */
class EmployeeScheduleTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private int $employeeRoleId;

    private int $departmentId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createSchema();
        MonthlyLock::forgetCache();
        Employee::forgetScheduleCache();

        // Saturday is a company working day; 09:00–18:00
        DB::table('attendance_settings')->insert([
            'standard_shift_start' => '09:00:00',
            'standard_shift_end' => '18:00:00',
            'late_arrival_grace_minutes' => 15,
            'early_departure_grace_minutes' => 0,
            'working_days' => 'Mon,Tue,Wed,Thu,Fri,Sat',
            'missing_punch_enabled' => true,
            'missing_punch_correction_days' => 1,
            'missing_punch_after_deadline' => 'half_day',
            'missing_punch_policy_from' => '2026-09-01',
            'wfh_enabled' => true,
            'wfh_max_days_per_month' => 2,
        ]);

        $adminRoleId = DB::table('roles')->insertGetId(['name' => 'administrator', 'display_name' => 'Administrator']);
        $this->employeeRoleId = DB::table('roles')->insertGetId(['name' => 'employee', 'display_name' => 'Employee']);
        $this->departmentId = DB::table('departments')->insertGetId(['name' => 'IT']);
        $this->admin = User::factory()->create(['role_id' => $adminRoleId, 'status' => 'Active']);
    }

    protected function tearDown(): void
    {
        MonthlyLock::forgetCache();
        Employee::forgetScheduleCache();

        parent::tearDown();
    }

    public function test_an_off_day_is_a_weekend_only_for_that_employee(): void
    {
        $this->travelTo('2026-10-12 12:00:00');
        $sara = $this->employee('GP-2', '2', ['off_days' => 'Sat']);
        $ali = $this->employee('GP-1', '1');
        $settings = DB::table('attendance_settings')->first();

        // Saturday 10 Oct: nobody punched
        $this->assertSame('Weekend', $sara->attendanceDays('2026-10-10', '2026-10-10', $settings)->get('2026-10-10')['status']);
        $this->assertSame('Absent', $ali->attendanceDays('2026-10-10', '2026-10-10', $settings)->get('2026-10-10')['status']);

        // Monday is a working day for both
        $this->assertSame('Absent', $sara->attendanceDays('2026-10-05', '2026-10-05', $settings)->get('2026-10-05')['status']);
        $this->assertFalse($sara->worksOn('2026-10-10', $settings));
        $this->assertTrue($sara->worksOn('2026-10-05', $settings));

        // A Saturday punch by Sara is never a late / penalty
        DB::table('device_attendance')->insert(['uid' => '2', 'name' => 'Sara', 'date' => '2026-10-10', 'check_in' => '11:00:00', 'check_out' => '18:00:00', 'status' => 'Late']);
        $this->assertSame(0, $sara->penaltySummary('2026-09-26', '2026-10-25', $settings)['counts']['late']);
    }

    public function test_a_single_punch_on_an_off_day_is_not_a_missing_punch(): void
    {
        $this->travelTo('2026-10-12 12:00:00');
        $sara = $this->employee('GP-2', '2', ['off_days' => 'Sat']);
        DB::table('device_attendance')->insert(['uid' => '2', 'name' => 'Sara', 'date' => '2026-10-10', 'check_in' => '10:00:00', 'check_out' => null, 'status' => 'On Time']);

        $this->artisan('attendance:missing-punches')->assertSuccessful();

        $this->assertSame(0, DB::table('portal_notifications')->where('user_id', $sara->user_id)->count());
        $this->assertNull(DB::table('device_attendance')->value('adjustment'));
    }

    public function test_a_fixed_wfh_day_counts_as_approved_wfh_without_a_request(): void
    {
        $this->travelTo(Carbon::parse('2026-10-09 10:00:00')); // Friday
        $sara = $this->employee('GP-2', '2', ['wfh_days' => 'Fri']);
        $settings = DB::table('attendance_settings')->first();

        // Last Friday without a portal check-in shows as a WFH day with no check-in
        $this->assertSame('WFH approved – no check-in', $sara->attendanceDays('2026-10-02', '2026-10-02', $settings)->get('2026-10-02')['wfh']);
        $this->assertNull($sara->attendanceDays('2026-10-01', '2026-10-01', $settings)->get('2026-10-01')['wfh']);

        // Today (Friday) she can check in from the portal without any request
        $this->actingAs($sara->user)->get(route('employee.wfh'))->assertOk()->assertSee('Work From Home day (Fridays)');
        $this->actingAs($sara->user)->post(route('employee.wfh.check-in'))->assertSessionHas('success');

        $row = DB::table('device_attendance')->where('uid', '2')->where('date', '2026-10-09')->sole();
        $this->assertSame('10:00:00', $row->check_in);
        $this->assertSame('wfh', $row->source);
        $this->assertSame('WFH', $sara->attendanceDays('2026-10-09', '2026-10-09', $settings)->get('2026-10-09')['wfh']);

        // Somebody without a fixed day still needs an approved request
        $ali = $this->employee('GP-1', '1');
        $this->actingAs($ali->user)->post(route('employee.wfh.check-in'))->assertSessionHas('error', 'You do not have an approved Work From Home for today.');
    }

    public function test_fixed_wfh_days_are_not_requested_again_and_do_not_use_the_monthly_limit(): void
    {
        $this->travelTo('2026-10-05 10:00:00'); // Monday
        $sara = $this->employee('GP-2', '2', ['wfh_days' => 'Fri']);

        // Only Fridays: nothing to request
        $this->actingAs($sara->user)
            ->post(route('employee.wfh.store'), ['from_date' => '2026-10-09', 'to_date' => '2026-10-09', 'reason' => 'Already my WFH day'])
            ->assertSessionHas('error', 'The selected dates do not include any working day that is not already a fixed Work From Home day.');
        $this->assertSame(0, DB::table('wfh_requests')->count());

        // Thu–Sat: Thursday and Saturday count (2 days), Friday does not, so the limit of 2 is fine
        $this->actingAs($sara->user)
            ->post(route('employee.wfh.store'), ['from_date' => '2026-10-08', 'to_date' => '2026-10-10', 'reason' => 'Family'])
            ->assertSessionHas('success');
        $this->assertSame(2, (int) DB::table('wfh_requests')->value('days'));
    }

    public function test_admin_saves_the_schedule_and_a_day_cannot_be_both_off_and_wfh(): void
    {
        $sara = $this->employee('GP-2', '2');

        $this->actingAs($this->admin)
            ->put(route('admin.employee.update', $sara->id), $this->updateData($sara) + ['off_days' => ['Sat'], 'wfh_days' => ['Mon', 'Fri']])
            ->assertSessionHas('success');
        $this->assertSame('Sat', $sara->fresh()->off_days);
        $this->assertSame('Mon,Fri', $sara->fresh()->wfh_days);
        $this->assertSame(['Mon', 'Fri'], $sara->fresh()->wfhDays());

        $this->actingAs($this->admin)
            ->put(route('admin.employee.update', $sara->id), $this->updateData($sara) + ['off_days' => ['Fri'], 'wfh_days' => ['Fri']])
            ->assertSessionHas('error', 'A day cannot be both an off day and a Work From Home day (Fri).');
        $this->assertSame('Sat', $sara->fresh()->off_days);

        // The policy weekend (Sunday) is never offered or saved
        $this->actingAs($this->admin)->get(route('admin.employee'))->assertOk()->assertDontSee('value="Sun"', false);
        $this->actingAs($this->admin)
            ->put(route('admin.employee.update', $sara->id), $this->updateData($sara) + ['off_days' => ['Sun', 'Sat']])
            ->assertSessionHas('success');
        $this->assertSame('Sat', $sara->fresh()->off_days);

        // Unticking everything clears both
        $this->actingAs($this->admin)->put(route('admin.employee.update', $sara->id), $this->updateData($sara))->assertSessionHas('success');
        $this->assertNull($sara->fresh()->off_days);
        $this->assertNull($sara->fresh()->wfh_days);

        $this->actingAs($sara->user)->get(route('employee.profile'))->assertOk()->assertDontSee('Weekly off');
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function employee(string $employeeId, string $uid, array $extra = []): Employee
    {
        $user = User::factory()->create(['role_id' => $this->employeeRoleId, 'status' => 'Active']);

        return Employee::create($extra + [
            'employee_id' => $employeeId, 'zkteco_uid' => $uid, 'user_id' => $user->id, 'role_id' => $this->employeeRoleId,
            'department_id' => $this->departmentId, 'first_name' => $employeeId === 'GP-1' ? 'Ali' : 'Sara', 'last_name' => 'Khan',
            'email' => $user->email, 'cnic' => $employeeId, 'employment_status' => 'active',
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
            'department_id' => $this->departmentId, 'job_title' => 'Developer', 'joining_date' => '2026-09-01', 'employment_status' => 'active',
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
            $table->boolean('wfh_enabled')->default(true);
            $table->integer('wfh_max_days_per_month')->default(0);
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
            $table->integer('days')->default(0);
            $table->text('reason')->nullable();
            $table->string('status');
            $table->unsignedBigInteger('manager_id')->nullable();
            $table->string('manager_status')->nullable();
            $table->text('manager_note')->nullable();
            $table->timestamp('manager_reviewed_at')->nullable();
            $table->text('admin_note')->nullable();
            $table->string('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
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
