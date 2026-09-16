// TAASCOR Attendance Monitoring System - Client-side JS

document.addEventListener('DOMContentLoaded', () => {
    // Mobile Sidebar Toggle
    const mobileMenuBtn = document.getElementById('mobileMenuBtn');
    const sidebar = document.getElementById('sidebar');
    const sidebarOverlay = document.getElementById('sidebarOverlay');

    if (mobileMenuBtn && sidebar && sidebarOverlay) {
        mobileMenuBtn.addEventListener('click', () => {
            sidebar.classList.toggle('open');
            sidebarOverlay.classList.toggle('show');
        });

        sidebarOverlay.addEventListener('click', () => {
            sidebar.classList.remove('open');
            sidebarOverlay.classList.remove('show');
        });
    }

    // User Profile Dropdown
    const userMenuBtn = document.getElementById('userMenuBtn');
    const userMenu = document.getElementById('userMenu');

    if (userMenuBtn && userMenu) {
        userMenuBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            userMenu.classList.toggle('show');
            if (notifMenu) notifMenu.classList.remove('show');
        });
    }

    // Notification Dropdown & Polling
    const notifBellBtn = document.getElementById('notifBellBtn');
    const notifMenu = document.getElementById('notifMenu');
    const notifBadge = document.getElementById('notifCountBadge');
    const notifContainer = document.getElementById('notifItemsContainer');

    if (notifBellBtn && notifMenu) {
        notifBellBtn.addEventListener('click', async (e) => {
            e.stopPropagation();
            notifMenu.classList.toggle('show');
            if (userMenu) userMenu.classList.remove('show');

            if (notifMenu.classList.contains('show')) {
                await loadNotificationDropdown();
            }
        });
    }

    // Close dropdowns on outside click
    document.addEventListener('click', () => {
        if (userMenu) userMenu.classList.remove('show');
        if (notifMenu) notifMenu.classList.remove('show');
    });

    async function loadNotificationDropdown() {
        if (!notifContainer) return;
        try {
            const res = await fetch('/api/notifications/latest', {
                headers: { 'Accept': 'application/json' }
            });
            const data = await res.json();
            
            if (!data.notifications || data.notifications.length === 0) {
                notifContainer.innerHTML = '<div class="notif-empty">No new notifications</div>';
                return;
            }

            notifContainer.innerHTML = data.notifications.map(n => `
                <div class="notif-item ${n.is_read ? '' : 'unread'}">
                    <div class="notif-title">
                        ${n.link ? `<a href="${n.link}" style="color: inherit; text-decoration: underline;">${n.title}</a>` : n.title}
                    </div>
                    <div class="notif-msg">${n.message}</div>
                    <div class="notif-time">${new Date(n.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}</div>
                </div>
            `).join('');
        } catch (err) {
            notifContainer.innerHTML = '<div class="notif-empty">Unable to load notifications</div>';
        }
    }

    // Periodic Notification Polling (every 30s)
    async function pollUnreadCount() {
        if (!notifBadge) return;
        try {
            const res = await fetch('/api/notifications/unread-count');
            const data = await res.json();
            if (data.count > 0) {
                notifBadge.textContent = data.count;
                notifBadge.classList.remove('hidden');
            } else {
                notifBadge.classList.add('hidden');
            }
        } catch (e) {
            // Silently handle polling failure
        }
    }

    if (notifBadge) {
        setInterval(pollUnreadCount, 30000);
    }

    // Schedule Conflict Detection
    const startDateInput = document.getElementById('start_date');
    const conflictAlert = document.getElementById('conflictAlert');
    const conflictMsg = document.getElementById('conflictMsg');

    if (startDateInput && conflictAlert) {
        startDateInput.addEventListener('change', checkConflicts);
        document.querySelectorAll('.emp-checkbox').forEach(cb => {
            cb.addEventListener('change', checkConflicts);
        });

        async function checkConflicts() {
            const date = startDateInput.value;
            const checkedEmp = document.querySelector('.emp-checkbox:checked');
            if (!date || !checkedEmp) {
                conflictAlert.classList.add('hidden');
                return;
            }

            try {
                const res = await fetch(`/api/schedules/check-conflict?employee_id=${checkedEmp.value}&work_date=${date}`);
                const data = await res.json();
                if (data.conflict) {
                    conflictMsg.textContent = data.message;
                    conflictAlert.classList.remove('hidden');
                } else {
                    conflictAlert.classList.add('hidden');
                }
            } catch (e) {
                conflictAlert.classList.add('hidden');
            }
        }
    }
});
