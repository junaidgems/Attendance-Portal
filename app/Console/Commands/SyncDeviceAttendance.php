<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\DeductionWaiver;
use App\Models\Employee;
use App\Models\MonthlyLock;
use App\Models\PortalNotification;
use Carbon\Carbon;
use DateTimeImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use ZkTeco\Laravel\Facades\ZkTeco;

class SyncDeviceAttendance extends Command
{
    protected $signature = 'device:sync-attendance {--check-clock : Also check and correct the device clock (never done automatically)}';

    protected $description = 'Pull attendance from the biometric device into device_attendance (late / early-out per shift and grace policy)';

    /**
     * A last punch closer than this to the first punch of the day is a repeated scan at arrival, not a check-out.
     * (On the device, repeated scans are under 2 minutes apart; real check-outs are hours later.)
     */
    public const MIN_MINUTES_BETWEEN_IN_AND_OUT = 10;

    /**
     * Check-in and check-out per person per day from the raw device punches: the first punch of the day is the
     * check-in and the last punch is the check-out. The device's In/Out state is ignored, because staff often
     * punch without pressing the Out key and the device then stores the evening punch as "CheckIn" too.
     *
     * @param  iterable<object>  $records  Device punches (userId, recordedAt)
     *                                     (A single punch late in the shift is moved to the check-out when saving, see Employee::singlePunchIsCheckOut.)
     * @return array<string, array{uid: string, date: string, check_in: string, check_out: ?string}> keyed "uid_date"
     */
    public static function dailyPunches(iterable $records): array
    {
        $days = [];

        foreach ($records as $record) {
            $key = $record->userId.'_'.$record->recordedAt->format('Y-m-d');
            $time = $record->recordedAt->format('H:i:s');

            $days[$key] ??= ['uid' => (string) $record->userId, 'date' => $record->recordedAt->format('Y-m-d'), 'first' => $time, 'last' => $time];
            $days[$key]['first'] = min($days[$key]['first'], $time);
            $days[$key]['last'] = max($days[$key]['last'], $time);
        }

        return array_map(function (array $day) {
            $minutes = (strtotime($day['last']) - strtotime($day['first'])) / 60;

            return [
                'uid' => $day['uid'],
                'date' => $day['date'],
                'check_in' => $day['first'],
                'check_out' => $minutes >= self::MIN_MINUTES_BETWEEN_IN_AND_OUT ? $day['last'] : null,
            ];
        }, $days);
    }

    /**
     * Local time of the office / device (the device clock has no time zone).
     */
    public const DEVICE_TIMEZONE = 'Asia/Karachi';

    /**
     * A device clock off by more than this is set to the correct time (e.g. AM/PM set the wrong way round).
     */
    public const MAX_CLOCK_DRIFT_MINUTES = 5;

    protected function readDeviceClock(): DateTimeImmutable
    {
        // Tried up to 3 times: the device sometimes answers the first connection of the day badly
        return retry(3, fn () => ZkTeco::connection()->session(fn ($device) => $device->info()->time()), 3000);
    }

    /**
     * The clock is never touched by the scheduled or manual sync: reading / setting it makes the device show
     * "Authentication failure" on its screen. Admin checks and sets it from the Biometric Sync page when needed
     * (or runs the command with --check-clock).
     */
    protected function shouldCheckClock(): bool
    {
        return (bool) $this->option('check-clock');
    }

    protected function writeDeviceClock(DateTimeImmutable $time): void
    {
        ZkTeco::connection()->session(fn ($device) => $device->info()->setTime($time));
    }

    /**
     * Compare the device clock with Pakistan time and set it right when it is more than a few minutes off,
     * so punches are not saved at the wrong time (e.g. 9:00 PM instead of 9:00 AM).
     *
     * @return int|null Minutes the device was off (positive = ahead), or null when the clock was fine
     */
    protected function correctDeviceClock(): ?int
    {
        // Both as wall-clock times: the device stores no time zone
        $deviceTime = Carbon::parse($this->readDeviceClock()->format('Y-m-d H:i:s'));
        $officeTime = Carbon::parse(Carbon::now(self::DEVICE_TIMEZONE)->format('Y-m-d H:i:s'));

        $offsetMinutes = (int) round(($deviceTime->getTimestamp() - $officeTime->getTimestamp()) / 60);

        if (abs($offsetMinutes) <= self::MAX_CLOCK_DRIFT_MINUTES) {
            return null;
        }

        $this->writeDeviceClock(new DateTimeImmutable($officeTime->format('Y-m-d H:i:s')));

        return $offsetMinutes;
    }

    /**
     * Users and attendance punches from the device. The device sometimes times out, so each read is tried up to 3 times.
     *
     * @return array{0: iterable<object>, 1: iterable<object>}
     */
    protected function readDevice(): array
    {
        $users = retry(3, fn () => ZkTeco::connection()->session(
            fn ($device) => $device->users()->all()
        ), 3000);

        $attendance = retry(3, fn () => ZkTeco::connection()->session(
            fn ($device) => $device->attendance()->all()
        ), 3000);

        return [$users, $attendance];
    }

    public function handle(): int
    {
        $this->info('Starting sync...');

        // Step 0 (only with --check-clock): correct the device clock. A failure here never stops the attendance sync.
        try {
            $offsetMinutes = $this->shouldCheckClock() ? $this->correctDeviceClock() : null;

            if ($offsetMinutes !== null) {
                $offsetLabel = intdiv(abs($offsetMinutes), 60).'h '.(abs($offsetMinutes) % 60).'m '.($offsetMinutes > 0 ? 'ahead' : 'behind');
                $this->warn("Device clock was {$offsetLabel}; it was set to Pakistan time.");

                AuditLog::record('sync', 'Device clock corrected', "The attendance device clock was {$offsetLabel} and was set to Pakistan time. Punches made before this may have the wrong time.", 'System');
                PortalNotification::notifyAdmins('sync', 'Attendance device clock corrected',
                    "The attendance device clock was {$offsetLabel} (e.g. AM/PM set the wrong way). It has been set to the correct time. Check punches made before ".now(self::DEVICE_TIMEZONE)->format('d M h:i A').' for wrong times.',
                    'admin.biometric.sync', 'device-clock:'.now()->format('Y-m-d-H-i'));
            }
        } catch (\Throwable $e) {
            $this->warn('Device clock check failed: '.$e->getMessage());
        }

        try {
            // Step 1 + 2: device users (for names) and attendance punches
            [$users, $attendance] = $this->readDevice();

            $userNames = [];
            foreach ($users as $user) {
                $userNames[$user->userId] = $user->name ?? ('User '.$user->userId);
            }

            // First punch of the day = check-in, last punch = check-out (the device's In/Out state is not reliable)
            $grouped = [];
            foreach (self::dailyPunches($attendance) as $key => $day) {
                // Older device history is not saved (see Employee::ATTENDANCE_START_DATE)
                if ($day['date'] < Employee::ATTENDANCE_START_DATE) {
                    continue;
                }

                $grouped[$key] = $day + ['name' => $userNames[$day['uid']] ?? ('User '.$day['uid'])];
            }

            // Step 3: Save to device_attendance. Late / early-out come from each employee's shift + grace policy.
            $settings = DB::table('attendance_settings')->first();
            $employeesByUid = Employee::with(['shift', 'gracePolicy'])
                ->whereNotNull('zkteco_uid')
                ->get()
                ->keyBy(fn ($e) => (string) $e->zkteco_uid);

            // Days changed by an approved correction / official duty / Admin / missing-punch policy are locked
            $adjustedDays = DB::table('device_attendance')
                ->whereNotNull('adjustment')
                ->get(['uid', 'date', 'adjustment'])
                ->mapWithKeys(fn ($r) => [$r->uid.'_'.Carbon::parse($r->date)->toDateString() => $r->adjustment]);

            $savedCount = 0;
            $healedCount = 0;
            foreach ($grouped as $entry) {
                $adjustment = $adjustedDays->get($entry['uid'].'_'.$entry['date']);
                $employee = $employeesByUid->get((string) $entry['uid']);

                // One punch in the second half of the shift is the check-out: the check-in was missed
                if (! $entry['check_out'] && Employee::singlePunchIsCheckOut($employee, $entry['date'], $entry['check_in'], $settings)) {
                    $entry['check_out'] = $entry['check_in'];
                    $entry['check_in'] = null;
                }

                // The missing-punch policy only filled in the punch the device did not seem to have. When the
                // device does have both (e.g. stored under the In key), the real punches replace that half day / absent.
                $healsMissingPunch = $adjustment && str_starts_with($adjustment, 'missing_punch_') && $entry['check_in'] && $entry['check_out'];

                // Also skip payroll-locked months: their attendance is frozen
                if (($adjustment && ! $healsMissingPunch) || MonthlyLock::isLocked($entry['date'])) {
                    continue;
                }

                $punctuality = Employee::punctuality(
                    $employee,
                    $entry['date'],
                    $entry['check_in'],
                    $entry['check_out'],
                    $settings
                );

                DB::table('device_attendance')->updateOrInsert(
                    ['uid' => $entry['uid'], 'date' => $entry['date']],
                    [
                        'name' => $entry['name'],
                        'check_in' => $entry['check_in'],
                        'check_out' => $entry['check_out'],
                        'status' => $punctuality['late_minutes'] > 0 ? 'Late' : 'On Time',
                        'late_minutes' => $punctuality['late_minutes'],
                        'early_out_minutes' => $punctuality['early_out_minutes'],
                        // Office machine punch = on-site: it replaces a portal (WFH) check-in for the same day,
                        // so the Work From Home details of that day are cleared too
                        'source' => 'device',
                        'work_summary' => null,
                        'check_in_ip' => null,
                        'check_out_ip' => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ] + ($healsMissingPunch ? [
                        'adjustment' => null,
                        'adjustment_note' => null,
                        'adjusted_by' => null,
                        'adjusted_at' => null,
                        'original_check_in' => null,
                        'original_check_out' => null,
                    ] : [])
                );

                $savedCount++;
                $healedCount += $healsMissingPunch ? 1 : 0;
            }

            $this->info("$savedCount records saved/updated");

            if ($healedCount > 0) {
                $this->info("$healedCount missing-punch days restored from the device's real punches");
                AuditLog::record('attendance', 'Missing punch restored from device', "{$healedCount} day(s) had both punches on the device; the half day / absent of the missing-punch policy was removed", 'System');
            }

            // Half-day leave applied but the employee worked a full day → auto-reject
            $this->call('leave:auto-reject-half-days');

            // Checked in but never checked out → notify, then apply the policy after the deadline
            $this->call('attendance:missing-punches');

            // Late / early-out threshold alerts for Admin and Line Manager
            $this->call('attendance:send-alerts');

            // Automatic penalty Casual Leave (e.g. every 3 late arrivals = 1 leave)
            $this->call('attendance:apply-penalties');

            // Days first synced after a waiver was approved: mark the waived items on them too
            DeductionWaiver::where('status', 'approved')
                ->whereDate('date', '>=', now()->subDays(62)->toDateString())
                ->get(['employee_id', 'date'])
                ->unique(fn ($w) => $w->employee_id.'_'.$w->date)
                ->each(fn ($w) => DeductionWaiver::syncAttendanceFor($w->employee_id, $w->date));

        } catch (\Throwable $e) {
            // Report the failure (exit code 1) so the Biometric Sync page shows an error instead of "complete"
            $this->error('Sync failed: '.$e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
