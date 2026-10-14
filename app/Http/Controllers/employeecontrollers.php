<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Department;
use App\Models\Employee;
use App\Models\GracePolicy;
use App\Models\Role;
use App\Models\Shift;
use App\Models\User;
use App\Notifications\LoginCredentials;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class employeecontrollers extends Controller
{
    /**
     * Employment statuses that block the portal login (an employee on leave can still log in).
     */
    private const LOGIN_BLOCKED_STATUSES = ['inactive', 'terminated'];

    public function index()
    {
        $employees = Employee::with(['user', 'role', 'department', 'manager', 'shift', 'gracePolicy'])
            ->orderBy('employee_id')
            ->get();

        $departments = Department::select('id', 'name')->orderBy('name')->get();
        $roles = Role::select('id', 'display_name', 'name')->orderBy('display_name')->get();
        // "Reports To" lists every active employee (same as the edit form)
        $managers = Employee::where('employment_status', 'active')
            ->orderBy('first_name')
            ->get();

        $lastEmployee = Employee::orderBy('id', 'desc')->first();
        $nextNumber = $lastEmployee ? ((int) substr($lastEmployee->employee_id, 3)) + 1 : 1001;
        $nextEmployeeId = 'GP-'.$nextNumber;

        $gracePolicies = GracePolicy::orderBy('name')->get();
        $shifts = Shift::where('is_active', 1)->orderBy('name')->get();
        // Off / WFH days can only be chosen from the company working days (the policy weekend is already off)
        $workingDays = self::companyWorkingDays();

        $usedUids = Employee::whereNotNull('zkteco_uid')->pluck('zkteco_uid')->toArray();
        $deviceUsers = DB::table('device_attendance')
            ->select('uid', 'name')
            ->distinct()
            ->whereNotIn('uid', $usedUids)
            ->orderBy('name')
            ->get();

        return view('attendence.pages.admin.employee', compact(
            'employees',
            'departments',
            'roles',
            'managers',
            'nextEmployeeId',
            'deviceUsers',
            'shifts',
            'gracePolicies',
            'workingDays'
        ));
    }

    public function store(Request $request)
    {
        $request->validate([
            'first_name' => 'required|string|max:50',
            'last_name' => 'required|string|max:50',
            'email' => 'required|email|unique:users,email|unique:employees,email',
            'password' => 'required|min:6',
            'phone' => 'nullable|string|max:20',
            'cnic' => 'required|string|max:15|unique:employees,cnic',
            'employee_id' => 'required|unique:employees,employee_id',
            'zkteco_uid' => 'nullable|integer|unique:employees,zkteco_uid',
            'department_id' => 'required|exists:departments,id',
            'job_title' => 'required|string|max:100',
            'joining_date' => 'required|date',
            'role_id' => 'required|exists:roles,id',
            'reports_to_id' => 'nullable|exists:employees,id',
            'date_of_birth' => 'nullable|date',
            'gender' => 'nullable|in:male,female,other',
            'address' => 'nullable|string',
            'emergency_contact' => 'nullable|string|max:20',
            'shift_id' => 'nullable|exists:shifts,id',
            'grace_policy_id' => 'nullable|exists:grace_policies,id',
        ] + self::SCHEDULE_RULES);

        if ($scheduleError = $this->scheduleError($request)) {
            return redirect()->back()->withInput()->with('error', $scheduleError);
        }

        // Login account and employee record are saved together: if the employee cannot be saved, the
        // login account is not kept either (it would block the email for the next attempt)
        try {
            $user = DB::transaction(function () use ($request) {
                $user = User::create([
                    'name' => $request->first_name.' '.$request->last_name,
                    'email' => $request->email,
                    'password' => Hash::make($request->password),
                    'role_id' => $request->role_id,
                    'status' => 'Active',
                ]);

                Employee::create([
                    'company_id' => 1,
                    'employee_id' => $request->employee_id,
                    'zkteco_uid' => $request->zkteco_uid ?: null,
                    'user_id' => $user->id,
                    'first_name' => $request->first_name,
                    'last_name' => $request->last_name,
                    'email' => $request->email,
                    'phone' => $request->phone,
                    'cnic' => $request->cnic,
                    'department_id' => $request->department_id,
                    'role_id' => $request->role_id,
                    'reports_to_id' => $request->reports_to_id,
                    'job_title' => $request->job_title,
                    'joining_date' => $request->joining_date,
                    'date_of_birth' => $request->date_of_birth,
                    'gender' => $request->gender,
                    'address' => $request->address,
                    'emergency_contact' => $request->emergency_contact,
                    'employment_status' => 'active',
                    'shift_id' => $request->shift_id ?: null,
                    'grace_policy_id' => $request->grace_policy_id ?: null,
                ] + $this->scheduleValues($request));

                return $user;
            });
        } catch (QueryException $e) {
            report($e);

            return redirect()->back()->withInput()->with('error', 'The employee could not be saved, nothing was created. Database error: '.($e->errorInfo[2] ?? $e->getMessage()));
        }

        if ($request->boolean('send_credentials')) {
            return $this->sendLoginDetails($user, $request->password)
                ? redirect()->back()->with('success', "Employee created successfully! Login details emailed to {$user->email}.")
                : redirect()->back()->with('warning', 'Employee created, but the login email could not be sent. Check the mail settings and use the envelope button to send it again.');
        }

        return redirect()->back()->with('success', 'Employee created successfully!');
    }

    public function edit($id)
    {
        $employee = Employee::with(['user', 'role', 'department', 'shift', 'gracePolicy'])->findOrFail($id);
        $departments = Department::select('id', 'name')->orderBy('name')->get();
        $roles = Role::select('id', 'display_name')->orderBy('display_name')->get();
        $shifts = Shift::where('is_active', 1)->orderBy('name')->get();
        $gracePolicies = GracePolicy::orderBy('name')->get();

        $managers = Employee::where('employment_status', 'active')
            ->where('id', '!=', $id)
            ->orderBy('first_name')
            ->get();

        $usedUids = Employee::whereNotNull('zkteco_uid')
            ->where('id', '!=', $id)
            ->pluck('zkteco_uid')
            ->toArray();

        $deviceUsers = DB::table('device_attendance')
            ->select('uid', 'name')
            ->distinct()
            ->whereNotIn('uid', $usedUids)
            ->orderBy('name')
            ->get();

        return response()->json([
            'employee' => $employee,
            'departments' => $departments,
            'roles' => $roles,
            'managers' => $managers,
            'deviceUsers' => $deviceUsers,
            'shifts' => $shifts,
            'gracePolicies' => $gracePolicies,
        ]);
    }

    public function update(Request $request, $id)
    {
        $employee = Employee::findOrFail($id);

        $request->validate([
            'first_name' => 'required|string|max:50',
            'last_name' => 'required|string|max:50',
            'email' => 'required|email|unique:employees,email,'.$id,
            'employee_id' => 'required|unique:employees,employee_id,'.$id,
            'zkteco_uid' => 'nullable|integer|unique:employees,zkteco_uid,'.$id,
            'role_id' => 'required|exists:roles,id',
            'department_id' => 'required|exists:departments,id',
            'job_title' => 'required|string|max:100',
            'joining_date' => 'required|date',
            'phone' => 'nullable|string|max:20',
            'cnic' => 'required|string|max:15|unique:employees,cnic,'.$id,
            'reports_to_id' => 'nullable|exists:employees,id',
            'employment_status' => 'required|in:active,inactive,terminated,on_leave',
            'date_of_birth' => 'nullable|date',
            'gender' => 'nullable|in:male,female,other',
            'address' => 'nullable|string',
            'emergency_contact' => 'nullable|string|max:20',
            'shift_id' => 'nullable|exists:shifts,id',
            'grace_policy_id' => 'nullable|exists:grace_policies,id',
        ] + self::SCHEDULE_RULES);

        if ((int) $employee->user_id === (int) $request->user()->id && in_array($request->employment_status, self::LOGIN_BLOCKED_STATUSES, true)) {
            return redirect()->back()->with('error', 'You cannot deactivate your own account.');
        }

        if ($scheduleError = $this->scheduleError($request)) {
            return redirect()->back()->with('error', $scheduleError);
        }

        $probationBefore = Employee::hasProbationColumn() && $employee->probation_until ? Carbon::parse($employee->probation_until)->toDateString() : null;

        $employee->update([
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'email' => $request->email,
            'employee_id' => $request->employee_id,
            'zkteco_uid' => $request->zkteco_uid ?: null,
            'phone' => $request->phone,
            'cnic' => $request->cnic,
            'department_id' => $request->department_id,
            'role_id' => $request->role_id,
            'reports_to_id' => $request->reports_to_id,
            'job_title' => $request->job_title,
            'joining_date' => $request->joining_date,
            'employment_status' => $request->employment_status,
            'date_of_birth' => $request->date_of_birth,
            'gender' => $request->gender,
            'address' => $request->address,
            'emergency_contact' => $request->emergency_contact,
            'shift_id' => $request->shift_id ?: null,
            'grace_policy_id' => $request->grace_policy_id ?: null,
        ] + $this->scheduleValues($request));

        if (Employee::hasProbationColumn()) {
            $this->auditProbationChange($employee, $probationBefore, $employee->fresh()->probation_until ? Carbon::parse($employee->fresh()->probation_until)->toDateString() : null);
        }

        $user = User::find($employee->user_id);
        if ($user) {
            $user->update([
                'name' => $request->first_name.' '.$request->last_name,
                'email' => $request->email,
                'role_id' => $request->role_id,
                // Inactive / Terminated: no portal login; Active / On Leave: login allowed
                'status' => in_array($request->employment_status, self::LOGIN_BLOCKED_STATUSES, true) ? 'Inactive' : 'Active',
            ]);

            if ($request->filled('password')) {
                $user->update(['password' => Hash::make($request->password)]);

                if ($request->boolean('send_credentials')) {
                    return $this->sendLoginDetails($user, $request->password)
                        ? redirect()->back()->with('success', "Employee updated successfully! New login details emailed to {$user->email}.")
                        : redirect()->back()->with('warning', 'Employee updated, but the login email could not be sent. Check the mail settings and try again.');
                }
            }
        }

        return redirect()->back()->with('success', 'Employee updated successfully!');
    }

    /**
     * Weekly schedule of the employee: fixed off days and fixed Work From Home days (checkbox lists).
     *
     * @var array<string, string>
     */
    private const SCHEDULE_RULES = [
        'off_days' => 'nullable|array',
        'off_days.*' => 'in:Mon,Tue,Wed,Thu,Fri,Sat,Sun',
        'wfh_days' => 'nullable|array',
        'wfh_days.*' => 'in:Mon,Tue,Wed,Thu,Fri,Sat,Sun',
        // Permanent, or on probation until a date (saved only once the column exists)
        'employment_type' => 'nullable|in:permanent,probation',
        'probation_until' => 'nullable|date|required_if:employment_type,probation',
    ];

    /**
     * A day cannot be both an off day and a fixed WFH day.
     */
    private function scheduleError(Request $request): ?string
    {
        $both = array_intersect((array) $request->input('off_days', []), (array) $request->input('wfh_days', []));

        return $both ? 'A day cannot be both an off day and a Work From Home day ('.implode(', ', $both).').' : null;
    }

    /**
     * off_days / wfh_days to save ("Sat" / "Mon,Fri" / null), limited to the company working days;
     * nothing until the columns exist.
     *
     * @return array<string, ?string>
     */
    private function scheduleValues(Request $request): array
    {
        if (! Employee::hasScheduleColumns()) {
            return [];
        }

        $workingDays = self::companyWorkingDays();
        $days = fn (string $field) => implode(',', array_intersect(Employee::parseWeekdays(implode(',', (array) $request->input($field, []))), $workingDays)) ?: null;

        $values = ['off_days' => $days('off_days'), 'wfh_days' => $days('wfh_days')];

        if (Employee::hasProbationColumn()) {
            $onProbation = $request->input('employment_type') === 'probation' && $request->filled('probation_until');
            $values['probation_until'] = $onProbation ? Carbon::parse($request->probation_until)->toDateString() : null;
        }

        return $values;
    }

    /**
     * Audit a changed probation end date (set, extended, shortened or removed).
     */
    private function auditProbationChange(Employee $employee, ?string $before, ?string $after): void
    {
        if ($before === $after) {
            return;
        }

        $label = fn (?string $d) => $d ? Carbon::parse($d)->format('d M Y') : 'none';

        AuditLog::record('employee', 'Probation changed', "{$employee->full_name}: probation until {$label($before)} → {$label($after)}");
    }

    /**
     * Attendance Policy working days, in weekday order.
     *
     * @return array<int, string>
     */
    private static function companyWorkingDays(): array
    {
        return Employee::parseWeekdays(DB::table('attendance_settings')->value('working_days') ?? 'Mon,Tue,Wed,Thu,Fri');
    }

    /**
     * Email one employee their login details. Their current password cannot be read back (it is stored
     * hashed), so a new password is generated, saved and emailed.
     */
    public function sendCredentials(Request $request, $id): RedirectResponse
    {
        $employee = Employee::with('user')->findOrFail($id);
        $user = $employee->user;

        if (! $user || ! $user->email) {
            return redirect()->back()->with('error', "{$employee->full_name} has no login account with an email address.");
        }

        if ($user->id === $request->user()->id) {
            return redirect()->back()->with('error', 'You cannot reset and email your own password from here.');
        }

        if (! $user->isActive()) {
            return redirect()->back()->with('error', "{$employee->full_name}'s login account is inactive.");
        }

        if (! $this->sendLoginDetails($user, $this->newPassword($user))) {
            return redirect()->back()->with('error', "A new password was set for {$employee->full_name}, but the email could not be sent. Check the mail settings and try again.");
        }

        return redirect()->back()->with('success', "New login details emailed to {$employee->full_name} ({$user->email}).");
    }

    /**
     * Email login details to every active employee who has not received them yet (a new password each).
     * Administrators and the logged-in user are skipped, so no Admin is locked out by a bulk send.
     */
    public function sendCredentialsToAll(Request $request): RedirectResponse
    {
        $users = User::query()
            ->whereNull('credentials_sent_at')
            ->whereNotNull('email')
            ->where('id', '!=', $request->user()->id)
            ->whereDoesntHave('role', fn ($q) => $q->where('name', 'administrator'))
            ->whereHas('employee', fn ($q) => $q->where('employment_status', 'active'))
            ->get()
            ->filter->isActive();

        if ($users->isEmpty()) {
            return redirect()->back()->with('info', 'Every active employee has already received their login details. Use the envelope button on a row to send them again.');
        }

        $sent = $users->filter(fn (User $user) => $this->sendLoginDetails($user, $this->newPassword($user)))->count();
        $failed = $users->count() - $sent;

        if ($failed > 0) {
            return redirect()->back()->with('warning', "Login details emailed to {$sent} employee(s); {$failed} email(s) could not be sent. Check the mail settings and press the button again for the rest.");
        }

        return redirect()->back()->with('success', "Login details emailed to {$sent} employee(s).");
    }

    /**
     * Save a new random password for the user and return it.
     */
    private function newPassword(User $user): string
    {
        $password = Str::password(10, symbols: false);
        $user->update(['password' => Hash::make($password)]);

        return $password;
    }

    /**
     * Returns false when the email could not be sent (the error is logged, the request keeps working).
     */
    private function sendLoginDetails(User $user, string $password): bool
    {
        try {
            $user->notify(new LoginCredentials($user->email, $password));
        } catch (\Throwable $e) {
            report($e);

            return false;
        }

        $user->forceFill(['credentials_sent_at' => now()])->save();

        AuditLog::record('employee', 'Login details emailed', "{$user->name} ({$user->email})");

        return true;
    }

    /**
     * Activate or deactivate an employee instead of deleting them: a deactivated employee cannot log in
     * (and is logged out on their next click), and their attendance and leave history stay as they are.
     */
    public function toggleStatus(Request $request, $id): RedirectResponse
    {
        $employee = Employee::with('user')->findOrFail($id);

        if ((int) $employee->user_id === (int) $request->user()->id) {
            return redirect()->back()->with('error', 'You cannot deactivate your own account.');
        }

        $activate = $employee->employment_status !== 'active';

        DB::transaction(function () use ($employee, $activate) {
            $employee->update(['employment_status' => $activate ? 'active' : 'inactive']);
            $employee->user?->update(['status' => $activate ? 'Active' : 'Inactive']);
        });

        $action = $activate ? 'activated' : 'deactivated';
        AuditLog::record('employee', 'Employee '.$action, "{$employee->full_name} ({$employee->employee_id})");

        return redirect()->back()->with('success', $activate
            ? "{$employee->full_name} is active again and can log in."
            : "{$employee->full_name} has been deactivated and can no longer log in.");
    }

    public function destroy($id)
    {
        $employee = Employee::findOrFail($id);

        DB::transaction(function () use ($employee) {
            if ($employee->user) {
                $employee->user->delete();
            }

            // The row is only soft-deleted (attendance and leave history stay), so free its unique
            // email, CNIC, employee ID and device UID for a new employee: "ali@x.com" → "DEL15-ali@x.com"
            $tag = 'DEL'.$employee->id.'-';
            $employee->update([
                'email' => $tag.$employee->email,
                'cnic' => $tag.$employee->cnic,
                'employee_id' => $tag.$employee->employee_id,
                'zkteco_uid' => null,
            ]);

            $employee->delete();
        });

        return redirect()->back()->with('success', 'Employee deleted successfully!');
    }

    public function getEmployees()
    {
        $employees = Employee::select('id', 'employee_id', 'first_name', 'last_name')
            ->where('employment_status', 'active')
            ->orderBy('first_name')
            ->get()
            ->map(fn ($e) => [
                'id' => $e->id,
                'label' => $e->employee_id.' - '.$e->first_name.' '.$e->last_name,
            ]);

        return response()->json($employees);
    }

    public function getRoles()
    {
        $roles = Role::select('id', 'display_name as label')->orderBy('display_name')->get();

        return response()->json($roles);
    }

    public function getDepartments()
    {
        $departments = Department::select('id', 'name as label')->orderBy('name')->get();

        return response()->json($departments);
    }

    // ===== EXPORT CSV =====
    public function exportCsv()
    {
        $employees = Employee::with(['department', 'role', 'shift'])->orderBy('employee_id')->get();

        $filename = 'employees-'.now()->format('Y-m-d').'.csv';
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ];

        $callback = function () use ($employees) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['Emp ID', 'First Name', 'Last Name', 'Email', 'Phone', 'CNIC', 'Department', 'Job Title', 'Role', 'Shift', 'Joining Date', 'Status']);

            foreach ($employees as $e) {
                fputcsv($file, [
                    $e->employee_id,
                    $e->first_name,
                    $e->last_name,
                    $e->email,
                    $e->phone,
                    $e->cnic,
                    $e->department->name ?? '-',
                    $e->job_title,
                    $e->role->display_name ?? '-',
                    $e->shift->name ?? '-',
                    $e->joining_date,
                    $e->employment_status,
                ]);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    // ===== EXPORT EXCEL =====
    public function exportExcel()
    {
        $employees = Employee::with(['department', 'role', 'shift'])->orderBy('employee_id')->get();

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();

        $headers = ['Emp ID', 'First Name', 'Last Name', 'Email', 'Phone', 'CNIC', 'Department', 'Job Title', 'Role', 'Shift', 'Joining Date', 'Status'];
        $sheet->fromArray($headers, null, 'A1');

        $row = 2;
        foreach ($employees as $e) {
            $sheet->fromArray([
                $e->employee_id,
                $e->first_name,
                $e->last_name,
                $e->email,
                $e->phone,
                $e->cnic,
                $e->department->name ?? '-',
                $e->job_title,
                $e->role->display_name ?? '-',
                $e->shift->name ?? '-',
                $e->joining_date,
                $e->employment_status,
            ], null, 'A'.$row);
            $row++;
        }

        $filename = 'employees-'.now()->format('Y-m-d').'.xlsx';
        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    // ===== EXPORT PDF =====
    public function exportPdf()
    {
        $employees = Employee::with(['department', 'role', 'shift'])->orderBy('employee_id')->get();

        $pdf = Pdf::loadView('attendence.pages.admin.employee-pdf', compact('employees'))
            ->setPaper('a4', 'landscape');

        return $pdf->download('employees-'.now()->format('Y-m-d').'.pdf');
    }

    // ===== IMPORT CSV =====
    public function importCsv(Request $request)
    {
        $request->validate([
            'csv_file' => 'required|file|mimes:csv,txt|max:2048',
        ]);

        $file = fopen($request->file('csv_file')->getRealPath(), 'r');
        $header = fgetcsv($file);

        $lastEmployee = Employee::orderBy('id', 'desc')->first();
        $nextNumber = $lastEmployee ? ((int) substr($lastEmployee->employee_id, 3)) + 1 : 1001;

        $imported = 0;
        $skipped = 0;
        $errors = [];

        while (($row = fgetcsv($file)) !== false) {
            // Expected columns: First Name, Last Name, Email, Phone, CNIC, Department Name, Job Title, Joining Date
            [$firstName, $lastName, $email, $phone, $cnic, $deptName, $jobTitle, $joiningDate] = array_pad($row, 8, null);

            if (! $firstName || ! $email || ! $cnic) {
                $skipped++;

                continue;
            }

            if (User::where('email', $email)->exists() || Employee::where('email', $email)->exists() || Employee::where('cnic', $cnic)->exists()) {
                $skipped++;
                $errors[] = "$email ya $cnic already maujood hai";

                continue;
            }

            $department = Department::where('name', trim($deptName))->first();
            if (! $department) {
                $skipped++;
                $errors[] = "$firstName: department '$deptName' nahi mila";

                continue;
            }

            $employeeRole = Role::where('name', 'employee')->first();

            $user = User::create([
                'name' => $firstName.' '.$lastName,
                'email' => $email,
                'password' => Hash::make('changeme123'), // default password
                'role_id' => $employeeRole->id ?? null,
                'status' => 'Active',
            ]);

            Employee::create([
                'company_id' => 1,
                'employee_id' => 'GP-'.$nextNumber,
                'user_id' => $user->id,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email' => $email,
                'phone' => $phone,
                'cnic' => $cnic,
                'department_id' => $department->id,
                'role_id' => $employeeRole->id ?? null,
                'job_title' => $jobTitle ?: 'N/A',
                'joining_date' => $joiningDate ?: now()->format('Y-m-d'),
                'employment_status' => 'active',
            ]);

            $nextNumber++;
            $imported++;
        }

        fclose($file);

        $message = "$imported employees import hue.";
        if ($skipped > 0) {
            $message .= " $skipped skip hue.";
        }

        return redirect()->back()->with($errors ? 'error' : 'success', $message.(count($errors) ? ' ('.implode('; ', array_slice($errors, 0, 3)).')' : ''));
    }
}
