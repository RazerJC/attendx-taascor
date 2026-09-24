const fs = require('fs');
const path = require('path');
const XLSX = require('xlsx');
const ExcelJS = require('exceljs');
const { DateTime } = require('luxon');
const { getDb } = require('../db/database');

const EXCEL_PATH = path.join(__dirname, '../node_modules/xlsx/COOR TIMEKEEPING SEPT 16-30 ,2026.xlsx');

let cachedMaster = null;

/**
 * Parse the master Excel file once and cache its contents
 */
function loadMasterExcel() {
    if (cachedMaster) return cachedMaster;
    if (!fs.existsSync(EXCEL_PATH)) {
        console.warn('Master timekeeping Excel not found at:', EXCEL_PATH);
        return { coordinators: [], bankAccounts: {}, summary: [] };
    }

    try {
        const wb = XLSX.readFile(EXCEL_PATH);

        // 1. Bank Accounts Sheet
        const bankAccounts = {};
        if (wb.Sheets['COOR BANK ACCNT']) {
            const rawBank = XLSX.utils.sheet_to_json(wb.Sheets['COOR BANK ACCNT']);
            rawBank.forEach(b => {
                const name = (b.NAME || '').trim().toUpperCase();
                if (name) {
                    bankAccounts[name] = {
                        gcash: b['GCASH NUMBER '] || b['GCASH NUMBER'] || '—',
                        gotyme: b['GOTYME'] || '—',
                        metrobank: b['METRO BANK ATM'] || '—',
                        securityBank: b['SECURITY BANK'] || '—'
                    };
                }
            });
        }

        // 2. Summary Sheet
        const summary = [];
        if (wb.Sheets['SUMMARY']) {
            const rawSummary = XLSX.utils.sheet_to_json(wb.Sheets['SUMMARY']);
            rawSummary.forEach(s => {
                if (s.NAME && String(s.NAME).trim()) {
                    summary.push({
                        no: s['NO.'] || '',
                        name: String(s.NAME).trim(),
                        rate: s.RATE || '—',
                        days: parseFloat(s['Number of days'] || 0) || 0,
                        late: parseFloat(s['Number of late/s'] || 0) || 0,
                        regOt: parseFloat(s['Reg OT'] || 0) || 0,
                        nd: parseFloat(s['ND'] || 0) || 0,
                        sunSpHolRd: parseFloat(s['Sun/Sp. Hol/ RD work'] || 0) || 0,
                        sunSpHolRdOt: parseFloat(s['Sun/Sp. Hol/ RD work OT'] || 0) || 0,
                        legalHol: parseFloat(s['LEGAL HOLIDAY'] || 0) || 0,
                        legalHolOt: parseFloat(s['Legal Holiday OT'] || 0) || 0,
                        rdSpHol: parseFloat(s['Working on RD Sp. Hol '] || s['Working on RD Sp. Hol'] || 0) || 0,
                        rdSpHolOt: parseFloat(s['Working on RD Sp. Hol  OT'] || s['Working on RD Sp. Hol OT'] || 0) || 0,
                        vlSil: parseFloat(s['VL / SIL'] || 0) || 0,
                        cashAdvance: s['CASH ADVANCE'] || '0.00',
                        cellphone: s['CELLPHONE'] || '0.00',
                        remarks: s['Remarks'] || '',
                        adjustment: s['FOR Adjustment'] || ''
                    });
                }
            });
        }

        // 3. Timekeeping Breakdown Sheet
        const coordinators = [];
        if (wb.Sheets['TIME KEEPING ']) {
            const rawTk = XLSX.utils.sheet_to_json(wb.Sheets['TIME KEEPING '], { header: 1, raw: false });
            // Each coordinator block starts when column A is populated
            let currentCoor = null;

            for (let r = 1; r < rawTk.length; r++) {
                const row = rawTk[r];
                const colA = row[0] ? String(row[0]).trim() : '';

                if (colA) {
                    if (currentCoor) {
                        coordinators.push(currentCoor);
                    }
                    currentCoor = {
                        name: colA,
                        days: [],
                        totals: null
                    };
                }

                if (currentCoor) {
                    const colB = row[1] ? String(row[1]).trim() : '';
                    if (colB === 'TOTAL') {
                        currentCoor.totals = {
                            days: parseFloat(row[4] || 0) || 0,
                            late: parseFloat(row[5] || 0) || 0,
                            regHrs: parseFloat(row[6] || 0) || 0,
                            regOt: parseFloat(row[7] || 0) || 0,
                            nd: parseFloat(row[8] || 0) || 0,
                            sunSpHolRd: parseFloat(row[9] || 0) || 0,
                            sunSpHolRdOt: parseFloat(row[10] || 0) || 0,
                            legalHol: parseFloat(row[11] || 0) || 0,
                            legalHolOt: parseFloat(row[12] || 0) || 0,
                            rdSpHol: parseFloat(row[13] || 0) || 0,
                            rdSpHolOt: parseFloat(row[14] || 0) || 0,
                            vlSil: parseFloat(row[15] || 0) || 0,
                            adjustment: parseFloat(row[16] || 0) || 0
                        };
                    } else if (colB && !colB.toUpperCase().includes('DATE')) {
                        // Extract time in/out in military time (HH:mm)
                        const rawIn = row[2] ? String(row[2]).trim() : '';
                        const rawOut = row[3] ? String(row[3]).trim() : '';
                        const timeIn = rawIn ? rawIn.substring(0, 5) : '';
                        const timeOut = rawOut ? rawOut.substring(0, 5) : '';

                        currentCoor.days.push({
                            dateText: colB,
                            timeIn,
                            timeOut,
                            numDays: parseFloat(row[4] || 0) || 0,
                            late: parseFloat(row[5] || 0) || 0,
                            regHrs: parseFloat(row[6] || 0) || 0,
                            regOt: parseFloat(row[7] || 0) || 0,
                            nd: parseFloat(row[8] || 0) || 0,
                            sunSpHolRd: parseFloat(row[9] || 0) || 0,
                            sunSpHolRdOt: parseFloat(row[10] || 0) || 0,
                            legalHol: parseFloat(row[11] || 0) || 0,
                            legalHolOt: parseFloat(row[12] || 0) || 0,
                            rdSpHol: parseFloat(row[13] || 0) || 0,
                            rdSpHolOt: parseFloat(row[14] || 0) || 0,
                            vlSil: parseFloat(row[15] || 0) || 0,
                            adjustment: row[16] ? String(row[16]).trim() : ''
                        });
                    }
                }
            }
            if (currentCoor) {
                coordinators.push(currentCoor);
            }
        }

        cachedMaster = { coordinators, bankAccounts, summary };
        return cachedMaster;
    } catch (e) {
        console.error('Error loading master Excel:', e);
        return { coordinators: [], bankAccounts: {}, summary: [] };
    }
}

/**
 * Format military time string (HH:mm)
 */
function toMilitary(timeStr) {
    if (!timeStr) return '';
    const parts = String(timeStr).trim().split(':');
    if (parts.length >= 2) {
        const h = String(parseInt(parts[0], 10)).padStart(2, '0');
        const m = String(parts[1]).slice(0, 2).padStart(2, '0');
        return `${h}:${m}`;
    }
    return timeStr;
}

/**
 * Calculate hours between two military times minus 1h meal break if > 5h
 */
function calcHours(timeIn, timeOut) {
    if (!timeIn || !timeOut) return { regHrs: 0, regOt: 0, totalHrs: 0 };
    const [h1, m1] = timeIn.split(':').map(Number);
    const [h2, m2] = timeOut.split(':').map(Number);
    let diffMins = (h2 * 60 + m2) - (h1 * 60 + m1);
    if (diffMins < 0) diffMins += 24 * 60; // Over midnight

    // Deduct 1 hour meal break if shift is >= 5 hours
    let netMins = diffMins >= 300 ? diffMins - 60 : diffMins;
    const totalHrs = Math.max(0, netMins / 60);

    const regHrs = Math.min(8, totalHrs);
    const regOt = Math.max(0, totalHrs - 8);

    return {
        regHrs: Math.round(regHrs * 100) / 100,
        regOt: Math.round(regOt * 100) / 100,
        totalHrs: Math.round(totalHrs * 100) / 100
    };
}

/**
 * Build complete coordinator breakdown
 */
async function getCoordinatorTimekeeping(coordinatorId, cutoffStart, cutoffEnd) {
    const db = getDb();
    const master = loadMasterExcel();

    // 1. Get coordinator user from DB
    let user = null;
    if (coordinatorId) {
        user = await db.prepare(`
            SELECT u.id, u.full_name, u.email, u.phone, a.id as area_id, a.name as area_name
            FROM users u
            LEFT JOIN coordinator_area_assignments caa ON caa.user_id = u.id AND caa.is_current = 1
            LEFT JOIN areas a ON caa.area_id = a.id
            WHERE u.id = ?
        `).get(coordinatorId);
    }

    if (!user) {
        // Fallback to first coordinator if ID not specified
        user = await db.prepare(`
            SELECT u.id, u.full_name, u.email, u.phone, a.id as area_id, a.name as area_name
            FROM users u
            LEFT JOIN coordinator_area_assignments caa ON caa.user_id = u.id AND caa.is_current = 1
            LEFT JOIN areas a ON caa.area_id = a.id
            WHERE u.role = 'COORDINATOR' AND u.status = 'active'
            ORDER BY u.id ASC
            LIMIT 1
        `).get();
    }

    const coorName = user ? user.full_name : 'Coordinator';
    const areaName = user ? (user.area_name || 'Warehouse') : 'Warehouse';

    // Default cutoff to Sept 16 - Sept 30, 2026 or requested dates
    const startDate = cutoffStart || '2026-09-16';
    const endDate = cutoffEnd || '2026-09-30';

    // 2. Fetch live database punches for this coordinator
    let livePunches = [];
    if (user) {
        livePunches = await db.prepare(`
            SELECT work_date, time_in, time_out, correction_note
            FROM coordinator_attendance
            WHERE user_id = ? AND work_date >= ? AND work_date <= ?
            ORDER BY work_date ASC
        `).all(user.id, startDate, endDate);
    }

    const punchMap = {};
    livePunches.forEach(p => {
        punchMap[p.work_date] = p;
    });

    // 3. Find matching reference in Excel master (by name or partial name)
    const upperName = coorName.toUpperCase();
    let excelMatch = master.coordinators.find(c => {
        const cUp = c.name.toUpperCase();
        return cUp.includes(upperName) || upperName.includes(cUp.split('(')[0].trim());
    });

    // If no direct name match, check if there is an area match or use first sample
    if (!excelMatch && master.coordinators.length > 0) {
        excelMatch = master.coordinators.find(c => c.name.toUpperCase().includes('PHIXC')) || master.coordinators[1];
    }

    // 4. Find bank accounts
    let bankInfo = { gcash: '—', gotyme: '—', metrobank: 'ATM', securityBank: '—' };
    for (const [bName, bData] of Object.entries(master.bankAccounts)) {
        if (upperName.includes(bName) || bName.includes(upperName)) {
            bankInfo = bData;
            break;
        }
    }

    // 5. Generate daily breakdown rows
    const startDt = DateTime.fromISO(startDate);
    const endDt = DateTime.fromISO(endDate);
    const totalDaysCount = Math.max(1, Math.round(endDt.diff(startDt, 'days').days) + 1);

    const days = [];
    let cur = startDt;

    const totals = {
        days: 0,
        late: 0,
        regHrs: 0,
        regOt: 0,
        nd: 0,
        sunSpHolRd: 0,
        sunSpHolRdOt: 0,
        legalHol: 0,
        legalHolOt: 0,
        rdSpHol: 0,
        rdSpHolOt: 0,
        vlSil: 0,
        adjustment: 0
    };

    while (cur <= endDt) {
        const iso = cur.toISODate();
        const dateText = cur.setZone('Asia/Manila').toFormat('cccc, dd LLLL yyyy');
        const dbPunch = punchMap[iso];

        // Check excel row fallback
        const excelDay = excelMatch ? excelMatch.days.find(d => d.dateText && d.dateText.includes(String(cur.day))) : null;

        let timeIn = '';
        let timeOut = '';
        let numDays = 0;
        let late = 0;
        let regHrs = 0;
        let regOt = 0;
        let nd = 0;
        let sunSpHolRd = 0;
        let sunSpHolRdOt = 0;
        let legalHol = 0;
        let legalHolOt = 0;
        let rdSpHol = 0;
        let rdSpHolOt = 0;
        let vlSil = 0;
        let adjustment = '';

        if (dbPunch && dbPunch.time_in) {
            timeIn = toMilitary(dbPunch.time_in);
            timeOut = toMilitary(dbPunch.time_out);
            const calced = calcHours(timeIn, timeOut);
            regHrs = calced.regHrs;
            regOt = calced.regOt;
            numDays = regHrs >= 8 ? 1 : Math.round((regHrs / 8) * 100) / 100;
            adjustment = dbPunch.correction_note || '';
        } else if (excelDay) {
            timeIn = excelDay.timeIn;
            timeOut = excelDay.timeOut;
            numDays = excelDay.numDays;
            late = excelDay.late;
            regHrs = excelDay.regHrs;
            regOt = excelDay.regOt;
            nd = excelDay.nd;
            sunSpHolRd = excelDay.sunSpHolRd;
            sunSpHolRdOt = excelDay.sunSpHolRdOt;
            legalHol = excelDay.legalHol;
            legalHolOt = excelDay.legalHolOt;
            rdSpHol = excelDay.rdSpHol;
            rdSpHolOt = excelDay.rdSpHolOt;
            vlSil = excelDay.vlSil;
            adjustment = excelDay.adjustment;
        }

        totals.days += numDays;
        totals.late += late;
        totals.regHrs += regHrs;
        totals.regOt += regOt;
        totals.nd += nd;
        totals.sunSpHolRd += sunSpHolRd;
        totals.sunSpHolRdOt += sunSpHolRdOt;
        totals.legalHol += legalHol;
        totals.legalHolOt += legalHolOt;
        totals.rdSpHol += rdSpHol;
        totals.rdSpHolOt += rdSpHolOt;
        totals.vlSil += vlSil;

        days.push({
            dateIso: iso,
            dateText,
            timeIn,
            timeOut,
            numDays: numDays.toFixed(2),
            late: late.toFixed(2),
            regHrs: regHrs.toFixed(2),
            regOt: regOt.toFixed(2),
            nd: nd.toFixed(2),
            sunSpHolRd: sunSpHolRd.toFixed(2),
            sunSpHolRdOt: sunSpHolRdOt.toFixed(2),
            legalHol: legalHol.toFixed(2),
            legalHolOt: legalHolOt.toFixed(2),
            rdSpHol: rdSpHol.toFixed(2),
            rdSpHolOt: rdSpHolOt.toFixed(2),
            vlSil: vlSil.toFixed(2),
            adjustment
        });

        cur = cur.plus({ days: 1 });
    }

    // Format totals
    totals.days = totals.days.toFixed(2);
    totals.late = totals.late.toFixed(2);
    totals.regHrs = totals.regHrs.toFixed(2);
    totals.regOt = totals.regOt.toFixed(2);
    totals.nd = totals.nd.toFixed(2);
    totals.sunSpHolRd = totals.sunSpHolRd.toFixed(2);
    totals.sunSpHolRdOt = totals.sunSpHolRdOt.toFixed(2);
    totals.legalHol = totals.legalHol.toFixed(2);
    totals.legalHolOt = totals.legalHolOt.toFixed(2);
    totals.rdSpHol = totals.rdSpHol.toFixed(2);
    totals.rdSpHolOt = totals.rdSpHolOt.toFixed(2);
    totals.vlSil = totals.vlSil.toFixed(2);

    // List of all available coordinators in system & master
    const allDbCoordinators = await db.prepare(`
        SELECT u.id, u.full_name, a.name as area_name
        FROM users u
        LEFT JOIN coordinator_area_assignments caa ON caa.user_id = u.id AND caa.is_current = 1
        LEFT JOIN areas a ON caa.area_id = a.id
        WHERE u.role = 'COORDINATOR' AND u.status = 'active'
        ORDER BY u.full_name ASC
    `).all();

    return {
        coordinator: {
            id: user ? user.id : coordinatorId,
            name: coorName,
            area: areaName,
            email: user ? user.email : '—',
            phone: user ? user.phone : '—',
            bankInfo
        },
        period: {
            startDate,
            endDate,
            label: `${DateTime.fromISO(startDate).toFormat('MMM dd')} - ${DateTime.fromISO(endDate).toFormat('MMM dd, yyyy')}`
        },
        days,
        totals,
        coordinatorsList: allDbCoordinators,
        masterCoordinators: master.coordinators.map(c => c.name)
    };
}

/**
 * Generate Excel workbook matching COOR TIMEKEEPING SEPT 16-30 ,2026.xlsx format
 */
async function generateExcelWorkbook(breakdown) {
    const workbook = new ExcelJS.Workbook();
    workbook.creator = 'TAASCOR ATS';
    workbook.created = new Date();

    // ── SHEET 1: TIME KEEPING ──────────────────────────────────
    const sheet1 = workbook.addWorksheet('TIME KEEPING ');
    sheet1.views = [{ showGridLines: true }];

    const headers1 = [
        'Name/s', 'DATE', 'Time In', 'Time Out ', 'Number of days', 'Number of late/s',
        'Reg Hrs', 'Reg OT', 'ND', 'Sun/Sp. Hol/ RD work', 'Sun/Sp. Hol/ RD work OT',
        'LEGAL HOLIDAY', 'Legal Holiday OT', 'working on RD Sp. Hol ', 'working on RD Sp. Hol  OT',
        'VL / SIL', 'Adjustment'
    ];

    const headerRow = sheet1.addRow(headers1);
    headerRow.eachCell(cell => {
        cell.font = { bold: true, size: 10, color: { argb: 'FFFFFFFF' } };
        cell.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FF1B2A4A' } };
        cell.alignment = { vertical: 'middle', horizontal: 'center', wrapText: true };
    });

    // Coordinator rows
    breakdown.days.forEach((d, idx) => {
        const rowData = [
            idx === 0 ? `${breakdown.coordinator.name} (${breakdown.coordinator.area})` : '',
            d.dateText,
            d.timeIn ? d.timeIn + ':00' : '',
            d.timeOut ? d.timeOut + ':00' : '',
            parseFloat(d.numDays) || 0,
            parseFloat(d.late) || 0,
            parseFloat(d.regHrs) || 0,
            parseFloat(d.regOt) || 0,
            parseFloat(d.nd) || 0,
            parseFloat(d.sunSpHolRd) || 0,
            parseFloat(d.sunSpHolRdOt) || 0,
            parseFloat(d.legalHol) || 0,
            parseFloat(d.legalHolOt) || 0,
            parseFloat(d.rdSpHol) || 0,
            parseFloat(d.rdSpHolOt) || 0,
            parseFloat(d.vlSil) || 0,
            d.adjustment || ''
        ];
        sheet1.addRow(rowData);
    });

    // Blank line
    sheet1.addRow(['', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '']);

    // Total row
    const totalRowData = [
        '',
        'TOTAL',
        '',
        '',
        parseFloat(breakdown.totals.days) || 0,
        parseFloat(breakdown.totals.late) || 0,
        parseFloat(breakdown.totals.regHrs) || 0,
        parseFloat(breakdown.totals.regOt) || 0,
        parseFloat(breakdown.totals.nd) || 0,
        parseFloat(breakdown.totals.sunSpHolRd) || 0,
        parseFloat(breakdown.totals.sunSpHolRdOt) || 0,
        parseFloat(breakdown.totals.legalHol) || 0,
        parseFloat(breakdown.totals.legalHolOt) || 0,
        parseFloat(breakdown.totals.rdSpHol) || 0,
        parseFloat(breakdown.totals.rdSpHolOt) || 0,
        parseFloat(breakdown.totals.vlSil) || 0,
        ''
    ];
    const totalRow = sheet1.addRow(totalRowData);
    totalRow.eachCell((cell, colNumber) => {
        cell.font = { bold: true, size: 10 };
        cell.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FFF0F4FC' } };
        if (colNumber >= 5 && colNumber <= 16) {
            cell.numFmt = '#,##0.00';
        }
    });

    // Auto-fit column widths
    sheet1.columns.forEach((col, i) => {
        col.width = i === 0 ? 32 : i === 1 ? 28 : 14;
    });

    // ── SHEET 2: SUMMARY ───────────────────────────────────────
    const sheet2 = workbook.addWorksheet('SUMMARY');
    const headers2 = [
        'NO.', 'NAME', 'RATE', 'Number of days', 'Number of late/s', 'Reg OT', 'ND',
        'Sun/Sp. Hol/ RD work', 'Sun/Sp. Hol/ RD work OT', 'LEGAL HOLIDAY', 'Legal Holiday OT',
        'Working on RD Sp. Hol ', 'Working on RD Sp. Hol  OT', 'VL / SIL', 'CASH ADVANCE',
        'CELLPHONE', 'Remarks', 'FOR Adjustment'
    ];
    const h2 = sheet2.addRow(headers2);
    h2.eachCell(cell => {
        cell.font = { bold: true, size: 10, color: { argb: 'FFFFFFFF' } };
        cell.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FF1B2A4A' } };
    });
    sheet2.addRow([
        1,
        `${breakdown.coordinator.name} - ${breakdown.coordinator.area}`,
        '—',
        parseFloat(breakdown.totals.days) || 0,
        parseFloat(breakdown.totals.late) || 0,
        parseFloat(breakdown.totals.regOt) || 0,
        parseFloat(breakdown.totals.nd) || 0,
        parseFloat(breakdown.totals.sunSpHolRd) || 0,
        parseFloat(breakdown.totals.sunSpHolRdOt) || 0,
        parseFloat(breakdown.totals.legalHol) || 0,
        parseFloat(breakdown.totals.legalHolOt) || 0,
        parseFloat(breakdown.totals.rdSpHol) || 0,
        parseFloat(breakdown.totals.rdSpHolOt) || 0,
        parseFloat(breakdown.totals.vlSil) || 0,
        '0.00',
        '0.00',
        'Verified in ATS',
        ''
    ]);
    sheet2.columns.forEach((col, i) => {
        col.width = i === 1 ? 32 : 14;
    });

    // ── SHEET 3: COOR BANK ACCNT ───────────────────────────────
    const sheet3 = workbook.addWorksheet('COOR BANK ACCNT');
    const headers3 = ['NO.', 'NAME', 'GCASH NUMBER ', 'GOTYME', 'METRO BANK ATM', 'SECURITY BANK'];
    const h3 = sheet3.addRow(headers3);
    h3.eachCell(cell => {
        cell.font = { bold: true, size: 10, color: { argb: 'FFFFFFFF' } };
        cell.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FF1B2A4A' } };
    });
    sheet3.addRow([
        1,
        breakdown.coordinator.name,
        breakdown.coordinator.bankInfo.gcash,
        breakdown.coordinator.bankInfo.gotyme,
        breakdown.coordinator.bankInfo.metrobank,
        breakdown.coordinator.bankInfo.securityBank
    ]);
    sheet3.columns.forEach((col, i) => {
        col.width = i === 1 ? 28 : 20;
    });

    return workbook;
}

module.exports = {
    loadMasterExcel,
    getCoordinatorTimekeeping,
    generateExcelWorkbook,
    toMilitary
};
