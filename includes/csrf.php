<?php
/**
 * CSRF Protection Module — TAASCOR AttendX
 * Generates and validates CSRF tokens for forms and AJAX requests.
 */

/**
 * Generate or retrieve the current CSRF token for this session.
 */
function getCsrfToken() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['csrf_token_time'] = time();
    }
    // Rotate token every 2 hours
    if (isset($_SESSION['csrf_token_time']) && (time() - $_SESSION['csrf_token_time']) > 7200) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['csrf_token_time'] = time();
    }
    return $_SESSION['csrf_token'];
}

/**
 * Output a hidden form input containing the CSRF token.
 */
function csrfField() {
    $token = getCsrfToken();
    echo '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token) . '">';
}

/**
 * Output a meta tag for AJAX CSRF usage.
 */
function csrfMeta() {
    $token = getCsrfToken();
    echo '<meta name="csrf-token" content="' . htmlspecialchars($token) . '">';
}

/**
 * Validate a CSRF token from POST data or headers.
 * @return bool True if token is valid.
 */
function validateCsrfToken($token = null) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $sessionToken = $_SESSION['csrf_token'] ?? '';
    if (empty($sessionToken)) {
        return false;
    }

    // Use passed token if provided
    $submittedToken = $token;

    // Check POST field first if no token passed
    if (empty($submittedToken)) {
        $submittedToken = $_POST['csrf_token'] ?? '';
    }

    // Then check X-CSRF-Token header (for AJAX)
    if (empty($submittedToken)) {
        $submittedToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    }

    if (empty($submittedToken)) {
        return false;
    }

    return hash_equals($sessionToken, $submittedToken);
}

/**
 * Require a valid CSRF token or die with 403.
 * Call at the top of POST-handling code.
 */
function requireCsrfToken() {
    if (!validateCsrfToken()) {
        http_response_code(403);
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Invalid or missing security token. Please refresh the page and try again.']);
        } else {
            die('<div style="font-family:Inter,sans-serif;background:#0d0e12;color:#fff;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px;">
                <div style="background:rgba(255,255,255,0.06);padding:40px;border-radius:16px;border:1px solid rgba(255,255,255,0.1);text-align:center;max-width:400px;">
                    <h2 style="color:#ef4444;margin-bottom:16px;">🔒 Security Error</h2>
                    <p style="color:#9ca3af;font-size:14px;">Invalid or expired security token. This could happen if your session expired.</p>
                    <a href="javascript:history.back()" style="display:inline-block;margin-top:16px;padding:10px 24px;background:linear-gradient(to right,#20c997,#12b886);color:#fff;text-decoration:none;border-radius:12px;font-weight:600;font-size:14px;">← Go Back</a>
                </div>
            </div>');
        }
        exit;
    }
}
