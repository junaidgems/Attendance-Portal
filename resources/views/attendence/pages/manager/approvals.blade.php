@extends('attendence.layouts.app')

@section('title', 'Approvals')
@section('page-title', 'Approvals')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/manager-css/approval.css') }}">
@endpush

@section('content')


<!-- APPROVALS PAGE                            -->
<div class="row">
    <div class="col-12">
        <div class="glass-card">

            <!-- ===== TABS ===== -->
            <div class="approval-tabs" id="approvalTabs">
                <button class="tab-btn active" data-tab="corrections">
                    <i class="bi bi-pencil-square"></i> Correction Requests
                    <span class="badge-count">2</span>
                </button>
                <button class="tab-btn" data-tab="leaves">
                    <i class="bi bi-calendar-check"></i> Leave Requests
                    <span class="badge-count">1</span>
                </button>
                <button class="tab-btn" data-tab="waivers">
                    <i class="bi bi-shield-minus"></i> Deduction Waiver Requests
                    <span class="badge-count">0</span>
                </button>
            </div>

            
            <!-- TAB 1: CORRECTION REQUESTS                -->
         
            <div class="tab-content active" id="tab-corrections">

                <div class="section-header">
                    <h6>Correction Requests</h6>
                    <span class="sub">2 pending</span>
                </div>

                <!-- Correction Item 1 -->
                <div class="approval-item" data-id="1" data-type="correction" data-employee="John Doe" data-date="22 Jul 2026" data-detail="Missing Punch" data-reason="Forgot to punch in">
                    <div class="info">
                        <div class="avatar-sm">JD</div>
                        <div class="details">
                            <div class="name">John Doe</div>
                            <div class="meta"><i class="bi bi-calendar"></i> 22 Jul 2026 · Missing Punch</div>
                        </div>
                        <span class="reason"><i class="bi bi-clock"></i> Requested: 09:15 — "Forgot to punch in"</span>
                    </div>
                    <div class="actions">
                        <button class="btn-approve" data-bs-toggle="modal" data-bs-target="#approveModal" onclick="openModal('approve', this)"><i class="bi bi-check2"></i> Approve</button>
                        <button class="btn-reject" data-bs-toggle="modal" data-bs-target="#rejectModal" onclick="openModal('reject', this)"><i class="bi bi-x"></i> Reject</button>
                    </div>
                </div>

                <!-- Correction Item 2 -->
                <div class="approval-item" data-id="2" data-type="correction" data-employee="Jane Smith" data-date="22 Jul 2026" data-detail="Late Punch" data-reason="Traffic jam">
                    <div class="info">
                        <div class="avatar-sm">JS</div>
                        <div class="details">
                            <div class="name">Jane Smith</div>
                            <div class="meta"><i class="bi bi-calendar"></i> 22 Jul 2026 · Late Punch</div>
                        </div>
                        <span class="reason"><i class="bi bi-clock"></i> Requested: 10:30 — "Traffic jam"</span>
                    </div>
                    <div class="actions">
                        <button class="btn-approve" data-bs-toggle="modal" data-bs-target="#approveModal" onclick="openModal('approve', this)"><i class="bi bi-check2"></i> Approve</button>
                        <button class="btn-reject" data-bs-toggle="modal" data-bs-target="#rejectModal" onclick="openModal('reject', this)"><i class="bi bi-x"></i> Reject</button>
                    </div>
                </div>

            </div>

           
            <!-- TAB 2: LEAVE REQUESTS                     -->
            
            <div class="tab-content" id="tab-leaves">

                <div class="section-header">
                    <h6>Leave Requests</h6>
                    <span class="sub">1 pending</span>
                </div>

                <!-- Leave Item 1 -->
                <div class="approval-item" data-id="1" data-type="leave" data-employee="Mike Johnson" data-date="25-27 Jul 2026" data-detail="Annual Leave" data-reason="Family event">
                    <div class="info">
                        <div class="avatar-sm">MK</div>
                        <div class="details">
                            <div class="name">Mike Johnson</div>
                            <div class="meta"><i class="bi bi-calendar"></i> 25-27 Jul 2026 · Annual Leave</div>
                        </div>
                        <span class="reason"><i class="bi bi-chat"></i> "Family event"</span>
                    </div>
                    <div class="actions">
                        <button class="btn-approve" data-bs-toggle="modal" data-bs-target="#approveModal" onclick="openModal('approve', this)"><i class="bi bi-check2"></i> Approve</button>
                        <button class="btn-reject" data-bs-toggle="modal" data-bs-target="#rejectModal" onclick="openModal('reject', this)"><i class="bi bi-x"></i> Reject</button>
                    </div>
                </div>

            </div>

         
            <!-- TAB 3: DEDUCTION WAIVER REQUESTS          -->
           
            <div class="tab-content" id="tab-waivers">

                <div class="section-header">
                    <h6>Deduction Waiver Requests</h6>
                    <span class="sub">0 pending</span>
                </div>

                <div class="empty-state">
                    <i class="bi bi-check-circle"></i>
                    <h6>No pending waiver requests</h6>
                    <p>All waiver requests have been reviewed.</p>
                </div>

            </div>

        </div>
    </div>
</div>

<!-- APPROVE MODAL                             -->
<div class="modal fade modal-glass" id="approveModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-check2-circle" style="color:#22c55e;"></i> Approve Request</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="approveForm">
                    <input type="hidden" id="approveAction" value="approve">
                    <input type="hidden" id="approveEmployee" value="">
                    <input type="hidden" id="approveType" value="">
                    <input type="hidden" id="approveId" value="">

                    <div class="request-preview">
                        <div class="label">Employee</div>
                        <div class="value" id="approveEmployeeName">—</div>
                        <div class="label" style="margin-top:8px;">Request Details</div>
                        <div class="value" id="approveRequestDetails">—</div>
                        <div class="label" style="margin-top:8px;">Reason</div>
                        <div class="value" id="approveRequestReason">—</div>
                    </div>

                    <div class="form-group">
                        <label>Manager Note (Optional)</label>
                        <textarea class="form-control-custom" id="approveNote" rows="2" placeholder="Add a note for the employee..."></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-cancel" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn-save approve" onclick="submitApproval('approve')">
                    <i class="bi bi-check2"></i> Approve
                </button>
            </div>
        </div>
    </div>
</div>


<!-- REJECT MODAL                              -->
<div class="modal fade modal-glass" id="rejectModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-x-circle" style="color:#ef4444;"></i> Reject Request</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="rejectForm">
                    <input type="hidden" id="rejectAction" value="reject">
                    <input type="hidden" id="rejectEmployee" value="">
                    <input type="hidden" id="rejectType" value="">
                    <input type="hidden" id="rejectId" value="">

                    <div class="request-preview">
                        <div class="label">Employee</div>
                        <div class="value" id="rejectEmployeeName">—</div>
                        <div class="label" style="margin-top:8px;">Request Details</div>
                        <div class="value" id="rejectRequestDetails">—</div>
                        <div class="label" style="margin-top:8px;">Reason</div>
                        <div class="value" id="rejectRequestReason">—</div>
                    </div>

                    <div class="form-group">
                        <label>Reason for Rejection <span style="color:#ef4444;">*</span></label>
                        <textarea class="form-control-custom" id="rejectNote" rows="2" placeholder="Please provide a reason for rejection..." required></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-cancel" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn-save reject" onclick="submitApproval('reject')">
                    <i class="bi bi-x"></i> Reject
                </button>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
    // ===== TABS =====
    const tabBtns = document.querySelectorAll('.tab-btn');
    const tabContents = {
        corrections: document.getElementById('tab-corrections'),
        leaves: document.getElementById('tab-leaves'),
        waivers: document.getElementById('tab-waivers'),
    };

    tabBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            tabBtns.forEach(b => b.classList.remove('active'));
            this.classList.add('active');

            Object.values(tabContents).forEach(content => {
                content.classList.remove('active');
            });

            const tab = this.dataset.tab;
            if (tabContents[tab]) {
                tabContents[tab].classList.add('active');
            }
        });
    });

    // ===== OPEN MODAL =====
    function openModal(action, btn) {
        const item = btn.closest('.approval-item');
        const employee = item.dataset.employee || '—';
        const date = item.dataset.date || '—';
        const detail = item.dataset.detail || '—';
        const reason = item.dataset.reason || '—';
        const type = item.dataset.type || '—';
        const id = item.dataset.id || '—';

        const prefix = action === 'approve' ? 'approve' : 'reject';

        document.getElementById(prefix + 'EmployeeName').textContent = employee;
        document.getElementById(prefix + 'RequestDetails').textContent = date + ' · ' + detail;
        document.getElementById(prefix + 'RequestReason').textContent = '"' + reason + '"';
        document.getElementById(prefix + 'Employee').value = employee;
        document.getElementById(prefix + 'Type').value = type;
        document.getElementById(prefix + 'Id').value = id;

        if (action === 'reject') {
            document.getElementById('rejectNote').value = '';
        } else {
            document.getElementById('approveNote').value = '';
        }
    }

    // ===== SUBMIT APPROVAL =====
    function submitApproval(action) {
        const prefix = action === 'approve' ? 'approve' : 'reject';
        const employee = document.getElementById(prefix + 'Employee').value;
        const type = document.getElementById(prefix + 'Type').value;
        const id = document.getElementById(prefix + 'Id').value;
        const note = document.getElementById(prefix + 'Note').value;

        if (action === 'reject' && !note.trim()) {
            toastr.warning('Please provide a reason for rejection.');
            return;
        }

        // Close modal
        const modalId = action === 'approve' ? '#approveModal' : '#rejectModal';
        const modal = bootstrap.Modal.getInstance(document.querySelector(modalId));
        if (modal) modal.hide();

        // Show success
        const msg = action === 'approve' 
            ? 'Request approved successfully!' 
            : 'Request rejected!';
        toastr.success(msg);

        // Find and remove item
        const items = document.querySelectorAll('.approval-item');
        items.forEach(item => {
            if (item.dataset.id === id && item.dataset.type === type) {
                item.style.opacity = '0.3';
                item.style.pointerEvents = 'none';
                setTimeout(() => { item.style.display = 'none'; }, 500);
            }
        });

        // Update badge count
        const activeTab = document.querySelector('.tab-btn.active');
        if (activeTab) {
            const badge = activeTab.querySelector('.badge-count');
            if (badge) {
                const current = parseInt(badge.textContent) || 0;
                if (current > 0) {
                    badge.textContent = current - 1;
                }
            }
        }
    }

    // Reset modals on close
    document.querySelectorAll('.modal').forEach(modal => {
        modal.addEventListener('hidden.bs.modal', function() {
            const textareas = this.querySelectorAll('textarea');
            textareas.forEach(ta => ta.value = '');
        });
    });
</script>
@endpush