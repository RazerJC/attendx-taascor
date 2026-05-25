-- TAASCOR Attendance Monitoring System Database Schema
-- Database: taascor_attendance

-- Departments Table
CREATE TABLE IF NOT EXISTS departments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Users Table (Admin & Coordinators)
CREATE TABLE IF NOT EXISTS users (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Employees Table
CREATE TABLE IF NOT EXISTS employees (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Attendance Table (full schema with all tracking columns)
CREATE TABLE IF NOT EXISTS attendance (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Activity Log Table
CREATE TABLE IF NOT EXISTS activity_log (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    action VARCHAR(100) NOT NULL,
    details TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user (user_id),
    INDEX idx_action (action),
    INDEX idx_date (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Attendance Edit Requests Table (for past date edits requiring approval)
CREATE TABLE IF NOT EXISTS attendance_edit_requests (
    id INT PRIMARY KEY AUTO_INCREMENT,
    employee_id INT NOT NULL,
    attendance_date DATE NOT NULL,
    old_status ENUM('present', 'absent', 'no_work', 'leave', 'sent_home', 'rest_day') NULL,
    new_status ENUM('present', 'absent', 'no_work', 'leave', 'sent_home', 'rest_day') NOT NULL,
    requested_by INT NOT NULL,
    reason TEXT,
    status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    approved_by INT,
    approved_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    FOREIGN KEY (requested_by) REFERENCES users(id) ON DELETE RESTRICT,
    FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_status (status),
    INDEX idx_employee (employee_id),
    INDEX idx_date (attendance_date),
    INDEX idx_requested_by (requested_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default admin user (password hash is updated by start.sh at runtime)
INSERT IGNORE INTO users (username, password, full_name, role, status)
VALUES ('admin', '$2y$10$placeholder', 'Administrator', 'admin', 'active');

-- Sample departments
INSERT IGNORE INTO departments (name)
VALUES
    ('Human Resources'),
    ('Finance'),
    ('Operations'),
    ('Sales'),
    ('Marketing');

-- Sample employees
INSERT IGNORE INTO employees (first_name, last_name, department_id, position, date_hired, status)
SELECT 'John', 'Doe', d.id, 'Manager', '2023-01-15', 'active'
FROM departments d WHERE d.name = 'Human Resources'
UNION ALL
SELECT 'Jane', 'Smith', d.id, 'Analyst', '2023-02-20', 'active'
FROM departments d WHERE d.name = 'Finance'
UNION ALL
SELECT 'Bob', 'Johnson', d.id, 'Officer', '2023-03-10', 'active'
FROM departments d WHERE d.name = 'Operations'
ON DUPLICATE KEY UPDATE status = 'active';
