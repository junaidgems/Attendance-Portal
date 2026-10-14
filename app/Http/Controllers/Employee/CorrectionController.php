<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\CorrectionRequest;
use App\Models\MonthlyLock;
use App\Models\PortalNotification;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CorrectionController extends Controller
{
    public function index()
    {
        $employee = Auth::user()->employee;

        $requests = $employee->correctionRequests()->orderBy('date', 'desc')->get();

        return view('attendence.pages.employee.correction-requests', compact('requests'));
    }

    public function store(Request $request)
    {
        $request->validate(CorrectionRequest::rules());

        // Payroll-locked month: no changes
        MonthlyLock::ensureOpen($request->date);

        $employee = Auth::user()->employee;

        $correction = CorrectionRequest::submit($employee, $request->only(['date', 'change_type', 'requested_check_in', 'requested_check_out', 'reason']));

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
