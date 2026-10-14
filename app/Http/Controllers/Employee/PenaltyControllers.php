<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\DeductionWaiver;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\MonthlyLock;
use App\Models\PortalNotification;
use Carbon\Carbon;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PenaltyControllers extends Controller
{
    public function index(Request $request)
    {
        $employee = Auth::user()->employee->load(['shift', 'gracePolicy']);
        $settings = DB::table('attendance_settings')->first();

        return view('attendence.pages.employee.penalty-waivers', self::penaltyViewData($employee, $settings, (int) $request->input('cycle', 0)));
    }

    public function storeWaiver(Request $request)
    {
        $request->validate([
            'date' => 'required|date|before_or_equal:today',
            'type' => 'required|in:late,early_out,half_day,absent',
            'reason' => 'required|string|max:500',
        ]);

        self::submitWaiver(Auth::user()->employee, $request->date, $request->type, $request->reason);

        return redirect()->back()->with('success', 'Waiver request submitted!');
    }

    /**
     * Create or resubmit a waiver. It goes to the Line Manager first; employees without a
     * Line Manager (and Line Managers themselves) go straight to Admin.
     */
    public static function submitWaiver(Employee $employee, string $date, string $type, string $reason): DeductionWaiver
    {
        // Payroll-locked month: no changes
        MonthlyLock::ensureOpen($date);

        // One waiver request per item: once submitted (pending, approved or rejected) it cannot be requested again
        $existing = DeductionWaiver::where('employee_id', $employee->id)
            ->whereDate('date', $date)
            ->where('type', $type)
            ->first();

        if ($existing) {
            $day = Carbon::parse($date)->format('d M Y');

            throw new HttpResponseException(redirect()->back()->with('error', match ($existing->status) {
                'pending' => "A waiver for this item on {$day} is already pending review.",
                'approved' => "The waiver for this item on {$day} has already been approved.",
                default => "The waiver for this item on {$day} was rejected and cannot be requested again.",
            }));
        }

        $needsManager = LeaveRequest::reviewingManagerFor($employee) !== null;

        $waiver = DeductionWaiver::updateOrCreate(
            ['employee_id' => $employee->id, 'date' => $date, 'type' => $type],
            [
                'reason' => $reason,
                'status' => 'pending',
                // A resubmitted waiver is reviewed again from the start
                'lm_status' => $needsManager ? 'pending' : 'not_required',
                'lm_note' => null,
                'lm_reviewed_by' => null,
                'lm_reviewed_at' => null,
                'manager_note' => null,
                'reviewed_by' => null,
                'reviewed_at' => null,
            ]
        );

        $label = strtolower(DeductionWaiver::deductionLabel($type));
        $title = "Waiver request from {$employee->full_name}";
        $message = "{$employee->full_name} requested a {$label} waiver for ".Carbon::parse($date)->format('d M Y').". Reason: {$reason}";
        // Every (re)submission is a new request for the reviewer
        $key = "waiver:request:{$waiver->id}:".now()->timestamp;

        $needsManager
            ? PortalNotification::notifyReviewer($employee, 'waiver', $title, $message, 'admin.approvals', $key)
            : PortalNotification::notifyAdmins('waiver', $title, $message, 'admin.approvals', $key);

        return $waiver;
    }

    /**
     * Shared data for the Employee and Line Manager penalty pages.
     *
     * @return array<string, mixed>
     */
    public static function penaltyViewData(Employee $employee, ?object $settings, int $offset): array
    {
        $cycles = [];
        $cursor = now();
        for ($i = 0; $i < 4; $i++) {
            [$start, $end] = Employee::attendanceCycle($cursor, $settings);
            $cycles[$i] = [$start->toDateString(), $end->toDateString(), $start->format('d M').' – '.$end->format('d M Y')];
            $cursor = $start->copy()->subDay();
        }
        $offset = isset($cycles[$offset]) ? $offset : 0;
        [$from, $to, $label] = $cycles[$offset];

        $summary = $employee->penaltySummary($from, min($to, now()->toDateString()), $settings);

        // Casual leaves actually deducted for this cycle (automatic penalty leaves)
        $clDeducted = $employee->leaveRequests()
            ->where('is_penalty', true)
            ->where('penalty_key', 'like', $from.':%')
            ->sum('days');

        $casualType = LeaveType::where('name', 'Casual Leave')->first();
        $clUsed = $casualType
            ? (float) $employee->leaveRequests()->where('leave_type_id', $casualType->id)->where('status', 'approved')->inLeaveYear()->sum('days')
            : 0;

        return [
            'items' => $summary['items'],
            'lateCount' => $summary['counts']['late'],
            'earlyOutCount' => $summary['counts']['early_out'],
            'halfDayCount' => $summary['counts']['half_day'],
            'absentCount' => $summary['counts']['absent'],
            'thresholds' => $summary['thresholds'],
            'clDue' => $summary['cl_due'],
            'clDeducted' => $clDeducted,
            'clBalance' => max(0, $employee->leaveAllowance($casualType) - $clUsed),
            // Only a shortfall once penalties are really being deducted
            'salaryDeduct' => $settings?->penalty_policy_enabled ? max(0, $summary['cl_due'] - $clDeducted) : 0,
            'penaltyEnabled' => (bool) $settings?->penalty_policy_enabled,
            'cycles' => $cycles,
            'offset' => $offset,
            'label' => $label,
        ];
    }
}
