@extends('attendence.layouts.app')

@section('title', 'Approvals')
@section('page-title', 'Approvals')

@section('content')
    <link rel="stylesheet" href="{{ asset('css/approval.css') }}">
    <style>
        .approval-item .info .reason {
            white-space: nowrap;
            max-width: 280px;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .approval-item .info .reason {
            font-size: 13px;
            color: var(--text-secondary);
            background: var(--bg-body);
            padding: 4px 12px;
            border-radius: 6px;
            border: 1px solid var(--border-color);
            max-width: 280px;
            cursor: pointer;
        }

        .approval-item .info .reason.truncated {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .approval-item .info .reason.expanded {
            white-space: normal;
            max-width: 320px;
            word-break: break-word;
        }

        .approval-item .info .reason .toggle-link {
            color: var(--primary);
            font-weight: 600;
            margin-left: 4px;
            white-space: nowrap;
        }
    </style>


    <!-- APPROVALS PAGE -->
    <div class="row">
        <div class="col-12">
            <div class="glass-card">

                <!-- ===== TABS ===== -->
                <div class="approval-tabs" id="approvalTabs">
                    <button class="tab-btn active" data-tab="corrections">
                        <i class="bi bi-pencil-square"></i> Correction Requests
                        <span class="badge-count">{{ count($data['corrections']) }}</span>
                    </button>
                    <button class="tab-btn" data-tab="leaves">
                        <i class="bi bi-calendar-check"></i> Leave Requests
                        <span class="badge-count">{{ count($data['leaves']) }}</span>
                    </button>
                    <button class="tab-btn" data-tab="waivers">
                        <i class="bi bi-shield-minus"></i> Deduction Waiver Requests
                        <span class="badge-count">{{ count($data['waivers']) }}</span>
                    </button>
                    <button class="tab-btn" data-tab="history">
                        <i class="bi bi-clock-history"></i> History
                        <span class="badge-count">{{ count($data['history']) }}</span>
                    </button>
                </div>

                <!-- TAB 1: CORRECTION REQUESTS -->
                <div class="tab-content active" id="tab-corrections">
                    @if (count($data['corrections']) > 0)
                        <div
                            style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                            <span style="font-size: 14px; color: var(--text-muted);">
                                <strong style="color: var(--text-primary);">{{ count($data['corrections']) }}</strong>
                                pending
                                requests
                            </span>
                        </div>

                        @foreach ($data['corrections'] as $item)
                            <div class="approval-item" data-id="{{ $item['id'] }}">
                                <div class="info">
                                    <div class="avatar-sm">{{ $item['avatar'] }}</div>
                                    <div class="details">
                                        <div class="name">{{ $item['employee'] }}</div>
                                        <div class="meta">
                                            <i class="bi bi-person-badge"></i> {{ $item['designation'] }}
                                            &nbsp;·&nbsp;
                                            <i class="bi bi-calendar"></i>
                                            {{ \Carbon\Carbon::parse($item['date'])->format('d M Y') }}
                                            · {{ $item['type'] }}
                                        </div>
                                        @if (in_array($item['lm_status'] ?? null, ['approved', 'rejected']))
                                            {{-- Line Manager / Manager recommendation: Admin makes the final decision --}}
                                            <div class="meta">
                                                @if ($item['lm_status'] === 'approved')
                                                    <i class="bi bi-person-check" style="color:#16a34a;"></i> <span style="color:#16a34a;">Approved</span>
                                                @else
                                                    <i class="bi bi-person-x" style="color:#dc2626;"></i> <span style="color:#dc2626;">Not approved</span>
                                                @endif
                                                by {{ $item['lm_by'] ?? 'Line Manager' }}
                                                @if ($item['lm_note'])
                                                    — "{{ $item['lm_note'] }}"
                                                @endif
                                            </div>
                                        @else
                                            <div class="meta" style="color:#d97706;">
                                                <i class="bi bi-hourglass-split"></i> {{ $item['awaiting'] }}
                                            </div>
                                        @endif
                                    </div>
                                    <span class="reason" title="{{ $item['reason'] }}">
                                        <i class="bi bi-clock"></i> Requested: {{ $item['requested_time'] }}
                                        — "{{ $item['reason'] }}"
                                    </span>
                                </div>
                                <div class="actions">
                                    <button type="button" class="btn-outline-glass"
                                        style="padding:6px 14px; font-size:13px;"
                                        onclick="openViewModal('{{ $item['employee'] }}', '{{ $item['designation'] }}', '{{ \Carbon\Carbon::parse($item['date'])->format('d M Y') }}', '{{ $item['type'] }}', '{{ addslashes($item['current_record']) }}', '{{ addslashes($item['requested_time']) }}', '{{ addslashes($item['reason']) }}')">
                                        <i class="bi bi-eye"></i> View
                                    </button>
                                    <button type="button" class="btn-approve"
                                        onclick="openApproveModal('{{ route('admin.approvals.correction.approve', $item['id']) }}')"><i class="bi bi-check2"></i>
                                            Approve</button>
                                    <button type="button" class="btn-reject"
                                        onclick="openRejectModal('{{ route('admin.approvals.correction.reject', $item['id']) }}')">
                                        <i class="bi bi-x"></i> Reject
                                    </button>
                                </div>
                            </div>
                        @endforeach
                    @else
                        <div class="empty-state">
                            <i class="bi bi-check-circle"></i>
                            <h6>No pending corrections</h6>
                            <p>All correction requests have been reviewed.</p>
                        </div>
                    @endif
                </div>

                <!-- TAB 2: LEAVE REQUESTS -->
                <div class="tab-content" id="tab-leaves">
                    @if (count($data['leaves']) > 0)
                        <div
                            style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                            <span style="font-size: 14px; color: var(--text-muted);">
                                <strong style="color: var(--text-primary);">{{ count($data['leaves']) }}</strong> pending
                                requests
                            </span>
                        </div>

                        @foreach ($data['leaves'] as $item)
                            <div class="approval-item" data-id="{{ $item['id'] }}">
                                <div class="info">
                                    <div class="avatar-sm">{{ $item['avatar'] }}</div>
                                    <div class="details">
                                        <div class="name">{{ $item['employee'] }}</div>
                                        <div class="meta">
                                            <i class="bi bi-person-badge"></i> {{ $item['designation'] }}
                                            &nbsp;·&nbsp;
                                            <i class="bi bi-calendar"></i>
                                            {{ \Carbon\Carbon::parse($item['date'])->format('d M Y') }}
                                            · {{ $item['type'] }}
                                            @if ($item['is_half_day'])
                                                · Half day
                                            @endif
                                        </div>
                                        @if ($item['awaiting'] ?? null)
                                            {{-- A Line Manager's / Manager's own request: their reviewer sees it too, whoever decides first --}}
                                            <div class="meta" style="color:#d97706;">
                                                <i class="bi bi-hourglass-split"></i> {{ $item['awaiting'] }} — you may decide now
                                            </div>
                                        @elseif ($item['manager_name'])
                                            <div class="meta">
                                                @if (($item['manager_status'] ?? null) === 'rejected')
                                                    <i class="bi bi-person-x" style="color:#dc2626;"></i> <span style="color:#dc2626;">Not approved</span> by {{ $item['manager_name'] }}
                                                @else
                                                    <i class="bi bi-person-check" style="color:#16a34a;"></i> Approved by {{ $item['manager_name'] }}
                                                @endif
                                                @if ($item['manager_note'])
                                                    — "{{ $item['manager_note'] }}"
                                                @endif
                                            </div>
                                        @endif
                                    </div>
                                    <span class="reason" title="{{ $item['reason'] }}"><i class="bi bi-chat"></i>
                                        {{ $item['reason'] }}</span>
                                </div>
                                <div class="actions">
                                    <button type="button" class="btn-approve"
                                        onclick="openApproveModal('{{ route('admin.leave-request.approve', $item['id']) }}')"><i class="bi bi-check2"></i>
                                            Approve</button>
                                    <button type="button" class="btn-reject"
                                        onclick="openRejectModal('{{ route('admin.leave-request.reject', $item['id']) }}')">
                                        <i class="bi bi-x"></i> Reject
                                    </button>
                                </div>
                            </div>
                        @endforeach
                    @else
                        <div class="empty-state">
                            <i class="bi bi-check-circle"></i>
                            <h6>No pending leave requests</h6>
                            <p>All leave requests have been reviewed.</p>
                        </div>
                    @endif
                </div>

                <!-- TAB 3: DEDUCTION WAIVER REQUESTS -->
                <div class="tab-content" id="tab-waivers">
                    @if (count($data['waivers']) > 0)
                        <div
                            style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                            <span style="font-size: 14px; color: var(--text-muted);">
                                <strong style="color: var(--text-primary);">{{ count($data['waivers']) }}</strong> pending
                                requests
                            </span>
                        </div>

                        @foreach ($data['waivers'] as $item)
                            <div class="approval-item" data-id="{{ $item['id'] }}">
                                <div class="info">
                                    <div class="avatar-sm">{{ $item['avatar'] }}</div>
                                    <div class="details">
                                        <div class="name">{{ $item['employee'] }}</div>
                                        <div class="meta">
                                            <i class="bi bi-person-badge"></i> {{ $item['designation'] }}
                                            &nbsp;·&nbsp;
                                            <i class="bi bi-calendar"></i>
                                            {{ \Carbon\Carbon::parse($item['date'])->format('d M Y') }}
                                            · {{ $item['type'] }}
                                        </div>
                                    </div>
                                    <span class="reason" title="{{ $item['reason'] }}"><i class="bi bi-chat"></i>
                                        {{ $item['reason'] }}</span>
                                    

                                    @if ($item['lm_status'] === 'not_required')
                                        <div style="margin-top:8px; font-size:12px; color:var(--text-muted);">
                                            <i class="bi bi-arrow-right-circle"></i> No Line Manager review — sent directly to Admin
                                        </div>
                                    @elseif ($item['lm_status'] && $item['lm_status'] !== 'pending')
                                        <div
                                            style="margin-top:8px; padding:10px 14px; background:{{ $item['lm_status'] === 'approved' ? '#d1fae5' : '#fee2e2' }}; border-radius:8px; font-size:12px;">
                                            <strong>Manager's ({{ $item['lm_reviewed_by'] }}):</strong>
                                            {{ ucfirst($item['lm_status']) }} — "{{ $item['lm_note'] }}"
                                        </div>
                                    @else
                                        <div style="margin-top:8px; font-size:12px; color:var(--text-muted);">
                                            <i class="bi bi-hourglass-split"></i> Awaiting line manager's review
                                        </div>
                                    @endif
                                </div>
                                <div class="actions">
                                    <button type="button" class="btn-approve"
                                        onclick="openApproveModal('{{ route('admin.approvals.waiver.approve', $item['id']) }}')"><i class="bi bi-check2"></i>
                                            Approve</button>
                                    <button type="button" class="btn-reject"
                                        onclick="openRejectModal('{{ route('admin.approvals.waiver.reject', $item['id']) }}')">
                                        <i class="bi bi-x"></i> Reject
                                    </button>
                                </div>
                            </div>
                        @endforeach
                    @else
                        <div class="empty-state">
                            <i class="bi bi-check-circle"></i>
                            <h6>No pending waiver requests</h6>
                            <p>All waiver requests have been reviewed.</p>
                        </div>
                    @endif
                </div>

                <!-- TAB 4: HISTORY -->
                <div class="tab-content" id="tab-history">
                    @if (count($data['history']) > 0)
                        <div
                            style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                            <span style="font-size: 14px; color: var(--text-muted);">
                                <strong style="color: var(--text-primary);">{{ count($data['history']) }}</strong>
                                processed
                                requests
                            </span>
                        </div>
                        <div class="table-wrap">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Employee</th>
                                        <th>Type</th>
                                        <th>Detail</th>
                                        <th>Date</th>
                                        <th>Status</th>
                                        <th>Decisions &amp; notes</th>
                                        <th>Reviewed</th>
                                        <th style="text-align:right;">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($data['history'] as $h)
                                        <tr>
                                            <td>
                                                <div style="display:flex; align-items:center; gap:8px;">
                                                    <div class="avatar-sm">{{ $h['avatar'] }}</div>
                                                    <strong>{{ $h['employee'] }}</strong>
                                                </div>
                                            </td>
                                            <td>{{ $h['type'] }}</td>
                                            <td>{{ $h['detail'] }}</td>
                                            <td>{{ \Carbon\Carbon::parse($h['date'])->format('d M Y') }}</td>
                                            <td><span
                                                    class="status-badge-sm {{ $h['status'] }}">{{ ucfirst($h['status']) }}</span>
                                            </td>
                                            <td style="font-size:12px; color:var(--text-muted); max-width:340px; white-space:normal;">
                                                {{-- Each step: who decided, what, and their note (Line Manager / Manager first, then Admin) --}}
                                                @forelse ($h['steps'] ?? [] as $step)
                                                    <div style="margin-bottom:4px;">
                                                        <strong style="color:var(--text-primary);">{{ $step['who'] }}</strong>:
                                                        <span style="color:{{ in_array($step['decision'], ['approved', 'recommended']) ? '#16a34a' : '#dc2626' }};">{{ ucfirst($step['decision']) }}</span>
                                                        @if ($step['note'])
                                                            — "{{ $step['note'] }}"
                                                        @endif
                                                    </div>
                                                @empty
                                                    {{ $h['note'] ?? '-' }}
                                                @endforelse
                                            </td>
                                            <td style="font-size:12px; color:var(--text-muted);">
                                                {{ $h['reviewed_at'] ? \Carbon\Carbon::parse($h['reviewed_at'])->format('d M Y') : '-' }}
                                                @if (($h['reviewed_by'] ?? '-') !== '-')
                                                    <br>by {{ $h['reviewed_by'] }}
                                                @endif
                                            </td>
                                            <td style="text-align:right;">
                                                <button type="button" class="btn-outline-glass"
                                                    style="padding:2px 10px;font-size:11px;"
                                                    onclick="openEditHistory('{{ strtolower($h['type']) }}', {{ $h['id'] }}, '{{ $h['status'] }}', '{{ addslashes($h['note'] ?? '') }}')">
                                                    <i class="bi bi-pencil"></i>
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
                            <h6>No history yet</h6>
                            <p>Approved or rejected requests will appear here.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>


    <div class="modal fade modal-glass" id="viewCorrectionModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-file-text" style="color:var(--primary);"></i> Correction
                        Request
                        Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="request-preview"
                        style="background:var(--bg-body); border-radius:10px; padding:16px; border:1px solid var(--border-color); margin-bottom:16px;">
                        <div class="label"
                            style="font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:0.4px; color:var(--text-muted);">
                            Employee</div>
                        <div class="value" id="viewEmployeeName"
                            style="font-size:15px; font-weight:600; margin-top:2px;">—
                        </div>

                        <div class="label"
                            style="font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:0.4px; color:var(--text-muted); margin-top:12px;">
                            Designation</div>
                        <div class="value" id="viewDesignation" style="font-size:14px; margin-top:2px;">—</div>

                        <div class="label"
                            style="font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:0.4px; color:var(--text-muted); margin-top:12px;">
                            Date &amp; Type</div>
                        <div class="value" id="viewDateType" style="font-size:14px; margin-top:2px;">—</div>
                    </div>

                    <div class="form-group">
                        <label class="form-label-sm">Current Record</label>
                        <div style="padding:10px 14px; background:var(--bg-body); border:1px solid var(--border-color); border-radius:10px; font-size:14px;"
                            id="viewCurrentRecord">—</div>
                    </div>

                    <div class="form-group">
                        <label class="form-label-sm">Requested Correction</label>
                        <div style="padding:10px 14px; background:#d1fae5; border:1px solid #86efac; border-radius:10px; font-size:14px; color:#065f46; font-weight:600;"
                            id="viewRequestedCorrection">—</div>
                    </div>

                    <div class="form-group">
                        <label class="form-label-sm">Employee's Reason</label>
                        <div style="padding:10px 14px; background:var(--bg-body); border:1px solid var(--border-color); border-radius:10px; font-size:14px;"
                            id="viewReason">—</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-cancel" data-bs-dismiss="modal">Close</button>
                </div>
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
                        <h5 class="modal-title"><i class="bi bi-x-circle" style="color:#dc2626;"></i> Reject Request</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label class="form-label-sm">Reason for rejection</label>
                            <textarea name="reason" class="form-control-custom" rows="3"
                                placeholder="This reason will be shown to the employee..." required></textarea>
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

    <!-- EDIT HISTORY MODAL -->
    <div class="modal fade modal-glass" id="editHistoryModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="POST" id="editHistoryForm" action="">
                    @csrf
                    @method('PUT')
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="bi bi-pencil" style="color:var(--primary);"></i> Edit Request
                            Status</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="form-group" style="margin-bottom:16px;">
                            <label class="form-label-sm">Status</label>
                            <select name="status" id="editHistoryStatus" class="form-control-custom" required>
                                <option value="pending">Pending</option>
                                <option value="approved">Approved</option>
                                <option value="rejected">Rejected</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label-sm">Note (optional)</label>
                            <textarea name="note" id="editHistoryNote" class="form-control-custom" rows="3"
                                placeholder="This note will be shown to the employee..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn-cancel" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn-save"><i class="bi bi-check2"></i> Update</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
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

        function openEditHistory(type, id, status, note) {
            const routes = {
                'correction': `/admin/approvals/correction/${id}`,
                'leave': `/admin/leave/request/${id}`,
                'waiver': `/admin/approvals/waiver/${id}`,
            };

            document.getElementById('editHistoryForm').action = routes[type];
            document.getElementById('editHistoryStatus').value = status;
            document.getElementById('editHistoryNote').value = note;
            new bootstrap.Modal(document.getElementById('editHistoryModal')).show();
        }

        function openViewModal(employee, designation, date, type, currentRecord, requestedCorrection, reason) {
            document.getElementById('viewEmployeeName').textContent = employee;
            document.getElementById('viewDesignation').textContent = designation;
            document.getElementById('viewDateType').textContent = date + ' · ' + type;
            document.getElementById('viewCurrentRecord').textContent = currentRecord;
            document.getElementById('viewRequestedCorrection').textContent = requestedCorrection;
            document.getElementById('viewReason').textContent = reason;
            new bootstrap.Modal(document.getElementById('viewCorrectionModal')).show();
        }
        document.addEventListener('DOMContentLoaded', function() {
            // ===== TABS =====
            const tabBtns = document.querySelectorAll('.tab-btn');
            const tabContents = {
                corrections: document.getElementById('tab-corrections'),
                leaves: document.getElementById('tab-leaves'),
                waivers: document.getElementById('tab-waivers'),
                history: document.getElementById('tab-history'),
            };

            tabBtns.forEach(btn => {
                btn.addEventListener('click', function() {
                    tabBtns.forEach(b => b.classList.remove('active'));
                    this.classList.add('active');

                    Object.values(tabContents).forEach(content => content.classList.remove(
                        'active'));

                    const tab = this.dataset.tab;
                    if (tabContents[tab]) tabContents[tab].classList.add('active');
                });
            });
        });

        function toggleReason(el) {
            el.classList.toggle('truncated');
            el.classList.toggle('expanded');
        }
    </script>
@endpush
