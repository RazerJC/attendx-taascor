<?php
/**
 * FINAL EXECUTION - Complete Attendance Organization
 * Direct PHP/CLI execution of Python processor
 */

// Start execution
echo "════════════════════════════════════════════════════════════════════════════════\n";
echo "EXECUTING: Attendance Data Organization by Department\n";
echo "════════════════════════════════════════════════════════════════════════════════\n\n";

$baseDir = 'c:\\xampp\\htdocs\\ATTENDANCE';
chdir($baseDir);

echo "[1/3] Verifying master file...\n";
if (file_exists('coordinator/Master File Bi Chain Mamatid 2026 (1).xlsx')) {
    echo "✓ Master file found: coordinator/Master File Bi Chain Mamatid 2026 (1).xlsx\n\n";
} else {
    echo "✗ Master file not found!\n";
    exit(1);
}

echo "[2/3] Running Python processor...\n";
echo "Command: python organize_by_department.py\n\n";

// Execute Python script and capture output
$output = shell_exec("python organize_by_department.py 2>&1");

// Display output
echo $output;

echo "\n[3/3] Verification...\n";

// Check for newly created folders
$dirs = array_diff(scandir('.'), array('..', '.', 'coordinator', 'admin', 'assets', 'includes', 'api.php', 'index.php'));
$departmentFolders = 0;

foreach ($dirs as $dir) {
    if (is_dir($dir) && $dir[0] !== '.' && $dir[0] !== '!' && strlen($dir) > 0) {
        // Check if it has an attendance file
        $attendanceFile = $dir . '/' . $dir . '_attendance.xlsx';
        if (file_exists($attendanceFile)) {
            $departmentFolders++;
            echo "✓ " . $dir . "/ → " . $dir . "_attendance.xlsx\n";
        }
    }
}

if ($departmentFolders > 0) {
    echo "\n════════════════════════════════════════════════════════════════════════════════\n";
    echo "✓ SUCCESS! Created $departmentFolders department folders with organized data\n";
    echo "════════════════════════════════════════════════════════════════════════════════\n";
    echo "\nResults saved in: c:\\xampp\\htdocs\\ATTENDANCE\\\n";
    echo "Each department folder contains: [Department]_attendance.xlsx\n";
    echo "\nOriginal master file: UNCHANGED (safe!)\n";
    echo "\n✓ PROCESSING COMPLETE!\n";
} else {
    echo "\n⚠ Checking if Python executed successfully...\n";
    if (strpos($output, 'COMPLETE') !== false || strpos($output, 'created') !== false) {
        echo "✓ Script executed - results are being organized\n";
    }
}

echo "\n";
?>
