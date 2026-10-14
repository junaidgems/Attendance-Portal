{{-- Read-only profile of the logged-in person, from their employee record (used by Employee / Line Manager / Manager / Director),
     or from the login account alone when there is no employee record (Admin) --}}
@php
    $user = Auth::user();
    $employee = $user->employee;
    $fullName = $employee ? trim($employee->first_name.' '.$employee->last_name) : ($user->name ?? 'User');
    $initials = strtoupper(substr($employee->first_name ?? $user->name ?? 'U', 0, 1).substr($employee->last_name ?? '', 0, 1));
    $roleLabel = $employee?->roleLabel() ?? ($user->role->display_name ?? ucwords(str_replace('_', ' ', $user->role->name ?? 'Employee')));
    $reportsTo = $employee?->manager ? trim($employee->manager->first_name.' '.$employee->manager->last_name) : '—';
    $teamSize = $employee?->isTeamLead() ? $employee->subordinates()->where('employment_status', 'active')->count() : null;
    $isActive = $employee ? strtolower((string) ($employee->employment_status ?? 'active')) === 'active' : $user->isActive();
    $statusLabel = $isActive ? 'Active' : ucfirst($employee->employment_status ?? $user->status ?? 'Inactive');
    $date = fn ($value) => $value ? \Carbon\Carbon::parse($value)->format('d M Y') : '—';
@endphp

<div class="profile-page">

    {{-- PROFILE HEADER --}}
    <div class="profile-header-card">
        <div class="profile-avatar-wrapper">
            <div class="profile-avatar-large">{{ $initials }}</div>
        </div>
        <div class="profile-info">
            <h2>{{ $fullName }}</h2>
            <p class="profile-email">{{ $employee->email ?? $user->email }}</p>
            <div>
                <span class="profile-role">
                    <i class="bi bi-person-badge"></i>
                    {{ $roleLabel }}
                </span>
                <span class="profile-status" @if(! $isActive) style="background:#fee2e2; color:#b91c1c;" @endif>
                    <i class="bi {{ $isActive ? 'bi-check-circle' : 'bi-x-circle' }}"></i>
                    {{ $statusLabel }}
                </span>
            </div>
        </div>
    </div>

    @if (! $employee)
        {{-- Login account only (Admin): no employee record to show --}}
        <div class="profile-form-card">
            <div class="form-section-title">
                <i class="bi bi-shield-lock"></i> Account
            </div>
            <div class="info-badges">
                <div class="info-badge">
                    <i class="bi bi-envelope"></i>
                    <span><strong>Email:</strong> {{ $user->email }}</span>
                </div>
                <div class="info-badge">
                    <i class="bi bi-person-badge"></i>
                    <span><strong>Role:</strong> {{ $roleLabel }}</span>
                </div>
                <div class="info-badge">
                    <i class="bi bi-calendar"></i>
                    <span><strong>Account created:</strong> {{ $date($user->created_at) }}</span>
                </div>
            </div>
            <p style="font-size:12px; color:var(--text-muted); margin:12px 0 0;">
                <i class="bi bi-info-circle"></i> This account has no employee record, so no attendance is tracked for it.
            </p>
        </div>
    @else
    {{-- EMPLOYEE INFORMATION --}}
    <div class="profile-form-card">
        <div class="form-section-title">
            <i class="bi bi-person-badge"></i> Employee Information
        </div>
        <div class="info-badges">
            <div class="info-badge">
                <i class="bi bi-person"></i>
                <span><strong>Employee ID:</strong> {{ $employee->employee_id ?? '—' }}</span>
            </div>
            <div class="info-badge">
                <i class="bi bi-building"></i>
                <span><strong>Department:</strong> {{ $employee?->department->name ?? '—' }}</span>
            </div>
            <div class="info-badge">
                <i class="bi bi-person"></i>
                <span><strong>Reports To:</strong> {{ $reportsTo }}</span>
            </div>
            @if($teamSize !== null)
                <div class="info-badge">
                    <i class="bi bi-people"></i>
                    <span><strong>Team Size:</strong> {{ $teamSize }} {{ $teamSize == 1 ? 'member' : 'members' }}</span>
                </div>
            @endif
            <div class="info-badge">
                <i class="bi bi-calendar"></i>
                <span><strong>Joining Date:</strong> {{ $date($employee->joining_date ?? null) }}</span>
            </div>
            <div class="info-badge">
                <i class="bi bi-clock"></i>
                <span><strong>Shift:</strong> {{ $employee?->shift->name ?? 'Standard Shift' }}</span>
            </div>
            @if ($employee?->isOnProbation())
                <div class="info-badge" style="border-color:#fcd34d;">
                    <i class="bi bi-hourglass-split" style="color:#b45309;"></i>
                    <span><strong>Probation until:</strong> {{ $date($employee->probation_until) }} (no paid leave till then)</span>
                </div>
            @endif
            @if ($employee?->offDays())
                <div class="info-badge">
                    <i class="bi bi-moon"></i>
                    <span><strong>Weekly off:</strong> {{ implode(', ', $employee->offDays()) }}</span>
                </div>
            @endif
            @if ($employee?->wfhDays())
                <div class="info-badge">
                    <i class="bi bi-house-door"></i>
                    <span><strong>Work From Home days:</strong> {{ implode(', ', $employee->wfhDays()) }}</span>
                </div>
            @endif
        </div>
    </div>

    {{-- PERSONAL INFORMATION (maintained by Admin) --}}
    <div class="profile-form-card">
        <div class="form-section-title">
            <i class="bi bi-person"></i> Personal Information
        </div>
        <div class="form-grid">
            <div class="form-group">
                <label class="form-label">Full Name</label>
                <input type="text" class="form-input readonly" value="{{ $fullName }}" disabled>
            </div>
            <div class="form-group">
                <label class="form-label">Email Address</label>
                <input type="text" class="form-input readonly" value="{{ $employee->email ?? $user->email }}" disabled>
            </div>
            <div class="form-group">
                <label class="form-label">Phone Number</label>
                <input type="text" class="form-input readonly" value="{{ $employee->phone ?? '—' }}" disabled>
            </div>
            <div class="form-group">
                <label class="form-label">CNIC</label>
                <input type="text" class="form-input readonly" value="{{ $employee->cnic ?? '—' }}" disabled>
            </div>
            <div class="form-group">
                <label class="form-label">Job Title</label>
                <input type="text" class="form-input readonly" value="{{ $employee->job_title ?? '—' }}" disabled>
            </div>
            <div class="form-group">
                <label class="form-label">Gender</label>
                <input type="text" class="form-input readonly" value="{{ $employee->gender ? ucfirst($employee->gender) : '—' }}" disabled>
            </div>
            <div class="form-group">
                <label class="form-label">Date of Birth</label>
                <input type="text" class="form-input readonly" value="{{ $date($employee->date_of_birth ?? null) }}" disabled>
            </div>
            <div class="form-group">
                <label class="form-label">Emergency Contact</label>
                <input type="text" class="form-input readonly" value="{{ $employee->emergency_contact ?? '—' }}" disabled>
            </div>
            <div class="form-group full-width">
                <label class="form-label">Address</label>
                <input type="text" class="form-input readonly" value="{{ $employee->address ?? '—' }}" disabled>
            </div>
        </div>
        <p style="font-size:12px; color:var(--text-muted); margin:12px 0 0;">
            <i class="bi bi-info-circle"></i> To change any of these details, please contact HR / Admin.
        </p>
    </div>
    @endif

</div>
