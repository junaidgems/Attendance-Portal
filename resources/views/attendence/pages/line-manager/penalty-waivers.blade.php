@extends('attendence.layouts.app')

@section('title', 'Penalty & Waivers')
@section('page-title', 'Penalty & Waivers')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/manager-css/penalty-wave.css') }}">

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
            <label style="font-size:12px; color:var(--text-muted); font-weight:600;">Employee</label>
            <select id="employeeSelector" onchange="updateCycle()">
                <option value="0" selected>John Doe (GP-1003)</option>
                <option value="1">Jane Smith (GP-1004)</option>
                <option value="2">Mike Johnson (GP-1005)</option>
            </select>
            <label style="font-size:12px; color:var(--text-muted); font-weight:600; margin-left:8px;">Cycle</label>
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
            <h6>Penalty Items</h6>
            <span class="subtitle" id="penaltyCount">0 penalty items</span>
        </div>
        <!-- ✅ Manager ke liye "Request Waiver" button nahi hai -->
    </div>

    <div style="overflow-x:auto;">
        <table class="penalty-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Type</th>
                    <th>Status</th>
                    <th>Waiver Status</th>
                    <th style="text-align:right;">Action</th>
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

@endsection

@push('scripts')
<script>
    // ==========================================
    // DATA — Per Employee & Cycle
    // ==========================================
    const allData = {
        // Employee 0: John Doe
        '0_0': {
            label: 'John Doe · 26 Jun – 25 Jul 2026',
            late: 2,
            halfDay: 1,
            clDeduct: 1,
            salaryDeduct: 0,
            items: [
                { date: '20 Jul 2026', type: 'Late Arrival', status: 'Flagged', waiver: 'Pending' },
                { date: '18 Jul 2026', type: 'Half-Day', status: 'Flagged', waiver: '—' },
            ]
        },
        '0_1': {
            label: 'John Doe · 26 May – 25 Jun 2026',
            late: 3,
            halfDay: 2,
            clDeduct: 2,
            salaryDeduct: 0,
            items: [
                { date: '15 Jun 2026', type: 'Late Arrival', status: 'Flagged', waiver: 'Approved' },
                { date: '12 Jun 2026', type: 'Late Arrival', status: 'Flagged', waiver: 'Pending' },
                { date: '10 Jun 2026', type: 'Half-Day', status: 'Flagged', waiver: '—' },
            ]
        },
        // Employee 1: Jane Smith
        '1_0': {
            label: 'Jane Smith · 26 Jun – 25 Jul 2026',
            late: 1,
            halfDay: 0,
            clDeduct: 0,
            salaryDeduct: 0,
            items: [
                { date: '19 Jul 2026', type: 'Late Arrival', status: 'Flagged', waiver: 'Approved' },
            ]
        },
        '1_1': {
            label: 'Jane Smith · 26 May – 25 Jun 2026',
            late: 4,
            halfDay: 2,
            clDeduct: 2,
            salaryDeduct: 0,
            items: [
                { date: '22 Jun 2026', type: 'Late Arrival', status: 'Flagged', waiver: '—' },
                { date: '20 Jun 2026', type: 'Late Arrival', status: 'Flagged', waiver: '—' },
                { date: '18 Jun 2026', type: 'Half-Day', status: 'Flagged', waiver: 'Pending' },
                { date: '16 Jun 2026', type: 'Late Arrival', status: 'Flagged', waiver: '—' },
            ]
        },
        // Employee 2: Mike Johnson
        '2_0': {
            label: 'Mike Johnson · 26 Jun – 25 Jul 2026',
            late: 0,
            halfDay: 0,
            clDeduct: 0,
            salaryDeduct: 0,
            items: []
        },
        '2_1': {
            label: 'Mike Johnson · 26 May – 25 Jun 2026',
            late: 1,
            halfDay: 0,
            clDeduct: 0,
            salaryDeduct: 0,
            items: [
                { date: '25 Jun 2026', type: 'Late Arrival', status: 'Flagged', waiver: 'Rejected' },
            ]
        },
    };

    // ==========================================
    // UPDATE FUNCTION
    // ==========================================
    function updateCycle() {
        const emp = document.getElementById('employeeSelector').value;
        const cycle = document.getElementById('cycleSelector').value;
        const key = emp + '_' + cycle;

        const data = allData[key] || {
            label: 'No data',
            late: 0,
            halfDay: 0,
            clDeduct: 0,
            salaryDeduct: 0,
            items: []
        };

        document.getElementById('cycleDateDisplay').textContent = data.label;
        document.getElementById('lateCount').textContent = data.late;
        document.getElementById('halfDayCount').textContent = data.halfDay;
        document.getElementById('clDeductCount').textContent = data.clDeduct;
        document.getElementById('salaryDeductionCount').textContent = data.salaryDeduct;
        document.getElementById('penaltyCount').textContent = data.items.length + ' penalty items';

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
        data.items.forEach((item, index) => {
            const waiverClass = item.waiver === 'Pending' ? 'pending' :
                              item.waiver === 'Approved' ? 'approved' :
                              item.waiver === 'Rejected' ? 'rejected' : '';
            const waiverDisplay = item.waiver === '—' ? '—' :
                                 `<span class="status-badge ${waiverClass}">${item.waiver}</span>`;

            let actionHtml = '';
            if (item.waiver === 'Pending') {
                // ✅ Manager Approve/Reject karega
                actionHtml = `
                    <div style="display:flex; gap:6px; justify-content:flex-end;">
                        <button class="btn-approve" onclick="handleWaiver(${index}, 'approve')">
                            <i class="bi bi-check2"></i> Approve
                        </button>
                        <button class="btn-reject" onclick="handleWaiver(${index}, 'reject')">
                            <i class="bi bi-x"></i> Reject
                        </button>
                    </div>
                `;
            } else if (item.waiver === 'Approved') {
                actionHtml = `<span class="status-text-approved"><i class="bi bi-check-circle"></i> Approved</span>`;
            } else if (item.waiver === 'Rejected') {
                actionHtml = `<span class="status-text-rejected"><i class="bi bi-x-circle"></i> Rejected</span>`;
            } else {
                actionHtml = `<span style="color:var(--text-muted);">—</span>`;
            }

            html += `
                <tr>
                    <td>${item.date}</td>
                    <td>${item.type}</td>
                    <td><span class="status-badge flagged">${item.status}</span></td>
                    <td>${waiverDisplay}</td>
                    <td style="text-align:right; white-space:nowrap;">${actionHtml}</td>
                </tr>
            `;
        });

        tbody.innerHTML = html;
    }

    // ==========================================
    // HANDLE WAIVER — Approve / Reject
    // ==========================================
    function handleWaiver(index, action) {
        const emp = document.getElementById('employeeSelector').value;
        const cycle = document.getElementById('cycleSelector').value;
        const key = emp + '_' + cycle;
        const data = allData[key];

        if (!data || !data.items[index]) return;

        const item = data.items[index];
        const msg = action === 'approve' 
            ? `✅ Waiver approved for ${item.date} (${item.type})` 
            : `❌ Waiver rejected for ${item.date} (${item.type})`;

        if (action === 'approve') {
            item.waiver = 'Approved';
            toastr.success('Waiver approved!');
        } else {
            item.waiver = 'Rejected';
            toastr.warning('Waiver rejected!');
        }

        updateCycle();
    }

    // ==========================================
    // INIT
    // ==========================================
    document.addEventListener('DOMContentLoaded', function() {
        updateCycle();
    });
</script>
@endpush