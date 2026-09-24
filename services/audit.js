// Audit logging service
const { getDb } = require('../db/database');

async function logAction(userId, action, entityType = null, entityId = null, details = null, ipAddress = null) {
    const db = getDb();
    const detailsJson = details ? JSON.stringify(details) : null;
    
    (await db.prepare(
        `INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details, ip_address, created_at)
         VALUES (?, ?, ?, ?, ?, ?, UTC_TIMESTAMP())`
    ).run(userId, action, entityType, entityId, detailsJson, ipAddress));
}

async function getAuditLogs(filters = {}, limit = 50, offset = 0) {
    const db = getDb();
    let where = [];
    let params = [];
    
    if (filters.userId) {
        where.push('a.user_id = ?');
        params.push(filters.userId);
    }
    if (filters.entityType) {
        where.push('a.entity_type = ?');
        params.push(filters.entityType);
    }
    if (filters.entityId) {
        where.push('a.entity_id = ?');
        params.push(filters.entityId);
    }
    if (filters.action) {
        where.push('a.action LIKE ?');
        params.push(`%${filters.action}%`);
    }
    if (filters.dateFrom) {
        where.push('a.created_at >= ?');
        params.push(filters.dateFrom);
    }
    if (filters.dateTo) {
        where.push('a.created_at <= ?');
        params.push(filters.dateTo + 'T23:59:59');
    }
    
    const whereClause = where.length > 0 ? 'WHERE ' + where.join(' AND ') : '';
    
    const logs = (await db.prepare(
        `SELECT a.*, u.full_name as user_name, u.role as user_role
         FROM audit_logs a
         LEFT JOIN users u ON a.user_id = u.id
         ${whereClause}
         ORDER BY a.created_at DESC
         LIMIT ? OFFSET ?`
    ).all(...params, limit, offset));
    
    const countResult = (await db.prepare(
        `SELECT COUNT(*) as count FROM audit_logs a ${whereClause}`
    ).get(...params));
    
    return { logs, total: countResult.count };
}

module.exports = { logAction, getAuditLogs };
