<?php

namespace App\Http\Controllers\Director;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Employee;
use App\Models\LeaveRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Attendance cycle summary per employee (present, late, early out, half day, absent, leave, WFH,
 * penalty leave), for the whole company or one department.
 */
class ReportController extends Controller
{
    /**
     * Columns of the summary, in order: key => heading.
     */
    private const COLUMNS = [
        'working_days' => 'Working Days',
        'present' => 'Present',
        'late' => 'Late',
        'early_out' => 'Early Out',
        'half_day' => 'Half Day',
        'missing_punch' => 'Missing Punch',
        'absent' => 'Absent',
        'on_leave' => 'On Leave',
        'wfh' => 'WFH',
        'penalty_leave' => 'Penalty Leave',
    ];

    public function index(Request $request): View
    {
        return view('attendence.pages.director.reports', $this->buildReport($request) + [
            'columns' => self::COLUMNS,
            'departments' => Department::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $report = $this->buildReport($request);

        return response()->streamDownload(function () use ($report) {
            $file = fopen('php://output', 'w');
            fputcsv($file, array_merge(['Emp ID', 'Employee', 'Department'], array_values(self::COLUMNS)));
            foreach ($report['rows'] as $row) {
                fputcsv($file, array_merge([$row['emp_id'], $row['name'], $row['department']], array_map(fn ($key) => $row[$key], array_keys(self::COLUMNS))));
            }
            fclose($file);
        }, 'attendance-report-'.$report['from'].'-to-'.$report['to'].'.csv', ['Content-Type' => 'text/csv']);
    }

    /**
     * @return array{rows: array<int, array<string, mixed>>, totals: array<string, float|int>, from: string, to: string, cycleLabel: string, offset: int, departmentId: int}
     */
    private function buildReport(Request $request): array
    {
        $settings = DB::table('attendance_settings')->first();
        $offset = max(0, min(12, $request->integer('cycle')));
        $departmentId = $request->integer('department');

        // Current attendance cycle, or one of the previous cycles (?cycle=1 = last cycle)
        [$start, $end] = Employee::attendanceCycle(now(), $settings);
        for ($i = 0; $i < $offset; $i++) {
            [$start, $end] = Employee::attendanceCycle($start->copy()->subDay(), $settings);
        }
        $to = $end->lt(now()) ? $end->copy() : now()->startOfDay();

        $employees = Employee::with(['department', 'shift', 'gracePolicy'])
            ->where('employment_status', 'active')
            ->whereDoesntHave('role', fn ($q) => $q->where('name', 'director'))
            ->when($departmentId, fn ($q) => $q->where('department_id', $departmentId))
            ->orderBy('first_name')
            ->get();

        $holidays = DB::table('holidays')->get();
        $penaltyDays = LeaveRequest::where('is_penalty', true)
            ->where('status', 'approved')
            ->whereIn('employee_id', $employees->pluck('id'))
            ->whereBetween('from_date', [$start->toDateString(), $end->toDateString()])
            ->groupBy('employee_id')
            ->selectRaw('employee_id, SUM(days) as total')
            ->pluck('total', 'employee_id');

        $rows = $employees->map(function (Employee $emp) use ($start, $to, $settings, $holidays, $penaltyDays) {
            $days = $emp->attendanceDays($start, $to, $settings, $holidays);
            $worked = $days->whereNotIn('status', ['', 'Weekend', 'Holiday']);

            return [
                'emp_id' => $emp->employee_id,
                'name' => $emp->full_name,
                'department' => $emp->department->name ?? '-',
                'working_days' => $worked->count(),
                'present' => $worked->filter(fn ($d) => $d['record']?->check_in && $d['status'] !== 'On Leave')->count(),
                'late' => $days->where('status', 'Late')->count(),
                'early_out' => $days->where('status', 'Early Out')->count(),
                'half_day' => $days->where('status', 'Half Day')->count(),
                'missing_punch' => $days->where('status', 'Missing Punch')->count(),
                'absent' => $days->where('status', 'Absent')->filter(fn ($d) => ! $d['wfh'])->count(),
                'on_leave' => $days->where('status', 'On Leave')->count(),
                'wfh' => $days->filter(fn ($d) => $d['wfh'])->count(),
                'penalty_leave' => (float) ($penaltyDays[$emp->id] ?? 0),
            ];
        })->values()->all();

        $totals = [];
        foreach (array_keys(self::COLUMNS) as $key) {
            $totals[$key] = array_sum(array_column($rows, $key));
        }

        return [
            'rows' => $rows,
            'totals' => $totals,
            'from' => $start->toDateString(),
            'to' => $end->toDateString(),
            'cycleLabel' => $start->format('d M').' – '.$end->format('d M Y'),
            'offset' => $offset,
            'departmentId' => $departmentId,
        ];
    }
}
