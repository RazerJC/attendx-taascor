const express = require('express');
const router = express.Router();
const { requireAuth } = require('../middleware/auth');
const { requireArea, validateAreaAccess } = require('../middleware/area-guard');
const { getDb } = require('../db/database');
const { logAction } = require('../services/audit');

// GET /employees
router.get('/', requireAuth, (req, res) => {
    const user = req.session.user;
    const db = getDb();
    const tab = req.query.tab === 'inactive' ? 'inactive' : 'active';
    const search = req.query.search ? req.query.search.trim() : '';
    const positionId = req.query.position_id ? parseInt(req.query.position_id) : null;
    const departmentId = req.query.department_id ? parseInt(req.query.department_id) : null;
    const selectedAreaId = user.role === 'COORDINATOR' ? req.userAreaId : (req.query.area_id ? parseInt(req.query.area_id) : null);

    let where = ['e.status = ?'];
    let params = [tab];

    if (selectedAreaId) {
        where.push('e.area_id = ?');
        params.push(selectedAreaId);
    }

    if (positionId) {
        where.push('e.position_id = ?');
        params.push(positionId);
    }

    if (departmentId) {
        where.push('e.department_id = ?');
        params.push(departmentId);
    }

    if (search) {
        where.push('(e.full_name LIKE ? OR e.employee_id LIKE ?)');
        params.push(`%${search}%`, `%${search}%`);
    }

    const whereClause = 'WHERE ' + where.join(' AND ');

    const employees = db.prepare(`
        SELECT e.*, a.name as area_name, p.title as position_title, u.full_name as coordinator_name,
               d.name as dept_name,
               (SELECT status FROM btw_cases WHERE employee_id = e.id AND status IN ('pending_hr_review', 'for_clarification') LIMIT 1) as open_btw_status
        FROM employees e
        JOIN areas a ON e.area_id = a.id
        LEFT JOIN positions p ON e.position_id = p.id
        LEFT JOIN departments d ON e.department_id = d.id
        LEFT JOIN users u ON e.coordinator_id = u.id
        ${whereClause}
        ORDER BY e.full_name ASC
    `).all(...params);

    // Counts for tabs
    let activeWhere = user.role === 'COORDINATOR' ? "WHERE area_id = ? AND status = 'active'" : "WHERE status = 'active'";
    let inactiveWhere = user.role === 'COORDINATOR' ? "WHERE area_id = ? AND status = 'inactive'" : "WHERE status = 'inactive'";
    const activeParams = user.role === 'COORDINATOR' ? [req.userAreaId] : [];
    const inactiveParams = user.role === 'COORDINATOR' ? [req.userAreaId] : [];

    const activeCount = db.prepare(`SELECT COUNT(*) as count FROM employees ${activeWhere}`).get(...activeParams).count;
    const inactiveCount = db.prepare(`SELECT COUNT(*) as count FROM employees ${inactiveWhere}`).get(...inactiveParams).count;

    const positions = db.prepare('SELECT * FROM positions WHERE is_active = 1 ORDER BY title ASC').all();
    const areas = user.role !== 'COORDINATOR' ? db.prepare('SELECT * FROM areas WHERE is_active = 1 ORDER BY name ASC').all() : [];

    // Departments for filtering
    let deptQuery = "SELECT * FROM departments";
    let deptQueryParams = [];
    if (selectedAreaId) {
        deptQuery += " WHERE area_id = ?";
        deptQueryParams.push(selectedAreaId);
    }
    deptQuery += " ORDER BY name ASC";
    const departments = db.prepare(deptQuery).all(...deptQueryParams);

    res.render('employees/list', {
        title: 'Employee Masterfile - TAASCOR',
        employees,
        tab,
        search,
        positionId,
        departmentId,
        selectedAreaId,
        activeCount,
        inactiveCount,
        positions,
        departments,
        areas
    });
});

// GET /employees/new
router.get('/new', requireAuth, (req, res) => {
    const user = req.session.user;
    const db = getDb();

    if (user.role === 'COORDINATOR' && !req.userAreaId) {
        req.flash('error', 'You do not have an assigned area.');
        return res.redirect('/employees');
    }

    const positions = db.prepare('SELECT * FROM positions WHERE is_active = 1 ORDER BY title ASC').all();
    const areas = user.role === 'COORDINATOR'
        ? [req.userArea]
        : db.prepare('SELECT * FROM areas WHERE is_active = 1 ORDER BY name ASC').all();

    const targetAreaId = user.role === 'COORDINATOR' ? req.userAreaId : (areas[0]?.id || 1);
    const departments = db.prepare('SELECT * FROM departments WHERE area_id = ? ORDER BY name ASC').all(targetAreaId);

    res.render('employees/form', {
        title: 'Add Employee - TAASCOR',
        employee: null,
        positions,
        departments,
        areas,
        isEdit: false
    });
});

// POST /employees
router.post('/', requireAuth, (req, res) => {
    const user = req.session.user;
    const { first_name, last_name, area_id, position_id, department_id, employment_start_date, remarks } = req.body;
    const db = getDb();

    if (!first_name || !last_name || !area_id || !employment_start_date) {
        req.flash('error', 'Please fill in all required fields.');
        return res.redirect('/employees/new');
    }

    const assignedAreaId = user.role === 'COORDINATOR' ? req.userAreaId : parseInt(area_id);

    // Validate area access
    if (!validateAreaAccess(assignedAreaId, req)) {
        return res.status(403).render('error', { title: 'Access Denied', message: 'You can only add employees to your assigned area.', code: 403 });
    }

    const coordinatorId = user.role === 'COORDINATOR' ? user.id : null;
    const fullName = `${first_name.trim()} ${last_name.trim()}`;

    // Auto-generate employee ID: TAAS-YYYY-XXXX
    const year = new Date().getFullYear();
    const count = db.prepare('SELECT COUNT(*) as count FROM employees').get().count;
    const empId = `TAAS-${year}-${String(count + 1).padStart(4, '0')}`;

    const result = db.prepare(`
        INSERT INTO employees (employee_id, first_name, last_name, full_name, area_id, coordinator_id, position_id, department_id, employment_start_date, status, remarks, created_at, updated_at, created_by)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', ?, datetime('now'), datetime('now'), ?)
    `).run(
        empId,
        first_name.trim(),
        last_name.trim(),
        fullName,
        assignedAreaId,
        coordinatorId,
        position_id ? parseInt(position_id) : null,
        department_id ? parseInt(department_id) : null,
        employment_start_date,
        remarks ? remarks.trim() : null,
        user.id
    );

    logAction(user.id, 'CREATE_EMPLOYEE', 'employees', result.lastInsertRowid, {
        employee_id: empId,
        full_name: fullName,
        area_id: assignedAreaId,
        department_id: department_id ? parseInt(department_id) : null
    });

    req.flash('success', `Employee ${fullName} (${empId}) added successfully.`);
    res.redirect(`/employees/${result.lastInsertRowid}`);
});

// GET /employees/:id
router.get('/:id', requireAuth, (req, res) => {
    const user = req.session.user;
    const db = getDb();
    const id = parseInt(req.params.id);

    const employee = db.prepare(`
        SELECT e.*, a.name as area_name, p.title as position_title, u.full_name as coordinator_name,
               d.name as dept_name
        FROM employees e
        JOIN areas a ON e.area_id = a.id
        LEFT JOIN positions p ON e.position_id = p.id
        LEFT JOIN departments d ON e.department_id = d.id
        LEFT JOIN users u ON e.coordinator_id = u.id
        WHERE e.id = ?
    `).get(id);

    if (!employee) {
        req.flash('error', 'Employee not found.');
        return res.redirect('/employees');
    }

    if (!validateAreaAccess(employee.area_id, req)) {
        return res.status(403).render('error', { title: 'Access Denied', message: 'You can only access employees within your assigned area.', code: 403 });
    }

    // Historical attendance summary
    const attendanceStats = db.prepare(`
        SELECT 
            COUNT(*) as total_records,
            SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present_count,
            SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late_count,
            SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent_count,
            SUM(CASE WHEN status IN ('leave', 'on_leave') THEN 1 ELSE 0 END) as leave_count,
            SUM(CASE WHEN status = 'no_work' THEN 1 ELSE 0 END) as nowork_count,
            SUM(CASE WHEN status = 'sent_home' THEN 1 ELSE 0 END) as senthome_count,
            SUM(CASE WHEN status = 'rest_day' THEN 1 ELSE 0 END) as restday_count
        FROM employee_attendance
        WHERE employee_id = ?
    `).get(id);

    // Recent attendance
    const recentAttendance = db.prepare(`
        SELECT ea.*, u.full_name as recorded_by_name
        FROM employee_attendance ea
        LEFT JOIN users u ON ea.recorded_by = u.id
        WHERE ea.employee_id = ?
        ORDER BY ea.work_date DESC
        LIMIT 10
    `).all(id);

    // BTW cases
    const btwCases = db.prepare(`
        SELECT * FROM btw_cases WHERE employee_id = ? ORDER BY created_at DESC
    `).all(id);

    // Concerns
    const concerns = db.prepare(`
        SELECT * FROM concern_reports WHERE employee_id = ? ORDER BY created_at DESC
    `).all(id);

    // Departments in this area for quick reassignment
    const departments = db.prepare('SELECT * FROM departments WHERE area_id = ? ORDER BY name ASC').all(employee.area_id);

    res.render('employees/view', {
        title: `${employee.full_name} - Employee Details`,
        employee,
        attendanceStats,
        recentAttendance,
        btwCases,
        concerns,
        departments
    });
});

// GET /employees/:id/edit
router.get('/:id/edit', requireAuth, (req, res) => {
    const user = req.session.user;
    const db = getDb();
    const id = parseInt(req.params.id);

    const employee = db.prepare('SELECT * FROM employees WHERE id = ?').get(id);
    if (!employee) {
        req.flash('error', 'Employee not found.');
        return res.redirect('/employees');
    }

    if (!validateAreaAccess(employee.area_id, req)) {
        return res.status(403).render('error', { title: 'Access Denied', message: 'You can only edit employees within your assigned area.', code: 403 });
    }

    const positions = db.prepare('SELECT * FROM positions WHERE is_active = 1 ORDER BY title ASC').all();
    const areas = user.role === 'COORDINATOR'
        ? [req.userArea]
        : db.prepare('SELECT * FROM areas WHERE is_active = 1 ORDER BY name ASC').all();

    const departments = db.prepare('SELECT * FROM departments WHERE area_id = ? ORDER BY name ASC').all(employee.area_id);

    res.render('employees/form', {
        title: `Edit ${employee.full_name} - TAASCOR`,
        employee,
        positions,
        departments,
        areas,
        isEdit: true
    });
});

// POST /employees/:id/edit
router.post('/:id/edit', requireAuth, (req, res) => {
    const user = req.session.user;
    const db = getDb();
    const id = parseInt(req.params.id);

    const employee = db.prepare('SELECT * FROM employees WHERE id = ?').get(id);
    if (!employee) {
        req.flash('error', 'Employee not found.');
        return res.redirect('/employees');
    }

    if (!validateAreaAccess(employee.area_id, req)) {
        return res.status(403).render('error', { title: 'Access Denied', message: 'You can only edit employees within your assigned area.', code: 403 });
    }

    const { first_name, last_name, position_id, department_id, employment_start_date, remarks, area_id } = req.body;
    const targetAreaId = user.role === 'COORDINATOR' ? req.userAreaId : (area_id ? parseInt(area_id) : employee.area_id);
    const fullName = `${first_name.trim()} ${last_name.trim()}`;

    db.prepare(`
        UPDATE employees 
        SET first_name = ?, last_name = ?, full_name = ?, position_id = ?, department_id = ?,
            area_id = ?, employment_start_date = ?, remarks = ?, updated_at = datetime('now'), updated_by = ?
        WHERE id = ?
    `).run(
        first_name.trim(),
        last_name.trim(),
        fullName,
        position_id ? parseInt(position_id) : null,
        department_id ? parseInt(department_id) : null,
        targetAreaId,
        employment_start_date,
        remarks ? remarks.trim() : null,
        user.id,
        id
    );

    logAction(user.id, 'UPDATE_EMPLOYEE', 'employees', id, { full_name: fullName, department_id: department_id ? parseInt(department_id) : null });

    req.flash('success', 'Employee updated successfully.');
    res.redirect(`/employees/${id}`);
});

// POST /employees/:id/assign-department
router.post('/:id/assign-department', requireAuth, (req, res) => {
    const user = req.session.user;
    const db = getDb();
    const id = parseInt(req.params.id);
    const departmentId = req.body.department_id ? parseInt(req.body.department_id) : null;

    const employee = db.prepare('SELECT * FROM employees WHERE id = ?').get(id);
    if (!employee) {
        req.flash('error', 'Employee not found.');
        return res.redirect('back');
    }

    if (!validateAreaAccess(employee.area_id, req)) {
        return res.status(403).render('error', { title: 'Access Denied', message: 'You cannot alter employees outside your assigned area.', code: 403 });
    }

    let deptName = 'Unassigned';
    if (departmentId) {
        const dept = db.prepare('SELECT * FROM departments WHERE id = ?').get(departmentId);
        if (dept) deptName = dept.name;
    }

    db.prepare("UPDATE employees SET department_id = ?, updated_at = datetime('now'), updated_by = ? WHERE id = ?")
        .run(departmentId, user.id, id);

    logAction(user.id, 'ASSIGN_EMPLOYEE_DEPARTMENT', 'employees', id, { full_name: employee.full_name, department_id: departmentId, department_name: deptName });
    req.flash('success', `${employee.full_name} assigned to "${deptName}".`);
    res.redirect('back');
});

// POST /employees/:id/toggle-status (Deactivate or Reactivate - preserves all historical records!)
router.post('/:id/toggle-status', requireAuth, (req, res) => {
    const user = req.session.user;
    const db = getDb();
    const id = parseInt(req.params.id);

    const employee = db.prepare('SELECT * FROM employees WHERE id = ?').get(id);
    if (!employee) {
        req.flash('error', 'Employee not found.');
        return res.redirect('/employees');
    }

    if (!validateAreaAccess(employee.area_id, req)) {
        return res.status(403).render('error', { title: 'Access Denied', message: 'You cannot alter employee status outside your assigned area.', code: 403 });
    }

    if (employee.status === 'active') {
        const { inactivation_reason } = req.body;
        if (!inactivation_reason) {
            req.flash('error', 'Please provide a reason for deactivation.');
            return res.redirect(`/employees/${id}`);
        }

        const now = new Date().toISOString().split('T')[0];
        db.prepare(`
            UPDATE employees 
            SET status = 'inactive', inactivation_date = ?, inactivation_reason = ?, updated_at = datetime('now'), updated_by = ?
            WHERE id = ?
        `).run(now, inactivation_reason.trim(), user.id, id);

        logAction(user.id, 'DEACTIVATE_EMPLOYEE', 'employees', id, { reason: inactivation_reason });
        req.flash('success', `${employee.full_name} has been marked inactive. All historical records have been preserved.`);
    } else {
        db.prepare(`
            UPDATE employees 
            SET status = 'active', inactivation_date = NULL, inactivation_reason = NULL, updated_at = datetime('now'), updated_by = ?
            WHERE id = ?
        `).run(user.id, id);

        logAction(user.id, 'REACTIVATE_EMPLOYEE', 'employees', id);
        req.flash('success', `${employee.full_name} has been reactivated.`);
    }

    res.redirect(`/employees/${id}`);
});

module.exports = router;
