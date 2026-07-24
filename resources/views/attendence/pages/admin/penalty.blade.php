@extends('attendence.layouts.app')

@section('title', 'Penalty & Waivers')
@section('page-title', 'Penalty & Waivers')

@push('styles')
    <style>
        .glass-card {
            background: var(--bg-topbar);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 20px 24px;
            transition: var(--transition);
            box-shadow: var(--shadow);
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
            min-width: 120px;
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
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 14px;
            margin: 16px 0;
        }

        .summary-card {
            background: var(--hover-bg);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 14px 16px;
            text-align: center;
            transition: var(--transition);
        }

        .summary-card:hover {
            border-color: var(--primary);
        }

        .summary-card .number {
            font-size: 26px;
            font-weight: 700;
            color: var(--text-primary);
            line-height: 1.2;
        }

        .summary-card .label {
            font-size: 12px;
            color: var(--text-muted);
            margin-top: 2px;
        }

        .summary-card .sub {
            font-size: 11px;
            color: var(--text-muted);
            opacity: 0.6;
            margin-top: 2px;
        }

        .table-wrap {
            overflow-x: auto;
            margin-top: 4px;
            -webkit-overflow-scrolling: touch;      
        }

        .table-wrap table {
            width: 100%;
            border-collapse: collapse;
            min-width: 750px;
        }

        .table-wrap th {
            text-align: left;
            padding: 10px 12px;
            font-size: 10px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--text-muted);
            border-bottom: 2px solid var(--border-color);
            background: var(--hover-bg);
        }

        .table-wrap td {
            padding: 9px 12px;
            font-size: 13px;
            color: var(--text-secondary);
            border-bottom: 1px solid var(--border-color);
            vertical-align: middle;
            text-align: left;
        }

        .table-wrap td:first-child {
            text-align: left;
        }

        .table-wrap tr:hover td {
            background: var(--hover-bg);
        }

        .badge-waiver-pending {
            background: #fef3c7;
            color: #d97706;
            padding: 2px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
        }

        [data-theme="dark"] .badge-waiver-pending {
            background: #78350f;
            color: #fbbf24;
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

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            margin: 16px 0 12px;
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

        .info-box {
            background: var(--hover-bg);
            border: 1px solid var(--border-color);
            border-radius: 10px;
            padding: 14px 18px;
            font-size: 13px;
            color: var(--text-secondary);
            line-height: 1.6;
            margin-top: 16px;
        }

        .info-box i {
            color: var(--primary);
            margin-right: 6px;
        }

        .btn-group {
            gap: 10px !important;
        }

          .table-wrap th, .table-wrap td {
                white-space: nowrap;
            }

        @media (max-width: 992px) {
            .filter-bar .form-group {
                min-width: 100px;
            }

            .summary-grid {
                grid-template-columns: repeat(2, 1fr);
            }
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
                grid-template-columns: repeat(2, 1fr);
            }

          

        }

        @media (max-width: 576px) {
            .glass-card {
                padding: 12px;
            }

            .summary-grid {
                grid-template-columns: 1fr 1fr;
            }

            .summary-card .number {
                font-size: 18px;
            }

            .table-wrap th,
            .table-wrap td {
                padding: 6px 8px;
                font-size: 11px;
            }
        }
    </style>
@endpush

@section('content')


    <!-- FILTER BAR   -->
    <div class="row">
        <div class="col-12">
            <div class="glass-card">
                <form method="GET" action="{{ route('admin.penalty') }}" id="filterForm">
                    <div class="filter-bar">

                        <div class="form-group">
                            <label>From</label>
                            <input type="date" class="form-control" name="from" id="filterFrom" value="{{ $from }}">
                        </div>

                        <div class="form-group">
                            <label>To</label>
                            <input type="date" class="form-control" name="to" id="filterTo" value="{{ $to }}">
                        </div>

                        <div class="form-group">
                            <label>Department</label>
                            <select class="form-control" name="department" id="filterDepartment">
                                <option value="all" {{ $department == 'all' ? 'selected' : '' }}>All departments</option>
                                <option value="ICT" {{ $department == 'ICT' ? 'selected' : '' }}>ICT</option>
                                <option value="Engineering" {{ $department == 'Engineering' ? 'selected' : '' }}>Engineering
                                </option>
                                <option value="HR" {{ $department == 'HR' ? 'selected' : '' }}>HR</option>
                                <option value="Finance" {{ $department == 'Finance' ? 'selected' : '' }}>Finance</option>
                                <option value="Marketing" {{ $department == 'Marketing' ? 'selected' : '' }}>Marketing
                                </option>
                            </select>
                        </div>

                        <div class="form-group" style="flex: 1.5;">
                            <label>Search</label>
                            <input type="text" class="form-control" name="search" id="filterSearch"
                                placeholder="Name or Employee ID" value="{{ $search }}">
                        </div>
                        <!-- Clear Button with Default Dates -->
                        <a href="{{ route('admin.penalty', ['from' => '2026-06-26', 'to' => '2026-07-25']) }}"
                            class="btn-outline-glass">
                            <i class="bi bi-arrow-counterclockwise"></i>
                        </a>
                        <div class="btn-group">
                            <!-- Filter Button -->
                            <button type="submit" class="btn-primary-glass">
                                <i class="bi bi-funnel"></i> Filter
                            </button>



                            <!-- Export CSV -->
                            <button type="submit" formaction="{{ route('admin.penalty.export') }}"
                                class="btn-outline-glass">
                                <i class="bi bi-download"></i> Export CSV
                            </button>
                        </div>

                    </div>
                </form>
            </div>
        </div>
    </div>


    <!-- SUMMARY CARDS    -->

    <div class="row" style="margin-top: 16px;">
        <div class="col-12">
            <div class="summary-grid">
                <div class="summary-card">
                    <div class="number">{{ $summary['penalties'] }}</div>
                    <div class="label">Employees with Penalties</div>
                    <div class="sub">this cycle</div>
                </div>
                <div class="summary-card">
                    <div class="number">{{ $summary['cl_to_deduct'] }}</div>
                    <div class="label">Casual Leave to Deduct</div>
                    <div class="sub">day(s)</div>
                </div>
                <div class="summary-card">
                    <div class="number">{{ $summary['salary_deductions'] }}</div>
                    <div class="label">Salary-Day Deductions</div>
                    <div class="sub">where CL insufficient</div>
                </div>
                <div class="summary-card">
                    <div class="number">{{ $summary['waivers_pending'] }}</div>
                    <div class="label">Waivers Pending</div>
                    <div class="sub">awaiting review</div>
                </div>
            </div>
        </div>
    </div>

    <!-- PENALTY SUMMARY TABLE                      -->

    <div class="row" style="margin-top: 16px;">
        <div class="col-12">
            <div class="glass-card">

                <div class="section-header">
                    <div>
                        <h6>Penalty Summary</h6>
                        <span class="subtitle">{{ count($employees) }} employee ·
                            {{ \Carbon\Carbon::parse($from)->format('d M Y') }} –
                            {{ \Carbon\Carbon::parse($to)->format('d M Y') }}</span>
                    </div>
                </div>

                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>Dept</th>
                                <th>Late</th>
                                <th>Half-Days</th>
                                <th>Waivers</th>
                                <th>CL Due</th>
                                <th>CL Balance</th>
                                <th>CL Deducted</th>
                                <th>Salary Days</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($employees as $emp)
                                <tr>
                                    <td>
                                        <strong>{{ $emp['employee'] }}</strong>
                                        <br>
                                        <span style="font-size:11px; color:var(--text-muted);">{{ $emp['id'] }}</span>
                                    </td>
                                    <td>{{ $emp['dept'] }}</td>
                                    <td>{{ $emp['late'] }}</td>
                                    <td>{{ $emp['half_days'] }}</td>
                                    <td>
                                        @if($emp['waivers'] === 'Pending')
                                            <span class="badge-waiver-pending">Pending</span>
                                        @else
                                            {{ $emp['waivers'] }}
                                        @endif
                                    </td>
                                    <td>{{ $emp['cl_due'] }}</td>
                                    <td>{{ $emp['cl_balance'] }}</td>
                                    <td>{{ $emp['cl_deducted'] }}</td>
                                    <td>{{ $emp['salary_days'] }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9">
                                        <div class="empty-state">
                                            <i class="bi bi-inbox"></i>
                                            <p>No records found.</p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

            </div>
        </div>
    </div>


    <!-- INFO FOOTER                               -->

    <div class="row" style="margin-top: 16px;">
        <div class="col-12">
            <div class="info-box">
                <i class="bi bi-info-circle"></i>
                <strong>How penalties work:</strong>
                Every 3 late arrivals in a cycle equal 1 Casual Leave (the first 2 carry no penalty);
                every 2 half-days equal 1 Casual Leave. Where the Casual Leave balance is insufficient,
                the shortfall is a salary-day deduction. Waivers are reviewed under Approvals;
                approved items are removed from the penalty count. Evaluated per attendance cycle;
                payroll applies the resulting deductions.
            </div>
        </div>
    </div>

@endsection