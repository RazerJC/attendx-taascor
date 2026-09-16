<?php
/**
 * Admin — Employee Management
 * Two-tab view: By Department / By Coordinator
 * Shows which coordinator handles each employee based on department assignment.
 */
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();
$db = getDB();
$pageTitle = 'Employee Management';

// Filters
$deptFilter = $_GET['dept'] ?? '';
$search     = $_GET['search'] ?? '';
$tab        = $_GET['tab'] ?? 'department'; // 'department' or 'coordinator'

// Departments for filter
$departments = $db->query("SELECT * FROM departments ORDER BY name")->fetchAll();

// Get all coordinators with their department assignments
$coordinators = $db->query("
    SELECT u.id, u.full_name, u.username, u.department_id, d.name as dept_name
    FROM users u
    LEFT JOIN departments d ON u.department_id = d.id
    WHERE u.role = 'coordinator' AND u.status = 'active'
    ORDER BY u.full_name ASC
")->fetchAll();

// Build a map: department_id => coordinator info
$deptCoordinatorMap = [];
foreach ($coordinators as $c) {
    if ($c['department_id']) {
        $deptCoordinatorMap[$c['department_id']] = $c;
    }
}

// Employees query — include coordinator info via department
$sql = "SELECT e.*, d.name as dept_name
        FROM employees e
        JOIN departments d ON e.department_id = d.id
        WHERE e.status = 'active'";
$params = [];

if ($deptFilter) {
    $sql .= " AND e.department_id = ?";
    $params[] = $deptFilter;
}
if ($search) {
    $sql .= " AND (e.first_name LIKE ? OR e.last_name LIKE ? OR e.position LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$sql .= " ORDER BY d.name, e.last_name, e.first_name";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$employees = $stmt->fetchAll();

// Group employees by coordinator for the coordinator tab
$empsByCoordinator = [];
$unassignedEmps = [];
foreach ($employees as $emp) {
    $coordInfo = $deptCoordinatorMap[$emp['department_id']] ?? null;
    if ($coordInfo) {
        $coordId = $coordInfo['id'];
        if (!isset($empsByCoordinator[$coordId])) {
            $empsByCoordinator[$coordId] = [
                'coordinator' => $coordInfo,
                'employees' => []
            ];
        }
        $empsByCoordinator[$coordId]['employees'][] = $emp;
    } else {
        $unassignedEmps[] = $emp;
    }
}

// Group employees by department for the department tab
$empsByDept = [];
foreach ($employees as $emp) {
    $deptId = $emp['department_id'];
    if (!isset($empsByDept[$deptId])) {
        $empsByDept[$deptId] = [
            'id' => $deptId,
            'name' => $emp['dept_name'],
            'employees' => []
        ];
    }
    $empsByDept[$deptId]['employees'][] = $emp;
}

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
    <button onclick="document.getElementById('addEmployeeModal').classList.add('show')" class="px-5 py-2.5 bg-primary-600 hover:bg-primary-500 text-white rounded-xl text-xs font-semibold uppercase tracking-wider transition-colors whitespace-nowrap">
        + Add Employee
    </button>
</div>

<!-- Tab Navigation -->
<div class="flex gap-1 mb-5 bg-dark-700/30 p-1 rounded-xl w-fit border border-white/5">
    <a href="?tab=department<?= $search ? '&search=' . urlencode($search) : '' ?><?= $deptFilter ? '&dept=' . urlencode($deptFilter) : '' ?>"
       class="px-5 py-2.5 rounded-lg text-xs font-bold uppercase tracking-wider transition-all duration-200 flex items-center gap-2
              <?= $tab === 'department' ? 'bg-primary-600 text-white shadow-lg shadow-primary-500/20' : 'text-gray-400 hover:text-white hover:bg-white/5' ?>">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
        By Department
    </a>
    <a href="?tab=coordinator<?= $search ? '&search=' . urlencode($search) : '' ?><?= $deptFilter ? '&dept=' . urlencode($deptFilter) : '' ?>"
       class="px-5 py-2.5 rounded-lg text-xs font-bold uppercase tracking-wider transition-all duration-200 flex items-center gap-2
              <?= $tab === 'coordinator' ? 'bg-primary-600 text-white shadow-lg shadow-primary-500/20' : 'text-gray-400 hover:text-white hover:bg-white/5' ?>">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0-.001h6v-1a6 6 0 00-9-5.197M13 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
        By Coordinator
    </a>
</div>

<?php if ($tab === 'department'): ?>
<!-- ===================== TAB: BY DEPARTMENT ===================== -->
<div class="space-y-4">
    <?php if (empty($empsByDept)): ?>
        <div class="glass-card p-8 text-center">
            <p class="text-gray-500">No employees found matching your filters.</p>
        </div>
    <?php endif; ?>

    <?php foreach ($empsByDept as $deptId => $group):
        $deptName = $group['name'];
        $deptEmps = $group['employees'];
        $empCount = count($deptEmps);
        $coordInfo = $deptCoordinatorMap[$deptId] ?? null;
    ?>
    <div class="glass-card department-card" data-dept-id="<?= $deptId ?>">
        <!-- Department Header -->
        <div class="glass-card-header cursor-pointer select-none hover:bg-white/[0.02] transition-colors" onclick="toggleDeptFolder(<?= $deptId ?>)">
            <div class="flex items-center gap-3">
                <svg class="w-4 h-4 text-gray-500 transition-transform duration-200" id="dept_arrow_<?= $deptId ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-primary-500 to-primary-700 flex items-center justify-center text-white font-bold text-sm shadow-lg shadow-primary-500/20 flex-shrink-0">
                    🏢
                </div>
                <div>
                    <div class="text-sm font-bold text-white"><?= htmlspecialchars($deptName) ?></div>
                    <div class="text-[10px] text-gray-500 flex items-center gap-2">
                        <?php if ($coordInfo): ?>
                            <span class="text-blue-400 font-medium">Coordinator: <?= htmlspecialchars($coordInfo['full_name']) ?></span>
                        <?php else: ?>
                            <span class="text-gray-600 italic">No coordinator assigned</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <span class="text-xs text-gray-400 bg-white/5 px-3 py-1 rounded-full font-semibold">
                    👥 <?= $empCount ?> employee<?= $empCount !== 1 ? 's' : '' ?>
                </span>
            </div>
        </div>

        <!-- Employee List -->
        <div class="dept-body" id="dept_body_<?= $deptId ?>" style="display:none;">
            <div class="table-wrap">
                <table class="glass-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Department</th>
                            <th>Coordinator</th>
                            <th>Position</th>
                            <th>Date Hired</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($deptEmps as $emp): ?>
                        <tr>
                            <td class="font-medium text-white whitespace-nowrap"><?= htmlspecialchars($emp['last_name'] . ', ' . $emp['first_name']) ?></td>
                            <td>
                                <select onchange="updateEmployeeDepartment(<?= $emp['id'] ?>, this.value)" class="px-2.5 py-1.5 bg-dark-700/50 border border-white/10 rounded-xl text-white text-xs focus:outline-none focus:border-primary-500/50 cursor-pointer w-full max-w-[200px]">
                                    <?php foreach ($departments as $d): ?>
                                    <option value="<?= $d['id'] ?>" <?= $emp['department_id'] == $d['id'] ? 'selected' : '' ?>><?= htmlspecialchars($d['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td>
                                <?php if ($coordInfo): ?>
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-semibold bg-blue-500/10 text-blue-400 border border-blue-500/20">
                                        <span class="w-5 h-5 rounded-md bg-gradient-to-br from-blue-500 to-blue-700 flex items-center justify-center text-white text-[10px] font-bold flex-shrink-0">
                                            <?= strtoupper(substr($coordInfo['full_name'], 0, 1)) ?>
                                        </span>
                                        <?= htmlspecialchars($coordInfo['full_name']) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-gray-600 italic text-xs">No coordinator</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-gray-400"><?= htmlspecialchars($emp['position']) ?></td>
                            <td class="text-gray-500"><?= $emp['date_hired'] ? date('M d, Y', strtotime($emp['date_hired'])) : '—' ?></td>
                            <td>
                                <a href="/ATTENDANCE/admin/employee_attendance.php?emp=<?= $emp['id'] ?>" class="text-xs text-primary-400 hover:text-primary-300 mr-3">📅 Attendance</a>
                                <a href="/ATTENDANCE/admin/employee_actions.php?search=<?= urlencode($emp['last_name']) ?>&tab=btw" class="text-xs text-green-400 hover:text-green-300 mr-3">💼 BTW/Actions</a>
                                <a href="/ATTENDANCE/admin/violations.php?emp=<?= $emp['id'] ?>" class="text-xs text-yellow-400 hover:text-yellow-300 mr-3">Violations</a>
                                <button type="button" onclick="deleteEmployee(<?= $emp['id'] ?>, '<?= htmlspecialchars($emp['last_name'] . ', ' . $emp['first_name'], ENT_QUOTES) ?>')" class="text-xs text-red-400 hover:text-red-300">🗑️ Delete</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?php else: ?>
<!-- ===================== TAB: BY COORDINATOR ===================== -->
<div class="space-y-4">
    <?php if (empty($empsByCoordinator) && empty($unassignedEmps)): ?>
        <div class="glass-card p-8 text-center">
            <p class="text-gray-500">No employees found matching your filters.</p>
        </div>
    <?php endif; ?>

    <?php foreach ($empsByCoordinator as $coordId => $group):
        $coord = $group['coordinator'];
        $coordEmps = $group['employees'];
        $empCount = count($coordEmps);
    ?>
    <div class="glass-card coordinator-card" data-coord-id="<?= $coordId ?>">
        <!-- Coordinator Header -->
        <div class="glass-card-header cursor-pointer select-none hover:bg-white/[0.02] transition-colors" onclick="toggleCoordFolder(<?= $coordId ?>)">
            <div class="flex items-center gap-3">
                <svg class="w-4 h-4 text-gray-500 transition-transform duration-200" id="coord_arrow_<?= $coordId ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-blue-500 to-blue-700 flex items-center justify-center text-white font-bold text-sm shadow-lg shadow-blue-500/20 flex-shrink-0">
                    <?= strtoupper(substr($coord['full_name'], 0, 1)) ?>
                </div>
                <div>
                    <div class="text-sm font-bold text-white"><?= htmlspecialchars($coord['full_name']) ?></div>
                    <div class="text-[10px] text-gray-500 flex items-center gap-2">
                        <span class="text-blue-400 font-medium">Coordinator</span>
                        <?php if ($coord['dept_name']): ?>
                            <span>•</span>
                            <span class="text-gray-400"><?= htmlspecialchars($coord['dept_name']) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <span class="text-xs text-gray-400 bg-white/5 px-3 py-1 rounded-full font-semibold">
                    👥 <?= $empCount ?> employee<?= $empCount !== 1 ? 's' : '' ?>
                </span>
            </div>
        </div>

        <!-- Employee List -->
        <div class="coord-body" id="coord_body_<?= $coordId ?>" style="display:none;">
            <div class="table-wrap">
                <table class="glass-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Department</th>
                            <th>Position</th>
                            <th>Date Hired</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($coordEmps as $emp): ?>
                        <tr>
                            <td class="font-medium text-white whitespace-nowrap"><?= htmlspecialchars($emp['last_name'] . ', ' . $emp['first_name']) ?></td>
                            <td>
                                <select onchange="updateEmployeeDepartment(<?= $emp['id'] ?>, this.value)" class="px-2.5 py-1.5 bg-dark-700/50 border border-white/10 rounded-xl text-white text-xs focus:outline-none focus:border-primary-500/50 cursor-pointer w-full max-w-[200px]">
                                    <?php foreach ($departments as $d): ?>
                                    <option value="<?= $d['id'] ?>" <?= $emp['department_id'] == $d['id'] ? 'selected' : '' ?>><?= htmlspecialchars($d['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td class="text-gray-400"><?= htmlspecialchars($emp['position']) ?></td>
                            <td class="text-gray-500"><?= $emp['date_hired'] ? date('M d, Y', strtotime($emp['date_hired'])) : '—' ?></td>
                            <td>
                                <a href="/ATTENDANCE/admin/employee_attendance.php?emp=<?= $emp['id'] ?>" class="text-xs text-primary-400 hover:text-primary-300 mr-3">📅 Attendance</a>
                                <a href="/ATTENDANCE/admin/employee_actions.php?search=<?= urlencode($emp['last_name']) ?>&tab=btw" class="text-xs text-green-400 hover:text-green-300 mr-3">💼 BTW/Actions</a>
                                <button type="button" onclick="deleteEmployee(<?= $emp['id'] ?>, '<?= htmlspecialchars($emp['last_name'] . ', ' . $emp['first_name'], ENT_QUOTES) ?>')" class="text-xs text-red-400 hover:text-red-300">🗑️ Delete</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endforeach; ?>

    <?php if (!empty($unassignedEmps)): ?>
    <!-- Unassigned Employees (no coordinator) -->
    <div class="glass-card" data-coord-id="unassigned">
        <div class="glass-card-header cursor-pointer select-none hover:bg-white/[0.02] transition-colors" onclick="toggleCoordFolder('unassigned')">
            <div class="flex items-center gap-3">
                <svg class="w-4 h-4 text-gray-500 transition-transform duration-200" id="coord_arrow_unassigned" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-gray-600 to-gray-800 flex items-center justify-center text-white font-bold text-sm flex-shrink-0">
                    ?
                </div>
                <div>
                    <div class="text-sm font-bold text-gray-400">No Coordinator Assigned</div>
                    <div class="text-[10px] text-gray-600">Department has no coordinator</div>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <span class="text-xs text-gray-500 bg-white/5 px-3 py-1 rounded-full font-semibold">
                    👥 <?= count($unassignedEmps) ?> employee<?= count($unassignedEmps) !== 1 ? 's' : '' ?>
                </span>
            </div>
        </div>

        <div class="coord-body" id="coord_body_unassigned" style="display:none;">
            <div class="table-wrap">
                <table class="glass-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Department</th>
                            <th>Position</th>
                            <th>Date Hired</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($unassignedEmps as $emp): ?>
                        <tr>
                            <td class="font-medium text-white whitespace-nowrap"><?= htmlspecialchars($emp['last_name'] . ', ' . $emp['first_name']) ?></td>
                            <td>
                                <select onchange="updateEmployeeDepartment(<?= $emp['id'] ?>, this.value)" class="px-2.5 py-1.5 bg-dark-700/50 border border-white/10 rounded-xl text-white text-xs focus:outline-none focus:border-primary-500/50 cursor-pointer w-full max-w-[200px]">
                                    <?php foreach ($departments as $d): ?>
                                    <option value="<?= $d['id'] ?>" <?= $emp['department_id'] == $d['id'] ? 'selected' : '' ?>><?= htmlspecialchars($d['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td class="text-gray-400"><?= htmlspecialchars($emp['position']) ?></td>
                            <td class="text-gray-500"><?= $emp['date_hired'] ? date('M d, Y', strtotime($emp['date_hired'])) : '—' ?></td>
                            <td>
                                <a href="/ATTENDANCE/admin/employee_attendance.php?emp=<?= $emp['id'] ?>" class="text-xs text-primary-400 hover:text-primary-300 mr-3">📅 Attendance</a>
                                <a href="/ATTENDANCE/admin/employee_actions.php?search=<?= urlencode($emp['last_name']) ?>&tab=btw" class="text-xs text-green-400 hover:text-green-300 mr-3">💼 BTW/Actions</a>
                                <button type="button" onclick="deleteEmployee(<?= $emp['id'] ?>, '<?= htmlspecialchars($emp['last_name'] . ', ' . $emp['first_name'], ENT_QUOTES) ?>')" class="text-xs text-red-400 hover:text-red-300">🗑️ Delete</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- Add Employee Modal -->
<div id="addEmployeeModal" class="modal-overlay" onclick="if(event.target===this)this.classList.remove('show')">
    <div class="modal-content">
        <div class="px-6 py-4 border-b border-white/10 flex items-center justify-between">
            <h3 class="text-base font-semibold text-white">Add New Employee</h3>
            <button onclick="document.getElementById('addEmployeeModal').classList.remove('show')" class="text-gray-500 hover:text-white text-xl">&times;</button>
        </div>
        <form method="POST" action="/ATTENDANCE/api.php?action=add_employee" class="p-6 space-y-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-400 mb-1.5 uppercase tracking-wider">First Name</label>
                    <input type="text" name="first_name" required class="w-full px-4 py-2.5 bg-dark-700/50 border border-white/10 rounded-xl text-white text-sm focus:outline-none focus:border-primary-500/50">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-400 mb-1.5 uppercase tracking-wider">Last Name</label>
                    <input type="text" name="last_name" required class="w-full px-4 py-2.5 bg-dark-700/50 border border-white/10 rounded-xl text-white text-sm focus:outline-none focus:border-primary-500/50">
                </div>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-400 mb-1.5 uppercase tracking-wider">Department</label>
                <select name="department_id" required class="w-full px-4 py-2.5 bg-dark-700/50 border border-white/10 rounded-xl text-white text-sm focus:outline-none focus:border-primary-500/50">
                    <option value="">Select department</option>
                    <?php foreach ($departments as $d): ?>
                    <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-400 mb-1.5 uppercase tracking-wider">Position</label>
                <input type="text" name="position" class="w-full px-4 py-2.5 bg-dark-700/50 border border-white/10 rounded-xl text-white text-sm focus:outline-none focus:border-primary-500/50">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-400 mb-1.5 uppercase tracking-wider">Date Hired</label>
                <input type="date" name="date_hired" class="w-full px-4 py-2.5 bg-dark-700/50 border border-white/10 rounded-xl text-white text-sm focus:outline-none focus:border-primary-500/50">
            </div>
            <button type="submit" class="w-full py-3 bg-gradient-to-r from-primary-500 to-primary-600 hover:from-primary-400 hover:to-primary-500 text-white font-semibold rounded-xl text-sm transition-all">
                Add Employee
            </button>
        </form>
    </div>
</div>

<script>
function updateEmployeeDepartment(empId, deptId) {
    const formData = new FormData();
    formData.append('action', 'update_employee_department');
    formData.append('employee_id', empId);
    formData.append('department_id', deptId);
    
    fetch('/ATTENDANCE/api.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Error: ' + (data.message || 'Failed to update department'));
            location.reload();
        }
    })
    .catch(error => {
        alert('Error: ' + error);
        location.reload();
    });
}

function deleteEmployee(empId, empName) {
    if (confirm(`Are you sure you want to delete ${empName}? This action cannot be undone.`)) {
        const formData = new FormData();
        formData.append('action', 'delete_employee');
        formData.append('employee_id', empId);
        
        fetch('/ATTENDANCE/api.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Employee deleted successfully');
                location.reload();
            } else {
                alert('Error: ' + (data.message || 'Failed to delete employee'));
            }
        })
        .catch(error => {
            alert('Error: ' + error);
        });
    }
}

function toggleCoordFolder(coordId) {
    const body = document.getElementById('coord_body_' + coordId);
    const arrow = document.getElementById('coord_arrow_' + coordId);
    if (body.style.display === 'none') {
        body.style.display = '';
        arrow.style.transform = 'rotate(90deg)';
    } else {
        body.style.display = 'none';
        arrow.style.transform = '';
    }
}

function toggleDeptFolder(deptId) {
    const body = document.getElementById('dept_body_' + deptId);
    const arrow = document.getElementById('dept_arrow_' + deptId);
    if (body.style.display === 'none') {
        body.style.display = '';
        arrow.style.transform = 'rotate(90deg)';
    } else {
        body.style.display = 'none';
        arrow.style.transform = '';
    }
}

// Auto-expand first coordinator/department folder
document.addEventListener('DOMContentLoaded', () => {
    const firstCard = document.querySelector('.coordinator-card');
    if (firstCard) {
        const coordId = firstCard.getAttribute('data-coord-id');
        toggleCoordFolder(coordId);
    }
    
    const firstDeptCard = document.querySelector('.department-card');
    if (firstDeptCard) {
        const deptId = firstDeptCard.getAttribute('data-dept-id');
        toggleDeptFolder(deptId);
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
