@extends('attendence.layouts.app')

@section('title', 'Team Attendance')
@section('page-title', 'Team Attendance')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/manager-css/team-attendance.css') }}">
@endpush

@section('content')

    <!-- ===== FILTER BAR ===== -->
    <div class="row">
        <div class="col-12">
            <div class="glass-card">
                <form method="GET" action="{{ route('manager.team.attendance') }}">
                    <div class="filter-bar">

                        <div class="form-group">
                            <label>Date</label>
                            <input type="date" class="form-control" name="date" value="{{ date('Y-m-d') }}">
                        </div>

                        <div class="form-group">
                            <label>Team Member</label>
                            <select class="form-control" name="member">
                                <option value="all">— Day view: whole team —</option>
                                <option value="john">John Doe</option>
                                <option value="jane">Jane Smith</option>
                                <option value="mike">Mike Johnson</option>
                                <option value="sarah">Sarah Lee</option>
                                <option value="david">David Khan</option>
                            </select>
                        </div>

                        <div class="btn-group">
                            <button type="submit" class="btn-primary-glass"><i class="bi bi-check2-circle"></i> Mark
                                attendance</button>
                            <button type="submit" class="btn-outline-glass"><i class="bi bi-download"></i> Export day
                                CSV</button>
                        </div>

                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ===== DAY STATUS ===== -->
    <div class="row">
        <div class="col-12">
            <div class="glass-card">
                <div class="day-status">
                    <span><strong>Wed 22 Jul</strong></span>
                    <span class="badge-working">Working day</span>
                    <span style="color:var(--text-muted); font-size:13px; margin-left:8px;">— Day view: whole team —</span>
                </div>
            </div>
        </div>
    </div>

    <!-- ===== STATUS FILTER TABS ===== -->
    <div class="row">
        <div class="col-12">
            <div class="glass-card" style="padding-bottom:8px;">
                <div class="status-tabs">
                    <button class="tab-btn active" data-filter="all">All <span class="count">5</span></button>
                    <button class="tab-btn" data-filter="present">Present <span class="count">2</span></button>
                    <button class="tab-btn" data-filter="late">Late <span class="count">1</span></button>
                    <button class="tab-btn" data-filter="early-out">Early out <span class="count">0</span></button>
                    <button class="tab-btn" data-filter="half-day">Half day <span class="count">0</span></button>
                    <button class="tab-btn" data-filter="absent">Missing/absent <span class="count">2</span></button>
                </div>
            </div>
        </div>
    </div>

    <!-- ===== TEAM TABLE ===== -->
    <div class="row" style="margin-top:4px;">
        <div class="col-12">
            <div class="glass-card" style="padding:0; overflow:hidden;">

                <div style="overflow-x:auto;">
                    <table class="team-table" id="teamTable">
                        <thead>
                            <tr>
                                <th>Member</th>
                                <th>Status</th>
                                <th>IN</th>
                                <th>OUT</th>
                                <th>Net Hours</th>
                                <th>Timeline</th>
                            </tr>
                        </thead>
                        <tbody id="teamTableBody">
                            <tr data-status="present">
                                <td><strong>John Doe</strong><br><span
                                        style="font-size:11px; color:var(--text-muted);">GP-1003</span></td>
                                <td><span class="status-badge-sm present">Present</span></td>
                                <td>09:00</td>
                                <td>18:00</td>
                                <td>8h 0m</td>
                                <td><span class="timeline-on-time"><i class="bi bi-check-circle"></i> On Time</span></td>
                            </tr>
                            <tr data-status="late">
                                <td><strong>Jane Smith</strong><br><span
                                        style="font-size:11px; color:var(--text-muted);">GP-1004</span></td>
                                <td><span class="status-badge-sm late">Late</span></td>
                                <td>09:35</td>
                                <td>18:00</td>
                                <td>7h 25m</td>
                                <td><span class="timeline-late"><i class="bi bi-clock"></i> Late by 35m</span></td>
                            </tr>
                            <tr data-status="present">
                                <td><strong>Mike Johnson</strong><br><span
                                        style="font-size:11px; color:var(--text-muted);">GP-1005</span></td>
                                <td><span class="status-badge-sm present">Present</span></td>
                                <td>08:55</td>
                                <td>17:30</td>
                                <td>7h 35m</td>
                                <td><span class="timeline-on-time"><i class="bi bi-check-circle"></i> On Time</span></td>
                            </tr>
                            <tr data-status="absent">
                                <td><strong>Sarah Lee</strong><br><span
                                        style="font-size:11px; color:var(--text-muted);">GP-1006</span></td>
                                <td><span class="status-badge-sm absent">Absent</span></td>
                                <td>—</td>
                                <td>—</td>
                                <td>—</td>
                                <td><span class="timeline-absent"><i class="bi bi-x-circle"></i> No Punch</span></td>
                            </tr>
                            <tr data-status="absent">
                                <td><strong>David Khan</strong><br><span
                                        style="font-size:11px; color:var(--text-muted);">GP-1007</span></td>
                                <td><span class="status-badge-sm missing">Missing</span></td>
                                <td>—</td>
                                <td>—</td>
                                <td>—</td>
                                <td><span class="timeline-absent"><i class="bi bi-x-circle"></i> No Punch</span></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

            </div>
        </div>
    </div>

@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {

            // ===== STATUS FILTER TABS =====
            const tabBtns = document.querySelectorAll('.tab-btn');
            const rows = document.querySelectorAll('#teamTableBody tr');

            tabBtns.forEach(btn => {
                btn.addEventListener('click', function () {
                    // Remove active from all tabs
                    tabBtns.forEach(b => b.classList.remove('active'));
                    this.classList.add('active');

                    const filter = this.dataset.filter;

                    rows.forEach(row => {
                        if (filter === 'all') {
                            row.style.display = '';
                        } else {
                            const status = row.dataset.status;
                            if (status === filter) {
                                row.style.display = '';
                            } else {
                                row.style.display = 'none';
                            }
                        }
                    });
                });
            });

        });
    </script>
@endpush