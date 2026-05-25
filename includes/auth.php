<?php
/**
 * TAASCOR Attendance — Auth, Database & Session Helpers
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// --- Database (env vars for Render.com, fallback for local XAMPP) ---
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('DB_NAME') ?: 'taascor_attendance');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_CHARSET', 'utf8mb4');

function getDB() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            // Enable SSL for cloud databases (TiDB Cloud requires TLS)
            if (DB_HOST !== 'localhost' && DB_HOST !== '127.0.0.1') {
                $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
                $options[PDO::MYSQL_ATTR_SSL_CA] = '';
            }
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            http_response_code(503);
            die('<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
            <title>Database Error — AttendX</title>
            <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
            <style>*{margin:0;padding:0;box-sizing:border-box}body{font-family:Inter,sans-serif;background:#0d0e12;color:#fff;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
            .card{background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.1);border-radius:20px;padding:32px;max-width:480px;width:100%;text-align:center}
            h2{color:#f87171;margin-bottom:12px;font-size:20px}p{color:#9ca3af;font-size:14px;line-height:1.6;margin-bottom:12px}
            .err{background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.2);border-radius:10px;padding:12px;color:#fca5a5;font-size:12px;font-family:monospace;word-break:break-all;margin:16px 0}
            .hint{background:rgba(59,130,246,.08);border:1px solid rgba(59,130,246,.15);border-radius:10px;padding:14px;color:#93c5fd;font-size:13px;text-align:left;margin-top:16px}
            .hint strong{color:#60a5fa}</style></head><body><div class="card">
            <h2>⚠️ Database Connection Error</h2>
            <p>AttendX cannot connect to the MySQL database.</p>
            <div class="err">' . htmlspecialchars($e->getMessage()) . '</div>
            <div class="hint"><strong>How to fix:</strong><br>
            1. Go to your <strong>Render Dashboard → Environment</strong><br>
            2. Set these variables: <strong>DB_HOST</strong>, <strong>DB_NAME</strong>, <strong>DB_USER</strong>, <strong>DB_PASS</strong><br>
            3. Use a free MySQL provider like <strong>TiDB Cloud</strong> or <strong>Aiven</strong><br>
            4. After setting env vars, run <strong>/ATTENDANCE/cloud-setup.php?key=YOUR_SETUP_KEY</strong></div>
            </div></body></html>');
        }
    }
    return $pdo;
}

// --- Auth ---
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function currentUser() {
    if (!isLoggedIn()) return null;
    return [
        'id'            => $_SESSION['user_id'],
        'username'      => $_SESSION['username'],
        'full_name'     => $_SESSION['full_name'],
        'role'          => $_SESSION['role'],
        'department_id' => $_SESSION['department_id'] ?? null,
    ];
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: /ATTENDANCE/index.php');
        exit;
    }
}

function requireAdmin() {
    requireLogin();
    if ($_SESSION['role'] !== 'admin') {
        header('Location: /ATTENDANCE/index.php');
        exit;
    }
}

function requireCoordinator() {
    requireLogin();
    if ($_SESSION['role'] !== 'coordinator') {
        header('Location: /ATTENDANCE/index.php');
        exit;
    }
}

function logActivity($action, $details = '') {
    try {
        $db = getDB();
        $userId = $_SESSION['user_id'] ?? null;
        $stmt = $db->prepare("INSERT INTO activity_log (user_id, action, details) VALUES (?, ?, ?)");
        $stmt->execute([$userId, $action, $details]);
    } catch (PDOException $e) {
        // Silently fail if user was deleted or FK constraint fails
    }
}

function setFlash($type, $message) {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

/**
 * Get departments visible to the current user.
 * Admin & coordinators with NULL department_id see ALL.
 * Coordinators with a specific department_id see only theirs.
 */
function getVisibleDepartments($db) {
    $deptId = $_SESSION['department_id'] ?? null;
    if ($_SESSION['role'] === 'admin' || $deptId === null) {
        return $db->query("SELECT * FROM departments ORDER BY name")->fetchAll();
    }
    $stmt = $db->prepare("SELECT * FROM departments WHERE id = ? ORDER BY name");
    $stmt->execute([$deptId]);
    return $stmt->fetchAll();
}

/**
 * Get employees visible to the current user.
 * Admin & coordinators with NULL department_id see ALL.
 * Coordinators with a specific department_id see only theirs.
 */
function getVisibleEmployees($db) {
    $deptId = $_SESSION['department_id'] ?? null;
    if ($_SESSION['role'] === 'admin' || $deptId === null) {
        return $db->query("
            SELECT e.id, e.first_name, e.last_name, e.position, e.department_id
            FROM employees e WHERE e.status='active'
            ORDER BY e.last_name, e.first_name
        ")->fetchAll();
    }
    $stmt = $db->prepare("
        SELECT e.id, e.first_name, e.last_name, e.position, e.department_id
        FROM employees e WHERE e.status='active' AND e.department_id = ?
        ORDER BY e.last_name, e.first_name
    ");
    $stmt->execute([$deptId]);
    return $stmt->fetchAll();
}
