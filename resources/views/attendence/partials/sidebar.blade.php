<!-- SIDEBAR                                  -->
<nav class="sidebar">

    <!-- Brand -->
    <div class="sidebar-brand">
        <span class="brand-icon">GI</span>
        <span class="brand-text"><span>GI</span> Attendance System</span>
    </div>

    <!-- Menu -->
    <ul class="sidebar-menu">


        <!-- ===== MAIN ===== -->
        <li class="menu-label">Main</li>
        <!-- ADMIN DASHBOARD                           -->


        <!-- ADMIN MENU                                -->
        @if(Auth::user()->role->name == 'administrator')
            <li class="nav-item">
                <a href="{{ route('admin.dashboard') }}"
                    class="nav-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
                    <i class="bi bi-grid-1x2"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="{{ route('admin.employee') }}"
                    class="nav-link {{ request()->routeIs('admin.employee') ? 'active' : '' }}">
                    <i class="bi bi-people"></i>
                    <span>Employees</span>
                </a>
            </li>

            <li class="nav-item">
                <a href="{{ route('admin.attendance') }}"
                    class="nav-link {{ request()->routeIs('admin.attendance') ? 'active' : '' }}">
                    <i class="bi bi-clock-history"></i>
                    <span>All Attendance</span>
                </a>
            </li>

            <li class="nav-item">
                <a href="{{ route('admin.approvals') }}"
                    class="nav-link {{ request()->routeIs('admin.approvals') ? 'active' : '' }}">
                    <i class="bi bi-check2-circle"></i>
                    <span>Approvals</span>
                </a>
            </li>

            <!-- ===== LEAVE ===== -->
            <li class="menu-label" style="margin-top: 8px;">Leave</li>

            <li class="nav-item">
                <a href="{{ route('admin.leaves') }}"
                    class="nav-link {{ request()->routeIs('admin.leaves') ? 'active' : '' }}">
                    <i class="bi bi-calendar-check"></i>
                    <span>Leave Management</span>
                </a>
            </li>

            <li class="nav-item">
                <a href="{{ route('admin.leave.report') }}"
                    class="nav-link {{ request()->routeIs('admin.leave.report') ? 'active' : '' }}">
                    <i class="bi bi-file-earmark-text"></i>
                    <span>Leave Report</span>
                </a>
            </li>

            <!-- ===== PENALTY ===== -->
            <li class="nav-item">
                <a href="{{ route('admin.penalty') }}"
                    class="nav-link {{ request()->routeIs('admin.penalty') ? 'active' : '' }}">
                    <i class="bi bi-shield"></i>
                    <span>Late &amp; Half-Day Penalty</span>
                </a>
            </li>

            <!-- ===== SETTINGS ===== -->
            <li class="menu-label" style="margin-top: 8px;">Settings</li>

            <li class="nav-item">
                <a href="{{ route('admin.attendance.audit') }}"
                    class="nav-link {{ request()->routeIs('admin.attendance.audit') ? 'active' : '' }}">
                    <i class="bi bi-clipboard-check"></i>
                    <span>Attendance Audit Report</span>
                </a>
            </li>

            <li class="nav-item">
                <a href="{{ route('admin.biometric.sync') }}"
                    class="nav-link {{ request()->routeIs('admin.biometric.sync') ? 'active' : '' }}">
                    <i class="bi bi-fingerprint"></i>
                    <span>Biometric Sync</span>
                </a>
            </li>

            <li class="nav-item">
                <a href="{{ route('admin.attendance.policy') }}"
                    class="nav-link {{ request()->routeIs('admin.attendance.policy') ? 'active' : '' }}">
                    <i class="bi bi-file-text"></i>
                    <span>Attendance Policy</span>
                </a>
            </li>

            <li class="nav-item">
                <a href="{{ route('admin.shifts') }}"
                    class="nav-link {{ request()->routeIs('admin.shifts') ? 'active' : '' }}">
                    <i class="bi bi-clock"></i>
                    <span>Shift Management</span>
                </a>
            </li>

            <li class="nav-item">
                <a href="{{ route('admin.audit.log') }}"
                    class="nav-link {{ request()->routeIs('admin.audit.log') ? 'active' : '' }}">
                    <i class="bi bi-journal-text"></i>
                    <span>Audit Log</span>
                </a>
            </li>

        @endif


        <!-- EMPLOYEE MENU                             -->
        @if(Auth::user()->role->name == 'Employee')
            <li class="nav-item">
                <a href="{{ route('employee.dashboard') }}"
                    class="nav-link {{ request()->routeIs('employee.dashboard') ? 'active' : '' }}">
                    <i class="bi bi-grid-1x2"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="{{ route('employee.my.attendance') }}"
                    class="nav-link {{ request()->routeIs('employee.my.attendance') ? 'active' : '' }}">
                    <i class="bi bi-clock-history"></i>
                    <span>My Attendance</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="{{ route('employee.correction.requests') }}"
                    class="nav-link {{ request()->routeIs('employee.correction.requests') ? 'active' : '' }}">
                    <i class="bi bi-pencil-square"></i>
                    <span>Correction Requests</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="{{ route('employee.leave') }}"
                    class="nav-link {{ request()->routeIs('employee.leave') ? 'active' : '' }}">
                    <i class="bi bi-calendar-check"></i>
                    <span>Leave</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="{{ route('employee.leave.report') }}"
                    class="nav-link {{ request()->routeIs('employee.leave.report') ? 'active' : '' }}">
                    <i class="bi bi-file-earmark-text"></i>
                    <span>Leave Report</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="{{ route('employee.penalty.waivers') }}"
                    class="nav-link {{ request()->routeIs('employee.penalty.waivers') ? 'active' : '' }}">
                    <i class="bi bi-shield"></i>
                    <span>Penalty &amp; Waivers</span>
                </a>
            </li>
        @endif


        <!-- MANAGER MENU                              -->
        @if(Auth::user()->role->name == 'Manager')

            <li class="nav-item">
                <a href="{{ route('manager.dashboard') }}"
                    class="nav-link {{ request()->routeIs('manager.dashboard') ? 'active' : '' }}">
                    <i class="bi bi-grid-1x2"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="{{ route('manager.my.attendance') }}"
                    class="nav-link {{ request()->routeIs('manager.my.attendance') ? 'active' : '' }}">
                    <i class="bi bi-clock-history"></i>
                    <span>My Attendance</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="{{ route('manager.correction.requests') }}"
                    class="nav-link {{ request()->routeIs('manager.correction.requests') ? 'active' : '' }}">
                    <i class="bi bi-pencil-square"></i>
                    <span>Correction Requests</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="{{ route('manager.leave') }}"
                    class="nav-link {{ request()->routeIs('manager.leave') ? 'active' : '' }}">
                    <i class="bi bi-calendar-check"></i>
                    <span>Leave</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="{{ route('manager.leave.report') }}"
                    class="nav-link {{ request()->routeIs('manager.leave.report') ? 'active' : '' }}">
                    <i class="bi bi-file-earmark-text"></i>
                    <span>Leave Report</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="{{ route('manager.penalty.waivers') }}"
                    class="nav-link {{ request()->routeIs('manager.penalty.waivers') ? 'active' : '' }}">
                    <i class="bi bi-shield"></i>
                    <span>Penalty &amp; Waivers</span>
                </a>
            </li>

            <!-- ===== TEAM ===== -->
            <li class="menu-label" style="margin-top: 8px;">Team</li>

            <li class="nav-item">
                <a href="{{ route('manager.team.attendance') }}"
                    class="nav-link {{ request()->routeIs('manager.team.attendance') ? 'active' : '' }}">
                    <i class="bi bi-people"></i>
                    <span>Team Attendance</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="{{ route('manager.approvals') }}"
                    class="nav-link {{ request()->routeIs('manager.approvals') ? 'active' : '' }}">
                    <i class="bi bi-check2-circle"></i>
                    <span>Approvals</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="{{ route('manager.attendance.patterns') }}"
                    class="nav-link {{ request()->routeIs('manager.attendance.patterns') ? 'active' : '' }}">
                    <i class="bi bi-graph-up"></i>
                    <span>Attendance Patterns</span>
                </a>
            </li>
        @endif


        <!-- LINE MANAGER MENU                         -->

        @if(Auth::user()->role->name == 'Line_Manager')

            <!-- Dashboard -->
            <li class="nav-item">
                <a href="{{ route('line_manager.dashboard') }}"
                    class="nav-link {{ request()->routeIs('line_manager.dashboard') ? 'active' : '' }}">
                    <i class="bi bi-grid-1x2"></i>
                    <span>Dashboard</span>
                </a>
            </li>

            <!-- My Attendance -->
            <li class="nav-item">
                <a href="{{ route('line_manager.my.attendance') }}"
                    class="nav-link {{ request()->routeIs('line_manager.my.attendance') ? 'active' : '' }}">
                    <i class="bi bi-clock-history"></i>
                    <span>My Attendance</span>
                </a>
            </li>

            <!-- Correction Requests -->
            <li class="nav-item">
                <a href="{{ route('line_manager.correction.requests') }}"
                    class="nav-link {{ request()->routeIs('line_manager.correction.requests') ? 'active' : '' }}">
                    <i class="bi bi-pencil-square"></i>
                    <span>Correction Requests</span>
                </a>
            </li>

            <!-- Leave -->
            <li class="nav-item">
                <a href="{{ route('line_manager.leave') }}"
                    class="nav-link {{ request()->routeIs('line_manager.leave') ? 'active' : '' }}">
                    <i class="bi bi-calendar-check"></i>
                    <span>Leave</span>
                </a>
            </li>

            <!-- Leave Report -->
            <li class="nav-item">
                <a href="{{ route('line_manager.leave.report') }}"
                    class="nav-link {{ request()->routeIs('line_manager.leave.report') ? 'active' : '' }}">
                    <i class="bi bi-file-earmark-text"></i>
                    <span>Leave Report</span>
                </a>
            </li>

            <!-- Penalty & Waivers -->
            <li class="nav-item">
                <a href="{{ route('line_manager.penalty.waivers') }}"
                    class="nav-link {{ request()->routeIs('line_manager.penalty.waivers') ? 'active' : '' }}">
                    <i class="bi bi-shield"></i>
                    <span>Penalty &amp; Waivers</span>
                </a>
            </li>

            <!-- ===== TEAM ===== -->
            <li class="menu-label" style="margin-top: 8px;">Team</li>

            <!-- Team Attendance -->
            <li class="nav-item">
                <a href="{{ route('line_manager.team.attendance') }}"
                    class="nav-link {{ request()->routeIs('line_manager.team.attendance') ? 'active' : '' }}">
                    <i class="bi bi-people"></i>
                    <span>Team Attendance</span>
                </a>
            </li>

            <!-- Approvals -->
            <li class="nav-item">
                <a href="{{ route('line_manager.approvals') }}"
                    class="nav-link {{ request()->routeIs('line_manager.approvals') ? 'active' : '' }}">
                    <i class="bi bi-check2-circle"></i>
                    <span>Approvals</span>
                </a>
            </li>

            <!-- Attendance Patterns -->
            <li class="nav-item">
                <a href="{{ route('line_manager.attendance.patterns') }}"
                    class="nav-link {{ request()->routeIs('line_manager.attendance.patterns') ? 'active' : '' }}">
                    <i class="bi bi-graph-up"></i>
                    <span>Attendance Patterns</span>
                </a>
            </li>

        @endif


        <!-- DIRECTOR MENU                             -->
         @if(Auth::user()->role->name == 'Director')

            <li class="nav-item">
                <a href="{{ route('director.dashboard') }}"
                    class="nav-link {{ request()->routeIs('director.dashboard') ? 'active' : '' }}">
                    <i class="bi bi-grid-1x2"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <!-- My Attendance -->
            <li class="nav-item">
                <a href="{{ route('director.my.attendance') }}"
                    class="nav-link {{ request()->routeIs('director.my.attendance') ? 'active' : '' }}">
                    <i class="bi bi-clock-history"></i>
                    <span>My Attendance</span>
                </a>
            </li>

            <!-- Correction Requests -->
            <li class="nav-item">
                <a href="{{ route('director.correction.requests') }}"
                    class="nav-link {{ request()->routeIs('director.correction.requests') ? 'active' : '' }}">
                    <i class="bi bi-pencil-square"></i>
                    <span>Correction Requests</span>
                </a>
            </li>

            <!-- Leave -->
            <li class="nav-item">
                <a href="{{ route('director.leave') }}"
                    class="nav-link {{ request()->routeIs('director.leave') ? 'active' : '' }}">
                    <i class="bi bi-calendar-check"></i>
                    <span>Leave</span>
                </a>
            </li>

            <!-- Leave Report -->
            <li class="nav-item">
                <a href="{{ route('director.leave.report') }}"
                    class="nav-link {{ request()->routeIs('director.leave.report') ? 'active' : '' }}">
                    <i class="bi bi-file-earmark-text"></i>
                    <span>Leave Report</span>
                </a>
            </li>

            <!-- Penalty & Waivers -->
            <li class="nav-item">
                <a href="{{ route('director.penalty.waivers') }}"
                    class="nav-link {{ request()->routeIs('director.penalty.waivers') ? 'active' : '' }}">
                    <i class="bi bi-shield"></i>
                    <span>Penalty &amp; Waivers</span>
                </a>
            </li>

            <!-- ===== TEAM ===== -->
            <li class="menu-label" style="margin-top: 8px;">Team</li>

            <!-- Team Attendance -->
            <li class="nav-item">
                <a href="{{ route('director.team.attendance') }}"
                    class="nav-link {{ request()->routeIs('director.team.attendance') ? 'active' : '' }}">
                    <i class="bi bi-people"></i>
                    <span>Team Attendance</span>
                </a>
            </li>

            <!-- Approvals -->
            <li class="nav-item">
                <a href="{{ route('director.approvals') }}"
                    class="nav-link {{ request()->routeIs('director.approvals') ? 'active' : '' }}">
                    <i class="bi bi-check2-circle"></i>
                    <span>Approvals</span>
                </a>
            </li>

            <!-- Attendance Patterns -->
            <li class="nav-item">
                <a href="{{ route('director.attendance.patterns') }}"
                    class="nav-link {{ request()->routeIs('director.attendance.patterns') ? 'active' : '' }}">
                    <i class="bi bi-graph-up"></i>
                    <span>Attendance Patterns</span>
                </a>
            </li>

            <!-- Attendance Breakdown -->
            <li class="nav-item">
                <a href="{{ route('director.attendance.breakdown') }}"
                    class="nav-link {{ request()->routeIs('director.attendance.breakdown') ? 'active' : '' }}">
                    <i class="bi bi-bar-chart"></i>
                    <span>Attendance Breakdown</span>
                </a>
            </li>

            <!-- ===== SETTINGS / REPORTS ===== -->
            <li class="menu-label" style="margin-top: 8px;">Settings</li>


        @endif
    </ul>

    <!-- ===== FOOTER: User + Logout ===== -->
    <div class="sidebar-footer">
        <div class="avatar">
            {{ substr(Auth::user()->name ?? 'FS', 0, 2) }}
        </div>
        <div class="user-info">
            <div class="user-name">{{ Auth::user()->name ?? 'Guest' }}</div>
            <div class="user-role">
                {{ ucfirst(str_replace('_', ' ', Auth::user()->role->name ?? 'No Role')) }}
            </div>
        </div>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="logout-btn" title="Logout">
                <i class="bi bi-box-arrow-right"></i>
            </button>
        </form>
    </div>

</nav>