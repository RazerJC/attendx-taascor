<?php
/**
 * Password Reset Form & Handler — TAASCOR AttendX
 * Validates reset token and allows setting a new website password.
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

$token = trim($_GET['token'] ?? $_POST['token'] ?? '');
$email = strtolower(trim($_GET['email'] ?? $_POST['email'] ?? ''));

$tokenValid = false;
$user = null;
$error = '';
$success = false;

$db = getDB();

if (!empty($token) && !empty($email)) {
    $stmt = $db->prepare("SELECT id, username, email, password_reset_token, password_reset_expires FROM users WHERE email = ? AND password_reset_token = ?");
    $stmt->execute([$email, $token]);
    $user = $stmt->fetch();

    if ($user) {
        if (!empty($user['password_reset_expires']) && strtotime($user['password_reset_expires']) < time()) {
            $error = 'This password reset link has expired (links are valid for 1 hour). Please request a new one.';
        } else {
            $tokenValid = true;
        }
    } else {
        $error = 'Invalid or expired password reset link.';
    }
} else {
    $error = 'Missing reset token or email address.';
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $tokenValid && $user) {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid or expired session. Please refresh and try again.';
    } else {
        $newPassword = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (empty($newPassword) || empty($confirmPassword)) {
            $error = 'Please fill in both password fields.';
        } elseif (strlen($newPassword) < 8 || !preg_match('/[A-Za-z]/', $newPassword) || !preg_match('/[0-9]/', $newPassword)) {
            $error = 'Password must be at least 8 characters and include at least one letter and one number.';
        } elseif ($newPassword !== $confirmPassword) {
            $error = 'Passwords do not match.';
        } else {
            $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);
            try {
                $upd = $db->prepare("
                    UPDATE users 
                    SET password = ?, 
                        password_reset_token = NULL, 
                        password_reset_expires = NULL,
                        login_attempts = 0,
                        locked_until = NULL,
                        reset_requested = 0
                    WHERE id = ?
                ");
                $upd->execute([$hashedPassword, $user['id']]);

                // Log activity
                $_SESSION['user_id'] = $user['id'];
                logActivity('password_reset', 'User successfully reset password via email token');
                unset($_SESSION['user_id']);

                $success = true;
                $tokenValid = false; // Prevent resubmission
            } catch (PDOException $e) {
                error_log("Password reset failed: " . $e->getMessage());
                $error = 'Failed to update password. Please try again.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" class="min-h-screen">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Set New Password — TAASCOR AttendX</title>
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
    <div class="login-bg"></div>

    <div class="relative z-10 w-full max-w-md">
        <div class="login-card bg-dark-800/80 backdrop-blur-2xl border border-white/10 rounded-3xl p-8 md:p-10 shadow-2xl">
            <!-- Logo -->
            <div class="flex flex-col items-center mb-8">
                <img src="/ATTENDANCE/assets/images/logo.png" alt="TMGS Logo" class="login-logo-img mb-4 login-logo">
                <h1 class="text-xl font-bold text-white tracking-tight">Set New Password</h1>
                <p class="text-[10px] text-gray-500 uppercase tracking-widest mt-1">TAASCOR AttendX Security</p>
            </div>

            <!-- Error -->
            <?php if ($error): ?>
            <div class="mb-6 p-4 bg-red-500/10 border border-red-500/20 rounded-2xl text-red-400 text-xs font-semibold leading-relaxed text-center">
                <?= htmlspecialchars($error) ?>
            </div>
            <?php endif; ?>

            <!-- Success -->
            <?php if ($success): ?>
            <div class="text-center">
                <div class="w-16 h-16 mx-auto mb-4 bg-primary-500/10 border border-primary-500/30 rounded-2xl flex items-center justify-center text-3xl text-primary-400">
                    ✓
                </div>
                <h2 class="text-lg font-bold text-white mb-2">Password Updated!</h2>
                <p class="text-xs text-gray-400 leading-relaxed mb-6">
                    Your AttendX password has been reset successfully. You can now sign in with your business email and new password.
                </p>
                <a href="/ATTENDANCE/index.php" class="inline-block w-full py-3 bg-gradient-to-r from-primary-500 to-primary-600 hover:from-primary-400 hover:to-primary-500 text-white font-bold rounded-xl shadow-lg shadow-primary-900/40 transition-all text-xs uppercase tracking-wider">
                    Sign In Now
                </a>
            </div>
            <?php elseif ($tokenValid): ?>
            <!-- Reset Form -->
            <form method="POST" class="space-y-4">
                <?= csrfField() ?>
                <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                <input type="hidden" name="email" value="<?= htmlspecialchars($email) ?>">

                <div>
                    <label class="block text-[10px] font-semibold text-gray-400 mb-1.5 uppercase tracking-wider">New Password</label>
                    <input type="password" name="password" required autofocus
                           class="w-full px-4 py-3 bg-dark-700/50 border border-white/10 rounded-xl text-white placeholder-gray-600 focus:outline-none focus:border-primary-500/50 focus:ring-2 focus:ring-primary-500/20 transition-all text-xs"
                           placeholder="Min 8 chars, letters + numbers">
                </div>

                <div>
                    <label class="block text-[10px] font-semibold text-gray-400 mb-1.5 uppercase tracking-wider">Confirm New Password</label>
                    <input type="password" name="confirm_password" required
                           class="w-full px-4 py-3 bg-dark-700/50 border border-white/10 rounded-xl text-white placeholder-gray-600 focus:outline-none focus:border-primary-500/50 focus:ring-2 focus:ring-primary-500/20 transition-all text-xs"
                           placeholder="Retype new password">
                </div>

                <div class="p-3 bg-dark-700/30 border border-white/5 rounded-xl">
                    <p class="text-[10px] text-gray-400 leading-relaxed">
                        🔒 <strong class="text-gray-300">Notice:</strong> Do not use your Hostinger webmail mailbox password. Set a unique password for AttendX.
                    </p>
                </div>

                <button type="submit"
                        class="w-full py-3.5 bg-gradient-to-r from-primary-500 to-primary-600 hover:from-primary-400 hover:to-primary-500 text-white font-bold rounded-xl shadow-lg shadow-primary-900/40 transition-all duration-300 text-xs uppercase tracking-wider active:scale-[0.98]">
                    Save New Password
                </button>
            </form>
            <?php else: ?>
            <div class="text-center space-y-4">
                <a href="/ATTENDANCE/forgot_password.php" class="inline-block w-full py-3 bg-primary-600 hover:bg-primary-500 text-white font-bold rounded-xl transition-all text-xs uppercase tracking-wider">
                    Request New Reset Link
                </a>
                <a href="/ATTENDANCE/index.php" class="inline-block text-xs text-primary-400 hover:underline">
                    Return to Sign In
                </a>
            </div>
            <?php endif; ?>

            <div class="flex items-center justify-between mt-8 text-xs border-t border-white/5 pt-6">
                <a href="/ATTENDANCE/index.php" class="text-primary-400 hover:underline">Back to Login</a>
                <a href="/ATTENDANCE/register.php" class="text-gray-500 hover:text-gray-300">Register</a>
            </div>
        </div>
    </div>
</body>
</html>
