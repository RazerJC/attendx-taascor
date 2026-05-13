<?php
/**
 * Complete Database Reset - Drop all tables and recreate
 */

define('DB_HOST', 'localhost');
define('DB_NAME', 'taascor_attendance');
define('DB_USER', 'root');
define('DB_PASS', '');

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "Starting complete database reset...\n\n";
    
    // Disable foreign key checks
    $pdo->exec("SET FOREIGN_KEY_CHECKS=0");
    
    // Drop all tables
    $tables = $pdo->query("SHOW TABLES")->fetchAll();
    foreach ($tables as $table) {
        $tableName = array_values($table)[0];
        $pdo->exec("DROP TABLE IF EXISTS `$tableName`");
        echo "✓ Dropped table: $tableName\n";
    }
    
    echo "\nCreating fresh tables...\n\n";
    
    // Create departments
    $pdo->exec("
    CREATE TABLE departments (
        id INT PRIMARY KEY AUTO_INCREMENT,
        name VARCHAR(100) NOT NULL UNIQUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "✓ Created: departments\n";
    
    // Create users
    $pdo->exec("
    CREATE TABLE users (
        id INT PRIMARY KEY AUTO_INCREMENT,
        username VARCHAR(50) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        full_name VARCHAR(100) NOT NULL,
        role ENUM('admin', 'coordinator') NOT NULL DEFAULT 'coordinator',
        department_id INT NULL,
        status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "✓ Created: users\n";
    
    // Create employees
    $pdo->exec("
    CREATE TABLE employees (
        id INT PRIMARY KEY AUTO_INCREMENT,
        first_name VARCHAR(50) NOT NULL,
        last_name VARCHAR(50) NOT NULL,
        department_id INT NOT NULL,
        position VARCHAR(100),
        date_hired DATE,
        status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE RESTRICT,
        INDEX idx_department (department_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "✓ Created: employees\n";
    
    // Create attendance with ALL required columns
    $pdo->exec("
    CREATE TABLE attendance (
        id INT PRIMARY KEY AUTO_INCREMENT,
        employee_id INT NOT NULL,
        date DATE NOT NULL,
        status ENUM('present', 'absent', 'no_work', 'leave', 'sent_home', 'rest_day') NOT NULL DEFAULT 'present',
        recorded_by INT,
        is_late BOOLEAN DEFAULT FALSE,
        late_minutes INT DEFAULT 0,
        is_undertime BOOLEAN DEFAULT FALSE,
        undertime_minutes INT DEFAULT 0,
        ot_hours DECIMAL(5,2) DEFAULT 0,
        total_hours DECIMAL(5,2) DEFAULT 0,
        time_in TIME,
        time_out TIME,
        ot_start TIME,
        ot_end TIME,
        remarks TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY unique_attendance (employee_id, date),
        FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
        FOREIGN KEY (recorded_by) REFERENCES users(id) ON DELETE SET NULL,
        INDEX idx_date (date),
        INDEX idx_employee (employee_id),
        INDEX idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "✓ Created: attendance\n";
    
    // Create activity log
    $pdo->exec("
    CREATE TABLE activity_log (
        id INT PRIMARY KEY AUTO_INCREMENT,
        user_id INT,
        action VARCHAR(100) NOT NULL,
        details TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
        INDEX idx_user (user_id),
        INDEX idx_action (action),
        INDEX idx_date (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "✓ Created: activity_log\n";
    
    // Re-enable foreign key checks
    $pdo->exec("SET FOREIGN_KEY_CHECKS=1");
    
    echo "\nInserting sample data...\n\n";
    
    // Insert departments
    $departments = ['Human Resources', 'Finance', 'Operations', 'Sales', 'Marketing'];
    foreach ($departments as $dept) {
        $pdo->exec("INSERT INTO departments (name) VALUES ('$dept')");
    }
    echo "✓ Added 5 departments\n";
    
    // Create admin user
    $adminPassword = password_hash('admin123', PASSWORD_BCRYPT);
    $stmt = $pdo->prepare("INSERT INTO users (username, password, full_name, role, status) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute(['admin', $adminPassword, 'Administrator', 'admin', 'active']);
    echo "✓ Created admin user\n";
    
    // Insert sample employees
    $employees = [
        ['John', 'Doe', 1, 'Manager', '2023-01-15'],
        ['Jane', 'Smith', 2, 'Analyst', '2023-02-20'],
        ['Bob', 'Johnson', 3, 'Officer', '2023-03-10'],
        ['Alice', 'Williams', 4, 'Manager', '2023-04-05'],
        ['Charlie', 'Brown', 5, 'Specialist', '2023-05-12']
    ];
    foreach ($employees as $emp) {
        $stmt = $pdo->prepare("INSERT INTO employees (first_name, last_name, department_id, position, date_hired) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute($emp);
    }
    echo "✓ Added 5 sample employees\n";
    
    // Insert sample attendance records
    $admin_id = $pdo->query("SELECT id FROM users WHERE username='admin'")->fetch()['id'];
    $employees_list = $pdo->query("SELECT id FROM employees LIMIT 5")->fetchAll();
    
    $statuses = ['present', 'absent', 'leave'];
    $count = 0;
    foreach ($employees_list as $emp) {
        for ($i = 0; $i < 30; $i++) {
            $date = date('Y-m-d', strtotime("-$i days"));
            $status = $statuses[array_rand($statuses)];
            $stmt = $pdo->prepare("INSERT INTO attendance (employee_id, date, status, recorded_by) VALUES (?, ?, ?, ?)");
            $stmt->execute([$emp['id'], $date, $status, $admin_id]);
            $count++;
        }
    }
    echo "✓ Added $count sample attendance records\n";
    
    echo "\n✅ DATABASE COMPLETELY RESET AND READY!\n\n";
    echo "LOGIN CREDENTIALS:\n";
    echo "Username: admin\n";
    echo "Password: admin123\n\n";
    echo "Go to: http://localhost/ATTENDANCE/\n";
    
} catch (PDOException $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n";
    echo $e->getTraceAsString();
}
?>
