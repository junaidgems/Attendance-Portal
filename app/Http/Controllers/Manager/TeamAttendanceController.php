<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Models\DeductionWaiver;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\WfhRequest;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TeamAttendanceController extends Controller
{
    /**
     * Views folder (resources/views/attendence/pages/{viewFolder}); the Director pages reuse these controllers.
     */
    protected string $viewFolder = 'manager';

    /**
     * Extra data for the page view, e.g. the Director's department filter.
     *
     * @return array<string, mixed>
     */
    protected function extraViewData(Request $request): array
    {
        return [];
    }

    protected function currentManager()
    {
        return Employee::where('user_id', Auth::id())->firstOrFail();
    }

    protected function teamMembers($manager)
    {
        return Employee::with(['department', 'shift', 'gracePolicy'])
            ->where('reports_to_id', $manager->id)
            ->where('employment_status', 'active')
            ->orderBy('first_name')
            ->get();
    }

    public function index(Request $request)
    {
        return view('attendence.pages.'.$this->viewFolder.'.team-attendance', $this->buildData($request) + $this->extraViewData($request));
    }

    public function export(Request $request)
    {
        $data = $this->buildData($request);

        $filename = 'team-attendance-'.$data['date'].'.csv';
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ];

        $callback = function () use ($data) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['Member', 'Emp ID', 'Date', 'Status', 'IN', 'OUT', 'Net Hours', 'Timeline']);
            foreach ($data['rows'] as $r) {
                fputcsv($file, [$r['name'], $r['emp_id'], $r['date'], $r['status'], $r['in'], $r['out'], $r['net'], $r['timeline']]);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    private function buildData(Request $request)
    {
        $manager = $this->currentManager();
        $team = $this->teamMembers($manager);

        $date = $request->input('date', now()->format('Y-m-d'));
        if ($date > now()->format('Y-m-d')) {
            $date = now()->format('Y-m-d'); // future date allowed nahi
        }
        $memberId = $request->input('member', 'all');

        $settings = DB::table('attendance_settings')->first();
        $holidays = DB::table('holidays')->get();
        $workingDays = $settings ? explode(',', $settings->working_days) : ['Mon', 'Tue', 'Wed', 'Thu', 'Fri'];

        $rows = [];

        if ($memberId === 'all') {
            // ===== DAY VIEW: poori team, ek date =====
            $records = DB::table('device_attendance')
                ->where('date', $date)
                ->get()
                ->keyBy(fn ($r) => (string) $r->uid);

            $leaves = LeaveRequest::whereIn('employee_id', $team->pluck('id'))
                ->approvedAbsence()
                ->get()
                ->groupBy('employee_id');

            $wfhDays = WfhRequest::approvedDayKeys($team->pluck('id'), $date, $date);

            foreach ($team as $emp) {
                $r = $emp->zkteco_uid ? $records->get((string) $emp->zkteco_uid) : null;
                $rows[] = $this->buildRow($emp, $date, $r, $settings, $holidays, $leaves->get($emp->id, collect()), isset($wfhDays[$emp->id.'_'.$date]));
            }
        } else {
            // ===== MEMBER VIEW: ek member, poora mahina =====
            $emp = $team->firstWhere('id', (int) $memberId);

            if ($emp) {
                $monthStart = Carbon::parse($date)->startOfMonth();
                $monthEnd = Carbon::parse($date)->endOfMonth();
                if ($monthEnd->gt(now())) {
                    $monthEnd = now();
                }

                $records = collect();
                if ($emp->zkteco_uid) {
                    $records = DB::table('device_attendance')
                        ->where('uid', $emp->zkteco_uid)
                        ->whereBetween('date', [$monthStart->format('Y-m-d'), $monthEnd->format('Y-m-d')])
                        ->get()
                        ->keyBy('date');
                }

                $leaves = $emp->leaveRequests()->approvedAbsence()->get();
                $wfhDays = WfhRequest::approvedDayKeys([$emp->id], $monthStart, $monthEnd);

                $cursor = $monthStart->copy();
                while ($cursor->lte($monthEnd)) {
                    $dStr = $cursor->format('Y-m-d');
                    $rows[] = $this->buildRow($emp, $dStr, $records->get($dStr), $settings, $holidays, $leaves, isset($wfhDays[$emp->id.'_'.$dStr]));
                    $cursor->addDay();
                }
            }
        }

        $rows = array_values(array_filter($rows, fn ($r) => $r['status'] !== ''));

        $counts = ['all' => count($rows), 'present' => 0, 'late' => 0, 'early-out' => 0, 'half-day' => 0, 'absent' => 0];
        foreach ($rows as $r) {
            foreach (['present', 'late', 'early-out', 'half-day', 'absent'] as $tag) {
                if (in_array($tag, $r['tags'])) {
                    $counts[$tag]++;
                }
            }
        }

        $d = Carbon::parse($date);
        $isHoliday = $holidays->contains(fn ($h) => $date >= $h->from_date && $date <= ($h->to_date ?? $h->from_date));
        $dayLabel = ! in_array($d->format('D'), $workingDays) ? 'Weekend' : ($isHoliday ? 'Holiday' : 'Working day');

        return [
            'rows' => $rows,
            'counts' => $counts,
            'date' => $date,
            'memberId' => $memberId,
            'team' => $team,
            'dayLabel' => $dayLabel,
            'dateDisplay' => $d->format('D d M'),
        ];
    }

    protected function buildRow($emp, $dateStr, $r, $settings, $holidays, $leaves, bool $isApprovedWfhDay = false)
    {
        // Shift timings and half/full-day limits for this date (Saturday has its own timings)
        ['end' => $shiftEnd, 'half_day_max' => $halfDayMax, 'full_day_min' => $fullDayMin] = $emp->shiftTimesOn($dateStr, $settings);
        $earlyGrace = $emp->gracePolicy->early_grace_minutes ?? ($settings->early_departure_grace_minutes ?? 0);

        $date = Carbon::parse($dateStr);

        $row = [
            'name' => $emp->first_name.' '.$emp->last_name,
            'emp_id' => $emp->employee_id,
            'date' => $date->format('D d M'),
            'status' => '',
            'tags' => [],
            'in' => '—',
            'out' => '—',
            'net' => '—',
            'net_minutes' => null,
            'timeline' => '—',
            'timeline_class' => '',
        ];

        $holiday = $holidays->first(fn ($h) => $dateStr >= $h->from_date && $dateStr <= ($h->to_date ?? $h->from_date));
        $leave = $leaves->first(fn ($l) => $dateStr >= $l->from_date && $dateStr <= $l->to_date);
        $isWorkingDay = $emp->worksOn($dateStr, $settings);

        if (! $isWorkingDay) {
            $row['status'] = 'Weekend';
        } elseif ($leave) {
            $row['status'] = 'On Leave';
        } elseif ($r && ! $r->check_in && $r->check_out) {
            // Only the evening punch: the check-in is missing
            $row['status'] = 'Present';
            $row['tags'] = ['present', 'absent']; // "Missing/absent" tab mein bhi aaye
            $row['in'] = 'Missing Punch';
            $row['out'] = Carbon::parse($r->check_out)->format('H:i');
            $row['timeline'] = 'Missing Punch';
            $row['timeline_class'] = 'timeline-absent';
        } elseif ($r && $r->check_in) {
            $row['in'] = Carbon::parse($r->check_in)->format('H:i');
            $row['tags'] = ['present'];

            if (! $r->check_out) {
                $isToday = $dateStr === now()->format('Y-m-d');
                $ongoing = $isToday && now()->lt(Carbon::parse($dateStr.' '.$shiftEnd));

                $row['status'] = 'Present';
                $row['timeline_class'] = 'timeline-on-time';

                if ($ongoing) {
                    $row['timeline'] = 'In Progress';
                } else {
                    $row['out'] = 'Missing Punch';
                    $row['timeline'] = 'Missing Punch';
                    $row['tags'][] = 'absent'; // "Missing/absent" tab mein bhi aaye
                    $row['timeline_class'] = 'timeline-absent';
                }
            } else {
                $in = Carbon::parse($dateStr.' '.$r->check_in);
                $out = Carbon::parse($dateStr.' '.$r->check_out);
                $net = $out->gt($in) ? (int) $in->diffInMinutes($out) : 0;

                $row['out'] = $out->format('H:i');
                $row['net'] = floor($net / 60).'h '.($net % 60).'m';
                $row['net_minutes'] = $net;

                $shiftEndTime = Carbon::parse($dateStr.' '.$shiftEnd)->subMinutes($earlyGrace);

                if ($net > 0 && ($net <= $halfDayMax || Employee::isHalfDayByLateArrival($emp, $dateStr, $r->check_in, $settings)) && ! DeductionWaiver::isWaived($r, 'half_day')) {
                    $row['status'] = 'Half Day';
                    $row['tags'][] = 'half-day';
                    $row['timeline'] = 'Half Day';
                    $row['timeline_class'] = 'timeline-late';
                } elseif ($r->status === 'Late' && ! DeductionWaiver::isWaived($r, 'late')) {
                    $row['status'] = 'Late';
                    $row['tags'][] = 'late';
                    $row['timeline'] = 'Late';
                    $row['timeline_class'] = 'timeline-late';
                } elseif ($net >= $fullDayMin) {
                    $row['status'] = 'Present';
                    $row['timeline'] = 'On Time';
                    $row['timeline_class'] = 'timeline-on-time';
                } elseif ($out->lt($shiftEndTime) && ! DeductionWaiver::isWaived($r, 'early_out')) {
                    $row['status'] = 'Early Out';
                    $row['tags'][] = 'early-out';
                    $row['timeline'] = 'Early Out';
                    $row['timeline_class'] = 'timeline-late';
                } else {
                    $row['status'] = 'Present';
                    $row['timeline'] = 'On Time';
                    $row['timeline_class'] = 'timeline-on-time';
                }
            }
        } elseif ($holiday) {
            $row['status'] = 'Holiday';
            $row['timeline'] = $holiday->name;
        } elseif ($date->gt(now()->startOfDay()) || $dateStr < Employee::ATTENDANCE_START_DATE) {
            $row['status'] = ''; // future din, skip
        } else {
            $row['status'] = 'Absent';
            $row['tags'] = ['absent'];
            $row['timeline'] = 'No Punch';
            $row['timeline_class'] = 'timeline-absent';
        }

        // Approved Work From Home day: portal check-in, or approved but never checked in
        $wfhLabel = WfhRequest::dayLabel($r, $isApprovedWfhDay);
        if ($wfhLabel && ! in_array($row['status'], ['', 'Weekend', 'Holiday', 'On Leave'], true)) {
            $row['timeline'] = $row['status'] === 'Absent' ? $wfhLabel : $row['timeline'].' · '.$wfhLabel;
            $row['wfh'] = $wfhLabel;
        }

        return $row;
    }
}
