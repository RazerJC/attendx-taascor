<?php
/**
 * Attendance Organization System - Web Interface
 * Executes department organization via web browser
 */

header('Content-Type: text/html; charset=utf-8');

// Get action from request
$action = isset($_GET['action']) ? $_GET['action'] : 'display';

if ($action === 'process') {
    // Execute the Python script
    processAttendanceData();
} else {
    // Show the web interface
    displayInterface();
}

function displayInterface() {
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Attendance Organization System</title>
        <style>
            body {
                font-family: Arial, sans-serif;
                max-width: 900px;
                margin: 50px auto;
                padding: 20px;
                background: #f5f5f5;
            }
            .container {
                background: white;
                padding: 30px;
                border-radius: 8px;
                box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            }
            h1 {
                color: #333;
                border-bottom: 3px solid #007bff;
                padding-bottom: 10px;
            }
            .info-box {
                background: #e7f3ff;
                border-left: 4px solid #2196F3;
                padding: 15px;
                margin: 20px 0;
                border-radius: 4px;
            }
            .button {
                display: inline-block;
                padding: 12px 30px;
                margin: 10px 0;
                background: #007bff;
                color: white;
                text-decoration: none;
                border-radius: 4px;
                border: none;
                font-size: 16px;
                cursor: pointer;
                transition: background 0.3s;
            }
            .button:hover {
                background: #0056b3;
            }
            .button.success {
                background: #28a745;
            }
            .button.success:hover {
                background: #218838;
            }
            .status {
                margin: 20px 0;
                padding: 15px;
                background: #f0f0f0;
                border-radius: 4px;
                font-family: monospace;
                white-space: pre-wrap;
                word-wrap: break-word;
                max-height: 400px;
                overflow-y: auto;
            }
            .requirements {
                background: #fff3cd;
                border-left: 4px solid #ffc107;
                padding: 15px;
                margin: 20px 0;
                border-radius: 4px;
            }
            ul {
                line-height: 1.8;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>✓ Attendance Data Organization System</h1>
            
            <div class="info-box">
                <strong>Purpose:</strong> Organize master attendance file by department
                <br>Automatically creates separate Excel files for each department
            </div>

            <div class="requirements">
                <strong>✓ Status: READY</strong>
                <ul>
                    <li>Master file: coordinator/Master File Bi Chain Mamatid 2026 (1).xlsx</li>
                    <li>System: PHP/SQL ready</li>
                    <li>Time to execute: < 1 minute</li>
                </ul>
            </div>

            <h2>What Will Happen</h2>
            <p>When you click "Process Now":</p>
            <ul>
                <li>✓ Reads the master Excel file</li>
                <li>✓ Identifies all unique departments</li>
                <li>✓ Creates a folder for each department</li>
                <li>✓ Generates Excel file with department data</li>
                <li>✓ Original file remains unchanged (safe!)</li>
            </ul>

            <h2>Execute Now</h2>
            <form method="get">
                <input type="hidden" name="action" value="process">
                <button type="submit" class="button success" onclick="this.disabled=true; this.innerText='Processing...'; return true;">
                    ▶ Process Attendance Data Now
                </button>
            </form>

            <h2>What You'll Get</h2>
            <p>After processing, folder structure will look like:</p>
            <div style="background: #f9f9f9; padding: 15px; border-radius: 4px; font-family: monospace; font-size: 12px;">
                ATTENDANCE/<br>
                ├─ Admin/<br>
                │  └─ Admin_attendance.xlsx<br>
                ├─ Finance/<br>
                │  └─ Finance_attendance.xlsx<br>
                ├─ HR/<br>
                │  └─ HR_attendance.xlsx<br>
                └─ ... (one folder per department)
            </div>

            <div class="info-box" style="margin-top: 30px;">
                <strong>Questions?</strong><br>
                See documentation files in the ATTENDANCE folder:<br>
                • 00_START_HERE_READ_THIS.txt<br>
                • QUICK_REFERENCE.txt<br>
                • README.md
            </div>
        </div>
    </body>
    </html>
    <?php
}

function processAttendanceData() {
    // Change to attendance directory
    $attendanceDir = 'C:\\xampp\\htdocs\\ATTENDANCE';
    $scriptPath = $attendanceDir . '\\organize_by_department.py';
    
    // Check if Python script exists
    if (!file_exists($scriptPath)) {
        $scriptPath = $attendanceDir . '\\process_departments.py';
    }
    
    if (!file_exists($scriptPath)) {
        echo "<!DOCTYPE html><html><head><title>Error</title></head><body>";
        echo "<h1>Error: Python script not found</h1>";
        echo "<p>Could not find: $scriptPath</p>";
        echo "<a href='?action=display'>Back</a>";
        echo "</body></html>";
        return;
    }
    
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Processing Attendance Data</title>
        <style>
            body {
                font-family: Arial, sans-serif;
                max-width: 900px;
                margin: 50px auto;
                padding: 20px;
                background: #f5f5f5;
            }
            .container {
                background: white;
                padding: 30px;
                border-radius: 8px;
                box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            }
            h1 {
                color: #333;
            }
            .status-box {
                background: #f0f0f0;
                border: 1px solid #ddd;
                border-radius: 4px;
                padding: 15px;
                font-family: monospace;
                white-space: pre-wrap;
                word-wrap: break-word;
                max-height: 500px;
                overflow-y: auto;
                line-height: 1.6;
            }
            .success {
                color: #28a745;
                font-weight: bold;
            }
            .button {
                display: inline-block;
                padding: 10px 20px;
                margin-top: 20px;
                background: #007bff;
                color: white;
                text-decoration: none;
                border-radius: 4px;
                cursor: pointer;
            }
            .button:hover {
                background: #0056b3;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>⏳ Processing Attendance Data...</h1>
            <div class="status-box">
    <?php
    
    // Execute Python script
    $output = shell_exec("cd " . escapeshellarg($attendanceDir) . " && python organize_by_department.py 2>&1");
    
    echo htmlspecialchars($output);
    
    if (strpos($output, 'COMPLETE') !== false || strpos($output, 'created') !== false) {
        echo "\n\n<span class='success'>✓ PROCESSING COMPLETE!</span>";
    }
    
    ?>
            </div>
            
            <h2>✓ Processing Finished</h2>
            <p>Check the ATTENDANCE folder for new department folders:</p>
            <ul>
                <li>Look for folders: Admin, Finance, HR, etc.</li>
                <li>Each folder contains: [Department]_attendance.xlsx</li>
                <li>Original master file remains unchanged</li>
            </ul>
            
            <a href="?action=display" class="button">← Back to Main Menu</a>
        </div>
    </body>
    </html>
    <?php
}
?>
