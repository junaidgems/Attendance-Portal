@extends('attendence.layouts.app')

@section('title', 'Leave Report')
@section('page-title', 'Leave Report')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/employee/leavereport.css') }}">
@endpush

@section('content')

<!-- FILTER BAR -->
<div class="glass-card">
    <form method="GET" action="{{ route('employee.leave.report') }}">
        <div class="filter-bar">

            <div class="form-group">
                <label>Year</label>
                <select class="form-control" name="year">
                    @for($y = now()->year; $y >= now()->year - 3; $y--)
                        <option value="{{ $y }}" {{ $year == $y ? 'selected' : '' }}>{{ $y }}</option>
                    @endfor
                </select>
            </div>

            <div class="form-group">
                <label>From</label>
                <input type="date" class="form-control" name="from" value="{{ $from }}">
            </div>

            <div class="form-group">
                <label>To</label>
                <input type="date" class="form-control" name="to" value="{{ $to }}">
            </div>

            <div class="form-group">
                <label>Leave Type</label>
                <select class="form-control" name="leave_type">
                    <option value="all" {{ $leaveTypeId == 'all' ? 'selected' : '' }}>All types</option>
                    @foreach($leaveTypes as $type)
                        <option value="{{ $type->id }}" {{ $leaveTypeId == $type->id ? 'selected' : '' }}>{{ $type->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="form-group">
                <label>Status</label>
                <select class="form-control" name="status">
                    <option value="all" {{ $status == 'all' ? 'selected' : '' }}>All</option>
                    <option value="approved" {{ $status == 'approved' ? 'selected' : '' }}>Approved</option>
                    <option value="pending" {{ $status == 'pending' ? 'selected' : '' }}>Pending</option>
                    <option value="rejected" {{ $status == 'rejected' ? 'selected' : '' }}>Rejected</option>
                </select>
            </div>

            <div class="btn-group">
                <button type="submit" class="btn-primary-glass"><i class="bi bi-funnel"></i> Filter</button>
                <a href="{{ route('employee.leave.report') }}" class="btn-outline-glass"><i class="bi bi-arrow-counterclockwise"></i> Clear</a>
            </div>

        </div>
    </form>
</div>


<!-- SUMMARY CARDS -->
<div class="summary-grid">
    <div class="summary-card">
        <div class="number">{{ $summary['approved_days'] }}</div>
        <div class="label">Approved Leave Days</div>
        <div class="sub">in selected range</div>
    </div>
    <div class="summary-card">
        <div class="number">{{ $summary['pending_requests'] }}</div>
        <div class="label">Pending Requests</div>
        <div class="sub">awaiting approval</div>
    </div>
    <div class="summary-card">
        <div class="number">{{ $summary['rejected_requests'] }}</div>
        <div class="label">Rejected Requests</div>
        <div class="sub">in selected range</div>
    </div>
    <div class="summary-card">
        <div class="number">{{ $summary['on_leave_today'] }}</div>
        <div class="label">On Leave Today</div>
        <div class="sub">today</div>
    </div>
</div>


<!-- LEAVE BALANCES -->
<div class="glass-card">
    <div class="section-header">
        <div>
            <h6>Leave balances</h6>
            <span class="subtitle">{{ $year }}</span>
        </div>
    </div>

    <div class="table-wrap">
        <table class="balance-table">
            <thead>
                <tr>
                    <th>Employee</th>
                    @foreach($balances as $name => $b)
                        <th style="text-align:center;">{{ $name }}</th>
                    @endforeach
                    <th style="text-align:center;">Total Taken</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>
                        <strong>{{ $employee->first_name }} {{ $employee->last_name }}</strong><br>
                        <span style="font-size:11px; color:var(--text-muted);">{{ $employee->employee_id }}</span>
                    </td>
                    @foreach($balances as $b)
                        <td style="text-align:center;">
                            <span class="balance-used">{{ $b['used'] }}</span>
                            <span class="balance-total">/ {{ $b['total'] }}</span>
                        </td>
                    @endforeach
                    <td style="text-align:center;">
                        <span style="font-weight:700; color:var(--text-primary);">{{ $totalTaken }}</span>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>


<!-- LEAVE REGISTER -->
<div class="glass-card">
    <div class="section-header">
        <div>
            <h6>Leave register</h6>
            <span class="subtitle">{{ $register->count() }} requests</span>
        </div>
    </div>

    <div class="table-wrap">
        <table class="register-table">
            <thead>
                <tr>
                    <th>Type</th><th>From</th><th>To</th><th>Days</th><th>Status</th><th>Reason</th>
                </tr>
            </thead>
            <tbody>
                @forelse($register as $r)
                    <tr>
                        <td><strong>{{ $r->leaveType->name ?? '-' }}</strong></td>
                        <td>{{ \Carbon\Carbon::parse($r->from_date)->format('d M Y') }}</td>
                        <td>{{ \Carbon\Carbon::parse($r->to_date)->format('d M Y') }}</td>
                        <td>{{ $r->days }}</td>
                        <td><span class="status-badge {{ $r->status }}">{{ ucfirst($r->status) }}</span></td>
                        <td style="font-size:12px; color:var(--text-muted);">{{ $r->reason }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6">
                            <div class="empty-state">
                                <i class="bi bi-inbox"></i>
                                <p>No leave requests match these filters.</p>
                                <span class="sub">Try adjusting your search or filters.</span>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@endsection