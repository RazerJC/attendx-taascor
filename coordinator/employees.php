<?php
/**
 * Coordinator — My Employees
 * Shows employees under this coordinator's assigned department.
 */
require_once __DIR__ . '/../includes/auth.php';
requireCoordinator();
$db = getDB();
$user = currentUser();
$pageTitle = 'My Employees';

$search = $_GET['search'] ?? '';

// Get coordinator's department info
$coordDeptId = $user['department_id'];
$coordDeptName = '';
if ($coordDeptId) {
    $deptStmt = $db->prepare("SELECT name FROM departments WHERE id = ?");
    $deptStmt->execute([$coordDeptId]);
    $coordDeptName = $deptStmt->fetchColumn() ?: '';
}

// Get employees visible to this coordinator
$allEmps = getVisibleEmployees($db);

// Apply search filter
if ($search) {
    $searchLower = strtolower($search);
    $allEmps = array_filter($allEmps, function($emp) use ($searchLower) {
        return strpos(strtolower($emp['first_name']), $searchLower) !== false
            || strpos(strtolower($emp['last_name']), $searchLower) !== false
            || strpos(strtolower($emp['position'] ?? ''), $searchLower) !== false;
    });
}

$totalEmployees = count($allEmps);

// Group by position
$empsByPos = [];
foreach ($allEmps as $emp) {
    $position = $emp['position'] ?: 'Unassigned';
    $empsByPos[$position][] = $emp;
}
ksort($empsByPos);

// Get today's attendance for these employees
$today = date('Y-m-d');
$attStmt = $db->prepare("SELECT employee_id, status FROM attendance WHERE date=?");
$attStmt->execute([$today]);
$attMap = [];
foreach ($attStmt->fetchAll() as $a) {
    $attMap[$a['employee_id']] = $a['status'];
}

// Status counts
$counts = ['present'=>0, 'absent'=>0, 'no_work'=>0, 'leave'=>0, 'sent_home'=>0, 'rest_day'=>0];
$noRecord = 0;
foreach ($allEmps as $emp) {
    $s = $attMap[$emp['id']] ?? '';
    if (isset($counts[$s])) {
        $counts[$s]++;
    } else {
        $noRecord++;
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<!-- Page Header -->
<div class="mb-6 p-5 rounded-2xl bg-gradient-to-r from-blue-600/20 via-blue-500/10 to-transparent border border-blue-500/15">
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div class="flex items-center gap-4">
            <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-blue-500 to-blue-700 flex items-center justify-center text-white font-bold text-lg shadow-lg shadow-blue-500/20 flex-shrink-0">
                <?= strtoupper(substr($user['full_name'], 0, 1)) ?>
            </div>
            <div>
                <h2 class="text-xl font-bold text-white">My Employees</h2>
                <?php if ($coordDeptName): ?>
                    <p class="text-sm text-gray-400 mt-0.5">
                        Department: <span class="text-blue-400 font-semibold"><?= htmlspecialchars($coordDeptName) ?></span>
                    </p>
                <?php else: ?>
                    <p class="text-sm text-amber-400 mt-0.5">⚠️ No department assigned — contact admin</p>
                <?php endif; ?>
            </div>
        </div>
        <div class="flex items-center gap-2 flex-wrap">
            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-white/5 border border-white/10 rounded-full text-xs font-semibold text-gray-300">
                👥 <?= $totalEmployees ?> Total
            </span>
            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-green-500/10 border border-green-500/20 rounded-full text-xs font-semibold text-green-400">
                ✅ <?= $counts['present'] ?> Present
            </span>
        </div>
    </div>
</div>

<!-- Search Bar -->
<div class="mb-5">
    <form method="GET" class="flex gap-2">
        <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search by name or position..."
               class="flex-1 px-4 py-2.5 bg-dark-700/50 border border-white/10 rounded-xl text-white placeholder-gray-600 text-sm focus:outline-none focus:border-primary-500/50">
        <button type="submit" class="px-5 py-2.5 bg-primary-600 hover:bg-primary-500 text-white rounded-xl text-xs font-semibold uppercase tracking-wider transition-colors">
            Search
        </button>
        <?php if ($search): ?>
            <a href="?" class="px-4 py-2.5 bg-white/5 hover:bg-white/10 text-gray-400 rounded-xl text-xs font-semibold uppercase tracking-wider transition-colors border border-white/10">
                Clear
            </a>
        <?php endif; ?>
    </form>
</div>

<!-- Today's Status Summary -->
<div class="grid grid-cols-4 md:grid-cols-7 gap-2 md:gap-3 mb-5">
    <div class="stat-card">
        <div class="stat-icon bg-green-500/15 text-green-400">✅</div>
        <div class="stat-value text-green-400"><?= $counts['present'] ?></div>
        <div class="stat-label">P</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon bg-red-500/15 text-red-400">❌</div>
        <div class="stat-value text-red-400"><?= $counts['absent'] ?></div>
        <div class="stat-label">A</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon bg-gray-500/15 text-gray-400">🚫</div>
        <div class="stat-value text-gray-400"><?= $counts['no_work'] ?></div>
        <div class="stat-label">NW</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon bg-purple-500/15 text-purple-400">📋</div>
        <div class="stat-value text-purple-400"><?= $counts['leave'] ?></div>
        <div class="stat-label">SL</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon bg-teal-500/15 text-teal-400">🏠</div>
        <div class="stat-value text-teal-400"><?= $counts['sent_home'] ?></div>
        <div class="stat-label">SH</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon bg-orange-500/15 text-orange-400">🛏️</div>
        <div class="stat-value text-orange-400"><?= $counts['rest_day'] ?></div>
        <div class="stat-label">RD</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon bg-white/5 text-gray-500">—</div>
        <div class="stat-value text-gray-500"><?= $noRecord ?></div>
        <div class="stat-label">No Record</div>
    </div>
</div>

<?php if (!$coordDeptId): ?>
    <!-- No Department Warning -->
    <div class="glass-card p-8 text-center">
        <div class="text-4xl mb-3">⚠️</div>
        <h3 class="text-lg font-bold text-white mb-2">No Department Assigned</h3>
        <p class="text-sm text-gray-400">Your account doesn't have a department assigned yet. Please contact the administrator to assign you a department so you can see your employees.</p>
    </div>
<?php elseif (empty($allEmps)): ?>
    <div class="glass-card p-8 text-center">
        <div class="text-4xl mb-3">📭</div>
        <h3 class="text-lg font-bold text-white mb-2">No Employees Found</h3>
        <p class="text-sm text-gray-400">
            <?php if ($search): ?>
                No employees match your search "<strong class="text-white"><?= htmlspecialchars($search) ?></strong>". Try a different search term.
            <?php else: ?>
                No active employees in your department yet.
            <?php endif; ?>
        </p>
    </div>
<?php else: ?>
    <!-- Employees Grouped by Position -->
    <div class="space-y-3">
        <?php foreach ($empsByPos as $position => $emps):
            $posCount = count($emps);
            $posKey = md5($position);
            
            // Count statuses for this position
            $posCounts = ['present'=>0,'absent'=>0,'no_work'=>0,'leave'=>0,'sent_home'=>0,'rest_day'=>0,'none'=>0];
            foreach ($emps as $emp) {
                $s = $attMap[$emp['id']] ?? 'none';
                $posCounts[$s]++;
            }
        ?>
        <div class="glass-card position-folder" data-pos-key="<?= $posKey ?>">
            <!-- Position Header -->
            <div class="glass-card-header cursor-pointer select-none hover:bg-white/[0.02] transition-colors" onclick="toggleFolder('<?= $posKey ?>')">
                <div class="flex items-center gap-2.5">
                    <svg class="w-4 h-4 text-gray-500 transition-transform duration-200" id="arrow_<?= $posKey ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    <span class="text-sm font-semibold text-white flex items-center">
                        <img src="/ATTENDANCE/assets/images/staff_icon.png" class="w-8 h-8 inline-block mr-2 object-contain flex-shrink-0" alt="Position">
                        <span><?= htmlspecialchars($position) ?></span>
                    </span>
                    <span class="text-xs text-gray-500 bg-white/5 px-2 py-0.5 rounded-full font-semibold"><?= $posCount ?></span>
                    <!-- Status summary -->
                    <div class="flex gap-1.5 ml-2 text-[10px] font-semibold">
                        <?php if ($posCounts['present']): ?><span class="text-green-400 font-bold"><?= $posCounts['present'] ?>P</span><?php endif; ?>
                        <?php if ($posCounts['absent']): ?><span class="text-red-400 font-bold"><?= $posCounts['absent'] ?>A</span><?php endif; ?>
                        <?php if ($posCounts['no_work']): ?><span class="text-gray-400 font-bold"><?= $posCounts['no_work'] ?>NW</span><?php endif; ?>
                        <?php if ($posCounts['leave']): ?><span class="text-purple-400 font-bold"><?= $posCounts['leave'] ?>SL</span><?php endif; ?>
                        <?php if ($posCounts['sent_home']): ?><span class="text-teal-400 font-bold"><?= $posCounts['sent_home'] ?>SH</span><?php endif; ?>
                        <?php if ($posCounts['rest_day']): ?><span class="text-orange-400 font-bold"><?= $posCounts['rest_day'] ?>RD</span><?php endif; ?>
                        <?php if ($posCounts['none']): ?><span class="text-gray-600 font-bold"><?= $posCounts['none'] ?>—</span><?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Employees List -->
            <div class="folder-body" id="body_<?= $posKey ?>" style="display:none;">
                <?php
                // Sort employees by last name
                usort($emps, function($a, $b) {
                    return strcmp($a['last_name'], $b['last_name']);
                });
                
                foreach ($emps as $emp):
                    $status = $attMap[$emp['id']] ?? '';
                    $statusLabels = ['present'=>'P','absent'=>'A','no_work'=>'NW','leave'=>'SL','sent_home'=>'SH','rest_day'=>'RD'];
                    $statusBadge = ['present'=>'badge-present','absent'=>'badge-absent','no_work'=>'badge-no_work','leave'=>'badge-leave','sent_home'=>'badge-sent_home','rest_day'=>'badge-rest_day'];
                ?>
                <div class="flex items-center justify-between gap-3 px-6 py-3 border-b border-white/[0.04] last:border-0 hover:bg-white/[0.03] transition-all duration-200">
                    <div class="flex items-center gap-3 flex-1 min-w-0">
                        <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-primary-500/30 to-primary-700/30 flex items-center justify-center text-primary-300 font-bold text-xs flex-shrink-0 border border-primary-500/20">
                            <?= strtoupper(substr($emp['first_name'], 0, 1) . substr($emp['last_name'], 0, 1)) ?>
                        </div>
                        <div class="min-w-0">
                            <span class="text-sm font-semibold text-white"><?= htmlspecialchars($emp['last_name'] . ', ' . $emp['first_name']) ?></span>
                            <div class="text-[10px] text-gray-600"><?= htmlspecialchars($emp['position'] ?: 'No position') ?></div>
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        <a href="/ATTENDANCE/coordinator/employee_actions.php?search=<?= urlencode($emp['last_name']) ?>&tab=btw" class="text-xs text-green-400 hover:text-green-300 transition-colors mr-2">💼 Compliance</a>
                        <?php if ($status): ?>
                            <span class="badge <?= $statusBadge[$status] ?? '' ?> text-xs"><?= $statusLabels[$status] ?? '—' ?></span>
                        <?php else: ?>
                            <span class="text-xs text-gray-600 bg-white/5 px-2 py-0.5 rounded">No record</span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<script>
function toggleFolder(key) {
    const body = document.getElementById('body_' + key);
    const arrow = document.getElementById('arrow_' + key);
    if (body.style.display === 'none') {
        body.style.display = '';
        arrow.style.transform = 'rotate(90deg)';
    } else {
        body.style.display = 'none';
        arrow.style.transform = '';
    }
}

// Auto-expand first folder
document.addEventListener('DOMContentLoaded', () => {
    const firstFolder = document.querySelector('.position-folder');
    if (firstFolder) {
        const key = firstFolder.getAttribute('data-pos-key');
        toggleFolder(key);
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
