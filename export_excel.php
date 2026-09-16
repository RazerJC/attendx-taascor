<?php
/**
 * Export Department Attendance to Excel-compatible HTML
 */
require_once __DIR__ . '/includes/auth.php';
requireAdmin();

$db = getDB();

$deptId = $_GET['department_id'] ?? '';
$startDate = $_GET['start_date'] ?? '';
$endDate = $_GET['end_date'] ?? '';

if (empty($startDate) || empty($endDate)) {
    die("Error: Please select start and end dates.");
}

// Build query
$params = [$startDate, $endDate];
$whereDept = "";

if ($deptId && $deptId !== 'all') {
    $whereDept = "AND e.department_id = ?";
    $params[] = $deptId;
}

$query = "
    SELECT 
        a.date,
        e.first_name,
        e.last_name,
        e.position,
        d.name AS department_name,
        a.status,
        a.time_in,
        a.time_out,
        a.late_minutes,
        a.undertime_minutes,
        a.ot_hours,
        a.total_hours,
        a.remarks,
        u.full_name AS coordinator_name
    FROM attendance a
    JOIN employees e ON a.employee_id = e.id
    JOIN departments d ON e.department_id = d.id
    LEFT JOIN users u ON a.recorded_by = u.id
    WHERE a.date BETWEEN ? AND ?
    $whereDept
    ORDER BY a.date DESC, d.name ASC, e.last_name ASC, e.first_name ASC
";

$stmt = $db->prepare($query);
$stmt->execute($params);
$records = $stmt->fetchAll();

// Clean department name for filename
$deptName = 'All_Departments';
if ($deptId && $deptId !== 'all') {
    $deptStmt = $db->prepare("SELECT name FROM departments WHERE id = ?");
    $deptStmt->execute([$deptId]);
    $res = $deptStmt->fetch();
    if ($res) {
        $deptName = preg_replace('/[^A-Za-z0-9_\-]/', '_', $res['name']);
    }
}

$filename = "Attendance_" . $deptName . "_" . $startDate . "_to_" . $endDate . ".xls";

// Set Excel Headers
header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=\"$filename\"");
header("Pragma: no-cache");
header("Expires: 0");

$statusLabels = [
    'present' => 'Present',
    'absent' => 'Absent',
    'no_work' => 'No Work',
    'leave' => 'Sick Leave',
    'sent_home' => 'Sent Home',
    'rest_day' => 'Rest Day'
];
?>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<style>
    table {
        border-collapse: collapse;
        width: 100%;
    }
    th {
        background-color: #3b82f6;
        color: #ffffff;
        font-weight: bold;
        border: 1px solid #cccccc;
        padding: 8px;
        text-align: left;
    }
    td {
        border: 1px solid #cccccc;
        padding: 8px;
        text-align: left;
    }
    .status-present { color: #16a34a; font-weight: bold; }
    .status-absent { color: #dc2626; font-weight: bold; }
    .status-no_work { color: #4b5563; }
    .status-leave { color: #9333ea; font-weight: bold; }
    .status-sent_home { color: #0d9488; font-weight: bold; }
    .status-rest_day { color: #ea580c; font-weight: bold; }
</style>
<table>
    <thead>
        <tr>
            <th>Date</th>
            <th>Employee Name</th>
            <th>Department</th>
            <th>Position</th>
            <th>Status</th>
            <th>Time In</th>
            <th>Time Out</th>
            <th>Late (mins)</th>
            <th>Undertime (mins)</th>
            <th>OT Hours</th>
            <th>Total Hours</th>
            <th>Recorded By</th>
            <th>Remarks</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($records)): ?>
            <tr>
                <td colspan="13" style="text-align: center;">No attendance records found for this selection.</td>
            </tr>
        <?php else: ?>
            <?php foreach ($records as $r): 
                $statusClass = 'status-' . $r['status'];
                $statusText = $statusLabels[$r['status']] ?? $r['status'];
                $fullName = $r['last_name'] . ', ' . $r['first_name'];
            ?>
                <tr>
                    <td><?= htmlspecialchars($r['date']) ?></td>
                    <td><?= htmlspecialchars($fullName) ?></td>
                    <td><?= htmlspecialchars($r['department_name']) ?></td>
                    <td><?= htmlspecialchars($r['position'] ?? '—') ?></td>
                    <td class="<?= $statusClass ?>"><?= htmlspecialchars($statusText) ?></td>
                    <td><?= htmlspecialchars($r['time_in'] ?? '—') ?></td>
                    <td><?= htmlspecialchars($r['time_out'] ?? '—') ?></td>
                    <td><?= (int)$r['late_minutes'] ?></td>
                    <td><?= (int)$r['undertime_minutes'] ?></td>
                    <td><?= number_format((float)$r['ot_hours'], 2) ?></td>
                    <td><?= number_format((float)$r['total_hours'], 2) ?></td>
                    <td><?= htmlspecialchars($r['coordinator_name'] ?? 'System') ?></td>
                    <td><?= htmlspecialchars($r['remarks'] ?? '—') ?></td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>
