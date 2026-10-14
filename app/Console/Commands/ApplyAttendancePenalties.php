<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\Employee;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ApplyAttendancePenalties extends Command
{
    protected $signature = 'attendance:apply-penalties';

    protected $description = 'Deduct (or reverse) automatic penalty Casual Leaves for late arrivals, early outs and half days in the current cycle';

    public function handle(): int
    {
        $settings = DB::table('attendance_settings')->first();

        if (! $settings?->penalty_policy_enabled) {
            $this->info('Penalty policy is disabled in the Attendance Policy.');

            return self::SUCCESS;
        }

        $totals = ['created' => 0, 'reversed' => 0, 'shortfall' => 0];

        $employees = Employee::with(['shift', 'gracePolicy'])
            ->where('employment_status', 'active')
            ->whereNotNull('zkteco_uid')
            ->get();

        foreach ($employees as $employee) {
            $result = $employee->syncPenaltyLeaves(now(), $settings);

            foreach ($result as $key => $value) {
                $totals[$key] += $value;
            }

            if ($result['created'] || $result['reversed']) {
                AuditLog::record(
                    'penalty',
                    'Penalty leave updated',
                    "{$employee->full_name}: {$result['created']} deducted, {$result['reversed']} reversed",
                    'System'
                );
            }
        }

        $this->info("Penalty leaves: {$totals['created']} deducted, {$totals['reversed']} reversed, {$totals['shortfall']} salary-day shortfall");

        return self::SUCCESS;
    }
}
