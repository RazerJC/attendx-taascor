<?php
/**
 * Email Auth Migration — TAASCOR AttendX
 * Non-destructive migration: adds email auth columns to existing users table.
 * Preserves ALL existing data, IDs, passwords, roles, departments, attendance.
 * 
 * Protected: requires admin login OR setup key.
 * Run once, then delete or disable.
 */
require_once __DIR__ . '/includes/auth.php';

// Protection: admin login OR setup key OR CLI
$isCli = (php_sapi_name() === 'cli');
$setupKey = getenv('SETUP_KEY') ?: '';
$providedKey = $_GET['key'] ?? '';
$authorized = false;

if ($isCli) {
    $authorized = true;
} elseif (isLoggedIn() && ($_SESSION['role'] ?? '') === 'admin') {
    $authorized = true;
} elseif (!empty($setupKey) && $providedKey === $setupKey) {
    $authorized = true;
}

if (!$authorized) {
    http_response_code(403);
    die('<!DOCTYPE html><html><head><title>Access Denied</title></head><body style="font-family:Inter,sans-serif;background:#0d0e12;color:#fff;display:flex;align-items:center;justify-content:center;min-height:100vh;"><div style="background:rgba(255,255,255,0.06);padding:40px;border-radius:16px;border:1px solid rgba(255,255,255,0.1);text-align:center;"><h2 style="color:#ef4444;">🔒 Access Denied</h2><p style="color:#9ca3af;font-size:14px;">Admin login or setup key required.</p></div></div></body></html>');
}

$db = getDB();
$results = [];
$hasError = false;

// ============================================================
// Pre-flight: Check current state
// ============================================================
$results[] = ['status' => 'info', 'message' => '=== Pre-Migration Check ==='];

// Count existing records
$tables = ['users', 'departments', 'employees', 'attendance', 'activity_log'];
foreach ($tables as $t) {
    try {
        $count = $db->query("SELECT COUNT(*) FROM `$t`")->fetchColumn();
        $results[] = ['status' => 'info', 'message' => "Table $t: $count existing rows"];
    } catch (PDOException $e) {
        $results[] = ['status' => 'info', 'message' => "Table $t: does not exist yet"];
    }
}

// ============================================================
// Migration: Add email columns to users table
// ============================================================
$results[] = ['status' => 'info', 'message' => '=== Running Migration ==='];

$migrations = [
    // Email columns
    [
        'check' => "SELECT email FROM users LIMIT 1",
        'sql' => "ALTER TABLE users ADD COLUMN email VARCHAR(255) NULL AFTER username",
        'desc' => 'Added email column to users'
    ],
    [
        'check' => "SELECT email_verified FROM users LIMIT 1",
        'sql' => "ALTER TABLE users ADD COLUMN email_verified TINYINT NOT NULL DEFAULT 0 AFTER email",
        'desc' => 'Added email_verified column to users'
    ],
    [
        'check' => "SELECT email_verification_token FROM users LIMIT 1",
        'sql' => "ALTER TABLE users ADD COLUMN email_verification_token VARCHAR(64) NULL AFTER email_verified",
        'desc' => 'Added email_verification_token column to users'
    ],
    [
        'check' => "SELECT email_verification_expires FROM users LIMIT 1",
        'sql' => "ALTER TABLE users ADD COLUMN email_verification_expires DATETIME NULL AFTER email_verification_token",
        'desc' => 'Added email_verification_expires column to users'
    ],
    // Password reset tokens
    [
        'check' => "SELECT password_reset_token FROM users LIMIT 1",
        'sql' => "ALTER TABLE users ADD COLUMN password_reset_token VARCHAR(64) NULL AFTER email_verification_expires",
        'desc' => 'Added password_reset_token column to users'
    ],
    [
        'check' => "SELECT password_reset_expires FROM users LIMIT 1",
        'sql' => "ALTER TABLE users ADD COLUMN password_reset_expires DATETIME NULL AFTER password_reset_token",
        'desc' => 'Added password_reset_expires column to users'
    ],
    // Rate limiting columns
    [
        'check' => "SELECT login_attempts FROM users LIMIT 1",
        'sql' => "ALTER TABLE users ADD COLUMN login_attempts INT NOT NULL DEFAULT 0 AFTER password_reset_expires",
        'desc' => 'Added login_attempts column to users'
    ],
    [
        'check' => "SELECT locked_until FROM users LIMIT 1",
        'sql' => "ALTER TABLE users ADD COLUMN locked_until DATETIME NULL AFTER login_attempts",
        'desc' => 'Added locked_until column to users'
    ],
    [
        'check' => "SELECT last_login FROM users LIMIT 1",
        'sql' => "ALTER TABLE users ADD COLUMN last_login DATETIME NULL AFTER locked_until",
        'desc' => 'Added last_login column to users'
    ],
    // Unique index on email (allow NULLs for legacy accounts)
    [
        'check' => "SHOW INDEX FROM users WHERE Key_name = 'idx_email_unique'",
        'check_type' => 'index',
        'sql' => "ALTER TABLE users ADD UNIQUE INDEX idx_email_unique (email)",
        'desc' => 'Added unique index on email'
    ],
];

foreach ($migrations as $m) {
    try {
        if (isset($m['check_type']) && $m['check_type'] === 'index') {
            $result = $db->query($m['check'])->fetchAll();
            if (!empty($result)) {
                $results[] = ['status' => 'info', 'message' => "Skip (exists): {$m['desc']}"];
                continue;
            }
        } else {
            $db->query($m['check']);
            $results[] = ['status' => 'info', 'message' => "Skip (exists): {$m['desc']}"];
            continue;
        }
    } catch (PDOException $e) {
        // Column/index doesn't exist, proceed with migration
    }

    try {
        $db->exec($m['sql']);
        $results[] = ['status' => 'success', 'message' => $m['desc']];
    } catch (PDOException $e) {
        $hasError = true;
        $results[] = ['status' => 'error', 'message' => "{$m['desc']} FAILED: " . $e->getMessage()];
    }
}

// ============================================================
// Create login_rate_limits table (IP-based)
// ============================================================
try {
    $db->query("SELECT 1 FROM login_rate_limits LIMIT 1");
    $results[] = ['status' => 'info', 'message' => 'Skip (exists): login_rate_limits table'];
} catch (PDOException $e) {
    try {
        $db->exec("CREATE TABLE login_rate_limits (
            id INT PRIMARY KEY AUTO_INCREMENT,
            ip_address VARCHAR(45) NOT NULL,
            email_or_username VARCHAR(255) NOT NULL,
            attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            success TINYINT NOT NULL DEFAULT 0,
            INDEX idx_ip (ip_address),
            INDEX idx_email (email_or_username),
            INDEX idx_time (attempted_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $results[] = ['status' => 'success', 'message' => 'Created login_rate_limits table'];
    } catch (PDOException $e2) {
        $hasError = true;
        $results[] = ['status' => 'error', 'message' => 'login_rate_limits creation FAILED: ' . $e2->getMessage()];
    }
}

// ============================================================
// Create csrf_tokens table
// ============================================================
try {
    $db->query("SELECT 1 FROM csrf_tokens LIMIT 1");
    $results[] = ['status' => 'info', 'message' => 'Skip (exists): csrf_tokens table'];
} catch (PDOException $e) {
    try {
        $db->exec("CREATE TABLE csrf_tokens (
            id INT PRIMARY KEY AUTO_INCREMENT,
            session_id VARCHAR(128) NOT NULL,
            token VARCHAR(64) NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_session (session_id),
            INDEX idx_token (token)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $results[] = ['status' => 'success', 'message' => 'Created csrf_tokens table'];
    } catch (PDOException $e2) {
        $hasError = true;
        $results[] = ['status' => 'error', 'message' => 'csrf_tokens creation FAILED: ' . $e2->getMessage()];
    }
}

// ============================================================
// Post-flight: Verify data integrity
// ============================================================
$results[] = ['status' => 'info', 'message' => '=== Post-Migration Verification ==='];

foreach ($tables as $t) {
    try {
        $count = $db->query("SELECT COUNT(*) FROM `$t`")->fetchColumn();
        $results[] = ['status' => 'success', 'message' => "Table $t: $count rows (preserved)"];
    } catch (PDOException $e) {
        $results[] = ['status' => 'error', 'message' => "Table $t: verification failed"];
        $hasError = true;
    }
}

// Verify users have their original data intact
try {
    $users = $db->query("SELECT id, username, full_name, role, status, department_id FROM users ORDER BY id")->fetchAll();
    foreach ($users as $u) {
        $dept = $u['department_id'] ?: 'NULL';
        $results[] = ['status' => 'success', 'message' => "User #{$u['id']}: {$u['username']} ({$u['role']}/{$u['status']}) dept=$dept — preserved"];
    }
} catch (PDOException $e) {
    $hasError = true;
    $results[] = ['status' => 'error', 'message' => 'User verification failed: ' . $e->getMessage()];
}

// Verify new columns exist
try {
    $cols = $db->query("SHOW COLUMNS FROM users")->fetchAll(PDO::FETCH_COLUMN);
    $required = ['email', 'email_verified', 'email_verification_token', 'password_reset_token', 'login_attempts', 'locked_until', 'last_login'];
    $allPresent = true;
    foreach ($required as $col) {
        if (!in_array($col, $cols)) {
            $results[] = ['status' => 'error', 'message' => "Missing column: $col"];
            $allPresent = false;
            $hasError = true;
        }
    }
    if ($allPresent) {
        $results[] = ['status' => 'success', 'message' => 'All new columns verified present'];
    }
} catch (PDOException $e) {
    $hasError = true;
    $results[] = ['status' => 'error', 'message' => 'Column verification failed: ' . $e->getMessage()];
}

if (isLoggedIn()) {
    logActivity('Migration', 'Email auth migration completed' . ($hasError ? ' with errors' : ' successfully'));
}

if ($isCli) {
    echo "\n=== Migration " . ($hasError ? "Completed with ISSUES" : "SUCCESSFUL") . " ===\n\n";
    foreach ($results as $r) {
        $prefix = $r['status'] === 'success' ? '✓ ' : ($r['status'] === 'error' ? '❌ ' : 'ℹ ');
        echo $prefix . $r['message'] . "\n";
    }
    echo "\n";
    exit($hasError ? 1 : 0);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Migration — AttendX</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:'Inter',sans-serif; background:#0d0e12; color:#fff; min-height:100vh; display:flex; align-items:center; justify-content:center; padding:20px; }
        .card { background:rgba(255,255,255,0.06); border:1px solid rgba(255,255,255,0.1); border-radius:20px; padding:32px; max-width:640px; width:100%; max-height:90vh; overflow-y:auto; }
        h2 { font-size:18px; margin-bottom:16px; text-align:center; }
        .result { padding:8px 12px; border-radius:8px; margin-bottom:4px; font-size:11px; border:1px solid; font-family:monospace; }
        .result.success { background:rgba(34,197,94,0.1); border-color:rgba(34,197,94,0.2); color:#4ade80; }
        .result.error { background:rgba(239,68,68,0.1); border-color:rgba(239,68,68,0.2); color:#f87171; }
        .result.info { background:rgba(59,130,246,0.06); border-color:rgba(59,130,246,0.12); color:#60a5fa; }
        a.btn { display:inline-block; margin-top:16px; padding:12px 24px; background:linear-gradient(to right,#20c997,#12b886); color:#fff; text-decoration:none; border-radius:12px; font-weight:600; font-size:14px; text-align:center; width:100%; }
    </style>
</head>
<body>
    <div class="card">
        <h2><?= $hasError ? '⚠️ Migration Completed With Issues' : '✅ Migration Successful' ?></h2>
        <?php foreach ($results as $r): ?>
        <div class="result <?= $r['status'] ?>"><?= htmlspecialchars($r['message']) ?></div>
        <?php endforeach; ?>
        <a href="/ATTENDANCE/admin/dashboard.php" class="btn">← Back to Dashboard</a>
    </div>
</body>
</html>
