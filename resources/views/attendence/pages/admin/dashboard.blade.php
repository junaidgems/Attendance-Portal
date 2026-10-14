@extends('attendence.layouts.app')

@section('title', 'Dashboard')
@section('page-title', 'Dashboard')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/dashboard.css') }}">
@endpush

@section('content')

    <!-- STATS CARDS                               -->
    <div class="stat-grid">
        <a href="javascript:void(0)" class="stat-card" style="text-decoration:none;">
            <span class="stat-icon"><i class="bi bi-people"></i></span>
            <div class="stat-number">{{ $headcount }}</div>
            <div class="stat-label">Headcount</div>
            <div class="stat-sub">{{ $staffCount }} staff + {{ $adminCount }} admin · live from devices</div>
        </a>
        <a href="javascript:void(0)" class="stat-card" style="text-decoration:none;">
            <span class="stat-icon"><i class="bi bi-person-check"></i></span>
            <div class="stat-number">{{ $inOfficeNow }}</div>
            <div class="stat-label">In Office Now</div>
            <div class="stat-sub">live from devices · {{ $onTimeNow }} on time</div>
        </a>
        <a href="javascript:void(0)" class="stat-card" style="text-decoration:none;">
            <span class="stat-icon"><i class="bi bi-clock"></i></span>
            <div class="stat-number">{{ $lateToday }}</div>
            <div class="stat-label">Late Today</div>
            <div class="stat-sub">{{ $lateToday }} late · {{ $absentToday }} absent</div>
        </a>
        <a href="javascript:void(0)" class="stat-card" style="text-decoration:none;">
            <span class="stat-icon"><i class="bi bi-pencil-square"></i></span>
            <div class="stat-number">{{ $pendingCorrections }}</div>
            <div class="stat-label">Pending Corrections</div>
            <div class="stat-sub">{{ $approvedCorrections }} approved · {{ $rejectedCorrections }} rejected</div>
        </a>
    </div>
    <!-- MODULE CARDS   -->
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
            @forelse($deptStats as $d)
                <div class="dept-item">
                    <span class="dept-name">{{ $d['name'] }}</span>
                    <div style="display:flex;align-items:center;gap:12px;">
                        <div class="dept-bar">
                            <div class="fill" style="width:{{ $d['rate'] }}%;"></div>
                        </div>
                        <span class="dept-rate">{{ $d['rate'] }}%</span>
                    </div>
                </div>
            @empty
                <p style="font-size:13px; color:var(--text-muted);">Koi department data nahi mila.</p>
            @endforelse
        </div>

        <!-- Today's Exceptions -->
        <div class="panel">
            <div class="panel-header">
                <h6>Today's Exceptions</h6>
                <span class="badge-count">{{ now()->format('D d M') }}</span>
            </div>
            @forelse($exceptions as $e)
                <div class="notif-item">
                    <span class="notif-dot {{ $e['dot'] }}"></span>
                    <span class="notif-text"><strong>{{ $e['name'] }}</strong> · {{ $e['text'] }}</span>
                    <span class="notif-time">{{ $e['time'] }}</span>
                </div>
            @empty
                <p style="font-size:13px; color:var(--text-muted);">No exception Today</p>
            @endforelse
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
                    <div class="num">{{ $monthly['missing-punch'] }}</div>
                    <div class="label">Missing Punch</div>
                </a>
                <a href="{{ route('admin.attendance.breakdown', ['type' => 'half-day']) }}" class="stat-box"
                    style="text-decoration:none;">
                    <div class="num">{{ $monthly['half-day'] }}</div>
                    <div class="label">Half Day</div>
                </a>
                <a href="{{ route('admin.attendance.breakdown', ['type' => 'early-out']) }}" class="stat-box"
                    style="text-decoration:none;">
                    <div class="num">{{ $monthly['early-out'] }}</div>
                    <div class="label">Early Out</div>
                </a>
                <a href="{{ route('admin.attendance.breakdown', ['type' => 'absent']) }}" class="stat-box"
                    style="text-decoration:none;">
                    <div class="num">{{ $monthly['absent'] }}</div>
                    <div class="label">Absent</div>
                </a>
                <a href="{{ route('admin.attendance.breakdown', ['type' => 'late']) }}" class="stat-box"
                    style="text-decoration:none;">
                    <div class="num">{{ $monthly['late'] }}</div>
                    <div class="label">Late</div>
                </a>
                <a href="{{ route('admin.attendance.breakdown', ['type' => 'present']) }}" class="stat-box"
                    style="text-decoration:none;">
                    <div class="num">{{ $monthly['present'] }}</div>
                    <div class="label">Present</div>
                </a>
            </div>
        </div>

        <!-- Notifications -->
        <div class="panel">
            <div class="panel-header">
                <h6>Notifications</h6>
                <span class="badge-count">{{ count($notifications) }} new</span>
            </div>
            @forelse($notifications as $n)
                <div class="notif-item">
                    <span class="notif-dot {{ $n['dot'] }}"></span>
                    <span class="notif-text">{!! $n['text'] !!}</span>
                    <span class="notif-time">{{ $n['time'] }}</span>
                </div>
            @empty
                <p style="font-size:13px; color:var(--text-muted);">Koi naya notification nahi.</p>
            @endforelse
        </div>

    </div>

@endsection