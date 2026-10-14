<?php

namespace App\Console\Commands;

use App\Models\DeductionWaiver;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\PortalNotification;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Notifies Admin / Line Manager / employee when an employee reaches the late-arrival or
 * early-out threshold (Attendance Policy) within the current attendance cycle.
 * Each threshold step (3, 6, 9 …) is notified once per cycle.
 */
class SendAttendanceAlerts extends Command
{
    protected $signature = 'attendance:send-alerts';

    protected $description = 'Send late-arrival and early-out threshold alerts to Admin, Line Manager and employee';

    public function handle(): int
    {
        $settings = DB::table('attendance_settings')->first();

        if (! $settings || ! $settings->attendance_alerts_enabled) {
            $this->info('Attendance alerts are disabled in the Attendance Policy.');

            return self::SUCCESS;
        }

        [$cycleStart, $cycleEnd] = $this->currentCycle($settings);
        $cycleLabel = $cycleStart->format('d M').' – '.$cycleEnd->format('d M Y');

        $adminUserIds = $settings->alert_notify_admin
            ? User::whereHas('role', fn ($q) => $q->where('name', 'administrator'))->pluck('id')
            : collect();

        $employees = Employee::with(['manager', 'user'])
            ->where('employment_status', 'active')
            ->whereNotNull('zkteco_uid')
            ->get();

        $records = DB::table('device_attendance')
            ->whereIn('uid', $employees->pluck('zkteco_uid'))
            ->whereBetween('date', [$cycleStart->toDateString(), $cycleEnd->toDateString()])
            ->where(fn ($q) => $q->where('late_minutes', '>', 0)->orWhere('early_out_minutes', '>', 0))
            ->get()
            ->groupBy(fn ($r) => (string) $r->uid);

        $leaves = LeaveRequest::whereIn('employee_id', $employees->pluck('id'))
            ->approvedAbsence()
            ->whereDate('from_date', '<=', $cycleEnd->toDateString())
            ->whereDate('to_date', '>=', $cycleStart->toDateString())
            ->get()
            ->groupBy('employee_id');

        $lateWaivers = DeductionWaiver::whereIn('employee_id', $employees->pluck('id'))
            ->where('type', 'late')
            ->where('status', 'approved')
            ->whereBetween('date', [$cycleStart->toDateString(), $cycleEnd->toDateString()])
            ->get()
            ->groupBy('employee_id');

        $sent = 0;

        foreach ($employees as $employee) {
            $days = $this->countableDays(
                $records->get((string) $employee->zkteco_uid, collect()),
                $leaves->get($employee->id, collect()),
                $lateWaivers->get($employee->id, collect())->pluck('date')->map(fn ($d) => Carbon::parse($d)->toDateString())
            );

            $recipients = $adminUserIds->all();
            // The direct Line Manager / Manager of the employee
            $teamLead = $employee->manager?->isTeamLead() ? $employee->manager : null;
            $managerUserId = $settings->alert_notify_line_manager ? $teamLead?->user_id : null;

            foreach ([
                'late' => [$days->where('late_minutes', '>', 0)->count(), (int) $settings->late_alert_threshold, 'late arrivals'],
                'early_out' => [$days->where('early_out_minutes', '>', 0)->count(), (int) $settings->early_out_alert_threshold, 'early outs'],
            ] as $type => [$count, $threshold, $label]) {
                if ($threshold < 1 || $count < $threshold) {
                    continue;
                }

                // Highest threshold step reached, e.g. 7 lates with threshold 3 → step 6
                $step = intdiv($count, $threshold) * $threshold;
                $key = "{$type}:{$employee->id}:{$cycleStart->toDateString()}:{$step}";
                $title = $type === 'late' ? 'Late arrival alert' : 'Early out alert';

                $sent += PortalNotification::sendToUsers(
                    $recipients,
                    $type,
                    $title,
                    "{$employee->full_name} has {$step} {$label} this cycle ({$cycleLabel}).",
                    route('admin.attendance.breakdown', ['from' => $cycleStart->toDateString(), 'to' => $cycleEnd->toDateString(), 'search' => $employee->employee_id]),
                    $key
                );

                if ($managerUserId) {
                    $sent += PortalNotification::sendToUsers(
                        [$managerUserId],
                        $type,
                        $title,
                        "Your team member {$employee->full_name} has {$step} {$label} this cycle ({$cycleLabel}).",
                        route($teamLead->portalPrefix().'.attendance.breakdown', [
                            'from' => $cycleStart->toDateString(),
                            'to' => $cycleEnd->toDateString(),
                            'member' => $employee->id,
                            'type' => $type === 'late' ? 'late' : 'early-out',
                        ]),
                        $key
                    );
                }

                if ($settings->alert_notify_employee && $employee->user_id) {
                    $sent += PortalNotification::sendToUsers(
                        [$employee->user_id],
                        $type,
                        $title,
                        "You have {$step} {$label} this cycle ({$cycleLabel}). Please take care of your timing.",
                        route('employee.attendance.breakdown', ['from' => $cycleStart->toDateString(), 'to' => $cycleEnd->toDateString()]),
                        $key
                    );
                }
            }
        }

        $this->info("{$sent} attendance alert notification(s) sent");

        return self::SUCCESS;
    }

    /**
     * Days that count toward the alert: approved leave days are excluded (e.g. a half-day leave explains
     * a late arrival or early out), and approved late waivers remove that day's late arrival.
     */
    private function countableDays(Collection $records, Collection $leaves, Collection $waivedLateDates): Collection
    {
        return $records
            ->reject(fn ($r) => $leaves->contains(fn ($l) => $r->date >= Carbon::parse($l->from_date)->toDateString()
                && $r->date <= Carbon::parse($l->to_date)->toDateString()))
            ->map(function ($r) use ($waivedLateDates) {
                if ($waivedLateDates->contains($r->date) || DeductionWaiver::isWaived($r, 'late')) {
                    $r->late_minutes = 0;
                }
                if (DeductionWaiver::isWaived($r, 'early_out')) {
                    $r->early_out_minutes = 0;
                }

                return $r;
            });
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function currentCycle(object $settings): array
    {
        $today = now()->startOfDay();
        $startDay = (int) ($settings->cycle_start_day ?? 1);

        $start = Carbon::create($today->year, $today->month, min($startDay, $today->daysInMonth));
        if ($start->gt($today)) {
            $previous = $today->copy()->subMonthNoOverflow();
            $start = Carbon::create($previous->year, $previous->month, min($startDay, $previous->daysInMonth));
        }

        return [$start, $today];
    }
}
