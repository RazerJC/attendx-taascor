const { DateTime } = require('luxon');
function isExpired(timestamp, now = Date.now()) {
    const value = String(timestamp || '');
    const date = value.includes('T') ? DateTime.fromISO(value, { zone: 'utc' }) : DateTime.fromSQL(value, { zone: 'utc' });
    return !date.isValid || date.toMillis() <= now;
}
module.exports = { isExpired };
