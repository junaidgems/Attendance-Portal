@extends('attendence.layouts.app')

@section('title', 'Employee Dashboard')
@section('page-title', 'Dashboard')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/employee/attendencebreak.css') }}">
@endpush

@section('content')

<!-- ===== TOP BAR — Back to Dashboard ===== -->
<div class="top-bar">
    <a href="{{ route('manager.dashboard') }}" class="btn-back-glass">
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
            <form method="GET" action="{{ route('manager.attendance.breakdown') }}">
                <div class="filter-bar">

                    <div class="form-group">
                        <label>From</label>
                        <input type="date" class="form-control" name="from" value="2026-07-12">
                    </div>

                    <div class="form-group">
                        <label>To</label>
                        <input type="date" class="form-control" name="to" value="2026-07-20">
                    </div>

                    <div class="form-group">
                        <label>Department</label>
                        <select class="form-control" name="department">
                            <option value="all">All departments</option>
                            <option value="ICT">ICT</option>
                            <option value="Engineering">Engineering</option>
                            <option value="HR">HR</option>
                            <option value="Finance">Finance</option>
                            <option value="Marketing">Marketing</option>
                        </select>
                    </div>

                    <div class="form-group" style="flex:1.5;">
                        <label>Search</label>
                        <input type="text" class="form-control" name="search" placeholder="Name or Employee ID">
                    </div>

                    <div class="btn-group">
                        <button type="submit" class="btn-primary-glass"><i class="bi bi-funnel"></i> Filter</button>
                        <a href="{{ route('manager.attendance.breakdown') }}" class="btn-outline-glass"><i class="bi bi-arrow-counterclockwise"></i> Clear</a>
                        <button class="btn-outline-glass" onclick="alert('Export CSV coming soon!')"><i class="bi bi-download"></i> Export CSV</button>
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
                    <button class="nav-link active" id="all-tab" data-bs-toggle="tab" data-bs-target="#all" type="button" role="tab">
                        <span class="num">2</span> All records
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="missing-tab" data-bs-toggle="tab" data-bs-target="#missing" type="button" role="tab">
                        <span class="num">0</span> Missing Punch
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="halfday-tab" data-bs-toggle="tab" data-bs-target="#halfday" type="button" role="tab">
                        <span class="num">0</span> Half Day
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="earlyout-tab" data-bs-toggle="tab" data-bs-target="#earlyout" type="button" role="tab">
                        <span class="num">0</span> Early Out
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="absent-tab" data-bs-toggle="tab" data-bs-target="#absent" type="button" role="tab">
                        <span class="num">2</span> Absent
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="late-tab" data-bs-toggle="tab" data-bs-target="#late" type="button" role="tab">
                        <span class="num">0</span> Late
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="present-tab" data-bs-toggle="tab" data-bs-target="#present" type="button" role="tab">
                        <span class="num">0</span> Present
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="onleave-tab" data-bs-toggle="tab" data-bs-target="#onleave" type="button" role="tab">
                        <span class="num">0</span> On Leave
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
                            <h6>All Records</h6>
                            <span class="subtitle">2 records</span>
                        </div>
                    </div>
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
                                <tr>
                                    <td><strong>Junaid</strong></td>
                                    <td>GP-1002</td>
                                    <td>ICT</td>
                                    <td>Mon 13 Jul</td>
                                    <td><span class="status-badge-sm absent">Absent</span></td>
                                    <td><span class="shift-badge">Standard Shift</span></td>
                                    <td>09:00-17:00</td>
                                    <td>—</td>
                                    <td>—</td>
                                    <td>—</td>
                                    <td>—</td>
                                    <td>—</td>
                                </tr>
                                <tr>
                                    <td><strong>Junaid</strong></td>
                                    <td>GP-1002</td>
                                    <td>ICT</td>
                                    <td>Tue 14 Jul</td>
                                    <td><span class="status-badge-sm absent">Absent</span></td>
                                    <td><span class="shift-badge">Standard Shift</span></td>
                                    <td>09:00-17:00</td>
                                    <td>—</td>
                                    <td>—</td>
                                    <td>—</td>
                                    <td>—</td>
                                    <td>—</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- MISSING PUNCH -->
                <div class="tab-pane fade" id="missing" role="tabpanel">
                    <div class="section-header">
                        <div>
                            <h6>Missing Punch</h6>
                            <span class="subtitle">0 records</span>
                        </div>
                    </div>
                    <div class="empty-state">
                        <i class="bi bi-inbox"></i>
                        <p>No records match these filters.</p>
                    </div>
                </div>

                <!-- HALF DAY -->
                <div class="tab-pane fade" id="halfday" role="tabpanel">
                    <div class="section-header">
                        <div>
                            <h6>Half Day</h6>
                            <span class="subtitle">0 records</span>
                        </div>
                    </div>
                    <div class="empty-state">
                        <i class="bi bi-inbox"></i>
                        <p>No records match these filters.</p>
                    </div>
                </div>

                <!-- EARLY OUT -->
                <div class="tab-pane fade" id="earlyout" role="tabpanel">
                    <div class="section-header">
                        <div>
                            <h6>Early Out</h6>
                            <span class="subtitle">0 records</span>
                        </div>
                    </div>
                    <div class="empty-state">
                        <i class="bi bi-inbox"></i>
                        <p>No records match these filters.</p>
                    </div>
                </div>

                <!-- ABSENT -->
                <div class="tab-pane fade" id="absent" role="tabpanel">
                    <div class="section-header">
                        <div>
                            <h6>Absent</h6>
                            <span class="subtitle">2 records</span>
                        </div>
                    </div>
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
                                <tr>
                                    <td><strong>Junaid</strong></td>
                                    <td>GP-1002</td>
                                    <td>ICT</td>
                                    <td>Mon 13 Jul</td>
                                    <td><span class="status-badge-sm absent">Absent</span></td>
                                    <td><span class="shift-badge">Standard Shift</span></td>
                                    <td>09:00-17:00</td>
                                    <td>—</td>
                                    <td>—</td>
                                    <td>—</td>
                                    <td>—</td>
                                    <td>—</td>
                                </tr>
                                <tr>
                                    <td><strong>Junaid</strong></td>
                                    <td>GP-1002</td>
                                    <td>ICT</td>
                                    <td>Tue 14 Jul</td>
                                    <td><span class="status-badge-sm absent">Absent</span></td>
                                    <td><span class="shift-badge">Standard Shift</span></td>
                                    <td>09:00-17:00</td>
                                    <td>—</td>
                                    <td>—</td>
                                    <td>—</td>
                                    <td>—</td>
                                    <td>—</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- LATE -->
                <div class="tab-pane fade" id="late" role="tabpanel">
                    <div class="section-header">
                        <div>
                            <h6>Late</h6>
                            <span class="subtitle">0 records</span>
                        </div>
                    </div>
                    <div class="empty-state">
                        <i class="bi bi-inbox"></i>
                        <p>No records match these filters.</p>
                    </div>
                </div>

                <!-- PRESENT -->
                <div class="tab-pane fade" id="present" role="tabpanel">
                    <div class="section-header">
                        <div>
                            <h6>Present</h6>
                            <span class="subtitle">0 records</span>
                        </div>
                    </div>
                    <div class="empty-state">
                        <i class="bi bi-inbox"></i>
                        <p>No records match these filters.</p>
                    </div>
                </div>

                <!-- ON LEAVE -->
                <div class="tab-pane fade" id="onleave" role="tabpanel">
                    <div class="section-header">
                        <div>
                            <h6>On Leave</h6>
                            <span class="subtitle">0 records</span>
                        </div>
                    </div>
                    <div class="empty-state">
                        <i class="bi bi-inbox"></i>
                        <p>No records match these filters.</p>
                    </div>
                </div>

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