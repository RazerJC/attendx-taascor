/**
 * Setup Mark Pasa as the sole COORDINATOR
 * - Creates "Bi Chain Mamatid" area
 * - Creates Mark Pasa's user account
 * - Imports all ACTIVE employees from the Excel masterlist
 * - Removes all other COORDINATOR users
 * - Creates departments (OUTBOUND, INBOUND, LOGISTIC, etc.)
 */

const bcrypt = require('bcryptjs');
const XLSX = require('xlsx');
const path = require('path');
const { getDb } = require('./database');

const EXCEL_PATH = path.join('C:', 'Users', 'LENOVO', 'Downloads', 'BI CHAIN MAMATID UPDATE HRIS 2026.xlsx');

function run() {
    const db = getDb();
    
    console.log('=== SETTING UP MARK PASA AS SOLE COORDINATOR ===\n');
    
    // ─── STEP 1: Create the "Bi Chain Mamatid" area ───
    console.log('1. Creating Bi Chain Mamatid area...');
    let area = db.prepare("SELECT id FROM areas WHERE name = 'Bi Chain Mamatid'").get();
    if (!area) {
        db.prepare(
            "INSERT INTO areas (name, description, is_active, created_at) VALUES (?, ?, 1, datetime('now'))"
        ).run('Bi Chain Mamatid', 'Bi Chain Technology - Mamatid, Cabuyao, Laguna');
        area = db.prepare("SELECT id FROM areas WHERE name = 'Bi Chain Mamatid'").get();
        console.log(`   ✓ Area created with ID: ${area.id}`);
    } else {
        console.log(`   ✓ Area already exists with ID: ${area.id}`);
    }
    const areaId = area.id;
    
    // ─── STEP 2: Create departments under this area ───
    console.log('\n2. Creating departments...');
    const deptNames = ['OUTBOUND', 'INBOUND', 'LOGISTIC', 'LOGISTICS', 'WAREHOUSE MAN', 'INVENTORY', 'UTILITY'];
    const deptMap = {};
    
    for (const deptName of deptNames) {
        let dept = db.prepare("SELECT id FROM departments WHERE area_id = ? AND name = ? COLLATE NOCASE").get(areaId, deptName);
        if (!dept) {
            db.prepare(
                "INSERT INTO departments (area_id, name, description, created_at, updated_at) VALUES (?, ?, ?, datetime('now'), datetime('now'))"
            ).run(areaId, deptName, `${deptName} department - Bi Chain Mamatid`);
            dept = db.prepare("SELECT id FROM departments WHERE area_id = ? AND name = ? COLLATE NOCASE").get(areaId, deptName);
            console.log(`   ✓ Created department: ${deptName} (ID: ${dept.id})`);
        } else {
            console.log(`   ✓ Department exists: ${deptName} (ID: ${dept.id})`);
        }
        deptMap[deptName.toUpperCase()] = dept.id;
    }
    // Map LOGISTICS -> LOGISTIC (same thing)
    if (deptMap['LOGISTICS'] && !deptMap['LOGISTIC']) {
        deptMap['LOGISTIC'] = deptMap['LOGISTICS'];
    }
    
    // ─── STEP 3: Create positions if not existing ───
    console.log('\n3. Creating positions...');
    const positionNames = ['TEAM LEADER', 'WAREHOUSE WORKER', 'FORKLIFT OPERATOR', 'WAREHOUSE HELPER', 'WAREHOUSE MAN'];
    const posMap = {};
    
    for (const posName of positionNames) {
        let pos = db.prepare("SELECT id FROM positions WHERE title = ? COLLATE NOCASE").get(posName);
        if (!pos) {
            db.prepare(
                "INSERT INTO positions (title, description, is_active, created_at) VALUES (?, ?, 1, datetime('now'))"
            ).run(posName, `${posName} position`);
            pos = db.prepare("SELECT id FROM positions WHERE title = ? COLLATE NOCASE").get(posName);
            console.log(`   ✓ Created position: ${posName} (ID: ${pos.id})`);
        } else {
            console.log(`   ✓ Position exists: ${posName} (ID: ${pos.id})`);
        }
        posMap[posName.toUpperCase()] = pos.id;
    }
    
    // ─── STEP 4: Create Mark Pasa account ───
    console.log('\n4. Creating Mark Pasa coordinator account...');
    let markUser = db.prepare("SELECT id FROM users WHERE full_name = 'Mark Pasa' OR email = 'mark.pasa@taascor.com'").get();
    if (!markUser) {
        const password = 'MarkPasa2026!';
        const hash = bcrypt.hashSync(password, 12);
        db.prepare(
            `INSERT INTO users (email, password_hash, full_name, role, status, email_verified, must_change_password, approved_by, approved_at, created_at, updated_at)
             VALUES (?, ?, ?, 'COORDINATOR', 'active', 1, 1, 1, datetime('now'), datetime('now'), datetime('now'))`
        ).run('mark.pasa@taascor.com', hash, 'Mark Pasa');
        markUser = db.prepare("SELECT id FROM users WHERE email = 'mark.pasa@taascor.com'").get();
        console.log(`   ✓ Mark Pasa account created (ID: ${markUser.id})`);
        console.log(`   📧 Email: mark.pasa@taascor.com`);
        console.log(`   🔑 Password: ${password}`);
    } else {
        // Ensure he's active and COORDINATOR
        db.prepare("UPDATE users SET role = 'COORDINATOR', status = 'active', email_verified = 1 WHERE id = ?").run(markUser.id);
        console.log(`   ✓ Mark Pasa already exists (ID: ${markUser.id}), ensured active COORDINATOR`);
    }
    const markId = markUser.id;
    
    // ─── STEP 5: Assign Mark Pasa to Bi Chain Mamatid area ───
    console.log('\n5. Assigning Mark Pasa to Bi Chain Mamatid area...');
    const existingAssign = db.prepare(
        "SELECT id FROM coordinator_area_assignments WHERE user_id = ? AND area_id = ? AND is_current = 1"
    ).get(markId, areaId);
    
    if (!existingAssign) {
        db.prepare(
            `INSERT INTO coordinator_area_assignments (user_id, area_id, assigned_by, assigned_at, is_current, remarks)
             VALUES (?, ?, 1, datetime('now'), 1, 'Coordinator for Bi Chain Mamatid')`
        ).run(markId, areaId);
        console.log(`   ✓ Mark Pasa assigned to area ID: ${areaId}`);
    } else {
        console.log(`   ✓ Assignment already exists`);
    }
    
    // ─── STEP 6: Remove all other COORDINATOR users ───
    console.log('\n6. Removing all other coordinator accounts...');
    const otherCoords = db.prepare(
        "SELECT id, email, full_name FROM users WHERE role = 'COORDINATOR' AND id != ?"
    ).all(markId);
    
    for (const coord of otherCoords) {
        // End their area assignments
        db.prepare(
            "UPDATE coordinator_area_assignments SET is_current = 0, ended_at = datetime('now') WHERE user_id = ? AND is_current = 1"
        ).run(coord.id);
        // Deactivate (suspend) the account rather than delete to preserve foreign key integrity
        db.prepare(
            "UPDATE users SET status = 'suspended', updated_at = datetime('now') WHERE id = ?"
        ).run(coord.id);
        console.log(`   ✗ Removed coordinator: ${coord.full_name} (${coord.email}) → suspended`);
    }
    if (otherCoords.length === 0) {
        console.log('   ✓ No other coordinators to remove');
    }
    
    // ─── STEP 7: Import employees from Excel ───
    console.log('\n7. Importing ACTIVE employees from Bi Chain masterlist...');
    
    let wb;
    try {
        wb = XLSX.readFile(EXCEL_PATH);
    } catch (err) {
        console.error(`   ✗ Could not read Excel file: ${err.message}`);
        console.log('   Skipping employee import.');
        return;
    }
    
    const ws = wb.Sheets[wb.SheetNames[0]];
    const rows = XLSX.utils.sheet_to_json(ws, { header: 1 });
    
    // Header is row 0
    // Columns: [0]HMO LIST, [1]Employee Ident, [2]Old Employee Ident, [3]Payroll Employee ID,
    //           [4]Full Name, [5]Last Name, [6]First Name, [7]Middle Name, [8]Hire Date,
    //           [9]Separation Date, ... [22]Position, [23]Client, [24]Branch, [25]Client Location,
    //           [26]Department
    
    let imported = 0;
    let skipped = 0;
    
    for (let i = 1; i < rows.length; i++) {
        const row = rows[i];
        if (!row || row.length === 0) continue;
        
        const status = String(row[0] || '').trim().toUpperCase();
        if (status !== 'ACTIVE') continue;
        
        const fullName = String(row[4] || '').trim();
        const lastName = String(row[5] || '').trim();
        const firstName = String(row[6] || '').trim();
        const empIdCode = String(row[2] || row[3] || '').trim();
        const position = String(row[22] || '').trim().toUpperCase();
        const department = String(row[26] || '').trim().toUpperCase();
        
        if (!fullName || !lastName || !firstName) continue;
        
        // Generate employee_id
        let employeeId = empIdCode || `BC-AUTO-${i}`;
        
        // Parse hire date
        let hireDate = '2026-01-01';
        if (row[8]) {
            if (typeof row[8] === 'number') {
                // Excel date serial number
                const d = XLSX.SSF.parse_date_code(row[8]);
                if (d) {
                    hireDate = `${d.y}-${String(d.m).padStart(2, '0')}-${String(d.d).padStart(2, '0')}`;
                }
            } else {
                hireDate = String(row[8]);
            }
        }
        
        // Get position_id
        const posId = posMap[position] || null;
        
        // Get department_id
        const deptId = deptMap[department] || null;
        
        // Check if employee already exists by employee_id
        const existing = db.prepare("SELECT id FROM employees WHERE employee_id = ?").get(employeeId);
        if (existing) {
            skipped++;
            continue;
        }
        
        try {
            db.prepare(
                `INSERT INTO employees (employee_id, first_name, last_name, full_name, area_id, coordinator_id, department_id, position_id, employment_start_date, status, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', datetime('now'), datetime('now'))`
            ).run(employeeId, firstName, lastName, fullName, areaId, markId, deptId, posId, hireDate);
            imported++;
        } catch (err) {
            console.log(`   ⚠ Skipped "${fullName}": ${err.message}`);
            skipped++;
        }
    }
    
    console.log(`   ✓ Imported: ${imported} employees`);
    console.log(`   ○ Skipped: ${skipped} (already exist or errors)`);
    
    // ─── SUMMARY ───
    console.log('\n══════════════════════════════════════════════════');
    console.log('  SETUP COMPLETE');
    console.log('══════════════════════════════════════════════════');
    console.log(`  Coordinator: Mark Pasa`);
    console.log(`  Email:       mark.pasa@taascor.com`);
    console.log(`  Password:    MarkPasa2026!`);
    console.log(`  Area:        Bi Chain Mamatid`);
    console.log(`  Employees:   ${imported} imported from masterlist`);
    console.log('══════════════════════════════════════════════════\n');
}

// Initialize DB first
require('./database').initializeDb();
run();
