<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AttendancePolicyLock;
use App\Models\AuditLog;
use App\Models\Department;
use App\Models\Employee;
use App\Models\GracePolicy;
use App\Models\MonthlyLock;
use App\Models\PortalNotification;
use App\Models\Shift;
use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AttendancePolicyController extends Controller implements HasMiddleware
{
    /**
     * Every change on the policy page needs the Director's unlock (see AttendancePolicyLock).
     */
    public static function middleware(): array
    {
        return [
            (new Middleware(function (Request $request, Closure $next) {
                AttendancePolicyLock::ensureUnlocked();

                return $next($request);
            }))->except(['index', 'syncNow', 'requestUnlock']),
        ];
    }

    /**
     * Admin asks the Director to unlock the page.
     */
    public function requestUnlock()
    {
        if (! AttendancePolicyLock::isSupported()) {
            return redirect()->route('admin.attendance.policy')->with('error', 'The policy lock is not set up yet (database columns missing).');
        }

        if (AttendancePolicyLock::isUnlocked()) {
            return redirect()->route('admin.attendance.policy')->with('success', 'The Attendance Policy is already unlocked.');
        }

        AttendancePolicyLock::request(Auth::user()->name ?? 'Admin');

        return redirect()->route('admin.attendance.policy')->with('success', 'Unlock request sent to the Director. You will be notified when the page is unlocked.');
    }

    public function syncNow()
    {
        try {
            Artisan::call('device:sync-attendance');
            $output = Artisan::output();

            return redirect()->back()->with('success', 'Biometric sync complete! '.trim($output));
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', 'Sync failed: '.$e->getMessage());
        }
    }

    public function index()
    {
        $settings = DB::table('attendance_settings')->first();
        $holidays = DB::table('holidays')->orderBy('from_date', 'desc')->get();
        $gracePolicies = GracePolicy::withCount('employees')->orderBy('is_default', 'desc')->get();
        $monthlyLocks = DB::table('monthly_locks')->orderBy('month_year', 'desc')->get();
        $shifts = Shift::orderBy('name')->get();
        $departments = Department::withCount('employees')->orderBy('name')->get();
        $policyLock = AttendancePolicyLock::status();

        return view('attendence.pages.admin.attendance-policy', compact(
            'settings',
            'holidays',
            'gracePolicies',
            'monthlyLocks',
            'shifts',
            'departments',
            'policyLock'
        ));
    }

    public function updateSettings(Request $request)
    {
        $request->validate([
            'cycle_start_day' => 'required|integer|min:1|max:31',
            'cycle_end_day' => 'required|integer|min:1|max:31',
            'standard_shift_start' => 'required',
            'standard_shift_end' => 'required',
            'unpaid_break_minutes' => 'required|integer|min:0',
            'late_arrival_grace_minutes' => 'required|integer|min:0',
            'early_departure_grace_minutes' => 'required|integer|min:0',
            'half_day_max_minutes' => 'required|integer|min:0',
            'half_day_after_late_minutes' => 'nullable|integer|min:0|max:720',
            'full_day_min_minutes' => 'required|integer|min:0',
            'working_days' => 'required|string',
            'biometric_sync_time' => 'required',
            'biometric_auto_sync_enabled' => 'nullable|boolean',
            'penalty_policy_enabled' => 'nullable|boolean',
            'late_arrivals_per_casual_leave' => 'required|integer|min:1',
            'half_days_per_casual_leave' => 'required|integer|min:1',
            'early_outs_per_casual_leave' => 'required|integer|min:1',
            'absent_days_per_casual_leave' => 'nullable|integer|min:0|max:31',
            'attendance_alerts_enabled' => 'nullable|boolean',
            'late_alert_threshold' => 'required|integer|min:1',
            'early_out_alert_threshold' => 'required|integer|min:1',
            'alert_notify_admin' => 'nullable|boolean',
            'alert_notify_line_manager' => 'nullable|boolean',
            'alert_notify_employee' => 'nullable|boolean',
            'wfh_enabled' => 'nullable|boolean',
            'email_notifications_enabled' => 'nullable|boolean',
            'missing_punch_enabled' => 'nullable|boolean',
            'missing_punch_correction_days' => 'required|integer|min:0|max:31',
            'missing_punch_after_deadline' => 'required|in:half_day,absent,flag',
            'missing_punch_notify_manager' => 'nullable|boolean',
            'missing_punch_notify_admin' => 'nullable|boolean',
            'wfh_max_days_per_month' => 'required|integer|min:0|max:31',
            'leave_year_start_month' => 'nullable|integer|min:1|max:12',
            'leave_year_start_day' => 'nullable|integer|min:1|max:31',
            'penalty_policy_from' => 'nullable|date',
        ]);

        // Leave year start, e.g. "06-26" (26 June); only saved once the leave_year_start column exists
        $optionalSettings = [];
        if ($request->filled('leave_year_start_month') && $request->filled('leave_year_start_day')) {
            if (! checkdate((int) $request->leave_year_start_month, (int) $request->leave_year_start_day, 2024)) {
                return redirect()->back()->withInput()->with('error', 'The leave year start date is not a valid day of that month.');
            }

            if (Schema::hasColumn('attendance_settings', 'leave_year_start')) {
                $optionalSettings = ['leave_year_start' => sprintf('%02d-%02d', $request->leave_year_start_month, $request->leave_year_start_day)];
            }
        }

        // First day counted for penalties (empty = every day); only saved once the penalty_policy_from column exists
        if (Schema::hasColumn('attendance_settings', 'penalty_policy_from')) {
            $optionalSettings['penalty_policy_from'] = $request->filled('penalty_policy_from')
                ? Carbon::parse($request->penalty_policy_from)->toDateString()
                : null;
        }

        // Check-in later than this many minutes after the shift start = half day (empty = off); only once the column exists
        if (Schema::hasColumn('attendance_settings', 'half_day_after_late_minutes')) {
            $optionalSettings['half_day_after_late_minutes'] = $request->filled('half_day_after_late_minutes')
                ? (int) $request->half_day_after_late_minutes
                : null;
        }

        // Absent days per 1 Casual Leave (empty / 0 = absent days are not deducted); only once the column exists
        if (Schema::hasColumn('attendance_settings', 'absent_days_per_casual_leave')) {
            $optionalSettings['absent_days_per_casual_leave'] = $request->filled('absent_days_per_casual_leave')
                ? (int) $request->absent_days_per_casual_leave
                : null;
        }

        DB::table('attendance_settings')->where('id', 1)->update($optionalSettings + [
            'cycle_start_day' => $request->cycle_start_day,
            'cycle_end_day' => $request->cycle_end_day,
            'standard_shift_start' => $request->standard_shift_start,
            'standard_shift_end' => $request->standard_shift_end,
            'unpaid_break_minutes' => $request->unpaid_break_minutes,
            'late_arrival_grace_minutes' => $request->late_arrival_grace_minutes,
            'early_departure_grace_minutes' => $request->early_departure_grace_minutes,
            'half_day_max_minutes' => $request->half_day_max_minutes,
            'full_day_min_minutes' => $request->full_day_min_minutes,
            'working_days' => $request->working_days,
            'biometric_sync_time' => $request->biometric_sync_time,
            'biometric_auto_sync_enabled' => $request->boolean('biometric_auto_sync_enabled'),
            'penalty_policy_enabled' => $request->boolean('penalty_policy_enabled'),
            'late_arrivals_per_casual_leave' => $request->late_arrivals_per_casual_leave,
            'half_days_per_casual_leave' => $request->half_days_per_casual_leave,
            'early_outs_per_casual_leave' => $request->early_outs_per_casual_leave,
            'attendance_alerts_enabled' => $request->boolean('attendance_alerts_enabled'),
            'late_alert_threshold' => $request->late_alert_threshold,
            'early_out_alert_threshold' => $request->early_out_alert_threshold,
            'alert_notify_admin' => $request->boolean('alert_notify_admin'),
            'alert_notify_line_manager' => $request->boolean('alert_notify_line_manager'),
            'alert_notify_employee' => $request->boolean('alert_notify_employee'),
            'wfh_enabled' => $request->boolean('wfh_enabled'),
            'email_notifications_enabled' => $request->boolean('email_notifications_enabled'),
            'missing_punch_enabled' => $request->boolean('missing_punch_enabled'),
            'missing_punch_correction_days' => $request->missing_punch_correction_days,
            'missing_punch_after_deadline' => $request->missing_punch_after_deadline,
            'missing_punch_notify_manager' => $request->boolean('missing_punch_notify_manager'),
            'missing_punch_notify_admin' => $request->boolean('missing_punch_notify_admin'),
            'wfh_max_days_per_month' => $request->wfh_max_days_per_month,
            'updated_at' => now(),
        ]);

        AuditLog::record(
            'cycle',
            'Attendance cycle updated',
            $request->cycle_start_day.' to '.$request->cycle_end_day.' (working hours & grace updated)'
        );

        return redirect()->back()->with('success', 'Policy saved successfully!');
    }

    public function storeHoliday(Request $request)
    {
        $request->validate([
            'from_date' => 'required|date',
            'to_date' => 'nullable|date|after_or_equal:from_date',
            'name' => 'required|string|max:150',
            'type' => 'required|in:Gazetted,Unscheduled',
        ]);

        $holidayId = DB::table('holidays')->insertGetId([
            'from_date' => $request->from_date,
            'to_date' => $request->to_date ?: null,
            'name' => $request->name,
            'type' => $request->type,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        AuditLog::record(
            'holiday',
            'Holiday added',
            $request->name.' · '.$request->from_date.' → '.($request->to_date ?: $request->from_date).' · '.strtolower($request->type)
        );

        // Tell everyone (bell + email)
        $from = Carbon::parse($request->from_date);
        $to = Carbon::parse($request->to_date ?: $request->from_date);
        $dates = $from->isSameDay($to) ? $from->format('l, d M Y') : $from->format('d M').' – '.$to->format('d M Y');
        $userIds = Employee::where('employment_status', 'active')->whereNotNull('user_id')->pluck('user_id')
            ->merge(PortalNotification::adminUserIds());

        $sent = PortalNotification::sendToUsers($userIds, 'holiday', "Holiday: {$request->name}",
            "{$dates} is a holiday ({$request->name}). The office will be closed.",
            route('dashboard'), "holiday:{$holidayId}");

        return redirect()->back()->with('success', "Holiday added and {$sent} people notified!");
    }

    public function destroyHoliday($id)
    {
        DB::table('holidays')->where('id', $id)->delete();

        return redirect()->back()->with('success', 'Holiday removed!');
    }

    public function storeDepartment(Request $request)
    {
        $request->validate([
            'department_name' => 'required|string|max:100|unique:departments,name',
        ], [
            'department_name.unique' => 'This department already exists.',
        ]);

        $department = Department::create(['name' => trim($request->department_name)]);

        AuditLog::record('department', 'Department added', $department->name);

        return redirect()->back()->with('success', "Department \"{$department->name}\" added!");
    }

    /**
     * A department that still has employees cannot be removed: move them to another department first.
     */
    public function destroyDepartment($id)
    {
        $department = Department::withCount('employees')->findOrFail($id);

        if ($department->employees_count > 0) {
            return redirect()->back()->with('error', "\"{$department->name}\" has {$department->employees_count} employee(s). Move them to another department before removing it.");
        }

        $department->delete();

        AuditLog::record('department', 'Department removed', $department->name);

        return redirect()->back()->with('success', "Department \"{$department->name}\" removed!");
    }

    public function storeGracePolicy(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:100',
            'late_grace_minutes' => 'required|integer|min:0',
            'early_grace_minutes' => 'required|integer|min:0',
        ]);

        GracePolicy::create($request->only('name', 'late_grace_minutes', 'early_grace_minutes'));

        return redirect()->back()->with('success', 'Grace policy added successfully!');
    }

    public function updateGracePolicy(Request $request, $id)
    {
        $request->validate([
            'name' => 'required|string|max:100',
            'late_grace_minutes' => 'required|integer|min:0',
            'early_grace_minutes' => 'required|integer|min:0',
        ]);

        GracePolicy::findOrFail($id)->update($request->only('name', 'late_grace_minutes', 'early_grace_minutes'));

        return redirect()->back()->with('success', 'Grace policy updated successfully!');
    }

    public function toggleLock(Request $request)
    {
        $request->validate(['month_year' => 'required|date_format:Y-m']);

        $existing = DB::table('monthly_locks')->where('month_year', $request->month_year)->first();

        if ($existing) {
            $newStatus = ! $existing->is_locked;

            DB::table('monthly_locks')->where('month_year', $request->month_year)->update([
                'is_locked' => $newStatus,
                'locked_at' => $newStatus ? now() : null,
                'updated_at' => now(),
            ]);

            AuditLog::record(
                'lock',
                $newStatus ? 'Period locked' : 'Period unlocked',
                $request->month_year
            );
        } else {
            DB::table('monthly_locks')->insert([
                'month_year' => $request->month_year,
                'is_locked' => 1,
                'locked_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            AuditLog::record('lock', 'Period locked', $request->month_year);
        }

        MonthlyLock::forgetCache();

        return redirect()->back()->with('success', 'Month lock status updated!');
    }

    public function storeShift(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:100',
            'start_time' => 'required',
            'end_time' => 'required',
            // Saturday timings are optional; both or neither
            'saturday_start_time' => 'nullable|required_with:saturday_end_time',
            'saturday_end_time' => 'nullable|required_with:saturday_start_time',
        ]);

        Shift::create([
            'name' => $request->name,
            'start_time' => $request->start_time,
            'end_time' => $request->end_time,
            'saturday_start_time' => $request->saturday_start_time ?: null,
            'saturday_end_time' => $request->saturday_end_time ?: null,
            'is_active' => 1,
        ]);

        return redirect()->back()->with('success', 'Shift added successfully!');
    }

    public function updateShift(Request $request, $id)
    {
        $request->validate([
            'name' => 'required|string|max:100',
            'start_time' => 'required',
            'end_time' => 'required',
            // Saturday timings are optional; both or neither
            'saturday_start_time' => 'nullable|required_with:saturday_end_time',
            'saturday_end_time' => 'nullable|required_with:saturday_start_time',
        ]);

        Shift::findOrFail($id)->update([
            'name' => $request->name,
            'start_time' => $request->start_time,
            'end_time' => $request->end_time,
            'saturday_start_time' => $request->saturday_start_time ?: null,
            'saturday_end_time' => $request->saturday_end_time ?: null,
        ]);

        return redirect()->back()->with('success', 'Shift updated successfully!');
    }

    public function toggleShift($id)
    {
        $shift = Shift::findOrFail($id);
        $shift->update(['is_active' => ! $shift->is_active]);

        return redirect()->back()->with('success', 'Shift status updated!');
    }
}
