<?php
require_once __DIR__ . '/includes/auth.php';

header('Content-Type: application/json');

try {
    $db = getDB();
    $stmt = $db->query("SELECT id, username, full_name, role, department_id, status, created_at FROM users ORDER BY id");
    $users = $stmt->fetchAll();
    echo json_encode(['success' => true, 'data' => $users], JSON_PRETTY_PRINT);
} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()], JSON_PRETTY_PRINT);
}
?>
