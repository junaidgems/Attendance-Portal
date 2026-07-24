@extends('attendence.layouts.app')


@section('title', 'GI HR Platform - Home')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/home.css') }}">
@endpush

@section('content')
<div class="welcome-body">
    <div class="portal-wrapper">

        <!-- ===== CIRCLE GRADIENTS - LEFT & RIGHT ===== -->
        <div class="circle-left"></div>
        <div class="circle-right"></div>
        <div class="circle-top"></div>
        <div class="circle-bottom"></div>

        <!-- ===== TOP: BRAND + USER ===== -->
        <div class="row">
            <div class="col-md-6">
                <div class="brand-section">
                    <span class="brand-icon">GI</span>
                    <span class="brand-name"><span class="highlight">GI</span>HRPlatform</span>
                    <div class="brand-sub">GEMS Pathways</div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="user-section" style="float: right;">
                    <span class="user-name">Faraz Siddiqui</span>
                    <span class="user-email">· faraz@gems.edu</span>
                    <span class="user-badge">choose a module</span>
                </div>
            </div>
        </div>

        <!-- ===== HERO TEXT ===== -->
        <div class="hero-text">
            <div class="badge-top">
                <i class="bi bi-shield-check"></i> Integrated HR Management Platform
            </div>
            <h1>
                One secure <span class="gradient">sign-in</span> for<br>
                Attendance, Performance & Payroll
            </h1>
            <p>All connected to a single central HR database. Sign in once to access every module.</p>
        </div>

        <!-- ===== MODULES (Glass Cards) ===== -->
        <div class="row">

            <!-- Module 1: Attendance & Leave -->
            <div class="col-lg-4 col-md-6" style="margin-bottom: 24px;">
                <div class="glass-card" onclick="window.location.href='{{ route('login') }}'">
                    <div>
                        <span class="module-icon primary"><i class="bi bi-clock-history"></i></span>
                        <span class="tag-live"><i class="bi bi-broadcast"></i> Live</span>
                    </div>
                    <h4>Attendance & Leave</h4>
                    <p>Capture attendance cycles, leave approvals &amp; reports.</p>
                    <div class="features-tags">
                        <span><i class="bi bi-fingerprint"></i> Biometric sync</span>
                        <span><i class="bi bi-check2-all"></i> Leave &amp; approvals</span>
                        <span><i class="bi bi-file-earmark-text"></i> Reports</span>
                    </div>
                    <a href="{{ route('login') }}" class="module-link">
                        Open Module <i class="bi bi-arrow-right"></i>
                    </a>
                </div>
            </div>

            <!-- Module 2: Performance -->
            <div class="col-lg-4 col-md-6" style="margin-bottom: 24px;">
                <div class="glass-card">
                    <div>
                        <span class="module-icon success"><i class="bi bi-graph-up-arrow"></i></span>
                        <span class="tag-coming"><i class="bi bi-hourglass-split"></i> Coming Soon</span>
                    </div>
                    <h4>Performance</h4>
                    <p>Goals, appraisals, 360° reviews &amp; analytics.</p>
                    <div class="features-tags">
                        <span><i class="bi bi-bullseye"></i> Goals &amp; KPIs</span>
                        <span><i class="bi bi-star"></i> Appraisals</span>
                        <span><i class="bi bi-people"></i> Reviews</span>
                    </div>
                    <span class="coming-text"><i class="bi bi-clock"></i> Coming Soon</span>
                </div>
            </div>

            <!-- Module 3: Payroll -->
            <div class="col-lg-4 col-md-6" style="margin-bottom: 24px;">
                <div class="glass-card">
                    <div>
                        <span class="module-icon warning"><i class="bi bi-wallet2"></i></span>
                        <span class="tag-coming"><i class="bi bi-hourglass-split"></i> Coming Soon</span>
                    </div>
                    <h4>Payroll</h4>
                    <p>Payslips, salary structure, deductions &amp; runs.</p>
                    <div class="features-tags">
                        <span><i class="bi bi-file-earmark-pdf"></i> Payslips</span>
                        <span><i class="bi bi-cash-stack"></i> Salary</span>
                        <span><i class="bi bi-dash-circle"></i> Deductions</span>
                    </div>
                    <span class="coming-text"><i class="bi bi-clock"></i> Coming Soon</span>
                </div>
            </div>

        </div>

        <!-- ===== BOTTOM FEATURES ===== -->
        <div class="bottom-features">
            <div class="row">
                <div class="col-md-4">
                    <div class="feature-item">
                        <i class="bi bi-box-arrow-in-right"></i>
                        <span>Single sign-on</span>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="feature-item">
                        <i class="bi bi-database"></i>
                        <span>Shared central database</span>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="feature-item">
                        <i class="bi bi-arrow-repeat"></i>
                        <span>Real-time data sync</span>
                    </div>
                </div>
            </div>
        </div>

    </div>
    </div>
@endsection