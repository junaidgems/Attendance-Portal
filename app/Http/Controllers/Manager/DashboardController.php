<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Models\CorrectionRequest;
use App\Models\DeductionWaiver;
use App\Models\Employee;
use App\Models\LeaveRequest;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * Views folder (resources/views/attendence/pages/{viewFolder}); the Line Manager controller reuses this one.
     */
    protected string $viewFolder = 'manager';

    public function index()
    {
        $manager = Auth::user()->employee;

        if (! $manager) {
            return redirect()->route('login')->with('error', 'No employee record was found for this user.');
        }

        $team = Employee::with(['department', 'shift', 'gracePolicy'])
            ->where('reports_to_id', $manager->id)
            ->where('employment_status', 'active')
            ->get();

        $teamIds = $team->pluck('id');
        $settings = DB::table('attendance_settings')->first();
        $today = now()->format('Y-m-d');

        // ===== Each member's last 30 days, with the same rules as the attendance pages
        // (approved leave, waivers, corrections, WFH, today's check-in still in progress) =====
        $holidays = DB::table('holidays')->get();
        $teamDays = $team->mapWithKeys(fn ($emp) => [$emp->id => $emp->attendanceDays(now()->subDays(30), now(), $settings, $holidays)]);

        // ===== TODAY'S STATUS =====
        $inOfficeNow = 0;
        $lateToday = 0;
        $absentToday = 0;
        $teamToday = [];

        foreach ($team as $emp) {
            $day = $teamDays[$emp->id]->get($today);
            $r = $day['record'];

            if ($r?->check_in && ! $r->check_out) {
                $inOfficeNow++;
            }
            if ($day['status'] === 'Late') {
                $lateToday++;
            }
            if ($day['status'] === 'Absent') {
                $absentToday++;
            }

            $teamToday[] = [
                'name' => $emp->first_name.' '.$emp->last_name,
                'status' => $day['status'] ?: 'Absent',
                'in' => $r?->check_in ? Carbon::parse($r->check_in)->format('H:i') : '—',
                'out' => $r?->check_out ? Carbon::parse($r->check_out)->format('H:i') : '—',
                'timeline' => $day['status'] === 'Absent' && ! $day['wfh'] ? 'No Punch' : Employee::dayTimeline($day),
            ];
        }

        // ===== PENDING APPROVALS (team ke corrections + leaves + waivers) =====
        $pendingCorrections = CorrectionRequest::with('employee')
            ->whereIn('employee_id', $teamIds)
            ->awaitingManager()
            ->get()
            ->map(fn ($c) => [
                'id' => $c->id, 'kind' => 'correction',
                'employee' => $c->employee->first_name.' '.$c->employee->last_name,
                'date' => $c->date,
                'type' => 'Correction Request',
                'details' => ucfirst(str_replace('-', ' ', $c->change_type)),
                'reason' => $c->reason,
            ]);

        $pendingLeaves = LeaveRequest::with(['employee', 'leaveType'])
            ->whereIn('employee_id', $teamIds)
            ->awaitingManager()
            ->get()
            ->map(fn ($l) => [
                'id' => $l->id, 'kind' => 'leave',
                'employee' => $l->employee->first_name.' '.$l->employee->last_name,
                'date' => $l->from_date,
                'type' => 'Leave Request',
                'details' => $l->leaveType->name.' ('.($l->is_half_day
                    ? 'Half day · '.($l->half_day_session === 'second_half' ? '2nd half' : '1st half')
                    : $l->days.' days').')',
                'reason' => $l->reason,
            ]);

        $pendingWaivers = DeductionWaiver::with('employee')
            ->whereIn('employee_id', $teamIds)
            ->where('status', 'pending')
            ->where('lm_status', 'pending')
            ->get()
            ->map(fn ($w) => [
                'id' => $w->id, 'kind' => 'waiver',
                'employee' => $w->employee->first_name.' '.$w->employee->last_name,
                'date' => $w->date,
                'type' => 'Waiver Request',
                'details' => DeductionWaiver::deductionLabel($w->type),
                'reason' => $w->reason,
            ]);

        $pendingApprovals = $pendingCorrections->concat($pendingLeaves)->concat($pendingWaivers)->values();

        // ===== TEAM MONTHLY STATS (same period as the breakdown page) =====
        $monthStart = now()->startOfMonth()->toDateString();
        $monthDays = $teamDays->flatMap(fn ($days) => $days->filter(fn ($d) => $d['date'] >= $monthStart)->values());
        $monthly = [
            'missing-punch' => $monthDays->where('status', 'Missing Punch')->count(),
            'half-day' => $monthDays->where('status', 'Half Day')->count(),
            'early-out' => $monthDays->where('status', 'Early Out')->count(),
            'absent' => $monthDays->where('status', 'Absent')->filter(fn ($d) => $d['date'] < $today)->count(),
            'on-leave' => $monthDays->where('status', 'On Leave')->count(),
        ];

        // ===== 30-day avg attendance rate + avg hours (approved leave days are not counted against attendance) =====
        $allDays = $teamDays->flatMap(fn ($days) => $days->values());
        $expectedDays = $allDays->whereNotIn('status', ['', 'Weekend', 'Holiday', 'On Leave'])->count();
        $presentDays = $allDays->filter(fn ($d) => ($d['record']?->check_in || $d['record']?->check_out) && ! in_array($d['status'], ['Weekend', 'On Leave'], true))->count();
        $attendanceRate = $expectedDays > 0 ? round(($presentDays / $expectedDays) * 100) : 0;

        $workedMinutes = $allDays
            ->map(fn ($d) => $d['record']?->check_in && $d['record']->check_out
                ? Carbon::parse($d['record']->check_in)->diffInMinutes(Carbon::parse($d['record']->check_out), false)
                : 0)
            ->filter(fn ($minutes) => $minutes > 0);
        $avgHours = $workedMinutes->isNotEmpty() ? round($workedMinutes->avg() / 60, 1) : 0;

        return view('attendence.pages.'.$this->viewFolder.'.dashboard', [
            'teamSize' => $team->count(),
            'inOfficeNow' => $inOfficeNow,
            'lateToday' => $lateToday,
            'absentToday' => $absentToday,
            'pendingApprovals' => $pendingApprovals,
            'teamToday' => $teamToday,
            'monthly' => $monthly,
            'attendanceRate' => $attendanceRate,
            'avgHours' => $avgHours,
        ]);
    }

    /**
     * The Manager's / Line Manager's own attendance calendar, with the same rules as every other attendance page.
     */
    public function myAttendance(Request $request)
    {
        $employee = Auth::user()->employee->loadMissing(['shift', 'gracePolicy']);
        $month = (int) $request->input('month', now()->month);
        $year = (int) $request->input('year', now()->year);
        $settings = DB::table('attendance_settings')->first();

        return view('attendence.pages.'.$this->viewFolder.'.my-attendance', $employee->monthCalendar($year, $month, $settings) + [
            'month' => $month,
            'year' => $year,
        ]);
    }
}
