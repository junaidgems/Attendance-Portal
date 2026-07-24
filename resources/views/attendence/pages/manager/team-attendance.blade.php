@extends('attendence.layouts.app')

@section('title', 'Team Attendance')
@section('page-title', 'Team Attendance')

@push('styles')
    <style>
        .glass-card {
            background: var(--bg-topbar);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 20px 24px;
            transition: var(--transition);
            box-shadow: var(--shadow);
            margin-bottom: 20px;
        }

        .glass-card:hover {
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.06);
        }

        .filter-bar {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            align-items: flex-end;
        }

        .filter-bar .form-group {
            display: flex;
            flex-direction: column;
            gap: 4px;
            flex: 1;
            min-width: 130px;
        }

        .filter-bar .form-group label {
            font-size: 10px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--text-muted);
        }

        .filter-bar .form-control {
            background: var(--bg-body);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 7px 12px;
            color: var(--text-primary);
            font-size: 13px;
            width: 100%;
        }

        .filter-bar .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.08);
        }

        .filter-bar .btn-group {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .btn-primary-glass {
            background: var(--primary);
            color: #fff;
            border: none;
            padding: 7px 16px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 13px;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .btn-primary-glass:hover {
            opacity: 0.85;
            transform: translateY(-1px);
            box-shadow: 0 4px 16px rgba(59, 130, 246, 0.3);
        }

        .btn-outline-glass {
            background: transparent;
            border: 1px solid var(--border-color);
            color: var(--text-secondary);
            padding: 7px 14px;
            border-radius: 8px;
            font-size: 13px;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .btn-outline-glass:hover {
            background: var(--hover-bg);
            border-color: var(--primary);
            color: var(--text-primary);
        }

        .day-status {
            display: flex;
            align-items: center;
            gap: 8px;
            margin: 12px 0;
            padding: 8px 14px;
            background: var(--hover-bg);
            border-radius: 8px;
            border: 1px solid var(--border-color);
        }

        .day-status .badge-working {
            background: #22c55e;
            color: #fff;
            padding: 2px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }

        .day-status .badge-nonworking {
            background: #ef4444;
            color: #fff;
            padding: 2px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }

        .status-tabs {
            display: flex;
            flex-wrap: wrap;
            gap: 4px; 
            padding-bottom: 8px;
            border-bottom: 1px solid var(--border-color);
        }

        .status-tabs .tab-btn {
            background: transparent;
            border: none;
            padding: 4px 16px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            color: var(--text-secondary);
            cursor: pointer;
            transition: var(--transition);
        }

        .status-tabs .tab-btn:hover {
            background: var(--hover-bg);
        }

        .status-tabs .tab-btn.active {
            background: var(--primary);
            color: #fff;
        }

        .status-tabs .tab-btn .count {
            background: var(--hover-bg);
            padding: 0 8px;
            border-radius: 10px;
            font-size: 10px;
            margin-left: 4px;
        }

        .status-tabs .tab-btn.active .count {
            background: rgba(255, 255, 255, 0.2);
            color: #fff;
        }

        .team-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
            min-width: 700px;
        }

        .team-table th {
            text-align: left;
            padding: 10px 14px;
            font-size: 10px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--text-muted);
            border-bottom: 2px solid var(--border-color);
            background: var(--hover-bg);
        }

        .team-table td {
            padding: 10px 14px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-secondary);
            vertical-align: middle;
        }

        .team-table tr:hover td {
            background: var(--hover-bg);
        }

        .status-badge-sm {
            padding: 2px 12px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            display: inline-block;
        }

        .status-badge-sm.present {
            background: #d1fae5;
            color: #059669;
        }

        .status-badge-sm.late {
            background: #fef3c7;
            color: #d97706;
        }

        .status-badge-sm.early-out {
            background: #fef3c7;
            color: #d97706;
        }

        .status-badge-sm.half-day {
            background: #fef3c7;
            color: #d97706;
        }

        .status-badge-sm.absent {
            background: #fee2e2;
            color: #dc2626;
        }

        .status-badge-sm.missing {
            background: #fee2e2;
            color: #dc2626;
        }

        [data-theme="dark"] .status-badge-sm.present {
            background: #064e3b;
            color: #34d399;
        }

        [data-theme="dark"] .status-badge-sm.late {
            background: #78350f;
            color: #fbbf24;
        }

        [data-theme="dark"] .status-badge-sm.early-out {
            background: #78350f;
            color: #fbbf24;
        }

        [data-theme="dark"] .status-badge-sm.half-day {
            background: #78350f;
            color: #fbbf24;
        }

        [data-theme="dark"] .status-badge-sm.absent {
            background: #7f1d1d;
            color: #f87171;
        }

        [data-theme="dark"] .status-badge-sm.missing {
            background: #7f1d1d;
            color: #f87171;
        }

        .timeline-on-time {
            color: #059669;
            font-weight: 500;
        }

        .timeline-late {
            color: #d97706;
            font-weight: 500;
        }

        .timeline-absent {
            color: #dc2626;
            font-weight: 500;
        }

        .empty-state {
            text-align: center;
            padding: 30px 20px;
            color: var(--text-muted);
        }

        .empty-state i {
            font-size: 36px;
            opacity: 0.3;
            display: block;
            margin-bottom: 8px;
        }

        .empty-state p {
            font-size: 14px;
            margin: 0;
        }

        @media (max-width: 768px) {
            .status-tabs {
                justify-content: center;
                gap: 4px;
            }

            .day-status {
                flex-wrap: wrap;
                justify-content: center;
            }

            .glass-card {
                padding: 16px;
            }

            .filter-bar .form-group {
                min-width: 100%;
            }

            .filter-bar .btn-group {
                width: 100%;
            }

            .filter-bar .btn-group button {
                flex: 1;
                justify-content: center;
            }

            .team-table {
                font-size: 12px;
                min-width: 550px;
            }

            .team-table th,
            .team-table td {
                padding: 6px 8px;
            }

            .status-tabs .tab-btn {
                font-size: 11px;
                padding: 2px 10px;
            }
        }

        @media (max-width: 576px) {
            .glass-card {
                padding: 12px;
            }

            .team-table {
                font-size: 11px;
                min-width: 450px;
            }

            .team-table th,
            .team-table td {
                padding: 4px 6px;
            }

            .status-tabs .tab-btn {
                font-size: 10px;
                padding: 2px 8px;
            }
        }
    </style>
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