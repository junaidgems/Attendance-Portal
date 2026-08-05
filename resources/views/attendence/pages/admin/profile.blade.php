@extends('attendence.layouts.app')

@section('title', 'Admin Profile')
@section('page-title', 'Admin Profile')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/profile.css') }}">
@endpush

@section('content')
<div class="profile-page">

    {{-- PROFILE HEADER --}}
    <div class="profile-header-card">
        <div class="profile-avatar-wrapper">
            <div class="profile-avatar-large" id="profileAvatar">
                {{ substr(Auth::user()->name ?? 'AD', 0, 2) }}
            </div>
            <label class="avatar-upload-btn">
                <i class="bi bi-camera"></i>
                <input type="file" accept="image/*" onchange="uploadImage(this)">
            </label>
        </div>
        <div class="profile-info">
            <h2 id="displayName">{{ Auth::user()->name ?? 'Admin User' }}</h2>
            <p class="profile-email" id="displayEmail">{{ Auth::user()->email ?? 'admin@company.com' }}</p>
            <div>
                <span class="profile-role"><i class="bi bi-shield-lock"></i> Admin</span>
                <span class="profile-status"><i class="bi bi-check-circle"></i> Active</span>
            </div>
        </div>
    </div>

    {{-- INFO BADGES --}}
    <div class="profile-form-card">
        <div class="form-section-title"><i class="bi bi-person-badge"></i> Admin Information</div>
        <div class="info-badges">
            <div class="info-badge">
                <i class="bi bi-person"></i>
                <span><strong>Admin ID:</strong> ADM-2024-001</span>
            </div>
            <div class="info-badge">
                <i class="bi bi-building"></i>
                <span><strong>Department:</strong> HR & Administration</span>
            </div>
            <div class="info-badge">
                <i class="bi bi-calendar"></i>
                <span><strong>Joined:</strong> Jan 2024</span>
            </div>
            <div class="info-badge">
                <i class="bi bi-clock"></i>
                <span><strong>Last Login:</strong> Today, 10:30 AM</span>
            </div>
        </div>
    </div>

    {{-- PERSONAL INFORMATION --}}
    <div class="profile-form-card">
        <div class="form-section-title"><i class="bi bi-person"></i> Personal Information</div>
        <form id="profileForm">
            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label">Full Name <span class="required">*</span></label>
                    <input type="text" class="form-input" id="fullName" value="{{ Auth::user()->name ?? 'Admin User' }}">
                </div>
                <div class="form-group">
                    <label class="form-label">Email <span class="required">*</span></label>
                    <input type="email" class="form-input" id="emailAddress" value="{{ Auth::user()->email ?? 'admin@company.com' }}">
                </div>
                <div class="form-group">
                    <label class="form-label">Phone</label>
                    <input type="text" class="form-input" id="phoneNumber" value="+92 300 1234567">
                </div>
                <div class="form-group">
                    <label class="form-label">Role</label>
                    <input type="text" class="form-input readonly" value="Admin" disabled>
                </div>
                <div class="form-group full-width">
                    <label class="form-label">Bio</label>
                    <textarea class="form-input" id="userBio" rows="3">System administrator with 5+ years of experience.</textarea>
                </div>
            </div>
            <div class="form-actions">
                <button type="button" class="btn-save" onclick="saveProfile()"><i class="bi bi-check-lg"></i> Save</button>
                <button type="button" class="btn-cancel" onclick="resetProfile()"><i class="bi bi-arrow-counterclockwise"></i> Reset</button>
            </div>
        </form>
    </div>

    {{-- CHANGE PASSWORD --}}
    <div class="profile-form-card">
        <div class="form-section-title"><i class="bi bi-shield-lock"></i> Change Password</div>
        <form id="passwordForm">
            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label">Current Password <span class="required">*</span></label>
                    <div class="password-input-wrapper">
                        <input type="password" class="form-input" id="currentPassword" placeholder="Enter current password">
                        <button type="button" class="password-toggle" onclick="togglePassword('currentPassword')">
                            <i class="bi bi-eye" id="currentPasswordIcon"></i>
                        </button>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">New Password <span class="required">*</span></label>
                    <div class="password-input-wrapper">
                        <input type="password" class="form-input" id="newPassword" placeholder="Enter new password">
                        <button type="button" class="password-toggle" onclick="togglePassword('newPassword')">
                            <i class="bi bi-eye" id="newPasswordIcon"></i>
                        </button>
                    </div>
                    <span class="form-hint">Minimum 8 characters</span>
                </div>
                <div class="form-group full-width">
                    <label class="form-label">Confirm Password <span class="required">*</span></label>
                    <div class="password-input-wrapper">
                        <input type="password" class="form-input" id="confirmPassword" placeholder="Confirm password">
                        <button type="button" class="password-toggle" onclick="togglePassword('confirmPassword')">
                            <i class="bi bi-eye" id="confirmPasswordIcon"></i>
                        </button>
                    </div>
                </div>
            </div>
            <div class="form-actions">
                <button type="button" class="btn-save" onclick="changePassword()"><i class="bi bi-check-lg"></i> Update Password</button>
            </div>
        </form>
    </div>

    {{-- ACCOUNT ACTIONS --}}
    <div class="profile-form-card" style="border-color: #fee2e2;">
        <div class="form-section-title" style="color: #dc2626;"><i class="bi bi-exclamation-triangle"></i> Account Actions</div>
        <div class="account-actions">
            <button class="btn-danger" onclick="deactivateAccount()"><i class="bi bi-person-x"></i> Deactivate</button>
            <button class="btn-danger" onclick="deleteAccount()"><i class="bi bi-trash"></i> Delete</button>
        </div>
    </div>

</div>





<script>

    // ========================================== //
    // ✅ TOGGLE PASSWORD VISIBILITY             //
    // ========================================== //

    function togglePassword(inputId) {
        var input = document.getElementById(inputId);
        var icon = document.getElementById(inputId + 'Icon');
        
        if (!input || !icon) {
            return;
        }

        if (input.type === 'password') {
            input.type = 'text';
            icon.className = 'bi bi-eye-slash';
        } else {
            input.type = 'password';
            icon.className = 'bi bi-eye';
        }
    }

    // ========================================== //
    // UPLOAD PROFILE IMAGE                      //
    // ========================================== //

    function uploadImage(input) {
        if (input.files && input.files[0]) {
            var reader = new FileReader();
            reader.onload = function(e) {
                var avatar = document.getElementById('profileAvatar');
                avatar.innerHTML = '<img src="' + e.target.result + '" alt="Profile">';
                avatar.style.background = 'none';
                avatar.style.padding = '0';
            };
            reader.readAsDataURL(input.files[0]);
            showToast('success', '📸 Profile image uploaded successfully!');
        }
    }

    // ========================================== //
    // SAVE PROFILE                              //
    // ========================================== //

    function saveProfile() {
        var name = document.getElementById('fullName').value.trim();
        var email = document.getElementById('emailAddress').value.trim();

        if (!name || !email) {
            showToast('error', '❌ Please fill all required fields!');
            return;
        }

        var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(email)) {
            showToast('error', '❌ Please enter a valid email!');
            return;
        }

        document.getElementById('displayName').textContent = name;
        document.getElementById('displayEmail').textContent = email;

        showToast('success', '✅ Profile updated successfully!');
    }

    // ========================================== //
    // RESET PROFILE                             //
    // ========================================== //

    function resetProfile() {
        document.getElementById('fullName').value = '{{ Auth::user()->name ?? 'Employee User' }}';
        document.getElementById('emailAddress').value = '{{ Auth::user()->email ?? 'employee@company.com' }}';
        document.getElementById('phoneNumber').value = '+92 300 1234567';
        document.getElementById('designation').value = 'Software Engineer';
        document.getElementById('department').value = 'IT Department';
        document.getElementById('userBio').value = 'Passionate software engineer with 3+ years of experience in web development.';
        showToast('info', '🔄 Profile reset to original values');
    }

    // ========================================== //
    // CHANGE PASSWORD                           //
    // ========================================== //

    function changePassword() {
        var current = document.getElementById('currentPassword').value;
        var newPass = document.getElementById('newPassword').value;
        var confirm = document.getElementById('confirmPassword').value;

        if (!current || !newPass || !confirm) {
            showToast('error', '❌ Please fill all password fields!');
            return;
        }

        if (newPass.length < 8) {
            showToast('error', '❌ New password must be at least 8 characters!');
            return;
        }

        if (newPass !== confirm) {
            showToast('error', '❌ Passwords do not match!');
            return;
        }

        showToast('success', '🔒 Password changed successfully!');

        document.getElementById('currentPassword').value = '';
        document.getElementById('newPassword').value = '';
        document.getElementById('confirmPassword').value = '';
    }

    // ========================================== //
    // DEACTIVATE ACCOUNT                        //
    // ========================================== //

    function deactivateAccount() {
        if (confirm('Are you sure you want to deactivate your account? You can reactivate later.')) {
            showToast('warning', '⚠️ Account deactivated!');
        }
    }

    // ========================================== //
    // DELETE ACCOUNT                            //
    // ========================================== //

    function deleteAccount() {
        if (confirm('⚠️ Are you sure you want to permanently delete your account? This action cannot be undone!')) {
            showToast('error', '🗑️ Account deleted permanently!');
        }
    }

    // ========================================== //
    // TOAST FUNCTION                            //
    // ========================================== //

    if (typeof showToast === 'undefined') {
        function showToast(type, message) {
            alert(message);
        }
    }

</script>


@endsection


