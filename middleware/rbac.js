// Role-Based Access Control (RBAC) middleware

function authorize(...allowedRoles) {
    return (req, res, next) => {
        if (!req.session || !req.session.user) {
            req.flash('error', 'Please log in to continue.');
            return res.redirect('/login');
        }

        const userRole = req.session.user.role;
        if (!allowedRoles.includes(userRole)) {
            return res.status(403).render('error', {
                title: 'Access Denied',
                message: 'You do not have permission to access this resource.',
                code: 403
            });
        }

        next();
    };
}

module.exports = { authorize };
