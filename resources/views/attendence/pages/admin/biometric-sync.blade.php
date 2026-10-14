@extends('attendence.layouts.app')

@section('title', 'Biometric Sync')
@section('page-title', 'Biometric Sync')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/biometric.css') }}">
@endpush

@section('content')

    <!-- DEVICE INFO CARD                          -->
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
                        <div class="value">{{ $scheduledSyncTime }}</div>
                    </div>
                    <div class="info-item">
                        <div class="label">Enrolled employees</div>
                        <div class="value">{{ $enrolledCount }}</div>
                    </div>
                    <div class="info-item">
                        <div class="label">Last manual pull</div>
                        <div class="value">{{ $lastManualPull }}</div>
                    </div>
                </div>

                <div style="display:flex; flex-wrap:wrap; gap:12px; align-items:center;">
                    <button id="syncNowBtn" class="btn-primary-glass" onclick="runDeviceSync()">
                        <i class="bi bi-arrow-repeat"></i> <span id="syncBtnText">Run sync now</span>
                    </button>
                    <span id="syncStatusMsg" style="font-size:12px; color:var(--text-muted);">
                        Pulls today's punch logs from the device and maps them to employee records by ID.
                        Already-recorded employees are skipped.
                    </span>
                </div>

                <!-- DEVICE CLOCK -->
                <div class="info-grid" style="margin-top:16px;">
                    <div class="info-item">
                        <div class="label">Device time</div>
                        <div class="value" id="deviceClockDevice">
                            <button type="button" class="btn-outline-glass" onclick="checkDeviceClock()"><i class="bi bi-search"></i> Check device clock</button>
                        </div>
                    </div>
                    <div class="info-item">
                        <div class="label">Our time (Pakistan)</div>
                        <div class="value" id="deviceClockOffice">—</div>
                    </div>
                    <div class="info-item">
                        <div class="label">Difference</div>
                        <div class="value" id="deviceClockDiff">—</div>
                    </div>
                    <div class="info-item" style="display:flex; align-items:center;">
                        <form method="POST" action="{{ route('admin.biometric.device-clock') }}" style="margin:0;"
                            onsubmit="return confirm('Set the device clock to our time (Pakistan) now?');">
                            @csrf
                            <button type="submit" class="btn-outline-glass"><i class="bi bi-clock"></i> Set device clock</button>
                        </form>
                    </div>
                </div>
                <p style="font-size:12px; color:var(--text-muted); margin:8px 0 0;">
                    <i class="bi bi-info-circle"></i> The device clock is only read or set when you press these buttons (reading or
                    setting it makes the device show "Authentication failure" on its screen for a moment). The automatic sync never touches it.
                </p>

                <script>
                    // Device time is only read on request: every read shows "Authentication failure" on the device screen
                    function checkDeviceClock() {
                        document.getElementById('deviceClockDevice').textContent = 'Checking…';
                        fetch(@json(route('admin.biometric.device-clock.status')), { headers: { 'Accept': 'application/json' } })
                            .then(response => response.json())
                            .then(data => {
                                if (!data.success) {
                                    document.getElementById('deviceClockDevice').innerHTML = '<span style="color:#b91c1c;">Not answering</span>';
                                    return;
                                }
                                document.getElementById('deviceClockDevice').textContent = data.device;
                                document.getElementById('deviceClockOffice').textContent = data.office;
                                document.getElementById('deviceClockDiff').innerHTML = '<span style="display:inline-block; padding:3px 10px; border-radius:20px; font-size:12px; font-weight:600; '
                                    + (data.ok ? 'background:#dcfce7; color:#15803d;' : 'background:#fee2e2; color:#b91c1c;') + '">' + data.difference + '</span>';
                            })
                            .catch(() => {
                                document.getElementById('deviceClockDevice').innerHTML = '<span style="color:#b91c1c;">Not answering</span>';
                            });
                    }
                </script>

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
                        <span class="subtitle">{{ now()->format('D d M') }}</span>
                    </div>
                </div>

                <div class="sync-status-grid">
                    <div class="sync-status-item">
                        <div class="number" style="color:#059669;">{{ $presentCount }}</div>
                        <div class="label">Present</div>
                    </div>
                    <div class="sync-status-item">
                        <div class="number" style="color:#3b82f6;">{{ $mappedCount }}</div>
                        <div class="label">Mapped</div>
                    </div>
                    <div class="sync-status-item">
                        <div class="number" style="color:#f59e0b;">{{ $awaitingCount }}</div>
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
                            @forelse($employeeRows as $row)
                                <tr>
                                    <td>
                                        <span style="font-weight:600; color:var(--text-primary);">{{ $row['name'] }}</span>
                                        <br>
                                        <span style="font-size:11px; color:var(--text-muted);">{{ $row['title'] }}</span>
                                    </td>
                                    <td><strong>{{ $row['emp_id'] }}</strong></td>
                                    <td>{{ $row['device_record'] }}</td>
                                    <td>
                                        @if($row['status'] === 'Mapped')
                                            <span class="status-badge-online" style="font-size:11px; padding:2px 12px;"><i
                                                    class="bi bi-check-circle-fill"></i> Mapped</span>
                                        @else
                                            <span
                                                style="background:#fef3c7; color:#d97706; padding:2px 12px; border-radius:12px; font-size:11px; font-weight:600; display:inline-block;"><i
                                                    class="bi bi-clock"></i> Awaiting</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" style="text-align:center; padding:20px;">Koi employee device se link nahi
                                        hai.</td>
                                </tr>
                            @endforelse
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
                            @forelse($syncLogs as $log)
                                <tr>
                                    <td>{{ \Carbon\Carbon::parse($log->created_at)->format('d M H:i') }}</td>
                                    <td><span class="status-badge-online"
                                            style="font-size:11px; padding:2px 12px;">{{ $log->action }}</span></td>
                                    <td>{{ $log->detail }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" style="text-align:center; padding:20px;">Koi sync activity nahi mili.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

            </div>
        </div>
    </div>
    @push('scripts')
        <script>
            function runDeviceSync() {
                const btn = document.getElementById('syncNowBtn');
                const btnText = document.getElementById('syncBtnText');
                const statusMsg = document.getElementById('syncStatusMsg');
                const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

                // Button disable karo aur loading state dikhao
                btn.disabled = true;
                btn.style.opacity = '0.7';
                btnText.textContent = 'Syncing...';
                statusMsg.textContent = 'Fetching data from device...';
                statusMsg.style.color = 'var(--text-muted)';

                fetch('{{ route("admin.biometric.sync.run") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                    },
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            statusMsg.textContent = data.message;
                            statusMsg.style.color = '#059669';
                            // Reload so the new data shows in the table
                            setTimeout(() => location.reload(), 1200);
                        } else {
                            statusMsg.textContent = data.message || 'Sync failed. Please try again.';
                            statusMsg.style.color = '#dc2626';
                            btn.disabled = false;
                            btn.style.opacity = '1';
                            btnText.textContent = 'Run sync now';
                        }
                    })
                    .catch(err => {
                        statusMsg.textContent = 'Network error: ' + err.message;
                        statusMsg.style.color = '#dc2626';
                        btn.disabled = false;
                        btn.style.opacity = '1';
                        btnText.textContent = 'Run sync now';
                    });
            }
        </script>
    @endpush
@endsection