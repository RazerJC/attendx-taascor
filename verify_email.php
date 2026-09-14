<?php
/**
 * Email Verification Endpoint — TAASCOR AttendX
 * Validates business email verification tokens.
 */
require_once __DIR__ . '/includes/auth.php';

$token = trim($_GET['token'] ?? '');
$email = strtolower(trim($_GET['email'] ?? ''));

$status = 'error'; // 'success', 'already_verified', 'expired', 'invalid'
$message = '';
$user = null;

if (empty($token) || empty($email)) {
    $status = 'invalid';
    $message = 'Invalid verification link. Missing token or email parameter.';
} else {
    $db = getDB();
    $stmt = $db->prepare("SELECT id, username, email, email_verified, email_verification_expires, status FROM users WHERE email = ? AND email_verification_token = ?");
    $stmt->execute([$email, $token]);
    $user = $stmt->fetch();

    if (!$user) {
        // Check if already verified
        $chk = $db->prepare("SELECT id, email_verified, status FROM users WHERE email = ?");
        $chk->execute([$email]);
        $existing = $chk->fetch();

        if ($existing && !empty($existing['email_verified'])) {
            $status = 'already_verified';
            $message = 'This business email has already been verified! You can proceed to sign in.';
        } else {
            $status = 'invalid';
            $message = 'The verification link is invalid or has already been used.';
        }
    } else {
        // Check expiry
        if (!empty($user['email_verification_expires']) && strtotime($user['email_verification_expires']) < time()) {
            $status = 'expired';
            $message = 'This verification link has expired (links are valid for 24 hours). Please request a new verification link.';
        } else {
            // Valid token! Mark email as verified
            try {
                $upd = $db->prepare("
                    UPDATE users 
                    SET email_verified = 1, 
                        email_verification_token = NULL, 
                        email_verification_expires = NULL 
                    WHERE id = ?
                ");
                $upd->execute([$user['id']]);

                // Log activity if function exists
                if (function_exists('logActivity')) {
                    $_SESSION['user_id'] = $user['id'];
                    logActivity('verify_email', 'Business email verified: ' . $email);
                    unset($_SESSION['user_id']);
                }

                $status = 'success';
                if ($user['status'] === 'active') {
                    $message = 'Your business email has been verified successfully! Your account is active and you can sign in now.';
                } else {
                    $message = 'Your business email has been verified successfully! Your account is currently awaiting Administrator approval before you can sign in.';
                }
            } catch (PDOException $e) {
                error_log("Verification update error: " . $e->getMessage());
                $status = 'error';
                $message = 'A system error occurred while updating your verification status. Please contact support.';
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
    <title>Email Verification — TAASCOR AttendX</title>
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

    <div class="relative z-10 w-full max-w-md my-8">
        <div class="login-card bg-dark-800/80 backdrop-blur-2xl border border-white/10 rounded-3xl p-8 shadow-2xl text-center">
            <!-- Logo -->
            <div class="flex flex-col items-center mb-6">
                <img src="/ATTENDANCE/assets/images/logo.png" alt="TMGS Logo" class="login-logo-img mb-3 login-logo">
                <h1 class="text-xl font-bold text-white tracking-tight">Email Verification</h1>
                <p class="text-[10px] text-gray-500 uppercase tracking-widest mt-1">TAASCOR AttendX Security</p>
            </div>

            <?php if ($status === 'success' || $status === 'already_verified'): ?>
                <div class="w-16 h-16 mx-auto mb-4 bg-primary-500/10 border border-primary-500/30 rounded-2xl flex items-center justify-center text-3xl text-primary-400">
                    ✓
                </div>
                <h2 class="text-lg font-bold text-white mb-2">
                    <?= $status === 'success' ? 'Verification Complete' : 'Already Verified' ?>
                </h2>
                <p class="text-xs text-gray-400 leading-relaxed mb-6">
                    <?= htmlspecialchars($message) ?>
                </p>
                <a href="/ATTENDANCE/index.php" class="inline-block w-full py-3 bg-gradient-to-r from-primary-500 to-primary-600 hover:from-primary-400 hover:to-primary-500 text-white font-bold rounded-xl shadow-lg shadow-primary-900/40 transition-all text-xs uppercase tracking-wider">
                    Go to Sign In
                </a>
            <?php else: ?>
                <div class="w-16 h-16 mx-auto mb-4 bg-red-500/10 border border-red-500/30 rounded-2xl flex items-center justify-center text-3xl text-red-400">
                    ✕
                </div>
                <h2 class="text-lg font-bold text-white mb-2">Verification Failed</h2>
                <p class="text-xs text-red-400 leading-relaxed mb-6">
                    <?= htmlspecialchars($message) ?>
                </p>
                <div class="space-y-3">
                    <a href="/ATTENDANCE/register.php" class="inline-block w-full py-3 bg-dark-700 hover:bg-dark-600 text-white font-bold rounded-xl border border-white/10 transition-all text-xs uppercase tracking-wider">
                        Back to Registration
                    </a>
                    <a href="/ATTENDANCE/index.php" class="inline-block text-xs text-primary-400 hover:underline">
                        Return to Sign In
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
