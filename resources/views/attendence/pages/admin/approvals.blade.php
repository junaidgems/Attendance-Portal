@extends('attendence.layouts.app')

@section('title', 'Approvals')
@section('page-title', 'Approvals')

@section('content')
    <link rel="stylesheet" href="{{ asset('css/approval.css') }}">
    <!-- ========================================== -->
    <!-- APPROVALS PAGE                            -->
    <!-- ========================================== -->
    <div class="row">
        <div class="col-12">
            <div class="glass-card">

                <!-- ===== TABS ===== -->
                <div class="approval-tabs" id="approvalTabs">
                    <button class="tab-btn active" data-tab="corrections">
                        <i class="bi bi-pencil-square"></i> Correction Requests
                        <span class="badge-count">0</span>
                    </button>
                    <button class="tab-btn" data-tab="leaves">
                        <i class="bi bi-calendar-check"></i> Leave Requests
                        <span class="badge-count">0</span>
                    </button>
                    <button class="tab-btn" data-tab="waivers">
                        <i class="bi bi-shield-minus"></i> Deduction Waiver Requests
                        <span class="badge-count">0</span>
                    </button>
                </div>

                <!-- ========================================== -->
                <!-- TAB 1: CORRECTION REQUESTS                -->
                <!-- ========================================== -->
                <!-- ========================================== -->
                <!-- TAB 1: CORRECTION REQUESTS                -->
                <!-- ========================================== -->
                <div class="tab-content active" id="tab-corrections">

                    @if(count($data['corrections']) > 0)

                        <!-- Count -->
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                            <span style="font-size: 14px; color: var(--text-muted);">
                                <strong style="color: var(--text-primary);">{{ count($data['corrections']) }}</strong> pending
                                requests
                            </span>
                        </div>

                        <!-- Items -->
                        @foreach($data['corrections'] as $item)
                            <div class="approval-item" data-id="{{ $item['id'] }}">
                                <div class="info">
                                    <div class="avatar-sm">{{ $item['avatar'] }}</div>
                                    <div class="details">
                                        <div class="name">{{ $item['employee'] }}</div>
                                        <div class="meta">
                                            <i class="bi bi-calendar"></i>
                                            {{ \Carbon\Carbon::parse($item['date'])->format('d M Y') }}
                                            · {{ $item['type'] }}
                                        </div>
                                    </div>
                                    <span class="reason">
                                        <i class="bi bi-clock"></i> Requested: {{ $item['requested_time'] }}
                                        — "{{ $item['reason'] }}"
                                    </span>
                                </div>
                                <div class="actions">
                                    <button class="btn-approve" onclick="approveRequest({{ $item['id'] }}, 'correction')">
                                        <i class="bi bi-check2"></i> Approve
                                    </button>
                                    <button class="btn-reject" onclick="rejectRequest({{ $item['id'] }}, 'correction')">
                                        <i class="bi bi-x"></i> Reject
                                    </button>
                                </div>
                            </div>
                        @endforeach

                    @else

                        <!-- Empty State -->
                        <div class="empty-state">
                            <i class="bi bi-check-circle"></i>
                            <h6>No pending corrections</h6>
                            <p>All correction requests have been reviewed.</p>
                        </div>

                    @endif

                </div>
                <!-- ========================================== -->
                <!-- TAB 2: LEAVE REQUESTS                     -->
                <!-- ========================================== -->
                <div class="tab-content" id="tab-leaves">

                    <div class="empty-state">
                        <i class="bi bi-check-circle"></i>
                        <h6>No pending leave requests</h6>
                        <p>All leave requests have been reviewed.</p>
                    </div>

                    <!-- ===== DUMMY DATA — UNCOMMENT LATER ===== -->
                    <!--
                                    <div class="approval-item">
                                        <div class="info">
                                            <div class="avatar-sm">JS</div>
                                            <div class="details">
                                                <div class="name">Jane Smith</div>
                                                <div class="meta"><i class="bi bi-calendar"></i> 20-22 Jul 2026 · Annual Leave</div>
                                            </div>
                                            <span class="reason"><i class="bi bi-chat"></i> Family event</span>
                                        </div>
                                        <div class="actions">
                                            <button class="btn-approve"><i class="bi bi-check2"></i> Approve</button>
                                            <button class="btn-reject"><i class="bi bi-x"></i> Reject</button>
                                        </div>
                                    </div>
                                    -->

                </div>

                <!-- ========================================== -->
                <!-- TAB 3: DEDUCTION WAIVER REQUESTS          -->
                <!-- ========================================== -->
                <div class="tab-content" id="tab-waivers">

                    <div class="empty-state">
                        <i class="bi bi-check-circle"></i>
                        <h6>No pending waiver requests</h6>
                        <p>All waiver requests have been reviewed.</p>
                    </div>

                    <!-- ===== DUMMY DATA — UNCOMMENT LATER ===== -->
                    <!--
                                    <div class="approval-item">
                                        <div class="info">
                                            <div class="avatar-sm">MK</div>
                                            <div class="details">
                                                <div class="name">Mike Khan</div>
                                                <div class="meta"><i class="bi bi-calendar"></i> 17 Jul 2026 · Late Deduction</div>
                                            </div>
                                            <span class="reason"><i class="bi bi-chat"></i> Traffic jam</span>
                                        </div>
                                        <div class="actions">
                                            <button class="btn-approve"><i class="bi bi-check2"></i> Approve</button>
                                            <button class="btn-reject"><i class="bi bi-x"></i> Reject</button>
                                        </div>
                                    </div>
                                    -->

                </div>

            </div>
        </div>
    </div>

@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {

            // ===== TABS =====
            const tabBtns = document.querySelectorAll('.tab-btn');
            const tabContents = {
                corrections: document.getElementById('tab-corrections'),
                leaves: document.getElementById('tab-leaves'),
                waivers: document.getElementById('tab-waivers'),
            };

            tabBtns.forEach(btn => {
                btn.addEventListener('click', function () {
                    // Remove active from all tabs
                    tabBtns.forEach(b => b.classList.remove('active'));
                    this.classList.add('active');

                    // Hide all tab contents
                    Object.values(tabContents).forEach(content => {
                        content.classList.remove('active');
                    });

                    // Show selected tab
                    const tab = this.dataset.tab;
                    if (tabContents[tab]) {
                        tabContents[tab].classList.add('active');
                    }
                });
            });

            // ===== APPROVE/REJECT BUTTONS =====
            document.querySelectorAll('.btn-approve').forEach(btn => {
                btn.addEventListener('click', function () {
                    if (confirm('Approve this request?')) {
                        const item = this.closest('.approval-item');
                        if (item) {
                            item.style.opacity = '0.5';
                            item.style.pointerEvents = 'none';
                            alert('Request approved!');
                            // Later: AJAX call
                        }
                    }
                });
            });

            document.querySelectorAll('.btn-reject').forEach(btn => {
                btn.addEventListener('click', function () {
                    if (confirm('Reject this request?')) {
                        const item = this.closest('.approval-item');
                        if (item) {
                            item.style.opacity = '0.5';
                            item.style.pointerEvents = 'none';
                            alert('Request rejected!');
                            // Later: AJAX call
                        }
                    }
                });
            });

        });
    </script>
@endpush