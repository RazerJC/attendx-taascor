<?php
/**
 * Simple Reset: Delete admin and recreate with correct password
 */

define('DB_HOST', 'localhost');
define('DB_NAME', 'taascor_attendance');
define('DB_USER', 'root');
define('DB_PASS', '');

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Delete admin if exists
    $pdo->exec("DELETE FROM users WHERE username='admin'");
    
    // Create new admin with fresh password
    $password = 'admin123';
    $hash = password_hash($password, PASSWORD_BCRYPT);
    
    $stmt = $pdo->prepare("INSERT INTO users (username, password, full_name, role, status) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute(['admin', $hash, 'Administrator', 'admin', 'active']);
    
    // Verify it worked
    $user = $pdo->query("SELECT id, username FROM users WHERE username='admin'")->fetch();
    
    if ($user) {
        echo "✅ SUCCESS! Admin account reset.\n\n";
        echo "LOGIN CREDENTIALS:\n";
        echo "Username: admin\n";
        echo "Password: admin123\n\n";
        echo "Go to: http://localhost/ATTENDANCE/\n";
    } else {
        echo "❌ Failed to create admin user\n";
    }
    
} catch (PDOException $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
}
?>
