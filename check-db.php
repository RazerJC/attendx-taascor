<?php
// check-db.php - Database Diagnosis
require_once __DIR__ . '/includes/auth.php';
header('Content-Type: text/plain');

echo "=== AttendX DB Debug ===\n";
echo "DB_HOST: " . DB_HOST . "\n";
echo "DB_NAME: " . DB_NAME . "\n";
echo "DB_PORT: " . DB_PORT . "\n";
echo "DB_USER: " . DB_USER . "\n";

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
