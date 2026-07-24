@extends('layouts.app')

@section('title', 'Home - Attendance Portal')

<!-- ============================================ -->
<!-- ✅ PAGE SPECIFIC CSS - SIRF IS PAGE KE LIYE  -->
<!-- ============================================ -->
@push('styles')
<style>
    /* Home page specific styles */
    .hero-section {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 80px 0;
        border-radius: 20px;
        margin-top: 20px;
    }
    
    .feature-card {
        transition: transform 0.3s ease, box-shadow 0.3s ease;
        cursor: pointer;
    }
    
    .feature-card:hover {
        transform: translateY(-8px);
        box-shadow: 0 12px 30px rgba(0,0,0,0.15);
    }
    
    .feature-card .icon {
        font-size: 48px;
        color: #0d6efd;
    }
    
    .stats-number {
        font-size: 42px;
        font-weight: 700;
        color: #0d6efd;
    }
    
    .stats-label {
        color: #6c757d;
        font-weight: 500;
    }
</style>
@endpush

<!-- ============================================ -->
<!-- ✅ PAGE CONTENT                              -->
<!-- ============================================ -->
@section('content')

<!-- Hero Section -->
<div class="hero-section text-center">
    <div class="container">
        <h1 class="display-3 fw-bold mb-3">
            <i class="bi bi-clock-history me-3"></i>
            Attendance Management System
        </h1>
        <p class="lead mb-4">
            Track employee attendance easily and efficiently
        </p>
        <div class="d-grid gap-2 d-sm-flex justify-content-sm-center">
            <a href="{{ url('/attendance') }}" class="btn btn-light btn-lg px-4">
                <i class="bi bi-check2-circle me-2"></i> Mark Attendance
            </a>
            <a href="{{ url('/employees') }}" class="btn btn-outline-light btn-lg px-4">
                <i class="bi bi-people me-2"></i> View Employees
            </a>
        </div>
    </div>
</div>

<!-- Stats Section -->
<div class="row mt-4 g-4">
    <div class="col-md-3 col-6">
        <div class="card text-center p-3">
            <div class="stats-number">156</div>
            <div class="stats-label">Total Employees</div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="card text-center p-3">
            <div class="stats-number">89</div>
            <div class="stats-label">Present Today</div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="card text-center p-3">
            <div class="stats-number">12</div>
            <div class="stats-label">On Leave</div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="card text-center p-3">
            <div class="stats-number">97%</div>
            <div class="stats-label">Attendance Rate</div>
        </div>
    </div>
</div>

<!-- Features Section -->
<div class="row mt-5 g-4">
    <div class="col-md-4">
        <div class="card feature-card p-4 text-center">
            <div class="icon">
                <i class="bi bi-person-plus"></i>
            </div>
            <h5 class="mt-3">Add Employees</h5>
            <p class="text-muted">Register new employees with their details</p>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card feature-card p-4 text-center">
            <div class="icon">
                <i class="bi bi-check2-all"></i>
            </div>
            <h5 class="mt-3">Mark Attendance</h5>
            <p class="text-muted">Quickly mark attendance with one click</p>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card feature-card p-4 text-center">
            <div class="icon">
                <i class="bi bi-file-earmark-bar-graph"></i>
            </div>
            <h5 class="mt-3">Generate Reports</h5>
            <p class="text-muted">Get detailed attendance reports</p>
        </div>
    </div>
</div>

@endsection

<!-- ============================================ -->
<!-- ✅ PAGE SPECIFIC JS - SIRF IS PAGE KE LIYE  -->
<!-- ============================================ -->
@push('scripts')
<script>
    // Home page specific JavaScript
    console.log('Home page loaded!');
    
    // Stats animation example
    document.addEventListener('DOMContentLoaded', function() {
        const stats = document.querySelectorAll('.stats-number');
        stats.forEach(stat => {
            const text = stat.textContent;
            const number = parseInt(text);
            if (!isNaN(number)) {
                let current = 0;
                const increment = Math.ceil(number / 30);
                const timer = setInterval(() => {
                    current += increment;
                    if (current >= number) {
                        current = number;
                        clearInterval(timer);
                    }
                    stat.textContent = current;
                }, 40);
            }
        });
    });
</script>
@endpush