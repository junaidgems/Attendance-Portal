<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\DeductionWaiver;
use App\Models\Employee;
use App\Models\LeaveType;
use App\Models\WfhRequest;
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
                    // Today, before the shift ends, no check-out yet just means the day is still in progress
                    $stillAtWork = $dateStr === now()->format('Y-m-d') && now()->lt(Carbon::parse($dateStr.' '.$shiftEnd));
                    $entry['status'] = $stillAtWork && $r->status === 'Late' && ! DeductionWaiver::isWaived($r, 'late') ? 'Late' : 'Present';
                    $entry['out'] = $stillAtWork ? '—' : 'Missing Punch';
                    $entry['timeline'] = $stillAtWork ? 'In Progress' : 'Missing Punch';
                } else {
                    $in = Carbon::parse($dateStr.' '.$r->check_in);
                    $out = Carbon::parse($dateStr.' '.$r->check_out);
                    $netMinutes = $out->gt($in) ? $in->diffInMinutes($out) : 0;
                    $entry['out'] = $out->format('h:i A');
                    $entry['net'] = round($netMinutes / 60, 1);

                    $shiftEndTime = Carbon::parse($dateStr.' '.$shiftEnd)->subMinutes($earlyGrace);
                    $isEarly = $out->lt($shiftEndTime) && $netMinutes < $fullDayMin && ! DeductionWaiver::isWaived($r, 'early_out');
                    $isLate = $r->status === 'Late' && ! DeductionWaiver::isWaived($r, 'late');

                    if (($netMinutes <= $halfDayMax || Employee::isHalfDayByLateArrival($employee, $dateStr, $r->check_in, $settings)) && ! DeductionWaiver::isWaived($r, 'half_day')) {
                        $entry['status'] = 'Half Day';
                    } elseif ($isLate) {
                        $entry['status'] = 'Late';
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

        // ===== Summary counts (ab yahin PHP mein) =====
        $present = collect($data)->whereIn('status', ['Present', 'Late', 'Early Out', 'Half Day'])->count();
        $absent = collect($data)->where('status', 'Absent')->count();
        $late = collect($data)->where('status', 'Late')->count();
        $early = collect($data)->where('status', 'Early Out')->count();
        $half = collect($data)->where('status', 'Half Day')->count();
        $missing = collect($data)->filter(fn ($d) => $d['in'] === 'Missing Punch' || $d['out'] === 'Missing Punch')->count();
        $workingDaysCount = collect($data)->reject(fn ($d) => in_array($d['status'], ['Weekend', 'Holiday', '']))->count();

        return view('attendence.pages.employee.attendance', [
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

    public function myAttendanceData(Request $request)
    {
        $employee = Auth::user()->employee;

        $month = (int) $request->input('month', now()->month); // 1-12
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
                // ✅ Punch maujood hai — chahe holiday ho, normal present/late logic chalegi
                $r = $records[$dateStr];
                $entry['in'] = Carbon::parse($r->check_in)->format('h:i A');
                $entry['out'] = $r->check_out ? Carbon::parse($r->check_out)->format('h:i A') : '—';

                if (! $r->check_out) {
                    // Today, before the shift ends, no check-out yet just means the day is still in progress
                    $stillAtWork = $dateStr === now()->format('Y-m-d') && now()->lt(Carbon::parse($dateStr.' '.$shiftEnd));
                    $entry['status'] = $stillAtWork && $r->status === 'Late' && ! DeductionWaiver::isWaived($r, 'late') ? 'Late' : 'Present';
                    $entry['out'] = $stillAtWork ? '—' : 'Missing Punch';
                    $entry['timeline'] = $stillAtWork ? 'In Progress' : 'Missing Punch';
                } else {
                    $in = Carbon::parse($dateStr.' '.$r->check_in);
                    $out = Carbon::parse($dateStr.' '.$r->check_out);
                    $netMinutes = $out->gt($in) ? $in->diffInMinutes($out) : 0;
                    $entry['net'] = round($netMinutes / 60, 1);

                    $shiftEndTime = Carbon::parse($dateStr.' '.$shiftEnd)->subMinutes($earlyGrace);
                    $isEarly = $out->lt($shiftEndTime) && $netMinutes < $fullDayMin && ! DeductionWaiver::isWaived($r, 'early_out');
                    $isLate = $r->status === 'Late' && ! DeductionWaiver::isWaived($r, 'late');

                    if (($netMinutes <= $halfDayMax || Employee::isHalfDayByLateArrival($employee, $dateStr, $r->check_in, $settings)) && ! DeductionWaiver::isWaived($r, 'half_day')) {
                        $entry['status'] = 'Half Day';
                    } elseif ($isLate) {
                        $entry['status'] = 'Late';
                    } elseif ($isEarly) {
                        $entry['status'] = 'Early Out';
                    } else {
                        $entry['status'] = 'Present';
                    }
                    $entry['timeline'] = $entry['status'] === 'Late' ? 'Late' : ($entry['status'] === 'Present' ? 'On Time' : $entry['status']);
                }

                if ($holiday) {
                    $entry['timeline'] .= ' (Holiday)'; // optional note taake pata chale holiday pe aaya tha
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

        return response()->json($data);
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
