@extends('attendence.layouts.app')

@section('title', 'GI HR Platform - Login')

@section('content')
    <link rel="stylesheet" href="{{ asset('css/login.css') }}">

    <div class="login-container">

        <!-- LEFT SIDE -->
        <div class="login-left">
            <div class="orb1"></div>
            <div class="orb2"></div>

            <div class="content">
                <div class="brand">
                    <img src="{{ asset('img/ITA-GEMS-Pathway-HRMS-Portal-Logo.png') }}" alt="GI HR Platform">
                </div>

                <h1>
                    Attendance & HR<br>
                    <span class="highlight">Made Effortless</span>
                </h1>

                <p>
                    Biometric-synced attendance, line-manager approvals,
                    and role-based access — for employees, managers, and administrators.
                </p>

                <div class="features">
                    <div class="item">
                        <i class="bi bi-clock-history"></i>
                        <span>Real-time attendance &amp; live punch tracking</span>
                    </div>
                    <div class="item">
                        <i class="bi bi-check2-circle"></i>
                        <span>Correction requests with one-tap approvals</span>
                    </div>
                    <div class="item">
                        <i class="bi bi-gear"></i>
                        <span>Configurable policy &amp; biometric device sync</span>
                    </div>
                </div>

                <div class="footer-text">
                    GEMS Pathways · Internal HR Systems
                </div>
            </div>
        </div>

        <!-- RIGHT SIDE - LOGIN FORM -->
        <div class="login-right">

            <div class="welcome-text">
                <h3>Welcome back</h3>
                <p>Sign in with your work credentials to continue.</p>
            </div>

            @if(session('success'))
                <div class="alert-custom alert-success">
                    <i class="bi bi-check-circle-fill me-2"></i> {{ session('success') }}
                </div>
            @endif

            @if(session('error'))
                <div class="alert-custom alert-error">
                    <i class="bi bi-exclamation-circle-fill me-2"></i> {{ session('error') }}
                </div>
            @endif

            @if($errors->any())
                <div class="alert-custom alert-error">
                    @foreach($errors->all() as $error)
                        <div><i class="bi bi-exclamation-circle-fill me-2"></i> {{ $error }}</div>
                    @endforeach
                </div>
            @endif

            <form method="POST" action="{{ route('login.post') }}">
                @csrf

                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" placeholder="faraz@gems.edu" required>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="password-input-wrapper">
                        <input type="password" id="password" name="password" placeholder="••••••••" required>
                        <button type="button" class="password-toggle-btn" onclick="togglePassword()">
                            <i class="bi bi-eye" id="passwordIcon"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn-login">
                    <i class="bi bi-box-arrow-in-right me-2"></i> Sign In
                </button>
            </form>

        </div>

    </div>

    <script>
        history.pushState(null, null, location.href);
        window.onpopstate = function () {
            history.go(1);
        };

        function togglePassword() {
            const input = document.getElementById('password');
            const icon = document.getElementById('passwordIcon');

            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('bi-eye');
                icon.classList.add('bi-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('bi-eye-slash');
                icon.classList.add('bi-eye');
            }
        }
    </script>

@endsection