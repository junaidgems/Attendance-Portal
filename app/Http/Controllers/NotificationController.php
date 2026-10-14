<?php

namespace App\Http\Controllers;

use App\Models\PortalNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    /**
     * Mark one notification as read and open its link.
     */
    public function open(PortalNotification $notification): RedirectResponse
    {
        abort_unless((int) $notification->user_id === (int) Auth::id(), 403);

        if (! $notification->read_at) {
            $notification->update(['read_at' => now()]);
        }

        return $notification->link
            ? redirect()->to($notification->link)
            : redirect()->back();
    }

    public function readAll(): RedirectResponse
    {
        PortalNotification::where('user_id', Auth::id())
            ->unread()
            ->update(['read_at' => now()]);

        return redirect()->back();
    }

    /**
     * Remove one notification from the bell. The row is kept (only hidden), so its dedupe key still
     * stops the same alert and email from being sent again on the next sync.
     */
    public function clear(PortalNotification $notification): RedirectResponse
    {
        abort_unless((int) $notification->user_id === (int) Auth::id(), 403);

        $notification->forceFill([
            'read_at' => $notification->read_at ?? now(),
            'cleared_at' => now(),
        ])->save();

        return redirect()->back();
    }

    /**
     * Remove every notification from the logged-in user's bell (kept hidden, see clear()).
     */
    public function clearAll(): RedirectResponse
    {
        $notifications = PortalNotification::where('user_id', Auth::id())->whereNull('cleared_at');

        (clone $notifications)->unread()->update(['read_at' => now()]);
        $notifications->update(['cleared_at' => now()]);

        return redirect()->back();
    }
}
