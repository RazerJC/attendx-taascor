<?php
/**
 * Coordinator Dashboard
 * Folder-style attendance overview by department with date picker
 */
require_once __DIR__ . '/../includes/auth.php';
requireCoordinator();
$db = getDB();
$user = currentUser();
$pageTitle = 'Coordinator Dashboard';

$selectedDate = $_GET['date'] ?? date('Y-m-d');

// Get departments and employees visible to this coordinator
$departments = getVisibleDepartments($db);
$allEmps = getVisibleEmployees($db);

$totalEmployees = count($allEmps);

$empsByDept = [];
$empsByPos = [];
foreach ($allEmps as $emp) {
    $empsByDept[$emp['department_id']][] = $emp;
    
    // Group by position
    $position = $emp['position'] ?: 'Unassigned';
    if (!isset($empsByPos[$position])) {
        $empsByPos[$position] = [];
    }
    $empsByPos[$position][] = $emp;
}

// Attendance for selected date
$attStmt = $db->prepare("SELECT employee_id, status FROM attendance WHERE date=?");
$attStmt->execute([$selectedDate]);
$attMap = [];
foreach ($attStmt->fetchAll() as $a) {
    $attMap[$a['employee_id']] = $a['status'];
}

// Status counts
$counts = ['present'=>0, 'absent'=>0, 'no_work'=>0, 'leave'=>0, 'sent_home'=>0, 'rest_day'=>0];
foreach ($allEmps as $emp) {
    $s = $attMap[$emp['id']] ?? '';
    if (isset($counts[$s])) $counts[$s]++;
}
$noRecord = $totalEmployees - array_sum($counts);

// Time-based greeting
$hour = (int)date('G');
if ($hour >= 5 && $hour < 12) {
    $greeting = 'Good Morning';
    $greetIcon = '☀️';
} elseif ($hour >= 12 && $hour < 18) {
    $greeting = 'Good Afternoon';
    $greetIcon = '🌤️';
} else {
    $greeting = 'Good Evening';
    $greetIcon = '🌙';
}
$firstName = explode(' ', $user['full_name'])[0];

require_once __DIR__ . '/../includes/header.php';

// Check if selected date is in the past
$today = date('Y-m-d');
$isPastDate = strtotime($selectedDate) < strtotime($today);
$isFutureDate = strtotime($selectedDate) > strtotime($today);
?>

<!-- Greeting Banner -->
<div class="mb-6 p-5 rounded-2xl bg-gradient-to-r from-primary-600/20 via-primary-500/10 to-transparent border border-primary-500/15">
    <div class="flex items-center gap-4">
        <div class="w-12 h-12 rounded-xl bg-primary-500/20 flex items-center justify-center text-2xl flex-shrink-0">
            <?= $greetIcon ?>
        </div>
        <div>
            <h2 class="text-xl font-bold text-white"><?= $greeting ?>, Sir <?= htmlspecialchars($firstName) ?>! 👋</h2>
            <p class="text-sm text-gray-400 mt-0.5">Have a great and productive day. Here's your attendance overview.</p>
            <p class="text-xs text-gray-600 mt-1"><?= date('l, F d, Y — h:i A') ?></p>
        </div>
    </div>
</div>

<!-- Highlighted Date Header -->
<div class="mb-6">
    <div class="p-5 rounded-2xl bg-gradient-to-br <?= $isPastDate ? 'from-amber-600/20 via-amber-500/10 to-transparent border border-amber-500/30' : ($isFutureDate ? 'from-blue-600/20 via-blue-500/10 to-transparent border border-blue-500/30' : 'from-green-600/20 via-green-500/10 to-transparent border border-green-500/30') ?>">
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
            <div class="flex-1">
                <h2 class="text-2xl font-bold text-white">📅 Attendance Overview</h2>
                <p class="text-gray-300 mt-2"><?= date('l, F d, Y', strtotime($selectedDate)) ?></p>
                <div class="flex items-center gap-2 mt-3 flex-wrap">
                    <?php if ($isPastDate): ?>
                        <span class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-amber-500/20 border border-amber-500/40 rounded-full text-xs font-semibold text-amber-300">
                            ⚠️ PAST DATE
                        </span>
                    <?php elseif ($isFutureDate): ?>
                        <span class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-blue-500/20 border border-blue-500/40 rounded-full text-xs font-semibold text-blue-300">
                            🔮 Future Date
                        </span>
                    <?php else: ?>
                        <span class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-green-500/20 border border-green-500/40 rounded-full text-xs font-semibold text-green-300">
                            ✅ TODAY
                        </span>
                    <?php endif; ?>
                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-white/5 border border-white/10 rounded-full text-xs font-semibold text-gray-300">
                        👥 <?= $totalEmployees ?> Employees
                    </span>
                </div>
            </div>
            <div class="w-full sm:w-auto">
                <form method="GET" class="flex items-center gap-2 flex-col sm:flex-row">
                    <input type="date" name="date" value="<?= $selectedDate ?>" class="w-full px-4 py-2.5 bg-dark-700/50 border border-white/10 rounded-xl text-white text-sm focus:outline-none focus:border-primary-500/50" onchange="this.form.submit()">
                    <a href="?date=<?= date('Y-m-d') ?>" class="text-center px-4 py-2.5 bg-green-600/20 border border-green-500/40 text-green-300 rounded-xl text-xs font-semibold hover:bg-green-600/30 transition-colors whitespace-nowrap">
                        📍 Today
                    </a>
                </form>
            </div>
        </div>
    </div>
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

<!-- Position Stats Circles -->
<div class="mb-6">
    <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">By Position</h3>
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3">
        <?php 
        // Sort positions and filter those with employees
        ksort($empsByPos);
        $activePosns = array_filter($empsByPos, function($emps) {
            return count($emps) > 0;
        });
        
        $maxPosCount = max(array_map('count', $activePosns));
        
        foreach ($activePosns as $position => $emps): 
            $posCount = count($emps);
            $percentage = ($posCount / $maxPosCount) * 100;
            $circumference = 2 * M_PI * 45;
            $strokeDashOffset = $circumference - ($percentage / 100) * $circumference;
            $posKey = md5($position);
        ?>
        <div class="glass-card p-6 text-center flex flex-col items-center justify-center">
            <div class="relative w-24 h-24 mb-4">
                <svg class="w-full h-full transform -rotate-90" viewBox="0 0 100 100">
                    <!-- Background circle -->
                    <circle cx="50" cy="50" r="45" fill="none" stroke="rgba(255,255,255,0.1)" stroke-width="3"/>
                    <!-- Progress circle -->
                    <circle cx="50" cy="50" r="45" fill="none" stroke="url(#grad_pos_<?= $posKey ?>)" stroke-width="3" 
                            stroke-dasharray="<?= $circumference ?>" 
                            stroke-dashoffset="<?= $strokeDashOffset ?>"
                            stroke-linecap="round"
                            class="transition-all duration-1000 ease-out"
                            style="animation: fillCircle_pos_<?= $posKey ?> 1s ease-out forwards;"/>
                    <defs>
                        <linearGradient id="grad_pos_<?= $posKey ?>" x1="0%" y1="0%" x2="100%" y2="100%">
                            <stop offset="0%" style="stop-color:#20c997;stop-opacity:1" />
                            <stop offset="100%" style="stop-color:#12b886;stop-opacity:1" />
                        </linearGradient>
                    </defs>
                </svg>
                <div class="absolute inset-0 flex items-center justify-center">
                    <div class="text-center">
                        <div class="text-2xl font-bold text-primary-400"><?= $posCount ?></div>
                        <div class="text-[10px] text-gray-500">staff</div>
                    </div>
                </div>
            </div>
            <div class="text-xs font-semibold text-white truncate w-full"><?= htmlspecialchars($position) ?></div>
            <style>
                @keyframes fillCircle_pos_<?= $posKey ?> {
                    from { stroke-dashoffset: <?= $circumference ?>; }
                    to { stroke-dashoffset: <?= $strokeDashOffset ?>; }
                }
            </style>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Quick Links -->
<div class="flex flex-wrap gap-3 mb-5">
    <a href="/ATTENDANCE/coordinator/attendance.php?date=<?= $selectedDate ?>" class="inline-flex items-center gap-3 px-8 py-4 bg-gradient-to-r from-primary-600 to-primary-500 hover:from-primary-500 hover:to-primary-400 text-white rounded-2xl text-base font-bold uppercase tracking-wider transition-all shadow-lg shadow-primary-500/25 hover:shadow-primary-500/40 hover:scale-[1.02] active:scale-[0.98]">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
        Record Attendance
    </a>
</div>

<!-- Department Folders -->
<div class="space-y-3">
    <?php foreach ($departments as $dept):
        $deptEmps = $empsByDept[$dept['id']] ?? [];
        if (empty($deptEmps)) continue;
        $deptCount = count($deptEmps);
        $deptCounts = ['present'=>0,'absent'=>0,'no_work'=>0,'leave'=>0,'sent_home'=>0,'rest_day'=>0,'none'=>0];
        foreach ($deptEmps as $emp) {
            $s = $attMap[$emp['id']] ?? 'none';
            $deptCounts[$s]++;
        }
    ?>
    <div class="glass-card dept-folder" id="dept_<?= $dept['id'] ?>">
        <div class="glass-card-header cursor-pointer select-none hover:bg-white/[0.02] transition-colors" onclick="toggleFolder(<?= $dept['id'] ?>)">
            <div class="flex items-center gap-2">
                <svg class="w-4 h-4 text-gray-500 transition-transform duration-200" id="arrow_<?= $dept['id'] ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                <span class="text-sm font-semibold text-white" id="deptName_<?= $dept['id'] ?>">📁 <?= htmlspecialchars($dept['name']) ?></span>
                <span class="text-xs text-gray-500 bg-white/5 px-2 py-0.5 rounded-full"><?= $deptCount ?></span>
                <button type="button" onclick="event.stopPropagation(); editDept(<?= $dept['id'] ?>, '<?= htmlspecialchars($dept['name'], ENT_QUOTES) ?>')" class="text-gray-600 hover:text-primary-400 transition-colors ml-1" title="Edit department name">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                </button>
            </div>
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

document.addEventListener('DOMContentLoaded', () => {
    const firstFolder = document.querySelector('.dept-folder');
    if (firstFolder) {
        const id = firstFolder.id.replace('dept_', '');
        toggleFolder(id);
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
