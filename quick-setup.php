<?php
/**
 * Quick database verification and setup
 */

define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'taascor_attendance');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

$output = [];

try {
    // Try to connect to the database
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";charset=" . DB_CHARSET,
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
    
    $output[] = "✓ Connected to MySQL server";
    
    // Create database
    $pdo->exec("CREATE DATABASE IF NOT EXISTS " . DB_NAME . " CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $output[] = "✓ Database created/verified";
    
    // Use the database
    $pdo->exec("USE " . DB_NAME);
    $output[] = "✓ Using database: " . DB_NAME;
    
    // Create tables
    $tables = [
        "departments" => "CREATE TABLE IF NOT EXISTS departments (
            id INT PRIMARY KEY AUTO_INCREMENT,
            name VARCHAR(100) NOT NULL UNIQUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        "users" => "CREATE TABLE IF NOT EXISTS users (
            id INT PRIMARY KEY AUTO_INCREMENT,
            username VARCHAR(50) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            full_name VARCHAR(100) NOT NULL,
            role ENUM('admin', 'coordinator') NOT NULL DEFAULT 'coordinator',
            department_id INT,
            status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        "employees" => "CREATE TABLE IF NOT EXISTS employees (
            id INT PRIMARY KEY AUTO_INCREMENT,
            first_name VARCHAR(50) NOT NULL,
            last_name VARCHAR(50) NOT NULL,
            department_id INT NOT NULL,
            position VARCHAR(100),
            date_hired DATE,
            status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE RESTRICT,
            INDEX idx_department (department_id),
            INDEX idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        "attendance" => "CREATE TABLE IF NOT EXISTS attendance (
            id INT PRIMARY KEY AUTO_INCREMENT,
            employee_id INT NOT NULL,
            date DATE NOT NULL,
            status ENUM('present', 'absent', 'no_work', 'leave', 'sent_home', 'rest_day') NOT NULL,
            recorded_by INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_attendance (employee_id, date),
            FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
            FOREIGN KEY (recorded_by) REFERENCES users(id) ON DELETE SET NULL,
            INDEX idx_date (date),
            INDEX idx_employee (employee_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        "activity_log" => "CREATE TABLE IF NOT EXISTS activity_log (
            id INT PRIMARY KEY AUTO_INCREMENT,
            user_id INT,
            action VARCHAR(100) NOT NULL,
            details TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
            INDEX idx_user (user_id),
            INDEX idx_action (action),
            INDEX idx_date (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    ];
    
    foreach ($tables as $name => $sql) {
        $pdo->exec($sql);
        $output[] = "✓ Table created: $name";
    }
    
    // Insert sample data
    $adminExists = $pdo->query("SELECT id FROM users WHERE username='admin'")->fetch();
    
    if (!$adminExists) {
        $adminPassword = password_hash('admin123', PASSWORD_BCRYPT);
        $stmt = $pdo->prepare("INSERT INTO users (username, password, full_name, role, status) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute(['admin', $adminPassword, 'Administrator', 'admin', 'active']);
        $output[] = "✓ Admin user created (username: admin, password: admin123)";
    } else {
        $output[] = "⚠ Admin user already exists";
    }
    
    // Insert sample departments
    $depts = [
        'Assigned client',
        'PH LAG1',
        'PH LAG2',
        'PH LAG3',
        'PH LAG4',
        'PH LAG5',
        'PH LAG6',
        'PH LAG7',
        'PH LAG8',
        'PH LAG9',
        'PH LAG10',
        'PH LAG11',
        'PHL- BATINO',
        'PHE-A',
        'PHIX-C',
        'MMIX',
        'BC MAMATID',
        'BC SILANGAN',
        'BICANG'
    ];
    $inserted = 0;
    foreach ($depts as $dept) {
        try {
            $stmt = $pdo->prepare("INSERT INTO departments (name) VALUES (?)");
            $stmt->execute([$dept]);
            $inserted++;
        } catch (PDOException $e) {
            // Department might already exist
        }
    }
    $output[] = "✓ Sample departments: " . ($inserted > 0 ? "Added $inserted" : "Already exist");
    
    // Insert sample employees
    $empCount = $pdo->query("SELECT COUNT(*) as cnt FROM employees")->fetch()['cnt'];
    if ($empCount == 0) {
        $deptId = $pdo->query("SELECT id FROM departments LIMIT 1")->fetch()['id'];
        $employees = [
            ['John Carl', 'Bañares', 'Manager'],
            ['Jane', 'Smith', 'Analyst'],
            ['Bob', 'Johnson', 'Officer']
        ];
        foreach ($employees as $emp) {
            $stmt = $pdo->prepare("INSERT INTO employees (first_name, last_name, department_id, position, date_hired) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$emp[0], $emp[1], $deptId, $emp[2], date('Y-m-d')]);
        }
        $output[] = "✓ Sample employees added";
    } else {
        $output[] = "⚠ Employees already exist";
    }
    
    $success = true;
    $message = "Database setup completed successfully!";
    
} catch (PDOException $e) {
    $success = false;
    $output[] = "ERROR: " . $e->getMessage();
    $message = "Database setup failed!";
}

// Return as text or HTML
header('Content-Type: text/plain; charset=utf-8');
echo ($success ? "SUCCESS\n\n" : "FAILED\n\n");
echo implode("\n", $output) . "\n\n";
echo $message . "\n";
exit($success ? 0 : 1);
