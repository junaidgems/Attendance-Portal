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

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <!-- Google Font -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap"
        rel="stylesheet">

    {{-- Toast bar --}}
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">


    <!--  ROOT CSS (Global) -->
    <link rel="stylesheet" href="{{ asset('css/root.css') }}">

    @stack('styles')
</head>

<body>


    <!-- ✅ SIDEBAR + TOPBAR - DASHBOARD PAGES    -->

    @if(!in_array(Route::currentRouteName(), ['home', 'login', 'register']))

        <!-- Sidebar Overlay (Mobile) -->
        <div class="sidebar-overlay"></div>

        <!-- Sidebar -->
        @include('attendence.partials.sidebar')
        <div class="dash-inner">
            <!-- ===== TOPBAR (Bahir) ===== -->
            @include('attendence.partials.topbar')

            <!-- ===== MAIN WRAPPER (Sirf Content) ===== -->
            <div class="main-wrapper">
                <div class="gradient-circle-3"></div>
                <div class="content-wrapper">
                    @yield('content')
                </div>
            </div>
        </div>
    @else


        <!-- ✅ HOME / LOGIN PAGES - SIRF CONTENT    -->

        @yield('content')

    @endif

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- ===== Custom JS ===== -->
    <script src="{{ asset('js/dashboard.js') }}"></script>
    <!-- Toastr JS -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>


    <script>
        toastr.options = {
            "closeButton": true,
            "progressBar": true,
            "positionClass": "toast-top-right",
            "timeOut": 5000,
        };

        function showFlashMessage(type, message) {
            if (type === 'success') toastr.success(message);
            else if (type === 'error') toastr.error(message);
            else if (type === 'warning') toastr.warning(message);
            else if (type === 'info') toastr.info(message);
        }

        @if(session('success'))
            showFlashMessage('success', "{{ session('success') }}");
        @endif

        @if(session('error'))
            showFlashMessage('error', "{{ session('error') }}");
        @endif

        @if(session('warning'))
            showFlashMessage('warning', "{{ session('warning') }}");
        @endif

        @if(session('info'))
            showFlashMessage('info', "{{ session('info') }}");
        @endif
    </script>



    @stack('scripts')
</body>

</html>