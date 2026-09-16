const {getDb} = require('./database');
const db = getDb();

console.log('=== ACTIVE COORDINATORS ===');
db.prepare("SELECT id, email, full_name, role, status FROM users WHERE role = 'COORDINATOR' AND status = 'active'")
    .all().forEach(u => console.log(JSON.stringify(u)));

console.log('\n=== CURRENT AREA ASSIGNMENTS ===');
db.prepare("SELECT caa.user_id, u.full_name, a.name as area_name FROM coordinator_area_assignments caa JOIN users u ON u.id = caa.user_id JOIN areas a ON a.id = caa.area_id WHERE caa.is_current = 1")
    .all().forEach(c => console.log(JSON.stringify(c)));

console.log('\n=== BI CHAIN MAMATID EMPLOYEES ===');
db.prepare("SELECT e.employee_id, e.full_name, e.status, d.name as dept, p.title as pos FROM employees e LEFT JOIN departments d ON d.id = e.department_id LEFT JOIN positions p ON p.id = e.position_id WHERE e.area_id = 4")
    .all().forEach(e => console.log(`  ${e.employee_id} | ${e.full_name} | ${e.dept || 'N/A'} | ${e.pos || 'N/A'}`));

console.log('\n=== SUSPENDED COORDINATORS ===');
db.prepare("SELECT id, email, full_name FROM users WHERE role = 'COORDINATOR' AND status = 'suspended'")
    .all().forEach(u => console.log(`  ${u.full_name} (${u.email})`));
