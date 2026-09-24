// Rate limiters disabled per user request (no 15-minute lockouts or attempt limits)
const loginLimiter = (req, res, next) => next();
const registerLimiter = (req, res, next) => next();

module.exports = { loginLimiter, registerLimiter };

