<?php
/**
 * Admin — Coordinator Management
 * Handle coordinator approvals, deactivation, department assignments, and password resets.
 */
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();
$db = getDB();
$pageTitle = 'Coordinator Management';

$departments = $db->query("SELECT * FROM departments ORDER BY name")->fetchAll();

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $userId = (int)($_POST['user_id'] ?? 0);

    if ($userId > 0) {
        if ($action === 'approve') {
            $deptId = $_POST['department_id'] ?? null;
            $deptVal = $deptId === 'none' || empty($deptId) ? null : (int)$deptId;
            
            $stmt = $db->prepare("UPDATE users SET status = 'active', department_id = ?, reset_requested = 0 WHERE id = ?");
            if ($stmt->execute([$deptVal, $userId])) {
                logActivity('Coordinator Approved', "Approved coordinator ID: $userId");
                setFlash('success', 'Coordinator approved and activated successfully.');
            } else {
                setFlash('error', 'Failed to approve coordinator.');
            }
        } elseif ($action === 'deactivate') {
            $stmt = $db->prepare("UPDATE users SET status = 'inactive' WHERE id = ?");
            if ($stmt->execute([$userId])) {
                logActivity('Coordinator Deactivated', "Deactivated coordinator ID: $userId");
                setFlash('success', 'Coordinator deactivated successfully.');
            } else {
                setFlash('error', 'Failed to deactivate coordinator.');
            }
        } elseif ($action === 'assign_dept') {
            $deptId = $_POST['department_id'] ?? null;
            $deptVal = $deptId === 'none' || empty($deptId) ? null : (int)$deptId;
            
            $stmt = $db->prepare("UPDATE users SET department_id = ? WHERE id = ?");
            if ($stmt->execute([$deptVal, $userId])) {
                logActivity('Coordinator Department Updated', "Assigned dept ID $deptId to coordinator ID: $userId");
                setFlash('success', 'Department assigned successfully.');
            } else {
                setFlash('error', 'Failed to assign department.');
            }
        } elseif ($action === 'reset_password') {
            $newPassword = $_POST['new_password'] ?? '';
            if (strlen($newPassword) < 6) {
                setFlash('error', 'Password must be at least 6 characters.');
            } else {
                $hashed = password_hash($newPassword, PASSWORD_BCRYPT);
                $stmt = $db->prepare("UPDATE users SET password = ?, reset_requested = 0 WHERE id = ?");
                if ($stmt->execute([$hashed, $userId])) {
                    logActivity('Coordinator Password Reset', "Reset password for coordinator ID: $userId");
                    setFlash('success', 'Password reset successfully.');
                } else {
                    setFlash('error', 'Failed to reset password.');
                }
            }
        } elseif ($action === 'delete') {
            // Delete user
            $stmt = $db->prepare("DELETE FROM users WHERE id = ? AND role = 'coordinator'");
            if ($stmt->execute([$userId])) {
                logActivity('Coordinator Account Deleted', "Deleted coordinator ID: $userId");
                setFlash('success', 'Coordinator account deleted successfully.');
            } else {
                setFlash('error', 'Failed to delete coordinator.');
            }
        }
    }
    header('Location: /ATTENDANCE/admin/coordinators.php');
    exit;
}

// Fetch all coordinators
$coordinators = $db->query("
    SELECT u.id, u.username, u.full_name, u.status, u.reset_requested, u.department_id, d.name as dept_name
    FROM users u
    LEFT JOIN departments d ON u.department_id = d.id
    WHERE u.role = 'coordinator'
    ORDER BY u.status DESC, u.full_name ASC
")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<!-- Action Bar -->
<div class="flex justify-between items-center mb-5">
    <div>
        <h2 class="text-lg font-bold text-white">Coordinators Directory</h2>
        <p class="text-xs text-gray-500">Manage, approve, or reset passwords for coordinators</p>
    </div>
</div>

<!-- Coordinators List Table -->
<div class="glass-card mb-6">
    <div class="glass-card-header">
        <h2 class="text-sm font-semibold text-white">Coordinators (<?= count($coordinators) ?>)</h2>
    </div>
    <div class="table-wrap">
        <table class="glass-table">
            <thead>
                <tr>
                    <th>Full Name</th>
                    <th>Username</th>
                    <th>Assigned Department</th>
                    <th>Status</th>
                    <th>Password Reset Request</th>
                    <th class="text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($coordinators)): ?>
                    <tr><td colspan="6" class="text-center text-gray-600 py-8">No coordinator accounts found.</td></tr>
                <?php else: ?>
                    <?php foreach ($coordinators as $c): ?>
                    <tr class="text-white hover:bg-white/[0.01]">
                        <td class="font-bold"><?= htmlspecialchars($c['full_name']) ?></td>
                        <td class="text-gray-400 font-mono"><?= htmlspecialchars($c['username']) ?></td>
                        <td>
                            <?php if ($c['dept_name']): ?>
                                <span class="badge badge-present"><?= htmlspecialchars($c['dept_name']) ?></span>
                            <?php else: ?>
                                <span class="text-gray-600 italic">None (No Employees Visible)</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($c['status'] === 'active'): ?>
                                <span class="badge bg-green-500/10 text-green-400 border border-green-500/20 text-xs">Active</span>
                            <?php else: ?>
                                <span class="badge bg-amber-500/10 text-amber-400 border border-amber-500/20 text-xs">Pending Approval</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($c['reset_requested']): ?>
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-bold bg-red-500/10 text-red-400 border border-red-500/25 animate-pulse">
                                    ⚠️ Reset Requested
                                </span>
                            <?php else: ?>
                                <span class="text-gray-600 text-xs">No</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-right whitespace-nowrap">
                            <?php if ($c['status'] === 'inactive'): ?>
                                <button onclick="openApproveModal(<?= $c['id'] ?>, '<?= htmlspecialchars($c['full_name'], ENT_QUOTES) ?>')" 
                                        class="text-xs text-green-400 hover:text-green-300 font-semibold mr-3">
                                    ✅ Approve & Activate
                                </button>
                            <?php else: ?>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Deactivate coordinator <?= htmlspecialchars($c['full_name'], ENT_QUOTES) ?>?');">
                                    <input type="hidden" name="action" value="deactivate">
                                    <input type="hidden" name="user_id" value="<?= $c['id'] ?>">
                                    <button type="submit" class="text-xs text-amber-400 hover:text-amber-300 font-semibold mr-3">Deactivate</button>
                                </form>
                            <?php endif; ?>

                            <button onclick="openAssignModal(<?= $c['id'] ?>, '<?= htmlspecialchars($c['full_name'], ENT_QUOTES) ?>', '<?= $c['department_id'] ?: 'none' ?>')" 
                                    class="text-xs text-primary-400 hover:text-primary-300 font-semibold mr-3">
                                📁 Assign Dept
                            </button>

                            <button onclick="openResetModal(<?= $c['id'] ?>, '<?= htmlspecialchars($c['full_name'], ENT_QUOTES) ?>')" 
                                    class="text-xs text-purple-400 hover:text-purple-300 font-semibold mr-3">
                                🔑 Reset Pass
                            </button>

                            <form method="POST" style="display:inline;" onsubmit="return confirm('Permanently delete coordinator <?= htmlspecialchars($c['full_name'], ENT_QUOTES) ?>? This cannot be undone.');">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="user_id" value="<?= $c['id'] ?>">
                                <button type="submit" class="text-xs text-red-400 hover:text-red-300 font-semibold">Delete</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal: Approve & Activate -->
<div id="approveModal" class="modal-overlay" onclick="if(event.target===this)this.classList.remove('show')">
    <div class="modal-content" style="max-width:24rem;">
        <div class="px-5 py-4 border-b border-white/10 flex items-center justify-between">
            <h3 class="text-sm font-semibold text-white">Approve Coordinator</h3>
            <button onclick="document.getElementById('approveModal').classList.remove('show')" class="text-gray-500 hover:text-white text-xl">&times;</button>
        </div>
        <form method="POST" class="p-5 space-y-4">
            <input type="hidden" name="action" value="approve">
            <input type="hidden" name="user_id" id="approveUserId">
            <div>
                <p class="text-xs text-gray-300 leading-relaxed mb-3">
                    Assign a department for <strong id="approveName" class="text-white"></strong>. Their status will change to Active.
                </p>
                <label class="block text-[10px] font-semibold text-gray-400 mb-1.5 uppercase">Select Department</label>
                <select name="department_id" class="w-full px-3 py-2 bg-dark-700/50 border border-white/10 rounded-xl text-white text-xs focus:outline-none focus:border-primary-500/50">
                    <option value="none">No Department / Assign Later</option>
                    <?php foreach ($departments as $dept): ?>
                        <option value="<?= $dept['id'] ?>"><?= htmlspecialchars($dept['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="w-full py-2.5 bg-gradient-to-r from-green-500 to-green-600 hover:from-green-400 hover:to-green-500 text-white font-bold rounded-xl text-xs uppercase tracking-wider transition-all">
                Approve & Activate
            </button>
        </form>
    </div>
</div>

<!-- Modal: Assign Department -->
<div id="assignModal" class="modal-overlay" onclick="if(event.target===this)this.classList.remove('show')">
    <div class="modal-content" style="max-width:24rem;">
        <div class="px-5 py-4 border-b border-white/10 flex items-center justify-between">
            <h3 class="text-sm font-semibold text-white">Assign Department</h3>
            <button onclick="document.getElementById('assignModal').classList.remove('show')" class="text-gray-500 hover:text-white text-xl">&times;</button>
        </div>
        <form method="POST" class="p-5 space-y-4">
            <input type="hidden" name="action" value="assign_dept">
            <input type="hidden" name="user_id" id="assignUserId">
            <div>
                <p class="text-xs text-gray-300 leading-relaxed mb-3">
                    Update the department for <strong id="assignName" class="text-white"></strong>.
                </p>
                <label class="block text-[10px] font-semibold text-gray-400 mb-1.5 uppercase">Select Department</label>
                <select name="department_id" id="assignDeptSelect" class="w-full px-3 py-2 bg-dark-700/50 border border-white/10 rounded-xl text-white text-xs focus:outline-none focus:border-primary-500/50">
                    <option value="none">No Department / Assign Later</option>
                    <?php foreach ($departments as $dept): ?>
                        <option value="<?= $dept['id'] ?>"><?= htmlspecialchars($dept['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="w-full py-2.5 bg-gradient-to-r from-primary-500 to-primary-600 hover:from-primary-400 hover:to-primary-500 text-white font-bold rounded-xl text-xs uppercase tracking-wider transition-all">
                Save Assignment
            </button>
        </form>
    </div>
</div>

<!-- Modal: Reset Password -->
<div id="resetModal" class="modal-overlay" onclick="if(event.target===this)this.classList.remove('show')">
    <div class="modal-content" style="max-width:24rem;">
        <div class="px-5 py-4 border-b border-white/10 flex items-center justify-between">
            <h3 class="text-sm font-semibold text-white">🔑 Reset Password</h3>
            <button onclick="document.getElementById('resetModal').classList.remove('show')" class="text-gray-500 hover:text-white text-xl">&times;</button>
        </div>
        <form method="POST" class="p-5 space-y-4">
            <input type="hidden" name="action" value="reset_password">
            <input type="hidden" name="user_id" id="resetUserId">
            <div>
                <p class="text-xs text-gray-300 leading-relaxed mb-3">
                    Type a new password for <strong id="resetName" class="text-white"></strong>.
                </p>
                <label class="block text-[10px] font-semibold text-gray-400 mb-1.5 uppercase">New Password</label>
                <input type="password" name="new_password" required minlength="6" placeholder="Min 6 characters" 
                       class="w-full px-3 py-2 bg-dark-700/50 border border-white/10 rounded-xl text-white text-xs focus:outline-none focus:border-primary-500/50">
            </div>
            <button type="submit" class="w-full py-2.5 bg-gradient-to-r from-purple-500 to-purple-600 hover:from-purple-400 hover:to-purple-500 text-white font-bold rounded-xl text-xs uppercase tracking-wider transition-all">
                Reset Password
            </button>
        </form>
    </div>
</div>

<script>
function openApproveModal(userId, name) {
    document.getElementById('approveUserId').value = userId;
    document.getElementById('approveName').textContent = name;
    document.getElementById('approveModal').classList.add('show');
}

function openAssignModal(userId, name, currentDeptId) {
    document.getElementById('assignUserId').value = userId;
    document.getElementById('assignName').textContent = name;
    document.getElementById('assignDeptSelect').value = currentDeptId;
    document.getElementById('assignModal').classList.add('show');
}

function openResetModal(userId, name) {
    document.getElementById('resetUserId').value = userId;
    document.getElementById('resetName').textContent = name;
    document.getElementById('resetModal').classList.add('show');
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
