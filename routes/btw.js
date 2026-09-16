const express = require('express');
const router = express.Router();
const { requireAuth } = require('../middleware/auth');
const { validateAreaAccess } = require('../middleware/area-guard');
const { getDb } = require('../db/database');
const { logAction } = require('../services/audit');
const { createNotification, notifyHR } = require('../services/notification');

// GET /btw (List BTW cases)
router.get('/', requireAuth, (req, res) => {
    const user = req.session.user;
    const db = getDb();
    const statusFilter = req.query.status || '';
    const selectedAreaId = user.role === 'COORDINATOR' ? req.userAreaId : (req.query.area_id ? parseInt(req.query.area_id) : null);

    let where = [];
    let params = [];

    if (selectedAreaId) {
        where.push('b.area_id = ?');
        params.push(selectedAreaId);
    }

    if (statusFilter) {
        where.push('b.status = ?');
        params.push(statusFilter);
    }

    const whereClause = where.length > 0 ? 'WHERE ' + where.join(' AND ') : '';

    const cases = db.prepare(`
        SELECT b.*, e.full_name as employee_name, e.employee_id as emp_code,
               a.name as area_name, u.full_name as coordinator_name,
               hr.full_name as hr_reviewer_name,
               (SELECT COUNT(*) FROM btw_case_entries WHERE case_id = b.id) as reply_count
        FROM btw_cases b
        JOIN employees e ON b.employee_id = e.id
        JOIN areas a ON b.area_id = a.id
        LEFT JOIN users u ON b.coordinator_id = u.id
        LEFT JOIN users hr ON b.hr_reviewer_id = hr.id
        ${whereClause}
        ORDER BY b.created_at DESC
    `).all(...params);

    const areas = user.role !== 'COORDINATOR' ? db.prepare('SELECT * FROM areas WHERE is_active = 1 ORDER BY name ASC').all() : [];

    res.render('btw/list', {
        title: 'Back-to-Work Clearance - TAASCOR',
        cases,
        statusFilter,
        selectedAreaId,
        areas
    });
});

// GET /btw/:id (Forum/Thread View)
router.get('/:id', requireAuth, (req, res) => {
    const user = req.session.user;
    const db = getDb();
    const id = parseInt(req.params.id);

    const btwCase = db.prepare(`
        SELECT b.*, e.full_name as employee_name, e.employee_id as emp_code, e.status as emp_status,
               p.title as position_title, a.name as area_name, u.full_name as coordinator_name,
               hr.full_name as hr_reviewer_name
        FROM btw_cases b
        JOIN employees e ON b.employee_id = e.id
        JOIN areas a ON b.area_id = a.id
        LEFT JOIN positions p ON e.position_id = p.id
        LEFT JOIN users u ON b.coordinator_id = u.id
        LEFT JOIN users hr ON b.hr_reviewer_id = hr.id
        WHERE b.id = ?
    `).get(id);

    if (!btwCase) {
        req.flash('error', 'Back-to-Work case not found.');
        return res.redirect('/btw');
    }

    if (!validateAreaAccess(btwCase.area_id, req)) {
        return res.status(403).render('error', { title: 'Access Denied', message: 'You cannot access cases outside your assigned area.', code: 403 });
    }

    let dates = [];
    try {
        dates = JSON.parse(btwCase.absence_dates);
    } catch (e) {
        dates = [btwCase.absence_dates];
    }

    const entries = db.prepare(`
        SELECT be.*, u.full_name as author_name
        FROM btw_case_entries be
        JOIN users u ON be.author_id = u.id
        WHERE be.case_id = ?
        ORDER BY be.created_at ASC
    `).all(id);

    res.render('btw/view', {
        title: `Case ${btwCase.case_ref} - Back-to-Work`,
        btwCase,
        dates,
        entries
    });
});

// POST /btw/:id/reply (Coordinator or HR adds remarks/explanation)
router.post('/:id/reply', requireAuth, (req, res) => {
    const user = req.session.user;
    const db = getDb();
    const id = parseInt(req.params.id);
    const { content } = req.body;

    if (!content || !content.trim()) {
        req.flash('error', 'Message cannot be empty.');
        return res.redirect(`/btw/${id}`);
    }

    const btwCase = db.prepare('SELECT * FROM btw_cases WHERE id = ?').get(id);
    if (!btwCase) {
        req.flash('error', 'Case not found.');
        return res.redirect('/btw');
    }

    if (!validateAreaAccess(btwCase.area_id, req)) {
        return res.status(403).render('error', { title: 'Access Denied', message: 'Access denied.', code: 403 });
    }

    db.prepare(`
        INSERT INTO btw_case_entries (case_id, author_id, author_role, content, entry_type, created_at)
        VALUES (?, ?, ?, ?, 'comment', datetime('now'))
    `).run(id, user.id, user.role, content.trim());

    db.prepare("UPDATE btw_cases SET updated_at = datetime('now') WHERE id = ?").run(id);

    if (user.role === 'COORDINATOR') {
        notifyHR(
            `BTW Update: ${btwCase.case_ref}`,
            `Coordinator added remarks/explanation for Case ${btwCase.case_ref}.`,
            `/btw/${id}`,
            `btw-reply-${id}-${Date.now()}`
        );
    } else {
        createNotification(
            btwCase.coordinator_id,
            `HR Remark: ${btwCase.case_ref}`,
            `HR posted remarks on Case ${btwCase.case_ref}.`,
            `/btw/${id}`,
            `btw-hr-reply-${id}-${Date.now()}`
        );
    }

    req.flash('success', 'Update added to case thread.');
    res.redirect(`/btw/${id}`);
});

// POST /btw/:id/decision (HR / ADMIN Decision)
router.post('/:id/decision', requireAuth, (req, res) => {
    const user = req.session.user;
    if (user.role !== 'HR' && user.role !== 'ADMIN') {
        return res.status(403).render('error', { title: 'Access Denied', message: 'Only HR or Administrator can record Back-to-Work clearance decisions.', code: 403 });
    }

    const db = getDb();
    const id = parseInt(req.params.id);
    const { decision, authorized_return_date, hr_remarks } = req.body;

    if (!decision || !['approved', 'not_approved', 'for_clarification'].includes(decision)) {
        req.flash('error', 'Invalid decision status.');
        return res.redirect(`/btw/${id}`);
    }

    if (decision === 'approved' && !authorized_return_date) {
        req.flash('error', 'Please provide an authorized return date when approving clearance.');
        return res.redirect(`/btw/${id}`);
    }

    const btwCase = db.prepare('SELECT * FROM btw_cases WHERE id = ?').get(id);
    if (!btwCase) {
        req.flash('error', 'Case not found.');
        return res.redirect('/btw');
    }

    db.prepare(`
        UPDATE btw_cases 
        SET status = ?, hr_reviewer_id = ?, hr_decision = ?, hr_remarks = ?, 
            decision_at = datetime('now'), authorized_return_date = ?, updated_at = datetime('now')
        WHERE id = ?
    `).run(
        decision,
        user.id,
        decision.toUpperCase(),
        hr_remarks ? hr_remarks.trim() : null,
        decision === 'approved' ? authorized_return_date : null,
        id
    );

    // Add decision entry into thread
    let decisionText = `Official HR Decision: ${decision.replace(/_/g, ' ').toUpperCase()}`;
    if (decision === 'approved') {
        decisionText += ` | Authorized Return Date: ${authorized_return_date}`;
    }
    if (hr_remarks) {
        decisionText += `\nRemarks: ${hr_remarks.trim()}`;
    }

    db.prepare(`
        INSERT INTO btw_case_entries (case_id, author_id, author_role, content, entry_type, created_at)
        VALUES (?, ?, 'HR', ?, 'decision', datetime('now'))
    `).run(id, user.id, decisionText);

    // Notify coordinator immediately
    createNotification(
        btwCase.coordinator_id,
        `BTW Decision: ${btwCase.case_ref} - ${decision.toUpperCase()}`,
        `HR has decided: ${decision.replace(/_/g, ' ').toUpperCase()} on Case ${btwCase.case_ref}.`,
        `/btw/${id}`,
        `btw-decision-${id}-${Date.now()}`
    );

    logAction(user.id, 'RECORD_BTW_DECISION', 'btw_cases', id, {
        decision,
        authorized_return_date,
        hr_remarks
    });

    req.flash('success', `Decision recorded: ${decision.replace(/_/g, ' ').toUpperCase()}`);
    res.redirect(`/btw/${id}`);
});

module.exports = router;
