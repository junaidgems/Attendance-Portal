<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\MonthlyLock;
use App\Models\PortalNotification;
use App\Models\WfhRequest;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Work From Home: request (Employee → Line Manager → Admin) and portal check-in / check-out on approved days.
 */
class WfhController extends Controller
{
    /**
     * Views folder (resources/views/attendence/pages/{viewFolder}). Employees, Line Managers and
     * Managers share the same page; its forms post to the current portal's routes.
     */
    protected string $viewFolder = 'employee';

    /**
     * Route name prefix of the current portal: "employee", "line_manager" or "manager".
     */
    protected function routePrefix(): string
    {
        return explode('.', (string) request()->route()?->getName())[0] ?: 'employee';
    }

    public function index()
    {
        $employee = $this->employee();
        $settings = DB::table('attendance_settings')->first();
        $today = now()->toDateString();

        // Approved request for today, or one of the employee's fixed WFH weekdays
        $todayWfh = $employee->wfhRequests()->approvedOn($today)->first() ?? ($employee->isFixedWfhDay($today) ? 'fixed' : null);
        $todayRecord = $employee->zkteco_uid
            ? DB::table('device_attendance')->where('uid', $employee->zkteco_uid)->where('date', $today)->first()
            : null;

        $myRequests = $employee->wfhRequests()->with('manager')->latest()->get();

        return view('attendence.pages.'.$this->viewFolder.'.wfh', [
            'employee' => $employee,
            'todayWfh' => $todayWfh,
            'todayRecord' => $todayRecord,
            'todayShift' => $employee->shiftTimesOn($today, $settings),
            'myRequests' => $myRequests,
            'reviewingManager' => LeaveRequest::reviewingManagerFor($employee),
            'monthlyLimit' => (int) ($settings->wfh_max_days_per_month ?? 0),
            'usedThisMonth' => $this->daysInMonth($employee, now()),
            'wfhEnabled' => (bool) ($settings->wfh_enabled ?? true),
            'routePrefix' => $this->routePrefix(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'from_date' => 'required|date|after_or_equal:today',
            'to_date' => 'required|date|after_or_equal:from_date',
            'reason' => 'required|string|max:500',
        ]);

        // Payroll-locked month: no changes
        MonthlyLock::ensureOpen($request->from_date, $request->to_date);

        $employee = $this->employee();
        $settings = DB::table('attendance_settings')->first();

        if (! ($settings->wfh_enabled ?? true)) {
            return back()->with('error', 'Work From Home is currently disabled in the Attendance Policy.');
        }

        if (! $employee->zkteco_uid) {
            return back()->with('error', 'Your attendance ID is not set up yet. Please contact Admin.');
        }

        $days = $this->workingDays($employee, $request->from_date, $request->to_date, $settings);
        if ($days === 0) {
            return back()->withInput()->with('error', $employee->wfhDays()
                ? 'The selected dates do not include any working day that is not already a fixed Work From Home day.'
                : 'The selected dates do not include any working day.');
        }

        // One Work From Home request per day: once requested (pending, approved or rejected) it cannot be requested again
        $existingWfh = $employee->wfhRequests()
            ->whereDate('from_date', '<=', $request->to_date)
            ->whereDate('to_date', '>=', $request->from_date)
            ->latest('id')
            ->first();

        if ($existingWfh) {
            return back()->withInput()->with('error', match ($existingWfh->status) {
                'pending' => "You already have a pending Work From Home request for {$existingWfh->dateLabel()}.",
                'approved' => "Work From Home for {$existingWfh->dateLabel()} is already approved.",
                default => "Work From Home for {$existingWfh->dateLabel()} was rejected and cannot be requested again.",
            });
        }

        $leave = LeaveRequest::overlappingFor($employee->id, $request->from_date, $request->to_date);
        if ($leave) {
            return back()->withInput()->with('error', "You already have a {$leave->status} leave ({$leave->summary()}) on these dates. Leave and Work From Home cannot be on the same day.");
        }

        $limit = (int) ($settings->wfh_max_days_per_month ?? 0);
        if ($limit > 0) {
            $month = Carbon::parse($request->from_date);
            $requestedThisMonth = $this->workingDays($employee, $request->from_date, min($request->to_date, $month->copy()->endOfMonth()->toDateString()), $settings);

            if ($this->daysInMonth($employee, $month) + $requestedThisMonth > $limit) {
                return back()->withInput()->with('error', "Monthly Work From Home limit is {$limit} day(s). You have already used or requested {$this->daysInMonth($employee, $month)} in ".$month->format('F').'.');
            }
        }

        $manager = LeaveRequest::reviewingManagerFor($employee);

        $wfh = $employee->wfhRequests()->create([
            'from_date' => $request->from_date,
            'to_date' => $request->to_date,
            'days' => $days,
            'reason' => $request->reason,
            'status' => 'pending',
            'manager_id' => $manager?->id,
            'manager_status' => $manager ? LeaveRequest::MANAGER_PENDING : LeaveRequest::MANAGER_NOT_REQUIRED,
        ]);

        // Inform whoever has to review it first
        PortalNotification::notifyReviewer(
            $employee,
            'wfh',
            "Work From Home request from {$employee->full_name}",
            "{$employee->full_name} requested Work From Home for {$wfh->dateLabel()}. Reason: {$request->reason}",
            'admin.wfh',
            "wfh:request:{$wfh->id}"
        );

        $sentTo = $manager ? $manager->roleLabel().' ('.$manager->full_name.')' : 'Admin';

        return back()->with('success', "Work From Home request submitted and sent to {$sentTo} for approval.");
    }

    public function checkIn(Request $request): RedirectResponse
    {
        $employee = $this->employee();
        $today = now()->toDateString();

        // Payroll-locked month: no changes
        MonthlyLock::ensureOpen($today);

        if (! $employee->isFixedWfhDay($today) && ! $employee->wfhRequests()->approvedOn($today)->exists()) {
            return back()->with('error', 'You do not have an approved Work From Home for today.');
        }

        if (! $employee->zkteco_uid) {
            return back()->with('error', 'Your attendance ID is not set up yet. Please contact Admin.');
        }

        $existing = DB::table('device_attendance')->where('uid', $employee->zkteco_uid)->where('date', $today)->first();
        if (WfhRequest::isOnSite($existing)) {
            return back()->with('error', 'You punched in on the office machine at '.Carbon::parse($existing->check_in)->format('h:i A').', so today counts as on-site, not Work From Home.');
        }
        if ($existing?->check_in) {
            return back()->with('error', 'You already checked in today at '.Carbon::parse($existing->check_in)->format('h:i A').'.');
        }

        $settings = DB::table('attendance_settings')->first();
        $checkIn = now()->format('H:i:s');
        $punctuality = Employee::punctuality($employee, $today, $checkIn, null, $settings);

        DB::table('device_attendance')->updateOrInsert(
            ['uid' => $employee->zkteco_uid, 'date' => $today],
            [
                'name' => $employee->full_name,
                'check_in' => $checkIn,
                'check_out' => null,
                'status' => $punctuality['late_minutes'] > 0 ? 'Late' : 'On Time',
                'late_minutes' => $punctuality['late_minutes'],
                'early_out_minutes' => 0,
                'source' => 'wfh',
                'check_in_ip' => $request->ip(),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        return back()->with('success', 'Checked in at '.now()->format('h:i A').' (Work From Home).');
    }

    public function checkOut(Request $request): RedirectResponse
    {
        $request->validate([
            'work_summary' => 'required|string|min:10|max:1000',
        ]);

        $employee = $this->employee();
        $today = now()->toDateString();

        // Payroll-locked month: no changes
        MonthlyLock::ensureOpen($today);

        $record = DB::table('device_attendance')
            ->where('uid', $employee->zkteco_uid)
            ->where('date', $today)
            ->first();

        if (WfhRequest::isOnSite($record)) {
            return back()->with('error', 'Your attendance today comes from the office machine (on-site), so there is no Work From Home check-out. Punch out on the machine.');
        }

        if (! $record?->check_in) {
            return back()->with('error', 'Please check in first.');
        }

        if ($record->check_out) {
            return back()->with('error', 'You have already checked out today.');
        }

        $settings = DB::table('attendance_settings')->first();
        $checkOut = now()->format('H:i:s');
        $punctuality = Employee::punctuality($employee, $today, $record->check_in, $checkOut, $settings);

        DB::table('device_attendance')->where('id', $record->id)->update([
            'check_out' => $checkOut,
            'early_out_minutes' => $punctuality['early_out_minutes'],
            'work_summary' => $request->work_summary,
            'check_out_ip' => $request->ip(),
            'updated_at' => now(),
        ]);

        return back()->with('success', 'Checked out at '.now()->format('h:i A').'. Have a good evening!');
    }

    protected function employee(): Employee
    {
        $employee = Auth::user()->employee;

        abort_unless($employee, 403, 'Employee profile not found.');

        return $employee->load(['shift', 'gracePolicy']);
    }

    /**
     * Days a WFH request covers for the employee: their working days (own off days excluded) minus holidays
     * and minus their fixed WFH weekdays, which need no request and do not count toward the monthly limit.
     */
    private function workingDays(Employee $employee, string $from, string $to, ?object $settings): int
    {
        $holidays = DB::table('holidays')->get();
        $count = 0;

        for ($d = Carbon::parse($from); $d->lte(Carbon::parse($to)); $d->addDay()) {
            $day = $d->toDateString();
            $isHoliday = $holidays->contains(fn ($h) => $day >= $h->from_date && $day <= ($h->to_date ?? $h->from_date));

            if ($employee->worksOn($day, $settings) && ! $isHoliday && ! $employee->isFixedWfhDay($day)) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * WFH working days already approved or pending in the month of the given date.
     */
    private function daysInMonth(Employee $employee, Carbon $month): int
    {
        $settings = DB::table('attendance_settings')->first();
        $start = $month->copy()->startOfMonth()->toDateString();
        $end = $month->copy()->endOfMonth()->toDateString();

        return $employee->wfhRequests()
            ->whereIn('status', ['pending', 'approved'])
            ->whereDate('from_date', '<=', $end)
            ->whereDate('to_date', '>=', $start)
            ->get()
            ->sum(fn ($w) => $this->workingDays($employee, max(Carbon::parse($w->from_date)->toDateString(), $start), min(Carbon::parse($w->to_date)->toDateString(), $end), $settings));
    }
}
