@extends('attendence.layouts.app')

@section('title', 'Employees')
@section('page-title', 'Employees')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/employee.css') }}">
@endpush

@section('content')


    <!-- HEADER + ADD BUTTON                       -->

    <div class="row">
        <div class="col-12">
            <div class="glass-card">
                <div class="row align-items-center">

                    <!-- Left: Search -->
                    <div class="col-lg-5 col-md-12">
                        <div class="search-bar">
                            <input type="text" class="form-control" placeholder="🔍 Search name, ID, department...">
                        </div>
                    </div>

                    <!-- Right: Buttons -->
                    <div class="col-lg-7 col-md-12">
                        <div class="head-btns" style="display: flex; flex-wrap: wrap; gap: 8px; justify-content: flex-end;">

                            <button class="btn-primary-glass" data-bs-toggle="modal" data-bs-target="#addEmployeeModal">
                                <i class="bi bi-person-plus"></i> Add
                            </button>

                            <button class="btn-outline-glass" onclick="alert('Import CSV clicked')">
                                <i class="bi bi-file-earmark-arrow-up"></i> Import
                            </button>

                            <button class="btn-outline-glass" onclick="alert('Export CSV clicked')">
                                <i class="bi bi-file-earmark-arrow-down"></i> CSV
                            </button>

                            <button class="btn-outline-glass" onclick="alert('Export PDF clicked')">
                                <i class="bi bi-file-earmark-pdf"></i> PDF
                            </button>

                            <button class="btn-outline-glass" onclick="alert('Export Excel clicked')">
                                <i class="bi bi-file-earmark-excel"></i> Excel
                            </button>

                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>

    <!-- TABLE                                     -->

    <div class="row" style="margin-top: 20px;">
        <div class="col-12">
            <div class="glass-card" style="padding: 0; overflow: hidden;">
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>EMP ID</th>
                                <th>Department</th>
                                <th>Shift</th>
                                <th>Role</th>
                                <th>Reports To</th>
                                <th>Joined</th>
                                <th style="text-align: center;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($employees as $emp)
                                <tr>
                                    <td class="employe-info">
                                        <span class="employee-avatar">
                                            {{ strtoupper(substr($emp->first_name, 0, 1) . substr($emp->last_name, 0, 1)) }}
                                        </span>
                                        <div>
                                            <span class="employee-name">{{ $emp->first_name }} {{ $emp->last_name }}</span>
                                            <span class="employee-role">{{ $emp->job_title }}</span>
                                        </div>
                                    </td>
                                    <td><strong>{{ $emp->employee_id }}</strong></td>
                                    <td>{{ $emp->department->name ?? '-' }}</td>
                                    <td>Not Set</td>
                                    <td>{{ $emp->role->display_name ?? '-' }}</td>
                                    <td>
                                        {{ $emp->manager ? $emp->manager->first_name . ' ' . $emp->manager->last_name : 'None' }}
                                    </td>
                                    <td>{{ $emp->joining_date ? \Carbon\Carbon::parse($emp->joining_date)->format('d M Y') : '-' }}
                                    </td>
                                    <td style="text-align: center; white-space: nowrap;">
                                        <button type="button" class="btn-icon" onclick="openEditModal({{ $emp->id }})">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <form action="{{ route('admin.employee.destroy', $emp->id) }}" method="POST"
                                            style="display:inline;" onsubmit="return confirm('Delete this employee?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn-icon danger">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" style="text-align:center; padding: 20px;">No employees found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>



    <!-- ADD EMPLOYEE MODAL (Glassmorphism)        -->
    <div class="modal fade modal-glass" id="addEmployeeModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">

                {{-- IMPORTANT: form tag ab yahan se shuru hoga, poore modal-body ko wrap karega --}}
                <form class="form-glass" action="{{ route('admin.employee.store') }}" method="POST">
                    @csrf

                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="bi bi-person-plus" style="color:var(--primary);"></i> Add Employee
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>

                    <div class="modal-body">

                        {{-- Validation errors ka block --}}
                        @if ($errors->any())
                            <div class="alert alert-danger">
                                <ul class="mb-0">
                                    @foreach ($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>First Name</label>
                                    <input type="text" name="first_name" class="form-control" placeholder="e.g. Faraz"
                                        value="{{ old('first_name') }}" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Last Name</label>
                                    <input type="text" name="last_name" class="form-control" placeholder="e.g. Siddiqui"
                                        value="{{ old('last_name') }}" required>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Employee ID</label>
                                    <input type="text" name="employee_id" class="form-control" placeholder="GP-1005"
                                        value="{{ old('employee_id', $nextEmployeeId ?? 'GP-1005') }}" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>CNIC</label>
                                    <input type="text" name="cnic" class="form-control" placeholder="42101-1234567-1"
                                        value="{{ old('cnic') }}" required>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Email</label>
                                    <input type="email" name="email" class="form-control" placeholder="employee@gems.edu"
                                        value="{{ old('email') }}" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Password</label>
                                    <input type="password" name="password" class="form-control" placeholder="••••••••"
                                        required>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Phone</label>
                                    <input type="text" name="phone" class="form-control" placeholder="03xx-xxxxxxx"
                                        value="{{ old('phone') }}">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Emergency Contact</label>
                                    <input type="text" name="emergency_contact" class="form-control"
                                        placeholder="03xx-xxxxxxx" value="{{ old('emergency_contact') }}">
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Department</label>
                                    <select name="department_id" class="form-control" required>
                                        <option value="">Select Department</option>
                                        @foreach($departments as $dept)
                                            <option value="{{ $dept->id }}" {{ old('department_id') == $dept->id ? 'selected' : '' }}>
                                                {{ $dept->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Job Title</label>
                                    <input type="text" name="job_title" class="form-control"
                                        placeholder="e.g. System Administrator" value="{{ old('job_title') }}" required>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Role</label>
                                    <select name="role_id" class="form-control" required>
                                        <option value="">Select Role</option>
                                        @foreach($roles as $role)
                                            <option value="{{ $role->id }}" {{ old('role_id') == $role->id ? 'selected' : '' }}>
                                                {{ $role->display_name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Reports To</label>
                                    <select name="reports_to_id" class="form-control">
                                        <option value="">none</option>
                                        @foreach($managers as $manager)
                                            <option value="{{ $manager->id }}" {{ old('reports_to_id') == $manager->id ? 'selected' : '' }}>
                                                {{ $manager->first_name }} {{ $manager->last_name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Join Date</label>
                                    <input type="date" name="joining_date" class="form-control"
                                        value="{{ old('joining_date', date('Y-m-d')) }}" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Date of Birth</label>
                                    <input type="date" name="date_of_birth" class="form-control"
                                        value="{{ old('date_of_birth') }}">
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Gender</label>
                                    <select name="gender" class="form-control">
                                        <option value="">Select Gender</option>
                                        <option value="male" {{ old('gender') == 'male' ? 'selected' : '' }}>Male</option>
                                        <option value="female" {{ old('gender') == 'female' ? 'selected' : '' }}>Female
                                        </option>
                                        <option value="other" {{ old('gender') == 'other' ? 'selected' : '' }}>Other</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Address</label>
                                    <input type="text" name="address" class="form-control" placeholder="Address"
                                        value="{{ old('address') }}">
                                </div>
                            </div>
                        </div>

                        <div class="grace-info">
                            <i class="bi bi-shield-check"></i>
                            No reporting authority set — requests routed to <strong>Faraz Siddiqui</strong> (Administrator).
                        </div>

                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn-cancel" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn-save"><i class="bi bi-check2"></i> Add Employee</button>
                    </div>

                </form>
            </div>
        </div>
    </div>

    <div class="modal fade modal-glass" id="editEmployeeModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">

                <form class="form-glass" id="editForm" method="POST" action="">
                    @csrf
                    @method('PUT')

                    {{-- hidden field, JS isko dynamically employee ka id set karega --}}
                    <input type="hidden" name="_employee_id_placeholder" id="edit_pk">

                    <div class="modal-header">
                        <h5 class="modal-title"><i class="bi bi-pencil" style="color:var(--primary);"></i> Edit Employee
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>

                    <div class="modal-body">

                        {{-- AJAX se aane wale errors yahan JS dikhayega --}}
                        <div id="editErrorBox" class="alert alert-danger" style="display:none;"></div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>First Name</label>
                                    <input type="text" name="first_name" id="edit_first_name" class="form-control" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Last Name</label>
                                    <input type="text" name="last_name" id="edit_last_name" class="form-control" required>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Employee ID</label>
                                    <input type="text" name="employee_id" id="edit_employee_id" class="form-control"
                                        required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>CNIC</label>
                                    <input type="text" name="cnic" id="edit_cnic" class="form-control">
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Email</label>
                                    <input type="email" name="email" id="edit_email" class="form-control" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Password</label>
                                    <input type="password" name="password" class="form-control"
                                        placeholder="Leave blank to keep current">
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Phone</label>
                                    <input type="text" name="phone" id="edit_phone" class="form-control">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Emergency Contact</label>
                                    <input type="text" name="emergency_contact" id="edit_emergency_contact"
                                        class="form-control">
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Department</label>
                                    <select name="department_id" id="edit_department" class="form-control" required>
                                        @foreach($departments as $dept)
                                            <option value="{{ $dept->id }}">{{ $dept->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Job Title</label>
                                    <input type="text" name="job_title" id="edit_job_title" class="form-control" required>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Role</label>
                                    <select name="role_id" id="edit_role" class="form-control" required>
                                        @foreach($roles as $role)
                                            <option value="{{ $role->id }}">{{ $role->display_name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Reports To</label>
                                    <select name="reports_to_id" id="edit_reports_to" class="form-control">
                                        <option value="">none</option>
                                        @foreach($managers as $manager)
                                            <option value="{{ $manager->id }}">{{ $manager->first_name }}
                                                {{ $manager->last_name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Join Date</label>
                                    <input type="date" name="joining_date" id="edit_joining_date" class="form-control"
                                        required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Date of Birth</label>
                                    <input type="date" name="date_of_birth" id="edit_date_of_birth" class="form-control">
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Gender</label>
                                    <select name="gender" id="edit_gender" class="form-control">
                                        <option value="">Select Gender</option>
                                        <option value="male">Male</option>
                                        <option value="female">Female</option>
                                        <option value="other">Other</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Employment Status</label>
                                    <select name="employment_status" id="edit_employment_status" class="form-control"
                                        required>
                                        <option value="active">Active</option>
                                        <option value="inactive">Inactive</option>
                                        <option value="on_leave">On Leave</option>
                                        <option value="terminated">Terminated</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-12">
                                <div class="form-group">
                                    <label>Address</label>
                                    <input type="text" name="address" id="edit_address" class="form-control">
                                </div>
                            </div>
                        </div>

                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn-cancel" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn-save"><i class="bi bi-check2"></i> Update Employee</button>
                    </div>

                </form>
            </div>
        </div>
    </div>


    @if ($errors->any())
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                var modal = new bootstrap.Modal(document.getElementById('addEmployeeModal'));
                modal.show();
            });
        </script>
    @endif
@endsection

@push('scripts')
    <script>
        function editEmployee(id, name, email, department, jobtitle, role, reports, joined, shift, grace) {
            document.getElementById('edit_name').value = name;
            document.getElementById('edit_empid').value = id;
            document.getElementById('edit_email').value = email;
            document.getElementById('edit_department').value = department;
            document.getElementById('edit_jobtitle').value = jobtitle;
            document.getElementById('edit_role').value = role;
            document.getElementById('edit_reports').value = reports;
            document.getElementById('edit_joined').value = joined;
            document.getElementById('edit_shift').value = shift;
            document.getElementById('edit_grace').value = grace;
        }


        // Cleanup stray backdrops whenever any modal closes
        document.addEventListener('hidden.bs.modal', function () {
            document.body.classList.remove('modal-open');
            document.body.style.removeProperty('padding-right');
            document.querySelectorAll('.modal-backdrop').forEach(el => el.remove());
        });

        // Extra safety: before opening a modal, clear any leftover backdrops
        document.addEventListener('show.bs.modal', function () {
            document.querySelectorAll('.modal-backdrop').forEach(el => el.remove());
        });
    </script>
@endpush

<script>
    function openEditModal(id) {
        fetch(`/admin/employees/${id}/edit`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
            .then(res => res.json())
            .then(data => {
                const emp = data.employee;

                document.getElementById('editForm').action = `/admin/employees/${id}`;

                document.getElementById('edit_first_name').value = emp.first_name ?? '';
                document.getElementById('edit_last_name').value = emp.last_name ?? '';
                document.getElementById('edit_employee_id').value = emp.employee_id ?? '';
                document.getElementById('edit_cnic').value = emp.cnic ?? '';
                document.getElementById('edit_email').value = emp.email ?? '';
                document.getElementById('edit_phone').value = emp.phone ?? '';
                document.getElementById('edit_emergency_contact').value = emp.emergency_contact ?? '';
                document.getElementById('edit_department').value = emp.department_id ?? '';
                document.getElementById('edit_job_title').value = emp.job_title ?? '';
                document.getElementById('edit_role').value = emp.role_id ?? '';
                document.getElementById('edit_reports_to').value = emp.reports_to_id ?? '';
                document.getElementById('edit_joining_date').value = emp.joining_date ? emp.joining_date.substring(0, 10) : '';
                document.getElementById('edit_date_of_birth').value = emp.date_of_birth ? emp.date_of_birth.substring(0, 10) : '';
                document.getElementById('edit_gender').value = emp.gender ?? '';
                document.getElementById('edit_employment_status').value = emp.employment_status ?? 'active';
                document.getElementById('edit_address').value = emp.address ?? '';

                document.getElementById('editErrorBox').style.display = 'none';

                var modal = new bootstrap.Modal(document.getElementById('editEmployeeModal'));
                modal.show();
            })
            .catch(err => {
                console.error(err);
                alert('Employee data load nahi ho saka. Console check karo.');
            });
    }
</script>