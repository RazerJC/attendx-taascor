const express = require('express');
const router = express.Router();
const { requireAuth } = require('../middleware/auth');
const { getNotifications, markAsRead, markAllAsRead } = require('../services/notification');

// GET /notifications
router.get('/', requireAuth, async (req, res) => {
    const user = req.session.user;
    const notifications = (await getNotifications(user.id, 50, 0));

    res.render('notifications/index', {
        title: 'Notifications - TAASCOR',
        notifications
    });
});

// POST /notifications/:id/read
router.post('/:id/read', requireAuth, async (req, res) => {
    const user = req.session.user;
    const id = parseInt(req.params.id);
    (await markAsRead(id, user.id));

    if (req.xhr || req.headers.accept?.includes('application/json')) {
        return res.json({ success: true });
    }
    res.redirect('back');
});

// POST /notifications/mark-all-read
router.post('/mark-all-read', requireAuth, async (req, res) => {
    const user = req.session.user;
    (await markAllAsRead(user.id));

    if (req.xhr || req.headers.accept?.includes('application/json')) {
        return res.json({ success: true });
    }
    req.flash('success', 'All notifications marked as read.');
    res.redirect('/notifications');
});

module.exports = router;
