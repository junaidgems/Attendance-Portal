<?php

use App\Http\Controllers\Admin\ApprovalController;
use App\Http\Controllers\Admin\AttendanceController;
use App\Http\Controllers\employeecontrollers;
use App\Http\Controllers\Admin\LeaveController;
use App\Http\Controllers\Admin\PenaltyController;
use App\Http\Controllers\Auth\LoginController;
use Illuminate\Support\Facades\Route;


// AUTH ROUTES   //

Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login')->middleware('guest');
Route::post('/login', [LoginController::class, 'login'])->name('login.post');
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');


// DASHBOARD (Role Based)                    //

Route::get('/dashboard', function () {
    $role = session('user_role', 'employee');
    $routes = [
        'administrator' => 'admin.dashboard',
        'director' => 'director.dashboard',
        'manager' => 'manager.dashboard',
        'line_manager' => 'line_manager.dashboard',
        'employee' => 'employee.dashboard',
    ];
    return redirect()->route($routes[$role] ?? 'employee.dashboard');
})->name('dashboard');

// ADMIN ROUTES    //

Route::prefix('admin')->name('admin.')->middleware('auth')->group(function () {
    Route::get('/dashboard', function () {
        return view('attendence.pages.admin.dashboard');
    })->name('dashboard');

    // ===== EMPLOYEES =====
    Route::get('/employees', [employeecontrollers::class, 'index'])->name('employee');
    Route::post('/employees', [employeecontrollers::class, 'store'])->name('employee.store');
    Route::get('/employees/{id}/edit', [employeecontrollers::class, 'edit'])->name('employee.edit');
    Route::put('/employees/{id}', [employeecontrollers::class, 'update'])->name('employee.update');
    Route::delete('/employees/{id}', [employeecontrollers::class, 'destroy'])->name('employee.destroy');

    // ===== ATTENDANCE =====
    Route::get('/attendance', [AttendanceController::class, 'index'])->name('attendance');
    Route::post('/attendance/mark', [AttendanceController::class, 'mark'])->name('attendance.mark');
    Route::get('/attendance/export', [AttendanceController::class, 'export'])->name('attendance.export');

    // ===== APPROVALS =====
    Route::get('/approvals', [ApprovalController::class, 'index'])->name('approvals');

    // ===== LEAVE MANAGEMENT =====
    Route::get('/leaves', [LeaveController::class, 'index'])->name('leaves');

    // ===== LEAVE REPORT =====
    Route::get('/leave-report', [LeaveController::class, 'report'])->name('leave.report');
    Route::post('/leave-filter', [LeaveController::class, 'filter'])->name('leave.filter');
    Route::get('/leave-export/balances', [LeaveController::class, 'exportBalances'])->name('leave.export.balances');
    Route::get('/leave-export/register', [LeaveController::class, 'exportRegister'])->name('leave.export.register');

    // ===== PENALTY =====
    Route::get('/penalty-waivers', [PenaltyController::class, 'index'])->name('penalty');
    Route::get('/penalty-export', [PenaltyController::class, 'export'])->name('penalty.export');

    // ===== ATTENDANCE AUDIT =====
    Route::get('/attendance-audit', function () {
        return view('attendence.pages.admin.attendance-audit');
    })->name('attendance.audit');

    // ===== BIOMETRIC SYNC =====
    Route::get('/biometric-sync', function () {
        return view('attendence.pages.admin.biometric-sync');
    })->name('biometric.sync');

    // ===== ATTENDANCE POLICY =====
    Route::get('/attendance-policy', function () {
        return view('attendence.pages.admin.attendance-policy');
    })->name('attendance.policy');

    // ===== SHIFTS =====
    Route::get('/shifts', function () {
        return view('attendence.pages.admin.shifts');
    })->name('shifts');

    // ===== AUDIT LOG =====
    Route::get('/audit-log', function () {
        return view('attendence.pages.admin.audit-log');
    })->name('audit.log');

    // ===== ATTENDANCE BREAKDOWN =====
    Route::get('/attendance-breakdown', function () {
        return view('attendence.pages.admin.attendance-breakdown');
    })->name('attendance.breakdown');

});


// EMPLOYEE ROUTES                           //

Route::prefix('employee')->name('employee.')->middleware('auth')->group(function () {

    Route::get('/dashboard', function () {
        return view('attendence.pages.employee.dashboard');
    })->name('dashboard');

    Route::get('/my-attendance', function () {
        return view('attendence.pages.employee.attendance');
    })->name('my.attendance');

    Route::get('/attendance-breakdown', function () {
        return view('attendence.pages.employee.employeeAttendBreak');
    })->name('attendance.breakdown');

    Route::get('/correction-requests', function () {
        return view('attendence.pages.employee.correction-requests');
    })->name('correction.requests');

    Route::get('/leave', function () {
        return view('attendence.pages.employee.leave');
    })->name('leave');

    Route::get('/leave-report', function () {
        return view('attendence.pages.employee.leave-report');
    })->name('leave.report');

    Route::get('/penalty-waivers', function () {
        return view('attendence.pages.employee.penalty-waivers');
    })->name('penalty.waivers');

});



// MANAGER ROUTES                            //

Route::prefix('manager')->name('manager.')->middleware('auth')->group(function () {
    Route::get('/dashboard', function () {
        return view('attendence.pages.manager.dashboard');
    })->name('dashboard');

    Route::get('/my-attendance', function () {
        return view('attendence.pages.manager.my-attendance');
    })->name('my.attendance');

    Route::get('/correction-requests', function () {
        return view('attendence.pages.manager.correction-requests');
    })->name('correction.requests');

    Route::get('/leave', function () {
        return view('attendence.pages.manager.leave');
    })->name('leave');

    Route::get('/leave-report', function () {
        return view('attendence.pages.manager.leave-report');
    })->name('leave.report');

    Route::get('/penalty-waivers', function () {
        return view('attendence.pages.manager.penalty-waivers');
    })->name('penalty.waivers');

    Route::get('/team-attendance', function () {
        return view('attendence.pages.manager.team-attendance');
    })->name('team.attendance');

    Route::get('/approvals', function () {
        return view('attendence.pages.manager.approvals');
    })->name('approvals');

    Route::get('/attendance-patterns', function () {
        return view('attendence.pages.manager.attendance-patterns');
    })->name('attendance.patterns');

});


// LINE MANAGER ROUTES                       //

Route::prefix('line-manager')->name('line_manager.')->middleware('auth')->group(function () {

    Route::get('/dashboard', function () {
        return view('attendence.pages.line_manager.dashboard');
    })->name('dashboard');

    Route::get('/team-overview', function () {
        return view('attendence.pages.line_manager.team-overview');
    })->name('team.overview');

    Route::get('/team-attendance', function () {
        return view('attendence.pages.line_manager.team-attendance');
    })->name('team.attendance');

    Route::get('/approvals', function () {
        return view('attendence.pages.line_manager.approvals');
    })->name('approvals');

    Route::get('/leaves', function () {
        return view('attendence.pages.line_manager.leaves');
    })->name('leaves');

    Route::get('/leave-report', function () {
        return view('attendence.pages.line_manager.leave-report');
    })->name('leave.report');

});


// DIRECTOR ROUTES                           //

Route::prefix('director')->name('director.')->middleware('auth')->group(function () {

    Route::get('/dashboard', function () {
        return view('attendence.pages.director.dashboard');
    })->name('dashboard');

    Route::get('/my-attendance', function () {
        return view('attendence.pages.director.my-attendance');
    })->name('my.attendance');

    Route::get('/correction-requests', function () {
        return view('attendence.pages.director.correction-requests');
    })->name('correction.requests');

    Route::get('/leave', function () {
        return view('attendence.pages.director.leave');
    })->name('leave');

    Route::get('/leave-report', function () {
        return view('attendence.pages.director.leave-report');
    })->name('leave.report');

    Route::get('/penalty-waivers', function () {
        return view('attendence.pages.director.penalty-waivers');
    })->name('penalty.waivers');

    Route::get('/team-attendance', function () {
        return view('attendence.pages.director.team-attendance');
    })->name('team.attendance');

    Route::get('/approvals', function () {
        return view('attendence.pages.director.approvals');
    })->name('approvals');

    Route::get('/attendance-patterns', function () {
        return view('attendence.pages.director.attendance-patterns');
    })->name('attendance.patterns');

    Route::get('/attendance-breakdown', function () {
        return view('attendence.pages.director.attendance-breakdown');
    })->name('attendance.breakdown');

    Route::get('/employees', function () {
        return view('attendence.pages.director.employees');
    })->name('employees');

    Route::get('/attendance', function () {
        return view('attendence.pages.director.attendance');
    })->name('attendance');

    Route::get('/reports', function () {
        return view('attendence.pages.director.reports');
    })->name('reports');

    Route::get('/analytics', function () {
        return view('attendence.pages.director.analytics');
    })->name('analytics');

    Route::get('/leaves', function () {
        return view('attendence.pages.director.leaves');
    })->name('leaves');

});


// FALLBACK                                  //

Route::fallback(function () {
    return redirect()->route('login');
});