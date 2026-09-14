<?php
/**
 * Admin Password Reset Utility — TAASCOR AttendX
 * Restricted to CLI or active Admin session / Setup Key.
 */
require_once __DIR__ . '/includes/auth.php';

$isCli = (php_sapi_name() === 'cli');
$setupKey = getenv('SETUP_KEY') ?: '';
$providedKey = $_GET['key'] ?? '';
$authorized = $isCli || (isLoggedIn() && ($_SESSION['role'] ?? '') === 'admin') || (!empty($setupKey) && $providedKey === $setupKey);

if (!$authorized) {
    http_response_code(403);
    die("Access Denied: Admin session or valid SETUP_KEY required.\n");
}

try {
    $db = getDB();
    
    // Check if admin exists
    $admin = $db->query("SELECT id, username FROM users WHERE username = 'admin'")->fetch();
    $newPass = bin2hex(random_bytes(6)); // Secure 12-char random password
    $hash = password_hash($newPass, PASSWORD_BCRYPT);
    
    if ($admin) {
        $stmt = $db->prepare("UPDATE users SET password = ?, status = 'active', login_attempts = 0, locked_until = NULL WHERE id = ?");
        $stmt->execute([$hash, $admin['id']]);
        echo "✅ Admin password reset successfully.\n";
    } else {
        $stmt = $db->prepare("INSERT INTO users (username, password, full_name, role, status) VALUES ('admin', ?, 'Administrator', 'admin', 'active')");
        $stmt->execute([$hash]);
        echo "✅ Created new Administrator account.\n";
    }
    
    echo "Generated Temporary Password: " . htmlspecialchars($newPass) . "\n";
    echo "Please log in and change this password immediately.\n";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
