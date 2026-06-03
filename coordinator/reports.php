<?php
/**
 * Coordinator — Attendance Report
 * Date-range report with department folders, printable
 */
require_once __DIR__ . '/../includes/auth.php';
requireCoordinator();
$db = getDB();
$pageTitle = 'Attendance Report';

$dateFrom = $_GET['from'] ?? date('Y-m-01');
$dateTo = $_GET['to'] ?? date('Y-m-d');

$departments = getVisibleDepartments($db);
$allEmps = getVisibleEmployees($db);

$empsByDept = [];
foreach ($allEmps as $emp) {
    $empsByDept[$emp['department_id']][] = $emp;
}

// Get attendance for date range
$attStmt = $db->prepare("SELECT employee_id, status, COUNT(*) as cnt FROM attendance WHERE date BETWEEN ? AND ? GROUP BY employee_id, status");
$attStmt->execute([$dateFrom, $dateTo]);
$attData = [];
foreach ($attStmt->fetchAll() as $row) {
    $attData[$row['employee_id']][$row['status']] = $row['cnt'];
}

$totalDays = (int)((strtotime($dateTo) - strtotime($dateFrom)) / 86400) + 1;

$statusLabels = ['present'=>'P','absent'=>'A','no_work'=>'NW','leave'=>'SL','sent_home'=>'SH','rest_day'=>'RD'];

require_once __DIR__ . '/../includes/header.php';
?>

<!-- Date Range Selector -->
<div class="flex flex-col sm:flex-row items-start sm:items-center gap-3 mb-5">
    <form method="GET" class="flex flex-wrap items-end gap-2">
        <div>
            <label class="block text-[10px] font-medium text-gray-400 mb-1 uppercase tracking-wider">From</label>
            <input type="date" name="from" value="<?= $dateFrom ?>" class="px-3 py-2 bg-dark-700/50 border border-white/10 rounded-xl text-white text-sm focus:outline-none focus:border-primary-500/50">
        </div>
        <div>
            <label class="block text-[10px] font-medium text-gray-400 mb-1 uppercase tracking-wider">To</label>
            <input type="date" name="to" value="<?= $dateTo ?>" class="px-3 py-2 bg-dark-700/50 border border-white/10 rounded-xl text-white text-sm focus:outline-none focus:border-primary-500/50">
        </div>
        <button type="submit" class="px-4 py-2 bg-primary-600 hover:bg-primary-500 text-white rounded-xl text-xs font-semibold uppercase tracking-wider transition-all">Generate</button>
    </form>
    <div class="flex gap-2 ml-auto">
        <span class="text-xs text-gray-500 bg-white/5 px-3 py-1.5 rounded-full"><?= $totalDays ?> day(s)</span>
        <button type="button" class="px-4 py-2 bg-dark-700 border border-white/10 hover:bg-dark-700/80 text-white rounded-xl text-xs font-semibold uppercase tracking-wider transition-all" onclick="window.print()">🖨️ Print</button>
    </div>
</div>

<!-- Report by Department -->
<div class="space-y-3" id="reportContent">
    <?php foreach ($departments as $dept):
        $deptEmps = $empsByDept[$dept['id']] ?? [];
        if (empty($deptEmps)) continue;
        
        // Sort employees by position, then alphabetically by last name, then first name
        usort($deptEmps, function($a, $b) {
            $posA = $a['position'] ?: 'Unassigned';
            $posB = $b['position'] ?: 'Unassigned';
            $posComp = strcmp($posA, $posB);
            if ($posComp !== 0) {
                return $posComp;
            }
            $lastNameComp = strcmp($a['last_name'], $b['last_name']);
            if ($lastNameComp !== 0) {
                return $lastNameComp;
            }
            return strcmp($a['first_name'], $b['first_name']);
        });
    ?>
    <div class="glass-card">
        <div class="glass-card-header">
            <span class="text-sm font-semibold text-white">📁 <?= htmlspecialchars($dept['name']) ?></span>
            <span class="text-xs text-gray-500"><?= count($deptEmps) ?> employees</span>
        </div>
        <div class="table-wrap">
            <table class="glass-table">
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th>Position</th>
                        <th class="text-center">P</th>
                        <th class="text-center">A</th>
                        <th class="text-center">NW</th>
                        <th class="text-center">SL</th>
                        <th class="text-center">SH</th>
                        <th class="text-center">RD</th>
                        <th class="text-center">Total</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($deptEmps as $emp):
                    $empAtt = $attData[$emp['id']] ?? [];
                    $p  = $empAtt['present'] ?? 0;
                    $a  = $empAtt['absent'] ?? 0;
                    $nw = $empAtt['no_work'] ?? 0;
                    $sl = $empAtt['leave'] ?? 0;
                    $sh = $empAtt['sent_home'] ?? 0;
                    $rd = $empAtt['rest_day'] ?? 0;
                    $total = $p + $a + $nw + $sl + $sh + $rd;
                ?>
                <tr>
                    <td class="font-medium text-white whitespace-nowrap"><?= htmlspecialchars($emp['last_name'] . ', ' . $emp['first_name']) ?></td>
                    <td class="text-gray-500"><?= htmlspecialchars($emp['position']) ?></td>
                    <td class="text-center"><span class="<?= $p ? 'text-green-400 font-semibold' : 'text-gray-700' ?>"><?= $p ?></span></td>
                    <td class="text-center"><span class="<?= $a ? 'text-red-400 font-semibold' : 'text-gray-700' ?>"><?= $a ?></span></td>
                    <td class="text-center"><span class="<?= $nw ? 'text-gray-400 font-semibold' : 'text-gray-700' ?>"><?= $nw ?></span></td>
                    <td class="text-center"><span class="<?= $sl ? 'text-purple-400 font-semibold' : 'text-gray-700' ?>"><?= $sl ?></span></td>
                    <td class="text-center"><span class="<?= $sh ? 'text-teal-400 font-semibold' : 'text-gray-700' ?>"><?= $sh ?></span></td>
                    <td class="text-center"><span class="<?= $rd ? 'text-orange-400 font-semibold' : 'text-gray-700' ?>"><?= $rd ?></span></td>
                    <td class="text-center text-white font-semibold"><?= $total ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Print Styles -->
<style>
@media print {
    body { background: white !important; color: black !important; }
    aside, header, .nav-link, button[onclick*="print"], form, [data-toggle-dept], [data-edit-dept] { display: none !important; }
    .glass-card { background: white !important; border: 1px solid #ddd !important; break-inside: avoid; }
    .glass-card-header { background: #f5f5f5 !important; }
    .glass-table thead th { color: #333 !important; border-bottom: 2px solid #333 !important; }
    .glass-table tbody td { color: #333 !important; border-bottom: 1px solid #ddd !important; }
    .stat-card, .sticky { display: none !important; }
    main { padding: 0 !important; }
    #reportContent { margin: 0; }
}
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
