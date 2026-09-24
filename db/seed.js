// Seed script - creates initial admin and sample data for demonstration
const bcrypt = require('bcryptjs');
require('dotenv').config();
const { getDb, initializeDb, closeDb } = require('./database');
const { DateTime } = require('luxon');

async function createInitialAdmin() {
    const db = getDb();
    
    // Check if admin already exists
    const existingAdmin = (await db.prepare("SELECT id FROM users WHERE role = 'ADMIN' LIMIT 1").get());
    if (existingAdmin) return;
    
    const email = process.env.ADMIN_EMAIL;
    const password = process.env.ADMIN_PASSWORD;
    if (!email || !password || password.length < 12 || password === 'ChangeMe123!') {
        throw new Error('Set ADMIN_EMAIL and a unique ADMIN_PASSWORD of at least 12 characters for initial setup.');
    }
    const hash = bcrypt.hashSync(password, 12);
    
    (await db.prepare(
        `INSERT INTO users (email, password_hash, full_name, role, status, email_verified, must_change_password, created_at, updated_at)
         VALUES (?, ?, ?, 'ADMIN', 'active', 1, 1, UTC_TIMESTAMP(), UTC_TIMESTAMP())`
    ).run(email, hash, 'System Administrator'));
    
    console.log(`\n==================================================`);
    console.log(`  Initial ADMIN account created`);
    console.log(`  Email: ${email}`);
    console.log(`  CHANGE THIS PASSWORD ON FIRST LOGIN!`);
    console.log(`==================================================\n`);
}

async function seedSampleData() {
    const db = getDb();
    
    // Check if sample data already exists
    const existingAreas = (await db.prepare('SELECT COUNT(*) as count FROM areas').get());
    if (existingAreas.count > 0) {
        console.log('Sample data already exists, skipping seed.');
        return;
    }
    
    console.log('Seeding sample data...');
    
    const now = DateTime.now().setZone('Asia/Manila');
    const today = now.toFormat('yyyy-MM-dd');
    const yesterday = now.minus({ days: 1 }).toFormat('yyyy-MM-dd');
    const lastWeek = now.minus({ weeks: 1 }).toFormat('yyyy-MM-dd');
    const lastMonth = now.minus({ months: 1 }).toFormat('yyyy-MM-dd');
    
    // --- Areas ---
    (await db.prepare("INSERT INTO areas (name, description, is_active, created_at) VALUES (?, ?, 1, UTC_TIMESTAMP())").run('Warehouse A - Pasig', 'Main distribution warehouse in Pasig City'));
    (await db.prepare("INSERT INTO areas (name, description, is_active, created_at) VALUES (?, ?, 1, UTC_TIMESTAMP())").run('Warehouse B - Taguig', 'Secondary warehouse in Taguig City'));
    (await db.prepare("INSERT INTO areas (name, description, is_active, created_at) VALUES (?, ?, 1, UTC_TIMESTAMP())").run('Warehouse C - Makati', 'Makati storage and distribution center'));
    
    // --- Positions ---
    (await db.prepare("INSERT INTO positions (title, description, is_active, created_at) VALUES (?, ?, 1, UTC_TIMESTAMP())").run('Warehouse Worker', 'General warehouse operations'));
    (await db.prepare("INSERT INTO positions (title, description, is_active, created_at) VALUES (?, ?, 1, UTC_TIMESTAMP())").run('Forklift Operator', 'Forklift and heavy equipment operation'));
    (await db.prepare("INSERT INTO positions (title, description, is_active, created_at) VALUES (?, ?, 1, UTC_TIMESTAMP())").run('Inventory Clerk', 'Inventory management and tracking'));
    (await db.prepare("INSERT INTO positions (title, description, is_active, created_at) VALUES (?, ?, 1, UTC_TIMESTAMP())").run('Quality Inspector', 'Quality control and inspection'));
    (await db.prepare("INSERT INTO positions (title, description, is_active, created_at) VALUES (?, ?, 1, UTC_TIMESTAMP())").run('Packer', 'Product packing and preparation'));
    (await db.prepare("INSERT INTO positions (title, description, is_active, created_at) VALUES (?, ?, 1, UTC_TIMESTAMP())").run('Driver', 'Delivery and transport operations'));
    
    // --- HR Account ---
    const hrHash = bcrypt.hashSync('HrUser123!', 12);
    (await db.prepare(
        `INSERT INTO users (email, password_hash, full_name, role, status, email_verified, created_at, updated_at)
         VALUES (?, ?, ?, 'HR', 'active', 1, UTC_TIMESTAMP(), UTC_TIMESTAMP())`
    ).run('hr.santos@taascor.com', hrHash, 'Maria Santos'));
    
    // --- Coordinator Accounts ---
    const coordHash = bcrypt.hashSync('Coord123!', 12);
    
    (await db.prepare(
        `INSERT INTO users (email, password_hash, full_name, role, status, email_verified, approved_by, approved_at, created_at, updated_at)
         VALUES (?, ?, ?, 'COORDINATOR', 'active', 1, 1, UTC_TIMESTAMP(), UTC_TIMESTAMP(), UTC_TIMESTAMP())`
    ).run('juan.delacruz@taascor.com', coordHash, 'Juan Dela Cruz'));
    
    (await db.prepare(
        `INSERT INTO users (email, password_hash, full_name, role, status, email_verified, approved_by, approved_at, created_at, updated_at)
         VALUES (?, ?, ?, 'COORDINATOR', 'active', 1, 1, UTC_TIMESTAMP(), UTC_TIMESTAMP(), UTC_TIMESTAMP())`
    ).run('anna.reyes@taascor.com', coordHash, 'Anna Reyes'));
    
    // Pending coordinator
    (await db.prepare(
        `INSERT INTO users (email, password_hash, full_name, role, status, email_verified, created_at, updated_at)
         VALUES (?, ?, ?, 'COORDINATOR', 'pending', 1, UTC_TIMESTAMP(), UTC_TIMESTAMP())`
    ).run('mark.garcia@taascor.com', coordHash, 'Mark Garcia'));
    
    // --- Coordinator Area Assignments ---
    // Juan → Warehouse A
    (await db.prepare(
        `INSERT INTO coordinator_area_assignments (user_id, area_id, assigned_by, assigned_at, is_current)
         VALUES (3, 1, 1, UTC_TIMESTAMP(), 1)`
    ).run());
    
    // Anna → Warehouse B
    (await db.prepare(
        `INSERT INTO coordinator_area_assignments (user_id, area_id, assigned_by, assigned_at, is_current)
         VALUES (4, 2, 1, UTC_TIMESTAMP(), 1)`
    ).run());
    
    // --- Employees ---
    const employees = [
        // Warehouse A employees (coordinator: Juan, id=3)
        { eid: 'TAAS-2025-0001', fn: 'Pedro', ln: 'Ramos', area: 1, coord: 3, pos: 1, start: '2025-01-15' },
        { eid: 'TAAS-2025-0002', fn: 'Jose', ln: 'Mendoza', area: 1, coord: 3, pos: 1, start: '2025-02-01' },
        { eid: 'TAAS-2025-0003', fn: 'Carlo', ln: 'Villanueva', area: 1, coord: 3, pos: 2, start: '2025-03-01' },
        { eid: 'TAAS-2025-0004', fn: 'Maria', ln: 'Lim', area: 1, coord: 3, pos: 3, start: '2025-01-20' },
        { eid: 'TAAS-2025-0005', fn: 'Teresa', ln: 'Aquino', area: 1, coord: 3, pos: 5, start: '2025-04-01' },
        { eid: 'TAAS-2025-0006', fn: 'Roberto', ln: 'Cruz', area: 1, coord: 3, pos: 1, start: '2025-05-15', status: 'inactive', inactDate: '2026-08-01', inactReason: 'Resigned' },
        // Warehouse B employees (coordinator: Anna, id=4)
        { eid: 'TAAS-2025-0007', fn: 'Grace', ln: 'Tan', area: 2, coord: 4, pos: 1, start: '2025-01-10' },
        { eid: 'TAAS-2025-0008', fn: 'Ricardo', ln: 'Santos', area: 2, coord: 4, pos: 2, start: '2025-02-15' },
        { eid: 'TAAS-2025-0009', fn: 'Elena', ln: 'Fernandez', area: 2, coord: 4, pos: 3, start: '2025-03-20' },
        { eid: 'TAAS-2025-0010', fn: 'Miguel', ln: 'Bautista', area: 2, coord: 4, pos: 4, start: '2025-04-10' },
        { eid: 'TAAS-2025-0011', fn: 'Carmen', ln: 'Rivera', area: 2, coord: 4, pos: 5, start: '2025-05-01' },
        { eid: 'TAAS-2025-0012', fn: 'Antonio', ln: 'Gonzales', area: 2, coord: 4, pos: 6, start: '2025-06-01' },
    ];
    
    for (const emp of employees) {
        (await db.prepare(
            `INSERT INTO employees (employee_id, first_name, last_name, full_name, area_id, coordinator_id, position_id, employment_start_date, status, inactivation_date, inactivation_reason, created_at, updated_at, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP(), 1)`
        ).run(emp.eid, emp.fn, emp.ln, `${emp.fn} ${emp.ln}`, emp.area, emp.coord, emp.pos, emp.start, emp.status || 'active', emp.inactDate || null, emp.inactReason || null));
    }
    
    // --- Schedules for today and upcoming days ---
    const activeEmps = (await db.prepare("SELECT id FROM employees WHERE status = 'active'").all());
    for (const emp of activeEmps) {
        // Create schedules for the last 3 days, today, and next 3 days
        for (let d = -3; d <= 3; d++) {
            const date = now.plus({ days: d }).toFormat('yyyy-MM-dd');
            const dayOfWeek = now.plus({ days: d }).weekday; // 1=Mon, 7=Sun
            const isRest = dayOfWeek === 7; // Sunday rest
            
            (await db.prepare(
                `INSERT IGNORE INTO employee_schedules (employee_id, work_date, shift_start, shift_end, is_rest_day, created_by, created_at)
                 VALUES (?, ?, ?, ?, ?, 1, UTC_TIMESTAMP())`
            ).run(emp.id, date, isRest ? null : '08:00', isRest ? null : '17:00', isRest ? 1 : 0));
        }
    }
    
    // --- Sample attendance (yesterday) ---
    const yesterdayScheds = (await db.prepare(
        `SELECT es.id as sched_id, es.employee_id FROM employee_schedules es
         JOIN employees e ON es.employee_id = e.id
         WHERE es.work_date = ? AND es.is_rest_day = 0 AND e.status = 'active'`
    ).all(yesterday));
    
    for (const sched of yesterdayScheds) {
        const emp = (await db.prepare('SELECT coordinator_id FROM employees WHERE id = ?').get(sched.employee_id));
        const statuses = ['present', 'present', 'present', 'present', 'late', 'present'];
        const status = statuses[Math.floor(Math.random() * statuses.length)];
        
        (await db.prepare(
            `INSERT IGNORE INTO employee_attendance (employee_id, schedule_id, work_date, status, time_in, time_out, recorded_by, recorded_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP())`
        ).run(sched.employee_id, sched.sched_id, yesterday, status, 
              status === 'late' ? '08:35' : '07:55', '17:05', emp.coordinator_id));
    }
    
    // --- Sample BTW Case ---
    (await db.prepare(
        `INSERT INTO btw_cases (case_ref, employee_id, area_id, coordinator_id, status, absence_dates, absence_reason, created_at, updated_at)
         VALUES (?, 1, 1, 3, 'pending_hr_review', ?, 'Personal emergency', UTC_TIMESTAMP(), UTC_TIMESTAMP())`
    ).run('BTW-2026-0001', JSON.stringify([lastWeek])));
    
    (await db.prepare(
        `INSERT INTO btw_case_entries (case_id, author_id, author_role, content, entry_type, created_at)
         VALUES (1, 3, 'COORDINATOR', 'Employee Pedro Ramos was absent on ${lastWeek} due to a personal emergency. Requesting back-to-work clearance.', 'comment', UTC_TIMESTAMP())`
    ).run());
    
    // --- Sample Concern ---
    (await db.prepare(
        `INSERT INTO concern_reports (employee_id, area_id, category, description, reported_by, status, created_at, updated_at)
         VALUES (7, 2, 'Tardiness', 'Employee has been consistently late for the past week.', 4, 'submitted', UTC_TIMESTAMP(), UTC_TIMESTAMP())`
    ).run());
    
    // --- Sample Manpower Request ---
    (await db.prepare(
        `INSERT INTO manpower_requests (area_id, requested_by, status, deployment_date, shift_details, reason, priority, remarks, created_at, updated_at)
         VALUES (1, 3, 'submitted', ?, '08:00 - 17:00', 'Peak season additional staffing needed', 'high', 'Need workers ASAP', UTC_TIMESTAMP(), UTC_TIMESTAMP())`
    ).run(now.plus({ weeks: 1 }).toFormat('yyyy-MM-dd')));
    
    (await db.prepare(
        `INSERT INTO manpower_request_positions (request_id, position_id, quantity_requested)
         VALUES (1, 1, 3)`
    ).run());
    (await db.prepare(
        `INSERT INTO manpower_request_positions (request_id, position_id, quantity_requested)
         VALUES (1, 5, 2)`
    ).run());
    
    // --- Notifications ---
    (await db.prepare(
        `INSERT INTO notifications (user_id, title, message, link, created_at)
         VALUES (2, 'New BTW Case', 'Pedro Ramos has a pending back-to-work case.', '/btw/1', UTC_TIMESTAMP())`
    ).run());
    (await db.prepare(
        `INSERT INTO notifications (user_id, title, message, link, created_at)
         VALUES (2, 'New Concern Report', 'An employee concern has been submitted from Warehouse B.', '/concerns/1', UTC_TIMESTAMP())`
    ).run());
    (await db.prepare(
        `INSERT INTO notifications (user_id, title, message, link, created_at)
         VALUES (2, 'Manpower Request', 'A manpower request has been submitted for Warehouse A.', '/manpower/1', UTC_TIMESTAMP())`
    ).run());
    
    // --- Coordinator Attendance ---
    (await db.prepare(
        `INSERT INTO coordinator_attendance (user_id, work_date, time_in, time_out, time_in_server, time_out_server)
         VALUES (3, ?, '07:50', '17:05', UTC_TIMESTAMP(), UTC_TIMESTAMP())`
    ).run(yesterday));
    (await db.prepare(
        `INSERT INTO coordinator_attendance (user_id, work_date, time_in, time_out, time_in_server, time_out_server)
         VALUES (4, ?, '07:55', '17:10', UTC_TIMESTAMP(), UTC_TIMESTAMP())`
    ).run(yesterday));
    
    console.log('Sample data seeded successfully!');
    console.log('\nSample accounts:');
    console.log('  ADMIN:       uses ADMIN_EMAIL and ADMIN_PASSWORD from your environment');
    console.log('  HR:          hr.santos@taascor.com / HrUser123!');
    console.log('  COORDINATOR: juan.delacruz@taascor.com / Coord123! (Warehouse A)');
    console.log('  COORDINATOR: anna.reyes@taascor.com / Coord123! (Warehouse B)');
    console.log('  PENDING:     mark.garcia@taascor.com / Coord123!');
}

// Run directly
if (require.main === module) {
    (async () => {
        if (process.env.NODE_ENV === 'production') throw new Error('Demo seeding is disabled in production. Use npm run db:init.');
        await initializeDb();
        await createInitialAdmin();
        await seedSampleData();
    })().catch(error => { console.error(error.message); process.exitCode = 1; }).finally(closeDb);
}

module.exports = { createInitialAdmin, seedSampleData };
