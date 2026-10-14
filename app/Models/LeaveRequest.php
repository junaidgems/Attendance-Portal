<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class LeaveRequest extends Model
{
    /**
     * Manager stage: the request does not need Line Manager approval (goes straight to Admin).
     */
    public const MANAGER_NOT_REQUIRED = 'not_required';

    public const MANAGER_PENDING = 'pending';

    public const MANAGER_APPROVED = 'approved';

    public const MANAGER_REJECTED = 'rejected';

    protected $fillable = [
        'employee_id',
        'leave_type_id',
        'from_date',
        'to_date',
        'days',
        'is_half_day',
        'half_day_session',
        'reason',
        'status',
        'admin_note',
        'reviewed_by',
        'reviewed_at',
        'manager_id',
        'manager_status',
        'manager_note',
        'manager_reviewed_at',
        'is_penalty',
        'penalty_key',
        // Supporting document (e.g. doctor's slip), stored on the private disk
        'document_path',
    ];

    protected function casts(): array
    {
        return [
            'days' => 'float',
            'is_half_day' => 'boolean',
            'is_penalty' => 'boolean',
            'manager_reviewed_at' => 'datetime',
        ];
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function leaveType()
    {
        return $this->belongsTo(LeaveType::class);
    }

    /**
     * Line Manager (employee record) who reviews this request.
     */
    public function manager()
    {
        return $this->belongsTo(Employee::class, 'manager_id');
    }

    /**
     * Approved leave the employee actually took (they were away on these dates).
     * Penalty deductions are approved leave too, but only reduce the balance — the employee was not away.
     */
    /**
     * Requests starting in the leave year that contains the date (default today), e.g. 26 Jun 2026 – 25 Jun 2027.
     * Used for balances, so the full allowance comes back when a new leave year starts.
     */
    public function scopeInLeaveYear(Builder $query, Carbon|string|null $date = null): Builder
    {
        [$start, $end] = LeaveType::leaveYear($date);

        return $query->whereBetween('from_date', [$start->toDateString(), $end->toDateString()]);
    }

    public function scopeApprovedAbsence(Builder $query): Builder
    {
        return $query->where('status', 'approved')->where('is_penalty', false);
    }

    /**
     * The employee's leave (not a penalty deduction) overlapping the dates, e.g. to block Work From Home on a leave day.
     *
     * @param  array<int, string>  $statuses
     */
    public static function overlappingFor(int $employeeId, string $from, string $to, array $statuses = ['pending', 'approved']): ?self
    {
        return self::where('employee_id', $employeeId)
            ->where('is_penalty', false)
            ->whereIn('status', $statuses)
            ->whereDate('from_date', '<=', $to)
            ->whereDate('to_date', '>=', $from)
            ->latest('id')
            ->first();
    }

    /**
     * Requests currently awaiting Line Manager review.
     */
    public function scopeAwaitingManager(Builder $query): Builder
    {
        return $query->where('status', 'pending')
            ->where('manager_status', self::MANAGER_PENDING);
    }

    /**
     * Requests ready for Admin's final decision.
     */
    public function scopeAwaitingAdmin(Builder $query): Builder
    {
        return $query->where('status', 'pending')
            ->whereIn('manager_status', [self::MANAGER_APPROVED, self::MANAGER_REJECTED, self::MANAGER_NOT_REQUIRED]);
    }

    public function isAwaitingManager(): bool
    {
        return $this->status === 'pending' && $this->manager_status === self::MANAGER_PENDING;
    }

    /**
     * A Manager's request already decided by their Director: that decision is final, Admin cannot change it.
     * (While it is still pending, Admin may decide it too — whoever decides first.)
     */
    public function isDirectorDecision(): bool
    {
        return $this->status !== 'pending' && (bool) $this->manager?->isDirector();
    }

    public function isAwaitingAdmin(): bool
    {
        return $this->status === 'pending'
            && in_array($this->manager_status, [self::MANAGER_APPROVED, self::MANAGER_REJECTED, self::MANAGER_NOT_REQUIRED], true);
    }

    /**
     * A Line Manager's or Manager's own request: their reviewer (Manager / Director) and Admin see it at the
     * same time and whoever decides first decides finally. Employees' requests still go reviewer → Admin.
     */
    public function isTeamLeadRequest(): bool
    {
        return (bool) $this->employee?->isTeamLead();
    }

    /**
     * Admin may decide now: ready for Admin, or a Line Manager's / Manager's request still with its reviewer.
     */
    public function adminMayDecide(): bool
    {
        return $this->isAwaitingAdmin() || ($this->isAwaitingManager() && $this->isTeamLeadRequest());
    }

    /**
     * Who reviews the applicant's request first: their direct reporting person when that person leads
     * a team (Employee → Line Manager, Line Manager → Manager), or the Director a Manager reports to
     * (the Director's decision is final). Returns null when there is no such person (e.g. a Manager
     * reporting to nobody), in which case the request goes straight to Admin.
     */
    public static function reviewingManagerFor(Employee $applicant): ?Employee
    {
        $manager = $applicant->manager()->with('role')->first();

        if (! $manager || $manager->id === $applicant->id) {
            return null;
        }

        return $manager->isTeamLead() || self::isDirectorReview($applicant, $manager) ? $manager : null;
    }

    /**
     * A Manager's requests go to the Director they report to, and the Director decides them finally.
     */
    public static function isDirectorReview(Employee $applicant, ?Employee $reviewer): bool
    {
        return $reviewer !== null
            && $reviewer->isDirector()
            && $applicant->loadMissing('role')->roleKey() === 'manager';
    }

    /**
     * The Director who decides this applicant's requests, or null when Admin / a Line Manager does.
     */
    public static function directorFor(?Employee $applicant): ?Employee
    {
        $reviewer = $applicant ? self::reviewingManagerFor($applicant) : null;

        return $reviewer?->isDirector() ? $reviewer : null;
    }

    public static function isLineManager(Employee $employee): bool
    {
        return $employee->roleKey() === 'line_manager';
    }

    /**
     * For a half-day leave: worked minutes on the leave date when the punches show a full working day, otherwise null.
     *
     * Full day = that day's shift length (Saturday timings on Saturday) minus the early-departure grace.
     */
    public function fullDayWorkedMinutes(?object $settings = null): ?int
    {
        $employee = $this->employee;

        if (! $this->is_half_day || ! $employee?->zkteco_uid) {
            return null;
        }

        $date = Carbon::parse($this->from_date)->format('Y-m-d');

        $record = DB::table('device_attendance')
            ->where('uid', $employee->zkteco_uid)
            ->where('date', $date)
            ->first();

        if (! $record?->check_in || ! $record->check_out) {
            return null;
        }

        $settings ??= DB::table('attendance_settings')->first();

        $in = Carbon::parse($date.' '.$record->check_in);
        $out = Carbon::parse($date.' '.$record->check_out);
        $worked = $out->gt($in) ? (int) $in->diffInMinutes($out) : 0;

        $fullDayMinutes = $employee->shiftTimesOn($date, $settings)['full_day_min'];

        return $fullDayMinutes > 0 && $worked >= $fullDayMinutes ? $worked : null;
    }

    /**
     * e.g. "Casual Leave · 02 Oct 2026 (half day, first half)" or "Annual Leave · 02 Oct – 04 Oct 2026 (3 days)".
     */
    public function summary(): string
    {
        $from = Carbon::parse($this->from_date);
        $to = Carbon::parse($this->to_date);
        $dates = $from->isSameDay($to) ? $from->format('d M Y') : $from->format('d M').' – '.$to->format('d M Y');

        $length = $this->is_half_day
            ? 'half day, '.($this->half_day_session === 'second_half' ? 'second half' : 'first half')
            : $this->days.' '.($this->days == 1 ? 'day' : 'days');

        return ($this->leaveType->name ?? 'Leave')." · {$dates} ({$length})";
    }

    /**
     * Human readable stage label for views.
     */
    public function stageLabel(): string
    {
        return match (true) {
            $this->isAwaitingManager() => 'Awaiting '.($this->manager?->roleLabel() ?: 'Line Manager'),
            $this->isAwaitingAdmin() => 'Awaiting Admin',
            default => ucfirst($this->status),
        };
    }
}
