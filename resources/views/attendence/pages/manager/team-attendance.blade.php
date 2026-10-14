@extends('attendence.layouts.app')

@section('title', 'Team Attendance')
@section('page-title', 'Team Attendance')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/manager-css/team-attendance.css') }}">
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
                            <input type="date" class="form-control" name="date" value="{{ $date }}" max="{{ now()->format('Y-m-d') }}">
                        </div>

                        <div class="form-group">
                            <label>Team Member</label>
                            <select class="form-control" name="member">
                                <option value="all" {{ $memberId == 'all' ? 'selected' : '' }}>— Day view: whole team —</option>
                                @foreach($team as $t)
                                    <option value="{{ $t->id }}" {{ (string) $memberId === (string) $t->id ? 'selected' : '' }}>
                                        {{ $t->first_name }} {{ $t->last_name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="btn-group">
                            <button type="submit" class="btn-primary-glass"><i class="bi bi-funnel"></i> Show</button>
                            <a href="{{ route('manager.team.attendance.export', ['date' => $date, 'member' => $memberId]) }}" class="btn-outline-glass">
                                <i class="bi bi-download"></i> Export CSV
                            </a>
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
                    <span><strong>{{ $dateDisplay }}</strong></span>
                    <span class="badge-working">{{ $dayLabel }}</span>
                    <span style="color:var(--text-muted); font-size:13px; margin-left:8px;">
                        @if($memberId === 'all')
                            — Day view: whole team —
                        @else
                            — {{ \Carbon\Carbon::parse($date)->format('F Y') }} history —
                        @endif
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- ===== STATUS FILTER TABS ===== -->
    <div class="row">
        <div class="col-12">
            <div class="glass-card" style="padding-bottom:8px;">
                <div class="status-tabs">
                    <button class="tab-btn active" data-filter="all">All <span class="count">{{ $counts['all'] }}</span></button>
                    <button class="tab-btn" data-filter="present">Present <span class="count">{{ $counts['present'] }}</span></button>
                    <button class="tab-btn" data-filter="late">Late <span class="count">{{ $counts['late'] }}</span></button>
                    <button class="tab-btn" data-filter="early-out">Early out <span class="count">{{ $counts['early-out'] }}</span></button>
                    <button class="tab-btn" data-filter="half-day">Half day <span class="count">{{ $counts['half-day'] }}</span></button>
                    <button class="tab-btn" data-filter="absent">Missing/absent <span class="count">{{ $counts['absent'] }}</span></button>
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
                                <th>{{ $memberId === 'all' ? 'Member' : 'Date' }}</th>
                                <th>Status</th>
                                <th>IN</th>
                                <th>OUT</th>
                                <th>Net Hours</th>
                                <th>Timeline</th>
                            </tr>
                        </thead>
                        <tbody id="teamTableBody">
                            @forelse($rows as $r)
                                <tr data-tags="{{ implode(' ', $r['tags']) }}">
                                    <td>
                                        @if($memberId === 'all')
                                            <strong>{{ $r['name'] }}</strong><br>
                                            <span style="font-size:11px; color:var(--text-muted);">{{ $r['emp_id'] }}</span>
                                        @else
                                            <strong>{{ $r['date'] }}</strong>
                                        @endif
                                    </td>
                                    <td><span class="status-badge-sm {{ \Illuminate\Support\Str::slug(strtolower($r['status'])) }}">{{ $r['status'] }}</span></td>
                                    <td>
                                        @if($r['in'] === 'Missing Punch')
                                            <span style="color:#dc2626; font-weight:600;">{{ $r['in'] }}</span>
                                        @else
                                            {{ $r['in'] }}
                                        @endif
                                    </td>
                                    <td>
                                        @if($r['out'] === 'Missing Punch')
                                            <span style="color:#dc2626; font-weight:600;">{{ $r['out'] }}</span>
                                        @else
                                            {{ $r['out'] }}
                                        @endif
                                    </td>
                                    <td>{{ $r['net'] }}</td>
                                    <td>
                                        @if($r['timeline_class'])
                                            <span class="{{ $r['timeline_class'] }}">{{ $r['timeline'] }}</span>
                                        @else
                                            {{ $r['timeline'] }}
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" style="text-align:center; padding:30px;">
                                        Aapki team mein koi record nahi mila.
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
        // Status tabs sirf already-render-shuda rows ko show/hide karte hain (UI filter, koi data fetch nahi)
        document.addEventListener('DOMContentLoaded', function () {
            const tabBtns = document.querySelectorAll('.status-tabs .tab-btn');
            const rows = document.querySelectorAll('#teamTableBody tr[data-tags]');

            tabBtns.forEach(btn => {
                btn.addEventListener('click', function () {
                    tabBtns.forEach(b => b.classList.remove('active'));
                    this.classList.add('active');

                    const filter = this.dataset.filter;
                    rows.forEach(row => {
                        const tags = row.dataset.tags.split(' ');
                        row.style.display = (filter === 'all' || tags.includes(filter)) ? '' : 'none';
                    });
                });
            });
        });
    </script>
@endpush