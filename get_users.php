<?php
/**
 * Users Listing Endpoint — TAASCOR AttendX
 * Restricted to active Administrator sessions only.
 */
require_once __DIR__ . '/includes/auth.php';

requireAdmin();

header('Content-Type: application/json');

try {
    $db = getDB();
    $stmt = $db->query("
        SELECT u.id, u.username, u.email, u.email_verified, u.full_name, u.role, u.department_id, d.name as department_name, u.status, u.created_at 
        FROM users u 
        LEFT JOIN departments d ON u.department_id = d.id 
        ORDER BY u.id
    ");
    $users = $stmt->fetchAll();
    echo json_encode(['success' => true, 'data' => $users], JSON_PRETTY_PRINT);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database query failed'], JSON_PRETTY_PRINT);
}
