<?php
/**
 * Admin — Activity Log
 */
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();
$db = getDB();
$pageTitle = 'Activity Log';

$logs = $db->query("
    SELECT al.*, u.full_name as user_name, u.role as user_role
    FROM activity_log al
    LEFT JOIN users u ON al.user_id = u.id
    ORDER BY al.created_at DESC
    LIMIT 100
")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="glass-card">
    <div class="glass-card-header">
        <h2 class="text-sm font-semibold text-white">Activity Log</h2>
        <span class="text-xs text-gray-500"><?= count($logs) ?> entries</span>
    </div>
    <div class="table-wrap">
        <table class="glass-table">
            <thead>
                <tr>
                    <th>User</th>
                    <th>Role</th>
                    <th>Action</th>
                    <th>Details</th>
                    <th>Timestamp</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($logs)): ?>
                <tr><td colspan="5" class="text-center text-gray-600 py-8">No activity recorded yet</td></tr>
            <?php else: ?>
                <?php foreach ($logs as $log): ?>
                <tr>
                    <td class="font-medium text-white whitespace-nowrap"><?= htmlspecialchars($log['user_name'] ?? 'System') ?></td>
                    <td>
                        <span class="badge <?= ($log['user_role'] ?? '') === 'admin' ? 'badge-present' : 'badge-leave' ?>">
                            <?= ucfirst($log['user_role'] ?? 'system') ?>
                        </span>
                    </td>
                    <td class="text-gray-300"><?= htmlspecialchars($log['action']) ?></td>
                    <td class="text-gray-500 text-xs max-w-[200px] truncate"><?= htmlspecialchars($log['details'] ?: '—') ?></td>
                    <td class="text-gray-500 text-xs whitespace-nowrap"><?= date('M d, Y g:i A', strtotime($log['created_at'])) ?></td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
