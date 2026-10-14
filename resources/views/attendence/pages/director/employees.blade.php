@extends('attendence.layouts.app')

@section('title', 'Employees')
@section('page-title', 'Employees')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/employee.css') }}">
    <link rel="stylesheet" href="{{ asset('css/manager-css/team-attendance.css') }}">
@endpush

@section('content')

    <!-- ===== FILTER BAR ===== -->
    <div class="row">
        <div class="col-12">
            <div class="glass-card">
                <form method="GET" action="{{ route('director.employees') }}">
                    <div class="filter-bar">
                        <div class="form-group" style="flex:2;">
                            <label>Search</label>
                            <input type="text" class="form-control" name="search" value="{{ $filters['search'] }}"
                                placeholder="Name, employee ID, email or job title">
                        </div>
                        <div class="form-group">
                            <label>Department</label>
                            <select class="form-control" name="department">
                                <option value="">All departments</option>
                                @foreach ($departments as $dept)
                                    <option value="{{ $dept->id }}" @selected($filters['department'] === $dept->id)>{{ $dept->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Status</label>
                            <select class="form-control" name="status">
                                @foreach (['active' => 'Active', 'inactive' => 'Inactive', 'on_leave' => 'On Leave', 'terminated' => 'Terminated', 'all' => 'All'] as $value => $label)
                                    <option value="{{ $value }}" @selected($filters['status'] === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="btn-group">
                            <button type="submit" class="btn-primary-glass"><i class="bi bi-funnel"></i> Show</button>
                            <a href="{{ route('director.employees') }}" class="btn-outline-glass"><i class="bi bi-arrow-counterclockwise"></i> Clear</a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ===== TABLE ===== -->
    <div class="row" style="margin-top: 4px;">
        <div class="col-12">
            <div class="glass-card" style="padding: 0; overflow: hidden;">
                <div class="table-wrap" style="overflow-x:auto;">
                    <table>
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>EMP ID</th>
                                <th>Department</th>
                                <th>Role</th>
                                <th>Reports To</th>
                                <th>Shift</th>
                                <th>Joined</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($employees as $emp)
                                <tr>
                                    <td class="employe-info">
                                        <span class="employee-avatar">{{ strtoupper(substr($emp->first_name, 0, 1).substr($emp->last_name, 0, 1)) }}</span>
                                        <div>
                                            <span class="employee-name">{{ $emp->full_name }}</span>
                                            <span class="employee-role">{{ $emp->job_title }} · {{ $emp->email }}</span>
                                        </div>
                                    </td>
                                    <td><strong>{{ $emp->employee_id }}</strong></td>
                                    <td>{{ $emp->department->name ?? '-' }}</td>
                                    <td>{{ $emp->role->display_name ?? '-' }}</td>
                                    <td>{{ $emp->manager?->full_name ?? 'None' }}</td>
                                    <td>{{ $emp->shift->name ?? 'Default' }}</td>
                                    <td>{{ $emp->joining_date ? \Carbon\Carbon::parse($emp->joining_date)->format('d M Y') : '-' }}</td>
                                    <td>
                                        @if ($emp->employment_status === 'active')
                                            <span style="display:inline-block; padding:3px 10px; border-radius:20px; font-size:12px; font-weight:600; background:#dcfce7; color:#15803d;">Active</span>
                                        @else
                                            <span style="display:inline-block; padding:3px 10px; border-radius:20px; font-size:12px; font-weight:600; background:#fee2e2; color:#b91c1c;">{{ ucwords(str_replace('_', ' ', $emp->employment_status ?? 'inactive')) }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" style="text-align:center; padding: 20px;">No employees found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <p style="font-size:12px; color:var(--text-muted); margin-top:8px;">
        {{ $employees->count() }} {{ \Illuminate\Support\Str::plural('employee', $employees->count()) }} · Employees are added and edited by Admin.
    </p>

@endsection
