<?php
/**
 * Coordinator Forgot Password Request — TAASCOR Attendance Monitoring System
 */
require_once __DIR__ . '/includes/auth.php';

// If already logged in, redirect
if (isLoggedIn()) {
    if ($_SESSION['role'] === 'admin') {
        header('Location: /ATTENDANCE/admin/dashboard.php');
    } else {
        header('Location: /ATTENDANCE/coordinator/dashboard.php');
    }
    exit;
}

$db = getDB();
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');

    if (empty($username)) {
        $error = 'Please enter your username.';
    } else {
        $stmt = $db->prepare("SELECT id, role FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user) {
            if ($user['role'] !== 'coordinator') {
                $error = 'Password reset requests are only available for Coordinator accounts.';
            } else {
                // Set reset requested flag
                $update = $db->prepare("UPDATE users SET reset_requested = 1 WHERE id = ?");
                if ($update->execute([$user['id']])) {
                    // Log activity
                    $log = $db->prepare("INSERT INTO activity_log (user_id, action, details) VALUES (?, 'Password Reset Request', ?)");
                    $log->execute([$user['id'], "Coordinator requested password reset for username: $username"]);
                    
                    $message = '✅ Reset request submitted successfully! Please contact your Administrator to set your new password.';
                } else {
                    $error = 'Failed to submit reset request. Please try again.';
                }
            }
        } else {
            $error = 'Username not found in the system.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password — TAASCOR</title>
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

    <!-- Card -->
    <div class="relative z-10 w-full max-w-md">
        <div class="login-card bg-dark-800/80 backdrop-blur-2xl border border-white/10 rounded-3xl p-8 md:p-10 shadow-2xl">
            <!-- Logo -->
            <div class="flex flex-col items-center mb-8">
                <img src="/ATTENDANCE/assets/images/logo.png" alt="TMGS Logo" class="login-logo-img mb-4 login-logo">
                <h1 class="text-xl font-bold text-white tracking-tight">Reset Password</h1>
                <p class="text-[10px] text-gray-500 uppercase tracking-widest mt-1">Submit request to admin</p>
            </div>

            <!-- Error -->
            <?php if ($error): ?>
            <div class="mb-5 px-4 py-2.5 bg-red-500/10 border border-red-500/20 rounded-xl text-red-400 text-xs text-center font-semibold">
                <?= htmlspecialchars($error) ?>
            </div>
            <?php endif; ?>

            <!-- Success -->
            <?php if ($message): ?>
            <div class="mb-5 p-4 bg-green-500/10 border border-green-500/20 rounded-xl text-green-400 text-xs text-center font-semibold leading-relaxed">
                <?= htmlspecialchars($message) ?>
                <div class="mt-4">
                    <a href="/ATTENDANCE/index.php" class="inline-block px-4 py-2 bg-green-600 hover:bg-green-500 text-white rounded-lg font-bold text-xs uppercase tracking-wider transition-colors">
                        Back to Login
                    </a>
                </div>
            </div>
            <?php endif; ?>

            <?php if (empty($message)): ?>
            <!-- Form -->
            <form method="POST" class="space-y-5">
                <div>
                    <label class="block text-xs font-medium text-gray-400 mb-2 uppercase tracking-wider">Username</label>
                    <input type="text" name="username" required value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                           class="w-full px-4 py-3 bg-dark-700/50 border border-white/10 rounded-xl text-white placeholder-gray-600 focus:outline-none focus:border-primary-500/50 focus:ring-2 focus:ring-primary-500/20 transition-all text-xs"
                           placeholder="Enter your coordinator username">
                </div>
                <button type="submit"
                        class="w-full py-3 bg-gradient-to-r from-primary-500 to-primary-600 hover:from-primary-400 hover:to-primary-500 text-white font-bold rounded-xl shadow-lg transition-all duration-300 text-xs uppercase tracking-wider active:scale-[0.98]">
                    Send Reset Request
                </button>
            </form>
            <?php endif; ?>

            <div class="flex items-center justify-between mt-6 text-xs border-t border-white/5 pt-4">
                <a href="/ATTENDANCE/index.php" class="text-primary-400 hover:underline">Back to Login</a>
                <a href="/ATTENDANCE/register.php" class="text-gray-500 hover:text-gray-300">Register Account</a>
            </div>
        </div>
    </div>
</body>
</html>
