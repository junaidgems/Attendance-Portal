@extends('attendence.layouts.app')

@section('title', 'Leave Management')
@section('page-title', 'Leave Management')

@push('styles')
 <link rel="stylesheet" href="{{ asset('css/leave.css') }}">
@endpush

@section('content')

    <!-- LEAVE TYPES  -->
    <div class="row">
        <div class="col-12">
            <div class="glass-card">

                <div class="section-header">
                    <div>
                        <h6>Leave Types</h6>
                        <span class="subtitle">Create, edit &amp; set day allocations</span>
                    </div>
                    <button class="btn-primary-glass" data-bs-toggle="modal" data-bs-target="#addLeaveTypeModal">
                        <i class="bi bi-plus-circle"></i> Add Leave Type
                    </button>
                </div>

                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Leave Type</th>
                                <th>Days / Year</th>
                                <th>In Use</th>
                                <th style="text-align: right;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($leaveTypes as $type)
                                <tr>
                                    <td>
                                        <span class="leave-color-dot" style="background:{{ $type['color'] }};"></span>
                                        {{ $type['name'] }}
                                    </td>
                                    <td>{{ $type['days'] }}</td>
                                    <td>{{ $type['in_use'] }} requests</td>
                                    <td style="text-align: right; white-space: nowrap;">
                                        <button class="btn-icon"
                                            onclick="editLeaveType({{ $type['id'] }}, '{{ $type['name'] }}', {{ $type['days'] }})">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <button class="btn-icon danger" onclick="deleteLeaveType({{ $type['id'] }})">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4">
                                        <div class="empty-state">
                                            <i class="bi bi-calendar2-plus"></i>
                                            <p>No leave types added yet.</p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

            </div>
        </div>
    </div>

    <!-- LEAVE REQUESTS — TABLE VIEW  -->
    <div class="row" style="margin-top: 20px;">
        <div class="col-12">
            <div class="glass-card">

                <div class="section-header">
                    <div>
                        <h6>Leave Requests</h6>
                        <span class="subtitle">{{ count($leaveRequests) }} requests</span>
                    </div>
                </div>

                <!-- ===== BOOTSTRAP TABS ===== -->
                <ul class="nav nav-tabs nav-tabs-custom" id="leaveRequestTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="pending-tab" data-bs-toggle="tab" data-bs-target="#pending"
                            type="button" role="tab">
                            Pending
                            <span
                                class="badge-count">{{ count(array_filter($leaveRequests, fn($r) => $r['status'] == 'pending')) }}</span>
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="approved-tab" data-bs-toggle="tab" data-bs-target="#approved"
                            type="button" role="tab">
                            Approved
                            <span
                                class="badge-count">{{ count(array_filter($leaveRequests, fn($r) => $r['status'] == 'approved')) }}</span>
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="rejected-tab" data-bs-toggle="tab" data-bs-target="#rejected"
                            type="button" role="tab">
                            Rejected
                            <span
                                class="badge-count">{{ count(array_filter($leaveRequests, fn($r) => $r['status'] == 'rejected')) }}</span>
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="all-tab" data-bs-toggle="tab" data-bs-target="#all" type="button"
                            role="tab">
                            All
                            <span class="badge-count">{{ count($leaveRequests) }}</span>
                        </button>
                    </li>
                </ul>

                <!-- ===== TAB CONTENT ===== -->
                <div class="tab-content">

                    <!-- PENDING -->
                    <div class="tab-pane fade show active" id="pending" role="tabpanel">
                        @php $pending = array_filter($leaveRequests, fn($r) => $r['status'] == 'pending'); @endphp
                        @if(count($pending) > 0)
                            <div class="table-wrap">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Employee</th>
                                            <th>Type</th>
                                            <th>From</th>
                                            <th>To</th>
                                            <th>Days</th>
                                            <th>Status</th>
                                            <th style="text-align: right;">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($pending as $req)
                                            <tr>
                                                <td><strong>{{ $req['employee'] }}</strong></td>
                                                <td>{{ $req['type'] }}</td>
                                                <td>{{ \Carbon\Carbon::parse($req['from'])->format('d M Y') }}</td>
                                                <td>{{ \Carbon\Carbon::parse($req['to'])->format('d M Y') }}</td>
                                                <td>{{ $req['days'] }}</td>
                                                <td><span class="status-badge-sm pending">Pending</span></td>
                                                <td style="text-align: right; white-space: nowrap;">
                                                    <button class="btn-outline-glass" style="color:#22c55e; border-color:#22c55e;">
                                                        <i class="bi bi-check2"></i>
                                                    </button>
                                                    <button class="btn-outline-glass danger">
                                                        <i class="bi bi-x"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <div class="empty-state">
                                <i class="bi bi-inbox"></i>
                                <p>No pending leave requests.</p>
                            </div>
                        @endif
                    </div>

                    <!-- APPROVED -->
                    <div class="tab-pane fade" id="approved" role="tabpanel">
                        @php $approved = array_filter($leaveRequests, fn($r) => $r['status'] == 'approved'); @endphp
                        @if(count($approved) > 0)
                            <div class="table-wrap">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Employee</th>
                                            <th>Type</th>
                                            <th>From</th>
                                            <th>To</th>
                                            <th>Days</th>
                                            <th>Status</th>
                                            <th style="text-align: right;">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($approved as $req)
                                            <tr>
                                                <td><strong>{{ $req['employee'] }}</strong></td>
                                                <td>{{ $req['type'] }}</td>
                                                <td>{{ \Carbon\Carbon::parse($req['from'])->format('d M Y') }}</td>
                                                <td>{{ \Carbon\Carbon::parse($req['to'])->format('d M Y') }}</td>
                                                <td>{{ $req['days'] }}</td>
                                                <td><span class="status-badge-sm approved">Approved</span></td>
                                                <td style="text-align: right; font-size:12px; color:var(--text-muted);">
                                                    Approved on 16 Jul 2026
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <div class="empty-state">
                                <i class="bi bi-check-circle"></i>
                                <p>No approved leave requests.</p>
                            </div>
                        @endif
                    </div>

                    <!-- REJECTED -->
                    <div class="tab-pane fade" id="rejected" role="tabpanel">
                        @php $rejected = array_filter($leaveRequests, fn($r) => $r['status'] == 'rejected'); @endphp
                        @if(count($rejected) > 0)
                            <div class="table-wrap">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Employee</th>
                                            <th>Type</th>
                                            <th>From</th>
                                            <th>To</th>
                                            <th>Days</th>
                                            <th>Status</th>
                                            <th style="text-align: right;">Reason</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($rejected as $req)
                                            <tr>
                                                <td><strong>{{ $req['employee'] }}</strong></td>
                                                <td>{{ $req['type'] }}</td>
                                                <td>{{ \Carbon\Carbon::parse($req['from'])->format('d M Y') }}</td>
                                                <td>{{ \Carbon\Carbon::parse($req['to'])->format('d M Y') }}</td>
                                                <td>{{ $req['days'] }}</td>
                                                <td><span class="status-badge-sm rejected">Rejected</span></td>
                                                <td style="font-size:12px; color:var(--text-muted);">{{ $req['reason'] }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <div class="empty-state">
                                <i class="bi bi-x-circle"></i>
                                <p>No rejected leave requests.</p>
                            </div>
                        @endif
                    </div>

                    <!-- ALL -->
                    <div class="tab-pane fade" id="all" role="tabpanel">
                        @if(count($leaveRequests) > 0)
                            <div class="table-wrap">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Employee</th>
                                            <th>Type</th>
                                            <th>From</th>
                                            <th>To</th>
                                            <th>Days</th>
                                            <th>Status</th>
                                            <th style="text-align: right;">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($leaveRequests as $req)
                                            <tr>
                                                <td><strong>{{ $req['employee'] }}</strong></td>
                                                <td>{{ $req['type'] }}</td>
                                                <td>{{ \Carbon\Carbon::parse($req['from'])->format('d M Y') }}</td>
                                                <td>{{ \Carbon\Carbon::parse($req['to'])->format('d M Y') }}</td>
                                                <td>{{ $req['days'] }}</td>
                                                <td><span
                                                        class="status-badge-sm {{ $req['status'] }}">{{ ucfirst($req['status']) }}</span>
                                                </td>
                                                <td style="text-align: right; white-space: nowrap;">
                                                    @if($req['status'] == 'pending')
                                                        <button class="btn-outline-glass" style="color:#22c55e; border-color:#22c55e;">
                                                            <i class="bi bi-check2"></i>
                                                        </button>
                                                        <button class="btn-outline-glass danger">
                                                            <i class="bi bi-x"></i>
                                                        </button>
                                                    @else
                                                        <span style="font-size:12px; color:var(--text-muted);">
                                                            {{ ucfirst($req['status']) }} on 16 Jul 2026
                                                        </span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <div class="empty-state">
                                <i class="bi bi-inbox"></i>
                                <p>No leave requests found.</p>
                            </div>
                        @endif
                    </div>

                </div>

            </div>
        </div>
    </div>

    <!-- ADD LEAVE TYPE MODAL -->

    <div class="modal fade modal-glass" id="addLeaveTypeModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-plus-circle" style="color:var(--primary);"></i> Add Leave Type
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form class="form-glass" id="addLeaveTypeForm">
                        <div class="form-group">
                            <label>Leave Type Name</label>
                            <input type="text" class="form-control" id="add_leave_name" placeholder="e.g. Annual Leave">
                        </div>
                        <div class="form-group">
                            <label>Days / Year</label>
                            <input type="number" class="form-control" id="add_leave_days" placeholder="14">
                        </div>
                        <div class="form-group">
                            <label>Color</label>
                            <input type="color" class="form-control" id="add_leave_color" value="#3b82f6"
                                style="padding: 4px; height: 44px;">
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-cancel" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn-save" onclick="saveLeaveType()"><i class="bi bi-check2"></i> Add Leave
                        Type</button>
                </div>
            </div>
        </div>
    </div>

    <!-- EDIT LEAVE TYPE MODAL-->
    <div class="modal fade modal-glass" id="editLeaveTypeModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-pencil" style="color:var(--primary);"></i> Edit Leave Type</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form class="form-glass" id="editLeaveTypeForm">
                        <input type="hidden" id="edit_leave_id">
                        <div class="form-group">
                            <label>Leave Type Name</label>
                            <input type="text" class="form-control" id="edit_leave_name" placeholder="e.g. Annual Leave">
                        </div>
                        <div class="form-group">
                            <label>Days / Year</label>
                            <input type="number" class="form-control" id="edit_leave_days" placeholder="14">
                        </div>
                        <div class="form-group">
                            <label>Color</label>
                            <input type="color" class="form-control" id="edit_leave_color" value="#3b82f6"
                                style="padding: 4px; height: 44px;">
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-cancel" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn-save" onclick="updateLeaveType()"><i class="bi bi-check2"></i> Save
                        changes</button>
                </div>
            </div>
        </div>
    </div>

@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {

            // ===== EDIT LEAVE TYPE =====
            window.editLeaveType = function (id, name, days) {
                document.getElementById('edit_leave_id').value = id;
                document.getElementById('edit_leave_name').value = name;
                document.getElementById('edit_leave_days').value = days;
                // Color set karna hai toh
                // document.getElementById('edit_leave_color').value = '#3b82f6';

                const modal = new bootstrap.Modal(document.getElementById('editLeaveTypeModal'));
                modal.show();
            };

            // ===== UPDATE LEAVE TYPE =====
            window.updateLeaveType = function () {
                const id = document.getElementById('edit_leave_id').value;
                const name = document.getElementById('edit_leave_name').value;
                const days = document.getElementById('edit_leave_days').value;

                if (!name || !days) {
                    alert('Please fill all fields.');
                    return;
                }

                alert('Leave Type ID: ' + id + ' updated!\nName: ' + name + '\nDays: ' + days);

                const modal = bootstrap.Modal.getInstance(document.getElementById('editLeaveTypeModal'));
                if (modal) modal.hide();
            };

            // ===== ADD LEAVE TYPE =====
            window.saveLeaveType = function () {
                const name = document.getElementById('add_leave_name').value;
                const days = document.getElementById('add_leave_days').value;

                if (!name || !days) {
                    alert('Please fill all fields.');
                    return;
                }

                alert('Leave Type added!\nName: ' + name + '\nDays: ' + days);

                const modal = bootstrap.Modal.getInstance(document.getElementById('addLeaveTypeModal'));
                if (modal) modal.hide();

                document.getElementById('addLeaveTypeForm').reset();
            };

            // ===== DELETE LEAVE TYPE =====
            window.deleteLeaveType = function (id) {
                if (confirm('Delete this leave type?')) {
                    alert('Leave type ID: ' + id + ' deleted!');
                }
            };

        });
    </script>
@endpush