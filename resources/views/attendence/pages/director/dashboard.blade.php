@extends('attendence.layouts.app')

@section('title', 'Director Dashboard')
@section('page-title', 'Dashboard')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/manager-css/dashboard.css') }}">
    <style>
        .modal-glass .modal-content { background: var(--bg-card, #ffffff); border: 1px solid var(--border-color, #e5e7eb); border-radius: 16px; box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15); }
        .modal-glass .modal-header { border-bottom: 1px solid var(--border-color, #e5e7eb); padding: 20px 24px; }
        .modal-glass .modal-header .modal-title { font-weight: 700; font-size: 18px; color: var(--text-primary, #0a0e1a); display: flex; align-items: center; gap: 8px; }
        .modal-glass .modal-body { padding: 24px; }
        .modal-glass .modal-footer { border-top: 1px solid var(--border-color, #e5e7eb); padding: 16px 24px; gap: 10px; }
        .form-control-custom { width: 100%; padding: 10px 14px; border: 1px solid var(--border-color, #e5e7eb); border-radius: 8px; font-size: 14px; background: var(--bg-card, #ffffff); color: var(--text-primary, #0a0e1a); font-family: inherit; resize: vertical; }
        .form-control-custom:focus { outline: none; border-color: var(--primary, #3b82f6); box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.10); }
        .btn-save.approve, .btn-save.reject { color: #fff; border: none; padding: 10px 24px; border-radius: 8px; font-weight: 600; font-size: 14px; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; }
        .btn-save.approve { background: #10b981; }
        .btn-save.approve:hover { background: #059669; }
        .btn-save.reject { background: #ef4444; }
        .btn-save.reject:hover { background: #dc2626; }
        .btn-cancel { background: var(--bg-card, #ffffff); border: 1px solid var(--border-color, #e5e7eb); color: var(--text-secondary, #4b5563); padding: 10px 18px; border-radius: 8px; font-weight: 600; font-size: 14px; cursor: pointer; }
        .form-group { margin-bottom: 16px; }
        .form-group label { font-size: 13px; font-weight: 600; color: var(--text-secondary, #4b5563); display: block; margin-bottom: 4px; }
        .btn-approve, .btn-reject { border: none; padding: 6px 12px; border-radius: 6px; cursor: pointer; font-size: 14px; }
        .btn-approve { background: #d1fae5; color: #065f46; }
        .btn-approve:hover { background: #10b981; color: #fff; }
        .btn-reject { background: #fee2e2; color: #991b1b; margin-left: 4px; }
        .btn-reject:hover { background: #ef4444; color: #fff; }
    </style>
@endpush

@section('content')

    <!-- ===== GREETING ===== -->
    <div class="greeting-card">
        <h4>Welcome, {{ Auth::user()->name }} 👋</h4>
        <p>Company attendance at a glance · {{ now()->format('D d M Y') }}</p>
    </div>

    @unless ($hasDirectorProfile)
        <div class="notif-card">
            <div class="notif-left">
                <i class="bi bi-info-circle"></i>
                <span class="notif-title">No employee profile is linked to your account</span>
            </div>
            <div class="notif-right">Ask Admin to add you as an employee with the Director role, so Managers can report to you.</div>
        </div>
    @endunless

    <!-- ===== STATS ===== -->
    <div class="stat-grid">
        <div class="stat-card">
            <div class="number">{{ $employeeCount }}</div>
            <div class="label">Employees</div>
            <div class="sub">active</div>
        </div>
        <div class="stat-card">
            <div class="number">{{ $todayCounts['present'] }}</div>
            <div class="label">Present Today</div>
            <div class="sub">{{ $todayCounts['in_office'] }} in office now · {{ $todayCounts['wfh'] }} WFH</div>
        </div>
        <div class="stat-card">
            <div class="number">{{ $todayCounts['late'] }}</div>
            <div class="label">Late Today</div>
            <div class="sub">{{ $todayCounts['absent'] }} absent · {{ $todayCounts['on_leave'] }} on leave</div>
        </div>
        <div class="stat-card">
            <div class="number">{{ $pendingApprovals->count() }}</div>
            <div class="label">Pending Approvals</div>
            <div class="sub">Manager requests</div>
        </div>
    </div>

    <!-- ===== QUICK ACTIONS ===== -->
    <div class="quick-actions">
        <a href="{{ route('director.approvals') }}" class="quick-action">
            <i class="bi bi-check2-circle"></i>
            <h6>Review approvals</h6>
            <p>{{ $pendingApprovals->count() }} pending {{ \Illuminate\Support\Str::plural('request', $pendingApprovals->count()) }}</p>
        </a>
        <a href="{{ route('director.team.attendance') }}" class="quick-action">
            <i class="bi bi-people"></i>
            <h6>Company attendance</h6>
            <p>Today &amp; history</p>
        </a>
        <a href="{{ route('director.reports') }}" class="quick-action">
            <i class="bi bi-file-earmark-bar-graph"></i>
            <h6>Reports</h6>
            <p>Cycle summary per employee</p>
        </a>
    </div>

    <!-- ===== PENDING APPROVALS ===== -->
    <div class="glass-card">
        <div class="section-header">
            <h6>Pending approvals — Managers</h6>
            <span class="sub">{{ $pendingApprovals->count() }} requests</span>
        </div>

        <div style="overflow-x:auto;">
            <table class="approval-table">
                <thead>
                    <tr>
                        <th>Manager</th>
                        <th>Type</th>
                        <th>Details</th>
                        <th style="text-align:right;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($pendingApprovals as $req)
                        <tr>
                            <td><strong>{{ $req['employee'] }}</strong><br><span style="font-size:11px; color:var(--text-muted);">{{ $req['date'] }}</span></td>
                            <td>{{ $req['type'] }}</td>
                            <td>{{ $req['detail'] }}</td>
                            <td style="text-align:right; white-space:nowrap;">
                                <button type="button" class="btn-approve" title="Approve"
                                    onclick="openDecision('approveNoteModal', 'approveNoteForm', '{{ route('director.approvals.'.$req['kind'].'.approve', $req['id']) }}')">
                                    <i class="bi bi-check2"></i>
                                </button>
                                <button type="button" class="btn-reject" title="Reject"
                                    onclick="openDecision('rejectReasonModal', 'rejectReasonForm', '{{ route('director.approvals.'.$req['kind'].'.reject', $req['id']) }}')">
                                    <i class="bi bi-x"></i>
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" style="text-align:center; padding:20px;">No pending requests from Managers.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- ===== 30-DAY AVERAGES ===== -->
    <div class="glass-card">
        <div class="avg-grid">
            <div class="avg-card">
                <div class="avg-number">{{ $attendanceRate }}%</div>
                <div class="avg-label">company — 30 days</div>
            </div>
            <div class="avg-card">
                <div class="avg-number">{{ $avgHours }}h</div>
                <div class="avg-label">avg hours / day</div>
            </div>
        </div>
    </div>

    <!-- ===== THIS MONTH ===== -->
    <div class="glass-card">
        <div class="section-header">
            <h6>Company — This Month — Tap any card for full list</h6>
        </div>

        <div class="monthly-grid">
            <a href="{{ route('director.attendance.breakdown', ['type' => 'missing-punch']) }}" class="monthly-item">
                <div class="num">{{ $monthly['missing-punch'] }}</div>
                <div class="label">Missing Punch</div>
            </a>
            <a href="{{ route('director.attendance.breakdown', ['type' => 'half-day']) }}" class="monthly-item">
                <div class="num">{{ $monthly['half-day'] }}</div>
                <div class="label">Half Day</div>
            </a>
            <a href="{{ route('director.attendance.breakdown', ['type' => 'early-out']) }}" class="monthly-item">
                <div class="num">{{ $monthly['early-out'] }}</div>
                <div class="label">Early Out</div>
            </a>
            <a href="{{ route('director.attendance.breakdown', ['type' => 'absent']) }}" class="monthly-item">
                <div class="num">{{ $monthly['absent'] }}</div>
                <div class="label">Absent</div>
            </a>
            <a href="{{ route('director.attendance.breakdown', ['type' => 'on-leave']) }}" class="monthly-item">
                <div class="num">{{ $monthly['on-leave'] }}</div>
                <div class="label">On Leave</div>
            </a>
        </div>
    </div>

    <!-- ===== DEPARTMENTS TODAY ===== -->
    <div class="glass-card">
        <div class="section-header">
            <h6>Departments today</h6>
            <span class="sub">{{ $departments->count() }} departments</span>
        </div>

        <div class="team-table">
            <table>
                <thead>
                    <tr>
                        <th>Department</th>
                        <th>Employees</th>
                        <th>Present</th>
                        <th>Late</th>
                        <th>Absent</th>
                        <th>On Leave</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($departments as $dept)
                        <tr>
                            <td><strong>{{ $dept['name'] }}</strong></td>
                            <td>{{ $dept['total'] }}</td>
                            <td>{{ $dept['present'] }}</td>
                            <td>{{ $dept['late'] }}</td>
                            <td>{{ $dept['absent'] }}</td>
                            <td>{{ $dept['on_leave'] }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" style="text-align:center; padding:20px;">No employees found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- ===== EVERYONE TODAY ===== -->
    <div class="glass-card">
        <div class="section-header">
            <h6>Everyone today</h6>
            <span class="sub">{{ now()->format('D d M') }}</span>
        </div>

        <div class="team-table">
            <table>
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th>Department</th>
                        <th>Status</th>
                        <th>IN</th>
                        <th>OUT</th>
                        <th>Timeline</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($todayRows as $t)
                        <tr>
                            <td><strong>{{ $t['name'] }}</strong></td>
                            <td>{{ $t['department'] }}</td>
                            <td><span class="status-{{ \Illuminate\Support\Str::slug($t['status']) }}">{{ $t['status'] }}</span></td>
                            <td>{{ $t['in'] }}</td>
                            <td>{{ $t['out'] }}</td>
                            <td>{{ $t['timeline'] }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" style="text-align:center; padding:20px;">No employees found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- ===== APPROVE MODAL ===== -->
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
                            <label>Note (optional)</label>
                            <textarea name="note" class="form-control-custom" rows="3" maxlength="500"
                                placeholder="This note will be visible to the Manager and Admin..."></textarea>
                        </div>
                        <p style="font-size:12px; color:var(--text-muted); margin:0;">
                            Your decision is final. Admin will be informed and does not need to approve it again.
                        </p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn-cancel" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn-save approve"><i class="bi bi-check2"></i> Approve</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ===== REJECT MODAL ===== -->
    <div class="modal fade modal-glass" id="rejectReasonModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="POST" id="rejectReasonForm" action="">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="bi bi-x-circle" style="color:#ef4444;"></i> Reject Request</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label>Reason for rejection <span style="color:#ef4444;">*</span></label>
                            <textarea name="note" class="form-control-custom" rows="3" maxlength="500" required
                                placeholder="This reason will be visible to the Manager and Admin..."></textarea>
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

    <script>
        function openDecision(modalId, formId, actionUrl) {
            const form = document.getElementById(formId);
            form.reset();
            form.action = actionUrl;
            new bootstrap.Modal(document.getElementById(modalId)).show();
        }
    </script>
@endsection
