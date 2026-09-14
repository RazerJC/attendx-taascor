<?php
require_once __DIR__ . '/includes/auth.php';
try {
    $db = getDB();
    $p = password_hash('jc123', PASSWORD_BCRYPT);
    $stmt = $db->prepare("UPDATE users SET password = ? WHERE username = ?");
    $stmt->execute([$p, 'jc']);
    echo "SUCCESS_UPDATE_JC";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage();
}
?>
