require('dotenv').config();
const express = require('express');
const path = require('path');
const session = require('express-session');
const cookieParser = require('cookie-parser');
const { initializeDb, getDb } = require('./db/database');
const { createInitialAdmin } = require('./db/seed');
const { DateTime } = require('luxon');
const csrfProtection = require('./middleware/csrf');
const { attachArea } = require('./middleware/area-guard');

const app = express();
const PORT = process.env.PORT || 3000;

// Initialize database
initializeDb();
createInitialAdmin();

// Body parsing (support image upload)
app.use(express.json({ limit: '15mb' }));
app.use(express.urlencoded({ extended: true, limit: '15mb' }));
app.use(cookieParser());

// Static files
app.use(express.static(path.join(__dirname, 'public')));

// Trust proxy for production (Render, Heroku, etc.)
app.set('trust proxy', 1);

// Health check & legacy compatibility (before CSRF and session)
app.get('/health', (req, res) => res.status(200).json({ status: 'ok', timestamp: new Date().toISOString() }));
app.get('/ATTENDANCE/health.php', (req, res) => res.status(200).send('OK'));
app.get('/ATTENDANCE', (req, res) => res.redirect('/'));
app.get('/ATTENDANCE/*', (req, res) => res.redirect('/'));

// Session
app.use(session({
    secret: process.env.SESSION_SECRET || 'taascor-attendance-monitoring-secret-key-2026',
    resave: false,
    saveUninitialized: false,
    cookie: {
        secure: process.env.NODE_ENV === 'production',
        httpOnly: true,
        maxAge: 8 * 60 * 60 * 1000, // 8 hours
        sameSite: 'lax'
    },
    name: 'taascor.sid'
}));

// View engine
app.set('view engine', 'ejs');
app.set('views', path.join(__dirname, 'views'));

// Global template variables & Area attachment
app.use((req, res, next) => {
    if (req.session.user) {
        try {
            const db = getDb();
            const freshUser = db.prepare('SELECT id, email, full_name, role, status, profile_photo, phone, bio FROM users WHERE id = ?').get(req.session.user.id);
            if (freshUser) {
                req.session.user.full_name = freshUser.full_name;
                req.session.user.profile_photo = freshUser.profile_photo;
                req.session.user.phone = freshUser.phone;
                req.session.user.bio = freshUser.bio;
            }
        } catch (e) {}
    }
    res.locals.user = req.session.user || null;
    res.locals.currentPath = req.path;
    res.locals.DateTime = DateTime;
    
    res.locals.formatDate = (dateStr) => {
        if (!dateStr) return '—';
        try {
            const str = String(dateStr).trim();
            let dt = str.includes('T')
                ? DateTime.fromISO(str, { zone: 'utc' })
                : DateTime.fromSQL(str, { zone: 'utc' });
            if (!dt.isValid) {
                dt = DateTime.fromISO(str.replace(' ', 'T'), { zone: 'utc' });
            }
            return dt.isValid ? dt.setZone('Asia/Manila').toFormat('MMM dd, yyyy') : str;
        } catch (e) {
            return dateStr;
        }
    };

    res.locals.formatDateTime = (dateStr) => {
        if (!dateStr) return '—';
        try {
            const str = String(dateStr).trim();
            let dt = str.includes('T')
                ? DateTime.fromISO(str, { zone: 'utc' })
                : DateTime.fromSQL(str, { zone: 'utc' });
            if (!dt.isValid) {
                dt = DateTime.fromISO(str.replace(' ', 'T'), { zone: 'utc' });
            }
            return dt.isValid ? dt.setZone('Asia/Manila').toFormat('MMM dd, yyyy hh:mm a') : str;
        } catch (e) {
            return dateStr;
        }
    };

    res.locals.formatTime = (timeStr) => {
        if (!timeStr) return '—';
        try {
            const parts = timeStr.split(':');
            const h = parseInt(parts[0], 10);
            const m = parts[1];
            const ampm = h >= 12 ? 'PM' : 'AM';
            const h12 = h % 12 || 12;
            return `${h12}:${m} ${ampm}`;
        } catch (e) {
            return timeStr;
        }
    };

    // Flash messages
    res.locals.flash = req.session.flash || {};
    delete req.session.flash;

    // Unread notifications
    if (req.session.user) {
        try {
            const db = getDb();
            const result = db.prepare('SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0').get(req.session.user.id);
            res.locals.unreadCount = result ? result.count : 0;
        } catch (e) {
            res.locals.unreadCount = 0;
        }
    } else {
        res.locals.unreadCount = 0;
    }

    next();
});

// Flash message helper
app.use((req, res, next) => {
    req.flash = (type, message) => {
        req.session.flash = req.session.flash || {};
        req.session.flash[type] = message;
    };
    next();
});

// Area guard attaches current area for coordinators
app.use(attachArea);

// CSRF Protection
app.use(csrfProtection);

// Mount Routes
app.use('/', require('./routes/auth'));
app.use('/dashboard', require('./routes/dashboard'));
app.use('/employees', require('./routes/employees'));
app.use('/schedules', require('./routes/schedules'));
app.use('/attendance', require('./routes/attendance'));
app.use('/coordinator-attendance', require('./routes/coordinator-attendance'));
app.use('/concerns', require('./routes/concerns'));
app.use('/btw', require('./routes/btw'));
app.use('/manpower', require('./routes/manpower'));
app.use('/notifications', require('./routes/notifications'));
app.use('/admin', require('./routes/admin'));
app.use('/departments', require('./routes/departments'));
app.use('/reports', require('./routes/reports'));
app.use('/api', require('./routes/api'));

// 404 handler
app.use((req, res) => {
    res.status(404).render('error', {
        title: 'Page Not Found',
        message: 'The page you are looking for does not exist.',
        code: 404
    });
});

// Error handler
app.use((err, req, res, next) => {
    console.error(err);

    if (err.code === 'EBADCSRFTOKEN') {
        req.flash('error', 'Form session expired or invalid CSRF token. Please try again.');
        const fallback = req.get('Referrer') || req.originalUrl || '/';
        return res.redirect(fallback);
    }

    res.status(500).render('error', {
        title: 'Server Error',
        message: process.env.NODE_ENV === 'production'
            ? 'An unexpected error occurred.'
            : err.message,
        code: 500
    });
});

if (require.main === module) {
    app.listen(PORT, () => {
        console.log(`TAASCOR Attendance Monitoring System running on http://localhost:${PORT}`);
        console.log(`Environment: ${process.env.NODE_ENV || 'development'}`);
    });
}

module.exports = app;
