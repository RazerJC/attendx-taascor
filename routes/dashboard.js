const express = require('express');
const router = express.Router();
const { requireAuth } = require('../middleware/auth');
const { requireArea } = require('../middleware/area-guard');
const { getDb } = require('../db/database');
const { DateTime } = require('luxon');

// GET /dashboard
router.get('/', requireAuth, (req, res) => {
    const user = req.session.user;
    const db = getDb();
    const today = DateTime.now().setZone('Asia/Manila').toFormat('yyyy-MM-dd');

    if (user.role === 'COORDINATOR') {
        const areaId = req.userAreaId;
        if (!areaId) {
            return res.render('dashboard/coordinator', {
                title: 'Coordinator Dashboard - TAASCOR',
                noArea: true
            });
        }

        // Active & inactive counts
        const empStats = db.prepare(`
            SELECT 
                SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_count,
                SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) as inactive_count
            FROM employees 
            WHERE area_id = ?
        `).get(areaId);

        // Today's attendance stats for assigned area
        const attStats = db.prepare(`
            SELECT 
                SUM(CASE WHEN ea.status = 'present' THEN 1 ELSE 0 END) as present_count,
                SUM(CASE WHEN ea.status = 'late' THEN 1 ELSE 0 END) as late_count,
                SUM(CASE WHEN ea.status = 'absent' THEN 1 ELSE 0 END) as absent_count,
                SUM(CASE WHEN ea.status = 'on_leave' THEN 1 ELSE 0 END) as leave_count,
                SUM(CASE WHEN ea.status = 'rest_day' THEN 1 ELSE 0 END) as rest_count
            FROM employee_attendance ea
            JOIN employees e ON ea.employee_id = e.id
            WHERE e.area_id = ? AND ea.work_date = ?
        `).get(areaId, today);

        // Employees scheduled today
        const scheduledTodayCount = db.prepare(`
            SELECT COUNT(*) as count 
            FROM employee_schedules es
            JOIN employees e ON es.employee_id = e.id
            WHERE e.area_id = ? AND es.work_date = ? AND es.is_rest_day = 0 AND e.status = 'active'
        `).get(areaId, today).count;

        // Employees absent today
        const absentToday = db.prepare(`
            SELECT e.id, e.employee_id, e.full_name, p.title as position_title, ea.remarks, b.id as btw_id, b.status as btw_status
            FROM employee_attendance ea
            JOIN employees e ON ea.employee_id = e.id
            LEFT JOIN positions p ON e.position_id = p.id
            LEFT JOIN btw_cases b ON b.employee_id = e.id AND b.status IN ('pending_hr_review', 'for_clarification')
            WHERE e.area_id = ? AND ea.work_date = ? AND ea.status = 'absent'
        `).all(areaId, today);

        // Pending BTW cases
        const pendingBtw = db.prepare(`
            SELECT b.*, e.full_name, e.employee_id as emp_code
            FROM btw_cases b
            JOIN employees e ON b.employee_id = e.id
            WHERE b.area_id = ? AND b.status IN ('pending_hr_review', 'for_clarification')
            ORDER BY b.created_at DESC
        `).all(areaId);

        // Open employee concerns
        const openConcerns = db.prepare(`
            SELECT c.*, e.full_name, e.employee_id as emp_code
            FROM concern_reports c
            JOIN employees e ON c.employee_id = e.id
            WHERE c.area_id = ? AND c.status != 'resolved'
            ORDER BY c.created_at DESC
        `).all(areaId);

        // Manpower requests in progress
        const manpowerRequests = db.prepare(`
            SELECT mr.*, 
                   COALESCE((SELECT SUM(quantity_requested) FROM manpower_request_positions WHERE request_id = mr.id), 0) as total_requested,
                   COALESCE((SELECT COUNT(*) FROM manpower_allocations WHERE request_id = mr.id AND status = 'confirmed'), 0) as confirmed_count,
                   COALESCE((SELECT COUNT(*) FROM manpower_allocations WHERE request_id = mr.id AND status = 'proposed'), 0) as proposed_count
            FROM manpower_requests mr
            WHERE mr.area_id = ? AND mr.status NOT IN ('filled', 'declined', 'cancelled')
            ORDER BY mr.created_at DESC
        `).all(areaId);

        // Incoming workers awaiting coordinator confirmation
        const incomingWorkers = db.prepare(`
            SELECT ma.*, mr.deployment_date, p.title as requested_position, e.full_name as emp_name
            FROM manpower_allocations ma
            JOIN manpower_requests mr ON ma.request_id = mr.id
            LEFT JOIN manpower_request_positions mrp ON ma.request_position_id = mrp.id
            LEFT JOIN positions p ON mrp.position_id = p.id
            LEFT JOIN employees e ON ma.employee_id = e.id
            WHERE mr.area_id = ? AND ma.status = 'proposed'
            ORDER BY mr.deployment_date ASC
        `).all(areaId);

        // Coordinator's own attendance today
        const ownAttendance = db.prepare(`
            SELECT * FROM coordinator_attendance 
            WHERE user_id = ? AND work_date = ?
        `).get(user.id, today);

        return res.render('dashboard/coordinator', {
            title: 'Coordinator Dashboard - TAASCOR',
            today,
            activeCount: empStats?.active_count || 0,
            inactiveCount: empStats?.inactive_count || 0,
            presentCount: attStats?.present_count || 0,
            lateCount: attStats?.late_count || 0,
            absentCount: attStats?.absent_count || 0,
            leaveCount: attStats?.leave_count || 0,
            restCount: attStats?.rest_count || 0,
            scheduledTodayCount,
            absentToday,
            pendingBtw,
            openConcerns,
            manpowerRequests,
            incomingWorkers,
            ownAttendance,
            noArea: false
        });
    }

    if (user.role === 'HR') {
        // Pending coordinator registrations
        const pendingCoordinators = db.prepare(`
            SELECT * FROM users 
            WHERE role = 'COORDINATOR' AND status = 'pending'
            ORDER BY created_at ASC
        `).all();

        // Coordinator attendance today
        const coordinatorAttendanceToday = db.prepare(`
            SELECT u.id, u.full_name, a.name as area_name, ca.time_in, ca.time_out, ca.correction_requested
            FROM users u
            LEFT JOIN coordinator_area_assignments caa ON caa.user_id = u.id AND caa.is_current = 1
            LEFT JOIN areas a ON caa.area_id = a.id
            LEFT JOIN coordinator_attendance ca ON ca.user_id = u.id AND ca.work_date = ?
            WHERE u.role = 'COORDINATOR' AND u.status = 'active'
            ORDER BY u.full_name ASC
        `).all(today);

        // System-wide employee attendance today
        const systemAtt = db.prepare(`
            SELECT 
                SUM(CASE WHEN ea.status = 'present' THEN 1 ELSE 0 END) as present_count,
                SUM(CASE WHEN ea.status = 'late' THEN 1 ELSE 0 END) as late_count,
                SUM(CASE WHEN ea.status = 'absent' THEN 1 ELSE 0 END) as absent_count,
                SUM(CASE WHEN ea.status = 'on_leave' THEN 1 ELSE 0 END) as leave_count
            FROM employee_attendance ea
            WHERE ea.work_date = ?
        `).get(today);

        // Unresolved BTW cases
        const pendingBtw = db.prepare(`
            SELECT b.*, e.full_name, e.employee_id as emp_code, a.name as area_name, u.full_name as coordinator_name
            FROM btw_cases b
            JOIN employees e ON b.employee_id = e.id
            JOIN areas a ON b.area_id = a.id
            LEFT JOIN users u ON b.coordinator_id = u.id
            WHERE b.status IN ('pending_hr_review', 'for_clarification')
            ORDER BY b.created_at DESC
        `).all();

        // Concerns requiring action
        const activeConcerns = db.prepare(`
            SELECT c.*, e.full_name, a.name as area_name, u.full_name as coordinator_name
            FROM concern_reports c
            JOIN employees e ON c.employee_id = e.id
            JOIN areas a ON c.area_id = a.id
            LEFT JOIN users u ON c.reported_by = u.id
            WHERE c.status IN ('submitted', 'under_review', 'for_employee_reporting')
            ORDER BY c.created_at DESC
        `).all();

        // Active manpower requests
        const activeManpower = db.prepare(`
            SELECT mr.*, a.name as area_name, u.full_name as requester_name,
                   COALESCE((SELECT SUM(quantity_requested) FROM manpower_request_positions WHERE request_id = mr.id), 0) as total_requested,
                   COALESCE((SELECT COUNT(*) FROM manpower_allocations WHERE request_id = mr.id AND status = 'confirmed'), 0) as confirmed_count,
                   COALESCE((SELECT COUNT(*) FROM manpower_allocations WHERE request_id = mr.id AND status = 'proposed'), 0) as proposed_count
            FROM manpower_requests mr
            JOIN areas a ON mr.area_id = a.id
            LEFT JOIN users u ON mr.requested_by = u.id
            WHERE mr.status NOT IN ('filled', 'declined', 'cancelled')
            ORDER BY mr.deployment_date ASC
        `).all();

        return res.render('dashboard/hr', {
            title: 'HR Dashboard - TAASCOR',
            today,
            pendingCoordinators,
            coordinatorAttendanceToday,
            systemAtt: {
                presentCount: systemAtt?.present_count || 0,
                lateCount: systemAtt?.late_count || 0,
                absentCount: systemAtt?.absent_count || 0,
                leaveCount: systemAtt?.leave_count || 0
            },
            pendingBtw,
            activeConcerns,
            activeManpower
        });
    }

    if (user.role === 'ADMIN') {
        // Total stats
        const totalEmployees = db.prepare("SELECT COUNT(*) as count FROM employees WHERE status = 'active'").get().count;
        const totalAreas = db.prepare("SELECT COUNT(*) as count FROM areas WHERE is_active = 1").get().count;
        const totalUsers = db.prepare("SELECT COUNT(*) as count FROM users WHERE status = 'active'").get().count;
        const pendingAccounts = db.prepare("SELECT COUNT(*) as count FROM users WHERE status = 'pending'").get().count;

        // Area summaries
        const areaSummaries = db.prepare(`
            SELECT a.id, a.name,
                   (SELECT COUNT(*) FROM employees WHERE area_id = a.id AND status = 'active') as active_workers,
                   (SELECT u.full_name FROM coordinator_area_assignments caa JOIN users u ON caa.user_id = u.id WHERE caa.area_id = a.id AND caa.is_current = 1 LIMIT 1) as current_coordinator,
                   (SELECT COUNT(*) FROM employee_attendance ea JOIN employees e ON ea.employee_id = e.id WHERE e.area_id = a.id AND ea.work_date = ? AND ea.status = 'present') as present_today,
                   (SELECT COUNT(*) FROM employee_attendance ea JOIN employees e ON ea.employee_id = e.id WHERE e.area_id = a.id AND ea.work_date = ? AND ea.status = 'absent') as absent_today
            FROM areas a
            WHERE a.is_active = 1
            ORDER BY a.name ASC
        `).all(today, today);

        // System pending actions
        const pendingBtwCount = db.prepare("SELECT COUNT(*) as count FROM btw_cases WHERE status IN ('pending_hr_review', 'for_clarification')").get().count;
        const openConcernsCount = db.prepare("SELECT COUNT(*) as count FROM concern_reports WHERE status != 'resolved'").get().count;
        const openRequestsCount = db.prepare("SELECT COUNT(*) as count FROM manpower_requests WHERE status NOT IN ('filled', 'declined', 'cancelled')").get().count;

        // Recent audit events
        const recentAudit = db.prepare(`
            SELECT a.*, u.full_name, u.role
            FROM audit_logs a
            LEFT JOIN users u ON a.user_id = u.id
            ORDER BY a.created_at DESC
            LIMIT 10
        `).all();

        return res.render('dashboard/admin', {
            title: 'Admin Dashboard - TAASCOR',
            today,
            totalEmployees,
            totalAreas,
            totalUsers,
            pendingAccounts,
            pendingBtwCount,
            openConcernsCount,
            openRequestsCount,
            areaSummaries,
            recentAudit
        });
    }

    res.redirect('/login');
});

module.exports = router;
