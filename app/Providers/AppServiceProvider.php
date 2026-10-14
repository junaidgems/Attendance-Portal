<?php

namespace App\Providers;

use App\Models\CorrectionRequest;
use App\Models\DeductionWaiver;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\PortalNotification;
use App\Models\WfhRequest;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Topbar bell: latest notifications and unread count of the logged-in user
        View::composer('attendence.partials.topbar', function ($view) {
            $userId = Auth::id();
            $notifications = collect();
            $unreadCount = 0;

            if ($userId) {
                try {
                    // Cleared notifications stay in the table (for their dedupe key) but leave the bell
                    $notifications = PortalNotification::where('user_id', $userId)->whereNull('cleared_at')->latest()->limit(10)->get();
                    $unreadCount = PortalNotification::where('user_id', $userId)->whereNull('cleared_at')->unread()->count();
                } catch (QueryException $e) {
                    // portal_notifications table not created yet — keep the bell empty instead of breaking every page
                    report($e);
                }
            }

            $view->with([
                'topbarNotifications' => $notifications,
                'topbarUnreadCount' => $unreadCount,
            ]);
        });

        // Current attendance cycle from the Attendance Policy, e.g. "26 Sep – 25 Oct 2026"
        View::composer(['attendence.partials.topbar', 'attendence.pages.employee.dashboard'], function ($view) {
            $label = '';

            try {
                [$start, $end] = Employee::attendanceCycle(now(), DB::table('attendance_settings')->first());
                $label = $start->format('d M').' – '.$end->format('d M Y');
            } catch (QueryException $e) {
                report($e);
            }

            $view->with('attendanceCycleLabel', $label);
        });

        // Sidebar: red count on menu items that have requests waiting for the logged-in user's decision
        View::composer('attendence.partials.sidebar', function ($view) {
            $badges = [];

            try {
                $badges = $this->sidebarBadges();
            } catch (QueryException $e) {
                report($e);
            }

            $view->with('sidebarBadges', $badges);
        });
    }

    /**
     * Pending request counts per sidebar route, matching the pending lists on those pages.
     *
     * @return array<string, int>
     */
    private function sidebarBadges(): array
    {
        $user = Auth::user();

        if (! $user) {
            return [];
        }

        if ($user->hasRole('administrator')) {
            $leaves = LeaveRequest::awaitingAdmin()->count();

            // Managers' requests waiting for their Director are not counted: Admin does not decide them
            $notForDirector = fn ($request) => ! $request->isDirectorDecision();

            return [
                'admin.approvals' => CorrectionRequest::with('employee')->where('status', 'pending')->get()->filter($notForDirector)->count()
                    + $leaves
                    + DeductionWaiver::with('employee')->where('status', 'pending')->get()->filter($notForDirector)->count(),
                'admin.leaves' => $leaves,
                'admin.wfh' => WfhRequest::with('manager')->where('status', 'pending')->get()->filter($notForDirector)->count(),
            ];
        }

        $lead = $user->employee;

        // Director: requests of the Managers reporting to them
        if ($lead?->isDirector()) {
            $managerIds = Employee::where('reports_to_id', $lead->id)
                ->whereHas('role', fn ($q) => $q->where('name', 'manager'))
                ->pluck('id');

            return [
                'director.approvals' => CorrectionRequest::whereIn('employee_id', $managerIds)->awaitingManager()->count()
                    + LeaveRequest::whereIn('employee_id', $managerIds)->awaitingManager()->count()
                    + DeductionWaiver::whereIn('employee_id', $managerIds)->where('status', 'pending')->where('lm_status', 'pending')->count()
                    + WfhRequest::whereIn('employee_id', $managerIds)->awaitingManager()->count(),
            ];
        }

        if (! $lead?->isTeamLead()) {
            return [];
        }

        $teamIds = Employee::where('reports_to_id', $lead->id)->pluck('id');

        return [
            $lead->portalPrefix().'.approvals' => CorrectionRequest::whereIn('employee_id', $teamIds)->awaitingManager()->count()
                + LeaveRequest::whereIn('employee_id', $teamIds)->awaitingManager()->count()
                + DeductionWaiver::whereIn('employee_id', $teamIds)->where('status', 'pending')->where('lm_status', 'pending')->count()
                + WfhRequest::whereIn('employee_id', $teamIds)->awaitingManager()->count(),
        ];
    }
}
