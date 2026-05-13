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
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            die('<div style="padding:40px;font-family:sans-serif;text-align:center;">
                <h2>Database Connection Error</h2>
                <p>Could not connect to MySQL. Please make sure XAMPP/MySQL is running.</p>
                <p style="color:#888;font-size:13px;">' . $e->getMessage() . '</p>
            </div>');
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
