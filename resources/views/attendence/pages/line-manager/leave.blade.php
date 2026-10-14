@extends('attendence.layouts.app')

@section('title', 'Leave')
@section('page-title', 'Leave')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/employee/leave.css') }}">
@endpush

@section('content')

    {{-- @if(session('success'))
    <script>document.addEventListener('DOMContentLoaded', () => toastr.success(@json(session('success'))));</script>
    @endif
    @if(session('error'))
    <script>document.addEventListener('DOMContentLoaded', () => toastr.error(@json(session('error'))));</script>
    @endif --}}

    <!-- LEAVE BALANCES -->
    <div class="glass-card">
        <div class="page-header">
            @php([$leaveYearFrom, $leaveYearTo] = \App\Models\LeaveType::leaveYear())
            <h4>Leave balances · {{ $leaveYearFrom->format('d M Y') }} – {{ $leaveYearTo->format('d M Y') }}</h4>
        </div>
        @if ($probationUntil ?? null)
            <div style="margin:0 0 12px; padding:10px 14px; border-radius:8px; background:#fef3c7; color:#92400e; font-size:13px;">
                <i class="bi bi-hourglass-split"></i> You are on <strong>probation until {{ $probationUntil->format('d M Y') }}</strong>: no paid leave until then,
                and leave requests open after that date.
            </div>
        @endif

        <div class="leave-balance-grid">
            @foreach($balances as $b)
                <div class="leave-balance-card">
                    <span class="icon">📋</span>
                    <div class="label">{{ $b['name'] }}</div>
                    <div class="balance">{{ $b['remaining'] }}</div>
                    <div class="total">/ {{ $b['total'] }} d</div>
                    <div class="taken">{{ $b['used'] }} taken this year</div>
                </div>
            @endforeach
        </div>
    </div>

    <!-- LEAVE REQUESTS TABLE -->
    <div class="glass-card">
        <div class="top-actions">
            <span style="font-size:13px; color:var(--text-muted);">My leave requests · {{ $myRequests->count() }}
                total</span>
            <button class="btn-primary-glass" data-bs-toggle="modal" data-bs-target="#applyLeaveModal">
                <i class="bi bi-plus"></i> Apply for leave
            </button>
        </div>

        <div style="overflow-x:auto;">
            <table class="leave-table">
                <thead>
                    <tr>
                        <th>Type</th>
                        <th>Dates</th>
                        <th>Days</th>
                        <th>Reason</th>
                        <th>Status</th>
                        <th>Note</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($myRequests as $r)
                        <tr>
                            <td><strong>{{ $r->leaveType->name ?? '-' }}</strong></td>
                            <td>{{ \Carbon\Carbon::parse($r->from_date)->format('d M') }} –
                                {{ \Carbon\Carbon::parse($r->to_date)->format('d M Y') }}</td>
                            <td>
                                {{ $r->days }}
                                @if($r->is_half_day)
                                    <br><span style="font-size:11px; color:var(--text-muted);">Half day ·
                                        {{ $r->half_day_session === 'second_half' ? '2nd half' : '1st half' }}</span>
                                @endif
                            </td>
                            <td>
                                {{ $r->reason }}
                                @if ($r->document_path)
                                    <br><a href="{{ route('leave.document', $r->id) }}" target="_blank" style="font-size:12px;">
                                        <i class="bi bi-paperclip"></i> Document</a>
                                @endif
                            </td>
                            <td>
                                <span class="status-badge {{ $r->status }}">{{ ucfirst($r->status) }}</span>
                                @if($r->status === 'pending')
                                    <br><span style="font-size:11px; color:var(--text-muted);">{{ $r->stageLabel() }}</span>
                                @endif
                            </td>
                            <td style="font-size:12px; color:var(--text-muted);">
                                @if($r->manager_note)
                                    <div><strong>{{ $r->manager?->roleLabel() ?: 'Line Manager' }}:</strong> {{ $r->manager_note }}</div>
                                @endif
                                @if($r->admin_note)
                                    <div><strong>Admin:</strong> {{ $r->admin_note }}</div>
                                @endif
                                @if(!$r->manager_note && !$r->admin_note)
                                    —
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6">
                                <div class="empty-state">
                                    <i class="bi bi-inbox"></i>
                                    <p>No leave requests yet.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- APPLY FOR LEAVE MODAL -->
    <div class="modal fade modal-glass" id="applyLeaveModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="POST" action="{{ route('line_manager.leave.store') }}" id="leaveForm" enctype="multipart/form-data">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="bi bi-calendar-plus" style="color:var(--primary);"></i> Apply for
                            leave</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label>Leave Type</label>
                            <select class="form-control-custom" name="leave_type_id" id="leaveType"
                                onchange="updateLeaveInfo()" required>
                                @foreach($balances as $b)
                                    <option value="{{ $b['id'] }}" data-available="{{ $b['remaining'] }}"
                                        data-name="{{ $b['name'] }}" data-rules="{{ $b['rules'] }}"
                                        data-requires-document="{{ $b['requires_document'] ? 1 : 0 }}" data-document-from="{{ $b['document_from'] }}" @selected(old('leave_type_id') == $b['id'])>
                                        {{ $b['name'] }} · {{ $b['remaining'] }} left
                                    </option>
                                @endforeach
                            </select>
                            <small id="leaveRules" style="display:none; font-size:12px; color:#b45309; margin-top:4px;">
                                <i class="bi bi-info-circle"></i> <span></span>
                            </small>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>From</label>
                                    <input type="date" class="form-control-custom" name="from_date" id="fromDate"
                                        value="{{ date('Y-m-d') }}" onchange="calculateDays()" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>To</label>
                                    <input type="date" class="form-control-custom" name="to_date" id="toDate"
                                        value="{{ date('Y-m-d') }}" onchange="calculateDays()" required>
                                </div>
                            </div>
                        </div>

                        <div class="checkbox-group">
                            <input type="checkbox" class="form-control-custom" name="half_day" id="halfDay" value="1"
                                style="width:auto;" onchange="calculateDays()">
                            <label for="halfDay">Half day (single day only)</label>
                        </div>

                        <div class="form-group" id="halfDaySessionGroup" style="display:none;">
                            <label>Half day session</label>
                            <select class="form-control-custom" name="half_day_session" id="halfDaySession">
                                <option value="first_half">First half (morning)</option>
                                <option value="second_half">Second half (afternoon)</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Reason</label>
                            <textarea class="form-control-custom" name="reason" rows="2" placeholder="Reason for leave..."
                                required></textarea>
                        </div>

                        <div class="form-group" id="documentGroup" style="display:none;">
                            <label>Supporting document <span style="color:#ef4444;">*</span></label>
                            <input type="file" class="form-control-custom" name="document" id="leaveDocument"
                                accept=".jpg,.jpeg,.png,.pdf">
                            <small style="font-size:11px; color:var(--text-muted);">e.g. doctor's slip · JPG, PNG or PDF, max 5 MB</small>
                        </div>

                        <div class="leave-info" id="leaveInfo">
                            <span id="daysCount">0</span> working days requested · <span id="availableDays">0</span>
                            available in <span id="leaveTypeName"></span>
                            <br><span style="font-size:12px;">This request will first go to
                                {{ $reviewingManager ? $reviewingManager->roleLabel().' ('.$reviewingManager->full_name.')' : 'Admin' }}
                                for approval.</span>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn-cancel" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn-save">
                            <i class="bi bi-check2"></i> Submit request
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

@endsection

@push('scripts')
    <script>
        function calculateDays() {
            const from = document.getElementById('fromDate').value;
            const to = document.getElementById('toDate').value;
            const halfDay = document.getElementById('halfDay').checked;

            // Half day is a single day: set To date equal to From date
            document.getElementById('halfDaySessionGroup').style.display = halfDay ? '' : 'none';
            if (halfDay && from) {
                document.getElementById('toDate').value = from;
            }

            if (!from || !to) {
                setRequestedDays(0);
                return;
            }

            if (halfDay) {
                setRequestedDays(0.5);
                return;
            }

            // Same as the server: Attendance Policy working days, minus holidays
            const workingDays = @json($workingDays);
            const holidays = @json($holidayDates);
            const dayNames = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
            let count = 0;
            const current = new Date(from + 'T00:00:00');
            const end = new Date(to + 'T00:00:00');

            while (current <= end) {
                const iso = current.getFullYear() + '-' + String(current.getMonth() + 1).padStart(2, '0') + '-' + String(current.getDate()).padStart(2, '0');
                if (workingDays.includes(dayNames[current.getDay()]) && ! holidays.includes(iso)) count++;
                current.setDate(current.getDate() + 1);
            }

            setRequestedDays(count);
        }

        // Shows the day count and the document upload when the selected type needs it for this many days
        // (e.g. Sick Leave: 1 day without, 2 or more days with a doctor's slip)
        function setRequestedDays(days) {
            document.getElementById('daysCount').textContent = days;

            const select = document.getElementById('leaveType');
            const selected = select.options[select.selectedIndex];
            const needsDocument = selected.dataset.requiresDocument === '1' && days > 0
                && days >= parseFloat(selected.dataset.documentFrom || 0);

            document.getElementById('documentGroup').style.display = needsDocument ? '' : 'none';
            document.getElementById('leaveDocument').required = needsDocument;
        }

        function updateLeaveInfo() {
            const select = document.getElementById('leaveType');
            const selected = select.options[select.selectedIndex];
            document.getElementById('availableDays').textContent = selected.dataset.available;
            document.getElementById('leaveTypeName').textContent = selected.dataset.name;

            // Leave policy of the selected type (e.g. max per month, minimum days, document required)
            const rules = document.getElementById('leaveRules');
            rules.style.display = selected.dataset.rules ? '' : 'none';
            rules.querySelector('span').textContent = selected.dataset.rules || '';

            calculateDays();
        }

        document.addEventListener('DOMContentLoaded', function () {
            updateLeaveInfo();
        });
    </script>
@endpush