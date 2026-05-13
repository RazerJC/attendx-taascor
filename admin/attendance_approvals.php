<?php
/**
 * Admin — Attendance Edit Approvals
 * Review and approve/reject coordinator edit requests for past date attendance
 */
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();
$db = getDB();
$user = currentUser();
$pageTitle = 'Attendance Edit Approvals';

// Filter by status
$filter = $_GET['filter'] ?? 'pending'; // pending, approved, rejected, all
$validFilters = ['pending', 'approved', 'rejected', 'all'];
if (!in_array($filter, $validFilters)) $filter = 'pending';

// Get pagination
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 15;
$offset = ($page - 1) * $perPage;

// Build query
$whereClause = '';
if ($filter !== 'all') {
    $whereClause = "WHERE aer.status = '$filter'";
}

// Count total
$countStmt = $db->query("SELECT COUNT(*) FROM attendance_edit_requests aer $whereClause");
$total = $countStmt->fetchColumn();
$totalPages = ceil($total / $perPage);

// Get requests with employee and coordinator info
$stmt = $db->prepare("
    SELECT 
        aer.id,
        aer.employee_id,
        aer.attendance_date,
        aer.old_status,
        aer.new_status,
        aer.reason,
        aer.status,
        aer.created_at,
        aer.approved_at,
        aer.approved_by,
        e.first_name,
        e.last_name,
        e.position,
        e.department_id,
        d.name as department_name,
        u1.full_name as requested_by_name,
        u2.full_name as approved_by_name
    FROM attendance_edit_requests aer
    JOIN employees e ON aer.employee_id = e.id
    JOIN departments d ON e.department_id = d.id
    JOIN users u1 ON aer.requested_by = u1.id
    LEFT JOIN users u2 ON aer.approved_by = u2.id
    $whereClause
    ORDER BY aer.created_at DESC
    LIMIT ? OFFSET ?
");
$stmt->execute([$perPage, $offset]);
$requests = $stmt->fetchAll();

// Status colors
$statusColors = [
    'pending' => ['bg' => 'bg-amber-500/20', 'border' => 'border-amber-500/40', 'text' => 'text-amber-300', 'label' => '⏳ Pending'],
    'approved' => ['bg' => 'bg-green-500/20', 'border' => 'border-green-500/40', 'text' => 'text-green-300', 'label' => '✅ Approved'],
    'rejected' => ['bg' => 'bg-red-500/20', 'border' => 'border-red-500/40', 'text' => 'text-red-300', 'label' => '❌ Rejected'],
];

require_once __DIR__ . '/../includes/header.php';
?>

<!-- Page Header -->
<div class="mb-6">
    <div class="p-6 rounded-2xl bg-gradient-to-r from-indigo-600/20 via-indigo-500/10 to-transparent border border-indigo-500/30">
        <div class="flex items-center gap-4">
            <div class="w-14 h-14 rounded-xl bg-indigo-500/20 flex items-center justify-center text-3xl flex-shrink-0">
                ✏️
            </div>
            <div>
                <h1 class="text-3xl font-bold text-white">Attendance Edit Approvals</h1>
                <p class="text-gray-400 mt-1">Review and manage coordinator requests to edit past attendance records</p>
            </div>
        </div>
    </div>
</div>

<!-- Filter Tabs -->
<div class="flex flex-wrap gap-2 mb-6 border-b border-white/10 pb-4">
    <a href="?filter=pending" class="px-4 py-2 text-sm font-semibold rounded-lg transition-all <?= $filter === 'pending' ? 'bg-amber-500/20 text-amber-300 border border-amber-500/40' : 'text-gray-400 hover:text-gray-300' ?>">
        ⏳ Pending (<?php 
            $c = $db->query("SELECT COUNT(*) FROM attendance_edit_requests WHERE status='pending'");
            echo $c->fetchColumn();
        ?>)
    </a>
    <a href="?filter=approved" class="px-4 py-2 text-sm font-semibold rounded-lg transition-all <?= $filter === 'approved' ? 'bg-green-500/20 text-green-300 border border-green-500/40' : 'text-gray-400 hover:text-gray-300' ?>">
        ✅ Approved
    </a>
    <a href="?filter=rejected" class="px-4 py-2 text-sm font-semibold rounded-lg transition-all <?= $filter === 'rejected' ? 'bg-red-500/20 text-red-300 border border-red-500/40' : 'text-gray-400 hover:text-gray-300' ?>">
        ❌ Rejected
    </a>
    <a href="?filter=all" class="px-4 py-2 text-sm font-semibold rounded-lg transition-all <?= $filter === 'all' ? 'bg-primary-500/20 text-primary-300 border border-primary-500/40' : 'text-gray-400 hover:text-gray-300' ?>">
        📋 All Requests
    </a>
</div>

<!-- Requests List -->
<?php if (empty($requests)): ?>
    <div class="glass-card p-12 text-center">
        <div class="text-5xl mb-4">📭</div>
        <h3 class="text-lg font-semibold text-white mb-2">No requests found</h3>
        <p class="text-gray-400">There are no <?= $filter === 'all' ? '' : "$filter " ?>attendance edit requests at this time.</p>
    </div>
<?php else: ?>
    <div class="space-y-3">
        <?php foreach ($requests as $req): 
            $colors = $statusColors[$req['status']] ?? $statusColors['pending'];
            $statusLabel = $colors['label'];
            $isOldToNew = $req['old_status'] !== $req['new_status'];
        ?>
        <div class="glass-card overflow-hidden hover:border-white/20 transition-colors">
            <div class="p-5 <?= $colors['bg'] ?> border-b <?= $colors['border'] ?>">
                <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
                    <div class="flex-1">
                        <div class="flex items-center gap-3 mb-2">
                            <div class="text-lg font-bold text-white">
                                <?= htmlspecialchars($req['last_name'] . ', ' . $req['first_name']) ?>
                            </div>
                            <span class="text-xs bg-white/10 px-2.5 py-1 rounded-full text-gray-400">
                                <?= htmlspecialchars($req['position'] ?: 'N/A') ?>
                            </span>
                        </div>
                        <p class="text-xs text-gray-400">
                            Department: <strong><?= htmlspecialchars($req['department_name']) ?></strong>
                        </p>
                    </div>
                    <span class="<?= $colors['text'] ?> <?= $colors['bg'] ?> border <?= $colors['border'] ?> px-3 py-1.5 rounded-full text-xs font-semibold whitespace-nowrap">
                        <?= $statusLabel ?>
                    </span>
                </div>
            </div>

            <div class="p-5 space-y-4">
                <!-- Request Details -->
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                    <div>
                        <p class="text-xs text-gray-500 uppercase tracking-wider font-semibold mb-1">Attendance Date</p>
                        <p class="text-base font-bold text-white"><?= date('M d, Y', strtotime($req['attendance_date'])) ?></p>
                        <p class="text-xs text-gray-600 mt-0.5"><?= date('l', strtotime($req['attendance_date'])) ?></p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 uppercase tracking-wider font-semibold mb-1">Requested By</p>
                        <p class="text-base font-bold text-white"><?= htmlspecialchars($req['requested_by_name']) ?></p>
                        <p class="text-xs text-gray-600 mt-0.5"><?= date('M d, Y h:i A', strtotime($req['created_at'])) ?></p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 uppercase tracking-wider font-semibold mb-1">Previous Status</p>
                        <?php if ($req['old_status']): ?>
                            <span class="inline-flex items-center px-2.5 py-1 bg-gray-600/30 text-gray-300 rounded-lg text-xs font-semibold">
                                <?php 
                                    $oldMap = ['present'=>'Present', 'absent'=>'Absent', 'no_work'=>'No Work', 'leave'=>'Sick Leave', 'sent_home'=>'Sent Home', 'rest_day'=>'Rest Day'];
                                    echo $oldMap[$req['old_status']] ?? $req['old_status'];
                                ?>
                            </span>
                        <?php else: ?>
                            <p class="text-xs text-gray-600">No Record</p>
                        <?php endif; ?>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 uppercase tracking-wider font-semibold mb-1">New Status</p>
                        <span class="inline-flex items-center px-2.5 py-1 bg-primary-600/30 text-primary-300 rounded-lg text-xs font-semibold">
                            <?php 
                                $newMap = ['present'=>'Present', 'absent'=>'Absent', 'no_work'=>'No Work', 'leave'=>'Sick Leave', 'sent_home'=>'Sent Home', 'rest_day'=>'Rest Day'];
                                echo $newMap[$req['new_status']] ?? $req['new_status'];
                            ?>
                        </span>
                    </div>
                </div>

                <?php if ($req['reason']): ?>
                <!-- Reason -->
                <div class="bg-white/5 border border-white/10 rounded-lg p-3">
                    <p class="text-xs text-gray-500 uppercase tracking-wider font-semibold mb-2">Reason</p>
                    <p class="text-sm text-gray-300"><?= htmlspecialchars($req['reason']) ?></p>
                </div>
                <?php endif; ?>

                <?php if ($req['status'] !== 'pending'): ?>
                <!-- Approval Info -->
                <div class="bg-white/5 border border-white/10 rounded-lg p-3">
                    <p class="text-xs text-gray-500 uppercase tracking-wider font-semibold mb-2">
                        <?= $req['status'] === 'approved' ? '✅ Approved By' : '❌ Rejected By' ?>
                    </p>
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-white"><?= htmlspecialchars($req['approved_by_name'] ?? 'System') ?></span>
                        <span class="text-xs text-gray-600"><?= date('M d, Y h:i A', strtotime($req['approved_at'])) ?></span>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Action Buttons (only for pending) -->
                <?php if ($req['status'] === 'pending'): ?>
                <div class="flex gap-2 pt-2 border-t border-white/10">
                    <button onclick="approveRequest(<?= $req['id'] ?>)" class="flex-1 px-4 py-2.5 bg-green-600/20 hover:bg-green-600/30 border border-green-500/40 text-green-300 rounded-lg text-sm font-semibold transition-colors">
                        ✅ Approve
                    </button>
                    <button onclick="rejectRequest(<?= $req['id'] ?>)" class="flex-1 px-4 py-2.5 bg-red-600/20 hover:bg-red-600/30 border border-red-500/40 text-red-300 rounded-lg text-sm font-semibold transition-colors">
                        ❌ Reject
                    </button>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <div class="flex justify-center gap-2 mt-8">
        <?php for ($p = 1; $p <= $totalPages; $p++): ?>
            <a href="?filter=<?= $filter ?>&page=<?= $p ?>" class="px-4 py-2 rounded-lg text-sm font-semibold transition-colors <?= $p === $page ? 'bg-primary-600 text-white' : 'bg-white/5 text-gray-400 hover:bg-white/10' ?>">
                <?= $p ?>
            </a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
<?php endif; ?>

<!-- Approve Confirmation Modal -->
<div id="approveModal" class="modal-overlay">
    <div class="modal-content" style="max-width:24rem;">
        <div class="px-5 py-4 border-b border-white/10 flex items-center justify-between">
            <h3 class="text-sm font-semibold text-white">✅ Approve Edit Request</h3>
            <button type="button" data-close-modal="approveModal" class="text-gray-500 hover:text-white text-xl">&times;</button>
        </div>
        <div class="p-5 space-y-4">
            <div class="bg-green-600/10 border border-green-500/30 rounded-lg p-3">
                <p class="text-sm text-green-300">Are you sure you want to approve this attendance edit request?</p>
            </div>
            <input type="hidden" id="approveRequestId">
            <div>
                <label class="block text-xs font-medium text-gray-400 mb-1.5 uppercase tracking-wider">Notes (Optional)</label>
                <textarea id="approveNotes" class="w-full px-4 py-2.5 bg-dark-700/50 border border-white/10 rounded-xl text-white text-sm focus:outline-none focus:border-primary-500/50 resize-none" rows="3" placeholder="Add any notes..."></textarea>
            </div>
            <div class="flex gap-2">
                <button type="button" data-close-modal="approveModal" class="flex-1 py-2.5 bg-white/5 hover:bg-white/10 text-gray-300 font-semibold rounded-xl text-sm transition-colors">Cancel</button>
                <button type="button" onclick="confirmApprove()" class="flex-1 py-2.5 bg-green-600 hover:bg-green-500 text-white font-semibold rounded-xl text-sm transition-colors">Approve</button>
            </div>
        </div>
    </div>
</div>

<!-- Reject Confirmation Modal -->
<div id="rejectModal" class="modal-overlay">
    <div class="modal-content" style="max-width:24rem;">
        <div class="px-5 py-4 border-b border-white/10 flex items-center justify-between">
            <h3 class="text-sm font-semibold text-white">❌ Reject Edit Request</h3>
            <button type="button" data-close-modal="rejectModal" class="text-gray-500 hover:text-white text-xl">&times;</button>
        </div>
        <div class="p-5 space-y-4">
            <div class="bg-red-600/10 border border-red-500/30 rounded-lg p-3">
                <p class="text-sm text-red-300">Are you sure you want to reject this attendance edit request?</p>
            </div>
            <input type="hidden" id="rejectRequestId">
            <div>
                <label class="block text-xs font-medium text-gray-400 mb-1.5 uppercase tracking-wider">Rejection Reason</label>
                <textarea id="rejectReason" class="w-full px-4 py-2.5 bg-dark-700/50 border border-white/10 rounded-xl text-white text-sm focus:outline-none focus:border-primary-500/50 resize-none" rows="3" placeholder="Explain why this request is being rejected..." required></textarea>
            </div>
            <div class="flex gap-2">
                <button type="button" data-close-modal="rejectModal" class="flex-1 py-2.5 bg-white/5 hover:bg-white/10 text-gray-300 font-semibold rounded-xl text-sm transition-colors">Cancel</button>
                <button type="button" onclick="confirmReject()" class="flex-1 py-2.5 bg-red-600 hover:bg-red-500 text-white font-semibold rounded-xl text-sm transition-colors">Reject</button>
            </div>
        </div>
    </div>
</div>

<script>
    function approveRequest(requestId) {
        document.getElementById('approveRequestId').value = requestId;
        document.getElementById('approveNotes').value = '';
        document.querySelector('[data-open-modal="approveModal"]')?.click();
        // Manually open modal since we're using custom modal system
        const modal = document.getElementById('approveModal');
        modal.classList.add('show');
        modal.style.display = 'flex';
    }

    function rejectRequest(requestId) {
        document.getElementById('rejectRequestId').value = requestId;
        document.getElementById('rejectReason').value = '';
        const modal = document.getElementById('rejectModal');
        modal.classList.add('show');
        modal.style.display = 'flex';
    }

    function confirmApprove() {
        const requestId = document.getElementById('approveRequestId').value;
        const notes = document.getElementById('approveNotes').value;
        
        // Submit via AJAX
        fetch('/ATTENDANCE/api.php?action=approve_edit_request', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'id=' + requestId + '&notes=' + encodeURIComponent(notes)
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Error: ' + (data.error || 'Failed to approve'));
            }
        });
    }

    function confirmReject() {
        const requestId = document.getElementById('rejectRequestId').value;
        const reason = document.getElementById('rejectReason').value;
        
        if (!reason.trim()) {
            alert('Please provide a rejection reason');
            return;
        }
        
        // Submit via AJAX
        fetch('/ATTENDANCE/api.php?action=reject_edit_request', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'id=' + requestId + '&reason=' + encodeURIComponent(reason)
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Error: ' + (data.error || 'Failed to reject'));
            }
        });
    }

    // Modal system
    document.addEventListener('click', function(e) {
        if (e.target.matches('[data-close-modal]')) {
            const modalId = e.target.getAttribute('data-close-modal');
            const modal = document.getElementById(modalId);
            modal.classList.remove('show');
            modal.style.display = 'none';
        }
    });
</script>

<style>
    .modal-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0,0,0,0.5);
        z-index: 1000;
        align-items: center;
        justify-content: center;
    }
    
    .modal-overlay.show {
        display: flex;
    }
</style>
