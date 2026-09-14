<?php
/**
 * Database Check Endpoint — TAASCOR AttendX
 * Restricted to Administrator sessions or CLI. Credentials masked.
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

echo "=== AttendX DB Debug ===\n";
echo "DB_HOST: " . DB_HOST . "\n";
echo "DB_NAME: " . DB_NAME . "\n";
echo "DB_PORT: " . DB_PORT . "\n";
echo "DB_USER: " . substr(DB_USER, 0, 2) . "***\n";

try {
    $db = getDB();
    echo "Connection: SUCCESS\n";
    $tables = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    echo "Tables found (" . count($tables) . "):\n";
    foreach ($tables as $t) {
        echo "  - $t\n";
    }
} catch (Exception $e) {
    echo "Connection: FAILED\n";
    echo "Error: " . $e->getMessage() . "\n";
}
