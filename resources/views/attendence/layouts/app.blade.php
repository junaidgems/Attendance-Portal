<!DOCTYPE html>
<html lang="en" data-theme="light">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>@yield('title', 'GI HR Platform')</title>
    <link rel="icon" type="image/png" href="{{ asset('img/ITA-GP-Gem-Shield-Emblem.png') }}">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <!-- Google Font -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap"
        rel="stylesheet">

    <!-- Toastr CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">

    <!-- ROOT CSS (Global) -->
    <link rel="stylesheet" href="{{ asset('css/root.css') }}">

    @stack('styles')
</head>

<body>

    @if (session()->has(\App\Http\Controllers\Admin\ImpersonationController::SESSION_KEY) && Auth::check())
        {{-- Admin is using an employee's account ("Login as") --}}
        <div style="position:sticky; top:0; z-index:2000; background:#dc2626; color:#fff; padding:8px 16px; display:flex; flex-wrap:wrap; align-items:center; justify-content:center; gap:12px; font-size:14px;">
            <span><i class="bi bi-person-badge"></i> You are logged in as <strong>{{ Auth::user()->name }}</strong>. Everything you do is saved under this account.</span>
            <form method="POST" action="{{ route('impersonation.stop') }}" style="margin:0;">
                @csrf
                <button type="submit" style="background:#fff; color:#dc2626; border:none; border-radius:6px; padding:4px 12px; font-weight:600; cursor:pointer;">
                    <i class="bi bi-arrow-left"></i> Back to Admin
                </button>
            </form>
        </div>
    @endif

    @if(!in_array(Route::currentRouteName(), ['home', 'login', 'register']))
        <div class="sidebar-overlay"></div>
        @include('attendence.partials.sidebar')
        <div class="dash-inner">
            @include('attendence.partials.topbar')
            <div class="main-wrapper">
                <div class="gradient-circle-3"></div>
                <div class="content-wrapper">
                    @yield('content')
                </div>
            </div>
        </div>
    @else
        @yield('content')
    @endif

    <!-- ========================================== -->
    <!-- ✅ JQUERY (Must be loaded before Toastr)  -->
    <!-- ========================================== -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Toastr JS -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>

    <!-- Custom JS -->
    <script src="{{ asset('js/dashboard.js') }}"></script>


    <script>
        // ✅ SHOW TOAST FUNCTION                     //

        function showToast(type, message) {
            if (typeof toastr !== 'undefined') {
                if (type === 'success') toastr.success(message);
                else if (type === 'error') toastr.error(message);
                else if (type === 'warning') toastr.warning(message);
                else if (type === 'info') toastr.info(message);
            } else {
                alert(message);
            }
        }

    
        // ✅ DOCUMENT READY                         //
    

        document.addEventListener('DOMContentLoaded', function () {

            // Toastr Configuration
            if (typeof toastr !== 'undefined') {
                toastr.options = {
                    "closeButton": true,
                    "debug": false,
                    "newestOnTop": false,
                    "progressBar": true,
                    "positionClass": "toast-bottom-right",
                    "preventDuplicates": false,
                    "onclick": null,
                    "showDuration": "300",
                    "hideDuration": "1000",
                    "timeOut": "5000",
                    "extendedTimeOut": "1000",
                    "showEasing": "swing",
                    "hideEasing": "linear",
                    "showMethod": "fadeIn",
                    "hideMethod": "fadeOut"
                };
            }

            // ✅ DEBUG: Check if session messages are coming
            console.log('Checking session messages...');

            @if(session('success'))
                console.log('Success message found: {{ session('success') }}');
                showToast('success', "{{ session('success') }}");
            @endif

            @if(session('error'))
                console.log('Error message found: {{ session('error') }}');
                showToast('error', "{{ session('error') }}");
            @endif

            // Form validation errors (e.g. a leave date in the past)
            @if(isset($errors) && $errors->any())
                showToast('error', @json($errors->first()));
            @endif

            @if(session('warning'))
                console.log('Warning message found: {{ session('warning') }}');
                showToast('warning', "{{ session('warning') }}");
            @endif

            @if(session('info'))
                console.log('Info message found: {{ session('info') }}');
                showToast('info', "{{ session('info') }}");
            @endif

        // Sidebar Toggle
        const sidebarToggle = document.getElementById('sidebarToggle');
            if (sidebarToggle) {
                sidebarToggle.addEventListener('click', function () {
                    const sidebar = document.querySelector('.sidebar');
                    const mainContent = document.querySelector('.main-wrapper');
                    if (sidebar) sidebar.classList.toggle('collapsed');
                    if (mainContent) mainContent.classList.toggle('expanded');
                });
            }

        });
    </script>

    @stack('scripts')
</body>

</html>