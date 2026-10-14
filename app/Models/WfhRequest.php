<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Work From Home request: Employee → Line Manager → Admin (same stages as a leave request).
 * On an approved WFH day the employee checks in / out from the portal.
 */
class WfhRequest extends Model
{
    protected $fillable = [
        'employee_id',
        'from_date',
        'to_date',
        'days',
        'reason',
        'status',
        'manager_id',
        'manager_status',
        'manager_note',
        'manager_reviewed_at',
        'admin_note',
        'reviewed_by',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'days' => 'integer',
            'manager_reviewed_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function manager()
    {
        return $this->belongsTo(Employee::class, 'manager_id');
    }

    public function scopeAwaitingManager(Builder $query): Builder
    {
        return $query->where('status', 'pending')->where('manager_status', LeaveRequest::MANAGER_PENDING);
    }

    public function scopeAwaitingAdmin(Builder $query): Builder
    {
        return $query->where('status', 'pending')
            ->whereIn('manager_status', [LeaveRequest::MANAGER_APPROVED, LeaveRequest::MANAGER_NOT_REQUIRED]);
    }

    /**
     * Approved WFH covering the given date.
     */
    public function scopeApprovedOn(Builder $query, Carbon|string $date): Builder
    {
        $day = Carbon::parse($date)->toDateString();

        return $query->where('status', 'approved')
            ->whereDate('from_date', '<=', $day)
            ->whereDate('to_date', '>=', $day);
    }

    public function isAwaitingManager(): bool
    {
        return $this->status === 'pending' && $this->manager_status === LeaveRequest::MANAGER_PENDING;
    }

    /**
     * A Manager's request already decided by their Director: that decision is final, Admin cannot change it.
     * (While it is still pending, Admin may decide it too — whoever decides first.)
     */
    public function isDirectorDecision(): bool
    {
        return $this->status !== 'pending' && (bool) $this->manager?->isDirector();
    }

    /**
     * A Line Manager's or Manager's own request (see LeaveRequest::isTeamLeadRequest).
     */
    public function isTeamLeadRequest(): bool
    {
        return (bool) $this->employee?->isTeamLead();
    }

    public function isAwaitingAdmin(): bool
    {
        return $this->status === 'pending'
            && in_array($this->manager_status, [LeaveRequest::MANAGER_APPROVED, LeaveRequest::MANAGER_NOT_REQUIRED], true);
    }

    public function stageLabel(): string
    {
        return match (true) {
            $this->isAwaitingManager() => 'Awaiting '.($this->manager?->roleLabel() ?: 'Line Manager'),
            $this->isAwaitingAdmin() => 'Awaiting Admin',
            default => ucfirst($this->status),
        };
    }

    /**
     * The employee's Work From Home overlapping the dates, e.g. to block a leave on a WFH day.
     *
     * @param  array<int, string>  $statuses
     */
    public static function overlappingFor(int $employeeId, string $from, string $to, array $statuses = ['pending', 'approved']): ?self
    {
        return self::where('employee_id', $employeeId)
            ->whereIn('status', $statuses)
            ->whereDate('from_date', '<=', $to)
            ->whereDate('to_date', '>=', $from)
            ->latest('id')
            ->first();
    }

    /**
     * WFH days for the given employees within a date range, keyed "{employee_id}_{Y-m-d}": approved requests
     * plus the employees' fixed WFH weekdays (set by Admin on the employee).
     *
     * @param  iterable<int>  $employeeIds
     * @return array<string, true>
     */
    public static function approvedDayKeys(iterable $employeeIds, Carbon|string $from, Carbon|string $to): array
    {
        $from = Carbon::parse($from)->startOfDay();
        $to = Carbon::parse($to)->startOfDay();
        $ids = collect($employeeIds)->all();
        $keys = [];

        self::where('status', 'approved')
            ->whereIn('employee_id', $ids)
            ->whereDate('from_date', '<=', $to->toDateString())
            ->whereDate('to_date', '>=', $from->toDateString())
            ->get(['employee_id', 'from_date', 'to_date'])
            ->each(function ($wfh) use ($from, $to, &$keys) {
                $day = Carbon::parse($wfh->from_date)->max($from)->copy();
                $last = Carbon::parse($wfh->to_date)->min($to);
                for (; $day->lte($last); $day->addDay()) {
                    $keys[$wfh->employee_id.'_'.$day->toDateString()] = true;
                }
            });

        if (Employee::hasScheduleColumns()) {
            Employee::whereIn('id', $ids)
                ->whereNotNull('wfh_days')
                ->get(['id', 'wfh_days'])
                ->each(function (Employee $employee) use ($from, $to, &$keys) {
                    for ($day = $from->copy(); $day->lte($to); $day->addDay()) {
                        if ($employee->isFixedWfhDay($day)) {
                            $keys[$employee->id.'_'.$day->toDateString()] = true;
                        }
                    }
                });
        }

        return $keys;
    }

    /**
     * The day has an office machine punch: the employee is on-site, not working from home.
     */
    public static function isOnSite(?object $record): bool
    {
        return (bool) $record?->check_in
            && ($record->source ?? 'device') !== 'wfh'
            && ($record->status ?? null) !== 'WFH';
    }

    /**
     * WFH label for an attendance day, or null when the day has nothing to do with WFH.
     * Office machine punches replace a portal check-in, so an employee who came to the office shows no WFH label.
     */
    public static function dayLabel(?object $record, bool $isApprovedWfhDay): ?string
    {
        $checkedInFromPortal = $record?->check_in
            && (($record->source ?? null) === 'wfh' || ($record->status ?? null) === 'WFH');

        return match (true) {
            $checkedInFromPortal => $isApprovedWfhDay || ($record->status ?? null) === 'WFH' ? 'WFH' : 'WFH (not approved)',
            $isApprovedWfhDay && ! $record?->check_in => 'WFH approved – no check-in',
            default => null,
        };
    }

    public function dateLabel(): string
    {
        $from = Carbon::parse($this->from_date);
        $to = Carbon::parse($this->to_date);

        return $from->isSameDay($to)
            ? $from->format('d M Y')
            : $from->format('d M').' – '.$to->format('d M Y');
    }
}
