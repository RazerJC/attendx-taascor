const { getDb } = require('./database');

function purgeOtherCoordinators() {
    const db = getDb();
    const mark = db.prepare("SELECT id FROM users WHERE email = 'mark.pasa@taascor.com'").get();
    if (!mark) {
        console.error('Mark Pasa account not found!');
        return;
    }
    const markId = mark.id;
    console.log(`Mark Pasa ID: ${markId}`);

    const otherCoords = db.prepare("SELECT id, full_name, email FROM users WHERE role = 'COORDINATOR' AND id != ?").all(markId);
    console.log(`Found ${otherCoords.length} other coordinators to remove completely.`);
    if (otherCoords.length === 0) {
        console.log('No other coordinators to remove.');
        return;
    }

    const ids = otherCoords.map(c => c.id);
    const placeholders = ids.map(() => '?').join(',');

    // Reassign references to preserve foreign keys
    db.prepare(`UPDATE positions SET created_by = 1 WHERE created_by IN (${placeholders})`).run(...ids);
    db.prepare(`UPDATE departments SET created_by = 1 WHERE created_by IN (${placeholders})`).run(...ids);
    db.prepare(`UPDATE employees SET coordinator_id = ? WHERE coordinator_id IN (${placeholders})`).run(markId, ...ids);
    db.prepare(`UPDATE employees SET created_by = 1 WHERE created_by IN (${placeholders})`).run(...ids);
    db.prepare(`UPDATE employees SET updated_by = 1 WHERE updated_by IN (${placeholders})`).run(...ids);
    db.prepare(`UPDATE employee_schedules SET created_by = 1 WHERE created_by IN (${placeholders})`).run(...ids);
    db.prepare(`UPDATE employee_schedules SET updated_by = 1 WHERE updated_by IN (${placeholders})`).run(...ids);
    db.prepare(`UPDATE employee_attendance SET recorded_by = 1 WHERE recorded_by IN (${placeholders})`).run(...ids);
    db.prepare(`UPDATE employee_attendance SET updated_by = 1 WHERE updated_by IN (${placeholders})`).run(...ids);
    db.prepare(`UPDATE btw_cases SET coordinator_id = ? WHERE coordinator_id IN (${placeholders})`).run(markId, ...ids);
    db.prepare(`UPDATE btw_case_entries SET author_id = ? WHERE author_id IN (${placeholders})`).run(markId, ...ids);
    db.prepare(`UPDATE concern_reports SET reported_by = ? WHERE reported_by IN (${placeholders})`).run(markId, ...ids);
    db.prepare(`UPDATE concern_entries SET author_id = ? WHERE author_id IN (${placeholders})`).run(markId, ...ids);
    db.prepare(`UPDATE manpower_requests SET requested_by = ? WHERE requested_by IN (${placeholders})`).run(markId, ...ids);
    db.prepare(`UPDATE manpower_allocations SET allocated_by = 1 WHERE allocated_by IN (${placeholders})`).run(...ids);
    db.prepare(`UPDATE manpower_allocations SET confirmed_by = ? WHERE confirmed_by IN (${placeholders})`).run(markId, ...ids);
    db.prepare(`UPDATE manpower_request_entries SET author_id = ? WHERE author_id IN (${placeholders})`).run(markId, ...ids);

    // Delete personal records
    db.prepare(`DELETE FROM coordinator_attendance WHERE user_id IN (${placeholders})`).run(...ids);
    db.prepare(`DELETE FROM coordinator_area_assignments WHERE user_id IN (${placeholders})`).run(...ids);
    db.prepare(`DELETE FROM notifications WHERE user_id IN (${placeholders})`).run(...ids);
    db.prepare(`DELETE FROM audit_logs WHERE user_id IN (${placeholders})`).run(...ids);
    db.prepare(`DELETE FROM email_verification_tokens WHERE user_id IN (${placeholders})`).run(...ids);
    db.prepare(`DELETE FROM password_reset_tokens WHERE user_id IN (${placeholders})`).run(...ids);

    // Delete users
    db.prepare(`DELETE FROM users WHERE id IN (${placeholders})`).run(...ids);

    console.log(`✓ Successfully deleted ${ids.length} coordinators from users table.`);

    console.log('\n--- VERIFICATION OF REMAINING USERS ---');
    const allUsers = db.prepare("SELECT id, email, full_name, role, status FROM users").all();
    allUsers.forEach(u => console.log(`[${u.role}] ID:${u.id} | ${u.full_name} (${u.email}) - ${u.status}`));

    console.log('\n--- VERIFICATION OF ACTIVE AREA ASSIGNMENTS ---');
    const caa = db.prepare("SELECT caa.*, u.full_name, a.name as area_name FROM coordinator_area_assignments caa JOIN users u ON u.id = caa.user_id JOIN areas a ON a.id = caa.area_id WHERE caa.is_current = 1").all();
    caa.forEach(c => console.log(`Coordinator: ${c.full_name} -> Area: ${c.area_name}`));
}

purgeOtherCoordinators();
