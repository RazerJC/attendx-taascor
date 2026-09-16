const express = require('express');
const router = express.Router();
const { requireAuth } = require('../middleware/auth');
const { getDb } = require('../db/database');
const { logAction } = require('../services/audit');
const { notifyHR, createNotification } = require('../services/notification');
const { DateTime } = require('luxon');

// GET /coordinator-attendance (Coordinator's own log or HR view)
router.get('/', requireAuth, (req, res) => {
    const user = req.session.user;
    const db = getDb();
    const today = DateTime.now().setZone('Asia/Manila').toFormat('yyyy-MM-dd');

    if (user.role === 'COORDINATOR') {
        const history = db.prepare(`
            SELECT * FROM coordinator_attendance 
            WHERE user_id = ? 
            ORDER BY work_date DESC 
            LIMIT 30
        `).all(user.id);

        const todayRecord = db.prepare(`
            SELECT * FROM coordinator_attendance 
            WHERE user_id = ? AND work_date = ?
        `).get(user.id, today);

        return res.render('attendance/coordinator', {
            title: 'My Attendance - TAASCOR',
            history,
            todayRecord,
            today,
            isCoordinator: true
        });
    }

    // HR and ADMIN monitoring view
    const filterDate = req.query.date || today;
    const records = db.prepare(`
        SELECT ca.*, u.full_name, a.name as area_name, approver.full_name as approved_by_name
        FROM users u
        LEFT JOIN coordinator_area_assignments caa ON caa.user_id = u.id AND caa.is_current = 1
        LEFT JOIN areas a ON caa.area_id = a.id
        LEFT JOIN coordinator_attendance ca ON ca.user_id = u.id AND ca.work_date = ?
        LEFT JOIN users approver ON ca.correction_approved_by = approver.id
        WHERE u.role = 'COORDINATOR' AND u.status = 'active'
        ORDER BY u.full_name ASC
    `).all(filterDate);

    // Pending correction requests
    const pendingCorrections = db.prepare(`
        SELECT ca.*, u.full_name, a.name as area_name
        FROM coordinator_attendance ca
        JOIN users u ON ca.user_id = u.id
        LEFT JOIN coordinator_area_assignments caa ON caa.user_id = u.id AND caa.is_current = 1
        LEFT JOIN areas a ON caa.area_id = a.id
        WHERE ca.correction_requested = 1 AND ca.correction_approved_by IS NULL
        ORDER BY ca.work_date DESC
    `).all();

    res.render('attendance/coordinator', {
        title: 'Coordinator Attendance Monitoring - TAASCOR',
        records,
        pendingCorrections,
        filterDate,
        isCoordinator: false
    });
});

// POST /coordinator-attendance/time-in (Server timestamp)
router.post('/time-in', requireAuth, (req, res) => {
    const user = req.session.user;
    if (user.role !== 'COORDINATOR') {
        req.flash('error', 'Only coordinators record time-in through this feature.');
        return res.redirect('/coordinator-attendance');
    }

    const db = getDb();
    const manilaNow = DateTime.now().setZone('Asia/Manila');
    const workDate = manilaNow.toFormat('yyyy-MM-dd');
    const timeFormatted = manilaNow.toFormat('HH:mm');
    const serverTimestamp = DateTime.now().toUTC().toISO();

    const existing = db.prepare('SELECT id, time_in FROM coordinator_attendance WHERE user_id = ? AND work_date = ?').get(user.id, workDate);

    if (existing) {
        req.flash('error', `You already recorded Time-In today at ${existing.time_in}. Free editing is not permitted.`);
        return res.redirect('/coordinator-attendance');
    }

    db.prepare(`
        INSERT INTO coordinator_attendance (user_id, work_date, time_in, time_in_server)
        VALUES (?, ?, ?, ?)
    `).run(user.id, workDate, timeFormatted, serverTimestamp);

    logAction(user.id, 'COORDINATOR_TIME_IN', 'coordinator_attendance', null, { workDate, timeFormatted });

    req.flash('success', `Time-In recorded at ${timeFormatted}. Have a productive shift!`);
    res.redirect('/coordinator-attendance');
});

// POST /coordinator-attendance/time-out (Server timestamp)
router.post('/time-out', requireAuth, (req, res) => {
    const user = req.session.user;
    if (user.role !== 'COORDINATOR') {
        return res.redirect('/coordinator-attendance');
    }

    const db = getDb();
    const manilaNow = DateTime.now().setZone('Asia/Manila');
    const workDate = manilaNow.toFormat('yyyy-MM-dd');
    const timeFormatted = manilaNow.toFormat('HH:mm');
    const serverTimestamp = DateTime.now().toUTC().toISO();

    const existing = db.prepare('SELECT id, time_out FROM coordinator_attendance WHERE user_id = ? AND work_date = ?').get(user.id, workDate);

    if (!existing) {
        req.flash('error', 'You have not recorded Time-In for today yet.');
        return res.redirect('/coordinator-attendance');
    }

    if (existing.time_out) {
        req.flash('error', `You already recorded Time-Out today at ${existing.time_out}.`);
        return res.redirect('/coordinator-attendance');
    }

    db.prepare(`
        UPDATE coordinator_attendance 
        SET time_out = ?, time_out_server = ? 
        WHERE id = ?
    `).run(timeFormatted, serverTimestamp, existing.id);

    logAction(user.id, 'COORDINATOR_TIME_OUT', 'coordinator_attendance', existing.id, { workDate, timeFormatted });

    req.flash('success', `Time-Out recorded at ${timeFormatted}. Shift completed.`);
    res.redirect('/coordinator-attendance');
});

// POST /coordinator-attendance/request-correction (Coordinator requests correction)
router.post('/request-correction', requireAuth, (req, res) => {
    const user = req.session.user;
    const { attendance_id, correction_reason } = req.body;
    const db = getDb();

    if (!attendance_id || !correction_reason) {
        req.flash('error', 'Please provide a reason for the correction request.');
        return res.redirect('/coordinator-attendance');
    }

    const row = db.prepare('SELECT * FROM coordinator_attendance WHERE id = ? AND user_id = ?').get(parseInt(attendance_id), user.id);
    if (!row) {
        req.flash('error', 'Attendance record not found.');
        return res.redirect('/coordinator-attendance');
    }

    db.prepare(`
        UPDATE coordinator_attendance 
        SET correction_requested = 1, correction_reason = ? 
        WHERE id = ?
    `).run(correction_reason.trim(), row.id);

    notifyHR(
        'Attendance Correction Request',
        `Coordinator ${user.full_name} requested attendance correction for ${row.work_date}: "${correction_reason.trim()}"`,
        '/coordinator-attendance',
        `att-corr-${row.id}`
    );

    logAction(user.id, 'REQUEST_ATTENDANCE_CORRECTION', 'coordinator_attendance', row.id, { reason: correction_reason });

    req.flash('success', 'Correction request submitted to HR for review.');
    res.redirect('/coordinator-attendance');
});

// POST /coordinator-attendance/approve-correction (HR only)
router.post('/approve-correction', requireAuth, (req, res) => {
    const user = req.session.user;
    if (user.role !== 'HR' && user.role !== 'ADMIN') {
        return res.status(403).render('error', { title: 'Access Denied', message: 'Only HR can approve attendance corrections.', code: 403 });
    }

    const { attendance_id, new_time_in, new_time_out, hr_notes } = req.body;
    const db = getDb();

    const row = db.prepare('SELECT * FROM coordinator_attendance WHERE id = ?').get(parseInt(attendance_id));
    if (!row) {
        req.flash('error', 'Attendance record not found.');
        return res.redirect('/coordinator-attendance');
    }

    db.prepare(`
        UPDATE coordinator_attendance 
        SET time_in = COALESCE(?, time_in),
            time_out = COALESCE(?, time_out),
            correction_requested = 0,
            correction_approved_by = ?,
            correction_approved_at = datetime('now'),
            correction_note = ?
        WHERE id = ?
    `).run(
        new_time_in || null,
        new_time_out || null,
        user.id,
        hr_notes ? hr_notes.trim() : 'Approved by HR',
        row.id
    );

    createNotification(
        row.user_id,
        'Attendance Correction Approved',
        `HR approved your attendance correction for date ${row.work_date}.`,
        '/coordinator-attendance',
        `att-corr-approved-${row.id}`
    );

    logAction(user.id, 'APPROVE_ATTENDANCE_CORRECTION', 'coordinator_attendance', row.id, {
        original_in: row.time_in,
        original_out: row.time_out,
        new_time_in,
        new_time_out,
        hr_notes
    });

    req.flash('success', 'Attendance correction applied and logged.');
    res.redirect('/coordinator-attendance');
});

module.exports = router;
