<?php

namespace App\Http\Controllers\LineManager;

use App\Http\Controllers\Controller;
use App\Models\CorrectionRequest;
use App\Models\DeductionWaiver;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\MonthlyLock;
use App\Models\PortalNotification;
use App\Models\User;
use App\Models\WfhRequest;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class ApprovalController extends Controller
{
    /**
     * Get IDs of employees who report to the current Line Manager.
     */
    private function teamIds()
    {
        $manager = Auth::user()->employee;

        return Employee::where('reports_to_id', $manager->id)
            ->pluck('id');
    }

    /**
     * "Line Manager" or "Manager" — the logged-in reviewer's role, for messages.
     */
    private function reviewerLabel(): string
    {
        return $this->currentManager()->loadMissing('role')->roleLabel();
    }

    /**
     * Get currently logged-in Line Manager / Manager employee record.
     */
    private function currentManager()
    {
        return Auth::user()->employee;
    }

    /**
     * Approvals page: pending corrections, leaves and waivers of the Line Manager's team.
     */
    public function index()
    {
        $teamIds = $this->teamIds();

        $avatar = fn (?Employee $e) => $e
            ? strtoupper(substr($e->first_name, 0, 1).substr($e->last_name, 0, 1))
            : 'NA';

        $corrections = CorrectionRequest::with('employee')
            ->whereIn('employee_id', $teamIds)
            ->awaitingManager()
            ->orderBy('date', 'desc')
            ->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                'kind' => 'correction',
                'employee' => $c->employee?->full_name ?? 'Unknown Employee',
                'avatar' => $avatar($c->employee),
                'date' => Carbon::parse($c->date)->format('d M Y'),
                'detail' => $c->typeLabel(),
                'extra' => 'Current: '.($c->current_record ?: '—').' → Requested: '.($c->requested_correction ?: '—'),
                'reason' => $c->reason,
            ]);

        $leaves = LeaveRequest::with(['employee', 'leaveType'])
            ->whereIn('employee_id', $teamIds)
            ->awaitingManager()
            ->orderBy('from_date', 'desc')
            ->get()
            ->map(fn ($l) => [
                'id' => $l->id,
                'kind' => 'leave',
                'employee' => $l->employee?->full_name ?? 'Unknown Employee',
                'avatar' => $avatar($l->employee),
                'date' => $l->from_date === $l->to_date
                    ? Carbon::parse($l->from_date)->format('d M Y')
                    : Carbon::parse($l->from_date)->format('d M').' – '.Carbon::parse($l->to_date)->format('d M Y'),
                'detail' => ($l->leaveType->name ?? 'Leave').' · '.($l->is_half_day
                    ? 'Half day ('.($l->half_day_session === 'second_half' ? 'second half' : 'first half').')'
                    : $l->days.' '.($l->days == 1 ? 'day' : 'days')),
                'extra' => null,
                'reason' => $l->reason,
                'document_url' => $l->document_path ? route('leave.document', $l->id) : null,
            ]);

        $waivers = DeductionWaiver::with('employee')
            ->whereIn('employee_id', $teamIds)
            ->where('status', 'pending')
            ->where('lm_status', 'pending')
            ->orderBy('date', 'desc')
            ->get()
            ->map(fn ($w) => [
                'id' => $w->id,
                'kind' => 'waiver',
                'employee' => $w->employee?->full_name ?? 'Unknown Employee',
                'avatar' => $avatar($w->employee),
                'date' => Carbon::parse($w->date)->format('d M Y'),
                'detail' => DeductionWaiver::deductionLabel($w->type),
                'extra' => null,
                'reason' => $w->reason,
            ]);

        $wfh = WfhRequest::with('employee')
            ->whereIn('employee_id', $teamIds)
            ->awaitingManager()
            ->orderBy('from_date')
            ->get()
            ->map(fn ($w) => [
                'id' => $w->id,
                'kind' => 'wfh',
                'employee' => $w->employee?->full_name ?? 'Unknown Employee',
                'avatar' => $avatar($w->employee),
                'date' => $w->dateLabel(),
                'detail' => 'Work From Home · '.$w->days.' '.($w->days === 1 ? 'day' : 'days'),
                'extra' => null,
                'reason' => $w->reason,
            ]);

        $history = $this->history($teamIds, $avatar);

        return view('attendence.pages.line-manager.approvals', compact('corrections', 'leaves', 'waivers', 'wfh', 'history'));
    }

    // WORK FROM HOME

    /**
     * Line Manager approval forwards the WFH request to Admin for the final decision.
     */
    public function approveWfh(Request $request, $id)
    {
        $request->validate(['note' => 'nullable|string|max:500']);

        $w = $this->findTeamWfh($id);

        if ($w->isTeamLeadRequest()) {
            return $this->finalizeTeamLeadWfh($w, $request->note);
        }

        $w->update([
            'manager_id' => $this->currentManager()->id,
            'manager_status' => LeaveRequest::MANAGER_APPROVED,
            'manager_note' => $request->note,
            'manager_reviewed_at' => now(),
        ]);

        PortalNotification::sendToUsers(
            User::whereHas('role', fn ($q) => $q->where('name', 'administrator'))->pluck('id'),
            'wfh',
            'Work From Home request',
            "{$this->currentManager()->full_name} approved Work From Home for {$w->employee->full_name} ({$w->dateLabel()}). Final approval needed.",
            route('admin.wfh'),
            "wfh:admin:{$w->id}"
        );

        return redirect()->back()->with('success', 'Work From Home approved and forwarded to Admin for final approval.');
    }

    /**
     * Line Manager rejection is final; the request does not go to Admin.
     */
    public function rejectWfh(Request $request, $id)
    {
        $request->validate(['note' => 'required|string|max:500']);

        $w = $this->findTeamWfh($id);

        $w->update([
            'status' => 'rejected',
            'manager_id' => $this->currentManager()->id,
            'manager_status' => LeaveRequest::MANAGER_REJECTED,
            'manager_note' => $request->note,
            'manager_reviewed_at' => now(),
            'reviewed_at' => now(),
            'reviewed_by' => Auth::user()->name,
        ]);

        PortalNotification::notifyEmployee($w->employee, 'wfh', 'Work From Home rejected',
            "Your Work From Home for {$w->dateLabel()} was rejected by your {$this->reviewerLabel()}: \"{$request->note}\"",
            'wfh', "wfh:decision:{$w->id}");

        return redirect()->back()->with('success', 'Work From Home rejected!');
    }

    private function findTeamWfh($id): WfhRequest
    {
        $w = WfhRequest::with('employee')->findOrFail($id);

        // Payroll-locked month: no changes
        MonthlyLock::ensureOpen($w->from_date, $w->to_date);

        abort_unless($this->teamIds()->contains($w->employee_id), 403);

        if (! $w->isAwaitingManager()) {
            abort(422, 'This Work From Home request is no longer awaiting your review.');
        }

        return $w;
    }

    /**
     * Reviewed requests of the team, with the Line Manager decision and the Admin final decision.
     *
     * @return Collection<int, array{kind: string, type: string, employee: string, avatar: string, date: string, detail: string, reason: ?string, lm_status: ?string, lm_note: ?string, final_status: string, admin_note: ?string, admin_by: ?string, updated_at: \Illuminate\Support\Carbon}>
     */
    private function history($teamIds, callable $avatar)
    {
        $finalStatus = fn (string $status, bool $awaitingAdmin) => $status === 'pending'
            ? ($awaitingAdmin ? 'awaiting_admin' : 'pending')
            : $status;

        $leaves = LeaveRequest::with(['employee', 'leaveType'])
            ->whereIn('employee_id', $teamIds)
            ->where('is_penalty', false) // automatic penalty deductions are not requests anyone decided
            ->where(fn ($q) => $q->where('status', '!=', 'pending')
                ->orWhere('manager_status', '!=', LeaveRequest::MANAGER_PENDING))
            ->latest('updated_at')
            ->limit(100)
            ->get()
            ->map(fn ($l) => [
                'kind' => 'leave',
                'type' => 'Leave Request',
                'employee' => $l->employee?->full_name ?? 'Unknown Employee',
                'avatar' => $avatar($l->employee),
                'date' => $l->from_date === $l->to_date
                    ? Carbon::parse($l->from_date)->format('d M Y')
                    : Carbon::parse($l->from_date)->format('d M').' – '.Carbon::parse($l->to_date)->format('d M Y'),
                'detail' => ($l->leaveType->name ?? 'Leave').' · '.($l->is_half_day ? 'Half day' : $l->days.' '.($l->days == 1 ? 'day' : 'days')),
                'reason' => $l->reason,
                'lm_status' => in_array($l->manager_status, [LeaveRequest::MANAGER_APPROVED, LeaveRequest::MANAGER_REJECTED], true)
                    ? $l->manager_status
                    : null,
                'lm_note' => $l->manager_note,
                'final_status' => $finalStatus($l->status, $l->isAwaitingAdmin()),
                // Admin note only exists when Admin (not the Line Manager) made the final decision
                // Older requests were closed by the Line Manager's own rejection (reviewed_by = that manager)
                'admin_note' => $l->status === 'pending' || $l->reviewed_by === $l->manager?->full_name ? null : $l->admin_note,
                'admin_by' => $l->status === 'pending' || $l->reviewed_by === $l->manager?->full_name ? null : $l->reviewed_by,
                'updated_at' => $l->updated_at,
            ]);

        $waivers = DeductionWaiver::with('employee')
            ->whereIn('employee_id', $teamIds)
            ->where(fn ($q) => $q->where('status', '!=', 'pending')
                ->orWhere('lm_status', '!=', 'pending'))
            ->latest('updated_at')
            ->limit(100)
            ->get()
            ->map(fn ($w) => [
                'kind' => 'waiver',
                'type' => 'Deduction Waiver',
                'employee' => $w->employee?->full_name ?? 'Unknown Employee',
                'avatar' => $avatar($w->employee),
                'date' => Carbon::parse($w->date)->format('d M Y'),
                'detail' => DeductionWaiver::deductionLabel($w->type),
                'reason' => $w->reason,
                'lm_status' => in_array($w->lm_status, ['approved', 'rejected'], true) ? $w->lm_status : null,
                'lm_note' => $w->lm_note,
                'final_status' => $finalStatus($w->status, $w->lm_status !== 'pending'),
                'admin_note' => $w->status === 'pending' ? null : $w->manager_note,
                'admin_by' => $w->status === 'pending' ? null : $w->reviewed_by,
                'updated_at' => $w->updated_at,
            ]);

        // Corrections: Line Manager / Manager recommendation, then Admin's final decision.
        // Older ones were decided by the Line Manager alone (no lm_status, reviewed_by = that manager).
        $corrections = CorrectionRequest::with(['employee.manager'])
            ->whereIn('employee_id', $teamIds)
            ->where(fn ($q) => $q->where('status', '!=', 'pending')
                ->orWhereIn('lm_status', ['approved', 'rejected']))
            ->latest('updated_at')
            ->limit(100)
            ->get()
            ->map(function ($c) use ($avatar, $finalStatus) {
                $legacy = ! in_array($c->lm_status, ['approved', 'rejected'], true)
                    && $c->reviewed_by && $c->reviewed_by === $c->employee?->manager?->full_name;

                return [
                    'kind' => 'correction',
                    'type' => 'Correction Request',
                    'employee' => $c->employee?->full_name ?? 'Unknown Employee',
                    'avatar' => $avatar($c->employee),
                    'date' => Carbon::parse($c->date)->format('d M Y'),
                    'detail' => $c->typeLabel(),
                    'reason' => $c->reason,
                    'lm_status' => $legacy ? $c->status : (in_array($c->lm_status, ['approved', 'rejected'], true) ? $c->lm_status : null),
                    'lm_note' => $legacy ? $c->manager_note : $c->lm_note,
                    'final_status' => $finalStatus($c->status, $c->status === 'pending' && ! $c->isAwaitingManager()),
                    'admin_note' => $legacy || $c->status === 'pending' ? null : $c->manager_note,
                    'admin_by' => $legacy || $c->status === 'pending' ? null : $c->reviewed_by,
                    'updated_at' => $c->updated_at,
                ];
            });

        $wfh = WfhRequest::with('employee')
            ->whereIn('employee_id', $teamIds)
            ->where(fn ($q) => $q->where('status', '!=', 'pending')
                ->orWhere('manager_status', '!=', LeaveRequest::MANAGER_PENDING))
            ->latest('updated_at')
            ->limit(100)
            ->get()
            ->map(fn ($w) => [
                'kind' => 'wfh',
                'type' => 'Work From Home',
                'employee' => $w->employee?->full_name ?? 'Unknown Employee',
                'avatar' => $avatar($w->employee),
                'date' => $w->dateLabel(),
                'detail' => $w->days.' '.($w->days === 1 ? 'day' : 'days'),
                'reason' => $w->reason,
                'lm_status' => in_array($w->manager_status, [LeaveRequest::MANAGER_APPROVED, LeaveRequest::MANAGER_REJECTED], true)
                    ? $w->manager_status
                    : null,
                'lm_note' => $w->manager_note,
                'final_status' => $finalStatus($w->status, $w->isAwaitingAdmin()),
                'admin_note' => $w->manager_status === LeaveRequest::MANAGER_REJECTED ? null : $w->admin_note,
                'admin_by' => $w->manager_status === LeaveRequest::MANAGER_REJECTED ? null : $w->reviewed_by,
                'updated_at' => $w->updated_at,
            ]);

        return $leaves->concat($waivers)->concat($corrections)->concat($wfh)
            ->sortByDesc('updated_at')
            ->values();
    }

    // CORRECTION REQUESTS

    /**
     * Line Manager / Manager recommends approving the correction; Admin makes the final decision
     * (and only then is the attendance changed).
     */
    public function approveCorrection(Request $request, $id)
    {
        $request->validate([
            'note' => 'nullable|string|max:500',
        ]);

        return $this->recommendCorrection($id, 'approved', $request->note);
    }

    /**
     * Line Manager / Manager does not approve the correction; Admin makes the final decision.
     */
    public function rejectCorrection(Request $request, $id)
    {
        $request->validate([
            'note' => 'required|string|max:500',
        ]);

        return $this->recommendCorrection($id, 'rejected', $request->note);
    }

    private function recommendCorrection($id, string $decision, ?string $note)
    {
        $c = CorrectionRequest::with('employee')->findOrFail($id);

        // Payroll-locked month: no changes
        MonthlyLock::ensureOpen($c->date);

        abort_unless($this->teamIds()->contains($c->employee_id), 403);

        if (! $c->isAwaitingManager()) {
            return redirect()->back()->with('error', 'This correction request is no longer awaiting your review.');
        }

        $manager = $this->currentManager();

        if ($c->isTeamLeadRequest()) {
            return $this->finalizeTeamLeadCorrection($c, $decision, $note);
        }

        $c->update([
            'lm_status' => $decision,
            'lm_note' => $note,
            'lm_reviewed_by' => $manager->id,
            'lm_reviewed_at' => now(),
        ]);

        $day = Carbon::parse($c->date)->format('d M Y');
        $verb = $decision === 'approved' ? 'approved' : 'did not approve';

        PortalNotification::notifyAdmins('correction', "Correction decision needed: {$c->employee->full_name}",
            "{$manager->full_name} {$verb} {$c->employee->full_name}'s correction for {$day} ({$c->typeLabel()}). Final decision needed."
                .($note ? " Note: {$note}" : ''),
            'admin.approvals', "correction:admin:{$c->id}");

        PortalNotification::notifyEmployee($c->employee, 'correction', 'Correction request update',
            "Your {$this->reviewerLabel()} {$verb} your correction for {$day}".($note ? " (\"{$note}\")" : '').'. Admin will make the final decision.',
            'corrections', "correction:lm-decision:{$c->id}");

        return redirect()->back()->with('success', $decision === 'approved'
            ? 'Correction approved and sent to Admin for the final decision.'
            : 'Correction marked as not approved and sent to Admin for the final decision.');
    }

    // LEAVE REQUESTS

    /**
     * Line Manager approval forwards the request to Admin for the final decision.
     * The final 'status' stays pending until Admin decides.
     */
    public function approveLeave(Request $request, $id)
    {
        $request->validate([
            'note' => 'nullable|string|max:500',
        ]);

        $l = $this->findTeamLeave($id);

        if ($l->isTeamLeadRequest()) {
            return $this->finalizeTeamLeadLeave($l, 'approved', $request->note);
        }

        $l->update([
            'manager_id' => $this->currentManager()->id,
            'manager_status' => LeaveRequest::MANAGER_APPROVED,
            'manager_note' => $request->note,
            'manager_reviewed_at' => now(),
        ]);

        PortalNotification::notifyAdmins('leave', "Leave approval needed: {$l->employee->full_name}",
            "{$this->currentManager()->full_name} approved {$l->employee->full_name}'s {$l->summary()}. Final approval needed.".($request->note ? " Note: {$request->note}" : ''),
            'admin.leaves', "leave:admin:{$l->id}");

        return redirect()->back()
            ->with('success', 'Leave approved and forwarded to Admin for final approval.');
    }

    /**
     * Line Manager / Manager does not approve: recorded with a note, Admin makes the final decision.
     */
    public function rejectLeave(Request $request, $id)
    {
        $request->validate([
            'note' => 'required|string|max:500',
        ]);

        $l = $this->findTeamLeave($id);

        if ($l->isTeamLeadRequest()) {
            return $this->finalizeTeamLeadLeave($l, 'rejected', $request->note);
        }

        // Recommendation only: the request stays pending and goes to Admin for the final decision
        $l->update([
            'manager_id' => $this->currentManager()->id,
            'manager_status' => LeaveRequest::MANAGER_REJECTED,
            'manager_note' => $request->note,
            'manager_reviewed_at' => now(),
        ]);

        PortalNotification::notifyAdmins('leave', "Leave decision needed: {$l->employee->full_name}",
            "{$this->currentManager()->full_name} did not approve {$l->employee->full_name}'s {$l->summary()}. Final decision needed. Note: {$request->note}",
            'admin.leaves', "leave:admin:{$l->id}");

        PortalNotification::notifyEmployee($l->employee, 'leave', 'Leave request update',
            "Your {$this->reviewerLabel()} did not approve your {$l->summary()} (\"{$request->note}\"). Admin will make the final decision.",
            'leave', "leave:lm-decision:{$l->id}");

        return redirect()->back()
            ->with('success', 'Leave marked as not approved and sent to Admin for the final decision.');
    }

    /**
     * A leave request from the manager's own team that is still awaiting Line Manager review.
     */
    private function findTeamLeave($id): LeaveRequest
    {
        $l = LeaveRequest::findOrFail($id);

        // Payroll-locked month: no changes
        MonthlyLock::ensureOpen($l->from_date, $l->to_date);

        abort_unless($this->teamIds()->contains($l->employee_id), 403);

        if (! $l->isAwaitingManager()) {
            abort(422, 'This leave request is no longer awaiting your review.');
        }

        return $l;
    }

    // DEDUCTION WAIVERS
    // Line Manager only gives recommendation.
    // Admin will give FINAL approval/rejection.

    public function approveWaiver(Request $request, $id)
    {
        /*
         * Manager MUST provide a note explaining
         * why they recommend approval.
         */
        $request->validate([
            'note' => 'required|string|max:500',
        ]);

        $w = DeductionWaiver::findOrFail($id);

        // Payroll-locked month: no changes
        MonthlyLock::ensureOpen($w->date);

        /*
         * Make sure this waiver belongs to an employee
         * who reports to this Line Manager.
         */
        abort_unless(
            $this->teamIds()->contains($w->employee_id),
            403
        );

        /*
         * Don't allow Line Manager to modify a waiver
         * after Admin has already made the final decision.
         */
        if ($w->status !== 'pending') {
            return redirect()->back()
                ->with('error', 'This waiver has already been finalized by Admin.');
        }

        $manager = $this->currentManager();

        if ($w->isTeamLeadRequest()) {
            return $this->finalizeTeamLeadWaiver($w, 'approved', $request->note);
        }

        /*
         * IMPORTANT:
         *
         * We DO NOT change:
         * $w->status
         *
         * Main status remains "pending".
         * Admin will change it later.
         */
        $w->update([
            'lm_status' => 'approved',
            'lm_note' => $request->note,
            'lm_reviewed_by' => $manager->id,
            'lm_reviewed_at' => now(),
        ]);

        PortalNotification::notifyAdmins('waiver', "Waiver approval needed: {$w->employee->full_name}",
            "{$manager->full_name} recommends approving the ".strtolower(DeductionWaiver::deductionLabel($w->type)).' waiver for '.$w->employee->full_name.' on '.Carbon::parse($w->date)->format('d M Y').". Note: {$request->note}",
            'admin.approvals', "waiver:admin:{$w->id}");

        return redirect()->back()
            ->with(
                'success',
                'Waiver recommended for approval and sent to Admin.'
            );
    }

    public function rejectWaiver(Request $request, $id)
    {
        /*
         * Manager MUST provide a note explaining
         * why they recommend rejection.
         */
        $request->validate([
            'note' => 'required|string|max:500',
        ]);

        $w = DeductionWaiver::findOrFail($id);

        // Payroll-locked month: no changes
        MonthlyLock::ensureOpen($w->date);

        /*
         * Security:
         * Manager can only review their own team.
         */
        abort_unless(
            $this->teamIds()->contains($w->employee_id),
            403
        );

        /*
         * Admin already finalized it.
         */
        if ($w->status !== 'pending') {
            return redirect()->back()
                ->with('error', 'This waiver has already been finalized by Admin.');
        }

        $manager = $this->currentManager();

        if ($w->isTeamLeadRequest()) {
            return $this->finalizeTeamLeadWaiver($w, 'rejected', $request->note);
        }

        /*
         * Again:
         * Main status remains "pending".
         *
         * lm_status contains Line Manager's recommendation.
         */
        $w->update([
            'lm_status' => 'rejected',
            'lm_note' => $request->note,
            'lm_reviewed_by' => $manager->id,
            'lm_reviewed_at' => now(),
        ]);

        PortalNotification::notifyAdmins('waiver', "Waiver review: {$w->employee->full_name}",
            "{$manager->full_name} does not recommend the ".strtolower(DeductionWaiver::deductionLabel($w->type)).' waiver for '.$w->employee->full_name.' on '.Carbon::parse($w->date)->format('d M Y').". Note: {$request->note}",
            'admin.approvals', "waiver:admin:{$w->id}");

        return redirect()->back()
            ->with(
                'success',
                'Waiver recommended for rejection and sent to Admin.'
            );
    }

    // REQUESTS OF A LINE MANAGER / MANAGER IN THE TEAM
    // Their reviewer and Admin see these at the same time; whoever decides first decides finally
    // (the same changes as an Admin decision are made, Admin is only informed).

    private function finalizeTeamLeadLeave(LeaveRequest $l, string $decision, ?string $note)
    {
        if ($decision === 'approved' && ($wfh = WfhRequest::overlappingFor($l->employee_id, $l->from_date, $l->to_date, ['approved']))) {
            return redirect()->back()->with('error', "{$l->employee->full_name} has approved Work From Home for {$wfh->dateLabel()}. Leave and Work From Home cannot be on the same day.");
        }

        $manager = $this->currentManager();

        $l->update([
            'manager_id' => $manager->id,
            'manager_status' => $decision === 'approved' ? LeaveRequest::MANAGER_APPROVED : LeaveRequest::MANAGER_REJECTED,
            'manager_note' => $note,
            'manager_reviewed_at' => now(),
            'status' => $decision,
            'reviewed_at' => now(),
            'reviewed_by' => $manager->full_name,
        ]);

        $this->announceFinalDecision($l->employee, 'leave', 'leave', 'admin.leaves', "Leave request {$decision}", $l->summary(), $decision, $note, "leave:{$l->id}");

        return redirect()->back()->with('success', 'Leave request '.$decision.'.');
    }

    private function finalizeTeamLeadWfh(WfhRequest $w, ?string $note)
    {
        if ($leave = LeaveRequest::overlappingFor($w->employee_id, $w->from_date, $w->to_date, ['approved'])) {
            return redirect()->back()->with('error', "{$w->employee->full_name} has an approved leave ({$leave->summary()}) on these dates. Leave and Work From Home cannot be on the same day.");
        }

        $manager = $this->currentManager();

        $w->update([
            'manager_id' => $manager->id,
            'manager_status' => LeaveRequest::MANAGER_APPROVED,
            'manager_note' => $note,
            'manager_reviewed_at' => now(),
            'status' => 'approved',
            'reviewed_at' => now(),
            'reviewed_by' => $manager->full_name,
        ]);

        $this->announceFinalDecision($w->employee, 'wfh', 'wfh', 'admin.wfh', 'Work From Home approved',
            'Work From Home for '.$w->dateLabel(), 'approved', $note, "wfh:{$w->id}");

        return redirect()->back()->with('success', 'Work From Home approved.');
    }

    private function finalizeTeamLeadCorrection(CorrectionRequest $c, string $decision, ?string $note)
    {
        $manager = $this->currentManager();

        $c->update([
            'lm_status' => $decision,
            'lm_note' => $note,
            'lm_reviewed_by' => $manager->id,
            'lm_reviewed_at' => now(),
            'status' => $decision,
            'reviewed_at' => now(),
            'reviewed_by' => $manager->full_name,
            'manager_note' => $note ?: ucfirst($decision).' by '.$this->reviewerLabel(),
        ]);

        // Approved → the corrected times go into attendance (and the penalty leaves are refreshed)
        $c->syncAttendanceWithStatus($manager->full_name);

        $this->announceFinalDecision($c->employee, 'correction', 'corrections', 'admin.approvals', "Correction request {$decision}",
            'correction request for '.Carbon::parse($c->date)->format('d M Y'), $decision, $note, "correction:{$c->id}");

        return redirect()->back()->with('success', 'Correction request '.$decision.'.');
    }

    private function finalizeTeamLeadWaiver(DeductionWaiver $w, string $decision, ?string $note)
    {
        $manager = $this->currentManager();

        $w->update([
            'lm_status' => $decision,
            'lm_note' => $note,
            'lm_reviewed_by' => $manager->id,
            'lm_reviewed_at' => now(),
            'status' => $decision,
            'reviewed_at' => now(),
            'reviewed_by' => $manager->full_name,
            'manager_note' => $note ?: ucfirst($decision).' by '.$this->reviewerLabel(),
        ]);

        // Approved → the item stops counting: attendance shows it as waived and penalty leaves are refreshed
        $w->syncAttendance();
        $w->employee?->load(['shift', 'gracePolicy'])->syncPenaltyLeaves($w->date);

        $this->announceFinalDecision($w->employee, 'waiver', 'penalty', 'admin.approvals', "Waiver {$decision}",
            strtolower(DeductionWaiver::deductionLabel($w->type)).' waiver for '.Carbon::parse($w->date)->format('d M Y'), $decision, $note, "waiver:{$w->id}");

        return redirect()->back()->with('success', 'Waiver request '.$decision.'.');
    }

    /**
     * Tell the applicant the final decision and inform Admin (no action needed).
     */
    private function announceFinalDecision(?Employee $applicant, string $type, string $page, string $adminRoute, string $title, string $subject, string $decision, ?string $note, string $key): void
    {
        $manager = $this->currentManager();
        $noteText = $note ? " Note: {$note}" : '';

        PortalNotification::notifyEmployee($applicant, $type, $title,
            "Your {$subject} was {$decision} by {$manager->full_name} ({$this->reviewerLabel()}).{$noteText}",
            $page, "{$key}:final-decision");

        PortalNotification::notifyAdmins($type, ucfirst($subject)." {$decision} by {$this->reviewerLabel()}",
            "{$manager->full_name} ({$this->reviewerLabel()}) {$decision} {$applicant?->full_name}'s {$subject}. No action is needed from Admin.{$noteText}",
            $adminRoute, "{$key}:final-decision");
    }
}
