@extends('attendence.layouts.app')

@section('title', 'Attendance Patterns')
@section('page-title', 'Attendance Patterns')

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

    .summary-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
        gap: 14px;
        margin: 16px 0;
    }
    .summary-card {
        background: var(--bg-topbar);
        border: 1px solid var(--border-color);
        border-radius: 12px;
        padding: 14px 16px;
        text-align: center;
        transition: var(--transition);
    }
    .summary-card:hover {
        transform: translateY(-3px);
        box-shadow: var(--shadow);
        border-color: var(--primary);
    }
    .summary-card .number {
        font-size: 28px;
        font-weight: 700;
        color: var(--text-primary);
    }
    .summary-card .label {
        font-size: 12px;
        color: var(--text-muted);
    }
    .summary-card .sub {
        font-size: 11px;
        color: var(--text-muted);
        opacity: 0.6;
        margin-top: 2px;
    }

    .section-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 8px;
        margin: 16px 0 12px;
    }
    .section-header h6 {
        font-size: 14px;
        font-weight: 600;
        color: var(--text-primary);
        margin: 0;
    }
    .section-header .sub {
        font-size: 12px;
        color: var(--text-muted);
    }

    .patterns-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 13px;
        min-width: 700px;
    }
    .patterns-table th {
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
    .patterns-table td {
        padding: 10px 14px;
        border-bottom: 1px solid var(--border-color);
        color: var(--text-secondary);
        vertical-align: middle;
        text-align: center;
    }
    .patterns-table td:first-child {
        text-align: left;
    }
    .patterns-table tr:hover td {
        background: var(--hover-bg);
    }

    .patterns-table .member-name {
        font-weight: 600;
        color: var(--text-primary);
    }
    .patterns-table .member-id {
        font-size: 11px;
        color: var(--text-muted);
    }

    .badge-punctuality {
        padding: 2px 12px;
        border-radius: 12px;
        font-size: 11px;
        font-weight: 600;
        display: inline-block;
    }
    .badge-punctuality.good {
        background: #d1fae5;
        color: #059669;
    }
    .badge-punctuality.average {
        background: #fef3c7;
        color: #d97706;
    }
    .badge-punctuality.poor {
        background: #fee2e2;
        color: #dc2626;
    }
    [data-theme="dark"] .badge-punctuality.good {
        background: #064e3b;
        color: #34d399;
    }
    [data-theme="dark"] .badge-punctuality.average {
        background: #78350f;
        color: #fbbf24;
    }
    [data-theme="dark"] .badge-punctuality.poor {
        background: #7f1d1d;
        color: #f87171;
    }

    .status-dot {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        display: inline-block;
        margin-right: 4px;
    }
    .status-dot.green { background: #22c55e; }
    .status-dot.yellow { background: #f59e0b; }
    .status-dot.red { background: #ef4444; }

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
.btn-group {
    gap: 10px;
}

tbody, td, tfoot, th, thead, tr {
    white-space: nowrap;
}

    @media (max-width: 768px) {
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
        .summary-grid {
            grid-template-columns: 1fr 1fr;
        }
        .patterns-table {
            font-size: 12px;
            min-width: 550px;
        }
        .patterns-table th,
        .patterns-table td {
            padding: 6px 8px;
        }
    }
    @media (max-width: 576px) {
        .glass-card {
            padding: 12px;
        }
        .summary-grid {
            grid-template-columns: 1fr;
        }
        .patterns-table {
            font-size: 11px;
            min-width: 450px;
        }
        .patterns-table th,
        .patterns-table td {
            padding: 4px 6px;
        }
        .badge-punctuality {
            font-size: 9px;
            padding: 1px 8px;
        }
    }
</style>
@endpush

@section('content')

<!-- ===== FILTER BAR ===== -->
<div class="row">
    <div class="col-12">
        <div class="glass-card">
            <form method="GET" action="{{ route('line-manager.attendance.patterns') }}">
                <div class="filter-bar">

                    <div class="form-group">
                        <label>Period</label>
                        <select class="form-control" name="period">
                            <option value="30" selected>Last 30 days</option>
                            <option value="60">Last 60 days</option>
                            <option value="90">Last 90 days</option>
                            <option value="cycle">Current cycle</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Department</label>
                        <select class="form-control" name="department">
                            <option value="all">All departments</option>
                            <option value="ict">ICT</option>
                            <option value="engineering">Engineering</option>
                            <option value="hr">HR</option>
                            <option value="finance">Finance</option>
                            <option value="marketing">Marketing</option>
                        </select>
                    </div>

                    <div class="btn-group">
                        <button type="submit" class="btn-primary-glass"><i class="bi bi-funnel"></i> Filter</button>
                        <a href="{{ route('manager.attendance.patterns') }}" class="btn-outline-glass"><i class="bi bi-arrow-counterclockwise"></i> Clear</a>
                        <button class="btn-outline-glass" onclick="alert('Export coming soon!')"><i class="bi bi-download"></i> Export</button>
                    </div>

                </div>
            </form>
        </div>
    </div>
</div>

<!-- ===== SUMMARY CARDS ===== -->
<div class="row">
    <div class="col-12">
        <div class="summary-grid">
            <div class="summary-card">
                <div class="number" style="color:#3b82f6;">87%</div>
                <div class="label">Avg Attendance</div>
                <div class="sub">team, 30 days</div>
            </div>
            <div class="summary-card">
                <div class="number" style="color:#d97706;">12</div>
                <div class="label">Total Late Arrivals</div>
                <div class="sub">this period</div>
            </div>
            <div class="summary-card">
                <div class="number" style="color:#ef4444;">2</div>
                <div class="label">Members to Watch</div>
                <div class="sub">≥3 late, absence, or &lt;90%</div>
            </div>
        </div>
    </div>
</div>

<!-- ===== PER-MEMBER PATTERNS TABLE ===== -->
<div class="row" style="margin-top:4px;">
    <div class="col-12">
        <div class="glass-card" style="padding:0; overflow:hidden;">

            <div class="section-header" style="padding:16px 20px 0 20px;">
                <div>
                    <h6>Per-Member Patterns</h6>
                    <span class="sub">5 members · 30-day period</span>
                </div>
            </div>

            <div style="overflow-x:auto; padding:0 4px 4px 4px;">
                <table class="patterns-table">
                    <thead>
                        <tr>
                            <th>Member</th>
                            <th>Attendance</th>
                            <th>Late Arrivals</th>
                            <th>Early</th>
                            <th>Half</th>
                            <th>Absent</th>
                            <th>Punctuality</th>
                            <th>Avg Hrs</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Member 1 -->
                        <tr>
                            <td>
                                <span class="member-name">John Doe</span>
                                <br>
                                <span class="member-id">GP-1003</span>
                            </td>
                            <td><span style="font-weight:600; color:#22c55e;">96%</span></td>
                            <td>2</td>
                            <td>0</td>
                            <td>1</td>
                            <td>0</td>
                            <td><span class="badge-punctuality good"><span class="status-dot green"></span> Good</span></td>
                            <td>8.2h</td>
                        </tr>

                        <!-- Member 2 -->
                        <tr>
                            <td>
                                <span class="member-name">Jane Smith</span>
                                <br>
                                <span class="member-id">GP-1004</span>
                            </td>
                            <td><span style="font-weight:600; color:#f59e0b;">84%</span></td>
                            <td>4</td>
                            <td>0</td>
                            <td>2</td>
                            <td>1</td>
                            <td><span class="badge-punctuality average"><span class="status-dot yellow"></span> Average</span></td>
                            <td>7.5h</td>
                        </tr>

                        <!-- Member 3 -->
                        <tr>
                            <td>
                                <span class="member-name">Mike Johnson</span>
                                <br>
                                <span class="member-id">GP-1005</span>
                            </td>
                            <td><span style="font-weight:600; color:#22c55e;">92%</span></td>
                            <td>1</td>
                            <td>1</td>
                            <td>0</td>
                            <td>1</td>
                            <td><span class="badge-punctuality good"><span class="status-dot green"></span> Good</span></td>
                            <td>8.0h</td>
                        </tr>

                        <!-- Member 4 — Members to Watch -->
                        <tr style="border-left: 3px solid #ef4444;">
                            <td>
                                <span class="member-name">Sarah Lee</span>
                                <br>
                                <span class="member-id">GP-1006</span>
                                <br>
                                <span style="font-size:10px; color:#ef4444; font-weight:600;"><i class="bi bi-exclamation-triangle"></i> Needs attention</span>
                            </td>
                            <td><span style="font-weight:600; color:#ef4444;">72%</span></td>
                            <td>5</td>
                            <td>0</td>
                            <td>3</td>
                            <td>4</td>
                            <td><span class="badge-punctuality poor"><span class="status-dot red"></span> Poor</span></td>
                            <td>6.8h</td>
                        </tr>

                        <!-- Member 5 — Members to Watch -->
                        <tr style="border-left: 3px solid #ef4444;">
                            <td>
                                <span class="member-name">David Khan</span>
                                <br>
                                <span class="member-id">GP-1007</span>
                                <br>
                                <span style="font-size:10px; color:#ef4444; font-weight:600;"><i class="bi bi-exclamation-triangle"></i> Needs attention</span>
                            </td>
                            <td><span style="font-weight:600; color:#ef4444;">68%</span></td>
                            <td>6</td>
                            <td>2</td>
                            <td>1</td>
                            <td>5</td>
                            <td><span class="badge-punctuality poor"><span class="status-dot red"></span> Poor</span></td>
                            <td>6.5h</td>
                        </tr>
                    </tbody>
                </table>
            </div>

        </div>
    </div>
</div>

@endsection