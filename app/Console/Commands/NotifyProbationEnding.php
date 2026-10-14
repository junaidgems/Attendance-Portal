<?php

namespace App\Console\Commands;

use App\Models\Employee;
use App\Models\PortalNotification;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Reminds Admin a week before an employee's probation ends, so it can be confirmed or extended in time.
 */
class NotifyProbationEnding extends Command
{
    protected $signature = 'employees:probation-ending';

    protected $description = 'Notify Admin about employees whose probation ends in 7 days';

    public const DAYS_BEFORE = 7;

    public function handle(): int
    {
        if (! Employee::hasProbationColumn()) {
            $this->info('Probation is not set up yet (column missing).');

            return self::SUCCESS;
        }

        $endsOn = now()->addDays(self::DAYS_BEFORE)->toDateString();

        $employees = Employee::with('department')
            ->where('employment_status', 'active')
            ->whereDate('probation_until', $endsOn)
            ->orderBy('first_name')
            ->get();

        foreach ($employees as $employee) {
            PortalNotification::notifyAdmins('employee', "Probation ends soon: {$employee->full_name}",
                "{$employee->full_name}'s probation ends on ".Carbon::parse($employee->probation_until)->format('d M Y')
                    .'. Confirm the employee, or extend the probation date on the Employees page.',
                'admin.employee', "probation:ending:{$employee->id}:{$endsOn}");
        }

        $this->info("{$employees->count()} probation reminder(s) sent");

        return self::SUCCESS;
    }
}
