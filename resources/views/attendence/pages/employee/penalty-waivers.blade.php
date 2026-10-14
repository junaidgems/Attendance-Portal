@extends('attendence.layouts.app')

@section('title', 'Penalty & Waivers')
@section('page-title', 'Penalty & Waivers')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/employee/penaltyWave.css') }}">
@endpush

@section('content')


    <!-- ATTENDANCE CYCLE HEADER WITH SELECTOR -->
    <div class="glass-card">
        <div class="cycle-header">
            <div>
                <h4>Attendance Cycle</h4>
                <span class="cycle-date">{{ $label }}</span>
            </div>
            <div class="cycle-selector">
                <label style="font-size:12px; color:var(--text-muted); font-weight:600;">Select Cycle</label>
                <form method="GET" action="{{ route('employee.penalty') }}">
                    <select name="cycle" onchange="this.form.submit()">
                        @foreach($cycles as $i => $c)
                            <option value="{{ $i }}" {{ $offset == $i ? 'selected' : '' }}>{{ $c[2] }}</option>
                        @endforeach
                    </select>
                </form>
            </div>
        </div>
    </div>

    <!-- SUMMARY CARDS -->
    <div class="summary-grid">
        <div class="summary-card">
            <div class="number" style="color:#d97706;">{{ $lateCount }}</div>
            <div class="label">Late Arrivals</div>
            <div class="sub">{{ $thresholds['late'] }} = ½ Casual Leave</div>
        </div>
        <div class="summary-card">
            <div class="number" style="color:#d97706;">{{ $earlyOutCount }}</div>
            <div class="label">Early Outs</div>
            <div class="sub">{{ $thresholds['early_out'] }} = ½ Casual Leave</div>
        </div>
        <div class="summary-card">
            <div class="number" style="color:#d97706;">{{ $halfDayCount }}</div>
            <div class="label">Half-Days</div>
            <div class="sub">{{ $thresholds['half_day'] }} = 1 Casual Leave</div>
        </div>
        <div class="summary-card">
            <div class="number" style="color:#dc2626;">{{ $absentCount }}</div>
            <div class="label">Absent Days</div>
            <div class="sub">{{ $thresholds['absent'] > 0 ? $thresholds['absent'].' = 1 Casual Leave' : 'not deducted' }}</div>
        </div>
        <div class="summary-card">
            <div class="number" style="color:#059669;">{{ $clDeducted }} / {{ $clDue }}</div>
            <div class="label">Casual Leave Deducted</div>
            <div class="sub">deducted / due · {{ $clBalance }} CL left</div>
        </div>
        <div class="summary-card">
            <div class="number" style="color:#dc2626;">{{ $salaryDeduct }}</div>
            <div class="label">Salary-Day Deduction</div>
            <div class="sub">where CL insufficient</div>
        </div>
    </div>

    @unless ($penaltyEnabled)
        <div class="glass-card" style="padding:12px 18px; font-size:13px; color:var(--text-muted);">
            <i class="bi bi-info-circle"></i> The penalty policy is currently switched off, so no Casual Leave is being deducted. Counts are shown for information only.
        </div>
    @endunless

    <!-- PENALTY ITEMS TABLE -->
    <div class="glass-card">
        <div class="section-header">
            <div>
                <h6>Your late arrivals, early outs &amp; half-days</h6>
                <span class="subtitle">{{ $items->count() }} penalty items · {{ $label }}</span>
            </div>
            <button class="btn-primary-glass" data-bs-toggle="modal" data-bs-target="#waiverRequestModal">
                <i class="bi bi-plus"></i> Request Waiver
            </button>
        </div>

        <div style="overflow-x:auto;">
            <table class="penalty-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Waiver Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($items as $item)
                        <tr>
                            <td>{{ \Carbon\Carbon::parse($item['date'])->format('d M Y') }}</td>
                            <td>{{ $item['type_label'] }}</td>
                            <td><span class="status-badge pending">Flagged</span></td>
                            <td>
                                @if($item['waiver_status'])
                                    <span
                                        class="status-badge {{ $item['waiver_status'] }}">{{ ucfirst($item['waiver_status']) }}</span>
                                @if (! empty($item['lm_note']))
                                    <div style="font-size:11px; color:var(--text-muted); margin-top:4px;"><strong>Reviewer:</strong> {{ $item['lm_note'] }}</div>
                                @endif
                                @if (! empty($item['admin_note']))
                                    <div style="font-size:11px; color:var(--text-muted); margin-top:2px;"><strong>Admin:</strong> {{ $item['admin_note'] }}</div>
                                @endif
                                @else
                                    —
                                @endif
                            </td>
                            <td>
                                @if(!$item['waiver_status'])
                                    <button type="button" class="btn-outline-glass" style="padding:2px 10px;font-size:11px;"
                                        onclick="openWaiverModal('{{ $item['date'] }}', '{{ $item['type'] }}')">Request</button>
                                @elseif($item['waiver_status'] === 'pending')
                                    <span style="font-size:12px; color:#d97706;">⏳ Pending</span>
                                @elseif($item['waiver_status'] === 'approved')
                                    <span style="font-size:12px; color:#22c55e;">✅ Waived</span>
                                @else
                                    {{-- Decided: one request per item --}}
                                    <button type="button" class="btn-outline-glass" style="padding:2px 10px;font-size:11px; opacity:.5; cursor:not-allowed;"
                                        disabled title="This waiver was rejected and cannot be requested again">Request</button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5">
                                <div class="empty-state">
                                    <i class="bi bi-check-circle"></i>
                                    <p>No late arrivals, early outs or half-days in this cycle.</p>
                                    <span class="sub">All good! Keep it up.</span>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- INFO BOX -->
    <div class="glass-card">
        <div class="info-box">
            <i class="bi bi-info-circle"></i>
            Every <strong>{{ $thresholds['late'] }} late arrivals</strong> or <strong>{{ $thresholds['early_out'] }} early outs</strong>
            in a cycle equal ½ Casual Leave (half day), and every <strong>{{ $thresholds['half_day'] }} half-days</strong>@if ($thresholds['absent'] > 0) or <strong>{{ $thresholds['absent'] }} absent {{ $thresholds['absent'] == 1 ? 'day' : 'days' }}</strong> (no punch, no leave)@endif equal 1 Casual Leave, deducted automatically
            as soon as the limit is reached. Where the Casual Leave balance is insufficient, the shortfall is a
            <strong>salary-day deduction</strong>. A <strong>waiver</strong> can be requested for any item; once the Admin
            approves it, the item stops counting and the deducted leave is returned automatically. Days covered by approved
            leave are not counted.
        </div>
    </div>

    <!-- WAIVER REQUEST MODAL -->
    <div class="modal fade modal-glass" id="waiverRequestModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="POST" action="{{ route('employee.penalty.waiver.store') }}">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="bi bi-shield" style="color:var(--primary);"></i> Request Waiver
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label class="form-label-sm">Date</label>
                            <input type="date" name="date" id="waiverDate" class="form-control-custom"
                                max="{{ now()->format('Y-m-d') }}" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label-sm">Type</label>
                            <select name="type" id="waiverType" class="form-control-custom" required>
                                <option value="">Select type...</option>
                                <option value="late">Late Arrival</option>
                                <option value="early_out">Early Out</option>
                                <option value="half_day">Half-Day</option>
                                <option value="absent">Absent</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label-sm">Reason</label>
                            <textarea name="reason" class="form-control-custom" rows="2"
                                placeholder="Explain why you want this waived..." required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn-cancel" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn-save"><i class="bi bi-check2"></i> Submit Waiver</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

@endsection

@push('scripts')
    <script>
        function openWaiverModal(date, type) {
            document.getElementById('waiverDate').value = date;
            document.getElementById('waiverType').value = type;
            new bootstrap.Modal(document.getElementById('waiverRequestModal')).show();
        }
    </script>
@endpush