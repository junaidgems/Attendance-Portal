<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Department;
use App\Models\Employee;
use App\Models\PortalNotification;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AttendanceAuditController extends Controller
{
    public function index(Request $request)
    {
        $from = max($request->input('from', now()->subDays(7)->format('Y-m-d')), Employee::ATTENDANCE_START_DATE);
        $to = $request->input('to', now()->format('Y-m-d'));
        $department = $request->input('department', 'all');
        $status = $request->input('status', 'all');
        $search = $request->input('search');
        $employee = $request->input('employee', 'all');

        $rows = $this->buildRows($from, $to, $department, $status, $search, $employee);

        return view('attendence.pages.admin.attendance-audit', [
            'rows' => $rows,
            'from' => $from,
            'to' => $to,
            'department' => $department,
            'status' => $status,
            'search' => $search,
            'employee' => $employee,
            'departments' => Department::orderBy('name')->get(),
            // Employee dropdown: every active employee (narrowed by department in the page)
            'employees' => Employee::with('department')
                ->where('employment_status', 'active')
                ->orderBy('first_name')
                ->orderBy('last_name')
                ->get(),
        ]);
    }

    public function export(Request $request)
    {
        $from = max($request->input('from', now()->subDays(7)->format('Y-m-d')), Employee::ATTENDANCE_START_DATE);
        $to = $request->input('to', now()->format('Y-m-d'));
        $department = $request->input('department', 'all');
        $status = $request->input('status', 'all');
        $search = $request->input('search');
        $employee = $request->input('employee', 'all');

        $rows = $this->buildRows($from, $to, $department, $status, $search, $employee);

        $filename = 'attendance-audit-'.now()->format('Y-m-d').'.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ];

        $callback = function () use ($rows) {
            $file = fopen('php://output', 'w');
            fputcsv($file, [
                '#', 'Emp ID', 'Employee', 'Department', 'Designation', 'Date', 'Shift',
                'Sch In', 'Sch Out', 'First In', 'Last Out', 'Total Hrs',
                'Late (min)', 'Early Leave (min)', 'OT (hrs)', 'Status', 'Verification', 'Remarks',
            ]);

            foreach ($rows as $row) {
                fputcsv($file, [
                    $row['#'], $row['emp_id'], $row['employee'], $row['dept'], $row['designation'],
                    $row['date'], $row['shift'], $row['sch_in'], $row['sch_out'],
                    $row['first_in'], $row['last_out'], $row['total_hrs'],
                    $row['late_min'], $row['early_min'], $row['ot_hrs'],
                    $row['status'], $row['verification'], $row['remarks'],
                ]);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Admin informs an employee (bell + email) that they were absent on a day without a punch or an approved leave,
     * optionally with a copy to their Line Manager / Manager.
     */
    public function informAbsent(Request $request)
    {
        $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'date' => 'required|date',
            'message' => 'required|string|max:1000',
            'copy_manager' => 'nullable|boolean',
        ]);

        $employee = Employee::with('manager')->findOrFail($request->employee_id);

        if (! $employee->user_id) {
            return redirect()->back()->with('error', "{$employee->full_name} has no portal account, so no email can be sent.");
        }

        $date = Carbon::parse($request->date)->format('d M Y');
        $key = "absent:{$employee->id}:{$request->date}:".now()->timestamp;

        PortalNotification::notifyEmployee($employee, 'attendance', "Absent on {$date}", $request->message, 'attendance', $key);

        $copied = '';
        if ($request->boolean('copy_manager') && $employee->manager?->user_id) {
            PortalNotification::sendToUsers([$employee->manager->user_id], 'attendance', "{$employee->full_name} absent on {$date}",
                "Admin informed {$employee->full_name} about being absent on {$date}: \"{$request->message}\"",
                route($employee->manager->portalPrefix().'.team.attendance'), $key.':manager');
            $copied = " and {$employee->manager->full_name}";
        }

        AuditLog::record('attendance', 'Absent employee informed', "{$employee->full_name} · {$date}");

        return redirect()->back()->with('success', "{$employee->full_name}{$copied} informed by email and portal notification.");
    }

    /**
     * One row per employee per day, with the same rules as All Attendance, the dashboards and the
     * breakdown (shift + Saturday timings, approved leave, waivers, corrections, WFH, today in progress).
     * Days before Employee::ATTENDANCE_START_DATE have no data and are not listed.
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildRows($from, $to, $department, $status, $search, $employee = 'all'): array
    {
        $settings = DB::table('attendance_settings')->first();
        $holidays = DB::table('holidays')->get();

        $from = max(Carbon::parse($from)->toDateString(), Employee::ATTENDANCE_START_DATE);
        $to = min(Carbon::parse($to)->toDateString(), now()->toDateString());
        if ($from > $to) {
            return [];
        }

        $query = Employee::with(['department', 'shift', 'gracePolicy'])
            ->where('employment_status', 'active');

        if ($employee !== 'all') {
            $query->where('id', (int) $employee);
        }

        if ($department !== 'all') {
            $query->whereHas('department', fn ($q) => $q->where('name', $department));
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%$search%")
                    ->orWhere('last_name', 'like', "%$search%")
                    ->orWhere('employee_id', 'like', "%$search%");
            });
        }

        $rows = [];
        $rowNum = 1;

        foreach ($query->orderBy('first_name')->get() as $emp) {
            foreach ($emp->attendanceDays($from, $to, $settings, $holidays) as $date => $day) {
                if ($day['status'] === '' || ($status !== 'all' && $day['status'] !== $status)) {
                    continue;
                }

                $r = $day['record'];
                $times = $emp->shiftTimesOn($date, $settings);
                $hasPunches = $r?->check_in && $day['status'] !== 'On Leave';
                $punctuality = $hasPunches
                    ? Employee::punctuality($emp, $date, $r->check_in, $r->check_out, $settings)
                    : ['late_minutes' => 0, 'early_out_minutes' => 0];

                $in = $hasPunches ? Carbon::parse($date.' '.$r->check_in) : null;
                $out = $hasPunches && $r->check_out ? Carbon::parse($date.' '.$r->check_out) : null;
                $worked = $in && $out && $out->gt($in) ? (int) $in->diffInMinutes($out) : 0;
                $shiftEnd = Carbon::parse($date.' '.$times['end']);
                $holiday = $holidays->first(fn ($h) => $date >= $h->from_date && $date <= ($h->to_date ?? $h->from_date));

                $rows[] = [
                    '#' => $rowNum++,
                    'employee_key' => $emp->id,
                    'date_iso' => $date,
                    'emp_id' => $emp->employee_id,
                    'employee' => $emp->first_name.' '.$emp->last_name,
                    'dept' => $emp->department->name ?? '-',
                    'designation' => $emp->job_title,
                    'date' => Carbon::parse($date)->format('d-M-Y'),
                    'shift' => $emp->shift->name ?? 'Standard Shift',
                    'sch_in' => Carbon::parse($times['start'])->format('H:i'),
                    'sch_out' => Carbon::parse($times['end'])->format('H:i'),
                    'first_in' => $in ? $in->format('H:i') : '—',
                    'last_out' => match (true) {
                        ! $hasPunches => '—',
                        (bool) $out && $out->gt($in) => $out->format('H:i'),
                        $day['in_progress'] => 'In progress',
                        default => 'Missing',
                    },
                    'total_hrs' => $worked > 0 ? round($worked / 60, 1) : '—',
                    'late_min' => $punctuality['late_minutes'],
                    'early_min' => $punctuality['early_out_minutes'],
                    'ot_hrs' => $out && $out->gt($shiftEnd) ? round((int) $shiftEnd->diffInMinutes($out) / 60, 1) : 0,
                    'status' => $day['status'],
                    'verification' => match (true) {
                        ! $hasPunches => '—',
                        ($r->source ?? null) === 'wfh' => 'Portal (Work From Home)',
                        (bool) ($r->adjustment ?? null) => 'Adjusted by '.($r->adjusted_by ?: 'Admin'),
                        default => 'Biometric Verified',
                    },
                    'remarks' => match ($day['status']) {
                        'Weekend' => 'Weekly off',
                        'Holiday' => $holiday->name ?? 'Holiday',
                        'On Leave' => 'Approved leave',
                        'Absent' => $day['wfh'] ?: 'No attendance record',
                        default => implode(' · ', array_filter([$day['in_progress'] ? 'In progress' : null, $day['waived'] ? implode(', ', $day['waived']).' waived' : null, $day['wfh'], $day['note']])) ?: '—',
                    },
                ];
            }
        }

        return $rows;
    }
}
