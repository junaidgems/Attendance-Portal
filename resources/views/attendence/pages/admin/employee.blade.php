@extends('attendence.layouts.app')

@section('title', 'Employees')
@section('page-title', 'Employees')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/employee.css') }}">
    <style>
        .sidebar-menu .nav-item {
    list-style: none;
    margin-bottom: 14px;
}
        /* Weekly off / WFH day checkboxes on the employee forms */
        .weekday-checks { display: flex; flex-wrap: wrap; gap: 6px 12px; padding: 6px 0; }
        .weekday-checks label { display: inline-flex; align-items: center; gap: 4px; font-size: 13px; margin: 0; cursor: pointer; }
        .weekday-checks input { width: auto; margin: 0; }
    </style>
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

    <button class="btn-outline-glass" data-bs-toggle="modal" data-bs-target="#importCsvModal">
        <i class="bi bi-file-earmark-arrow-up"></i> Import
    </button>

    <a href="{{ route('admin.employee.export.csv') }}" class="btn-outline-glass">
        <i class="bi bi-file-earmark-arrow-down"></i> CSV
    </a>

    <a href="{{ route('admin.employee.export.pdf') }}" class="btn-outline-glass">
        <i class="bi bi-file-earmark-pdf"></i> PDF
    </a>

    <a href="{{ route('admin.employee.export.excel') }}" class="btn-outline-glass">
        <i class="bi bi-file-earmark-excel"></i> Excel
    </a>

    <form action="{{ route('admin.employee.send-credentials.all') }}" method="POST" style="display:inline;"
        onsubmit="return confirm('Email login details to every active employee who has not received them yet?\n\nA new password will be created for each of them. Administrators are skipped.');">
        @csrf
        <button type="submit" class="btn-outline-glass">
            <i class="bi bi-envelope-paper"></i> Send Login Details to All
        </button>
    </form>

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
                                <th>Status</th>
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
                                    <td>
                                        @if ($emp->shift)
                                            {{ $emp->shift->name }}
                                            <br>
                                            <small
                                                style="color:#888;">{{ \Carbon\Carbon::parse($emp->shift->start_time)->format('h:i A') }}
                                                -
                                                {{ \Carbon\Carbon::parse($emp->shift->end_time)->format('h:i A') }}</small>
                                        @else
                                            <span style="color:#999;">Default</span>
                                        @endif
                                    </td>
                                    <td>{{ $emp->role->display_name ?? '-' }}</td>
                                    <td>
                                        {{ $emp->manager ? $emp->manager->first_name . ' ' . $emp->manager->last_name : 'None' }}
                                    </td>
                                    <td>{{ $emp->joining_date ? \Carbon\Carbon::parse($emp->joining_date)->format('d M Y') : '-' }}
                                    </td>
                                    <td>
                                        @if ($emp->employment_status === 'active')
                                            <span style="display:inline-block; padding:3px 10px; border-radius:20px; font-size:12px; font-weight:600; background:#dcfce7; color:#15803d;">Active</span>
                                            @if ($emp->isOnProbation())
                                                <br><span title="Probation until {{ \Carbon\Carbon::parse($emp->probation_until)->format('d M Y') }}" style="display:inline-block; margin-top:4px; padding:2px 8px; border-radius:20px; font-size:11px; font-weight:600; background:#fef3c7; color:#b45309;"><i class="bi bi-hourglass-split"></i> Probation · {{ \Carbon\Carbon::parse($emp->probation_until)->format('d M') }}</span>
                                            @endif
                                        @else
                                            <span style="display:inline-block; padding:3px 10px; border-radius:20px; font-size:12px; font-weight:600; background:#fee2e2; color:#b91c1c;">{{ ucwords(str_replace('_', ' ', $emp->employment_status ?? 'inactive')) }}</span>
                                        @endif
                                    </td>
                                    <td style="text-align: center; white-space: nowrap;">
                                        <button type="button" class="btn-icon"
                                            onclick="openEditModal({{ $emp->id }})">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        @if ($emp->user && $emp->user->email)
                                            <form action="{{ route('admin.employee.send-credentials', $emp->id) }}" method="POST"
                                                style="display:inline;"
                                                onsubmit="return confirm(@js('Email login details to '.$emp->first_name.' '.$emp->last_name."?\n\nA new password will be created and their current password will stop working."));">
                                                @csrf
                                                <button type="submit" class="btn-icon"
                                                    title="{{ $emp->user->credentials_sent_at ? 'Login details sent '.\Carbon\Carbon::parse($emp->user->credentials_sent_at)->format('d M Y').' · send again' : 'Send login details' }}">
                                                    <i class="bi {{ $emp->user->credentials_sent_at ? 'bi-envelope-check' : 'bi-envelope' }}"></i>
                                                </button>
                                            </form>
                                        @endif
                                        @if ($emp->user && (int) $emp->user_id !== (int) auth()->id() && $emp->employment_status === 'active' && $emp->user->isActive() && ! $emp->user->hasRole('administrator'))
                                            <form action="{{ route('admin.employee.login-as', $emp->id) }}" method="POST"
                                                style="display:inline;"
                                                onsubmit="return confirm(@js('Log in as '.$emp->first_name.' '.$emp->last_name."?\n\nYou will use their account with full access; everything you do is saved under their name. Use \"Back to Admin\" at the top to return."));">
                                                @csrf
                                                <button type="submit" class="btn-icon" title="Login as this employee">
                                                    <i class="bi bi-box-arrow-in-right"></i>
                                                </button>
                                            </form>
                                        @endif
                                        @if ((int) $emp->user_id !== (int) auth()->id())
                                            @php($isActive = $emp->employment_status === 'active')
                                            <form action="{{ route('admin.employee.toggle-status', $emp->id) }}" method="POST"
                                                style="display:inline;"
                                                onsubmit="return confirm(@js(($isActive ? 'Deactivate ' : 'Activate ').$emp->first_name.' '.$emp->last_name.($isActive ? "?\n\nThey will no longer be able to log in. Their attendance and leave history stay." : "?\n\nThey will be able to log in again.")));">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" class="btn-icon {{ $isActive ? 'danger' : '' }}"
                                                    title="{{ $isActive ? 'Deactivate (block login)' : 'Activate (allow login)' }}">
                                                    <i class="bi {{ $isActive ? 'bi-person-dash' : 'bi-person-check' }}"></i>
                                                </button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" style="text-align:center; padding: 20px;">No employees found.</td>
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

                <form class="form-glass" action="{{ route('admin.employee.store') }}" method="POST">
                    @csrf

                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="bi bi-person-plus" style="color:var(--primary);"></i> Add Employee
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>

                    <div class="modal-body">

                        {{-- Validation errors block --}}
                        @if ($errors->any())
                            <div class="alert alert-danger">
                                <ul class="mb-0">
                                    @foreach ($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        {{-- ✅ Device User Selection --}}
                        <div class="row">
                            <div class="col-12">
                                <div class="form-group">
                                    <label>Select from Device</label>
                                    <select id="device_user_select" class="form-control" onchange="fillFromDevice(this)">
                                        <option value="">-- Manually fill or Select From Device</option>
                                        @foreach ($deviceUsers as $du)
                                            <option value="{{ $du->uid }}" data-name="{{ $du->name }}">
                                                {{ $du->name }} (Device UID: {{ $du->uid }})
                                            </option>
                                        @endforeach
                                    </select>
                                    <input type="hidden" name="zkteco_uid" id="zkteco_uid_input"
                                        value="{{ old('zkteco_uid') }}">
                                    @if (count($deviceUsers) === 0)
                                        <small style="color:#c0392b;">No unassigned users found on the device.</small>
                                    @endif
                                </div>
                            </div>
                        </div>

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
                                    <input type="text" name="last_name" class="form-control"
                                        placeholder="e.g. Siddiqui" value="{{ old('last_name') }}" required>
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
                                    <input type="text" name="cnic" class="form-control"
                                        placeholder="42101-1234567-1" value="{{ old('cnic') }}" required>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Email</label>
                                    <input type="email" name="email" class="form-control"
                                        placeholder="employee@gems.edu" value="{{ old('email') }}" required autocomplete="off">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Password</label>
                                    <div style="position:relative;">
                                        <input type="password" name="password" id="add_password" class="form-control"
                                            placeholder="••••••••" required style="padding-right:40px;" autocomplete="new-password">
                                        <button type="button" onclick="togglePassword('add_password', this)"
                                            style="position:absolute; right:10px; top:50%; transform:translateY(-50%); background:none; border:none; cursor:pointer; color:var(--text-muted);">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="form-group" style="display:flex; align-items:center; gap:8px;">
                            <input type="checkbox" name="send_credentials" id="add_send_credentials" value="1"
                                style="width:auto;" @checked(old('send_credentials', true))>
                            <label for="add_send_credentials" style="margin:0;">
                                <i class="bi bi-envelope"></i> Email the portal link, email and this password to the employee
                            </label>
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
                                        @foreach ($departments as $dept)
                                            <option value="{{ $dept->id }}"
                                                {{ old('department_id') == $dept->id ? 'selected' : '' }}>
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
                                        @foreach ($roles as $role)
                                            <option value="{{ $role->id }}"
                                                {{ old('role_id') == $role->id ? 'selected' : '' }}>
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
                                        @foreach ($managers as $manager)
                                            <option value="{{ $manager->id }}"
                                                {{ old('reports_to_id') == $manager->id ? 'selected' : '' }}>
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
                                    <input type="date" name="joining_date" id="add_joining_date" class="form-control"
                                        value="{{ old('joining_date', date('Y-m-d')) }}" required
                                        onchange="defaultProbation(this.value, 'add_probation_until')">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Employment Type</label>
                                    <div style="display:flex; gap:8px;">
                                        <select name="employment_type" id="add_employment_type" class="form-control" style="flex:1;"
                                            onchange="toggleProbation('add_employment_type', 'add_probation_wrap')">
                                            <option value="permanent" @selected(old('employment_type', 'permanent') === 'permanent')>Permanent</option>
                                            <option value="probation" @selected(old('employment_type') === 'probation')>Probation</option>
                                        </select>
                                        <div id="add_probation_wrap" style="flex:1; display:none;">
                                            <input type="date" name="probation_until" id="add_probation_until" class="form-control" title="Probation until"
                                                value="{{ old('probation_until', date('Y-m-d', strtotime('+'.\App\Models\Employee::PROBATION_MONTHS.' months'))) }}">
                                        </div>
                                    </div>
                                    <small style="color:var(--text-muted);">Probation: no paid leave until the date (default {{ \App\Models\Employee::PROBATION_MONTHS }} months after joining)</small>
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
                                    <label>Shift</label>
                                    <select name="shift_id" class="form-control">
                                        <option value="">-- Use Company Default --</option>
                                        @foreach ($shifts as $shift)
                                            <option value="{{ $shift->id }}"
                                                {{ old('shift_id') == $shift->id ? 'selected' : '' }}>
                                                {{ $shift->name }}
                                                ({{ \Carbon\Carbon::parse($shift->start_time)->format('h:i A') }} -
                                                {{ \Carbon\Carbon::parse($shift->end_time)->format('h:i A') }})
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Grace Policy</label>
                                    <select name="grace_policy_id" class="form-control">
                                        <option value="">-- Use Company Default --</option>
                                        @foreach ($gracePolicies as $gp)
                                            <option value="{{ $gp->id }}"
                                                {{ old('grace_policy_id') == $gp->id ? 'selected' : '' }}>
                                                {{ $gp->name }} ({{ $gp->late_grace_minutes }} min late /
                                                {{ $gp->early_grace_minutes }} min early)
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>
                        {{-- Weekly schedule: fixed off days (own weekend) and fixed Work From Home days --}}
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Weekly Off Days <small style="font-weight:400; color:var(--text-muted);">(never absent on these)</small></label>
                                    <div class="weekday-checks">
                                        @foreach ($workingDays as $day)
                                            <label><input type="checkbox" name="off_days[]" value="{{ $day }}" {{ in_array($day, (array) old('off_days', [])) ? 'checked' : '' }}> {{ $day }}</label>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Fixed Work From Home Days <small style="font-weight:400; color:var(--text-muted);">(no request needed, portal check-in)</small></label>
                                    <div class="weekday-checks">
                                        @foreach ($workingDays as $day)
                                            <label><input type="checkbox" name="wfh_days[]" value="{{ $day }}" {{ in_array($day, (array) old('wfh_days', [])) ? 'checked' : '' }}> {{ $day }}</label>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Gender</label>
                                    <select name="gender" class="form-control">
                                        <option value="">Select Gender</option>
                                        <option value="male" {{ old('gender') == 'male' ? 'selected' : '' }}>Male
                                        </option>
                                        <option value="female" {{ old('gender') == 'female' ? 'selected' : '' }}>Female
                                        </option>
                                        <option value="other" {{ old('gender') == 'other' ? 'selected' : '' }}>Other
                                        </option>
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
                            No reporting authority set — requests routed to <strong>Hr</strong> (Administrator).
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

    <!-- Edit EMPLOYEE MODAL (Glassmorphism)        -->
    <div class="modal fade modal-glass" id="editEmployeeModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">

                <form class="form-glass" id="editForm" method="POST" action="">
                    @csrf
                    @method('PUT')

                    <input type="hidden" name="_employee_id_placeholder" id="edit_pk">

                    <div class="modal-header">
                        <h5 class="modal-title"><i class="bi bi-pencil" style="color:var(--primary);"></i> Edit Employee
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>

                    <div class="modal-body">

                        <div id="editErrorBox" class="alert alert-danger" style="display:none;"></div>

                        {{-- ✅ Device User Selection --}}
                        <div class="row">
                            <div class="col-12">
                                <div class="form-group">
                                    <label>Device User (Machine UID)</label>
                                    <select name="zkteco_uid" id="edit_zkteco_uid" class="form-control">
                                        <option value="">-- No Device User --</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>First Name</label>
                                    <input type="text" name="first_name" id="edit_first_name" class="form-control"
                                        required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Last Name</label>
                                    <input type="text" name="last_name" id="edit_last_name" class="form-control"
                                        required>
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
                                    <input type="text" name="cnic" id="edit_cnic" class="form-control" maxlength="15"
                                        required>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Email</label>
                                    <input type="email" name="email" id="edit_email" class="form-control" required autocomplete="off">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Password</label>
                                    <div style="position:relative;">
                                        <input type="password" name="password" id="edit_password" class="form-control"
                                            placeholder="Leave blank to keep current" style="padding-right:40px;"
                                            autocomplete="new-password">
                                        <button type="button" onclick="togglePassword('edit_password', this)"
                                            style="position:absolute; right:10px; top:50%; transform:translateY(-50%); background:none; border:none; cursor:pointer; color:var(--text-muted);">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="form-group" style="display:flex; align-items:center; gap:8px;">
                            <input type="checkbox" name="send_credentials" id="edit_send_credentials" value="1"
                                style="width:auto;">
                            <label for="edit_send_credentials" style="margin:0;">
                                <i class="bi bi-envelope"></i> If a new password is entered, email it to the employee
                            </label>
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
                                        @foreach ($departments as $dept)
                                            <option value="{{ $dept->id }}">{{ $dept->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Job Title</label>
                                    <input type="text" name="job_title" id="edit_job_title" class="form-control"
                                        required>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Role</label>
                                    <select name="role_id" id="edit_role" class="form-control" required>
                                        @foreach ($roles as $role)
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
                                        @foreach ($managers as $manager)
                                            <option value="{{ $manager->id }}">{{ $manager->first_name }}
                                                {{ $manager->last_name }}
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
                                    <input type="date" name="joining_date" id="edit_joining_date"
                                        class="form-control" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Employment Type</label>
                                    <div style="display:flex; gap:8px;">
                                        <select name="employment_type" id="edit_employment_type" class="form-control" style="flex:1;"
                                            onchange="toggleProbation('edit_employment_type', 'edit_probation_wrap')">
                                            <option value="permanent">Permanent</option>
                                            <option value="probation">Probation</option>
                                        </select>
                                        <div id="edit_probation_wrap" style="flex:1; display:none;">
                                            <input type="date" name="probation_until" id="edit_probation_until" class="form-control" title="Probation until">
                                        </div>
                                    </div>
                                    <small style="color:var(--text-muted);">Probation: no paid leave until the date</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Date of Birth</label>
                                    <input type="date" name="date_of_birth" id="edit_date_of_birth"
                                        class="form-control">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Shift</label>
                                    <select name="shift_id" id="edit_shift_id" class="form-control">
                                        <option value="">-- Use Company Default --</option>
                                        @foreach ($shifts as $shift)
                                            <option value="{{ $shift->id }}"
                                                {{ old('shift_id') == $shift->id ? 'selected' : '' }}>
                                                {{ $shift->name }}
                                                ({{ \Carbon\Carbon::parse($shift->start_time)->format('h:i A') }} -
                                                {{ \Carbon\Carbon::parse($shift->end_time)->format('h:i A') }})
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Grace Policy</label>
                                    <select name="grace_policy_id" id="edit_grace_policy_id" class="form-control">
                                        <option value="">-- Use Company Default --</option>
                                        @foreach ($gracePolicies as $gp)
                                            <option value="{{ $gp->id }}">{{ $gp->name }}
                                                ({{ $gp->late_grace_minutes }} min late / {{ $gp->early_grace_minutes }}
                                                min early)
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>
                        {{-- Weekly schedule: fixed off days (own weekend) and fixed Work From Home days --}}
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Weekly Off Days <small style="font-weight:400; color:var(--text-muted);">(never absent on these)</small></label>
                                    <div class="weekday-checks">
                                        @foreach ($workingDays as $day)
                                            <label><input type="checkbox" name="off_days[]" value="{{ $day }}" class="edit-off-day"> {{ $day }}</label>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Fixed Work From Home Days <small style="font-weight:400; color:var(--text-muted);">(no request needed, portal check-in)</small></label>
                                    <div class="weekday-checks">
                                        @foreach ($workingDays as $day)
                                            <label><input type="checkbox" name="wfh_days[]" value="{{ $day }}" class="edit-wfh-day"> {{ $day }}</label>
                                        @endforeach
                                    </div>
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

    <div class="modal fade modal-glass" id="importCsvModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="{{ route('admin.employee.import') }}" enctype="multipart/form-data">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-file-earmark-arrow-up" style="color:var(--primary);"></i> Import Employees (CSV)</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="form-group" style="margin-bottom:16px;">
                        <label class="form-label-sm">CSV File</label>
                        <input type="file" name="csv_file" class="form-control-custom" accept=".csv" required>
                    </div>
                    <div class="info-box">
                        <i class="bi bi-info-circle"></i>
                        Column order: <strong>First Name, Last Name, Email, Phone, CNIC, Department Name, Job Title, Joining Date</strong>. First row (headers) skip ho jayegi. Default password: <code>changeme123</code>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-outline-glass" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn-primary-glass"><i class="bi bi-upload"></i> Import</button>
                </div>
            </form>
        </div>
    </div>
</div>


    @if ($errors->any())
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                var modal = new bootstrap.Modal(document.getElementById('addEmployeeModal'));
                modal.show();
            });
        </script>
    @endif
@endsection

@push('scripts')
    <script>
        // ✅ Device se select karne par naam aur ID auto-fill karo (Add modal)
        function fillFromDevice(select) {
            const option = select.options[select.selectedIndex];
            const uid = option.value;
            const name = option.getAttribute('data-name') || '';

            document.getElementById('zkteco_uid_input').value = uid;

            if (uid) {
                const parts = name.trim().split(' ');
                const firstName = parts[0] || '';
                const lastName = parts.length > 1 ? parts.slice(1).join(' ') : '';

                document.querySelector('#addEmployeeModal [name="first_name"]').value = firstName;
                document.querySelector('#addEmployeeModal [name="last_name"]').value = lastName;
                document.querySelector('#addEmployeeModal [name="employee_id"]').value = 'UID-' + uid;
            } else {
                document.querySelector('#addEmployeeModal [name="first_name"]').value = '';
                document.querySelector('#addEmployeeModal [name="last_name"]').value = '';
            }
        }

        // Cleanup stray backdrops whenever any modal closes
        document.addEventListener('hidden.bs.modal', function() {
            document.body.classList.remove('modal-open');
            document.body.style.removeProperty('padding-right');
            document.querySelectorAll('.modal-backdrop').forEach(el => el.remove());
        });

        // Extra safety: before opening a modal, clear any leftover backdrops
        document.addEventListener('show.bs.modal', function() {
            document.querySelectorAll('.modal-backdrop').forEach(el => el.remove());
        });

        function togglePassword(inputId, btn) {
            const input = document.getElementById(inputId);
            const icon = btn.querySelector('i');
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('bi-eye');
                icon.classList.add('bi-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('bi-eye-slash');
                icon.classList.add('bi-eye');
            }
        }
    </script>
@endpush

<script>
    // Probation defaults to 3 months after the joining date (Admin may change it)
    function defaultProbation(joiningDate, targetId) {
        if (!joiningDate) return;
        const d = new Date(joiningDate + 'T00:00:00');
        d.setMonth(d.getMonth() + {{ \App\Models\Employee::PROBATION_MONTHS }});
        document.getElementById(targetId).value = d.toISOString().substring(0, 10);
    }

    // The probation end date is only shown (and required) for Employment Type = Probation
    function toggleProbation(selectId, wrapId) {
        const onProbation = document.getElementById(selectId).value === 'probation';
        const wrap = document.getElementById(wrapId);
        wrap.style.display = onProbation ? 'block' : 'none';
        wrap.querySelector('input').required = onProbation;
    }

    document.addEventListener('DOMContentLoaded', function () {
        toggleProbation('add_employment_type', 'add_probation_wrap');
    });

    function openEditModal(id) {
        fetch(`/admin/employees/${id}/edit`, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(res => res.json())
            .then(data => {
                const emp = data.employee;

                // ✅ Device users dropdown populate karo
                const zkSelect = document.getElementById('edit_zkteco_uid');
                zkSelect.innerHTML = '<option value="">-- NO user Found in this Device --</option>';
                (data.deviceUsers || []).forEach(du => {
                    const opt = document.createElement('option');
                    opt.value = du.uid;
                    opt.textContent = `${du.name} (Device UID: ${du.uid})`;
                    if (emp.zkteco_uid == du.uid) opt.selected = true;
                    zkSelect.appendChild(opt);
                });

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
                document.getElementById('edit_joining_date').value = emp.joining_date ? emp.joining_date.substring(
                    0, 10) : '';
                // Employment type: on probation when a probation end date is set
                document.getElementById('edit_employment_type').value = emp.probation_until ? 'probation' : 'permanent';
                document.getElementById('edit_probation_until').value = emp.probation_until ? emp.probation_until.substring(0, 10) : '';
                toggleProbation('edit_employment_type', 'edit_probation_wrap');
                document.getElementById('edit_date_of_birth').value = emp.date_of_birth ? emp.date_of_birth
                    .substring(0, 10) : '';
                document.getElementById('edit_gender').value = emp.gender ?? '';
                document.getElementById('edit_employment_status').value = emp.employment_status ?? 'active';
                document.getElementById('edit_address').value = emp.address ?? '';

                document.getElementById('editErrorBox').style.display = 'none';
                // Keep the current shift and grace policy selected, otherwise saving the form clears them
                document.getElementById('edit_shift_id').value = emp.shift_id ?? '';
                document.getElementById('edit_grace_policy_id').value = emp.grace_policy_id ?? '';
                // Weekly off days and fixed Work From Home days ("Sat" / "Mon,Fri")
                const offDays = (emp.off_days || '').split(',');
                const wfhDays = (emp.wfh_days || '').split(',');
                document.querySelectorAll('.edit-off-day').forEach(cb => cb.checked = offDays.includes(cb.value));
                document.querySelectorAll('.edit-wfh-day').forEach(cb => cb.checked = wfhDays.includes(cb.value));
                var modal = new bootstrap.Modal(document.getElementById('editEmployeeModal'));
                modal.show();
            })
            .catch(err => {
                console.error(err);
                alert('Employee data load nahi ho saka. Console check karo.');
            });
    }
</script>
