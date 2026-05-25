<?php
/**
 * Direct Database Setup & Verification
 */

define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'taascor_attendance');
define('DB_USER', 'root');
define('DB_PASS', '');

try {
    // Connect to MySQL
    $pdo = new PDO("mysql:host=" . DB_HOST, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Create database
    $pdo->exec("CREATE DATABASE IF NOT EXISTS " . DB_NAME . " CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE " . DB_NAME);
    
    // Create all tables
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS departments (
        id INT PRIMARY KEY AUTO_INCREMENT,
        name VARCHAR(100) NOT NULL UNIQUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS users (
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
    
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS employees (
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
    
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS attendance (
        id INT PRIMARY KEY AUTO_INCREMENT,
        employee_id INT NOT NULL,
        date DATE NOT NULL,
        status ENUM('present', 'absent', 'no_work', 'leave', 'sent_home', 'rest_day') NOT NULL,
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
        UNIQUE KEY unique_attendance (employee_id, date),
        FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
        FOREIGN KEY (recorded_by) REFERENCES users(id) ON DELETE SET NULL,
        INDEX idx_date (date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS activity_log (
        id INT PRIMARY KEY AUTO_INCREMENT,
        user_id INT,
        action VARCHAR(100) NOT NULL,
        details TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
        INDEX idx_user (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    
    // Disable foreign key checks temporarily
    $pdo->exec("SET FOREIGN_KEY_CHECKS=0");
    
    // Clear existing data
    $pdo->exec("DELETE FROM activity_log");
    $pdo->exec("DELETE FROM attendance");
    $pdo->exec("DELETE FROM employees");
    $pdo->exec("DELETE FROM users");
    $pdo->exec("DELETE FROM departments");
    
    // Re-enable foreign key checks
    $pdo->exec("SET FOREIGN_KEY_CHECKS=1");
    
    // Insert default departments
    $pdo->exec("INSERT INTO departments (name) VALUES ('Human Resources'), ('Finance'), ('Operations'), ('Sales'), ('Marketing')");
    
    // Create admin user with proper password hash
    $adminPassword = password_hash('admin123', PASSWORD_BCRYPT);
    $stmt = $pdo->prepare("INSERT INTO users (username, password, full_name, role, status) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute(['admin', $adminPassword, 'Administrator', 'admin', 'active']);
    
    // Insert sample employees
    $pdo->exec("DELETE FROM employees");
    $pdo->exec("INSERT INTO employees (first_name, last_name, department_id, position, date_hired, status) 
               VALUES ('John', 'Doe', 1, 'Manager', '2023-01-15', 'active'),
                      ('Jane', 'Smith', 2, 'Analyst', '2023-02-20', 'active'),
                      ('Bob', 'Johnson', 3, 'Officer', '2023-03-10', 'active')");
    
    // Verify admin user was created
    $check = $pdo->query("SELECT id, username, full_name FROM users WHERE username='admin'")->fetch();
    
    echo "✅ DATABASE SETUP SUCCESSFUL!\n\n";
    echo "Admin User Created:\n";
    echo "- Username: admin\n";
    echo "- Password: admin123\n";
    echo "- Role: Administrator\n";
    echo "- Status: Active\n\n";
    echo "All tables created and sample data inserted.\n";
    
} catch (PDOException $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    die();
}
?>
