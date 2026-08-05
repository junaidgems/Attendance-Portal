<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class employeecontrollers extends Controller
{
    /**
     * Display a listing of employees.
     */
  public function index()
{
    $employees = Employee::with(['user', 'role', 'department', 'manager'])
        ->orderBy('employee_id')
        ->get();

    $departments = Department::select('id', 'name')->orderBy('name')->get();
    $roles = Role::select('id', 'display_name', 'name')->orderBy('display_name')->get();
    $managers = Employee::with('user')
        ->whereIn('role_id', function ($query) {
            $query->select('id')->from('roles')->whereIn('name', ['administrator', 'director', 'manager', 'line_manager']);
        })
        ->where('employment_status', 'active')
        ->orderBy('first_name')
        ->get();

    // ✅ Next Employee ID auto-generate karo
    $lastEmployee = Employee::orderBy('id', 'desc')->first();
    $nextNumber = $lastEmployee ? ((int) substr($lastEmployee->employee_id, 3)) + 1 : 1001;
    $nextEmployeeId = 'GP-' . $nextNumber;

    return view('attendence.pages.admin.employee', compact('employees', 'departments', 'roles', 'managers', 'nextEmployeeId'));
}

    /**
     * Store a newly created employee.
     */
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
        'department_id' => 'required|exists:departments,id',
        'job_title' => 'required|string|max:100',
        'joining_date' => 'required|date',
        'role_id' => 'required|exists:roles,id',
        'reports_to_id' => 'nullable|exists:employees,id',
        'date_of_birth' => 'nullable|date',
        'gender' => 'nullable|in:male,female,other',
        'address' => 'nullable|string',
        'emergency_contact' => 'nullable|string|max:20',
        // ❌ shift_id HATAO
        // ❌ grace_policy_id HATAO
    ]);

    // 1. Create User
    $user = User::create([
        'name' => $request->first_name . ' ' . $request->last_name,
        'email' => $request->email,
        'password' => Hash::make($request->password),
        'role_id' => $request->role_id,
        'status' => 'Active',
    ]);

    // 2. Create Employee
    Employee::create([
        'company_id' => 1,
        'employee_id' => $request->employee_id,
        'user_id' => $user->id,
        'first_name' => $request->first_name,
        'last_name' => $request->last_name,
        'email' => $request->email,
        'phone' => $request->phone,
        'cnic' => $request->cnic,
        'department_id' => $request->department_id,
        // ❌ shift_id HATAO
        // ❌ grace_policy_id HATAO
        'role_id' => $request->role_id,
        'reports_to_id' => $request->reports_to_id,
        'job_title' => $request->job_title,
        'joining_date' => $request->joining_date,
        'date_of_birth' => $request->date_of_birth,
        'gender' => $request->gender,
        'address' => $request->address,
        'emergency_contact' => $request->emergency_contact,
        'employment_status' => 'active',
    ]);

    return redirect()->back()->with('success', 'Employee created successfully!');
}

    /*** Show the form for editing the specified employee.*/

    public function edit($id)
    {
        $employee = Employee::with(['user', 'role', 'department'])->findOrFail($id);
        $departments = Department::select('id', 'name')->orderBy('name')->get();
        $roles = Role::select('id', 'display_name')->orderBy('display_name')->get();
        $managers = Employee::where('employment_status', 'active')
            ->where('id', '!=', $id)
            ->orderBy('first_name')
            ->get();

        return response()->json([
            'employee' => $employee,
            'departments' => $departments,
            'roles' => $roles,
            'managers' => $managers,
        ]);
    }

    /* Update the specified employee.*/

  public function update(Request $request, $id)
{
    $employee = Employee::findOrFail($id);

    $request->validate([
        'first_name' => 'required|string|max:50',
        'last_name' => 'required|string|max:50',
        'email' => 'required|email|unique:employees,email,' . $id,
        'employee_id' => 'required|unique:employees,employee_id,' . $id,
        'role_id' => 'required|exists:roles,id',
        'department_id' => 'required|exists:departments,id',
        'job_title' => 'required|string|max:100',
        'joining_date' => 'required|date',
        'phone' => 'nullable|string|max:20',
        'cnic' => 'nullable|string|max:15|unique:employees,cnic,' . $id,
        'reports_to_id' => 'nullable|exists:employees,id',
        'employment_status' => 'required|in:active,inactive,terminated,on_leave',
        'date_of_birth' => 'nullable|date',
        'gender' => 'nullable|in:male,female,other',
        'address' => 'nullable|string',
        'emergency_contact' => 'nullable|string|max:20',
        // ❌ shift_id HATAO
        // ❌ grace_policy_id HATAO
    ]);

    $employee->update([
        'first_name' => $request->first_name,
        'last_name' => $request->last_name,
        'email' => $request->email,
        'employee_id' => $request->employee_id,
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
        // ❌ shift_id HATAO
        // ❌ grace_policy_id HATAO
    ]);

    $user = User::find($employee->user_id);
    if ($user) {
        $user->update([
            'name' => $request->first_name . ' ' . $request->last_name,
            'email' => $request->email,
            'role_id' => $request->role_id,
        ]);

        if ($request->filled('password')) {
            $user->update(['password' => Hash::make($request->password)]);
        }
    }

    return redirect()->back()->with('success', 'Employee updated successfully!');
}

    /* Remove the specified employee. */
    public function destroy($id)
    {
        $employee = Employee::findOrFail($id);

        // ✅ User delete (soft delete employees)
        if ($employee->user) {
            $employee->user->delete();
        }

        $employee->delete();

        return redirect()->back()->with('success', 'Employee deleted successfully!');
    }

    // ===== API METHODS FOR DROPDOWNS =====

    public function getEmployees()
    {
        $employees = Employee::select('id', 'employee_id', 'first_name', 'last_name')
            ->where('employment_status', 'active')
            ->orderBy('first_name')
            ->get()
            ->map(fn($e) => [
                'id' => $e->id,
                'label' => $e->employee_id . ' - ' . $e->first_name . ' ' . $e->last_name
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
}