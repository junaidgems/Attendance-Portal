@extends('attendence.layouts.app')

@section('title', 'Employee Dashboard')
@section('page-title', 'Dashboard')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/employee/dashboard.css') }}">
@endpush

@section('content')

    <!-- ===== GREETING ===== -->
    <div class="greeting-card">
        <h4>Welcome, {{ session('user_name') }} 👋</h4>
       {{ session()->get('user_role') }} 

        <p>Your attendance at a glance - Cycle: 26 Jun – 25 Jul 2026</p>
    </div>

    <!-- ===== STATS ===== -->
    <div class="stat-grid">
        <div class="stat-card">
            <div class="number">0%</div>
            <div class="label">Attendance (30d)</div>
            <div class="sub">0/17 working days</div>
        </div>
        <div class="stat-card">
            <div class="number">0</div>
            <div class="label">Late Arrivals</div>
        </div>
        <div class="stat-card">
            <div class="number">—</div>
            <div class="label">Avg Hours / Day</div>
        </div>
        <div class="stat-card">
            <div class="number">0</div>
            <div class="label">Open Requests</div>
        </div>
    </div>

    <!-- ===== LEAVE BALANCE ===== -->
    <div class="section-header">
        <h6>Leave Balance</h6>
        <span class="sub"><a href="#" style="color:var(--primary); text-decoration:none;">Apply →</a></span>
    </div>

    <div class="leave-balance-grid">
        <div class="leave-card">
            <span class="leave-icon annual">🏖️</span>
            <div class="leave-label">Annual Leave</div>
            <div class="leave-balance">14</div>
            <div class="leave-total">/ 14</div>
        </div>
        <div class="leave-card">
            <span class="leave-icon sick">🤒</span>
            <div class="leave-label">Sick Leave</div>
            <div class="leave-balance">8</div>
            <div class="leave-total">/ 8</div>
        </div>
        <div class="leave-card">
            <span class="leave-icon casual">🎯</span>
            <div class="leave-label">Casual Leave</div>
            <div class="leave-balance">10</div>
            <div class="leave-total">/ 10</div>
        </div>
    </div>

    <!-- ===== QUICK ACTIONS ===== -->
    <div class="quick-actions">
        <a href="{{ route('employee.my.attendance') }}" class="quick-action">
            <i class="bi bi-clock-history"></i>
            <h6>My Attendance</h6>
            <p>Full record &amp; monthly view</p>
        </a>
        <a href="{{ route('employee.correction.requests') }}" class="quick-action">
            <i class="bi bi-pencil-square"></i>
            <h6>Correction Requests</h6>
            <p>Fix a wrong or missing punch</p>
        </a>
        <a href="{{ route('employee.leave') }}" class="quick-action">
            <i class="bi bi-calendar-check"></i>
            <h6>Leave</h6>
            <p>Apply &amp; view leave</p>
        </a>
        <a href="{{ route('employee.leave.report') }}" class="quick-action">
            <i class="bi bi-file-earmark-text"></i>
            <h6>Leave Report</h6>
            <p>Your leave history</p>
        </a>
    </div>

<!-- ===== MONTHLY STATS — LINKS TO BREAKDOWN ===== -->
<div class="glass-card">
    <div class="section-header">
        <h6>This Month — Tap any card for full list</h6>
        <span class="sub">0 new</span>
    </div>

    <div class="monthly-grid">
        <a href="{{ route('employee.attendance.breakdown', ['type' => 'missing-punch']) }}" class="monthly-item" style="text-decoration:none;">
            <div class="num">0</div>
            <div class="label">Missing Punch</div>
        </a>
        <a href="{{ route('employee.attendance.breakdown', ['type' => 'half-day']) }}" class="monthly-item" style="text-decoration:none;">
            <div class="num">0</div>
            <div class="label">Half Day</div>
        </a>
        <a href="{{ route('employee.attendance.breakdown', ['type' => 'early-out']) }}" class="monthly-item" style="text-decoration:none;">
            <div class="num">0</div>
            <div class="label">Early Out</div>
        </a>
        <a href="{{ route('employee.attendance.breakdown', ['type' => 'absent']) }}" class="monthly-item" style="text-decoration:none;">
            <div class="num">13</div>
            <div class="label">Absent</div>
        </a>
        <a href="{{ route('employee.attendance.breakdown', ['type' => 'on-leave']) }}" class="monthly-item" style="text-decoration:none;">
            <div class="num">0</div>
            <div class="label">On Leave</div>
        </a>
    </div>
</div>
    <!-- ===== NOTIFICATIONS ===== -->
    <div class="notif-card">
        <div class="notif-left">
            <i class="bi bi-bell"></i>
            <span class="notif-title">Notifications</span>
            <span class="notif-count">0 new</span>
        </div>
        <div class="notif-right">
            <i class="bi bi-check-circle" style="color:#22c55e;"></i> You're all caught up.
        </div>
    </div>

    <!-- ===== RECENT DAYS ===== -->
    <div class="section-header">
        <h6>Recent days</h6>
        <span class="sub">Last working days</span>
    </div>

    <div class="recent-table">
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Status</th>
                    <th>IN</th>
                    <th>OUT</th>
                    <th>Hours</th>
                    <th>Timeline</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Tue 14 Jul</td>
                    <td><span class="status-badge absent">Absent</span></td>
                    <td>—</td>
                    <td>—</td>
                    <td>—</td>
                    <td>—</td>
                </tr>
                <tr>
                    <td>Mon 13 Jul</td>
                    <td><span class="status-badge absent">Absent</span></td>
                    <td>—</td>
                    <td>—</td>
                    <td>—</td>
                    <td>—</td>
                </tr>
            </tbody>
        </table>
    </div>

@endsection