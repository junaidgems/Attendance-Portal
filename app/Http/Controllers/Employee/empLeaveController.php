<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\MonthlyLock;
use App\Models\PortalNotification;
use App\Models\WfhRequest;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class empLeaveController extends Controller
{
    /**
     * Views folder (resources/views/attendence/pages/{viewFolder}); overridden by the Line Manager controller.
     */
    protected string $viewFolder = 'employee';

    /**
     * Leave type that can be requested only once a month (one pending or approved request per month).
     */
    private const ONCE_A_MONTH_LEAVE_TYPE = 'Casual Leave';

    public function index()
    {
        $employee = Auth::user()->employee;
        $leaveTypes = LeaveType::orderBy('name')->get();

        $balances = [];
        foreach ($leaveTypes as $type) {
            // Balance of the current leave year (the full allowance comes back every leave year)
            $used = $employee->leaveRequests()
                ->where('leave_type_id', $type->id)
                ->where('status', 'approved')
                ->inLeaveYear()
                ->sum('days');

            // No paid leave while on probation
            $total = $employee->leaveAllowance($type);

            $balances[] = [
                'id' => $type->id,
                'name' => $type->name,
                'color' => $type->color,
                'total' => $total,
                'used' => $used,
                'remaining' => max(0, $total - $used),
                'rules' => $type->rulesSummary(),
                'requires_document' => $type->requires_document,
                'document_from' => $type->document_required_from_days ?: 0,
                'min_days' => $type->min_days_per_request,
            ];
        }

        $myRequests = $employee->leaveRequests()
            ->with(['leaveType', 'manager'])
            ->orderBy('created_at', 'desc')
            ->get();

        $reviewingManager = LeaveRequest::reviewingManagerFor($employee);

        // The apply form counts days with the same working days and holidays as the server (the employee's own
        // off days are not working days)
        $settings = DB::table('attendance_settings')->first();
        $workingDays = array_values(array_filter(Employee::WEEKDAYS, fn ($day) => $employee->worksOn(Carbon::now()->next($day), $settings)));
        $holidayDates = DB::table('holidays')->get()
            ->flatMap(fn ($h) => collect(Carbon::parse($h->from_date)->daysUntil(Carbon::parse($h->to_date ?? $h->from_date)))->map->toDateString())
            ->values();

        $probationUntil = $employee->isOnProbation() ? Carbon::parse($employee->probation_until) : null;

        return view('attendence.pages.'.$this->viewFolder.'.leave', compact('balances', 'leaveTypes', 'myRequests', 'reviewingManager', 'workingDays', 'holidayDates', 'probationUntil'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'leave_type_id' => 'required|exists:leave_types,id',
            // Past dates are allowed, e.g. to enter earlier leave of the leave year (from July) so it is deducted;
            // payroll-locked months stay blocked below
            'from_date' => 'required|date',
            'to_date' => 'required|date|after_or_equal:from_date',
            'reason' => 'required|string|max:500',
            'half_day' => 'nullable|boolean',
            'half_day_session' => 'nullable|required_if:half_day,1|in:first_half,second_half',
            'document' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
        ], [
            'document.mimes' => 'The document must be a JPG, PNG or PDF file.',
            'document.max' => 'The document may not be larger than 5 MB.',
        ]);

        // Payroll-locked month: no changes
        MonthlyLock::ensureOpen($request->from_date, $request->to_date);

        $isHalfDay = $request->boolean('half_day');

        if ($isHalfDay && $request->from_date !== $request->to_date) {
            return redirect()->back()->withInput()->with('error', 'Half day leave can only be applied for a single day (From and To must be the same date).');
        }

        $employee = Auth::user()->employee;

        if (! $employee) {
            abort(403, 'Employee profile not found.');
        }

        if ($employee->isOnProbation()) {
            return redirect()->back()->withInput()->with('error', 'You are on probation until '.Carbon::parse($employee->probation_until)->format('d M Y').'. Leave can be applied after that.');
        }

        $settings = DB::table('attendance_settings')->first();
        $holidays = DB::table('holidays')->get();

        // ===== Count the employee's working days (excluding weekends, their own off days and holidays) =====
        $from = Carbon::parse($request->from_date);
        $to = Carbon::parse($request->to_date);
        $days = 0;
        $daysByMonth = [];
        $cursor = $from->copy();

        while ($cursor->lte($to)) {
            $dateStr = $cursor->format('Y-m-d');
            $isWorkingDay = $employee->worksOn($dateStr, $settings);
            $isHoliday = $holidays->contains(fn ($h) => $dateStr >= $h->from_date && $dateStr <= ($h->to_date ?? $h->from_date));

            if ($isWorkingDay && ! $isHoliday) {
                $days++;
                $daysByMonth[$cursor->format('Y-m')] = ($daysByMonth[$cursor->format('Y-m')] ?? 0) + 1;
            }
            $cursor->addDay();
        }

        if ($days <= 0) {
            return redirect()->back()->withInput()->with('error', 'The selected dates do not include any working day.');
        }

        // Half day only on a working day: check the working day first, then count 0.5
        if ($isHalfDay) {
            $days = 0.5;
            $daysByMonth = [$from->format('Y-m') => 0.5];
        }

        // ===== One leave per day: dates with a pending or approved leave cannot be requested again
        // (after a rejection the employee may apply again, e.g. for fewer days) =====
        $existing = $employee->leaveRequests()
            ->where('is_penalty', false)
            ->whereIn('status', ['pending', 'approved'])
            ->whereDate('from_date', '<=', $request->to_date)
            ->whereDate('to_date', '>=', $request->from_date)
            ->latest('id')
            ->first();

        if ($existing) {
            $when = $existing->summary();

            return redirect()->back()->withInput()->with('error', $existing->status === 'pending'
                ? "You already have a pending leave request for these dates ({$when})."
                : "You already have an approved leave for these dates ({$when}).");
        }

        // ===== No leave on a Work From Home day (pending or approved) =====
        $wfh = WfhRequest::overlappingFor($employee->id, $request->from_date, $request->to_date);
        if ($wfh) {
            return redirect()->back()->withInput()->with('error', "Work From Home for {$wfh->dateLabel()} is already {$wfh->status}. Leave and Work From Home cannot be on the same day.");
        }

        // ===== Balance check (approved + pending both count), in the leave year of the requested dates =====
        $leaveType = LeaveType::findOrFail($request->leave_type_id);
        $used = (float) $employee->leaveRequests()
            ->where('leave_type_id', $leaveType->id)
            ->whereIn('status', ['approved', 'pending'])
            ->inLeaveYear($request->from_date)
            ->sum('days');
        $remaining = $employee->leaveAllowance($leaveType) - $used;

        if ($days > $remaining) {
            return redirect()->back()->withInput()->with('error', "Insufficient balance! Only {$remaining} day(s) left in {$leaveType->name}.");
        }

        // ===== Leave policy rules of this leave type (set by Admin on Leave Management) =====
        if ($leaveType->min_days_per_request && $days < $leaveType->min_days_per_request) {
            return redirect()->back()->withInput()->with('error', "{$leaveType->name} must be at least ".(float) $leaveType->min_days_per_request." working day(s) per request. You selected {$days}.");
        }

        if ($leaveType->max_days_per_month) {
            $maxDays = LeaveType::formatDays($leaveType->max_days_per_month);

            foreach ($daysByMonth as $yearMonth => $requestedDays) {
                $month = Carbon::createFromFormat('Y-m-d', $yearMonth.'-01');
                $takenDays = (float) $employee->leaveRequests()
                    ->where('leave_type_id', $leaveType->id)
                    ->where('is_penalty', false)
                    ->whereIn('status', ['pending', 'approved'])
                    ->whereBetween('from_date', [$month->copy()->startOfMonth()->toDateString(), $month->copy()->endOfMonth()->toDateString()])
                    ->sum('days');

                if ($takenDays + $requestedDays > $leaveType->max_days_per_month) {
                    $left = max(0, $leaveType->max_days_per_month - $takenDays);

                    return redirect()->back()->withInput()->with('error', "You can only take {$maxDays} day(s) of {$leaveType->name} in ".$month->format('F Y')
                        .". You requested {$requestedDays}".($takenDays > 0 ? ' and already have '.LeaveType::formatDays($takenDays).' that month' : '')
                        .', so you can request '.LeaveType::formatDays($left).' more.');
                }
            }
        }

        // ===== Casual Leave: only one request per month (pending or approved; penalty leaves do not count) =====
        if (strcasecmp(trim($leaveType->name), self::ONCE_A_MONTH_LEAVE_TYPE) === 0) {
            foreach (array_keys($daysByMonth) as $yearMonth) {
                $month = Carbon::createFromFormat('Y-m-d', $yearMonth.'-01');

                $alreadyThisMonth = $employee->leaveRequests()
                    ->where('leave_type_id', $leaveType->id)
                    ->where('is_penalty', false)
                    ->whereIn('status', ['pending', 'approved'])
                    ->whereDate('from_date', '<=', $month->copy()->endOfMonth()->toDateString())
                    ->whereDate('to_date', '>=', $month->copy()->startOfMonth()->toDateString())
                    ->exists();

                if ($alreadyThisMonth) {
                    return redirect()->back()->withInput()->with('error', "{$leaveType->name} can be requested only once a month. You already have a {$leaveType->name} request in ".$month->format('F Y').'.');
                }
            }
        }

        if ($leaveType->needsDocument($days) && ! $request->hasFile('document')) {
            $from = $leaveType->document_required_from_days > 1 ? ' of '.LeaveType::formatDays($leaveType->document_required_from_days).' or more days' : '';

            return redirect()->back()->withInput()->with('error', "{$leaveType->name}{$from} needs a supporting document (e.g. a doctor's slip). Please attach it as a JPG, PNG or PDF.");
        }

        // ===== Approval route: Employee → Line Manager → Admin, otherwise straight to Admin =====
        $manager = LeaveRequest::reviewingManagerFor($employee);

        $leave = LeaveRequest::create([
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'from_date' => $request->from_date,
            'to_date' => $request->to_date,
            'days' => $days,
            'is_half_day' => $isHalfDay,
            'half_day_session' => $isHalfDay ? $request->half_day_session : null,
            'status' => 'pending',
            'reason' => $request->reason,
            'manager_id' => $manager?->id,
            'manager_status' => $manager ? LeaveRequest::MANAGER_PENDING : LeaveRequest::MANAGER_NOT_REQUIRED,
            // Private disk: only the employee, their reviewer and Admin can open it (LeaveDocumentController)
            'document_path' => $request->hasFile('document')
                ? $request->file('document')->store('leave-documents/'.$employee->id, 'local')
                : null,
        ]);

        PortalNotification::notifyReviewer(
            $employee,
            'leave',
            "Leave request from {$employee->full_name}",
            "{$employee->full_name} requested {$leave->summary()}. Reason: {$request->reason}",
            'admin.leaves',
            "leave:request:{$leave->id}"
        );

        $sentTo = $manager ? $manager->roleLabel().' ('.$manager->full_name.')' : 'Admin';

        return redirect()->back()->with('success', "Leave request submitted and sent to {$sentTo} for approval.");
    }

    // Leave report
    public function report(Request $request)
    {
        $employee = Auth::user()->employee;

        $year = $request->input('year', now()->year);
        $from = $request->input('from', $year.'-01-01');
        $to = $request->input('to', now()->format('Y-m-d'));
        $leaveTypeId = $request->input('leave_type', 'all');
        $status = $request->input('status', 'all');

        $leaveTypes = LeaveType::orderBy('name')->get();

        // ===== Summary cards =====
        $baseQuery = $employee->leaveRequests()->whereBetween('from_date', [$from, $to]);

        $summary = [
            'approved_days' => (clone $baseQuery)->where('status', 'approved')->sum('days'),
            'pending_requests' => (clone $baseQuery)->where('status', 'pending')->count(),
            'rejected_requests' => (clone $baseQuery)->where('status', 'rejected')->count(),
            'on_leave_today' => $employee->leaveRequests()
                ->approvedAbsence()
                ->whereDate('from_date', '<=', now())
                ->whereDate('to_date', '>=', now())
                ->exists() ? 1 : 0,
        ];

        // ===== Leave balances (is employee ka, har type ke liye) =====
        $balances = [];
        foreach ($leaveTypes as $type) {
            $balances[$type->name] = [
                'used' => $employee->leaveRequests()
                    ->where('leave_type_id', $type->id)
                    ->where('status', 'approved')
                    ->inLeaveYear()
                    ->sum('days'),
                'total' => $employee->leaveAllowance($type),
            ];
        }
        $totalTaken = array_sum(array_column($balances, 'used'));

        // ===== Leave register (filtered list) =====
        $query = $employee->leaveRequests()->with('leaveType')
            ->whereBetween('from_date', [$from, $to]);

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        if ($leaveTypeId !== 'all') {
            $query->where('leave_type_id', $leaveTypeId);
        }

        $register = $query->orderBy('from_date', 'desc')->get();

        return view('attendence.pages.employee.leave-report', compact(
            'summary',
            'balances',
            'totalTaken',
            'register',
            'leaveTypes',
            'year',
            'from',
            'to',
            'leaveTypeId',
            'status',
            'employee'
        ));
    }
}
