<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\MonthlyLock;
use App\Models\PortalNotification;
use App\Models\WfhRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class WfhController extends Controller
{
    public function index()
    {
        $requests = WfhRequest::with(['employee.department', 'manager'])
            ->latest()
            ->limit(300)
            ->get();

        $today = now()->toDateString();

        // Everyone on approved WFH today, with their portal check-in / check-out
        $todayWfh = WfhRequest::with('employee.department')->approvedOn($today)->get()
            ->map(function ($w) use ($today) {
                $record = $w->employee?->zkteco_uid
                    ? DB::table('device_attendance')->where('uid', $w->employee->zkteco_uid)->where('date', $today)->first()
                    : null;

                $isOnSite = WfhRequest::isOnSite($record);

                return [
                    // Office machine punch = came to the office, so the day is on-site, not Work From Home
                    'state' => match (true) {
                        $isOnSite => 'On-site (office machine)',
                        ! $record?->check_in => 'Not checked in',
                        ! $record->check_out => 'Working from home',
                        default => 'Checked out',
                    },
                    'on_site' => $isOnSite,
                    'employee' => $w->employee?->full_name ?? '-',
                    'department' => $w->employee?->department->name ?? '-',
                    'check_in' => $record?->check_in,
                    'check_out' => $record?->check_out,
                    'late_minutes' => $record->late_minutes ?? 0,
                    'source' => $record->source ?? null,
                    'summary' => $record->work_summary ?? null,
                    'ip' => $record->check_in_ip ?? null,
                ];
            });

        return view('attendence.pages.admin.wfh', [
            // A Manager's request waiting for their Director is not Admin's to decide
            'pending' => $requests->where('status', 'pending')->reject(fn ($w) => $w->isDirectorDecision())->values(),
            'approved' => $requests->where('status', 'approved')->values(),
            'rejected' => $requests->where('status', 'rejected')->values(),
            'todayWfh' => $todayWfh,
        ]);
    }

    public function approve(Request $request, $id): RedirectResponse
    {
        $request->validate(['note' => 'nullable|string|max:500']);

        $wfh = WfhRequest::with('employee')->findOrFail($id);

        if ($wfh->isDirectorDecision()) {
            return back()->with('error', "This is a Manager's request decided by their Director. Admin cannot change it.");
        }

        // Payroll-locked month: no changes
        MonthlyLock::ensureOpen($wfh->from_date, $wfh->to_date);

        // Admin may decide any pending request (also before the Line Manager / Manager has reviewed it)
        // and may change a rejection into an approval later
        if ($wfh->status === 'approved') {
            return back()->with('error', 'This request is already approved.');
        }

        // Leave and Work From Home cannot be on the same day
        $leave = LeaveRequest::overlappingFor($wfh->employee_id, $wfh->from_date, $wfh->to_date, ['approved']);
        if ($leave) {
            return back()->with('error', "This employee has an approved leave ({$leave->summary()}) on these dates. Change that leave first, then approve Work From Home.");
        }

        $previousStatus = $wfh->status;
        $wasAwaitingManager = $wfh->isAwaitingManager();
        $skippedReviewer = $wasAwaitingManager ? $wfh->manager : null;

        $wfh->update([
            'status' => 'approved',
            'admin_note' => $request->note,
            'reviewed_by' => Auth::user()->name,
            'reviewed_at' => now(),
        ] + $this->skipReviewFields($wasAwaitingManager));

        if ($previousStatus !== 'pending') {
            $this->notifyDecisionChanged($wfh, 'approved', $request->note);

            return back()->with('success', 'Decision changed: Work From Home is now approved.');
        }

        $this->notifySkippedReviewer($wfh, $skippedReviewer, 'approved', $request->note);

        $this->notifyEmployee($wfh, 'Work From Home approved', "Your Work From Home for {$wfh->dateLabel()} is approved. Check in from the Work From Home page on that day."
            .($request->note ? " Admin note: {$request->note}" : ''));

        return back()->with('success', 'Work From Home approved!');
    }

    public function reject(Request $request, $id): RedirectResponse
    {
        $request->validate(['reason' => 'required|string|max:500']);

        $wfh = WfhRequest::with('employee')->findOrFail($id);

        if ($wfh->isDirectorDecision()) {
            return back()->with('error', "This is a Manager's request decided by their Director. Admin cannot change it.");
        }

        // Payroll-locked month: no changes
        MonthlyLock::ensureOpen($wfh->from_date, $wfh->to_date);

        // Admin may decide any pending request (also before the Line Manager / Manager has reviewed it)
        // and may withdraw an approval later, e.g. when the employee comes to the office after all
        if ($wfh->status === 'rejected') {
            return back()->with('error', 'This request is already rejected.');
        }

        $previousStatus = $wfh->status;
        $wasAwaitingManager = $wfh->isAwaitingManager();
        $skippedReviewer = $wasAwaitingManager ? $wfh->manager : null;

        $wfh->update([
            'status' => 'rejected',
            'admin_note' => $request->reason,
            'reviewed_by' => Auth::user()->name,
            'reviewed_at' => now(),
        ] + $this->skipReviewFields($wasAwaitingManager));

        if ($previousStatus !== 'pending') {
            $this->notifyDecisionChanged($wfh, 'rejected', $request->reason);

            return back()->with('success', 'Decision changed: Work From Home is now rejected.');
        }

        $this->notifySkippedReviewer($wfh, $skippedReviewer, 'rejected', $request->reason);

        $this->notifyEmployee($wfh, 'Work From Home rejected', "Your Work From Home for {$wfh->dateLabel()} was rejected: \"{$request->reason}\"");

        return back()->with('success', 'Work From Home rejected!');
    }

    /**
     * Decided by Admin before the Line Manager / Manager reviewed it: their step is no longer needed.
     *
     * @return array<string, mixed>
     */
    private function skipReviewFields(bool $wasAwaitingManager): array
    {
        return $wasAwaitingManager ? ['manager_status' => LeaveRequest::MANAGER_NOT_REQUIRED] : [];
    }

    /**
     * Let the Line Manager / Manager know Admin already decided, so it drops out of their queue.
     */
    private function notifySkippedReviewer(WfhRequest $wfh, ?Employee $reviewer, string $decision, ?string $note): void
    {
        if (! $reviewer?->user_id) {
            return;
        }

        PortalNotification::sendToUsers([$reviewer->user_id], 'wfh', "Work From Home {$decision} by Admin",
            "Admin {$decision} {$wfh->employee?->full_name}'s Work From Home for {$wfh->dateLabel()} directly, so it no longer needs your review."
                .($note ? " Admin note: {$note}" : ''),
            route($reviewer->portalPrefix().'.approvals'), "wfh:admin-direct:{$wfh->id}");
    }

    /**
     * Admin changed an earlier decision: tell the employee and the Line Manager / Manager of the request.
     */
    private function notifyDecisionChanged(WfhRequest $wfh, string $decision, ?string $note): void
    {
        $noteText = $note ? " Admin note: {$note}" : '';
        $dedupeKey = "wfh:decision-changed:{$wfh->id}:{$decision}:".now()->timestamp;

        PortalNotification::notifyEmployee($wfh->employee, 'wfh', "Work From Home decision changed to {$decision}",
            "Admin changed the decision on your Work From Home for {$wfh->dateLabel()} to {$decision}.{$noteText}",
            'wfh', $dedupeKey);

        $reviewer = $wfh->manager;
        if ($reviewer?->user_id) {
            PortalNotification::sendToUsers([$reviewer->user_id], 'wfh', "Work From Home decision changed to {$decision}",
                "Admin changed the decision on {$wfh->employee?->full_name}'s Work From Home for {$wfh->dateLabel()} to {$decision}.{$noteText}",
                route($reviewer->portalPrefix().'.approvals'), $dedupeKey);
        }
    }

    private function notifyEmployee(WfhRequest $wfh, string $title, string $message): void
    {
        PortalNotification::notifyEmployee($wfh->employee, 'wfh', $title, $message, 'wfh', "wfh:decision:{$wfh->id}");
    }
}
