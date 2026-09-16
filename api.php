<?php
/**
 * TAASCOR — Unified API Handler
 * Routes: ?action=save_attendance|add_department|update_department|add_employee|update_employee|approve_edit_request|reject_edit_request
 */
require_once __DIR__ . '/includes/auth.php';

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$readOnlyActions = ['get_notifications', 'get_chat_history', 'get_chat_contacts'];

// Enforce POST for all state-changing endpoints
if ($_SERVER['REQUEST_METHOD'] !== 'POST' && !in_array($action, $readOnlyActions, true)) {
    header('Content-Type: application/json');
    http_response_code(405);
    echo json_encode(['error' => 'POST method required']);
    exit;
}

// Enforce CSRF token validation on all POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!validateCsrfToken($csrfToken)) {
        header('Content-Type: application/json');
        http_response_code(403);
        echo json_encode(['error' => 'Invalid or missing CSRF token. Please reload the page.']);
        exit;
    }
}

$db = getDB();

// Helper to handle Back to Work (BTW) auto-creation/deletion based on attendance status
function handleBtwOnAttendanceChange($db, $employeeId, $date, $status) {
    if ($status === 'absent') {
        $stmt = $db->prepare("SELECT id FROM back_to_work WHERE employee_id = ? AND absence_date = ?");
        $stmt->execute([$employeeId, $date]);
        if (!$stmt->fetch()) {
            $ins = $db->prepare("INSERT INTO back_to_work (employee_id, absence_date, status) VALUES (?, ?, 'pending')");
            $ins->execute([$employeeId, $date]);
        }
    } else {
        // If status changes from absent to something else, remove pending BTW for that date
        $del = $db->prepare("DELETE FROM back_to_work WHERE employee_id = ? AND absence_date = ? AND status = 'pending'");
        $del->execute([$employeeId, $date]);
    }
}

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
    $coordDept = $_SESSION['department_id'] ?? null;

    // Cache allowed employee IDs for department scoping if coordinator
    $allowedEmpIds = null;
    if (!$isAdmin) {
        if ($coordDept !== null) {
            $allowedStmt = $db->prepare("SELECT id FROM employees WHERE department_id = ?");
            $allowedStmt->execute([$coordDept]);
            $allowedEmpIds = array_map('intval', $allowedStmt->fetchAll(PDO::FETCH_COLUMN));
        } else {
            $allowedEmpIds = [];
        }
    }

    // JSON batch mode (AJAX)
    if (isset($_POST['attendance_json'])) {
        header('Content-Type: application/json');
        $attendance = json_decode($_POST['attendance_json'], true) ?: [];
        $dates = json_decode($_POST['dates'] ?? '[]', true) ?: [];
        if (empty($dates)) $dates = [$_POST['date'] ?? date('Y-m-d')];

        $db->beginTransaction();
        try {
            foreach ($dates as $date) {
                if (!isValidDate($date)) continue;
                $isPastDate = strtotime($date) < strtotime($today);
                
                foreach ($attendance as $empId => $status) {
                    $empId = (int)$empId;
                    if ($allowedEmpIds !== null && !in_array($empId, $allowedEmpIds, true)) {
                        continue; // Skip employee outside coordinator's department
                    }
                    if (!in_array($status, ['present','absent','no_work','leave','sent_home','rest_day'])) continue;
                    
                    // If coordinator tries to edit past attendance, create approval request
                    if ($isPastDate && !$isAdmin) {
                        $current = $db->prepare("SELECT status FROM attendance WHERE employee_id=? AND date=? FOR UPDATE");
                        $current->execute([$empId, $date]);
                        $currentStatus = $current->fetchColumn() ?: null;
                        
                        $reqStmt = $db->prepare("INSERT INTO attendance_edit_requests (employee_id, attendance_date, old_status, new_status, requested_by, reason) VALUES (?, ?, ?, ?, ?, ?)");
                        $reqStmt->execute([$empId, $date, $currentStatus, $status, $_SESSION['user_id'], htmlspecialchars($_POST['reason'] ?? '')]);
                        $pending_approval++;
                    } else {
                        $stmt = $db->prepare("INSERT INTO attendance (employee_id,date,status,recorded_by) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE status=VALUES(status),recorded_by=VALUES(recorded_by)");
                        $stmt->execute([$empId, $date, $status, $_SESSION['user_id']]);
                        handleBtwOnAttendanceChange($db, $empId, $date, $status);
                        $saved++;
                    }
                }
            }
            $db->commit();
        } catch (Exception $e) {
            $db->rollBack();
            error_log("save_attendance error: " . $e->getMessage());
            echo json_encode(['success' => false, 'error' => 'Database error during attendance save: ' . $e->getMessage()]);
            exit;
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
    if (!isValidDate($date)) {
        setFlash('error', 'Invalid date format.');
        header('Location: /ATTENDANCE/coordinator/attendance.php');
        exit;
    }
    $attendance = $_POST['attendance'] ?? [];
    $isPastDate = strtotime($date) < strtotime($today);
    
    $db->beginTransaction();
    try {
        foreach ($attendance as $empId => $data) {
            $empId = (int)$empId;
            if ($allowedEmpIds !== null && !in_array($empId, $allowedEmpIds, true)) {
                continue;
            }
            $status = $data['status'] ?? '';
            if (!in_array($status, ['present','absent','no_work','leave','sent_home','rest_day'])) continue;
            
            if ($isPastDate && !$isAdmin) {
                $current = $db->prepare("SELECT status FROM attendance WHERE employee_id=? AND date=? FOR UPDATE");
                $current->execute([$empId, $date]);
                $currentStatus = $current->fetchColumn() ?: null;
                
                $reqStmt = $db->prepare("INSERT INTO attendance_edit_requests (employee_id, attendance_date, old_status, new_status, requested_by, reason) VALUES (?, ?, ?, ?, ?, ?)");
                $reqStmt->execute([$empId, $date, $currentStatus, $status, $_SESSION['user_id'], htmlspecialchars($_POST['reason'] ?? '')]);
                $pending_approval++;
            } else {
                $stmt = $db->prepare("INSERT INTO attendance (employee_id,date,status,recorded_by) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE status=VALUES(status),recorded_by=VALUES(recorded_by)");
                $stmt->execute([$empId, $date, $status, $_SESSION['user_id']]);
                handleBtwOnAttendanceChange($db, $empId, $date, $status);
                $saved++;
            }
        }
        $db->commit();
    } catch (Exception $e) {
        $db->rollBack();
        error_log("save_attendance fallback error: " . $e->getMessage());
        setFlash('error', 'Failed to save attendance due to database conflict.');
        header('Location: /ATTENDANCE/coordinator/attendance.php?date=' . $date);
        exit;
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
    requireAdmin();
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
    requireAdmin();
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
// DELETE DEPARTMENT
// ============================================================
case 'delete_department':
    requireAdmin();
    header('Content-Type: application/json');
    $id = (int)($_POST['id'] ?? 0);
    if (!$id) { echo json_encode(['error' => 'Missing department ID']); exit; }

    // Check if department has employees
    $check = $db->prepare("SELECT COUNT(*) FROM employees WHERE department_id = ?");
    $check->execute([$id]);
    $empCount = $check->fetchColumn();

    if ($empCount > 0) {
        echo json_encode(['error' => "Cannot delete department. It has $empCount employee(s) assigned to it."]);
        exit;
    }

    $stmt = $db->prepare("DELETE FROM departments WHERE id = ?");
    $stmt->execute([$id]);
    logActivity('Delete Department', "Deleted department ID: $id");
    echo json_encode(['success' => true]);
    exit;

// ============================================================
// ASSIGN COORDINATOR TO DEPARTMENT
// ============================================================
case 'assign_coordinator':
    requireAdmin();
    header('Content-Type: application/json');
    $deptId = (int)($_POST['department_id'] ?? 0);
    $coordId = (int)($_POST['coordinator_id'] ?? 0); // 0 means unassign

    if (!$deptId) { echo json_encode(['error' => 'Missing department ID']); exit; }

    // Unassign any coordinator currently assigned to this department
    $stmt = $db->prepare("UPDATE users SET department_id = NULL WHERE department_id = ? AND role = 'coordinator'");
    $stmt->execute([$deptId]);

    // If a coordinator was selected, assign them
    if ($coordId > 0) {
        $stmt = $db->prepare("UPDATE users SET department_id = ? WHERE id = ? AND role = 'coordinator'");
        $stmt->execute([$deptId, $coordId]);
        
        // Get coordinator name for activity log
        $cName = $db->prepare("SELECT full_name FROM users WHERE id = ?");
        $cName->execute([$coordId]);
        $coordinatorName = $cName->fetchColumn() ?: "ID $coordId";
        logActivity('Assign Coordinator', "Assigned coordinator $coordinatorName to department ID: $deptId");
    } else {
        logActivity('Unassign Coordinator', "Cleared coordinator for department ID: $deptId");
    }

    echo json_encode(['success' => true]);
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
    requireAdmin();
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
// UPDATE EMPLOYEE DEPARTMENT
// ============================================================
case 'update_employee_department':
    requireAdmin();
    header('Content-Type: application/json');
    $employeeId = (int)($_POST['employee_id'] ?? 0);
    $departmentId = (int)($_POST['department_id'] ?? 0);

    if (!$employeeId || !$departmentId) {
        echo json_encode(['success' => false, 'message' => 'Missing employee ID or department ID']);
        exit;
    }

    $stmt = $db->prepare("UPDATE employees SET department_id = ? WHERE id = ?");
    $stmt->execute([$departmentId, $employeeId]);
    logActivity('Edit Employee Department', "Updated employee #$employeeId department to ID: $departmentId");
    echo json_encode(['success' => true, 'message' => 'Department updated successfully']);
    exit;

// ============================================================
// DELETE EMPLOYEE
// ============================================================
case 'delete_employee':
    requireAdmin();
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
    handleBtwOnAttendanceChange($db, $request['employee_id'], $request['attendance_date'], $request['new_status']);
    
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
// CREATE ABSENCE WARNING
// ============================================================
case 'create_warning':
    requireAdmin();
    header('Content-Type: application/json');
    $employeeId = (int)($_POST['employee_id'] ?? 0);
    $warningLevel = $_POST['warning_level'] ?? '';
    $absenceCount = (int)($_POST['absence_count'] ?? 0);
    $remarks = trim($_POST['remarks'] ?? '');

    if (!$employeeId || !$warningLevel) {
        echo json_encode(['success' => false, 'message' => 'Employee and warning level are required']);
        exit;
    }
    if (!in_array($warningLevel, ['1st_warning','2nd_warning','3rd_warning','final_warning'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid warning level']);
        exit;
    }

    $stmt = $db->prepare("INSERT INTO absence_warnings (employee_id, warning_level, absence_count, issued_by, remarks) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$employeeId, $warningLevel, $absenceCount, $_SESSION['user_id'], $remarks]);

    // Get employee name for log
    $emp = $db->prepare("SELECT CONCAT(last_name, ', ', first_name) as name FROM employees WHERE id = ?");
    $emp->execute([$employeeId]);
    $empName = $emp->fetchColumn() ?: "ID $employeeId";
    logActivity('Issue Warning', "Issued $warningLevel to $empName. Absences: $absenceCount");
    echo json_encode(['success' => true, 'message' => 'Warning issued successfully']);
    exit;

// ============================================================
// DELETE ABSENCE WARNING
// ============================================================
case 'delete_warning':
    requireAdmin();
    header('Content-Type: application/json');
    $id = (int)($_POST['id'] ?? 0);
    if (!$id) { echo json_encode(['success' => false, 'message' => 'Missing warning ID']); exit; }

    $db->prepare("DELETE FROM absence_warnings WHERE id = ?")->execute([$id]);
    logActivity('Delete Warning', "Deleted absence warning #$id");
    echo json_encode(['success' => true, 'message' => 'Warning deleted']);
    exit;

// ============================================================
// CREATE REPORT-TO-OFFICE NOTICE
// ============================================================
case 'create_report_to_office':
    requireAdmin();
    header('Content-Type: application/json');
    $employeeId = (int)($_POST['employee_id'] ?? 0);
    $reason = trim($_POST['reason'] ?? '');
    $reportDate = $_POST['report_date'] ?? '';
    $remarks = trim($_POST['remarks'] ?? '');

    if (!$employeeId || !$reason || !$reportDate) {
        echo json_encode(['success' => false, 'message' => 'Employee, reason, and report date are required']);
        exit;
    }

    $stmt = $db->prepare("INSERT INTO report_to_office (employee_id, reason, report_date, issued_by, remarks) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$employeeId, $reason, $reportDate, $_SESSION['user_id'], $remarks]);

    $emp = $db->prepare("SELECT CONCAT(last_name, ', ', first_name) as name FROM employees WHERE id = ?");
    $emp->execute([$employeeId]);
    $empName = $emp->fetchColumn() ?: "ID $employeeId";
    logActivity('Report-to-Office', "Created RTO notice for $empName on $reportDate");
    echo json_encode(['success' => true, 'message' => 'Report-to-Office notice created']);
    exit;

// ============================================================
// UPDATE REPORT-TO-OFFICE STATUS
// ============================================================
case 'update_report_to_office':
    requireAdmin();
    header('Content-Type: application/json');
    $id = (int)($_POST['id'] ?? 0);
    $status = $_POST['status'] ?? '';
    $remarks = trim($_POST['remarks'] ?? '');

    if (!$id || !$status) { echo json_encode(['success' => false, 'message' => 'Missing required fields']); exit; }
    if (!in_array($status, ['pending','completed','no_show'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid status']);
        exit;
    }

    $stmt = $db->prepare("UPDATE report_to_office SET status = ?, remarks = ? WHERE id = ?");
    $stmt->execute([$status, $remarks, $id]);
    logActivity('Update RTO', "Updated RTO #$id status to $status");
    echo json_encode(['success' => true, 'message' => 'Report-to-Office updated']);
    exit;

// ============================================================
// CREATE BACK-TO-WORK EVALUATION
// ============================================================
case 'create_back_to_work':
    requireAdmin();
    header('Content-Type: application/json');
    $employeeId = (int)($_POST['employee_id'] ?? 0);
    $evaluationResult = trim($_POST['evaluation_result'] ?? '');
    $status = $_POST['status'] ?? 'pending';
    $remarks = trim($_POST['remarks'] ?? '');
    $evaluationDate = $_POST['evaluation_date'] ?? date('Y-m-d');

    if (!$employeeId) {
        echo json_encode(['success' => false, 'message' => 'Employee is required']);
        exit;
    }
    if (!in_array($status, ['pending','approved','failed','requires_further_action'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid status']);
        exit;
    }

    $stmt = $db->prepare("INSERT INTO back_to_work (employee_id, evaluation_result, status, remarks, evaluated_by, evaluation_date) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$employeeId, $evaluationResult, $status, $remarks, $_SESSION['user_id'], $evaluationDate]);

    $emp = $db->prepare("SELECT CONCAT(last_name, ', ', first_name) as name FROM employees WHERE id = ?");
    $emp->execute([$employeeId]);
    $empName = $emp->fetchColumn() ?: "ID $employeeId";
    logActivity('Back-to-Work', "Created BTW evaluation for $empName — Status: $status");
    echo json_encode(['success' => true, 'message' => 'Back-to-Work evaluation recorded']);
    exit;

// ============================================================
// UPDATE BACK-TO-WORK EVALUATION
// ============================================================
case 'update_back_to_work':
    requireAdmin();
    header('Content-Type: application/json');
    $id = (int)($_POST['id'] ?? 0);
    $evaluationResult = trim($_POST['evaluation_result'] ?? '');
    $status = $_POST['status'] ?? '';
    $remarks = trim($_POST['remarks'] ?? '');

    if (!$id || !$status) { echo json_encode(['success' => false, 'message' => 'Missing required fields']); exit; }
    if (!in_array($status, ['pending','approved','failed','requires_further_action'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid status']);
        exit;
    }

    $stmt = $db->prepare("UPDATE back_to_work SET evaluation_result = ?, status = ?, remarks = ?, evaluated_by = ?, evaluation_date = CURDATE() WHERE id = ?");
    $stmt->execute([$evaluationResult, $status, $remarks, $_SESSION['user_id'], $id]);

    // Fetch employee details to get department and name
    $empInfo = $db->prepare("SELECT e.first_name, e.last_name, e.department_id FROM back_to_work b JOIN employees e ON b.employee_id = e.id WHERE b.id = ?");
    $empInfo->execute([$id]);
    $employee = $empInfo->fetch(PDO::FETCH_ASSOC);

    if ($employee) {
        $empName = $employee['last_name'] . ', ' . $employee['first_name'];
        $deptId = $employee['department_id'];

        // Find coordinator for this department
        $coordsStmt = $db->prepare("SELECT id FROM users WHERE role = 'coordinator' AND department_id = ? AND status = 'active'");
        $coordsStmt->execute([$deptId]);
        $coords = $coordsStmt->fetchAll(PDO::FETCH_ASSOC);

        $senderName = $_SESSION['full_name'] ?? 'Admin';
        $statusLabels = [
            'approved' => 'Approved',
            'failed' => 'Failed',
            'requires_further_action' => 'Requires Further Action',
            'pending' => 'Pending'
        ];
        $statusText = $statusLabels[$status] ?? $status;

        foreach ($coords as $coord) {
            $stmtNotif = $db->prepare("INSERT INTO notifications (user_id, sender_id, type, message, link) VALUES (?, ?, 'btw_processed', ?, '/ATTENDANCE/coordinator/employee_actions.php?tab=btw')");
            $stmtNotif->execute([$coord['id'], $_SESSION['user_id'], "Admin $senderName processed Back-to-Work for $empName (Status: $statusText)"]);
        }
    }

    logActivity('Update BTW', "Updated BTW #$id status to $status");
    echo json_encode(['success' => true, 'message' => 'Back-to-Work evaluation updated']);
    exit;

// ============================================================
// CREATE SUSPENSION
// ============================================================
case 'create_suspension':
    requireAdmin();
    header('Content-Type: application/json');
    $employeeId = (int)($_POST['employee_id'] ?? 0);
    $suspensionDate = $_POST['suspension_date'] ?? '';
    $endDate = $_POST['end_date'] ?? null;
    $reason = trim($_POST['reason'] ?? '');
    $remarks = trim($_POST['remarks'] ?? '');

    if (!$employeeId || !$suspensionDate || !$reason) {
        echo json_encode(['success' => false, 'message' => 'Employee, suspension date, and reason are required']);
        exit;
    }

    $stmt = $db->prepare("INSERT INTO suspensions (employee_id, suspension_date, end_date, reason, issued_by, remarks) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$employeeId, $suspensionDate, $endDate ?: null, $reason, $_SESSION['user_id'], $remarks]);

    $emp = $db->prepare("SELECT CONCAT(last_name, ', ', first_name) as name FROM employees WHERE id = ?");
    $emp->execute([$employeeId]);
    $empName = $emp->fetchColumn() ?: "ID $employeeId";
    logActivity('Suspend Employee', "Suspended $empName from $suspensionDate. Reason: $reason");
    echo json_encode(['success' => true, 'message' => 'Employee suspended']);
    exit;

// ============================================================
// UPDATE SUSPENSION
// ============================================================
case 'update_suspension':
    requireAdmin();
    header('Content-Type: application/json');
    $id = (int)($_POST['id'] ?? 0);
    $suspensionDate = $_POST['suspension_date'] ?? '';
    $endDate = $_POST['end_date'] ?? null;
    $reason = trim($_POST['reason'] ?? '');
    $remarks = trim($_POST['remarks'] ?? '');

    if (!$id) { echo json_encode(['success' => false, 'message' => 'Missing suspension ID']); exit; }

    $stmt = $db->prepare("UPDATE suspensions SET suspension_date = ?, end_date = ?, reason = ?, remarks = ? WHERE id = ?");
    $stmt->execute([$suspensionDate, $endDate ?: null, $reason, $remarks, $id]);
    logActivity('Update Suspension', "Updated suspension #$id");
    echo json_encode(['success' => true, 'message' => 'Suspension updated']);
    exit;

// ============================================================
// LIFT SUSPENSION
// ============================================================
case 'lift_suspension':
    requireAdmin();
    header('Content-Type: application/json');
    $id = (int)($_POST['id'] ?? 0);
    $remarks = trim($_POST['remarks'] ?? '');

    if (!$id) { echo json_encode(['success' => false, 'message' => 'Missing suspension ID']); exit; }

    $stmt = $db->prepare("UPDATE suspensions SET status = 'lifted', end_date = CURDATE(), remarks = ? WHERE id = ?");
    $stmt->execute([$remarks, $id]);
    logActivity('Lift Suspension', "Lifted suspension #$id");
    echo json_encode(['success' => true, 'message' => 'Suspension lifted']);
    exit;

// ============================================================
// COORDINATOR REQUEST RTO
// ============================================================
case 'coordinator_request_rto':
    requireCoordinator();
    header('Content-Type: application/json');
    $employeeId = (int)($_POST['employee_id'] ?? 0);
    $reason = trim($_POST['reason'] ?? '');
    $reportDate = $_POST['report_date'] ?? date('Y-m-d');

    if (!$employeeId || !$reason) {
        echo json_encode(['success' => false, 'message' => 'Employee and reason are required']);
        exit;
    }

    // Verify coordinator has access to this employee
    requireEmployeeAccess($db, $employeeId);

    // Insert as 'requested' status
    $stmt = $db->prepare("INSERT INTO report_to_office (employee_id, reason, report_date, issued_by, status, remarks) VALUES (?, ?, ?, ?, 'requested', '')");
    $stmt->execute([$employeeId, $reason, $reportDate, $_SESSION['user_id']]);

    // Fetch employee name
    $empStmt = $db->prepare("SELECT CONCAT(last_name, ', ', first_name) FROM employees WHERE id = ?");
    $empStmt->execute([$employeeId]);
    $empName = $empStmt->fetchColumn() ?: "Employee #$employeeId";

    // Send notifications to all active admins
    $admins = $db->query("SELECT id FROM users WHERE role = 'admin' AND status = 'active'")->fetchAll();
    $senderName = $_SESSION['user_name'] ?? $user['full_name'];
    foreach ($admins as $admin) {
        $stmtNotif = $db->prepare("INSERT INTO notifications (user_id, sender_id, type, message, link) VALUES (?, ?, 'rto_request', ?, '/ATTENDANCE/admin/employee_actions.php?tab=rto')");
        $stmtNotif->execute([$admin['id'], $_SESSION['user_id'], "Coordinator $senderName requested RTO notice for $empName"]);
    }

    logActivity('RTO Request', "Coordinator $senderName requested RTO for $empName");
    echo json_encode(['success' => true, 'message' => 'RTO request sent to Admin']);
    exit;

// ============================================================
// APPROVE RTO REQUEST
// ============================================================
case 'approve_rto_request':
    requireAdmin();
    header('Content-Type: application/json');
    $id = (int)($_POST['id'] ?? 0);
    if (!$id) { echo json_encode(['success' => false, 'message' => 'Missing request ID']); exit; }

    // Fetch RTO details
    $rtoStmt = $db->prepare("SELECT r.*, CONCAT(e.last_name, ', ', e.first_name) as emp_name FROM report_to_office r JOIN employees e ON r.employee_id = e.id WHERE r.id = ?");
    $rtoStmt->execute([$id]);
    $rto = $rtoStmt->fetch(PDO::FETCH_ASSOC);

    if (!$rto) {
        echo json_encode(['success' => false, 'message' => 'RTO request not found']);
        exit;
    }

    // Update status to pending (active)
    $stmt = $db->prepare("UPDATE report_to_office SET status = 'pending', issued_by = ? WHERE id = ?");
    $stmt->execute([$_SESSION['user_id'], $id]);

    // Notify coordinator who requested it
    $stmtNotif = $db->prepare("INSERT INTO notifications (user_id, sender_id, type, message, link) VALUES (?, ?, 'rto_approved', ?, '/ATTENDANCE/coordinator/employee_actions.php?tab=rto')");
    $stmtNotif->execute([$rto['issued_by'], $_SESSION['user_id'], "Admin approved RTO notice for {$rto['emp_name']}"]);

    logActivity('Approve RTO', "Approved RTO request #$id for {$rto['emp_name']}");
    echo json_encode(['success' => true, 'message' => 'RTO request approved']);
    exit;

// ============================================================
// REJECT RTO REQUEST
// ============================================================
case 'reject_rto_request':
    requireAdmin();
    header('Content-Type: application/json');
    $id = (int)($_POST['id'] ?? 0);
    if (!$id) { echo json_encode(['success' => false, 'message' => 'Missing request ID']); exit; }

    // Fetch RTO details
    $rtoStmt = $db->prepare("SELECT r.*, CONCAT(e.last_name, ', ', e.first_name) as emp_name FROM report_to_office r JOIN employees e ON r.employee_id = e.id WHERE r.id = ?");
    $rtoStmt->execute([$id]);
    $rto = $rtoStmt->fetch(PDO::FETCH_ASSOC);

    if (!$rto) {
        echo json_encode(['success' => false, 'message' => 'RTO request not found']);
        exit;
    }

    // Delete request
    $db->prepare("DELETE FROM report_to_office WHERE id = ?")->execute([$id]);

    // Notify coordinator who requested it
    $stmtNotif = $db->prepare("INSERT INTO notifications (user_id, sender_id, type, message, link) VALUES (?, ?, 'rto_rejected', ?, '/ATTENDANCE/coordinator/employee_actions.php?tab=rto')");
    $stmtNotif->execute([$rto['issued_by'], $_SESSION['user_id'], "Admin rejected RTO request for {$rto['emp_name']}"]);

    logActivity('Reject RTO', "Rejected RTO request #$id for {$rto['emp_name']}");
    echo json_encode(['success' => true, 'message' => 'RTO request rejected']);
    exit;

// ============================================================
// GET NOTIFICATIONS
// ============================================================
case 'get_notifications':
    requireLogin();
    header('Content-Type: application/json');
    $userId = $_SESSION['user_id'] ?? 0;
    if (!$userId) { echo json_encode([]); exit; }

    $stmt = $db->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 20");
    $stmt->execute([$userId]);
    $notifs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $unreadStmt = $db->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
    $unreadStmt->execute([$userId]);
    $unreadCount = (int)$unreadStmt->fetchColumn();

    echo json_encode(['notifications' => $notifs, 'unread_count' => $unreadCount]);
    exit;

// ============================================================
// MARK NOTIFICATIONS READ
// ============================================================
case 'mark_notifications_read':
    requireLogin();
    header('Content-Type: application/json');
    $userId = $_SESSION['user_id'] ?? 0;
    if ($userId) {
        $db->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?")->execute([$userId]);
    }
    echo json_encode(['success' => true]);
    exit;

// ============================================================
// SEND CHAT MESSAGE
// ============================================================
case 'send_chat_message':
    requireLogin();
    header('Content-Type: application/json');
    $senderId = $_SESSION['user_id'] ?? 0;
    $message = trim($_POST['message'] ?? '');

    if (!$senderId || !$message) {
        echo json_encode(['success' => false, 'message' => 'Missing message content']);
        exit;
    }

    $receiverId = 0;
    $userRole = $_SESSION['role'] ?? $_SESSION['user_role'] ?? '';
    if ($userRole === 'admin') {
        $receiverId = (int)($_POST['receiver_id'] ?? 0);
    } else {
        // Coordinator sending to Admin. Find first active admin.
        $adminStmt = $db->query("SELECT id FROM users WHERE role = 'admin' AND status = 'active' ORDER BY id ASC LIMIT 1");
        $receiverId = (int)$adminStmt->fetchColumn();
    }

    if (!$receiverId) {
        echo json_encode(['success' => false, 'message' => 'No active admin/recipient found']);
        exit;
    }

    // Insert chat message
    $stmt = $db->prepare("INSERT INTO chat_messages (sender_id, receiver_id, message) VALUES (?, ?, ?)");
    $stmt->execute([$senderId, $receiverId, htmlspecialchars($message)]);

    // Send simple alert notification to recipient to light up indicator
    $senderName = $_SESSION['full_name'] ?? $_SESSION['user_name'] ?? 'Someone';
    $stmtNotif = $db->prepare("INSERT INTO notifications (user_id, sender_id, type, message, link) VALUES (?, ?, 'chat_alert', ?, '')");
    $stmtNotif->execute([$receiverId, $senderId, "New chat message from $senderName"]);

    echo json_encode(['success' => true]);
    exit;

// ============================================================
// GET CHAT HISTORY
// ============================================================
case 'get_chat_history':
    requireLogin();
    header('Content-Type: application/json');
    $userId = $_SESSION['user_id'] ?? 0;
    if (!$userId) { echo json_encode(['messages' => []]); exit; }

    $otherId = 0;
    $userRole = $_SESSION['role'] ?? $_SESSION['user_role'] ?? '';
    if ($userRole === 'admin') {
        $otherId = (int)($_GET['other_id'] ?? 0);
    } else {
        // Coordinator connects to primary admin
        $adminStmt = $db->query("SELECT id FROM users WHERE role = 'admin' AND status = 'active' ORDER BY id ASC LIMIT 1");
        $otherId = (int)$adminStmt->fetchColumn();
    }

    if (!$otherId) {
        echo json_encode(['messages' => []]);
        exit;
    }

    // Fetch messages
    $stmt = $db->prepare("
        SELECT * FROM chat_messages 
        WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?) 
        ORDER BY created_at ASC
    ");
    $stmt->execute([$userId, $otherId, $otherId, $userId]);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Mark received messages as read
    $mark = $db->prepare("UPDATE chat_messages SET is_read = 1 WHERE sender_id = ? AND receiver_id = ?");
    $mark->execute([$otherId, $userId]);

    echo json_encode(['messages' => $messages]);
    exit;

// ============================================================
// GET CHAT CONTACTS (ADMIN ONLY)
// ============================================================
case 'get_chat_contacts':
    requireAdmin();
    header('Content-Type: application/json');
    $adminId = $_SESSION['user_id'];

    $stmt = $db->prepare("
        SELECT u.id, u.full_name, u.role, d.name as dept_name,
               (SELECT message FROM chat_messages WHERE (sender_id = u.id AND receiver_id = ?) OR (sender_id = ? AND receiver_id = u.id) ORDER BY created_at DESC LIMIT 1) as last_msg,
               (SELECT created_at FROM chat_messages WHERE (sender_id = u.id AND receiver_id = ?) OR (sender_id = ? AND receiver_id = u.id) ORDER BY created_at DESC LIMIT 1) as last_time,
               (SELECT COUNT(*) FROM chat_messages WHERE sender_id = u.id AND receiver_id = ? AND is_read = 0) as unread_count
        FROM users u
        LEFT JOIN departments d ON u.department_id = d.id
        WHERE u.role = 'coordinator' AND u.status = 'active'
        ORDER BY last_time DESC, u.full_name ASC
    ");
    $stmt->execute([$adminId, $adminId, $adminId, $adminId, $adminId]);
    $contacts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['contacts' => $contacts]);
    exit;

// ============================================================
// UNKNOWN ACTION
// ============================================================
default:
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unknown action: ' . $action]);
    exit;
}
