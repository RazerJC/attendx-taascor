const rateLimit = require('express-rate-limit');

// Login rate limiter: max 10 attempts per 15 minutes per IP
const loginLimiter = rateLimit({
    windowMs: 15 * 60 * 1000,
    max: 10,
    standardHeaders: true,
    legacyHeaders: false,
    message: 'Too many login attempts from this IP address. Please try again after 15 minutes.',
    handler: (req, res, next, options) => {
        req.flash('error', options.message);
        res.redirect('/login');
    }
});

// Registration rate limiter
const registerLimiter = rateLimit({
    windowMs: 60 * 60 * 1000,
    max: 5,
    message: 'Too many registration requests. Please try again later.'
});

module.exports = { loginLimiter, registerLimiter };
