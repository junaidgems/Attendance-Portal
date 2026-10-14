<!-- TOPBAR      -->
<header class="topbar">

    <!-- Left -->
    <div class="topbar-left">
        <button class="toggle-btn" aria-label="Toggle Sidebar">
            <i class="bi bi-layout-sidebar-reverse"></i>
        </button>
        <div>
            <h5 class="page-title">@yield('page-title', 'Dashboard')</h5>
            <p>Organisation overview · Cycle: {{ $attendanceCycleLabel }}</p>
        </div>
    </div>

    <!-- Right -->
    <div class="topbar-right">

        <!-- Theme Toggle -->
        <button class="theme-toggle" aria-label="Toggle Theme">
            <i class="bi bi-moon-fill"></i>
        </button>

        <!-- NOTIFICATIONS - Bootstrap Dropdown -->
        <div class="dropdown">
            <button class="notification-btn dropdown-toggle" type="button" data-bs-toggle="dropdown"
                aria-expanded="false">
                <i class="bi bi-bell"></i>
                @if ($topbarUnreadCount > 0)
                    <span class="notification-dot">{{ $topbarUnreadCount > 9 ? '9+' : $topbarUnreadCount }}</span>
                @endif
            </button>

            <ul class="dropdown-menu dropdown-menu-end notification-dropdown">
                <li class="dropdown-header">
                    <span>Notifications</span>
                    <span class="dropdown-header-badge">{{ $topbarUnreadCount }} new</span>
                </li>
                <li>
                    <hr class="dropdown-divider">
                </li>
                @forelse ($topbarNotifications as $notification)
                    <li style="position:relative;">
                        <form method="POST" action="{{ route('notifications.clear', $notification) }}"
                            style="position:absolute; top:6px; right:8px; z-index:2; margin:0;">
                            @csrf
                            <button type="submit" title="Clear"
                                style="border:none; background:transparent; color:var(--text-muted, #9ca3af); font-size:16px; line-height:1; padding:2px 4px; cursor:pointer;">
                                <i class="bi bi-x"></i>
                            </button>
                        </form>
                        <a href="{{ route('notifications.open', $notification) }}" style="padding-right:30px;"
                            class="notification-item {{ $notification->read_at ? '' : 'unread' }}">
                            <i class="bi {{ [
                                'late' => 'bi-alarm',
                                'early_out' => 'bi-box-arrow-left',
                                'leave' => 'bi-calendar-check',
                                'correction' => 'bi-pencil-square',
                                'waiver' => 'bi-shield-check',
                                'penalty' => 'bi-exclamation-triangle',
                                'wfh' => 'bi-house-door',
                            ][$notification->type] ?? 'bi-bell' }}"></i>
                            <div>
                                <div class="notification-title">{{ $notification->title }}</div>
                                <div class="notification-message">{{ $notification->message }}</div>
                                <div class="notification-time">{{ $notification->created_at->diffForHumans() }}</div>
                            </div>
                        </a>
                    </li>
                @empty
                    <li>
                        <div class="notification-empty">
                            <i class="bi bi-check-circle"></i>
                            <p>You're all caught up.</p>
                        </div>
                    </li>
                @endforelse
                @if ($topbarNotifications->isNotEmpty())
                    <li style="display:flex;">
                        @if ($topbarUnreadCount > 0)
                            <form method="POST" action="{{ route('notifications.read-all') }}" class="notification-footer" style="flex:1;">
                                @csrf
                                <button type="submit">Mark all as read</button>
                            </form>
                        @endif
                        <form method="POST" action="{{ route('notifications.clear-all') }}" class="notification-footer" style="flex:1;"
                            onsubmit="return confirm('Clear all notifications?');">
                            @csrf
                            <button type="submit"><i class="bi bi-trash3"></i> Clear all</button>
                        </form>
                    </li>
                @endif
            </ul>
        </div>

        <!-- PROFILE - Bootstrap Dropdown -->
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
        @if(Auth::user()->hasRole('administrator'))
            <a class="dropdown-item" href="{{ route('admin.profile') }}">
                <i class="bi bi-person"></i> Profile
            </a>
        @elseif(Auth::user()->hasRole('employee'))
            <a class="dropdown-item" href="{{ route('employee.profile') }}">
                <i class="bi bi-person"></i> Profile
            </a>
        @elseif(Auth::user()->hasRole('line_manager'))
            <a class="dropdown-item" href="{{ route('line_manager.profile') }}">
                <i class="bi bi-person"></i> Profile
            </a>
        @elseif(Auth::user()->hasRole('manager'))
            <a class="dropdown-item" href="{{ route('manager.profile') }}">
                <i class="bi bi-person"></i> Profile
            </a>
        @elseif(Auth::user()->hasRole('director'))
            <a class="dropdown-item" href="{{ route('director.profile') }}">
                <i class="bi bi-person"></i> Profile
            </a>
        @else
            <a class="dropdown-item" href="#">
                <i class="bi bi-person"></i> Profile
            </a>
        @endif
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