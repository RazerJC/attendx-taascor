const { DateTime } = require('luxon');
const labels = { pending_hr_review: 'Pending HR review', for_clarification: 'For clarification', not_approved: 'Rejected', approved: 'Approved' };
function validDate(value) {
    return typeof value === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(value) && DateTime.fromISO(value).isValid;
}
function validateDecision(body) {
    if (!['approved', 'not_approved', 'for_clarification'].includes(body.decision)) return 'Invalid decision.';
    if (!body.hr_remarks?.trim()) return 'Please enter the HR reason / summary.';
    if (!['none', 'nte', 'warning', 'violation', 'suspension'].includes(body.action_type || 'none')) return 'Invalid HR action.';
    if (body.authorized_return_date && !validDate(body.authorized_return_date)) return 'Invalid return date.';
    if (body.decision === 'approved' && !body.authorized_return_date) return 'An authorized return date is required.';
    if (body.action_type && body.action_type !== 'none' && !validDate(body.issued_date)) return 'An action issue date is required.';
    if (body.action_type === 'suspension') {
        if (!validDate(body.suspension_start) || !validDate(body.suspension_end) || body.suspension_end < body.suspension_start) return 'Enter a valid suspension date range.';
        if (!validDate(body.authorized_return_date) || body.authorized_return_date <= body.suspension_end) return 'Return date must be after the last suspension day.';
    }
    return null;
}
const absenceCondition = "ea.status = 'absent' AND NOT EXISTS (SELECT 1 FROM employee_schedules rs WHERE rs.employee_id=ea.employee_id AND rs.work_date=ea.work_date AND rs.is_rest_day=1)";
module.exports = { labels, validDate, validateDecision, absenceCondition };
