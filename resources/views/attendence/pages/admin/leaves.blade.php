@extends('attendence.layouts.app')

@section('title', 'Leave Management')
@section('page-title', 'Leave Management')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/leave.css') }}">
@endpush

@section('content')

    {{-- @if (session('success'))
        <script>document.addEventListener('DOMContentLoaded', () => toastr.success(@json(session('success'))));</script>
    @endif
    @if (session('error'))
        <script>document.addEventListener('DOMContentLoaded', () => toastr.error(@json(session('error'))));</script>
    @endif --}}

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
                                <th>Rules</th>
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
                                    <td style="font-size:12px; color:var(--text-muted); white-space:normal;">{{ $type['rules'] ?: '—' }}</td>
                                    <td style="text-align: right; white-space: nowrap;">
                                        <button type="button" class="btn-icon"
                                            onclick="editLeaveType({{ $type['id'] }}, @js($type['name']), {{ $type['days'] }}, {{ $type['is_system'] ? 'true' : 'false' }}, @js(['max_per_month' => $type['max_per_month'], 'min_days' => $type['min_days'], 'requires_document' => $type['requires_document'], 'document_from' => $type['document_from']]))">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        @if ($type['is_system'] || $type['in_use'] > 0)
                                            {{-- System types are used by penalties / dashboards; used types would orphan their requests --}}
                                            <span class="btn-icon" style="cursor:default; opacity:.5;"
                                                title="{{ $type['is_system'] ? 'System leave type: cannot be deleted' : 'In use by leave requests: cannot be deleted' }}">
                                                <i class="bi bi-lock"></i>
                                            </span>
                                        @else
                                            <form method="POST" action="{{ route('admin.leave-type.destroy', $type['id']) }}"
                                                style="display:inline;" onsubmit="return confirm('Delete this leave type?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn-icon danger">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5">
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

                    <!-- PENDING (Approve/Reject only, no Edit/Delete) -->
                    <div class="tab-pane fade show active" id="pending" role="tabpanel">
                        @php $pending = array_filter($leaveRequests, fn($r) => $r['status'] == 'pending'); @endphp
                        @if (count($pending) > 0)
                            <div class="table-wrap">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Employee</th><th>Type</th><th>From</th><th>To</th><th>Days</th><th>Status</th><th>Line Manager Note</th>
                                            <th style="text-align: right;">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($pending as $req)
                                            <tr>
                                                <td>
                                                    <strong>{{ $req['employee'] }}</strong><br>
                                                    <span style="font-size:11px; color:var(--text-muted);">{{ $req['department'] }}</span>
                                                </td>
                                                <td>
                                                    {{ $req['type'] }}
                                                    @if ($req['document_url'])
                                                        <br><a href="{{ $req['document_url'] }}" target="_blank" style="font-size:11px;"><i class="bi bi-paperclip"></i> Document</a>
                                                    @endif
                                                </td>
                                                <td>{{ \Carbon\Carbon::parse($req['from'])->format('d M Y') }}</td>
                                                <td>{{ \Carbon\Carbon::parse($req['to'])->format('d M Y') }}</td>
                                                <td>
                                                    {{ $req['days'] }}
                                                    @if ($req['is_half_day'])
                                                        <br><span style="font-size:11px; color:var(--text-muted);">Half day · {{ $req['half_day_session'] === 'second_half' ? '2nd half' : '1st half' }}</span>
                                                    @endif
                                                </td>
                                                <td>
                                                    <span class="status-badge-sm pending">Pending</span><br>
                                                    <span style="font-size:11px; color:var(--text-muted);">{{ $req['stage'] }}</span>
                                                </td>
                                                <td style="font-size:12px; color:var(--text-muted);">
                                                    @if ($req['manager_status'] === 'approved')
                                                        <strong>{{ $req['manager_name'] ?? 'Line Manager' }}:</strong>
                                                        <span style="color:#16a34a;">Approved</span>{{ $req['manager_note'] ? ' — "' . $req['manager_note'] . '"' : '' }}
                                                    @elseif ($req['manager_status'] === 'rejected')
                                                        <strong>{{ $req['manager_name'] ?? 'Line Manager' }}:</strong>
                                                        <span style="color:#dc2626;">Not approved</span>{{ $req['manager_note'] ? ' — "' . $req['manager_note'] . '"' : '' }}
                                                    @elseif ($req['awaiting_manager'])
                                                        Waiting for {{ $req['manager_name'] ?? 'Line Manager' }}
                                                    @else
                                                        —
                                                    @endif
                                                </td>
                                                <td style="text-align: right; white-space:nowrap;">
                                                    @if ($req['awaiting_manager'] && ! $req['admin_can_decide'])
                                                        <span style="font-size:12px; color:var(--text-muted);">{{ $req['stage'] }}</span>
                                                    @else
                                                        <button type="button" class="btn-outline-glass" style="color:#22c55e; border-color:#22c55e;"
                                        onclick="openApproveModal('{{ route('admin.leave-request.approve', $req['id']) }}')"><i class="bi bi-check2"></i></button>
                                                        <button type="button" class="btn-outline-glass danger" onclick="openRejectModal('{{ route('admin.leave-request.reject', $req['id']) }}')">
                                                            <i class="bi bi-x"></i>
                                                        </button>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <div class="empty-state"><i class="bi bi-inbox"></i><p>No pending leave requests.</p></div>
                        @endif
                    </div>

                    <!-- APPROVED (Edit + Delete) -->
                    <div class="tab-pane fade" id="approved" role="tabpanel">
                        @php $approved = array_filter($leaveRequests, fn($r) => $r['status'] == 'approved'); @endphp
                        @if (count($approved) > 0)
                            <div class="table-wrap">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Employee</th><th>Type</th><th>From</th><th>To</th><th>Days</th><th>Status</th><th>Notes</th>
                                            <th style="text-align: right;">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($approved as $req)
                                            <tr>
                                                <td>
                                                    <strong>{{ $req['employee'] }}</strong><br>
                                                    <span style="font-size:11px; color:var(--text-muted);">{{ $req['department'] }}</span>
                                                </td>
                                                <td>
                                                    {{ $req['type'] }}
                                                    @if ($req['document_url'])
                                                        <br><a href="{{ $req['document_url'] }}" target="_blank" style="font-size:11px;"><i class="bi bi-paperclip"></i> Document</a>
                                                    @endif
                                                </td>
                                                <td>{{ \Carbon\Carbon::parse($req['from'])->format('d M Y') }}</td>
                                                <td>{{ \Carbon\Carbon::parse($req['to'])->format('d M Y') }}</td>
                                                <td>{{ $req['days'] }}</td>
                                                <td>
                                                    <span class="status-badge-sm approved">Approved</span>
                                                    @if ($req['full_day_worked'])
                                                        <br><span style="font-size:11px; color:#ef4444; font-weight:600;"
                                                            title="Machine punches show a full working day. Use Edit to change the status to Rejected.">
                                                            <i class="bi bi-exclamation-triangle"></i>
                                                            Full day worked ({{ intdiv($req['full_day_worked'], 60) }}h {{ $req['full_day_worked'] % 60 }}m)
                                                        </span>
                                                    @endif
                                                </td>
                                                <td style="font-size:12px; color:var(--text-muted); max-width:260px; white-space:normal;">
                                                    @if ($req['manager_status'] === 'approved')
                                                        <div><strong>{{ $req['manager_name'] ?? 'Line Manager' }}:</strong> {{ $req['manager_note'] ?: 'Approved' }}</div>
                                                    @endif
                                                    @if ($req['admin_note'])
                                                        <div><strong>Admin:</strong> {{ $req['admin_note'] }}</div>
                                                    @endif
                                                    @if ($req['manager_status'] !== 'approved' && ! $req['admin_note'])
                                                        —
                                                    @endif
                                                </td>
                                                <td style="text-align: right; white-space:nowrap;">
                                                    <span style="font-size:12px; color:var(--text-muted); margin-right:8px;">
                                                        {{ $req['reviewed_at'] ? \Carbon\Carbon::parse($req['reviewed_at'])->format('d M Y') : '-' }}
                                                    </span>
                                                    <button type="button" class="btn-outline-glass" style="padding:2px 8px;"
                                                        onclick="openEditLeave({{ $req['id'] }}, {{ $req['leave_type_id'] }}, '{{ $req['from'] }}', '{{ $req['to'] }}', {{ $req['days'] }}, '{{ $req['status'] }}', '{{ addslashes($req['reason'] ?? '') }}')">
                                                        <i class="bi bi-pencil"></i>
                                                    </button>
                                                    <form method="POST" action="{{ route('admin.leave-request.destroy', $req['id']) }}" style="display:inline;" onsubmit="return confirm('Delete this leave request?');">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="btn-outline-glass danger" style="padding:2px 8px;"><i class="bi bi-trash"></i></button>
                                                    </form>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <div class="empty-state"><i class="bi bi-check-circle"></i><p>No approved leave requests.</p></div>
                        @endif
                    </div>

                    <!-- REJECTED (Edit + Delete, Admin Note column) -->
                    <div class="tab-pane fade" id="rejected" role="tabpanel">
                        @php $rejected = array_filter($leaveRequests, fn($r) => $r['status'] == 'rejected'); @endphp
                        @if (count($rejected) > 0)
                            <div class="table-wrap">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Employee</th><th>Type</th><th>From</th><th>To</th><th>Days</th><th>Status</th><th>Admin Note</th>
                                            <th style="text-align: right;">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($rejected as $req)
                                            <tr>
                                                <td>
                                                    <strong>{{ $req['employee'] }}</strong><br>
                                                    <span style="font-size:11px; color:var(--text-muted);">{{ $req['department'] }}</span>
                                                </td>
                                                <td>
                                                    {{ $req['type'] }}
                                                    @if ($req['document_url'])
                                                        <br><a href="{{ $req['document_url'] }}" target="_blank" style="font-size:11px;"><i class="bi bi-paperclip"></i> Document</a>
                                                    @endif
                                                </td>
                                                <td>{{ \Carbon\Carbon::parse($req['from'])->format('d M Y') }}</td>
                                                <td>{{ \Carbon\Carbon::parse($req['to'])->format('d M Y') }}</td>
                                                <td>{{ $req['days'] }}</td>
                                                <td><span class="status-badge-sm rejected">Rejected</span></td>
                                                <td style="font-size:12px; color:var(--text-muted);">
                                                    @if ($req['manager_status'] === 'rejected')
                                                        <strong>Line Manager:</strong> {{ $req['manager_note'] ?? '-' }}
                                                    @else
                                                        {{ $req['admin_note'] ?? '-' }}
                                                    @endif
                                                </td>
                                                <td style="text-align: right; white-space:nowrap;">
                                                    <button type="button" class="btn-outline-glass" style="padding:2px 8px;"
                                                        onclick="openEditLeave({{ $req['id'] }}, {{ $req['leave_type_id'] }}, '{{ $req['from'] }}', '{{ $req['to'] }}', {{ $req['days'] }}, '{{ $req['status'] }}', '{{ addslashes($req['reason'] ?? '') }}')">
                                                        <i class="bi bi-pencil"></i>
                                                    </button>
                                                    <form method="POST" action="{{ route('admin.leave-request.destroy', $req['id']) }}" style="display:inline;" onsubmit="return confirm('Delete this leave request?');">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="btn-outline-glass danger" style="padding:2px 8px;"><i class="bi bi-trash"></i></button>
                                                    </form>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <div class="empty-state"><i class="bi bi-x-circle"></i><p>No rejected leave requests.</p></div>
                        @endif
                    </div>

                    <!-- ALL (status/date only, no Edit/Delete) -->
                    <div class="tab-pane fade" id="all" role="tabpanel">
                        @if (count($leaveRequests) > 0)
                            <div class="table-wrap">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Employee</th><th>Type</th><th>From</th><th>To</th><th>Days</th><th>Status</th>
                                            <th style="text-align: right;">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($leaveRequests as $req)
                                            <tr>
                                                <td>
                                                    <strong>{{ $req['employee'] }}</strong><br>
                                                    <span style="font-size:11px; color:var(--text-muted);">{{ $req['department'] }}</span>
                                                </td>
                                                <td>
                                                    {{ $req['type'] }}
                                                    @if ($req['document_url'])
                                                        <br><a href="{{ $req['document_url'] }}" target="_blank" style="font-size:11px;"><i class="bi bi-paperclip"></i> Document</a>
                                                    @endif
                                                </td>
                                                <td>{{ \Carbon\Carbon::parse($req['from'])->format('d M Y') }}</td>
                                                <td>{{ \Carbon\Carbon::parse($req['to'])->format('d M Y') }}</td>
                                                <td>{{ $req['days'] }}</td>
                                                <td><span class="status-badge-sm {{ $req['status'] }}">{{ ucfirst($req['status']) }}</span></td>
                                                <td style="text-align: right; white-space: nowrap;">
                                                    @if ($req['awaiting_manager'])
                                                        <span style="font-size:12px; color:var(--text-muted);">{{ $req['stage'] }}</span>
                                                    @elseif ($req['status'] == 'pending')
                                                        <button type="button" class="btn-outline-glass" style="color:#22c55e; border-color:#22c55e;"
                                        onclick="openApproveModal('{{ route('admin.leave-request.approve', $req['id']) }}')">
                                                                <i class="bi bi-check2"></i>
                                                            </button>
                                                        <button type="button" class="btn-outline-glass danger" onclick="openRejectModal('{{ route('admin.leave-request.reject', $req['id']) }}')">
                                                            <i class="bi bi-x"></i>
                                                        </button>
                                                    @else
                                                        <span style="font-size:12px; color:var(--text-muted);">
                                                            {{ ucfirst($req['status']) }} on {{ $req['reviewed_at'] ? \Carbon\Carbon::parse($req['reviewed_at'])->format('d M Y') : '-' }}
                                                        </span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <div class="empty-state"><i class="bi bi-inbox"></i><p>No leave requests found.</p></div>
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
                <form class="form-glass" method="POST" action="{{ route('admin.leave-type.store') }}">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="bi bi-plus-circle" style="color:var(--primary);"></i> Add Leave Type</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label>Leave Type Name</label>
                            <input type="text" name="name" class="form-control" placeholder="e.g. Annual Leave" required>
                        </div>
                        <div class="form-group">
                            <label>Days / Year</label>
                            <input type="number" name="days_per_year" class="form-control" placeholder="14" required>
                        </div>
                        <div class="form-group">
                            <label>Color</label>
                            <input type="color" name="color" class="form-control" value="#3b82f6" style="padding: 4px; height: 44px;">
                        </div>
                        <div class="form-group">
                            <label>Max days per month <small style="color:var(--text-muted);">(empty = no limit)</small></label>
                            <input type="number" name="max_days_per_month" id="add_max_per_month" class="form-control" min="0.5" max="31" step="0.5" placeholder="e.g. 2">
                        </div>
                        <div class="form-group">
                            <label>Minimum days per request <small style="color:var(--text-muted);">(empty = no minimum)</small></label>
                            <input type="number" name="min_days_per_request" id="add_min_days" class="form-control" min="0.5" step="0.5" placeholder="e.g. 3">
                        </div>
                        <div class="form-group" style="display:flex; align-items:center; gap:8px;">
                            <input type="checkbox" name="requires_document" id="add_requires_document" value="1" style="width:auto;">
                            <label for="add_requires_document" style="margin:0;">Supporting document required (e.g. doctor's slip)</label>
                        </div>
                        <div class="form-group">
                            <label>Document needed from <small style="color:var(--text-muted);">(days per request, empty = always)</small></label>
                            <input type="number" name="document_required_from_days" id="add_document_from" class="form-control" min="0.5" step="0.5" placeholder="e.g. 2">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn-cancel" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn-save"><i class="bi bi-check2"></i> Add Leave Type</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- EDIT LEAVE TYPE MODAL -->
    <div class="modal fade modal-glass" id="editLeaveTypeModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form class="form-glass" method="POST" id="editLeaveTypeForm" action="">
                    @csrf
                    @method('PUT')
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="bi bi-pencil" style="color:var(--primary);"></i> Edit Leave Type</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label>Leave Type Name</label>
                            <input type="text" name="name" id="edit_leave_name" class="form-control" required>
                            <small id="edit_leave_name_hint" style="display:none; font-size:11px; color:var(--text-muted);">
                                System leave type: the name is used by penalties and dashboards, so it cannot be changed.
                            </small>
                        </div>
                        <div class="form-group">
                            <label>Days / Year</label>
                            <input type="number" name="days_per_year" id="edit_leave_days" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label>Max days per month <small style="color:var(--text-muted);">(empty = no limit)</small></label>
                            <input type="number" name="max_days_per_month" id="edit_max_per_month" class="form-control" min="0.5" max="31" step="0.5" placeholder="e.g. 2">
                        </div>
                        <div class="form-group">
                            <label>Minimum days per request <small style="color:var(--text-muted);">(empty = no minimum)</small></label>
                            <input type="number" name="min_days_per_request" id="edit_min_days" class="form-control" min="0.5" step="0.5" placeholder="e.g. 3">
                        </div>
                        <div class="form-group" style="display:flex; align-items:center; gap:8px;">
                            <input type="checkbox" name="requires_document" id="edit_requires_document" value="1" style="width:auto;">
                            <label for="edit_requires_document" style="margin:0;">Supporting document required (e.g. doctor's slip)</label>
                        </div>
                        <div class="form-group">
                            <label>Document needed from <small style="color:var(--text-muted);">(days per request, empty = always)</small></label>
                            <input type="number" name="document_required_from_days" id="edit_document_from" class="form-control" min="0.5" step="0.5" placeholder="e.g. 2">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn-cancel" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn-save"><i class="bi bi-check2"></i> Save changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- EDIT LEAVE REQUEST MODAL -->
<div class="modal fade modal-glass" id="editLeaveModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" id="editLeaveForm" action="">
                @csrf
                @method('PUT')
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-eye"></i> Leave Request Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="form-group" style="margin-bottom:16px;">
                        <label class="form-label-sm">Leave Type</label>
                        <select id="editLeaveType" class="form-control-custom" disabled>
                            @foreach($leaveTypesRaw ?? [] as $t)
                                <option value="{{ $t->id }}">{{ $t->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group" style="margin-bottom:16px;">
                                <label class="form-label-sm">From</label>
                                <input type="date" id="editLeaveFrom" class="form-control-custom" disabled>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group" style="margin-bottom:16px;">
                                <label class="form-label-sm">To</label>
                                <input type="date" id="editLeaveTo" class="form-control-custom" disabled>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group" style="margin-bottom:16px;">
                                <label class="form-label-sm">Days</label>
                                <input type="number" id="editLeaveDays" class="form-control-custom" disabled>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group" style="margin-bottom:16px;">
                                <label class="form-label-sm">Status</label>
                                <select name="status" id="editLeaveStatus" class="form-control-custom" required>
                                    <option value="pending">Pending</option>
                                    <option value="approved">Approved</option>
                                    <option value="rejected">Rejected</option>
                                </select>
                            </div>
                        </div>
                    </div>
                   <div class="form-group" style="margin-bottom:16px;">
    <label class="form-label-sm">Employee's Reason</label>
    <textarea id="editLeaveReason" class="form-control-custom" rows="2" disabled></textarea>
</div>

<div class="form-group" id="editAdminNoteGroup" style="display:none;">
    <label class="form-label-sm">Rejection Note (visible to employee)</label>
    <textarea name="admin_note" id="editAdminNote" class="form-control-custom" rows="2" placeholder="This reason will be visible to the employee..."></textarea>
</div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-outline-glass" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn-primary-glass"><i class="bi bi-check2"></i> Update Status</button>
                </div>
            </form>
        </div>
    </div>
</div>


    <!-- APPROVE MODAL (optional note for the employee and their Line Manager / Manager) -->
    <div class="modal fade modal-glass" id="approveNoteModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="POST" id="approveNoteForm" action="">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="bi bi-check2-circle" style="color:#22c55e;"></i> Approve Request</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label class="form-label-sm">Admin note (optional)</label>
                            <textarea name="note" class="form-control-custom" rows="3" maxlength="500"
                                placeholder="This note will be visible to the employee and their Line Manager / Manager..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn-cancel" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn-save" style="background:#22c55e;"><i class="bi bi-check2"></i> Approve</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- REJECT REASON MODAL -->
    <div class="modal fade modal-glass" id="rejectReasonModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="POST" id="rejectReasonForm" action="">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="bi bi-x-circle" style="color:#dc2626;"></i> Reject Leave Request</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label class="form-label-sm">Reason for rejection</label>
                            <textarea name="reason" class="form-control-custom" rows="3" placeholder="This reason will be visible to the employee..." required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn-cancel" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn-save" style="background:#dc2626;">
                            <i class="bi bi-x"></i> Reject
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

@endsection

@push('scripts')
    <script>
        window.editLeaveType = function (id, name, days, isSystem = false, rules = {}) {
            document.getElementById('edit_max_per_month').value = rules.max_per_month ?? '';
            document.getElementById('edit_min_days').value = rules.min_days ?? '';
            document.getElementById('edit_requires_document').checked = !! rules.requires_document;
            document.getElementById('edit_document_from').value = rules.document_from ?? '';
            document.getElementById('editLeaveTypeForm').action = `/admin/leave/type/${id}`;
            document.getElementById('edit_leave_name').value = name;
            document.getElementById('edit_leave_name').readOnly = isSystem;
            document.getElementById('edit_leave_name_hint').style.display = isSystem ? '' : 'none';
            document.getElementById('edit_leave_days').value = days;

            const modal = new bootstrap.Modal(document.getElementById('editLeaveTypeModal'));
            modal.show();
        };

function openEditLeave(id, leaveTypeId, from, to, days, status, reason, adminNote) {
    document.getElementById('editLeaveForm').action = `/admin/leave/request/${id}`;
    document.getElementById('editLeaveType').value = leaveTypeId;
    document.getElementById('editLeaveFrom').value = from;
    document.getElementById('editLeaveTo').value = to;
    document.getElementById('editLeaveDays').value = days;
    document.getElementById('editLeaveStatus').value = status;
    document.getElementById('editLeaveReason').value = reason;
    document.getElementById('editAdminNote').value = adminNote || '';
    toggleAdminNoteField();
    new bootstrap.Modal(document.getElementById('editLeaveModal')).show();
}

function toggleAdminNoteField() {
    const status = document.getElementById('editLeaveStatus').value;
    document.getElementById('editAdminNoteGroup').style.display = status === 'rejected' ? 'block' : 'none';
}

document.getElementById('editLeaveStatus').addEventListener('change', toggleAdminNoteField);

        function openApproveModal(actionUrl) {
            const form = document.getElementById('approveNoteForm');
            form.action = actionUrl;
            form.reset();
            new bootstrap.Modal(document.getElementById('approveNoteModal')).show();
        }

        function openRejectModal(actionUrl) {
            document.getElementById('rejectReasonForm').action = actionUrl;
            new bootstrap.Modal(document.getElementById('rejectReasonModal')).show();
        }
    </script>
@endpush