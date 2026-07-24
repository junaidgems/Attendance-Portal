@extends('attendence.layouts.app')

@section('title', 'Work Shifts')
@section('page-title', 'Work Shifts')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/shifts.css') }}">
@endpush

@section('content')

<!-- ========================================== -->
<!-- SHIFTS LIST                               -->
<!-- ========================================== -->
<div class="row">
    <div class="col-12">
        <div class="glass-card">
            <div class="section-title"><i class="bi bi-clock"></i> Work Shifts</div>
            <p class="section-sub">1 shift</p>

            <div style="overflow-x:auto;">
                <table class="shift-table">
                    <thead>
                        <tr>
                            <th>Shift</th>
                            <th>Working Hours</th>
                            <th>Span</th>
                            <th>Assigned</th>
                            <th style="text-align:right;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>
                                <strong>Standard Shift</strong>
                                <br>
                                <span class="shift-default">Default</span>
                            </td>
                            <td class="shift-hours">09:00 – 17:00</td>
                            <td>8h</td>
                            <td>2 employees</td>
                            <td style="text-align:right;">
                                <button class="btn-outline-glass" data-bs-toggle="modal" data-bs-target="#editShiftModal">
                                    <i class="bi bi-pencil"></i> Edit
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div style="margin-top:16px;">
                <button class="btn-primary-glass" data-bs-toggle="modal" data-bs-target="#addShiftModal">
                    <i class="bi bi-plus"></i> Add shift
                </button>
            </div>

            <div class="info-box">
                <i class="bi bi-info-circle"></i>
                The <strong>Standard Shift</strong> applies to every employee by default and shares its hours with the Attendance Policy. Add shifts for staff with different timings, then assign one to an employee from the <strong>Employees</strong> screen. Attendance (late, early leave, overtime) is calculated against each employee's assigned shift.
            </div>
        </div>
    </div>
</div>

<!-- ========================================== -->
<!-- ADD SHIFT MODAL                           -->
<!-- ========================================== -->
<div class="modal fade modal-glass" id="addShiftModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-plus-circle" style="color:var(--primary);"></i> Add shift</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form>
                    <div class="form-group">
                        <label>Shift Name</label>
                        <input type="text" class="form-control-custom" placeholder="e.g. Night Shift">
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Check-In Time</label>
                                <input type="time" class="form-control-custom" value="09:00">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Check-Out Time</label>
                                <input type="time" class="form-control-custom" value="18:00">
                            </div>
                        </div>
                    </div>
                    <div class="info-box" style="margin-top:8px;">
                        <i class="bi bi-info-circle"></i>
                        Assign this shift to employees from the <strong>Employees</strong> screen.
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-outline-glass" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn-primary-glass" onclick="toastr.success('Shift added successfully!'); bootstrap.Modal.getInstance(document.getElementById('addShiftModal')).hide();">
                    <i class="bi bi-check2"></i> Add shift
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ========================================== -->
<!-- EDIT SHIFT MODAL                          -->
<!-- ========================================== -->
<div class="modal fade modal-glass" id="editShiftModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-pencil" style="color:var(--primary);"></i> Edit standard shift</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form>
                    <div class="form-group">
                        <label>Shift Name</label>
                        <input type="text" class="form-control-custom" value="Standard Shift">
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Check-In Time</label>
                                <input type="time" class="form-control-custom" value="09:00">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Check-Out Time</label>
                                <input type="time" class="form-control-custom" value="17:00">
                            </div>
                        </div>
                    </div>
                    <div class="info-box" style="margin-top:8px;">
                        <i class="bi bi-info-circle"></i>
                        These hours also update the default schedule in <strong>Attendance Policy</strong>.
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-outline-glass" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn-primary-glass" onclick="toastr.success('Shift updated successfully!'); bootstrap.Modal.getInstance(document.getElementById('editShiftModal')).hide();">
                    <i class="bi bi-check2"></i> Save changes
                </button>
            </div>
        </div>
    </div>
</div>

@endsection