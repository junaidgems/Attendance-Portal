<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The Attendance Policy page is locked for Admin. Admin asks the Director to unlock it; the Director's
 * unlock opens the page for UNLOCK_MINUTES (or until the Director locks it again).
 * Stored on the attendance_settings row (policy_unlock_requested_at/_by, policy_unlocked_until/_by).
 */
class AttendancePolicyLock
{
    public const UNLOCK_MINUTES = 60;

    private static ?bool $supported = null;

    /**
     * The lock columns exist (until they are added, the page stays open as before).
     */
    public static function isSupported(): bool
    {
        return self::$supported ??= Schema::hasColumn('attendance_settings', 'policy_unlocked_until');
    }

    public static function forgetCache(): void
    {
        self::$supported = null;
    }

    public static function isUnlocked(): bool
    {
        return self::unlockedUntil()?->isFuture() ?? ! self::isSupported();
    }

    public static function unlockedUntil(): ?Carbon
    {
        $until = self::isSupported() ? self::row()?->policy_unlocked_until : null;

        return $until ? Carbon::parse($until) : null;
    }

    /**
     * Everything the policy page and the Director's approvals page show about the lock.
     *
     * @return array{supported: bool, unlocked: bool, unlocked_until: ?Carbon, unlocked_by: ?string, requested_at: ?Carbon, requested_by: ?string}
     */
    public static function status(): array
    {
        $row = self::isSupported() ? self::row() : null;
        $unlocked = self::isUnlocked();

        return [
            'supported' => self::isSupported(),
            'unlocked' => $unlocked,
            'unlocked_until' => $unlocked ? self::unlockedUntil() : null,
            'unlocked_by' => $unlocked ? $row?->policy_unlocked_by : null,
            // A request is only open while the page is still locked
            'requested_at' => ! $unlocked && $row?->policy_unlock_requested_at ? Carbon::parse($row->policy_unlock_requested_at) : null,
            'requested_by' => ! $unlocked ? $row?->policy_unlock_requested_by : null,
        ];
    }

    /**
     * Admin asks the Director to unlock the page.
     */
    public static function request(string $by): void
    {
        self::update(['policy_unlock_requested_at' => now(), 'policy_unlock_requested_by' => $by]);

        PortalNotification::sendToUsers(
            self::directorUserIds(),
            'policy',
            'Attendance Policy unlock requested',
            "{$by} asked to unlock the Attendance Policy page. Open Approvals to unlock it.",
            route('director.approvals'),
            'policy-unlock:request:'.now()->timestamp
        );

        AuditLog::record('policy', 'Policy unlock requested', "{$by} asked the Director to unlock the Attendance Policy", $by);
    }

    /**
     * The Director opens the page for Admin for UNLOCK_MINUTES.
     */
    public static function unlock(string $by): Carbon
    {
        $until = now()->addMinutes(self::UNLOCK_MINUTES);

        self::update([
            'policy_unlocked_until' => $until,
            'policy_unlocked_by' => $by,
            'policy_unlock_requested_at' => null,
            'policy_unlock_requested_by' => null,
        ]);

        PortalNotification::notifyAdmins('policy', 'Attendance Policy unlocked',
            "{$by} (Director) unlocked the Attendance Policy page until {$until->format('h:i A')}. Make your changes before then.",
            'admin.attendance.policy', 'policy-unlock:open:'.now()->timestamp);

        AuditLog::record('policy', 'Policy unlocked by Director', "Attendance Policy page unlocked until {$until->format('d M Y h:i A')}", $by);

        return $until;
    }

    /**
     * The Director closes the page again before the time runs out.
     */
    public static function lock(string $by): void
    {
        self::update(['policy_unlocked_until' => null, 'policy_unlocked_by' => null]);

        PortalNotification::notifyAdmins('policy', 'Attendance Policy locked',
            "{$by} (Director) locked the Attendance Policy page again.",
            'admin.attendance.policy', 'policy-unlock:close:'.now()->timestamp);

        AuditLog::record('policy', 'Policy locked by Director', 'Attendance Policy page locked', $by);
    }

    /**
     * Stop a change to the policy page while it is locked.
     */
    public static function ensureUnlocked(): void
    {
        if (! self::isUnlocked()) {
            throw new HttpResponseException(redirect()->route('admin.attendance.policy')
                ->with('error', 'The Attendance Policy is locked. Ask the Director to unlock it first.'));
        }
    }

    private static function row(): ?object
    {
        return DB::table('attendance_settings')->first();
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private static function update(array $values): void
    {
        DB::table('attendance_settings')->where('id', '>', 0)->update($values);
    }

    /**
     * @return Collection<int, int>
     */
    private static function directorUserIds()
    {
        return Employee::whereHas('role', fn ($q) => $q->where('name', 'director'))
            ->where('employment_status', 'active')
            ->whereNotNull('user_id')
            ->pluck('user_id');
    }
}
