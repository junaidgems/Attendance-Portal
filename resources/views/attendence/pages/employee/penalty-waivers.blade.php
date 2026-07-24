@extends('attendence.layouts.app')

@section('title', 'Penalty & Waivers')
@section('page-title', 'Penalty & Waivers')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/employee/penaltyWave.css') }}">
@endpush

@section('content')

<!-- ===== ATTENDANCE CYCLE HEADER WITH SELECTOR ===== -->
<div class="glass-card">
    <div class="cycle-header">
        <div>
            <h4>Attendance Cycle</h4>
            <span class="cycle-date" id="cycleDateDisplay">26 Jun – 25 Jul 2026</span>
        </div>
        <div class="cycle-selector">
            <label style="font-size:12px; color:var(--text-muted); font-weight:600;">Select Cycle</label>
            <select id="cycleSelector" onchange="updateCycle()">
                <option value="0" selected>26 Jun – 25 Jul 2026</option>
                <option value="1">26 May – 25 Jun 2026</option>
                <option value="2">26 Apr – 25 May 2026</option>
                <option value="3">26 Mar – 25 Apr 2026</option>
            </select>
        </div>
    </div>
</div>

<!-- ===== SUMMARY CARDS ===== -->
<div class="summary-grid" id="summaryCards">
    <div class="summary-card">
        <div class="number" style="color:#d97706;" id="lateCount">0</div>
        <div class="label">Late Arrivals</div>
        <div class="sub">flagged</div>
    </div>
    <div class="summary-card">
        <div class="number" style="color:#d97706;" id="halfDayCount">0</div>
        <div class="label">Half-Days</div>
        <div class="sub">flagged</div>
    </div>
    <div class="summary-card">
        <div class="number" style="color:#059669;" id="clDeductCount">0</div>
        <div class="label">Casual Leave to Deduct</div>
        <div class="sub">after waivers</div>
    </div>
    <div class="summary-card">
        <div class="number" style="color:#dc2626;" id="salaryDeductionCount">0</div>
        <div class="label">Salary-Day Deduction</div>
        <div class="sub">where CL insufficient</div>
    </div>
</div>

<!-- ===== PENALTY ITEMS TABLE ===== -->
<div class="glass-card">

    <div class="section-header">
        <div>
            <h6>Your late arrivals &amp; half-days</h6>
            <span class="subtitle" id="penaltyCount">0 penalty items · 26 Jun – 25 Jul 2026</span>
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
            <tbody id="penaltyTableBody">
                <!-- Dynamic content via JS -->
            </tbody>
        </table>
    </div>

</div>

<!-- ===== INFO BOX ===== -->
<div class="glass-card">
    <div class="info-box">
        <i class="bi bi-info-circle"></i>
        Every <strong>3 late arrivals</strong> in a cycle equal 1 Casual Leave (the first 2 carry no penalty); every <strong>2 half-days</strong> equal 1 Casual Leave. Where the Casual Leave balance is insufficient, the shortfall is a <strong>salary-day deduction</strong>. You can request a <strong>waiver</strong> for any late arrival or half-day; if your line manager approves, it will not count toward your penalty. Evaluated per attendance cycle; payroll applies the resulting deductions.
    </div>
</div>


<!-- WAIVER REQUEST MODAL                      -->

<div class="modal fade modal-glass" id="waiverRequestModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-shield" style="color:var(--primary);"></i> Request Waiver</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="waiverForm">
                    <div class="form-group">
                        <label>Date</label>
                        <input type="date" class="form-control-custom" value="{{ date('Y-m-d') }}">
                    </div>
                    <div class="form-group">
                        <label>Type</label>
                        <select class="form-control-custom">
                            <option value="">Select type...</option>
                            <option value="late">Late Arrival</option>
                            <option value="half-day">Half-Day</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Reason</label>
                        <textarea class="form-control-custom" rows="2" placeholder="Explain why you want this waived..."></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-cancel" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn-save" onclick="submitWaiver()">
                    <i class="bi bi-check2"></i> Submit Waiver
                </button>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
    // ==========================================
    // DATA — Per Cycle
    // ==========================================
    const cycleData = {
        0: { // 26 Jun – 25 Jul 2026
            label: '26 Jun – 25 Jul 2026',
            late: 0,
            halfDay: 0,
            clDeduct: 0,
            salaryDeduct: 0,
            items: [
                // { date: '20 Jul 2026', type: 'Late Arrival', status: 'Flagged', waiver: 'Pending' },
                // { date: '18 Jul 2026', type: 'Half-Day', status: 'Flagged', waiver: 'Approved' },
            ]
        },
        1: { // 26 May – 25 Jun 2026
            label: '26 May – 25 Jun 2026',
            late: 2,
            halfDay: 1,
            clDeduct: 1,
            salaryDeduct: 0,
            items: [
                { date: '15 Jun 2026', type: 'Late Arrival', status: 'Flagged', waiver: 'Pending' },
                { date: '12 Jun 2026', type: 'Late Arrival', status: 'Flagged', waiver: '—' },
                { date: '10 Jun 2026', type: 'Half-Day', status: 'Flagged', waiver: 'Approved' },
            ]
        },
        2: { // 26 Apr – 25 May 2026
            label: '26 Apr – 25 May 2026',
            late: 3,
            halfDay: 2,
            clDeduct: 2,
            salaryDeduct: 1,
            items: [
                { date: '20 May 2026', type: 'Late Arrival', status: 'Flagged', waiver: 'Approved' },
                { date: '18 May 2026', type: 'Late Arrival', status: 'Flagged', waiver: 'Rejected' },
                { date: '15 May 2026', type: 'Late Arrival', status: 'Flagged', waiver: '—' },
                { date: '12 May 2026', type: 'Half-Day', status: 'Flagged', waiver: 'Pending' },
                { date: '10 May 2026', type: 'Half-Day', status: 'Flagged', waiver: '—' },
            ]
        },
        3: { // 26 Mar – 25 Apr 2026
            label: '26 Mar – 25 Apr 2026',
            late: 1,
            halfDay: 0,
            clDeduct: 0,
            salaryDeduct: 0,
            items: [
                { date: '05 Apr 2026', type: 'Late Arrival', status: 'Flagged', waiver: 'Approved' },
            ]
        },
    };

    // ==========================================
    // UPDATE FUNCTION
    // ==========================================
    function updateCycle() {
        const val = document.getElementById('cycleSelector').value;
        const data = cycleData[val] || cycleData[0];

        // Update date display
        document.getElementById('cycleDateDisplay').textContent = data.label;

        // Update summary cards
        document.getElementById('lateCount').textContent = data.late;
        document.getElementById('halfDayCount').textContent = data.halfDay;
        document.getElementById('clDeductCount').textContent = data.clDeduct;
        document.getElementById('salaryDeductionCount').textContent = data.salaryDeduct;

        // Update penalty count
        document.getElementById('penaltyCount').textContent = data.items.length + ' penalty items · ' + data.label;

        // Update table
        const tbody = document.getElementById('penaltyTableBody');

        if (data.items.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="5">
                        <div class="empty-state">
                            <i class="bi bi-check-circle"></i>
                            <p>No late arrivals or half-days in this cycle.</p>
                            <span class="sub">All good! Keep it up.</span>
                        </div>
                    </td>
                </tr>
            `;
            return;
        }

        let html = '';
        data.items.forEach(item => {
            const waiverClass = item.waiver === 'Pending' ? 'pending' :
                              item.waiver === 'Approved' ? 'approved' :
                              item.waiver === 'Rejected' ? 'rejected' : '';
            const waiverDisplay = item.waiver === '—' ? '—' :
                                 `<span class="status-badge ${waiverClass}">${item.waiver}</span>`;

            let actionHtml = '';
            if (item.waiver === '—' || item.waiver === 'Rejected') {
                actionHtml = `<button class="btn-outline-glass" style="padding:2px 10px;font-size:11px;" onclick="alert('Request waiver for ${item.date}')">Request</button>`;
            } else if (item.waiver === 'Pending') {
                actionHtml = `<span style="font-size:12px; color:#d97706;">⏳ Pending</span>`;
            } else if (item.waiver === 'Approved') {
                actionHtml = `<span style="font-size:12px; color:#22c55e;">✅ Waived</span>`;
            }

            html += `
                <tr>
                    <td>${item.date}</td>
                    <td>${item.type}</td>
                    <td><span class="status-badge pending">${item.status}</span></td>
                    <td>${waiverDisplay}</td>
                    <td>${actionHtml}</td>
                </tr>
            `;
        });

        tbody.innerHTML = html;
    }

    // ==========================================
    // SUBMIT WAIVER
    // ==========================================
    function submitWaiver() {
        const form = document.getElementById('waiverForm');
        const date = form.querySelector('input[type="date"]').value;
        const type = form.querySelector('select').value;
        const reason = form.querySelector('textarea').value;

        if (!date) {
            toastr.warning('Please select a date.');
            return;
        }
        if (!type) {
            toastr.warning('Please select a type.');
            return;
        }
        if (!reason.trim()) {
            toastr.warning('Please enter a reason.');
            return;
        }

        toastr.success('Waiver request submitted successfully!');

        const modal = bootstrap.Modal.getInstance(document.getElementById('waiverRequestModal'));
        if (modal) modal.hide();

        form.reset();
    }

    // ==========================================
    // INIT
    // ==========================================
    document.addEventListener('DOMContentLoaded', function() {
        updateCycle();
    });
</script>
@endpush