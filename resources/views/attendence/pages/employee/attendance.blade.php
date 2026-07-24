@extends('attendence.layouts.app')

@section('title', 'My Attendance')
@section('page-title', 'My Attendance')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/employee/myAttendance.css') }}">
@endpush

@section('content')


    <!-- MONTH HEADER   -->

    <div class="glass-card">
        <div class="month-header">
            <h4 id="monthYear">Jul 2026</h4>
            <div style="display:flex; gap:12px; align-items:center; flex-wrap:wrap;">
                <div class="nav-buttons">
                    <button onclick="changeMonth(-1)"><i class="bi bi-chevron-left"></i></button>
                    <button onclick="changeMonth(1)"><i class="bi bi-chevron-right"></i></button>
                </div>
                <div class="view-toggle">
                    <button class="active" data-view="list" onclick="switchView('list')">List</button>
                    <button data-view="calendar" onclick="switchView('calendar')">Calendar</button>
                </div>
            </div>
        </div>
    </div>


    <!-- SUMMARY CARDS                             -->

    <div class="summary-grid" id="summaryCards">
        <div class="summary-card">
            <div class="number" style="color:#059669;" id="presentCount">0</div>
            <div class="label">Present</div>
            <div class="sub">full days</div>
        </div>
        <div class="summary-card">
            <div class="number" style="color:#d97706;" id="lateEarlyCount">0</div>
            <div class="label">Late / Early</div>
            <div class="sub" id="lateEarlySub">0 late - 0 early</div>
        </div>
        <div class="summary-card">
            <div class="number" style="color:#d97706;" id="halfMissingCount">0</div>
            <div class="label">Half / Missing</div>
            <div class="sub" id="halfMissingSub">0 half - 0 missing</div>
        </div>
        <div class="summary-card">
            <div class="number" style="color:#dc2626;" id="absentCount">10</div>
            <div class="label">Absent</div>
            <div class="sub" id="absentSub">10 working days</div>
        </div>
    </div>


    <!-- LIST VIEW                                 -->

    <div class="glass-card">
        <div class="view-content active" id="listView">
            <div class="table-wrap">
                <table class="attendance-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Status</th>
                            <th>IN</th>
                            <th>OUT</th>
                            <th>Net Hours</th>
                            <th>Timeline</th>
                        </tr>
                    </thead>
                    <tbody id="listTableBody">
                        <!-- Dynamic content -->
                    </tbody>
                </table>
            </div>
        </div>


        <!-- CALENDAR VIEW                             -->

        <div class="view-content" id="calendarView">
            <div class="calendar-grid" id="calendarGrid">
                <!-- Dynamic content -->
            </div>
        </div>
    </div>

@endsection

@push('scripts')
    <script>
        // DATA
        let currentMonth = 6;  // July = 6 (0-based)
        let currentYear = 2026;

        const statusColors = {
            'Present': 'present',
            'Absent': 'absent',
            'Late': 'late',
            'Half Day': 'half-day',
            'Early Out': 'early-out',
            'Missing Punch': 'missing-punch'
        };

        // ==========================================
        // GENERATE DUMMY DATA FOR A MONTH
        // ==========================================
        function generateDummyData(month, year) {
            const daysInMonth = new Date(year, month + 1, 0).getDate();
            const data = [];
            const statuses = ['Present', 'Absent', 'Late', 'Half Day', 'Early Out', 'Missing Punch'];
            const weights = [1, 5, 2, 1, 1, 1]; // Absent zyada aaye

            for (let i = 1; i <= daysInMonth; i++) {
                const date = new Date(year, month, i);
                const day = date.getDay();
                // Weekend par Absent
                let status = 'Absent';
                if (day !== 0 && day !== 6) {
                    // Random status
                    const rand = Math.random();
                    let cum = 0;
                    for (let j = 0; j < statuses.length; j++) {
                        cum += weights[j] / weights.reduce((a, b) => a + b, 0);
                        if (rand < cum) {
                            status = statuses[j];
                            break;
                        }
                    }
                }
                data.push({
                    date: i,
                    day: ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'][day],
                    status: status,
                    in: status === 'Present' || status === 'Late' ? '09:' + String(15 + Math.floor(Math.random() * 20)).padStart(2, '0') : '—',
                    out: status === 'Present' || status === 'Late' ? '17:' + String(10 + Math.floor(Math.random() * 30)).padStart(2, '0') : '—',
                    net: status === 'Present' ? '8.0' : status === 'Late' ? '7.5' : '—',
                    timeline: status === 'Present' ? 'On Time' : status === 'Late' ? 'Late by ' + (Math.floor(Math.random() * 30) + 5) + 'm' : '—'
                });
            }
            return data;
        }

        // ==========================================
        // RENDER FUNCTIONS
        // ==========================================
        function renderMonth() {
            const monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
            document.getElementById('monthYear').textContent = monthNames[currentMonth] + ' ' + currentYear;

            const data = generateDummyData(currentMonth, currentYear);

            // Update Summary
            const present = data.filter(d => d.status === 'Present').length;
            const absent = data.filter(d => d.status === 'Absent').length;
            const late = data.filter(d => d.status === 'Late').length;
            const early = data.filter(d => d.status === 'Early Out').length;
            const half = data.filter(d => d.status === 'Half Day').length;
            const missing = data.filter(d => d.status === 'Missing Punch').length;

            document.getElementById('presentCount').textContent = present;
            document.getElementById('absentCount').textContent = absent;
            document.getElementById('lateEarlyCount').textContent = late + early;
            document.getElementById('lateEarlySub').textContent = late + ' late - ' + early + ' early';
            document.getElementById('halfMissingCount').textContent = half + missing;
            document.getElementById('halfMissingSub').textContent = half + ' half - ' + missing + ' missing';
            document.getElementById('absentSub').textContent = absent + ' working days';

            // Render List
            renderList(data);

            // Render Calendar
            renderCalendar(data);
        }

        function renderList(data) {
            const tbody = document.getElementById('listTableBody');
            if (data.length === 0) {
                tbody.innerHTML = `<tr><td colspan="6"><div class="empty-state"><i class="bi bi-inbox"></i><p>No records found.</p></div></td></tr>`;
                return;
            }

            let html = '';
            data.forEach(d => {
                const statusClass = statusColors[d.status] || 'absent';
                html += `<tr>
                        <td>${d.day} ${d.date}</td>
                        <td><span class="status-badge ${statusClass}">${d.status}</span></td>
                        <td>${d.in}</td>
                        <td>${d.out}</td>
                        <td>${d.net}</td>
                        <td>${d.timeline}</td>
                    </tr>`;
            });
            tbody.innerHTML = html;
        }

        function renderCalendar(data) {
            const grid = document.getElementById('calendarGrid');
            const firstDay = new Date(currentYear, currentMonth, 1).getDay();
            const daysInMonth = new Date(currentYear, currentMonth + 1, 0).getDate();
            const today = new Date().getDate();
            const thisMonth = new Date().getMonth();
            const thisYear = new Date().getFullYear();

            let html = '';
            const weekdays = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
            weekdays.forEach(day => {
                html += `<div class="weekday">${day}</div>`;
            });

            // Empty days before month start
            for (let i = 0; i < firstDay; i++) {
                html += `<div class="day-cell weekend" style="border-color:transparent;background:transparent;box-shadow:none;"></div>`;
            }

            // Days of month
            for (let i = 1; i <= daysInMonth; i++) {
                const date = new Date(currentYear, currentMonth, i);
                const day = date.getDay();
                const isWeekend = (day === 0 || day === 6);
                const isToday = (i === today && currentMonth === thisMonth && currentYear === thisYear);

                const record = data.find(d => d.date === i);
                const status = record ? record.status : '';
                const statusClass = statusColors[status] || '';
                const statusDisplay = status ? `<span class="day-status ${statusClass}">${status}</span>` : '';

                let classes = 'day-cell';
                if (isWeekend) classes += ' weekend';
                if (isToday) classes += ' today';

                html += `<div class="${classes}">
                        <span class="day-number">${i}</span>
                        ${statusDisplay}
                    </div>`;
            }

            grid.innerHTML = html;
        }

        // ==========================================
        // NAVIGATION
        // ==========================================
        function changeMonth(delta) {
            currentMonth += delta;
            if (currentMonth > 11) {
                currentMonth = 0;
                currentYear++;
            } else if (currentMonth < 0) {
                currentMonth = 11;
                currentYear--;
            }
            renderMonth();
        }

        // ==========================================
        // VIEW TOGGLE
        // ==========================================
        function switchView(view) {
            document.querySelectorAll('.view-toggle button').forEach(btn => {
                btn.classList.remove('active');
            });
            document.querySelector(`.view-toggle button[data-view="${view}"]`).classList.add('active');

            document.querySelectorAll('.view-content').forEach(el => {
                el.classList.remove('active');
            });
            document.getElementById(view + 'View').classList.add('active');
        }

        // ==========================================
        // INITIAL RENDER
        // ==========================================
        document.addEventListener('DOMContentLoaded', function () {
            renderMonth();
        });
    </script>
@endpush