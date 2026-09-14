<?php
/**
 * SMTP Mailer — TAASCOR AttendX
 * Lightweight SMTP mailer using fsockopen (no external dependencies).
 * Configured for Hostinger email hosting.
 * 
 * Environment variables:
 *   SMTP_HOST — e.g. smtp.hostinger.com
 *   SMTP_PORT — e.g. 465 (SSL) or 587 (TLS)
 *   SMTP_USER — e.g. noreply@taascor.com
 *   SMTP_PASS — mailbox password
 *   SITE_URL  — e.g. https://yourdomain.com/ATTENDANCE
 */

// SMTP Configuration from environment
define('SMTP_HOST', getenv('SMTP_HOST') ?: 'smtp.hostinger.com');
define('SMTP_PORT', (int)(getenv('SMTP_PORT') ?: 465));
define('SMTP_USER', getenv('SMTP_USER') ?: 'noreply@taascor.com');
define('SMTP_PASS', getenv('SMTP_PASS') ?: '');
define('SMTP_FROM_NAME', 'TAASCOR AttendX');
define('SITE_URL', getenv('SITE_URL') ?: 'http://localhost/ATTENDANCE');

/**
 * Send an email via SMTP.
 * @param string $to Recipient email
 * @param string $subject Email subject
 * @param string $htmlBody HTML email body
 * @return array ['success' => bool, 'message' => string]
 */
function sendSmtpEmail($to, $subject, $htmlBody) {
    // If SMTP password not configured, return failure with helpful message
    if (empty(SMTP_PASS)) {
        error_log("AttendX Mailer: SMTP_PASS not configured. Email to $to not sent.");
        return ['success' => false, 'message' => 'Email service not configured. Please contact your administrator.'];
    }

    try {
        $useSSL = (SMTP_PORT === 465);
        $host = $useSSL ? 'ssl://' . SMTP_HOST : SMTP_HOST;
        
        $socket = @fsockopen($host, SMTP_PORT, $errno, $errstr, 15);
        if (!$socket) {
            error_log("AttendX Mailer: Connection failed — $errstr ($errno)");
            return ['success' => false, 'message' => 'Unable to connect to email server.'];
        }

        // Set stream timeout
        stream_set_timeout($socket, 15);

        // Read greeting
        $response = fgets($socket, 512);
        if (substr($response, 0, 3) !== '220') {
            fclose($socket);
            return ['success' => false, 'message' => 'Email server rejected connection.'];
        }

        // EHLO
        smtpCommand($socket, "EHLO " . gethostname(), '250');

        // STARTTLS for port 587
        if (!$useSSL && SMTP_PORT === 587) {
            smtpCommand($socket, "STARTTLS", '220');
            stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            smtpCommand($socket, "EHLO " . gethostname(), '250');
        }

        // AUTH LOGIN
        smtpCommand($socket, "AUTH LOGIN", '334');
        smtpCommand($socket, base64_encode(SMTP_USER), '334');
        smtpCommand($socket, base64_encode(SMTP_PASS), '235');

        // MAIL FROM
        smtpCommand($socket, "MAIL FROM:<" . SMTP_USER . ">", '250');

        // RCPT TO
        smtpCommand($socket, "RCPT TO:<$to>", '250');

        // DATA
        smtpCommand($socket, "DATA", '354');

        // Headers and body
        $boundary = md5(uniqid(time()));
        $message = "From: " . SMTP_FROM_NAME . " <" . SMTP_USER . ">\r\n";
        $message .= "To: $to\r\n";
        $message .= "Subject: $subject\r\n";
        $message .= "MIME-Version: 1.0\r\n";
        $message .= "Content-Type: multipart/alternative; boundary=\"$boundary\"\r\n";
        $message .= "X-Mailer: TAASCOR-AttendX\r\n";
        $message .= "\r\n";
        
        // Plain text version
        $plainText = strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>', '</div>'], "\n", $htmlBody));
        $message .= "--$boundary\r\n";
        $message .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $message .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
        $message .= $plainText . "\r\n";
        
        // HTML version
        $message .= "--$boundary\r\n";
        $message .= "Content-Type: text/html; charset=UTF-8\r\n";
        $message .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
        $message .= $htmlBody . "\r\n";
        $message .= "--$boundary--\r\n";

        // Escape dots at start of line
        $message = str_replace("\r\n.\r\n", "\r\n..\r\n", $message);
        
        fputs($socket, $message . "\r\n.\r\n");
        $response = fgets($socket, 512);
        
        // QUIT
        fputs($socket, "QUIT\r\n");
        fclose($socket);

        if (substr($response, 0, 3) === '250') {
            return ['success' => true, 'message' => 'Email sent successfully.'];
        } else {
            error_log("AttendX Mailer: DATA response — $response");
            return ['success' => false, 'message' => 'Email server rejected the message.'];
        }

    } catch (Exception $e) {
        error_log("AttendX Mailer: Exception — " . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to send email: ' . $e->getMessage()];
    }
}

/**
 * Send an SMTP command and check response.
 */
function smtpCommand($socket, $command, $expectedCode) {
    fputs($socket, $command . "\r\n");
    $response = '';
    while ($line = fgets($socket, 512)) {
        $response .= $line;
        // Check for end of multi-line response
        if (substr($line, 3, 1) === ' ') break;
    }
    if (substr($response, 0, 3) !== $expectedCode) {
        throw new Exception("SMTP Error: Expected $expectedCode, got: $response");
    }
    return $response;
}

// ============================================================
// Email Templates
// ============================================================

/**
 * Send email verification link.
 */
function sendVerificationEmail($email, $token, $fullName = '') {
    $verifyUrl = SITE_URL . '/verify_email.php?token=' . urlencode($token) . '&email=' . urlencode($email);
    $name = $fullName ?: $email;

    $html = getEmailTemplate(
        'Verify Your Email',
        "Hello $name,",
        'Please verify your @taascor.com business email to complete your AttendX account registration.',
        $verifyUrl,
        'Verify Email Address',
        'This link expires in 24 hours. If you did not create this account, please ignore this email.'
    );

    return sendSmtpEmail($email, 'Verify Your Email — TAASCOR AttendX', $html);
}

/**
 * Send password reset link.
 */
function sendPasswordResetEmail($email, $token, $fullName = '') {
    $resetUrl = SITE_URL . '/reset_password.php?token=' . urlencode($token) . '&email=' . urlencode($email);
    $name = $fullName ?: $email;

    $html = getEmailTemplate(
        'Password Reset Request',
        "Hello $name,",
        'You requested a password reset for your TAASCOR AttendX account. Click the button below to set a new password.',
        $resetUrl,
        'Reset Password',
        'This link expires in 1 hour. If you did not request this reset, please ignore this email and your password will remain unchanged.'
    );

    return sendSmtpEmail($email, 'Password Reset — TAASCOR AttendX', $html);
}

/**
 * Generate a branded HTML email template matching the TAASCOR dark/green theme.
 */
function getEmailTemplate($title, $greeting, $body, $actionUrl, $actionText, $footer) {
    return '<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"></head>
<body style="margin:0;padding:0;background-color:#0d0e12;font-family:Inter,Arial,Helvetica,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background-color:#0d0e12;padding:40px 20px;">
<tr><td align="center">
<table width="480" cellpadding="0" cellspacing="0" style="background:rgba(255,255,255,0.06);border:1px solid rgba(255,255,255,0.1);border-radius:20px;overflow:hidden;">
    <!-- Header -->
    <tr><td style="background:linear-gradient(135deg,#12b886,#0ca678);padding:28px 32px;text-align:center;">
        <h1 style="margin:0;color:#fff;font-size:22px;font-weight:700;letter-spacing:0.5px;">TAASCOR AttendX</h1>
        <p style="margin:6px 0 0;color:rgba(255,255,255,0.8);font-size:11px;text-transform:uppercase;letter-spacing:3px;">Attendance Monitoring System</p>
    </td></tr>
    <!-- Body -->
    <tr><td style="padding:32px;">
        <h2 style="margin:0 0 16px;color:#fff;font-size:18px;font-weight:600;">' . htmlspecialchars($title) . '</h2>
        <p style="margin:0 0 8px;color:#d1d5db;font-size:14px;line-height:1.6;">' . htmlspecialchars($greeting) . '</p>
        <p style="margin:0 0 24px;color:#9ca3af;font-size:14px;line-height:1.6;">' . htmlspecialchars($body) . '</p>
        <table width="100%" cellpadding="0" cellspacing="0">
        <tr><td align="center">
            <a href="' . htmlspecialchars($actionUrl) . '" style="display:inline-block;padding:14px 32px;background:linear-gradient(to right,#20c997,#12b886);color:#fff;text-decoration:none;border-radius:12px;font-weight:700;font-size:14px;letter-spacing:0.5px;">' . htmlspecialchars($actionText) . '</a>
        </td></tr>
        </table>
        <p style="margin:24px 0 0;color:#6b7280;font-size:12px;line-height:1.5;border-top:1px solid rgba(255,255,255,0.06);padding-top:16px;">
            ' . htmlspecialchars($footer) . '
        </p>
        <p style="margin:12px 0 0;color:#4b5563;font-size:11px;word-break:break-all;">
            If the button doesn\'t work, copy this link:<br>
            <a href="' . htmlspecialchars($actionUrl) . '" style="color:#38d9a9;">' . htmlspecialchars($actionUrl) . '</a>
        </p>
    </td></tr>
    <!-- Footer -->
    <tr><td style="padding:16px 32px;text-align:center;border-top:1px solid rgba(255,255,255,0.06);">
        <p style="margin:0;color:#4b5563;font-size:11px;">© ' . date('Y') . ' TAASCOR Management & General Services Corp.</p>
    </td></tr>
</table>
</td></tr>
</table>
</body></html>';
}

/**
 * Validate that an email is a valid @taascor.com address.
 * @return bool
 */
function validateTaascorEmail($email) {
    $email = strtolower(trim($email));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return false;
    }
    // Extract domain
    $parts = explode('@', $email);
    if (count($parts) !== 2) {
        return false;
    }
    // Exact domain match — only taascor.com
    return $parts[1] === 'taascor.com';
}

/**
 * Generate a secure random token for email verification or password reset.
 */
function generateSecureToken() {
    return bin2hex(random_bytes(32));
}
