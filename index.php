<?php
/**
 * Login & Logout — TAASCOR Attendance Monitoring System
 * Supports both email (@taascor.com) and legacy username login.
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
$rateLimitMsg = '';

// Handle login POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF validation
    if (!validateCsrfToken()) {
        $error = 'Security token expired. Please try again.';
    } else {
        $loginIdentifier = strtolower(trim($_POST['login_email'] ?? ''));
        $password = $_POST['password'] ?? '';

        if ($loginIdentifier && $password) {
            // Rate limit check
            $rateCheck = checkLoginRateLimit($loginIdentifier);
            if (!$rateCheck['allowed']) {
                $minutes = ceil($rateCheck['retry_after'] / 60);
                $error = "Too many login attempts. Please try again in $minutes minute(s).";
            } else {
                $db = getDB();

                // Determine if login is email or username
                $isEmail = strpos($loginIdentifier, '@') !== false;
                
                if ($isEmail) {
                    $stmt = $db->prepare("SELECT * FROM users WHERE LOWER(email) = ?");
                } else {
                    $stmt = $db->prepare("SELECT * FROM users WHERE LOWER(username) = ?");
                }
                $stmt->execute([$loginIdentifier]);
                $user = $stmt->fetch();

                if ($user) {
                    // Check account lock
                    $lockCheck = checkAccountLock($user);
                    if ($lockCheck['locked']) {
                        $minutes = ceil($lockCheck['retry_after'] / 60);
                        $error = "Account temporarily locked. Try again in $minutes minute(s).";
                        recordLoginAttempt($loginIdentifier, false);
                    } elseif ($user['status'] === 'inactive') {
                        $error = 'Your coordinator account is pending administrator approval.';
                        recordLoginAttempt($loginIdentifier, false);
                    } elseif ($isEmail && empty($user['email_verified'])) {
                        $error = 'Please verify your email address first. Check your @taascor.com inbox.';
                        recordLoginAttempt($loginIdentifier, false);
                    } elseif (password_verify($password, $user['password'])) {
                        // Successful login
                        setLoginSession($user);
                        recordLoginAttempt($loginIdentifier, true);
                        resetLoginAttempts($user['id']);

                        // Log activity
                        $log = $db->prepare("INSERT INTO activity_log (user_id, action, details) VALUES (?, ?, ?)");
                        $log->execute([$user['id'], 'Login', 'User logged in via ' . ($isEmail ? 'email' : 'username')]);

                        // Redirect by role
                        if ($user['role'] === 'admin') {
                            header('Location: /ATTENDANCE/admin/dashboard.php');
                        } else {
                            header('Location: /ATTENDANCE/coordinator/dashboard.php');
                        }
                        exit;
                    } else {
                        // Wrong password
                        incrementLoginAttempts($user['id']);
                        recordLoginAttempt($loginIdentifier, false);
                        $error = 'Invalid credentials. Please check your email/username and password.';
                    }
                } else {
                    recordLoginAttempt($loginIdentifier, false);
                    $error = 'Invalid credentials. Please check your email/username and password.';
                }
            }
        } else {
            $error = 'Please enter both your email/username and password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" class="min-h-screen">
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
<body class="min-h-screen bg-dark-900 font-sans antialiased flex items-center justify-center p-4">
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
                <?php csrfField(); ?>
                <div>
                    <label class="block text-xs font-medium text-gray-400 mb-2 uppercase tracking-wider">Business Email or Username</label>
                    <input type="text" name="login_email" required autocomplete="email"
                           value="<?= htmlspecialchars($_POST['login_email'] ?? '') ?>"
                           class="w-full px-4 py-3.5 bg-dark-700/50 border border-white/10 rounded-xl text-white placeholder-gray-600 focus:outline-none focus:border-primary-500/50 focus:ring-2 focus:ring-primary-500/20 transition-all text-sm"
                           placeholder="you@taascor.com or username" id="loginEmail">
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

            <div class="flex items-center justify-between mt-6 text-xs border-t border-white/5 pt-4">
                <a href="/ATTENDANCE/register.php" class="text-primary-400 hover:underline">Register Coordinator</a>
                <a href="/ATTENDANCE/forgot_password.php" class="text-gray-500 hover:text-gray-300">Forgot Password?</a>
            </div>

            <p class="text-center text-xs text-gray-600 mt-6">TAASCOR Attendance Monitoring System &copy; <?= date('Y') ?></p>
        </div>
    </div>
</body>
</html>
