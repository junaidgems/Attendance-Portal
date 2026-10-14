<?php

namespace App\Http\Controllers\Director;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Every employee of the company, read only (Admin adds and edits employees).
 */
class EmployeeController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->input('search'));
        $departmentId = $request->integer('department');
        $status = $request->input('status', 'active');

        $employees = Employee::with(['department', 'role', 'manager', 'shift'])
            ->when($departmentId, fn ($q) => $q->where('department_id', $departmentId))
            ->when($status !== 'all', fn ($q) => $q->where('employment_status', $status))
            ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('first_name', 'like', "%{$search}%")
                ->orWhere('last_name', 'like', "%{$search}%")
                ->orWhere('employee_id', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
                ->orWhere('job_title', 'like', "%{$search}%")))
            ->orderBy('first_name')
            ->get();

        return view('attendence.pages.director.employees', [
            'employees' => $employees,
            'departments' => Department::orderBy('name')->get(['id', 'name']),
            'filters' => ['search' => $search, 'department' => $departmentId, 'status' => $status],
        ]);
    }
}
