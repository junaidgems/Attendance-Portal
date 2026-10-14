<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\CorrectionRequest;
use App\Models\Employee;
use App\Models\MonthlyLock;
use App\Models\PortalNotification;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Missing punch policy (only one punch in the day: no check-out, or no check-in):
 * 1. Tell the employee (and their Line Manager / Manager and Admin, per policy) the day after.
 * 2. If no correction is submitted within the allowed days, apply the policy: count the day as a
 *    half day or absent (or only keep it flagged). Days with a pending correction wait for its decision.
 */
class ApplyMissingPunchPolicy extends Command
{
    protected $signature = 'attendance:missing-punches';

    protected $description = 'Notify about missing check-outs and apply the missing punch policy after the correction deadline';

    public function handle(): int
    {
        $settings = DB::table('attendance_settings')->first();

        if (! ($settings->missing_punch_enabled ?? false)) {
            $this->info('Missing punch policy is disabled in the Attendance Policy.');

            return self::SUCCESS;
        }

        $today = now()->startOfDay();
        $days = max(0, (int) $settings->missing_punch_correction_days);
        $action = $settings->missing_punch_after_deadline ?? 'half_day';
        $from = $settings->missing_punch_policy_from ?? $today->toDateString();

        $records = DB::table('device_attendance')
            ->where('date', '>=', $from)
            ->where('date', '<', $today->toDateString())
            ->where(fn ($q) => $q
                ->where(fn ($q) => $q->whereNotNull('check_in')->whereNull('check_out'))
                ->orWhere(fn ($q) => $q->whereNull('check_in')->whereNotNull('check_out')))
            ->whereNull('adjustment')
            ->get();

        $employees = Employee::with(['manager.role', 'role', 'shift', 'gracePolicy'])
            ->where('employment_status', 'active')
            ->whereIn('zkteco_uid', $records->pluck('uid')->unique())
            ->get()
            ->keyBy(fn ($e) => (string) $e->zkteco_uid);

        // Only the employee's working days: a punch on a weekend (or their own off day) or holiday is not a missed working day
        $holidays = DB::table('holidays')->get();
        $records = $records->filter(function ($r) use ($employees, $holidays, $settings) {
            $date = Carbon::parse($r->date)->toDateString();
            $employee = $employees->get((string) $r->uid);

            return $employee
                && ! MonthlyLock::isLocked($date)
                && $employee->worksOn($date, $settings)
                && ! $holidays->contains(fn ($h) => $date >= $h->from_date && $date <= ($h->to_date ?? $h->from_date));
        });

        $notified = 0;
        $applied = 0;

        foreach ($records as $record) {
            $employee = $employees->get((string) $record->uid);
            if (! $employee) {
                continue;
            }

            $date = Carbon::parse($record->date);
            $deadline = $date->copy()->addDays($days);
            $notified += $this->notify($employee, $record, $date, $deadline, $settings);

            $hasCorrection = CorrectionRequest::where('employee_id', $employee->id)
                ->whereDate('date', $date)
                ->whereIn('status', ['pending', 'approved'])
                ->exists();

            if ($action !== 'flag' && $today->gt($deadline) && ! $hasCorrection) {
                $this->resolve($employee, $record, $action, $days, $settings);
                $applied++;
            }
        }

        $this->info("Missing punches: {$notified} notification(s) sent, {$applied} day(s) resolved by policy");

        return self::SUCCESS;
    }

    /**
     * One notice per missing punch for the employee, their team lead and Admin (per policy).
     */
    private function notify(Employee $employee, object $record, Carbon $date, Carbon $deadline, object $settings): int
    {
        $day = $date->format('D d M Y');
        $key = "missing-punch:{$employee->id}:{$date->toDateString()}";
        $sent = 0;

        // Which punch is missing
        $missingIn = ! $record->check_in;
        $missing = $missingIn ? 'check-in' : 'check-out';
        $what = $missingIn
            ? "You checked out on {$day} but there is no check-in. If you forgot to check in or the device was not working,"
            : "You checked in on {$day} but there is no check-out. If you forgot to check out or left for office work,";
        $whatLead = $missingIn
            ? "{$employee->full_name} checked out on {$day} but did not check in."
            : "{$employee->full_name} checked in on {$day} but did not check out.";

        if ($employee->user_id) {
            $sent += PortalNotification::sendToUsers(
                [$employee->user_id],
                'correction',
                "Missing {$missing} on {$date->format('d M')}",
                "{$what} please submit a correction by {$deadline->format('d M Y')}.",
                route($employee->portalPrefix().'.correction.requests', ['date' => $date->toDateString(), 'type' => "forgot-{$missing}"]),
                $key
            );
        }

        $lead = $employee->manager?->isTeamLead() ? $employee->manager : null;
        if (($settings->missing_punch_notify_manager ?? true) && $lead?->user_id) {
            $sent += PortalNotification::sendToUsers(
                [$lead->user_id],
                'correction',
                "Missing {$missing}: {$employee->full_name}",
                "{$whatLead} Please follow up.",
                route($lead->portalPrefix().'.attendance.breakdown', [
                    'from' => $date->toDateString(), 'to' => $date->toDateString(), 'member' => $employee->id, 'type' => 'missing-punch',
                ]),
                $key
            );
        }

        if ($settings->missing_punch_notify_admin ?? false) {
            $sent += PortalNotification::sendToUsers(
                PortalNotification::adminUserIds(),
                'correction',
                "Missing {$missing}: {$employee->full_name}",
                $whatLead,
                route('admin.attendance.breakdown', ['from' => $date->toDateString(), 'to' => $date->toDateString(), 'search' => $employee->employee_id]),
                $key
            );
        }

        return $sent;
    }

    /**
     * Deadline passed without a correction: count the day as a half day or absent. The machine punch
     * is kept in original_check_in / original_check_out, and an approved correction later still replaces this.
     */
    private function resolve(Employee $employee, object $record, string $action, int $days, object $settings): void
    {
        $date = Carbon::parse($record->date)->toDateString();
        $missing = $record->check_in ? 'check-out' : 'check-in';

        if ($action === 'half_day') {
            // Worked time exactly at the half-day limit → every page counts it as a Half Day
            $halfDayMax = max(1, $employee->shiftTimesOn($date, $settings)['half_day_max']);

            if ($record->check_in) {
                $checkIn = $record->check_in;
                $checkOut = Carbon::parse($date.' '.$checkIn)->addMinutes($halfDayMax)->format('H:i:s');
            } else {
                // Only the evening punch: the half day ends at it (no late can be counted)
                $checkOut = $record->check_out;
                $checkIn = Carbon::parse($date.' '.$checkOut)->subMinutes($halfDayMax)->format('H:i:s');
            }
            $label = 'Half Day';
        } else {
            $checkIn = null;
            $checkOut = null;
            $label = 'Absent';
        }

        // A filled-in check-in must not count as late
        $punctuality = $record->check_in
            ? Employee::punctuality($employee, $date, $checkIn, $checkOut, $settings)
            : ['late_minutes' => 0, 'early_out_minutes' => 0];

        DB::table('device_attendance')->where('id', $record->id)->update([
            'check_in' => $checkIn,
            'check_out' => $checkOut,
            'status' => $punctuality['late_minutes'] > 0 ? 'Late' : 'On Time',
            'late_minutes' => $punctuality['late_minutes'],
            'early_out_minutes' => 0,
            'adjustment' => $action === 'half_day' ? 'missing_punch_half_day' : 'missing_punch_absent',
            'adjustment_note' => "Missing {$missing} not corrected within {$days} day(s): counted as {$label}",
            'adjusted_by' => 'System',
            'adjusted_at' => now(),
            'original_check_in' => $record->check_in,
            'original_check_out' => $record->check_out,
            'updated_at' => now(),
        ]);

        $employee->syncPenaltyLeaves($date, $settings);

        AuditLog::record('attendance', 'Missing punch policy applied', "{$employee->full_name} — {$date} counted as {$label}", 'System');

        if ($employee->user_id) {
            PortalNotification::sendToUsers(
                [$employee->user_id],
                'correction',
                'Missing '.$missing.' on '.Carbon::parse($date)->format('d M').' counted as '.$label,
                'No correction was submitted within '.$days.' day(s) for '.Carbon::parse($date)->format('D d M Y')
                    .", so the day is counted as {$label} under the attendance policy.",
                route($employee->portalPrefix().'.correction.requests'),
                "missing-punch-resolved:{$employee->id}:{$date}"
            );
        }
    }
}
