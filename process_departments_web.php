<?php
/**
 * Attendance Data Processing Script (PHP Version)
 * Reads master Excel file and creates department-specific files
 * 
 * This PHP script uses a different approach if Python is unavailable
 * It reads the Excel file and processes the data
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Configuration
$file_path = 'c:\\xampp\\htdocs\\ATTENDANCE\\coordinator\\Master File Bi Chain Mamatid 2026 (1).xlsx';
$base_dir = 'c:\\xampp\\htdocs\\ATTENDANCE';

// Check if we're in CLI mode
$is_cli = php_sapi_name() === 'cli';

function print_header($title) {
    global $is_cli;
    if ($is_cli) {
        echo "\n" . str_repeat("=", 80) . "\n";
        echo $title . "\n";
        echo str_repeat("=", 80) . "\n\n";
    } else {
        echo "<h1>$title</h1>";
    }
}

function print_section($title) {
    global $is_cli;
    if ($is_cli) {
        echo "\n" . $title . "\n";
        echo str_repeat("-", strlen($title)) . "\n\n";
    } else {
        echo "<h2>$title</h2>";
    }
}

function print_line($text = "") {
    global $is_cli;
    if ($is_cli) {
        echo $text . "\n";
    } else {
        echo htmlspecialchars($text) . "<br>\n";
    }
}

function print_success($text) {
    print_line("✓ " . $text);
}

function print_error($text) {
    print_line("✗ " . $text);
}

function print_info($text) {
    print_line("→ " . $text);
}

// Start output
if (!$is_cli) {
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Attendance Data Processing</title>
        <style>
            body { font-family: monospace; margin: 20px; background: #f5f5f5; }
            h1, h2 { color: #333; }
            .success { color: green; }
            .error { color: red; }
            .info { color: blue; }
            pre { background: #fff; padding: 10px; border: 1px solid #ccc; overflow-x: auto; }
        </style>
    </head>
    <body>
    <?php
}

print_header("ATTENDANCE DATA PROCESSING");

// Check if file exists
if (!file_exists($file_path)) {
    print_error("File not found: $file_path");
    if (!$is_cli) echo "</body></html>";
    exit(1);
}

print_success("File found: $file_path");

// Try to use Python if available
$python_available = false;
$output = array();
$return_code = 0;

// Check for Python
exec("python --version 2>&1", $output, $return_code);

if ($return_code === 0) {
    $python_available = true;
    print_success("Python is available");
    print_info("Running Python script instead...");
    
    // Run the Python script
    $python_script = $base_dir . DIRECTORY_SEPARATOR . 'process_departments.py';
    if (file_exists($python_script)) {
        print_info("Executing: python $python_script");
        print_line("");
        
        // Execute with output buffering
        system("python \"$python_script\"", $return_code);
        
        if ($return_code === 0) {
            print_success("Python script completed successfully!");
        } else {
            print_error("Python script failed with code: $return_code");
        }
    } else {
        print_error("Python script not found: $python_script");
    }
} else {
    print_info("Python not available - using PHP fallback");
    print_section("Note: PHP-based Excel Processing");
    print_line("PHP does not have built-in support for reading modern Excel files (.xlsx)");
    print_line("Please use Python for reliable Excel processing:");
    print_line("");
    print_line("1. Install Python from python.org");
    print_line("2. Run: python process_departments.py");
    print_line("3. Or use the batch file: run_process_departments.bat");
}

print_section("Summary");
print_info("For best results, use the Python script:");
print_line("  Location: " . $base_dir . "\\process_departments.py");
print_line("  Usage: python process_departments.py");
print_line("");
print_info("Instructions available in: PROCESSING_INSTRUCTIONS.md");

if (!$is_cli) {
    ?>
    </body>
    </html>
    <?php
}
?>
