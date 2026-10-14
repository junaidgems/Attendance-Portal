<?php

namespace Tests\Feature\Admin;

use App\Models\Employee;
use App\Models\MonthlyLock;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MarkAttendanceTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createAttendanceSchema();
        MonthlyLock::forgetCache();

        $roleId = DB::table('roles')->insertGetId(['name' => 'administrator']);
        $this->admin = User::factory()->create(['name' => 'Admin User', 'role_id' => $roleId, 'status' => 'active']);

        DB::table('attendance_settings')->insert([
            'standard_shift_start' => '09:00:00',
            'standard_shift_end' => '18:00:00',
            'late_arrival_grace_minutes' => 15,
            'early_departure_grace_minutes' => 0,
            'working_days' => 'Mon,Tue,Wed,Thu,Fri',
        ]);

        $this->employee = Employee::create([
            'employee_id' => 'EMP-001',
            'zkteco_uid' => '101',
            'first_name' => 'Ali',
            'last_name' => 'Khan',
            'employment_status' => 'active',
        ]);
    }

    protected function tearDown(): void
    {
        MonthlyLock::forgetCache();

        parent::tearDown();
    }

    public function test_present_day_is_saved_and_locked_against_machine_sync(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.attendance.mark'), [
            'employee_id' => $this->employee->id,
            'date' => '2026-10-05',
            'day_type' => 'present',
            'punch_in' => '09:30',
            'punch_out' => '18:00',
            'note' => 'Forgot to punch',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $record = DB::table('device_attendance')->where('uid', '101')->first();
        $this->assertSame('09:30:00', $record->check_in);
        $this->assertSame('18:00:00', $record->check_out);
        $this->assertSame('Late', $record->status);
        $this->assertSame(15, (int) $record->late_minutes);
        $this->assertSame('manual', $record->adjustment);
        $this->assertSame('Marked Present by Admin: Forgot to punch', $record->adjustment_note);
        $this->assertSame('Admin User', $record->adjusted_by);
    }

    public function test_absent_day_is_saved_without_punches(): void
    {
        $this->actingAs($this->admin)->post(route('admin.attendance.mark'), [
            'employee_id' => $this->employee->id,
            'date' => '2026-10-05',
            'day_type' => 'absent',
            'punch_in' => '09:00',
            'punch_out' => '18:00',
        ])->assertSessionHas('success');

        $record = DB::table('device_attendance')->where('uid', '101')->first();
        $this->assertNull($record->check_in);
        $this->assertNull($record->check_out);
        $this->assertSame('Absent', $record->status);

        $this->assertSame('Absent', $this->employee->fresh()->dayStatus('2026-10-05', $record, DB::table('attendance_settings')->first())['status']);
    }

    public function test_machine_punches_and_created_at_are_kept_when_a_day_is_overwritten(): void
    {
        DB::table('device_attendance')->insert([
            'uid' => '101',
            'name' => 'Ali Khan',
            'date' => '2026-10-05',
            'check_in' => '10:00:00',
            'check_out' => '16:00:00',
            'status' => 'Late',
            'created_at' => '2026-10-05 10:00:00',
            'updated_at' => '2026-10-05 10:00:00',
        ]);

        foreach (['09:00', '09:10'] as $punchIn) {
            $this->actingAs($this->admin)->post(route('admin.attendance.mark'), [
                'employee_id' => $this->employee->id,
                'date' => '2026-10-05',
                'day_type' => 'present',
                'punch_in' => $punchIn,
                'punch_out' => '18:00',
            ])->assertSessionHas('success');
        }

        $this->assertSame(1, DB::table('device_attendance')->count());

        $record = DB::table('device_attendance')->first();
        $this->assertSame('09:10:00', $record->check_in);
        $this->assertSame('On Time', $record->status);
        $this->assertSame('10:00:00', $record->original_check_in);
        $this->assertSame('16:00:00', $record->original_check_out);
        $this->assertSame('2026-10-05 10:00:00', $record->created_at);
    }

    public function test_wfh_day_is_shown_as_wfh(): void
    {
        $this->actingAs($this->admin)->post(route('admin.attendance.mark'), [
            'employee_id' => $this->employee->id,
            'date' => '2026-10-05',
            'day_type' => 'wfh',
            'punch_in' => '09:00',
            'punch_out' => '18:00',
        ])->assertSessionHas('success');

        $record = DB::table('device_attendance')->first();
        $this->assertSame('WFH', $record->status);
        $this->assertSame('Marked Work From Home by Admin', $record->adjustment_note);
    }

    public function test_audit_log_is_written(): void
    {
        $this->actingAs($this->admin)->post(route('admin.attendance.mark'), [
            'employee_id' => $this->employee->id,
            'date' => '2026-10-05',
            'day_type' => 'present',
            'punch_in' => '09:00',
            'punch_out' => '18:00',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'category' => 'attendance',
            'action' => 'Attendance marked manually',
            'detail' => 'Ali Khan — 2026-10-05 marked Present (In 09:00, Out 18:00)',
            'actor_name' => 'Admin User',
        ]);
    }

    public function test_locked_month_cannot_be_changed(): void
    {
        DB::table('monthly_locks')->insert(['month_year' => '2026-10', 'is_locked' => true]);

        $this->actingAs($this->admin)->post(route('admin.attendance.mark'), [
            'employee_id' => $this->employee->id,
            'date' => '2026-10-05',
            'day_type' => 'present',
            'punch_in' => '09:00',
            'punch_out' => '18:00',
        ])->assertSessionHas('error');

        $this->assertSame(0, DB::table('device_attendance')->count());
        $this->assertSame(0, DB::table('audit_logs')->count());
    }

    public function test_employee_without_device_uid_is_rejected(): void
    {
        $this->employee->update(['zkteco_uid' => null]);

        $this->actingAs($this->admin)->post(route('admin.attendance.mark'), [
            'employee_id' => $this->employee->id,
            'date' => '2026-10-05',
            'day_type' => 'present',
            'punch_in' => '09:00',
            'punch_out' => '18:00',
        ])->assertSessionHas('error');

        $this->assertSame(0, DB::table('device_attendance')->count());
    }

    public function test_punch_out_before_punch_in_is_rejected(): void
    {
        $this->actingAs($this->admin)->post(route('admin.attendance.mark'), [
            'employee_id' => $this->employee->id,
            'date' => '2026-10-05',
            'day_type' => 'present',
            'punch_in' => '18:00',
            'punch_out' => '09:00',
        ])->assertSessionHasErrors('punch_out');

        $this->assertSame(0, DB::table('device_attendance')->count());
    }

    public function test_invalid_times_and_missing_punch_in_are_rejected(): void
    {
        $this->actingAs($this->admin)->post(route('admin.attendance.mark'), [
            'employee_id' => $this->employee->id,
            'date' => '2026-10-05',
            'day_type' => 'present',
            'punch_in' => 'abc',
            'punch_out' => '25:99',
        ])->assertSessionHasErrors(['punch_in', 'punch_out']);

        $this->actingAs($this->admin)->post(route('admin.attendance.mark'), [
            'employee_id' => $this->employee->id,
            'date' => '2026-10-05',
            'day_type' => 'present',
            'punch_in' => '',
        ])->assertSessionHasErrors('punch_in');

        $this->assertSame(0, DB::table('device_attendance')->count());
    }

    public function test_punch_out_is_optional(): void
    {
        $this->actingAs($this->admin)->post(route('admin.attendance.mark'), [
            'employee_id' => $this->employee->id,
            'date' => '2026-10-05',
            'day_type' => 'present',
            'punch_in' => '09:00',
        ])->assertSessionHas('success');

        $this->assertNull(DB::table('device_attendance')->first()->check_out);
    }

    public function test_non_admin_cannot_mark_attendance(): void
    {
        $roleId = DB::table('roles')->insertGetId(['name' => 'employee']);
        $user = User::factory()->create(['role_id' => $roleId, 'status' => 'active']);

        $this->actingAs($user)->post(route('admin.attendance.mark'), [
            'employee_id' => $this->employee->id,
            'date' => '2026-10-05',
            'day_type' => 'present',
            'punch_in' => '09:00',
        ])->assertForbidden();

        $this->assertSame(0, DB::table('device_attendance')->count());
    }

    /**
     * The attendance tables have no migrations in this repository, so the columns used here are created for the test.
     */
    private function createAttendanceSchema(): void
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
            $table->unsignedBigInteger('company_id')->nullable();
            $table->string('employee_id')->nullable();
            $table->string('zkteco_uid')->nullable();
            $table->unsignedBigInteger('shift_id')->nullable();
            $table->unsignedBigInteger('grace_policy_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('first_name');
            $table->string('last_name')->nullable();
            $table->unsignedBigInteger('department_id')->nullable();
            $table->unsignedBigInteger('role_id')->nullable();
            $table->unsignedBigInteger('reports_to_id')->nullable();
            $table->date('joining_date')->nullable();
            $table->string('employment_status')->nullable();
            $table->timestamps();
            $table->softDeletes();
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
            $table->string('adjustment')->nullable();
            $table->string('adjustment_note')->nullable();
            $table->string('adjusted_by')->nullable();
            $table->timestamp('adjusted_at')->nullable();
            $table->time('original_check_in')->nullable();
            $table->time('original_check_out')->nullable();
            $table->timestamps();
        });

        Schema::create('attendance_settings', function (Blueprint $table) {
            $table->id();
            $table->time('standard_shift_start')->nullable();
            $table->time('standard_shift_end')->nullable();
            $table->integer('late_arrival_grace_minutes')->nullable();
            $table->integer('early_departure_grace_minutes')->nullable();
            $table->string('working_days')->nullable();
        });

        Schema::create('monthly_locks', function (Blueprint $table) {
            $table->id();
            $table->string('month_year');
            $table->boolean('is_locked')->default(false);
            $table->timestamp('locked_at')->nullable();
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
