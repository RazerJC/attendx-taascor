<?php
/**
 * Admin Dashboard
 * Folder-style attendance overview by department
 */
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();
$db = getDB();
$pageTitle = 'Admin Dashboard';

$today = date('Y-m-d');
$selectedDate = $_GET['date'] ?? $today;
$searchQuery = $_GET['search'] ?? '';

// Get all employees
$totalEmployees = $db->query("SELECT COUNT(*) FROM employees WHERE status='active'")->fetchColumn();

// Get today's attendance counts
$statusCounts = $db->prepare("SELECT status, COUNT(*) as cnt FROM attendance WHERE date=? GROUP BY status");
$statusCounts->execute([$selectedDate]);
$counts = ['present'=>0, 'absent'=>0, 'no_work'=>0, 'leave'=>0, 'sent_home'=>0, 'rest_day'=>0];
foreach ($statusCounts->fetchAll() as $row) {
    $counts[$row['status']] = $row['cnt'];
}
$noRecord = $totalEmployees - array_sum($counts);

// Get department statistics
$deptStats = $db->query("
    SELECT d.id, d.name, COUNT(e.id) as emp_count
    FROM departments d
    LEFT JOIN employees e ON e.department_id = d.id AND e.status='active'
    GROUP BY d.id, d.name
    ORDER BY d.name
")->fetchAll();

// Get departments with employees and today's attendance
$departments = $db->query("SELECT * FROM departments ORDER BY name")->fetchAll();

$allEmps = $db->query("
    SELECT e.id, e.first_name, e.last_name, e.position, e.department_id
    FROM employees e WHERE e.status='active'
    ORDER BY e.last_name, e.first_name
")->fetchAll();

$empsByDept = [];
foreach ($allEmps as $emp) {
    $empsByDept[$emp['department_id']][] = $emp;
}

// Today's attendance map
$attToday = $db->prepare("SELECT employee_id, status FROM attendance WHERE date=?");
$attToday->execute([$selectedDate]);
$attMap = [];
foreach ($attToday->fetchAll() as $a) {
    $attMap[$a['employee_id']] = $a['status'];
}

// Recent activity
$recentActivity = $db->query("
    SELECT al.*, u.full_name as user_name
    FROM activity_log al
    LEFT JOIN users u ON al.user_id = u.id
    ORDER BY al.created_at DESC LIMIT 8
")->fetchAll();

require_once __DIR__ . '/../includes/header.php';

// Get count of pending approval requests
$pendingCount = $db->query("SELECT COUNT(*) FROM attendance_edit_requests WHERE status='pending'")->fetchColumn();
?>

<!-- Date Header -->
<div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 mb-5">
    <div>
        <h2 class="text-lg font-bold text-white">Attendance Overview</h2>
        <p class="text-xs text-gray-500"><?= date('l, F d, Y', strtotime($selectedDate)) ?></p>
    </div>
    <div class="flex items-center gap-3">
        <form method="GET" class="flex items-center gap-2">
            <input type="date" name="date" value="<?= $selectedDate ?>" class="px-4 py-2.5 bg-dark-700/50 border border-white/10 rounded-xl text-white text-sm focus:outline-none focus:border-primary-500/50" onchange="this.form.submit()">
        </form>
        <span class="text-xs text-gray-500 bg-white/5 px-3 py-1.5 rounded-full">👥 <?= $totalEmployees ?></span>
    </div>
</div>

<!-- Quick Action: Attendance Edit Approvals -->
<?php if ($pendingCount > 0): ?>
<div class="mb-5">
    <a href="/ATTENDANCE/admin/attendance_approvals.php?filter=pending" class="flex items-center gap-3 px-5 py-4 bg-gradient-to-r from-amber-600/20 to-amber-500/10 hover:from-amber-600/30 hover:to-amber-500/20 border border-amber-500/40 rounded-xl transition-all group">
        <div class="flex items-center justify-center w-10 h-10 rounded-lg bg-amber-500/20 group-hover:bg-amber-500/30 transition-colors">
            <span class="text-xl">⚠️</span>
        </div>
        <div class="flex-1">
            <p class="text-sm font-semibold text-white">Attendance Edit Approvals</p>
            <p class="text-xs text-amber-200 mt-0.5"><?= $pendingCount ?> pending request<?= $pendingCount !== 1 ? 's' : '' ?> require your review</p>
        </div>
        <div class="flex items-center justify-center w-8 h-8 rounded-lg bg-amber-500/20 text-amber-300 font-bold text-sm">
            <?= $pendingCount ?>
        </div>
    </a>
</div>
<?php endif; ?>
</div>

<!-- Stat Cards -->
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

<!-- Department Stats Circles -->
<div class="mb-6">
    <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">By Department</h3>
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3">
        <?php 
        // Filter departments with employees
        $activeDepts = array_filter($deptStats, function($stat) {
            return $stat['emp_count'] > 0;
        });
        
        $maxCount = max(array_column($activeDepts, 'emp_count'));
        
        foreach ($activeDepts as $stat): 
            $percentage = ($stat['emp_count'] / $maxCount) * 100;
            $circumference = 2 * M_PI * 45; // circle radius 45
            $strokeDashOffset = $circumference - ($percentage / 100) * $circumference;
        ?>
        <div class="glass-card p-6 text-center flex flex-col items-center justify-center">
            <div class="relative w-24 h-24 mb-4">
                <svg class="w-full h-full transform -rotate-90" viewBox="0 0 100 100">
                    <!-- Background circle -->
                    <circle cx="50" cy="50" r="45" fill="none" stroke="rgba(255,255,255,0.1)" stroke-width="3"/>
                    <!-- Progress circle -->
                    <circle cx="50" cy="50" r="45" fill="none" stroke="url(#grad_<?= $stat['id'] ?>)" stroke-width="3" 
                            stroke-dasharray="<?= $circumference ?>" 
                            stroke-dashoffset="<?= $strokeDashOffset ?>"
                            stroke-linecap="round"
                            class="transition-all duration-1000 ease-out"
                            style="animation: fillCircle_<?= $stat['id'] ?> 1s ease-out forwards;"/>
                    <defs>
                        <linearGradient id="grad_<?= $stat['id'] ?>" x1="0%" y1="0%" x2="100%" y2="100%">
                            <stop offset="0%" style="stop-color:#20c997;stop-opacity:1" />
                            <stop offset="100%" style="stop-color:#12b886;stop-opacity:1" />
                        </linearGradient>
                    </defs>
                </svg>
                <div class="absolute inset-0 flex items-center justify-center">
                    <div class="text-center">
                        <div class="text-2xl font-bold text-primary-400"><?= $stat['emp_count'] ?></div>
                        <div class="text-[10px] text-gray-500">staff</div>
                    </div>
                </div>
            </div>
            <div class="text-xs font-semibold text-white truncate w-full"><?= htmlspecialchars($stat['name']) ?></div>
            <style>
                @keyframes fillCircle_<?= $stat['id'] ?> {
                    from { stroke-dashoffset: <?= $circumference ?>; }
                    to { stroke-dashoffset: <?= $strokeDashOffset ?>; }
                }
            </style>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Search All Employees -->
<div class="glass-card mb-6">
    <div class="glass-card-header">
        <h2 class="text-sm font-semibold text-white">🔍 All Employees</h2>
    </div>
    <div class="p-4 border-b border-white/10">
        <form method="GET" class="flex items-center gap-2">
            <input type="hidden" name="date" value="<?= $selectedDate ?>">
            <input type="text" name="search" value="<?= htmlspecialchars($searchQuery) ?>" placeholder="Search by name or position..." 
                   class="flex-1 px-4 py-2.5 bg-dark-700/50 border border-white/10 rounded-xl text-white placeholder-gray-600 text-sm focus:outline-none focus:border-primary-500/50">
            <button type="submit" class="px-4 py-2.5 bg-primary-600 hover:bg-primary-500 text-white rounded-xl text-xs font-semibold transition-colors">
                Search
            </button>
            <?php if ($searchQuery): ?>
            <a href="?date=<?= $selectedDate ?>" class="px-4 py-2.5 bg-white/10 hover:bg-white/20 text-gray-300 rounded-xl text-xs font-semibold transition-colors">
                Clear
            </a>
            <?php endif; ?>
        </form>
    </div>
    <div class="table-wrap">
        <table class="glass-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Department</th>
                    <th>Position</th>
                    <th>Status (<?= $selectedDate ?>)</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
            <?php
            // Get all or filtered employees
            $empQuery = "
                SELECT e.id, e.first_name, e.last_name, e.position, d.name as dept_name, e.department_id
                FROM employees e
                LEFT JOIN departments d ON e.department_id = d.id
                WHERE e.status='active'
            ";
            
            if ($searchQuery) {
                $empQuery .= " AND (e.first_name LIKE ? OR e.last_name LIKE ? OR e.position LIKE ?)";
                $stmt = $db->prepare($empQuery . " ORDER BY e.last_name, e.first_name LIMIT 50");
                $search = "%$searchQuery%";
                $stmt->execute([$search, $search, $search]);
                $employees = $stmt->fetchAll();
            } else {
                $employees = $db->query($empQuery . " ORDER BY e.last_name, e.first_name LIMIT 50")->fetchAll();
            }
            
            if (empty($employees)): ?>
                <tr><td colspan="5" class="text-center text-gray-600 py-8">No employees found</td></tr>
            <?php else: ?>
                <?php foreach ($employees as $emp):
                    $status = $attMap[$emp['id']] ?? '';
                    $statusLabels = ['present'=>'P','absent'=>'A','no_work'=>'NW','leave'=>'SL','sent_home'=>'SH','rest_day'=>'RD'];
                    $statusBadge = ['present'=>'badge-present','absent'=>'badge-absent','no_work'=>'badge-no_work','leave'=>'badge-leave','sent_home'=>'badge-sent_home','rest_day'=>'badge-rest_day'];
                ?>
                <tr>
                    <td class="font-medium text-white"><?= htmlspecialchars($emp['last_name'] . ', ' . $emp['first_name']) ?></td>
                    <td><span class="text-xs text-gray-400"><?= htmlspecialchars($emp['dept_name'] ?? 'Unassigned') ?></span></td>
                    <td class="text-gray-400 text-sm"><?= htmlspecialchars($emp['position']) ?></td>
                    <td>
                        <?php if ($status): ?>
                            <span class="badge <?= $statusBadge[$status] ?? '' ?> text-xs"><?= $statusLabels[$status] ?? '—' ?></span>
                        <?php else: ?>
                            <span class="text-xs text-gray-600">—</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a href="/ATTENDANCE/admin/employee_attendance.php?emp=<?= $emp['id'] ?>" class="text-xs text-primary-400 hover:text-primary-300">View</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<div class="space-y-3 mb-6">
    <?php foreach ($departments as $dept):
        $deptEmps = $empsByDept[$dept['id']] ?? [];
        if (empty($deptEmps)) continue;
        $deptCount = count($deptEmps);
        // Count statuses for this department
        $deptCounts = ['present'=>0,'absent'=>0,'no_work'=>0,'leave'=>0,'sent_home'=>0,'rest_day'=>0,'none'=>0];
        foreach ($deptEmps as $emp) {
            $s = $attMap[$emp['id']] ?? 'none';
            $deptCounts[$s]++;
        }
    ?>
    <div class="glass-card dept-folder" id="dept_<?= $dept['id'] ?>">
        <!-- Folder Header -->
        <div class="glass-card-header cursor-pointer select-none hover:bg-white/[0.02] transition-colors" onclick="toggleFolder(<?= $dept['id'] ?>)">
            <div class="flex items-center gap-2">
                <svg class="w-4 h-4 text-gray-500 transition-transform duration-200" id="arrow_<?= $dept['id'] ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                <span class="text-sm font-semibold text-white" id="deptName_<?= $dept['id'] ?>">📁 <?= htmlspecialchars($dept['name']) ?></span>
                <span class="text-xs text-gray-500 bg-white/5 px-2 py-0.5 rounded-full"><?= $deptCount ?></span>
                <button type="button" onclick="event.stopPropagation(); editDept(<?= $dept['id'] ?>, '<?= htmlspecialchars($dept['name'], ENT_QUOTES) ?>')" class="text-gray-600 hover:text-primary-400 transition-colors ml-1" title="Edit department name">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                </button>
            </div>
            <!-- Mini status summary -->
            <div class="flex gap-1.5 text-[10px] font-semibold" onclick="event.stopPropagation()">
                <?php if ($deptCounts['present']): ?><span class="text-green-400"><?= $deptCounts['present'] ?>P</span><?php endif; ?>
                <?php if ($deptCounts['absent']): ?><span class="text-red-400"><?= $deptCounts['absent'] ?>A</span><?php endif; ?>
                <?php if ($deptCounts['no_work']): ?><span class="text-gray-400"><?= $deptCounts['no_work'] ?>NW</span><?php endif; ?>
                <?php if ($deptCounts['leave']): ?><span class="text-purple-400"><?= $deptCounts['leave'] ?>SL</span><?php endif; ?>
                <?php if ($deptCounts['sent_home']): ?><span class="text-teal-400"><?= $deptCounts['sent_home'] ?>SH</span><?php endif; ?>
                <?php if ($deptCounts['rest_day']): ?><span class="text-orange-400"><?= $deptCounts['rest_day'] ?>RD</span><?php endif; ?>
                <?php if ($deptCounts['none']): ?><span class="text-gray-600"><?= $deptCounts['none'] ?>—</span><?php endif; ?>
            </div>
        </div>

        <!-- Employee List -->
        <div class="dept-body" id="body_<?= $dept['id'] ?>" style="display:none;">
            <?php foreach ($deptEmps as $emp):
                $status = $attMap[$emp['id']] ?? '';
                $statusLabels = ['present'=>'P','absent'=>'A','no_work'=>'NW','leave'=>'SL','sent_home'=>'SH','rest_day'=>'RD'];
                $statusBadge = ['present'=>'badge-present','absent'=>'badge-absent','no_work'=>'badge-no_work','leave'=>'badge-leave','sent_home'=>'badge-sent_home','rest_day'=>'badge-rest_day'];
            ?>
            <div class="flex items-center gap-2 px-4 py-2 border-b border-white/[0.04] last:border-0 hover:bg-white/[0.02] transition-colors">
                <div class="flex-1 min-w-0">
                    <span class="text-sm font-medium text-white"><?= htmlspecialchars($emp['last_name'] . ', ' . $emp['first_name']) ?></span>
                    <span class="text-xs text-gray-600 ml-1 hidden sm:inline">— <?= htmlspecialchars($emp['position']) ?></span>
                </div>
                <?php if ($status): ?>
                    <span class="badge <?= $statusBadge[$status] ?? '' ?> text-xs"><?= $statusLabels[$status] ?? '—' ?></span>
                <?php else: ?>
                    <span class="text-xs text-gray-600">—</span>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Recent Activity -->
<div class="glass-card">
    <div class="glass-card-header">
        <h2 class="text-sm font-semibold text-white">Recent Activity</h2>
        <a href="/ATTENDANCE/admin/activity_log.php" class="text-xs text-primary-400 hover:text-primary-300">View All →</a>
    </div>
    <div class="table-wrap">
        <table class="glass-table">
            <thead><tr><th>User</th><th>Action</th><th>Time</th></tr></thead>
            <tbody>
            <?php if (empty($recentActivity)): ?>
                <tr><td colspan="3" class="text-center text-gray-600 py-8">No activity yet</td></tr>
            <?php else: ?>
                <?php foreach ($recentActivity as $a): ?>
                <tr>
                    <td class="font-medium text-white"><?= htmlspecialchars($a['user_name'] ?? 'System') ?></td>
                    <td class="text-gray-400"><?= htmlspecialchars($a['action']) ?></td>
                    <td class="text-gray-500 text-xs whitespace-nowrap"><?= date('M d, g:i A', strtotime($a['created_at'])) ?></td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Edit Department Modal -->
<div id="editDeptModal" class="modal-overlay" onclick="if(event.target===this)this.classList.remove('show')">
    <div class="modal-content" style="max-width:24rem;">
        <div class="px-5 py-4 border-b border-white/10 flex items-center justify-between">
            <h3 class="text-sm font-semibold text-white">✏️ Edit Department</h3>
            <button onclick="document.getElementById('editDeptModal').classList.remove('show')" class="text-gray-500 hover:text-white text-xl">&times;</button>
        </div>
        <div class="p-5 space-y-4">
            <input type="hidden" id="editDeptId">
            <div>
                <label class="block text-xs font-medium text-gray-400 mb-1.5 uppercase tracking-wider">Department Name</label>
                <input type="text" id="editDeptName" class="w-full px-4 py-2.5 bg-dark-700/50 border border-white/10 rounded-xl text-white text-sm focus:outline-none focus:border-primary-500/50">
            </div>
            <button onclick="saveDept()" class="w-full py-2.5 bg-gradient-to-r from-primary-500 to-primary-600 hover:from-primary-400 hover:to-primary-500 text-white font-semibold rounded-xl text-sm transition-all">Save</button>
        </div>
    </div>
</div>

<script>
function toggleFolder(deptId) {
    const body = document.getElementById('body_' + deptId);
    const arrow = document.getElementById('arrow_' + deptId);
    if (body.style.display === 'none') {
        body.style.display = '';
        arrow.style.transform = 'rotate(90deg)';
    } else {
        body.style.display = 'none';
        arrow.style.transform = '';
    }
}

function editDept(id, name) {
    document.getElementById('editDeptId').value = id;
    document.getElementById('editDeptName').value = name;
    document.getElementById('editDeptModal').classList.add('show');
    setTimeout(() => document.getElementById('editDeptName').focus(), 100);
}

function saveDept() {
    const id = document.getElementById('editDeptId').value;
    const name = document.getElementById('editDeptName').value.trim();
    if (!name) return;
    const fd = new FormData();
    fd.append('id', id);
    fd.append('name', name);
    fetch('/ATTENDANCE/api.php?action=update_department', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                document.getElementById('deptName_' + id).textContent = '📁 ' + data.name;
                document.getElementById('editDeptModal').classList.remove('show');
            } else {
                alert(data.error || 'Error saving');
            }
        });
}

// Auto-expand first folder
document.addEventListener('DOMContentLoaded', () => {
    const firstFolder = document.querySelector('.dept-folder');
    if (firstFolder) {
        const id = firstFolder.id.replace('dept_', '');
        toggleFolder(id);
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
