<!-- TOPBAR      -->
<header class="topbar">

    <!-- Left -->
    <div class="topbar-left">
        <button class="toggle-btn" aria-label="Toggle Sidebar">
            <i class="bi bi-layout-sidebar-reverse"></i>
        </button>
        <div>
            <h5 class="page-title">@yield('page-title', 'Dashboard')</h5>
            <p>Organisation overview · Cycle: 26 Jun – 25 Jul 2026</p>
        </div>
    </div>

    <!-- Right -->
    <div class="topbar-right">

        <!-- Theme Toggle -->
        <button class="theme-toggle" aria-label="Toggle Theme">
            <i class="bi bi-moon-fill"></i>
        </button>

        <!-- ========================================== -->
        <!-- NOTIFICATIONS - Bootstrap Dropdown -->
        <!-- ========================================== -->
        <div class="dropdown">
            <button class="notification-btn dropdown-toggle" type="button" data-bs-toggle="dropdown"
                aria-expanded="false">
                <i class="bi bi-bell"></i>
                <span class="notification-dot">0</span>
            </button>

            <ul class="dropdown-menu dropdown-menu-end notification-dropdown">
                <li class="dropdown-header">
                    <span>Notifications</span>
                    <span class="dropdown-header-badge">0 new</span>
                </li>
                <li>
                    <hr class="dropdown-divider">
                </li>
                <li>
                    <div class="notification-empty">
                        <i class="bi bi-check-circle"></i>
                        <p>You're all caught up.</p>
                    </div>
                </li>
            </ul>
        </div>

        <!-- ========================================== -->
        <!-- PROFILE - Bootstrap Dropdown -->
        <!-- ========================================== -->
        <div class="dropdown">
            <button class="dropdown-toggle-btn dropdown-toggle" type="button" data-bs-toggle="dropdown"
                aria-expanded="false">
                <div class="avatar-sm">
                    {{ substr(Auth::user()->name ?? 'U', 0, 2) }}
                </div>
                <span class="user-name-top">{{ Auth::user()->name ?? 'User' }}</span>
                <i class="bi bi-chevron-down chevron"></i>
            </button>

            <ul class="dropdown-menu dropdown-menu-end">
                <li>
                    <a class="dropdown-item" href="#">
                        <i class="bi bi-person"></i> Profile
                    </a>
                </li>
                <li>
                    <a class="dropdown-item" href="#">
                        <i class="bi bi-gear"></i> Settings
                    </a>
                </li>
                <li>
                    <hr class="dropdown-divider">
                </li>
                <li>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="dropdown-item"
                            style="color:#ef4444; width:100%; border:none; background:transparent;">
                            <i class="bi bi-box-arrow-right"></i> Logout
                        </button>
                    </form>
                </li>
            </ul>
        </div>

    </div>
</header>