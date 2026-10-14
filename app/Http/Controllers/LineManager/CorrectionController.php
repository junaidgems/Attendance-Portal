<?php

namespace App\Http\Controllers\LineManager;

use App\Http\Controllers\Controller;
use App\Models\CorrectionRequest;
use App\Models\Employee;
use App\Models\MonthlyLock;
use App\Models\PortalNotification;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CorrectionController extends Controller
{
    private function currentEmployee()
    {
        return Employee::where('user_id', Auth::id())->firstOrFail();
    }

    public function index()
    {
        $employee = $this->currentEmployee();

        $requests = CorrectionRequest::where('employee_id', $employee->id)
            ->orderBy('date', 'desc')
            ->get();

        return view('attendence.pages.line-manager.correction-requests', compact('requests'));
    }

    public function store(Request $request)
    {
        $request->validate(CorrectionRequest::rules());

        // Payroll-locked month: no changes
        MonthlyLock::ensureOpen($request->date);

        $employee = $this->currentEmployee();

        $correction = CorrectionRequest::submit($employee, $request->only(['date', 'change_type', 'requested_check_in', 'requested_check_out', 'reason']));

        // Reviewed by the person they report to (a Line Manager's Manager), otherwise by Admin
        PortalNotification::notifyReviewer(
            $employee,
            'correction',
            "Correction request from {$employee->full_name}",
            "{$employee->full_name} requested an attendance correction for ".Carbon::parse($request->date)->format('d M Y')
                .": {$correction->typeLabel()} ({$correction->requestedLabel()}). Reason: {$request->reason}",
            'admin.approvals',
            "correction:request:{$correction->id}"
        );

        return redirect()->back()->with('success', 'Correction request submitted!');
    }
}
