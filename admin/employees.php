<?php
/**
 * Admin — Employee Management
 */
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();
$db = getDB();
$pageTitle = 'Employee Management';

// Filters
$deptFilter = $_GET['dept'] ?? '';
$search     = $_GET['search'] ?? '';

// Departments for filter
$departments = $db->query("SELECT * FROM departments ORDER BY name")->fetchAll();

// Employees query
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

require_once __DIR__ . '/../includes/header.php';
?>

<!-- Filters -->
<div class="flex flex-col sm:flex-row gap-3 mb-5">
    <form method="GET" class="flex flex-col sm:flex-row gap-2 flex-1">
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

<!-- Employees Table -->
<div class="glass-card">
    <div class="glass-card-header">
        <h2 class="text-sm font-semibold text-white">Employees (<?= count($employees) ?>)</h2>
    </div>
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
            <?php if (empty($employees)): ?>
                <tr><td colspan="5" class="text-center text-gray-600 py-8">No employees found</td></tr>
            <?php else: ?>
                <?php foreach ($employees as $emp): ?>
                <tr>
                    <td class="font-medium text-white whitespace-nowrap"><?= htmlspecialchars($emp['last_name'] . ', ' . $emp['first_name']) ?></td>
                    <td><span class="badge badge-present"><?= htmlspecialchars($emp['dept_name']) ?></span></td>
                    <td class="text-gray-400"><?= htmlspecialchars($emp['position']) ?></td>
                    <td class="text-gray-500"><?= $emp['date_hired'] ? date('M d, Y', strtotime($emp['date_hired'])) : '—' ?></td>
                    <td>
                        <a href="/ATTENDANCE/admin/employee_attendance.php?emp=<?= $emp['id'] ?>" class="text-xs text-primary-400 hover:text-primary-300 mr-3">📅 Attendance</a>
                        <a href="/ATTENDANCE/admin/violations.php?emp=<?= $emp['id'] ?>" class="text-xs text-yellow-400 hover:text-yellow-300 mr-3">Violations</a>
                        <button type="button" onclick="deleteEmployee(<?= $emp['id'] ?>, '<?= htmlspecialchars($emp['last_name'] . ', ' . $emp['first_name'], ENT_QUOTES) ?>')" class="text-xs text-red-400 hover:text-red-300">🗑️ Delete</button>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

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
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
