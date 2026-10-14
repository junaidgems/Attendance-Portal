@extends('attendence.layouts.app')

@section('title', 'All Attendance')
@section('page-title', 'All Attendance')
<link rel="stylesheet" href="{{ asset('css/attendance.css') }}">



@section('content')

    <!-- FILTER BAR                                -->

    <div class="row">
        <div class="col-12">
            <div class="glass-card">
                <form method="GET" action="{{ route('admin.attendance') }}" class="filter-bar">

                    <div class="form-group">
                        <label>Date</label>
                        <input type="date" class="form-control" name="date" value="{{ $date }}">
                    </div>

                    <div class="form-group">
                        <label>Department</label>
                        <select class="form-control" name="department">
                            <option value="all">All Departments</option>
                            @foreach ($departments as $dept)
                                <option value="{{ $dept->name }}"
                                    {{ $filters['department'] == $dept->name ? 'selected' : '' }}>
                                    {{ $dept->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Status</label>
                        <select class="form-control" name="status">
                            <option value="all">All Status</option>
                            @foreach (['Present', 'Late', 'Early Out', 'Half Day', 'Missing Punch'] as $option)
                                <option value="{{ $option }}" @selected($filters['status'] === $option)>{{ $option }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-group" style="flex: 2;">
                        <label>Search</label>
                        <input type="text" class="form-control" name="search" placeholder="Name or ID"
                            value="{{ $filters['search'] }}">
                    </div>

                    <div class="btn-group">
                        <button type="submit" class="btn-primary-glass">
                            <i class="bi bi-funnel"></i> Filter
                        </button>
                        <button type="button" class="btn-primary-glass" data-bs-toggle="modal"
                            data-bs-target="#markAttendanceModal">
                            <i class="bi bi-check2-circle"></i> Mark Attendance
                        </button>
                        <a href="{{ route('admin.attendance') }}" class="btn-outline-glass">
                            <i class="bi bi-arrow-counterclockwise"></i>
                        </a>
                    </div>

                </form>
            </div>
        </div>
    </div>


    <!-- NON-WORKING DAY ALERT                     -->

    <div class="row" style="margin-top: 16px;">
        <div class="col-12">
            <div class="alert-glass">
                <i class="bi bi-info-circle"></i>
                <span id="nonWorkingMsg">Showing attendance for
                    {{ \Carbon\Carbon::parse($date)->format('D d M Y') }}</span>
            </div>
        </div>
    </div>


    <!-- TABLE                                     -->

    <div class="row" style="margin-top: 16px;">
        <div class="col-12">
            <div class="glass-card" style="padding: 0; overflow: hidden;">

                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>ID</th>
                                <th>Department</th>
                                <th>Status</th>
                                <th>IN</th>
                                <th>OUT</th>
                                <th>NET</th>
                                <th>Timeline</th>
                            </tr>
                        </thead>
                        <tbody id="attendanceTableBody">
                            @forelse($attendances as $att)
                                <tr data-name="{{ strtolower($att['name']) }}" data-id="{{ strtolower($att['id']) }}"
                                    data-department="{{ $att['department'] }}" data-status="{{ $att['status'] }}">
                                    <td>
                                        <span class="employee-name">{{ $att['name'] }}</span>
                                    </td>
                                    <td><span class="employee-id">{{ $att['id'] }}</span></td>
                                    <td class="dept-cell">{{ $att['department'] }}</td>
                                    <td>
                                        <span class="status-badge {{ \Illuminate\Support\Str::slug($att['status']) }}">
                                            {{ $att['status'] }}
                                        </span>
                                        @if ($att['wfh'])
                                            <br><span style="font-size:11px; color:#2563eb;"><i class="bi bi-house-door"></i> {{ $att['wfh'] }}</span>
                                        @endif
                                        @if ($att['waived'])
                                            <br><span style="font-size:11px; color:#16a34a;"><i class="bi bi-shield-check"></i> {{ $att['waived'] }}</span>
                                        @endif
                                        @if ($att['note'])
                                            <br><span style="font-size:11px; color:var(--text-muted);"><i class="bi bi-info-circle"></i> {{ $att['note'] }}</span>
                                        @endif
                                    </td>
                                    <td>{{ $att['in'] }}</td>
                                    <td>{{ $att['out'] }}</td>
                                    <td>{{ $att['net'] }}</td>
                                    <td>
                                        @if ($att['timeline'] == 'On Time')
                                            <span class="timeline-on-time"><i class="bi bi-check-circle"></i>
                                                {{ $att['timeline'] }}</span>
                                        @elseif(str_contains($att['timeline'], 'Late'))
                                            <span class="timeline-late"><i class="bi bi-clock"></i>
                                                {{ $att['timeline'] }}</span>
                                        @elseif($att['timeline'] == 'No Punch')
                                            <span class="timeline-absent"><i class="bi bi-x-circle"></i>
                                                {{ $att['timeline'] }}</span>
                                        @elseif($att['timeline'] == 'Approved Leave')
                                            <span class="timeline-leave"><i class="bi bi-calendar-check"></i>
                                                {{ $att['timeline'] }}</span>
                                        @else
                                            <span>{{ $att['timeline'] }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr id="emptyRow">
                                    <td colspan="8">
                                        <div class="empty-state">
                                            <i class="bi bi-inbox"></i>
                                            <p>No matching records for this day.</p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($records->hasPages())
                    <div class="attendance-pagination">
                        <span>Showing {{ $records->firstItem() }}–{{ $records->lastItem() }} of {{ $records->total() }}</span>
                        {{ $records->onEachSide(1)->links('pagination::bootstrap-4') }}
                    </div>
                @endif

                <div id="noResultsMessage"
                    style="display: none; padding: 30px 20px; text-align: center; color: var(--text-muted);">
                    <i class="bi bi-inbox" style="font-size: 36px; opacity: 0.3; display: block; margin-bottom: 8px;"></i>
                    <p>No records match your filters.</p>
                </div>

            </div>
        </div>
    </div>


    <!-- MARK ATTENDANCE MODAL   -->

    <div class="modal fade modal-glass" id="markAttendanceModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-pencil" style="color:var(--primary);"></i> Mark / Edit
                        Attendance</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form class="form-glass" id="attendanceForm" method="POST"
                        action="{{ route('admin.attendance.mark') }}">
                        @csrf

                        <div class="form-group">
                            <label>Employee</label>
                            <select class="form-control" name="employee_id" required>
                                <option value="">Select Employee</option>
                                @foreach ($allEmployees as $emp)
                                    <option value="{{ $emp->id }}">{{ $emp->first_name }} {{ $emp->last_name }} ·
                                        {{ $emp->employee_id }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Date</label>
                            <input type="date" class="form-control" name="date" id="modalDate"
                                value="{{ $date ?: now()->format('Y-m-d') }}" required>
                        </div>

                        <div class="form-group">
                            <label>Day Type</label>
                            <select class="form-control" name="day_type" id="modalDayType">
                                <option value="present">Present (with punches)</option>
                                <option value="wfh">Work From Home</option>
                                <option value="onduty">On Duty / Official Tour</option>
                                <option value="absent">Absent</option>
                            </select>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Punch In</label>
                                    <input type="time" class="form-control" name="punch_in" id="modalPunchIn"
                                        value="09:00">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Punch Out</label>
                                    <input type="time" class="form-control" name="punch_out" id="modalPunchOut"
                                        value="18:00">
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Reason / Note</label>
                            <textarea class="form-control" name="note" rows="2"
                                placeholder="e.g. Missed punch, on-site meeting, approved WFH..."></textarea>
                        </div>

                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-cancel" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" form="attendanceForm" class="btn-save">
                        <i class="bi bi-check2"></i> Save Attendance
                    </button>
                </div>
            </div>
        </div>
    </div>

@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {

            // ===== DOM Elements =====
            const filterDate = document.getElementById('filterDate');
            const filterDepartment = document.getElementById('filterDepartment');
            const filterStatus = document.getElementById('filterStatus');
            const filterSearch = document.getElementById('filterSearch');
            const resetBtn = document.getElementById('resetFilterBtn');
            const tableRows = document.querySelectorAll('#attendanceTableBody tr:not(#emptyRow)');
            const emptyRow = document.getElementById('emptyRow');
            const noResultsMsg = document.getElementById('noResultsMessage');
            document.getElementById('edit_grace_policy_id').value = emp.grace_policy_id ?? '';
            // ===== Filter Function =====
            function filterTable() {
                const dept = filterDepartment.value.toLowerCase();
                const status = filterStatus.value.toLowerCase();
                const search = filterSearch.value.toLowerCase().trim();

                let visibleCount = 0;

                tableRows.forEach(row => {
                    const rowDept = row.dataset.department ? row.dataset.department.toLowerCase() : '';
                    const rowStatus = row.dataset.status ? row.dataset.status.toLowerCase() : '';
                    const rowName = row.dataset.name || '';
                    const rowId = row.dataset.id || '';

                    let deptMatch = (dept === 'all' || rowDept === dept);
                    let statusMatch = (status === 'all' || rowStatus === status);
                    let searchMatch = (search === '' || rowName.includes(search) || rowId.includes(search));

                    if (deptMatch && statusMatch && searchMatch) {
                        row.style.display = '';
                        visibleCount++;
                    } else {
                        row.style.display = 'none';
                    }
                });

                if (visibleCount === 0) {
                    if (emptyRow) emptyRow.style.display = 'none';
                    if (noResultsMsg) noResultsMsg.style.display = 'block';
                } else {
                    if (emptyRow) emptyRow.style.display = '';
                    if (noResultsMsg) noResultsMsg.style.display = 'none';
                }
            }

            // ===== Event Listeners =====
            filterDepartment.addEventListener('change', filterTable);
            filterStatus.addEventListener('change', filterTable);
            filterSearch.addEventListener('keyup', filterTable);
            filterDate.addEventListener('change', function() {
                const date = this.value;
                window.location.href = "{{ route('admin.attendance') }}?date=" + date;
            });

            // ===== Reset Button =====
            if (resetBtn) {
                resetBtn.addEventListener('click', function() {
                    filterDepartment.value = 'all';
                    filterStatus.value = 'all';
                    filterSearch.value = '';
                    filterDate.value = '2026-07-18';
                    document.getElementById('nonWorkingMsg').textContent =
                        'Sat 18 Jul is a non-working day under the current policy.';
                    document.getElementById('modalDate').value = '2026-07-18';
                    filterTable();
                });
            }

            // ===== Mark Attendance Modal - Pre-fill Date =====
            document.addEventListener('DOMContentLoaded', function() {
                // Mark Attendance Modal - Pre-fill Date jab modal khule
                const markModal = document.getElementById('markAttendanceModal');
                if (markModal) {
                    markModal.addEventListener('show.bs.modal', function() {
                        const dateInput = document.querySelector('input[name="date"]');
                        if (dateInput && !dateInput.value) {
                            dateInput.value = new Date().toISOString().split('T')[0];
                        }
                    });
                }
            });

            // ===== Save Attendance =====
            document.getElementById('saveAttendanceBtn').addEventListener('click', function() {
                const employee = document.getElementById('modalEmployee').value;
                const date = document.getElementById('modalDate').value;
                const punchIn = document.getElementById('modalPunchIn').value;
                const punchOut = document.getElementById('modalPunchOut').value;
                const note = document.getElementById('modalNote').value;

                if (!employee) {
                    alert('Please select an employee.');
                    return;
                }

                alert('Attendance saved for: ' + employee + '\nDate: ' + date + '\nPunch In: ' + punchIn +
                    '\nPunch Out: ' + punchOut + '\nNote: ' + note);

                // Close modal
                const modal = bootstrap.Modal.getInstance(document.getElementById('markAttendanceModal'));
                if (modal) modal.hide();
            });

            // ===== Export CSV =====
            document.getElementById('exportCsvBtn').addEventListener('click', function() {
                alert('Export CSV functionality will be implemented.');
            });

            // ===== Initial Filter =====
            filterTable();

        });
    </script>
@endpush
