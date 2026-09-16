// Notification service
const { getDb } = require('../db/database');

function createNotification(userId, title, message, link = null, dedupKey = null) {
    const db = getDb();
    
    // Check for duplicate if dedup key provided
    if (dedupKey) {
        const existing = db.prepare(
            'SELECT id FROM notifications WHERE dedup_key = ?'
        ).get(dedupKey);
        if (existing) return existing.id;
    }
    
    const result = db.prepare(
        `INSERT INTO notifications (user_id, title, message, link, dedup_key, created_at) 
         VALUES (?, ?, ?, ?, ?, datetime('now'))`
    ).run(userId, title, message, link, dedupKey);
    
    return result.lastInsertRowid;
}

function notifyRole(role, title, message, link = null, dedupKey = null) {
    const db = getDb();
    const users = db.prepare('SELECT id FROM users WHERE role = ? AND status = ?').all(role, 'active');
    
    for (const user of users) {
        const key = dedupKey ? `${dedupKey}-${user.id}` : null;
        createNotification(user.id, title, message, link, key);
    }
}

function notifyHR(title, message, link = null, dedupKey = null) {
    notifyRole('HR', title, message, link, dedupKey);
    notifyRole('ADMIN', title, message, link, dedupKey);
}

function getUnreadCount(userId) {
    const db = getDb();
    const result = db.prepare('SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0').get(userId);
    return result.count;
}

function getNotifications(userId, limit = 20, offset = 0) {
    const db = getDb();
    return db.prepare(
        `SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT ? OFFSET ?`
    ).all(userId, limit, offset);
}

function markAsRead(notificationId, userId) {
    const db = getDb();
    db.prepare('UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?').run(notificationId, userId);
}

function markAllAsRead(userId) {
    const db = getDb();
    db.prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0').run(userId);
}

module.exports = { createNotification, notifyRole, notifyHR, getUnreadCount, getNotifications, markAsRead, markAllAsRead };
