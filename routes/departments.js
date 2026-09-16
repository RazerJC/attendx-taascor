const express = require('express');
const router = express.Router();
const { requireAuth } = require('../middleware/auth');
const { requireArea, validateAreaAccess } = require('../middleware/area-guard');
const { getDb } = require('../db/database');
const { logAction } = require('../services/audit');

// Helper to determine if request is AJAX
function isAjax(req) {
    return req.xhr || (req.headers.accept && req.headers.accept.includes('application/json')) || req.path.startsWith('/api/');
}

// GET /departments
router.get('/', requireAuth, (req, res) => {
    const user = req.session.user;
    const db = getDb();
    const selectedAreaId = user.role === 'COORDINATOR' ? req.userAreaId : (req.query.area_id ? parseInt(req.query.area_id) : null);
    const search = req.query.search ? req.query.search.trim() : '';

    let deptWhere = [];
    let deptParams = [];

    if (selectedAreaId) {
        deptWhere.push('d.area_id = ?');
        deptParams.push(selectedAreaId);
    }

    if (search) {
        deptWhere.push('d.name LIKE ?');
        deptParams.push(`%${search}%`);
    }

    const whereClause = deptWhere.length > 0 ? 'WHERE ' + deptWhere.join(' AND ') : '';

    const departments = db.prepare(`
        SELECT d.*, a.name as area_name, u.full_name as creator_name,
               (SELECT COUNT(*) FROM employees WHERE department_id = d.id AND status = 'active') as employee_count
        FROM departments d
        JOIN areas a ON d.area_id = a.id
        LEFT JOIN users u ON d.created_by = u.id
        ${whereClause}
        ORDER BY a.name ASC, d.name ASC
    `).all(...deptParams);

    // Fetch employees per department for expandable cards
    const deptIds = departments.map(d => d.id);
    let deptEmployeesMap = {};
    if (deptIds.length > 0) {
        const placeholders = deptIds.map(() => '?').join(',');
        const emps = db.prepare(`
            SELECT e.id, e.employee_id, e.full_name, e.first_name, e.last_name, e.department_id, p.title as position_title
            FROM employees e
            LEFT JOIN positions p ON e.position_id = p.id
            WHERE e.department_id IN (${placeholders}) AND e.status = 'active'
            ORDER BY e.last_name ASC, e.first_name ASC
        `).all(...deptIds);

        for (const emp of emps) {
            if (!deptEmployeesMap[emp.department_id]) {
                deptEmployeesMap[emp.department_id] = [];
            }
            deptEmployeesMap[emp.department_id].push(emp);
        }
    }

    // Also get unassigned employees in this area
    let unassignedWhere = ["e.status = 'active'", "e.department_id IS NULL"];
    let unassignedParams = [];
    if (selectedAreaId) {
        unassignedWhere.push("e.area_id = ?");
        unassignedParams.push(selectedAreaId);
    }
    const unassignedEmployees = db.prepare(`
        SELECT e.id, e.employee_id, e.full_name, e.first_name, e.last_name, a.name as area_name, p.title as position_title
        FROM employees e
        JOIN areas a ON e.area_id = a.id
        LEFT JOIN positions p ON e.position_id = p.id
        WHERE ${unassignedWhere.join(' AND ')}
        ORDER BY e.full_name ASC
    `).all(...unassignedParams);

    const areas = user.role !== 'COORDINATOR' ? db.prepare('SELECT * FROM areas WHERE is_active = 1 ORDER BY name ASC').all() : [];

    res.render('departments/list', {
        title: 'Departments Management - TAASCOR',
        departments,
        deptEmployeesMap,
        unassignedEmployees,
        selectedAreaId,
        areas,
        search
    });
});

// POST /departments (Create Department)
router.post('/', requireAuth, (req, res) => {
    const user = req.session.user;
    const db = getDb();
    const name = (req.body.name || '').trim();
    const description = (req.body.description || '').trim();
    const areaId = user.role === 'COORDINATOR' ? req.userAreaId : parseInt(req.body.area_id);

    if (!name) {
        if (isAjax(req)) return res.status(400).json({ success: false, error: 'Department name is required.' });
        req.flash('error', 'Department name is required.');
        return res.redirect('/departments');
    }

    if (!areaId || !validateAreaAccess(areaId, req)) {
        if (isAjax(req)) return res.status(403).json({ success: false, error: 'Unauthorized area selection.' });
        req.flash('error', 'Unauthorized area selection.');
        return res.redirect('/departments');
    }

    // Check duplicate in same area
    const existing = db.prepare('SELECT id FROM departments WHERE area_id = ? AND name = ? COLLATE NOCASE').get(areaId, name);
    if (existing) {
        if (isAjax(req)) return res.status(400).json({ success: false, error: `Department "${name}" already exists in this area.` });
        req.flash('error', `Department "${name}" already exists in this area.`);
        return res.redirect('/departments');
    }

    const result = db.prepare(`
        INSERT INTO departments (area_id, name, description, created_by, created_at, updated_at)
        VALUES (?, ?, ?, ?, datetime('now'), datetime('now'))
    `).run(areaId, name, description || null, user.id);

    logAction(user.id, 'CREATE_DEPARTMENT', 'departments', result.lastInsertRowid, { name, area_id: areaId });

    if (isAjax(req)) {
        return res.json({ success: true, id: result.lastInsertRowid, name, area_id: areaId });
    }

    req.flash('success', `Department "${name}" created successfully.`);
    res.redirect('/departments');
});

// POST /departments/:id/edit (Rename / Update Department)
router.post('/:id/edit', requireAuth, (req, res) => {
    const user = req.session.user;
    const db = getDb();
    const deptId = parseInt(req.params.id);
    const name = (req.body.name || '').trim();
    const description = (req.body.description || '').trim();

    if (!name) {
        if (isAjax(req)) return res.status(400).json({ success: false, error: 'Department name cannot be empty.' });
        req.flash('error', 'Department name cannot be empty.');
        return res.redirect('/departments');
    }

    const dept = db.prepare('SELECT * FROM departments WHERE id = ?').get(deptId);
    if (!dept) {
        if (isAjax(req)) return res.status(404).json({ success: false, error: 'Department not found.' });
        req.flash('error', 'Department not found.');
        return res.redirect('/departments');
    }

    if (!validateAreaAccess(dept.area_id, req)) {
        if (isAjax(req)) return res.status(403).json({ success: false, error: 'Access denied for this department.' });
        req.flash('error', 'Access denied for this department.');
        return res.redirect('/departments');
    }

    // Check duplicate
    const duplicate = db.prepare('SELECT id FROM departments WHERE area_id = ? AND name = ? COLLATE NOCASE AND id != ?')
        .get(dept.area_id, name, deptId);
    if (duplicate) {
        if (isAjax(req)) return res.status(400).json({ success: false, error: `Another department named "${name}" already exists in this area.` });
        req.flash('error', `Another department named "${name}" already exists in this area.`);
        return res.redirect('/departments');
    }

    db.prepare(`
        UPDATE departments 
        SET name = ?, description = ?, updated_at = datetime('now')
        WHERE id = ?
    `).run(name, description || dept.description, deptId);

    logAction(user.id, 'UPDATE_DEPARTMENT', 'departments', deptId, { old_name: dept.name, new_name: name });

    if (isAjax(req)) {
        return res.json({ success: true, id: deptId, name });
    }

    req.flash('success', `Department renamed to "${name}".`);
    res.redirect('/departments');
});

// POST /departments/:id/delete (Delete Department)
router.post('/:id/delete', requireAuth, (req, res) => {
    const user = req.session.user;
    const db = getDb();
    const deptId = parseInt(req.params.id);

    const dept = db.prepare('SELECT * FROM departments WHERE id = ?').get(deptId);
    if (!dept) {
        if (isAjax(req)) return res.status(404).json({ success: false, error: 'Department not found.' });
        req.flash('error', 'Department not found.');
        return res.redirect('/departments');
    }

    if (!validateAreaAccess(dept.area_id, req)) {
        if (isAjax(req)) return res.status(403).json({ success: false, error: 'Access denied.' });
        req.flash('error', 'Access denied.');
        return res.redirect('/departments');
    }

    // Check if any employees assigned
    const assignedCount = db.prepare('SELECT COUNT(*) as count FROM employees WHERE department_id = ?').get(deptId).count;
    if (assignedCount > 0) {
        const msg = `Cannot delete department "${dept.name}". It currently has ${assignedCount} employee(s) assigned. Please reassign them first.`;
        if (isAjax(req)) return res.status(400).json({ success: false, error: msg });
        req.flash('error', msg);
        return res.redirect('/departments');
    }

    db.prepare('DELETE FROM departments WHERE id = ?').run(deptId);
    logAction(user.id, 'DELETE_DEPARTMENT', 'departments', deptId, { name: dept.name });

    if (isAjax(req)) {
        return res.json({ success: true });
    }

    req.flash('success', `Department "${dept.name}" deleted.`);
    res.redirect('/departments');
});

// POST /departments/assign-employee (Assign or Reassign employee to department)
router.post('/assign-employee', requireAuth, (req, res) => {
    const user = req.session.user;
    const db = getDb();
    const employeeId = parseInt(req.body.employee_id);
    const departmentId = req.body.department_id ? parseInt(req.body.department_id) : null;

    if (!employeeId) {
        if (isAjax(req)) return res.status(400).json({ success: false, error: 'Employee ID is required.' });
        req.flash('error', 'Employee ID is required.');
        return res.redirect('back');
    }

    const employee = db.prepare('SELECT * FROM employees WHERE id = ?').get(employeeId);
    if (!employee) {
        if (isAjax(req)) return res.status(404).json({ success: false, error: 'Employee not found.' });
        req.flash('error', 'Employee not found.');
        return res.redirect('back');
    }

    if (!validateAreaAccess(employee.area_id, req)) {
        if (isAjax(req)) return res.status(403).json({ success: false, error: 'Access denied for this employee.' });
        req.flash('error', 'Access denied for this employee.');
        return res.redirect('back');
    }

    let deptName = 'Unassigned';
    if (departmentId) {
        const dept = db.prepare('SELECT * FROM departments WHERE id = ?').get(departmentId);
        if (!dept) {
            if (isAjax(req)) return res.status(404).json({ success: false, error: 'Department not found.' });
            req.flash('error', 'Department not found.');
            return res.redirect('back');
        }
        deptName = dept.name;
    }

    db.prepare(`
        UPDATE employees 
        SET department_id = ?, updated_at = datetime('now'), updated_by = ?
        WHERE id = ?
    `).run(departmentId, user.id, employeeId);

    logAction(user.id, 'ASSIGN_EMPLOYEE_DEPARTMENT', 'employees', employeeId, {
        full_name: employee.full_name,
        department_id: departmentId,
        department_name: deptName
    });

    if (isAjax(req)) {
        return res.json({ success: true, employee_id: employeeId, department_id: departmentId, department_name: deptName });
    }

    req.flash('success', `${employee.full_name} assigned to "${deptName}".`);
    res.redirect('back');
});

module.exports = router;
