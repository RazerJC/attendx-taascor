<?php
/**
 * Import Employee Data from CSV
 * This script imports employee records from employees_import.csv into the database
 */

// Get database connection
require_once 'includes/auth.php';

// Get database configuration from auth.php or create connection
$conn = new mysqli("localhost", "root", "", "taascor_attendance");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set charset to utf8mb4
$conn->set_charset("utf8mb4");

$csvFile = 'employees_import.csv';

if (!file_exists($csvFile)) {
    die("CSV file not found: $csvFile");
}

// Read CSV file
$handle = fopen($csvFile, 'r');
$header = fgetcsv($handle);
$imported = 0;
$skipped = 0;
$errors = array();

// Department mapping based on position
$departmentMap = array(
    'INBOUND' => 'Operations',
    'OUTBOUND' => 'Operations',
    'TL' => 'Operations',
    'INVENTORY' => 'Operations',
    'SHIPPING' => 'Operations',
    'RT OPERATORS' => 'Operations',
    'UTILITY' => 'Operations'
);

echo "<h2>Employee Import Report</h2>";
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>NO</th><th>Name</th><th>Status</th><th>Message</th></tr>";

while (($row = fgetcsv($handle)) !== false) {
    if (empty($row[0]) || $row[0] == 'NO') continue; // Skip header and empty rows
    
    $no = trim($row[0]);
    $dingTalkId = trim($row[1]);
    $fullName = trim($row[2]);
    $dateHired = trim($row[3]);
    $statusVal = trim($row[4]); // ACTIVE, RESIGNED, FOR RTA
    $position = trim($row[5]);
    $birthday = trim($row[6]);
    $idNumber = trim($row[7]);
    $active = trim($row[8]);
    
    if (empty($fullName)) {
        $skipped++;
        echo "<tr><td>$no</td><td>EMPTY</td><td>SKIP</td><td>No name provided</td></tr>";
        continue;
    }
    
    // Split full name into first and last name
    $nameParts = explode(' ', $fullName, 2);
    $firstName = trim($nameParts[0]);
    $lastName = isset($nameParts[1]) ? trim($nameParts[1]) : '';
    
    // Map status
    $employeeStatus = 'active';
    if (strtoupper($statusVal) === 'RESIGNED' || strtoupper($statusVal) === 'FOR RTA') {
        $employeeStatus = 'inactive';
    }
    
    // Get department ID
    $deptName = isset($departmentMap[$position]) ? $departmentMap[$position] : 'Operations';
    $deptResult = $conn->query("SELECT id FROM departments WHERE name = '$deptName'");
    
    if ($deptResult->num_rows == 0) {
        // Create department if it doesn't exist
        $conn->query("INSERT INTO departments (name) VALUES ('$deptName')");
        $departmentId = $conn->insert_id;
    } else {
        $deptRow = $deptResult->fetch_assoc();
        $departmentId = $deptRow['id'];
    }
    
    // Format date hired
    $dateParts = !empty($dateHired) ? explode('/', $dateHired) : array(date('m'), date('d'), date('Y'));
    if (count($dateParts) == 3) {
        $dateHiredFormatted = $dateParts[2] . '-' . str_pad($dateParts[0], 2, '0', STR_PAD_LEFT) . '-' . str_pad($dateParts[1], 2, '0', STR_PAD_LEFT);
    } else {
        $dateHiredFormatted = date('Y-m-d');
    }
    
    // Check if employee already exists
    $checkResult = $conn->query("SELECT id FROM employees WHERE first_name = '$firstName' AND last_name = '$lastName'");
    
    if ($checkResult->num_rows > 0) {
        $skipped++;
        echo "<tr><td>$no</td><td>$fullName</td><td>SKIP</td><td>Employee already exists</td></tr>";
    } else {
        // Insert employee
        $query = "INSERT INTO employees (first_name, last_name, department_id, position, date_hired, status) 
                  VALUES ('$firstName', '$lastName', $departmentId, '$position', '$dateHiredFormatted', '$employeeStatus')";
        
        if ($conn->query($query)) {
            $imported++;
            echo "<tr><td>$no</td><td>$fullName</td><td>SUCCESS</td><td>Imported as $position in $deptName</td></tr>";
        } else {
            $skipped++;
            $errors[] = "Error importing $fullName: " . $conn->error;
            echo "<tr><td>$no</td><td>$fullName</td><td>ERROR</td><td>" . $conn->error . "</td></tr>";
        }
    }
}

echo "</table>";
echo "<hr>";
echo "<p><strong>Import Summary:</strong></p>";
echo "<p>Imported: $imported | Skipped: $skipped</p>";

if (!empty($errors)) {
    echo "<p><strong>Errors:</strong></p>";
    foreach ($errors as $error) {
        echo "<p>$error</p>";
    }
}

fclose($handle);
$conn->close();
?>
