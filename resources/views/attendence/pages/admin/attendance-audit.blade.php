@extends('attendence.layouts.app')

@section('title', 'Attendance Audit')
@section('page-title', 'Attendance Audit')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/attendence-audit.css') }}">
@endpush

@section('content')


    <!-- FILTER BAR  -->
    <div class="row">
        <div class="col-12">
            <div class="glass-card">
                <form method="GET" action="{{ route('admin.attendance.audit') }}">
                    <div class="filter-bar">

                        <div class="form-group">
                            <label>From</label>
                            <input type="date" class="form-control" name="from" value="2026-06-26">
                        </div>

                        <div class="form-group">
                            <label>To</label>
                            <input type="date" class="form-control" name="to" value="2026-07-20">
                        </div>

                        <div class="form-group">
                            <label>Department</label>
                            <select class="form-control" name="department">
                                <option value="all">All departments</option>
                                <option value="ICT">ICT</option>
                                <option value="Engineering">Engineering</option>
                                <option value="HR">HR</option>
                                <option value="Finance">Finance</option>
                                <option value="Marketing">Marketing</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Status</label>
                            <select class="form-control" name="status">
                                <option value="all">All statuses</option>
                                <option value="Present">Present</option>
                                <option value="Absent">Absent</option>
                                <option value="Late">Late</option>
                                <option value="Early Leave">Early Leave</option>
                                <option value="Holiday">Holiday</option>
                            </select>
                        </div>

                        <div class="form-group" style="flex: 1.5;">
                            <label>Search</label>
                            <input type="text" class="form-control" name="search" placeholder="Name or Employee ID">
                        </div>

                        <div class="btn-group">
                            <button type="submit" class="btn-primary-glass">
                                <i class="bi bi-funnel"></i> Filter
                            </button>
                            <a href="{{ route('admin.attendance.audit') }}" class="btn-outline-glass">
                                <i class="bi bi-arrow-counterclockwise"></i> Clear
                            </a>

                        </div>

                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- TABLE WITH STATIC DUMMY DATA    -->
    <div class="row" style="margin-top: 16px;">
        <div class="col-12">
            <div class="glass-card">

                <div class="section-header">
                    <div>
                        <h6>Attendance Audit</h6>
                        <span class="subtitle">7 rows</span>
                    </div>
                    <div class="btn-group">

                        <button class="btn-primary-glass" onclick="alert('Export functionality coming soon!')">
                            <i class="bi bi-download"></i> Export
                        </button>
                    </div>

                </div>

                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Emp ID</th>
                                <th>Employee</th>
                                <th>Dept</th>
                                <th>Designation</th>
                                <th>Date</th>
                                <th>Shift</th>
                                <th>Sch In</th>
                                <th>Sch Out</th>
                                <th>First In</th>
                                <th>Last Out</th>
                                <th>Total Hrs</th>
                                <th>Late (min)</th>
                                <th>Early Leave</th>
                                <th>OT (hrs)</th>
                                <th>Status</th>
                                <th>Machine</th>
                                <th>Verification</th>
                                <th>Remarks</th>
                            </tr>
                        </thead>
                        <tbody>

                            <!-- Row 1 -->
                            <tr>
                                <td>1</td>
                                <td><strong>GP-1002</strong></td>
                                <td>Junaid</td>
                                <td>ICT</td>
                                <td>Frontend Developer</td>
                                <td>26-Jun-2026</td>
                                <td>Standard Shift</td>
                                <td>09:00</td>
                                <td>17:00</td>
                                <td>—</td>
                                <td>—</td>
                                <td>—</td>
                                <td>0</td>
                                <td>0</td>
                                <td>0</td>
                                <td><span class="status-badge absent">Absent</span></td>
                                <td>—</td>
                                <td>No attendance record</td>
                                <td>—</td>
                            </tr>

                            <!-- Row 2 -->
                            <tr>
                                <td>2</td>
                                <td><strong>GP-1002</strong></td>
                                <td>Junaid</td>
                                <td>ICT</td>
                                <td>Frontend Developer</td>
                                <td>27-Jun-2026</td>
                                <td>Standard Shift</td>
                                <td>09:00</td>
                                <td>17:00</td>
                                <td>—</td>
                                <td>—</td>
                                <td>—</td>
                                <td>0</td>
                                <td>0</td>
                                <td>0</td>
                                <td><span class="status-badge holiday">Holiday</span></td>
                                <td>—</td>
                                <td>Weekly holiday</td>
                                <td>—</td>
                            </tr>

                            <!-- Row 3 -->
                            <tr>
                                <td>3</td>
                                <td><strong>GP-1002</strong></td>
                                <td>Junaid</td>
                                <td>ICT</td>
                                <td>Frontend Developer</td>
                                <td>28-Jun-2026</td>
                                <td>Standard Shift</td>
                                <td>09:00</td>
                                <td>17:00</td>
                                <td>—</td>
                                <td>—</td>
                                <td>—</td>
                                <td>0</td>
                                <td>0</td>
                                <td>0</td>
                                <td><span class="status-badge holiday">Holiday</span></td>
                                <td>—</td>
                                <td>Weekly holiday</td>
                                <td>—</td>
                            </tr>

                            <!-- Row 4 -->
                            <tr>
                                <td>4</td>
                                <td><strong>GP-1001</strong></td>
                                <td>Faraz Siddiqui</td>
                                <td>ICT</td>
                                <td>System Administrator</td>
                                <td>26-Jun-2026</td>
                                <td>Standard Shift</td>
                                <td>09:00</td>
                                <td>17:00</td>
                                <td>09:05</td>
                                <td>17:30</td>
                                <td>8.5</td>
                                <td>5</td>
                                <td>0</td>
                                <td>0.5</td>
                                <td><span class="status-badge present">Present</span></td>
                                <td>BIO-001</td>
                                <td>Biometric Verified</td>
                                <td>—</td>
                            </tr>

                            <!-- Row 5 -->
                            <tr>
                                <td>5</td>
                                <td><strong>GP-1003</strong></td>
                                <td>John Doe</td>
                                <td>Engineering</td>
                                <td>Engineering Manager</td>
                                <td>26-Jun-2026</td>
                                <td>Standard Shift</td>
                                <td>09:00</td>
                                <td>17:00</td>
                                <td>09:45</td>
                                <td>17:10</td>
                                <td>7.5</td>
                                <td>45</td>
                                <td>0</td>
                                <td>0</td>
                                <td><span class="status-badge late">Late</span></td>
                                <td>BIO-002</td>
                                <td>Biometric Verified</td>
                                <td>Traffic jam</td>
                            </tr>

                            <!-- Row 6 -->
                            <tr>
                                <td>6</td>
                                <td><strong>GP-1004</strong></td>
                                <td>Jane Smith</td>
                                <td>HR</td>
                                <td>HR Executive</td>
                                <td>26-Jun-2026</td>
                                <td>Standard Shift</td>
                                <td>09:00</td>
                                <td>17:00</td>
                                <td>09:00</td>
                                <td>16:30</td>
                                <td>7.5</td>
                                <td>0</td>
                                <td>30</td>
                                <td>0</td>
                                <td><span class="status-badge early-leave">Early Leave</span></td>
                                <td>BIO-003</td>
                                <td>Biometric Verified</td>
                                <td>Doctor appointment</td>
                            </tr>

                            <!-- Row 7 -->
                            <tr>
                                <td>7</td>
                                <td><strong>GP-1005</strong></td>
                                <td>Mike Johnson</td>
                                <td>Finance</td>
                                <td>Finance Analyst</td>
                                <td>26-Jun-2026</td>
                                <td>Standard Shift</td>
                                <td>09:00</td>
                                <td>17:00</td>
                                <td>08:55</td>
                                <td>18:00</td>
                                <td>9.0</td>
                                <td>0</td>
                                <td>0</td>
                                <td>1.0</td>
                                <td><span class="status-badge present">Present</span></td>
                                <td>BIO-004</td>
                                <td>Biometric Verified</td>
                                <td>—</td>
                            </tr>

                        </tbody>
                    </table>
                </div>

            </div>
        </div>
    </div>

@endsection