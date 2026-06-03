<?php
/**
 * Coordinator Registration — TAASCOR Attendance Monitoring System
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
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = trim($_POST['full_name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $deptId = $_POST['department_id'] ?? null;

    if (empty($fullName) || empty($username) || empty($password) || empty($confirmPassword)) {
        $error = 'All fields are required.';
    } elseif ($password !== $confirmPassword) {
        $error = 'Passwords do not match.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } else {
        // Check if username already exists
        $stmt = $db->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            $error = 'Username is already taken.';
        } else {
            // Insert user
            $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $db->prepare("
                INSERT INTO users (username, password, full_name, role, department_id, status)
                VALUES (?, ?, ?, 'coordinator', ?, 'inactive')
            ");
            
            $deptValue = $deptId === 'none' || empty($deptId) ? null : $deptId;
            
            if ($stmt->execute([$username, $hashedPassword, $fullName, $deptValue])) {
                $message = '✅ Registration submitted successfully! Please wait for Admin approval before logging in.';
            } else {
                $error = 'An error occurred. Please try again.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register Coordinator — TAASCOR</title>
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

    <!-- Registration Card -->
    <div class="relative z-10 w-full max-w-md my-8">
        <div class="login-card bg-dark-800/80 backdrop-blur-2xl border border-white/10 rounded-3xl p-8 shadow-2xl">
            <!-- Logo -->
            <div class="flex flex-col items-center mb-6">
                <img src="/ATTENDANCE/assets/images/logo.png" alt="TMGS Logo" class="login-logo-img mb-3 login-logo">
                <h1 class="text-xl font-bold text-white tracking-tight">Register Coordinator</h1>
                <p class="text-[10px] text-gray-500 uppercase tracking-widest mt-1">Submit account for approval</p>
            </div>

            <!-- Error -->
            <?php if ($error): ?>
            <div class="mb-4 px-4 py-2.5 bg-red-500/10 border border-red-500/20 rounded-xl text-red-400 text-xs text-center font-semibold">
                <?= htmlspecialchars($error) ?>
            </div>
            <?php endif; ?>

            <!-- Success Message -->
            <?php if ($message): ?>
            <div class="mb-4 p-4 bg-green-500/10 border border-green-500/20 rounded-xl text-green-400 text-xs text-center font-semibold leading-relaxed">
                <?= htmlspecialchars($message) ?>
                <div class="mt-3">
                    <a href="/ATTENDANCE/index.php" class="inline-block px-4 py-2 bg-green-600 hover:bg-green-500 text-white rounded-lg font-bold text-xs uppercase tracking-wider transition-colors">
                        Go to Sign In
                    </a>
                </div>
            </div>
            <?php endif; ?>

            <?php if (empty($message)): ?>
            <!-- Form -->
            <form method="POST" class="space-y-4">
                <div>
                    <label class="block text-[10px] font-semibold text-gray-400 mb-1.5 uppercase tracking-wider">Full Name</label>
                    <input type="text" name="full_name" required value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>"
                           class="w-full px-4 py-2.5 bg-dark-700/50 border border-white/10 rounded-xl text-white placeholder-gray-600 focus:outline-none focus:border-primary-500/50 focus:ring-2 focus:ring-primary-500/20 transition-all text-xs"
                           placeholder="e.g. Mark Pasamba">
                </div>
                <div>
                    <label class="block text-[10px] font-semibold text-gray-400 mb-1.5 uppercase tracking-wider">Username</label>
                    <input type="text" name="username" required value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                           class="w-full px-4 py-2.5 bg-dark-700/50 border border-white/10 rounded-xl text-white placeholder-gray-600 focus:outline-none focus:border-primary-500/50 focus:ring-2 focus:ring-primary-500/20 transition-all text-xs"
                           placeholder="e.g. coor_ops">
                </div>
                <div>
                    <label class="block text-[10px] font-semibold text-gray-400 mb-1.5 uppercase tracking-wider">Department</label>
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
                        <label class="block text-[10px] font-semibold text-gray-400 mb-1.5 uppercase tracking-wider">Password</label>
                        <input type="password" name="password" required
                               class="w-full px-4 py-2.5 bg-dark-700/50 border border-white/10 rounded-xl text-white placeholder-gray-600 focus:outline-none focus:border-primary-500/50 focus:ring-2 focus:ring-primary-500/20 transition-all text-xs"
                               placeholder="Min 6 characters">
                    </div>
                    <div>
                        <label class="block text-[10px] font-semibold text-gray-400 mb-1.5 uppercase tracking-wider">Confirm Password</label>
                        <input type="password" name="confirm_password" required
                               class="w-full px-4 py-2.5 bg-dark-700/50 border border-white/10 rounded-xl text-white placeholder-gray-600 focus:outline-none focus:border-primary-500/50 focus:ring-2 focus:ring-primary-500/20 transition-all text-xs"
                               placeholder="Retype password">
                    </div>
                </div>
                <button type="submit"
                        class="w-full py-3 bg-gradient-to-r from-primary-500 to-primary-600 hover:from-primary-400 hover:to-primary-500 text-white font-bold rounded-xl shadow-lg transition-all duration-300 text-xs uppercase tracking-wider active:scale-[0.98]">
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
</body>
</html>
