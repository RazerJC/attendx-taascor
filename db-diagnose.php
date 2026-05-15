<?php
// Diagnostic endpoint - outputs plain text for debugging
header('Content-Type: text/plain');

$host = getenv('DB_HOST') ?: 'localhost';
$port = getenv('DB_PORT') ?: '3306';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') ?: '';
$dbname = getenv('DB_NAME') ?: 'taascor_attendance';
$key = getenv('SETUP_KEY') ?: '';

if (empty($key) || ($_GET['key'] ?? '') !== $key) { die('ACCESS DENIED'); }

echo "=== AttendX DB Diagnostic ===\n";
echo "Host: $host\n";
echo "Port: $port\n";
echo "User: $user\n";
echo "DB: $dbname\n\n";

$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false,
    PDO::MYSQL_ATTR_SSL_CA => '',
];

// Step 1: Connect without database
echo "--- Step 1: Connect to server ---\n";
try {
    $pdo = new PDO("mysql:host=$host;port=$port;charset=utf8mb4", $user, $pass, $options);
    echo "OK: Connected to server\n";
} catch (Exception $e) {
    die("FAIL: " . $e->getMessage() . "\n");
}

// Step 2: List databases
echo "\n--- Step 2: List databases ---\n";
try {
    $dbs = $pdo->query("SHOW DATABASES")->fetchAll(PDO::FETCH_COLUMN);
    echo "Databases: " . implode(', ', $dbs) . "\n";
} catch (Exception $e) {
    echo "FAIL: " . $e->getMessage() . "\n";
}

// Step 3: Try CREATE DATABASE
echo "\n--- Step 3: Create database '$dbname' ---\n";
try {
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname`");
    echo "OK: Database created/exists\n";
} catch (Exception $e) {
    echo "FAIL: " . $e->getMessage() . "\n";
    echo "Trying USE test instead...\n";
}

// Step 4: Connect to target database
echo "\n--- Step 4: Connect to '$dbname' ---\n";
try {
    $pdo2 = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4", $user, $pass, $options);
    echo "OK: Connected to $dbname\n";
} catch (Exception $e) {
    echo "FAIL: " . $e->getMessage() . "\n";
    // Try 'test' database as fallback
    echo "Trying 'test' database...\n";
    try {
        $pdo2 = new PDO("mysql:host=$host;port=$port;dbname=test;charset=utf8mb4", $user, $pass, $options);
        echo "OK: Connected to 'test' database\n";
    } catch (Exception $e2) {
        die("FAIL: " . $e2->getMessage() . "\n");
    }
}

// Step 5: Try creating a table
echo "\n--- Step 5: Create test table ---\n";
try {
    $pdo2->exec("CREATE TABLE IF NOT EXISTS _diag_test (id INT PRIMARY KEY)");
    echo "OK: Table created\n";
    $pdo2->exec("DROP TABLE IF EXISTS _diag_test");
    echo "OK: Table dropped\n";
} catch (Exception $e) {
    echo "FAIL: " . $e->getMessage() . "\n";
}

echo "\n=== DONE ===\n";
