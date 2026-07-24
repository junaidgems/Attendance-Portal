@extends('attendence.layouts.app')

@section('title', 'Leave Report')
@section('page-title', 'Leave Report')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/employee/leavereport.css') }}">
@endpush

@section('content')


<!-- FILTER BAR  -->
<div class="glass-card">
    <form method="GET" action="{{ route('employee.leave.report') }}">
        <div class="filter-bar">

            <div class="form-group">
                <label>Year</label>
                <select class="form-control" name="year">
                    <option value="2026" selected>2026</option>
                    <option value="2025">2025</option>
                    <option value="2024">2024</option>
                </select>
            </div>

            <div class="form-group">
                <label>From</label>
                <input type="date" class="form-control" name="from" value="2026-01-01">
            </div>

            <div class="form-group">
                <label>To</label>
                <input type="date" class="form-control" name="to" value="2026-07-21">
            </div>

            <div class="form-group">
                <label>Leave Type</label>
                <select class="form-control" name="leave_type">
                    <option value="all">All types</option>
                    <option value="annual">Annual Leave</option>
                    <option value="sick">Sick Leave</option>
                    <option value="casual">Casual Leave</option>
                </select>
            </div>

            <div class="form-group">
                <label>Status</label>
                <select class="form-control" name="status">
                    <option value="all">All</option>
                    <option value="approved">Approved</option>
                    <option value="pending">Pending</option>
                    <option value="rejected">Rejected</option>
                </select>
            </div>

            <div class="btn-group">
                <button type="submit" class="btn-primary-glass"><i class="bi bi-funnel"></i> Filter</button>
                <a href="{{ route('employee.leave.report') }}" class="btn-outline-glass"><i class="bi bi-arrow-counterclockwise"></i> Clear</a>
            </div>

        </div>
    </form>
</div>


<!-- SUMMARY CARDS -->

<div class="summary-grid">
    <div class="summary-card">
        <div class="number">0</div>
        <div class="label">Approved Leave Days</div>
        <div class="sub">in selected range</div>
    </div>
    <div class="summary-card">
        <div class="number">0</div>
        <div class="label">Pending Requests</div>
        <div class="sub">awaiting approval</div>
    </div>
    <div class="summary-card">
        <div class="number">0</div>
        <div class="label">Rejected Requests</div>
        <div class="sub">in selected range</div>
    </div>
    <div class="summary-card">
        <div class="number">0</div>
        <div class="label">On Leave Today</div>
        <div class="sub">today</div>
    </div>
</div>


<!-- LEAVE BALANCES   -->

<div class="glass-card">

    <div class="section-header">
        <div>
            <h6>Leave balances</h6>
            <span class="subtitle">1 employee · 2026</span>
        </div>
    </div>

    <div class="table-wrap">
        <table class="balance-table">
            <thead>
                <tr>
                    <th>Employee</th>
                    <th style="text-align:center;">Annual Leave</th>
                    <th style="text-align:center;">Sick Leave</th>
                    <th style="text-align:center;">Casual Leave</th>
                    <th style="text-align:center;">Total Taken</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>
                        <strong>Junaid</strong>
                        <br>
                        <span style="font-size:11px; color:var(--text-muted);">GP-1002</span>
                    </td>
                    <td style="text-align:center;">
                        <span class="balance-used">0</span>
                        <span class="balance-total">/ 14</span>
                    </td>
                    <td style="text-align:center;">
                        <span class="balance-used">0</span>
                        <span class="balance-total">/ 8</span>
                    </td>
                    <td style="text-align:center;">
                        <span class="balance-used">0</span>
                        <span class="balance-total">/ 10</span>
                    </td>
                    <td style="text-align:center;">
                        <span style="font-weight:700; color:var(--text-primary);">0</span>
                    </td>
                </tr>
            </tbody>
    </table>
    </div>

</div>


<!-- LEAVE REGISTER  -->

<div class="glass-card">

    <div class="section-header">
        <div>
            <h6>Leave register</h6>
            <span class="subtitle">0 requests</span>
        </div>
    </div>

    <div class="table-wrap">
        <table class="register-table">
            <thead>
                <tr>
                    <th>Type</th>
                    <th>From</th>
                    <th>To</th>
                    <th>Days</th>
                    <th>Status</th>
                    <th>Reason</th>
                </tr>
            </thead>
            <tbody>
                <!-- Dummy Data 1 -->
                <tr>
                    <td><strong>Annual Leave</strong></td>
                    <td>20 Jul 2026</td>
                    <td>22 Jul 2026</td>
                    <td>3</td>
                    <td><span class="status-badge pending">Pending</span></td>
                    <td style="font-size:12px; color:var(--text-muted);">Family event</td>
                </tr>
                <!-- Dummy Data 2 -->
                <tr>
                    <td><strong>Sick Leave</strong></td>
                    <td>15 Jul 2026</td>
                    <td>15 Jul 2026</td>
                    <td>1</td>
                    <td><span class="status-badge approved">Approved</span></td>
                    <td style="font-size:12px; color:var(--text-muted);">Doctor appointment</td>
                </tr>
                <!-- Dummy Data 3 -->
                <tr>
                    <td><strong>Casual Leave</strong></td>
                    <td>10 Jul 2026</td>
                    <td>10 Jul 2026</td>
                    <td>1</td>
                    <td><span class="status-badge rejected">Rejected</span></td>
                    <td style="font-size:12px; color:var(--text-muted);">Insufficient balance</td>
                </tr>

                <!-- Empty State (Uncomment if needed) -->
                <!--
                <tr>
                    <td colspan="6">
                        <div class="empty-state">
                            <i class="bi bi-inbox"></i>
                            <p>No leave requests match these filters.</p>
                            <span class="sub">Try adjusting your search or filters.</span>
                        </div>
                    </td>
                </tr>
                -->
            </tbody>
        </table>
    </div>

</div>

@endsection