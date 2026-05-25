<?php
/**
 * Cloud Database Setup — AttendX For TAASCOR
 * 
 * Run this ONCE after deploying to Render.com to initialize the database.
 * Access: /ATTENDANCE/cloud-setup.php?key=YOUR_SETUP_KEY
 * 
 * The SETUP_KEY env var protects this endpoint from unauthorized access.
 */

// Verify setup key
$setupKey = getenv('SETUP_KEY') ?: '';
$providedKey = $_GET['key'] ?? '';

if (empty($setupKey) || $providedKey !== $setupKey) {
    http_response_code(403);
    die('<div style="padding:40px;font-family:Inter,sans-serif;text-align:center;background:#0d0e12;color:#fff;min-height:100vh;display:flex;align-items:center;justify-content:center;">
        <div style="background:rgba(255,255,255,0.06);padding:40px;border-radius:16px;border:1px solid rgba(255,255,255,0.1);max-width:400px;">
            <h2 style="color:#ef4444;margin-bottom:16px;">🔒 Access Denied</h2>
            <p style="color:#9ca3af;font-size:14px;">Invalid or missing setup key.<br>Add <code>?key=YOUR_SETUP_KEY</code> to the URL.</p>
        </div>
    </div>');
}

// Database config from environment
$host = getenv('DB_HOST') ?: '127.0.0.1';
$dbname = getenv('DB_NAME') ?: 'taascor_attendance';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') ?: '';
$port = getenv('DB_PORT') ?: '3306';

$results = [];
$hasError = false;

try {
    // First try to create database (may fail on managed services like TiDB Cloud)
    $dsn = "mysql:host={$host};port={$port};charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ];
    if ($host !== 'localhost' && $host !== '127.0.0.1') {
        $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
        $options[PDO::MYSQL_ATTR_SSL_CA] = '';
    }
    $pdo = new PDO($dsn, $user, $pass, $options);
    $results[] = ['status' => 'success', 'message' => 'Connected to MySQL server'];

    // Try to create database (may fail on TiDB Cloud - that's OK)
    try {
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbname}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $results[] = ['status' => 'success', 'message' => "Database '{$dbname}' created/verified"];
    } catch (Exception $dbErr) {
        $results[] = ['status' => 'info', 'message' => "Skipped CREATE DATABASE: " . $dbErr->getMessage()];
    }

    // Connect directly to the target database
    $dsn2 = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";
    $pdo = new PDO($dsn2, $user, $pass, $options);
    $results[] = ['status' => 'success', 'message' => "Connected to database '{$dbname}'"];

    // Read and execute SQL schema
    $schemaFile = __DIR__ . '/init-database.sql';
    if (!file_exists($schemaFile)) {
        throw new Exception('init-database.sql not found');
    }

    $schema = file_get_contents($schemaFile);
    $statements = array_filter(
        array_map('trim', preg_split('/;(\s|$)/', $schema)),
        function($s) { return !empty($s) && strpos(trim($s), '--') !== 0; }
    );

    $tableCount = 0;
    foreach ($statements as $statement) {
        if (!empty(trim($statement))) {
            $pdo->exec($statement . ';');
            if (stripos($statement, 'CREATE TABLE') !== false) {
                $tableCount++;
            }
        }
    }
    $results[] = ['status' => 'success', 'message' => "Executed {$tableCount} CREATE TABLE statements"];

    // Create admin user with proper password hash
    $adminPassword = 'admin123';
    $hashedPassword = password_hash($adminPassword, PASSWORD_BCRYPT);
    
    // Check if admin already exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = 'admin'");
    $stmt->execute();
    
    if ($stmt->fetch()) {
        // Update existing admin password
        $update = $pdo->prepare("UPDATE users SET password = ? WHERE username = 'admin'");
        $update->execute([$hashedPassword]);
        $results[] = ['status' => 'success', 'message' => 'Admin password updated'];
    } else {
        // Insert admin user
        $insert = $pdo->prepare("INSERT INTO users (username, password, full_name, role, status) VALUES ('admin', ?, 'Administrator', 'admin', 'active')");
        $insert->execute([$hashedPassword]);
        $results[] = ['status' => 'success', 'message' => 'Admin user created'];
    }

    // Verify tables
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    $results[] = ['status' => 'info', 'message' => 'Tables: ' . implode(', ', $tables)];

} catch (Exception $e) {
    $hasError = true;
    $results[] = ['status' => 'error', 'message' => $e->getMessage()];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cloud Setup — AttendX For TAASCOR</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:'Inter',sans-serif; background:#0d0e12; color:#fff; min-height:100vh; display:flex; align-items:center; justify-content:center; padding:20px; }
        .card { background:rgba(255,255,255,0.06); border:1px solid rgba(255,255,255,0.1); border-radius:20px; padding:32px; max-width:520px; width:100%; }
        h2 { font-size:20px; margin-bottom:20px; text-align:center; }
        .result { padding:12px 16px; border-radius:10px; margin-bottom:8px; font-size:13px; border:1px solid; }
        .result.success { background:rgba(34,197,94,0.1); border-color:rgba(34,197,94,0.2); color:#4ade80; }
        .result.error { background:rgba(239,68,68,0.1); border-color:rgba(239,68,68,0.2); color:#f87171; }
        .result.info { background:rgba(59,130,246,0.1); border-color:rgba(59,130,246,0.2); color:#60a5fa; }
        .creds { background:rgba(32,201,151,0.1); border:1px solid rgba(32,201,151,0.2); border-radius:12px; padding:16px; margin-top:16px; }
        .creds strong { color:#20c997; }
        .creds code { background:rgba(255,255,255,0.1); padding:2px 8px; border-radius:4px; font-size:13px; }
        a.btn { display:inline-block; margin-top:16px; padding:12px 24px; background:linear-gradient(to right,#20c997,#12b886); color:#fff; text-decoration:none; border-radius:12px; font-weight:600; font-size:14px; text-align:center; width:100%; }
        a.btn:hover { opacity:0.9; }
    </style>
</head>
<body>
    <div class="card">
        <h2><?= $hasError ? '❌ Setup Error' : '✅ AttendX Setup Complete!' ?></h2>
        
        <?php foreach ($results as $r): ?>
        <div class="result <?= $r['status'] ?>">
            <?= $r['status'] === 'success' ? '✓' : ($r['status'] === 'error' ? '✗' : 'ℹ') ?>
            <?= htmlspecialchars($r['message']) ?>
        </div>
        <?php endforeach; ?>

        <?php if (!$hasError): ?>
        <div class="creds">
            <strong>Default Admin Credentials:</strong><br><br>
            Username: <code>admin</code><br>
            Password: <code>admin123</code><br><br>
            <span style="color:#9ca3af;font-size:12px;">⚠️ Change the password after first login!</span>
        </div>
        <a href="/ATTENDANCE/index.php" class="btn">🚀 Go to AttendX Login</a>
        <?php else: ?>
        <div style="margin-top:16px;color:#9ca3af;font-size:12px;text-align:center;">
            Check your MySQL environment variables in Render.com dashboard.
        </div>
        <?php endif; ?>
    </div>
</body>
</html>
