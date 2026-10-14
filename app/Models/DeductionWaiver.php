<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class DeductionWaiver extends Model
{
    protected $fillable = [
        'employee_id',
        'date',
        'type',
        'reason',
        'status',

        // Line Manager review
        'lm_status',
        'lm_note',
        'lm_reviewed_by',
        'lm_reviewed_at',

        // Final Admin review
        'manager_note',
        'reviewed_at',
        'reviewed_by',
    ];

    /**
     * Penalty item type => label shown on penalty and approval pages.
     */
    public const TYPE_LABELS = [
        'late' => 'Late Arrival',
        'early_out' => 'Early Out',
        'half_day' => 'Half-Day',
        'absent' => 'Absent',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * The Line Manager / Manager / Director who reviewed the waiver.
     */
    public function lmReviewer()
    {
        return $this->belongsTo(Employee::class, 'lm_reviewed_by');
    }

    /**
     * A Manager's request already decided by their Director: that decision is final, Admin cannot change it.
     * (While it is still pending, Admin may decide it too — whoever decides first.)
     */
    public function isDirectorDecision(): bool
    {
        return (bool) $this->lmReviewer?->isDirector();
    }

    /**
     * A Line Manager's or Manager's own request (see LeaveRequest::isTeamLeadRequest).
     */
    public function isTeamLeadRequest(): bool
    {
        return (bool) $this->employee?->isTeamLead();
    }

    public function isAwaitingManager(): bool
    {
        return $this->status === 'pending' && $this->lm_status === 'pending';
    }

    /**
     * Admin may decide now: reviewed by the Line Manager / Manager, no review needed, or a Line Manager's /
     * Manager's own request still with its reviewer.
     */
    public function adminMayDecide(): bool
    {
        return $this->status === 'pending' && (! $this->isAwaitingManager() || $this->isTeamLeadRequest());
    }

    /**
     * Is this item (late / early_out / half_day) waived on the day's attendance record?
     * (An absent day has no record; its waiver only counts on the penalty pages.)
     * Approved waivers are written to device_attendance.waived_types, so attendance pages show the day
     * as if that issue did not happen (e.g. a waived late arrival counts as Present).
     */
    public static function isWaived(?object $record, string $type): bool
    {
        $waived = $record->waived_types ?? null;

        return $waived && in_array($type, explode(',', $waived), true);
    }

    /**
     * Write every approved waiver of this employee and day onto the attendance record (and remove
     * rejected / reverted ones), so the attendance status follows the final decision.
     */
    public static function syncAttendanceFor(int $employeeId, string $date): void
    {
        $employee = Employee::find($employeeId);

        if (! $employee?->zkteco_uid) {
            return;
        }

        $day = Carbon::parse($date)->toDateString();

        $types = self::where('employee_id', $employeeId)
            ->whereDate('date', $day)
            ->where('status', 'approved')
            ->pluck('type')
            ->unique()
            ->sort()
            ->implode(',');

        DB::table('device_attendance')
            ->where('uid', $employee->zkteco_uid)
            ->where('date', $day)
            ->update(['waived_types' => $types ?: null]);
    }

    public function syncAttendance(): void
    {
        self::syncAttendanceFor($this->employee_id, $this->date);
    }

    /**
     * e.g. "Late Deduction", "Early Out Deduction", "Half-Day Deduction".
     */
    public static function deductionLabel(?string $type): string
    {
        return match ($type) {
            'late' => 'Late Deduction',
            'early_out' => 'Early Out Deduction',
            'half_day' => 'Half-Day Deduction',
            'absent' => 'Absent Deduction',
            default => 'Attendance Deduction',
        };
    }
}
