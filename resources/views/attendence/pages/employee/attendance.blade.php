@extends('attendence.layouts.app')

@section('title', 'My Attendance')
@section('page-title', 'My Attendance')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/employee/myAttendance.css') }}">
@endpush

@section('content')

<!-- MONTH HEADER -->
<div class="glass-card">
    <div class="month-header">
        <h4 id="monthYear">{{ \Carbon\Carbon::create($year, $month, 1)->format('M Y') }}</h4>
        <div style="display:flex; gap:12px; align-items:center; flex-wrap:wrap;">
            <div class="nav-buttons">
                @php
                    $prev = \Carbon\Carbon::create($year, $month, 1)->subMonth();
                    $next = \Carbon\Carbon::create($year, $month, 1)->addMonth();
                @endphp
                <a href="{{ route('employee.my.attendance', ['month' => $prev->month, 'year' => $prev->year]) }}"><button type="button"><i class="bi bi-chevron-left"></i></button></a>
                <a href="{{ route('employee.my.attendance', ['month' => $next->month, 'year' => $next->year]) }}"><button type="button"><i class="bi bi-chevron-right"></i></button></a>
            </div>
            <div class="view-toggle">
                <button class="active" data-view="list" onclick="switchView('list')">List</button>
                <button data-view="calendar" onclick="switchView('calendar')">Calendar</button>
            </div>
        </div>
    </div>
</div>

<!-- SUMMARY CARDS -->
<div class="summary-grid">
    <div class="summary-card">
        <div class="number" style="color:#059669;">{{ $present }}</div>
        <div class="label">Present</div>
        <div class="sub">full days</div>
    </div>
    <div class="summary-card">
        <div class="number" style="color:#d97706;">{{ $late + $early }}</div>
        <div class="label">Late / Early</div>
        <div class="sub">{{ $late }} late - {{ $early }} early</div>
    </div>
    <div class="summary-card">
        <div class="number" style="color:#d97706;">{{ $half + $missing }}</div>
        <div class="label">Half / Missing</div>
        <div class="sub">{{ $half }} half - {{ $missing }} missing</div>
    </div>
    <div class="summary-card">
        <div class="number" style="color:#dc2626;">{{ $absent }}</div>
        <div class="label">Absent</div>
        <div class="sub">{{ $workingDaysCount }} working days</div>
    </div>
</div>

<!-- LIST VIEW -->
<div class="glass-card">
    <div class="view-content active" id="listView">
        <div class="table-wrap">
            <table class="attendance-table">
                <thead>
                    <tr><th>Date</th><th>Status</th><th>IN</th><th>OUT</th><th>Net Hours</th><th>Timeline</th></tr>
                </thead>
                <tbody>
                    @php $visible = collect($data)->where('status', '!=', '')->values(); @endphp
                    @forelse($visible as $d)
                        <tr>
                            <td>{{ $d['day'] }} {{ $d['date'] }}</td>
                            <td><span class="status-badge {{ \Illuminate\Support\Str::slug(strtolower($d['status'])) }}">{{ $d['status'] }}</span></td>
                            <td>
                                @if($d['in'] === 'Missing Punch')
                                    <span style="color:#dc2626; font-weight:600;">{{ $d['in'] }}</span>
                                    @if (($d['full_date'] ?? null) && $d['full_date'] < now()->toDateString())
                                        <br><a href="{{ route('employee.correction.requests', ['date' => $d['full_date'], 'type' => 'forgot-check-in']) }}"
                                            style="font-size:11px;"><i class="bi bi-pencil-square"></i> Request correction</a>
                                    @endif
                                @else
                                    {{ $d['in'] }}
                                @endif
                            </td>
                            <td>
                                @if($d['out'] === 'Missing Punch')
                                    <span style="color:#dc2626; font-weight:600;">{{ $d['out'] }}</span>
                                    @if (($d['full_date'] ?? null) && $d['full_date'] < now()->toDateString())
                                        <br><a href="{{ route('employee.correction.requests', ['date' => $d['full_date'], 'type' => 'forgot-check-out']) }}"
                                            style="font-size:11px;"><i class="bi bi-pencil-square"></i> Request correction</a>
                                    @endif
                                @else
                                    {{ $d['out'] }}
                                @endif
                            </td>
                            <td>{{ $d['net'] }}</td>
                            <td>
                                {{ $d['timeline'] }}
                                @if (! empty($d['adjustment']))
                                    <br><span style="font-size:11px; color:var(--text-muted);"><i class="bi bi-info-circle"></i> {{ $d['adjustment'] }}</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><div class="empty-state"><i class="bi bi-inbox"></i><p>No records found.</p></div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- CALENDAR VIEW -->
    <div class="view-content" id="calendarView">
        <div class="calendar-grid">
            @foreach(['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $wd)
                <div class="weekday">{{ $wd }}</div>
            @endforeach

            @php
                $firstDay = \Carbon\Carbon::create($year, $month, 1)->dayOfWeek;
                $todayStr = now()->format('Y-m-d');
            @endphp

            @for($i = 0; $i < $firstDay; $i++)
                <div class="day-cell weekend" style="border-color:transparent;background:transparent;box-shadow:none;"></div>
            @endfor

            @foreach($data as $d)
                @php
                    $cellDate = \Carbon\Carbon::create($year, $month, $d['date'])->format('Y-m-d');
                    $isToday = $cellDate === $todayStr;
                    $isWeekend = $d['status'] === 'Weekend';
                @endphp
                <div class="day-cell {{ $isWeekend ? 'weekend' : '' }} {{ $isToday ? 'today' : '' }}">
                    <span class="day-number">{{ $d['date'] }}</span>
                    @if($d['status'])
                        <span class="day-status {{ \Illuminate\Support\Str::slug(strtolower($d['status'])) }}">{{ $d['status'] }}</span>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
    function switchView(view) {
        document.querySelectorAll('.view-toggle button').forEach(btn => btn.classList.remove('active'));
        document.querySelector(`.view-toggle button[data-view="${view}"]`).classList.add('active');
        document.querySelectorAll('.view-content').forEach(el => el.classList.remove('active'));
        document.getElementById(view + 'View').classList.add('active');
    }
</script>
@endpush