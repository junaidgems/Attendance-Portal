<?php

namespace App\Models;

use App\Notifications\PortalMessage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;

/**
 * In-portal notification shown under the topbar bell, with an email copy.
 */
class PortalNotification extends Model
{
    /**
     * Requester-side pages: page key => [employee route, team-lead route without the prefix].
     * Team leads (Line Manager / Manager) share the same pages under their own prefix.
     */
    private const OWN_PAGES = [
        'leave' => ['employee.leave', 'leave'],
        'corrections' => ['employee.correction.requests', 'correction.requests'],
        'penalty' => ['employee.penalty', 'penalty.waivers'],
        'wfh' => ['employee.wfh', 'wfh'],
        'attendance' => ['employee.attendance.breakdown', 'my.attendance'],
    ];

    protected $fillable = [
        'user_id',
        'type',
        'title',
        'message',
        'link',
        'dedupe_key',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scopeUnread(Builder $query): Builder
    {
        return $query->whereNull('read_at');
    }

    /**
     * Send one notification (bell + email) to several users. A (user, dedupe_key) pair is only ever
     * notified once, so repeated runs (e.g. every sync) never send the same email twice.
     *
     * @param  iterable<int|null>  $userIds
     * @return int Number of new notifications created
     */
    public static function sendToUsers(iterable $userIds, string $type, string $title, string $message, ?string $link, ?string $dedupeKey): int
    {
        $now = now();
        $newUserIds = [];

        foreach (collect($userIds)->filter()->unique() as $userId) {
            $inserted = static::query()->insertOrIgnore([
                'user_id' => $userId,
                'type' => $type,
                'title' => $title,
                'message' => $message,
                'link' => $link,
                'dedupe_key' => $dedupeKey,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            if ($inserted) {
                $newUserIds[] = $userId;
            }
        }

        static::sendEmails($newUserIds, $title, $message, $link);

        return count($newUserIds);
    }

    /**
     * Tell the first reviewer about a new request: the applicant's Line Manager / Manager, or Admin when there is none.
     */
    public static function notifyReviewer(Employee $applicant, string $type, string $title, string $message, string $adminRoute, string $dedupeKey): void
    {
        $manager = LeaveRequest::reviewingManagerFor($applicant);

        $manager?->user_id
            ? static::sendToUsers([$manager->user_id], $type, $title, $message, route($manager->portalPrefix().'.approvals'), $dedupeKey)
            : static::notifyAdmins($type, $title, $message, $adminRoute, $dedupeKey);
    }

    public static function notifyAdmins(string $type, string $title, string $message, string $adminRoute, string $dedupeKey): void
    {
        static::sendToUsers(static::adminUserIds(), $type, $title, $message, route($adminRoute), $dedupeKey);
    }

    /**
     * Tell an employee (or Line Manager) about their own request, linking to their own page.
     *
     * @param  string  $page  leave | corrections | penalty | wfh | attendance
     */
    public static function notifyEmployee(?Employee $employee, string $type, string $title, string $message, string $page, string $dedupeKey): void
    {
        if (! $employee?->user_id) {
            return;
        }

        [$employeeRoute, $teamLeadRoute] = self::OWN_PAGES[$page];
        $employee->loadMissing('role');
        $route = $employee->isTeamLead() ? $employee->portalPrefix().'.'.$teamLeadRoute : $employeeRoute;

        static::sendToUsers([$employee->user_id], $type, $title, $message, route($route), $dedupeKey);
    }

    public static function adminUserIds(): Collection
    {
        return User::whereHas('role', fn ($q) => $q->where('name', 'administrator'))->pluck('id');
    }

    /**
     * Email copy of the notification. Never breaks the request: mail problems are only logged.
     *
     * @param  array<int>  $userIds
     */
    private static function sendEmails(array $userIds, string $title, string $message, ?string $link): void
    {
        if (! $userIds || ! static::emailEnabled()) {
            return;
        }

        try {
            $users = User::whereIn('id', $userIds)->whereNotNull('email')->get()->filter->isActive();
            $notification = new PortalMessage($title, $message, $link);

            // Queue in the background once a "jobs" table exists (production); otherwise send right away
            static::queueAvailable()
                ? Notification::send($users, $notification)
                : Notification::sendNow($users, $notification);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    private static function emailEnabled(): bool
    {
        try {
            return (bool) (DB::table('attendance_settings')->value('email_notifications_enabled') ?? true);
        } catch (\Throwable) {
            // Column not added yet
            return true;
        }
    }

    private static function queueAvailable(): bool
    {
        static $available;

        return $available ??= config('queue.default') !== 'sync'
            && (config('queue.default') !== 'database' || Schema::hasTable(config('queue.connections.database.table', 'jobs')));
    }
}
