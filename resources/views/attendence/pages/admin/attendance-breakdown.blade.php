@extends('attendence.layouts.app')

@section('title', 'Attendance Breakdown')
@section('page-title', 'Attendance Breakdown')

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

        .btn-back-glass {
            background: transparent;
            border: 1px solid var(--border-color);
            color: var(--text-secondary);
            padding: 7px 16px;
            border-radius: 8px;
            font-size: 13px;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 6px;
            text-decoration: none;
        }

        .btn-back-glass:hover {
            background: var(--hover-bg);
            border-color: var(--primary);
            color: var(--text-primary);
        }

        /* ===== BOOTSTRAP TABS ===== */
        .nav-tabs-custom {
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 0;
            margin-bottom: 16px;
            gap: 4px;
            flex-wrap: wrap;
        }

        .nav-tabs-custom .nav-link {
            background: transparent;
            border: none;
            padding: 6px 16px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 500;
            color: var(--text-secondary);
            cursor: pointer;
            transition: var(--transition);
            position: relative;
        }

        .nav-tabs-custom .nav-link:hover {
            background: var(--hover-bg);
            color: var(--text-primary);
        }

        .nav-tabs-custom .nav-link.active {
            background: var(--primary);
            color: #fff;
        }

        .nav-tabs-custom .nav-link .num {
            font-weight: 700;
        }

        .tab-pane {
            animation: fadeIn 0.25s ease;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(6px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* ===== TABLE ===== */
        .table-wrap {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            margin-top: 4px;
            border-radius: 8px;
        }

        .table-wrap table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
            min-width: 1000px;
        }

        .table-wrap th {
            text-align: left;
            padding: 8px 10px;
            font-size: 10px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--text-muted);
            border-bottom: 2px solid var(--border-color);
            background: var(--hover-bg);
            white-space: nowrap;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .table-wrap td {
            padding: 8px 10px;
            color: var(--text-secondary);
            border-bottom: 1px solid var(--border-color);
            vertical-align: middle;
            white-space: nowrap;
        }

        .table-wrap tr:hover td {
            background: var(--hover-bg);
        }

        .status-badge-sm {
            padding: 2px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            display: inline-block;
        }

        .status-badge-sm.present {
            background: #d1fae5;
            color: #059669;
        }

        .status-badge-sm.absent {
            background: #fee2e2;
            color: #dc2626;
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

        .status-badge-sm.missing-punch {
            background: #fee2e2;
            color: #dc2626;
        }

        .status-badge-sm.on-leave {
            background: #dbeafe;
            color: #1d4ed8;
        }

        .status-badge-sm.holiday {
            background: #ede9fe;
            color: #6d28d9;
        }

        [data-theme="dark"] .status-badge-sm.present {
            background: #064e3b;
            color: #34d399;
        }

        [data-theme="dark"] .status-badge-sm.absent {
            background: #7f1d1d;
            color: #f87171;
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

        [data-theme="dark"] .status-badge-sm.missing-punch {
            background: #7f1d1d;
            color: #f87171;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 12px;
        }

        .section-header h6 {
            font-size: 14px;
            font-weight: 600;
            color: var(--text-primary);
            margin: 0;
        }

        .section-header .subtitle {
            font-size: 12px;
            color: var(--text-muted);
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

        .top-bar {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            margin-bottom: 16px;
        }

        .shift-badge {
            background: var(--hover-bg);
            padding: 1px 8px;
            border-radius: 4px;
            font-size: 11px;
            color: var(--text-muted);
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

            .top-bar {
                flex-direction: column;
                align-items: stretch;
            }

            .nav-tabs-custom .nav-link {
                font-size: 11px;
                padding: 12px 17px;
            }

            .table-wrap table {
                font-size: 12px;
                min-width: 800px;
            }

            .table-wrap th,
            .table-wrap td {
                padding: 6px 8px;
            }
        }

        @media (max-width: 576px) {
            .glass-card {
                padding: 12px;
            }

            .nav-tabs-custom .nav-link {
                font-size: 11px;
                padding: 12px 17px;
            }

            .table-wrap table {
                font-size: 11px;
                min-width: 700px;
            }

            .table-wrap th,
            .table-wrap td {
                padding: 4px 6px;
            }
        }
    </style>
@endpush

@section('content')

    <!-- TOP BAR — Back to Dashboard                -->
    <div class="top-bar">
        <a href="{{ route('dashboard') }}" class="btn-back-glass">
            <i class="bi bi-arrow-left"></i> Back to dashboard
        </a>
        <span style="font-size:13px; color:var(--text-muted);">
            <i class="bi bi-calendar3"></i> Attendance Breakdown
        </span>
    </div>


    <!-- FILTER BAR                                -->
    <div class="row">
        <div class="col-12">
            <div class="glass-card">
                <form method="GET" action="{{ route('admin.attendance.breakdown') }}">
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
                            <label>Department</label>
                            <select class="form-control" name="department">
                                <option value="all" {{ $department == 'all' ? 'selected' : '' }}>All departments</option>
                                @foreach($departments as $dept)
                                    <option value="{{ $dept->name }}" {{ $department == $dept->name ? 'selected' : '' }}>
                                        {{ $dept->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group" style="flex:1.5;">
                            <label>Search</label>
                            <input type="text" class="form-control" name="search" placeholder="Name or Employee ID"
                                value="{{ $search }}">
                        </div>
                        <div class="btn-group">
                            <button type="submit" class="btn-primary-glass"><i class="bi bi-funnel"></i> Filter</button>
                            <a href="{{ route('admin.attendance.breakdown') }}" class="btn-outline-glass"><i
                                    class="bi bi-arrow-counterclockwise"></i> Clear</a>
                            <a href="{{ route('admin.attendance.breakdown.export', ['from' => $from, 'to' => $to, 'department' => $department, 'search' => $search, 'tab' => 'all']) }}"
                                class="btn-outline-glass" id="exportLink"><i class="bi bi-download"></i> Export CSV</a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>


    @php
        $tabs = [
            'all' => 'All records',
            'missing' => 'Missing Punch',
            'halfday' => 'Half Day',
            'earlyout' => 'Early Out',
            'absent' => 'Absent',
            'late' => 'Late',
            'present' => 'Present',
            'onleave' => 'On Leave',
        ];
    @endphp

    <!-- BOOTSTRAP TABS — STATS                    -->
    <div class="row">
        <div class="col-12">
            <div class="glass-card" style="padding-bottom:4px;">
                <ul class="nav nav-tabs nav-tabs-custom" id="statsTabs" role="tablist">
                    @foreach ($tabs as $key => $label)
                        <li class="nav-item" role="presentation">
                            <button class="nav-link {{ $loop->first ? 'active' : '' }}" id="{{ $key }}-tab" data-bs-toggle="tab"
                                data-bs-target="#{{ $key }}" data-export-tab="{{ $key }}" type="button" role="tab">
                                <span class="num">{{ $grouped[$key]->count() }}</span> {{ $label }}
                            </button>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>

    <!-- TAB CONTENT                               -->
    <div class="row" style="margin-top:16px;">
        <div class="col-12">
            <div class="glass-card">
                <div class="tab-content">
                    @foreach ($tabs as $key => $label)
                        <div class="tab-pane fade {{ $loop->first ? 'show active' : '' }}" id="{{ $key }}" role="tabpanel">
                            <div class="section-header">
                                <div>
                                    <h6>{{ $key === 'all' ? 'All Records' : $label }}</h6>
                                    <span class="subtitle">{{ $grouped[$key]->count() }} {{ $grouped[$key]->count() === 1 ? 'record' : 'records' }}</span>
                                </div>
                            </div>

                            @if ($grouped[$key]->isEmpty())
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
                                                        <span class="status-badge-sm {{ \Illuminate\Support\Str::slug($row['status']) }}">{{ $row['status'] }}</span>
                                                        @if ($row['remarks'] !== $row['status'] && ! in_array($row['remarks'], ['On Time', '—'], true))
                                                            <br><span style="font-size:10px; color:var(--text-muted);">{{ $row['remarks'] }}</span>
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
        document.addEventListener('DOMContentLoaded', function () {
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

            // Open the tab from ?type= (dashboard cards and pagination links)
            const type = new URLSearchParams(window.location.search).get('type');
            const tabButton = tabMap[type] ? document.getElementById(tabMap[type]) : null;
            if (tabButton) {
                bootstrap.Tab.getOrCreateInstance(tabButton).show();
            }
        });
    </script>
@endpush
