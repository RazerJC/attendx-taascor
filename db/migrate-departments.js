const { DatabaseSync } = require('node:sqlite');
const path = require('path');

const dbPath = path.join(__dirname, 'taascor.db');
const db = new DatabaseSync(dbPath);

console.log('--- Starting Safe SQLite Database Migration ---');

// 1. Create departments table
db.exec(`
    CREATE TABLE IF NOT EXISTS departments (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        area_id INTEGER NOT NULL REFERENCES areas(id),
        name TEXT NOT NULL,
        description TEXT,
        created_by INTEGER REFERENCES users(id),
        created_at TEXT NOT NULL DEFAULT (datetime('now')),
        updated_at TEXT NOT NULL DEFAULT (datetime('now')),
        UNIQUE(area_id, name COLLATE NOCASE)
    );
    CREATE INDEX IF NOT EXISTS idx_dept_area ON departments(area_id);
`);
console.log('✓ Departments table created/verified');

// 2. Add department_id column to employees table if missing
const empCols = db.prepare("PRAGMA table_info(employees)").all().map(c => c.name);
if (!empCols.includes('department_id')) {
    db.exec("ALTER TABLE employees ADD COLUMN department_id INTEGER REFERENCES departments(id);");
    console.log('✓ Added department_id column to employees table');
} else {
    console.log('✓ department_id column already exists in employees table');
}
db.exec("CREATE INDEX IF NOT EXISTS idx_emp_dept ON employees(department_id);");

// 3. Update employee_attendance table CHECK constraint if needed
// Check current sql
const attSql = db.prepare("SELECT sql FROM sqlite_master WHERE type='table' AND name='employee_attendance'").get().sql;
if (!attSql.includes('no_work') || !attSql.includes('sent_home')) {
    console.log('Migrating employee_attendance table to support all ATTENDANCE statuses (present, late, absent, on_leave, leave, no_work, sent_home, rest_day)...');
    
    db.exec(`
        PRAGMA foreign_keys=OFF;
        
        CREATE TABLE employee_attendance_new (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            employee_id INTEGER NOT NULL REFERENCES employees(id),
            schedule_id INTEGER REFERENCES employee_schedules(id),
            work_date TEXT NOT NULL,
            status TEXT NOT NULL CHECK(status IN ('present','late','absent','on_leave','leave','no_work','sent_home','rest_day')),
            time_in TEXT,
            time_out TEXT,
            remarks TEXT,
            recorded_by INTEGER NOT NULL REFERENCES users(id),
            recorded_at TEXT NOT NULL DEFAULT (datetime('now')),
            updated_by INTEGER REFERENCES users(id),
            updated_at TEXT,
            version INTEGER NOT NULL DEFAULT 1,
            UNIQUE(employee_id, work_date)
        );

        INSERT INTO employee_attendance_new (id, employee_id, schedule_id, work_date, status, time_in, time_out, remarks, recorded_by, recorded_at, updated_by, updated_at, version)
        SELECT id, employee_id, schedule_id, work_date, status, time_in, time_out, remarks, recorded_by, recorded_at, updated_by, updated_at, version
        FROM employee_attendance;

        DROP TABLE employee_attendance;
        ALTER TABLE employee_attendance_new RENAME TO employee_attendance;

        CREATE INDEX IF NOT EXISTS idx_att_emp_date ON employee_attendance(employee_id, work_date);
        CREATE INDEX IF NOT EXISTS idx_att_date ON employee_attendance(work_date);
        CREATE INDEX IF NOT EXISTS idx_att_status ON employee_attendance(status);

        PRAGMA foreign_keys=ON;
    `);
    console.log('✓ employee_attendance table upgraded successfully');
} else {
    console.log('✓ employee_attendance table already supports all statuses');
}

// 4. Seed standard departments from PHP system for all areas
const areas = db.prepare("SELECT id, name FROM areas").all();
const standardDeptNames = [
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
    'BICANG',
    'Operations',
    'Logistics'
];

const insertDeptStmt = db.prepare(`
    INSERT OR IGNORE INTO departments (area_id, name, description, created_by)
    VALUES (?, ?, ?, 1)
`);

for (const area of areas) {
    for (const dName of standardDeptNames) {
        insertDeptStmt.run(area.id, dName, `${dName} department in ${area.name}`);
    }
}
console.log(`✓ Seeded standard departments for ${areas.length} area(s)`);

// 5. Assign existing unassigned employees to departments in their area
const unassignedEmps = db.prepare("SELECT id, area_id FROM employees WHERE department_id IS NULL").all();
if (unassignedEmps.length > 0) {
    const updateEmpDept = db.prepare("UPDATE employees SET department_id = ? WHERE id = ?");
    for (const emp of unassignedEmps) {
        // Find first department for this employee's area
        const dept = db.prepare("SELECT id FROM departments WHERE area_id = ? ORDER BY id ASC LIMIT 1").get(emp.area_id);
        if (dept) {
            updateEmpDept.run(dept.id, emp.id);
        }
    }
    console.log(`✓ Assigned ${unassignedEmps.length} unassigned employee(s) to default departments`);
}

console.log('--- Migration Completed Successfully ---');
