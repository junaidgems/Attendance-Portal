@extends('attendence.layouts.app')

@section('title', 'Work From Home')
@section('page-title', 'Work From Home')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/leave.css') }}">
@endpush

@section('content')

    @php
        $tabs = [
            'pending' => ['label' => 'Pending', 'rows' => $pending],
            'approved' => ['label' => 'Approved', 'rows' => $approved],
            'rejected' => ['label' => 'Rejected', 'rows' => $rejected],
        ];
    @endphp

    <!-- TODAY ON WFH -->
    <div class="row">
        <div class="col-12">
            <div class="glass-card">
                <div class="section-header">
                    <div>
                        <h6><i class="bi bi-house-door"></i> Working from home today</h6>
                        <span class="subtitle">{{ now()->format('D d M Y') }} · {{ $todayWfh->count() }} {{ $todayWfh->count() === 1 ? 'employee' : 'employees' }}</span>
                    </div>
                </div>

                @if ($todayWfh->isNotEmpty())
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>Employee</th><th>Status</th><th>Check In</th><th>Check Out</th><th>Late</th><th>Work Summary</th><th>IP</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($todayWfh as $row)
                                    <tr>
                                        <td>
                                            <strong>{{ $row['employee'] }}</strong><br>
                                            <span style="font-size:11px; color:var(--text-muted);">{{ $row['department'] }}</span>
                                        </td>
                                        <td>
                                            <span style="font-size:12px; font-weight:600; color:{{ $row['on_site'] ? '#16a34a' : ($row['check_in'] ? '#2563eb' : '#ef4444') }};">
                                                @if ($row['on_site'])<i class="bi bi-building"></i>@endif {{ $row['state'] }}
                                            </span>
                                        </td>
                                        <td>
                                            @if ($row['check_in'])
                                                {{ \Carbon\Carbon::parse($row['check_in'])->format('h:i A') }}
                                                @if ($row['source'] === 'device')
                                                    <br><span style="font-size:11px; color:var(--text-muted);">Office machine</span>
                                                @endif
                                            @else
                                                <span style="color:#ef4444;">Not checked in</span>
                                            @endif
                                        </td>
                                        <td>{{ $row['check_out'] ? \Carbon\Carbon::parse($row['check_out'])->format('h:i A') : '—' }}</td>
                                        <td>{{ $row['late_minutes'] ? $row['late_minutes'] . 'm' : '—' }}</td>
                                        <td style="font-size:12px; max-width:320px; white-space:normal;">{{ $row['summary'] ?? '—' }}</td>
                                        <td style="font-size:11px; color:var(--text-muted);">{{ $row['ip'] ?? '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="empty-state"><i class="bi bi-house"></i><p>Nobody is working from home today.</p></div>
                @endif
            </div>
        </div>
    </div>

    <!-- REQUESTS -->
    <div class="row" style="margin-top: 20px;">
        <div class="col-12">
            <div class="glass-card">
                <div class="section-header">
                    <div>
                        <h6>Work From Home Requests</h6>
                        <span class="subtitle">Employee → Line Manager → Admin (final)</span>
                    </div>
                </div>

                <ul class="nav nav-tabs nav-tabs-custom" role="tablist">
                    @foreach ($tabs as $key => $tab)
                        <li class="nav-item" role="presentation">
                            <button class="nav-link {{ $loop->first ? 'active' : '' }}" data-bs-toggle="tab"
                                data-bs-target="#wfh-{{ $key }}" type="button" role="tab">
                                {{ $tab['label'] }} <span class="badge-count">{{ $tab['rows']->count() }}</span>
                            </button>
                        </li>
                    @endforeach
                </ul>

                <div class="tab-content">
                    @foreach ($tabs as $key => $tab)
                        <div class="tab-pane fade {{ $loop->first ? 'show active' : '' }}" id="wfh-{{ $key }}" role="tabpanel">
                            @if ($tab['rows']->isNotEmpty())
                                <div class="table-wrap">
                                    <table>
                                        <thead>
                                            <tr>
                                                <th>Employee</th><th>Dates</th><th>Days</th><th>Reason</th><th>Line Manager</th><th>Status</th>
                                                <th style="text-align:right;">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($tab['rows'] as $r)
                                                <tr>
                                                    <td>
                                                        <strong>{{ $r->employee?->full_name ?? '-' }}</strong><br>
                                                        <span style="font-size:11px; color:var(--text-muted);">{{ $r->employee?->department->name ?? '-' }}</span>
                                                    </td>
                                                    <td>{{ $r->dateLabel() }}</td>
                                                    <td>{{ $r->days }}</td>
                                                    <td style="max-width:260px; white-space:normal;">{{ $r->reason }}</td>
                                                    <td style="font-size:12px; color:var(--text-muted);">
                                                        @if ($r->manager_status === 'not_required')
                                                            Not required
                                                        @elseif ($r->manager_status === 'pending')
                                                            Waiting for {{ $r->manager?->full_name ?? 'Line Manager' }}
                                                        @else
                                                            <strong>{{ ucfirst($r->manager_status) }}</strong>
                                                            {{ $r->manager?->full_name ? 'by ' . $r->manager->full_name : '' }}
                                                            @if ($r->manager_note)
                                                                <br>"{{ $r->manager_note }}"
                                                            @endif
                                                        @endif
                                                    </td>
                                                    <td>
                                                        <span class="status-badge-sm {{ $r->status }}">{{ ucfirst($r->status) }}</span>
                                                        @if ($r->status === 'pending')
                                                            <br><span style="font-size:11px; color:var(--text-muted);">{{ $r->stageLabel() }}</span>
                                                        @elseif ($r->admin_note)
                                                            <br><span style="font-size:11px; color:var(--text-muted);">"{{ $r->admin_note }}"</span>
                                                        @endif
                                                    </td>
                                                    <td style="text-align:right; white-space:nowrap;">
                                                        @if ($r->status === 'pending')
                                                            {{-- Admin can decide right away, even before the Line Manager / Manager reviews it --}}
                                                            <button type="button" class="btn-outline-glass" style="color:#22c55e; border-color:#22c55e;"
                                                                onclick="openWfhApprove('{{ route('admin.wfh.approve', $r->id) }}', {{ $r->isAwaitingManager() ? 'true' : 'false' }})"
                                                                title="Approve"><i class="bi bi-check2"></i></button>
                                                            <button type="button" class="btn-outline-glass danger"
                                                                onclick="openWfhReject('{{ route('admin.wfh.reject', $r->id) }}')" title="Reject"><i class="bi bi-x"></i></button>
                                                            @if ($r->isAwaitingManager())
                                                                <br><span style="font-size:11px; color:var(--text-muted);">
                                                                    {{ $r->manager?->full_name ?? 'Line Manager' }} hasn't reviewed yet
                                                                </span>
                                                            @endif
                                                        @else
                                                            {{-- Admin can change the decision later, e.g. the employee came to the office after all --}}
                                                            @if ($r->status === 'approved')
                                                                <button type="button" class="btn-outline-glass danger"
                                                                    onclick="openWfhReject('{{ route('admin.wfh.reject', $r->id) }}', true)"
                                                                    title="Change decision to rejected"><i class="bi bi-arrow-repeat"></i> Reject</button>
                                                            @else
                                                                <button type="button" class="btn-outline-glass" style="color:#22c55e; border-color:#22c55e;"
                                                                    onclick="openWfhApprove('{{ route('admin.wfh.approve', $r->id) }}', false, true)"
                                                                    title="Change decision to approved"><i class="bi bi-arrow-repeat"></i> Approve</button>
                                                            @endif
                                                            <br><span style="font-size:12px; color:var(--text-muted);">
                                                                {{ $r->reviewed_by ?? '-' }}{{ $r->reviewed_at ? ' · ' . $r->reviewed_at->format('d M Y') : '' }}
                                                            </span>
                                                        @endif
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @else
                                <div class="empty-state"><i class="bi bi-inbox"></i><p>No {{ strtolower($tab['label']) }} requests.</p></div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    <!-- APPROVE MODAL -->
    <div class="modal fade modal-glass" id="wfhApproveModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="POST" id="wfhApproveForm" action="">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="bi bi-check2-circle" style="color:#22c55e;"></i> Approve Work From Home</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p id="wfhSkipHint" style="font-size:12px; color:#d97706; margin:0 0 10px;">
                            <i class="bi bi-info-circle"></i> The Line Manager / Manager has not reviewed this yet. Approving now
                            finalises it and removes it from their queue (they will be notified).
                        </p>
                        <p class="wfh-change-hint" style="font-size:12px; color:#d97706; margin:0 0 10px;">
                            <i class="bi bi-arrow-repeat"></i> This request was rejected earlier. Approving it changes the decision;
                            the employee and their Line Manager / Manager will be notified.
                        </p>
                        <div class="form-group">
                            <label>Admin note (optional)</label>
                            <textarea name="note" class="form-control-custom" rows="3" maxlength="500"
                                placeholder="This note will be visible to the employee..."></textarea>
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

    <!-- REJECT MODAL -->
    <div class="modal fade modal-glass" id="wfhRejectModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="POST" id="wfhRejectForm" action="">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="bi bi-x-circle" style="color:#ef4444;"></i> Reject Work From Home</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p class="wfh-change-hint" style="font-size:12px; color:#d97706; margin:0 0 10px;">
                            <i class="bi bi-arrow-repeat"></i> This request was approved earlier. Rejecting it withdraws the approval
                            (e.g. the employee came to the office); the employee and their Line Manager / Manager will be notified.
                        </p>
                        <div class="form-group">
                            <label>Reason for rejection</label>
                            <textarea name="reason" class="form-control-custom" rows="3" maxlength="500" required
                                placeholder="This reason will be visible to the employee..."></textarea>
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
        function openWfhApprove(actionUrl, awaitingManager, isChange = false) {
            const form = document.getElementById('wfhApproveForm');
            form.action = actionUrl;
            form.reset();
            document.getElementById('wfhSkipHint').style.display = awaitingManager ? '' : 'none';
            form.querySelector('.wfh-change-hint').style.display = isChange ? '' : 'none';
            new bootstrap.Modal(document.getElementById('wfhApproveModal')).show();
        }

        function openWfhReject(actionUrl, isChange = false) {
            const form = document.getElementById('wfhRejectForm');
            form.action = actionUrl;
            form.reset();
            form.querySelector('.wfh-change-hint').style.display = isChange ? '' : 'none';
            new bootstrap.Modal(document.getElementById('wfhRejectModal')).show();
        }
    </script>
@endpush
