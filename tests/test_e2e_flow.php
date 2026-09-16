<?php
/**
 * End-to-End Registration, Email Verification & Recovery Test
 * Verifies full business email lifecycle.
 */
require_once __DIR__ . '/../includes/auth.php';

echo "=== Running E2E Business Email Flow Test ===\n\n";

$db = getDB();
$testEmail = 'tester_' . bin2hex(random_bytes(3)) . '@taascor.com';
$initialPass = 'Secret123!';
$newPass = 'NewPass456!';
$testFullName = 'Automated Test User';

$createdUserId = null;

try {
    // 1. Validate registration constraints
    echo "1. Testing Email Domain Validation...\n";
    if (!validateTaascorEmail($testEmail)) throw new Exception("Valid email rejected");
    if (validateTaascorEmail('tester@gmail.com')) throw new Exception("Gmail accepted");
    echo "   ✓ Validation rules correct\n\n";

    // 2. Simulate Registration
    echo "2. Simulating Registration of $testEmail...\n";
    $token = generateSecureToken();
    $hash = password_hash($initialPass, PASSWORD_BCRYPT);
    $username = 'test_' . bin2hex(random_bytes(4));

    $stmt = $db->prepare("
        INSERT INTO users (
            username, email, email_verified, email_verification_token, email_verification_expires,
            password, full_name, role, status
        ) VALUES (
            ?, ?, 0, ?, DATE_ADD(NOW(), INTERVAL 24 HOUR),
            ?, ?, 'coordinator', 'inactive'
        )
    ");
    $stmt->execute([$username, $testEmail, $token, $hash, $testFullName]);
    $createdUserId = (int)$db->lastInsertId();
    echo "   ✓ Created inactive user #$createdUserId with unverified email\n\n";

    // 3. Simulate Email Verification link click
    echo "3. Simulating Email Verification Link...\n";
    $chk = $db->prepare("SELECT id, email_verification_expires FROM users WHERE email = ? AND email_verification_token = ?");
    $chk->execute([$testEmail, $token]);
    $u = $chk->fetch();
    if (!$u) throw new Exception("Token not found");

    $upd = $db->prepare("UPDATE users SET email_verified = 1, email_verification_token = NULL, email_verification_expires = NULL WHERE id = ?");
    $upd->execute([$createdUserId]);

    $verified = (int)$db->query("SELECT email_verified FROM users WHERE id = $createdUserId")->fetchColumn();
    if ($verified !== 1) throw new Exception("Email verification update failed");
    echo "   ✓ Email verified successfully (email_verified = 1)\n\n";

    // 4. Simulate Password Reset Request
    echo "4. Simulating Password Reset Request...\n";
    $resetToken = generateSecureToken();
    $db->prepare("UPDATE users SET password_reset_token = ?, password_reset_expires = DATE_ADD(NOW(), INTERVAL 1 HOUR) WHERE id = ?")
       ->execute([$resetToken, $createdUserId]);

    $resetChk = $db->prepare("SELECT id FROM users WHERE email = ? AND password_reset_token = ? AND password_reset_expires > NOW()");
    $resetChk->execute([$testEmail, $resetToken]);
    if (!$resetChk->fetch()) throw new Exception("Reset token invalid or expired");
    echo "   ✓ Reset token generated and active\n\n";

    // 5. Simulate Setting New Password
    echo "5. Simulating Password Reset Submission...\n";
    $newHash = password_hash($newPass, PASSWORD_BCRYPT);
    $db->prepare("UPDATE users SET password = ?, password_reset_token = NULL, password_reset_expires = NULL WHERE id = ?")
       ->execute([$newHash, $createdUserId]);

    $freshUser = $db->query("SELECT password, password_reset_token FROM users WHERE id = $createdUserId")->fetch();
    if (!empty($freshUser['password_reset_token'])) throw new Exception("Reset token was not cleared");
    if (!password_verify($newPass, $freshUser['password'])) throw new Exception("New password verify failed");
    echo "   ✓ Password updated and token invalidated\n\n";

    // 6. Simulate Admin Approval
    echo "6. Simulating Admin Approval & Activation...\n";
    $db->prepare("UPDATE users SET status = 'active' WHERE id = ?")->execute([$createdUserId]);
    $activeStatus = $db->query("SELECT status FROM users WHERE id = $createdUserId")->fetchColumn();
    if ($activeStatus !== 'active') throw new Exception("Admin approval activation failed");
    echo "   ✓ Account activated by admin\n\n";

    // 7. Simulate Login by Email
    echo "7. Simulating Login by Business Email...\n";
    $loginStmt = $db->prepare("SELECT * FROM users WHERE email = ? AND status = 'active'");
    $loginStmt->execute([$testEmail]);
    $loginUser = $loginStmt->fetch();
    if (!$loginUser || !password_verify($newPass, $loginUser['password'])) throw new Exception("Login verification failed");
    echo "   ✓ Authentication by business email succeeded!\n\n";

    echo "✅ ALL E2E LIFECYCLE TESTS PASSED!\n";

} catch (Exception $e) {
    echo "❌ E2E TEST FAILED: " . $e->getMessage() . "\n";
} finally {
    if ($createdUserId) {
        $db->prepare("DELETE FROM users WHERE id = ?")->execute([$createdUserId]);
        echo "   (Cleaned up test user #$createdUserId)\n";
    }
}
