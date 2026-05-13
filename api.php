<?php
/**
 * TAASCOR — Unified API Handler
 * Routes: ?action=save_attendance|add_department|update_department|add_employee|update_employee|approve_edit_request|reject_edit_request
 */
require_once __DIR__ . '/includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'POST only']);
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$db = getDB();

switch ($action) {

// ============================================================
// SAVE ATTENDANCE
// ============================================================
case 'save_attendance':
    requireCoordinator();
    $saved = 0;
    $pending_approval = 0;
    $user = currentUser();
    $isAdmin = $user['role'] === 'admin';
    $today = date('Y-m-d');

    // JSON batch mode (AJAX)
    if (isset($_POST['attendance_json'])) {
        header('Content-Type: application/json');
        $attendance = json_decode($_POST['attendance_json'], true) ?: [];
        $dates = json_decode($_POST['dates'] ?? '[]', true) ?: [];
        if (empty($dates)) $dates = [$_POST['date'] ?? date('Y-m-d')];

        foreach ($dates as $date) {
            $isPastDate = strtotime($date) < strtotime($today);
            
            foreach ($attendance as $empId => $status) {
                if (!in_array($status, ['present','absent','no_work','leave','sent_home','rest_day'])) continue;
                
                // If coordinator tries to edit past attendance, create approval request
                if ($isPastDate && !$isAdmin) {
                    // Get current status for record
                    $current = $db->prepare("SELECT status FROM attendance WHERE employee_id=? AND date=?");
                    $current->execute([$empId, $date]);
                    $currentStatus = $current->fetchColumn() ?: null;
                    
                    // Create edit request
                    $reqStmt = $db->prepare("INSERT INTO attendance_edit_requests (employee_id, attendance_date, old_status, new_status, requested_by, reason) VALUES (?, ?, ?, ?, ?, ?)");
                    $reqStmt->execute([$empId, $date, $currentStatus, $status, $_SESSION['user_id'], $_POST['reason'] ?? '']);
                    $pending_approval++;
                } else {
                    // Admin or today's date: direct update
                    $stmt = $db->prepare("INSERT INTO attendance (employee_id,date,status,recorded_by) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE status=VALUES(status),recorded_by=VALUES(recorded_by)");
                    $stmt->execute([$empId, $date, $status, $_SESSION['user_id']]);
                    $saved++;
                }
            }
        }
        
        $message = '';
        if ($saved > 0) $message .= "Saved $saved record(s). ";
        if ($pending_approval > 0) $message .= "Submitted $pending_approval edit request(s) pending HR approval. ";
        
        logActivity('Record Attendance', trim($message));
        echo json_encode(['success' => true, 'saved' => $saved, 'pending' => $pending_approval, 'message' => trim($message)]);
        exit;
    }

    // Form POST fallback
    $date = $_POST['date'] ?? date('Y-m-d');
    $attendance = $_POST['attendance'] ?? [];
    $isPastDate = strtotime($date) < strtotime($today);
    
    foreach ($attendance as $empId => $data) {
        $status = $data['status'] ?? '';
        if (!in_array($status, ['present','absent','no_work','leave','sent_home','rest_day'])) continue;
        
        // If coordinator tries to edit past attendance, create approval request
        if ($isPastDate && !$isAdmin) {
            // Get current status for record
            $current = $db->prepare("SELECT status FROM attendance WHERE employee_id=? AND date=?");
            $current->execute([$empId, $date]);
            $currentStatus = $current->fetchColumn() ?: null;
            
            // Create edit request
            $reqStmt = $db->prepare("INSERT INTO attendance_edit_requests (employee_id, attendance_date, old_status, new_status, requested_by, reason) VALUES (?, ?, ?, ?, ?, ?)");
            $reqStmt->execute([$empId, $date, $currentStatus, $status, $_SESSION['user_id'], $_POST['reason'] ?? '']);
            $pending_approval++;
        } else {
            // Admin or today's date: direct update
            $stmt = $db->prepare("INSERT INTO attendance (employee_id,date,status,recorded_by) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE status=VALUES(status),recorded_by=VALUES(recorded_by)");
            $stmt->execute([$empId, $date, $status, $_SESSION['user_id']]);
            $saved++;
        }
    }
    
    $message = '';
    if ($saved > 0) $message .= "Saved $saved employee(s). ";
    if ($pending_approval > 0) $message .= "Submitted $pending_approval edit request(s) pending HR approval. ";
    
    logActivity('Record Attendance', trim($message) . "Date: $date");
    
    if (!empty($message)) {
        setFlash('success', trim($message));
    } elseif ($saved == 0 && $pending_approval == 0) {
        setFlash('info', 'No changes made.');
    }
    
    header('Location: /ATTENDANCE/coordinator/attendance.php?date=' . $date);
    exit;



// ============================================================
// ADD DEPARTMENT
// ============================================================
case 'add_department':
    requireLogin();
    header('Content-Type: application/json');
    $name = trim($_POST['name'] ?? '');
    if (empty($name)) { echo json_encode(['error' => 'Department name is required']); exit; }

    $check = $db->prepare("SELECT id FROM departments WHERE name = ?");
    $check->execute([$name]);
    if ($check->fetch()) { echo json_encode(['error' => 'Department already exists']); exit; }

    $stmt = $db->prepare("INSERT INTO departments (name) VALUES (?)");
    $stmt->execute([$name]);
    $newId = $db->lastInsertId();
    logActivity('Add Department', "Created department: $name (ID: $newId)");
    echo json_encode(['success' => true, 'id' => $newId, 'name' => $name]);
    exit;

// ============================================================
// UPDATE DEPARTMENT
// ============================================================
case 'update_department':
    requireLogin();
    header('Content-Type: application/json');
    $id = (int)($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    if (!$id || !$name) { echo json_encode(['error' => 'Missing id or name']); exit; }

    $stmt = $db->prepare("UPDATE departments SET name = ? WHERE id = ?");
    $stmt->execute([$name, $id]);
    logActivity('Edit Department', "Renamed department #$id to \"$name\"");
    echo json_encode(['success' => true, 'name' => $name]);
    exit;

// ============================================================
// ADD EMPLOYEE
// ============================================================
case 'add_employee':
    requireAdmin();
    $firstName    = trim($_POST['first_name'] ?? '');
    $lastName     = trim($_POST['last_name'] ?? '');
    $departmentId = $_POST['department_id'] ?? '';
    $position     = trim($_POST['position'] ?? '');
    $dateHired    = $_POST['date_hired'] ?? null;

    if (!$firstName || !$lastName || !$departmentId) {
        setFlash('error', 'First name, last name, and department are required.');
        header('Location: /ATTENDANCE/admin/employees.php');
        exit;
    }

    $stmt = $db->prepare("INSERT INTO employees (first_name, last_name, department_id, position, date_hired) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$firstName, $lastName, $departmentId, $position, $dateHired ?: null]);
    logActivity('Add Employee', "Added employee: $firstName $lastName");
    setFlash('success', "Employee $firstName $lastName added successfully!");
    header('Location: /ATTENDANCE/admin/employees.php');
    exit;

// ============================================================
// UPDATE EMPLOYEE
// ============================================================
case 'update_employee':
    requireLogin();
    header('Content-Type: application/json');
    $id = (int)($_POST['id'] ?? 0);
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $position = trim($_POST['position'] ?? '');
    if (!$id || !$firstName || !$lastName) { echo json_encode(['error' => 'Missing required fields']); exit; }

    $stmt = $db->prepare("UPDATE employees SET first_name = ?, last_name = ?, position = ? WHERE id = ?");
    $stmt->execute([$firstName, $lastName, $position, $id]);
    logActivity('Edit Employee', "Updated employee #$id: $lastName, $firstName");
    echo json_encode(['success' => true, 'first_name' => $firstName, 'last_name' => $lastName, 'position' => $position]);
    exit;

// ============================================================
// DELETE EMPLOYEE
// ============================================================
case 'delete_employee':
    requireLogin();
    header('Content-Type: application/json');
    $id = (int)($_POST['employee_id'] ?? 0);
    if (!$id) { 
        echo json_encode(['success' => false, 'message' => 'Invalid employee ID']); 
        exit; 
    }

    // Get employee details before deleting
    $stmt = $db->prepare("SELECT first_name, last_name FROM employees WHERE id = ?");
    $stmt->execute([$id]);
    $emp = $stmt->fetch();
    
    if (!$emp) {
        echo json_encode(['success' => false, 'message' => 'Employee not found']); 
        exit;
    }

    // Delete employee (cascades to attendance records)
    $stmt = $db->prepare("DELETE FROM employees WHERE id = ?");
    $stmt->execute([$id]);
    
    logActivity('Delete Employee', "Deleted employee #$id: {$emp['last_name']}, {$emp['first_name']}");
    echo json_encode(['success' => true, 'message' => 'Employee deleted successfully']);
    exit;

// ============================================================
// APPROVE ATTENDANCE EDIT REQUEST
// ============================================================
case 'approve_edit_request':
    requireAdmin();
    header('Content-Type: application/json');
    
    $id = (int)($_POST['id'] ?? 0);
    if (!$id) { echo json_encode(['error' => 'Missing request ID']); exit; }
    
    // Get the request
    $req = $db->prepare("SELECT * FROM attendance_edit_requests WHERE id = ?");
    $req->execute([$id]);
    $request = $req->fetch();
    
    if (!$request) { echo json_encode(['error' => 'Request not found']); exit; }
    if ($request['status'] !== 'pending') { echo json_encode(['error' => 'Request is not pending']); exit; }
    
    // Update attendance directly
    $stmt = $db->prepare("INSERT INTO attendance (employee_id,date,status,recorded_by) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE status=VALUES(status),recorded_by=VALUES(recorded_by)");
    $stmt->execute([$request['employee_id'], $request['attendance_date'], $request['new_status'], $_SESSION['user_id']]);
    
    // Update request status
    $updateReq = $db->prepare("UPDATE attendance_edit_requests SET status='approved', approved_by=?, approved_at=NOW() WHERE id=?");
    $updateReq->execute([$_SESSION['user_id'], $id]);
    
    $notes = $_POST['notes'] ?? '';
    logActivity('Approve Attendance Edit', "Approved edit request #$id for employee #{$request['employee_id']} on {$request['attendance_date']}. Notes: $notes");
    
    echo json_encode(['success' => true, 'message' => 'Edit request approved']);
    exit;

// ============================================================
// REJECT ATTENDANCE EDIT REQUEST
// ============================================================
case 'reject_edit_request':
    requireAdmin();
    header('Content-Type: application/json');
    
    $id = (int)($_POST['id'] ?? 0);
    $reason = trim($_POST['reason'] ?? '');
    
    if (!$id) { echo json_encode(['error' => 'Missing request ID']); exit; }
    if (empty($reason)) { echo json_encode(['error' => 'Rejection reason is required']); exit; }
    
    // Get the request
    $req = $db->prepare("SELECT * FROM attendance_edit_requests WHERE id = ?");
    $req->execute([$id]);
    $request = $req->fetch();
    
    if (!$request) { echo json_encode(['error' => 'Request not found']); exit; }
    if ($request['status'] !== 'pending') { echo json_encode(['error' => 'Request is not pending']); exit; }
    
    // Update request status
    $updateReq = $db->prepare("UPDATE attendance_edit_requests SET status='rejected', approved_by=?, approved_at=NOW(), reason=? WHERE id=?");
    $updateReq->execute([$_SESSION['user_id'], $reason, $id]);
    
    logActivity('Reject Attendance Edit', "Rejected edit request #$id for employee #{$request['employee_id']} on {$request['attendance_date']}. Reason: $reason");
    
    echo json_encode(['success' => true, 'message' => 'Edit request rejected']);
    exit;

// ============================================================
// UNKNOWN ACTION
// ============================================================
default:
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unknown action: ' . $action]);
    exit;
}
