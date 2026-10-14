@extends('attendence.layouts.app')

@section('title', 'Work From Home')
@section('page-title', 'Work From Home')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/employee/leave.css') }}">
    <style>
        .wfh-today { display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: wrap; }
        .wfh-today .times { display: flex; gap: 24px; flex-wrap: wrap; font-size: 13px; color: var(--text-muted); }
        .wfh-today .times strong { display: block; font-size: 18px; color: var(--text-primary); }
        .wfh-badge { display: inline-block; padding: 2px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; background: rgba(59, 130, 246, .12); color: #3b82f6; }
        .wfh-badge.on-site { background: rgba(34, 197, 94, .12); color: #16a34a; }
    </style>
@endpush

@section('content')

    @if ($errors->any())
        <div class="glass-card" style="border-left:3px solid #ef4444; padding:12px 18px; font-size:13px; color:#ef4444;">
            @foreach ($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    <!-- TODAY -->
    <div class="glass-card">
        <div class="page-header">
            <h4><i class="bi bi-house-door"></i> Today · {{ now()->format('D d M Y') }}</h4>
        </div>

        @if ($todayWfh)
            {{-- An office machine punch makes the day on-site: no Work From Home check-in / check-out --}}
            @php($isOnSite = \App\Models\WfhRequest::isOnSite($todayRecord))
            <div class="wfh-today">
                <div>
                    @if ($isOnSite)
                        <span class="wfh-badge on-site"><i class="bi bi-building"></i> On-site today</span>
                    @elseif ($todayWfh === 'fixed')
                        <span class="wfh-badge">Work From Home day ({{ now()->format('l') }}s)</span>
                    @else
                        <span class="wfh-badge">Approved Work From Home</span>
                    @endif
                    <div style="font-size:13px; color:var(--text-muted); margin-top:6px;">
                        Shift {{ \Carbon\Carbon::parse($todayShift['start'])->format('h:i A') }} –
                        {{ \Carbon\Carbon::parse($todayShift['end'])->format('h:i A') }}
                    </div>
                </div>

                <div class="times">
                    <div>Check in
                        <strong>{{ $todayRecord?->check_in ? \Carbon\Carbon::parse($todayRecord->check_in)->format('h:i A') : '—' }}</strong>
                    </div>
                    <div>Check out
                        <strong>{{ $todayRecord?->check_out ? \Carbon\Carbon::parse($todayRecord->check_out)->format('h:i A') : '—' }}</strong>
                    </div>
                </div>

                <div>
                    @if ($isOnSite)
                        <span style="font-size:13px; color:var(--text-muted);">
                            You punched in on the office machine, so today counts as on-site, not Work From Home.<br>
                            Punch out on the machine when you leave.
                        </span>
                    @elseif (! $todayRecord?->check_in)
                        <form method="POST" action="{{ route($routePrefix . '.wfh.check-in') }}">
                            @csrf
                            <button type="submit" class="btn-primary-glass"><i class="bi bi-box-arrow-in-right"></i> Check In</button>
                        </form>
                    @elseif (! $todayRecord->check_out)
                        <button type="button" class="btn-primary-glass" data-bs-toggle="modal" data-bs-target="#checkOutModal">
                            <i class="bi bi-box-arrow-right"></i> Check Out
                        </button>
                    @else
                        <span style="font-size:13px; color:#22c55e;"><i class="bi bi-check-circle"></i> Done for today</span>
                    @endif
                </div>
            </div>
        @else
            <p style="font-size:13px; color:var(--text-muted); margin:0;">
                You are not on approved Work From Home today. Check In / Check Out appears here on your approved days.
            </p>
        @endif
    </div>

    <!-- MY REQUESTS -->
    <div class="glass-card">
        <div class="top-actions">
            <span style="font-size:13px; color:var(--text-muted);">
                My Work From Home requests · {{ $myRequests->count() }} total
                @if ($monthlyLimit > 0)
                    · {{ $usedThisMonth }} / {{ $monthlyLimit }} days used this month
                @endif
            </span>
            @if ($wfhEnabled)
                <button class="btn-primary-glass" data-bs-toggle="modal" data-bs-target="#wfhRequestModal">
                    <i class="bi bi-plus"></i> Request Work From Home
                </button>
            @endif
        </div>

        <div style="overflow-x:auto;">
            <table class="leave-table">
                <thead>
                    <tr>
                        <th>Dates</th>
                        <th>Days</th>
                        <th>Reason</th>
                        <th>Status</th>
                        <th>Note</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($myRequests as $r)
                        <tr>
                            <td>{{ $r->dateLabel() }}</td>
                            <td>{{ $r->days }}</td>
                            <td>{{ $r->reason }}</td>
                            <td>
                                <span class="status-badge {{ $r->status }}">{{ ucfirst($r->status) }}</span>
                                @if ($r->status === 'pending')
                                    <br><span style="font-size:11px; color:var(--text-muted);">{{ $r->stageLabel() }}</span>
                                @endif
                            </td>
                            <td style="font-size:12px; color:var(--text-muted);">
                                @if ($r->manager_note)
                                    <div><strong>Line Manager:</strong> {{ $r->manager_note }}</div>
                                @endif
                                @if ($r->admin_note)
                                    <div><strong>Admin:</strong> {{ $r->admin_note }}</div>
                                @endif
                                @if (! $r->manager_note && ! $r->admin_note)
                                    —
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5">
                                <div class="empty-state">
                                    <i class="bi bi-inbox"></i>
                                    <p>No Work From Home requests yet.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- HOW IT WORKS -->
    <div class="glass-card">
        <div class="info-box" style="font-size:13px; color:var(--text-muted);">
            <i class="bi bi-info-circle"></i>
            Request Work From Home in advance (or on the same day for emergencies). It goes to
            {{ $reviewingManager ? 'your Line Manager (' . $reviewingManager->full_name . '), then Admin' : 'Admin' }}.
            On an approved day, press <strong>Check In</strong> when you start and <strong>Check Out</strong> with a short
            summary of your work when you finish. Your shift timings, late arrival, early out and penalty rules apply as in
            the office. No check-in on an approved day counts as absent.
        </div>
    </div>

    <!-- REQUEST MODAL -->
    <div class="modal fade modal-glass" id="wfhRequestModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="POST" action="{{ route($routePrefix . '.wfh.store') }}">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="bi bi-house-door" style="color:var(--primary);"></i> Request Work From Home</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>From</label>
                                    <input type="date" class="form-control-custom" name="from_date" min="{{ now()->format('Y-m-d') }}"
                                        value="{{ old('from_date', now()->format('Y-m-d')) }}" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>To</label>
                                    <input type="date" class="form-control-custom" name="to_date" min="{{ now()->format('Y-m-d') }}"
                                        value="{{ old('to_date', now()->format('Y-m-d')) }}" required>
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Reason</label>
                            <textarea class="form-control-custom" name="reason" rows="2" maxlength="500" required
                                placeholder="e.g. Internet technician visit, feeling unwell but able to work...">{{ old('reason') }}</textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn-cancel" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn-save"><i class="bi bi-check2"></i> Submit request</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- CHECK OUT MODAL -->
    <div class="modal fade modal-glass" id="checkOutModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="POST" action="{{ route($routePrefix . '.wfh.check-out') }}">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="bi bi-box-arrow-right" style="color:var(--primary);"></i> Check Out</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label>What did you work on today?</label>
                            <textarea class="form-control-custom" name="work_summary" rows="4" minlength="10" maxlength="1000" required
                                placeholder="Tasks completed, meetings attended, work in progress...">{{ old('work_summary') }}</textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn-cancel" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn-save"><i class="bi bi-check2"></i> Check Out</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

@endsection
