<?php
/**
 * Shared Footer Include
 */
$user = currentUser();
?>
<?php if ($user): ?>
        </main><!-- /Page Content -->
    </div><!-- /Main Content -->

<!-- Floating Chat Widget -->
<div id="chatWidget" class="fixed bottom-6 right-6 z-50 flex flex-col items-end gap-3">
    <!-- Chat Window -->
    <div id="chatWindow" class="w-[360px] h-[480px] bg-dark-800 border border-white/10 rounded-2xl shadow-2xl overflow-hidden" style="display:none;">
        <div class="flex flex-col h-full">
        <!-- Chat Header -->
        <div class="px-4 py-3 bg-gradient-to-r from-primary-600 to-primary-700 flex items-center justify-between flex-shrink-0">
            <div class="flex items-center gap-2">
                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                <span id="chatTitle" class="text-sm font-bold text-white">Messages</span>
            </div>
            <div class="flex items-center gap-2">
                <?php if ($user['role'] === 'admin'): ?>
                <button onclick="showChatContacts()" class="text-white/70 hover:text-white text-xs" title="Back to contacts">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                </button>
                <?php endif; ?>
                <button onclick="toggleChat()" class="text-white/70 hover:text-white">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
        </div>
        <!-- Chat Body -->
        <div id="chatBody" class="flex-1 overflow-y-auto p-4 space-y-3" style="scroll-behavior:smooth;">
            <?php if ($user['role'] === 'admin'): ?>
            <div id="chatContactList" class="space-y-2">
                <div class="text-center text-xs text-gray-500 py-8">Loading contacts...</div>
            </div>
            <div id="chatMessages" class="space-y-2 hidden"></div>
            <?php else: ?>
            <div id="chatMessages" class="space-y-2">
                <div class="text-center text-xs text-gray-500 py-8">Loading messages...</div>
            </div>
            <?php endif; ?>
        </div>
        <!-- Chat Input -->
        <div id="chatInputArea" class="p-3 border-t border-white/10 flex-shrink-0 <?= $user['role'] === 'admin' ? 'hidden' : '' ?>">
            <form id="chatForm" class="flex gap-2" onsubmit="sendChatMessage(event)">
                <?= csrfField() ?>
                <input type="text" id="chatInput" placeholder="Type a message..." autocomplete="off"
                       class="flex-1 px-3 py-2 bg-dark-700/50 border border-white/10 rounded-xl text-white text-sm focus:outline-none focus:border-primary-500/50 placeholder-gray-600">
                <button type="submit" class="px-4 py-2 bg-primary-600 hover:bg-primary-500 text-white rounded-xl text-sm font-semibold transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                </button>
            </form>
        </div>
        </div>
    </div>
    <!-- Floating Chat Button -->
    <button onclick="toggleChat()" class="w-14 h-14 bg-gradient-to-br from-primary-500 to-primary-700 hover:from-primary-400 hover:to-primary-600 text-white rounded-2xl shadow-lg shadow-primary-500/30 flex items-center justify-center transition-all duration-200 hover:scale-105 relative">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
        <span id="chatBadge" class="absolute -top-1 -right-1 w-5 h-5 bg-red-500 rounded-full text-[10px] font-bold flex items-center justify-center border-2 border-dark-900 hidden">0</span>
    </button>
</div>

</div><!-- /appLayout -->
<?php endif; ?>

<script>
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    sidebar.classList.toggle('-translate-x-full');
    overlay.classList.toggle('hidden');
}

// Theme toggle
function toggleTheme() {
    const html = document.documentElement;
    const isLight = html.classList.toggle('light-mode');
    localStorage.setItem('taascor_theme', isLight ? 'light' : 'dark');
    updateThemeIcons();
}
function updateThemeIcons() {
    const isLight = document.documentElement.classList.contains('light-mode');
    const sun = document.getElementById('iconSun');
    const moon = document.getElementById('iconMoon');
    if (sun && moon) {
        sun.classList.toggle('hidden', !isLight);
        moon.classList.toggle('hidden', isLight);
    }
}

// Auto-dismiss flash
document.addEventListener('DOMContentLoaded', () => {
    const flash = document.getElementById('flashMsg');
    if (flash) setTimeout(() => flash.remove(), 4000);
    updateThemeIcons();
});

// ============================================================
// HELPERS
// ============================================================
function formatTimeAgo(dateStr) {
    const d = new Date(dateStr);
    const now = new Date();
    const mins = Math.floor((now - d) / 60000);
    if (mins < 1) return 'Just now';
    if (mins < 60) return mins + 'm ago';
    const hrs = Math.floor(mins / 60);
    if (hrs < 24) return hrs + 'h ago';
    return Math.floor(hrs / 24) + 'd ago';
}
function escHtml(s) {
    const d = document.createElement('div');
    d.textContent = s || '';
    return d.innerHTML;
}

// ============================================================
// NOTIFICATION SYSTEM
// ============================================================
let _notifOpen = false;

function toggleNotifDropdown(e) {
    e && e.stopPropagation();
    const dd = document.getElementById('notifDropdown');
    _notifOpen = !_notifOpen;
    dd.style.display = _notifOpen ? 'flex' : 'none';
    if (_notifOpen) fetchNotifications();
}

function markAllNotificationsRead(e) {
    e && e.stopPropagation();
    fetch('/ATTENDANCE/api.php', { method: 'POST', body: new URLSearchParams({ action: 'mark_notifications_read' }) })
        .then(() => fetchNotifications());
}

function fetchNotifications() {
    fetch('/ATTENDANCE/api.php?action=get_notifications')
        .then(r => r.json())
        .then(data => {
            const badge = document.getElementById('notifBadge');
            const list = document.getElementById('notifList');
            if (!badge || !list) return;
            if (data.unread_count > 0) badge.classList.remove('hidden');
            else badge.classList.add('hidden');

            if (!data.notifications || data.notifications.length === 0) {
                list.innerHTML = '<div class="p-4 text-center text-xs text-gray-500">No notifications yet</div>';
                return;
            }
            const typeIcons = { rto_request:'📋', rto_approved:'✅', rto_rejected:'❌', chat_alert:'💬', password_reset_request:'🔑', password_reset_completed:'🔐' };
            list.innerHTML = data.notifications.map(n => {
                const unread = !parseInt(n.is_read);
                const icon = typeIcons[n.type] || '🔔';
                return `<a href="${n.link || '#'}" class="block px-4 py-3 hover:bg-white/[0.03] transition-colors ${unread ? 'bg-primary-500/[0.04]' : ''}">
                    <div class="flex items-start gap-2.5">
                        <span class="text-base flex-shrink-0 mt-0.5">${icon}</span>
                        <div class="flex-1 min-w-0">
                            <p class="text-xs ${unread ? 'text-white font-semibold' : 'text-gray-400'} leading-relaxed">${escHtml(n.message)}</p>
                            <span class="text-[10px] text-gray-600 mt-1 block">${formatTimeAgo(n.created_at)}</span>
                        </div>
                        ${unread ? '<span class="w-2 h-2 bg-primary-400 rounded-full flex-shrink-0 mt-1.5"></span>' : ''}
                    </div>
                </a>`;
            }).join('');
        }).catch(() => {});
}

document.addEventListener('click', (e) => {
    if (_notifOpen && !e.target.closest('#notifBell') && !e.target.closest('#notifDropdown')) {
        _notifOpen = false;
        document.getElementById('notifDropdown').style.display = 'none';
    }
});

setInterval(fetchNotifications, 5000);
document.addEventListener('DOMContentLoaded', fetchNotifications);

// ============================================================
// CHAT SYSTEM
// ============================================================
const USER_ROLE = '<?= $user['role'] ?? '' ?>';
const USER_ID = <?= (int)($user['id'] ?? 0) ?>;
let _chatOpen = false;
let _chatOtherId = 0;
let _chatPoll = null;

function toggleChat() {
    const win = document.getElementById('chatWindow');
    _chatOpen = !_chatOpen;
    win.style.display = _chatOpen ? 'block' : 'none';
    if (_chatOpen) {
        if (USER_ROLE === 'admin') showChatContacts();
        else { loadChatHistory(); startChatPoll(); }
    } else stopChatPoll();
}

function showChatContacts() {
    const cl = document.getElementById('chatContactList');
    const ma = document.getElementById('chatMessages');
    const ia = document.getElementById('chatInputArea');
    if (cl) cl.classList.remove('hidden');
    if (ma) ma.classList.add('hidden');
    if (ia) ia.classList.add('hidden');
    document.getElementById('chatTitle').textContent = 'Coordinators';
    _chatOtherId = 0;
    stopChatPoll();

    fetch('/ATTENDANCE/api.php?action=get_chat_contacts')
        .then(r => r.json())
        .then(data => {
            if (!cl) return;
            if (!data.contacts || data.contacts.length === 0) {
                cl.innerHTML = '<div class="text-center text-xs text-gray-500 py-8">No coordinators found</div>';
                return;
            }
            cl.innerHTML = data.contacts.map(c => {
                const init = (c.full_name || '?')[0].toUpperCase();
                const unread = parseInt(c.unread_count) || 0;
                const last = c.last_msg ? escHtml(c.last_msg).substring(0, 40) : 'No messages yet';
                const time = c.last_time ? formatTimeAgo(c.last_time) : '';
                return `<div onclick="openChatWith(${c.id}, '${escHtml(c.full_name).replace(/'/g, "\\'")}' )" class="flex items-center gap-3 p-3 rounded-xl hover:bg-white/[0.04] cursor-pointer transition-colors border border-transparent hover:border-white/[0.06]">
                    <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-blue-500 to-blue-700 flex items-center justify-center text-white font-bold text-sm flex-shrink-0">${init}</div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-semibold text-white truncate">${escHtml(c.full_name)}</span>
                            <span class="text-[10px] text-gray-600">${time}</span>
                        </div>
                        <div class="flex items-center justify-between mt-0.5">
                            <span class="text-xs text-gray-500 truncate">${last}</span>
                            ${unread > 0 ? `<span class="w-5 h-5 bg-primary-500 rounded-full text-[10px] text-white font-bold flex items-center justify-center flex-shrink-0">${unread}</span>` : ''}
                        </div>
                    </div>
                </div>`;
            }).join('');
            const total = data.contacts.reduce((s, c) => s + (parseInt(c.unread_count) || 0), 0);
            const cb = document.getElementById('chatBadge');
            if (total > 0) { cb.textContent = total > 9 ? '9+' : total; cb.classList.remove('hidden'); }
            else cb.classList.add('hidden');
        }).catch(() => {});
}

function openChatWith(id, name) {
    _chatOtherId = id;
    const cl = document.getElementById('chatContactList');
    const ma = document.getElementById('chatMessages');
    const ia = document.getElementById('chatInputArea');
    if (cl) cl.classList.add('hidden');
    if (ma) ma.classList.remove('hidden');
    if (ia) ia.classList.remove('hidden');
    document.getElementById('chatTitle').textContent = name;
    loadChatHistory();
    startChatPoll();
}

function loadChatHistory() {
    const url = USER_ROLE === 'admin'
        ? `/ATTENDANCE/api.php?action=get_chat_history&other_id=${_chatOtherId}`
        : '/ATTENDANCE/api.php?action=get_chat_history';
    fetch(url).then(r => r.json()).then(data => {
        const ma = document.getElementById('chatMessages');
        if (!ma) return;
        if (!data.messages || data.messages.length === 0) {
            ma.innerHTML = '<div class="text-center text-xs text-gray-500 py-8">No messages yet. Say hello! 👋</div>';
            return;
        }
        ma.innerHTML = data.messages.map(m => {
            const mine = parseInt(m.sender_id) === USER_ID;
            const time = new Date(m.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
            return `<div class="flex ${mine ? 'justify-end' : 'justify-start'}">
                <div class="max-w-[75%] px-3.5 py-2 rounded-2xl text-sm leading-relaxed ${mine
                    ? 'bg-primary-600 text-white rounded-br-md'
                    : 'bg-white/[0.06] text-gray-300 border border-white/[0.06] rounded-bl-md'}">
                    <p>${escHtml(m.message)}</p>
                    <div class="text-[10px] ${mine ? 'text-primary-200' : 'text-gray-600'} mt-1 text-right">${time}</div>
                </div>
            </div>`;
        }).join('');
        const body = document.getElementById('chatBody');
        if (body) body.scrollTop = body.scrollHeight;
    }).catch(() => {});
}

function sendChatMessage(e) {
    e.preventDefault();
    const inp = document.getElementById('chatInput');
    const msg = inp.value.trim();
    if (!msg) return;
    const fd = new FormData();
    fd.append('action', 'send_chat_message');
    fd.append('message', msg);
    if (USER_ROLE === 'admin' && _chatOtherId) fd.append('receiver_id', _chatOtherId);
    inp.value = '';
    fetch('/ATTENDANCE/api.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => { if (d.success) loadChatHistory(); else alert('Failed: ' + (d.message || '')); })
        .catch(err => alert('Error: ' + err));
}

function startChatPoll() { stopChatPoll(); _chatPoll = setInterval(() => { if (_chatOpen) loadChatHistory(); }, 3000); }
function stopChatPoll() { if (_chatPoll) { clearInterval(_chatPoll); _chatPoll = null; } }

// Badge poll when chat closed
function pollChatBadge() {
    if (_chatOpen || USER_ROLE !== 'admin') return;
    fetch('/ATTENDANCE/api.php?action=get_chat_contacts')
        .then(r => r.json())
        .then(data => {
            const t = (data.contacts || []).reduce((s, c) => s + (parseInt(c.unread_count) || 0), 0);
            const b = document.getElementById('chatBadge');
            if (t > 0) { b.textContent = t > 9 ? '9+' : t; b.classList.remove('hidden'); }
            else b.classList.add('hidden');
        }).catch(() => {});
}
setInterval(pollChatBadge, 8000);
document.addEventListener('DOMContentLoaded', pollChatBadge);
</script>
</body>
</html>
