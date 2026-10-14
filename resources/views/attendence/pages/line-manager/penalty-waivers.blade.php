@extends('attendence.layouts.app')

@section('title', 'Penalty & Waivers')
@section('page-title', 'Penalty & Waivers')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/manager-css/penalty-wave.css') }}">
@endpush

@section('content')


    <!-- HEADER + SELECTORS -->
    <div class="glass-card">
        <div class="cycle-header">
            <div>
                <h4>Attendance Cycle</h4>
                <span class="cycle-date">{{ $targetName }} · {{ $label }}</span>
            </div>
            <form method="GET" action="{{ route('line_manager.penalty.waivers') }}" class="cycle-selector">
                <label style="font-size:12px; color:var(--text-muted); font-weight:600;">Employee</label>
                <select name="employee" onchange="this.form.submit()">
                    <option value="self" {{ $selectedId == 'self' ? 'selected' : '' }}>Myself</option>
                    @foreach ($team as $t)
                        <option value="{{ $t->id }}" {{ (string) $selectedId === (string) $t->id ? 'selected' : '' }}>
                            {{ $t->first_name }} {{ $t->last_name }} ({{ $t->employee_id }})</option>
                    @endforeach
                </select>

                <label style="font-size:12px; color:var(--text-muted); font-weight:600; margin-left:8px;">Cycle</label>
                <select name="cycle" onchange="this.form.submit()">
                    @foreach ($cycles as $i => $c)
                        <option value="{{ $i }}" {{ $offset == $i ? 'selected' : '' }}>{{ $c[2] }}
                        </option>
                    @endforeach
                </select>
            </form>
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
                <h6>Penalty Items — {{ $targetName }}</h6>
                <span class="subtitle">{{ $items->count() }} penalty items</span>
            </div>
            @if ($isSelf)
                <button class="btn-primary-glass" data-bs-toggle="modal" data-bs-target="#waiverRequestModal">
                    <i class="bi bi-plus"></i> Request Waiver
                </button>
            @endif
        </div>

        <div style="overflow-x:auto;">
           <table class="penalty-table">

    <thead>
        <tr>
            <th>Date</th>
            <th>Type</th>
            <th>Status</th>
            <th>Employee Note</th>
            <th>Waiver Status</th>
            <th style="text-align:right;">Action</th>
        </tr>
    </thead>

    <tbody>

        @forelse($items as $item)

            <tr>

                {{-- DATE --}}
                <td>
                    {{ \Carbon\Carbon::parse($item['date'])->format('d M Y') }}
                </td>


                {{-- TYPE --}}
                <td>
                    {{ $item['type_label'] }}
                </td>


                {{-- PENALTY STATUS --}}
                <td>
                    <span class="status-badge flagged">
                        Flagged
                    </span>
                </td>


                {{-- EMPLOYEE WAIVER REASON --}}
                <td style="max-width:250px;">

                    @if(!empty($item['waiver_reason']))

                        <span
                            title="{{ $item['waiver_reason'] }}"
                            style="
                                font-size:12px;
                                color:var(--text-secondary);
                                white-space:normal;
                            "
                        >
                            <i class="bi bi-chat-left-text"></i>
                            {{ $item['waiver_reason'] }}
                        </span>

                    @else

                        <span style="color:var(--text-muted);">
                            —
                        </span>

                    @endif

                </td>


                {{-- FINAL ADMIN WAIVER STATUS --}}
                <td>

                    @if($item['waiver_status'] === 'pending')

                        <span class="status-badge pending">
                            Pending
                        </span>

                    @elseif($item['waiver_status'] === 'approved')

                        <span class="status-badge approved">
                            Approved
                        </span>

                    @elseif($item['waiver_status'] === 'rejected')

                        <span class="status-badge rejected">
                            Rejected
                        </span>

                    @else

                        —

                    @endif

                    @if (! empty($item['admin_note']))
                        <div style="font-size:11px; color:var(--text-muted); margin-top:4px; white-space:normal;">
                            <strong>Admin:</strong> {{ $item['admin_note'] }}
                        </div>
                    @endif

                </td>


                {{-- ACTION --}}
                <td style="text-align:right; white-space:nowrap;">


                    {{-- ======================================
                        LINE MANAGER VIEWING HIMSELF
                    ======================================= --}}
                    @if($isSelf)


                        @if(!$item['waiver_status'])

                            <button
                                type="button"
                                class="btn-outline-glass"
                                style="padding:2px 10px;font-size:11px;"
                                onclick="openWaiverModal(
                                    '{{ $item['date'] }}',
                                    '{{ $item['type'] }}'
                                )"
                            >
                                Request
                            </button>


                        @elseif($item['waiver_status'] === 'pending')

                            <span style="font-size:12px;color:#d97706;">
                                <i class="bi bi-hourglass-split"></i>
                                Pending
                            </span>


                        @elseif($item['waiver_status'] === 'approved')

                            <span style="font-size:12px;color:#22c55e;">
                                <i class="bi bi-check-circle"></i>
                                Waived
                            </span>

                        @else

                            {{-- Decided: one request per item --}}
                            <button type="button" class="btn-outline-glass" style="padding:2px 10px;font-size:11px; opacity:.5; cursor:not-allowed;"
                                disabled title="This waiver was rejected and cannot be requested again">
                                Request
                            </button>

                        @endif



                    {{-- ======================================
                        LINE MANAGER VIEWING TEAM MEMBER
                    ======================================= --}}
                    @else


                        {{-- MANAGER HAS NOT REVIEWED YET --}}
                        @if(
                            $item['waiver_status'] === 'pending' &&
                            $item['lm_status'] === 'pending'
                        )

                            <div
                                style="
                                    display:flex;
                                    gap:6px;
                                    justify-content:flex-end;
                                "
                            >

                                {{-- APPROVE --}}
                                <button
                                    type="button"
                                    class="btn-approve"
                                    onclick="openReviewWaiverModal(
                                        '{{ route('line_manager.approvals.waiver.approve', $item['waiver_id']) }}',
                                        'approve',
                                        '{{ $item['date'] }}',
                                        '{{ $item['type_label'] }}'
                                    )"
                                >
                                    <i class="bi bi-check2"></i>
                                    Approve
                                </button>


                                {{-- REJECT --}}
                                <button
                                    type="button"
                                    class="btn-reject"
                                    onclick="openReviewWaiverModal(
                                        '{{ route('line_manager.approvals.waiver.reject', $item['waiver_id']) }}',
                                        'reject',
                                        '{{ $item['date'] }}',
                                        '{{ $item['type_label'] }}'
                                    )"
                                >
                                    <i class="bi bi-x"></i>
                                    Reject
                                </button>

                            </div>



                        {{-- MANAGER APPROVED --}}
                        @elseif($item['lm_status'] === 'approved')

                            <div style="text-align:right;">

                                <div
                                    style="
                                        color:#059669;
                                        font-size:12px;
                                        font-weight:600;
                                    "
                                >
                                    <i class="bi bi-check-circle-fill"></i>
                                    Approved
                                </div>

                                @if(!empty($item['lm_note']))

                                    <div
                                        style="
                                            margin-top:4px;
                                            font-size:11px;
                                            color:var(--text-muted);
                                            max-width:220px;
                                            white-space:normal;
                                        "
                                    >
                                        {{ $item['lm_note'] }}
                                    </div>

                                @endif

                            </div>



                        {{-- MANAGER REJECTED --}}
                        @elseif($item['lm_status'] === 'rejected')

                            <div style="text-align:right;">

                                <div
                                    style="
                                        color:#dc2626;
                                        font-size:12px;
                                        font-weight:600;
                                    "
                                >
                                    <i class="bi bi-x-circle-fill"></i>
                                    Rejected
                                </div>

                                @if(!empty($item['lm_note']))

                                    <div
                                        style="
                                            margin-top:4px;
                                            font-size:11px;
                                            color:var(--text-muted);
                                            max-width:220px;
                                            white-space:normal;
                                        "
                                    >
                                        {{ $item['lm_note'] }}
                                    </div>

                                @endif

                            </div>


                        @else

                            <span style="color:var(--text-muted);">
                                —
                            </span>

                        @endif

                    @endif

                </td>

            </tr>


        @empty

            <tr>
                <td colspan="6">

                    <div class="empty-state">

                        <i class="bi bi-check-circle"></i>

                        <p>
                            No late arrivals, early outs or half-days in this cycle.
                        </p>

                        <span class="sub">
                            All good! Keep it up.
                        </span>

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

    @if ($isSelf)
        <!-- WAIVER REQUEST MODAL (sirf khud ke liye) -->
        <div class="modal fade modal-glass" id="waiverRequestModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <form method="POST" action="{{ route('line_manager.penalty.waiver.store') }}">
                        @csrf
                        <div class="modal-header">
                            <h5 class="modal-title"><i class="bi bi-shield" style="color:var(--primary);"></i> Request
                                Waiver</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="form-group">
                                <label class="form-label-sm">Date</label>
                                <input type="date" name="date" id="waiverDate" class="form-control-custom" required>
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
    @endif
    <div class="modal fade modal-glass" id="reviewWaiverModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="POST" id="reviewWaiverForm" action="">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="bi bi-pencil-square" style="color:var(--primary);"></i> Review
                            Waiver Request</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p style="font-size:13px; color:var(--text-muted); margin-bottom:16px;" id="reviewItemInfo"></p>
                        <div class="form-group">
                            <label class="form-label-sm">Your Note (required)</label>
                            <textarea name="note" class="form-control-custom" rows="3" placeholder="Reasoning for admin ke liye..."
                                required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn-cancel" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn-reject" onclick="submitReview('reject')"><i
                                class="bi bi-x"></i> Recommend Reject</button>
                        <button type="button" class="btn-approve" onclick="submitReview('approve')"><i
                                class="bi bi-check2"></i> Recommend Approve</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>

   
    // OWN WAIVER REQUEST MODAL
    
    function openWaiverModal(date, type) {

        const dateInput = document.getElementById('waiverDate');
        const typeInput = document.getElementById('waiverType');
        const modalElement = document.getElementById('waiverRequestModal');

        if (!modalElement) {
            console.error('waiverRequestModal not found');
            return;
        }

        if (dateInput) {
            dateInput.value = date;
        }

        if (typeInput) {
            typeInput.value = type;
        }

        const modal = new bootstrap.Modal(modalElement);
        modal.show();
    }


   
    // LINE MANAGER REVIEW MODAL
  
    function openReviewWaiverModal(actionUrl, decision, date, type) {

        const form = document.getElementById('reviewWaiverForm');
        const modalElement = document.getElementById('reviewWaiverModal');
        const itemInfo = document.getElementById('reviewItemInfo');
        const noteField = form
            ? form.querySelector('textarea[name="note"]')
            : null;

        if (!form) {
            console.error('reviewWaiverForm not found');
            return;
        }

        if (!modalElement) {
            console.error('reviewWaiverModal not found');
            return;
        }

        // Set controller route
        form.action = actionUrl;

        // Clear previous note
        if (noteField) {
            noteField.value = '';
        }

        // Show item information
        if (itemInfo) {
            itemInfo.innerHTML =
                '<strong>' + type + '</strong> · ' + date;
        }

        // Change popup title depending on action
        const title = modalElement.querySelector('.modal-title');

        if (title) {

            if (decision === 'approve') {

                title.innerHTML =
                    '<i class="bi bi-check-circle" style="color:#22c55e;"></i> ' +
                    'Recommend Waiver Approval';

            } else {

                title.innerHTML =
                    '<i class="bi bi-x-circle" style="color:#dc2626;"></i> ' +
                    'Recommend Waiver Rejection';
            }
        }

        // Store decision for submitReview()
        form.dataset.decision = decision;

        // Open Bootstrap modal
        const modal = new bootstrap.Modal(modalElement);
        modal.show();
    }


    // ==========================================
    // SUBMIT LINE MANAGER REVIEW
    // ==========================================
    function submitReview(decision) {

        const form = document.getElementById('reviewWaiverForm');

        if (!form) {
            return;
        }

        const noteField = form.querySelector(
            'textarea[name="note"]'
        );

        const note = noteField
            ? noteField.value.trim()
            : '';

        // Note is mandatory
        if (!note) {

            if (noteField) {
                noteField.focus();
            }

            alert(
                decision === 'approve'
                    ? 'Please enter why you are approving this waiver.'
                    : 'Please enter why you are rejecting this waiver.'
            );

            return;
        }

        form.submit();
    }

</script>
@endpush
