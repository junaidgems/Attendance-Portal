<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CorrectionRequest;
use App\Models\DeductionWaiver;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\MonthlyLock;
use App\Models\PortalNotification;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ApprovalController extends Controller
{
    public function index()
    {

        // PENDING CORRECTION REQUESTS

        $corrections = CorrectionRequest::with([
            'employee.department',
            'employee.role',
            'lmReviewer.role',
        ])
            ->where('status', 'pending')
            ->orderBy('date', 'desc')
            ->get()
            // A Manager's request waiting for their Director is not Admin's to decide
            ->reject(fn ($c) => $c->isDirectorDecision())
            ->values()
            ->map(fn ($c) => [
                'id' => $c->id,

                'employee' => $c->employee->first_name.' '.
                    $c->employee->last_name,

                'avatar' => strtoupper(
                    substr($c->employee->first_name, 0, 1).
                    substr($c->employee->last_name, 0, 1)
                ),

                'designation' => ($c->employee->role->display_name ?? '-').
                    ' · '.
                    ($c->employee->department->name ?? '-'),

                'date' => $c->date,

                'type' => $c->typeLabel(),

                'current_record' => $c->current_record ?: '—',

                'requested_time' => $c->requested_correction,

                'reason' => $c->reason,

                // Where it stands, and the Line Manager / Manager's recommendation (if given)
                'awaiting' => $c->stageLabel(),
                'lm_status' => $c->lm_status,
                'lm_note' => $c->lm_note,
                'lm_by' => $c->lmReviewer ? "{$c->lmReviewer->full_name} ({$c->lmReviewer->roleLabel()})" : null,
            ]);

        // PENDING LEAVE REQUESTS

        // PENDING LEAVE REQUESTS

        $leaves = LeaveRequest::with([
            'employee.department',
            'employee.role',
            'leaveType',
            'manager',
        ])
            ->where('status', 'pending')
            ->orderBy('from_date', 'desc')
            ->get()
            // Ready for Admin, plus Line Managers' / Managers' own requests still with their reviewer (whoever decides first)
            ->filter(fn ($l) => $l->adminMayDecide())
            ->values()
            ->map(function ($l) {

                $employee = $l->employee;

                return [
                    'id' => $l->id,

                    'employee' => $employee
                        ? trim($employee->first_name.' '.$employee->last_name)
                        : 'Unknown Employee',

                    'avatar' => $employee
                        ? strtoupper(substr($employee->first_name, 0, 1).substr($employee->last_name, 0, 1))
                        : 'NA',

                    'designation' => $employee
                        ? (($employee->role->display_name ?? '-').' · '.($employee->department->name ?? '-'))
                        : '-',

                    'from' => $l->from_date,
                    'to' => $l->to_date,

                    // Blade mein date key use ho rahi hai
                    'date' => $l->from_date,

                    'type' => $l->leaveType->name ?? '-',

                    'reason' => $l->reason,
                    'status' => $l->status,
                    'manager_name' => $l->manager?->full_name,
                    'manager_status' => $l->manager_status,
                    'manager_note' => $l->manager_note,
                    'is_half_day' => $l->is_half_day,
                    // Still with the Line Manager / Manager / Director too: Admin may decide first
                    'awaiting' => $l->isAwaitingManager() ? $l->stageLabel() : null,
                ];
            });

        // PENDING WAIVER REQUESTS
        $waivers = DeductionWaiver::with([
            'employee.department',
            'employee.role',
        ])
            ->where('status', 'pending')
            ->orderBy('date', 'desc')
            ->get()
            // A Manager's request waiting for their Director is not Admin's to decide
            ->reject(fn ($w) => $w->isDirectorDecision())
            ->values()
            ->map(function ($w) {

                $employee = $w->employee;

                $managerName = '—';

                if (! empty($w->lm_reviewed_by)) {
                    $manager = Employee::find($w->lm_reviewed_by);

                    if ($manager) {
                        $managerName = trim($manager->first_name.' '.$manager->last_name);
                    }
                }

                return [
                    'id' => $w->id,

                    'employee' => $employee
                        ? trim($employee->first_name.' '.$employee->last_name)
                        : 'Unknown Employee',

                    'avatar' => $employee
                        ? strtoupper(substr($employee->first_name, 0, 1).substr($employee->last_name, 0, 1))
                        : 'NA',

                    'designation' => $employee
                        ? (($employee->role->display_name ?? '-').' · '.($employee->department->name ?? '-'))
                        : '-',

                    'date' => $w->date,

                    'type' => DeductionWaiver::deductionLabel($w->type),

                    'reason' => $w->reason,

                    'lm_status' => $w->lm_status ?? 'pending',
                    'lm_note' => $w->lm_note,
                    'lm_reviewed_by' => $managerName,
                    'lm_reviewed_at' => $w->lm_reviewed_at,
                ];
            });

        // HISTORY - CORRECTIONS

        $correctionHistory = CorrectionRequest::with(['employee.manager.role', 'lmReviewer.role'])
            ->whereIn('status', ['approved', 'rejected'])
            ->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                'type' => 'Correction',

                'employee' => $c->employee->first_name.' '.
                    $c->employee->last_name,

                'avatar' => strtoupper(
                    substr($c->employee->first_name, 0, 1).
                    substr($c->employee->last_name, 0, 1)
                ),

                'date' => $c->date,

                'detail' => $c->typeLabel(),

                'status' => $c->status,

                'note' => $c->manager_note,

                'reviewed_by' => $c->reviewed_by ?? '-',

                // Line Manager / Manager recommendation (if any), then Admin's final decision.
                // Older corrections were decided by the Line Manager alone: a single step.
                'steps' => in_array($c->lm_status, ['approved', 'rejected'], true)
                    ? [
                        [
                            'who' => $c->lmReviewer ? "{$c->lmReviewer->full_name} ({$c->lmReviewer->roleLabel()})" : 'Line Manager',
                            'decision' => $c->lm_status === 'approved' ? 'approved' : 'not approved',
                            'note' => $c->lm_note,
                        ],
                        [
                            'who' => ($c->reviewed_by ?: 'Admin').' (Admin)',
                            'decision' => $c->status,
                            'note' => $c->manager_note,
                        ],
                    ]
                    : [[
                        'who' => $this->reviewerLabel($c->reviewed_by, $c->employee),
                        'decision' => $c->status,
                        'note' => $c->manager_note,
                    ]],

                'reviewed_at' => $c->reviewed_at,
            ]);

        // =====================================================
        // HISTORY - LEAVES
        // =====================================================
        $leaveHistory = LeaveRequest::with([
            'employee',
            'leaveType',
            'manager.role',
        ])
            ->whereIn('status', ['approved', 'rejected'])
            // Automatic penalty deductions are not requests anyone decided (they show on the Penalty pages)
            ->where('is_penalty', false)
            ->get()
            ->map(fn ($l) => [
                'id' => $l->id,
                'type' => 'Leave',

                'employee' => $l->employee->first_name.' '.
                    $l->employee->last_name,

                'avatar' => strtoupper(
                    substr($l->employee->first_name, 0, 1).
                    substr($l->employee->last_name, 0, 1)
                ),

                'date' => $l->from_date,

                'detail' => $l->leaveType->name ?? '-',

                'status' => $l->status,

                'note' => $l->admin_note,

                'reviewed_by' => $l->reviewed_by ?? '-',

                'steps' => array_values(array_filter([
                    in_array($l->manager_status, [LeaveRequest::MANAGER_APPROVED, LeaveRequest::MANAGER_REJECTED], true) ? [
                        'who' => $l->manager ? "{$l->manager->full_name} ({$l->manager->roleLabel()})" : 'Line Manager',
                        'decision' => $l->manager_status,
                        'note' => $l->manager_note,
                    ] : null,
                    // Older requests were closed by the Line Manager's own rejection: no Admin step
                    ! ($l->manager_status === LeaveRequest::MANAGER_REJECTED && $l->reviewed_by === $l->manager?->full_name) ? [
                        'who' => $l->reviewed_by === 'System' ? 'System (automatic)' : ($l->reviewed_by ?: 'Admin').' (Admin)',
                        'decision' => $l->status,
                        'note' => $l->admin_note,
                    ] : null,
                ])),

                'reviewed_at' => $l->reviewed_at,
            ]);

        // =====================================================
        // HISTORY - WAIVERS
        // =====================================================
        $waiverHistory = DeductionWaiver::with('employee')
            ->whereIn('status', ['approved', 'rejected'])
            ->get()
            ->map(fn ($w) => [
                'id' => $w->id,
                'type' => 'Waiver',

                'employee' => $w->employee->first_name.' '.
                    $w->employee->last_name,

                'avatar' => strtoupper(
                    substr($w->employee->first_name, 0, 1).
                    substr($w->employee->last_name, 0, 1)
                ),

                'date' => $w->date,

                'detail' => DeductionWaiver::deductionLabel($w->type),

                'status' => $w->status,

                // Final Admin note
                'note' => $w->manager_note,

                'reviewed_by' => $w->reviewed_by ?? '-',

                'steps' => array_values(array_filter([
                    in_array($w->lm_status, ['approved', 'rejected'], true) ? [
                        'who' => ($lm = $w->lm_reviewed_by ? Employee::with('role')->find($w->lm_reviewed_by) : null)
                            ? "{$lm->full_name} ({$lm->roleLabel()})"
                            : 'Line Manager',
                        'decision' => $w->lm_status === 'approved' ? 'recommended' : 'not recommended',
                        'note' => $w->lm_note,
                    ] : null,
                    [
                        'who' => ($w->reviewed_by ?: 'Admin').' (Admin)',
                        'decision' => $w->status,
                        'note' => $w->manager_note,
                    ],
                ])),

                'reviewed_at' => $w->reviewed_at,
            ]);

        // =====================================================
        // COMBINE HISTORY
        // =====================================================
        $history = $correctionHistory
            ->concat($leaveHistory)
            ->concat($waiverHistory)
            ->sortByDesc('reviewed_at')
            ->values();

        $data = [
            'corrections' => $corrections,
            'leaves' => $leaves,
            'waivers' => $waivers,
            'history' => $history,
        ];

        return view(
            'attendence.pages.admin.approvals',
            compact('data')
        );
    }

    // =========================================================
    // CORRECTIONS
    // =========================================================

    public function approveCorrection(Request $request, $id)
    {
        $request->validate(['note' => 'nullable|string|max:500']);

        $c = CorrectionRequest::findOrFail($id);

        if ($c->isDirectorDecision()) {
            return redirect()->back()->with('error', "This is a Manager's request decided by their Director. Admin cannot change it.");
        }

        // Payroll-locked month: no changes
        MonthlyLock::ensureOpen($c->date);

        $c->update([
            'status' => 'approved',
            'reviewed_at' => now(),
            'reviewed_by' => Auth::user()->name,
            'manager_note' => $request->note ?: 'Approved by Admin',
        ]);

        // Approved → the corrected times go into attendance; otherwise any earlier change is undone
        $this->closeCorrectionReview($c, 'approved', $request->note);

        $c->syncAttendanceWithStatus(Auth::user()->name);

        PortalNotification::notifyEmployee($c->employee, 'correction', 'Correction request approved',
            'Your correction request for '.Carbon::parse($c->date)->format('d M Y').' has been approved.'.($request->note ? " Admin note: {$request->note}" : ''),
            'corrections', "correction:decision:{$c->id}");

        return redirect()->back()
            ->with('success', 'Correction approved!');
    }

    public function rejectCorrection(Request $request, $id)
    {
        $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $c = CorrectionRequest::findOrFail($id);

        if ($c->isDirectorDecision()) {
            return redirect()->back()->with('error', "This is a Manager's request decided by their Director. Admin cannot change it.");
        }

        // Payroll-locked month: no changes
        MonthlyLock::ensureOpen($c->date);

        $c->update([
            'status' => 'rejected',
            'reviewed_at' => now(),
            'reviewed_by' => Auth::user()->name,
            'manager_note' => $request->reason,
        ]);

        // Approved → the corrected times go into attendance; otherwise any earlier change is undone
        $this->closeCorrectionReview($c, 'rejected', $request->reason);

        $c->syncAttendanceWithStatus(Auth::user()->name);

        PortalNotification::notifyEmployee($c->employee, 'correction', 'Correction request rejected',
            'Your correction request for '.Carbon::parse($c->date)->format('d M Y')." was rejected: \"{$request->reason}\"",
            'corrections', "correction:decision:{$c->id}");

        return redirect()->back()
            ->with('success', 'Correction rejected!');
    }

    // WAIVERS - FINAL ADMIN DECISION

    public function approveWaiver(Request $request, $id)
    {
        $request->validate(['note' => 'nullable|string|max:500']);

        $w = DeductionWaiver::findOrFail($id);

        if ($w->isDirectorDecision()) {
            return redirect()->back()->with('error', "This is a Manager's request decided by their Director. Admin cannot change it.");
        }

        // Payroll-locked month: no changes
        MonthlyLock::ensureOpen($w->date);

        // An employee's waiver needs the Line Manager's review first; a Line Manager's / Manager's own
        // waiver may be decided by Admin right away (whoever decides first)
        if (! $w->adminMayDecide()) {
            return redirect()->back()->with('error', $w->status === 'pending'
                ? 'Line Manager has not reviewed this waiver yet.'
                : 'A decision has already been made on this waiver.');
        }

        $w->update([
            // FINAL status
            'status' => 'approved',

            'reviewed_at' => now(),

            'reviewed_by' => Auth::user()->name,

            'manager_note' => $request->note ?: 'Approved by Admin',
        ] + $this->skipWaiverReviewFields($w));

        // The waived item no longer counts: reverse the penalty leave if the count drops below the limit
        $this->syncWaiverPenalty($w);

        PortalNotification::notifyEmployee($w->employee, 'waiver', 'Waiver approved',
            'Your '.strtolower(DeductionWaiver::deductionLabel($w->type)).' waiver for '.Carbon::parse($w->date)->format('d M Y').' has been approved. It no longer counts toward your penalty.'.($request->note ? " Admin note: {$request->note}" : ''),
            'penalty', "waiver:decision:{$w->id}");

        $this->notifyWaiverReviewer($w, 'approved', $request->note);

        return redirect()->back()
            ->with(
                'success',
                'Waiver finally approved!'
            );
    }

    /**
     * After Admin's final decision on a correction: a Line Manager / Manager step that was still open is
     * no longer needed, and whoever recommended it is told the outcome.
     */
    private function closeCorrectionReview(CorrectionRequest $c, string $decision, ?string $note): void
    {
        if ($c->lm_status === 'pending') {
            $c->update(['lm_status' => 'not_required']);
        }

        $reviewer = $c->lmReviewer ?? ($c->employee ? LeaveRequest::reviewingManagerFor($c->employee) : null);

        if (! $reviewer?->user_id) {
            return;
        }

        PortalNotification::sendToUsers([$reviewer->user_id], 'correction', "Correction {$decision} by Admin",
            "Admin {$decision} {$c->employee?->full_name}'s correction for ".Carbon::parse($c->date)->format('d M Y').'.'
                .($note ? " Admin note: {$note}" : ''),
            route($reviewer->portalPrefix().'.approvals'), "correction:admin-decision:{$c->id}");
    }

    /**
     * "Furqan Iqbal (Line Manager)" / "Admin User (Admin)" for a stored reviewer name.
     */
    private function reviewerLabel(?string $name, ?Employee $employee): string
    {
        if (! $name) {
            return 'Unknown reviewer';
        }

        $lead = $employee?->manager;

        return $lead && $lead->full_name === $name
            ? "{$name} ({$lead->roleLabel()})"
            : "{$name} (Admin)";
    }

    /**
     * Decided by Admin while the waiver was still with the Line Manager / Manager / Director: their step is no longer needed.
     *
     * @return array<string, string>
     */
    private function skipWaiverReviewFields(DeductionWaiver $w): array
    {
        return $w->isAwaitingManager() ? ['lm_status' => 'not_required'] : [];
    }

    /**
     * Tell the Line Manager / Manager / Director who recommended the waiver (or who still had it in their queue)
     * about Admin's final decision.
     */
    private function notifyWaiverReviewer(DeductionWaiver $w, string $decision, ?string $note): void
    {
        $reviewer = $w->lm_reviewed_by
            ? Employee::find($w->lm_reviewed_by)
            : ($w->employee ? LeaveRequest::reviewingManagerFor($w->employee) : null);

        if (! $reviewer?->user_id) {
            return;
        }

        PortalNotification::sendToUsers([$reviewer->user_id], 'waiver', "Waiver {$decision} by Admin",
            "Admin {$decision} the ".strtolower(DeductionWaiver::deductionLabel($w->type))." waiver of {$w->employee?->full_name} for "
                .Carbon::parse($w->date)->format('d M Y').'.'.($note ? " Admin note: {$note}" : ''),
            route($reviewer->portalPrefix().'.approvals'), "waiver:admin-decision:{$w->id}");
    }

    /**
     * Re-evaluate the automatic penalty leaves of the waiver's cycle after a final decision.
     */
    private function syncWaiverPenalty(DeductionWaiver $w): void
    {
        // Approved → the item stops showing on attendance (e.g. Late → Present); rejected → nothing changes
        $w->syncAttendance();

        $w->employee?->load(['shift', 'gracePolicy'])->syncPenaltyLeaves($w->date);
    }

    public function rejectWaiver(Request $request, $id)
    {
        $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $w = DeductionWaiver::findOrFail($id);

        if ($w->isDirectorDecision()) {
            return redirect()->back()->with('error', "This is a Manager's request decided by their Director. Admin cannot change it.");
        }

        // Payroll-locked month: no changes
        MonthlyLock::ensureOpen($w->date);

        if (! $w->adminMayDecide()) {
            return redirect()->back()->with('error', $w->status === 'pending'
                ? 'Line Manager has not reviewed this waiver yet.'
                : 'A decision has already been made on this waiver.');
        }

        $w->update([
            // FINAL status
            'status' => 'rejected',

            'reviewed_at' => now(),

            'reviewed_by' => Auth::user()->name,

            // Final Admin rejection reason
            'manager_note' => $request->reason,
        ] + $this->skipWaiverReviewFields($w));

        $this->syncWaiverPenalty($w);

        PortalNotification::notifyEmployee($w->employee, 'waiver', 'Waiver rejected',
            'Your '.strtolower(DeductionWaiver::deductionLabel($w->type)).' waiver for '.Carbon::parse($w->date)->format('d M Y')." was rejected: \"{$request->reason}\"",
            'penalty', "waiver:decision:{$w->id}");

        $this->notifyWaiverReviewer($w, 'rejected', $request->reason);

        return redirect()->back()
            ->with(
                'success',
                'Waiver finally rejected!'
            );
    }

    // UPDATE CORRECTION HISTORY

    public function updateCorrection(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:pending,approved,rejected',

            'note' => 'nullable|string|max:500',
        ]);

        $c = CorrectionRequest::findOrFail($id);

        if ($c->isDirectorDecision()) {
            return redirect()->back()->with('error', "This is a Manager's request decided by their Director. Admin cannot change it.");
        }

        // Payroll-locked month: no changes
        MonthlyLock::ensureOpen($c->date);

        $data = [
            'status' => $request->status,
            'reviewed_at' => now(),
            'reviewed_by' => Auth::user()->name,
        ];

        if ($request->filled('note')) {
            $data['manager_note'] =
                $request->note;
        }

        $c->update($data);

        // Approved → the corrected times go into attendance; otherwise any earlier change is undone
        $c->syncAttendanceWithStatus(Auth::user()->name);

        if (in_array($request->status, ['approved', 'rejected'], true)) {
            PortalNotification::notifyEmployee($c->employee, 'correction', 'Correction request '.$request->status,
                'Your correction request for '.Carbon::parse($c->date)->format('d M Y')." was changed to {$request->status} by Admin.".($request->note ? " Note: {$request->note}" : ''),
                'corrections', "correction:update:{$c->id}:".now()->timestamp);
        }

        return redirect()->back()
            ->with(
                'success',
                'Correction request updated!'
            );
    }

    // UPDATE WAIVER HISTORY

    public function updateWaiver(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:pending,approved,rejected',

            'note' => 'nullable|string|max:500',
        ]);

        $w = DeductionWaiver::findOrFail($id);

        if ($w->isDirectorDecision()) {
            return redirect()->back()->with('error', "This is a Manager's request decided by their Director. Admin cannot change it.");
        }

        // Payroll-locked month: no changes
        MonthlyLock::ensureOpen($w->date);

        $data = [
            'status' => $request->status,
            'reviewed_at' => now(),
            'reviewed_by' => Auth::user()->name,
        ];

        if ($request->filled('note')) {
            $data['manager_note'] =
                $request->note;
        }

        $w->update($data);

        $this->syncWaiverPenalty($w);

        if (in_array($request->status, ['approved', 'rejected'], true)) {
            PortalNotification::notifyEmployee($w->employee, 'waiver', 'Waiver '.$request->status,
                'Your '.strtolower(DeductionWaiver::deductionLabel($w->type)).' waiver for '.Carbon::parse($w->date)->format('d M Y')." was changed to {$request->status} by Admin.".($request->note ? " Note: {$request->note}" : ''),
                'penalty', "waiver:update:{$w->id}:".now()->timestamp);
        }

        return redirect()->back()
            ->with(
                'success',
                'Waiver request updated!'
            );
    }
}
