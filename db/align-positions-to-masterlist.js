/**
 * Align positions strictly with the Masterlist:
 * Masterlist positions:
 * 1. TEAM LEADER
 * 2. WAREHOUSE WORKER
 * 3. FORKLIFT OPERATOR
 * 4. WAREHOUSE HELPER
 * 
 * Removes all other positions (Inventory Clerk, Quality Inspector, Packer, Driver, Line Lead, WAREHOUSE MAN).
 */

const { getDb } = require('./database');
const XLSX = require('xlsx');
const path = require('path');

const EXCEL_PATH = path.join('C:', 'Users', 'LENOVO', 'Downloads', 'BI CHAIN MAMATID UPDATE HRIS 2026.xlsx');

function run() {
    const db = getDb();
    console.log('=== ALIGNING POSITIONS TO MASTERLIST ===\n');

    const MASTERLIST_POSITIONS = [
        { title: 'TEAM LEADER', description: 'Team leader and operational supervisor' },
        { title: 'WAREHOUSE WORKER', description: 'General warehouse operations and handling' },
        { title: 'FORKLIFT OPERATOR', description: 'Forklift and material handling equipment operation' },
        { title: 'WAREHOUSE HELPER', description: 'Warehouse support and assistant' }
    ];

    // 1. Ensure the 4 masterlist positions exist with exact uppercase title
    const posIdMap = {};
    for (const p of MASTERLIST_POSITIONS) {
        let existing = db.prepare("SELECT id FROM positions WHERE title = ? COLLATE NOCASE").get(p.title);
        if (existing) {
            // Update title to exact uppercase
            db.prepare("UPDATE positions SET title = ?, description = ?, is_active = 1 WHERE id = ?")
                .run(p.title, p.description, existing.id);
            posIdMap[p.title] = existing.id;
            console.log(`✓ Position updated/confirmed: ${p.title} (ID: ${existing.id})`);
        } else {
            const res = db.prepare("INSERT INTO positions (title, description, is_active, created_at) VALUES (?, ?, 1, datetime('now'))")
                .run(p.title, p.description);
            posIdMap[p.title] = res.lastInsertRowid;
            console.log(`✓ Position created: ${p.title} (ID: ${res.lastInsertRowid})`);
        }
    }

    const validIds = Object.values(posIdMap);
    console.log('\nMasterlist Position IDs:', posIdMap);

    // 2. Reassign any employees pointing to obsolete positions
    // First, for Bi Chain employees (area_id = 4), read exact position from Excel
    const wb = XLSX.readFile(EXCEL_PATH);
    const ws = wb.Sheets[wb.SheetNames[0]];
    const data = XLSX.utils.sheet_to_json(ws, { header: 1 });

    const excelPosByEmpId = {};
    for (let i = 1; i < data.length; i++) {
        const row = data[i];
        if (!row || !row[4]) continue;
        const empCode = String(row[2] || row[3] || '').trim();
        const fullName = String(row[4] || '').trim().toUpperCase();
        const pos = String(row[22] || '').trim().toUpperCase();
        if (pos && posIdMap[pos]) {
            if (empCode) excelPosByEmpId[empCode] = posIdMap[pos];
            excelPosByEmpId[fullName] = posIdMap[pos];
        }
    }

    // Update all Bi Chain employees
    const biChainEmps = db.prepare("SELECT id, employee_id, full_name, position_id FROM employees WHERE area_id = 4").all();
    for (const emp of biChainEmps) {
        const targetPosId = excelPosByEmpId[emp.employee_id] || excelPosByEmpId[emp.full_name.toUpperCase()] || posIdMap['WAREHOUSE WORKER'];
        db.prepare("UPDATE employees SET position_id = ? WHERE id = ?").run(targetPosId, emp.id);
    }
    console.log(`✓ Updated all ${biChainEmps.length} Bi Chain employees with exact Masterlist positions.`);

    // For any non-Bi Chain employees pointing to positions not in validIds, reassign to WAREHOUSE WORKER or FORKLIFT OPERATOR
    const otherEmps = db.prepare(`SELECT id, position_id FROM employees WHERE position_id NOT IN (${validIds.join(',')})`).all();
    for (const emp of otherEmps) {
        db.prepare("UPDATE employees SET position_id = ? WHERE id = ?").run(posIdMap['WAREHOUSE WORKER'], emp.id);
    }
    console.log(`✓ Reassigned ${otherEmps.length} other employees to valid positions.`);

    // 3. Update manpower allocations & requests pointing to obsolete positions
    db.prepare(`UPDATE manpower_request_positions SET position_id = ? WHERE position_id NOT IN (${validIds.join(',')})`)
        .run(posIdMap['WAREHOUSE WORKER']);
    db.prepare(`UPDATE manpower_allocations SET actual_position_id = ? WHERE actual_position_id IS NOT NULL AND actual_position_id NOT IN (${validIds.join(',')})`)
        .run(posIdMap['WAREHOUSE WORKER']);

    // 4. Delete all other positions
    const deleted = db.prepare(`DELETE FROM positions WHERE id NOT IN (${validIds.join(',')})`).run();
    console.log(`✓ Removed ${deleted.changes} obsolete positions from database.`);

    // 5. Verification
    console.log('\n--- FINAL POSITIONS IN DATABASE ---');
    const finalPositions = db.prepare("SELECT p.id, p.title, p.description, COUNT(e.id) as employee_count FROM positions p LEFT JOIN employees e ON e.position_id = p.id GROUP BY p.id ORDER BY p.id ASC").all();
    finalPositions.forEach(p => {
        console.log(`  [ID ${p.id}] ${p.title} -> ${p.employee_count} employees assigned`);
    });

    console.log('\n--- BI CHAIN MAMATID EMPLOYEE ROSTER ---');
    const roster = db.prepare(`
        SELECT e.employee_id, e.full_name, p.title as position, d.name as department
        FROM employees e
        JOIN positions p ON p.id = e.position_id
        LEFT JOIN departments d ON d.id = e.department_id
        WHERE e.area_id = 4
        ORDER BY p.title ASC, e.full_name ASC
    `).all();
    roster.forEach(r => {
        console.log(`  ${r.employee_id.padEnd(20)} | ${r.full_name.padEnd(30)} | ${r.position.padEnd(18)} | ${r.department || 'N/A'}`);
    });
}

run();
