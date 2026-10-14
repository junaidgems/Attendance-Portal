@extends('attendence.layouts.app')

@section('title', 'Admin Profile')
@section('page-title', 'Admin Profile')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/profile.css') }}">
@endpush

@section('content')
    @include('attendence.partials.profile-details')

    @if ($errors->any())
        <div class="profile-form-card" style="border-color:#fecaca;">
            <strong style="color:#dc2626;"><i class="bi bi-exclamation-triangle"></i> Not saved:</strong>
            <ul style="margin:6px 0 0; padding-left:18px; font-size:13px;">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="profile-page">
        {{-- NAME / EMAIL OF THE LOGIN ACCOUNT --}}
        <div class="profile-form-card">
            <div class="form-section-title">
                <i class="bi bi-pencil-square"></i> Edit Profile
            </div>
            <form method="POST" action="{{ route('admin.profile.update') }}">
                @csrf
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Full Name <span class="required">*</span></label>
                        <input type="text" name="name" class="form-input" value="{{ old('name', Auth::user()->name) }}" required maxlength="100">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Email Address <span class="required">*</span></label>
                        <input type="email" name="email" class="form-input" value="{{ old('email', Auth::user()->email) }}" required maxlength="150" autocomplete="off">
                    </div>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn-save"><i class="bi bi-check-lg"></i> Save Changes</button>
                </div>
            </form>
        </div>

        {{-- CHANGE PASSWORD --}}
        <div class="profile-form-card">
            <div class="form-section-title">
                <i class="bi bi-shield-lock"></i> Change Password
            </div>
            <form method="POST" action="{{ route('admin.profile.password') }}">
                @csrf
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Current Password <span class="required">*</span></label>
                        <div class="password-input-wrapper">
                            <input type="password" name="current_password" id="currentPassword" class="form-input" required autocomplete="current-password">
                            <button type="button" class="password-toggle" onclick="togglePassword('currentPassword')"><i class="bi bi-eye" id="currentPasswordIcon"></i></button>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">New Password <span class="required">*</span></label>
                        <div class="password-input-wrapper">
                            <input type="password" name="password" id="newPassword" class="form-input" required minlength="8" autocomplete="new-password">
                            <button type="button" class="password-toggle" onclick="togglePassword('newPassword')"><i class="bi bi-eye" id="newPasswordIcon"></i></button>
                        </div>
                        <span class="form-hint">At least 8 characters</span>
                    </div>
                    <div class="form-group full-width">
                        <label class="form-label">Confirm New Password <span class="required">*</span></label>
                        <div class="password-input-wrapper">
                            <input type="password" name="password_confirmation" id="confirmPassword" class="form-input" required minlength="8" autocomplete="new-password">
                            <button type="button" class="password-toggle" onclick="togglePassword('confirmPassword')"><i class="bi bi-eye" id="confirmPasswordIcon"></i></button>
                        </div>
                    </div>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn-save"><i class="bi bi-check-lg"></i> Update Password</button>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    function togglePassword(inputId) {
        var input = document.getElementById(inputId);
        var icon = document.getElementById(inputId + 'Icon');
        if (!input || !icon) return;
        input.type = input.type === 'password' ? 'text' : 'password';
        icon.className = input.type === 'password' ? 'bi bi-eye' : 'bi bi-eye-slash';
    }
</script>
@endpush
