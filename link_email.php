<?php
/**
 * Link Business Email — TAASCOR AttendX
 * Allows existing accounts (coordinators and admins) to link and verify their @taascor.com business email.
 */
require_once __DIR__ . '/includes/auth.php';
requireLogin();

$user = currentUser();
$db = getDB();

// Fetch fresh user record from DB
$stmt = $db->prepare("SELECT id, username, email, email_verified, email_verification_expires, full_name, role FROM users WHERE id = ?");
$stmt->execute([$user['id']]);
$userData = $stmt->fetch();

$error = '';
$message = '';
$messageType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid or expired session. Please refresh and try again.';
    } else {
        $action = $_POST['action'] ?? 'link';

        if ($action === 'resend') {
            // Resend verification email to existing unverified email
            if (empty($userData['email'])) {
                $error = 'No email address registered to verify.';
            } else {
                $token = generateSecureToken();
                try {
                    $upd = $db->prepare("
                        UPDATE users 
                        SET email_verification_token = ?, 
                            email_verification_expires = DATE_ADD(NOW(), INTERVAL 24 HOUR) 
                        WHERE id = ?
                    ");
                    $upd->execute([$token, $userData['id']]);

                    $res = sendVerificationEmail($userData['email'], $token, $userData['full_name']);
                    if ($res['success']) {
                        $message = '✅ A fresh verification link has been sent to ' . htmlspecialchars($userData['email']) . '. Please check your inbox.';
                        $messageType = 'success';
                    } else {
                        $message = '⚠️ Verification email could not be delivered automatically (' . htmlspecialchars($res['message']) . '). Please contact the Administrator.';
                        $messageType = 'warning';
                    }
                } catch (PDOException $e) {
                    $error = 'Failed to generate verification link. Please try again.';
                }
            }
        } elseif ($action === 'link') {
            $newEmail = strtolower(trim($_POST['email'] ?? ''));

            if (empty($newEmail)) {
                $error = 'Please enter your @taascor.com business email.';
            } elseif (!validateTaascorEmail($newEmail)) {
                $error = 'Invalid email domain. Only official @taascor.com business email addresses are accepted.';
            } else {
                // Check if email is already taken by another account
                $dupStmt = $db->prepare("SELECT id, username FROM users WHERE email = ? AND id != ?");
                $dupStmt->execute([$newEmail, $userData['id']]);
                if ($dupStmt->fetch()) {
                    $error = 'This business email is already linked to another account.';
                } else {
                    $token = generateSecureToken();
                    try {
                        $upd = $db->prepare("
                            UPDATE users 
                            SET email = ?, 
                                email_verified = 0, 
                                email_verification_token = ?, 
                                email_verification_expires = DATE_ADD(NOW(), INTERVAL 24 HOUR) 
                            WHERE id = ?
                        ");
                        $upd->execute([$newEmail, $token, $userData['id']]);

                        // Update session email
                        $_SESSION['email'] = $newEmail;

                        // Send verification email
                        $res = sendVerificationEmail($newEmail, $token, $userData['full_name']);
                        if ($res['success']) {
                            $message = '✅ Business email updated! We sent a verification link to ' . htmlspecialchars($newEmail) . '. Please click the link in your inbox to complete verification.';
                            $messageType = 'success';
                        } else {
                            $message = '⚠️ Business email saved! However, the automated verification email failed (' . htmlspecialchars($res['message']) . '). Please contact your Administrator to verify your email manually.';
                            $messageType = 'warning';
                        }

                        // Refresh user data
                        $stmt->execute([$user['id']]);
                        $userData = $stmt->fetch();
                    } catch (PDOException $e) {
                        error_log("Email link failed: " . $e->getMessage());
                        $error = 'Database error while linking email. Please try again.';
                    }
                }
            }
        }
    }
}

$pageTitle = 'Business Email Settings';
require_once __DIR__ . '/includes/header.php';
?>

<div class="p-6 md:p-8 max-w-4xl mx-auto">
    <!-- Header -->
    <div class="mb-8 flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-white tracking-tight flex items-center gap-3">
                <span class="w-10 h-10 rounded-xl bg-primary-500/10 border border-primary-500/20 flex items-center justify-center text-primary-400">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                </span>
                Business Email Settings
            </h1>
            <p class="text-xs text-gray-400 mt-1">Manage and verify your official @taascor.com business email</p>
        </div>
        <a href="<?= $user['role'] === 'admin' ? '/ATTENDANCE/admin/dashboard.php' : '/ATTENDANCE/coordinator/dashboard.php' ?>" class="px-4 py-2 bg-dark-700 hover:bg-dark-600 border border-white/10 rounded-xl text-xs font-semibold text-gray-300 transition-colors">
            ← Back to Dashboard
        </a>
    </div>

    <!-- Feedback messages -->
    <?php if ($error): ?>
    <div class="mb-6 p-4 bg-red-500/10 border border-red-500/20 rounded-2xl text-red-400 text-xs font-semibold">
        <?= htmlspecialchars($error) ?>
    </div>
    <?php endif; ?>

    <?php if ($message): ?>
    <div class="mb-6 p-4 <?= $messageType === 'success' ? 'bg-green-500/10 border-green-500/20 text-green-400' : 'bg-amber-500/10 border-amber-500/20 text-amber-300' ?> border rounded-2xl text-xs font-semibold leading-relaxed">
        <?= $message ?>
    </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <!-- Status Card -->
        <div class="md:col-span-1 bg-dark-800 border border-glassBorder rounded-2xl p-6 flex flex-col justify-between">
            <div>
                <h3 class="text-xs font-bold uppercase tracking-wider text-gray-400 mb-4">Current Status</h3>
                
                <?php if (!empty($userData['email'])): ?>
                    <div class="mb-3">
                        <div class="text-[10px] uppercase tracking-wider text-gray-500">Registered Email</div>
                        <div class="text-sm font-semibold text-white break-all mt-0.5">
                            <?= htmlspecialchars($userData['email']) ?>
                        </div>
                    </div>

                    <?php if (!empty($userData['email_verified'])): ?>
                        <div class="inline-flex items-center gap-1.5 px-3 py-1 bg-green-500/10 border border-green-500/30 rounded-full text-green-400 text-xs font-bold">
                            <span>✓</span> Verified @taascor.com
                        </div>
                        <p class="text-[11px] text-gray-500 mt-3 leading-relaxed">
                            Your business email is verified. You can use it to sign in and recover your password anytime.
                        </p>
                    <?php else: ?>
                        <div class="inline-flex items-center gap-1.5 px-3 py-1 bg-amber-500/10 border border-amber-500/30 rounded-full text-amber-400 text-xs font-bold">
                            <span>⏳</span> Pending Verification
                        </div>
                        <p class="text-[11px] text-amber-400/80 mt-3 leading-relaxed">
                            A verification link was sent to your inbox. Please click it to verify.
                        </p>

                        <form method="POST" class="mt-4">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="resend">
                            <button type="submit" class="w-full py-2 bg-amber-500/20 hover:bg-amber-500/30 text-amber-300 border border-amber-500/30 rounded-xl text-xs font-bold transition-all">
                                Resend Verification Link
                            </button>
                        </form>
                    <?php endif; ?>

                <?php else: ?>
                    <div class="inline-flex items-center gap-1.5 px-3 py-1 bg-gray-500/10 border border-gray-500/30 rounded-full text-gray-400 text-xs font-bold">
                        <span>✕</span> Not Linked
                    </div>
                    <p class="text-[11px] text-gray-500 mt-3 leading-relaxed">
                        No business email is linked to this account yet. Link your @taascor.com mailbox below.
                    </p>
                <?php endif; ?>
            </div>

            <div class="pt-6 border-t border-glassBorder mt-6">
                <div class="text-[10px] uppercase tracking-wider text-gray-500">Username Identifier</div>
                <div class="text-xs font-medium text-gray-300 mt-0.5">
                    @<?= htmlspecialchars($userData['username']) ?>
                </div>
            </div>
        </div>

        <!-- Form Card -->
        <div class="md:col-span-2 bg-dark-800 border border-glassBorder rounded-2xl p-6">
            <h3 class="text-sm font-bold text-white mb-1">
                <?= empty($userData['email']) ? 'Link Your Business Email' : 'Update Business Email' ?>
            </h3>
            <p class="text-xs text-gray-400 mb-6">
                Enter your official @taascor.com business email hosted on Hostinger. A verification email will be dispatched.
            </p>

            <form method="POST" class="space-y-4">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="link">

                <div>
                    <label class="block text-[10px] font-semibold text-gray-400 mb-1.5 uppercase tracking-wider">
                        Business Email <span class="text-primary-400 font-normal">(@taascor.com)</span>
                    </label>
                    <input type="email" name="email" id="emailInput" required 
                           value="<?= htmlspecialchars($userData['email'] ?? '') ?>"
                           class="w-full px-4 py-2.5 bg-dark-700/50 border border-white/10 rounded-xl text-white placeholder-gray-600 focus:outline-none focus:border-primary-500/50 focus:ring-2 focus:ring-primary-500/20 transition-all text-xs"
                           placeholder="yourname@taascor.com">
                    <p id="emailValidationMsg" class="text-[10px] text-gray-500 mt-1">Must strictly end with @taascor.com</p>
                </div>

                <div class="p-3 bg-dark-700/30 border border-white/5 rounded-xl">
                    <p class="text-[10px] text-gray-400 leading-relaxed">
                        🔒 <strong class="text-gray-300">Security Note:</strong> Changing your business email will require re-verifying the new address before it can be used for login or password recovery.
                    </p>
                </div>

                <button type="submit"
                        class="py-2.5 px-6 bg-gradient-to-r from-primary-500 to-primary-600 hover:from-primary-400 hover:to-primary-500 text-white font-bold rounded-xl shadow-lg shadow-primary-900/40 transition-all text-xs uppercase tracking-wider">
                    <?= empty($userData['email']) ? 'Link Business Email' : 'Update & Send Verification' ?>
                </button>
            </form>
        </div>
    </div>
</div>

<script>
    const emailInput = document.getElementById('emailInput');
    const msg = document.getElementById('emailValidationMsg');
    if (emailInput) {
        emailInput.addEventListener('input', function() {
            const val = this.value.trim().toLowerCase();
            if (val.length > 0 && !val.endsWith('@taascor.com')) {
                msg.textContent = '⚠️ Must end with @taascor.com';
                msg.className = 'text-[10px] text-amber-400 mt-1';
            } else if (val.endsWith('@taascor.com')) {
                msg.textContent = '✓ Valid TAASCOR business domain';
                msg.className = 'text-[10px] text-primary-400 mt-1';
            } else {
                msg.textContent = 'Must strictly end with @taascor.com';
                msg.className = 'text-[10px] text-gray-500 mt-1';
            }
        });
    }
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
