<?php
/**
 * Admin — Clear Reports History
 * Clear attendance records and activity logs with confirmation
 */
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();
$db = getDB();
$pageTitle = 'Clear History';

$action = $_POST['action'] ?? '';
$cleared = false;
$error = '';

if ($action === 'clear_all') {
    $confirmCode = $_POST['confirm_code'] ?? '';
    if ($confirmCode !== 'CLEAR_ALL') {
        $error = 'Invalid confirmation code';
    } else {
        try {
            // Clear attendance records
            $db->query("DELETE FROM attendance");
            $attendanceCount = $db->query("SELECT FOUND_ROWS() as cnt")->fetch()['cnt'] ?? 0;
            
            // Clear activity logs
            $db->query("DELETE FROM activity_log");
            $logCount = $db->query("SELECT FOUND_ROWS() as cnt")->fetch()['cnt'] ?? 0;
            
            logActivity('Clear History', "Cleared all attendance records and activity logs");
            $cleared = true;
        } catch (Exception $e) {
            $error = 'Error clearing history: ' . $e->getMessage();
        }
    }
} elseif ($action === 'clear_attendance') {
    $confirmCode = $_POST['confirm_code'] ?? '';
    if ($confirmCode !== 'CLEAR_ATTENDANCE') {
        $error = 'Invalid confirmation code';
    } else {
        try {
            $db->query("DELETE FROM attendance");
            logActivity('Clear History', "Cleared all attendance records");
            $cleared = true;
        } catch (Exception $e) {
            $error = 'Error clearing attendance: ' . $e->getMessage();
        }
    }
} elseif ($action === 'clear_logs') {
    $confirmCode = $_POST['confirm_code'] ?? '';
    if ($confirmCode !== 'CLEAR_LOGS') {
        $error = 'Invalid confirmation code';
    } else {
        try {
            $db->query("DELETE FROM activity_log");
            logActivity('Clear History', "Cleared all activity logs");
            $cleared = true;
        } catch (Exception $e) {
            $error = 'Error clearing logs: ' . $e->getMessage();
        }
    }
}

// Get statistics
$attStats = $db->query("SELECT COUNT(*) as count FROM attendance")->fetch();
$logStats = $db->query("SELECT COUNT(*) as count FROM activity_log")->fetch();
$attCount = $attStats['count'] ?? 0;
$logCount = $logStats['count'] ?? 0;

require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-2xl mx-auto">
    <!-- Success Message -->
    <?php if ($cleared): ?>
    <div class="mb-4 p-4 bg-green-500/20 border border-green-500/30 rounded-xl text-green-400">
        ✅ History cleared successfully!
    </div>
    <?php endif; ?>

    <!-- Error Message -->
    <?php if ($error): ?>
    <div class="mb-4 p-4 bg-red-500/20 border border-red-500/30 rounded-xl text-red-400">
        ❌ <?= htmlspecialchars($error) ?>
    </div>
    <?php endif; ?>

    <!-- Statistics -->
    <div class="glass-card mb-6">
        <div class="glass-card-header">
            <h2 class="text-sm font-semibold text-white">Current Data</h2>
        </div>
        <div class="p-4 space-y-3">
            <div class="flex items-center justify-between">
                <span class="text-sm text-gray-400">Attendance Records:</span>
                <span class="text-lg font-semibold text-white"><?= number_format($attCount) ?></span>
            </div>
            <div class="flex items-center justify-between">
                <span class="text-sm text-gray-400">Activity Logs:</span>
                <span class="text-lg font-semibold text-white"><?= number_format($logCount) ?></span>
            </div>
        </div>
    </div>

    <!-- Clear Options -->
    <div class="space-y-4">
        <!-- Clear All -->
        <div class="glass-card">
            <div class="glass-card-header">
                <h3 class="text-sm font-semibold text-white">🔴 Clear Everything</h3>
            </div>
            <div class="p-4">
                <p class="text-xs text-gray-400 mb-4">Delete all attendance records AND activity logs. This cannot be undone.</p>
                <form method="POST" onsubmit="return confirm('⚠️ This will permanently delete ALL attendance records and activity logs. Are you absolutely sure?');">
                    <input type="hidden" name="action" value="clear_all">
                    <div class="mb-4">
                        <label class="block text-xs font-medium text-gray-400 mb-1.5 uppercase tracking-wider">Confirmation Code</label>
                        <input type="text" name="confirm_code" placeholder="Type: CLEAR_ALL" 
                               class="w-full px-4 py-2.5 bg-dark-700/50 border border-white/10 rounded-xl text-white text-sm focus:outline-none focus:border-red-500/50 font-mono"
                               required>
                        <p class="text-xs text-gray-600 mt-2">Type the confirmation code above to proceed</p>
                    </div>
                    <button type="submit" class="w-full py-2.5 bg-red-600 hover:bg-red-500 text-white font-semibold rounded-xl text-sm transition-all">
                        Delete All Records & Logs
                    </button>
                </form>
            </div>
        </div>

        <!-- Clear Attendance Only -->
        <div class="glass-card">
            <div class="glass-card-header">
                <h3 class="text-sm font-semibold text-white">📅 Clear Attendance Only</h3>
            </div>
            <div class="p-4">
                <p class="text-xs text-gray-400 mb-4">Delete only attendance records (<?= number_format($attCount) ?>). Activity logs will be preserved.</p>
                <form method="POST" onsubmit="return confirm('Delete all attendance records? Activity logs will be kept.');">
                    <input type="hidden" name="action" value="clear_attendance">
                    <div class="mb-4">
                        <label class="block text-xs font-medium text-gray-400 mb-1.5 uppercase tracking-wider">Confirmation Code</label>
                        <input type="text" name="confirm_code" placeholder="Type: CLEAR_ATTENDANCE" 
                               class="w-full px-4 py-2.5 bg-dark-700/50 border border-white/10 rounded-xl text-white text-sm focus:outline-none focus:border-orange-500/50 font-mono"
                               required>
                        <p class="text-xs text-gray-600 mt-2">Type the confirmation code above to proceed</p>
                    </div>
                    <button type="submit" class="w-full py-2.5 bg-orange-600 hover:bg-orange-500 text-white font-semibold rounded-xl text-sm transition-all">
                        Delete Attendance Records
                    </button>
                </form>
            </div>
        </div>

        <!-- Clear Logs Only -->
        <div class="glass-card">
            <div class="glass-card-header">
                <h3 class="text-sm font-semibold text-white">📝 Clear Activity Logs Only</h3>
            </div>
            <div class="p-4">
                <p class="text-xs text-gray-400 mb-4">Delete only activity logs (<?= number_format($logCount) ?>). Attendance records will be preserved.</p>
                <form method="POST" onsubmit="return confirm('Delete all activity logs? Attendance records will be kept.');">
                    <input type="hidden" name="action" value="clear_logs">
                    <div class="mb-4">
                        <label class="block text-xs font-medium text-gray-400 mb-1.5 uppercase tracking-wider">Confirmation Code</label>
                        <input type="text" name="confirm_code" placeholder="Type: CLEAR_LOGS" 
                               class="w-full px-4 py-2.5 bg-dark-700/50 border border-white/10 rounded-xl text-white text-sm focus:outline-none focus:border-yellow-500/50 font-mono"
                               required>
                        <p class="text-xs text-gray-600 mt-2">Type the confirmation code above to proceed</p>
                    </div>
                    <button type="submit" class="w-full py-2.5 bg-yellow-600 hover:bg-yellow-500 text-white font-semibold rounded-xl text-sm transition-all">
                        Delete Activity Logs
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Warning -->
    <div class="mt-6 p-4 bg-yellow-500/10 border border-yellow-500/30 rounded-xl">
        <p class="text-xs text-yellow-400">
            <strong>⚠️ Warning:</strong> These actions are permanent and cannot be undone. Make sure you have a backup if you need to keep this data.
        </p>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
