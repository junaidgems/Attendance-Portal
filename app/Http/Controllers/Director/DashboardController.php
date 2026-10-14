<?php

namespace App\Http\Controllers\Director;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Director\Concerns\ManagerRequests;
use App\Models\Employee;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Company overview for the Director: today's attendance, department summary, this month and the
 * Managers' requests waiting for the Director's decision.
 */
class DashboardController extends Controller
{
    use ManagerRequests;

    public function index(): View
    {
        $employees = Employee::with(['department', 'shift', 'gracePolicy'])
            ->where('employment_status', 'active')
            ->whereDoesntHave('role', fn ($q) => $q->where('name', 'director'))
            ->orderBy('first_name')
            ->get();

        $settings = DB::table('attendance_settings')->first();
        $holidays = DB::table('holidays')->get();
        $today = now()->toDateString();

        // Last 30 days per employee, with the same rules as the attendance pages
        // (approved leave, waivers, corrections, WFH, today's check-in still in progress)
        $days = $employees->mapWithKeys(fn ($emp) => [$emp->id => $emp->attendanceDays(now()->subDays(30), now(), $settings, $holidays)]);

        // ===== TODAY =====
        $todayCounts = ['present' => 0, 'in_office' => 0, 'late' => 0, 'absent' => 0, 'on_leave' => 0, 'wfh' => 0];
        $todayRows = [];
        $departments = [];

        foreach ($employees as $emp) {
            $day = $days[$emp->id]->get($today);
            $record = $day['record'];
            $status = $day['status'] ?: 'Absent';
            $isPresent = (bool) $record?->check_in && ! in_array($status, ['Weekend', 'On Leave'], true);

            $todayCounts['present'] += $isPresent ? 1 : 0;
            $todayCounts['in_office'] += $record?->check_in && ! $record->check_out ? 1 : 0;
            $todayCounts['late'] += $status === 'Late' ? 1 : 0;
            $todayCounts['absent'] += $status === 'Absent' && ! $day['wfh'] ? 1 : 0;
            $todayCounts['on_leave'] += $status === 'On Leave' ? 1 : 0;
            $todayCounts['wfh'] += $day['wfh'] ? 1 : 0;

            $deptName = $emp->department->name ?? 'No Department';
            $departments[$deptName] ??= ['name' => $deptName, 'total' => 0, 'present' => 0, 'late' => 0, 'absent' => 0, 'on_leave' => 0];
            $departments[$deptName]['total']++;
            $departments[$deptName]['present'] += $isPresent ? 1 : 0;
            $departments[$deptName]['late'] += $status === 'Late' ? 1 : 0;
            $departments[$deptName]['absent'] += $status === 'Absent' && ! $day['wfh'] ? 1 : 0;
            $departments[$deptName]['on_leave'] += $status === 'On Leave' ? 1 : 0;

            $todayRows[] = [
                'name' => $emp->full_name,
                'department' => $deptName,
                'status' => $status,
                'in' => $record?->check_in ? Carbon::parse($record->check_in)->format('H:i') : '—',
                'out' => $record?->check_out ? Carbon::parse($record->check_out)->format('H:i') : '—',
                'timeline' => $status === 'Absent' && ! $day['wfh'] ? 'No Punch' : Employee::dayTimeline($day),
            ];
        }

        // ===== THIS MONTH (same period as the breakdown page) =====
        $monthStart = now()->startOfMonth()->toDateString();
        $monthDays = $days->flatMap(fn ($d) => $d->filter(fn ($day) => $day['date'] >= $monthStart)->values());
        $monthly = [
            'missing-punch' => $monthDays->where('status', 'Missing Punch')->count(),
            'half-day' => $monthDays->where('status', 'Half Day')->count(),
            'early-out' => $monthDays->where('status', 'Early Out')->count(),
            'absent' => $monthDays->where('status', 'Absent')->filter(fn ($d) => $d['date'] < $today)->count(),
            'on-leave' => $monthDays->where('status', 'On Leave')->count(),
        ];

        // ===== 30-DAY ATTENDANCE RATE + AVERAGE HOURS (approved leave days are not counted against attendance) =====
        $allDays = $days->flatMap(fn ($d) => $d->values());
        $expectedDays = $allDays->whereNotIn('status', ['', 'Weekend', 'Holiday', 'On Leave'])->count();
        $presentDays = $allDays->filter(fn ($d) => ($d['record']?->check_in || $d['record']?->check_out) && ! in_array($d['status'], ['Weekend', 'On Leave'], true))->count();
        $workedMinutes = $allDays
            ->map(fn ($d) => $d['record']?->check_in && $d['record']->check_out
                ? Carbon::parse($d['record']->check_in)->diffInMinutes(Carbon::parse($d['record']->check_out), false)
                : 0)
            ->filter(fn ($minutes) => $minutes > 0);

        $pending = $this->pendingManagerRequests();
        $pendingApprovals = collect($pending)
            ->flatMap(fn ($rows, $group) => $rows->map(fn ($row) => $row + ['type' => [
                'corrections' => 'Correction Request',
                'leaves' => 'Leave Request',
                'waivers' => 'Waiver Request',
                'wfh' => 'Work From Home',
            ][$group]]))
            ->values();

        return view('attendence.pages.director.dashboard', [
            'employeeCount' => $employees->count(),
            'todayCounts' => $todayCounts,
            'todayRows' => $todayRows,
            'departments' => collect($departments)->sortBy('name')->values(),
            'monthly' => $monthly,
            'attendanceRate' => $expectedDays > 0 ? round(($presentDays / $expectedDays) * 100) : 0,
            'avgHours' => $workedMinutes->isNotEmpty() ? round($workedMinutes->avg() / 60, 1) : 0,
            'pendingApprovals' => $pendingApprovals,
            'hasDirectorProfile' => $this->director() !== null,
        ]);
    }
}
