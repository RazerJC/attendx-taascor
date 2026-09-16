<?php
/**
 * Database Setup Script — TAASCOR Attendance Monitoring System
 * Creates all tables and inserts initial data
 */

define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'taascor_attendance');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

try {
    // Connect to MySQL server (without specific database)
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";charset=" . DB_CHARSET,
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );

    // Create database if it doesn't exist
    $pdo->exec("CREATE DATABASE IF NOT EXISTS " . DB_NAME . " CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE " . DB_NAME);

    // Read and execute SQL schema
    $schema = file_get_contents(__DIR__ . '/init-database.sql');
    $statements = array_filter(
        array_map('trim', preg_split('/;(\s|$)/', $schema)),
        function($s) { return !empty($s) && strpos(trim($s), '--') !== 0; }
    );

    foreach ($statements as $statement) {
        if (!empty(trim($statement))) {
            $pdo->exec($statement . ';');
        }
    }

    // Update admin password with proper hash
    $adminPassword = 'admin123';
    $hashedPassword = password_hash($adminPassword, PASSWORD_BCRYPT);
    
    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE username = 'admin'");
    $stmt->execute([$hashedPassword]);

    echo '<div style="padding: 40px; font-family: sans-serif; text-align: center; background: #f0f0f0; min-height: 100vh; display: flex; align-items: center; justify-content: center;">';
    echo '<div style="background: white; padding: 40px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); max-width: 500px;">';
    echo '<h2 style="color: #12b886; margin-bottom: 20px;">✅ Database Setup Complete!</h2>';
    echo '<p style="color: #333; margin: 10px 0;">All tables have been created successfully.</p>';
    echo '<div style="background: #f9f9f9; padding: 15px; border-radius: 5px; margin: 20px 0; text-align: left; font-family: monospace; font-size: 14px;">';
    echo '<strong>Default Admin Credentials:</strong><br>';
    echo 'Username: <strong>admin</strong><br>';
    echo 'Password: <strong>admin123</strong><br>';
    echo '</div>';
    echo '<p style="color: #666; font-size: 14px; margin: 15px 0;">Please change the admin password after first login.</p>';
    echo '<a href="/ATTENDANCE/" style="display: inline-block; margin-top: 20px; padding: 10px 30px; background: #12b886; color: white; text-decoration: none; border-radius: 5px; font-weight: bold;">Go to Login</a>';
    echo '</div>';
    echo '</div>';

} catch (PDOException $e) {
    echo '<div style="padding: 40px; font-family: sans-serif; text-align: center; background: #f8d7da; min-height: 100vh; display: flex; align-items: center; justify-content: center;">';
    echo '<div style="background: white; padding: 40px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); max-width: 500px;">';
    echo '<h2 style="color: #d32f2f; margin-bottom: 20px;">❌ Database Setup Error</h2>';
    echo '<p style="color: #666; margin: 10px 0;"><strong>Error:</strong></p>';
    echo '<pre style="background: #f9f9f9; padding: 15px; border-radius: 5px; text-align: left; overflow: auto;">' . htmlspecialchars($e->getMessage()) . '</pre>';
    echo '<p style="color: #666; font-size: 14px; margin-top: 20px;">Make sure:</p>';
    echo '<ul style="text-align: left; color: #666; display: inline-block;">';
    echo '<li>MySQL/XAMPP is running</li>';
    echo '<li>Database user (root) has no password or correct password</li>';
    echo '<li>You have proper database permissions</li>';
    echo '</ul>';
    echo '</div>';
    echo '</div>';
}
