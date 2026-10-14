<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Department;
use App\Models\Employee;
use App\Models\MonthlyLock;
use App\Models\WfhRequest;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class AttendanceController extends Controller
{
    public function index(Request $request)
    {
        $date = $request->input('date');
        $department = $request->input('department', 'all');
        $status = $request->input('status', 'all');
        $search = $request->input('search');

        $query = DB::table('device_attendance')
            ->leftJoin('employees', 'employees.zkteco_uid', '=', 'device_attendance.uid')
            ->leftJoin('departments', 'departments.id', '=', 'employees.department_id')
            ->select(
                'device_attendance.*',
                'employees.employee_id',
                'employees.first_name',
                'employees.last_name',
                'departments.name as department_name'
            )
            ->orderBy('device_attendance.date', 'desc')
            ->orderBy('device_attendance.name');

        if ($date) {
            $query->where('device_attendance.date', $date);
        }

        if ($department !== 'all') {
            $query->where('departments.name', $department);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('device_attendance.name', 'like', "%$search%")
                    ->orWhere('employees.employee_id', 'like', "%$search%");
            });
        }

        // Status is worked out from the punches with the shift rules and approved waivers (not the raw
        // machine column), so Early Out / Half Day / Missing Punch show here and waived items disappear.
        $settings = DB::table('attendance_settings')->first();
        $employeesByUid = Employee::with(['shift', 'gracePolicy'])
            ->whereNotNull('zkteco_uid')
            ->get()
            ->keyBy(fn ($e) => (string) $e->zkteco_uid);

        $toRow = function ($row) use ($employeesByUid, $settings, &$wfhDays) {
            $net = '-';
            if ($row->check_in && $row->check_out) {
                $in = Carbon::parse($row->check_in);
                $out = Carbon::parse($row->check_out);
                if ($out->gt($in)) {
                    $diff = $in->diff($out);
                    $net = $diff->h.'h '.$diff->i.'m';
                }
            }

            $employee = $employeesByUid->get((string) $row->uid);
            $day = $employee
                ? $employee->dayStatus(Carbon::parse($row->date)->toDateString(), $row, $settings)
                : ['status' => $row->status === 'Late' ? 'Late' : 'Present', 'waived' => []];

            $displayName = $row->first_name ? ($row->first_name.' '.$row->last_name) : $row->name;

            return [
                'name' => $displayName,
                'id' => $row->employee_id ?? ('UID-'.$row->uid),
                'department' => $row->department_name ?? '-',
                'status' => $day['status'],
                'in' => $row->check_in ? Carbon::parse($row->check_in)->format('h:i A') : '-',
                'out' => $row->check_out ? Carbon::parse($row->check_out)->format('h:i A') : '-',
                'net' => $net,
                'timeline' => match (true) {
                    ! empty($day['in_progress']) => 'In Progress',
                    $day['status'] === 'Present' => 'On Time',
                    default => $day['status'],
                },
                // e.g. "Late waived" — shown under the status
                'waived' => $day['waived'] ? implode(', ', $day['waived']).' waived' : null,
                'note' => $row->adjustment_note ?? null,
                'wfh' => WfhRequest::dayLabel($row, $employee && isset($wfhDays[$employee->id.'_'.Carbon::parse($row->date)->toDateString()])),
                'date' => $row->date,
            ];
        };

        // Approved WFH days for the rows being shown
        $loadWfhDays = function ($rows) use ($employeesByUid, &$wfhDays) {
            $ids = $rows->map(fn ($r) => $employeesByUid->get((string) $r->uid)?->id)->filter()->unique();
            $wfhDays = $rows->isEmpty() ? [] : WfhRequest::approvedDayKeys($ids, $rows->min('date'), $rows->max('date'));
        };
        $wfhDays = [];

        if ($status === 'all') {
            $records = $query->paginate(25)->withQueryString();
            $loadWfhDays($records->getCollection());
            $attendances = $records->getCollection()->map($toRow);
        } else {
            // Filter on the worked-out status, then paginate the result
            $rows = $query->get();
            $loadWfhDays($rows);
            $all = $rows->map($toRow)->where('status', $status)->values();
            $page = max(1, (int) $request->input('page', 1));
            $records = (new LengthAwarePaginator($all->forPage($page, 25)->values(), $all->count(), 25, $page, [
                'path' => $request->url(),
            ]))->withQueryString();
            $attendances = $records->getCollection();
        }

        $departments = Department::select('id', 'name')->orderBy('name')->get();
        $allEmployees = Employee::where('employment_status', 'active')->orderBy('first_name')->get();

        return view('attendence.pages.admin.attendance', [
            'attendances' => $attendances,
            'records' => $records, // pagination links ke liye
            'date' => $date,
            'departments' => $departments,
            'allEmployees' => $allEmployees,
            'filters' => compact('department', 'status', 'search'),
        ]);
    }

    /**
     * Manually mark one day's attendance. The day is locked against the machine sync (same as an
     * approved correction), and the machine's original punches are kept the first time it is adjusted.
     */
    public function markAttendance(Request $request): RedirectResponse
    {
        $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'date' => 'required|date',
            'day_type' => 'required|in:present,wfh,onduty,absent',
            // Absent days are saved without punches, so the times are not checked
            'punch_in' => 'exclude_if:day_type,absent|required|date_format:H:i,H:i:s',
            'punch_out' => 'exclude_if:day_type,absent|nullable|date_format:H:i,H:i:s|after:punch_in',
            'note' => 'nullable|string|max:255',
        ], [
            'punch_in.required' => 'Punch In is required unless the day is marked Absent.',
            'punch_out.after' => 'Punch Out must be after Punch In.',
        ]);

        $employee = Employee::with(['shift', 'gracePolicy'])->findOrFail($request->employee_id);

        if (! $employee->zkteco_uid) {
            return redirect()->back()->with('error', 'This employee is not linked to a device UID, so attendance cannot be marked manually. Edit the employee and link a device first.');
        }

        $date = Carbon::parse($request->date)->toDateString();
        MonthlyLock::ensureOpen($date);

        $isAbsent = $request->day_type === 'absent';
        $checkIn = $isAbsent ? null : Carbon::parse($request->punch_in)->format('H:i:s');
        $checkOut = $isAbsent || ! $request->punch_out ? null : Carbon::parse($request->punch_out)->format('H:i:s');

        $settings = DB::table('attendance_settings')->first();
        $punctuality = Employee::punctuality($employee, $date, $checkIn, $checkOut, $settings);

        $record = DB::table('device_attendance')->where('uid', $employee->zkteco_uid)->where('date', $date)->first();

        $dayTypeLabel = match ($request->day_type) {
            'absent' => 'Absent',
            'wfh' => 'Work From Home',
            'onduty' => 'On Duty',
            default => 'Present',
        };

        DB::table('device_attendance')->updateOrInsert(
            ['uid' => $employee->zkteco_uid, 'date' => $date],
            [
                'name' => $record->name ?? $employee->full_name,
                'check_in' => $checkIn,
                'check_out' => $checkOut,
                'status' => match (true) {
                    $isAbsent => 'Absent',
                    $request->day_type === 'wfh' => 'WFH',
                    default => $punctuality['late_minutes'] > 0 ? 'Late' : 'On Time',
                },
                'late_minutes' => $punctuality['late_minutes'],
                'early_out_minutes' => $punctuality['early_out_minutes'],
                'adjustment' => 'manual',
                'adjustment_note' => $request->filled('note')
                    ? "Marked {$dayTypeLabel} by Admin: ".$request->note
                    : "Marked {$dayTypeLabel} by Admin",
                'adjusted_by' => $request->user()->name ?? 'Admin',
                'adjusted_at' => now(),
                // Keep the machine's original punches only the first time the day is adjusted
                'original_check_in' => $record?->adjustment ? $record->original_check_in : $record?->check_in,
                'original_check_out' => $record?->adjustment ? $record->original_check_out : $record?->check_out,
                'created_at' => $record->created_at ?? now(),
                'updated_at' => now(),
            ]
        );

        $punches = $isAbsent ? 'no punches' : 'In '.substr($checkIn, 0, 5).($checkOut ? ', Out '.substr($checkOut, 0, 5) : '');
        AuditLog::record('attendance', 'Attendance marked manually', "{$employee->full_name} — {$date} marked {$dayTypeLabel} ({$punches})");

        return redirect()->back()->with('success', 'Attendance marked manually.');
    }
}
