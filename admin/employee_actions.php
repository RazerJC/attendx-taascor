<?php
/**
 * Admin — Employee Actions (Disciplinary Management)
 * 4-tab view: Absence Warnings | Report-to-Office | Back-to-Work | Suspensions
 * Full CRUD for HR/Admin role.
 */
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();
$db = getDB();
$pageTitle = 'Employee Actions';

$tab = $_GET['tab'] ?? 'warnings';
$deptFilter = $_GET['dept'] ?? '';
$search = $_GET['search'] ?? '';

// Get all departments and employees for dropdowns
$departments = $db->query("SELECT * FROM departments ORDER BY name")->fetchAll();
$employees = $db->query("
    SELECT e.id, e.first_name, e.last_name, e.position, e.department_id, d.name as dept_name
    FROM employees e
    JOIN departments d ON e.department_id = d.id
    WHERE e.status = 'active'
    ORDER BY e.last_name, e.first_name
")->fetchAll();

// Apply filters to employees list
$filteredEmpIds = [];
foreach ($employees as $emp) {
    $match = true;
    if ($deptFilter && $emp['department_id'] != $deptFilter) $match = false;
    if ($search) {
        $s = strtolower($search);
        if (strpos(strtolower($emp['first_name']), $s) === false && strpos(strtolower($emp['last_name']), $s) === false) $match = false;
    }
    if ($match) $filteredEmpIds[] = $emp['id'];
}
$empIdPlaceholders = count($filteredEmpIds) > 0 ? implode(',', $filteredEmpIds) : '0';

// Fetch data based on active tab
$warnings = $rtoNotices = $btwRecords = $suspensions = [];

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

<!-- Filters -->
<div class="flex flex-col sm:flex-row gap-3 mb-5">
    <form method="GET" class="flex flex-col sm:flex-row gap-2 flex-1">
        <input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>">
        <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search employees..."
               class="flex-1 px-4 py-2.5 bg-dark-700/50 border border-white/10 rounded-xl text-white placeholder-gray-600 text-sm focus:outline-none focus:border-primary-500/50">
        <select name="dept" class="px-4 py-2.5 bg-dark-700/50 border border-white/10 rounded-xl text-white text-sm focus:outline-none focus:border-primary-500/50 min-w-[140px]">
            <option value="">All Departments</option>
            <?php foreach ($departments as $d): ?>
            <option value="<?= $d['id'] ?>" <?= $deptFilter == $d['id'] ? 'selected' : '' ?>><?= htmlspecialchars($d['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="px-5 py-2.5 bg-primary-600 hover:bg-primary-500 text-white rounded-xl text-xs font-semibold uppercase tracking-wider transition-colors">
            Filter
        </button>
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
        $qs = http_build_query(array_filter(['tab'=>$key, 'search'=>$search, 'dept'=>$deptFilter]));
    ?>
    <a href="?<?= $qs ?>"
       class="px-4 py-2.5 rounded-lg text-xs font-bold uppercase tracking-wider transition-all duration-200 flex items-center gap-1.5
              <?= $isActive ? 'bg-primary-600 text-white shadow-lg shadow-primary-500/20' : 'text-gray-400 hover:text-white hover:bg-white/5' ?>">
        <span><?= $info[0] ?></span>
        <span><?= $info[1] ?></span>
    </a>
    <?php endforeach; ?>
</div>

<?php if ($tab === 'warnings'): ?>
<!-- ==================== ABSENCE WARNINGS TAB ==================== -->
<div class="flex items-center justify-between mb-4">
    <h2 class="text-sm font-semibold text-white">Absence Warnings (<?= count($warnings) ?>)</h2>
    <button onclick="document.getElementById('warningModal').classList.add('show')" class="px-4 py-2.5 bg-yellow-600 hover:bg-yellow-500 text-white rounded-xl text-xs font-semibold uppercase tracking-wider transition-colors">
        + Issue Warning
    </button>
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
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($warnings)): ?>
                <tr><td colspan="8" class="text-center text-gray-600 py-8">No warnings found</td></tr>
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
                    <td class="text-gray-500 max-w-[200px] truncate" title="<?= htmlspecialchars($w['remarks']) ?>"><?= htmlspecialchars($w['remarks'] ?: '—') ?></td>
                    <td>
                        <button onclick="deleteWarning(<?= $w['id'] ?>)" class="text-xs text-red-400 hover:text-red-300">🗑️ Delete</button>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Issue Warning Modal -->
<div id="warningModal" class="modal-overlay" onclick="if(event.target===this)this.classList.remove('show')">
    <div class="modal-content">
        <div class="px-6 py-4 border-b border-white/10 flex items-center justify-between">
            <h3 class="text-base font-semibold text-white">⚠️ Issue Absence Warning</h3>
            <button onclick="document.getElementById('warningModal').classList.remove('show')" class="text-gray-500 hover:text-white text-xl">&times;</button>
        </div>
        <form id="warningForm" class="p-6 space-y-4">
            <div>
                <label class="block text-xs font-medium text-gray-400 mb-1.5 uppercase tracking-wider">Employee</label>
                <select name="employee_id" required class="w-full px-4 py-2.5 bg-dark-700/50 border border-white/10 rounded-xl text-white text-sm focus:outline-none focus:border-primary-500/50">
                    <option value="">Select employee</option>
                    <?php foreach ($employees as $emp): ?>
                    <option value="<?= $emp['id'] ?>"><?= htmlspecialchars($emp['last_name'] . ', ' . $emp['first_name']) ?> — <?= htmlspecialchars($emp['dept_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-400 mb-1.5 uppercase tracking-wider">Warning Level</label>
                    <select name="warning_level" required class="w-full px-4 py-2.5 bg-dark-700/50 border border-white/10 rounded-xl text-white text-sm focus:outline-none focus:border-primary-500/50">
                        <option value="1st_warning">1st Warning</option>
                        <option value="2nd_warning">2nd Warning</option>
                        <option value="3rd_warning">3rd Warning</option>
                        <option value="final_warning">Final Warning</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-400 mb-1.5 uppercase tracking-wider">Absence Count</label>
                    <input type="number" name="absence_count" min="0" value="0" class="w-full px-4 py-2.5 bg-dark-700/50 border border-white/10 rounded-xl text-white text-sm focus:outline-none focus:border-primary-500/50">
                </div>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-400 mb-1.5 uppercase tracking-wider">Remarks</label>
                <textarea name="remarks" rows="3" class="w-full px-4 py-2.5 bg-dark-700/50 border border-white/10 rounded-xl text-white text-sm focus:outline-none focus:border-primary-500/50 resize-none" placeholder="Optional remarks..."></textarea>
            </div>
            <button type="submit" class="w-full py-3 bg-gradient-to-r from-yellow-500 to-yellow-600 hover:from-yellow-400 hover:to-yellow-500 text-white font-semibold rounded-xl text-sm transition-all">
                Issue Warning
            </button>
        </form>
    </div>
</div>

<?php elseif ($tab === 'rto'): ?>
<!-- ==================== REPORT-TO-OFFICE TAB ==================== -->
<div class="flex items-center justify-between mb-4">
    <h2 class="text-sm font-semibold text-white">Report-to-Office Notices (<?= count($rtoNotices) ?>)</h2>
    <button onclick="document.getElementById('rtoModal').classList.add('show')" class="px-4 py-2.5 bg-blue-600 hover:bg-blue-500 text-white rounded-xl text-xs font-semibold uppercase tracking-wider transition-colors">
        + Create RTO Notice
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
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($rtoNotices)): ?>
                <tr><td colspan="8" class="text-center text-gray-600 py-8">No RTO notices found</td></tr>
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
                    <td class="text-gray-500 max-w-[150px] truncate" title="<?= htmlspecialchars($r['remarks']) ?>"><?= htmlspecialchars($r['remarks'] ?: '—') ?></td>
                    <td>
                        <?php if ($r['status'] === 'requested'): ?>
                            <button onclick="approveRtoRequest(<?= $r['id'] ?>)" class="text-xs text-green-400 hover:text-green-300 mr-3">✅ Approve</button>
                            <button onclick="rejectRtoRequest(<?= $r['id'] ?>)" class="text-xs text-red-400 hover:text-red-300">❌ Reject</button>
                        <?php else: ?>
                            <button onclick="openEditRto(<?= htmlspecialchars(json_encode($r)) ?>)" class="text-xs text-primary-400 hover:text-primary-300 mr-2">✏️ Edit</button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Create RTO Modal -->
<div id="rtoModal" class="modal-overlay" onclick="if(event.target===this)this.classList.remove('show')">
    <div class="modal-content">
        <div class="px-6 py-4 border-b border-white/10 flex items-center justify-between">
            <h3 class="text-base font-semibold text-white">📋 Create Report-to-Office Notice</h3>
            <button onclick="document.getElementById('rtoModal').classList.remove('show')" class="text-gray-500 hover:text-white text-xl">&times;</button>
        </div>
        <form id="rtoForm" class="p-6 space-y-4">
            <div>
                <label class="block text-xs font-medium text-gray-400 mb-1.5 uppercase tracking-wider">Employee</label>
                <select name="employee_id" required class="w-full px-4 py-2.5 bg-dark-700/50 border border-white/10 rounded-xl text-white text-sm focus:outline-none focus:border-primary-500/50">
                    <option value="">Select employee</option>
                    <?php foreach ($employees as $emp): ?>
                    <option value="<?= $emp['id'] ?>"><?= htmlspecialchars($emp['last_name'] . ', ' . $emp['first_name']) ?> — <?= htmlspecialchars($emp['dept_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-400 mb-1.5 uppercase tracking-wider">Report Date</label>
                <input type="date" name="report_date" required value="<?= date('Y-m-d') ?>" class="w-full px-4 py-2.5 bg-dark-700/50 border border-white/10 rounded-xl text-white text-sm focus:outline-none focus:border-primary-500/50">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-400 mb-1.5 uppercase tracking-wider">Reason</label>
                <textarea name="reason" rows="3" required class="w-full px-4 py-2.5 bg-dark-700/50 border border-white/10 rounded-xl text-white text-sm focus:outline-none focus:border-primary-500/50 resize-none" placeholder="Reason for reporting to office..."></textarea>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-400 mb-1.5 uppercase tracking-wider">Remarks</label>
                <textarea name="remarks" rows="2" class="w-full px-4 py-2.5 bg-dark-700/50 border border-white/10 rounded-xl text-white text-sm focus:outline-none focus:border-primary-500/50 resize-none" placeholder="Optional remarks..."></textarea>
            </div>
            <button type="submit" class="w-full py-3 bg-gradient-to-r from-blue-500 to-blue-600 hover:from-blue-400 hover:to-blue-500 text-white font-semibold rounded-xl text-sm transition-all">
                Create Notice
            </button>
        </form>
    </div>
</div>

<!-- Edit RTO Modal -->
<div id="editRtoModal" class="modal-overlay" onclick="if(event.target===this)this.classList.remove('show')">
    <div class="modal-content">
        <div class="px-6 py-4 border-b border-white/10 flex items-center justify-between">
            <h3 class="text-base font-semibold text-white">✏️ Update Report-to-Office</h3>
            <button onclick="document.getElementById('editRtoModal').classList.remove('show')" class="text-gray-500 hover:text-white text-xl">&times;</button>
        </div>
        <form id="editRtoForm" class="p-6 space-y-4">
            <input type="hidden" name="id" id="editRtoId">
            <div>
                <label class="block text-xs font-medium text-gray-400 mb-1.5 uppercase tracking-wider">Status</label>
                <select name="status" id="editRtoStatus" required class="w-full px-4 py-2.5 bg-dark-700/50 border border-white/10 rounded-xl text-white text-sm focus:outline-none focus:border-primary-500/50">
                    <option value="pending">Pending</option>
                    <option value="completed">Completed</option>
                    <option value="no_show">No Show</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-400 mb-1.5 uppercase tracking-wider">Remarks</label>
                <textarea name="remarks" id="editRtoRemarks" rows="3" class="w-full px-4 py-2.5 bg-dark-700/50 border border-white/10 rounded-xl text-white text-sm focus:outline-none focus:border-primary-500/50 resize-none"></textarea>
            </div>
            <button type="submit" class="w-full py-3 bg-gradient-to-r from-blue-500 to-blue-600 hover:from-blue-400 hover:to-blue-500 text-white font-semibold rounded-xl text-sm transition-all">
                Update Notice
            </button>
        </form>
    </div>
</div>

<?php elseif ($tab === 'btw'): ?>
<!-- ==================== BACK-TO-WORK TAB ==================== -->
<div class="flex items-center justify-between mb-4">
    <h2 class="text-sm font-semibold text-white">Back-to-Work Evaluations (<?= count($btwRecords) ?>)</h2>
    <button onclick="document.getElementById('btwModal').classList.add('show')" class="px-4 py-2.5 bg-green-600 hover:bg-green-500 text-white rounded-xl text-xs font-semibold uppercase tracking-wider transition-colors">
        + Record Evaluation
    </button>
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
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($btwRecords)): ?>
                <tr><td colspan="9" class="text-center text-gray-600 py-8">No evaluations found</td></tr>
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
                    <td class="text-gray-500 max-w-[150px] truncate" title="<?= htmlspecialchars($b['remarks']) ?>"><?= htmlspecialchars($b['remarks'] ?: '—') ?></td>
                    <td>
                        <?php if ($b['status'] === 'pending'): ?>
                            <button onclick="openEditBtw(<?= htmlspecialchars(json_encode($b)) ?>)" class="inline-flex items-center px-2.5 py-1.5 bg-blue-600 hover:bg-blue-500 text-white rounded-lg text-xs font-bold tracking-wider uppercase transition-all shadow-md shadow-blue-500/10">📋 Process</button>
                        <?php else: ?>
                            <button onclick="openEditBtw(<?= htmlspecialchars(json_encode($b)) ?>)" class="text-xs text-primary-400 hover:text-primary-300">✏️ Edit</button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Create BTW Modal -->
<div id="btwModal" class="modal-overlay" onclick="if(event.target===this)this.classList.remove('show')">
    <div class="modal-content">
        <div class="px-6 py-4 border-b border-white/10 flex items-center justify-between">
            <h3 class="text-base font-semibold text-white">✅ Record Back-to-Work Evaluation</h3>
            <button onclick="document.getElementById('btwModal').classList.remove('show')" class="text-gray-500 hover:text-white text-xl">&times;</button>
        </div>
        <form id="btwForm" class="p-6 space-y-4">
            <div>
                <label class="block text-xs font-medium text-gray-400 mb-1.5 uppercase tracking-wider">Employee</label>
                <select name="employee_id" required class="w-full px-4 py-2.5 bg-dark-700/50 border border-white/10 rounded-xl text-white text-sm focus:outline-none focus:border-primary-500/50">
                    <option value="">Select employee</option>
                    <?php foreach ($employees as $emp): ?>
                    <option value="<?= $emp['id'] ?>"><?= htmlspecialchars($emp['last_name'] . ', ' . $emp['first_name']) ?> — <?= htmlspecialchars($emp['dept_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-400 mb-1.5 uppercase tracking-wider">Evaluation Date</label>
                <input type="date" name="evaluation_date" value="<?= date('Y-m-d') ?>" class="w-full px-4 py-2.5 bg-dark-700/50 border border-white/10 rounded-xl text-white text-sm focus:outline-none focus:border-primary-500/50">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-400 mb-1.5 uppercase tracking-wider">Status</label>
                <select name="status" required class="w-full px-4 py-2.5 bg-dark-700/50 border border-white/10 rounded-xl text-white text-sm focus:outline-none focus:border-primary-500/50">
                    <option value="pending">Pending</option>
                    <option value="approved">Approved</option>
                    <option value="failed">Failed</option>
                    <option value="requires_further_action">Requires Further Action</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-400 mb-1.5 uppercase tracking-wider">Evaluation Result</label>
                <textarea name="evaluation_result" rows="3" class="w-full px-4 py-2.5 bg-dark-700/50 border border-white/10 rounded-xl text-white text-sm focus:outline-none focus:border-primary-500/50 resize-none" placeholder="Describe the evaluation outcome..."></textarea>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-400 mb-1.5 uppercase tracking-wider">Remarks</label>
                <textarea name="remarks" rows="2" class="w-full px-4 py-2.5 bg-dark-700/50 border border-white/10 rounded-xl text-white text-sm focus:outline-none focus:border-primary-500/50 resize-none" placeholder="Issues discussed, notes..."></textarea>
            </div>
            <button type="submit" class="w-full py-3 bg-gradient-to-r from-green-500 to-green-600 hover:from-green-400 hover:to-green-500 text-white font-semibold rounded-xl text-sm transition-all">
                Record Evaluation
            </button>
        </form>
    </div>
</div>

<!-- Edit BTW Modal -->
<div id="editBtwModal" class="modal-overlay" onclick="if(event.target===this)this.classList.remove('show')">
    <div class="modal-content">
        <div class="px-6 py-4 border-b border-white/10 flex items-center justify-between">
            <h3 class="text-base font-semibold text-white">✏️ Update Back-to-Work Evaluation</h3>
            <button onclick="document.getElementById('editBtwModal').classList.remove('show')" class="text-gray-500 hover:text-white text-xl">&times;</button>
        </div>
        <form id="editBtwForm" class="p-6 space-y-4">
            <input type="hidden" name="id" id="editBtwId">
            <div>
                <label class="block text-xs font-medium text-gray-400 mb-1.5 uppercase tracking-wider">Status</label>
                <select name="status" id="editBtwStatus" required class="w-full px-4 py-2.5 bg-dark-700/50 border border-white/10 rounded-xl text-white text-sm focus:outline-none focus:border-primary-500/50">
                    <option value="pending">Pending</option>
                    <option value="approved">Approved</option>
                    <option value="failed">Failed</option>
                    <option value="requires_further_action">Requires Further Action</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-400 mb-1.5 uppercase tracking-wider">Evaluation Result</label>
                <textarea name="evaluation_result" id="editBtwResult" rows="3" class="w-full px-4 py-2.5 bg-dark-700/50 border border-white/10 rounded-xl text-white text-sm focus:outline-none focus:border-primary-500/50 resize-none"></textarea>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-400 mb-1.5 uppercase tracking-wider">Remarks</label>
                <textarea name="remarks" id="editBtwRemarks" rows="2" class="w-full px-4 py-2.5 bg-dark-700/50 border border-white/10 rounded-xl text-white text-sm focus:outline-none focus:border-primary-500/50 resize-none"></textarea>
            </div>
            <button type="submit" class="w-full py-3 bg-gradient-to-r from-green-500 to-green-600 hover:from-green-400 hover:to-green-500 text-white font-semibold rounded-xl text-sm transition-all">
                Update Evaluation
            </button>
        </form>
    </div>
</div>

<?php elseif ($tab === 'suspensions'): ?>
<!-- ==================== SUSPENSIONS TAB ==================== -->
<div class="flex items-center justify-between mb-4">
    <h2 class="text-sm font-semibold text-white">Suspensions (<?= count($suspensions) ?>)</h2>
    <button onclick="document.getElementById('suspendModal').classList.add('show')" class="px-4 py-2.5 bg-red-600 hover:bg-red-500 text-white rounded-xl text-xs font-semibold uppercase tracking-wider transition-colors">
        + Suspend Employee
    </button>
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
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($suspensions)): ?>
                <tr><td colspan="8" class="text-center text-gray-600 py-8">No suspensions found</td></tr>
            <?php else: ?>
                <?php foreach ($suspensions as $s):
                    $sColor = $susStatusColors[$s['status']] ?? 'gray';
                ?>
                <tr>
                    <td class="font-medium text-white whitespace-nowrap"><?= htmlspecialchars($s['last_name'] . ', ' . $s['first_name']) ?></td>
                    <td><span class="badge badge-present"><?= htmlspecialchars($s['dept_name']) ?></span></td>
                    <td class="text-gray-300 font-semibold"><?= date('M d, Y', strtotime($s['suspension_date'])) ?></td>
                    <td class="text-gray-400"><?= $s['end_date'] ? date('M d, Y', strtotime($s['end_date'])) : '—' ?></td>
                    <td class="text-gray-400 max-w-[200px] truncate" title="<?= htmlspecialchars($s['reason']) ?>"><?= htmlspecialchars($s['reason']) ?></td>
                    <td><span class="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-bold bg-<?= $sColor ?>-500/15 text-<?= $sColor ?>-400 border border-<?= $sColor ?>-500/20"><?= $susStatusLabels[$s['status']] ?? $s['status'] ?></span></td>
                    <td class="text-gray-400"><?= htmlspecialchars($s['issued_by_name']) ?></td>
                    <td class="whitespace-nowrap">
                        <button onclick="openEditSuspension(<?= htmlspecialchars(json_encode($s)) ?>)" class="text-xs text-primary-400 hover:text-primary-300 mr-2">✏️ Edit</button>
                        <?php if ($s['status'] === 'active'): ?>
                        <button onclick="liftSuspension(<?= $s['id'] ?>)" class="text-xs text-green-400 hover:text-green-300">✅ Lift</button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Suspend Employee Modal -->
<div id="suspendModal" class="modal-overlay" onclick="if(event.target===this)this.classList.remove('show')">
    <div class="modal-content">
        <div class="px-6 py-4 border-b border-white/10 flex items-center justify-between">
            <h3 class="text-base font-semibold text-white">🚫 Suspend Employee</h3>
            <button onclick="document.getElementById('suspendModal').classList.remove('show')" class="text-gray-500 hover:text-white text-xl">&times;</button>
        </div>
        <form id="suspendForm" class="p-6 space-y-4">
            <div>
                <label class="block text-xs font-medium text-gray-400 mb-1.5 uppercase tracking-wider">Employee</label>
                <select name="employee_id" required class="w-full px-4 py-2.5 bg-dark-700/50 border border-white/10 rounded-xl text-white text-sm focus:outline-none focus:border-primary-500/50">
                    <option value="">Select employee</option>
                    <?php foreach ($employees as $emp): ?>
                    <option value="<?= $emp['id'] ?>"><?= htmlspecialchars($emp['last_name'] . ', ' . $emp['first_name']) ?> — <?= htmlspecialchars($emp['dept_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-400 mb-1.5 uppercase tracking-wider">Start Date</label>
                    <input type="date" name="suspension_date" required value="<?= date('Y-m-d') ?>" class="w-full px-4 py-2.5 bg-dark-700/50 border border-white/10 rounded-xl text-white text-sm focus:outline-none focus:border-primary-500/50">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-400 mb-1.5 uppercase tracking-wider">End Date (Optional)</label>
                    <input type="date" name="end_date" class="w-full px-4 py-2.5 bg-dark-700/50 border border-white/10 rounded-xl text-white text-sm focus:outline-none focus:border-primary-500/50">
                </div>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-400 mb-1.5 uppercase tracking-wider">Reason</label>
                <textarea name="reason" rows="3" required class="w-full px-4 py-2.5 bg-dark-700/50 border border-white/10 rounded-xl text-white text-sm focus:outline-none focus:border-primary-500/50 resize-none" placeholder="Reason for suspension..."></textarea>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-400 mb-1.5 uppercase tracking-wider">Remarks</label>
                <textarea name="remarks" rows="2" class="w-full px-4 py-2.5 bg-dark-700/50 border border-white/10 rounded-xl text-white text-sm focus:outline-none focus:border-primary-500/50 resize-none" placeholder="Optional remarks..."></textarea>
            </div>
            <button type="submit" class="w-full py-3 bg-gradient-to-r from-red-500 to-red-600 hover:from-red-400 hover:to-red-500 text-white font-semibold rounded-xl text-sm transition-all">
                Suspend Employee
            </button>
        </form>
    </div>
</div>

<!-- Edit Suspension Modal -->
<div id="editSuspensionModal" class="modal-overlay" onclick="if(event.target===this)this.classList.remove('show')">
    <div class="modal-content">
        <div class="px-6 py-4 border-b border-white/10 flex items-center justify-between">
            <h3 class="text-base font-semibold text-white">✏️ Update Suspension</h3>
            <button onclick="document.getElementById('editSuspensionModal').classList.remove('show')" class="text-gray-500 hover:text-white text-xl">&times;</button>
        </div>
        <form id="editSuspensionForm" class="p-6 space-y-4">
            <input type="hidden" name="id" id="editSusId">
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-400 mb-1.5 uppercase tracking-wider">Start Date</label>
                    <input type="date" name="suspension_date" id="editSusStartDate" required class="w-full px-4 py-2.5 bg-dark-700/50 border border-white/10 rounded-xl text-white text-sm focus:outline-none focus:border-primary-500/50">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-400 mb-1.5 uppercase tracking-wider">End Date</label>
                    <input type="date" name="end_date" id="editSusEndDate" class="w-full px-4 py-2.5 bg-dark-700/50 border border-white/10 rounded-xl text-white text-sm focus:outline-none focus:border-primary-500/50">
                </div>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-400 mb-1.5 uppercase tracking-wider">Reason</label>
                <textarea name="reason" id="editSusReason" rows="3" required class="w-full px-4 py-2.5 bg-dark-700/50 border border-white/10 rounded-xl text-white text-sm focus:outline-none focus:border-primary-500/50 resize-none"></textarea>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-400 mb-1.5 uppercase tracking-wider">Remarks</label>
                <textarea name="remarks" id="editSusRemarks" rows="2" class="w-full px-4 py-2.5 bg-dark-700/50 border border-white/10 rounded-xl text-white text-sm focus:outline-none focus:border-primary-500/50 resize-none"></textarea>
            </div>
            <button type="submit" class="w-full py-3 bg-gradient-to-r from-red-500 to-red-600 hover:from-red-400 hover:to-red-500 text-white font-semibold rounded-xl text-sm transition-all">
                Update Suspension
            </button>
        </form>
    </div>
</div>
<?php endif; ?>

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

// ============ WARNINGS ============
document.getElementById('warningForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    apiPost('create_warning', formToObj(this), () => location.reload());
});

function deleteWarning(id) {
    if (confirm('Delete this warning?')) apiPost('delete_warning', { id }, () => location.reload());
}

// ============ REPORT-TO-OFFICE ============
document.getElementById('rtoForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    apiPost('create_report_to_office', formToObj(this), () => location.reload());
});

function openEditRto(r) {
    document.getElementById('editRtoId').value = r.id;
    document.getElementById('editRtoStatus').value = r.status;
    document.getElementById('editRtoRemarks').value = r.remarks || '';
    document.getElementById('editRtoModal').classList.add('show');
}

document.getElementById('editRtoForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    apiPost('update_report_to_office', formToObj(this), () => location.reload());
});

function approveRtoRequest(id) {
    if (confirm('Are you sure you want to approve this Report-to-Office request?')) {
        apiPost('approve_rto_request', { id }, () => location.reload());
    }
}

function rejectRtoRequest(id) {
    if (confirm('Are you sure you want to reject this Report-to-Office request?')) {
        apiPost('reject_rto_request', { id }, () => location.reload());
    }
}

// ============ BACK-TO-WORK ============
document.getElementById('btwForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    apiPost('create_back_to_work', formToObj(this), () => location.reload());
});

function openEditBtw(b) {
    document.getElementById('editBtwId').value = b.id;
    document.getElementById('editBtwStatus').value = b.status;
    document.getElementById('editBtwResult').value = b.evaluation_result || '';
    document.getElementById('editBtwRemarks').value = b.remarks || '';
    
    const titleEl = document.querySelector('#editBtwModal h3');
    const submitBtn = document.querySelector('#editBtwForm button[type="submit"]');
    if (titleEl && submitBtn) {
        if (b.status === 'pending') {
            titleEl.innerHTML = '📋 Process Back-to-Work Evaluation';
            submitBtn.innerHTML = 'Process & Approve';
            submitBtn.className = 'w-full py-3 bg-gradient-to-r from-blue-500 to-blue-600 hover:from-blue-400 hover:to-blue-500 text-white font-semibold rounded-xl text-sm transition-all';
        } else {
            titleEl.innerHTML = '✏️ Update Back-to-Work Evaluation';
            submitBtn.innerHTML = 'Update Evaluation';
            submitBtn.className = 'w-full py-3 bg-gradient-to-r from-green-500 to-green-600 hover:from-green-400 hover:to-green-500 text-white font-semibold rounded-xl text-sm transition-all';
        }
    }
    
    document.getElementById('editBtwModal').classList.add('show');
}

document.getElementById('editBtwForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    apiPost('update_back_to_work', formToObj(this), () => location.reload());
});

// ============ SUSPENSIONS ============
document.getElementById('suspendForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    apiPost('create_suspension', formToObj(this), () => location.reload());
});

function openEditSuspension(s) {
    document.getElementById('editSusId').value = s.id;
    document.getElementById('editSusStartDate').value = s.suspension_date;
    document.getElementById('editSusEndDate').value = s.end_date || '';
    document.getElementById('editSusReason').value = s.reason || '';
    document.getElementById('editSusRemarks').value = s.remarks || '';
    document.getElementById('editSuspensionModal').classList.add('show');
}

document.getElementById('editSuspensionForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    apiPost('update_suspension', formToObj(this), () => location.reload());
});

function liftSuspension(id) {
    if (confirm('Lift this suspension?')) {
        const remarks = prompt('Remarks (optional):') || '';
        apiPost('lift_suspension', { id, remarks }, () => location.reload());
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
