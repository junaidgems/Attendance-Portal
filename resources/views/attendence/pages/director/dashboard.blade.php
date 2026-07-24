@extends('attendence.layouts.app')

@section('title', 'Employee Dashboard')
@section('page-title', 'Dashboard')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/manager-css/dashboard.css') }}">
@endpush

@section('content')

    <!-- ===== GREETING ===== -->
    <div class="greeting-card">
        <h4>Welcome, {{ session('user_name') }} 👋</h4>
        <p>Your team attendance at a glance</p>
    </div>

    <!-- ===== NOTIFICATIONS — TOP ===== -->
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

    <!-- ===== STATS ===== -->
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

    <!-- ===== QUICK ACTIONS ===== -->
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

<!-- ===== PENDING APPROVALS — TABLE VIEW ===== -->
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
                        <button class="btn-approve"><i class="bi bi-check2"></i></button>
                        <button class="btn-reject"><i class="bi bi-x"></i></button>
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
                        <button class="btn-approve"><i class="bi bi-check2"></i></button>
                        <button class="btn-reject"><i class="bi bi-x"></i></button>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

    <!-- ===== AVG ATTENDANCE ===== -->
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

    <!-- ===== TEAM MONTHLY STATS ===== -->
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

    <!-- ===== TEAM TODAY — WITH DATA ===== -->
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