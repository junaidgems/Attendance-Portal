@extends('attendence.layouts.app')

@section('title', 'Dashboard')
@section('page-title', 'Dashboard')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/dashboard.css') }}">
@endpush

@section('content')

    <!-- STATS CARDS                               -->
    <div class="stat-grid">
        <a href="{{ url('/attendance/filter?type=headcount') }}" class="stat-card" style="text-decoration:none;">
            <span class="stat-icon"><i class="bi bi-people"></i></span>
            <div class="stat-number">156</div>
            <div class="stat-label">Headcount</div>
            <div class="stat-sub">142 staff + 14 admin · live from devices</div>
        </a>
        <a href="{{ url('/attendance/filter?type=present') }}" class="stat-card" style="text-decoration:none;">
            <span class="stat-icon"><i class="bi bi-person-check"></i></span>
            <div class="stat-number">89</div>
            <div class="stat-label">In Office Now</div>
            <div class="stat-sub">live from devices · 82 on time</div>
        </a>
        <a href="{{ url('/attendance/filter?type=late') }}" class="stat-card" style="text-decoration:none;">
            <span class="stat-icon"><i class="bi bi-clock"></i></span>
            <div class="stat-number">7</div>
            <div class="stat-label">Late Today</div>
            <div class="stat-sub">7 late · 0 absent</div>
        </a>
        <a href="{{ url('/attendance/filter?type=corrections') }}" class="stat-card" style="text-decoration:none;">
            <span class="stat-icon"><i class="bi bi-pencil-square"></i></span>
            <div class="stat-number">3</div>
            <div class="stat-label">Pending Corrections</div>
            <div class="stat-sub">2 approved · 1 rejected</div>
        </a>
    </div>

    <!-- MODULE CARDS                              -->
    <div class="module-grid">
        <a href="{{ route('admin.employee') }}" class="module-card" style="text-decoration:none;">
            <div class="module-icon blue"><i class="bi bi-person-badge"></i></div>
            <div class="module-info">
                <h6>Employees</h6>
                <p>Manage the directory</p>
            </div>
            <span class="module-arrow"><i class="bi bi-chevron-right"></i></span>
        </a>

        <a href="{{ route('admin.biometric.sync') }}" class="module-card" style="text-decoration:none;">
            <div class="module-icon green"><i class="bi bi-fingerprint"></i></div>
            <div class="module-info">
                <h6>Biometric Sync</h6>
                <p>All synced today</p>
            </div>
            <span class="module-arrow"><i class="bi bi-chevron-right"></i></span>
        </a>

        <a href="{{ route('admin.attendance') }}" class="module-card" style="text-decoration:none;">
            <div class="module-icon purple"><i class="bi bi-clock-history"></i></div>
            <div class="module-info">
                <h6>All Attendance</h6>
                <p>Org-wide records</p>
            </div>
            <span class="module-arrow"><i class="bi bi-chevron-right"></i></span>
        </a>

        <a href="{{ route('admin.attendance.policy') }}" class="module-card" style="text-decoration:none;">
            <div class="module-icon orange"><i class="bi bi-file-text"></i></div>
            <div class="module-info">
                <h6>Policy</h6>
                <p>Rules &amp; schedule</p>
            </div>
            <span class="module-arrow"><i class="bi bi-chevron-right"></i></span>
        </a>
    </div>

    <!-- TWO COLUMN - DEPARTMENTS + EXCEPTIONS     -->
    <div class="two-col">

        <!-- Attendance by Department -->
        <div class="panel">
            <div class="panel-header">
                <h6>Attendance by Department</h6>
                <span class="badge-count">30-day rate</span>
            </div>
            <div class="dept-item">
                <span class="dept-name">Engineering</span>
                <div style="display:flex;align-items:center;gap:12px;">
                    <div class="dept-bar">
                        <div class="fill" style="width:92%;"></div>
                    </div>
                    <span class="dept-rate">92%</span>
                </div>
            </div>
            <div class="dept-item">
                <span class="dept-name">HR</span>
                <div style="display:flex;align-items:center;gap:12px;">
                    <div class="dept-bar">
                        <div class="fill" style="width:85%;"></div>
                    </div>
                    <span class="dept-rate">85%</span>
                </div>
            </div>
            <div class="dept-item">
                <span class="dept-name">Finance</span>
                <div style="display:flex;align-items:center;gap:12px;">
                    <div class="dept-bar">
                        <div class="fill" style="width:78%;"></div>
                    </div>
                    <span class="dept-rate">78%</span>
                </div>
            </div>
            <div class="dept-item">
                <span class="dept-name">Marketing</span>
                <div style="display:flex;align-items:center;gap:12px;">
                    <div class="dept-bar">
                        <div class="fill" style="width:88%;"></div>
                    </div>
                    <span class="dept-rate">88%</span>
                </div>
            </div>
            <div class="dept-item">
                <span class="dept-name">Operations</span>
                <div style="display:flex;align-items:center;gap:12px;">
                    <div class="dept-bar">
                        <div class="fill" style="width:81%;"></div>
                    </div>
                    <span class="dept-rate">81%</span>
                </div>
            </div>
        </div>

        <!-- Today's Exceptions -->
        <div class="panel">
            <div class="panel-header">
                <h6>Today’s Exceptions</h6>
                <span class="badge-count">Fri 17 Jul</span>
            </div>

            <div class="notif-item">
                <span class="notif-dot yellow"></span>
                <span class="notif-text"><strong>John Doe</strong> · Late in (09:45)</span>
                <span class="notif-time">15 min ago</span>
            </div>
            <div class="notif-item">
                <span class="notif-dot yellow"></span>
                <span class="notif-text"><strong>Jane Smith</strong> · Late in (09:30)</span>
                <span class="notif-time">30 min ago</span>
            </div>
            <div class="notif-item">
                <span class="notif-dot blue"></span>
                <span class="notif-text"><strong>Mike Johnson</strong> · Leave (Approved)</span>
                <span class="notif-time">1 hour ago</span>
            </div>
            <div class="notif-item">
                <span class="notif-dot green"></span>
                <span class="notif-text"><strong>Sarah Lee</strong> · Early out (Approved)</span>
                <span class="notif-time">2 hours ago</span>
            </div>
        </div>

    </div>

    <!-- BOTTOM ROW - MONTHLY STATS + NOTIFICATIONS -->
    <div class="two-col" style="margin-bottom:0;">

        <!-- Monthly Stats -->
        <div class="panel">
            <div class="panel-header">
                <h6>This Month — Organisation</h6>
                <span class="badge-count">tap any card for full list</span>
            </div>

            <div class="monthly-grid">
                <a href="{{ route('admin.attendance.breakdown', ['type' => 'missing-punch']) }}" class="stat-box"
                    style="text-decoration:none;">
                    <div class="num">12</div>
                    <div class="label">Missing Punch</div>
                </a>
                <a href="{{ route('admin.attendance.breakdown', ['type' => 'half-day']) }}" class="stat-box"
                    style="text-decoration:none;">
                    <div class="num">8</div>
                    <div class="label">Half Day</div>
                </a>
                <a href="{{ route('admin.attendance.breakdown', ['type' => 'early-out']) }}" class="stat-box"
                    style="text-decoration:none;">
                    <div class="num">5</div>
                    <div class="label">Early Out</div>
                </a>
                <a href="{{ route('admin.attendance.breakdown', ['type' => 'absent']) }}" class="stat-box"
                    style="text-decoration:none;">
                    <div class="num">3</div>
                    <div class="label">Absent</div>
                </a>
                <a href="{{ route('admin.attendance.breakdown', ['type' => 'late']) }}" class="stat-box"
                    style="text-decoration:none;">
                    <div class="num">9</div>
                    <div class="label">Late</div>
                </a>
                <a href="{{ route('admin.attendance.breakdown', ['type' => 'present']) }}" class="stat-box"
                    style="text-decoration:none;">
                    <div class="num">89</div>
                    <div class="label">Present</div>
                </a>
            </div>
        </div>

        <!-- Notifications -->
        <div class="panel">
            <div class="panel-header">
                <h6>Notifications</h6>
                <span class="badge-count">3 new</span>
            </div>

            <div class="notif-item">
                <span class="notif-dot blue"></span>
                <span class="notif-text"><strong>Attendance Policy</strong> updated</span>
                <span class="notif-time">5 min ago</span>
            </div>
            <div class="notif-item">
                <span class="notif-dot green"></span>
                <span class="notif-text"><strong>3 leave requests</strong> pending approval</span>
                <span class="notif-time">10 min ago</span>
            </div>
            <div class="notif-item">
                <span class="notif-dot yellow"></span>
                <span class="notif-text"><strong>Biometric sync</strong> completed</span>
                <span class="notif-time">1 hour ago</span>
            </div>
        </div>

    </div>

@endsection