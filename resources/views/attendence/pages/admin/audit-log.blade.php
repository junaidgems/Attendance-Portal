@extends('attendence.layouts.app')

@section('title', 'Audit Log')
@section('page-title', 'Audit Log')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/audit-log.css') }}">
@endpush

@section('content')

    <!-- AUDIT LOG                                 -->

    <div class="row">
        <div class="col-12">
            <div class="glass-card">

                <div class="section-header">
                    <div class="section-title"><i class="bi bi-journal-text"></i> Audit Log</div>
                </div>

                <!-- Filter Bar -->
                <div class="filter-bar">
                    <div class="form-group" style="flex:2;">
                        <label>Search</label>
                        <input type="text" class="form-control" placeholder="Action, person or detail" id="searchInput">
                    </div>
                    <div class="form-group">
                        <label>Category</label>
                        <select class="form-control" id="categoryFilter">
                            <option value="all">All activity</option>
                            <option value="cycle">Attendance Cycle</option>
                            <option value="lock">Period Lock</option>
                            <option value="holiday">Holiday</option>
                            <option value="sync">Biometric Sync</option>
                            <option value="signin">Sign In</option>

                        </select>
                    </div>
                    <div class="btn-group">
                        <button class="btn-primary-glass" id="filterBtn"><i class="bi bi-funnel"></i> Filter</button>
                        <button class="btn-outline-glass" id="clearFilterBtn"><i class="bi bi-arrow-counterclockwise"></i>
                            Clear</button>
                        <button class="btn-outline-glass" id="exportBtn"><i class="bi bi-download"></i> Export CSV</button>
                    </div>
                </div>

                <!-- Table -->
                <div class="table-wrap" style="margin-top:16px;">
                    <table class="audit-table" id="auditTable">
                        <thead>
                            <tr>
                                <th>Timestamp</th>
                                <th>Actor</th>
                                <th>Action</th>
                                <th>Detail</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody id="auditTableBody">
                            <!-- Logout Row -->
                            <tr>
                                <td class="timestamp">{{ date('d M H:i') }}</td>
                                <td><span class="actor-name">Admin User</span></td>
                                <td><span class="action-badge cycle">Attendance cycle updated</span></td>

                                <td>12 Jul – 1 Aug 2026</td>
                                <td>

                                    <form method="POST" action="{{ route('logout') }}" style="display:inline;">
                                        @csrf
                                        <button type="submit" class="btn-danger-glass"
                                            style="padding:4px 14px;border-radius:6px;font-size:12px;border:none;cursor:pointer;background:#ef4444;color:#fff;font-weight:600;">
                                            <i class="bi bi-box-arrow-right"></i> Logout Now
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <!-- Other Logs -->
                            <tr>
                                <td class="timestamp">20 Jul 15:26</td>
                                <td><span class="actor-name">Faraz Siddiqui</span></td>
                                <td><span class="action-badge cycle">Attendance cycle updated</span></td>
                                <td>12 Jul – 1 Aug 2026</td>
                                <td>

                                    <form method="POST" action="{{ route('logout') }}" style="display:inline;">
                                        @csrf
                                        <button type="submit" class="btn-danger-glass"
                                            style="padding:4px 14px;border-radius:6px;font-size:12px;border:none;cursor:pointer;background:#ef4444;color:#fff;font-weight:600;">
                                            <i class="bi bi-box-arrow-right"></i> Logout Now
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <tr>
                                <td class="timestamp">20 Jul 15:26</td>
                                <td><span class="actor-name">Faraz Siddiqui</span></td>
                                <td><span class="action-badge cycle">Attendance cycle updated</span></td>
                                <td>12 Jul – 10 Aug 2026</td>
                                <td>

                                    <form method="POST" action="{{ route('logout') }}" style="display:inline;">
                                        @csrf
                                        <button type="submit" class="btn-danger-glass"
                                            style="padding:4px 14px;border-radius:6px;font-size:12px;border:none;cursor:pointer;background:#ef4444;color:#fff;font-weight:600;">
                                            <i class="bi bi-box-arrow-right"></i> Logout Now
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <tr>
                                <td class="timestamp">20 Jul 15:26</td>
                                <td><span class="actor-name">Faraz Siddiqui</span></td>
                                <td><span class="action-badge cycle">Attendance cycle updated</span></td>
                                <td>26 Jun – 10 Jul 2026</td>
                                <td>

                                    <form method="POST" action="{{ route('logout') }}" style="display:inline;">
                                        @csrf
                                        <button type="submit" class="btn-danger-glass"
                                            style="padding:4px 14px;border-radius:6px;font-size:12px;border:none;cursor:pointer;background:#ef4444;color:#fff;font-weight:600;">
                                            <i class="bi bi-box-arrow-right"></i> Logout Now
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <tr>
                                <td class="timestamp">20 Jul 15:26</td>
                                <td><span class="actor-name">Faraz Siddiqui</span></td>
                                <td><span class="action-badge lock">Period locked</span></td>
                                <td>May 2026</td>
                                <td>

                                    <form method="POST" action="{{ route('logout') }}" style="display:inline;">
                                        @csrf
                                        <button type="submit" class="btn-danger-glass"
                                            style="padding:4px 14px;border-radius:6px;font-size:12px;border:none;cursor:pointer;background:#ef4444;color:#fff;font-weight:600;">
                                            <i class="bi bi-box-arrow-right"></i> Logout Now
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <tr>
                                <td class="timestamp">20 Jul 15:26</td>
                                <td><span class="actor-name">Faraz Siddiqui</span></td>
                                <td><span class="action-badge holiday">Holiday added</span></td>
                                <td>Eid · 2026-07-15 → 2026-07-21 · unscheduled</td>
                            </tr>
                            <tr>
                                <td class="timestamp">20 Jul 13:54</td>
                                <td><span class="actor-name">Faraz Siddiqui</span></td>
                                <td><span class="action-badge sync">Biometric sync</span></td>
                                <td>Pulled 1 new punch record(s) from BIO-KHI-01</td>
                            </tr>
                            <tr>
                                <td class="timestamp">20 Jul 10:56</td>
                                <td><span class="actor-name">Faraz Siddiqui</span></td>
                                <td><span class="action-badge signin">Sign in</span></td>
                                <td>Faraz Siddiqui signed in</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- No Results -->
                <div id="noResults" style="display:none; text-align:center; padding:30px 20px; color:var(--text-muted);">
                    <i class="bi bi-inbox" style="font-size:36px; opacity:0.3; display:block; margin-bottom:8px;"></i>
                    <p>No audit records found.</p>
                </div>

            </div>
        </div>
    </div>

@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {

            const searchInput = document.getElementById('searchInput');
            const categoryFilter = document.getElementById('categoryFilter');
            const filterBtn = document.getElementById('filterBtn');
            const clearBtn = document.getElementById('clearFilterBtn');
            const exportBtn = document.getElementById('exportBtn');
            const tableBody = document.getElementById('auditTableBody');
            const noResults = document.getElementById('noResults');

            // ===== FILTER FUNCTION =====
            function filterTable() {
                const search = searchInput.value.toLowerCase().trim();
                const category = categoryFilter.value;

                let rows = tableBody.querySelectorAll('tr');
                let visibleCount = 0;

                // Category mapping
                const categoryMap = {
                    'cycle': ['Attendance cycle updated'],
                    'lock': ['Period locked'],
                    'holiday': ['Holiday added'],
                    'sync': ['Biometric sync'],
                    'signin': ['Sign in'],
                };

                rows.forEach(row => {
                    const text = row.textContent.toLowerCase();
                    const actionCell = row.querySelector('td:nth-child(3)');
                    const actionText = actionCell ? actionCell.textContent.trim() : '';

                    let categoryMatch = true;
                    if (category !== 'all') {
                        const keywords = categoryMap[category] || [];
                        categoryMatch = keywords.some(keyword => actionText.includes(keyword));
                    }

                    let searchMatch = true;
                    if (search) {
                        searchMatch = text.includes(search);
                    }

                    if (categoryMatch && searchMatch) {
                        row.style.display = '';
                        visibleCount++;
                    } else {
                        row.style.display = 'none';
                    }
                });

                if (visibleCount === 0) {
                    noResults.style.display = 'block';
                } else {
                    noResults.style.display = 'none';
                }
            }

            // ===== EVENT LISTENERS =====
            filterBtn.addEventListener('click', filterTable);

            searchInput.addEventListener('keyup', function (e) {
                if (e.key === 'Enter') filterTable();
            });

            clearBtn.addEventListener('click', function () {
                searchInput.value = '';
                categoryFilter.value = 'all';
                filterTable();
                toastr.info('Filters cleared');
            });

            // ===== EXPORT CSV =====
            exportBtn.addEventListener('click', function () {
                const rows = tableBody.querySelectorAll('tr:not([style*="display: none"])');
                if (rows.length === 0) {
                    toastr.warning('No records to export');
                    return;
                }

                let csv = 'Timestamp,Actor,Action,Detail\n';
                rows.forEach(row => {
                    const cols = row.querySelectorAll('td');
                    if (cols.length === 4) {
                        csv += [
                            cols[0].textContent.trim(),
                            cols[1].textContent.trim(),
                            cols[2].textContent.trim(),
                            cols[3].textContent.trim()
                        ].join(',') + '\n';
                    }
                });

                const blob = new Blob([csv], { type: 'text/csv' });
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = 'audit-log-' + new Date().toISOString().slice(0, 10) + '.csv';
                a.click();
                window.URL.revokeObjectURL(url);
                toastr.success('Export started!');
            });

        });
    </script>
@endpush