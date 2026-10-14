<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Payroll period lock (table monthly_locks, month_year = "2026-09").
 * A locked month is frozen: no leave, correction, waiver or WFH changes, and no automatic
 * recalculation (machine sync, penalties, missing punch policy) for dates in that month.
 */
class MonthlyLock extends Model
{
    protected $fillable = ['month_year', 'is_locked', 'locked_at'];

    protected function casts(): array
    {
        return [
            'is_locked' => 'boolean',
            'locked_at' => 'datetime',
        ];
    }

    /**
     * Locked months ("Y-m") — loaded once per request / command run.
     *
     * @var array<string, true>|null
     */
    private static ?array $locked = null;

    public static function lockedMonths(): array
    {
        return self::$locked ??= self::where('is_locked', true)->pluck('month_year')->flip()->map(fn () => true)->all();
    }

    /**
     * Forget the cached list (after locking / unlocking).
     */
    public static function forgetCache(): void
    {
        self::$locked = null;
    }

    public static function isLocked(Carbon|string|null $date): bool
    {
        return $date !== null && isset(self::lockedMonths()[Carbon::parse($date)->format('Y-m')]);
    }

    /**
     * First locked month touched by a date range, e.g. a leave from 28 Sep to 3 Oct.
     */
    public static function firstLockedIn(Carbon|string $from, Carbon|string|null $to = null): ?Carbon
    {
        if (! self::lockedMonths()) {
            return null;
        }

        $month = Carbon::parse($from)->startOfMonth();
        $last = Carbon::parse($to ?? $from)->startOfMonth();

        for (; $month->lte($last); $month->addMonth()) {
            if (self::isLocked($month)) {
                return $month->copy();
            }
        }

        return null;
    }

    /**
     * Stop the current action (redirect back with an error) when the dates fall in a locked month.
     */
    public static function ensureOpen(Carbon|string|null $from, Carbon|string|null $to = null): void
    {
        $month = $from ? self::firstLockedIn($from, $to) : null;

        if ($month) {
            throw new HttpResponseException(
                redirect()->back()->withInput()->with('error', self::message($month))
            );
        }
    }

    public static function message(Carbon|string $date): string
    {
        return Carbon::parse($date)->format('F Y').' is locked for payroll, so its attendance can no longer be changed. Please contact Admin.';
    }
}
