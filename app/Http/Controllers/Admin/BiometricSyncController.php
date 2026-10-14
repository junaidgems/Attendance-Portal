<?php

namespace App\Http\Controllers\Admin;

use App\Console\Commands\SyncDeviceAttendance;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Employee;
use Carbon\Carbon;
use DateTimeImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use ZkTeco\Laravel\Facades\ZkTeco;

class BiometricSyncController extends Controller
{
    public function index()
    {
        $today = now()->format('Y-m-d');

        // ===== Enrolled employees (jo device se link hain) =====
        $enrolledEmployees = Employee::with('department')
            ->whereNotNull('zkteco_uid')
            ->where('employment_status', 'active')
            ->orderBy('first_name')
            ->get();

        $enrolledCount = $enrolledEmployees->count();

        // ===== Aaj ka device data =====
        $todayRecords = DB::table('device_attendance')
            ->where('date', $today)
            ->get()
            ->keyBy(fn ($r) => (string) $r->uid);

        $presentCount = 0;
        $mappedCount = 0;
        $awaitingCount = 0;

        $employeeRows = [];

        foreach ($enrolledEmployees as $emp) {
            $r = $todayRecords->get((string) $emp->zkteco_uid);

            if ($r && $r->check_in) {
                $mappedCount++;
                $presentCount++;
                $deviceRecord = Carbon::parse($r->check_in)->format('H:i').' - '.($r->check_out ? Carbon::parse($r->check_out)->format('H:i') : '—');
                $status = 'Mapped';
            } else {
                $awaitingCount++;
                $deviceRecord = '—';
                $status = 'Awaiting';
            }

            $employeeRows[] = [
                'name' => $emp->first_name.' '.$emp->last_name,
                'title' => $emp->job_title,
                'emp_id' => $emp->employee_id,
                'device_record' => $deviceRecord,
                'status' => $status,
            ];
        }

        // ===== Last manual/scheduled sync (audit log se) =====
        $lastSyncLog = AuditLog::where('category', 'sync')->latest('created_at')->first();
        $lastManualPull = $lastSyncLog ? Carbon::parse($lastSyncLog->created_at)->format('d M H:i') : 'Never';

        // ===== Sync Activity Log (last 7 din) =====
        $syncLogs = AuditLog::where('category', 'sync')
            ->where('created_at', '>=', now()->subDays(7))
            ->orderBy('created_at', 'desc')
            ->get();

        $settings = DB::table('attendance_settings')->first();

        return view('attendence.pages.admin.biometric-sync', [
            'enrolledCount' => $enrolledCount,
            'presentCount' => $presentCount,
            'mappedCount' => $mappedCount,
            'awaitingCount' => $awaitingCount,
            'employeeRows' => $employeeRows,
            'lastManualPull' => $lastManualPull,
            'syncLogs' => $syncLogs,
            'scheduledSyncTime' => $settings ? Carbon::parse($settings->biometric_sync_time)->format('H:i') : '—',
        ]);
    }

    public function runSync(Request $request)
    {
        try {
            $exitCode = Artisan::call('device:sync-attendance');
            $output = Artisan::output();

            if ($exitCode !== 0) {
                AuditLog::record('sync', 'Manual sync failed', trim($output));

                // e.g. "Sync failed: Timed out waiting for a response from the device."
                $reason = collect(preg_split('/\R/', trim($output)))->last(fn ($line) => str_starts_with($line, 'Sync failed')) ?? 'Sync failed.';

                return response()->json([
                    'success' => false,
                    'message' => $reason.' Check that the device is on and connected to the network, then try again.',
                    'output' => $output,
                ], 500);
            }

            AuditLog::record('sync', 'Manual sync', trim($output));

            return response()->json([
                'success' => true,
                'message' => 'Sync complete!',
                'output' => $output,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Set the attendance device clock to Pakistan time now (the sync also does this on its own when the
     * clock is more than a few minutes off).
     */
    public function setDeviceClock(): RedirectResponse
    {
        try {
            $before = Carbon::parse($this->readDeviceClock()->format('Y-m-d H:i:s'));
            $now = Carbon::parse(Carbon::now(SyncDeviceAttendance::DEVICE_TIMEZONE)->format('Y-m-d H:i:s'));
            $this->writeDeviceClock(new DateTimeImmutable($now->format('Y-m-d H:i:s')));
        } catch (\Throwable $e) {
            report($e);

            return redirect()->back()->with('error', 'The device did not answer, so its clock was not changed: '.$e->getMessage());
        }

        $minutes = (int) round(($before->getTimestamp() - $now->getTimestamp()) / 60);
        $was = $minutes === 0 ? 'already correct' : intdiv(abs($minutes), 60).'h '.(abs($minutes) % 60).'m '.($minutes > 0 ? 'ahead' : 'behind');

        AuditLog::record('sync', 'Device clock set by Admin', 'Device showed '.$before->format('d M Y h:i:s A')." ({$was}); set to ".$now->format('d M Y h:i:s A').' (Pakistan time)');

        return redirect()->back()->with('success', 'Device clock set to '.$now->format('h:i A')." (it was {$was}).");
    }

    /**
     * Device time, Pakistan time and the difference, for the Biometric Sync page (loaded after the page, so a
     * device that does not answer never slows the page down).
     */
    public function deviceClockStatus(): JsonResponse
    {
        try {
            $device = Carbon::parse($this->readDeviceClock()->format('Y-m-d H:i:s'));
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => 'The device is not answering.']);
        }

        $office = Carbon::parse(Carbon::now(SyncDeviceAttendance::DEVICE_TIMEZONE)->format('Y-m-d H:i:s'));
        $minutes = (int) round(($device->getTimestamp() - $office->getTimestamp()) / 60);

        return response()->json([
            'success' => true,
            'device' => $device->format('d M Y, h:i:s A'),
            'office' => $office->format('d M Y, h:i:s A'),
            'difference' => $minutes === 0 ? 'Correct' : intdiv(abs($minutes), 60).'h '.(abs($minutes) % 60).'m '.($minutes > 0 ? 'ahead' : 'behind'),
            'ok' => abs($minutes) <= SyncDeviceAttendance::MAX_CLOCK_DRIFT_MINUTES,
        ]);
    }

    protected function readDeviceClock(): DateTimeImmutable
    {
        return ZkTeco::connection()->session(fn ($device) => $device->info()->time());
    }

    protected function writeDeviceClock(DateTimeImmutable $time): void
    {
        ZkTeco::connection()->session(fn ($device) => $device->info()->setTime($time));
    }
}
