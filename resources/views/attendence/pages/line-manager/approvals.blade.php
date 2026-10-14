@extends('attendence.layouts.app')

@section('title', 'Approvals')
@section('page-title', 'Approvals')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/manager-css/approval.css') }}">
@endpush

@section('content')


<!-- APPROVALS PAGE  -->
@php
    $tabs = [
        'corrections' => ['title' => 'Correction Requests', 'icon' => 'bi-pencil-square', 'items' => $corrections, 'empty' => 'No pending correction requests'],
        'leaves' => ['title' => 'Leave Requests', 'icon' => 'bi-calendar-check', 'items' => $leaves, 'empty' => 'No pending leave requests'],
        'waivers' => ['title' => 'Deduction Waiver Requests', 'icon' => 'bi-shield-minus', 'items' => $waivers, 'empty' => 'No pending waiver requests'],
        'wfh' => ['title' => 'Work From Home', 'icon' => 'bi-house-door', 'items' => $wfh, 'empty' => 'No pending Work From Home requests'],
    ];
@endphp

<div class="row">
    <div class="col-12">
        <div class="glass-card">

            <!-- ===== TABS ===== -->
            <div class="approval-tabs" id="approvalTabs">
                @foreach ($tabs as $key => $tab)
                    <button class="tab-btn {{ $loop->first ? 'active' : '' }}" data-tab="{{ $key }}">
                        <i class="bi {{ $tab['icon'] }}"></i> {{ $tab['title'] }}
                        <span class="badge-count">{{ $tab['items']->count() }}</span>
                    </button>
                @endforeach
                <button class="tab-btn" data-tab="history">
                    <i class="bi bi-clock-history"></i> History
                    <span class="badge-count">{{ $history->count() }}</span>
                </button>
            </div>

            @foreach ($tabs as $key => $tab)
                <div class="tab-content {{ $loop->first ? 'active' : '' }}" id="tab-{{ $key }}">

                    <div class="section-header">
                        <h6>{{ $tab['title'] }}</h6>
                        <span class="sub">{{ $tab['items']->count() }} pending</span>
                    </div>

                    @forelse ($tab['items'] as $item)
                        <div class="approval-item"
                            data-employee="{{ $item['employee'] }}"
                            data-date="{{ $item['date'] }}"
                            data-detail="{{ $item['detail'] }}"
                            data-reason="{{ $item['reason'] }}"
                            data-kind="{{ $item['kind'] }}"
                            data-approve-url="{{ route('line_manager.approvals.' . $item['kind'] . '.approve', $item['id']) }}"
                            data-reject-url="{{ route('line_manager.approvals.' . $item['kind'] . '.reject', $item['id']) }}">
                            <div class="info">
                                <div class="avatar-sm">{{ $item['avatar'] }}</div>
                                <div class="details">
                                    <div class="name">{{ $item['employee'] }}</div>
                                    <div class="meta"><i class="bi bi-calendar"></i> {{ $item['date'] }} · {{ $item['detail'] }}</div>
                                    @if ($item['extra'])
                                        <div class="meta"><i class="bi bi-clock"></i> {{ $item['extra'] }}</div>
                                    @endif
                                    @if ($item['document_url'] ?? null)
                                        <div class="meta"><a href="{{ $item['document_url'] }}" target="_blank"><i class="bi bi-paperclip"></i> View document</a></div>
                                    @endif
                                </div>
                                <span class="reason" title="{{ $item['reason'] }}"><i class="bi bi-chat"></i> "{{ $item['reason'] }}"</span>
                            </div>
                            <div class="actions">
                                <button type="button" class="btn-approve" onclick="openModal('approve', this)"><i class="bi bi-check2"></i> Approve</button>
                                <button type="button" class="btn-reject" onclick="openModal('reject', this)"><i class="bi bi-x"></i> Reject</button>
                            </div>
                        </div>
                    @empty
                        <div class="empty-state">
                            <i class="bi bi-check-circle"></i>
                            <h6>{{ $tab['empty'] }}</h6>
                            <p>All requests from your team have been reviewed.</p>
                        </div>
                    @endforelse

                </div>
            @endforeach

            <!-- TAB: HISTORY (Line Manager decision + Admin final decision) -->
            @php
                $statusLabels = [
                    'approved' => 'Approved',
                    'rejected' => 'Rejected',
                    'awaiting_admin' => 'Awaiting Admin',
                    'pending' => 'Pending',
                ];
            @endphp
            <div class="tab-content" id="tab-history">

                <div class="section-header">
                    <h6>History</h6>
                    <span class="sub">{{ $history->count() }} reviewed requests</span>
                </div>

                @forelse ($history as $item)
                    <div class="approval-item history-item">
                        <div class="info">
                            <div class="avatar-sm">{{ $item['avatar'] }}</div>
                            <div class="details">
                                <div class="name">{{ $item['employee'] }}</div>
                                <div class="meta">
                                    <i class="bi bi-tag"></i> {{ $item['type'] }}
                                    · <i class="bi bi-calendar"></i> {{ $item['date'] }} · {{ $item['detail'] }}
                                </div>
                                @if ($item['reason'])
                                    <div class="meta"><i class="bi bi-chat"></i> "{{ $item['reason'] }}"</div>
                                @endif

                                <div class="history-decisions">
                                    @if ($item['lm_status'])
                                        <div class="history-decision">
                                            <span class="history-badge {{ $item['lm_status'] }}">Line Manager: {{ $statusLabels[$item['lm_status']] }}</span>
                                            @if ($item['lm_note'])
                                                <span class="history-note">"{{ $item['lm_note'] }}"</span>
                                            @endif
                                        </div>
                                    @endif

                                    @if ($item['admin_by'] || $item['final_status'] === 'awaiting_admin')
                                        <div class="history-decision">
                                            <span class="history-badge {{ $item['final_status'] }}">Admin: {{ $statusLabels[$item['final_status']] ?? ucfirst($item['final_status']) }}</span>
                                            @if ($item['admin_note'])
                                                <span class="history-note">"{{ $item['admin_note'] }}"</span>
                                            @endif
                                            @if ($item['admin_by'])
                                                <span class="history-by">by {{ $item['admin_by'] }}</span>
                                            @endif
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                        <div class="actions">
                            <span class="history-by">{{ $item['updated_at']?->format('d M Y, h:i A') }}</span>
                        </div>
                    </div>
                @empty
                    <div class="empty-state">
                        <i class="bi bi-clock-history"></i>
                        <h6>No history yet</h6>
                        <p>Requests you review will appear here with the Admin's final decision.</p>
                    </div>
                @endforelse

            </div>

        </div>
    </div>
</div>

<!-- APPROVE MODAL -->
<div class="modal fade modal-glass" id="approveModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" id="approveForm" action="">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-check2-circle" style="color:#22c55e;"></i> Approve Request</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="request-preview">
                        <div class="label">Employee</div>
                        <div class="value" id="approveEmployeeName">—</div>
                        <div class="label" style="margin-top:8px;">Request Details</div>
                        <div class="value" id="approveRequestDetails">—</div>
                        <div class="label" style="margin-top:8px;">Reason</div>
                        <div class="value" id="approveRequestReason">—</div>
                    </div>

                    <div class="form-group">
                        <label>Manager Note <span id="approveNoteHint">(Optional)</span></label>
                        <textarea class="form-control-custom" name="note" id="approveNote" rows="2" maxlength="500"
                            placeholder="Add a note for the employee and Admin..."></textarea>
                    </div>
                    <p id="approveForwardHint" style="font-size:12px; color:var(--text-muted); margin:0;"></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-cancel" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn-save approve"><i class="bi bi-check2"></i> Approve</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- REJECT MODAL -->
<div class="modal fade modal-glass" id="rejectModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" id="rejectForm" action="">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-x-circle" style="color:#ef4444;"></i> Reject Request</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="request-preview">
                        <div class="label">Employee</div>
                        <div class="value" id="rejectEmployeeName">—</div>
                        <div class="label" style="margin-top:8px;">Request Details</div>
                        <div class="value" id="rejectRequestDetails">—</div>
                        <div class="label" style="margin-top:8px;">Reason</div>
                        <div class="value" id="rejectRequestReason">—</div>
                    </div>

                    <div class="form-group">
                        <label>Reason for Rejection <span style="color:#ef4444;">*</span></label>
                        <textarea class="form-control-custom" name="note" id="rejectNote" rows="2" maxlength="500"
                            placeholder="Please provide a reason for rejection..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-cancel" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn-save reject"><i class="bi bi-x"></i> Reject</button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
    // ===== TABS =====
    const tabBtns = document.querySelectorAll('.tab-btn');

    tabBtns.forEach(btn => {
        btn.addEventListener('click', function () {
            tabBtns.forEach(b => b.classList.remove('active'));
            this.classList.add('active');

            document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));

            const target = document.getElementById('tab-' + this.dataset.tab);
            if (target) {
                target.classList.add('active');
            }
        });
    });

    // ===== OPEN APPROVE / REJECT MODAL =====
    function openModal(action, btn) {
        const item = btn.closest('.approval-item');
        const prefix = action === 'approve' ? 'approve' : 'reject';
        const form = document.getElementById(prefix + 'Form');

        form.reset();
        form.action = action === 'approve' ? item.dataset.approveUrl : item.dataset.rejectUrl;

        document.getElementById(prefix + 'EmployeeName').textContent = item.dataset.employee || '—';
        document.getElementById(prefix + 'RequestDetails').textContent = (item.dataset.date || '—') + ' · ' + (item.dataset.detail || '—');
        document.getElementById(prefix + 'RequestReason').textContent = '"' + (item.dataset.reason || '—') + '"';

        if (action === 'approve') {
            // Waiver recommendations require a note; leave and waiver approvals go on to Admin.
            const noteRequired = item.dataset.kind === 'waiver';
            document.getElementById('approveNote').required = noteRequired;
            document.getElementById('approveNoteHint').textContent = noteRequired ? '*' : '(Optional)';
            document.getElementById('approveForwardHint').textContent =
                'After your approval, this request will be sent to Admin for the final decision.';
        }

        new bootstrap.Modal(document.getElementById(prefix + 'Modal')).show();
    }
</script>
@endpush
