const { getDb } = require('./database');
const db = getDb();

const tables = [
  ['users', 'approved_by'],
  ['areas', 'created_by'],
  ['positions', 'created_by'],
  ['departments', 'created_by'],
  ['employees', 'coordinator_id'],
  ['employees', 'created_by'],
  ['employees', 'updated_by'],
  ['employee_schedules', 'created_by'],
  ['employee_schedules', 'updated_by'],
  ['employee_attendance', 'recorded_by'],
  ['employee_attendance', 'updated_by'],
  ['btw_cases', 'coordinator_id'],
  ['btw_cases', 'hr_reviewer_id'],
  ['btw_case_entries', 'author_id'],
  ['concern_reports', 'reported_by'],
  ['concern_entries', 'author_id'],
  ['manpower_requests', 'requested_by'],
  ['manpower_allocations', 'allocated_by'],
  ['manpower_allocations', 'confirmed_by'],
  ['manpower_request_entries', 'author_id'],
  ['notifications', 'user_id'],
  ['audit_logs', 'user_id'],
  ['coordinator_attendance', 'user_id'],
  ['coordinator_area_assignments', 'user_id'],
  ['coordinator_area_assignments', 'assigned_by']
];

for (const id of [3, 4, 5, 6, 8]) {
    console.log(`\n=== Checking user ${id} ===`);
    tables.forEach(([tbl, col]) => {
        try {
            const r = db.prepare(`SELECT COUNT(*) as c FROM ${tbl} WHERE ${col} = ?`).get(id);
            if (r && r.c > 0) console.log(`  ${tbl}.${col}: ${r.c}`);
        } catch (e) {
            console.error(`  Error ${tbl}.${col}: ${e.message}`);
        }
    });
}
