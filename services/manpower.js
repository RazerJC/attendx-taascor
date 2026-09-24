const { getDb } = require('../db/database');

/**
 * Calculates summary metrics for a manpower request
 */
async function getManpowerSummary(requestId) {
    const db = getDb();
    
    // Total requested
    const totalRequested = (await db.prepare(`
        SELECT COALESCE(SUM(quantity_requested), 0) as total 
        FROM manpower_request_positions 
        WHERE request_id = ?
    `).get(requestId)).total;

    // Allocations count
    const allocStats = (await db.prepare(`
        SELECT 
            COUNT(*) as total_alloc,
            SUM(CASE WHEN status = 'proposed' THEN 1 ELSE 0 END) as proposed_count,
            SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed_count,
            SUM(CASE WHEN status = 'did_not_report' THEN 1 ELSE 0 END) as no_show_count
        FROM manpower_allocations 
        WHERE request_id = ?
    `).get(requestId));

    const confirmed = allocStats.confirmed_count || 0;
    const proposed = allocStats.proposed_count || 0;
    const remaining = Math.max(0, totalRequested - confirmed);

    return {
        totalRequested,
        proposed,
        confirmed,
        noShow: allocStats.no_show_count || 0,
        remaining
    };
}

/**
 * Automatically updates request status based on confirmation numbers
 */
async function syncRequestStatus(requestId) {
    const db = getDb();
    const req = (await db.prepare(`SELECT status FROM manpower_requests WHERE id = ?`).get(requestId));
    if (!req || ['declined', 'cancelled'].includes(req.status)) {
        return;
    }

    const summary = (await getManpowerSummary(requestId));

    let newStatus = req.status;
    if (summary.totalRequested > 0) {
        if (summary.confirmed >= summary.totalRequested) {
            newStatus = 'filled';
        } else if (summary.confirmed > 0 || summary.proposed > 0) {
            newStatus = 'partially_filled';
        } else if (newStatus !== 'under_review') {
            newStatus = 'submitted';
        }
    }

    if (newStatus !== req.status) {
        (await db.prepare(`
            UPDATE manpower_requests 
            SET status = ?, updated_at = UTC_TIMESTAMP() 
            WHERE id = ?
        `).run(newStatus, requestId));
    }
}

module.exports = { getManpowerSummary, syncRequestStatus };
