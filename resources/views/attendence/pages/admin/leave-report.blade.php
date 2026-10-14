@extends('attendence.layouts.app')

@section('title', 'Leave Report')
@section('page-title', 'Leave Report')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/leave-report.css') }}">
@endpush

@section('content')


    <!-- FILTER BAR + EXPORT BUTTONS               -->
    <div class="row">
        <div class="col-12">
            <div class="glass-card">
                <form method="GET" action="{{ route('admin.leave.report') }}" id="filterForm">
                    <div class="filter-bar">

                        <div class="form-group">
                            <label>Year</label>
                            <select class="form-control" name="year">
                                <option value="2026" {{ request('year', '2026') == '2026' ? 'selected' : '' }}>2026</option>
                                <option value="2025" {{ request('year') == '2025' ? 'selected' : '' }}>2025</option>
                                <option value="2024" {{ request('year') == '2024' ? 'selected' : '' }}>2024</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>From</label>
                            <input type="date" class="form-control" name="from" value="{{ request('from', '2026-01-01') }}">
                        </div>

                        <div class="form-group">
                            <label>To</label>
                            <input type="date" class="form-control" name="to" value="{{ request('to', '2026-07-20') }}">
                        </div>

                        <div class="form-group">
                            <label>Leave Type</label>
                            <select class="form-control" name="leave_type">
                                <option value="all" {{ request('leave_type') == 'all' ? 'selected' : '' }}>All types</option>
                                <option value="Annual Leave" {{ request('leave_type') == 'Annual Leave' ? 'selected' : '' }}>
                                    Annual Leave</option>
                                <option value="Sick Leave" {{ request('leave_type') == 'Sick Leave' ? 'selected' : '' }}>Sick
                                    Leave</option>
                                <option value="Casual Leave" {{ request('leave_type') == 'Casual Leave' ? 'selected' : '' }}>
                                    Casual Leave</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Status</label>
                            <select class="form-control" name="status">
                                <option value="all" {{ request('status') == 'all' ? 'selected' : '' }}>All</option>
                                <option value="approved" {{ request('status') == 'approved' ? 'selected' : '' }}>Approved
                                </option>
                                <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Pending
                                </option>
                                <option value="rejected" {{ request('status') == 'rejected' ? 'selected' : '' }}>Rejected
                                </option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Department</label>
                            <select class="form-control" name="department">
                                <option value="all" {{ request('department') == 'all' ? 'selected' : '' }}>All departments
                                </option>
                                @foreach(\App\Models\Department::orderBy('name')->get() as $dept)
                                    <option value="{{ $dept->name }}" {{ request('department') == $dept->name ? 'selected' : '' }}>{{ $dept->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="form-group" style="flex: 1.5;">
                            <label>Search</label>
                            <input type="text" class="form-control" name="search" placeholder="Name or ID"
                                value="{{ request('search') }}">
                        </div>

                        <div class="btn-group">
                            <!-- Filter Button -->
                            <button type="submit" class="btn-primary-glass">
                                <i class="bi bi-funnel"></i> Filter
                            </button>

                            <!-- Export Balances -->
                            <button type="submit" formaction="{{ route('admin.leave.export.balances') }}"
                                class="btn-primary-glass">
                                <i class="bi bi-download"></i> Export balances
                            </button>

                            <!-- Export Register -->
                            <button type="submit" formaction="{{ route('admin.leave.export.register') }}"
                                class="btn-outline-glass">
                                <i class="bi bi-file-earmark-text"></i> Export register
                            </button>
                        </div>

                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- SUMMARY CARDS                             -->

    <div class="row" style="margin-top: 16px;">
        <div class="col-12">
            <div class="summary-grid" id="summaryContainer">
                <div class="summary-card">
                    <div class="number">{{ $summary['approved_days'] ?? 0 }}</div>
                    <div class="label">Approved Leave Days</div>
                    <div class="sub">in selected range</div>
                </div>
                <div class="summary-card">
                    <div class="number">{{ $summary['pending_requests'] ?? 0 }}</div>
                    <div class="label">Pending Requests</div>
                    <div class="sub">awaiting approval</div>
                </div>
                <div class="summary-card">
                    <div class="number">{{ $summary['rejected_requests'] ?? 0 }}</div>
                    <div class="label">Rejected Requests</div>
                    <div class="sub">in selected range</div>
                </div>
                <div class="summary-card">
                    <div class="number">{{ $summary['on_leave_today'] ?? 0 }}</div>
                    <div class="label">On Leave Today</div>
                    <div class="sub">people</div>
                </div>
            </div>
        </div>
    </div>


    <!-- LEAVE BALANCES                            -->

    <div class="row" style="margin-top: 16px;">
        <div class="col-12">
            <div class="glass-card" id="balancesContainer">
                <div class="section-header">
                    <div>
                        <h6>Leave Balances</h6>
                        <span class="subtitle">{{ count($balances) }} employee · 2026</span>
                    </div>
                </div>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>Dept</th>
                                <th style="text-align: center;">Annual Leave</th>
                                <th style="text-align: center;">Sick Leave</th>
                                <th style="text-align: center;">Casual Leave</th>
                                <th style="text-align: center;">Total Taken</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($balances as $emp)
                                <tr>
                                    <td><strong>{{ $emp['employee'] }}</strong><br><span
                                            style="font-size:11px; color:var(--text-muted);">{{ $emp['id'] }}</span></td>
                                    <td>{{ $emp['dept'] }}</td>
                                    <td style="text-align: center;"><span
                                            class="balance-used">{{ $emp['annual']['used'] }}</span><span
                                            class="balance-total">/ {{ $emp['annual']['total'] }}</span></td>
                                    <td style="text-align: center;"><span
                                            class="balance-used">{{ $emp['sick']['used'] }}</span><span class="balance-total">/
                                            {{ $emp['sick']['total'] }}</span></td>
                                    <td style="text-align: center;"><span
                                            class="balance-used">{{ $emp['casual']['used'] }}</span><span
                                            class="balance-total">/ {{ $emp['casual']['total'] }}</span></td>
                                    <td style="text-align: center;"><span
                                            style="font-weight:700; color:var(--text-primary);">{{ $emp['total_taken'] }}</span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6">
                                        <div class="empty-state"><i class="bi bi-inbox"></i>
                                            <p>No leave balances found.</p>
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


    <!-- LEAVE REGISTER                            -->

    <div class="row" style="margin-top: 20px;">
        <div class="col-12">
            <div class="glass-card" id="registerContainer">
                <div class="section-header">
                    <div>
                        <h6>Leave Register</h6>
                        <span class="subtitle">{{ count($register) }} requests</span>
                    </div>
                </div>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>Type</th>
                                <th>From</th>
                                <th>To</th>
                                <th>Days</th>
                                <th>Status</th>
                                <th style="text-align: right;">Applied</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($register as $req)
                                <tr>
                                    <td><strong>{{ $req['employee'] }}</strong><br><span
                                            style="font-size:11px; color:var(--text-muted);">{{ $req['id'] }}</span></td>
                                    <td>{{ $req['type'] }}</td>
                                    <td>{{ \Carbon\Carbon::parse($req['from'])->format('d M Y') }}</td>
                                    <td>{{ \Carbon\Carbon::parse($req['to'])->format('d M Y') }}</td>
                                    <td>{{ $req['days'] }}</td>
                                    <td><span class="status-badge-sm {{ $req['status'] }}">{{ ucfirst($req['status']) }}</span>
                                    </td>
                                    <td style="text-align: right; font-size:12px; color:var(--text-muted);">
                                        {{ \Carbon\Carbon::parse($req['applied'])->format('d M Y') }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7">
                                        <div class="empty-state"><i class="bi bi-inbox"></i>
                                            <p>No leave requests match these filters.</p><span class="sub">Try adjusting your
                                                search or filters.</span>
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

@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {

            // ===== GET FILTER PARAMS =====
            function getFilterParams() {
                return {
                    year: document.getElementById('filterYear').value,
                    from: document.getElementById('filterFrom').value,
                    to: document.getElementById('filterTo').value,
                    leave_type: document.getElementById('filterLeaveType').value,
                    status: document.getElementById('filterStatus').value,
                    department: document.getElementById('filterDepartment').value,
                    search: document.getElementById('filterSearch').value
                };
            }

            // ===== APPLY FILTERS =====
            function applyFilters() {
                const params = getFilterParams();

                // Show loading
                document.querySelectorAll('#summaryContainer, #balancesContainer, #registerContainer').forEach(el => {
                    el.innerHTML = '<div style="text-align:center;padding:30px;"><i class="bi bi-hourglass-split" style="font-size:28px;display:block;margin-bottom:8px;opacity:0.5;"></i><p style="color:var(--text-muted);">Loading...</p></div>';
                });

                fetch('{{ route("admin.leave.filter") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify(params)
                })
                    .then(response => response.json())
                    .then(data => {
                        document.getElementById('summaryContainer').innerHTML = data.summary;
                        document.getElementById('balancesContainer').innerHTML = data.balances;
                        document.getElementById('registerContainer').innerHTML = data.register;
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        toastr.error('Something went wrong!');
                    });
            }

            // ===== FILTER BUTTON =====
            document.getElementById('filterBtn').addEventListener('click', applyFilters);

            // ===== ENTER KEY =====
            document.getElementById('filterSearch').addEventListener('keyup', function (e) {
                if (e.key === 'Enter') applyFilters();
            });

            // ===== ON CHANGE AUTO FILTER =====
            ['filterYear', 'filterFrom', 'filterTo', 'filterLeaveType', 'filterStatus', 'filterDepartment'].forEach(id => {
                document.getElementById(id).addEventListener('change', applyFilters);
            });

            // ===== EXPORT BALANCES =====
            document.getElementById('exportBalancesBtn').addEventListener('click', function () {
                const params = new URLSearchParams(getFilterParams());
                window.location.href = '{{ route("admin.leave.export.balances") }}?' + params.toString();
            });

            // ===== EXPORT REGISTER =====
            document.getElementById('exportRegisterBtn').addEventListener('click', function () {
                const params = new URLSearchParams(getFilterParams());
                window.location.href = '{{ route("admin.leave.export.register") }}?' + params.toString();
            });

        });
    </script>
@endpush