<?php
/**
 * Login & Logout — TAASCOR Attendance Monitoring System
 */
require_once __DIR__ . '/includes/auth.php';

// Handle logout
if (isset($_GET['logout'])) {
    if (isLoggedIn()) logActivity('Logout', 'User logged out');
    session_unset();
    session_destroy();
    header('Location: /ATTENDANCE/index.php');
    exit;
}

// Already logged in? Redirect
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] === 'admin') {
        header('Location: /ATTENDANCE/admin/dashboard.php');
    } else {
        header('Location: /ATTENDANCE/coordinator/dashboard.php');
    }
    exit;
}

$error = '';

// Handle login POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username && $password) {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM users WHERE username = ? AND status = 'active'");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            // Set session
            $_SESSION['user_id']       = $user['id'];
            $_SESSION['username']      = $user['username'];
            $_SESSION['full_name']     = $user['full_name'];
            $_SESSION['role']          = $user['role'];
            $_SESSION['department_id'] = $user['department_id'];

            // Log activity
            $log = $db->prepare("INSERT INTO activity_log (user_id, action, details) VALUES (?, ?, ?)");
            $log->execute([$user['id'], 'Login', 'User logged in']);

            // Redirect by role
            if ($user['role'] === 'admin') {    
                header('Location: /ATTENDANCE/admin/dashboard.php');
            } else {
                header('Location: /ATTENDANCE/coordinator/dashboard.php');
            }
            exit;
        } else {
            $error = 'Invalid username or password.';
        }
    } else {
        $error = 'Please enter both username and password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — TAASCOR Attendance</title>
    <meta name="description" content="Login to the TAASCOR Attendance Monitoring System.">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: { 400:'#38d9a9', 500:'#20c997', 600:'#12b886', 700:'#0ca678' },
                        dark:    { 700:'#1a1d23', 800:'#14161a', 900:'#0d0e12' },
                    },
                    fontFamily: { sans: ['Inter','system-ui','sans-serif'] }
                }
            }
        }
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/ATTENDANCE/assets/css/custom.css">
</head>
<body class="h-full bg-dark-900 font-sans antialiased flex items-center justify-center p-4">
    <!-- Animated BG -->
    <div class="login-bg"></div>

    <!-- Login Card -->
    <div class="relative z-10 w-full max-w-md">
        <div class="login-card bg-dark-800/80 backdrop-blur-2xl border border-white/10 rounded-3xl p-8 md:p-10 shadow-2xl">
            <!-- Logo -->
            <div class="flex flex-col items-center mb-8">
                <img src="/ATTENDANCE/assets/images/logo.png" alt="TMGS — TAASCOR Management & General Services Corp." class="login-logo-img mb-4 login-logo">
                <h1 class="text-2xl font-bold text-white tracking-tight">TAASCOR</h1>
                <p class="text-xs text-gray-500 uppercase tracking-[0.25em] mt-1">Attendance Monitoring System</p>
            </div>

            <!-- Error -->
            <?php if ($error): ?>
            <div class="mb-5 px-4 py-3 bg-red-500/10 border border-red-500/20 rounded-xl text-red-400 text-sm text-center font-medium">
                <?= htmlspecialchars($error) ?>
            </div>
            <?php endif; ?>

            <!-- Form -->
            <form method="POST" class="space-y-5">
                <div>
                    <label class="block text-xs font-medium text-gray-400 mb-2 uppercase tracking-wider">Username</label>
                    <input type="text" name="username" required autocomplete="username"
                           value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                           class="w-full px-4 py-3.5 bg-dark-700/50 border border-white/10 rounded-xl text-white placeholder-gray-600 focus:outline-none focus:border-primary-500/50 focus:ring-2 focus:ring-primary-500/20 transition-all text-sm"
                           placeholder="Enter your username" id="loginUsername">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-400 mb-2 uppercase tracking-wider">Password</label>
                    <input type="password" name="password" required autocomplete="current-password"
                           class="w-full px-4 py-3.5 bg-dark-700/50 border border-white/10 rounded-xl text-white placeholder-gray-600 focus:outline-none focus:border-primary-500/50 focus:ring-2 focus:ring-primary-500/20 transition-all text-sm"
                           placeholder="Enter your password" id="loginPassword">
                </div>
                <button type="submit" id="loginBtn"
                        class="w-full py-3.5 bg-gradient-to-r from-primary-500 to-primary-600 hover:from-primary-400 hover:to-primary-500 text-white font-semibold rounded-xl shadow-lg shadow-primary-500/25 hover:shadow-primary-500/40 transition-all duration-300 text-sm uppercase tracking-wider active:scale-[0.98]">
                    Sign In
                </button>
            </form>

            <p class="text-center text-xs text-gray-600 mt-6">TAASCOR Attendance Monitoring System &copy; <?= date('Y') ?></p>
        </div>
    </div>
</body>
</html>
