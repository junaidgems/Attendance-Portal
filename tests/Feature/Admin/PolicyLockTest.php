<?php

namespace Tests\Feature\Admin;

use App\Models\AttendancePolicyLock;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * The Attendance Policy page is locked for Admin until the Director unlocks it.
 */
class PolicyLockTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $directorUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createSchema();
        AttendancePolicyLock::forgetCache();

        DB::table('attendance_settings')->insert(['id' => 1, 'cycle_start_day' => 26, 'cycle_end_day' => 25]);
        $adminRole = DB::table('roles')->insertGetId(['name' => 'administrator', 'display_name' => 'Administrator']);
        $directorRole = DB::table('roles')->insertGetId(['name' => 'director', 'display_name' => 'Director']);

        $this->admin = User::factory()->create(['name' => 'Admin User', 'role_id' => $adminRole, 'status' => 'Active']);
        $this->directorUser = User::factory()->create(['name' => 'Dana Director', 'role_id' => $directorRole, 'status' => 'Active']);
        Employee::create(['employee_id' => 'GP-1', 'user_id' => $this->directorUser->id, 'role_id' => $directorRole, 'first_name' => 'Dana', 'last_name' => 'Director', 'employment_status' => 'active']);
    }

    protected function tearDown(): void
    {
        AttendancePolicyLock::forgetCache();

        parent::tearDown();
    }

    public function test_the_policy_page_is_locked_until_the_director_unlocks_it(): void
    {
        $this->actingAs($this->admin)->get(route('admin.attendance.policy'))
            ->assertOk()
            ->assertSee('Attendance Policy is locked')
            ->assertSee('Request unlock from Director');

        $this->actingAs($this->admin)
            ->post(route('admin.attendance-policy.settings'), $this->policy())
            ->assertRedirect(route('admin.attendance.policy'))
            ->assertSessionHas('error', 'The Attendance Policy is locked. Ask the Director to unlock it first.');
        $this->assertSame(26, (int) DB::table('attendance_settings')->value('cycle_start_day'));

        // Holidays, shifts, grace policies and the month lock are locked too
        $this->actingAs($this->admin)
            ->post(route('admin.holiday.store'), ['name' => 'Eid', 'from_date' => '2026-10-20', 'to_date' => '2026-10-20', 'type' => 'Gazetted'])
            ->assertSessionHas('error');
        $this->assertSame(0, DB::table('holidays')->count());
    }

    public function test_admin_requests_an_unlock_and_the_director_unlocks_for_an_hour(): void
    {
        $this->travelTo('2026-10-10 10:00:00');

        $this->actingAs($this->admin)->post(route('admin.attendance-policy.request-unlock'))
            ->assertRedirect(route('admin.attendance.policy'))
            ->assertSessionHas('success');
        $this->assertSame('Admin User', DB::table('attendance_settings')->value('policy_unlock_requested_by'));
        $this->assertDatabaseHas('portal_notifications', ['user_id' => $this->directorUser->id, 'title' => 'Attendance Policy unlock requested']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'Policy unlock requested']);

        $this->actingAs($this->directorUser)->post(route('director.policy.unlock'))
            ->assertSessionHas('success', 'Attendance Policy unlocked for Admin until 11:00 AM.');
        $this->assertSame('2026-10-10 11:00:00', DB::table('attendance_settings')->value('policy_unlocked_until'));
        $this->assertSame('Dana Director', DB::table('attendance_settings')->value('policy_unlocked_by'));
        $this->assertNull(DB::table('attendance_settings')->value('policy_unlock_requested_at'));
        $this->assertDatabaseHas('portal_notifications', ['user_id' => $this->admin->id, 'title' => 'Attendance Policy unlocked']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'Policy unlocked by Director']);

        // Admin can now save
        $this->actingAs($this->admin)->get(route('admin.attendance.policy'))->assertOk()->assertSee('Unlocked by Dana Director');
        $this->actingAs($this->admin)
            ->post(route('admin.attendance-policy.settings'), $this->policy(['cycle_start_day' => 1, 'cycle_end_day' => 31]))
            ->assertSessionHas('success');
        $this->assertSame(1, (int) DB::table('attendance_settings')->value('cycle_start_day'));

        // An hour later the page is locked again by itself
        $this->travelTo('2026-10-10 11:00:01');
        $this->actingAs($this->admin)
            ->post(route('admin.attendance-policy.settings'), $this->policy(['cycle_start_day' => 5, 'cycle_end_day' => 4]))
            ->assertSessionHas('error');
        $this->assertSame(1, (int) DB::table('attendance_settings')->value('cycle_start_day'));
    }

    public function test_the_director_can_lock_the_page_again_early(): void
    {
        $this->actingAs($this->directorUser)->post(route('director.policy.unlock'));
        $this->assertTrue(AttendancePolicyLock::isUnlocked());

        $this->actingAs($this->directorUser)->post(route('director.policy.lock'))->assertSessionHas('success', 'Attendance Policy locked.');

        $this->assertFalse(AttendancePolicyLock::isUnlocked());
        $this->assertDatabaseHas('audit_logs', ['action' => 'Policy locked by Director']);
        $this->actingAs($this->admin)->post(route('admin.attendance-policy.settings'), $this->policy())->assertSessionHas('error');
    }

    public function test_only_the_director_can_unlock(): void
    {
        $this->actingAs($this->admin)->post(route('director.policy.unlock'))->assertForbidden();

        $this->assertFalse(AttendancePolicyLock::isUnlocked());
    }

    public function test_page_stays_open_before_the_lock_columns_are_added(): void
    {
        Schema::table('attendance_settings', fn (Blueprint $table) => $table->dropColumn(['policy_unlocked_until', 'policy_unlocked_by', 'policy_unlock_requested_at', 'policy_unlock_requested_by']));
        AttendancePolicyLock::forgetCache();

        $this->actingAs($this->admin)->get(route('admin.attendance.policy'))->assertOk()->assertDontSee('Attendance Policy is locked');
        $this->actingAs($this->admin)
            ->post(route('admin.attendance-policy.settings'), $this->policy(['cycle_start_day' => 1, 'cycle_end_day' => 31]))
            ->assertSessionHas('success');
        $this->assertSame(1, (int) DB::table('attendance_settings')->value('cycle_start_day'));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function policy(array $overrides = []): array
    {
        return $overrides + [
            'cycle_start_day' => 26, 'cycle_end_day' => 25,
            'standard_shift_start' => '09:00', 'standard_shift_end' => '18:00',
            'unpaid_break_minutes' => 0, 'late_arrival_grace_minutes' => 15, 'early_departure_grace_minutes' => 0,
            'half_day_max_minutes' => 240, 'full_day_min_minutes' => 480, 'working_days' => 'Mon,Tue,Wed,Thu,Fri',
            'biometric_sync_time' => '23:00',
            'late_arrivals_per_casual_leave' => 3, 'half_days_per_casual_leave' => 2, 'early_outs_per_casual_leave' => 3,
            'late_alert_threshold' => 3, 'early_out_alert_threshold' => 3,
            'missing_punch_correction_days' => 2, 'missing_punch_after_deadline' => 'flag',
            'wfh_max_days_per_month' => 0,
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
            $table->string('employee_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('role_id')->nullable();
            $table->unsignedBigInteger('department_id')->nullable();
            $table->unsignedBigInteger('grace_policy_id')->nullable();
            $table->string('first_name');
            $table->string('last_name')->nullable();
            $table->string('employment_status')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('attendance_settings', function (Blueprint $table) {
            $table->id();
            foreach (['cycle_start_day', 'cycle_end_day', 'unpaid_break_minutes', 'late_arrival_grace_minutes', 'early_departure_grace_minutes',
                'half_day_max_minutes', 'full_day_min_minutes', 'late_arrivals_per_casual_leave', 'half_days_per_casual_leave',
                'early_outs_per_casual_leave', 'late_alert_threshold', 'early_out_alert_threshold', 'missing_punch_correction_days',
                'wfh_max_days_per_month', 'absent_days_per_casual_leave', 'half_day_after_late_minutes'] as $column) {
                $table->integer($column)->nullable();
            }
            foreach (['biometric_auto_sync_enabled', 'penalty_policy_enabled', 'attendance_alerts_enabled', 'alert_notify_admin',
                'alert_notify_line_manager', 'alert_notify_employee', 'wfh_enabled', 'email_notifications_enabled',
                'missing_punch_enabled', 'missing_punch_notify_manager', 'missing_punch_notify_admin'] as $column) {
                $table->boolean($column)->default(false);
            }
            $table->string('standard_shift_start')->nullable();
            $table->string('standard_shift_end')->nullable();
            $table->string('working_days')->nullable();
            $table->string('biometric_sync_time')->nullable();
            $table->string('missing_punch_after_deadline')->nullable();
            $table->date('missing_punch_policy_from')->nullable();
            $table->string('leave_year_start', 5)->default('06-26');
            $table->date('penalty_policy_from')->nullable();
            $table->timestamp('policy_unlock_requested_at')->nullable();
            $table->string('policy_unlock_requested_by')->nullable();
            $table->timestamp('policy_unlocked_until')->nullable();
            $table->string('policy_unlocked_by')->nullable();
            $table->timestamps();
        });
        Schema::create('holidays', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->date('from_date');
            $table->date('to_date')->nullable();
            $table->string('type')->nullable();
            $table->timestamps();
        });
        foreach (['shifts', 'grace_policies', 'departments'] as $name) {
            Schema::create($name, function (Blueprint $table) {
                $table->id();
                $table->string('name')->nullable();
                $table->boolean('is_default')->default(false);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }
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
