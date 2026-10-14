<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\LeaveRequest;
use App\Models\MonthlyLock;
use App\Models\PortalNotification;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AutoRejectWorkedHalfDayLeaves extends Command
{
    protected $signature = 'leave:auto-reject-half-days';

    protected $description = 'Reject pending half-day leave requests when the machine punches show a full working day on that date';

    public function handle(): int
    {
        $settings = DB::table('attendance_settings')->first();

        // Only pending requests: approved ones are left for Admin to reject manually.
        $requests = LeaveRequest::with(['employee.shift', 'employee.gracePolicy'])
            ->where('status', 'pending')
            ->where('is_half_day', true)
            ->whereDate('from_date', '<=', now()->toDateString())
            ->get();

        $rejected = 0;

        foreach ($requests as $leaveRequest) {
            if (MonthlyLock::isLocked($leaveRequest->from_date)) {
                continue;
            }

            $workedMinutes = $leaveRequest->fullDayWorkedMinutes($settings);

            if ($workedMinutes === null) {
                continue;
            }

            $date = Carbon::parse($leaveRequest->from_date)->format('d M Y');
            $worked = intdiv($workedMinutes, 60).'h '.($workedMinutes % 60).'m';
            $note = "Auto-rejected: attendance shows a full working day on {$date} ({$worked} worked).";

            $leaveRequest->update([
                'status' => 'rejected',
                'admin_note' => $note,
                'reviewed_by' => 'System',
                'reviewed_at' => now(),
            ]);

            AuditLog::record('leave', 'Half-day leave auto-rejected', ($leaveRequest->employee?->full_name ?? 'Employee').' — '.$note, 'System');

            // Only the employee is told: Line Managers / Managers are only notified of requests they have to review
            PortalNotification::notifyEmployee($leaveRequest->employee, 'leave', 'Half-day leave auto-rejected',
                "Your {$leaveRequest->summary()} was rejected automatically: attendance shows a full working day on {$date} ({$worked} worked).",
                'leave', "leave:decision:{$leaveRequest->id}");

            $rejected++;
        }

        $this->info("{$rejected} half-day leave request(s) auto-rejected");

        return self::SUCCESS;
    }
}
