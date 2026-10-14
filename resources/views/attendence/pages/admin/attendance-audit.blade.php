@extends('attendence.layouts.app')

@section('title', 'Attendance Audit')
@section('page-title', 'Attendance Audit')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/attendence-audit.css') }}">
@endpush

@section('content')


    <!-- FILTER BAR  -->
    <div class="row">
        <div class="col-12">
            <div class="glass-card">
                <form method="GET" action="{{ route('admin.attendance.audit') }}">
                    <div class="filter-bar">

                        <div class="form-group">
                            <label>From</label>
                            <input type="date" class="form-control" name="from" value="{{ $from }}" min="{{ \App\Models\Employee::ATTENDANCE_START_DATE }}">
                        </div>

                        <div class="form-group">
                            <label>To</label>
                            <input type="date" class="form-control" name="to" value="{{ $to }}" min="{{ \App\Models\Employee::ATTENDANCE_START_DATE }}">
                        </div>

                        <div class="form-group">
                            <label>Department</label>
                            <select class="form-control" name="department" id="auditDepartment">
                                <option value="all" {{ $department == 'all' ? 'selected' : '' }}>All departments</option>
                                @foreach($departments as $dept)
                                    <option value="{{ $dept->name }}" {{ $department == $dept->name ? 'selected' : '' }}>
                                        {{ $dept->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="form-group" style="flex: 1.5;">
                            <label>Employee</label>
                            <select class="form-control" name="employee" id="auditEmployee">
                                <option value="all" @selected($employee === 'all')>All employees</option>
                                @foreach ($employees as $emp)
                                    <option value="{{ $emp->id }}" data-department="{{ $emp->department->name ?? '' }}"
                                        @selected((string) $employee === (string) $emp->id)>
                                        {{ $emp->full_name }} ({{ $emp->employee_id }})
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Status</label>
                            <select class="form-control" name="status">
                                <option value="all" {{ $status == 'all' ? 'selected' : '' }}>All statuses</option>
                                @foreach (['Present', 'Late', 'Early Out', 'Half Day', 'Missing Punch', 'Absent', 'On Leave', 'Holiday', 'Weekend'] as $option)
                                    <option value="{{ $option }}" @selected($status === $option)>{{ $option }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="form-group" style="flex: 1.5;">
                            <label>Search</label>
                            <input type="text" class="form-control" name="search" placeholder="Name or Employee ID"
                                value="{{ $search }}">
                        </div>

                        <div class="btn-group">
                            <button type="submit" class="btn-primary-glass">
                                <i class="bi bi-funnel"></i> Filter
                            </button>
                            <a href="{{ route('admin.attendance.audit') }}" class="btn-outline-glass">
                                <i class="bi bi-arrow-counterclockwise"></i> Clear
                            </a>

                        </div>

                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- TABLE WITH STATIC DUMMY DATA    -->
    <div class="row" style="margin-top: 16px;">
        <div class="col-12">
            <div class="glass-card">

                <div class="section-header">
                    <div>
                        <h6>Attendance Audit</h6>
                        <span class="subtitle">{{ count($rows) }} rows</span>
                    </div>
                    <div class="btn-group">
                        <a href="{{ route('admin.attendance.audit.export', ['from' => $from, 'to' => $to, 'department' => $department, 'employee' => $employee, 'status' => $status, 'search' => $search]) }}"
                            class="btn-primary-glass">
                            <i class="bi bi-download"></i> Export
                        </a>
                    </div>

                </div>

                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Emp ID</th>
                                <th>Employee</th>
                                <th>Dept</th>
                                <th>Designation</th>
                                <th>Date</th>
                                <th>Shift</th>
                                <th>Sch In</th>
                                <th>Sch Out</th>
                                <th>First In</th>
                                <th>Last Out</th>
                                <th>Total Hrs</th>
                                <th>Late (min)</th>
                                <th>Early Leave</th>
                                <th>OT (hrs)</th>
                                <th>Status</th>
                                <th>Verification</th>
                                <th>Remarks</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($rows as $row)
                                <tr>
                                    <td>{{ $row['#'] }}</td>
                                    <td><strong>{{ $row['emp_id'] }}</strong></td>
                                    <td>{{ $row['employee'] }}</td>
                                    <td>{{ $row['dept'] }}</td>
                                    <td>{{ $row['designation'] }}</td>
                                    <td>{{ $row['date'] }}</td>
                                    <td>{{ $row['shift'] }}</td>
                                    <td>{{ $row['sch_in'] }}</td>
                                    <td>{{ $row['sch_out'] }}</td>
                                    <td>{{ $row['first_in'] }}</td>
                                    <td>{{ $row['last_out'] }}</td>
                                    <td>{{ $row['total_hrs'] }}</td>
                                    <td>{{ $row['late_min'] }}</td>
                                    <td>{{ $row['early_min'] }}</td>
                                    <td>{{ $row['ot_hrs'] }}</td>
                                    <td><span
                                            class="status-badge {{ \Illuminate\Support\Str::slug($row['status']) }}">{{ $row['status'] }}</span>
                                    </td>
                                    <td>{{ $row['verification'] }}</td>
                                    <td>{{ $row['remarks'] }}</td>
                                    <td>
                                        @if ($row['status'] === 'Absent')
                                            <button type="button" class="btn-outline-glass" style="padding:4px 10px; font-size:12px; white-space:nowrap;"
                                                onclick="openInformAbsent({{ $row['employee_key'] }}, @js($row['employee']), '{{ $row['date_iso'] }}', @js($row['date']))">
                                                <i class="bi bi-envelope"></i> Inform
                                            </button>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="19" style="text-align:center; padding:20px;">No Record Found</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

            </div>
        </div>
    </div>

    <!-- INFORM ABSENT EMPLOYEE -->
    <div class="modal fade modal-glass" id="informAbsentModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="POST" action="{{ route('admin.attendance.audit.inform-absent') }}">
                    @csrf
                    <input type="hidden" name="employee_id" id="informEmployeeId">
                    <input type="hidden" name="date" id="informDate">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="bi bi-envelope"></i> Inform <span id="informEmployeeName"></span></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label>Message (sent by email and portal notification)</label>
                            <textarea name="message" id="informMessage" class="form-control" rows="4" maxlength="1000" required></textarea>
                        </div>
                        <div class="form-group" style="display:flex; align-items:center; gap:8px; margin-top:10px;">
                            <input type="checkbox" name="copy_manager" id="informCopyManager" value="1" style="width:auto;" checked>
                            <label for="informCopyManager" style="margin:0;">Also inform their Line Manager / Manager</label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn-outline-glass" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn-primary-glass"><i class="bi bi-send"></i> Send</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

@endsection

@push('scripts')
<script>
    function openInformAbsent(employeeId, name, dateIso, dateLabel) {
        document.getElementById('informEmployeeId').value = employeeId;
        document.getElementById('informDate').value = dateIso;
        document.getElementById('informEmployeeName').textContent = name;
        document.getElementById('informMessage').value =
            'You were marked absent on ' + dateLabel + ' (no attendance punch and no approved leave). ' +
            'If this is a mistake, please submit a correction request or contact Admin.';
        new bootstrap.Modal(document.getElementById('informAbsentModal')).show();
    }

    // Employee dropdown shows only the chosen department's employees
    (function () {
        const department = document.getElementById('auditDepartment');
        const employee = document.getElementById('auditEmployee');

        function filterEmployees() {
            const dept = department.value;
            employee.querySelectorAll('option[data-department]').forEach(function (option) {
                const visible = dept === 'all' || option.dataset.department === dept;
                option.hidden = !visible;
                option.disabled = !visible;
            });
            if (employee.selectedOptions[0]?.disabled) {
                employee.value = 'all';
            }
        }

        department.addEventListener('change', filterEmployees);
        filterEmployees();
    })();
</script>
@endpush
