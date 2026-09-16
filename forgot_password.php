<?php
/**
 * Forgot Password — TAASCOR AttendX
 * Secure token-based password reset via @taascor.com business email with admin fallback.
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
$messageType = 'success';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid or expired session. Please refresh and try again.';
    } else {
        $identifier = trim($_POST['identifier'] ?? '');

        if (empty($identifier)) {
            $error = 'Please enter your business email or username.';
        } else {
            // Find user by email or username
            $stmt = $db->prepare("SELECT id, username, email, email_verified, full_name, role, status FROM users WHERE email = ? OR username = ?");
            $stmt->execute([$identifier, $identifier]);
            $user = $stmt->fetch();

            if (!$user) {
                // Generic message to prevent username/email enumeration
                $message = 'If an account exists with that identifier, instructions have been sent or recorded. Please check your inbox.';
                $messageType = 'success';
            } else {
                // If user has an email
                if (!empty($user['email'])) {
                    $token = generateSecureToken();
                    try {
                        $upd = $db->prepare("
                            UPDATE users 
                            SET password_reset_token = ?, 
                                password_reset_expires = DATE_ADD(NOW(), INTERVAL 1 HOUR) 
                            WHERE id = ?
                        ");
                        $upd->execute([$token, $user['id']]);

                        $mailResult = sendPasswordResetEmail($user['email'], $token, $user['full_name']);

                        if ($mailResult['success']) {
                            $message = '✅ A password reset link has been sent to your business email (' . htmlspecialchars($user['email']) . '). The link expires in 1 hour.';
                            $messageType = 'success';
                        } else {
                            // Email failed (e.g. SMTP credentials pending), set reset_requested as fallback
                            $db->prepare("UPDATE users SET reset_requested = 1 WHERE id = ?")->execute([$user['id']]);
                            $message = '⚠️ Automated email delivery is temporarily unavailable. A password reset request has been logged for Administrator review. Please contact your Administrator.';
                            $messageType = 'warning';
                        }
                    } catch (PDOException $e) {
                        error_log("Password reset error: " . $e->getMessage());
                        $error = 'A system error occurred. Please try again later.';
                    }
                } else {
                    // Legacy account with no email linked yet — notify admin
                    try {
                        $db->prepare("UPDATE users SET reset_requested = 1 WHERE id = ?")->execute([$user['id']]);

                        // Notify admins
                        $adminStmt = $db->query("SELECT id FROM users WHERE role = 'admin' AND status = 'active'");
                        $admins = $adminStmt->fetchAll(PDO::FETCH_COLUMN);
                        if (!empty($admins)) {
                            // Check if notifications table exists
                            try {
                                $notifStmt = $db->prepare("INSERT INTO notifications (user_id, sender_id, type, message, link) VALUES (?, ?, 'password_reset_request', ?, '/ATTENDANCE/admin/coordinators.php')");
                                $notifMsg = "Password reset requested by " . ($user['full_name'] ?: $user['username']) . " (@{$user['username']})";
                                foreach ($admins as $adminId) {
                                    $notifStmt->execute([$adminId, $user['id'], $notifMsg]);
                                }
                            } catch (PDOException $ex) {
                                // notifications table optional
                            }
                        }

                        $message = '✅ Legacy account detected without a linked email. A reset request has been submitted to the Administrator.';
                        $messageType = 'success';
                    } catch (PDOException $e) {
                        $error = 'Failed to submit reset request. Please try again.';
                    }
                }
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
    <title>Forgot Password — TAASCOR AttendX</title>
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
                <h1 class="text-xl font-bold text-white tracking-tight">Forgot Password</h1>
                <p class="text-[10px] text-gray-500 uppercase tracking-widest mt-1">Self-Service Account Recovery</p>
            </div>

            <!-- Error Message -->
            <?php if ($error): ?>
            <div class="mb-6 p-4 bg-red-500/10 border border-red-500/20 rounded-2xl text-red-400 text-xs font-semibold leading-relaxed text-center">
                <?= htmlspecialchars($error) ?>
            </div>
            <?php endif; ?>

            <!-- Success / Info Message -->
            <?php if ($message): ?>
            <div class="mb-6 p-4 <?= $messageType === 'success' ? 'bg-green-500/10 border-green-500/20 text-green-400' : 'bg-amber-500/10 border-amber-500/20 text-amber-300' ?> border rounded-2xl text-xs font-semibold leading-relaxed text-center">
                <?= $message ?>
                <div class="mt-4">
                    <a href="/ATTENDANCE/index.php" class="inline-block px-5 py-2.5 bg-primary-600 hover:bg-primary-500 text-white rounded-xl font-bold text-xs uppercase tracking-wider transition-colors shadow-lg shadow-primary-900/30">
                        Back to Sign In
                    </a>
                </div>
            </div>
            <?php endif; ?>

            <?php if (empty($message)): ?>
            <form method="POST" class="space-y-4">
                <?= csrfField() ?>

                <div>
                    <label class="block text-[10px] font-semibold text-gray-400 mb-1.5 uppercase tracking-wider">
                        Business Email or Username
                    </label>
                    <input type="text" name="identifier" required value="<?= htmlspecialchars($_POST['identifier'] ?? '') ?>"
                           class="w-full px-4 py-3 bg-dark-700/50 border border-white/10 rounded-xl text-white placeholder-gray-600 focus:outline-none focus:border-primary-500/50 focus:ring-2 focus:ring-primary-500/20 transition-all text-xs"
                           placeholder="yourname@taascor.com or username">
                    <p class="text-[10px] text-gray-500 mt-1.5">
                        We'll send a secure password reset link to your verified business email.
                    </p>
                </div>

                <button type="submit"
                        class="w-full py-3.5 bg-gradient-to-r from-primary-500 to-primary-600 hover:from-primary-400 hover:to-primary-500 text-white font-bold rounded-xl shadow-lg shadow-primary-900/40 transition-all duration-300 text-xs uppercase tracking-wider active:scale-[0.98]">
                    Send Reset Link
                </button>
            </form>
            <?php endif; ?>

            <div class="flex items-center justify-between mt-8 text-xs border-t border-white/5 pt-6">
                <a href="/ATTENDANCE/index.php" class="text-primary-400 hover:underline">Back to Login</a>
                <a href="/ATTENDANCE/register.php" class="text-gray-500 hover:text-gray-300">Create Account</a>
            </div>
        </div>
    </div>
</body>
</html>
