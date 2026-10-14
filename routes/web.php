<?php

use App\Console\Commands\SyncDeviceAttendance;
use App\Http\Controllers\Admin\ApprovalController;
use App\Http\Controllers\Admin\AttendanceAuditController;
use App\Http\Controllers\Admin\AttendanceBreakdownController;
use App\Http\Controllers\Admin\AttendanceController;
use App\Http\Controllers\Admin\AttendancePolicyController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\BiometricSyncController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\ImpersonationController;
use App\Http\Controllers\Admin\LeaveController;
use App\Http\Controllers\Admin\PenaltyController;
use App\Http\Controllers\Admin\ProfileController as AdminProfileController;
use App\Http\Controllers\Admin\WfhController as AdminWfhController;
use App\Http\Controllers\Auth\logincontroller;
use App\Http\Controllers\Director\ApprovalController as DirectorApprovalController;
use App\Http\Controllers\Director\AttendanceBreakdownController as DirectorAttendanceBreakdownController;
use App\Http\Controllers\Director\AttendancePatternController as DirectorAttendancePatternController;
use App\Http\Controllers\Director\DashboardController as DirectorDashboardController;
use App\Http\Controllers\Director\EmployeeController as DirectorEmployeeController;
use App\Http\Controllers\Director\ReportController as DirectorReportController;
use App\Http\Controllers\Director\TeamAttendanceController as DirectorTeamAttendanceController;
use App\Http\Controllers\Employee\CorrectionController;
use App\Http\Controllers\Employee\empLeaveController;
use App\Http\Controllers\Employee\EmployeeDashboardController;
use App\Http\Controllers\Employee\PenaltyControllers;
use App\Http\Controllers\Employee\WfhController as EmployeeWfhController;
use App\Http\Controllers\employeecontrollers;
use App\Http\Controllers\LeaveDocumentController;
use App\Http\Controllers\LineManager\ApprovalController as LineManagerApprovalController;
use App\Http\Controllers\LineManager\AttendanceBreakdownController as LineManagerAttendanceBreakdownController;
use App\Http\Controllers\LineManager\AttendancePatternController as LineManagerAttendancePatternController;
use App\Http\Controllers\LineManager\CorrectionController as LineManagerCorrectionController;
use App\Http\Controllers\LineManager\DashboardController as LineManagerDashboardController;
use App\Http\Controllers\LineManager\LeaveController as LineManagerLeaveController;
use App\Http\Controllers\LineManager\Leavereportcontroller as LineManagerLeaveReportController;
use App\Http\Controllers\LineManager\PenaltyController as LineManagerPenaltyController;
use App\Http\Controllers\LineManager\TeamAttendanceController as LineManagerTeamAttendanceController;
use App\Http\Controllers\LineManager\WfhController as LineManagerWfhController;
use App\Http\Controllers\Manager\ApprovalController as ManagerApprovalController;
use App\Http\Controllers\Manager\AttendanceBreakdownController as ManagerAttendanceBreakdownController;
use App\Http\Controllers\Manager\AttendancePatternController as ManagerAttendancePatternController;
use App\Http\Controllers\Manager\CorrectionController as ManagerCorrectionController;
use App\Http\Controllers\Manager\DashboardController as ManagerDashboardController;
use App\Http\Controllers\Manager\LeaveController as ManagerLeaveController;
use App\Http\Controllers\Manager\Leavereportcontroller as ManagerLeaveReportController;
use App\Http\Controllers\Manager\PenaltyController as ManagerPenaltyController;
use App\Http\Controllers\Manager\TeamAttendanceController as ManagerTeamAttendanceController;
use App\Http\Controllers\Manager\WfhController as ManagerWfhController;
use App\Http\Controllers\NotificationController;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use ZkTeco\Laravel\Facades\ZkTeco;

Route::get('/optimize-clear', function () {
    Artisan::call('optimize:clear');

    return response('<pre>'.e(Artisan::output()).'</pre>');
})->name('optimize.clear');

Route::middleware(['auth', 'role:administrator'])->get('/zkteco-test', function () {
    try {
        $users = ZkTeco::connection()->session(
            fn ($device) => $device->users()->all()
        );

        return response()->json([
            'success' => true,
            'users' => $users,
        ]);

    } catch (Throwable $e) {
        return response()->json([
            'success' => false,
            'message' => $e->getMessage(),
        ], 500);
    }
});
Route::middleware(['auth', 'role:administrator'])->get('/zkteco-report', function () {
    try {
        // Step 1: Users list fetch karo (naam ke liye)
        $users = ZkTeco::connection()->session(
            fn ($device) => $device->users()->all()
        );

        $userNames = [];
        foreach ($users as $user) {
            $userNames[$user->userId] = $user->name ?? ('User '.$user->userId);
        }

        // Step 2: Attendance records fetch karo
        $attendance = ZkTeco::connection()->session(
            fn ($device) => $device->attendance()->all()
        );

        $officeStartTime = '10:00:00';
        $lateThresholdMinutes = 15;

        // First punch of the day = check-in, last punch = check-out (the device's In/Out state is not reliable)
        $grouped = [];
        foreach (SyncDeviceAttendance::dailyPunches($attendance) as $key => $day) {
            $grouped[$key] = [
                'user_id' => $day['uid'],
                'name' => $userNames[$day['uid']] ?? ('User '.$day['uid']),
                'date' => $day['date'],
                'check_in' => $day['check_in'],
                'check_out' => $day['check_out'],
            ];
        }

        $report = [];
        foreach ($grouped as $entry) {
            $status = 'On Time';
            if ($entry['check_in']) {
                $expectedTime = strtotime($officeStartTime) + ($lateThresholdMinutes * 60);
                if (strtotime($entry['check_in']) > $expectedTime) {
                    $status = 'Late';
                }
            }
            $report[] = [
                'user_id' => $entry['user_id'],
                'name' => $entry['name'],
                'date' => $entry['date'],
                'check_in' => $entry['check_in'],
                'check_out' => $entry['check_out'],
                'status' => $status,
            ];
        }

        usort($report, fn ($a, $b) => strcmp($b['date'], $a['date']) ?: strcmp($a['user_id'], $b['user_id']));

        return view('zkteco-report', ['report' => $report]);

    } catch (Throwable $e) {
        return response()->json([
            'success' => false,
            'message' => $e->getMessage(),
        ], 500);
    }
});
Route::middleware(['auth', 'role:administrator'])->get('/zkteco-debug', function () {
    try {
        $attendance = ZkTeco::connection()->session(
            fn ($device) => $device->attendance()->all()
        );

        $first = $attendance[0] ?? null;

        if (! $first) {
            return response()->json(['success' => true, 'message' => 'No records found']);
        }

        $vars = get_object_vars($first);
        $safeVars = [];

        foreach ($vars as $key => $value) {
            if (is_object($value)) {
                // Enum ya koi aur object ho to uska naam/value nikal lo
                if ($value instanceof UnitEnum) {
                    $safeVars[$key] = $value->name;
                } elseif ($value instanceof BackedEnum) {
                    $safeVars[$key] = $value->value;
                } elseif (method_exists($value, '__toString')) {
                    $safeVars[$key] = (string) $value;
                } else {
                    $safeVars[$key] = get_class($value);
                }
            } else {
                $safeVars[$key] = $value;
            }
        }

        return response()->json([
            'success' => true,
            'class' => get_class($first),
            'fields' => $safeVars,
        ]);

    } catch (Throwable $e) {
        return response()->json([
            'success' => false,
            'message' => $e->getMessage(),
        ], 500);
    }
});

// AUTH ROUTES   //

Route::get('/login', [logincontroller::class, 'showLoginForm'])->name('login')->middleware('guest');
Route::post('/login', [logincontroller::class, 'login'])->name('login.post')->middleware('throttle:5,1');
Route::post('/logout', [logincontroller::class, 'logout'])->name('logout');

// NOTIFICATIONS (all roles) //

Route::middleware('auth')->group(function () {
    Route::get('/notifications/{notification}/open', [NotificationController::class, 'open'])->name('notifications.open');
    Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::post('/notifications/clear-all', [NotificationController::class, 'clearAll'])->name('notifications.clear-all');
    Route::post('/notifications/{notification}/clear', [NotificationController::class, 'clear'])->name('notifications.clear');

    // "Back to Admin" while using an employee's account (any role, the Admin's id is in the session)
    Route::post('/impersonation/stop', [ImpersonationController::class, 'stop'])->name('impersonation.stop');
    // Leave supporting document (e.g. doctor's slip): employee, their reviewer and Admin only
    Route::get('/leave-documents/{leaveRequest}', [LeaveDocumentController::class, 'show'])->name('leave.document');

});

// DASHBOARD (Role Based) //

Route::get('/dashboard', function () {
    $route = auth()->user()->dashboardRoute();

    abort_unless($route, 403, 'Your account has no valid role. Please contact Admin.');

    return redirect()->route($route);
})->middleware('auth')->name('dashboard');

// ADMIN ROUTES    //

Route::prefix('admin')->name('admin.')->middleware(['auth', 'role:administrator'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    // Admin's own profile: name / email and password
    Route::get('/profile', [AdminProfileController::class, 'index'])->name('profile');
    Route::post('/profile', [AdminProfileController::class, 'update'])->name('profile.update');
    Route::post('/profile/password', [AdminProfileController::class, 'updatePassword'])->name('profile.password');

    // ===== EMPLOYEES =====
    Route::get('/employees', [employeecontrollers::class, 'index'])->name('employee');
    Route::post('/employees', [employeecontrollers::class, 'store'])->name('employee.store');
    Route::get('/employees/{id}/edit', [employeecontrollers::class, 'edit'])->name('employee.edit');
    Route::put('/employees/{id}', [employeecontrollers::class, 'update'])->name('employee.update');
    Route::delete('/employees/{id}', [employeecontrollers::class, 'destroy'])->name('employee.destroy');
    Route::post('/employees/send-credentials', [employeecontrollers::class, 'sendCredentialsToAll'])->name('employee.send-credentials.all');
    Route::post('/employees/{id}/send-credentials', [employeecontrollers::class, 'sendCredentials'])->name('employee.send-credentials');
    Route::patch('/employees/{id}/toggle-status', [employeecontrollers::class, 'toggleStatus'])->name('employee.toggle-status');
    Route::post('/employees/{id}/login-as', [ImpersonationController::class, 'start'])->name('employee.login-as');

    // ===== ATTENDANCE =====
    Route::get('/attendance', [AttendanceController::class, 'index'])->name('attendance');
    Route::post('/attendance/mark', [AttendanceController::class, 'markAttendance'])->name('attendance.mark');
    Route::get('/attendance/export', [AttendanceController::class, 'export'])->name('attendance.export');

    // ===== APPROVALS =====
    Route::get('/approvals', [ApprovalController::class, 'index'])->name('approvals');
    Route::post('/approvals/correction/{id}/approve', [ApprovalController::class, 'approveCorrection'])->name('approvals.correction.approve');
    Route::post('/approvals/correction/{id}/reject', [ApprovalController::class, 'rejectCorrection'])->name('approvals.correction.reject');
    Route::put('/approvals/correction/{id}', [ApprovalController::class, 'updateCorrection'])->name('approvals.correction.update');

    Route::post('/approvals/waiver/{id}/approve', [ApprovalController::class, 'approveWaiver'])->name('approvals.waiver.approve');
    Route::post('/approvals/waiver/{id}/reject', [ApprovalController::class, 'rejectWaiver'])->name('approvals.waiver.reject');
    Route::put('/approvals/waiver/{id}', [ApprovalController::class, 'updateWaiver'])->name('approvals.waiver.update');

    Route::get('/leave', [LeaveController::class, 'index'])->name('leaves');
    Route::post('/leave/type', [LeaveController::class, 'storeType'])->name('leave-type.store');
    Route::put('/leave/type/{id}', [LeaveController::class, 'updateType'])->name('leave-type.update');
    Route::delete('/leave/type/{id}', [LeaveController::class, 'destroyType'])->name('leave-type.destroy');
    Route::post('/leave/request/{id}/approve', [LeaveController::class, 'approveRequest'])->name('leave-request.approve');
    Route::post('/leave/request/{id}/reject', [LeaveController::class, 'rejectRequest'])->name('leave-request.reject');

    // ===== LEAVE REPORT =====
    Route::get('/leave-reports', [LeaveController::class, 'report'])->name('leave.report');
    Route::post('/leave-filter', [LeaveController::class, 'filter'])->name('leave.filter');
    Route::get('/leave-export/balances', [LeaveController::class, 'exportBalances'])->name('leave.export.balances');
    Route::get('/leave-export/register', [LeaveController::class, 'exportRegister'])->name('leave.export.register');
    Route::put('/leave/request/{id}', [LeaveController::class, 'updateRequest'])->name('leave-request.update');
    Route::delete('/leave/request/{id}', [LeaveController::class, 'destroyRequest'])->name('leave-request.destroy');
    // ===== PENALTY =====
    Route::get('/penalty-waivers', [PenaltyController::class, 'index'])->name('penalty');

    // ===== WORK FROM HOME =====
    Route::get('/work-from-home', [AdminWfhController::class, 'index'])->name('wfh');
    Route::post('/work-from-home/{id}/approve', [AdminWfhController::class, 'approve'])->name('wfh.approve');
    Route::post('/work-from-home/{id}/reject', [AdminWfhController::class, 'reject'])->name('wfh.reject');
    Route::get('/penalty-export', [PenaltyController::class, 'export'])->name('penalty.export');

    // ===== ATTENDANCE AUDIT =====
    Route::get('/attendance-audit', [AttendanceAuditController::class, 'index'])->name('attendance.audit');

    // Route::get('/biometric-sync', function () {
    //     return view('attendence.pages.admin.biometric-sync');
    // })->name('biometric.sync');

    Route::get('/biometric-sync', [BiometricSyncController::class, 'index'])->name('biometric.sync');
    Route::post('/biometric-sync/run', [BiometricSyncController::class, 'runSync'])->name('biometric.sync.run');
    Route::get('/biometric-sync/device-clock', [BiometricSyncController::class, 'deviceClockStatus'])->name('biometric.device-clock.status');
    Route::post('/biometric-sync/device-clock', [BiometricSyncController::class, 'setDeviceClock'])->name('biometric.device-clock');
    // ===== ATTENDANCE POLICY =====

    Route::get('/attendance-policy', [AttendancePolicyController::class, 'index'])->name('attendance.policy');
    Route::post('/attendance-policy/settings', [AttendancePolicyController::class, 'updateSettings'])->name('attendance-policy.settings');
    Route::post('/attendance-policy/holiday', [AttendancePolicyController::class, 'storeHoliday'])->name('holiday.store');
    Route::delete('/attendance-policy/holiday/{id}', [AttendancePolicyController::class, 'destroyHoliday'])->name('holiday.destroy');
    Route::post('/attendance-policy/department', [AttendancePolicyController::class, 'storeDepartment'])->name('department.store');
    Route::delete('/attendance-policy/department/{id}', [AttendancePolicyController::class, 'destroyDepartment'])->name('department.destroy');
    Route::post('/attendance-policy/department', [AttendancePolicyController::class, 'storeDepartment'])->name('department.store');
    Route::delete('/attendance-policy/department/{id}', [AttendancePolicyController::class, 'destroyDepartment'])->name('department.destroy');
    Route::post('/attendance-policy/grace-policy', [AttendancePolicyController::class, 'storeGracePolicy'])->name('grace-policy.store');
    Route::put('/attendance-policy/grace-policy/{id}', [AttendancePolicyController::class, 'updateGracePolicy'])->name('grace-policy.update');
    Route::post('/attendance-policy/lock', [AttendancePolicyController::class, 'toggleLock'])->name('lock.toggle');
    Route::post('/attendance-policy/sync-now', [AttendancePolicyController::class, 'syncNow'])->name('biometric.sync-now');
    // The page is locked until the Director unlocks it
    Route::post('/attendance-policy/request-unlock', [AttendancePolicyController::class, 'requestUnlock'])->name('attendance-policy.request-unlock');
    // ===== SHIFTS =====
    Route::get('/shifts', function () {
        return view('attendence.pages.admin.shifts');
    })->name('shifts');
    Route::post('/attendance-policy/shift', [AttendancePolicyController::class, 'storeShift'])->name('shift.store');
    Route::put('/attendance-policy/shift/{id}', [AttendancePolicyController::class, 'updateShift'])->name('shift.update');
    Route::post('/attendance-policy/shift/{id}/toggle', [AttendancePolicyController::class, 'toggleShift'])->name('shift.toggle');
    // ===== AUDIT LOG =====
    // Route::get('/audit-log', function () {
    //     return view('attendence.pages.admin.audit-log');
    // })->name('audit.log');
    Route::get('/audit-log', [AuditLogController::class, 'index'])->name('audit.log');
    Route::get('/audit-log/export', [AuditLogController::class, 'export'])->name('audit.log.export');
    // ===== BIOMETRIC SYNC =====
    // ===== ATTENDANCE BREAKDOWN =====
    Route::get('/attendance-breakdown', [AttendanceBreakdownController::class, 'index'])->name('attendance.breakdown');
    Route::get('/attendance-breakdown/export', [AttendanceBreakdownController::class, 'export'])->name('attendance.breakdown.export');
    // Route::get('/attendance-breakdown/export', [AttendanceBreakdownController::class, 'export'])->name('attendance.breakdown.export');

    // Export

    Route::get('/employees/export/csv', [employeecontrollers::class, 'exportCsv'])->name('employee.export.csv');
    Route::get('/employees/export/excel', [employeecontrollers::class, 'exportExcel'])->name('employee.export.excel');
    Route::get('/employees/export/pdf', [employeecontrollers::class, 'exportPdf'])->name('employee.export.pdf');
    Route::post('/employees/import', [employeecontrollers::class, 'importCsv'])->name('employee.import');
    Route::get('/attendance-audit/export', [AttendanceAuditController::class, 'export'])->name('attendance.audit.export');
    Route::post('/attendance-audit/inform-absent', [AttendanceAuditController::class, 'informAbsent'])->name('attendance.audit.inform-absent');

});

// EMPLOYEE ROUTES //

Route::prefix('employee')->name('employee.')->middleware(['auth', 'role:employee'])->group(function () {

    Route::get('/dashboard', [EmployeeDashboardController::class, 'index'])->name('dashboard');

    Route::get('/my-attendance', [EmployeeDashboardController::class, 'myAttendance'])->name('my.attendance');
    Route::get('/my-attendance/data', [EmployeeDashboardController::class, 'myAttendanceData'])->name('my.attendance.data');
    Route::get('/attendance-breakdown', [EmployeeDashboardController::class, 'breakdown'])->name('attendance.breakdown');

    Route::get('/correction-requests', [CorrectionController::class, 'index'])->name('correction.requests');
    Route::post('/correction-requests', [CorrectionController::class, 'store'])->name('correction.store');

    Route::get('/leave', [empLeaveController::class, 'index'])->name('leave');
    Route::post('/leave', [empLeaveController::class, 'store'])->name('leave.store');

    Route::get('/leave-report', [empLeaveController::class, 'report'])->name('leave.report');

    Route::get('/penalty-waivers', [PenaltyControllers::class, 'index'])->name('penalty');

    Route::get('/work-from-home', [EmployeeWfhController::class, 'index'])->name('wfh');
    Route::post('/work-from-home', [EmployeeWfhController::class, 'store'])->name('wfh.store');
    Route::post('/work-from-home/check-in', [EmployeeWfhController::class, 'checkIn'])->name('wfh.check-in');
    Route::post('/work-from-home/check-out', [EmployeeWfhController::class, 'checkOut'])->name('wfh.check-out');
    Route::post('/penalty-waivers/waiver', [PenaltyControllers::class, 'storeWaiver'])->name('penalty.waiver.store');
    Route::get('/profile', function () {
        return view('attendence.pages.employee.profile');
    })->name('profile');
});

// LINE MANAGER ROUTES //

Route::prefix('line-manager')->name('line_manager.')->middleware(['auth', 'role:line_manager'])->group(function () {
    Route::get('/dashboard', [LineManagerDashboardController::class, 'index'])->name('dashboard');
    Route::get('/my-attendance', [LineManagerDashboardController::class, 'myAttendance'])->name('my.attendance');

    // Approvals of the team's requests
    Route::get('/approvals', [LineManagerApprovalController::class, 'index'])->name('approvals');
    Route::post('/approvals/correction/{id}/approve', [LineManagerApprovalController::class, 'approveCorrection'])->name('approvals.correction.approve');
    Route::post('/approvals/correction/{id}/reject', [LineManagerApprovalController::class, 'rejectCorrection'])->name('approvals.correction.reject');
    Route::post('/approvals/leave/{id}/approve', [LineManagerApprovalController::class, 'approveLeave'])->name('approvals.leave.approve');
    Route::post('/approvals/leave/{id}/reject', [LineManagerApprovalController::class, 'rejectLeave'])->name('approvals.leave.reject');
    Route::post('/approvals/waiver/{id}/approve', [LineManagerApprovalController::class, 'approveWaiver'])->name('approvals.waiver.approve');
    Route::post('/approvals/waiver/{id}/reject', [LineManagerApprovalController::class, 'rejectWaiver'])->name('approvals.waiver.reject');
    Route::post('/approvals/wfh/{id}/approve', [LineManagerApprovalController::class, 'approveWfh'])->name('approvals.wfh.approve');
    Route::post('/approvals/wfh/{id}/reject', [LineManagerApprovalController::class, 'rejectWfh'])->name('approvals.wfh.reject');

    // Own requests
    Route::get('/work-from-home', [LineManagerWfhController::class, 'index'])->name('wfh');
    Route::post('/work-from-home', [LineManagerWfhController::class, 'store'])->name('wfh.store');
    Route::post('/work-from-home/check-in', [LineManagerWfhController::class, 'checkIn'])->name('wfh.check-in');
    Route::post('/work-from-home/check-out', [LineManagerWfhController::class, 'checkOut'])->name('wfh.check-out');

    Route::get('/correction-requests', [LineManagerCorrectionController::class, 'index'])->name('correction.requests');
    Route::post('/correction-requests', [LineManagerCorrectionController::class, 'store'])->name('correction.store');

    Route::get('/leave', [LineManagerLeaveController::class, 'index'])->name('leave');
    Route::post('/leave', [LineManagerLeaveController::class, 'store'])->name('leave.store');

    Route::get('/penalty-waivers', [LineManagerPenaltyController::class, 'index'])->name('penalty.waivers');
    Route::post('/penalty-waivers/waiver', [LineManagerPenaltyController::class, 'storeWaiver'])->name('penalty.waiver.store');

    // Team views
    Route::get('/team-attendance', [LineManagerTeamAttendanceController::class, 'index'])->name('team.attendance');
    Route::get('/team-attendance/export', [LineManagerTeamAttendanceController::class, 'export'])->name('team.attendance.export');
    Route::get('/leave-report', [LineManagerLeaveReportController::class, 'report'])->name('leave.report');
    Route::get('/attendance-patterns', [LineManagerAttendancePatternController::class, 'index'])->name('attendance.patterns');
    Route::get('/attendance-patterns/export', [LineManagerAttendancePatternController::class, 'export'])->name('attendance.patterns.export');
    Route::get('/attendance-breakdown', [LineManagerAttendanceBreakdownController::class, 'index'])->name('attendance.breakdown');
    Route::get('/attendance-breakdown/export', [LineManagerAttendanceBreakdownController::class, 'export'])->name('attendance.breakdown.export');

    Route::get('/profile', function () {
        return view('attendence.pages.line-manager.profile');
    })->name('profile');
});

// MANAGER ROUTES (Manager leads Line Managers) //

Route::prefix('manager')->name('manager.')->middleware(['auth', 'role:manager'])->group(function () {
    Route::get('/dashboard', [ManagerDashboardController::class, 'index'])->name('dashboard');
    Route::get('/my-attendance', [ManagerDashboardController::class, 'myAttendance'])->name('my.attendance');

    // Approvals of the team's requests
    Route::get('/approvals', [ManagerApprovalController::class, 'index'])->name('approvals');
    Route::post('/approvals/correction/{id}/approve', [ManagerApprovalController::class, 'approveCorrection'])->name('approvals.correction.approve');
    Route::post('/approvals/correction/{id}/reject', [ManagerApprovalController::class, 'rejectCorrection'])->name('approvals.correction.reject');
    Route::post('/approvals/leave/{id}/approve', [ManagerApprovalController::class, 'approveLeave'])->name('approvals.leave.approve');
    Route::post('/approvals/leave/{id}/reject', [ManagerApprovalController::class, 'rejectLeave'])->name('approvals.leave.reject');
    Route::post('/approvals/waiver/{id}/approve', [ManagerApprovalController::class, 'approveWaiver'])->name('approvals.waiver.approve');
    Route::post('/approvals/waiver/{id}/reject', [ManagerApprovalController::class, 'rejectWaiver'])->name('approvals.waiver.reject');
    Route::post('/approvals/wfh/{id}/approve', [ManagerApprovalController::class, 'approveWfh'])->name('approvals.wfh.approve');
    Route::post('/approvals/wfh/{id}/reject', [ManagerApprovalController::class, 'rejectWfh'])->name('approvals.wfh.reject');

    // Own requests
    Route::get('/work-from-home', [ManagerWfhController::class, 'index'])->name('wfh');
    Route::post('/work-from-home', [ManagerWfhController::class, 'store'])->name('wfh.store');
    Route::post('/work-from-home/check-in', [ManagerWfhController::class, 'checkIn'])->name('wfh.check-in');
    Route::post('/work-from-home/check-out', [ManagerWfhController::class, 'checkOut'])->name('wfh.check-out');

    Route::get('/correction-requests', [ManagerCorrectionController::class, 'index'])->name('correction.requests');
    Route::post('/correction-requests', [ManagerCorrectionController::class, 'store'])->name('correction.store');

    Route::get('/leave', [ManagerLeaveController::class, 'index'])->name('leave');
    Route::post('/leave', [ManagerLeaveController::class, 'store'])->name('leave.store');

    Route::get('/penalty-waivers', [ManagerPenaltyController::class, 'index'])->name('penalty.waivers');
    Route::post('/penalty-waivers/waiver', [ManagerPenaltyController::class, 'storeWaiver'])->name('penalty.waiver.store');

    // Team views
    Route::get('/team-attendance', [ManagerTeamAttendanceController::class, 'index'])->name('team.attendance');
    Route::get('/team-attendance/export', [ManagerTeamAttendanceController::class, 'export'])->name('team.attendance.export');
    Route::get('/leave-report', [ManagerLeaveReportController::class, 'report'])->name('leave.report');
    Route::get('/attendance-patterns', [ManagerAttendancePatternController::class, 'index'])->name('attendance.patterns');
    Route::get('/attendance-patterns/export', [ManagerAttendancePatternController::class, 'export'])->name('attendance.patterns.export');
    Route::get('/attendance-breakdown', [ManagerAttendanceBreakdownController::class, 'index'])->name('attendance.breakdown');
    Route::get('/attendance-breakdown/export', [ManagerAttendanceBreakdownController::class, 'export'])->name('attendance.breakdown.export');

    Route::get('/profile', function () {
        return view('attendence.pages.manager.profile');
    })->name('profile');
});

// DIRECTOR ROUTES                           //

Route::prefix('director')->name('director.')->middleware(['auth', 'role:director'])->group(function () {
    Route::get('/dashboard', [DirectorDashboardController::class, 'index'])->name('dashboard');

    // Final decision on the requests of the Managers reporting to the Director
    Route::get('/approvals', [DirectorApprovalController::class, 'index'])->name('approvals');
    Route::post('/approvals/correction/{id}/approve', [DirectorApprovalController::class, 'approveCorrection'])->name('approvals.correction.approve');
    Route::post('/approvals/correction/{id}/reject', [DirectorApprovalController::class, 'rejectCorrection'])->name('approvals.correction.reject');
    Route::post('/approvals/leave/{id}/approve', [DirectorApprovalController::class, 'approveLeave'])->name('approvals.leave.approve');
    Route::post('/approvals/leave/{id}/reject', [DirectorApprovalController::class, 'rejectLeave'])->name('approvals.leave.reject');
    Route::post('/approvals/waiver/{id}/approve', [DirectorApprovalController::class, 'approveWaiver'])->name('approvals.waiver.approve');
    Route::post('/approvals/waiver/{id}/reject', [DirectorApprovalController::class, 'rejectWaiver'])->name('approvals.waiver.reject');
    Route::post('/approvals/wfh/{id}/approve', [DirectorApprovalController::class, 'approveWfh'])->name('approvals.wfh.approve');
    Route::post('/approvals/wfh/{id}/reject', [DirectorApprovalController::class, 'rejectWfh'])->name('approvals.wfh.reject');

    // Attendance Policy page lock (Admin asks, the Director unlocks)
    Route::post('/policy/unlock', [DirectorApprovalController::class, 'unlockPolicy'])->name('policy.unlock');
    Route::post('/policy/lock', [DirectorApprovalController::class, 'lockPolicy'])->name('policy.lock');

    // Whole company
    Route::get('/team-attendance', [DirectorTeamAttendanceController::class, 'index'])->name('team.attendance');
    Route::get('/team-attendance/export', [DirectorTeamAttendanceController::class, 'export'])->name('team.attendance.export');
    Route::get('/attendance-patterns', [DirectorAttendancePatternController::class, 'index'])->name('attendance.patterns');
    Route::get('/attendance-patterns/export', [DirectorAttendancePatternController::class, 'export'])->name('attendance.patterns.export');
    Route::get('/attendance-breakdown', [DirectorAttendanceBreakdownController::class, 'index'])->name('attendance.breakdown');
    Route::get('/attendance-breakdown/export', [DirectorAttendanceBreakdownController::class, 'export'])->name('attendance.breakdown.export');
    Route::get('/employees', [DirectorEmployeeController::class, 'index'])->name('employees');
    Route::get('/reports', [DirectorReportController::class, 'index'])->name('reports');
    Route::get('/reports/export', [DirectorReportController::class, 'export'])->name('reports.export');

    Route::get('/profile', function () {
        return view('attendence.pages.director.profile');
    })->name('profile');
});

// FALLBACK                                  //

Route::fallback(function () {
    return redirect()->route('login');
});
