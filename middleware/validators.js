// Input validation helpers
const { body, query, param, validationResult } = require('express-validator');

function handleValidationErrors(req, res, next) {
    const errors = validationResult(req);
    if (!errors.isEmpty()) {
        if (req.xhr || req.headers.accept?.includes('application/json')) {
            return res.status(400).json({ errors: errors.array() });
        }
        req.flash('error', errors.array().map(e => e.msg).join('. '));
        return res.redirect('back');
    }
    next();
}

// Sanitize and validate common fields
const validateEmail = body('email')
    .isEmail().withMessage('Please enter a valid email address.')
    .normalizeEmail()
    .trim();

const validateTaascorEmail = body('email')
    .isEmail().withMessage('Please enter a valid email address.')
    .normalizeEmail()
    .trim()
    .custom(value => {
        if (!value.endsWith('@taascor.com')) {
            throw new Error('Registration requires a @taascor.com email address.');
        }
        return true;
    });

const validatePassword = body('password')
    .isLength({ min: 8 }).withMessage('Password must be at least 8 characters.')
    .matches(/[A-Z]/).withMessage('Password must contain at least one uppercase letter.')
    .matches(/[a-z]/).withMessage('Password must contain at least one lowercase letter.')
    .matches(/[0-9]/).withMessage('Password must contain at least one number.');

const validateName = body('full_name')
    .trim()
    .notEmpty().withMessage('Full name is required.')
    .isLength({ max: 200 }).withMessage('Name is too long.');

const validateId = param('id')
    .isInt({ min: 1 }).withMessage('Invalid ID.');

const validateDate = (field) => body(field)
    .matches(/^\d{4}-\d{2}-\d{2}$/).withMessage(`${field} must be a valid date (YYYY-MM-DD).`);

const validateTime = (field) => body(field)
    .optional({ values: 'falsy' })
    .matches(/^\d{2}:\d{2}$/).withMessage(`${field} must be a valid time (HH:MM).`);

const validatePagination = [
    query('page').optional().isInt({ min: 1 }).toInt(),
    query('limit').optional().isInt({ min: 1, max: 100 }).toInt()
];

function sanitizeHtml(str) {
    if (!str) return str;
    return str
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#x27;');
}

module.exports = {
    handleValidationErrors,
    validateEmail,
    validateTaascorEmail,
    validatePassword,
    validateName,
    validateId,
    validateDate,
    validateTime,
    validatePagination,
    sanitizeHtml,
    body,
    query,
    param,
    validationResult
};
