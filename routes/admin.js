const express = require('express');
const router = express.Router();
const bcrypt = require('bcryptjs');
const { requireAuth } = require('../middleware/auth');
const { getDb } = require('../db/database');
const { logAction, getAuditLogs } = require('../services/audit');
const { sendAccountApprovedEmail } = require('../services/email');
const { createNotification } = require('../services/notification');

// Middleware to ensure HR or ADMIN
function requireAdminOrHR(req, res, next) {
    if (req.session.user?.role !== 'ADMIN' && req.session.user?.role !== 'HR') {
        return res.status(403).render('error', { title: 'Access Denied', message: 'You do not have permission to access management features.', code: 403 });
    }
    next();
}

function requireAdmin(req, res, next) {
    if (req.session.user?.role !== 'ADMIN') {
        return res.status(403).render('error', { title: 'Access Denied', message: 'This page is restricted to Administrators only.', code: 403 });
    }
    next();
}

// GET /admin/pending-coordinators (HR & ADMIN)
router.get('/pending-coordinators', requireAuth, requireAdminOrHR, (req, res) => {
    const db = getDb();
    const pendingList = db.prepare(`
        SELECT * FROM users 
        WHERE role = 'COORDINATOR' AND status = 'pending'
        ORDER BY created_at ASC
    `).all();

    const areas = db.prepare('SELECT * FROM areas WHERE is_active = 1 ORDER BY name ASC').all();

    res.render('admin/pending-coordinators', {
        title: 'Pending Coordinator Registrations - TAASCOR',
        pendingList,
        areas
    });
});

// POST /admin/approve-coordinator (HR & ADMIN: Assign area and activate)
router.post('/approve-coordinator', requireAuth, requireAdminOrHR, async (req, res) => {
    const user = req.session.user;
    const db = getDb();
    const { user_id, area_id, remarks } = req.body;

    if (!user_id || !area_id) {
        req.flash('error', 'You must assign the coordinator to an area or warehouse before approving.');
        return res.redirect('/admin/pending-coordinators');
    }

    const coordinator = db.prepare('SELECT * FROM users WHERE id = ? AND role = "COORDINATOR"').get(parseInt(user_id));
    if (!coordinator) {
        req.flash('error', 'Coordinator not found.');
        return res.redirect('/admin/pending-coordinators');
    }

    const area = db.prepare('SELECT * FROM areas WHERE id = ?').get(parseInt(area_id));

    // End any existing assignment
    db.prepare(`
        UPDATE coordinator_area_assignments 
        SET is_current = 0, ended_at = datetime('now') 
        WHERE user_id = ? AND is_current = 1
    `).run(coordinator.id);

    // Insert new assignment
    db.prepare(`
        INSERT INTO coordinator_area_assignments (user_id, area_id, assigned_by, assigned_at, is_current, remarks)
        VALUES (?, ?, ?, datetime('now'), 1, ?)
    `).run(coordinator.id, area.id, user.id, remarks ? remarks.trim() : null);

    // Update user status to active
    db.prepare(`
        UPDATE users 
        SET status = 'active', approved_by = ?, approved_at = datetime('now'), updated_at = datetime('now')
        WHERE id = ?
    `).run(user.id, coordinator.id);

    logAction(user.id, 'APPROVE_COORDINATOR', 'users', coordinator.id, {
        area_id: area.id,
        area_name: area.name,
        remarks
    });

    createNotification(
        coordinator.id,
        'Account Approved!',
        `Your coordinator registration has been approved. You are assigned to: ${area.name}.`,
        '/dashboard'
    );

    try {
        await sendAccountApprovedEmail(coordinator.email, coordinator.full_name);
    } catch (e) {
        console.error('Email error:', e.message);
    }

    req.flash('success', `Coordinator ${coordinator.full_name} approved and assigned to ${area.name}.`);
    res.redirect('/admin/pending-coordinators');
});

// POST /admin/reject-coordinator (HR & ADMIN)
router.post('/reject-coordinator', requireAuth, requireAdminOrHR, (req, res) => {
    const user = req.session.user;
    const db = getDb();
    const { user_id, rejection_remarks } = req.body;

    if (!user_id || !rejection_remarks) {
        req.flash('error', 'Please provide rejection remarks.');
        return res.redirect('/admin/pending-coordinators');
    }

    db.prepare(`
        UPDATE users 
        SET status = 'rejected', rejection_remarks = ?, updated_at = datetime('now')
        WHERE id = ?
    `).run(rejection_remarks.trim(), parseInt(user_id));

    logAction(user.id, 'REJECT_COORDINATOR', 'users', parseInt(user_id), { remarks: rejection_remarks });

    req.flash('success', 'Coordinator registration rejected.');
    res.redirect('/admin/pending-coordinators');
});

// POST /admin/reassign-coordinator (HR & ADMIN)
router.post('/reassign-coordinator', requireAuth, requireAdminOrHR, (req, res) => {
    const user = req.session.user;
    const db = getDb();
    const { user_id, new_area_id, remarks } = req.body;

    if (!user_id || !new_area_id) {
        req.flash('error', 'Please specify coordinator and new area.');
        return res.redirect('back');
    }

    const coordId = parseInt(user_id);
    const newArea = db.prepare('SELECT * FROM areas WHERE id = ?').get(parseInt(new_area_id));

    // End previous assignment while preserving history
    db.prepare(`
        UPDATE coordinator_area_assignments 
        SET is_current = 0, ended_at = datetime('now') 
        WHERE user_id = ? AND is_current = 1
    `).run(coordId);

    // Create new assignment
    db.prepare(`
        INSERT INTO coordinator_area_assignments (user_id, area_id, assigned_by, assigned_at, is_current, remarks)
        VALUES (?, ?, ?, datetime('now'), 1, ?)
    `).run(coordId, newArea.id, user.id, remarks ? remarks.trim() : 'Reassigned by HR');

    logAction(user.id, 'REASSIGN_COORDINATOR', 'coordinator_area_assignments', null, {
        coordinator_id: coordId,
        new_area: newArea.name
    });

    createNotification(
        coordId,
        'Assignment Updated',
        `Your assigned area has been updated to: ${newArea.name}.`,
        '/dashboard'
    );

    req.flash('success', `Coordinator reassigned to ${newArea.name}. History preserved.`);
    res.redirect('back');
});

// GET /admin/users (ADMIN only)
router.get('/users', requireAuth, requireAdmin, (req, res) => {
    const db = getDb();
    const users = db.prepare(`
        SELECT u.*, a.name as current_area_name, caa.assigned_at as area_assigned_at
        FROM users u
        LEFT JOIN coordinator_area_assignments caa ON caa.user_id = u.id AND caa.is_current = 1
        LEFT JOIN areas a ON caa.area_id = a.id
        ORDER BY u.created_at DESC
    `).all();

    const areas = db.prepare('SELECT * FROM areas WHERE is_active = 1 ORDER BY name ASC').all();

    res.render('admin/users', {
        title: 'User Management - TAASCOR',
        users,
        areas
    });
});

// POST /admin/create-hr (ADMIN only)
router.post('/create-hr', requireAuth, requireAdmin, (req, res) => {
    const admin = req.session.user;
    const db = getDb();
    const { email, password, full_name } = req.body;

    if (!email || !password || !full_name) {
        req.flash('error', 'All fields are required.');
        return res.redirect('/admin/users');
    }

    const trimmedEmail = email.trim().toLowerCase();
    const existing = db.prepare('SELECT id FROM users WHERE email = ? COLLATE NOCASE').get(trimmedEmail);
    if (existing) {
        req.flash('error', 'An account with that email already exists.');
        return res.redirect('/admin/users');
    }

    const hash = bcrypt.hashSync(password, 12);

    db.prepare(`
        INSERT INTO users (email, password_hash, full_name, role, status, email_verified, must_change_password, created_at, updated_at)
        VALUES (?, ?, ?, 'HR', 'active', 1, 1, datetime('now'), datetime('now'))
    `).run(trimmedEmail, hash, full_name.trim());

    logAction(admin.id, 'CREATE_HR_USER', 'users', null, { email: trimmedEmail });

    req.flash('success', `HR account created for ${full_name} (${trimmedEmail}).`);
    res.redirect('/admin/users');
});

// POST /admin/users/:id/toggle-status (ADMIN only)
router.post('/users/:id/toggle-status', requireAuth, requireAdmin, (req, res) => {
    const admin = req.session.user;
    const db = getDb();
    const id = parseInt(req.params.id);

    if (id === admin.id) {
        req.flash('error', 'You cannot suspend your own admin account.');
        return res.redirect('/admin/users');
    }

    const targetUser = db.prepare('SELECT * FROM users WHERE id = ?').get(id);
    if (!targetUser) {
        req.flash('error', 'User not found.');
        return res.redirect('/admin/users');
    }

    const newStatus = targetUser.status === 'active' ? 'suspended' : 'active';
    db.prepare("UPDATE users SET status = ?, updated_at = datetime('now') WHERE id = ?").run(newStatus, id);

    logAction(admin.id, 'TOGGLE_USER_STATUS', 'users', id, { newStatus });

    req.flash('success', `User ${targetUser.full_name} is now ${newStatus}.`);
    res.redirect('/admin/users');
});

// GET /admin/areas (ADMIN & HR)
router.get('/areas', requireAuth, requireAdminOrHR, (req, res) => {
    const db = getDb();
    const areas = db.prepare(`
        SELECT a.*,
               (SELECT COUNT(*) FROM employees WHERE area_id = a.id AND status = 'active') as active_workers,
               (SELECT u.full_name FROM coordinator_area_assignments caa JOIN users u ON caa.user_id = u.id WHERE caa.area_id = a.id AND caa.is_current = 1 LIMIT 1) as current_coordinator
        FROM areas a
        ORDER BY a.name ASC
    `).all();

    res.render('admin/areas', {
        title: 'Warehouses & Areas - TAASCOR',
        areas
    });
});

// POST /admin/areas
router.post('/areas', requireAuth, requireAdminOrHR, (req, res) => {
    const user = req.session.user;
    const db = getDb();
    const { name, description } = req.body;

    if (!name || !name.trim()) {
        req.flash('error', 'Area name is required.');
        return res.redirect('/admin/areas');
    }

    try {
        db.prepare(`
            INSERT INTO areas (name, description, is_active, created_at, created_by)
            VALUES (?, ?, 1, datetime('now'), ?)
        `).run(name.trim(), description ? description.trim() : null, user.id);

        logAction(user.id, 'CREATE_AREA', 'areas', null, { name: name.trim() });
        req.flash('success', `Area "${name.trim()}" added successfully.`);
    } catch (e) {
        req.flash('error', 'An area with this name already exists.');
    }

    res.redirect('/admin/areas');
});

// GET /admin/positions (ADMIN & HR)
router.get('/positions', requireAuth, requireAdminOrHR, (req, res) => {
    const db = getDb();
    const positions = db.prepare(`
        SELECT p.*, (SELECT COUNT(*) FROM employees WHERE position_id = p.id AND status = 'active') as active_workers
        FROM positions p
        ORDER BY p.title ASC
    `).all();

    res.render('admin/positions', {
        title: 'Positions - TAASCOR',
        positions
    });
});

// POST /admin/positions
router.post('/positions', requireAuth, requireAdminOrHR, (req, res) => {
    const user = req.session.user;
    const db = getDb();
    const { title, description } = req.body;

    if (!title || !title.trim()) {
        req.flash('error', 'Position title is required.');
        return res.redirect('/admin/positions');
    }

    try {
        db.prepare(`
            INSERT INTO positions (title, description, is_active, created_at, created_by)
            VALUES (?, ?, 1, datetime('now'), ?)
        `).run(title.trim(), description ? description.trim() : null, user.id);

        logAction(user.id, 'CREATE_POSITION', 'positions', null, { title: title.trim() });
        req.flash('success', `Position "${title.trim()}" added successfully.`);
    } catch (e) {
        req.flash('error', 'A position with this title already exists.');
    }

    res.redirect('/admin/positions');
});

// GET /admin/audit-logs (ADMIN only)
router.get('/audit-logs', requireAuth, requireAdmin, (req, res) => {
    const page = parseInt(req.query.page) || 1;
    const limit = 50;
    const offset = (page - 1) * limit;

    const filters = {
        action: req.query.action || '',
        entityType: req.query.entity_type || '',
        dateFrom: req.query.date_from || '',
        dateTo: req.query.date_to || ''
    };

    const { logs, total } = getAuditLogs(filters, limit, offset);
    const totalPages = Math.ceil(total / limit);

    res.render('admin/audit-logs', {
        title: 'Audit Logs - TAASCOR',
        logs,
        total,
        page,
        totalPages,
        filters
    });
});

module.exports = router;
