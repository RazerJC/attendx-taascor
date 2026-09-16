<?php
/**
 * Coordinator Registration — TAASCOR Attendance Monitoring System
 * Requires @taascor.com business email, email verification, and admin approval.
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
$departments = $db->query("SELECT * FROM departments ORDER BY name")->fetchAll();

$message = '';
$messageType = 'success'; // 'success' or 'warning'
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid or expired session. Please refresh and try again.';
    } else {
        $fullName = trim($_POST['full_name'] ?? '');
        $email = strtolower(trim($_POST['email'] ?? ''));
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        $deptId = $_POST['department_id'] ?? null;

        // Basic validation
        if (empty($fullName) || empty($email) || empty($password) || empty($confirmPassword)) {
            $error = 'All fields are required.';
        } elseif (!validateTaascorEmail($email)) {
            $error = 'Only official @taascor.com business email addresses are accepted.';
        } elseif (strlen($password) < 8 || !preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) {
            $error = 'Password must be at least 8 characters and include at least one letter and one number.';
        } elseif ($password !== $confirmPassword) {
            $error = 'Passwords do not match.';
        } else {
            // Check if email is already registered
            $stmt = $db->prepare("SELECT id, email_verified, status FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $existingUser = $stmt->fetch();

            if ($existingUser) {
                $error = 'This business email is already registered. If you forgot your password, please use the password reset page.';
            } else {
                // Generate a unique username based on the email local-part
                $localPart = explode('@', $email)[0];
                $cleanLocal = preg_replace('/[^a-zA-Z0-9._-]/', '', $localPart);
                if (empty($cleanLocal)) {
                    $cleanLocal = 'user_' . substr(bin2hex(random_bytes(4)), 0, 6);
                }
                
                // Ensure username is unique
                $candidateUsername = substr($cleanLocal, 0, 45);
                $uStmt = $db->prepare("SELECT id FROM users WHERE username = ?");
                $uStmt->execute([$candidateUsername]);
                if ($uStmt->fetch()) {
                    $candidateUsername = substr($cleanLocal, 0, 40) . '_' . substr(bin2hex(random_bytes(2)), 0, 4);
                }

                $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
                $deptValue = ($deptId === 'none' || empty($deptId)) ? null : (int)$deptId;
                $token = generateSecureToken();

                try {
                    $stmt = $db->prepare("
                        INSERT INTO users (
                            username, email, email_verified, email_verification_token, email_verification_expires,
                            password, full_name, role, department_id, status
                        ) VALUES (
                            ?, ?, 0, ?, DATE_ADD(NOW(), INTERVAL 24 HOUR),
                            ?, ?, 'coordinator', ?, 'inactive'
                        )
                    ");

                    if ($stmt->execute([$candidateUsername, $email, $token, $hashedPassword, $fullName, $deptValue])) {
                        // Send verification email via SMTP
                        $mailResult = sendVerificationEmail($email, $token, $fullName);

                        if ($mailResult['success']) {
                            $message = '✅ Registration submitted successfully! We sent a verification link to ' . htmlspecialchars($email) . '. Please check your inbox and click the link to verify your email, then await Administrator approval.';
                            $messageType = 'success';
                        } else {
                            // Account created, but email failed (e.g. SMTP credentials not yet set up)
                            $message = '⚠️ Registration submitted! However, the verification email could not be sent automatically (' . htmlspecialchars($mailResult['message']) . '). Please contact the Administrator to verify and activate your account.';
                            $messageType = 'warning';
                        }
                    } else {
                        $error = 'An error occurred during registration. Please try again.';
                    }
                } catch (PDOException $e) {
                    error_log("Registration error: " . $e->getMessage());
                    $error = 'Registration could not be completed. Please try again or contact support.';
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
    <title>Register Coordinator — TAASCOR AttendX</title>
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

    <!-- Registration Card -->
    <div class="relative z-10 w-full max-w-md my-8">
        <div class="login-card bg-dark-800/80 backdrop-blur-2xl border border-white/10 rounded-3xl p-8 shadow-2xl">
            <!-- Logo -->
            <div class="flex flex-col items-center mb-6">
                <img src="/ATTENDANCE/assets/images/logo.png" alt="TMGS Logo" class="login-logo-img mb-3 login-logo">
                <h1 class="text-xl font-bold text-white tracking-tight">Register Coordinator</h1>
                <p class="text-[10px] text-gray-500 uppercase tracking-widest mt-1">TAASCOR Business Email Required</p>
            </div>

            <!-- Error -->
            <?php if ($error): ?>
            <div class="mb-4 px-4 py-3 bg-red-500/10 border border-red-500/20 rounded-xl text-red-400 text-xs text-center font-semibold leading-relaxed">
                <?= htmlspecialchars($error) ?>
            </div>
            <?php endif; ?>

            <!-- Success / Warning Message -->
            <?php if ($message): ?>
            <div class="mb-4 p-4 <?= $messageType === 'success' ? 'bg-green-500/10 border-green-500/20 text-green-400' : 'bg-amber-500/10 border-amber-500/20 text-amber-300' ?> border rounded-xl text-xs font-semibold leading-relaxed">
                <p><?= $message ?></p>
                <div class="mt-4 text-center">
                    <a href="/ATTENDANCE/index.php" class="inline-block px-5 py-2.5 bg-primary-600 hover:bg-primary-500 text-white rounded-xl font-bold text-xs uppercase tracking-wider transition-colors shadow-lg shadow-primary-900/30">
                        Go to Sign In
                    </a>
                </div>
            </div>
            <?php endif; ?>

            <?php if (empty($message)): ?>
            <!-- Form -->
            <form method="POST" class="space-y-4" novalidate id="registerForm">
                <?= csrfField() ?>

                <div>
                    <label class="block text-[10px] font-semibold text-gray-400 mb-1.5 uppercase tracking-wider">Full Name</label>
                    <input type="text" name="full_name" required value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>"
                           class="w-full px-4 py-2.5 bg-dark-700/50 border border-white/10 rounded-xl text-white placeholder-gray-600 focus:outline-none focus:border-primary-500/50 focus:ring-2 focus:ring-primary-500/20 transition-all text-xs"
                           placeholder="e.g. John Carl Bañares">
                </div>

                <div>
                    <label class="block text-[10px] font-semibold text-gray-400 mb-1.5 uppercase tracking-wider">
                        Business Email <span class="text-primary-400 font-normal">(@taascor.com)</span>
                    </label>
                    <input type="email" name="email" id="email" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                           class="w-full px-4 py-2.5 bg-dark-700/50 border border-white/10 rounded-xl text-white placeholder-gray-600 focus:outline-none focus:border-primary-500/50 focus:ring-2 focus:ring-primary-500/20 transition-all text-xs"
                           placeholder="username@taascor.com">
                    <p id="emailHelp" class="text-[10px] text-gray-500 mt-1">Must be your official @taascor.com business mailbox</p>
                </div>

                <div>
                    <label class="block text-[10px] font-semibold text-gray-400 mb-1.5 uppercase tracking-wider">Department Assignment</label>
                    <select name="department_id" class="w-full px-4 py-2.5 bg-dark-700/50 border border-white/10 rounded-xl text-white focus:outline-none focus:border-primary-500/50 focus:ring-2 focus:ring-primary-500/20 transition-all text-xs">
                        <option value="none">No Department / Assign Later</option>
                        <?php foreach ($departments as $dept): ?>
                            <option value="<?= $dept['id'] ?>" <?= (isset($_POST['department_id']) && $_POST['department_id'] == $dept['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($dept['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[10px] font-semibold text-gray-400 mb-1.5 uppercase tracking-wider">AttendX Password</label>
                        <input type="password" name="password" id="password" required
                               class="w-full px-4 py-2.5 bg-dark-700/50 border border-white/10 rounded-xl text-white placeholder-gray-600 focus:outline-none focus:border-primary-500/50 focus:ring-2 focus:ring-primary-500/20 transition-all text-xs"
                               placeholder="Min 8 chars, letters+digits">
                    </div>
                    <div>
                        <label class="block text-[10px] font-semibold text-gray-400 mb-1.5 uppercase tracking-wider">Confirm Password</label>
                        <input type="password" name="confirm_password" id="confirm_password" required
                               class="w-full px-4 py-2.5 bg-dark-700/50 border border-white/10 rounded-xl text-white placeholder-gray-600 focus:outline-none focus:border-primary-500/50 focus:ring-2 focus:ring-primary-500/20 transition-all text-xs"
                               placeholder="Retype password">
                    </div>
                </div>

                <div class="p-3 bg-dark-700/30 border border-white/5 rounded-xl">
                    <p class="text-[10px] text-gray-400 leading-relaxed">
                        🔒 <strong class="text-gray-300">Privacy Notice:</strong> Create a separate password for this website. Do not enter your Hostinger email mailbox password.
                    </p>
                </div>

                <button type="submit"
                        class="w-full py-3 bg-gradient-to-r from-primary-500 to-primary-600 hover:from-primary-400 hover:to-primary-500 text-white font-bold rounded-xl shadow-lg shadow-primary-900/40 transition-all duration-300 text-xs uppercase tracking-wider active:scale-[0.98]">
                    Register Account
                </button>
            </form>
            <?php endif; ?>

            <div class="flex items-center justify-between mt-6 text-xs border-t border-white/5 pt-4">
                <a href="/ATTENDANCE/index.php" class="text-primary-400 hover:underline">Back to Login</a>
                <a href="/ATTENDANCE/forgot_password.php" class="text-gray-500 hover:text-gray-300">Forgot Password?</a>
            </div>
        </div>
    </div>

    <script>
        const emailInput = document.getElementById('email');
        const emailHelp = document.getElementById('emailHelp');
        if (emailInput) {
            emailInput.addEventListener('input', function() {
                const val = this.value.trim().toLowerCase();
                if (val.length > 0 && !val.endsWith('@taascor.com')) {
                    emailHelp.textContent = '⚠️ Must end with @taascor.com';
                    emailHelp.className = 'text-[10px] text-amber-400 mt-1';
                } else if (val.endsWith('@taascor.com')) {
                    emailHelp.textContent = '✓ Valid TAASCOR domain';
                    emailHelp.className = 'text-[10px] text-primary-400 mt-1';
                } else {
                    emailHelp.textContent = 'Must be your official @taascor.com business mailbox';
                    emailHelp.className = 'text-[10px] text-gray-500 mt-1';
                }
            });
        }
    </script>
</body>
</html>
