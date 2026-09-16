<?php
/**
 * Add Coordinator Setup Script — TAASCOR AttendX
 * Restricted to CLI or Active Administrator Session.
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

header('Content-Type: text/plain');

try {
    $db = getDB();
    $username  = 'coor1';
    $password  = password_hash('coor1', PASSWORD_BCRYPT);
    $full_name = 'Sample Coordinator';
    $role      = 'coordinator';
    $status    = 'active';

    $stmt = $db->prepare("INSERT INTO users (username, password, full_name, role, status)
        VALUES (?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE full_name=VALUES(full_name), status=VALUES(status)");
    $stmt->execute([$username, $password, $full_name, $role, $status]);

    echo "✅ Coordinator account created/updated successfully.\n";
} catch (PDOException $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
}
