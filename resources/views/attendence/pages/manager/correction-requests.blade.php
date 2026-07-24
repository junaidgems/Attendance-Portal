@extends('attendence.layouts.app')

@section('title', 'Correction Requests')
@section('page-title', 'Correction Requests')

@push('styles')
  <link rel="stylesheet" href="{{ asset('css/employee/correction.css') }}">

@endpush

@section('content')

  
    <!-- PAGE HEADER                               -->
   
    <div class="glass-card">
        <div class="page-header">
            <h4>Your correction requests</h4>
            <p>Late, missing-punch, or wrong-time records can be corrected here.</p>
        </div>
    </div>


    <!-- TABLE + NEW REQUEST BUTTON                -->
    <div class="glass-card">

        <div class="top-actions">
            <span style="font-size:13px; color:var(--text-muted);">3 requests</span>
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
                        <th>Manager Note</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- ===== DUMMY DATA 1 ===== -->
                    <tr>
                        <td><strong>14 Jul 2026</strong></td>
                        <td>Missing Punch — IN: 09:00</td>
                        <td>Forgot to punch in</td>
                        <td><span class="status-badge pending">Pending</span></td>
                        <td class="manager-note">—</td>
                    </tr>

                    <!-- ===== DUMMY DATA 2 ===== -->
                    <tr>
                        <td><strong>12 Jul 2026</strong></td>
                        <td>Late — IN: 10:30 → 09:15</td>
                        <td>Traffic jam</td>
                        <td><span class="status-badge approved">Approved</span></td>
                        <td class="manager-note">Approved — medical emergency</td>
                    </tr>

                    <!-- ===== DUMMY DATA 3 ===== -->
                    <tr>
                        <td><strong>10 Jul 2026</strong></td>
                        <td>Early Out — OUT: 16:00 → 17:00</td>
                        <td>Doctor appointment</td>
                        <td><span class="status-badge rejected">Rejected</span></td>
                        <td class="manager-note">Insufficient proof</td>
                    </tr>

                    <!-- ===== EMPTY STATE — UNCOMMENT LATER ===== -->
                    <!--
                    <tr>
                        <td colspan="5">
                            <div class="empty-state">
                                <i class="bi bi-inbox"></i>
                                <p>No requests yet.</p>
                                <span class="sub">Submit one if a record looks wrong.</span>
                            </div>
                        </td>
                    </tr>
                    -->
                </tbody>
            </table>
        </div>

    </div>


    <!-- NEW REQUEST MODAL                         -->

    <div class="modal fade modal-glass" id="newRequestModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-pencil-square" style="color:var(--primary);"></i> New Correction
                        Request</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="correctionForm">
                        <div class="form-group">
                            <label>Date</label>
                            <input type="date" class="form-control-custom" value="{{ date('Y-m-d') }}">
                        </div>
                        <div class="form-group">
                            <label>Requested Change</label>
                            <select class="form-control-custom">
                                <option value="">Select change type...</option>
                                <option value="missing-punch">Missing Punch</option>
                                <option value="late">Late Arrival</option>
                                <option value="early-out">Early Out</option>
                                <option value="wrong-time">Wrong Time</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Current Record</label>
                            <input type="text" class="form-control-custom" placeholder="e.g. 09:00 - 17:00" value="—">
                        </div>
                        <div class="form-group">
                            <label>Requested Correction</label>
                            <input type="text" class="form-control-custom" placeholder="e.g. 09:15 - 17:00">
                        </div>
                        <div class="form-group">
                            <label>Reason</label>
                            <textarea class="form-control-custom" rows="2"
                                placeholder="Explain why you need this correction..."></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-cancel" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn-save" onclick="submitCorrection()">
                        <i class="bi bi-check2"></i> Submit Request
                    </button>
                </div>
            </div>
        </div>
    </div>

@endsection

@push('scripts')
    <script>
        function submitCorrection() {
            // Get form values
            const form = document.getElementById('correctionForm');
            const formData = new FormData(form);

            // Simple validation
            const date = formData.get('date') || document.querySelector('#correctionForm input[type="date"]').value;
            const changeType = document.querySelector('#correctionForm select').value;

            if (!changeType) {
                toastr.warning('Please select a change type.');
                return;
            }

            // Show success message
            toastr.success('Correction request submitted successfully!');

            // Close modal
            const modal = bootstrap.Modal.getInstance(document.getElementById('newRequestModal'));
            if (modal) modal.hide();

            // Reset form
            form.reset();
        }
    </script>
@endpush