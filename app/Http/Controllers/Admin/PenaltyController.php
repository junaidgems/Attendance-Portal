<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DeductionWaiver;
use App\Models\Department;
use App\Models\Employee;
use App\Models\LeaveType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PenaltyController extends Controller
{
    public function index(Request $request)
    {
        return view('attendence.pages.admin.penalty', $this->buildData($request));
    }

    public function export(Request $request)
    {
        $data = $this->buildData($request);

        $filename = 'penalties-'.$data['from'].'-to-'.$data['to'].'.csv';
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ];

        $callback = function () use ($data) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['Employee ID', 'Employee', 'Department', 'Late', 'Early Out', 'Half Days', 'Absent', 'Waivers', 'CL Due', 'CL Balance', 'CL Deducted', 'Salary Days']);
            foreach ($data['employees'] as $e) {
                fputcsv($file, [$e['id'], $e['employee'], $e['dept'], $e['late'], $e['early_out'], $e['half_days'], $e['absent'], $e['waivers'], $e['cl_due'], $e['cl_balance'], $e['cl_deducted'], $e['salary_days']]);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildData(Request $request): array
    {
        $settings = DB::table('attendance_settings')->first();
        [$cycleStart] = Employee::attendanceCycle(now(), $settings);

        $from = $request->input('from', $cycleStart->toDateString());
        $to = $request->input('to', now()->toDateString());
        $department = $request->input('department', 'all');
        $search = $request->input('search');
        $departments = Department::orderBy('name')->get();
        $casualType = LeaveType::where('name', 'Casual Leave')->first();

        $query = Employee::with(['department', 'shift', 'gracePolicy'])->where('employment_status', 'active');

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

        $employees = [];

        foreach ($query->orderBy('first_name')->get() as $emp) {
            if (! $emp->zkteco_uid) {
                continue;
            }

            $summary = $emp->penaltySummary($from, $to, $settings);

            if ($summary['items']->isEmpty()) {
                continue;
            }

            $approvedWaivers = $summary['items']->where('waiver_status', 'approved')->count();
            $pendingWaivers = $summary['items']->where('waiver_status', 'pending')->count();

            $clDeducted = $emp->leaveRequests()
                ->where('is_penalty', true)
                ->whereBetween('from_date', [$from, $to])
                ->sum('days');

            $clUsed = $casualType
                ? (float) $emp->leaveRequests()->where('leave_type_id', $casualType->id)->where('status', 'approved')->inLeaveYear()->sum('days')
                : 0;

            $employees[] = [
                'id' => $emp->employee_id,
                'employee' => $emp->full_name,
                'dept' => $emp->department->name ?? '-',
                'late' => $summary['counts']['late'],
                'early_out' => $summary['counts']['early_out'],
                'half_days' => $summary['counts']['half_day'],
                'absent' => $summary['counts']['absent'],
                'waivers' => $pendingWaivers > 0 ? 'Pending' : ($approvedWaivers > 0 ? $approvedWaivers : '—'),
                'cl_due' => $summary['cl_due'],
                'cl_balance' => max(0, $emp->leaveAllowance($casualType) - $clUsed),
                'cl_deducted' => $clDeducted,
                'salary_days' => $settings?->penalty_policy_enabled ? max(0, $summary['cl_due'] - $clDeducted) : 0,
            ];
        }

        $summary = [
            'penalties' => count(array_filter($employees, fn ($e) => $e['cl_due'] > 0)),
            'cl_to_deduct' => array_sum(array_column($employees, 'cl_deducted')),
            'salary_deductions' => count(array_filter($employees, fn ($e) => $e['salary_days'] > 0)),
            'waivers_pending' => DeductionWaiver::where('status', 'pending')->whereBetween('date', [$from, $to])->count(),
        ];

        return [
            'employees' => $employees,
            'summary' => $summary,
            'from' => $from,
            'to' => $to,
            'department' => $department,
            'search' => $search,
            'departments' => $departments,
            'penaltyEnabled' => (bool) $settings?->penalty_policy_enabled,
            'thresholds' => [
                'late' => (int) ($settings->late_arrivals_per_casual_leave ?? 3),
                'early_out' => (int) ($settings->early_outs_per_casual_leave ?? 3),
                'half_day' => (int) ($settings->half_days_per_casual_leave ?? 2),
                'absent' => (int) ($settings->absent_days_per_casual_leave ?? 0),
            ],
        ];
    }
}
