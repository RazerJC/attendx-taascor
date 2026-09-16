<?php
/**
 * Database & File Backup — TAASCOR AttendX
 * Admin-only: creates timestamped SQL dump + uploads zip
 * 
 * Usage: Access via browser when logged in as admin, or via CLI.
 */
require_once __DIR__ . '/includes/auth.php';

// CLI mode: allow without session if run from command line
$isCli = (php_sapi_name() === 'cli');
if (!$isCli) {
    requireAdmin();
}

$db = getDB();

// Create backups directory
$backupDir = __DIR__ . '/backups';
if (!is_dir($backupDir)) {
    mkdir($backupDir, 0750, true);
}

// Protect backups directory
$htaccess = $backupDir . '/.htaccess';
if (!file_exists($htaccess)) {
    file_put_contents($htaccess, "Order Deny,Allow\nDeny from all\n");
}

$timestamp = date('Y-m-d_His');
$results = [];
$hasError = false;

// ============================================================
// 1. SQL Database Dump
// ============================================================
$sqlFile = $backupDir . "/backup_{$timestamp}.sql";

try {
    $tables = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    $sql = "-- TAASCOR AttendX Database Backup\n";
    $sql .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
    $sql .= "-- Database: " . DB_NAME . "\n\n";
    $sql .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

    foreach ($tables as $table) {
        // Table structure
        $createStmt = $db->query("SHOW CREATE TABLE `$table`")->fetch();
        $sql .= "-- Table: $table\n";
        $sql .= "DROP TABLE IF EXISTS `$table`;\n";
        $sql .= $createStmt['Create Table'] . ";\n\n";

        // Table data
        $rows = $db->query("SELECT * FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
        if (!empty($rows)) {
            $columns = array_keys($rows[0]);
            $colList = implode('`, `', $columns);

            foreach (array_chunk($rows, 100) as $chunk) {
                $sql .= "INSERT INTO `$table` (`$colList`) VALUES\n";
                $values = [];
                foreach ($chunk as $row) {
                    $vals = [];
                    foreach ($row as $val) {
                        if ($val === null) {
                            $vals[] = 'NULL';
                        } else {
                            $vals[] = $db->quote($val);
                        }
                    }
                    $values[] = '(' . implode(', ', $vals) . ')';
                }
                $sql .= implode(",\n", $values) . ";\n\n";
            }
        }
    }

    $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";

    file_put_contents($sqlFile, $sql);
    $sqlSize = filesize($sqlFile);
    $results[] = ['status' => 'success', 'message' => "SQL dump created: backup_{$timestamp}.sql (" . round($sqlSize / 1024, 1) . " KB)"];
    $results[] = ['status' => 'info', 'message' => "Tables backed up: " . implode(', ', $tables)];

    // Count records per table
    foreach ($tables as $table) {
        $count = $db->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
        $results[] = ['status' => 'info', 'message' => "  → $table: $count rows"];
    }

} catch (Exception $e) {
    $hasError = true;
    $results[] = ['status' => 'error', 'message' => "SQL dump failed: " . $e->getMessage()];
}

// ============================================================
// 2. Uploads Directory Backup
// ============================================================
$uploadsDir = __DIR__ . '/uploads';
$zipFile = $backupDir . "/uploads_{$timestamp}.zip";

if (is_dir($uploadsDir) && class_exists('ZipArchive')) {
    $zip = new ZipArchive();
    if ($zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($uploadsDir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY
        );
        $fileCount = 0;
        foreach ($files as $file) {
            if (!$file->isDir()) {
                $filePath = $file->getRealPath();
                $relativePath = substr($filePath, strlen($uploadsDir) + 1);
                $zip->addFile($filePath, $relativePath);
                $fileCount++;
            }
        }
        $zip->close();
        $zipSize = file_exists($zipFile) ? filesize($zipFile) : 0;
        $results[] = ['status' => 'success', 'message' => "Uploads backup: uploads_{$timestamp}.zip ($fileCount files, " . round($zipSize / 1024, 1) . " KB)"];
    } else {
        $results[] = ['status' => 'error', 'message' => "Failed to create uploads zip"];
        $hasError = true;
    }
} else {
    $results[] = ['status' => 'info', 'message' => "Uploads directory empty or ZipArchive not available — skipped"];
}

// ============================================================
// 3. Verify Backup Integrity
// ============================================================
if (file_exists($sqlFile)) {
    $content = file_get_contents($sqlFile);
    $tableCount = preg_match_all('/CREATE TABLE/i', $content);
    $insertCount = preg_match_all('/INSERT INTO/i', $content);
    $results[] = ['status' => 'success', 'message' => "Backup verification: $tableCount CREATE TABLE, $insertCount INSERT statements"];

    // Verify it contains key tables
    $requiredTables = ['users', 'departments', 'employees', 'attendance', 'activity_log'];
    $missing = [];
    foreach ($requiredTables as $rt) {
        if (stripos($content, "Table: $rt") === false) {
            $missing[] = $rt;
        }
    }
    if (empty($missing)) {
        $results[] = ['status' => 'success', 'message' => "All critical tables present in backup"];
    } else {
        $results[] = ['status' => 'error', 'message' => "Missing tables in backup: " . implode(', ', $missing)];
        $hasError = true;
    }
}

// Log activity
if (!$isCli && isLoggedIn()) {
    logActivity('Database Backup', "Created backup: backup_{$timestamp}.sql" . ($hasError ? ' (with errors)' : ''));
}

// ============================================================
// Output
// ============================================================
if ($isCli) {
    echo "\n=== TAASCOR AttendX Database Backup ===\n\n";
    foreach ($results as $r) {
        $icon = $r['status'] === 'success' ? '✓' : ($r['status'] === 'error' ? '✗' : 'ℹ');
        echo "$icon {$r['message']}\n";
    }
    echo "\n" . ($hasError ? "⚠ Backup completed with errors\n" : "✅ Backup completed successfully\n");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Backup — AttendX</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:'Inter',sans-serif; background:#0d0e12; color:#fff; min-height:100vh; display:flex; align-items:center; justify-content:center; padding:20px; }
        .card { background:rgba(255,255,255,0.06); border:1px solid rgba(255,255,255,0.1); border-radius:20px; padding:32px; max-width:600px; width:100%; }
        h2 { font-size:20px; margin-bottom:20px; text-align:center; }
        .result { padding:10px 14px; border-radius:10px; margin-bottom:6px; font-size:12px; border:1px solid; font-family:monospace; }
        .result.success { background:rgba(34,197,94,0.1); border-color:rgba(34,197,94,0.2); color:#4ade80; }
        .result.error { background:rgba(239,68,68,0.1); border-color:rgba(239,68,68,0.2); color:#f87171; }
        .result.info { background:rgba(59,130,246,0.08); border-color:rgba(59,130,246,0.15); color:#60a5fa; }
        a.btn { display:inline-block; margin-top:16px; padding:12px 24px; background:linear-gradient(to right,#20c997,#12b886); color:#fff; text-decoration:none; border-radius:12px; font-weight:600; font-size:14px; text-align:center; width:100%; }
    </style>
</head>
<body>
    <div class="card">
        <h2><?= $hasError ? '⚠️ Backup Completed With Warnings' : '✅ Backup Complete' ?></h2>
        <?php foreach ($results as $r): ?>
        <div class="result <?= $r['status'] ?>">
            <?= $r['status'] === 'success' ? '✓' : ($r['status'] === 'error' ? '✗' : 'ℹ') ?>
            <?= htmlspecialchars($r['message']) ?>
        </div>
        <?php endforeach; ?>
        <a href="/ATTENDANCE/admin/dashboard.php" class="btn">← Back to Dashboard</a>
    </div>
</body>
</html>
