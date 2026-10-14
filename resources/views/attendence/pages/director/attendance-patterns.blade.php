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
            <form method="GET" action="{{ route('director.attendance.patterns') }}">
                <div class="filter-bar">

                    <div class="form-group">
                        <label>Period</label>
                        <select class="form-control" name="period">
                            <option value="30" @selected($period === '30')>Last 30 days</option>
                            <option value="60" @selected($period === '60')>Last 60 days</option>
                            <option value="90" @selected($period === '90')>Last 90 days</option>
                            <option value="cycle" @selected($period === 'cycle')>Current cycle</option>
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

                    <div class="btn-group">
                        <button type="submit" class="btn-primary-glass"><i class="bi bi-funnel"></i> Filter</button>
                        <a href="{{ route('director.attendance.patterns') }}" class="btn-outline-glass"><i class="bi bi-arrow-counterclockwise"></i> Clear</a>
                        <a href="{{ route('director.attendance.patterns.export', ['period' => $period, 'department' => $department]) }}" class="btn-outline-glass"><i class="bi bi-download"></i> Export</a>
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
                <div class="number" style="color:#3b82f6;">{{ $summary['avg_attendance'] !== null ? $summary['avg_attendance'] . '%' : '—' }}</div>
                <div class="label">Avg Attendance</div>
                <div class="sub">company, {{ $periodLabel }}</div>
            </div>
            <div class="summary-card">
                <div class="number" style="color:#d97706;">{{ $summary['total_late'] }}</div>
                <div class="label">Total Late Arrivals</div>
                <div class="sub">this period</div>
            </div>
            <div class="summary-card">
                <div class="number" style="color:#ef4444;">{{ $summary['watch_count'] }}</div>
                <div class="label">Members to Watch</div>
                <div class="sub">≥3 late, absence, or &lt;90%</div>
            </div>
        </div>
    </div>
</div>

<!-- ===== PER-MEMBER PATTERNS TABLE ===== -->
@php
    $punctualityClass = ['Good' => ['good', 'green'], 'Average' => ['average', 'yellow'], 'Poor' => ['poor', 'red']];
    $attendanceColor = fn ($pct) => $pct === null ? 'var(--text-muted)' : ($pct >= 90 ? '#22c55e' : ($pct >= 75 ? '#f59e0b' : '#ef4444'));
@endphp
<div class="row" style="margin-top:4px;">
    <div class="col-12">
        <div class="glass-card" style="padding:0; overflow:hidden;">

            <div class="section-header" style="padding:16px 20px 0 20px;">
                <div>
                    <h6>Per-Employee Patterns</h6>
                    <span class="sub">{{ count($members) }} {{ count($members) === 1 ? 'member' : 'members' }} · {{ $periodLabel }}</span>
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
                            <th>On Leave</th>
                            <th>Punctuality</th>
                            <th>Avg Hrs</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($members as $m)
                            <tr @if ($m['watch']) style="border-left: 3px solid #ef4444;" @endif>
                                <td>
                                    <span class="member-name">{{ $m['name'] }}</span>
                                    <br>
                                    <span class="member-id">{{ $m['emp_id'] }} · {{ $m['department'] }}</span>
                                    @if ($m['watch'])
                                        <br>
                                        <span style="font-size:10px; color:#ef4444; font-weight:600;"><i class="bi bi-exclamation-triangle"></i> Needs attention</span>
                                    @endif
                                </td>
                                <td>
                                    <span style="font-weight:600; color:{{ $attendanceColor($m['attendance']) }};">
                                        {{ $m['attendance'] !== null ? $m['attendance'] . '%' : '—' }}
                                    </span>
                                </td>
                                <td>{{ $m['late'] }}</td>
                                <td>{{ $m['early'] }}</td>
                                <td>{{ $m['half'] }}</td>
                                <td>{{ $m['absent'] }}</td>
                                <td>{{ $m['leave'] }}</td>
                                <td>
                                    @if (isset($punctualityClass[$m['punctuality']]))
                                        <span class="badge-punctuality {{ $punctualityClass[$m['punctuality']][0] }}">
                                            <span class="status-dot {{ $punctualityClass[$m['punctuality']][1] }}"></span> {{ $m['punctuality'] }}
                                        </span>
                                    @else
                                        <span style="color:var(--text-muted);">N/A</span>
                                    @endif
                                </td>
                                <td>{{ $m['avg_hours'] !== null ? $m['avg_hours'] . 'h' : '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" style="text-align:center; padding:24px; color:var(--text-muted);">
                                    No employees found for the selected filters.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

        </div>
    </div>
</div>

@endsection
