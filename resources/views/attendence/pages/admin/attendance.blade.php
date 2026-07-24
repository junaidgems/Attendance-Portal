@extends('attendence.layouts.app')

@section('title', 'All Attendance')
@section('page-title', 'All Attendance')
<link rel="stylesheet" href="{{ asset('css/attendance.css') }}">



@section('content')

    <!-- FILTER BAR                                -->

    <div class="row">
        <div class="col-12">
            <div class="glass-card">
                <div class="filter-bar">

                    <div class="form-group">
                        <label>Date</label>
                        <input type="date" class="form-control" id="filterDate" value="2026-07-18">
                    </div>

                    <div class="form-group">
                        <label>Department</label>
                        <select class="form-control" id="filterDepartment">
                            <option value="all">All Departments</option>
                            <option value="ICT">ICT</option>
                            <option value="Engineering">Engineering</option>
                            <option value="HR">HR</option>
                            <option value="Finance">Finance</option>
                            <option value="Marketing">Marketing</option>
                            <option value="Operations">Operations</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Status</label>
                        <select class="form-control" id="filterStatus">
                            <option value="all">All Status</option>
                            <option value="Present">Present</option>
                            <option value="Absent">Absent</option>
                            <option value="Late">Late</option>
                            <option value="Leave">Leave</option>
                        </select>
                    </div>

                    <div class="form-group" style="flex: 2;">
                        <label>Search</label>
                        <input type="text" class="form-control" id="filterSearch" placeholder="Name or ID">
                    </div>

                    <div class="btn-group">
                        <button class="btn-primary-glass" data-bs-toggle="modal" data-bs-target="#markAttendanceModal">
                            <i class="bi bi-check2-circle"></i> Mark Attendance
                        </button>
                        <button class="btn-outline-glass" id="exportCsvBtn">
                            <i class="bi bi-download"></i> Export CSV
                        </button>
                        <button class="btn-outline-glass" id="resetFilterBtn">
                            <i class="bi bi-arrow-counterclockwise"></i>
                        </button>
                    </div>

                </div>
            </div>
        </div>
    </div>


    <!-- NON-WORKING DAY ALERT                     -->

    <div class="row" style="margin-top: 16px;">
        <div class="col-12">
            <div class="alert-glass">
                <i class="bi bi-info-circle"></i>
                <span id="nonWorkingMsg">Sat 18 Jul is a non-working day under the current policy.</span>
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
                                        <span class="status-badge {{ strtolower($att['status']) }}">
                                            {{ $att['status'] }}
                                        </span>
                                    </td>
                                    <td>{{ $att['in'] }}</td>
                                    <td>{{ $att['out'] }}</td>
                                    <td>{{ $att['net'] }}</td>
                                    <td>
                                        @if($att['timeline'] == 'On Time')
                                            <span class="timeline-on-time"><i class="bi bi-check-circle"></i>
                                                {{ $att['timeline'] }}</span>
                                        @elseif(str_contains($att['timeline'], 'Late'))
                                            <span class="timeline-late"><i class="bi bi-clock"></i> {{ $att['timeline'] }}</span>
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
                    <form class="form-glass" id="attendanceForm">

                        <!-- Employee -->
                        <div class="form-group">
                            <label>Employee</label>
                            <select class="form-control" id="modalEmployee">
                                <option value="">Select Employee</option>
                                <option value="Faraz Siddiqui">Faraz Siddiqui · GP-1001</option>
                                <option value="John Doe">John Doe · GP-1002</option>
                                <option value="Jane Smith">Jane Smith · GP-1003</option>
                                <option value="Mike Johnson">Mike Johnson · GP-1004</option>
                                <option value="Sarah Lee">Sarah Lee · GP-1005</option>
                                <option value="David Khan">David Khan · GP-1006</option>
                            </select>
                        </div>

                        <!-- Date -->
                        <div class="form-group">
                            <label>Date</label>
                            <input type="date" class="form-control" id="modalDate" value="2026-07-18">
                        </div>

                        <!-- Day Type — Select (Fixed) -->
                        <div class="form-group">
                            <label>Day Type</label>
                            <select class="form-control" id="modalDayType">
                                <option value="present">Present (with punches)</option>
                                <option value="wfh">Work From Home</option>
                                <option value="onduty">On Duty / Official Tour</option>
                                <option value="absent">Absent</option>
                            </select>
                        </div>

                        <!-- Punch In / Punch Out -->
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Punch In</label>
                                    <input type="time" class="form-control" id="modalPunchIn" value="09:00">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Punch Out</label>
                                    <input type="time" class="form-control" id="modalPunchOut" value="18:00">
                                </div>
                            </div>
                        </div>

                        <!-- Reason / Note -->
                        <div class="form-group">
                            <label>Reason / Note</label>
                            <textarea class="form-control" id="modalNote" rows="2"
                                placeholder="e.g. Missed punch, on-site meeting, approved WFH..."></textarea>
                        </div>

                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-cancel" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn-save" id="saveAttendanceBtn">
                        <i class="bi bi-check2"></i> Save Attendance
                    </button>
                </div>
            </div>
        </div>
    </div>

@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {

            // ===== DOM Elements =====
            const filterDate = document.getElementById('filterDate');
            const filterDepartment = document.getElementById('filterDepartment');
            const filterStatus = document.getElementById('filterStatus');
            const filterSearch = document.getElementById('filterSearch');
            const resetBtn = document.getElementById('resetFilterBtn');
            const tableRows = document.querySelectorAll('#attendanceTableBody tr:not(#emptyRow)');
            const emptyRow = document.getElementById('emptyRow');
            const noResultsMsg = document.getElementById('noResultsMessage');

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
            filterDate.addEventListener('change', function () {
                const date = this.value;
                document.getElementById('nonWorkingMsg').textContent =
                    date + ' — Attendance records for selected date.';
                // Update modal date also
                document.getElementById('modalDate').value = date;
                filterTable();
            });

            // ===== Reset Button =====
            if (resetBtn) {
                resetBtn.addEventListener('click', function () {
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
            document.getElementById('markAttendanceModal').addEventListener('show.bs.modal', function () {
                const date = document.getElementById('filterDate').value;
                document.getElementById('modalDate').value = date;
            });

            // ===== Save Attendance =====
            document.getElementById('saveAttendanceBtn').addEventListener('click', function () {
                const employee = document.getElementById('modalEmployee').value;
                const date = document.getElementById('modalDate').value;
                const punchIn = document.getElementById('modalPunchIn').value;
                const punchOut = document.getElementById('modalPunchOut').value;
                const note = document.getElementById('modalNote').value;

                if (!employee) {
                    alert('Please select an employee.');
                    return;
                }

                alert('Attendance saved for: ' + employee + '\nDate: ' + date + '\nPunch In: ' + punchIn + '\nPunch Out: ' + punchOut + '\nNote: ' + note);

                // Close modal
                const modal = bootstrap.Modal.getInstance(document.getElementById('markAttendanceModal'));
                if (modal) modal.hide();
            });

            // ===== Export CSV =====
            document.getElementById('exportCsvBtn').addEventListener('click', function () {
                alert('Export CSV functionality will be implemented.');
            });

            // ===== Initial Filter =====
            filterTable();

        });
    </script>
@endpush