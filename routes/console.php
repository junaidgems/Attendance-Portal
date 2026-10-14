<?php

use App\Models\AuditLog;
use Carbon\Carbon;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::call(function () {
    $settings = DB::table('attendance_settings')->first();

    if ($settings && $settings->biometric_auto_sync_enabled) {
        $now = now()->format('H:i');
        $syncTime = Carbon::parse($settings->biometric_sync_time)->format('H:i');

        if ($now === $syncTime) {
            Artisan::call('device:sync-attendance');
            AuditLog::record('sync', 'Scheduled sync', trim(Artisan::output()), 'System');
        }
    }
})->everyMinute();

Schedule::command('device:sync-attendance')
    ->everyFiveMinutes();

// Admin is reminded a week before an employee's probation ends
Schedule::command('employees:probation-ending')
    ->dailyAt('09:00');

// Sends the queued notification emails (they wait in the "jobs" table until a worker runs)
Schedule::command('queue:work --stop-when-empty --tries=3')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground();
