@extends('attendence.layouts.app')

@section('title', 'Leave')
@section('page-title', 'Leave')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/employee/leave.css') }}">

@endpush

@section('content')


<!-- LEAVE BALANCES                            -->

<div class="glass-card">
    <div class="page-header">
        <h4>Leave balances · 2026</h4>
    </div>

    <div class="leave-balance-grid">
        <div class="leave-balance-card">
            <span class="icon">🏖️</span>
            <div class="label">Annual Leave</div>
            <div class="balance">14</div>
            <div class="total">/ 14 d</div>
            <div class="taken">0 taken this year</div>
        </div>
        <div class="leave-balance-card">
            <span class="icon">🤒</span>
            <div class="label">Sick Leave</div>
            <div class="balance">8</div>
            <div class="total">/ 8 d</div>
            <div class="taken">0 taken this year</div>
        </div>
        <div class="leave-balance-card">
            <span class="icon">🎯</span>
            <div class="label">Casual Leave</div>
            <div class="balance">10</div>
            <div class="total">/ 10 d</div>
            <div class="taken">0 taken this year</div>
        </div>
    </div>
</div>


<!-- LEAVE REQUESTS TABLE                      -->

<div class="glass-card">

    <div class="top-actions">
        <span style="font-size:13px; color:var(--text-muted);">My leave requests · 3 total</span>
        <button class="btn-primary-glass" data-bs-toggle="modal" data-bs-target="#applyLeaveModal">
            <i class="bi bi-plus"></i> Apply for leave
        </button>
    </div>

    <div style="overflow-x:auto;">
        <table class="leave-table">
            <thead>
                <tr>
                    <th>Type</th>
                    <th>Dates</th>
                    <th>Days</th>
                    <th>Reason</th>
                    <th>Status</th>
                    <th>Note</th>
                </tr>
            </thead>
            <tbody>
                <!-- Dummy Data 1 -->
                <tr>
                    <td><strong>Annual Leave</strong></td>
                    <td>20 Jul – 22 Jul 2026</td>
                    <td>3</td>
                    <td>Family event</td>
                    <td><span class="status-badge pending">Pending</span></td>
                    <td style="font-size:12px; color:var(--text-muted);">—</td>
                </tr>
                <!-- Dummy Data 2 -->
                <tr>
                    <td><strong>Sick Leave</strong></td>
                    <td>15 Jul 2026</td>
                    <td>1</td>
                    <td>Doctor appointment</td>
                    <td><span class="status-badge approved">Approved</span></td>
                    <td style="font-size:12px; color:var(--text-muted);">Approved</td>
                </tr>
                <!-- Dummy Data 3 -->
                <tr>
                    <td><strong>Casual Leave</strong></td>
                    <td>10 Jul 2026</td>
                    <td>1</td>
                    <td>Personal work</td>
                    <td><span class="status-badge rejected">Rejected</span></td>
                    <td style="font-size:12px; color:var(--text-muted);">Insufficient balance</td>
                </tr>

                <!-- Empty State (Uncomment if needed) -->
                <!--
                <tr>
                    <td colspan="6">
                        <div class="empty-state">
                            <i class="bi bi-inbox"></i>
                            <p>No leave requests yet.</p>
                        </div>
                    </td>
                </tr>
                -->
            </tbody>
        </table>
    </div>

</div>

<!-- APPLY FOR LEAVE MODAL                     -->

<div class="modal fade modal-glass" id="applyLeaveModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-calendar-plus" style="color:var(--primary);"></i> Apply for leave</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="leaveForm">
                    <div class="form-group">
                        <label>Leave Type</label>
                        <select class="form-control-custom" id="leaveType" onchange="updateLeaveInfo()">
                            <option value="annual">Annual Leave · 14 left</option>
                            <option value="sick">Sick Leave · 8 left</option>
                            <option value="casual">Casual Leave · 10 left</option>
                        </select>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>From</label>
                                <input type="date" class="form-control-custom" id="fromDate" value="{{ date('Y-m-d') }}" onchange="calculateDays()">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>To</label>
                                <input type="date" class="form-control-custom" id="toDate" value="{{ date('Y-m-d') }}" onchange="calculateDays()">
                            </div>
                        </div>
                    </div>

                    <div class="checkbox-group">
                        <input type="checkbox" class="form-control-custom" id="halfDay" style="width:auto;">
                        <label for="halfDay">Half day (single day only)</label>
                    </div>

                    <div class="form-group">
                        <label>Reason</label>
                        <textarea class="form-control-custom" rows="2" placeholder="Reason for leave..."></textarea>
                    </div>

                    <div class="leave-info" id="leaveInfo">
                        <span id="daysCount">0</span> working days requested · <span id="availableDays">14</span> available in <span id="leaveTypeName">Annual Leave</span>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-cancel" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn-save" onclick="submitLeave()">
                    <i class="bi bi-check2"></i> Submit request
                </button>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
    // ==========================================
    // LEAVE TYPE DATA
    // ==========================================
    const leaveData = {
        'annual': { name: 'Annual Leave', available: 14 },
        'sick': { name: 'Sick Leave', available: 8 },
        'casual': { name: 'Casual Leave', available: 10 }
    };

    // ==========================================
    // CALCULATE WORKING DAYS
    // ==========================================
    function calculateDays() {
        const from = document.getElementById('fromDate').value;
        const to = document.getElementById('toDate').value;

        if (!from || !to) {
            document.getElementById('daysCount').textContent = '0';
            return;
        }

        const start = new Date(from);
        const end = new Date(to);
        let count = 0;
        const current = new Date(start);

        while (current <= end) {
            const day = current.getDay();
            if (day !== 0 && day !== 6) { // Exclude weekends
                count++;
            }
            current.setDate(current.getDate() + 1);
        }

        document.getElementById('daysCount').textContent = count;
    }

    // ==========================================
    // UPDATE LEAVE INFO
    // ==========================================
    function updateLeaveInfo() {
        const type = document.getElementById('leaveType').value;
        const data = leaveData[type] || leaveData['annual'];

        document.getElementById('availableDays').textContent = data.available;
        document.getElementById('leaveTypeName').textContent = data.name;
        calculateDays();
    }

    // ==========================================
    // SUBMIT LEAVE
    // ==========================================
    function submitLeave() {
        const form = document.getElementById('leaveForm');
        const from = document.getElementById('fromDate').value;
        const to = document.getElementById('toDate').value;
        const reason = form.querySelector('textarea').value;

        if (!from || !to) {
            toastr.warning('Please select dates.');
            return;
        }

        if (!reason.trim()) {
            toastr.warning('Please enter a reason.');
            return;
        }

        toastr.success('Leave request submitted successfully!');

        const modal = bootstrap.Modal.getInstance(document.getElementById('applyLeaveModal'));
        if (modal) modal.hide();

        form.reset();
        document.getElementById('daysCount').textContent = '0';
    }

    // ==========================================
    // INIT
    // ==========================================
    document.addEventListener('DOMContentLoaded', function() {
        updateLeaveInfo();
    });
</script>
@endpush