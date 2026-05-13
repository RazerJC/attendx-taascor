<?php
/**
 * Admin — Employee Attendance Calendar View
 * Shows monthly attendance per employee with calendar grid
 */
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();
$db = getDB();
$pageTitle = 'Employee Attendance';

// All active employees for dropdown
$employees = $db->query("
    SELECT e.id, CONCAT(e.last_name, ', ', e.first_name) as full_name, d.name as dept_name
    FROM employees e
    JOIN departments d ON e.department_id = d.id
    WHERE e.status = 'active'
    ORDER BY e.last_name, e.first_name
")->fetchAll();

// Selected employee, month, year
$empId = $_GET['emp'] ?? ($employees[0]['id'] ?? 0);
$month = (int)($_GET['month'] ?? date('n'));
$year  = (int)($_GET['year']  ?? date('Y'));

// Clamp month/year
if ($month < 1) $month = 1;
if ($month > 12) $month = 12;
if ($year < 2020) $year = 2020;
if ($year > 2030) $year = 2030;

// Get employee info
$empStmt = $db->prepare("SELECT e.*, d.name as dept_name FROM employees e JOIN departments d ON e.department_id = d.id WHERE e.id = ?");
$empStmt->execute([$empId]);
$employee = $empStmt->fetch();

// Get attendance records for the month
$dateFrom = sprintf('%04d-%02d-01', $year, $month);
$dateTo   = date('Y-m-t', strtotime($dateFrom));

$attStmt = $db->prepare("
    SELECT * FROM attendance
    WHERE employee_id = ? AND date BETWEEN ? AND ?
    ORDER BY date ASC
");
$attStmt->execute([$empId, $dateFrom, $dateTo]);
$records = $attStmt->fetchAll();

// Index by date for calendar lookup
$attendanceByDate = [];
foreach ($records as $r) {
    $attendanceByDate[$r['date']] = $r;
}



// Compute summary for the month
$summary = ['present' => 0, 'absent' => 0, 'no_work' => 0, 'leave' => 0, 'sent_home' => 0, 'late' => 0, 'undertime' => 0, 'ot' => 0, 'total_hrs' => 0, 'ot_hrs' => 0];
foreach ($records as $r) {
    if (isset($summary[$r['status']])) $summary[$r['status']]++;
    if ($r['is_late']) $summary['late']++;
    if ($r['is_undertime']) $summary['undertime']++;
    if ($r['ot_hours'] > 0) $summary['ot']++;
    $summary['total_hrs'] += $r['total_hours'];
    $summary['ot_hrs']    += $r['ot_hours'];
}

// Calendar calculations
$daysInMonth  = (int)date('t', strtotime($dateFrom));
$firstDayOfWeek = (int)date('N', strtotime($dateFrom)); // 1=Mon, 7=Sun
$today = date('Y-m-d');

$monthNames = ['','January','February','March','April','May','June','July','August','September','October','November','December'];

require_once __DIR__ . '/../includes/header.php';
?>

<!-- Filters -->
<form method="GET" class="flex flex-col sm:flex-row flex-wrap gap-3 mb-5">
    <select name="emp" class="flex-1 min-w-[200px] px-3 py-2.5 bg-dark-700/50 border border-white/10 rounded-xl text-white text-sm focus:outline-none focus:border-primary-500/50" onchange="this.form.submit()">
        <?php foreach ($employees as $e): ?>
        <option value="<?= $e['id'] ?>" <?= $empId == $e['id'] ? 'selected' : '' ?>><?= htmlspecialchars($e['full_name']) ?> — <?= htmlspecialchars($e['dept_name']) ?></option>
        <?php endforeach; ?>
    </select>
    <select name="month" class="px-3 py-2.5 bg-dark-700/50 border border-white/10 rounded-xl text-white text-sm focus:outline-none focus:border-primary-500/50 min-w-[140px]">
        <?php for ($m = 1; $m <= 12; $m++): ?>
        <option value="<?= $m ?>" <?= $month == $m ? 'selected' : '' ?>><?= $monthNames[$m] ?></option>
        <?php endfor; ?>
    </select>
    <select name="year" class="px-3 py-2.5 bg-dark-700/50 border border-white/10 rounded-xl text-white text-sm focus:outline-none focus:border-primary-500/50 min-w-[100px]">
        <?php for ($y = 2024; $y <= 2030; $y++): ?>
        <option value="<?= $y ?>" <?= $year == $y ? 'selected' : '' ?>><?= $y ?></option>
        <?php endfor; ?>
    </select>
    <button type="submit" class="px-5 py-2.5 bg-primary-600 hover:bg-primary-500 text-white rounded-xl text-xs font-semibold uppercase tracking-wider transition-colors">
        View
    </button>
</form>

<?php if ($employee): ?>

<!-- Employee Info -->
<div class="glass-card mb-5">
    <div class="glass-card-body flex flex-col sm:flex-row items-start sm:items-center gap-3">
        <div class="w-10 h-10 rounded-full bg-primary-600/30 flex items-center justify-center text-primary-400 font-bold text-lg flex-shrink-0">
            <?= strtoupper(substr($employee['first_name'], 0, 1)) ?>
        </div>
        <div class="flex-1">
            <div class="text-white font-semibold text-base"><?= htmlspecialchars($employee['last_name'] . ', ' . $employee['first_name']) ?></div>
            <div class="text-gray-500 text-xs"><?= htmlspecialchars($employee['position']) ?> · <?= htmlspecialchars($employee['dept_name']) ?></div>
        </div>
        <div class="text-xs text-gray-600">
            <?= $monthNames[$month] ?> <?= $year ?>
        </div>
    </div>
</div>

<!-- Summary Cards -->
<div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-8 gap-3 mb-5">
    <div class="stat-card">
        <div class="stat-icon bg-green-500/15 text-green-400">✅</div>
        <div class="stat-value text-green-400"><?= $summary['present'] ?></div>
        <div class="stat-label">Present (P)</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon bg-red-500/15 text-red-400">❌</div>
        <div class="stat-value text-red-400"><?= $summary['absent'] ?></div>
        <div class="stat-label">Absent (A)</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon bg-gray-500/15 text-gray-400">🚫</div>
        <div class="stat-value text-gray-400"><?= $summary['no_work'] ?></div>
        <div class="stat-label">No Work (NW)</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon bg-purple-500/15 text-purple-400">📋</div>
        <div class="stat-value text-purple-400"><?= $summary['leave'] ?></div>
        <div class="stat-label">Leave (SL)</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon bg-teal-500/15 text-teal-400">🏠</div>
        <div class="stat-value text-teal-400"><?= $summary['sent_home'] ?></div>
        <div class="stat-label">Sent Home (SH)</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon bg-yellow-500/15 text-yellow-400">⏰</div>
        <div class="stat-value text-yellow-400"><?= $summary['late'] ?></div>
        <div class="stat-label">Late</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon bg-orange-500/15 text-orange-400">⚠️</div>
        <div class="stat-value text-orange-400"><?= $summary['undertime'] ?></div>
        <div class="stat-label">Undertime</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon bg-cyan-500/15 text-cyan-400">🕐</div>
        <div class="stat-value text-cyan-400"><?= number_format($summary['ot_hrs'], 1) ?></div>
        <div class="stat-label">Total OT Hrs</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon bg-primary-500/15 text-primary-400">📊</div>
        <div class="stat-value text-primary-400"><?= number_format($summary['total_hrs'], 1) ?></div>
        <div class="stat-label">Total Work Hrs</div>
    </div>
</div>

<!-- Calendar Grid -->
<div class="glass-card mb-5">
    <div class="glass-card-header">
        <h2 class="text-sm font-semibold text-white"><?= $monthNames[$month] ?> <?= $year ?> — Attendance Calendar</h2>
        <div class="flex items-center gap-2 text-xs flex-wrap">
            <span class="flex items-center gap-1"><span class="w-3 h-3 rounded-sm bg-green-500/40 border border-green-500/60 inline-block"></span> P</span>
            <span class="flex items-center gap-1"><span class="w-3 h-3 rounded-sm bg-red-500/40 border border-red-500/60 inline-block"></span> A</span>
            <span class="flex items-center gap-1"><span class="w-3 h-3 rounded-sm bg-gray-500/40 border border-gray-500/60 inline-block"></span> NW</span>
            <span class="flex items-center gap-1"><span class="w-3 h-3 rounded-sm bg-purple-500/40 border border-purple-500/60 inline-block"></span> SL</span>
            <span class="flex items-center gap-1"><span class="w-3 h-3 rounded-sm bg-teal-500/40 border border-teal-500/60 inline-block"></span> SH</span>
            <span class="flex items-center gap-1"><span class="w-3 h-3 rounded-sm bg-dark-700 border border-white/10 inline-block"></span> —</span>
        </div>
    </div>
    <div class="glass-card-body p-3 sm:p-5">
        <!-- Day headers -->
        <div class="grid grid-cols-7 gap-1 sm:gap-2 mb-2">
            <?php foreach (['Mon','Tue','Wed','Thu','Fri','Sat','Sun'] as $dayName): ?>
            <div class="text-center text-xs font-semibold text-gray-500 uppercase tracking-wider py-1"><?= $dayName ?></div>
            <?php endforeach; ?>
        </div>

        <!-- Calendar cells -->
        <div class="grid grid-cols-7 gap-1 sm:gap-2">
            <?php
            // Leading empty cells
            for ($i = 1; $i < $firstDayOfWeek; $i++):
            ?>
            <div class="aspect-square"></div>
            <?php endfor; ?>

            <?php
            for ($day = 1; $day <= $daysInMonth; $day++):
                $dateStr = sprintf('%04d-%02d-%02d', $year, $month, $day);
                $att = $attendanceByDate[$dateStr] ?? null;

                $isFuture  = $dateStr > $today;
                $isToday   = $dateStr === $today;

                // Determine cell style
                $cellClasses = 'cal-day relative aspect-square rounded-xl flex flex-col items-center justify-center cursor-pointer transition-all duration-200 border ';
                $dotHtml = '';

                if ($att) {
                    if ($att['status'] === 'present') {
                        $cellClasses .= 'bg-green-500/15 border-green-500/30 hover:bg-green-500/25 hover:border-green-500/50';
                        if ($att['is_late']) {
                            $dotHtml = '<span class="absolute top-1 right-1 w-2 h-2 rounded-full bg-yellow-400 animate-pulse" title="Late"></span>';
                        }
                        if ($att['is_undertime']) {
                            $dotHtml .= '<span class="absolute top-1 left-1 w-2 h-2 rounded-full bg-orange-400" title="Undertime"></span>';
                        }
                    } elseif ($att['status'] === 'absent') {
                        $cellClasses .= 'bg-red-500/15 border-red-500/30 hover:bg-red-500/25 hover:border-red-500/50';
                    } elseif ($att['status'] === 'no_work') {
                        $cellClasses .= 'bg-gray-500/15 border-gray-500/30 hover:bg-gray-500/25 hover:border-gray-500/50';
                    } elseif ($att['status'] === 'leave') {
                        $cellClasses .= 'bg-purple-500/15 border-purple-500/30 hover:bg-purple-500/25 hover:border-purple-500/50';
                    } elseif ($att['status'] === 'sent_home') {
                        $cellClasses .= 'bg-teal-500/15 border-teal-500/30 hover:bg-teal-500/25 hover:border-teal-500/50';
                    }
                } elseif ($isFuture) {
                    $cellClasses .= 'bg-dark-700/30 border-white/5 opacity-40';

                } else {
                    $cellClasses .= 'bg-dark-700/30 border-white/8 hover:bg-dark-700/50';
                }

                if ($isToday) {
                    $cellClasses .= ' ring-2 ring-primary-500/50';
                }

                // Build tooltip data
                $tooltipData = '';
                $statusNames = ['present'=>'Present','absent'=>'Absent','no_work'=>'No Work','leave'=>'Leave','sent_home'=>'Sent Home'];
                if ($att) {
                    $tipParts = [];
                    $tipParts[] = date('M d, Y (D)', strtotime($dateStr));
                    $tipParts[] = 'Status: ' . ($statusNames[$att['status']] ?? ucfirst($att['status']));
                    $tooltipData = htmlspecialchars(implode("\n", $tipParts));
                }
            ?>
            <div class="<?= $cellClasses ?>"
                 <?php if ($tooltipData): ?>
                 onclick="showDayDetail(this)" data-tooltip="<?= $tooltipData ?>"
                 <?php endif; ?>>
                <?= $dotHtml ?>
                <?php
                    $statusLabels = ['present'=>'P','absent'=>'A','no_work'=>'NW','leave'=>'SL','sent_home'=>'SH'];
                    $statusColors = ['present'=>'text-green-300','absent'=>'text-red-300','no_work'=>'text-gray-400','leave'=>'text-purple-300','sent_home'=>'text-teal-300'];
                    $dayColor = $isToday ? 'text-primary-400' : ($att ? ($statusColors[$att['status']] ?? 'text-gray-600') : 'text-gray-600');
                ?>
                <span class="text-sm font-semibold <?= $dayColor ?>"><?= $day ?></span>
                <?php if ($att): ?>
                <span class="text-[9px] font-bold leading-tight mt-0.5 <?= $statusColors[$att['status']] ?? 'text-gray-500' ?>"><?= $statusLabels[$att['status']] ?? '—' ?></span>
                <?php endif; ?>
            </div>
            <?php endfor; ?>
        </div>
    </div>
</div>

<!-- Day Detail Popup -->
<div id="dayDetailPopup" class="modal-overlay" onclick="if(event.target===this)this.classList.remove('show')">
    <div class="modal-content" style="max-width:24rem;">
        <div class="px-5 py-4 border-b border-white/10 flex items-center justify-between">
            <h3 class="text-sm font-semibold text-white">📋 Day Details</h3>
            <button onclick="document.getElementById('dayDetailPopup').classList.remove('show')" class="text-gray-500 hover:text-white text-xl">&times;</button>
        </div>
        <div id="dayDetailContent" class="p-5 text-sm text-gray-300 space-y-2">
        </div>
    </div>
</div>

<!-- Detailed Table -->
<div class="glass-card">
    <div class="glass-card-header">
        <h2 class="text-sm font-semibold text-white">Detailed Attendance Records (<?= count($records) ?>)</h2>
        <span class="text-xs text-gray-500"><?= $monthNames[$month] ?> <?= $year ?></span>
    </div>
    <div class="table-wrap">
        <table class="glass-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Day</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($records)): ?>
                <tr><td colspan="3" class="text-center text-gray-600 py-8">No attendance records found for this month</td></tr>
            <?php else: ?>
                <?php foreach ($records as $r): ?>
                <tr>
                    <td class="text-gray-400 whitespace-nowrap"><?= date('M d, Y', strtotime($r['date'])) ?></td>
                    <td class="text-gray-500"><?= date('D', strtotime($r['date'])) ?></td>
                    <?php
                        $statusDisplay = ['present'=>'P','absent'=>'A','no_work'=>'NW','leave'=>'SL','sent_home'=>'SH'];
                    ?>
                    <td><span class="badge badge-<?= $r['status'] ?>"><?= $statusDisplay[$r['status']] ?? ucfirst($r['status']) ?></span></td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php else: ?>
<div class="glass-card">
    <div class="glass-card-body text-center py-12 text-gray-500">
        <p class="text-lg mb-2">No employee found</p>
        <p class="text-sm">Please select a valid employee from the dropdown.</p>
    </div>
</div>
<?php endif; ?>

<script>
function showDayDetail(el) {
    const data = el.getAttribute('data-tooltip');
    if (!data) return;
    const lines = data.split('\n');
    const container = document.getElementById('dayDetailContent');
    container.innerHTML = '';

    lines.forEach((line, i) => {
        const div = document.createElement('div');
        if (i === 0) {
            div.className = 'text-white font-semibold text-base mb-1';
            div.textContent = line;
        } else {
            const parts = line.split(': ');
            if (parts.length >= 2) {
                const label = parts[0];
                const value = parts.slice(1).join(': ');
                div.innerHTML = '<span class="text-gray-500">' + label + ':</span> <span class="text-white font-medium">' + escapeHtml(value) + '</span>';

                // Color-code status
                if (label === 'Status') {
                    const statusColor = value.toLowerCase() === 'present' ? 'text-green-400' : 'text-red-400';
                    div.innerHTML = '<span class="text-gray-500">' + label + ':</span> <span class="font-semibold ' + statusColor + '">' + escapeHtml(value) + '</span>';
                }
                if (label === 'Late') {
                    div.innerHTML = '<span class="text-gray-500">' + label + ':</span> <span class="text-yellow-400 font-medium">' + escapeHtml(value) + '</span>';
                }
                if (label === 'Undertime') {
                    div.innerHTML = '<span class="text-gray-500">' + label + ':</span> <span class="text-orange-400 font-medium">' + escapeHtml(value) + '</span>';
                }
            } else {
                div.textContent = line;
            }
        }
        container.appendChild(div);
    });

    document.getElementById('dayDetailPopup').classList.add('show');
}

function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
