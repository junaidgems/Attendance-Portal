<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\MonthlyLock;
use App\Models\PortalNotification;
use App\Models\WfhRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class LeaveController extends Controller
{
    public function index()
    {
        $leaveTypesRaw = LeaveType::withCount('requests')->orderBy('name')->get();

        $leaveTypes = $leaveTypesRaw->map(fn ($t) => [
            'id' => $t->id,
            'name' => $t->name,
            'days' => $t->days_per_year,
            'color' => $t->color,
            'in_use' => $t->requests_count,
            'is_system' => $t->isSystemType(),
            'max_per_month' => $t->max_days_per_month,
            'min_days' => $t->min_days_per_request,
            'requires_document' => $t->requires_document,
            'document_from' => $t->document_required_from_days,
            'rules' => $t->rulesSummary(),
        ])->toArray();

        $requests = LeaveRequest::with(['employee.department', 'employee.shift', 'employee.gracePolicy', 'leaveType', 'manager'])->orderBy('created_at', 'desc')->get();
        $settings = DB::table('attendance_settings')->first();

        $leaveRequests = $requests->map(fn ($r) => [
            // Approved half day but the punches show a full working day → Admin may reject it
            'full_day_worked' => $r->status === 'approved' ? $r->fullDayWorkedMinutes($settings) : null,
            'id' => $r->id,
            'employee' => $r->employee ? ($r->employee->first_name.' '.$r->employee->last_name) : '-',
            'department' => $r->employee->department->name ?? '-',
            'employee_id' => $r->employee_id,
            'leave_type_id' => $r->leave_type_id,
            'admin_note' => $r->admin_note,
            'type' => $r->leaveType->name ?? '-',
            'from' => $r->from_date,
            'to' => $r->to_date,
            'days' => $r->days,
            'is_half_day' => $r->is_half_day,
            'half_day_session' => $r->half_day_session,
            'status' => $r->status,
            'reason' => $r->reason,
            'document_url' => $r->document_path ? route('leave.document', $r->id) : null,
            'reviewed_at' => $r->reviewed_at,
            'awaiting_manager' => $r->isAwaitingManager(),
            // A Line Manager's / Manager's own request: Admin may decide before their reviewer does
            'admin_can_decide' => $r->adminMayDecide(),
            'stage' => $r->stageLabel(),
            'manager_name' => $r->manager?->full_name,
            'manager_status' => $r->manager_status,
            'manager_note' => $r->manager_note,
        ])->toArray();

        $employees = Employee::where('employment_status', 'active')->orderBy('first_name')->get();

        return view('attendence.pages.admin.leaves', compact('leaveTypes', 'leaveTypesRaw', 'leaveRequests', 'employees'));
    }

    // ===== Leave Types CRUD =====

    public function storeType(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:100|unique:leave_types,name',
            'days_per_year' => 'required|integer|min:0',
            'color' => 'required|string|max:7',
            'max_days_per_month' => 'nullable|numeric|min:0.5|max:31',
            'min_days_per_request' => 'nullable|numeric|min:0.5|max:365',
            'requires_document' => 'nullable|boolean',
            'document_required_from_days' => 'nullable|numeric|min:0.5|max:365',
        ], [
            'name.unique' => 'A leave type with this name already exists.',
        ]);

        LeaveType::create($request->only('name', 'days_per_year', 'color') + $this->leaveRules($request));

        return redirect()->back()->with('success', 'Leave type added successfully!');
    }

    public function updateType(Request $request, $id)
    {
        $request->validate([
            'name' => 'required|string|max:100|unique:leave_types,name,'.(int) $id,
            'days_per_year' => 'required|integer|min:0',
            'max_days_per_month' => 'nullable|numeric|min:0.5|max:31',
            'min_days_per_request' => 'nullable|numeric|min:0.5|max:365',
            'requires_document' => 'nullable|boolean',
            'document_required_from_days' => 'nullable|numeric|min:0.5|max:365',
        ], [
            'name.unique' => 'A leave type with this name already exists.',
        ]);

        $leaveType = LeaveType::findOrFail($id);

        // Penalties and the dashboards find these by name
        if ($leaveType->isSystemType() && $request->name !== $leaveType->name) {
            return redirect()->back()->with('error', "\"{$leaveType->name}\" is a system leave type and cannot be renamed. You can still change its days per year.");
        }

        $leaveType->update($request->only('name', 'days_per_year') + $this->leaveRules($request));

        return redirect()->back()->with('success', 'Leave type updated successfully!');
    }

    /**
     * Leave policy rules from the leave type form (an empty field means no limit).
     *
     * @return array{max_days_per_month: ?float, min_days_per_request: ?float, requires_document: bool, document_required_from_days: ?float}
     */
    private function leaveRules(Request $request): array
    {
        return [
            'max_days_per_month' => $request->filled('max_days_per_month') ? (float) $request->max_days_per_month : null,
            'min_days_per_request' => $request->filled('min_days_per_request') ? (float) $request->min_days_per_request : null,
            'requires_document' => $request->boolean('requires_document'),
            'document_required_from_days' => $request->boolean('requires_document') && $request->filled('document_required_from_days')
                ? (float) $request->document_required_from_days
                : null,
        ];
    }

    public function destroyType($id)
    {
        $leaveType = LeaveType::withCount('requests')->findOrFail($id);

        if ($leaveType->isSystemType()) {
            return redirect()->back()->with('error', "\"{$leaveType->name}\" is a system leave type and cannot be deleted.");
        }

        // Its leave requests would lose their type
        if ($leaveType->requests_count > 0) {
            return redirect()->back()->with('error', "\"{$leaveType->name}\" is used by {$leaveType->requests_count} leave request(s) and cannot be deleted.");
        }

        $leaveType->delete();

        return redirect()->back()->with('success', 'Leave type deleted!');
    }

    // ===== Leave Requests approve/reject =====

    /**
     * Admin final approval, only after the Line Manager has approved (or when no Line Manager approval is required).
     */
    public function approveRequest(Request $request, $id)
    {
        $request->validate(['note' => 'nullable|string|max:500']);

        $leaveRequest = LeaveRequest::findOrFail($id);

        if ($leaveRequest->isDirectorDecision()) {
            return redirect()->back()->with('error', "This is a Manager's request decided by their Director. Admin cannot change it.");
        }

        // Payroll-locked month: no changes
        MonthlyLock::ensureOpen($leaveRequest->from_date, $leaveRequest->to_date);

        if (! $leaveRequest->adminMayDecide()) {
            return redirect()->back()->with('error', $this->notReadyMessage($leaveRequest));
        }

        if ($message = $this->wfhConflictMessage($leaveRequest)) {
            return redirect()->back()->with('error', $message);
        }

        $leaveRequest->update([
            'status' => 'approved',
            'admin_note' => $request->note,
            'reviewed_at' => now(),
            'reviewed_by' => Auth::user()->name,
        ] + $this->skipReviewFields($leaveRequest));

        PortalNotification::notifyEmployee($leaveRequest->employee, 'leave', 'Leave request approved',
            "Your {$leaveRequest->summary()} has been approved.".($request->note ? " Note: {$request->note}" : ''),
            'leave', "leave:decision:{$leaveRequest->id}");

        $this->notifyLeaveReviewer($leaveRequest, 'approved', $request->note);

        return redirect()->back()->with('success', 'Leave request approved!');
    }

    public function rejectRequest(Request $request, $id)
    {
        $request->validate(['reason' => 'required|string|max:500']);

        $leaveRequest = LeaveRequest::findOrFail($id);

        if ($leaveRequest->isDirectorDecision()) {
            return redirect()->back()->with('error', "This is a Manager's request decided by their Director. Admin cannot change it.");
        }

        // Payroll-locked month: no changes
        MonthlyLock::ensureOpen($leaveRequest->from_date, $leaveRequest->to_date);

        if (! $leaveRequest->adminMayDecide()) {
            return redirect()->back()->with('error', $this->notReadyMessage($leaveRequest));
        }

        $leaveRequest->update([
            'status' => 'rejected',
            'admin_note' => $request->reason,
            'reviewed_at' => now(),
            'reviewed_by' => Auth::user()->name,
        ] + $this->skipReviewFields($leaveRequest));

        PortalNotification::notifyEmployee($leaveRequest->employee, 'leave', 'Leave request rejected',
            "Your {$leaveRequest->summary()} was rejected: \"{$request->reason}\"",
            'leave', "leave:decision:{$leaveRequest->id}");

        $this->notifyLeaveReviewer($leaveRequest, 'rejected', $request->reason);

        return redirect()->back()->with('success', 'Leave request rejected!');
    }

    /**
     * Decided by Admin while the request was still with the Line Manager / Manager / Director: their step is no longer needed.
     *
     * @return array<string, string>
     */
    private function skipReviewFields(LeaveRequest $leaveRequest): array
    {
        return $leaveRequest->isAwaitingManager() ? ['manager_status' => LeaveRequest::MANAGER_NOT_REQUIRED] : [];
    }

    /**
     * Tell the Line Manager / Manager / Director who approved the leave (or still had it in their queue)
     * about Admin's final decision (with the note).
     */
    private function notifyLeaveReviewer(LeaveRequest $leaveRequest, string $decision, ?string $note): void
    {
        $reviewer = $leaveRequest->manager;

        if (! $reviewer?->user_id || $leaveRequest->manager_status === LeaveRequest::MANAGER_REJECTED) {
            return;
        }

        PortalNotification::sendToUsers([$reviewer->user_id], 'leave', "Leave {$decision} by Admin",
            "Admin {$decision} {$leaveRequest->employee?->full_name}'s {$leaveRequest->summary()}.".($note ? " Admin note: {$note}" : ''),
            route($reviewer->portalPrefix().'.approvals'), "leave:admin-decision:{$leaveRequest->id}");
    }

    /**
     * Leave cannot be approved on a day the employee has approved Work From Home.
     */
    private function wfhConflictMessage(LeaveRequest $leaveRequest): ?string
    {
        $wfh = WfhRequest::overlappingFor($leaveRequest->employee_id, $leaveRequest->from_date, $leaveRequest->to_date, ['approved']);

        return $wfh
            ? "This employee has approved Work From Home for {$wfh->dateLabel()}. Reject that Work From Home first, then approve the leave."
            : null;
    }

    private function notReadyMessage(LeaveRequest $leaveRequest): string
    {
        return $leaveRequest->isAwaitingManager()
            ? 'This request is still awaiting Line Manager review.'
            : 'A decision has already been made on this request.';
    }

    public function updateRequest(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:pending,approved,rejected',
            'admin_note' => 'nullable|string|max:500',
        ]);

        $leaveRequest = LeaveRequest::findOrFail($id);

        if ($leaveRequest->isDirectorDecision()) {
            return redirect()->back()->with('error', "This is a Manager's request decided by their Director. Admin cannot change it.");
        }

        // Payroll-locked month: no changes
        MonthlyLock::ensureOpen($leaveRequest->from_date, $leaveRequest->to_date);

        if ($request->status !== 'pending' && $leaveRequest->isAwaitingManager()) {
            return redirect()->back()->with('error', 'This request is still awaiting Line Manager review.');
        }

        if ($request->status === 'approved' && ($message = $this->wfhConflictMessage($leaveRequest))) {
            return redirect()->back()->with('error', $message);
        }

        $leaveRequest->update([
            'status' => $request->status,
            'admin_note' => $request->filled('admin_note') ? $request->admin_note : $leaveRequest->admin_note,
            'reviewed_at' => in_array($request->status, ['approved', 'rejected']) ? now() : $leaveRequest->reviewed_at,
            'reviewed_by' => in_array($request->status, ['approved', 'rejected']) ? Auth::user()->name : $leaveRequest->reviewed_by,
            // Re-opened by Admin after a Line Manager rejection: it now waits for Admin, instead of
            // sitting as "pending" where neither the Line Manager nor Admin can act on it
            'manager_status' => $request->status === 'pending' && $leaveRequest->manager_status === LeaveRequest::MANAGER_REJECTED
                ? LeaveRequest::MANAGER_NOT_REQUIRED
                : $leaveRequest->manager_status,
        ]);

        if (in_array($request->status, ['approved', 'rejected'], true)) {
            // Status changed later by Admin: a new key per change so the employee is told every time
            PortalNotification::notifyEmployee($leaveRequest->employee, 'leave', 'Leave request '.$request->status,
                "Your {$leaveRequest->summary()} was changed to {$request->status} by Admin.".($request->admin_note ? " Note: {$request->admin_note}" : ''),
                'leave', "leave:update:{$leaveRequest->id}:".now()->timestamp);
        }

        return redirect()->back()->with('success', 'Leave request status updated!');
    }

    public function destroyRequest($id)
    {
        $leaveRequest = LeaveRequest::findOrFail($id);

        if ($leaveRequest->isDirectorDecision()) {
            return redirect()->back()->with('error', "This is a Manager's request decided by their Director. Admin cannot change it.");
        }

        // Payroll-locked month: no changes
        MonthlyLock::ensureOpen($leaveRequest->from_date, $leaveRequest->to_date);

        $leaveRequest->delete();

        return redirect()->back()->with('success', 'Leave request deleted!');
    }

    // ===== Leave Report =====

    public function report(Request $request)
    {
        $year = $request->input('year', now()->year);
        $from = $request->input('from', $year.'-01-01');
        $to = $request->input('to', now()->format('Y-m-d'));
        $leaveType = $request->input('leave_type', 'all');
        $status = $request->input('status', 'all');
        $department = $request->input('department', 'all');
        $search = $request->input('search');

        // ===== Summary cards =====
        $baseQuery = LeaveRequest::whereBetween('from_date', [$from, $to]);

        $summary = [
            'approved_days' => (clone $baseQuery)->where('status', 'approved')->sum('days'),
            'pending_requests' => (clone $baseQuery)->where('status', 'pending')->count(),
            'rejected_requests' => (clone $baseQuery)->where('status', 'rejected')->count(),
            'on_leave_today' => LeaveRequest::approvedAbsence()
                ->whereDate('from_date', '<=', now())
                ->whereDate('to_date', '>=', now())
                ->count(),
        ];

        // ===== Leave Balances (per employee, per leave type) =====
        $employees = Employee::with('department')
            ->where('employment_status', 'active')
            ->orderBy('first_name')
            ->get();

        $annualType = LeaveType::where('name', 'Annual Leave')->first();
        $sickType = LeaveType::where('name', 'Sick Leave')->first();
        $casualType = LeaveType::where('name', 'Casual Leave')->first();

        $balances = [];
        foreach ($employees as $emp) {
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

        // ===== Leave Register (filtered list) =====
        $query = LeaveRequest::with(['employee.department', 'leaveType'])
            ->whereBetween('from_date', [$from, $to]);

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        if ($leaveType !== 'all') {
            $query->whereHas('leaveType', fn ($q) => $q->where('name', $leaveType));
        }

        if ($department !== 'all') {
            $query->whereHas('employee.department', fn ($q) => $q->where('name', $department));
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
            'applied' => $r->created_at,
        ])->toArray();

        return view('attendence.pages.admin.leave-report', compact('summary', 'balances', 'register'));
    }

    public function filter(Request $request)
    {
        return redirect()->route('admin.leave.report', $request->all());
    }

    // ===== EXPORT BALANCES (CSV) =====
    public function exportBalances(Request $request)
    {
        $balances = $this->buildBalances();

        $filename = 'leave-balances-'.now()->format('Y-m-d').'.csv';
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ];

        $callback = function () use ($balances) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['Employee ID', 'Employee', 'Department', 'Annual Used', 'Annual Total', 'Sick Used', 'Sick Total', 'Casual Used', 'Casual Total', 'Total Taken']);

            foreach ($balances as $b) {
                fputcsv($file, [
                    $b['id'],
                    $b['employee'],
                    $b['dept'],
                    $b['annual']['used'],
                    $b['annual']['total'],
                    $b['sick']['used'],
                    $b['sick']['total'],
                    $b['casual']['used'],
                    $b['casual']['total'],
                    $b['total_taken'],
                ]);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    // ===== EXPORT REGISTER (CSV) =====
    public function exportRegister(Request $request)
    {
        $year = $request->input('year', now()->year);
        $from = $request->input('from', $year.'-01-01');
        $to = $request->input('to', now()->format('Y-m-d'));
        $leaveType = $request->input('leave_type', 'all');
        $status = $request->input('status', 'all');
        $department = $request->input('department', 'all');
        $search = $request->input('search');

        $register = $this->buildRegister($from, $to, $leaveType, $status, $department, $search);

        $filename = 'leave-register-'.now()->format('Y-m-d').'.csv';
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ];

        $callback = function () use ($register) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['Employee ID', 'Employee', 'Type', 'From', 'To', 'Days', 'Status', 'Applied']);

            foreach ($register as $r) {
                fputcsv($file, [$r['id'], $r['employee'], $r['type'], $r['from'], $r['to'], $r['days'], $r['status'], $r['applied']]);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    // ===== PRIVATE HELPERS (reused by report() and export methods) =====

    private function buildBalances()
    {
        $employees = Employee::with('department')
            ->where('employment_status', 'active')
            ->orderBy('first_name')
            ->get();

        $annualType = LeaveType::where('name', 'Annual Leave')->first();
        $sickType = LeaveType::where('name', 'Sick Leave')->first();
        $casualType = LeaveType::where('name', 'Casual Leave')->first();

        $balances = [];
        foreach ($employees as $emp) {
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

        return $balances;
    }

    private function buildRegister($from, $to, $leaveType, $status, $department, $search)
    {
        $query = LeaveRequest::with(['employee.department', 'leaveType'])
            ->whereBetween('from_date', [$from, $to]);

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        if ($leaveType !== 'all') {
            $query->whereHas('leaveType', fn ($q) => $q->where('name', $leaveType));
        }

        if ($department !== 'all') {
            $query->whereHas('employee.department', fn ($q) => $q->where('name', $department));
        }

        if ($search) {
            $query->whereHas('employee', function ($q) use ($search) {
                $q->where('first_name', 'like', "%$search%")
                    ->orWhere('last_name', 'like', "%$search%")
                    ->orWhere('employee_id', 'like', "%$search%");
            });
        }

        return $query->orderBy('from_date', 'desc')->get()->map(fn ($r) => [
            'id' => $r->employee->employee_id ?? '-',
            'employee' => $r->employee ? ($r->employee->first_name.' '.$r->employee->last_name) : '-',
            'type' => $r->leaveType->name ?? '-',
            'from' => $r->from_date,
            'to' => $r->to_date,
            'days' => $r->days,
            'status' => $r->status,
            'applied' => $r->created_at,
        ])->toArray();
    }
}
