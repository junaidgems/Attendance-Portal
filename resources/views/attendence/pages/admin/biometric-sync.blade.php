@extends('attendence.layouts.app')

@section('title', 'Biometric Sync')
@section('page-title', 'Biometric Sync')

@push('styles')
   <link rel="stylesheet" href="{{ asset('css/biometric.css') }}">
@endpush

@section('content')

    <!-- ========================================== -->
    <!-- DEVICE INFO CARD                          -->
    <!-- ========================================== -->
    <div class="row">
        <div class="col-12">
            <div class="glass-card">

                <div class="device-header">
                    <div>
                        <h5><i class="bi bi-fingerprint" style="color:var(--primary);"></i> ZKTeco F18 - Main Entrance</h5>
                        <span class="device-id">Device ID BIO-KHI-01 · Firmware 6.60 · Auto-sync enabled</span>
                    </div>
                    <span class="status-badge-online"><i class="bi bi-circle-fill"></i> Online</span>
                </div>

                <div class="info-grid">
                    <div class="info-item">
                        <div class="label">Mapping key</div>
                        <div class="value">Unique Employee ID</div>
                    </div>
                    <div class="info-item">
                        <div class="label">Scheduled daily sync</div>
                        <div class="value">18:30</div>
                    </div>
                    <div class="info-item">
                        <div class="label">Enrolled employees</div>
                        <div class="value">1</div>
                    </div>
                    <div class="info-item">
                        <div class="label">Last manual pull</div>
                        <div class="value">20 Jul 13:54</div>
                    </div>
                </div>

                <div style="display:flex; flex-wrap:wrap; gap:12px; align-items:center;">
                    <button class="btn-primary-glass" onclick="alert('Sync started! Pulling punch logs...')">
                        <i class="bi bi-arrow-repeat"></i> Run sync now
                    </button>
                    <span style="font-size:12px; color:var(--text-muted);">
                        Simulates pulling today's punch logs from the device and mapping them to employee records by ID.
                        Already-recorded employees are skipped.
                    </span>
                </div>

            </div>
        </div>
    </div>


    <!-- TODAY'S SYNC STATUS                       -->

    <div class="row" style="margin-top: 20px;">
        <div class="col-12">
            <div class="glass-card">

                <div class="section-header">
                    <div>
                        <h6>Today's Sync Status</h6>
                        <span class="subtitle">Mon 20 Jul</span>
                    </div>
                </div>

                <div class="sync-status-grid">
                    <div class="sync-status-item">
                        <div class="number" style="color:#059669;">1</div>
                        <div class="label">Present</div>
                    </div>
                    <div class="sync-status-item">
                        <div class="number" style="color:#3b82f6;">1</div>
                        <div class="label">Mapped</div>
                    </div>
                    <div class="sync-status-item">
                        <div class="number" style="color:#f59e0b;">0</div>
                        <div class="label">Awaiting</div>
                    </div>
                </div>

                <!-- ===== EMPLOYEE TABLE ===== -->
                <div style="margin-top: 12px; overflow-x:auto;">
                    <table class="sync-employee-table">
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>ID</th>
                                <th>Device Record</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>
                                    <span style="font-weight:600; color:var(--text-primary);">Junaid</span>
                                    <br>
                                    <span style="font-size:11px; color:var(--text-muted);">Frontend Developer</span>
                                </td>
                                <td><strong>GP-1002</strong></td>
                                <td>09:19 - 18:00</td>
                                <td><span class="status-badge-online" style="font-size:11px; padding:2px 12px;"><i
                                            class="bi bi-check-circle-fill"></i> Mapped</span></td>
                            </tr>
                            <tr>
                                <td>
                                    <span style="font-weight:600; color:var(--text-primary);">Faraz Siddiqui</span>
                                    <br>
                                    <span style="font-size:11px; color:var(--text-muted);">System Administrator</span>
                                </td>
                                <td><strong>GP-1001</strong></td>
                                <td>09:05 - 17:30</td>
                                <td><span class="status-badge-online" style="font-size:11px; padding:2px 12px;"><i
                                            class="bi bi-check-circle-fill"></i> Mapped</span></td>
                            </tr>
                            <tr>
                                <td>
                                    <span style="font-weight:600; color:var(--text-primary);">John Doe</span>
                                    <br>
                                    <span style="font-size:11px; color:var(--text-muted);">Engineering Manager</span>
                                </td>
                                <td><strong>GP-1003</strong></td>
                                <td>—</td>
                                <td><span
                                        style="background:#fef3c7; color:#d97706; padding:2px 12px; border-radius:12px; font-size:11px; font-weight:600; display:inline-block;"><i
                                            class="bi bi-clock"></i> Awaiting</span></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div style="margin-top: 10px; font-size:12px; color:var(--text-muted);">
                    <i class="bi bi-info-circle"></i> Showing employees synced today from biometric device
                </div>

            </div>
        </div>
    </div>


    <!-- SYNC ACTIVITY LOG                          -->

    <div class="row" style="margin-top: 20px;">
        <div class="col-12">
            <div class="glass-card">

                <div class="section-header">
                    <div>
                        <h6>Sync Activity Log</h6>
                        <span class="subtitle">Last 7 days</span>
                    </div>
                </div>

                <div style="overflow-x:auto;">
                    <table class="log-table">
                        <thead>
                            <tr>
                                <th>Time</th>
                                <th>Event</th>
                                <th>Detail</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>20 Jul 13:54</td>
                                <td><span class="status-badge-online" style="font-size:11px; padding:2px 12px;">Biometric
                                        sync</span></td>
                                <td>Pulled 1 new punch record(s) from BIO-KHI-01</td>
                            </tr>
                            <tr>
                                <td>19 Jul 18:30</td>
                                <td><span class="status-badge-online" style="font-size:11px; padding:2px 12px;">Scheduled
                                        sync</span></td>
                                <td>Pulled 0 new punch record(s) from BIO-KHI-01</td>
                            </tr>
                            <tr>
                                <td>18 Jul 18:30</td>
                                <td><span class="status-badge-online" style="font-size:11px; padding:2px 12px;">Scheduled
                                        sync</span></td>
                                <td>Pulled 0 new punch record(s) from BIO-KHI-01</td>
                            </tr>
                            <tr>
                                <td>17 Jul 13:45</td>
                                <td><span class="status-badge-online" style="font-size:11px; padding:2px 12px;">Manual
                                        sync</span></td>
                                <td>Pulled 3 new punch record(s) from BIO-KHI-01</td>
                            </tr>
                            <tr>
                                <td>16 Jul 18:30</td>
                                <td><span class="status-badge-online" style="font-size:11px; padding:2px 12px;">Scheduled
                                        sync</span></td>
                                <td>Pulled 0 new punch record(s) from BIO-KHI-01</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

            </div>
        </div>
    </div>

@endsection