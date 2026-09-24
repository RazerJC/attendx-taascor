const express = require('express');
const router = express.Router();
const ExcelJS = require('exceljs');
const { requireAuth } = require('../middleware/auth');
const { validateAreaAccess, requireArea } = require('../middleware/area-guard');
const { getDb } = require('../db/database');
const { DateTime } = require('luxon');

// GET /reports
router.get('/', requireAuth, requireArea, async (req, res) => {
    const user = req.session.user;
    const db = getDb();
    const today = DateTime.now().setZone('Asia/Manila').toFormat('yyyy-MM-dd');
    const startOfMonth = DateTime.now().setZone('Asia/Manila').startOf('month').toFormat('yyyy-MM-dd');

    const dateFrom = req.query.date_from || startOfMonth;
    const dateTo = req.query.date_to || today;
    const selectedAreaId = user.role === 'COORDINATOR' ? req.userAreaId : (req.query.area_id ? parseInt(req.query.area_id) : null);
    const selectedStatus = req.query.status || '';

    let where = ['ea.work_date >= ?', 'ea.work_date <= ?'];
    let params = [dateFrom, dateTo];

    if (selectedAreaId) {
        where.push('e.area_id = ?');
        params.push(selectedAreaId);
    }

    if (selectedStatus) {
        where.push('ea.status = ?');
        params.push(selectedStatus);
    }

    const whereClause = 'WHERE ' + where.join(' AND ');

    const records = (await db.prepare(`
        SELECT ea.*, e.employee_id as emp_code, e.full_name as employee_name,
               a.name as area_name, p.title as position_title, u.full_name as recorder_name
        FROM employee_attendance ea
        JOIN employees e ON ea.employee_id = e.id
        JOIN areas a ON e.area_id = a.id
        LEFT JOIN positions p ON e.position_id = p.id
        LEFT JOIN users u ON ea.recorded_by = u.id
        ${whereClause}
        ORDER BY ea.work_date DESC, e.full_name ASC
        LIMIT 200
    `).all(...params));

    const areas = user.role !== 'COORDINATOR' ? (await db.prepare('SELECT * FROM areas WHERE is_active = 1 ORDER BY name ASC').all()) : [];

    res.render('reports/index', {
        title: 'Attendance Reports - TAASCOR',
        records,
        dateFrom,
        dateTo,
        selectedAreaId,
        selectedStatus,
        areas
    });
});

// GET /reports/export (CSV or Excel)
router.get('/export', requireAuth, requireArea, async (req, res) => {
    const user = req.session.user;
    const db = getDb();
    const format = req.query.format === 'csv' ? 'csv' : 'xlsx';

    const dateFrom = req.query.date_from || DateTime.now().setZone('Asia/Manila').startOf('month').toFormat('yyyy-MM-dd');
    const dateTo = req.query.date_to || DateTime.now().setZone('Asia/Manila').toFormat('yyyy-MM-dd');
    const selectedAreaId = user.role === 'COORDINATOR' ? req.userAreaId : (req.query.area_id ? parseInt(req.query.area_id) : null);
    const selectedStatus = req.query.status || '';

    let where = ['ea.work_date >= ?', 'ea.work_date <= ?'];
    let params = [dateFrom, dateTo];

    if (selectedAreaId) {
        where.push('e.area_id = ?');
        params.push(selectedAreaId);
    }

    if (selectedStatus) {
        where.push('ea.status = ?');
        params.push(selectedStatus);
    }

    const records = (await db.prepare(`
        SELECT ea.work_date, e.employee_id, e.full_name, a.name as area_name,
               p.title as position_title, ea.status, ea.time_in, ea.time_out,
               ea.remarks, u.full_name as recorded_by_name
        FROM employee_attendance ea
        JOIN employees e ON ea.employee_id = e.id
        JOIN areas a ON e.area_id = a.id
        LEFT JOIN positions p ON e.position_id = p.id
        LEFT JOIN users u ON ea.recorded_by = u.id
        WHERE ${where.join(' AND ')}
        ORDER BY ea.work_date DESC, e.full_name ASC
    `).all(...params));

    const workbook = new ExcelJS.Workbook();
    workbook.creator = 'TAASCOR Attendance System';
    workbook.created = new Date();

    const worksheet = workbook.addWorksheet('Attendance Report');

    worksheet.columns = [
        { header: 'Work Date', key: 'work_date', width: 14 },
        { header: 'Employee ID', key: 'employee_id', width: 16 },
        { header: 'Employee Name', key: 'full_name', width: 25 },
        { header: 'Area / Warehouse', key: 'area_name', width: 22 },
        { header: 'Position', key: 'position_title', width: 20 },
        { header: 'Status', key: 'status', width: 14 },
        { header: 'Time In', key: 'time_in', width: 12 },
        { header: 'Time Out', key: 'time_out', width: 12 },
        { header: 'Remarks', key: 'remarks', width: 25 },
        { header: 'Recorded By', key: 'recorded_by_name', width: 20 }
    ];

    // Style header row
    worksheet.getRow(1).font = { bold: true, color: { argb: 'FFFFFFFF' } };
    worksheet.getRow(1).fill = {
        type: 'pattern',
        pattern: 'solid',
        fgColor: { argb: 'FF1B2A4A' } // Navy primary
    };

    records.forEach(r => {
        worksheet.addRow({
            work_date: r.work_date,
            employee_id: r.employee_id,
            full_name: r.full_name,
            area_name: r.area_name,
            position_title: r.position_title || '—',
            status: r.status.toUpperCase(),
            time_in: r.time_in || '—',
            time_out: r.time_out || '—',
            remarks: r.remarks || '—',
            recorded_by_name: r.recorded_by_name || 'System'
        });
    });

    const filename = `TAASCOR_Attendance_${dateFrom}_to_${dateTo}`;

    if (format === 'csv') {
        res.setHeader('Content-Type', 'text/csv');
        res.setHeader('Content-Disposition', `attachment; filename="${filename}.csv"`);
        await workbook.csv.write(res);
    } else {
        res.setHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        res.setHeader('Content-Disposition', `attachment; filename="${filename}.xlsx"`);
        await workbook.xlsx.write(res);
    }
    res.end();
});

module.exports = router;
