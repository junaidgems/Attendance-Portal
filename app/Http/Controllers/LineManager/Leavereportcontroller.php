<?php

namespace App\Http\Controllers\LineManager;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LeaveReportController extends Controller
{
    /**
     * Logged-in line manager ka apna Employee record nikalo
     */
    private function currentManager()
    {
        return Employee::where('user_id', Auth::id())->firstOrFail();
    }

    public function report(Request $request)
    {
        $manager = $this->currentManager();

        // Sirf isi manager ke reports_to_id wale employees (uske team members)
        $teamEmployeeIds = Employee::where('reports_to_id', $manager->id)->pluck('id');

        $year = $request->input('year', now()->year);
        $from = $request->input('from', $year.'-01-01');
        $to = $request->input('to', now()->format('Y-m-d'));
        $leaveType = $request->input('leave_type', 'all');
        $status = $request->input('status', 'all');
        $search = $request->input('search');

        // ===== Summary cards (sirf team ka data) =====
        $baseQuery = LeaveRequest::whereIn('employee_id', $teamEmployeeIds)
            ->whereBetween('from_date', [$from, $to]);

        $summary = [
            'approved_days' => (clone $baseQuery)->where('status', 'approved')->sum('days'),
            'pending_requests' => (clone $baseQuery)->where('status', 'pending')->count(),
            'rejected_requests' => (clone $baseQuery)->where('status', 'rejected')->count(),
            'on_leave_today' => LeaveRequest::whereIn('employee_id', $teamEmployeeIds)
                ->approvedAbsence()
                ->whereDate('from_date', '<=', now())
                ->whereDate('to_date', '>=', now())
                ->count(),
        ];

        // ===== Leave Balances (sirf team members) =====
        $teamEmployees = Employee::with('department')
            ->whereIn('id', $teamEmployeeIds)
            ->where('employment_status', 'active')
            ->orderBy('first_name')
            ->get();

        $annualType = LeaveType::where('name', 'Annual Leave')->first();
        $sickType = LeaveType::where('name', 'Sick Leave')->first();
        $casualType = LeaveType::where('name', 'Casual Leave')->first();

        $balances = [];
        foreach ($teamEmployees as $emp) {
            $usedFor = fn ($typeId) => $typeId
                ? LeaveRequest::where('employee_id', $emp->id)->where('leave_type_id', $typeId)->where('status', 'approved')->inLeaveYear()->sum('days')
                : 0;

            $annualUsed = $usedFor($annualType->id ?? null);
            $sickUsed = $usedFor($sickType->id ?? null);
            $casualUsed = $usedFor($casualType->id ?? null);

            $balances[] = [
                'id' => $emp->employee_id,
                'employee' => $emp->first_name.' '.$emp->last_name,
                'dept' => $emp->department->name ?? '-',
                'annual' => ['used' => $annualUsed, 'total' => $emp->leaveAllowance($annualType)],
                'sick' => ['used' => $sickUsed, 'total' => $emp->leaveAllowance($sickType)],
                'casual' => ['used' => $casualUsed, 'total' => $emp->leaveAllowance($casualType)],
                'total_taken' => $annualUsed + $sickUsed + $casualUsed,
                'probation' => $emp->isOnProbation(),
            ];
        }

        // ===== Leave Register =====
        $query = LeaveRequest::with(['employee.department', 'leaveType'])
            ->whereIn('employee_id', $teamEmployeeIds)
            ->whereBetween('from_date', [$from, $to]);

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        if ($leaveType !== 'all') {
            $query->whereHas('leaveType', fn ($q) => $q->where('name', $leaveType));
        }

        if ($search) {
            $query->whereHas('employee', function ($q) use ($search) {
                $q->where('first_name', 'like', "%$search%")
                    ->orWhere('last_name', 'like', "%$search%")
                    ->orWhere('employee_id', 'like', "%$search%");
            });
        }

        $registerRaw = $query->orderBy('from_date', 'desc')->get();

        $register = $registerRaw->map(fn ($r) => [
            'id' => $r->employee->employee_id ?? '-',
            'employee' => $r->employee ? ($r->employee->first_name.' '.$r->employee->last_name) : '-',
            'type' => $r->leaveType->name ?? '-',
            'from' => $r->from_date,
            'to' => $r->to_date,
            'days' => $r->days,
            'status' => $r->status,
            'reason' => $r->reason,
            'applied' => $r->created_at,
        ])->toArray();

        return view('attendence.pages.line-manager.leave-report', compact(
            'summary', 'balances', 'register', 'manager', 'year', 'from', 'to', 'leaveType', 'status', 'search'
        ));
    }
}
