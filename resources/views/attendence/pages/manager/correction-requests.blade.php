@extends('attendence.layouts.app')

@section('title', 'Correction Requests')
@section('page-title', 'Correction Requests')

@push('styles')
  <link rel="stylesheet" href="{{ asset('css/employee/correction.css') }}">
@endpush

@section('content')


<div class="glass-card">
    <div class="page-header">
        <h4>Your correction requests</h4>
        <p>Late, missing-punch, or wrong-time records can be corrected here.</p>
    </div>
</div>

<div class="glass-card">
    <div class="top-actions">
        <span style="font-size:13px; color:var(--text-muted);">{{ $requests->count() }} requests</span>
        <button class="btn-primary-glass" data-bs-toggle="modal" data-bs-target="#newRequestModal">
            <i class="bi bi-plus"></i> New request
        </button>
    </div>

    <div style="overflow-x:auto;">
        <table class="correction-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Requested Change</th>
                    <th>Reason</th>
                    <th>Status</th>
                    <th>Notes</th>
                </tr>
            </thead>
            <tbody>
                @forelse($requests as $r)
                    <tr>
                        <td><strong>{{ \Carbon\Carbon::parse($r->date)->format('d M Y') }}</strong></td>
                        <td>
                            {{ $r->typeLabel() }}
                            <br><span style="font-size:11px; color:var(--text-muted);">{{ $r->current_record ?: '—' }} → {{ $r->requestedLabel() }}</span>
                            @if ($r->applied_at)
                                <br><span style="font-size:11px; color:#22c55e;"><i class="bi bi-check-circle"></i> Attendance updated</span>
                            @endif
                        </td>
                        <td>{{ $r->reason }}</td>
                        <td>
                            <span class="status-badge {{ $r->status }}">{{ ucfirst($r->status) }}</span>
                            @if ($r->status === 'pending')
                                <br><span style="font-size:11px; color:var(--text-muted);">{{ $r->stageLabel() }}</span>
                            @endif
                        </td>
                        <td class="manager-note">
                            @if (in_array($r->lm_status, ['approved', 'rejected']))
                                <div><strong>{{ $r->lmReviewer?->roleLabel() ?? 'Line Manager' }}:</strong>
                                    {{ $r->lm_status === 'approved' ? 'Approved' : 'Not approved' }}{{ $r->lm_note ? ' — ' . $r->lm_note : '' }}</div>
                            @endif
                            @if ($r->status !== 'pending' && $r->manager_note)
                                <div><strong>{{ in_array($r->lm_status, ['approved', 'rejected']) ? 'Admin' : 'Note' }}:</strong> {{ $r->manager_note }}</div>
                            @endif
                            @if (! in_array($r->lm_status, ['approved', 'rejected']) && ($r->status === 'pending' || ! $r->manager_note))
                                —
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5">
                            <div class="empty-state">
                                <i class="bi bi-inbox"></i>
                                <p>No requests yet.</p>
                                <span class="sub">Submit one if a record looks wrong.</span>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade modal-glass" id="newRequestModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="{{ route('manager.correction.store') }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-pencil-square" style="color:var(--primary);"></i> New Correction Request</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label class="form-label-sm">Date</label>
                        <input type="date" name="date" id="correctionDate" class="form-control-custom"
                            value="{{ old('date', request('date', date('Y-m-d'))) }}" max="{{ date('Y-m-d') }}" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label-sm">What happened?</label>
                        <select name="change_type" id="correctionType" class="form-control-custom" required onchange="toggleCorrectionTimes()">
                            <option value="">Select...</option>
                            @foreach (\App\Models\CorrectionRequest::TYPES as $value => $label)
                                <option value="{{ $value }}" @selected(old('change_type', request('type')) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="row">
                        <div class="col-6 form-group" id="checkInGroup">
                            <label class="form-label-sm">Actual check-in time</label>
                            <input type="time" name="requested_check_in" id="requestedCheckIn" class="form-control-custom" value="{{ old('requested_check_in') }}">
                        </div>
                        <div class="col-6 form-group" id="checkOutGroup">
                            <label class="form-label-sm" id="checkOutLabel">Actual check-out time</label>
                            <input type="time" name="requested_check_out" id="requestedCheckOut" class="form-control-custom" value="{{ old('requested_check_out') }}">
                        </div>
                    </div>
                    <p id="officialDutyHint" style="display:none; font-size:12px; color:var(--text-muted); margin:-6px 0 12px;">
                        Official duty counts as a full day (until the end of your shift) once approved.
                    </p>
                    <div class="form-group">
                        <label class="form-label-sm">Reason</label>
                        <textarea name="reason" class="form-control-custom" rows="2" maxlength="500"
                            placeholder="e.g. Forgot to punch out / Went to the client office for a meeting..." required>{{ old('reason') }}</textarea>
                    </div>
                    <p style="font-size:12px; color:var(--text-muted); margin:0;">
                        Once approved, your attendance for that day is updated with these times.
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-cancel" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn-save">
                        <i class="bi bi-check2"></i> Submit Request
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
    function toggleCorrectionTimes() {
        const type = document.getElementById('correctionType').value;
        const needsIn = type === 'forgot-check-in' || type === 'wrong-time';
        const needsOut = type !== 'forgot-check-in';

        document.getElementById('checkInGroup').style.display = needsIn ? '' : 'none';
        document.getElementById('checkOutGroup').style.display = needsOut ? '' : 'none';
        document.getElementById('requestedCheckIn').required = type === 'forgot-check-in';
        document.getElementById('requestedCheckOut').required = type === 'forgot-check-out' || type === 'official-duty';
        document.getElementById('checkOutLabel').textContent = type === 'official-duty' ? 'Time you left for office work' : 'Actual check-out time';
        document.getElementById('officialDutyHint').style.display = type === 'official-duty' ? '' : 'none';
    }

    document.addEventListener('DOMContentLoaded', function () {
        toggleCorrectionTimes();

        // Opened from "Request correction" on My Attendance, or after a validation error
        @if (request('date') || $errors->any())
            new bootstrap.Modal(document.getElementById('newRequestModal')).show();
        @endif
    });
</script>
@endpush
