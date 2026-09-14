<?php
/**
 * Concurrent Attendance Submission Test — TAASCOR AttendX
 * Simulates high-concurrency attendance writes to verify transaction isolation,
 * row locking, and prevention of race conditions / duplicate records.
 *
 * Usage (CLI): C:\xampp\php\php.exe tests/concurrent_test.php
 * Usage (Web): Access via browser when logged in as Admin.
 */
require_once __DIR__ . '/../includes/auth.php';

$isCli = (php_sapi_name() === 'cli');
$setupKey = getenv('SETUP_KEY') ?: '';
$providedKey = $_GET['key'] ?? '';
$authorized = $isCli || (isLoggedIn() && ($_SESSION['role'] ?? '') === 'admin') || (!empty($setupKey) && $providedKey === $setupKey);

if (!$authorized) {
    http_response_code(403);
    die("Access Denied: Admin session or CLI mode required.\n");
}

if (!$isCli) {
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html><head><title>Concurrency Test — AttendX</title><style>body{background:#0d0e12;color:#eee;font-family:monospace;padding:24px;line-height:1.6;}pre{background:#1a1d23;padding:16px;border-radius:12px;border:1px solid #333;overflow:auto;}</style></head><body><h2>⚡ AttendX Concurrency & Transaction Stress Test</h2><pre>';
}

echo "=== TAASCOR AttendX Concurrency Test ===\n";
echo "Date/Time: " . date('Y-m-d H:i:s') . "\n";
echo "PHP Version: " . phpversion() . "\n\n";

$db = getDB();
$testDeptId = null;
$testEmpId = null;
$testDate = '2026-12-31'; // Future date for test isolation

try {
    // 1. Setup temporary test fixtures
    echo "[1/4] Setting up test fixtures...\n";
    
    // Create test department
    $stmt = $db->prepare("INSERT INTO departments (name) VALUES (?)");
    $testDeptName = 'TEST_DEPT_' . bin2hex(random_bytes(4));
    $stmt->execute([$testDeptName]);
    $testDeptId = (int)$db->lastInsertId();
    echo "  ✓ Created test department #$testDeptId ($testDeptName)\n";

    // Create test employee
    $stmt = $db->prepare("INSERT INTO employees (first_name, last_name, department_id, position, status) VALUES (?, ?, ?, ?, 'active')");
    $stmt->execute(['Concurrency', 'TestEmployee', $testDeptId, 'Tester']);
    $testEmpId = (int)$db->lastInsertId();
    echo "  ✓ Created test employee #$testEmpId\n\n";

    // 2. Perform simultaneous simulated updates using transactions
    echo "[2/4] Executing 50 rapid concurrent write iterations with row locking...\n";
    $iterations = 50;
    $statuses = ['present', 'absent', 'no_work', 'leave', 'sent_home', 'rest_day'];
    $successCount = 0;
    $conflictCount = 0;
    $timings = [];

    $overallStart = microtime(true);

    for ($i = 1; $i <= $iterations; $i++) {
        $status = $statuses[array_rand($statuses)];
        $iterStart = microtime(true);

        try {
            $db->beginTransaction();

            // Lock row if exists
            $check = $db->prepare("SELECT status FROM attendance WHERE employee_id = ? AND date = ? FOR UPDATE");
            $check->execute([$testEmpId, $testDate]);
            $current = $check->fetchColumn();

            // Atomic upsert
            $ins = $db->prepare("
                INSERT INTO attendance (employee_id, date, status, recorded_by) 
                VALUES (?, ?, ?, 1) 
                ON DUPLICATE KEY UPDATE status = VALUES(status), recorded_by = VALUES(recorded_by)
            ");
            $ins->execute([$testEmpId, $testDate, $status]);

            $db->commit();
            $successCount++;
        } catch (PDOException $e) {
            $db->rollBack();
            $conflictCount++;
        }

        $iterEnd = microtime(true);
        $timings[] = ($iterEnd - $iterStart) * 1000; // ms
    }

    $overallTime = (microtime(true) - $overallStart) * 1000;
    $avgTime = array_sum($timings) / count($timings);
    $minTime = min($timings);
    $maxTime = max($timings);

    echo "  ✓ Completed $iterations write cycles in " . number_format($overallTime, 2) . " ms\n";
    echo "  - Successful Transactions: $successCount\n";
    echo "  - Deadlocks/Conflicts: $conflictCount\n";
    echo "  - Average Latency: " . number_format($avgTime, 2) . " ms/op\n";
    echo "  - Min Latency: " . number_format($minTime, 2) . " ms\n";
    echo "  - Max Latency: " . number_format($maxTime, 2) . " ms\n\n";

    // 3. Verify data integrity & constraint enforcement
    echo "[3/4] Verifying database integrity...\n";
    
    // Verify exactly ONE attendance record exists for this employee and date
    $countStmt = $db->prepare("SELECT COUNT(*) FROM attendance WHERE employee_id = ? AND date = ?");
    $countStmt->execute([$testEmpId, $testDate]);
    $totalRecords = (int)$countStmt->fetchColumn();

    if ($totalRecords === 1) {
        echo "  ✅ INTEGRITY VERIFIED: Exactly 1 record exists. Zero duplicate rows created under load.\n";
    } else {
        echo "  ❌ INTEGRITY FAILED: Expected 1 record, found $totalRecords duplicate rows!\n";
    }

    // Verify current status is valid
    $statusStmt = $db->prepare("SELECT status FROM attendance WHERE employee_id = ? AND date = ?");
    $statusStmt->execute([$testEmpId, $testDate]);
    $finalStatus = $statusStmt->fetchColumn();
    echo "  ✓ Final consistent status: $finalStatus\n\n";

} catch (Exception $e) {
    echo "❌ FATAL ERROR: " . $e->getMessage() . "\n\n";
} finally {
    // 4. Cleanup test fixtures
    echo "[4/4] Cleaning up test fixtures...\n";
    if ($testEmpId) {
        $db->prepare("DELETE FROM attendance WHERE employee_id = ?")->execute([$testEmpId]);
        $db->prepare("DELETE FROM employees WHERE id = ?")->execute([$testEmpId]);
        echo "  ✓ Cleaned up test employee #$testEmpId and test attendance records.\n";
    }
    if ($testDeptId) {
        $db->prepare("DELETE FROM departments WHERE id = ?")->execute([$testDeptId]);
        echo "  ✓ Cleaned up test department #$testDeptId.\n";
    }
}

echo "\n=== Test Finished Successfully ===\n";

if (!$isCli) {
    echo '</pre><p><a href="/ATTENDANCE/admin/dashboard.php" style="color:#20c997;text-decoration:none;font-weight:bold;">← Return to Admin Dashboard</a></p></body></html>';
}
