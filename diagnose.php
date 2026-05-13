<?php
/**
 * Diagnostic: Check database and admin user
 */

define('DB_HOST', 'localhost');
define('DB_NAME', 'taascor_attendance');
define('DB_USER', 'root');
define('DB_PASS', '');

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "=== DATABASE DIAGNOSTIC ===\n\n";
    
    // Check if users table exists
    $tables = $pdo->query("SHOW TABLES LIKE 'users'")->fetchAll();
    echo "Users table exists: " . (count($tables) > 0 ? "YES" : "NO") . "\n\n";
    
    // Check all users in database
    $users = $pdo->query("SELECT id, username, full_name, role, status FROM users")->fetchAll();
    echo "Users in database:\n";
    if (count($users) > 0) {
        foreach ($users as $user) {
            echo "- ID: {$user['id']}, Username: {$user['username']}, Name: {$user['full_name']}, Role: {$user['role']}, Status: {$user['status']}\n";
        }
    } else {
        echo "NO USERS FOUND!\n";
    }
    
    echo "\n";
    
    // Try to create admin if doesn't exist
    $adminExists = $pdo->query("SELECT id FROM users WHERE username='admin'")->fetch();
    
    if (!$adminExists) {
        echo "Admin user not found. Creating now...\n";
        
        $password = 'admin123';
        $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);
        
        echo "Password: $password\n";
        echo "Hash: $hash\n\n";
        
        $stmt = $pdo->prepare("INSERT INTO users (username, password, full_name, role, status) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute(['admin', $hash, 'Administrator', 'admin', 'active']);
        
        echo "✅ Admin user created successfully!\n\n";
    } else {
        echo "✅ Admin user exists.\n\n";
    }
    
    // Verify the admin user now exists
    $admin = $pdo->query("SELECT id, username, password FROM users WHERE username='admin'")->fetch();
    if ($admin) {
        echo "Admin verification:\n";
        echo "- Username: {$admin['username']}\n";
        echo "- Password hash: {$admin['password']}\n";
        
        // Test password verification
        $testPassword = 'admin123';
        $isValid = password_verify($testPassword, $admin['password']);
        echo "- Password 'admin123' matches: " . ($isValid ? "YES ✅" : "NO ❌") . "\n";
    }
    
    echo "\n✅ SETUP COMPLETE\n";
    echo "Login with:\n";
    echo "- Username: admin\n";
    echo "- Password: admin123\n";
    
} catch (PDOException $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
}
?>
