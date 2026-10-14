@extends('attendence.layouts.app')

@section('title', 'Reports')
@section('page-title', 'Reports')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/manager-css/team-attendance.css') }}">
@endpush

@section('content')

    <!-- ===== FILTER BAR ===== -->
    <div class="row">
        <div class="col-12">
            <div class="glass-card">
                <form method="GET" action="{{ route('director.reports') }}">
                    <div class="filter-bar">
                        <div class="form-group">
                            <label>Attendance Cycle</label>
                            <select class="form-control" name="cycle">
                                <option value="0" @selected($offset === 0)>Current cycle</option>
                                @for ($i = 1; $i <= 6; $i++)
                                    <option value="{{ $i }}" @selected($offset === $i)>{{ $i === 1 ? 'Last cycle' : $i.' cycles ago' }}</option>
                                @endfor
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Department</label>
                            <select class="form-control" name="department">
                                <option value="">All departments</option>
                                @foreach ($departments as $dept)
                                    <option value="{{ $dept->id }}" @selected($departmentId === $dept->id)>{{ $dept->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="btn-group">
                            <button type="submit" class="btn-primary-glass"><i class="bi bi-funnel"></i> Show</button>
                            <a href="{{ route('director.reports.export', ['cycle' => $offset, 'department' => $departmentId ?: null]) }}" class="btn-outline-glass">
                                <i class="bi bi-download"></i> Export CSV
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ===== CYCLE ===== -->
    <div class="row">
        <div class="col-12">
            <div class="glass-card">
                <div class="day-status">
                    <span><strong>{{ $cycleLabel }}</strong></span>
                    <span class="badge-working">{{ count($rows) }} {{ \Illuminate\Support\Str::plural('employee', count($rows)) }}</span>
                    <span style="color:var(--text-muted); font-size:13px; margin-left:8px;">
                        Counted up to {{ $to < now()->toDateString() ? \Carbon\Carbon::parse($to)->format('d M Y') : 'today' }}
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- ===== SUMMARY TABLE ===== -->
    <div class="row" style="margin-top:4px;">
        <div class="col-12">
            <div class="glass-card" style="padding:0; overflow:hidden;">
                <div style="overflow-x:auto;">
                    <table class="team-table">
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>Department</th>
                                @foreach ($columns as $heading)
                                    <th style="text-align:center;">{{ $heading }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($rows as $row)
                                <tr>
                                    <td>
                                        <strong>{{ $row['name'] }}</strong><br>
                                        <span style="font-size:11px; color:var(--text-muted);">{{ $row['emp_id'] }}</span>
                                    </td>
                                    <td>{{ $row['department'] }}</td>
                                    @foreach (array_keys($columns) as $key)
                                        <td style="text-align:center;">{{ $row[$key] ?: '—' }}</td>
                                    @endforeach
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ count($columns) + 2 }}" style="text-align:center; padding:30px;">No employees found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                        @if (count($rows) > 0)
                            <tfoot>
                                <tr>
                                    <th colspan="2">Total</th>
                                    @foreach (array_keys($columns) as $key)
                                        <th style="text-align:center;">{{ $totals[$key] }}</th>
                                    @endforeach
                                </tr>
                            </tfoot>
                        @endif
                    </table>
                </div>
            </div>
        </div>
    </div>

@endsection
