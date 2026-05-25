<?php
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'taascor_attendance');
define('DB_USER', 'root');
define('DB_PASS', '');

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $username  = 'coor1';
    $password  = password_hash('coor1', PASSWORD_BCRYPT);
    $full_name = 'markpasa';
    $role      = 'coordinator';
    $status    = 'active';

    $stmt = $pdo->prepare("INSERT INTO users (username, password, full_name, role, status)
        VALUES (?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE password=VALUES(password), full_name=VALUES(full_name), status=VALUES(status)");
    $stmt->execute([$username, $password, $full_name, $role, $status]);

    echo "Coordinator account created successfully!\n\n";

    // Show all users
    $rows = $pdo->query("SELECT id, username, full_name, role, status, created_at FROM users ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
    echo str_pad("ID",4) . str_pad("Username",12) . str_pad("Full Name",20) . str_pad("Role",14) . "Status\n";
    echo str_repeat("-", 65) . "\n";
    foreach ($rows as $r) {
        echo str_pad($r['id'],4) . str_pad($r['username'],12) . str_pad($r['full_name'],20) . str_pad($r['role'],14) . $r['status'] . "\n";
    }

} catch (PDOException $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
?>
