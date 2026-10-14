<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\LeaveType;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class EmployeeDashboardController extends Controller
{
    public function index()
    {
        $employee = Auth::user()->employee;

        // if (!$employee) {
        //     return redirect()->route('login')->with('error', 'Employee record nahi mila is user ke liye.');
        // }

        $settings = DB::table('attendance_settings')->first();

        // ===== Last 30 days: same rules as My Attendance (leave, waivers, corrections, WFH) =====
        $days = $employee->attendanceDays(now()->subDays(30), now(), $settings);
        $workDays = $days->whereNotIn('status', ['', 'Weekend', 'Holiday']);
        $checkedIn = $workDays->filter(fn ($d) => ($d['record']?->check_in || $d['record']?->check_out) && $d['status'] !== 'On Leave');

        // Approved leave days are not counted against attendance
        $totalWorkingDays = $workDays->where('status', '!=', 'On Leave')->count();
        $presentDays = $checkedIn->count();
        $lateCount = $workDays->where('status', 'Late')->count();
        $attendancePercent = $totalWorkingDays > 0 ? round(($presentDays / $totalWorkingDays) * 100) : 0;

        // ===== Average hours/day =====
        $workedMinutes = $checkedIn
            ->map(fn ($d) => $d['record']->check_in && $d['record']->check_out ? Carbon::parse($d['record']->check_in)->diffInMinutes(Carbon::parse($d['record']->check_out), false) : 0)
            ->filter(fn ($minutes) => $minutes > 0);
        $avgHours = $workedMinutes->isNotEmpty() ? round($workedMinutes->avg() / 60, 1).'h' : '—';

        // ===== This month (same period as the breakdown page) =====
        $month = $days->filter(fn ($d) => $d['date'] >= now()->startOfMonth()->toDateString());
        $missingPunch = $month->where('status', 'Missing Punch')->count();
        $halfDay = $month->where('status', 'Half Day')->count();
        $earlyOut = $month->where('status', 'Early Out')->count();
        $absentCount = $month->where('status', 'Absent')->filter(fn ($d) => $d['date'] < now()->toDateString())->count();
        $onLeaveCount = $month->where('status', 'On Leave')->count();

        // ===== Open requests (pending leave) =====
        $openRequests = $employee->leaveRequests()->where('status', 'pending')->count();

        // ===== Leave balances =====
        $leaveTypes = LeaveType::all();
        $balances = [];
        foreach (['Annual Leave', 'Sick Leave', 'Casual Leave'] as $typeName) {
            $type = $leaveTypes->firstWhere('name', $typeName);
            $used = $type
                ? $employee->leaveRequests()->where('leave_type_id', $type->id)->where('status', 'approved')->inLeaveYear()->sum('days')
                : 0;
            // No paid leave while on probation
            $total = $employee->leaveAllowance($type);

            $balances[$typeName] = [
                'used' => $total - $used, // remaining balance
                'total' => $total,
            ];
        }

        // ===== Recent days table (last 5 working days) =====
        $recentDays = $workDays->sortKeysDesc()->take(5)->map(function ($d) {
            $r = $d['record'];
            $minutes = $r?->check_in && $r->check_out ? Carbon::parse($r->check_in)->diffInMinutes(Carbon::parse($r->check_out), false) : 0;

            return [
                'date' => Carbon::parse($d['date'])->format('D d M'),
                'status' => $d['status'],
                'in' => $r?->check_in ? Carbon::parse($r->check_in)->format('h:i A') : '—',
                'out' => $r?->check_out ? Carbon::parse($r->check_out)->format('h:i A') : '—',
                'hours' => $minutes > 0 ? intdiv((int) $minutes, 60).'h '.((int) $minutes % 60).'m' : '—',
                'timeline' => Employee::dayTimeline($d),
            ];
        })->values();

        return view('attendence.pages.employee.dashboard', [
            'attendancePercent' => $attendancePercent,
            'presentDays' => $presentDays,
            'totalWorkingDays' => $totalWorkingDays,
            'lateCount' => $lateCount,
            'avgHours' => $avgHours,
            'openRequests' => $openRequests,
            'balances' => $balances,
            'missingPunch' => $missingPunch,
            'halfDay' => $halfDay,
            'earlyOut' => $earlyOut,
            'absentCount' => $absentCount,
            'onLeaveCount' => $onLeaveCount,
            'recentDays' => $recentDays,
        ]);
    }

    // My attendence
    public function myAttendance(Request $request)
    {
        return view('attendence.pages.employee.attendance', $this->calendar($request) + [
            'month' => (int) $request->input('month', now()->month),
            'year' => (int) $request->input('year', now()->year),
        ]);
    }

    public function myAttendanceData(Request $request)
    {
        return response()->json($this->calendar($request)['data']);
    }

    /**
     * The month's calendar rows and summary counts, with the same rules as every other attendance page.
     *
     * @return array<string, mixed>
     */
    private function calendar(Request $request): array
    {
        $employee = Auth::user()->employee->loadMissing(['shift', 'gracePolicy']);
        $settings = DB::table('attendance_settings')->first();

        return $employee->monthCalendar((int) $request->input('year', now()->year), (int) $request->input('month', now()->month), $settings);
    }

    // My Attendance Breakdown
    public function breakdown(Request $request)
    {
        $employee = Auth::user()->employee->loadMissing(['shift', 'gracePolicy', 'department']);

        $from = $request->input('from', now()->startOfMonth()->format('Y-m-d'));
        $to = $request->input('to', now()->format('Y-m-d'));
        $settings = DB::table('attendance_settings')->first();

        // Same rules as My Attendance and the dashboard: leave, waivers, corrections, WFH, today in progress
        $rows = $employee->attendanceDays($from, $to, $settings)
            ->reject(fn ($day) => in_array($day['status'], ['', 'Weekend'], true))
            ->map(fn ($day) => $employee->breakdownRow($day, $settings));

        return view('attendence.pages.employee.employeeAttendBreak', [
            'grouped' => Employee::breakdownTabs($rows),
            'employee' => $employee,
            'from' => $from,
            'to' => $to,
        ]);
    }
}
