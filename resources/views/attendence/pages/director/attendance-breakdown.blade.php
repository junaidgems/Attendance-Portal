@extends('attendence.layouts.app')

@section('title', 'Attendance Breakdown')
@section('page-title', 'Attendance Breakdown')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/employee/attendencebreak.css') }}">
@endpush

@section('content')

@php
    $tabs = [
        'all' => ['label' => 'All records', 'title' => 'All Records'],
        'missing' => ['label' => 'Missing Punch', 'title' => 'Missing Punch'],
        'halfday' => ['label' => 'Half Day', 'title' => 'Half Day'],
        'earlyout' => ['label' => 'Early Out', 'title' => 'Early Out'],
        'absent' => ['label' => 'Absent', 'title' => 'Absent'],
        'late' => ['label' => 'Late', 'title' => 'Late'],
        'present' => ['label' => 'Present', 'title' => 'Present'],
        'onleave' => ['label' => 'On Leave', 'title' => 'On Leave'],
    ];
    $statusClass = [
        'Present' => 'present',
        'Late' => 'late',
        'Early Out' => 'early-out',
        'Half Day' => 'half-day',
        'Absent' => 'absent',
        'On Leave' => 'on-leave',
        'Holiday' => 'holiday',
        'Missing Punch' => 'missing-punch',
    ];
@endphp

<!-- ===== TOP BAR — Back to Dashboard ===== -->
<div class="top-bar">
    <a href="{{ route('director.dashboard') }}" class="btn-back-glass">
        <i class="bi bi-arrow-left"></i> Back to dashboard
    </a>
    <span style="font-size:13px; color:var(--text-muted);">
        <i class="bi bi-calendar3"></i> Company Attendance Breakdown
    </span>
</div>

<!-- ===== FILTER BAR ===== -->
<div class="row">
    <div class="col-12">
        <div class="glass-card">
            <form method="GET" action="{{ route('director.attendance.breakdown') }}">
                <div class="filter-bar">

                    <div class="form-group">
                        <label>From</label>
                        <input type="date" class="form-control" name="from" value="{{ $from }}" max="{{ now()->format('Y-m-d') }}">
                    </div>

                    <div class="form-group">
                        <label>To</label>
                        <input type="date" class="form-control" name="to" value="{{ $to }}" max="{{ now()->format('Y-m-d') }}">
                    </div>

                    <div class="form-group">
                        <label>Employee</label>
                        <select class="form-control" name="member">
                            <option value="all">All employees</option>
                            @foreach ($members as $teamMember)
                                <option value="{{ $teamMember->id }}" @selected((string) $member === (string) $teamMember->id)>
                                    {{ $teamMember->full_name }} ({{ $teamMember->employee_id }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Department</label>
                        <select class="form-control" name="department">
                            <option value="all">All departments</option>
                            @foreach ($departments as $dept)
                                <option value="{{ $dept->id }}" @selected((string) $department === (string) $dept->id)>{{ $dept->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-group" style="flex:1.5;">
                        <label>Search</label>
                        <input type="text" class="form-control" name="search" value="{{ $search }}" placeholder="Name or Employee ID">
                    </div>

                    <div class="btn-group">
                        <button type="submit" class="btn-primary-glass"><i class="bi bi-funnel"></i> Filter</button>
                        <a href="{{ route('director.attendance.breakdown') }}" class="btn-outline-glass"><i class="bi bi-arrow-counterclockwise"></i> Clear</a>
                        <a href="{{ route('director.attendance.breakdown.export', ['from' => $from, 'to' => $to, 'member' => $member, 'department' => $department, 'search' => $search, 'tab' => 'all']) }}"
                            class="btn-outline-glass" id="exportLink"><i class="bi bi-download"></i> Export CSV</a>
                    </div>

                </div>
            </form>
        </div>
    </div>
</div>

<!-- ===== BOOTSTRAP TABS — STATS ===== -->
<div class="row">
    <div class="col-12">
        <div class="glass-card" style="padding-bottom:4px;">

            <ul class="nav nav-tabs nav-tabs-custom" id="statsTabs" role="tablist">
                @foreach ($tabs as $key => $tab)
                    <li class="nav-item" role="presentation">
                        <button class="nav-link {{ $loop->first ? 'active' : '' }}" id="{{ $key }}-tab" data-bs-toggle="tab"
                            data-bs-target="#{{ $key }}" data-export-tab="{{ $key }}" type="button" role="tab">
                            <span class="num">{{ $grouped[$key]->count() }}</span> {{ $tab['label'] }}
                        </button>
                    </li>
                @endforeach
            </ul>

        </div>
    </div>
</div>

<!-- ===== TAB CONTENT ===== -->
<div class="row" style="margin-top:16px;">
    <div class="col-12">
        <div class="glass-card">

            <div class="tab-content">

                @foreach ($tabs as $key => $tab)
                    <div class="tab-pane fade {{ $loop->first ? 'show active' : '' }}" id="{{ $key }}" role="tabpanel">
                        <div class="section-header">
                            <div>
                                <h6>{{ $tab['title'] }}</h6>
                                <span class="subtitle">{{ $grouped[$key]->count() }} {{ $grouped[$key]->count() === 1 ? 'record' : 'records' }}</span>
                            </div>
                        </div>

                        @if ($grouped[$key]->isNotEmpty())
                            <div class="table-wrap">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Employee</th>
                                            <th>Emp ID</th>
                                            <th>Department</th>
                                            <th>Date</th>
                                            <th>Status</th>
                                            <th>Shift</th>
                                            <th>Scheduled</th>
                                            <th>IN</th>
                                            <th>OUT</th>
                                            <th>Worked</th>
                                            <th>Late</th>
                                            <th>Early</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($pages[$key] as $row)
                                            <tr>
                                                <td><strong>{{ $row['employee'] }}</strong></td>
                                                <td>{{ $row['emp_id'] }}</td>
                                                <td>{{ $row['department'] }}</td>
                                                <td>{{ $row['date'] }}</td>
                                                <td>
                                                    <span class="status-badge-sm {{ $statusClass[$row['status']] ?? '' }}">{{ $row['status'] }}</span>
                                                    @if ($row['missing_punch'])
                                                        <br><span style="font-size:10px; color:#ef4444;">Missing punch</span>
                                                    @endif
                                                </td>
                                                <td><span class="shift-badge">{{ $row['shift'] }}</span></td>
                                                <td>{{ $row['scheduled'] }}</td>
                                                <td>{{ $row['in'] }}</td>
                                                <td>{{ $row['out'] }}</td>
                                                <td>{{ $row['worked'] }}</td>
                                                <td>{{ $row['late'] }}</td>
                                                <td>{{ $row['early'] }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>

                            @if ($pages[$key]->hasPages())
                                <div class="breakdown-pagination" style="margin-top:14px; font-size:13px;">
                                    {{ $pages[$key]->onEachSide(1)->links('pagination::bootstrap-5') }}
                                </div>
                            @endif
                        @else
                            <div class="empty-state">
                                <i class="bi bi-inbox"></i>
                                <p>No records match these filters.</p>
                            </div>
                        @endif
                    </div>
                @endforeach

            </div>

        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const urlParams = new URLSearchParams(window.location.search);
        const type = urlParams.get('type');
        const exportLink = document.getElementById('exportLink');

        const tabMap = {
            'missing-punch': 'missing-tab',
            'half-day': 'halfday-tab',
            'early-out': 'earlyout-tab',
            'absent': 'absent-tab',
            'late': 'late-tab',
            'present': 'present-tab',
            'on-leave': 'onleave-tab'
        };

        // Export follows the currently open tab
        document.querySelectorAll('#statsTabs [data-export-tab]').forEach(function (btn) {
            btn.addEventListener('shown.bs.tab', function () {
                const url = new URL(exportLink.href);
                url.searchParams.set('tab', btn.dataset.exportTab);
                exportLink.href = url.toString();
            });
        });

        if (type && tabMap[type]) {
            const tabElement = document.getElementById(tabMap[type]);
            if (tabElement) {
                new bootstrap.Tab(tabElement).show();
            }
        }
    });
</script>
@endpush
