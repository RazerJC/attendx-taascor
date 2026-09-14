<?php
/**
 * Import Employee Data from Mamatid Excel CSV
 */

require_once __DIR__ . '/includes/auth.php';
$db = getDB();

$csvFile = __DIR__ . '/coordinator/employees_mamatid.csv';

if (!file_exists($csvFile)) {
    die("CSV file not found: $csvFile\n");
}

$handle = fopen($csvFile, 'r');
$header = fgetcsv($handle);
$imported = 0;
$skipped = 0;
$errors = [];

echo "Starting employee import for BC MAMATID (Department ID 17)...\n\n";

while (($row = fgetcsv($handle)) !== false) {
    if (empty($row[2])) continue; // Skip if no name
    
    $no = trim($row[0]);
    $dingTalkId = trim($row[1]);
    $fullName = trim($row[2]);
    $dateHired = trim($row[3]);
    $statusVal = trim($row[4]); // ACTIVE, RESIGNED, FOR RTA
    $position = trim($row[5]);
    $birthday = trim($row[6]);
    $idNumber = trim($row[7]);
    $active = trim($row[8]);
    
    // Split name
    $nameParts = explode(' ', $fullName, 2);
    $firstName = trim($nameParts[0]);
    $lastName = isset($nameParts[1]) ? trim($nameParts[1]) : '';
    
    // Map status
    $employeeStatus = 'active';
    if (strtoupper($statusVal) === 'RESIGNED' || strtoupper($statusVal) === 'FOR RTA') {
        $employeeStatus = 'inactive';
    }
    
    // Department ID is 17 (BC MAMATID)
    $departmentId = 17;
    
    // Format date hired
    $dateHiredFormatted = null;
    if (!empty($dateHired)) {
        $dateParts = explode('/', $dateHired);
        if (count($dateParts) == 3) {
            $dateHiredFormatted = $dateParts[2] . '-' . str_pad($dateParts[0], 2, '0', STR_PAD_LEFT) . '-' . str_pad($dateParts[1], 2, '0', STR_PAD_LEFT);
        } else {
            $dateHiredFormatted = date('Y-m-d');
        }
    } else {
        $dateHiredFormatted = date('Y-m-d');
    }
    
    // Check if employee already exists in department 17
    $check = $db->prepare("SELECT id FROM employees WHERE first_name = ? AND last_name = ? AND department_id = 17");
    $check->execute([$firstName, $lastName]);
    if ($check->fetch()) {
        $skipped++;
        echo "Skipped: $fullName (already exists)\n";
    } else {
        // Insert employee
        $stmt = $db->prepare("INSERT INTO employees (first_name, last_name, department_id, position, date_hired, status) VALUES (?, ?, ?, ?, ?, ?)");
        if ($stmt->execute([$firstName, $lastName, $departmentId, $position, $dateHiredFormatted, $employeeStatus])) {
            $imported++;
            echo "Imported: $fullName as $position (Status: $employeeStatus)\n";
        } else {
            $errors[] = "Error importing $fullName";
            echo "Error: Failed to import $fullName\n";
        }
    }
}

fclose($handle);

echo "\nImport Summary:\n";
echo "Imported: $imported\n";
echo "Skipped: $skipped\n";
echo "Errors: " . count($errors) . "\n";
