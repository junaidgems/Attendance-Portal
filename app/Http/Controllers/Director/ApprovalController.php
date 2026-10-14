<?php

namespace App\Http\Controllers\Director;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Director\Concerns\ManagerRequests;
use App\Models\AttendancePolicyLock;
use App\Models\AuditLog;
use App\Models\CorrectionRequest;
use App\Models\DeductionWaiver;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\MonthlyLock;
use App\Models\PortalNotification;
use App\Models\WfhRequest;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * The Director decides the requests of the Managers reporting to them. The decision is final:
 * the same changes as an Admin decision are made (attendance, penalty leaves) and Admin is only informed.
 */
class ApprovalController extends Controller
{
    use ManagerRequests;

    public function index(): View
    {
        return view('attendence.pages.director.approvals', $this->pendingManagerRequests() + [
            'history' => $this->history(),
            'policyLock' => AttendancePolicyLock::status(),
        ]);
    }

    // ATTENDANCE POLICY LOCK

    /**
     * Open the Attendance Policy page for Admin for a limited time.
     */
    public function unlockPolicy(): RedirectResponse
    {
        if (! AttendancePolicyLock::isSupported()) {
            return redirect()->back()->with('error', 'The policy lock is not set up yet (database columns missing).');
        }

        $until = AttendancePolicyLock::unlock($this->director()?->full_name ?? 'Director');

        return redirect()->back()->with('success', 'Attendance Policy unlocked for Admin until '.$until->format('h:i A').'.');
    }

    /**
     * Close the Attendance Policy page again.
     */
    public function lockPolicy(): RedirectResponse
    {
        if (! AttendancePolicyLock::isSupported()) {
            return redirect()->back()->with('error', 'The policy lock is not set up yet (database columns missing).');
        }

        AttendancePolicyLock::lock($this->director()?->full_name ?? 'Director');

        return redirect()->back()->with('success', 'Attendance Policy locked.');
    }

    // LEAVE

    public function approveLeave(Request $request, $id): RedirectResponse
    {
        $request->validate(['note' => 'nullable|string|max:500']);

        return $this->decideLeave($id, 'approved', $request->note);
    }

    public function rejectLeave(Request $request, $id): RedirectResponse
    {
        $request->validate(['note' => 'required|string|max:500']);

        return $this->decideLeave($id, 'rejected', $request->note);
    }

    private function decideLeave($id, string $decision, ?string $note): RedirectResponse
    {
        $l = LeaveRequest::with(['employee', 'leaveType'])->findOrFail($id);

        MonthlyLock::ensureOpen($l->from_date, $l->to_date);
        abort_unless($this->managerIds()->contains($l->employee_id), 403);

        if (! $l->isAwaitingManager()) {
            return redirect()->back()->with('error', 'A decision has already been made on this request.');
        }

        if ($decision === 'approved' && ($wfh = WfhRequest::overlappingFor($l->employee_id, $l->from_date, $l->to_date, ['approved']))) {
            return redirect()->back()->with('error', "{$l->employee->full_name} has approved Work From Home for {$wfh->dateLabel()}. Leave and Work From Home cannot be on the same day.");
        }

        $director = $this->director();

        $l->update([
            'manager_id' => $director->id,
            'manager_status' => $decision === 'approved' ? LeaveRequest::MANAGER_APPROVED : LeaveRequest::MANAGER_REJECTED,
            'manager_note' => $note,
            'manager_reviewed_at' => now(),
            'status' => $decision,
            'reviewed_at' => now(),
            'reviewed_by' => $director->full_name,
        ]);

        $this->announce($l->employee, 'leave', 'leave', 'admin.leaves', "Leave request {$decision}", $l->summary(), $decision, $note, "leave:{$l->id}");

        return redirect()->back()->with('success', 'Leave request '.$decision.'.');
    }

    // CORRECTION

    public function approveCorrection(Request $request, $id): RedirectResponse
    {
        $request->validate(['note' => 'nullable|string|max:500']);

        return $this->decideCorrection($id, 'approved', $request->note);
    }

    public function rejectCorrection(Request $request, $id): RedirectResponse
    {
        $request->validate(['note' => 'required|string|max:500']);

        return $this->decideCorrection($id, 'rejected', $request->note);
    }

    private function decideCorrection($id, string $decision, ?string $note): RedirectResponse
    {
        $c = CorrectionRequest::with('employee')->findOrFail($id);

        MonthlyLock::ensureOpen($c->date);
        abort_unless($this->managerIds()->contains($c->employee_id), 403);

        if (! $c->isAwaitingManager()) {
            return redirect()->back()->with('error', 'A decision has already been made on this request.');
        }

        $director = $this->director();

        $c->update([
            'lm_status' => $decision,
            'lm_note' => $note,
            'lm_reviewed_by' => $director->id,
            'lm_reviewed_at' => now(),
            'status' => $decision,
            'reviewed_at' => now(),
            'reviewed_by' => $director->full_name,
            'manager_note' => $note ?: ucfirst($decision).' by Director',
        ]);

        // Approved → the corrected times go into attendance (and the penalty leaves are refreshed)
        $c->syncAttendanceWithStatus($director->full_name);

        $this->announce($c->employee, 'correction', 'corrections', 'admin.approvals', "Correction request {$decision}",
            'correction request for '.Carbon::parse($c->date)->format('d M Y'), $decision, $note, "correction:{$c->id}");

        return redirect()->back()->with('success', 'Correction request '.$decision.'.');
    }

    // WAIVER

    public function approveWaiver(Request $request, $id): RedirectResponse
    {
        $request->validate(['note' => 'nullable|string|max:500']);

        return $this->decideWaiver($id, 'approved', $request->note);
    }

    public function rejectWaiver(Request $request, $id): RedirectResponse
    {
        $request->validate(['note' => 'required|string|max:500']);

        return $this->decideWaiver($id, 'rejected', $request->note);
    }

    private function decideWaiver($id, string $decision, ?string $note): RedirectResponse
    {
        $w = DeductionWaiver::with('employee')->findOrFail($id);

        MonthlyLock::ensureOpen($w->date);
        abort_unless($this->managerIds()->contains($w->employee_id), 403);

        if ($w->status !== 'pending' || $w->lm_status !== 'pending') {
            return redirect()->back()->with('error', 'A decision has already been made on this request.');
        }

        $director = $this->director();

        $w->update([
            'lm_status' => $decision,
            'lm_note' => $note,
            'lm_reviewed_by' => $director->id,
            'lm_reviewed_at' => now(),
            'status' => $decision,
            'reviewed_at' => now(),
            'reviewed_by' => $director->full_name,
            'manager_note' => $note ?: ucfirst($decision).' by Director',
        ]);

        // Approved → the item stops counting: attendance shows it as waived and penalty leaves are refreshed
        $w->syncAttendance();
        $w->employee?->load(['shift', 'gracePolicy'])->syncPenaltyLeaves($w->date);

        $this->announce($w->employee, 'waiver', 'penalty', 'admin.approvals', "Waiver {$decision}",
            strtolower(DeductionWaiver::deductionLabel($w->type)).' waiver for '.Carbon::parse($w->date)->format('d M Y'), $decision, $note, "waiver:{$w->id}");

        return redirect()->back()->with('success', 'Waiver request '.$decision.'.');
    }

    // WORK FROM HOME

    public function approveWfh(Request $request, $id): RedirectResponse
    {
        $request->validate(['note' => 'nullable|string|max:500']);

        return $this->decideWfh($id, 'approved', $request->note);
    }

    public function rejectWfh(Request $request, $id): RedirectResponse
    {
        $request->validate(['note' => 'required|string|max:500']);

        return $this->decideWfh($id, 'rejected', $request->note);
    }

    private function decideWfh($id, string $decision, ?string $note): RedirectResponse
    {
        $w = WfhRequest::with('employee')->findOrFail($id);

        MonthlyLock::ensureOpen($w->from_date, $w->to_date);
        abort_unless($this->managerIds()->contains($w->employee_id), 403);

        if (! $w->isAwaitingManager()) {
            return redirect()->back()->with('error', 'A decision has already been made on this request.');
        }

        if ($decision === 'approved' && ($leave = LeaveRequest::overlappingFor($w->employee_id, $w->from_date, $w->to_date, ['approved']))) {
            return redirect()->back()->with('error', "{$w->employee->full_name} has an approved leave ({$leave->summary()}) on these dates. Leave and Work From Home cannot be on the same day.");
        }

        $director = $this->director();

        $w->update([
            'manager_id' => $director->id,
            'manager_status' => $decision === 'approved' ? LeaveRequest::MANAGER_APPROVED : LeaveRequest::MANAGER_REJECTED,
            'manager_note' => $note,
            'manager_reviewed_at' => now(),
            'status' => $decision,
            'reviewed_at' => now(),
            'reviewed_by' => $director->full_name,
        ]);

        $this->announce($w->employee, 'wfh', 'wfh', 'admin.wfh', "Work From Home {$decision}",
            'Work From Home for '.$w->dateLabel(), $decision, $note, "wfh:{$w->id}");

        return redirect()->back()->with('success', 'Work From Home request '.$decision.'.');
    }

    /**
     * Tell the Manager the final decision, inform Admin (no action needed) and write the audit log.
     */
    private function announce(?Employee $manager, string $type, string $page, string $adminRoute, string $title, string $subject, string $decision, ?string $note, string $key): void
    {
        $director = $this->director();
        $noteText = $note ? " Note: {$note}" : '';

        PortalNotification::notifyEmployee($manager, $type, $title,
            "Your {$subject} was {$decision} by {$director->full_name} (Director).{$noteText}",
            $page, "{$key}:director-decision");

        PortalNotification::notifyAdmins($type, ucfirst($subject)." {$decision} by Director",
            "{$director->full_name} (Director) {$decision} {$manager?->full_name}'s {$subject}. No action is needed from Admin.{$noteText}",
            $adminRoute, "{$key}:director-decision");

        AuditLog::record($type, ucfirst($decision).' by Director', "{$manager?->full_name} — {$subject}{$noteText}", $director->full_name);
    }

    /**
     * Requests of the Director's Managers that are no longer pending, newest first.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function history(): Collection
    {
        $managerIds = $this->managerIds();
        $decided = fn ($query) => $query->whereIn('employee_id', $managerIds)->where('status', '!=', 'pending')->latest('updated_at')->limit(100)->get();

        $row = fn (object $r, string $type, string $date, string $detail, ?string $note) => [
            'type' => $type,
            'employee' => $r->employee?->full_name ?? 'Unknown Employee',
            'avatar' => $r->employee ? strtoupper(substr($r->employee->first_name, 0, 1).substr($r->employee->last_name, 0, 1)) : 'NA',
            'date' => $date,
            'detail' => $detail,
            'reason' => $r->reason,
            'status' => $r->status,
            'note' => $note,
            'by' => $r->reviewed_by,
            'updated_at' => $r->updated_at,
        ];

        return collect()
            // Automatic penalty deductions are not requests anyone decided
            ->concat($decided(LeaveRequest::with(['employee', 'leaveType'])->where('is_penalty', false))->map(fn ($l) => $row($l, 'Leave Request', $this->dateRange($l->from_date, $l->to_date),
                ($l->leaveType->name ?? 'Leave').' · '.($l->is_half_day ? 'Half day' : $l->days.' '.($l->days == 1 ? 'day' : 'days')), $l->manager_note ?: $l->admin_note)))
            ->concat($decided(CorrectionRequest::with('employee'))->map(fn ($c) => $row($c, 'Correction Request', Carbon::parse($c->date)->format('d M Y'), $c->typeLabel(), $c->lm_note ?: $c->manager_note)))
            ->concat($decided(DeductionWaiver::with('employee'))->map(fn ($w) => $row($w, 'Deduction Waiver', Carbon::parse($w->date)->format('d M Y'), DeductionWaiver::deductionLabel($w->type), $w->lm_note ?: $w->manager_note)))
            ->concat($decided(WfhRequest::with('employee'))->map(fn ($w) => $row($w, 'Work From Home', $w->dateLabel(), $w->days.' '.($w->days == 1 ? 'day' : 'days'), $w->manager_note ?: $w->admin_note)))
            ->sortByDesc('updated_at')
            ->values();
    }
}
