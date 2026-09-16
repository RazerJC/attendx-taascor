<?php
/**
 * Database Reset Utility — TAASCOR AttendX
 * DANGER: Highly destructive. Drops and recreates database tables.
 * Strict protection: requires admin authentication, CSRF token, and explicit confirmation string.
 */
require_once __DIR__ . '/includes/auth.php';

requireAdmin();

$confirmed = false;
$error = '';
$results = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid or expired session token.';
    } elseif (($_POST['confirmation'] ?? '') !== 'CONFIRM_PERMANENT_DATABASE_RESET') {
        $error = 'Confirmation string did not match. Reset aborted.';
    } else {
        try {
            $db = getDB();
            $db->exec("SET FOREIGN_KEY_CHECKS = 0");
            
            // Drop all tables
            $tables = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
            foreach ($tables as $table) {
                $db->exec("DROP TABLE IF EXISTS `$table`");
                $results[] = "Dropped table: $table";
            }
            
            // Re-run init schema
            $sqlFile = __DIR__ . '/init-database.sql';
            if (file_exists($sqlFile)) {
                $sql = file_get_contents($sqlFile);
                $db->exec($sql);
                $results[] = "Re-initialized schema from init-database.sql";
            }
            
            $db->exec("SET FOREIGN_KEY_CHECKS = 1");
            $confirmed = true;
        } catch (Exception $e) {
            $error = 'Reset failed: ' . $e->getMessage();
        }
    }
}

$pageTitle = 'Database Reset Tool';
require_once __DIR__ . '/includes/header.php';
?>
<div class="p-6 md:p-8 max-w-2xl mx-auto">
    <div class="bg-red-500/10 border border-red-500/30 rounded-3xl p-8">
        <h1 class="text-xl font-bold text-red-400 mb-2">⚠️ Danger Zone: Complete Database Reset</h1>
        <p class="text-xs text-gray-400 leading-relaxed mb-6">
            This tool will drop <strong>ALL</strong> tables and permanently delete all employee, attendance, user, and department records.
            This action cannot be undone. Please ensure you have created a backup first via <a href="/ATTENDANCE/backup_database.php" class="text-primary-400 underline">Backup Database</a>.
        </p>

        <?php if ($error): ?>
        <div class="mb-6 p-4 bg-red-500/20 border border-red-500/40 rounded-xl text-red-300 text-xs font-semibold">
            <?= htmlspecialchars($error) ?>
        </div>
        <?php endif; ?>

        <?php if ($confirmed): ?>
        <div class="p-4 bg-green-500/20 border border-green-500/40 rounded-xl text-green-300 text-xs font-semibold mb-6">
            <p class="font-bold mb-2">✅ Database has been completely reset and re-initialized.</p>
            <ul class="list-disc list-inside space-y-1 text-[11px] text-green-200">
                <?php foreach ($results as $res): ?>
                    <li><?= htmlspecialchars($res) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <a href="/ATTENDANCE/admin/dashboard.php" class="inline-block px-5 py-2.5 bg-primary-600 hover:bg-primary-500 text-white font-bold rounded-xl text-xs uppercase tracking-wider">
            Return to Dashboard
        </a>
        <?php else: ?>
        <form method="POST" class="space-y-4" onsubmit="return confirm('ARE YOU 100% SURE? All data will be permanently wiped.');">
            <?= csrfField() ?>
            <div>
                <label class="block text-[10px] font-semibold text-gray-300 mb-1 uppercase tracking-wider">
                    Type <code class="text-red-400 font-mono">CONFIRM_PERMANENT_DATABASE_RESET</code> to proceed:
                </label>
                <input type="text" name="confirmation" required autocomplete="off"
                       class="w-full px-4 py-3 bg-dark-800 border border-red-500/30 rounded-xl text-white font-mono text-xs focus:outline-none focus:border-red-500">
            </div>
            <button type="submit" class="w-full py-3 bg-red-600 hover:bg-red-500 text-white font-bold rounded-xl text-xs uppercase tracking-wider transition-all">
                Permanently Wipe and Reinitialize Database
            </button>
        </form>
        <?php endif; ?>
    </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
