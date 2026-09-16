<?php
/**
 * Auth & Security Unit Test — TAASCOR AttendX
 */
require_once __DIR__ . '/../includes/auth.php';

echo "=== Running Auth & Security Unit Tests ===\n\n";

$passed = 0;
$failed = 0;

function assertTest($name, $condition) {
    global $passed, $failed;
    if ($condition) {
        echo "  ✓ PASS: $name\n";
        $passed++;
    } else {
        echo "  ❌ FAIL: $name\n";
        $failed++;
    }
}

// 1. Email validation tests
assertTest("Exact @taascor.com accepted", validateTaascorEmail('user@taascor.com') === true);
assertTest("Case insensitive @taascor.com accepted", validateTaascorEmail('John.Carl@TAASCOR.COM') === true);
assertTest("Non-taascor domain rejected (@gmail.com)", validateTaascorEmail('user@gmail.com') === false);
assertTest("Subdomain rejected (@sub.taascor.com)", validateTaascorEmail('user@sub.taascor.com') === false);
assertTest("Suffix spoof rejected (@nottaascor.com)", validateTaascorEmail('user@nottaascor.com') === false);
assertTest("Invalid email syntax rejected", validateTaascorEmail('user@@taascor.com') === false);
assertTest("Empty email rejected", validateTaascorEmail('') === false);

// 2. Token generation
$token = generateSecureToken();
assertTest("Secure token has 64 hex characters", strlen($token) === 64 && ctype_xdigit($token));

// 3. CSRF token generation and validation
$csrf = getCsrfToken();
assertTest("CSRF token generated", !empty($csrf) && strlen($csrf) === 64);
assertTest("CSRF token validation matches", validateCsrfToken($csrf) === true);
assertTest("Invalid CSRF token rejected", validateCsrfToken('invalid_token_12345') === false);
assertTest("Empty CSRF token rejected", validateCsrfToken('') === false);

// 4. Rate limiting test
$rateLimit = checkLoginRateLimit('test_nonexistent@taascor.com');
assertTest("Rate limit allows initial attempts", $rateLimit['allowed'] === true && $rateLimit['remaining'] > 0);

// 5. Date validation
assertTest("Valid date format accepted", isValidDate('2026-09-14') === true);
assertTest("Invalid date rejected", isValidDate('2026-02-31') === false);
assertTest("Invalid date format rejected", isValidDate('14/09/2026') === false);

echo "\nSummary: $passed passed, $failed failed.\n";
exit($failed > 0 ? 1 : 0);
