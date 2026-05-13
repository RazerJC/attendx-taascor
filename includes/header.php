<?php
/**
 * Shared Header Include
 * Includes Tailwind CSS CDN, custom styles, and navigation
 * With TAASCOR logo
 */
$user = currentUser();
$flash = getFlash();
$pageTitle = $pageTitle ?? 'TAASCOR Attendance';
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> — TAASCOR</title>
    <meta name="description" content="TAASCOR Attendance Monitoring System — manage employee attendance, scheduling, and disciplinary actions.">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary:   { 50:'#e6fcf5', 100:'#c3fae8', 200:'#96f2d7', 300:'#63e6be', 400:'#38d9a9', 500:'#20c997', 600:'#12b886', 700:'#0ca678', 800:'#099268', 900:'#087f5b' },
                        dark:      { 700:'#1a1d23', 800:'#14161a', 900:'#0d0e12' },
                        glass:     'rgba(255,255,255,0.06)',
                        glassBorder: 'rgba(255,255,255,0.10)',
                    },
                    fontFamily: {
                        sans: ['Inter', 'system-ui', 'sans-serif'],
                    }
                }
            }
        }
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/ATTENDANCE/assets/css/custom.css?v=<?= time() ?>">
    <script>
        // Load theme preference BEFORE render to prevent flash
        (function() {
            var theme = localStorage.getItem('taascor_theme') || 'dark';
            if (theme === 'light') document.documentElement.classList.add('light-mode');
        })();
    </script>
</head>
<body class="h-full font-sans antialiased theme-body">

<?php if ($user): ?>
<!-- ====== SIDEBAR + TOPBAR LAYOUT ====== -->
<div class="flex h-full" id="appLayout">
    <!-- Sidebar Overlay (mobile) -->
    <div id="sidebarOverlay" class="fixed inset-0 bg-black/60 z-30 hidden lg:hidden" onclick="toggleSidebar()"></div>

    <!-- Sidebar -->
    <aside id="sidebar" class="fixed lg:static inset-y-0 left-0 z-40 w-64 bg-dark-800 border-r border-glassBorder transform -translate-x-full lg:translate-x-0 transition-transform duration-300 flex flex-col">
        <!-- Logo -->
        <div class="flex items-center gap-3 px-5 py-5 border-b border-glassBorder">
            <img src="/ATTENDANCE/assets/images/logo.png" alt="TMGS Logo" class="sidebar-logo-img">
            <div>
                <div class="font-bold text-sm text-white tracking-wide">TAASCOR</div>
                <div class="text-[10px] text-gray-500 uppercase tracking-widest">Attendance System</div>
            </div>
        </div>

        <!-- Nav Links -->
        <nav class="flex-1 px-3 py-4 space-y-1 overflow-y-auto">
            <?php if ($user['role'] === 'admin'): ?>
                <a href="/ATTENDANCE/admin/dashboard.php" class="nav-link <?= strpos($_SERVER['PHP_SELF'],'admin/dashboard') !== false ? 'active' : '' ?>">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-4 0a1 1 0 01-1-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 01-1 1h-2z"/></svg>
                    <span>Dashboard</span>
                </a>
                <a href="/ATTENDANCE/admin/employees.php" class="nav-link <?= strpos($_SERVER['PHP_SELF'],'admin/employees') !== false ? 'active' : '' ?>">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    <span>Employees</span>
                </a>
                <a href="/ATTENDANCE/admin/reports.php" class="nav-link <?= strpos($_SERVER['PHP_SELF'],'admin/reports') !== false ? 'active' : '' ?>">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    <span>Reports</span>
                </a>
                <a href="/ATTENDANCE/admin/employee_attendance.php" class="nav-link <?= strpos($_SERVER['PHP_SELF'],'admin/employee_attendance') !== false ? 'active' : '' ?>">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 14l2 2 4-4"/></svg>
                    <span>Employee Attendance</span>
                </a>
                <a href="/ATTENDANCE/admin/activity_log.php" class="nav-link <?= strpos($_SERVER['PHP_SELF'],'admin/activity_log') !== false ? 'active' : '' ?>">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <span>Activity Log</span>
                </a>
                <div class="my-3 border-t border-glassBorder"></div>
                <a href="/ATTENDANCE/admin/clear_history.php" class="nav-link <?= strpos($_SERVER['PHP_SELF'],'admin/clear_history') !== false ? 'active' : '' ?> text-red-400 hover:text-red-300 hover:bg-red-500/10">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                    <span>Clear History</span>
                </a>
            <?php else: ?>
                <a href="/ATTENDANCE/coordinator/dashboard.php" class="nav-link <?= strpos($_SERVER['PHP_SELF'],'coordinator/dashboard') !== false ? 'active' : '' ?>">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-4 0a1 1 0 01-1-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 01-1 1h-2z"/></svg>
                    <span>Dashboard</span>
                </a>
                <a href="/ATTENDANCE/coordinator/attendance.php" class="nav-link <?= strpos($_SERVER['PHP_SELF'],'coordinator/attendance') !== false ? 'active' : '' ?>">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                    <span>Attendance</span>
                </a>

                <a href="/ATTENDANCE/coordinator/reports.php" class="nav-link <?= strpos($_SERVER['PHP_SELF'],'coordinator/reports') !== false ? 'active' : '' ?>">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    <span>Reports</span>
                </a>
            <?php endif; ?>
        </nav>

        <!-- User Footer -->
        <div class="px-4 py-4 border-t border-glassBorder bg-dark-900/40">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-primary-500 to-primary-700 flex items-center justify-center text-white font-bold text-sm shadow-lg shadow-primary-500/20 flex-shrink-0">
                    <?= strtoupper(substr($user['full_name'], 0, 1)) ?>
                </div>
                <div class="flex-1 min-w-0">
                    <div class="text-sm font-semibold text-white truncate"><?= htmlspecialchars($user['full_name']) ?></div>
                    <div class="text-[11px] text-primary-400 font-medium uppercase tracking-wide"><?= $user['role'] ?></div>
                </div>
                <a href="/ATTENDANCE/index.php?logout=1" class="w-8 h-8 rounded-lg bg-white/5 hover:bg-red-500/15 flex items-center justify-center text-gray-500 hover:text-red-400 transition-all" title="Logout">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a2 2 0 01-2 2H6a2 2 0 01-2-2V7a2 2 0 012-2h5a2 2 0 012 2v1"/></svg>
                </a>
            </div>
        </div>
    </aside>

    <!-- Main Content -->
    <div class="flex-1 flex flex-col min-h-screen overflow-x-hidden">
        <!-- Top Bar -->
        <header class="sticky top-0 z-20 theme-topbar backdrop-blur-lg border-b px-4 py-3 flex items-center gap-3">
            <button id="sidebarToggle" class="lg:hidden text-gray-400 hover:text-white transition-colors" onclick="toggleSidebar()">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
            </button>
            <h1 class="text-base font-semibold theme-text-primary flex-1"><?= htmlspecialchars($pageTitle) ?></h1>
            <!-- Theme Toggle -->
            <button id="themeToggle" onclick="toggleTheme()" class="w-9 h-9 rounded-xl flex items-center justify-center transition-all theme-toggle-btn" title="Toggle Light/Dark Mode">
                <svg id="iconSun" class="w-5 h-5 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                <svg id="iconMoon" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/></svg>
            </button>
        </header>

        <!-- Page Content -->
        <main class="flex-1 p-4 md:p-6 lg:p-8">

            <?php if ($flash): ?>
            <div class="mb-4 px-4 py-3 rounded-xl text-sm font-medium
                <?= $flash['type'] === 'success' ? 'bg-green-500/10 text-green-400 border border-green-500/20' : '' ?>
                <?= $flash['type'] === 'error'   ? 'bg-red-500/10 text-red-400 border border-red-500/20' : '' ?>
                <?= $flash['type'] === 'info'    ? 'bg-blue-500/10 text-blue-400 border border-blue-500/20' : '' ?>
            " id="flashMsg">
                <?= htmlspecialchars($flash['message']) ?>
            </div>
            <script>setTimeout(()=>document.getElementById('flashMsg')?.remove(), 4000);</script>
            <?php endif; ?>
<?php endif; ?>
