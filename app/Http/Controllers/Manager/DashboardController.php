<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Models\CorrectionRequest;
use App\Models\DeductionWaiver;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\WfhRequest;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
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

        return view('attendence.pages.manager.dashboard', [
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

    // For Attendence page of line manager, showing his own attendance records
    public function myAttendance(Request $request)
    {
        $employee = Auth::user()->employee;

        $month = (int) $request->input('month', now()->month);
        $year = (int) $request->input('year', now()->year);

        $settings = DB::table('attendance_settings')->first();
        $shiftEnd = $employee->shift->end_time ?? ($settings->standard_shift_end ?? '18:00:00');
        $earlyGrace = $employee->gracePolicy->early_grace_minutes ?? ($settings->early_departure_grace_minutes ?? 0);

        $startDate = Carbon::create($year, $month, 1)->startOfMonth();
        $endDate = $startDate->copy()->endOfMonth();

        $records = collect();
        if ($employee->zkteco_uid) {
            $records = DB::table('device_attendance')
                ->where('uid', $employee->zkteco_uid)
                ->whereBetween('date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
                ->get()
                ->keyBy('date');
        }

        $holidays = DB::table('holidays')->get();

        $leaves = $employee->leaveRequests()
            ->approvedAbsence()
            ->whereBetween('from_date', [$startDate->copy()->subMonth()->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->get();

        $wfhDays = WfhRequest::approvedDayKeys([$employee->id], $startDate, $endDate);

        $data = [];
        $cursor = $startDate->copy();
        $today = now()->startOfDay();

        while ($cursor->lte($endDate)) {
            $dateStr = $cursor->format('Y-m-d');
            // Shift timings and half/full-day limits for this date (Saturday has its own timings)
            ['start' => $shiftStart, 'end' => $shiftEnd, 'half_day_max' => $halfDayMax, 'full_day_min' => $fullDayMin] = $employee->shiftTimesOn($dateStr, $settings);
            $dayAbbr = $cursor->format('D');
            $isWorkingDay = $employee->worksOn($dateStr, $settings);

            $entry = [
                'date' => $cursor->day,
                'full_date' => $dateStr,
                'adjustment' => $records[$dateStr]->adjustment_note ?? null,
                'day' => $dayAbbr,
                'status' => '',
                'in' => '—',
                'out' => '—',
                'net' => '—',
                'timeline' => '—',
            ];

            $holiday = $holidays->first(fn ($h) => $dateStr >= $h->from_date && $dateStr <= ($h->to_date ?? $h->from_date));
            $leave = $leaves->first(fn ($l) => $dateStr >= $l->from_date && $dateStr <= $l->to_date);

            if (! $isWorkingDay) {
                $entry['status'] = 'Weekend';
            } elseif ($leave) {
                $entry['status'] = 'On Leave';
            } elseif (isset($records[$dateStr]) && ! $records[$dateStr]->check_in && $records[$dateStr]->check_out) {
                // Only the evening punch: the check-in is missing
                $entry['status'] = 'Present';
                $entry['in'] = 'Missing Punch';
                $entry['out'] = Carbon::parse($records[$dateStr]->check_out)->format('h:i A');
                $entry['timeline'] = 'Missing Punch';
            } elseif (isset($records[$dateStr]) && $records[$dateStr]->check_in) {
                $r = $records[$dateStr];
                $entry['in'] = Carbon::parse($r->check_in)->format('h:i A');

                if (! $r->check_out) {
                    $isToday = $dateStr === now()->format('Y-m-d');
                    $shiftEndDateTime = Carbon::parse($dateStr.' '.$shiftEnd);
                    $dayStillOngoing = $isToday && now()->lt($shiftEndDateTime);

                    $entry['status'] = 'Present';
                    if ($dayStillOngoing) {
                        $entry['out'] = '—';
                        $entry['timeline'] = 'In Progress';
                    } else {
                        $entry['out'] = 'Missing Punch';
                        $entry['timeline'] = 'Missing Punch';
                    }
                } else {
                    $in = Carbon::parse($dateStr.' '.$r->check_in);
                    $out = Carbon::parse($dateStr.' '.$r->check_out);
                    $netMinutes = $out->gt($in) ? $in->diffInMinutes($out) : 0;
                    $entry['out'] = $out->format('h:i A');
                    $entry['net'] = round($netMinutes / 60, 1);

                    $shiftEndTime = Carbon::parse($dateStr.' '.$shiftEnd)->subMinutes($earlyGrace);
                    $isEarly = $out->lt($shiftEndTime) && $netMinutes < $fullDayMin && ! DeductionWaiver::isWaived($r, 'early_out');
                    $isLate = $r->status === 'Late' && ! DeductionWaiver::isWaived($r, 'late');

                    if ($netMinutes > 0 && ($netMinutes <= $halfDayMax || Employee::isHalfDayByLateArrival($employee, $dateStr, $r->check_in, $settings)) && ! DeductionWaiver::isWaived($r, 'half_day')) {
                        $entry['status'] = 'Half Day';
                    } elseif ($isLate) {
                        $entry['status'] = 'Late';
                    } elseif ($netMinutes >= $fullDayMin) {
                        $entry['status'] = 'Present';
                    } elseif ($isEarly) {
                        $entry['status'] = 'Early Out';
                    } else {
                        $entry['status'] = 'Present';
                    }
                    $entry['timeline'] = $entry['status'] === 'Late' ? 'Late' : ($entry['status'] === 'Present' ? 'On Time' : $entry['status']);
                }
            } elseif ($holiday) {
                $entry['status'] = 'Holiday';
                $entry['timeline'] = $holiday->name;
            } elseif ($cursor->gt($today) || $dateStr < Employee::ATTENDANCE_START_DATE) {
                $entry['status'] = '';
            } else {
                $entry['status'] = 'Absent';
            }

            // Approved Work From Home day: portal check-in, or approved but never checked in
            $wfhLabel = WfhRequest::dayLabel($records[$dateStr] ?? null, isset($wfhDays[$employee->id.'_'.$dateStr]));
            if ($wfhLabel && ! in_array($entry['status'], ['', 'Weekend', 'Holiday', 'On Leave'], true)) {
                $entry['timeline'] = $entry['status'] === 'Absent' ? $wfhLabel : $entry['timeline'].' · '.$wfhLabel;
                $entry['wfh'] = $wfhLabel;
            }
            $data[] = $entry;
            $cursor->addDay();
        }

        $present = collect($data)->whereIn('status', ['Present', 'Late', 'Early Out', 'Half Day'])->count();
        $absent = collect($data)->where('status', 'Absent')->count();
        $late = collect($data)->where('status', 'Late')->count();
        $early = collect($data)->where('status', 'Early Out')->count();
        $half = collect($data)->where('status', 'Half Day')->count();
        $missing = collect($data)->filter(fn ($d) => $d['in'] === 'Missing Punch' || $d['out'] === 'Missing Punch')->count();
        $workingDaysCount = collect($data)->reject(fn ($d) => in_array($d['status'], ['Weekend', 'Holiday', '']))->count();

        return view('attendence.pages.manager.my-attendance', [
            'data' => $data,
            'month' => $month,
            'year' => $year,
            'present' => $present,
            'absent' => $absent,
            'late' => $late,
            'early' => $early,
            'half' => $half,
            'missing' => $missing,
            'workingDaysCount' => $workingDaysCount,
        ]);
    }
}
