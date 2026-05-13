<?php
/**
 * Admin — Attendance Reports
 */
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();
$db = getDB();
$pageTitle = 'Attendance Reports';

$departments = $db->query("SELECT * FROM departments ORDER BY name")->fetchAll();

$deptFilter  = $_GET['dept'] ?? '';
$dateFrom    = $_GET['from'] ?? date('Y-m-01');
$dateTo      = $_GET['to'] ?? date('Y-m-d');

// Build query
$sql = "SELECT a.date, a.status, a.is_late, a.late_minutes, a.is_undertime, a.undertime_minutes,
        a.ot_hours, a.total_hours, a.time_in, a.time_out, a.ot_start, a.ot_end, a.remarks,
        CONCAT(e.last_name,', ',e.first_name) as employee_name, e.id as employee_id,
        d.name as dept_name
        FROM attendance a
        JOIN employees e ON a.employee_id = e.id
        JOIN departments d ON e.department_id = d.id
        WHERE a.date BETWEEN ? AND ?";
$params = [$dateFrom, $dateTo];

if ($deptFilter) {
    $sql .= " AND e.department_id = ?";
    $params[] = $deptFilter;
}
$sql .= " ORDER BY a.date DESC, d.name, e.last_name, e.first_name";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$records = $stmt->fetchAll();

// Compute summary
$summary = [
    'present'   => 0,
    'absent'    => 0,
    'no_work'   => 0,
    'leave'     => 0,
    'sent_home' => 0,
    'rest_day'  => 0,
    'late'      => 0,
    'undertime' => 0,
    'ot'        => 0,
];
$totalOtHrs = 0;
$totalWorkHrs = 0;
foreach ($records as $r) {
    if (isset($summary[$r['status']])) {
        $summary[$r['status']]++;
    }
    if ($r['is_late']) $summary['late']++;
    if ($r['is_undertime']) $summary['undertime']++;
    if ($r['ot_hours'] > 0) $summary['ot']++;
    $totalOtHrs += $r['ot_hours'];
    $totalWorkHrs += $r['total_hours'];
}

require_once __DIR__ . '/../includes/header.php';
?>

<!-- Filters -->
<form method="GET" class="flex flex-col sm:flex-row flex-wrap gap-3 mb-5">
    <div class="flex gap-2 flex-1 min-w-0">
        <input type="date" name="from" value="<?= $dateFrom ?>" class="flex-1 px-3 py-2.5 bg-dark-700/50 border border-white/10 rounded-xl text-white text-sm focus:outline-none focus:border-primary-500/50">
        <input type="date" name="to" value="<?= $dateTo ?>" class="flex-1 px-3 py-2.5 bg-dark-700/50 border border-white/10 rounded-xl text-white text-sm focus:outline-none focus:border-primary-500/50">
    </div>
    <select name="dept" class="px-3 py-2.5 bg-dark-700/50 border border-white/10 rounded-xl text-white text-sm focus:outline-none focus:border-primary-500/50 min-w-[140px]">
        <option value="">All Departments</option>
        <?php foreach ($departments as $d): ?>
        <option value="<?= $d['id'] ?>" <?= $deptFilter == $d['id'] ? 'selected' : '' ?>><?= htmlspecialchars($d['name']) ?></option>
        <?php endforeach; ?>
    </select>
    <button type="submit" class="px-5 py-2.5 bg-primary-600 hover:bg-primary-500 text-white rounded-xl text-xs font-semibold uppercase tracking-wider transition-colors">
        Generate Report
    </button>
    <button type="button" onclick="window.print()" class="px-5 py-2.5 bg-dark-700 border border-white/10 hover:bg-dark-700/80 text-white rounded-xl text-xs font-semibold uppercase tracking-wider transition-colors text-center">
        🖨️ Print
    </button>
</form>

<!-- Summary Cards -->
<div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-7 gap-3 mb-5">
    <div class="stat-card">
        <div class="stat-value text-green-400"><?= $summary['present'] ?></div>
        <div class="stat-label">Present</div>
    </div>
    <div class="stat-card">
        <div class="stat-value text-red-400"><?= $summary['absent'] ?></div>
        <div class="stat-label">Absent</div>\
    </div>
    <div class="stat-card">
        <div class="stat-value text-yellow-400"><?= $summary['late'] ?></div>
        <div class="stat-label">Late Warnings</div>
    </div>
    <div class="stat-card">
        <div class="stat-value text-orange-400"><?= $summary['undertime'] ?></div>
        <div class="stat-label">Undertime</div>
    </div>
    <div class="stat-card">
        <div class="stat-value text-cyan-400"><?= $summary['ot'] ?></div>
        <div class="stat-label">OT Records</div>
    </div>
    <div class="stat-card">
        <div class="stat-value text-cyan-300"><?= number_format($totalOtHrs, 1) ?></div>
        <div class="stat-label">Total OT Hrs</div>
    </div>
    <div class="stat-card">
        <div class="stat-value text-primary-400"><?= number_format($totalWorkHrs, 1) ?></div>
        <div class="stat-label">Total Work Hrs</div>
    </div>
</div>

<!-- Report Table -->
<div class="glass-card">
    <div class="glass-card-header">
        <h2 class="text-sm font-semibold text-white">Attendance Records (<?= count($records) ?>)</h2>
        <span class="text-xs text-gray-500"><?= date('M d', strtotime($dateFrom)) ?> — <?= date('M d, Y', strtotime($dateTo)) ?></span>
    </div>
    <div class="table-wrap">
        <table class="glass-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Employee</th>
                    <th>Dept</th>
                    <th>Status</th>
                    <th>Time In</th>
                    <th>Time Out</th>
                    <th>Late?</th>
                    <th>UT?</th>
                    <th>OT Hrs</th>
                    <th>Total Hrs</th>
                    <th>Remarks</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($records)): ?>
                <tr><td colspan="11" class="text-center text-gray-600 py-8">No records found for this period</td></tr>
            <?php else: ?>
                <?php foreach ($records as $r): ?>
                <tr>
                    <td class="text-gray-400 whitespace-nowrap"><?= date('M d, Y', strtotime($r['date'])) ?></td>
                    <td class="font-medium text-white whitespace-nowrap"><?= htmlspecialchars($r['employee_name']) ?></td>
                    <td class="text-gray-500"><?= htmlspecialchars($r['dept_name']) ?></td>
                    <?php
                        $statusDisplay = ['present'=>'P','absent'=>'A','no_work'=>'NW','leave'=>'SL'];
                    ?>
                    <td><span class="badge badge-<?= $r['status'] ?>"><?= $statusDisplay[$r['status']] ?? ucfirst($r['status']) ?></span></td>
                    <td class="text-gray-400"><?= $r['time_in'] ? date('g:i A', strtotime($r['time_in'])) : '—' ?></td>
                    <td class="text-gray-400"><?= $r['time_out'] ? date('g:i A', strtotime($r['time_out'])) : '—' ?></td>
                    <td>
                        <?php if ($r['is_late']): ?>
                            <span class="badge badge-late-warn"><?= $r['late_minutes'] ?>min</span>
                        <?php else: ?>
                            <span class="text-gray-600">—</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($r['is_undertime']): ?>
                            <span class="badge badge-undertime"><?= $r['undertime_minutes'] ?>min</span>
                        <?php else: ?>
                            <span class="text-gray-600">—</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-cyan-400"><?= $r['ot_hours'] > 0 ? number_format($r['ot_hours'], 1) : '—' ?></td>
                    <td class="text-primary-400 font-medium"><?= $r['total_hours'] > 0 ? number_format($r['total_hours'], 1) : '—' ?></td>
                    <td class="text-gray-500 text-xs"><?= htmlspecialchars($r['remarks'] ?: '—') ?></td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
