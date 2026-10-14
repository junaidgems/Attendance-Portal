<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LeaveYearSettingTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('role_id')->nullable();
            $table->string('status')->nullable();
        });
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });
        Schema::create('attendance_settings', function (Blueprint $table) {
            $table->id();
            foreach (['cycle_start_day', 'cycle_end_day', 'unpaid_break_minutes', 'late_arrival_grace_minutes', 'early_departure_grace_minutes',
                'half_day_max_minutes', 'full_day_min_minutes', 'late_arrivals_per_casual_leave', 'half_days_per_casual_leave',
                'early_outs_per_casual_leave', 'late_alert_threshold', 'early_out_alert_threshold', 'missing_punch_correction_days',
                'wfh_max_days_per_month'] as $column) {
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
            $table->string('leave_year_start', 5)->default('06-26');
            $table->date('penalty_policy_from')->nullable();
            $table->integer('half_day_after_late_minutes')->nullable();
            $table->integer('absent_days_per_casual_leave')->nullable();
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

        DB::table('attendance_settings')->insert(['id' => 1]);
        $roleId = DB::table('roles')->insertGetId(['name' => 'administrator']);
        $this->admin = User::factory()->create(['role_id' => $roleId, 'status' => 'Active']);
    }

    public function test_admin_can_set_the_leave_year_start(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.attendance-policy.settings'), $this->policy(['leave_year_start_day' => 1, 'leave_year_start_month' => 7]))
            ->assertSessionHas('success');

        $this->assertSame('07-01', DB::table('attendance_settings')->value('leave_year_start'));
    }

    public function test_admin_can_set_and_clear_the_count_penalties_from_date(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.attendance-policy.settings'), $this->policy(['penalty_policy_from' => '2026-10-09']))
            ->assertSessionHas('success');
        $this->assertSame('2026-10-09', DB::table('attendance_settings')->value('penalty_policy_from'));

        $this->actingAs($this->admin)
            ->post(route('admin.attendance-policy.settings'), $this->policy(['penalty_policy_from' => '']))
            ->assertSessionHas('success');
        $this->assertNull(DB::table('attendance_settings')->value('penalty_policy_from'));
    }

    public function test_admin_can_set_and_clear_the_half_day_late_arrival_minutes(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.attendance-policy.settings'), $this->policy(['half_day_after_late_minutes' => 120]))
            ->assertSessionHas('success');
        $this->assertSame(120, (int) DB::table('attendance_settings')->value('half_day_after_late_minutes'));

        $this->actingAs($this->admin)
            ->post(route('admin.attendance-policy.settings'), $this->policy(['half_day_after_late_minutes' => '']))
            ->assertSessionHas('success');
        $this->assertNull(DB::table('attendance_settings')->value('half_day_after_late_minutes'));
    }

    public function test_admin_can_set_and_clear_the_absent_days_per_casual_leave(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.attendance-policy.settings'), $this->policy(['absent_days_per_casual_leave' => 1]))
            ->assertSessionHas('success');
        $this->assertSame(1, (int) DB::table('attendance_settings')->value('absent_days_per_casual_leave'));

        $this->actingAs($this->admin)
            ->post(route('admin.attendance-policy.settings'), $this->policy(['absent_days_per_casual_leave' => '']))
            ->assertSessionHas('success');
        $this->assertNull(DB::table('attendance_settings')->value('absent_days_per_casual_leave'));
    }

    public function test_an_impossible_date_is_rejected(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.attendance-policy.settings'), $this->policy(['leave_year_start_day' => 30, 'leave_year_start_month' => 2]))
            ->assertSessionHas('error', 'The leave year start date is not a valid day of that month.');

        $this->assertSame('06-26', DB::table('attendance_settings')->value('leave_year_start'));
    }

    public function test_settings_still_save_before_the_column_is_added(): void
    {
        Schema::table('attendance_settings', fn (Blueprint $table) => $table->dropColumn('leave_year_start'));

        $this->actingAs($this->admin)
            ->post(route('admin.attendance-policy.settings'), $this->policy(['leave_year_start_day' => 1, 'leave_year_start_month' => 7]))
            ->assertSessionHas('success');

        $this->assertSame(26, (int) DB::table('attendance_settings')->value('cycle_start_day'));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function policy(array $overrides): array
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
}
