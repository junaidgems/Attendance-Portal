@extends('attendence.layouts.app')

@section('title', 'Attendance Policy')
@section('page-title', 'Attendance Policy')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/attendance-policy.css') }}">
@endpush

@section('content')


    <!-- ATTENDANCE CYCLE                          -->

    <div class="row">
        <div class="col-12">
            <div class="glass-card">
                <div class="section-title"><i class="bi bi-calendar3"></i> Attendance Cycle</div>
                <p class="section-sub">Monthly cycle used across all attendance &amp; leave</p>

                <div class="policy-row">
                    <span class="label">Cycle Start Day</span>
                    <span class="value"><input type="number" value="12"></span>
                </div>
                <div class="policy-row">
                    <span class="label">Cycle End Day</span>
                    <span class="value"><input type="number" value="1"></span>
                </div>

                <div class="cycle-info">
                    <div class="cycle-item"><span class="label">Current</span> <span class="value">12 Jul – 1 Aug
                            2026</span></div>
                    <div class="cycle-item"><span class="label">Next</span> <span class="value">12 Aug – 1 Sep 2026</span>
                    </div>
                </div>

                <div class="info-box">
                    <i class="bi bi-info-circle"></i>
                    Every attendance and leave dashboard, summary and report uses this cycle instead of the calendar month,
                    and it repeats automatically each month. Changes apply to the current and upcoming cycles — historical
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
                    <span class="value"><input type="time" value="09:00"></span>
                    <span class="hint">am</span>
                </div>
                <div class="policy-row">
                    <span class="label">Standard Shift End</span>
                    <span class="value"><input type="time" value="17:00"></span>
                    <span class="hint">pm</span>
                </div>
                <div class="policy-row">
                    <span class="label">Unpaid Break Deducted (Minutes)</span>
                    <span class="value"><input type="number" value="60"></span>
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
                    <span class="value"><input type="number" value="15"></span>
                </div>
                <div class="policy-row">
                    <span class="label">Early Departure Grace (Minutes)</span>
                    <span class="value"><input type="number" value="10"></span>
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

                <div class="policy-row">
                    <span class="label">Half-Day If Net Worked ≤ (Minutes)</span>
                    <span class="value"><input type="number" value="270"></span>
                </div>
                <div class="policy-row">
                    <span class="label">Full-Day If Net Worked ≥ (Minutes)</span>
                    <span class="value"><input type="number" value="420"></span>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="glass-card">
                <div class="section-title"><i class="bi bi-calendar-week"></i> Working Days</div>

                <div class="weekday-grid">
                    <span class="day">SUN</span>
                    <span class="day">MON</span>
                    <span class="day">TUE</span>
                    <span class="day">WED</span>
                    <span class="day">THU</span>
                    <span class="day">FRI</span>
                    <span class="day off">SAT</span>
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
                    <span class="value"><input type="time" value="18:30"></span>
                    <span class="hint">pm</span>
                </div>

                <div class="toggle-wrapper">
                    <input type="checkbox" checked>
                    <span class="toggle-label">Automatically pull records from the attendance machine each day</span>
                </div>
            </div>
        </div>
    </div>


    <!-- HOLIDAYS                                  -->

    <div class="row">
        <div class="col-12">
            <div class="glass-card">
                <div class="section-title"><i class="bi bi-calendar-event"></i> Holidays</div>
                <p class="section-sub">Gazetted &amp; unscheduled — excluded from working days</p>

                <!-- Add Holiday Form -->
                <div class="add-holiday-row">
                    <div class="form-group">
                        <label>From Date</label>
                        <input type="date" class="form-control-custom" value="2026-07-15">
                    </div>
                    <div class="form-group">
                        <label>To Date (Optional)</label>
                        <input type="date" class="form-control-custom" value="2026-07-21">
                    </div>
                    <div class="form-group">
                        <label>Holiday Name</label>
                        <input type="text" class="form-control-custom" placeholder="e.g. Independence Day">
                    </div>
                    <div class="form-group">
                        <label>Type</label>
                        <select class="form-control-custom">
                            <option>Gazetted</option>
                            <option>Unscheduled</option>
                        </select>
                    </div>
                    <div class="form-group" style="flex:0 0 auto;">
                        <label>&nbsp;</label>
                        <button class="btn-primary-glass" onclick="toastr.success('Holiday added successfully!')"><i
                                class="bi bi-plus"></i> Add holiday</button>
                    </div>
                </div>

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
                            <tr>
                                <td>15-Jul-2026</td>
                                <td>Wed</td>
                                <td>Eid</td>
                                <td><span class="type-unscheduled">Unscheduled</span></td>
                                <td style="text-align:right;"><button class="btn-danger-glass"
                                        onclick="toastr.warning('Holiday removed!')"><i class="bi bi-trash"></i>
                                        Remove</button></td>
                            </tr>
                            <tr>
                                <td>16-Jul-2026</td>
                                <td>Thu</td>
                                <td>Eid</td>
                                <td><span class="type-unscheduled">Unscheduled</span></td>
                                <td style="text-align:right;"><button class="btn-danger-glass"
                                        onclick="toastr.warning('Holiday removed!')"><i class="bi bi-trash"></i>
                                        Remove</button></td>
                            </tr>
                            <tr>
                                <td>17-Jul-2026</td>
                                <td>Fri</td>
                                <td>Eid</td>
                                <td><span class="type-unscheduled">Unscheduled</span></td>
                                <td style="text-align:right;"><button class="btn-danger-glass"
                                        onclick="toastr.warning('Holiday removed!')"><i class="bi bi-trash"></i>
                                        Remove</button></td>
                            </tr>
                            <tr>
                                <td>18-Jul-2026</td>
                                <td>Sat</td>
                                <td>Eid</td>
                                <td><span class="type-unscheduled">Unscheduled</span></td>
                                <td style="text-align:right;"><button class="btn-danger-glass"
                                        onclick="toastr.warning('Holiday removed!')"><i class="bi bi-trash"></i>
                                        Remove</button></td>
                            </tr>
                            <tr>
                                <td>19-Jul-2026</td>
                                <td>Sun</td>
                                <td>Eid</td>
                                <td><span class="type-unscheduled">Unscheduled</span></td>
                                <td style="text-align:right;"><button class="btn-danger-glass"
                                        onclick="toastr.warning('Holiday removed!')"><i class="bi bi-trash"></i>
                                        Remove</button></td>
                            </tr>
                            <tr>
                                <td>20-Jul-2026</td>
                                <td>Mon</td>
                                <td>Eid</td>
                                <td><span class="type-unscheduled">Unscheduled</span></td>
                                <td style="text-align:right;"><button class="btn-danger-glass"
                                        onclick="toastr.warning('Holiday removed!')"><i class="bi bi-trash"></i>
                                        Remove</button></td>
                            </tr>
                            <tr>
                                <td>21-Jul-2026</td>
                                <td>Tue</td>
                                <td>Eid</td>
                                <td><span class="type-unscheduled">Unscheduled</span></td>
                                <td style="text-align:right;"><button class="btn-danger-glass"
                                        onclick="toastr.warning('Holiday removed!')"><i class="bi bi-trash"></i>
                                        Remove</button></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
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
                            <tr>
                                <td><strong>Standard Grace</strong> <span class="policy-default">Default</span></td>
                                <td>15 min</td>
                                <td>10 min</td>
                                <td>1</td>
                                <td style="text-align:right;">
                                    <button class="btn-outline-glass" style="padding:2px 12px;font-size:11px;"
                                        data-bs-toggle="modal" data-bs-target="#editGracePolicyModal">
                                        <i class="bi bi-pencil"></i> Edit
                                    </button>
                                </td>
                            </tr>
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


    <!-- LATE & HALF-DAY PENALTY                   -->

    <div class="row">
        <div class="col-12">
            <div class="glass-card">
                <div class="section-title"><i class="bi bi-exclamation-triangle"></i> Late &amp; Half-Day Penalty Policy
                </div>
                <p class="section-sub">Casual-leave / salary deductions per cycle</p>

                <div class="toggle-wrapper">
                    <input type="checkbox" checked>
                    <span class="toggle-label">Apply late-arrival and half-day penalties</span>
                </div>

                <div class="policy-row">
                    <span class="label">Late Arrivals Per 1 Casual Leave</span>
                    <span class="value"><input type="number" value="3"></span>
                </div>
                <div class="policy-row">
                    <span class="label">Half-Days Per 1 Casual Leave</span>
                    <span class="value"><input type="number" value="2"></span>
                </div>

                <div class="info-box">
                    <i class="bi bi-info-circle"></i>
                    Every <strong>N</strong> late arrivals in a cycle equal 1 Casual Leave (the first <strong>N-1</strong>
                    carry no penalty — e.g. 3 — first 2 free). Every <strong>M</strong> half-days equal 1 Casual Leave. If
                    the Casual Leave balance is insufficient, the shortfall is a salary-day deduction. Employees may request
                    a waiver for any late arrival or half-day (reviewed by their line manager); approved items are removed
                    from the count. Shown in <strong>Penalty &amp; Waivers</strong>. Saved immediately.
                </div>
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
                            <tr>
                                <td>Jul 2026</td>
                                <td><span class="badge-open"><i class="bi bi-unlock"></i> Open</span></td>
                                <td style="text-align:right;">
                                    <button class="btn-warning-glass lock-btn" onclick="toggleLock(this)">
                                        <i class="bi bi-lock"></i> Lock month
                                    </button>
                                </td>
                            </tr>
                            <tr>
                                <td>Jun 2026</td>
                                <td><span class="badge-open"><i class="bi bi-unlock"></i> Open</span></td>
                                <td style="text-align:right;">
                                    <button class="btn-warning-glass lock-btn" onclick="toggleLock(this)">
                                        <i class="bi bi-lock"></i> Lock month
                                    </button>
                                </td>
                            </tr>
                            <tr>
                                <td>May 2026</td>
                                <td><span class="badge-locked"><i class="bi bi-lock"></i> Locked</span></td>
                                <td style="text-align:right;">
                                    <button class="btn-success-glass lock-btn" onclick="toggleLock(this)">
                                        <i class="bi bi-unlock"></i> Unlock
                                    </button>
                                </td>
                            </tr>
                            <tr>
                                <td>Apr 2026</td>
                                <td><span class="badge-open"><i class="bi bi-unlock"></i> Open</span></td>
                                <td style="text-align:right;">
                                    <button class="btn-warning-glass lock-btn" onclick="toggleLock(this)">
                                        <i class="bi bi-lock"></i> Lock month
                                    </button>
                                </td>
                            </tr>
                            <tr>
                                <td>Mar 2026</td>
                                <td><span class="badge-open"><i class="bi bi-unlock"></i> Open</span></td>
                                <td style="text-align:right;">
                                    <button class="btn-warning-glass lock-btn" onclick="toggleLock(this)">
                                        <i class="bi bi-lock"></i> Lock month
                                    </button>
                                </td>
                            </tr>
                            <tr>
                                <td>Feb 2026</td>
                                <td><span class="badge-open"><i class="bi bi-unlock"></i> Open</span></td>
                                <td style="text-align:right;">
                                    <button class="btn-warning-glass lock-btn" onclick="toggleLock(this)">
                                        <i class="bi bi-lock"></i> Lock month
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="info-box" style="margin-top:8px;">
                    <i class="bi bi-info-circle"></i>
                    Locking a month blocks punches, manual edits, corrections and leave changes for that period.
                </div>
            </div>
        </div>
    </div>

    <!-- SAVE / RESET BUTTONS                      -->

    <div class="row">
        <div class="col-12">
            <div class="glass-card">
                <div class="btn-group-actions">
                    <button class="btn-outline-glass" onclick="toastr.warning('Settings reset to defaults!')"><i
                            class="bi bi-arrow-counterclockwise"></i> Reset to defaults</button>
                    <button class="btn-primary-glass" onclick="toastr.success('Policy saved successfully!')"><i
                            class="bi bi-check2"></i> Save policy</button>
                </div>

                <div class="footer-note">
                    <i class="bi bi-info-circle"></i>
                    Working-hours, grace, classification, and sync settings apply when you press <strong>Save</strong>.
                    Holidays are saved as you add or remove them, and immediately excluded from attendance calculations.
                </div>
            </div>
        </div>
    </div>


    <!-- ADD GRACE POLICY MODAL                    -->

    <div class="modal fade modal-glass" id="addGracePolicyModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-plus-circle" style="color:var(--primary);"></i> Add grace time
                        policy</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form>
                        <div class="form-group" style="margin-bottom:16px;">
                            <label
                                style="display:block;font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:0.5px;color:var(--text-muted);margin-bottom:4px;">Policy
                                Name</label>
                            <input type="text" class="form-control-custom" placeholder="e.g. Extended Grace">
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group" style="margin-bottom:16px;">
                                    <label
                                        style="display:block;font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:0.5px;color:var(--text-muted);margin-bottom:4px;">Late
                                        Arrival Grace (Minutes)</label>
                                    <input type="number" class="form-control-custom" value="30">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group" style="margin-bottom:16px;">
                                    <label
                                        style="display:block;font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:0.5px;color:var(--text-muted);margin-bottom:4px;">Early
                                        Departure Grace (Minutes)</label>
                                    <input type="number" class="form-control-custom" value="30">
                                </div>
                            </div>
                        </div>
                        <div class="info-box" style="margin-top:8px;">
                            <i class="bi bi-info-circle"></i>
                            Assign this policy to employees in the <strong>Employees</strong> screen. It applies only when
                            they arrive late or leave early, up to the limits above.
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-outline-glass" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn-primary-glass"
                        onclick="toastr.success('Grace policy added successfully!'); bootstrap.Modal.getInstance(document.getElementById('addGracePolicyModal')).hide();"><i
                            class="bi bi-check2"></i> Add policy</button>
                </div>
            </div>
        </div>
    </div>

    <!-- EDIT GRACE POLICY MODAL                   -->
    <div class="modal fade modal-glass" id="editGracePolicyModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-pencil" style="color:var(--primary);"></i> Edit grace time
                        policy</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form>
                        <div class="form-group" style="margin-bottom:16px;">
                            <label
                                style="display:block;font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:0.5px;color:var(--text-muted);margin-bottom:4px;">Policy
                                Name</label>
                            <input type="text" class="form-control-custom" value="Standard Grace">
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group" style="margin-bottom:16px;">
                                    <label
                                        style="display:block;font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:0.5px;color:var(--text-muted);margin-bottom:4px;">Late
                                        Arrival Grace (Minutes)</label>
                                    <input type="number" class="form-control-custom" value="15">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group" style="margin-bottom:16px;">
                                    <label
                                        style="display:block;font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:0.5px;color:var(--text-muted);margin-bottom:4px;">Early
                                        Departure Grace (Minutes)</label>
                                    <input type="number" class="form-control-custom" value="10">
                                </div>
                            </div>
                        </div>
                        <div class="info-box" style="margin-top:8px;">
                            <i class="bi bi-info-circle"></i>
                            This policy applies only when employees arrive late or leave early, up to the limits above.
                            Changes apply immediately.
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-outline-glass" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn-primary-glass"
                        onclick="toastr.success('Grace policy updated successfully!'); bootstrap.Modal.getInstance(document.getElementById('editGracePolicyModal')).hide();"><i
                            class="bi bi-check2"></i> Update policy</button>
                </div>
            </div>
        </div>
    </div>

@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {

            // ===== TOGGLE DAYS =====
            document.querySelectorAll('.weekday-grid .day').forEach(day => {
                day.addEventListener('click', function () {
                    this.classList.toggle('off');
                    if (this.classList.contains('off')) {
                        toastr.info(this.textContent + ' marked as non-working day');
                    } else {
                        toastr.info(this.textContent + ' marked as working day');
                    }
                });
            });

            // ===== TOGGLES =====
            document.querySelectorAll('.toggle-wrapper input[type="checkbox"]').forEach(function (toggle) {
                toggle.addEventListener('change', function () {
                    if (this.checked) {
                        toastr.success('Enabled');
                    } else {
                        toastr.warning('Disabled');
                    }
                });
            });

            // ===== INPUT CHANGE =====
            document.querySelectorAll('.policy-row input').forEach(function (input) {
                input.addEventListener('change', function () {
                    toastr.info('Updated to: ' + this.value);
                });
            });

        });

function toggleLock(btn) {
    const row = btn.closest('tr');
    const statusCell = row.querySelector('td:nth-child(2)');
    const isLocked = statusCell.querySelector('.badge-locked');

    if (isLocked) {
        // ✅ Unlock
        statusCell.innerHTML = '<span class="badge-open"><i class="bi bi-unlock"></i> Open</span>';
        btn.className = 'btn-warning-glass lock-btn';
        btn.innerHTML = '<i class="bi bi-lock"></i> Lock month';
        toastr.success('Month unlocked!');
    } else {
        // 🔒 Lock
        statusCell.innerHTML = '<span class="badge-locked"><i class="bi bi-lock"></i> Locked</span>';
        btn.className = 'btn-success-glass lock-btn';
        btn.innerHTML = '<i class="bi bi-unlock"></i> Unlock';
        toastr.warning('Month locked!');
    }
}

    </script>
@endpush