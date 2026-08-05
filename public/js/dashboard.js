/**
 * Sidebar Toggle
 */
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.querySelector('.sidebar');
    const overlay = document.querySelector('.sidebar-overlay');
    const toggleBtn = document.querySelector('.toggle-btn');
    const body = document.body;

    // Toggle sidebar
    if (toggleBtn) {
        toggleBtn.addEventListener('click', function() {
            if (window.innerWidth <= 1024) {
                sidebar.classList.toggle('open');
                overlay.classList.toggle('active');
            } else {
                body.classList.toggle('sidebar-collapsed');
                localStorage.setItem('sidebarCollapsed', body.classList.contains('sidebar-collapsed'));
            }
        });
    }

    // Close sidebar on overlay click
    if (overlay) {
        overlay.addEventListener('click', function() {
            sidebar.classList.remove('open');
            overlay.classList.remove('active');
        });
    }

    // Restore sidebar state on desktop
    if (window.innerWidth > 1024) {
        const isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
        if (isCollapsed) {
            body.classList.add('sidebar-collapsed');
        }
    }

    // Handle resize
    window.addEventListener('resize', function() {
        if (window.innerWidth > 1024) {
            overlay.classList.remove('active');
            sidebar.classList.remove('open');
        }
    });

    // ===== DARK/LIGHT THEME =====
    const themeToggle = document.querySelector('.theme-toggle');
    const html = document.documentElement;

    const savedTheme = localStorage.getItem('theme') || 'light';
    html.setAttribute('data-theme', savedTheme);

    if (themeToggle) {
        themeToggle.addEventListener('click', function() {
            const current = html.getAttribute('data-theme');
            const newTheme = current === 'dark' ? 'light' : 'dark';
            html.setAttribute('data-theme', newTheme);
            localStorage.setItem('theme', newTheme);
        });
    }
});

/**
 * Active Link Highlight
 */
document.addEventListener('DOMContentLoaded', function() {
    const currentPath = window.location.pathname;
    
    document.querySelectorAll('.sidebar-menu .nav-link').forEach(function(link) {
        const href = link.getAttribute('href');
        
        if (!href || href === '#') return;
        
        // ✅ Remove trailing slash
        const cleanHref = href.replace(/\/$/, '');
        const cleanCurrent = currentPath.replace(/\/$/, '');
        
        // ✅ Check if current path matches or starts with href
        if (cleanHref === cleanCurrent || 
            (cleanHref !== '/' && cleanCurrent.startsWith(cleanHref + '/'))) {
            link.classList.add('active');
        }
    });
});


document.getElementById('clearFilterBtn')?.addEventListener('click', function(e) {
    e.preventDefault();
    document.getElementById('filterFrom').value = '';
    document.getElementById('filterTo').value = '';
    document.getElementById('filterDepartment').value = 'all';
    document.getElementById('filterSearch').value = '';
    document.getElementById('filterForm').submit();
});




    document.addEventListener('DOMContentLoaded', function() {
        // URL se 'type' parameter lo
        const urlParams = new URLSearchParams(window.location.search);
        const type = urlParams.get('type');

        // Type ke hisaab se tab mapping
        const tabMap = {
            'missing-punch': 'missing-tab',
            'half-day': 'halfday-tab',
            'early-out': 'earlyout-tab',
            'absent': 'absent-tab',
            'late': 'late-tab',
            'present': 'present-tab',
            'on-leave': 'onleave-tab'
        };

        if (type && tabMap[type]) {
            const tabId = tabMap[type];
            const tabElement = document.getElementById(tabId);

            if (tabElement) {
                // Bootstrap tab activate karein
                const tab = new bootstrap.Tab(tabElement);
                tab.show();
            }
        }
    });

