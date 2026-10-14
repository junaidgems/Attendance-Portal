@extends('attendence.layouts.app')

@section('title', 'Attendance Policy')
@section('page-title', 'Attendance Policy')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/attendance-policy.css') }}">
@endpush

@section('content')

    {{-- @if(session('success'))
    <script>document.addEventListener('DOMContentLoaded', () => toastr.success(@json(session('success'))));</script>
    @endif
    @if(session('error'))
    <script>document.addEventListener('DOMContentLoaded', () => toastr.error(@json(session('error'))));</script>
    @endif --}}

    {{-- POLICY LOCK: every change here needs the Director's unlock --}}
    @if ($policyLock['supported'])
        @if ($policyLock['unlocked'])
            <div class="glass-card" style="border-left:3px solid #10b981; padding:12px 18px; font-size:13px; display:flex; align-items:center; gap:12px; flex-wrap:wrap;">
                <i class="bi bi-unlock" style="color:#10b981; font-size:18px;"></i>
                <div>
                    <strong style="color:#059669;">Unlocked by {{ $policyLock['unlocked_by'] }} (Director)</strong>
                    until <strong>{{ $policyLock['unlocked_until']->format('h:i A') }}</strong>. Save your changes before then; the page locks itself again.
                </div>
            </div>
        @else
            <div class="glass-card" style="border-left:3px solid #ef4444; padding:12px 18px; font-size:13px; display:flex; align-items:center; gap:12px; flex-wrap:wrap;">
                <i class="bi bi-lock-fill" style="color:#ef4444; font-size:18px;"></i>
                <div style="flex:1; min-width:220px;">
                    <strong style="color:#ef4444;">Attendance Policy is locked.</strong>
                    Changes need the Director's approval.
                    @if ($policyLock['requested_at'])
                        <br><span style="color:var(--text-muted);">Unlock requested by {{ $policyLock['requested_by'] }} on {{ $policyLock['requested_at']->format('d M, h:i A') }} — waiting for the Director.</span>
                    @endif
                </div>
                <form method="POST" action="{{ route('admin.attendance-policy.request-unlock') }}" data-keep-enabled="1">
                    @csrf
                    <button type="submit" class="btn-primary-glass" style="white-space:nowrap;">
                        <i class="bi bi-send"></i> {{ $policyLock['requested_at'] ? 'Send request again' : 'Request unlock from Director' }}
                    </button>
                </form>
            </div>
        @endif
    @endif

    @if ($errors->any())
        <div class="glass-card" style="border-left:3px solid #ef4444; padding:12px 18px; font-size:13px;">
            <strong style="color:#ef4444;"><i class="bi bi-exclamation-triangle"></i> Policy was not saved:</strong>
            <ul style="margin:6px 0 0; padding-left:18px;">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('admin.attendance-policy.settings') }}">
        @csrf

        <!-- ATTENDANCE CYCLE                          -->

        <div class="row">
            <div class="col-12">
                <div class="glass-card">
                    <div class="section-title"><i class="bi bi-calendar3"></i> Attendance Cycle</div>
                    <p class="section-sub">Monthly cycle used across all attendance &amp; leave</p>

                    <div class="policy-row">
                        <span class="label">Cycle Start Day</span>
                        <span class="value"><input type="number" name="cycle_start_day"
                                value="{{ $settings->cycle_start_day }}"></span>
                    </div>
                    <div class="policy-row">
                        <span class="label">Cycle End Day</span>
                        <span class="value"><input type="number" name="cycle_end_day"
                                value="{{ $settings->cycle_end_day }}"></span>
                    </div>

                    @php
                        [$leaveYearMonth, $leaveYearDay] = \App\Models\LeaveType::parseYearStart($settings->leave_year_start ?? null);
                        [$leaveYearFrom, $leaveYearTo] = \App\Models\LeaveType::leaveYear(null, $settings);
                    @endphp
                    <div class="policy-row">
                        <span class="label">
                            Leave Year Starts On
                            <small style="display:block; font-weight:400; color:var(--text-muted);">
                                Leave balances reset to the full allowance on this date every year.
                                Current leave year: {{ $leaveYearFrom->format('d M Y') }} – {{ $leaveYearTo->format('d M Y') }}
                            </small>
                        </span>
                        <span class="value" style="display:flex; gap:6px;">
                            <input type="number" name="leave_year_start_day" min="1" max="31" style="width:70px;"
                                value="{{ old('leave_year_start_day', $leaveYearDay) }}">
                            <select name="leave_year_start_month">
                                @foreach (range(1, 12) as $m)
                                    <option value="{{ $m }}" @selected((int) old('leave_year_start_month', $leaveYearMonth) === $m)>{{ \Carbon\Carbon::create(2024, $m, 1)->format('F') }}</option>
                                @endforeach
                            </select>
                        </span>
                    </div>

                    <div class="cycle-info">
                        <div class="cycle-item"><span class="label">Current</span> <span class="value">12 Jul – 1 Aug
                                2026</span></div>
                        <div class="cycle-item"><span class="label">Next</span> <span class="value">12 Aug – 1 Sep
                                2026</span>
                        </div>
                    </div>

                    <div class="info-box">
                        <i class="bi bi-info-circle"></i>
                        Every attendance and leave dashboard, summary and report uses this cycle instead of the calendar
                        month,
                        and it repeats automatically each month. Changes apply to the current and upcoming cycles —
                        historical
                        records are never modified. Configurable by administrators only.
                    </div>
                </div>
            </div>
        </div>


        <!-- WORKING HOURS + GRACE TIME                -->

        <div class="row">
            <div class="col-md-6">
                <div class="glass-card">
                    <div class="section-title"><i class="bi bi-clock"></i> Working hours</div>

                    <div class="policy-row">
                        <span class="label">Standard Shift Start</span>
                        <span class="value"><input type="time" name="standard_shift_start"
                                value="{{ \Carbon\Carbon::parse($settings->standard_shift_start)->format('H:i') }}"></span>
                    </div>
                    <div class="policy-row">
                        <span class="label">Standard Shift End</span>
                        <span class="value"><input type="time" name="standard_shift_end"
                                value="{{ \Carbon\Carbon::parse($settings->standard_shift_end)->format('H:i') }}"></span>
                    </div>
                    <div class="policy-row">
                        <span class="label">Unpaid Break Deducted (Minutes)</span>
                        <span class="value"><input type="number" name="unpaid_break_minutes"
                                value="{{ $settings->unpaid_break_minutes }}"></span>
                    </div>

                    <p style="font-size:13px; color:var(--text-muted); margin-top:12px;">Default shift hours applied to all
                        employees. Create additional shifts under <strong>Shifts</strong>.</p>
                </div>
            </div>

            <div class="col-md-6">
                <div class="glass-card">
                    <div class="section-title"><i class="bi bi-hourglass-split"></i> Grace time</div>

                    <div class="policy-row">
                        <span class="label">Late Arrival Grace (Minutes)</span>
                        <span class="value"><input type="number" name="late_arrival_grace_minutes"
                                value="{{ $settings->late_arrival_grace_minutes }}"></span>
                    </div>
                    <div class="policy-row">
                        <span class="label">Early Departure Grace (Minutes)</span>
                        <span class="value"><input type="number" name="early_departure_grace_minutes"
                                value="{{ $settings->early_departure_grace_minutes }}"></span>
                    </div>

                    <p style="font-size:13px; color:var(--text-muted); margin-top:12px;">A punch-in within the late grace is
                        treated as on time; a punch-out within the early grace is not counted as an early departure.</p>
                </div>
            </div>
        </div>


        <!-- DAY CLASSIFICATION + WORKING DAYS         -->

        <div class="row">
            <div class="col-md-6">
                <div class="glass-card">
                    <div class="section-title"><i class="bi bi-sun"></i> Day classification</div>

                    {{-- Day classification now follows each day's shift length; old minute settings are kept as-is --}}
                    <input type="hidden" name="half_day_max_minutes" value="{{ $settings->half_day_max_minutes }}">
                    <input type="hidden" name="full_day_min_minutes" value="{{ $settings->full_day_min_minutes }}">

                    <div class="policy-row">
                        <span class="label">Half Day</span>
                        <span class="value">Worked ≤ half of that day's shift</span>
                    </div>
                    <div class="policy-row">
                        <span class="label">Full Day</span>
                        <span class="value">Worked ≥ shift length − early grace</span>
                    </div>
                    <div class="policy-row">
                        <span class="label">Half Day If Check-In Is Later Than</span>
                        <span class="value"><input type="number" name="half_day_after_late_minutes" min="0" max="720" placeholder="off"
                                value="{{ $settings->half_day_after_late_minutes ?? '' }}"> minutes after shift start</span>
                    </div>
                    <p style="font-size:12px; color:var(--text-muted); margin:8px 0 0;">
                        Automatic from each employee's shift: an 8-hour weekday shift → half day at 4h or less; a 5-hour
                        Saturday shift → half day at 2h 30m or less. Saturday timings are set per shift under <strong>Shifts</strong>.
                        A check-in later than the minutes above (e.g. 120 = after 12:00 on a 10:00 shift) is a half day instead of a
                        late arrival, however long the employee stays. Leave it empty to turn this off.
                    </p>
                </div>
            </div>

            <div class="col-md-6">
                <div class="glass-card">
                    <div class="section-title"><i class="bi bi-calendar-week"></i> Working Days</div>

                    <input type="hidden" name="working_days" id="workingDaysInput" value="{{ $settings->working_days }}">
                    <div class="weekday-grid">
                        @php $activeDays = explode(',', $settings->working_days); @endphp
                        @foreach(['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $day)
                            <span class="day {{ in_array($day, $activeDays) ? '' : 'off' }}"
                                data-day="{{ $day }}">{{ strtoupper($day) }}</span>
                        @endforeach
                    </div>
                    <p style="font-size:12px; color:var(--text-muted); margin-top:6px;">Click on a day to toggle
                        working/non-working</p>
                </div>
            </div>
        </div>

        <!-- BIOMETRIC AUTO-SYNC                       -->
        <div class="row">
            <div class="col-12">
                <div class="glass-card">
                    <div class="section-title"><i class="bi bi-fingerprint"></i> Biometric auto-sync</div>

                    <div class="policy-row">
                        <span class="label">Scheduled Sync Time</span>
                        <span class="value"><input type="time" name="biometric_sync_time"
                                value="{{ \Carbon\Carbon::parse($settings->biometric_sync_time)->format('H:i') }}"></span>
                    </div>

                    <div class="toggle-wrapper">
                        <input type="checkbox" name="biometric_auto_sync_enabled" value="1" {{ $settings->biometric_auto_sync_enabled ? 'checked' : '' }}>
                        <span class="toggle-label">Automatically pull records from the attendance machine each day</span>
                    </div>
                    {{-- Submits the separate #syncNowForm below (a form cannot be nested inside the settings form) --}}
                    <button type="submit" form="syncNowForm" class="btn-primary-glass" style="margin-top:12px;">
                        <i class="bi bi-arrow-repeat"></i> Sync Now
                    </button>
                </div>
            </div>
        </div>


        <!-- LATE & HALF-DAY PENALTY                   -->

        <div class="row">
            <div class="col-12">
                <div class="glass-card">
                    <div class="section-title"><i class="bi bi-exclamation-triangle"></i> Late, Early-Out &amp; Half-Day Penalty Policy
                    </div>
                    <p class="section-sub">Casual-leave / salary deductions per cycle</p>

                    <div class="toggle-wrapper">
                        <input type="checkbox" name="penalty_policy_enabled" value="1" {{ $settings->penalty_policy_enabled ? 'checked' : '' }}>
                        <span class="toggle-label">Apply late-arrival, early-out and half-day penalties (deducts Casual Leave automatically)</span>
                    </div>

                    <div class="policy-row">
                        <span class="label">
                            Count Penalties From
                            <small style="display:block; font-weight:400; color:var(--text-muted);">
                                Late arrivals, early outs and half days before this date still show on attendance, but never
                                count toward a penalty. Leave empty to count every day.
                            </small>
                        </span>
                        <span class="value"><input type="date" name="penalty_policy_from"
                                value="{{ old('penalty_policy_from', $settings->penalty_policy_from ? \Carbon\Carbon::parse($settings->penalty_policy_from)->toDateString() : '') }}"></span>
                    </div>

                    <div class="policy-row">
                        <span class="label">Late Arrivals Per ½ Casual Leave (half day)</span>
                        <span class="value"><input type="number" name="late_arrivals_per_casual_leave"
                                value="{{ $settings->late_arrivals_per_casual_leave }}"></span>
                    </div>
                    <div class="policy-row">
                        <span class="label">Early Outs Per ½ Casual Leave (half day)</span>
                        <span class="value"><input type="number" name="early_outs_per_casual_leave" min="1"
                                value="{{ $settings->early_outs_per_casual_leave ?? 3 }}"></span>
                    </div>
                    <div class="policy-row">
                        <span class="label">Half-Days Per 1 Casual Leave</span>
                        <span class="value"><input type="number" name="half_days_per_casual_leave"
                                value="{{ $settings->half_days_per_casual_leave }}"></span>
                    </div>
                    <div class="policy-row">
                        <span class="label">Absent Days Per 1 Casual Leave
                            <small style="display:block; font-weight:400; color:var(--text-muted);">No punch and no leave on a working day. Empty = not deducted.</small>
                        </span>
                        <span class="value"><input type="number" name="absent_days_per_casual_leave" min="0" max="31" placeholder="off"
                                value="{{ $settings->absent_days_per_casual_leave ?? '' }}"></span>
                    </div>

                    <div class="info-box">
                        <i class="bi bi-info-circle"></i>
                        Every <strong>N</strong> late arrivals or early outs in a cycle (each counted separately) equal
                        <strong>½ Casual Leave (half day)</strong>, and every <strong>N</strong> half-days or absent days equal <strong>1 Casual Leave</strong>,
                        deducted automatically as soon as the limit is reached (e.g. 3 → the 3rd late arrival deducts a half day).
                        If the Casual Leave balance is insufficient, the shortfall is a salary-day deduction.
                        Employees may request a waiver for any item (Line Manager, then Admin); once approved it stops counting and the
                        deducted leave is returned. Days covered by approved leave are not counted. Shown in
                        <strong>Penalty &amp; Waivers</strong>.
                    </div>
                </div>
            </div>
        </div>

        <!-- LATE & EARLY-OUT ALERTS                   -->

        <div class="row">
            <div class="col-12">
                <div class="glass-card">
                    <div class="section-title"><i class="bi bi-bell"></i> Late &amp; Early-Out Alerts</div>
                    <p class="section-sub">Notify when an employee reaches a limit within the current cycle</p>

                    <div class="toggle-wrapper">
                        <input type="checkbox" name="attendance_alerts_enabled" value="1" {{ ($settings->attendance_alerts_enabled ?? 1) ? 'checked' : '' }}>
                        <span class="toggle-label">Send late-arrival and early-out alerts</span>
                    </div>

                    <div class="policy-row">
                        <span class="label">Alert After Late Arrivals</span>
                        <span class="value"><input type="number" name="late_alert_threshold" min="1"
                                value="{{ $settings->late_alert_threshold ?? 3 }}"></span>
                    </div>
                    <div class="policy-row">
                        <span class="label">Alert After Early Outs</span>
                        <span class="value"><input type="number" name="early_out_alert_threshold" min="1"
                                value="{{ $settings->early_out_alert_threshold ?? 3 }}"></span>
                    </div>

                    <div class="toggle-wrapper">
                        <input type="checkbox" name="alert_notify_admin" value="1" {{ ($settings->alert_notify_admin ?? 1) ? 'checked' : '' }}>
                        <span class="toggle-label">Notify Admin</span>
                    </div>
                    <div class="toggle-wrapper">
                        <input type="checkbox" name="alert_notify_line_manager" value="1" {{ ($settings->alert_notify_line_manager ?? 1) ? 'checked' : '' }}>
                        <span class="toggle-label">Notify the employee's Line Manager</span>
                    </div>
                    <div class="toggle-wrapper">
                        <input type="checkbox" name="alert_notify_employee" value="1" {{ ($settings->alert_notify_employee ?? 0) ? 'checked' : '' }}>
                        <span class="toggle-label">Notify the employee</span>
                    </div>

                    <div class="toggle-wrapper" style="margin-top:12px;">
                        <input type="checkbox" name="email_notifications_enabled" value="1" {{ ($settings->email_notifications_enabled ?? 1) ? 'checked' : '' }}>
                        <span class="toggle-label">Also send every notification by email (requests, approvals, rejections, alerts)</span>
                    </div>

                    <div class="info-box">
                        <i class="bi bi-info-circle"></i>
                        Late and early out are calculated automatically from each employee's <strong>shift</strong> and
                        <strong>grace policy</strong> on every biometric sync. When an employee reaches the limit
                        (e.g. 3 late arrivals) in the current cycle, an alert appears under the <i class="bi bi-bell"></i>
                        bell, and again at every further step (6, 9 …). Days covered by approved leave and approved late
                        waivers are not counted.
                    </div>
                </div>
            </div>
        </div>

        <!-- MISSING PUNCH                             -->

        <div class="row">
            <div class="col-12">
                <div class="glass-card">
                    <div class="section-title"><i class="bi bi-fingerprint"></i> Missing Punch (no check-out)</div>
                    <p class="section-sub">When an employee checks in but never checks out</p>

                    <div class="toggle-wrapper">
                        <input type="checkbox" name="missing_punch_enabled" value="1" {{ ($settings->missing_punch_enabled ?? 1) ? 'checked' : '' }}>
                        <span class="toggle-label">Apply the missing punch policy</span>
                    </div>

                    <div class="policy-row">
                        <span class="label">Days Allowed To Submit A Correction</span>
                        <span class="value"><input type="number" name="missing_punch_correction_days" min="0" max="31"
                                value="{{ $settings->missing_punch_correction_days ?? 3 }}"></span>
                    </div>
                    <div class="policy-row">
                        <span class="label">If Not Corrected In Time, Count The Day As</span>
                        <span class="value">
                            <select name="missing_punch_after_deadline" class="form-control-custom" style="min-width:180px;">
                                @php $afterDeadline = $settings->missing_punch_after_deadline ?? 'half_day'; @endphp
                                <option value="half_day" @selected($afterDeadline === 'half_day')>Half Day</option>
                                <option value="absent" @selected($afterDeadline === 'absent')>Absent</option>
                                <option value="flag" @selected($afterDeadline === 'flag')>Keep as Missing Punch (no deduction)</option>
                            </select>
                        </span>
                    </div>

                    <div class="toggle-wrapper">
                        <input type="checkbox" name="missing_punch_notify_manager" value="1" {{ ($settings->missing_punch_notify_manager ?? 1) ? 'checked' : '' }}>
                        <span class="toggle-label">Notify the employee's Line Manager / Manager</span>
                    </div>
                    <div class="toggle-wrapper">
                        <input type="checkbox" name="missing_punch_notify_admin" value="1" {{ ($settings->missing_punch_notify_admin ?? 0) ? 'checked' : '' }}>
                        <span class="toggle-label">Notify Admin</span>
                    </div>

                    <div class="info-box">
                        <i class="bi bi-info-circle"></i>
                        The day after a missing check-out, the employee is notified (bell + email) with a link to submit a
                        correction: <strong>Forgot to check out</strong> or <strong>Official duty</strong>. Once the correction
                        is approved, the attendance is updated with the corrected time. If nothing is submitted within the
                        allowed days, the day is counted as chosen above. Applies to days from
                        <strong>{{ \Carbon\Carbon::parse($settings->missing_punch_policy_from ?? now())->format('d M Y') }}</strong> onwards.
                    </div>
                </div>
            </div>
        </div>

        <!-- WORK FROM HOME                            -->

        <div class="row">
            <div class="col-12">
                <div class="glass-card">
                    <div class="section-title"><i class="bi bi-house-door"></i> Work From Home</div>
                    <p class="section-sub">Portal check-in / check-out on approved Work From Home days</p>

                    <div class="toggle-wrapper">
                        <input type="checkbox" name="wfh_enabled" value="1" {{ ($settings->wfh_enabled ?? 1) ? 'checked' : '' }}>
                        <span class="toggle-label">Allow Work From Home requests</span>
                    </div>

                    <div class="policy-row">
                        <span class="label">Max WFH Days Per Month (0 = no limit)</span>
                        <span class="value"><input type="number" name="wfh_max_days_per_month" min="0"
                                value="{{ $settings->wfh_max_days_per_month ?? 0 }}"></span>
                    </div>

                    <div class="info-box">
                        <i class="bi bi-info-circle"></i>
                        Employees request Work From Home (Line Manager, then Admin approves). On an approved day they
                        <strong>Check In</strong> and <strong>Check Out</strong> (with a work summary) from the portal.
                        Shift, late, early-out, half-day and penalty rules apply as in the office; no check-in counts as absent.
                    </div>
                </div>
            </div>
        </div>

        <!-- SAVE / RESET BUTTONS                      -->

        <div class="row">
            <div class="col-12">
                <div class="glass-card">
                    <div class="btn-group-actions">
                        <button type="button" class="btn-outline-glass" onclick="location.reload()"><i
                                class="bi bi-arrow-counterclockwise"></i> Reset to defaults</button>
                        <button type="submit" class="btn-primary-glass"><i class="bi bi-check2"></i> Save policy</button>
                    </div>

                    <div class="footer-note">
                        <i class="bi bi-info-circle"></i>
                        Working-hours, grace, classification, and sync settings apply when you press <strong>Save</strong>.
                        Holidays are saved as you add or remove them, and immediately excluded from attendance calculations.
                    </div>
                </div>
            </div>
        </div>

    </form>

    <form method="POST" action="{{ route('admin.biometric.sync-now') }}" id="syncNowForm" data-keep-enabled="1">
        @csrf
    </form>


    <!-- HOLIDAYS                                  -->

    <div class="row">
        <div class="col-12">
            <div class="glass-card">
                <div class="section-title"><i class="bi bi-calendar-event"></i> Holidays</div>
                <p class="section-sub">Gazetted &amp; unscheduled — excluded from working days</p>

                <!-- Add Holiday Form -->
                <form method="POST" action="{{ route('admin.holiday.store') }}" class="add-holiday-row">
                    @csrf
                    <div class="form-group">
                        <label>From Date</label>
                        <input type="date" name="from_date" class="form-control-custom" required>
                    </div>
                    <div class="form-group">
                        <label>To Date (Optional)</label>
                        <input type="date" name="to_date" class="form-control-custom">
                    </div>
                    <div class="form-group">
                        <label>Holiday Name</label>
                        <input type="text" name="name" class="form-control-custom" placeholder="e.g. Independence Day"
                            required>
                    </div>
                    <div class="form-group">
                        <label>Type</label>
                        <select name="type" class="form-control-custom">
                            <option>Gazetted</option>
                            <option>Unscheduled</option>
                        </select>
                    </div>
                    <div class="form-group" style="flex:0 0 auto;">
                        <label>&nbsp;</label>
                        <button type="submit" class="btn-primary-glass"><i class="bi bi-plus"></i> Add holiday</button>
                    </div>
                </form>

                <!-- Holiday Table -->
                <div class="table-wrap">
                    <table class="holiday-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Day</th>
                                <th>Holiday</th>
                                <th>Type</th>
                                <th style="text-align:right;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($holidays as $h)
                                <tr>
                                    <td>{{ \Carbon\Carbon::parse($h->from_date)->format('d-M-Y') }}</td>
                                    <td>{{ \Carbon\Carbon::parse($h->from_date)->format('D') }}</td>
                                    <td>{{ $h->name }}</td>
                                    <td><span class="type-{{ strtolower($h->type) }}">{{ $h->type }}</span></td>
                                    <td style="text-align:right;">
                                        <form method="POST" action="{{ route('admin.holiday.destroy', $h->id) }}"
                                            style="display:inline;" onsubmit="return confirm('Remove this holiday?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn-danger-glass"><i class="bi bi-trash"></i>
                                                Remove</button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" style="text-align:center; padding:20px;">No holidays added yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <!-- DEPARTMENTS -->
    <div class="row">
        <div class="col-12">
            <div class="glass-card">
                <div class="section-title"><i class="bi bi-diagram-3"></i> Departments</div>
                <p class="section-sub">Used on employee profiles, reports and filters. A department with employees cannot be removed.</p>

                <form method="POST" action="{{ route('admin.department.store') }}" class="add-holiday-row">
                    @csrf
                    <div class="form-group">
                        <label>Department Name</label>
                        <input type="text" name="department_name" class="form-control-custom" placeholder="e.g. Customer Support"
                            value="{{ old('department_name') }}" maxlength="100" required>
                    </div>
                    <div class="form-group" style="flex:0 0 auto;">
                        <label>&nbsp;</label>
                        <button type="submit" class="btn-primary-glass"><i class="bi bi-plus"></i> Add department</button>
                    </div>
                </form>

                <div class="table-wrap">
                    <table class="holiday-table">
                        <thead>
                            <tr>
                                <th>Department</th>
                                <th>Employees</th>
                                <th style="text-align:right;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($departments as $department)
                                <tr>
                                    <td>{{ $department->name }}</td>
                                    <td>{{ $department->employees_count }}</td>
                                    <td style="text-align:right;">
                                        @if ($department->employees_count > 0)
                                            <span style="font-size:12px; color:var(--text-muted);" title="Move its employees to another department first">
                                                <i class="bi bi-lock"></i> In use
                                            </span>
                                        @else
                                            <form method="POST" action="{{ route('admin.department.destroy', $department->id) }}"
                                                style="display:inline;" onsubmit="return confirm('Remove this department?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn-danger-glass"><i class="bi bi-trash"></i> Remove</button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" style="text-align:center; padding:20px;">No departments added yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- SHIFTS -->
    <div class="row">
        <div class="col-12">
            <div class="glass-card">
                <div class="section-title"><i class="bi bi-clock-history"></i> Shifts</div>
                <p class="section-sub">Assignable working-hour shifts (per employee)</p>

                <div class="table-wrap">
                    <table class="grace-policy-table">
                        <thead>
                            <tr>
                                <th>Shift Name</th>
                                <th>Start</th>
                                <th>End</th>
                                <th>Saturday</th>
                                <th>Status</th>
                                <th style="text-align:right;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($shifts as $s)
                                <tr>
                                    <td><strong>{{ $s->name }}</strong></td>
                                    <td>{{ \Carbon\Carbon::parse($s->start_time)->format('h:i A') }}</td>
                                    <td>{{ \Carbon\Carbon::parse($s->end_time)->format('h:i A') }}</td>
                                    <td>
                                        @if ($s->hasSaturdayTimings())
                                            {{ \Carbon\Carbon::parse($s->saturday_start_time)->format('h:i A') }} –
                                            {{ \Carbon\Carbon::parse($s->saturday_end_time)->format('h:i A') }}
                                        @else
                                            <span style="color:var(--text-muted);">Same as weekdays</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($s->is_active)
                                            <span class="badge-open"><i class="bi bi-check-circle"></i> Active</span>
                                        @else
                                            <span class="badge-locked"><i class="bi bi-x-circle"></i> Inactive</span>
                                        @endif
                                    </td>
                                    <td style="text-align:right;">
                                        <button type="button" class="btn-outline-glass" style="padding:2px 12px;font-size:11px;"
                                            onclick="openEditShift({{ $s->id }}, '{{ addslashes($s->name) }}', '{{ \Carbon\Carbon::parse($s->start_time)->format('H:i') }}', '{{ \Carbon\Carbon::parse($s->end_time)->format('H:i') }}', '{{ $s->saturday_start_time ? \Carbon\Carbon::parse($s->saturday_start_time)->format('H:i') : '' }}', '{{ $s->saturday_end_time ? \Carbon\Carbon::parse($s->saturday_end_time)->format('H:i') : '' }}')">
                                            <i class="bi bi-pencil"></i> Edit
                                        </button>
                                        <form method="POST" action="{{ route('admin.shift.toggle', $s->id) }}"
                                            style="display:inline;">
                                            @csrf
                                            <button type="submit"
                                                class="{{ $s->is_active ? 'btn-warning-glass' : 'btn-success-glass' }}"
                                                style="padding:2px 12px;font-size:11px;">
                                                {{ $s->is_active ? 'Deactivate' : 'Activate' }}
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <p style="font-size:13px; color:var(--text-muted); margin-top:8px;">Only <strong>Active</strong> shifts
                    appear in the Employee form dropdown.</p>

                <button class="btn-outline-glass" style="margin-top:8px;" data-bs-toggle="modal"
                    data-bs-target="#addShiftModal">
                    <i class="bi bi-plus"></i> Add shift
                </button>
            </div>
        </div>
    </div>

    <!-- ADD SHIFT MODAL -->
    <div class="modal fade modal-glass" id="addShiftModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="POST" action="{{ route('admin.shift.store') }}">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="bi bi-plus-circle" style="color:var(--primary);"></i> Add shift
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="form-group" style="margin-bottom:16px;">
                            <label
                                style="display:block;font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:0.5px;color:var(--text-muted);margin-bottom:4px;">Shift
                                Name</label>
                            <input type="text" name="name" class="form-control-custom" placeholder="e.g. Evening Shift"
                                required>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group" style="margin-bottom:16px;">
                                    <label
                                        style="display:block;font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:0.5px;color:var(--text-muted);margin-bottom:4px;">Start
                                        Time</label>
                                    <input type="time" name="start_time" class="form-control-custom" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group" style="margin-bottom:16px;">
                                    <label
                                        style="display:block;font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:0.5px;color:var(--text-muted);margin-bottom:4px;">End
                                        Time</label>
                                    <input type="time" name="end_time" class="form-control-custom" required>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group" style="margin-bottom:16px;">
                                    <label style="display:block;font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:0.5px;color:var(--text-muted);margin-bottom:4px;">Saturday Start</label>
                                    <input type="time" name="saturday_start_time" class="form-control-custom">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group" style="margin-bottom:16px;">
                                    <label style="display:block;font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:0.5px;color:var(--text-muted);margin-bottom:4px;">Saturday End</label>
                                    <input type="time" name="saturday_end_time" class="form-control-custom">
                                </div>
                            </div>
                        </div>
                        <p style="font-size:12px; color:var(--text-muted); margin:0;">
                            Optional. Leave empty if Saturday uses the normal timings (e.g. Saturday half day 09:00 – 14:00).
                        </p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn-outline-glass" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn-primary-glass"><i class="bi bi-check2"></i> Add shift</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- EDIT SHIFT MODAL -->
    <div class="modal fade modal-glass" id="editShiftModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="POST" id="editShiftForm" action="">
                    @csrf
                    @method('PUT')
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="bi bi-pencil" style="color:var(--primary);"></i> Edit shift</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="form-group" style="margin-bottom:16px;">
                            <label
                                style="display:block;font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:0.5px;color:var(--text-muted);margin-bottom:4px;">Shift
                                Name</label>
                            <input type="text" name="name" id="editShiftName" class="form-control-custom" required>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group" style="margin-bottom:16px;">
                                    <label
                                        style="display:block;font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:0.5px;color:var(--text-muted);margin-bottom:4px;">Start
                                        Time</label>
                                    <input type="time" name="start_time" id="editShiftStart" class="form-control-custom"
                                        required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group" style="margin-bottom:16px;">
                                    <label
                                        style="display:block;font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:0.5px;color:var(--text-muted);margin-bottom:4px;">End
                                        Time</label>
                                    <input type="time" name="end_time" id="editShiftEnd" class="form-control-custom"
                                        required>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group" style="margin-bottom:16px;">
                                    <label style="display:block;font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:0.5px;color:var(--text-muted);margin-bottom:4px;">Saturday Start</label>
                                    <input type="time" name="saturday_start_time" id="editShiftSatStart" class="form-control-custom">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group" style="margin-bottom:16px;">
                                    <label style="display:block;font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:0.5px;color:var(--text-muted);margin-bottom:4px;">Saturday End</label>
                                    <input type="time" name="saturday_end_time" id="editShiftSatEnd" class="form-control-custom">
                                </div>
                            </div>
                        </div>
                        <p style="font-size:12px; color:var(--text-muted); margin:0;">
                            Optional. Leave empty if Saturday uses the normal timings (e.g. Saturday half day 09:00 – 14:00).
                        </p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn-outline-glass" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn-primary-glass"><i class="bi bi-check2"></i> Update shift</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- GRACE TIME POLICIES                       -->
    <div class="row">
        <div class="col-12">
            <div class="glass-card">
                <div class="section-title"><i class="bi bi-clock-history"></i> Grace time policies</div>
                <p class="section-sub">Assignable grace allowances (per employee)</p>

                <div class="table-wrap">
                    <table class="grace-policy-table">
                        <thead>
                            <tr>
                                <th>Policy</th>
                                <th>Late Grace</th>
                                <th>Early Grace</th>
                                <th>Employees</th>
                                <th style="text-align:right;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($gracePolicies as $gp)
                                <tr>
                                    <td><strong>{{ $gp->name }}</strong> @if($gp->is_default)<span
                                    class="policy-default">Default</span>@endif</td>
                                    <td>{{ $gp->late_grace_minutes }} min</td>
                                    <td>{{ $gp->early_grace_minutes }} min</td>
                                    <td>{{ $gp->employees_count }}</td>
                                    <td style="text-align:right;">
                                        <button type="button" class="btn-outline-glass" style="padding:2px 12px;font-size:11px;"
                                            onclick="openEditGrace({{ $gp->id }}, '{{ addslashes($gp->name) }}', {{ $gp->late_grace_minutes }}, {{ $gp->early_grace_minutes }})">
                                            <i class="bi bi-pencil"></i> Edit
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <p style="font-size:13px; color:var(--text-muted); margin-top:8px;">The <strong>Standard Grace</strong>
                    values come from the Grace time card above. Assign a policy (or <strong>No grace</strong>) to each
                    person in <strong>Employees</strong>. No-grace employees are evaluated strictly against their shift.</p>

                <button class="btn-outline-glass" style="margin-top:8px;" data-bs-toggle="modal"
                    data-bs-target="#addGracePolicyModal">
                    <i class="bi bi-plus"></i> Add grace policy
                </button>
            </div>
        </div>
    </div>


    <!-- MONTHLY PERIOD LOCK                       -->

    <div class="row">
        <div class="col-12">
            <div class="glass-card">
                <div class="section-title"><i class="bi bi-lock"></i> Monthly period lock</div>
                <p class="section-sub">Finalize a month to prevent further edits</p>

                <div class="table-wrap">
                    <table class="lock-table">
                        <thead>
                            <tr>
                                <th>Month</th>
                                <th>Status</th>
                                <th style="text-align:right;">Action</th>
                            </tr>
                        </thead>
                        <tbody id="lockTableBody">
                            @forelse($monthlyLocks as $lock)
                                <tr>
                                    <td>{{ \Carbon\Carbon::createFromFormat('Y-m', $lock->month_year)->format('M Y') }}</td>
                                    <td>
                                        @if($lock->is_locked)
                                            <span class="badge-locked"><i class="bi bi-lock"></i> Locked</span>
                                        @else
                                            <span class="badge-open"><i class="bi bi-unlock"></i> Open</span>
                                        @endif
                                    </td>
                                    <td style="text-align:right;">
                                        <form method="POST" action="{{ route('admin.lock.toggle') }}" style="display:inline;">
                                            @csrf
                                            <input type="hidden" name="month_year" value="{{ $lock->month_year }}">
                                            <button type="submit"
                                                class="{{ $lock->is_locked ? 'btn-success-glass' : 'btn-warning-glass' }} lock-btn">
                                                <i class="bi bi-{{ $lock->is_locked ? 'unlock' : 'lock' }}"></i>
                                                {{ $lock->is_locked ? 'Unlock' : 'Lock month' }}
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" style="text-align:center; padding:20px;">No Month record Found</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <form method="POST" action="{{ route('admin.lock.toggle') }}"
                    style="display:flex; gap:8px; align-items:flex-end; flex-wrap:wrap; margin-top:12px;">
                    @csrf
                    <div>
                        <label style="display:block; font-size:12px; font-weight:600; color:var(--text-muted); margin-bottom:4px;">Lock a month</label>
                        <input type="month" name="month_year" class="form-control-custom" max="{{ now()->format('Y-m') }}"
                            value="{{ now()->subMonth()->format('Y-m') }}" required>
                    </div>
                    <button type="submit" class="btn-warning-glass lock-btn"
                        onclick="return confirm('Lock this month? Its attendance, leave, corrections and waivers can no longer be changed until you unlock it.');">
                        <i class="bi bi-lock"></i> Lock month
                    </button>
                </form>

                <div class="info-box" style="margin-top:8px;">
                    <i class="bi bi-info-circle"></i>
                    Lock a month once its payroll is final. For a locked month nobody can submit or approve leave,
                    corrections, deduction waivers or Work From Home, and machine sync, penalty leaves and the missing
                    punch policy no longer change it. Reports and exports still work. Unlock it here if a change is really needed.
                </div>
            </div>
        </div>
    </div>


    <!-- ADD GRACE POLICY MODAL                    -->

    <div class="modal fade modal-glass" id="addGracePolicyModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="POST" action="{{ route('admin.grace-policy.store') }}">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="bi bi-plus-circle" style="color:var(--primary);"></i> Add grace
                            time
                            policy</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="form-group" style="margin-bottom:16px;">
                            <label
                                style="display:block;font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:0.5px;color:var(--text-muted);margin-bottom:4px;">Policy
                                Name</label>
                            <input type="text" name="name" class="form-control-custom" placeholder="e.g. Extended Grace"
                                required>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group" style="margin-bottom:16px;">
                                    <label
                                        style="display:block;font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:0.5px;color:var(--text-muted);margin-bottom:4px;">Late
                                        Arrival Grace (Minutes)</label>
                                    <input type="number" name="late_grace_minutes" class="form-control-custom" value="30"
                                        required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group" style="margin-bottom:16px;">
                                    <label
                                        style="display:block;font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:0.5px;color:var(--text-muted);margin-bottom:4px;">Early
                                        Departure Grace (Minutes)</label>
                                    <input type="number" name="early_grace_minutes" class="form-control-custom" value="30"
                                        required>
                                </div>
                            </div>
                        </div>
                        <div class="info-box" style="margin-top:8px;">
                            <i class="bi bi-info-circle"></i>
                            Assign this policy to employees in the <strong>Employees</strong> screen. It applies only when
                            they arrive late or leave early, up to the limits above.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn-outline-glass" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn-primary-glass"><i class="bi bi-check2"></i> Add policy</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- EDIT GRACE POLICY MODAL                   -->
    <div class="modal fade modal-glass" id="editGracePolicyModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="POST" id="editGraceForm" action="">
                    @csrf
                    @method('PUT')
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="bi bi-pencil" style="color:var(--primary);"></i> Edit grace time
                            policy</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="form-group" style="margin-bottom:16px;">
                            <label
                                style="display:block;font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:0.5px;color:var(--text-muted);margin-bottom:4px;">Policy
                                Name</label>
                            <input type="text" name="name" id="editGraceName" class="form-control-custom" required>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group" style="margin-bottom:16px;">
                                    <label
                                        style="display:block;font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:0.5px;color:var(--text-muted);margin-bottom:4px;">Late
                                        Arrival Grace (Minutes)</label>
                                    <input type="number" name="late_grace_minutes" id="editGraceLate"
                                        class="form-control-custom" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group" style="margin-bottom:16px;">
                                    <label
                                        style="display:block;font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:0.5px;color:var(--text-muted);margin-bottom:4px;">Early
                                        Departure Grace (Minutes)</label>
                                    <input type="number" name="early_grace_minutes" id="editGraceEarly"
                                        class="form-control-custom" required>
                                </div>
                            </div>
                        </div>
                        <div class="info-box" style="margin-top:8px;">
                            <i class="bi bi-info-circle"></i>
                            This policy applies only when employees arrive late or leave early, up to the limits above.
                            Changes apply immediately.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn-outline-glass" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn-primary-glass"><i class="bi bi-check2"></i> Update policy</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {

            // ===== POLICY LOCKED: nothing on the page can be changed (the server refuses it too) =====
            @if ($policyLock['supported'] && ! $policyLock['unlocked'])
                document.querySelectorAll('form:not([data-keep-enabled])').forEach(form => {
                    form.querySelectorAll('input, select, textarea, button').forEach(el => el.disabled = true);
                });
                document.querySelectorAll('button[onclick^="openEdit"], .weekday-grid .day').forEach(el => {
                    el.style.pointerEvents = 'none';
                    el.style.opacity = '0.6';
                });
            @endif

            // ===== TOGGLE WORKING DAYS =====
            document.querySelectorAll('.weekday-grid .day').forEach(day => {
                day.addEventListener('click', function () {
                    this.classList.toggle('off');
                    const activeDays = Array.from(document.querySelectorAll('.weekday-grid .day:not(.off)')).map(d => d.dataset.day);
                    document.getElementById('workingDaysInput').value = activeDays.join(',');
                });
            });

        });

        // ===== EDIT GRACE POLICY MODAL =====
        function openEditGrace(id, name, late, early) {
            document.getElementById('editGraceForm').action = `/admin/attendance-policy/grace-policy/${id}`;
            document.getElementById('editGraceName').value = name;
            document.getElementById('editGraceLate').value = late;
            document.getElementById('editGraceEarly').value = early;
            new bootstrap.Modal(document.getElementById('editGracePolicyModal')).show();
        }

        function openEditShift(id, name, start, end, satStart, satEnd) {
            document.getElementById('editShiftForm').action = `/admin/attendance-policy/shift/${id}`;
            document.getElementById('editShiftName').value = name;
            document.getElementById('editShiftStart').value = start;
            document.getElementById('editShiftEnd').value = end;
            document.getElementById('editShiftSatStart').value = satStart || '';
            document.getElementById('editShiftSatEnd').value = satEnd || '';
            new bootstrap.Modal(document.getElementById('editShiftModal')).show();
        }
    </script>
@endpush