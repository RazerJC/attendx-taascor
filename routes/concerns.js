const express = require('express');
const router = express.Router();
const { requireAuth } = require('../middleware/auth');
const { validateAreaAccess } = require('../middleware/area-guard');
const { getDb } = require('../db/database');
const { logAction } = require('../services/audit');
const { notifyHR, createNotification } = require('../services/notification');

// GET /concerns (Forum-style list)
router.get('/', requireAuth, (req, res) => {
    const user = req.session.user;
    const db = getDb();
    const statusFilter = req.query.status || '';
    const selectedAreaId = user.role === 'COORDINATOR' ? req.userAreaId : (req.query.area_id ? parseInt(req.query.area_id) : null);

    let where = [];
    let params = [];

    if (selectedAreaId) {
        where.push('c.area_id = ?');
        params.push(selectedAreaId);
    }

    if (statusFilter) {
        where.push('c.status = ?');
        params.push(statusFilter);
    }

    const whereClause = where.length > 0 ? 'WHERE ' + where.join(' AND ') : '';

    const concerns = db.prepare(`
        SELECT c.*, e.full_name as employee_name, e.employee_id as emp_code,
               a.name as area_name, u.full_name as reported_by_name,
               (SELECT COUNT(*) FROM concern_entries WHERE concern_id = c.id) as reply_count
        FROM concern_reports c
        JOIN employees e ON c.employee_id = e.id
        JOIN areas a ON c.area_id = a.id
        LEFT JOIN users u ON c.reported_by = u.id
        ${whereClause}
        ORDER BY c.created_at DESC
    `).all(...params);

    const areas = user.role !== 'COORDINATOR' ? db.prepare('SELECT * FROM areas WHERE is_active = 1 ORDER BY name ASC').all() : [];

    res.render('concerns/list', {
        title: 'Employee Concerns - TAASCOR',
        concerns,
        statusFilter,
        selectedAreaId,
        areas
    });
});

// GET /concerns/new
router.get('/new', requireAuth, (req, res) => {
    const user = req.session.user;
    const db = getDb();

    const selectedAreaId = user.role === 'COORDINATOR' ? req.userAreaId : (req.query.area_id ? parseInt(req.query.area_id) : null);
    let empWhere = ["status = 'active'"];
    let empParams = [];
    if (selectedAreaId) {
        empWhere.push('area_id = ?');
        empParams.push(selectedAreaId);
    }

    const employees = db.prepare(`SELECT id, employee_id, full_name, area_id FROM employees WHERE ${empWhere.join(' AND ')} ORDER BY full_name ASC`).all(...empParams);
    const preselectedEmpId = req.query.employee_id ? parseInt(req.query.employee_id) : null;

    res.render('concerns/form', {
        title: 'Report Employee Concern - TAASCOR',
        employees,
        preselectedEmpId
    });
});

// POST /concerns
router.post('/', requireAuth, (req, res) => {
    const user = req.session.user;
    const { employee_id, category, description } = req.body;
    const db = getDb();

    if (!employee_id || !category || !description) {
        req.flash('error', 'Please fill in all required fields.');
        return res.redirect('/concerns/new');
    }

    const emp = db.prepare('SELECT * FROM employees WHERE id = ?').get(parseInt(employee_id));
    if (!emp) {
        req.flash('error', 'Employee not found.');
        return res.redirect('/concerns/new');
    }

    if (!validateAreaAccess(emp.area_id, req)) {
        return res.status(403).render('error', { title: 'Access Denied', message: 'You cannot submit concern reports for employees outside your area.', code: 403 });
    }

    const result = db.prepare(`
        INSERT INTO concern_reports (employee_id, area_id, category, description, reported_by, status, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, 'submitted', datetime('now'), datetime('now'))
    `).run(emp.id, emp.area_id, category, description.trim(), user.id);

    const concernId = result.lastInsertRowid;

    // Initial thread message
    db.prepare(`
        INSERT INTO concern_entries (concern_id, author_id, author_role, content, entry_type, created_at)
        VALUES (?, ?, ?, ?, 'comment', datetime('now'))
    `).run(concernId, user.id, user.role, description.trim());

    // Notify HR
    notifyHR(
        `Concern Reported: ${emp.full_name}`,
        `${user.full_name} submitted a concern for ${emp.full_name} (${category}).`,
        `/concerns/${concernId}`,
        `concern-new-${concernId}`
    );

    logAction(user.id, 'SUBMIT_CONCERN_REPORT', 'concern_reports', concernId, {
        employee_id: emp.id,
        category
    });

    req.flash('success', 'Employee concern report submitted successfully.');
    res.redirect(`/concerns/${concernId}`);
});

// GET /concerns/:id (Thread view)
router.get('/:id', requireAuth, (req, res) => {
    const user = req.session.user;
    const db = getDb();
    const id = parseInt(req.params.id);

    const concern = db.prepare(`
        SELECT c.*, e.full_name as employee_name, e.employee_id as emp_code,
               a.name as area_name, u.full_name as reported_by_name
        FROM concern_reports c
        JOIN employees e ON c.employee_id = e.id
        JOIN areas a ON c.area_id = a.id
        LEFT JOIN users u ON c.reported_by = u.id
        WHERE c.id = ?
    `).get(id);

    if (!concern) {
        req.flash('error', 'Concern report not found.');
        return res.redirect('/concerns');
    }

    if (!validateAreaAccess(concern.area_id, req)) {
        return res.status(403).render('error', { title: 'Access Denied', message: 'You cannot access concern reports outside your assigned area.', code: 403 });
    }

    const entries = db.prepare(`
        SELECT ce.*, u.full_name as author_name 
        FROM concern_entries ce
        JOIN users u ON ce.author_id = u.id
        WHERE ce.concern_id = ?
        ORDER BY ce.created_at ASC
    `).all(id);

    res.render('concerns/view', {
        title: `Concern: ${concern.employee_name} - TAASCOR`,
        concern,
        entries
    });
});

// POST /concerns/:id/reply (Add update or HR instruction)
router.post('/:id/reply', requireAuth, (req, res) => {
    const user = req.session.user;
    const db = getDb();
    const id = parseInt(req.params.id);
    const { content, new_status } = req.body;

    if (!content || !content.trim()) {
        req.flash('error', 'Message cannot be empty.');
        return res.redirect(`/concerns/${id}`);
    }

    const concern = db.prepare('SELECT * FROM concern_reports WHERE id = ?').get(id);
    if (!concern) {
        req.flash('error', 'Concern report not found.');
        return res.redirect('/concerns');
    }

    if (!validateAreaAccess(concern.area_id, req)) {
        return res.status(403).render('error', { title: 'Access Denied', message: 'Access denied.', code: 403 });
    }

    const entryType = user.role === 'COORDINATOR' ? 'comment' : 'instruction';

    db.prepare(`
        INSERT INTO concern_entries (concern_id, author_id, author_role, content, entry_type, created_at)
        VALUES (?, ?, ?, ?, ?, datetime('now'))
    `).run(id, user.id, user.role, content.trim(), entryType);

    // Update status if provided (HR or Admin can update status)
    if (new_status && ['submitted', 'under_review', 'for_employee_reporting', 'resolved'].includes(new_status)) {
        if (user.role === 'HR' || user.role === 'ADMIN') {
            db.prepare(`
                UPDATE concern_reports 
                SET status = ?, updated_at = datetime('now') 
                WHERE id = ?
            `).run(new_status, id);

            db.prepare(`
                INSERT INTO concern_entries (concern_id, author_id, author_role, content, entry_type, created_at)
                VALUES (?, ?, 'SYSTEM', ?, 'status_change', datetime('now'))
            `).run(id, user.id, `Status changed to: ${new_status.replace(/_/g, ' ').toUpperCase()}`);
        }
    }

    // Send notifications
    if (user.role === 'COORDINATOR') {
        notifyHR(
            'Concern Updated by Coordinator',
            `Coordinator added an update to concern #${id}.`,
            `/concerns/${id}`,
            `concern-reply-${id}-${Date.now()}`
        );
    } else {
        createNotification(
            concern.reported_by,
            'HR Response to Concern',
            `HR added instructions/response to concern #${id}.`,
            `/concerns/${id}`,
            `concern-hr-reply-${id}-${Date.now()}`
        );
    }

    logAction(user.id, 'REPLY_CONCERN_REPORT', 'concern_reports', id, { new_status });

    req.flash('success', 'Response recorded.');
    res.redirect(`/concerns/${id}`);
});

module.exports = router;
