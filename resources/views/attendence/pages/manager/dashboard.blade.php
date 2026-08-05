@extends('attendence.layouts.app')

@section('title', 'Manager Dashboard')
@section('page-title', 'Dashboard')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/manager-css/dashboard.css') }}">
    <style>
        .modal-glass .modal-content {
            background: var(--bg-card, #ffffff);
            border: 1px solid var(--border-color, #e5e7eb);
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
        }

        .modal-glass .modal-header {
            border-bottom: 1px solid var(--border-color, #e5e7eb);
            padding: 20px 24px;
        }

        .modal-glass .modal-header .modal-title {
            font-weight: 700;
            font-size: 18px;
            color: var(--text-primary, #0a0e1a);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .modal-glass .modal-body {
            padding: 24px;
        }

        .modal-glass .modal-footer {
            border-top: 1px solid var(--border-color, #e5e7eb);
            padding: 16px 24px;
            gap: 10px;
        }

        .request-preview {
            background: var(--bg-body, #f0f2f5);
            border-radius: 10px;
            padding: 16px;
            margin-bottom: 16px;
            border: 1px solid var(--border-color, #e5e7eb);
        }

        .request-preview .label {
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            color: var(--text-muted, #6b7280);
        }

        .request-preview .value {
            font-size: 15px;
            font-weight: 500;
            color: var(--text-primary, #0a0e1a);
            margin-top: 2px;
        }

        .form-control-custom {
            width: 100%;
            padding: 10px 14px;
            border: 1px solid var(--border-color, #e5e7eb);
            border-radius: 8px;
            font-size: 14px;
            background: var(--bg-card, #ffffff);
            color: var(--text-primary, #0a0e1a);
            transition: all 0.2s ease;
            font-family: inherit;
            resize: vertical;
        }

        .form-control-custom:focus {
            outline: none;
            border-color: var(--primary, #3b82f6);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.10);
        }

        .btn-save.approve {
            background: #10b981;
            color: #fff;
            border: none;
            padding: 10px 24px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .btn-save.approve:hover {
            background: #059669;
        }

        .btn-save.reject {
            background: #ef4444;
            color: #fff;
            border: none;
            padding: 10px 24px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .btn-save.reject:hover {
            background: #dc2626;
        }

        .btn-cancel-modal {
            background: var(--bg-card, #ffffff);
            border: 1px solid var(--border-color, #e5e7eb);
            color: var(--text-secondary, #4b5563);
            padding: 10px 18px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .btn-cancel-modal:hover {
            background: var(--hover-bg, #f3f4f6);
        }

        .form-group {
            margin-bottom: 16px;
        }

        .form-group label {
            font-size: 13px;
            font-weight: 600;
            color: var(--text-secondary, #4b5563);
            display: block;
            margin-bottom: 4px;
        }

        .btn-approve {
            background: #d1fae5;
            color: #065f46;
            border: none;
            padding: 6px 12px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.2s ease;
        }

        .btn-approve:hover {
            background: #10b981;
            color: #fff;
        }

        .btn-reject {
            background: #fee2e2;
            color: #991b1b;
            border: none;
            padding: 6px 12px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.2s ease;
            margin-left: 4px;
        }

        .btn-reject:hover {
            background: #ef4444;
            color: #fff;
        }

        .btn-approve i,
        .btn-reject i {
            font-size: 16px;
        }
    </style>
@endpush

@section('content')

    <div class="greeting-card">
        <h4>Welcome, {{ session('user_name') }} 👋</h4>
        <p>Your team attendance at a glance</p>
    </div>

    <div class="notif-card">
        <div class="notif-left">
            <i class="bi bi-bell"></i>
            <span class="notif-title">Notifications</span>
            <span class="notif-count">3 new</span>
        </div>
        <div class="notif-right">
            <i class="bi bi-check-circle"></i> You have pending approvals
        </div>
    </div>

    <div class="stat-grid">
        <div class="stat-card">
            <div class="number">5</div>
            <div class="label">Team Size</div>
            <div class="sub">direct reports</div>
        </div>
        <div class="stat-card">
            <div class="number">3</div>
            <div class="label">In Office Now</div>
            <div class="sub">live from devices</div>
        </div>
        <div class="stat-card">
            <div class="number">1</div>
            <div class="label">Late Today</div>
            <div class="sub">1 late - 0 absent</div>
        </div>
        <div class="stat-card">
            <div class="number">2</div>
            <div class="label">Pending Approvals</div>
            <div class="sub">2 requests</div>
        </div>
    </div>

    <div class="quick-actions">
        <a href="{{ route('manager.approvals') }}" class="quick-action">
            <i class="bi bi-check2-circle"></i>
            <h6>Review approvals</h6>
            <p>2 pending requests</p>
        </a>
        <a href="{{ route('manager.team.attendance') }}" class="quick-action">
            <i class="bi bi-people"></i>
            <h6>Team attendance</h6>
            <p>Today &amp; history</p>
        </a>
        <a href="{{ route('manager.attendance.patterns') }}" class="quick-action">
            <i class="bi bi-graph-up"></i>
            <h6>Attendance patterns</h6>
            <p>Spot trends early</p>
        </a>
    </div>

    <div class="glass-card">
        <div class="section-header">
            <h6>Pending approvals</h6>
            <span class="sub">2 requests</span>
        </div>

        <div style="overflow-x:auto;">
            <table class="approval-table">
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th>Type</th>
                        <th>Details</th>
                        <th style="text-align:right;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>
                            <strong>John Doe</strong>
                            <br>
                            <span style="font-size:11px; color:var(--text-muted);">22 Jul 2026</span>
                        </td>
                        <td>Leave Request</td>
                        <td>3 days</td>
                        <td style="text-align:right; white-space:nowrap;">
                            <button class="btn-approve" onclick="openApproveModal('John Doe', 'Leave Request', '3 days', 'Annual leave request for 3 days')">
                                <i class="bi bi-check2"></i>
                            </button>
                            <button class="btn-reject" onclick="openRejectModal('John Doe', 'Leave Request', '3 days', 'Annual leave request for 3 days')">
                                <i class="bi bi-x"></i>
                            </button>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <strong>Jane Smith</strong>
                            <br>
                            <span style="font-size:11px; color:var(--text-muted);">22 Jul 2026</span>
                        </td>
                        <td>Correction Request</td>
                        <td>Missing punch</td>
                        <td style="text-align:right; white-space:nowrap;">
                            <button class="btn-approve" onclick="openApproveModal('Jane Smith', 'Correction Request', 'Missing punch', 'Correction for 15 Jul 2026 missing punch')">
                                <i class="bi bi-check2"></i>
                            </button>
                            <button class="btn-reject" onclick="openRejectModal('Jane Smith', 'Correction Request', 'Missing punch', 'Correction for 15 Jul 2026 missing punch')">
                                <i class="bi bi-x"></i>
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <div class="glass-card">
        <div class="avg-grid">
            <div class="avg-card">
                <div class="avg-number">87%</div>
                <div class="avg-label">team — 30 days</div>
            </div>
            <div class="avg-card">
                <div class="avg-number">7.8h</div>
                <div class="avg-label">avg hours / day</div>
            </div>
        </div>
    </div>

    <div class="glass-card">
        <div class="section-header">
            <h6>Team — This Month — Tap any card for full list</h6>
            <span class="sub">0 new</span>
        </div>

        <div class="monthly-grid">
            <a href="{{ route('manager.attendance.breakdown', ['type' => 'missing-punch']) }}" class="monthly-item">
                <div class="num">2</div>
                <div class="label">Missing Punch</div>
                <div class="sub">2 this period</div>
            </a>
            <a href="{{ route('manager.attendance.breakdown', ['type' => 'half-day']) }}" class="monthly-item">
                <div class="num">1</div>
                <div class="label">Half Day</div>
                <div class="sub">1 this period</div>
            </a>
            <a href="{{ route('manager.attendance.breakdown', ['type' => 'early-out']) }}" class="monthly-item">
                <div class="num">0</div>
                <div class="label">Early Out</div>
                <div class="sub">None this period</div>
            </a>
            <a href="{{ route('manager.attendance.breakdown', ['type' => 'absent']) }}" class="monthly-item">
                <div class="num">3</div>
                <div class="label">Absent</div>
                <div class="sub">3 this period</div>
            </a>
            <a href="{{ route('manager.attendance.breakdown', ['type' => 'on-leave']) }}" class="monthly-item">
                <div class="num">2</div>
                <div class="label">On Leave</div>
                <div class="sub">2 this period</div>
            </a>
        </div>
    </div>

    <div class="glass-card">
        <div class="section-header">
            <h6>Team today</h6>
            <span class="sub">Wed 22 Jul</span>
        </div>

        <div class="team-table">
            <table>
                <thead>
                    <tr>
                        <th>Member</th>
                        <th>Status</th>
                        <th>IN</th>
                        <th>OUT</th>
                        <th>Timeline</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong>John Doe</strong></td>
                        <td><span class="status-present">Present</span></td>
                        <td>09:00</td>
                        <td>18:00</td>
                        <td>On Time</td>
                    </tr>
                    <tr>
                        <td><strong>Jane Smith</strong></td>
                        <td><span class="status-late">Late</span></td>
                        <td>09:35</td>
                        <td>18:00</td>
                        <td>Late by 35m</td>
                    </tr>
                    <tr>
                        <td><strong>Mike Johnson</strong></td>
                        <td><span class="status-present">Present</span></td>
                        <td>08:55</td>
                        <td>17:30</td>
                        <td>On Time</td>
                    </tr>
                    <tr>
                        <td><strong>Sarah Lee</strong></td>
                        <td><span class="status-absent">Absent</span></td>
                        <td>—</td>
                        <td>—</td>
                        <td>No Punch</td>
                    </tr>
                    <tr>
                        <td><strong>David Khan</strong></td>
                        <td><span class="status-present">Present</span></td>
                        <td>09:10</td>
                        <td>18:30</td>
                        <td>On Time</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

@endsection


<div class="modal fade modal-glass" id="approveModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-check2-circle" style="color:#22c55e;"></i> Approve Request</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="approveForm">
                    <div class="request-preview">
                        <div class="label">Employee</div>
                        <div class="value" id="approveEmployeeName">—</div>
                        <div class="label" style="margin-top:8px;">Request Type</div>
                        <div class="value" id="approveRequestType">—</div>
                        <div class="label" style="margin-top:8px;">Details</div>
                        <div class="value" id="approveRequestDetails">—</div>
                        <div class="label" style="margin-top:8px;">Reason</div>
                        <div class="value" id="approveRequestReason">—</div>
                    </div>
                    <div class="form-group">
                        <label>Manager Note (Optional)</label>
                        <textarea class="form-control-custom" id="approveNote" rows="2" placeholder="Add a note for the employee..."></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-cancel-modal" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn-save approve" onclick="submitApproval('approve')">
                    <i class="bi bi-check2"></i> Approve
                </button>
            </div>
        </div>
    </div>
</div>


<div class="modal fade modal-glass" id="rejectModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-x-circle" style="color:#ef4444;"></i> Reject Request</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="rejectForm">
                    <div class="request-preview">
                        <div class="label">Employee</div>
                        <div class="value" id="rejectEmployeeName">—</div>
                        <div class="label" style="margin-top:8px;">Request Type</div>
                        <div class="value" id="rejectRequestType">—</div>
                        <div class="label" style="margin-top:8px;">Details</div>
                        <div class="value" id="rejectRequestDetails">—</div>
                        <div class="label" style="margin-top:8px;">Reason</div>
                        <div class="value" id="rejectRequestReason">—</div>
                    </div>
                    <div class="form-group">
                        <label>Reason for Rejection <span style="color:#ef4444;">*</span></label>
                        <textarea class="form-control-custom" id="rejectNote" rows="2" placeholder="Please provide a reason for rejection..." required></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-cancel-modal" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn-save reject" onclick="submitApproval('reject')">
                    <i class="bi bi-x"></i> Reject
                </button>
            </div>
        </div>
    </div>
</div>


<script>
    function showToast(type, message) {
        if (typeof toastr !== 'undefined') {
            if (type === 'success') toastr.success(message);
            else if (type === 'error') toastr.error(message);
            else if (type === 'warning') toastr.warning(message);
            else if (type === 'info') toastr.info(message);
        } else {
            alert(message);
        }
    }

    function openApproveModal(employee, type, details, reason) {
        document.getElementById('approveEmployeeName').textContent = employee;
        document.getElementById('approveRequestType').textContent = type;
        document.getElementById('approveRequestDetails').textContent = details;
        document.getElementById('approveRequestReason').textContent = reason || 'N/A';
        document.getElementById('approveNote').value = '';
        var modal = new bootstrap.Modal(document.getElementById('approveModal'));
        modal.show();
    }

    function openRejectModal(employee, type, details, reason) {
        document.getElementById('rejectEmployeeName').textContent = employee;
        document.getElementById('rejectRequestType').textContent = type;
        document.getElementById('rejectRequestDetails').textContent = details;
        document.getElementById('rejectRequestReason').textContent = reason || 'N/A';
        document.getElementById('rejectNote').value = '';
        var modal = new bootstrap.Modal(document.getElementById('rejectModal'));
        modal.show();
    }

    function submitApproval(action) {
        var note = '';
        var employee = '';

        if (action === 'approve') {
            note = document.getElementById('approveNote').value.trim();
            employee = document.getElementById('approveEmployeeName').textContent;
            
            if (!confirm('Are you sure you want to approve this request for ' + employee + '?')) {
                return;
            }
            
            showToast('success', '✅ Request approved for ' + employee + '!');
            var modal = bootstrap.Modal.getInstance(document.getElementById('approveModal'));
            modal.hide();
            removeRow(employee);
        } else if (action === 'reject') {
            note = document.getElementById('rejectNote').value.trim();
            employee = document.getElementById('rejectEmployeeName').textContent;
            
            if (!note) {
                showToast('error', '❌ Please provide a reason for rejection.');
                document.getElementById('rejectNote').focus();
                document.getElementById('rejectNote').style.borderColor = '#ef4444';
                return;
            }
            
            if (!confirm('Are you sure you want to reject this request for ' + employee + '?')) {
                return;
            }
            
            showToast('error', '❌ Request rejected for ' + employee + '. Reason: ' + note);
            var modal = bootstrap.Modal.getInstance(document.getElementById('rejectModal'));
            modal.hide();
            removeRow(employee);
        }
    }

    function removeRow(employeeName) {
        var rows = document.querySelectorAll('.approval-table tbody tr');
        rows.forEach(function(row) {
            var nameCell = row.querySelector('td strong');
            if (nameCell && nameCell.textContent.trim() === employeeName) {
                row.remove();
            }
        });

        var countSpan = document.querySelector('.section-header .sub');
        if (countSpan) {
            var remaining = document.querySelectorAll('.approval-table tbody tr').length;
            countSpan.textContent = remaining + ' requests';
        }
    }
</script>