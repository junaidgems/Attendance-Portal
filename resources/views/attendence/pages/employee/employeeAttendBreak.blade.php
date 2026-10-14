@extends('attendence.layouts.app')

@section('title', 'My Attendance Breakdown')
@section('page-title', 'My Attendance Breakdown')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/employee/attendencebreak.css') }}">
@endpush

@section('content')

    <!-- ===== TOP BAR — Back to Dashboard ===== -->
    <div class="top-bar">
        <a href="{{ route('employee.dashboard') }}" class="btn-back-glass">
            <i class="bi bi-arrow-left"></i> Back to dashboard
        </a>
        <span style="font-size:13px; color:var(--text-muted);">
            <i class="bi bi-calendar3"></i> My Attendance Breakdown
        </span>
    </div>

    <!-- ===== FILTER BAR ===== -->
    <div class="row">
        <div class="col-12">
            <div class="glass-card">
                <form method="GET" action="{{ route('employee.attendance.breakdown') }}">
                    <div class="filter-bar">
                        <div class="form-group">
                            <label>From</label>
                            <input type="date" class="form-control" name="from" value="{{ $from }}">
                        </div>
                        <div class="form-group">
                            <label>To</label>
                            <input type="date" class="form-control" name="to" value="{{ $to }}">
                        </div>
                        <div class="btn-group">
                            <button type="submit" class="btn-primary-glass"><i class="bi bi-funnel"></i> Filter</button>
                            <a href="{{ route('employee.attendance.breakdown') }}" class="btn-outline-glass"><i
                                    class="bi bi-arrow-counterclockwise"></i> Clear</a>
                            <button class="btn-outline-glass" onclick="alert('Export CSV coming soon!')" type="button"><i
                                    class="bi bi-download"></i> Export CSV</button>
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
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="all-tab" data-bs-toggle="tab" data-bs-target="#all"
                            type="button" role="tab">
                            <span class="num">{{ $grouped['all']->count() }}</span> All records
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="missing-tab" data-bs-toggle="tab" data-bs-target="#missing"
                            type="button" role="tab">
                            <span class="num">{{ $grouped['missing']->count() }}</span> Missing Punch
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="halfday-tab" data-bs-toggle="tab" data-bs-target="#halfday"
                            type="button" role="tab">
                            <span class="num">{{ $grouped['halfday']->count() }}</span> Half Day
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="earlyout-tab" data-bs-toggle="tab" data-bs-target="#earlyout"
                            type="button" role="tab">
                            <span class="num">{{ $grouped['earlyout']->count() }}</span> Early Out
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="absent-tab" data-bs-toggle="tab" data-bs-target="#absent" type="button"
                            role="tab">
                            <span class="num">{{ $grouped['absent']->count() }}</span> Absent
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="late-tab" data-bs-toggle="tab" data-bs-target="#late" type="button"
                            role="tab">
                            <span class="num">{{ $grouped['late']->count() }}</span> Late
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="present-tab" data-bs-toggle="tab" data-bs-target="#present"
                            type="button" role="tab">
                            <span class="num">{{ $grouped['present']->count() }}</span> Present
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="onleave-tab" data-bs-toggle="tab" data-bs-target="#onleave"
                            type="button" role="tab">
                            <span class="num">{{ $grouped['onleave']->count() }}</span> On Leave
                        </button>
                    </li>
                </ul>
            </div>
        </div>
    </div>

    <!-- ===== TAB CONTENT ===== -->
    <div class="row" style="margin-top:16px;">
        <div class="col-12">
            <div class="glass-card">

                <div class="tab-content">

                    <!-- ALL RECORDS -->
                    <div class="tab-pane fade show active" id="all" role="tabpanel">
                        <div class="section-header">
                            <div>
                                <h6>All Records</h6><span class="subtitle">{{ $grouped['all']->count() }} records</span>
                            </div>
                        </div>
                        @if($grouped['all']->isEmpty())
                            <div class="empty-state"><i class="bi bi-inbox"></i>
                                <p>No records match these filters.</p>
                            </div>
                        @else
                            <div class="table-wrap">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Employee</th>
                                            <th>Emp ID</th>
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
                                        @foreach($grouped['all'] as $row)
                                            <tr>
                                                <td><strong>{{ $employee->first_name }} {{ $employee->last_name }}</strong></td>
                                                <td>{{ $employee->employee_id }}</td>
                                                <td>{{ $row['date'] }}</td>
                                                <td><span
                                                        class="status-badge-sm {{ strtolower(str_replace(' ', '-', $row['status'])) }}">{{ $row['status'] }}</span>
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
                        @endif
                    </div>

                    <!-- MISSING PUNCH -->
                    <div class="tab-pane fade" id="missing" role="tabpanel">
                        <div class="section-header">
                            <div>
                                <h6>Missing Punch</h6><span class="subtitle">{{ $grouped['missing']->count() }}
                                    records</span>
                            </div>
                        </div>
                        @if($grouped['missing']->isEmpty())
                            <div class="empty-state"><i class="bi bi-inbox"></i>
                                <p>No records match these filters.</p>
                            </div>
                        @else
                            <div class="table-wrap">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Employee</th>
                                            <th>Emp ID</th>
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
                                        @foreach($grouped['missing'] as $row)
                                            <tr>
                                                <td><strong>{{ $employee->first_name }} {{ $employee->last_name }}</strong></td>
                                                <td>{{ $employee->employee_id }}</td>
                                                <td>{{ $row['date'] }}</td>
                                                <td><span
                                                        class="status-badge-sm {{ strtolower(str_replace(' ', '-', $row['status'])) }}">{{ $row['status'] }}</span>
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
                        @endif
                    </div>

                    <!-- HALF DAY -->
                    <div class="tab-pane fade" id="halfday" role="tabpanel">
                        <div class="section-header">
                            <div>
                                <h6>Half Day</h6><span class="subtitle">{{ $grouped['halfday']->count() }} records</span>
                            </div>
                        </div>
                        @if($grouped['halfday']->isEmpty())
                            <div class="empty-state"><i class="bi bi-inbox"></i>
                                <p>No records match these filters.</p>
                            </div>
                        @else
                            <div class="table-wrap">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Employee</th>
                                            <th>Emp ID</th>
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
                                        @foreach($grouped['halfday'] as $row)
                                            <tr>
                                                <td><strong>{{ $employee->first_name }} {{ $employee->last_name }}</strong></td>
                                                <td>{{ $employee->employee_id }}</td>
                                                <td>{{ $row['date'] }}</td>
                                                <td><span
                                                        class="status-badge-sm {{ strtolower(str_replace(' ', '-', $row['status'])) }}">{{ $row['status'] }}</span>
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
                        @endif
                    </div>

                    <!-- EARLY OUT -->
                    <div class="tab-pane fade" id="earlyout" role="tabpanel">
                        <div class="section-header">
                            <div>
                                <h6>Early Out</h6><span class="subtitle">{{ $grouped['earlyout']->count() }} records</span>
                            </div>
                        </div>
                        @if($grouped['earlyout']->isEmpty())
                            <div class="empty-state"><i class="bi bi-inbox"></i>
                                <p>No records match these filters.</p>
                            </div>
                        @else
                            <div class="table-wrap">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Employee</th>
                                            <th>Emp ID</th>
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
                                        @foreach($grouped['earlyout'] as $row)
                                            <tr>
                                                <td><strong>{{ $employee->first_name }} {{ $employee->last_name }}</strong></td>
                                                <td>{{ $employee->employee_id }}</td>
                                                <td>{{ $row['date'] }}</td>
                                                <td><span
                                                        class="status-badge-sm {{ strtolower(str_replace(' ', '-', $row['status'])) }}">{{ $row['status'] }}</span>
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
                        @endif
                    </div>

                    <!-- ABSENT -->
                    <div class="tab-pane fade" id="absent" role="tabpanel">
                        <div class="section-header">
                            <div>
                                <h6>Absent</h6><span class="subtitle">{{ $grouped['absent']->count() }} records</span>
                            </div>
                        </div>
                        @if($grouped['absent']->isEmpty())
                            <div class="empty-state"><i class="bi bi-inbox"></i>
                                <p>No records match these filters.</p>
                            </div>
                        @else
                            <div class="table-wrap">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Employee</th>
                                            <th>Emp ID</th>
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
                                        @foreach($grouped['absent'] as $row)
                                            <tr>
                                                <td><strong>{{ $employee->first_name }} {{ $employee->last_name }}</strong></td>
                                                <td>{{ $employee->employee_id }}</td>
                                                <td>{{ $row['date'] }}</td>
                                                <td><span
                                                        class="status-badge-sm {{ strtolower(str_replace(' ', '-', $row['status'])) }}">{{ $row['status'] }}</span>
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
                        @endif
                    </div>

                    <!-- LATE -->
                    <div class="tab-pane fade" id="late" role="tabpanel">
                        <div class="section-header">
                            <div>
                                <h6>Late</h6><span class="subtitle">{{ $grouped['late']->count() }} records</span>
                            </div>
                        </div>
                        @if($grouped['late']->isEmpty())
                            <div class="empty-state"><i class="bi bi-inbox"></i>
                                <p>No records match these filters.</p>
                            </div>
                        @else
                            <div class="table-wrap">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Employee</th>
                                            <th>Emp ID</th>
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
                                        @foreach($grouped['late'] as $row)
                                            <tr>
                                                <td><strong>{{ $employee->first_name }} {{ $employee->last_name }}</strong></td>
                                                <td>{{ $employee->employee_id }}</td>
                                                <td>{{ $row['date'] }}</td>
                                                <td><span
                                                        class="status-badge-sm {{ strtolower(str_replace(' ', '-', $row['status'])) }}">{{ $row['status'] }}</span>
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
                        @endif
                    </div>

                    <!-- PRESENT -->
                    <div class="tab-pane fade" id="present" role="tabpanel">
                        <div class="section-header">
                            <div>
                                <h6>Present</h6><span class="subtitle">{{ $grouped['present']->count() }} records</span>
                            </div>
                        </div>
                        @if($grouped['present']->isEmpty())
                            <div class="empty-state"><i class="bi bi-inbox"></i>
                                <p>No records match these filters.</p>
                            </div>
                        @else
                            <div class="table-wrap">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Employee</th>
                                            <th>Emp ID</th>
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
                                        @foreach($grouped['present'] as $row)
                                            <tr>
                                                <td><strong>{{ $employee->first_name }} {{ $employee->last_name }}</strong></td>
                                                <td>{{ $employee->employee_id }}</td>
                                                <td>{{ $row['date'] }}</td>
                                                <td><span
                                                        class="status-badge-sm {{ strtolower(str_replace(' ', '-', $row['status'])) }}">{{ $row['status'] }}</span>
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
                        @endif
                    </div>

                    <!-- ON LEAVE -->
                    <div class="tab-pane fade" id="onleave" role="tabpanel">
                        <div class="section-header">
                            <div>
                                <h6>On Leave</h6><span class="subtitle">{{ $grouped['onleave']->count() }} records</span>
                            </div>
                        </div>
                        @if($grouped['onleave']->isEmpty())
                            <div class="empty-state"><i class="bi bi-inbox"></i>
                                <p>No records match these filters.</p>
                            </div>
                        @else
                            <div class="table-wrap">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Employee</th>
                                            <th>Emp ID</th>
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
                                        @foreach($grouped['onleave'] as $row)
                                            <tr>
                                                <td><strong>{{ $employee->first_name }} {{ $employee->last_name }}</strong></td>
                                                <td>{{ $employee->employee_id }}</td>
                                                <td>{{ $row['date'] }}</td>
                                                <td><span
                                                        class="status-badge-sm {{ strtolower(str_replace(' ', '-', $row['status'])) }}">{{ $row['status'] }}</span>
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
                        @endif
                    </div>

                </div>

            </div>
        </div>
    </div>

@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const urlParams = new URLSearchParams(window.location.search);
            const type = urlParams.get('type');

            const tabMap = {
                'missing-punch': 'missing-tab',
                'half-day': 'halfday-tab',
                'early-out': 'earlyout-tab',
                'absent': 'absent-tab',
                'late': 'late-tab',
                'present': 'present-tab',
                'on-leave': 'onleave-tab'
            };

            if (type && tabMap[type]) {
                const tabId = tabMap[type];
                const tabElement = document.getElementById(tabId);
                if (tabElement) {
                    const tab = new bootstrap.Tab(tabElement);
                    tab.show();
                }
            }
        });
    </script>
@endpush