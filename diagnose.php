<?php
/**
 * Diagnostic: Check database and admin user — TAASCOR AttendX
 * Restricted to Administrator sessions or CLI.
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
    $pdo = getDB();
    echo "=== TAASCOR AttendX Diagnostic ===\n\n";
    
    // Check users table
    $tables = $pdo->query("SHOW TABLES LIKE 'users'")->fetchAll();
    echo "Users table exists: " . (count($tables) > 0 ? "YES" : "NO") . "\n\n";
    
    // Check user count by role
    $roles = $pdo->query("SELECT role, COUNT(*) as cnt FROM users GROUP BY role")->fetchAll();
    echo "Users Summary:\n";
    foreach ($roles as $r) {
        echo "  - Role '{$r['role']}': {$r['cnt']} accounts\n";
    }
    
    // Check departments
    $deptCount = $pdo->query("SELECT COUNT(*) FROM departments")->fetchColumn();
    echo "\nDepartments Count: $deptCount\n";
    
    // Check employees
    $empCount = $pdo->query("SELECT COUNT(*) FROM employees")->fetchColumn();
    echo "Employees Count: $empCount\n";
    
    // Check attendance records
    $attCount = $pdo->query("SELECT COUNT(*) FROM attendance")->fetchColumn();
    echo "Attendance Records: $attCount\n";
    
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
}
