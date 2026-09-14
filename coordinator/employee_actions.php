<?php
/**
 * Coordinator — Employee Actions (Disciplinary & Compliance Monitoring)
 * 4-tab read-only view: Absence Warnings | Report-to-Office | Back-to-Work | Suspensions
 * Filtered by the coordinator's assigned department.
 */
require_once __DIR__ . '/../includes/auth.php';
requireCoordinator();
$db = getDB();
$user = currentUser();
$pageTitle = 'Employee Actions';

$tab = $_GET['tab'] ?? 'warnings';
$search = $_GET['search'] ?? '';

$coordDeptId = $user['department_id'];
$coordDeptName = '';
if ($coordDeptId) {
    $deptStmt = $db->prepare("SELECT name FROM departments WHERE id = ?");
    $deptStmt->execute([$coordDeptId]);
    $coordDeptName = $deptStmt->fetchColumn() ?: '';
}

// Get employees visible to this coordinator
$allEmps = getVisibleEmployees($db);

// Filter by search query if present
if ($search) {
    $searchLower = strtolower($search);
    $allEmps = array_filter($allEmps, function($emp) use ($searchLower) {
        return strpos(strtolower($emp['first_name']), $searchLower) !== false
            || strpos(strtolower($emp['last_name']), $searchLower) !== false
            || strpos(strtolower($emp['position'] ?? ''), $searchLower) !== false;
    });
}

$empIds = array_column($allEmps, 'id');
$empIdPlaceholders = count($empIds) > 0 ? implode(',', $empIds) : '0';

// Fetch data based on active tab
$warnings = $rtoNotices = $btwRecords = $suspensions = [];

if ($coordDeptId && count($empIds) > 0) {
    if ($tab === 'warnings') {
        $warnings = $db->query("
            SELECT w.*, e.first_name, e.last_name, e.position, d.name as dept_name, u.full_name as issued_by_name
            FROM absence_warnings w
            JOIN employees e ON w.employee_id = e.id
            JOIN departments d ON e.department_id = d.id
            JOIN users u ON w.issued_by = u.id
            WHERE w.employee_id IN ($empIdPlaceholders)
            ORDER BY w.created_at DESC
        ")->fetchAll();
    } elseif ($tab === 'rto') {
        $rtoNotices = $db->query("
            SELECT r.*, e.first_name, e.last_name, e.position, d.name as dept_name, u.full_name as issued_by_name
            FROM report_to_office r
            JOIN employees e ON r.employee_id = e.id
            JOIN departments d ON e.department_id = d.id
            JOIN users u ON r.issued_by = u.id
            WHERE r.employee_id IN ($empIdPlaceholders)
            ORDER BY r.created_at DESC
        ")->fetchAll();
    } elseif ($tab === 'btw') {
        $btwRecords = $db->query("
            SELECT b.*, e.first_name, e.last_name, e.position, d.name as dept_name, u.full_name as evaluated_by_name
            FROM back_to_work b
            JOIN employees e ON b.employee_id = e.id
            JOIN departments d ON e.department_id = d.id
            LEFT JOIN users u ON b.evaluated_by = u.id
            WHERE b.employee_id IN ($empIdPlaceholders)
            ORDER BY b.created_at DESC
        ")->fetchAll();
    } elseif ($tab === 'suspensions') {
        $suspensions = $db->query("
            SELECT s.*, e.first_name, e.last_name, e.position, d.name as dept_name, u.full_name as issued_by_name
            FROM suspensions s
            JOIN employees e ON s.employee_id = e.id
            JOIN departments d ON e.department_id = d.id
            JOIN users u ON s.issued_by = u.id
            WHERE s.employee_id IN ($empIdPlaceholders)
            ORDER BY s.created_at DESC
        ")->fetchAll();
    }
}

// Status badge helpers
$warningLabels = ['1st_warning'=>'1st Warning','2nd_warning'=>'2nd Warning','3rd_warning'=>'3rd Warning','final_warning'=>'Final Warning'];
$warningColors = ['1st_warning'=>'yellow','2nd_warning'=>'orange','3rd_warning'=>'red','final_warning'=>'rose'];
$rtoStatusLabels = ['requested'=>'Requested','pending'=>'Pending','completed'=>'Completed','no_show'=>'No Show'];
$rtoStatusColors = ['requested'=>'yellow','pending'=>'blue','completed'=>'green','no_show'=>'red'];
$btwStatusLabels = ['pending'=>'Pending','approved'=>'Approved','failed'=>'Failed','requires_further_action'=>'Requires Further Action'];
$btwStatusColors = ['pending'=>'yellow','approved'=>'green','failed'=>'red','requires_further_action'=>'orange'];
$susStatusLabels = ['active'=>'Active','lifted'=>'Lifted','completed'=>'Completed'];
$susStatusColors = ['active'=>'red','lifted'=>'green','completed'=>'gray'];

require_once __DIR__ . '/../includes/header.php';
?>

<!-- Page Sub-Header -->
<div class="mb-5 p-5 rounded-2xl bg-gradient-to-r from-blue-600/20 via-blue-500/10 to-transparent border border-blue-500/15">
    <h2 class="text-lg font-bold text-white">Department Disciplinary & Notice Board</h2>
    <?php if ($coordDeptName): ?>
        <p class="text-xs text-gray-400 mt-1">
            Viewing records for department: <span class="text-blue-400 font-semibold"><?= htmlspecialchars($coordDeptName) ?></span>
        </p>
    <?php else: ?>
        <p class="text-xs text-amber-400 mt-1">⚠️ No department assigned — contact administrator</p>
    <?php endif; ?>
</div>

<!-- Search Bar -->
<div class="mb-5">
    <form method="GET" class="flex gap-2">
        <input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>">
        <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search by employee name..."
               class="flex-1 px-4 py-2.5 bg-dark-700/50 border border-white/10 rounded-xl text-white placeholder-gray-600 text-sm focus:outline-none focus:border-primary-500/50">
        <button type="submit" class="px-5 py-2.5 bg-primary-600 hover:bg-primary-500 text-white rounded-xl text-xs font-semibold uppercase tracking-wider transition-colors">
            Search
        </button>
        <?php if ($search): ?>
            <a href="?tab=<?= urlencode($tab) ?>" class="px-4 py-2.5 bg-white/5 hover:bg-white/10 text-gray-400 rounded-xl text-xs font-semibold uppercase tracking-wider transition-colors border border-white/10">
                Clear
            </a>
        <?php endif; ?>
    </form>
</div>

<!-- Tab Navigation -->
<div class="flex gap-1 mb-5 bg-dark-700/30 p-1 rounded-xl w-fit border border-white/5 flex-wrap">
    <?php
    $tabs = [
        'warnings'    => ['⚠️', 'Warnings'],
        'rto'         => ['📋', 'Report-to-Office'],
        'btw'         => ['✅', 'Back-to-Work'],
        'suspensions' => ['🚫', 'Suspensions'],
    ];
    foreach ($tabs as $key => $info):
        $isActive = $tab === $key;
        $qs = http_build_query(array_filter(['tab'=>$key, 'search'=>$search]));
    ?>
    <a href="?<?= $qs ?>"
       class="px-4 py-2.5 rounded-lg text-xs font-bold uppercase tracking-wider transition-all duration-200 flex items-center gap-1.5
              <?= $isActive ? 'bg-primary-600 text-white shadow-lg shadow-primary-500/20' : 'text-gray-400 hover:text-white hover:bg-white/5' ?>">
        <span><?= $info[0] ?></span>
        <span><?= $info[1] ?></span>
    </a>
    <?php endforeach; ?>
</div>

<?php if (!$coordDeptId): ?>
    <!-- No Department Warning -->
    <div class="glass-card p-8 text-center">
        <div class="text-4xl mb-3">⚠️</div>
        <h3 class="text-lg font-bold text-white mb-2">No Department Assigned</h3>
        <p class="text-sm text-gray-400">Your account doesn't have an assigned department yet. Contact your administrator to assign you to a warehouse.</p>
    </div>
<?php else: ?>

    <?php if ($tab === 'warnings'): ?>
    <!-- ==================== ABSENCE WARNINGS TAB (READ ONLY) ==================== -->
    <div class="mb-4">
        <h2 class="text-sm font-semibold text-white">Absence Warnings (<?= count($warnings) ?>)</h2>
    </div>
    <div class="glass-card">
        <div class="table-wrap">
            <table class="glass-table">
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th>Department</th>
                        <th>Warning Level</th>
                        <th>Absences</th>
                        <th>Issued By</th>
                        <th>Date</th>
                        <th>Remarks</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($warnings)): ?>
                    <tr><td colspan="7" class="text-center text-gray-600 py-8">No warnings found for your department</td></tr>
                <?php else: ?>
                    <?php foreach ($warnings as $w):
                        $color = $warningColors[$w['warning_level']] ?? 'gray';
                    ?>
                    <tr>
                        <td class="font-medium text-white whitespace-nowrap"><?= htmlspecialchars($w['last_name'] . ', ' . $w['first_name']) ?></td>
                        <td><span class="badge badge-present"><?= htmlspecialchars($w['dept_name']) ?></span></td>
                        <td><span class="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-bold bg-<?= $color ?>-500/15 text-<?= $color ?>-400 border border-<?= $color ?>-500/20"><?= $warningLabels[$w['warning_level']] ?? $w['warning_level'] ?></span></td>
                        <td class="text-gray-400 font-semibold"><?= $w['absence_count'] ?></td>
                        <td class="text-gray-400"><?= htmlspecialchars($w['issued_by_name']) ?></td>
                        <td class="text-gray-500"><?= date('M d, Y', strtotime($w['created_at'])) ?></td>
                        <td class="text-gray-500 max-w-[250px] truncate" title="<?= htmlspecialchars($w['remarks']) ?>"><?= htmlspecialchars($w['remarks'] ?: '—') ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php elseif ($tab === 'rto'): ?>
    <!-- ==================== REPORT-TO-OFFICE TAB ==================== -->
    <div class="flex items-center justify-between mb-4">
        <h2 class="text-sm font-semibold text-white">Report-to-Office Notices (<?= count($rtoNotices) ?>)</h2>
        <button onclick="document.getElementById('rtoRequestModal').classList.add('show')" class="px-4 py-2.5 bg-blue-600 hover:bg-blue-500 text-white rounded-xl text-xs font-semibold uppercase tracking-wider transition-colors">
            + Request RTO Notice
        </button>
    </div>
    <div class="glass-card">
        <div class="table-wrap">
            <table class="glass-table">
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th>Department</th>
                        <th>Report Date</th>
                        <th>Reason</th>
                        <th>Status</th>
                        <th>Issued By</th>
                        <th>Remarks</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($rtoNotices)): ?>
                    <tr><td colspan="7" class="text-center text-gray-600 py-8">No RTO notices found for your department</td></tr>
                <?php else: ?>
                    <?php foreach ($rtoNotices as $r):
                        $sColor = $rtoStatusColors[$r['status']] ?? 'gray';
                    ?>
                    <tr>
                        <td class="font-medium text-white whitespace-nowrap"><?= htmlspecialchars($r['last_name'] . ', ' . $r['first_name']) ?></td>
                        <td><span class="badge badge-present"><?= htmlspecialchars($r['dept_name']) ?></span></td>
                        <td class="text-gray-300 font-semibold"><?= date('M d, Y', strtotime($r['report_date'])) ?></td>
                        <td class="text-gray-400 max-w-[200px] truncate" title="<?= htmlspecialchars($r['reason']) ?>"><?= htmlspecialchars($r['reason']) ?></td>
                        <td><span class="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-bold bg-<?= $sColor ?>-500/15 text-<?= $sColor ?>-400 border border-<?= $sColor ?>-500/20"><?= $rtoStatusLabels[$r['status']] ?? $r['status'] ?></span></td>
                        <td class="text-gray-400"><?= htmlspecialchars($r['issued_by_name']) ?></td>
                        <td class="text-gray-500 max-w-[200px] truncate" title="<?= htmlspecialchars($r['remarks']) ?>"><?= htmlspecialchars($r['remarks'] ?: '—') ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php elseif ($tab === 'btw'): ?>
    <!-- ==================== BACK-TO-WORK TAB (READ ONLY) ==================== -->
    <div class="mb-4">
        <h2 class="text-sm font-semibold text-white">Back-to-Work Evaluations (<?= count($btwRecords) ?>)</h2>
    </div>
    <div class="glass-card">
        <div class="table-wrap">
            <table class="glass-table">
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th>Department</th>
                        <th>Absence Date</th>
                        <th>Evaluation Date</th>
                        <th>Result</th>
                        <th>Status</th>
                        <th>Evaluated By</th>
                        <th>Remarks</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($btwRecords)): ?>
                    <tr><td colspan="8" class="text-center text-gray-600 py-8">No evaluations found for your department</td></tr>
                <?php else: ?>
                    <?php foreach ($btwRecords as $b):
                        $sColor = $btwStatusColors[$b['status']] ?? 'gray';
                    ?>
                    <tr>
                        <td class="font-medium text-white whitespace-nowrap"><?= htmlspecialchars($b['last_name'] . ', ' . $b['first_name']) ?></td>
                        <td><span class="badge badge-present"><?= htmlspecialchars($b['dept_name']) ?></span></td>
                        <td class="text-amber-400 font-semibold"><?= $b['absence_date'] ? date('M d, Y', strtotime($b['absence_date'])) : '—' ?></td>
                        <td class="text-gray-300"><?= $b['evaluation_date'] ? date('M d, Y', strtotime($b['evaluation_date'])) : '—' ?></td>
                        <td class="text-gray-400 max-w-[200px] truncate" title="<?= htmlspecialchars($b['evaluation_result']) ?>"><?= htmlspecialchars($b['evaluation_result'] ?: '—') ?></td>
                        <td><span class="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-bold bg-<?= $sColor ?>-500/15 text-<?= $sColor ?>-400 border border-<?= $sColor ?>-500/20"><?= $btwStatusLabels[$b['status']] ?? $b['status'] ?></span></td>
                        <td class="text-gray-400"><?= htmlspecialchars($b['evaluated_by_name'] ?? '—') ?></td>
                        <td class="text-gray-500 max-w-[200px] truncate" title="<?= htmlspecialchars($b['remarks']) ?>"><?= htmlspecialchars($b['remarks'] ?: '—') ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php elseif ($tab === 'suspensions'): ?>
    <!-- ==================== SUSPENSIONS TAB (READ ONLY) ==================== -->
    <div class="mb-4">
        <h2 class="text-sm font-semibold text-white">Suspensions (<?= count($suspensions) ?>)</h2>
    </div>
    <div class="glass-card">
        <div class="table-wrap">
            <table class="glass-table">
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th>Department</th>
                        <th>Start Date</th>
                        <th>End Date</th>
                        <th>Reason</th>
                        <th>Status</th>
                        <th>Issued By</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($suspensions)): ?>
                    <tr><td colspan="7" class="text-center text-gray-600 py-8">No suspensions found for your department</td></tr>
                <?php else: ?>
                    <?php foreach ($suspensions as $s):
                        $sColor = $susStatusColors[$s['status']] ?? 'gray';
                    ?>
                    <tr>
                        <td class="font-medium text-white whitespace-nowrap"><?= htmlspecialchars($s['last_name'] . ', ' . $s['first_name']) ?></td>
                        <td><span class="badge badge-present"><?= htmlspecialchars($s['dept_name']) ?></span></td>
                        <td class="text-gray-300 font-semibold"><?= date('M d, Y', strtotime($s['suspension_date'])) ?></td>
                        <td class="text-gray-400"><?= $s['end_date'] ? date('M d, Y', strtotime($s['end_date'])) : '—' ?></td>
                        <td class="text-gray-400 max-w-[250px] truncate" title="<?= htmlspecialchars($s['reason']) ?>"><?= htmlspecialchars($s['reason']) ?></td>
                        <td><span class="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-bold bg-<?= $sColor ?>-500/15 text-<?= $sColor ?>-400 border border-<?= $sColor ?>-500/20"><?= $susStatusLabels[$s['status']] ?? $s['status'] ?></span></td>
                        <td class="text-gray-400"><?= htmlspecialchars($s['issued_by_name']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

<?php endif; ?>

<!-- Request RTO Modal -->
<div id="rtoRequestModal" class="modal-overlay" onclick="if(event.target===this)this.classList.remove('show')">
    <div class="modal-content">
        <div class="px-6 py-4 border-b border-white/10 flex items-center justify-between">
            <h3 class="text-base font-semibold text-white">📋 Request Report-to-Office</h3>
            <button onclick="document.getElementById('rtoRequestModal').classList.remove('show')" class="text-gray-500 hover:text-white text-xl">&times;</button>
        </div>
        <form id="rtoRequestForm" class="p-6 space-y-4">
            <div>
                <label class="block text-xs font-medium text-gray-400 mb-1.5 uppercase tracking-wider">Employee</label>
                <select name="employee_id" required class="w-full px-4 py-2.5 bg-dark-700/50 border border-white/10 rounded-xl text-white text-sm focus:outline-none focus:border-primary-500/50">
                    <option value="">Select employee</option>
                    <?php foreach ($allEmps as $emp): ?>
                    <option value="<?= $emp['id'] ?>"><?= htmlspecialchars($emp['last_name'] . ', ' . $emp['first_name']) ?> — <?= htmlspecialchars($emp['position'] ?: 'Staff') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-400 mb-1.5 uppercase tracking-wider">Target Report Date</label>
                <input type="date" name="report_date" required value="<?= date('Y-m-d') ?>" class="w-full px-4 py-2.5 bg-dark-700/50 border border-white/10 rounded-xl text-white text-sm focus:outline-none focus:border-primary-500/50">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-400 mb-1.5 uppercase tracking-wider">Reason / Issue Description</label>
                <textarea name="reason" rows="4" required class="w-full px-4 py-2.5 bg-dark-700/50 border border-white/10 rounded-xl text-white text-sm focus:outline-none focus:border-primary-500/50 resize-none" placeholder="Describe the problem or reason why this employee is required to report..."></textarea>
            </div>
            <button type="submit" class="w-full py-3 bg-gradient-to-r from-blue-500 to-blue-600 hover:from-blue-400 hover:to-blue-500 text-white font-semibold rounded-xl text-sm transition-all">
                Send Request to Admin
            </button>
        </form>
    </div>
</div>

<script>
// ============ AJAX HELPER ============
function apiPost(action, data, onSuccess) {
    const fd = new FormData();
    fd.append('action', action);
    for (const [k, v] of Object.entries(data)) fd.append(k, v);
    fetch('/ATTENDANCE/api.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (d.success) { if (onSuccess) onSuccess(d); else location.reload(); }
            else alert('Error: ' + (d.message || 'Unknown error'));
        })
        .catch(e => alert('Error: ' + e));
}

function formToObj(form) {
    const obj = {};
    new FormData(form).forEach((v, k) => obj[k] = v);
    return obj;
}

// ============ RTO REQUEST FORM ============
document.getElementById('rtoRequestForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    apiPost('coordinator_request_rto', formToObj(this), (d) => {
        alert('Your request has been submitted to the Admin.');
        location.reload();
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
