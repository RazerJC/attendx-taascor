<?php
/**
 * Admin Dashboard
 * Folder-style attendance overview by department with positions, search, filters, files list, and excel export.
 */
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();
$db = getDB();
$pageTitle = 'Admin Dashboard';

$today = date('Y-m-d');
$selectedDate = $_GET['date'] ?? $today;


// Handle deleting uploaded master files
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_upload') {
    $fileToDelete = $_POST['filename'] ?? '';
    $safeName = basename($fileToDelete);
    if (strpos($safeName, 'Master_File_') === 0 && (str_ends_with($safeName, '.xlsx') || str_ends_with($safeName, '.xls'))) {
        $filePath = __DIR__ . '/../uploads/' . $safeName;
        if (file_exists($filePath)) {
            unlink($filePath);
            logActivity('Master File Deleted', "Deleted file: $safeName");
            setFlash('success', "File '$safeName' deleted successfully.");
        } else {
            setFlash('error', "File not found.");
        }
    } else {
        setFlash('error', "Invalid filename.");
    }
    header('Location: /ATTENDANCE/admin/dashboard.php?date=' . $selectedDate);
    exit;
}

// Get all active employees count
$totalEmployees = $db->query("SELECT COUNT(*) FROM employees WHERE status='active'")->fetchColumn();

// Get today's attendance counts
$statusCounts = $db->prepare("SELECT status, COUNT(*) as cnt FROM attendance WHERE date=? GROUP BY status");
$statusCounts->execute([$selectedDate]);
$counts = ['present'=>0, 'absent'=>0, 'no_work'=>0, 'leave'=>0, 'sent_home'=>0, 'rest_day'=>0];
foreach ($statusCounts->fetchAll() as $row) {
    $counts[$row['status']] = $row['cnt'];
}
$noRecord = max(0, $totalEmployees - array_sum($counts));

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

// Get all active employees
$allEmps = $db->query("
    SELECT e.id, e.first_name, e.last_name, e.position, e.department_id
    FROM employees e WHERE e.status='active'
    ORDER BY e.last_name, e.first_name
")->fetchAll();

// Group by department first, then by position
$empsByDeptAndPos = [];
foreach ($allEmps as $emp) {
    $deptId = $emp['department_id'];
    $position = $emp['position'] ?: 'Unassigned';
    if (!isset($empsByDeptAndPos[$deptId])) {
        $empsByDeptAndPos[$deptId] = [];
    }
    if (!isset($empsByDeptAndPos[$deptId][$position])) {
        $empsByDeptAndPos[$deptId][$position] = [];
    }
    $empsByDeptAndPos[$deptId][$position][] = $emp;
}

// Sort positions alphabetically and employees by last name within each position
foreach ($empsByDeptAndPos as $deptId => &$positions) {
    ksort($positions);
    foreach ($positions as &$emps) {
        usort($emps, function($a, $b) {
            return strcmp($a['last_name'], $b['last_name']);
        });
    }
}

// Today's attendance map with details (time and coordinator)
$attToday = $db->prepare("
    SELECT a.employee_id, a.status, a.time_in, a.time_out, u.full_name as coordinator
    FROM attendance a
    LEFT JOIN users u ON a.recorded_by = u.id
    WHERE a.date=?
");
$attToday->execute([$selectedDate]);
$attMap = [];
foreach ($attToday->fetchAll() as $a) {
    $attMap[$a['employee_id']] = [
        'status' => $a['status'],
        'time_in' => $a['time_in'],
        'time_out' => $a['time_out'],
        'coordinator' => $a['coordinator']
    ];
}

// Recent activity
$recentActivity = $db->query("
    SELECT al.*, u.full_name as user_name
    FROM activity_log al
    LEFT JOIN users u ON al.user_id = u.id
    ORDER BY al.created_at DESC LIMIT 8
")->fetchAll();

// Get count of pending approval requests
$pendingCount = $db->query("SELECT COUNT(*) FROM attendance_edit_requests WHERE status='pending'")->fetchColumn();

// Scan coordinator uploaded files
$uploadedFiles = glob(__DIR__ . '/../uploads/Master_File_*');
if ($uploadedFiles === false) {
    $uploadedFiles = [];
}
rsort($uploadedFiles);

require_once __DIR__ . '/../includes/header.php';
?>

<!-- Date Header -->
<div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 mb-5">
    <div>
        <h2 class="text-lg font-bold text-white">Attendance Overview</h2>
        <p class="text-xs text-gray-500"><?= date('l, F d, Y', strtotime($selectedDate)) ?></p>
    </div>
    <div class="flex items-center gap-3 w-full sm:w-auto">
        <form method="GET" class="flex items-center gap-2 w-full sm:w-auto">
            <input type="date" name="date" value="<?= $selectedDate ?>" class="px-4 py-2.5 bg-dark-700/50 border border-white/10 rounded-xl text-white text-sm focus:outline-none focus:border-primary-500/50" onchange="this.form.submit()">
        </form>
        <span class="text-xs text-gray-500 bg-white/5 px-3 py-1.5 rounded-full whitespace-nowrap">👥 <?= $totalEmployees ?> Employees</span>
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
        
        $maxCount = !empty($activeDepts) ? max(array_column($activeDepts, 'emp_count')) : 1;
        
        foreach ($activeDepts as $stat): 
            $percentage = ($stat['emp_count'] / $maxCount) * 100;
            $circumference = 2 * M_PI * 45; // circle radius 45
            $strokeDashOffset = $circumference - ($percentage / 100) * $circumference;
        ?>
        <div class="glass-card p-6 text-center flex flex-col items-center justify-center">
            <div class="relative w-24 h-24 mb-4">
                <svg class="w-full h-full transform -rotate-90" viewBox="0 0 100 100">
                    <circle cx="50" cy="50" r="45" fill="none" stroke="rgba(255,255,255,0.1)" stroke-width="3"/>
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

<!-- Admin Tool grid: Export & Uploaded Files -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
    <!-- Export Form Card -->
    <div class="lg:col-span-1 glass-card p-6 flex flex-col justify-between">
        <div>
            <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">📥 Export Department Attendance</h3>
            <p class="text-xs text-gray-500 mb-4 leading-relaxed">Download a complete attendance sheet directly to Excel (.xls).</p>
        </div>
        <form method="GET" action="/ATTENDANCE/export_excel.php" target="_blank" class="space-y-3">
            <div>
                <label class="block text-[10px] text-gray-400 font-semibold uppercase mb-1">Department</label>
                <select name="department_id" class="w-full px-3 py-2 bg-dark-700/50 border border-white/10 rounded-xl text-white text-xs focus:outline-none focus:border-primary-500/50">
                    <option value="all">All Departments</option>
                    <?php foreach ($departments as $dept): ?>
                        <option value="<?= $dept['id'] ?>"><?= htmlspecialchars($dept['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="grid grid-cols-2 gap-2">
                <div>
                    <label class="block text-[10px] text-gray-400 font-semibold uppercase mb-1">Start Date</label>
                    <input type="date" name="start_date" value="<?= $selectedDate ?>" required class="w-full px-3 py-2 bg-dark-700/50 border border-white/10 rounded-xl text-white text-xs focus:outline-none focus:border-primary-500/50">
                </div>
                <div>
                    <label class="block text-[10px] text-gray-400 font-semibold uppercase mb-1">End Date</label>
                    <input type="date" name="end_date" value="<?= $selectedDate ?>" required class="w-full px-3 py-2 bg-dark-700/50 border border-white/10 rounded-xl text-white text-xs focus:outline-none focus:border-primary-500/50">
                </div>
            </div>
            <button type="submit" class="w-full px-4 py-2.5 bg-gradient-to-r from-primary-600 to-primary-500 hover:from-primary-500 hover:to-primary-400 text-white rounded-xl text-xs font-bold uppercase tracking-wider transition-all flex items-center justify-center gap-2 shadow-lg shadow-primary-500/10">
                📥 Export to Excel
            </button>
        </form>
    </div>

    <!-- Coordinator Uploaded Files Manager -->
    <div class="lg:col-span-2 glass-card p-6 flex flex-col justify-between">
        <div>
            <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">📂 Coordinator Master Files</h3>
            <p class="text-xs text-gray-500 mb-4 leading-relaxed">Download or manage Excel Master Files uploaded by Coordinators.</p>
        </div>
        <div class="overflow-x-auto max-h-[190px] border border-white/5 rounded-xl bg-dark-900/40">
            <table class="w-full text-left text-xs border-collapse">
                <thead>
                    <tr class="bg-white/5 text-gray-400 border-b border-white/5">
                        <th class="px-4 py-2 font-semibold">Filename</th>
                        <th class="px-4 py-2 font-semibold">Size</th>
                        <th class="px-4 py-2 font-semibold">Uploaded Time</th>
                        <th class="px-4 py-2 font-semibold text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/[0.04]">
                    <?php if (empty($uploadedFiles)): ?>
                        <tr>
                            <td colspan="4" class="px-4 py-8 text-center text-gray-600">No master files uploaded yet.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($uploadedFiles as $file): 
                            $name = basename($file);
                            $size = filesize($file);
                            $sizeStr = $size >= 1048576 ? number_format($size / 1048576, 2) . ' MB' : number_format($size / 1024, 1) . ' KB';
                            $time = filemtime($file);
                        ?>
                            <tr class="hover:bg-white/[0.02] transition-colors text-white">
                                <td class="px-4 py-2 truncate max-w-[200px]" title="<?= htmlspecialchars($name) ?>">
                                    📄 <?= htmlspecialchars($name) ?>
                                </td>
                                <td class="px-4 py-2 text-gray-400"><?= $sizeStr ?></td>
                                <td class="px-4 py-2 text-gray-500"><?= date('Y-m-d H:i:s', $time) ?></td>
                                <td class="px-4 py-2 text-right whitespace-nowrap">
                                    <a href="/ATTENDANCE/uploads/<?= urlencode($name) ?>" download class="text-primary-400 hover:text-primary-300 font-semibold">Download</a>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this master file?');">
                                        <input type="hidden" name="action" value="delete_upload">
                                        <input type="hidden" name="filename" value="<?= htmlspecialchars($name) ?>">
                                        <button type="submit" class="text-red-400 hover:text-red-300 font-semibold ml-3">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>



<!-- Interactive Monitoring Filter Panel -->
<div class="glass-card mb-6 border border-primary-500/10">
    <div class="glass-card-header flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
            <h2 class="text-sm font-semibold text-white">📊 Live Status Monitoring</h2>
            <p class="text-xs text-gray-500 mt-0.5">Click a status button below to instantly filter the department directory.</p>
        </div>
    </div>
    <div class="p-4 flex flex-wrap gap-2.5">
        <button type="button" data-filter="all" class="filter-btn active px-4 py-2.5 rounded-xl text-xs font-bold uppercase tracking-wider transition-all border border-white/10 text-white bg-white/5 hover:bg-white/10">
            All (<?= $totalEmployees ?>)
        </button>
        <button type="button" data-filter="present" class="filter-btn px-4 py-2.5 rounded-xl text-xs font-bold uppercase tracking-wider transition-all border border-green-500/20 text-green-400 bg-green-500/10 hover:bg-green-500/20">
            P (<?= $counts['present'] ?>)
        </button>
        <button type="button" data-filter="absent" class="filter-btn px-4 py-2.5 rounded-xl text-xs font-bold uppercase tracking-wider transition-all border border-red-500/20 text-red-400 bg-red-500/10 hover:bg-red-500/20">
            A (<?= $counts['absent'] ?>)
        </button>
        <button type="button" data-filter="no_work" class="filter-btn px-4 py-2.5 rounded-xl text-xs font-bold uppercase tracking-wider transition-all border border-gray-500/20 text-gray-400 bg-gray-500/10 hover:bg-gray-500/20">
            NW (<?= $counts['no_work'] ?>)
        </button>
        <button type="button" data-filter="leave" class="filter-btn px-4 py-2.5 rounded-xl text-xs font-bold uppercase tracking-wider transition-all border border-purple-500/20 text-purple-400 bg-purple-500/10 hover:bg-purple-500/20">
            SL (<?= $counts['leave'] ?>)
        </button>
        <button type="button" data-filter="sent_home" class="filter-btn px-4 py-2.5 rounded-xl text-xs font-bold uppercase tracking-wider transition-all border border-teal-500/20 text-teal-400 bg-teal-500/10 hover:bg-teal-500/20">
            SH (<?= $counts['sent_home'] ?>)
        </button>
        <button type="button" data-filter="rest_day" class="filter-btn px-4 py-2.5 rounded-xl text-xs font-bold uppercase tracking-wider transition-all border border-orange-500/20 text-orange-400 bg-orange-500/10 hover:bg-orange-500/20">
            RD (<?= $counts['rest_day'] ?>)
        </button>
        <button type="button" data-filter="none" class="filter-btn px-4 py-2.5 rounded-xl text-xs font-bold uppercase tracking-wider transition-all border border-white/5 text-gray-500 bg-white/5 hover:bg-white/10">
            No Record (<?= $noRecord ?>)
        </button>
    </div>
</div>

<!-- Department Folders (Restructured) -->
<div class="space-y-3 mb-6">
    <?php foreach ($departments as $dept):
        $deptPosEmps = $empsByDeptAndPos[$dept['id']] ?? [];
        if (empty($deptPosEmps)) continue;
        
        // Count total employees in this department
        $deptCount = 0;
        $deptCounts = ['present'=>0,'absent'=>0,'no_work'=>0,'leave'=>0,'sent_home'=>0,'rest_day'=>0,'none'=>0];
        foreach ($deptPosEmps as $position => $emps) {
            $deptCount += count($emps);
            foreach ($emps as $emp) {
                $s = $attMap[$emp['id']]['status'] ?? 'none';
                $deptCounts[$s]++;
            }
        }
    ?>
    <div class="glass-card dept-folder" id="dept_<?= $dept['id'] ?>" data-dept-id="<?= $dept['id'] ?>">
        <!-- Folder Header -->
        <div class="glass-card-header cursor-pointer select-none hover:bg-white/[0.02] transition-colors" onclick="toggleFolder(<?= $dept['id'] ?>)">
            <div class="flex items-center gap-2">
                <svg class="w-4 h-4 text-gray-500 transition-transform duration-200" id="arrow_<?= $dept['id'] ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                <span class="text-sm font-semibold text-white" id="deptName_<?= $dept['id'] ?>">📁 <?= htmlspecialchars($dept['name']) ?></span>
                <span class="text-xs text-gray-500 bg-white/5 px-2 py-0.5 rounded-full font-semibold"><?= $deptCount ?></span>
                <button type="button" onclick="event.stopPropagation(); editDept(<?= $dept['id'] ?>, '<?= htmlspecialchars($dept['name'], ENT_QUOTES) ?>')" class="text-gray-600 hover:text-primary-400 transition-colors ml-1" title="Edit department name">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                </button>
            </div>
            <!-- Glowing status summary next to department name -->
            <div class="flex gap-1.5 text-[10px] font-semibold" onclick="event.stopPropagation()">
                <?php if ($deptCounts['present']): ?><span class="text-green-400 font-bold"><?= $deptCounts['present'] ?>P</span><?php endif; ?>
                <?php if ($deptCounts['absent']): ?><span class="text-red-400 font-bold"><?= $deptCounts['absent'] ?>A</span><?php endif; ?>
                <?php if ($deptCounts['no_work']): ?><span class="text-gray-400 font-bold"><?= $deptCounts['no_work'] ?>NW</span><?php endif; ?>
                <?php if ($deptCounts['leave']): ?><span class="text-purple-400 font-bold"><?= $deptCounts['leave'] ?>SL</span><?php endif; ?>
                <?php if ($deptCounts['sent_home']): ?><span class="text-teal-400 font-bold"><?= $deptCounts['sent_home'] ?>SH</span><?php endif; ?>
                <?php if ($deptCounts['rest_day']): ?><span class="text-orange-400 font-bold"><?= $deptCounts['rest_day'] ?>RD</span><?php endif; ?>
                <?php if ($deptCounts['none']): ?><span class="text-gray-600 font-bold"><?= $deptCounts['none'] ?>—</span><?php endif; ?>
            </div>
        </div>

        <!-- Position Subfolders -->
        <div class="dept-body" id="body_<?= $dept['id'] ?>" style="display:none;">
            <?php foreach ($deptPosEmps as $position => $emps):
                $posCount = count($emps);
                $posKey = md5($dept['id'] . $position);
                
                $posCounts = ['present'=>0,'absent'=>0,'no_work'=>0,'leave'=>0,'sent_home'=>0,'rest_day'=>0,'none'=>0];
                foreach ($emps as $emp) {
                    $s = $attMap[$emp['id']]['status'] ?? 'none';
                    $posCounts[$s]++;
                }
            ?>
            <div class="bg-white/[0.02] border-l-2 border-primary-600/30 position-folder" data-pos-key="<?= $posKey ?>">
                <!-- Position Folder Header -->
                <div class="px-5 py-3 flex items-center justify-between cursor-pointer hover:bg-white/[0.04] transition-colors" onclick="togglePositionFolder('<?= $posKey ?>')">
                    <div class="flex items-center gap-2.5 flex-1">
                        <svg class="w-4 h-4 text-gray-500 transition-transform duration-200" id="pos_arrow_<?= $posKey ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        <span class="text-sm font-semibold text-gray-200">📂 <?= htmlspecialchars($position) ?></span>
                        <span class="text-xs text-gray-500 bg-white/5 px-2 py-0.5 rounded font-semibold"><?= $posCount ?></span>
                        <!-- Glowing position-level status counters -->
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
                <!-- Position Folder Body (Employees) -->
                <div class="position-body bg-white/[0.01]" id="pos_body_<?= $posKey ?>" style="display:none;">
                    <?php foreach ($emps as $emp):
                        $attInfo = $attMap[$emp['id']] ?? null;
                        $status = $attInfo['status'] ?? '';
                        $timeIn = $attInfo['time_in'] ?? '';
                        $timeOut = $attInfo['time_out'] ?? '';
                        $recordedBy = $attInfo['coordinator'] ?? '';
                        
                        $statusLabels = ['present'=>'P','absent'=>'A','no_work'=>'NW','leave'=>'SL','sent_home'=>'SH','rest_day'=>'RD'];
                        $statusBadge = ['present'=>'badge-present','absent'=>'badge-absent','no_work'=>'badge-no_work','leave'=>'badge-leave','sent_home'=>'badge-sent_home','rest_day'=>'badge-rest_day'];
                    ?>
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 px-8 py-3.5 border-b border-white/[0.04] last:border-0 hover:bg-white/[0.03] transition-all duration-200 employee-row-item" data-status="<?= $status ?: 'none' ?>">
                        <div class="flex-1 min-w-0">
                            <span class="text-sm font-bold text-white"><?= htmlspecialchars($emp['last_name'] . ', ' . $emp['first_name']) ?></span>
                            <div class="flex flex-wrap items-center gap-x-2.5 gap-y-1 text-xs text-gray-500 mt-1">
                                <?php if ($timeIn || $timeOut): ?>
                                    <span class="inline-flex items-center gap-1 bg-white/5 px-2 py-0.5 rounded text-gray-400">
                                        🕒 In: <?= $timeIn ?: '—' ?> | Out: <?= $timeOut ?: '—' ?>
                                    </span>
                                <?php endif; ?>
                                <?php if ($recordedBy): ?>
                                    <span class="inline-flex items-center gap-1 bg-primary-500/10 text-primary-300 px-2 py-0.5 rounded">
                                        ✍️ Coordinator: <?= htmlspecialchars($recordedBy) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="inline-flex items-center gap-1 bg-white/5 text-gray-600 px-2 py-0.5 rounded">
                                        🚫 Not recorded
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="flex items-center gap-3">
                            <?php if ($status): ?>
                                <span class="badge <?= $statusBadge[$status] ?? '' ?> text-xs"><?= $statusLabels[$status] ?? '—' ?></span>
                            <?php else: ?>
                                <span class="text-xs text-gray-600">—</span>
                            <?php endif; ?>
                            <a href="/ATTENDANCE/admin/employee_attendance.php?emp=<?= $emp['id'] ?>" class="text-xs text-primary-400 hover:text-primary-300 ml-2">History</a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Recent Activity -->
<div class="glass-card mb-6">
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

function togglePositionFolder(posKey) {
    const body = document.getElementById('pos_body_' + posKey);
    const arrow = document.getElementById('pos_arrow_' + posKey);
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

// Auto-expand first folder and initialize status monitoring filters
document.addEventListener('DOMContentLoaded', () => {
    // Auto-expand first folder
    const firstFolder = document.querySelector('.dept-folder');
    if (firstFolder) {
        const id = firstFolder.id.replace('dept_', '');
        toggleFolder(id);
        
        // Also auto-expand its first position subfolder
        const firstPos = firstFolder.querySelector('.position-folder');
        if (firstPos) {
            const posKey = firstPos.getAttribute('data-pos-key');
            togglePositionFolder(posKey);
        }
    }

    // Filter Buttons Interaction
    const filterButtons = document.querySelectorAll('.filter-btn');
    filterButtons.forEach(btn => {
        btn.addEventListener('click', () => {
            const filter = btn.getAttribute('data-filter');
            
            // Update active style
            filterButtons.forEach(b => {
                b.classList.remove('active');
                b.style.boxShadow = '';
            });
            btn.classList.add('active');
            
            // 1. Filter folder-style directory structure
            const employeeItems = document.querySelectorAll('.employee-row-item');
            employeeItems.forEach(item => {
                const status = item.getAttribute('data-status');
                if (filter === 'all') {
                    item.style.display = '';
                } else {
                    item.style.display = (status === filter) ? '' : 'none';
                }
            });

            // Filter position subfolders
            const posFolders = document.querySelectorAll('.position-folder');
            posFolders.forEach(posFolder => {
                const posKey = posFolder.getAttribute('data-pos-key');
                const posBody = document.getElementById('pos_body_' + posKey);
                const posArrow = document.getElementById('pos_arrow_' + posKey);
                const visibleEmployees = posFolder.querySelectorAll('.employee-row-item:not([style*="display: none"])');
                
                if (visibleEmployees.length > 0) {
                    posFolder.style.display = '';
                    // Expand matching subfolders automatically during active filter
                    if (filter !== 'all') {
                        if (posBody) posBody.style.display = '';
                        if (posArrow) posArrow.style.transform = 'rotate(90deg)';
                    }
                } else {
                    posFolder.style.display = 'none';
                }
            });

            // Filter department folders
            const deptFolders = document.querySelectorAll('.dept-folder');
            deptFolders.forEach(deptFolder => {
                const deptId = deptFolder.getAttribute('data-dept-id');
                const deptBody = document.getElementById('body_' + deptId);
                const deptArrow = document.getElementById('arrow_' + deptId);
                const visiblePositions = deptFolder.querySelectorAll('.position-folder:not([style*="display: none"])');
                
                if (visiblePositions.length > 0) {
                    deptFolder.style.display = '';
                    // Expand matching department folders automatically during active filter
                    if (filter !== 'all') {
                        if (deptBody) deptBody.style.display = '';
                        if (deptArrow) deptArrow.style.transform = 'rotate(90deg)';
                    }
                } else {
                    deptFolder.style.display = 'none';
                }
            });
        });
    });
});
</script>

<style>
/* Active state styling for interactive status monitoring buttons */
.filter-btn.active {
    background-color: rgb(32 201 151 / 0.25) !important;
    border-color: rgb(32 201 151 / 0.6) !important;
    color: #ffffff !important;
    box-shadow: 0 0 12px rgba(32, 201, 151, 0.4);
}
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
