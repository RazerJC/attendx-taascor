<?php
/**
 * TAASCOR Attendance — Auth, Database, Session & Security Helpers
 * Enhanced with: session hardening, rate limiting, email auth, department scoping
 */

// --- Secure Session Configuration ---
if (session_status() === PHP_SESSION_NONE) {
    // Harden session cookies
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || 
               (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
    
    session_set_cookie_params([
        'lifetime' => 0,            // Session cookie (expires when browser closes)
        'path'     => '/ATTENDANCE/',
        'domain'   => '',
        'secure'   => $isHttps,     // HTTPS only when available
        'httponly'  => true,         // Prevent JS access
        'samesite' => 'Strict',     // Prevent CSRF via cookies
    ]);
    
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
    
    session_start();
}

// Include CSRF module
require_once __DIR__ . '/csrf.php';

// Include Mailer module
require_once __DIR__ . '/mailer.php';

// --- Database (env vars for cloud, fallback for local XAMPP) ---
if (!defined('DB_HOST')) {
    define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
    define('DB_NAME', getenv('DB_NAME') ?: 'taascor_attendance');
    define('DB_USER', getenv('DB_USER') ?: 'root');
    define('DB_PASS', getenv('DB_PASS') ?: '');
    define('DB_PORT', getenv('DB_PORT') ?: '3306');
    define('DB_CHARSET', 'utf8mb4');
}

function getDB() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_TIMEOUT            => 10,
            ];
            // Enable SSL for cloud databases (TiDB Cloud requires TLS)
            if (DB_HOST !== '127.0.0.1' && DB_HOST !== 'localhost') {
                if (defined('PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT')) {
                    $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
                }
                if (defined('PDO::MYSQL_ATTR_SSL_CA')) {
                    $options[PDO::MYSQL_ATTR_SSL_CA] = '';
                }
            }
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);

            // Automatic column migration for reset requests
            try {
                $pdo->query("SELECT reset_requested FROM users LIMIT 1");
            } catch (PDOException $ex) {
                $pdo->exec("ALTER TABLE users ADD COLUMN reset_requested TINYINT DEFAULT 0");
            }
            // Automatic migration for attendance_edit_requests table
            try {
                $pdo->query("SELECT 1 FROM attendance_edit_requests LIMIT 1");
            } catch (PDOException $ex) {
                $pdo->exec("CREATE TABLE IF NOT EXISTS attendance_edit_requests (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    employee_id INT NOT NULL,
                    attendance_date DATE NOT NULL,
                    old_status ENUM('present', 'absent', 'no_work', 'leave', 'sent_home', 'rest_day') NULL,
                    new_status ENUM('present', 'absent', 'no_work', 'leave', 'sent_home', 'rest_day') NOT NULL,
                    requested_by INT NOT NULL,
                    reason TEXT,
                    status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
                    approved_by INT,
                    approved_at TIMESTAMP NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
                    FOREIGN KEY (requested_by) REFERENCES users(id) ON DELETE RESTRICT,
                    FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL,
                    INDEX idx_status (status),
                    INDEX idx_employee (employee_id),
                    INDEX idx_date (attendance_date),
                    INDEX idx_requested_by (requested_by)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            }
            // Automatic migration for absence_warnings table
            try {
                $pdo->query("SELECT 1 FROM absence_warnings LIMIT 1");
            } catch (PDOException $ex) {
                $pdo->exec("CREATE TABLE IF NOT EXISTS absence_warnings (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    employee_id INT NOT NULL,
                    warning_level ENUM('1st_warning','2nd_warning','3rd_warning','final_warning') NOT NULL,
                    absence_count INT NOT NULL DEFAULT 0,
                    issued_by INT NOT NULL,
                    remarks TEXT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
                    FOREIGN KEY (issued_by) REFERENCES users(id) ON DELETE RESTRICT,
                    INDEX idx_employee (employee_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            }
            // Automatic migration for report_to_office table
            try {
                $pdo->query("SELECT 1 FROM report_to_office LIMIT 1");
            } catch (PDOException $ex) {
                $pdo->exec("CREATE TABLE IF NOT EXISTS report_to_office (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    employee_id INT NOT NULL,
                    reason TEXT NOT NULL,
                    report_date DATE NOT NULL,
                    issued_by INT NOT NULL,
                    status ENUM('pending','completed','no_show') NOT NULL DEFAULT 'pending',
                    remarks TEXT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
                    FOREIGN KEY (issued_by) REFERENCES users(id) ON DELETE RESTRICT,
                    INDEX idx_employee (employee_id),
                    INDEX idx_status (status)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            }
            // Automatic migration for back_to_work table
            try {
                $pdo->query("SELECT 1 FROM back_to_work LIMIT 1");
            } catch (PDOException $ex) {
                $pdo->exec("CREATE TABLE IF NOT EXISTS back_to_work (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    employee_id INT NOT NULL,
                    evaluation_result TEXT,
                    status ENUM('pending','approved','failed','requires_further_action') NOT NULL DEFAULT 'pending',
                    remarks TEXT,
                    evaluated_by INT,
                    evaluation_date DATE,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
                    FOREIGN KEY (evaluated_by) REFERENCES users(id) ON DELETE SET NULL,
                    INDEX idx_employee (employee_id),
                    INDEX idx_status (status)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            }
            // Automatic migration: add absence_date to back_to_work table
            try {
                $pdo->query("SELECT absence_date FROM back_to_work LIMIT 1");
            } catch (PDOException $ex) {
                $pdo->exec("ALTER TABLE back_to_work ADD COLUMN absence_date DATE NULL AFTER employee_id");
                $pdo->exec("ALTER TABLE back_to_work ADD INDEX idx_absence_date (absence_date)");
            }
            // Automatic migration for suspensions table
            try {
                $pdo->query("SELECT 1 FROM suspensions LIMIT 1");
            } catch (PDOException $ex) {
                $pdo->exec("CREATE TABLE IF NOT EXISTS suspensions (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    employee_id INT NOT NULL,
                    suspension_date DATE NOT NULL,
                    end_date DATE,
                    reason TEXT NOT NULL,
                    status ENUM('active','lifted','completed') NOT NULL DEFAULT 'active',
                    issued_by INT NOT NULL,
                    remarks TEXT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
                    FOREIGN KEY (issued_by) REFERENCES users(id) ON DELETE RESTRICT,
                    INDEX idx_employee (employee_id),
                    INDEX idx_status (status)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            }
            // Alter report_to_office table status column to include 'requested'
            try {
                $pdo->exec("ALTER TABLE report_to_office MODIFY COLUMN status ENUM('requested','pending','completed','no_show') NOT NULL DEFAULT 'pending'");
            } catch (PDOException $ex) {
                // Ignore if it fails
            }
            // Automatic migration for notifications table
            try {
                $pdo->query("SELECT 1 FROM notifications LIMIT 1");
            } catch (PDOException $ex) {
                $pdo->exec("CREATE TABLE IF NOT EXISTS notifications (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    user_id INT NOT NULL,
                    sender_id INT,
                    type VARCHAR(50) NOT NULL,
                    message TEXT NOT NULL,
                    link VARCHAR(255),
                    is_read BOOLEAN DEFAULT FALSE,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE SET NULL,
                    INDEX idx_user (user_id),
                    INDEX idx_read (is_read)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            }
            // Automatic migration for chat_messages table
            try {
                $pdo->query("SELECT 1 FROM chat_messages LIMIT 1");
            } catch (PDOException $ex) {
                $pdo->exec("CREATE TABLE IF NOT EXISTS chat_messages (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    sender_id INT NOT NULL,
                    receiver_id INT NOT NULL,
                    message TEXT NOT NULL,
                    is_read BOOLEAN DEFAULT FALSE,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
                    FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE,
                    INDEX idx_sender (sender_id),
                    INDEX idx_receiver (receiver_id),
                    INDEX idx_read (is_read)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            }
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
    if (!isset($_SESSION['user_id'])) return false;
    // Session fingerprint validation
    if (isset($_SESSION['_fingerprint'])) {
        $currentFingerprint = hash('sha256', ($_SERVER['HTTP_USER_AGENT'] ?? '') . '|attendx_salt_2026');
        if (!hash_equals($_SESSION['_fingerprint'], $currentFingerprint)) {
            // Fingerprint mismatch — possible session hijack
            session_unset();
            session_destroy();
            return false;
        }
    }
    return true;
}

function currentUser() {
    if (!isLoggedIn()) return null;
    return [
        'id'            => $_SESSION['user_id'],
        'username'      => $_SESSION['username'],
        'email'         => $_SESSION['email'] ?? null,
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
        http_response_code(403);
        header('Location: /ATTENDANCE/index.php');
        exit;
    }
}

function requireCoordinator() {
    requireLogin();
    if ($_SESSION['role'] !== 'coordinator') {
        http_response_code(403);
        header('Location: /ATTENDANCE/index.php');
        exit;
    }
}

/**
 * Require that the current user (admin or coordinator) has access to a given department.
 * Admins can access all departments. Coordinators can only access their assigned department.
 * @param int $departmentId The department to check access for.
 */
function requireDepartmentAccess($departmentId) {
    requireLogin();
    if ($_SESSION['role'] === 'admin') return; // Admins see all
    
    $userDeptId = $_SESSION['department_id'] ?? null;
    if ($userDeptId === null || (int)$userDeptId !== (int)$departmentId) {
        http_response_code(403);
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'You do not have access to this department.']);
        } else {
            header('Location: /ATTENDANCE/index.php');
        }
        exit;
    }
}

/**
 * Check if a coordinator has access to a specific employee.
 * @param PDO $db Database connection
 * @param int $employeeId Employee ID to check
 */
function requireEmployeeAccess($db, $employeeId) {
    requireLogin();
    if ($_SESSION['role'] === 'admin') return;
    
    $stmt = $db->prepare("SELECT department_id FROM employees WHERE id = ?");
    $stmt->execute([$employeeId]);
    $emp = $stmt->fetch();
    
    if (!$emp) {
        http_response_code(404);
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Employee not found.']);
        } else {
            header('Location: /ATTENDANCE/index.php');
        }
        exit;
    }
    
    requireDepartmentAccess($emp['department_id']);
}

/**
 * Set session variables on successful login.
 * Regenerates session ID to prevent fixation.
 */
function setLoginSession($user) {
    // Regenerate session ID to prevent session fixation
    session_regenerate_id(true);
    
    $_SESSION['user_id']       = $user['id'];
    $_SESSION['username']      = $user['username'];
    $_SESSION['email']         = $user['email'] ?? null;
    $_SESSION['full_name']     = $user['full_name'];
    $_SESSION['role']          = $user['role'];
    $_SESSION['department_id'] = $user['department_id'];
    $_SESSION['login_time']    = time();
    
    // Session fingerprint (User-Agent based)
    $_SESSION['_fingerprint'] = hash('sha256', ($_SERVER['HTTP_USER_AGENT'] ?? '') . '|attendx_salt_2026');
}

// --- Rate Limiting ---

/**
 * Check if login attempts from this IP/identifier are rate-limited.
 * @return array ['allowed' => bool, 'remaining' => int, 'retry_after' => int seconds]
 */
function checkLoginRateLimit($identifier) {
    $db = getDB();
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $windowMinutes = 15;
    $maxAttempts = 10;
    
    // Clean old entries (older than 1 hour)
    try {
        $db->exec("DELETE FROM login_rate_limits WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 1 HOUR)");
    } catch (PDOException $e) {
        // Table might not exist yet, allow login
        return ['allowed' => true, 'remaining' => $maxAttempts, 'retry_after' => 0];
    }
    
    // Count recent failed attempts from this IP
    try {
        $stmt = $db->prepare("SELECT COUNT(*) FROM login_rate_limits WHERE ip_address = ? AND success = 0 AND attempted_at > DATE_SUB(NOW(), INTERVAL ? MINUTE)");
        $stmt->execute([$ip, $windowMinutes]);
        $failedCount = (int)$stmt->fetchColumn();
    } catch (PDOException $e) {
        return ['allowed' => true, 'remaining' => $maxAttempts, 'retry_after' => 0];
    }
    
    if ($failedCount >= $maxAttempts) {
        // Find when the oldest relevant attempt was
        $stmt = $db->prepare("SELECT MIN(attempted_at) FROM login_rate_limits WHERE ip_address = ? AND success = 0 AND attempted_at > DATE_SUB(NOW(), INTERVAL ? MINUTE)");
        $stmt->execute([$ip, $windowMinutes]);
        $oldest = $stmt->fetchColumn();
        $retryAfter = $oldest ? max(0, ($windowMinutes * 60) - (time() - strtotime($oldest))) : 60;
        
        return ['allowed' => false, 'remaining' => 0, 'retry_after' => $retryAfter];
    }
    
    return ['allowed' => true, 'remaining' => $maxAttempts - $failedCount, 'retry_after' => 0];
}

/**
 * Record a login attempt.
 */
function recordLoginAttempt($identifier, $success) {
    try {
        $db = getDB();
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $stmt = $db->prepare("INSERT INTO login_rate_limits (ip_address, email_or_username, success) VALUES (?, ?, ?)");
        $stmt->execute([$ip, $identifier, $success ? 1 : 0]);
        
        // If successful, clear previous failures for this IP
        if ($success) {
            $db->prepare("DELETE FROM login_rate_limits WHERE ip_address = ? AND success = 0")->execute([$ip]);
        }
    } catch (PDOException $e) {
        // Silently fail — don't block login if rate limit table missing
    }
}

/**
 * Check if a user account is locked due to too many failed attempts.
 * @return array ['locked' => bool, 'retry_after' => int seconds]
 */
function checkAccountLock($user) {
    if (!empty($user['locked_until'])) {
        $lockedUntil = strtotime($user['locked_until']);
        if ($lockedUntil > time()) {
            return ['locked' => true, 'retry_after' => $lockedUntil - time()];
        }
    }
    return ['locked' => false, 'retry_after' => 0];
}

/**
 * Increment login attempts on a user account and lock if exceeded.
 */
function incrementLoginAttempts($userId) {
    try {
        $db = getDB();
        $lockThreshold = 10;
        $lockDuration = 30; // minutes
        
        $db->prepare("UPDATE users SET login_attempts = login_attempts + 1 WHERE id = ?")->execute([$userId]);
        
        // Check if threshold exceeded
        $stmt = $db->prepare("SELECT login_attempts FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $attempts = (int)$stmt->fetchColumn();
        
        if ($attempts >= $lockThreshold) {
            $db->prepare("UPDATE users SET locked_until = DATE_ADD(NOW(), INTERVAL ? MINUTE) WHERE id = ?")
               ->execute([$lockDuration, $userId]);
        }
    } catch (PDOException $e) {
        // Silently fail
    }
}

/**
 * Reset login attempts on successful login.
 */
function resetLoginAttempts($userId) {
    try {
        $db = getDB();
        $db->prepare("UPDATE users SET login_attempts = 0, locked_until = NULL, last_login = NOW() WHERE id = ?")
           ->execute([$userId]);
    } catch (PDOException $e) {
        // Silently fail
    }
}

// --- Activity Logging ---
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

// --- Flash Messages ---
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
    if ($_SESSION['role'] === 'admin') {
        return $db->query("SELECT * FROM departments ORDER BY name")->fetchAll();
    }
    if ($_SESSION['role'] === 'coordinator' && $deptId === null) {
        return [];
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
    if ($_SESSION['role'] === 'admin') {
        return $db->query("
            SELECT e.id, e.first_name, e.last_name, e.position, e.department_id
            FROM employees e WHERE e.status='active'
            ORDER BY e.last_name, e.first_name
        ")->fetchAll();
    }
    if ($_SESSION['role'] === 'coordinator' && $deptId === null) {
        return [];
    }
    $stmt = $db->prepare("
        SELECT e.id, e.first_name, e.last_name, e.position, e.department_id
        FROM employees e WHERE e.status='active' AND e.department_id = ?
        ORDER BY e.last_name, e.first_name
    ");
    $stmt->execute([$deptId]);
    return $stmt->fetchAll();
}

/**
 * Validate a date string format.
 * @return bool
 */
function isValidDate($dateStr, $format = 'Y-m-d') {
    $d = DateTime::createFromFormat($format, $dateStr);
    return $d && $d->format($format) === $dateStr;
}

/**
 * Validate a positive integer.
 * @return int|false
 */
function validatePositiveInt($value) {
    $val = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    return $val !== false ? $val : false;
}
