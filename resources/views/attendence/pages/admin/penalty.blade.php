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
            text-decoration: none;
        }

        .btn-primary-glass:hover {
            opacity: 0.85;
            transform: translateY(-1px);
            box-shadow: 0 4px 16px rgba(59, 130, 246, 0.3);
            color: #fff;
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
            text-decoration: none;
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
            white-space: nowrap;
        }

        .table-wrap td {
            padding: 9px 12px;
            font-size: 13px;
            color: var(--text-secondary);
            border-bottom: 1px solid var(--border-color);
            vertical-align: middle;
            text-align: left;
            white-space: nowrap;
        }

        .table-wrap td:first-child {
            text-align: left;
        }

        .table-wrap tr:hover td {
            background: var(--hover-bg);
        }

        .employee-name {
            font-weight: 600;
            color: var(--text-primary);
        }

        .employee-id {
            font-size: 11px;
            color: var(--text-muted);
            display: inline-block;
            margin-top: 2px;
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

        .filter-actions {
            display: flex;
            align-items: center;
            gap: 10px;
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

            .filter-actions {
                width: 100%;
            }

            .filter-actions .btn-primary-glass,
            .filter-actions .btn-outline-glass {
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

            .summary-card {
                padding: 12px 8px;
            }

            .summary-card .number {
                font-size: 18px;
            }

            .summary-card .label {
                font-size: 10px;
            }

            .summary-card .sub {
                font-size: 9px;
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

    {{-- =========================================================
    FILTER BAR
    ========================================================== --}}
    <div class="row">
        <div class="col-12">

            <div class="glass-card">

                <form method="GET" action="{{ route('admin.penalty') }}" id="filterForm">

                    <div class="filter-bar">

                        {{-- FROM DATE --}}
                        <div class="form-group">
                            <label for="filterFrom">From</label>

                            <input type="date" class="form-control" name="from" id="filterFrom" value="{{ $from }}">
                        </div>


                        {{-- TO DATE --}}
                        <div class="form-group">
                            <label for="filterTo">To</label>

                            <input type="date" class="form-control" name="to" id="filterTo" value="{{ $to }}">
                        </div>


                        {{-- DEPARTMENT --}}
                        <div class="form-group">

                            <label for="filterDepartment">
                                Department
                            </label>

                            <select class="form-control" name="department" id="filterDepartment">

                                <option value="all" {{ $department === 'all' ? 'selected' : '' }}>
                                    All departments
                                </option>

                                @foreach($departments as $dept)

                                    <option value="{{ $dept->name }}" {{ $department === $dept->name ? 'selected' : '' }}>
                                        {{ $dept->name }}
                                    </option>

                                @endforeach

                            </select>

                        </div>


                        {{-- SEARCH --}}
                        <div class="form-group" style="flex: 1.5;">

                            <label for="filterSearch">
                                Search
                            </label>

                            <input type="text" class="form-control" name="search" id="filterSearch"
                                placeholder="Name or Employee ID" value="{{ $search }}">

                        </div>


                        {{-- ACTION BUTTONS --}}
                        <div class="filter-actions">

                            {{-- RESET --}}
                            <a href="{{ route('admin.penalty') }}" class="btn-outline-glass" title="Reset filters">
                                <i class="bi bi-arrow-counterclockwise"></i>
                            </a>


                            {{-- FILTER --}}
                            <button type="submit" class="btn-primary-glass">
                                <i class="bi bi-funnel"></i>
                                Filter
                            </button>


                            {{-- EXPORT --}}
                            <button type="submit" formaction="{{ route('admin.penalty.export') }}"
                                class="btn-outline-glass">
                                <i class="bi bi-download"></i>
                                Export CSV
                            </button>

                        </div>

                    </div>

                </form>

            </div>

        </div>
    </div>


    {{-- =========================================================
    SUMMARY CARDS
    ========================================================== --}}
    <div class="row" style="margin-top: 16px;">

        <div class="col-12">

            <div class="summary-grid">

                {{-- EMPLOYEES WITH PENALTIES --}}
                <div class="summary-card">

                    <div class="number">
                        {{ $summary['penalties'] ?? 0 }}
                    </div>

                    <div class="label">
                        Employees with Penalties
                    </div>

                    <div class="sub">
                        this cycle
                    </div>

                </div>


                {{-- CL TO DEDUCT --}}
                <div class="summary-card">

                    <div class="number">
                        {{ $summary['cl_to_deduct'] ?? 0 }}
                    </div>

                    <div class="label">
                        Casual Leave to Deduct
                    </div>

                    <div class="sub">
                        day(s)
                    </div>

                </div>


                {{-- SALARY DEDUCTIONS --}}
                <div class="summary-card">

                    <div class="number">
                        {{ $summary['salary_deductions'] ?? 0 }}
                    </div>

                    <div class="label">
                        Salary-Day Deductions
                    </div>

                    <div class="sub">
                        where CL insufficient
                    </div>

                </div>


                {{-- WAIVERS --}}
                <div class="summary-card">

                    <div class="number">
                        {{ $summary['waivers_pending'] ?? 0 }}
                    </div>

                    <div class="label">
                        Waivers Pending
                    </div>

                    <div class="sub">
                        awaiting review
                    </div>

                </div>

            </div>

        </div>

    </div>


    {{-- =========================================================
    PENALTY SUMMARY TABLE
    ========================================================== --}}
    <div class="row" style="margin-top: 16px;">

        <div class="col-12">

            <div class="glass-card">

                {{-- TABLE HEADER --}}
                <div class="section-header">

                    <div>

                        <h6>
                            Penalty Summary
                        </h6>

                        <span class="subtitle">

                            {{ count($employees) }}
                            {{ count($employees) == 1 ? 'employee' : 'employees' }}

                            ·

                            {{ \Carbon\Carbon::parse($from)->format('d M Y') }}

                            –

                            {{ \Carbon\Carbon::parse($to)->format('d M Y') }}

                        </span>

                    </div>

                </div>


                {{-- TABLE --}}
                <div class="table-wrap">

                    <table>

                        <thead>

                            <tr>

                                <th>
                                    Employee
                                </th>

                                <th>
                                    Dept
                                </th>

                                <th>
                                    Late
                                </th>

                                <th>
                                    Early Out
                                </th>

                                <th>
                                    Half-Days
                                </th>

                                <th>
                                    Absent
                                </th>

                                <th>
                                    Waivers
                                </th>

                                <th>
                                    CL Due
                                </th>

                                <th>
                                    CL Balance
                                </th>

                                <th>
                                    CL Deducted
                                </th>

                                <th>
                                    Salary Days
                                </th>

                            </tr>

                        </thead>


                        <tbody>

                            @forelse($employees as $emp)

                                <tr>

                                    {{-- EMPLOYEE --}}
                                    <td>

                                        <span class="employee-name">
                                            {{ $emp['employee'] }}
                                        </span>

                                        <br>

                                        <span class="employee-id">
                                            {{ $emp['id'] }}
                                        </span>

                                    </td>


                                    {{-- DEPARTMENT --}}
                                    <td>
                                        {{ $emp['dept'] }}
                                    </td>


                                    {{-- LATE --}}
                                    <td>
                                        {{ $emp['late'] }}
                                    </td>


                                    {{-- EARLY OUT --}}
                                    <td>
                                        {{ $emp['early_out'] }}
                                    </td>


                                    {{-- HALF DAYS --}}
                                    <td>
                                        {{ $emp['half_days'] }}
                                    </td>


                                    {{-- ABSENT --}}
                                    <td>
                                        {{ $emp['absent'] }}
                                    </td>


                                    {{-- WAIVERS --}}
                                    <td>

                                        @if($emp['waivers'] === 'Pending')

                                            <span class="badge-waiver-pending">
                                                Pending
                                            </span>

                                        @else

                                            {{ $emp['waivers'] }}

                                        @endif

                                    </td>


                                    {{-- CL DUE --}}
                                    <td>
                                        {{ $emp['cl_due'] }}
                                    </td>


                                    {{-- CL BALANCE --}}
                                    <td>
                                        {{ $emp['cl_balance'] }}
                                    </td>


                                    {{-- CL DEDUCTED --}}
                                    <td>
                                        {{ $emp['cl_deducted'] }}
                                    </td>


                                    {{-- SALARY DAYS --}}
                                    <td>
                                        {{ $emp['salary_days'] }}
                                    </td>

                                </tr>

                            @empty

                                {{-- NO RECORDS --}}
                                <tr>

                                    <td colspan="10">

                                        <div class="empty-state">

                                            <i class="bi bi-inbox"></i>

                                            <p>
                                                No penalty records found
                                                for the selected filters.
                                            </p>

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


    {{-- =========================================================
    INFORMATION BOX
    ========================================================== --}}
    <div class="row" style="margin-top: 16px;">

        <div class="col-12">

            <div class="info-box">

                <i class="bi bi-info-circle"></i>

                <strong>
                    How penalties work:
                </strong>

                Every <strong>{{ $thresholds['late'] ?? 3 }} late arrivals</strong> or <strong>{{ $thresholds['early_out'] ?? 3 }} early outs</strong>
                in a cycle equal <strong>½ Casual Leave (half day)</strong>, and every

                <strong>{{ $thresholds['half_day'] ?? 2 }} half-days</strong> equal <strong>1 Casual Leave</strong>,
                @if (($thresholds['absent'] ?? 0) > 0)
                    and every <strong>{{ $thresholds['absent'] }} absent {{ $thresholds['absent'] == 1 ? 'day' : 'days' }}</strong> (no punch, no leave) equal <strong>1 Casual Leave</strong>,
                @endif

                deducted automatically as soon as the limit is reached

                @unless ($penaltyEnabled) (currently <strong>switched off</strong> in the Attendance Policy) @endunless.

                Where the Casual Leave balance is insufficient, the shortfall is a <strong>salary-day deduction</strong>.

                Waivers are reviewed under Approvals; once approved, the item stops counting and the deducted leave is returned.

                Days covered by approved leave are not counted.

            </div>

        </div>

    </div>

@endsection


@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {

            const filterForm = document.getElementById('filterForm');
            const fromInput = document.getElementById('filterFrom');
            const toInput = document.getElementById('filterTo');

            if (!filterForm || !fromInput || !toInput) {
                return;
            }

            /*
            |--------------------------------------------------------------------------
            | Validate Date Range
            |--------------------------------------------------------------------------
            */

            filterForm.addEventListener('submit', function (event) {

                if (
                    fromInput.value &&
                    toInput.value &&
                    fromInput.value > toInput.value
                ) {

                    event.preventDefault();

                    alert('The "From" date cannot be after the "To" date.');

                    fromInput.focus();
                }

            });

        });
    </script>
@endpush