<?php
/**
 * Coordinator — Attendance Entry
 * P / A / NW / SL / SH buttons
 * Folder-style department grouping
 */
require_once __DIR__ . '/../includes/auth.php';
requireCoordinator();
$db = getDB();
$user = currentUser();
$pageTitle = 'Record Attendance';

$selectedDate = $_GET['date'] ?? date('Y-m-d');

$departments = getVisibleDepartments($db);
$allEmps = getVisibleEmployees($db);

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
    // Sort position folders alphabetically
    ksort($positions);
    
    // Sort employees within each position by last name
    foreach ($positions as &$emps) {
        usort($emps, function($a, $b) {
            return strcmp($a['last_name'], $b['last_name']);
        });
    }
}

$existing = $db->prepare("SELECT employee_id, status FROM attendance WHERE date = ?");
$existing->execute([$selectedDate]);
$existingMap = [];
foreach ($existing->fetchAll() as $a) {
    $existingMap[$a['employee_id']] = $a['status'];
}

// Check if selected date is in the past
$today = date('Y-m-d');
$isPastDate = strtotime($selectedDate) < strtotime($today);
$isFutureDate = strtotime($selectedDate) > strtotime($today);

require_once __DIR__ . '/../includes/header.php';
?>

<!-- Enhanced Date Header with Prominent Display -->
<div class="mb-6">
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <!-- Date Display Card -->
        <div class="md:col-span-2">
            <div class="p-6 rounded-2xl bg-gradient-to-br <?= $isPastDate ? 'from-amber-600/20 via-amber-500/10 to-transparent border border-amber-500/30' : ($isFutureDate ? 'from-blue-600/20 via-blue-500/10 to-transparent border border-blue-500/30' : 'from-green-600/20 via-green-500/10 to-transparent border border-green-500/30') ?>">
                <div class="flex items-center gap-4">
                    <div class="w-16 h-16 rounded-xl <?= $isPastDate ? 'bg-amber-500/20' : ($isFutureDate ? 'bg-blue-500/20' : 'bg-green-500/20') ?> flex items-center justify-center text-4xl flex-shrink-0">
                        <?= $isPastDate ? '📅' : ($isFutureDate ? '🔮' : '📍') ?>
                    </div>
                    <div class="flex-1">
                        <h2 class="text-2xl md:text-3xl font-bold text-white">
                            <?= date('l', strtotime($selectedDate)) ?>
                        </h2>
                        <p class="text-sm md:text-base text-gray-300 mt-1">
                            <?= date('F d, Y', strtotime($selectedDate)) ?>
                        </p>
                        <div class="flex items-center gap-2 mt-2">
                            <?php if ($isPastDate): ?>
                                <span class="inline-flex items-center gap-1.5 px-3 py-1 bg-amber-500/20 border border-amber-500/40 rounded-full text-xs font-semibold text-amber-300">
                                    ⚠️ PAST DATE — HR Approval Required
                                </span>
                            <?php elseif ($isFutureDate): ?>
                                <span class="inline-flex items-center gap-1.5 px-3 py-1 bg-blue-500/20 border border-blue-500/40 rounded-full text-xs font-semibold text-blue-300">
                                    🔮 Future Date
                                </span>
                            <?php else: ?>
                                <span class="inline-flex items-center gap-1.5 px-3 py-1 bg-green-500/20 border border-green-500/40 rounded-full text-xs font-semibold text-green-300">
                                    ✅ TODAY — Direct Entry
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Date Selector Card -->
        <div class="p-6 rounded-2xl bg-gradient-to-br from-primary-600/20 via-primary-500/10 to-transparent border border-primary-500/30">
            <label class="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">📅 Select Date</label>
            <form method="GET" class="space-y-3">
                <input type="date" name="date" value="<?= $selectedDate ?>" class="w-full px-4 py-2.5 bg-dark-700/50 border border-white/10 rounded-xl text-white text-sm focus:outline-none focus:border-primary-500/50 transition-colors" onchange="this.form.submit()">
                <a href="?date=<?= date('Y-m-d') ?>" class="block w-full text-center px-4 py-2.5 bg-green-600/20 border border-green-500/40 text-green-300 rounded-xl text-xs font-semibold hover:bg-green-600/30 transition-colors">
                    📍 Today
                </a>
            </form>
        </div>
    </div>
</div>

<?php if ($isPastDate): ?>
<!-- Warning Banner for Past Dates -->
<div class="mb-6 p-4 bg-amber-600/20 border border-amber-500/40 rounded-xl">
    <div class="flex items-start gap-3">
        <div class="text-2xl flex-shrink-0 mt-0.5">⚠️</div>
        <div>
            <h3 class="text-sm font-semibold text-amber-300 mb-1">This is a PAST date</h3>
            <p class="text-xs text-amber-200 leading-relaxed">
                Any changes you make to attendance for previous days require <strong>HR/Admin approval</strong>. Your edit requests will be submitted for review.
            </p>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Attendance Form -->
<form method="POST" action="/ATTENDANCE/api.php?action=save_attendance" id="attendanceForm">
    <input type="hidden" name="date" value="<?= $selectedDate ?>">

    <!-- Add Department Button -->
    <div class="flex justify-end mb-3">
        <button type="button" data-open-add-dept class="inline-flex items-center gap-1.5 px-4 py-2 bg-primary-600 hover:bg-primary-500 text-white rounded-xl text-xs font-semibold uppercase tracking-wider transition-all">
            ➕ Add Department
        </button>
    </div>

    <div class="space-y-3">
        <?php foreach ($departments as $dept):
            $deptPosEmps = $empsByDeptAndPos[$dept['id']] ?? [];
            if (empty($deptPosEmps)) continue;
            
            // Count total employees in department
            $totalCount = 0;
            foreach ($deptPosEmps as $emps) {
                $totalCount += count($emps);
            }
        ?>
        <div class="glass-card" data-dept-folder="<?= $dept['id'] ?>">
            <div class="glass-card-header" style="cursor:pointer" data-toggle-dept="<?= $dept['id'] ?>">
                <div class="flex items-center gap-3">
                    <svg class="w-5 h-5 text-gray-500 transition-transform duration-200" data-arrow="<?= $dept['id'] ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    <span class="text-lg font-bold text-white" data-dept-name="<?= $dept['id'] ?>">📁 <?= htmlspecialchars($dept['name']) ?></span>
                    <span class="text-sm text-gray-500 bg-white/5 px-3 py-1 rounded-full font-semibold"><?= $totalCount ?> staff</span>
                    <button type="button" data-edit-dept="<?= $dept['id'] ?>" data-dept-current="<?= htmlspecialchars($dept['name'], ENT_QUOTES) ?>" class="text-gray-600 hover:text-primary-400 transition-colors ml-auto" title="Edit department">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                    </button>
                </div>
                <div class="flex gap-1.5" data-markall-group>
                    <button type="button" data-mark-all="<?= $dept['id'] ?>" data-mark-status="present" class="att-btn px-2.5 py-1 text-[10px] font-semibold">All P</button>
                    <button type="button" data-mark-all="<?= $dept['id'] ?>" data-mark-status="absent" class="att-btn px-2.5 py-1 text-[10px] font-semibold">All A</button>
                    <button type="button" data-mark-all="<?= $dept['id'] ?>" data-mark-status="no_work" class="att-btn px-2.5 py-1 text-[10px] font-semibold">All NW</button>
                    <button type="button" data-mark-all="<?= $dept['id'] ?>" data-mark-status="leave" class="att-btn px-2.5 py-1 text-[10px] font-semibold">All SL</button>
                    <button type="button" data-mark-all="<?= $dept['id'] ?>" data-mark-status="sent_home" class="att-btn px-2.5 py-1 text-[10px] font-semibold">All SH</button>
                    <button type="button" data-mark-all="<?= $dept['id'] ?>" data-mark-status="rest_day" class="att-btn px-2.5 py-1 text-[10px] font-semibold">All RD</button>
                </div>
            </div>
            <div data-dept-body="<?= $dept['id'] ?>" style="display:none;" class="pl-6 bg-white/[0.01]">
                <!-- Position folders within department -->
                <?php foreach ($deptPosEmps as $position => $emps):
                    $posCount = count($emps);
                    $posKey = md5($dept['id'] . $position); // unique key for position folder
                ?>
                <div class="bg-white/[0.02] border-l-2 border-primary-600/30">
                    <div class="px-5 py-3 flex items-center justify-between cursor-pointer hover:bg-white/[0.04] transition-colors" data-toggle-position="<?= $posKey ?>">
                        <div class="flex items-center gap-2.5 flex-1">
                            <svg class="w-4 h-4 text-gray-500 transition-transform duration-200" data-pos-arrow="<?= $posKey ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                            <span class="text-sm font-semibold text-gray-200">📂 <?= htmlspecialchars($position) ?></span>
                            <span class="text-sm text-gray-600 bg-white/5 px-2 py-0.5 rounded font-semibold"><?= $posCount ?></span>
                        </div>
                        <div class="flex gap-1" data-markall-pos="<?= $posKey ?>" onclick="event.stopPropagation();">
                            <button type="button" data-mark-pos="<?= $posKey ?>" data-mark-status="present" class="att-btn px-2 py-0.5 text-[9px] font-semibold">P</button>
                            <button type="button" data-mark-pos="<?= $posKey ?>" data-mark-status="absent" class="att-btn px-2 py-0.5 text-[9px] font-semibold">A</button>
                            <button type="button" data-mark-pos="<?= $posKey ?>" data-mark-status="no_work" class="att-btn px-2 py-0.5 text-[9px] font-semibold">NW</button>
                            <button type="button" data-mark-pos="<?= $posKey ?>" data-mark-status="leave" class="att-btn px-2 py-0.5 text-[9px] font-semibold">SL</button>
                            <button type="button" data-mark-pos="<?= $posKey ?>" data-mark-status="sent_home" class="att-btn px-2 py-0.5 text-[9px] font-semibold">SH</button>
                            <button type="button" data-mark-pos="<?= $posKey ?>" data-mark-status="rest_day" class="att-btn px-2 py-0.5 text-[9px] font-semibold">RD</button>
                        </div>
                    </div>
                    <div data-position-body="<?= $posKey ?>" style="display:none;" class="bg-white/[0.01]">
                        <?php foreach ($emps as $emp):
                            $status = $existingMap[$emp['id']] ?? '';
                        ?>
                        <div class="flex items-center gap-3 px-8 py-3.5 border-b border-white/[0.04] last:border-0 hover:bg-white/[0.03] transition-all duration-200 group" data-emp-row data-emp-dept="<?= $dept['id'] ?>" data-emp-id="<?= $emp['id'] ?>">
                            <!-- Employee Info -->
                            <div class="flex-1 min-w-0 flex items-center gap-2.5">
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2">
                                        <span class="text-base font-bold text-white" data-empname="<?= $emp['id'] ?>"><?= htmlspecialchars($emp['last_name'] . ', ' . $emp['first_name']) ?></span>
                                        <button type="button" data-edit-emp="<?= $emp['id'] ?>" data-emp-first="<?= htmlspecialchars($emp['first_name'], ENT_QUOTES) ?>" data-emp-last="<?= htmlspecialchars($emp['last_name'], ENT_QUOTES) ?>" data-emp-position="<?= htmlspecialchars($emp['position'], ENT_QUOTES) ?>" class="text-gray-600 hover:text-primary-400 transition-colors flex-shrink-0 opacity-0 group-hover:opacity-100" title="Edit employee">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                                        </button>
                                        <button type="button" onclick="deleteEmployeeCoord(<?= $emp['id'] ?>, '<?= htmlspecialchars($emp['last_name'] . ', ' . $emp['first_name'], ENT_QUOTES) ?>')" class="text-gray-600 hover:text-red-400 transition-colors flex-shrink-0 opacity-0 group-hover:opacity-100" title="Delete employee">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                        </button>
                                    </div>
                                    <span class="text-xs text-gray-500 ml-0.5 hidden sm:inline">— <?= htmlspecialchars($emp['position']) ?></span>
                                </div>
                            </div>

                            <!-- Status Input (Hidden) -->
                            <input type="hidden" name="attendance[<?= $emp['id'] ?>][status]" data-status="<?= $emp['id'] ?>" value="<?= $status ?>">

                            <!-- Status Buttons -->
                            <div class="flex gap-1.5 flex-shrink-0" data-status-group="<?= $emp['id'] ?>">
                                <button type="button" data-set-status="<?= $emp['id'] ?>" data-status-val="present" class="att-btn-enhanced present-btn px-3 py-2 text-sm font-semibold transition-all duration-200 hover:scale-105 <?= $status==='present'?'selected':'' ?>" title="Present">P</button>
                                <button type="button" data-set-status="<?= $emp['id'] ?>" data-status-val="absent" class="att-btn-enhanced absent-btn px-3 py-2 text-sm font-semibold transition-all duration-200 hover:scale-105 <?= $status==='absent'?'selected':'' ?>" title="Absent">A</button>
                                <button type="button" data-set-status="<?= $emp['id'] ?>" data-status-val="no_work" class="att-btn-enhanced nowork-btn px-3 py-2 text-sm font-semibold transition-all duration-200 hover:scale-105 <?= $status==='no_work'?'selected':'' ?>" title="No Work">NW</button>
                                <button type="button" data-set-status="<?= $emp['id'] ?>" data-status-val="leave" class="att-btn-enhanced leave-btn px-3 py-2 text-sm font-semibold transition-all duration-200 hover:scale-105 <?= $status==='leave'?'selected':'' ?>" title="Sick Leave">SL</button>
                                <button type="button" data-set-status="<?= $emp['id'] ?>" data-status-val="sent_home" class="att-btn-enhanced senthome-btn px-3 py-2 text-sm font-semibold transition-all duration-200 hover:scale-105 <?= $status==='sent_home'?'selected':'' ?>" title="Sent Home">SH</button>
                                <button type="button" data-set-status="<?= $emp['id'] ?>" data-status-val="rest_day" class="att-btn-enhanced restday-btn px-3 py-2 text-sm font-semibold transition-all duration-200 hover:scale-105 <?= $status==='rest_day'?'selected':'' ?>" title="Rest Day">RD</button>
                            </div>

                            <!-- Change Indicator -->
                            <div class="change-indicator text-xs font-bold text-amber-400 opacity-0 transition-opacity duration-200 whitespace-nowrap" data-change="<?= $emp['id'] ?>">
                                🔄 Changed
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

    <!-- Submit -->
    <div class="sticky bottom-0 bg-dark-900/90 backdrop-blur-lg border-t border-white/5 p-4 mt-4 -mx-4 md:-mx-6 lg:-mx-8 px-4 md:px-6 lg:px-8">
        <div class="flex flex-col sm:flex-row items-center gap-4 justify-between">
            <div class="text-xs text-gray-400">
                <?php if ($isPastDate): ?>
                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-amber-500/10 border border-amber-500/30 rounded-lg text-amber-300">
                        ⚠️ Past date edits require HR/Admin approval
                    </span>
                <?php else: ?>
                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-green-500/10 border border-green-500/30 rounded-lg text-green-300">
                        ✅ Changes will be saved immediately
                    </span>
                <?php endif; ?>
            </div>
            <button type="submit" class="w-full sm:w-auto px-8 py-3.5 bg-gradient-to-r <?= $isPastDate ? 'from-amber-600 to-amber-600 hover:from-amber-500 hover:to-amber-500' : 'from-primary-500 to-primary-600 hover:from-primary-400 hover:to-primary-500' ?> text-white font-semibold rounded-xl shadow-lg transition-all text-sm uppercase tracking-wider active:scale-[0.98]" style="<?= $isPastDate ? 'box-shadow: 0 10px 25px rgba(217,119,6,0.25);' : 'box-shadow: 0 10px 25px rgba(59,130,246,0.25);' ?>">
                <?= $isPastDate ? '📤 Submit for Approval' : '💾 Save Attendance' ?>
            </button>
        </div>
    </div>
</form>

<!-- Edit Department Modal -->
<div id="editDeptModal" class="modal-overlay">
    <div class="modal-content" style="max-width:24rem;">
        <div class="px-5 py-4 border-b border-white/10 flex items-center justify-between">
            <h3 class="text-sm font-semibold text-white">✏️ Edit Department</h3>
            <button type="button" data-close-modal="editDeptModal" class="text-gray-500 hover:text-white text-xl">&times;</button>
        </div>
        <div class="p-5 space-y-4">
            <input type="hidden" id="editDeptId">
            <div>
                <label class="block text-xs font-medium text-gray-400 mb-1.5 uppercase tracking-wider">Department Name</label>
                <input type="text" id="editDeptName" class="w-full px-4 py-2.5 bg-dark-700/50 border border-white/10 rounded-xl text-white text-sm focus:outline-none focus:border-primary-500/50">
            </div>
            <button type="button" id="saveDeptBtn" class="w-full py-2.5 bg-gradient-to-r from-primary-500 to-primary-600 hover:from-primary-400 hover:to-primary-500 text-white font-semibold rounded-xl text-sm transition-all">Save</button>
        </div>
    </div>
</div>

<!-- Edit Employee Modal -->
<div id="editEmpModal" class="modal-overlay">
    <div class="modal-content" style="max-width:24rem;">
        <div class="px-5 py-4 border-b border-white/10 flex items-center justify-between">
            <h3 class="text-sm font-semibold text-white">✏️ Edit Employee</h3>
            <button type="button" data-close-modal="editEmpModal" class="text-gray-500 hover:text-white text-xl">&times;</button>
        </div>
        <div class="p-5 space-y-4">
            <input type="hidden" id="editEmpId">
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-medium text-gray-400 mb-1.5 uppercase tracking-wider">First Name</label>
                    <input type="text" id="editEmpFirst" class="w-full px-3 py-2.5 bg-dark-700/50 border border-white/10 rounded-xl text-white text-sm focus:outline-none focus:border-primary-500/50">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-400 mb-1.5 uppercase tracking-wider">Last Name</label>
                    <input type="text" id="editEmpLast" class="w-full px-3 py-2.5 bg-dark-700/50 border border-white/10 rounded-xl text-white text-sm focus:outline-none focus:border-primary-500/50">
                </div>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-400 mb-1.5 uppercase tracking-wider">Position</label>
                <input type="text" id="editEmpPosition" class="w-full px-4 py-2.5 bg-dark-700/50 border border-white/10 rounded-xl text-white text-sm focus:outline-none focus:border-primary-500/50">
            </div>
            <button type="button" id="saveEmpBtn" class="w-full py-2.5 bg-gradient-to-r from-primary-500 to-primary-600 hover:from-primary-400 hover:to-primary-500 text-white font-semibold rounded-xl text-sm transition-all">Save</button>
        </div>
    </div>
    </div>
</div>

<!-- Add Department Modal -->
<div id="addDeptModal" class="modal-overlay">
    <div class="modal-content" style="max-width:24rem;">
        <div class="px-5 py-4 border-b border-white/10 flex items-center justify-between">
            <h3 class="text-sm font-semibold text-white">➕ Add Department</h3>
            <button type="button" data-close-modal="addDeptModal" class="text-gray-500 hover:text-white text-xl">&times;</button>
        </div>
        <div class="p-5 space-y-4">
            <div>
                <label class="block text-xs font-medium text-gray-400 mb-1.5 uppercase tracking-wider">Department Name</label>
                <input type="text" id="newDeptName" class="w-full px-4 py-2.5 bg-dark-700/50 border border-white/10 rounded-xl text-white text-sm focus:outline-none focus:border-primary-500/50" placeholder="e.g. Operations">
            </div>
            <button type="button" id="addDeptBtn" class="w-full py-2.5 bg-gradient-to-r from-primary-500 to-primary-600 hover:from-primary-400 hover:to-primary-500 text-white font-semibold rounded-xl text-sm transition-all">Create Department</button>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var STATUSES = ['present','absent','no_work','leave','sent_home','rest_day'];

    // Function to update button classes
    function updateButtonClasses(empId, selectedStatus) {
        var buttons = document.querySelectorAll('[data-set-status="' + empId + '"]');
        buttons.forEach(function(btn) {
            if (btn.getAttribute('data-status-val') === selectedStatus) {
                btn.classList.add('selected');
            } else {
                btn.classList.remove('selected');
            }
        });

        // Update change indicator
        var row = document.querySelector('[data-emp-row][data-emp-id="' + empId + '"]');
        if (row) {
            var indicator = row.querySelector('[data-change="' + empId + '"]');
            if (indicator) {
                if (selectedStatus) {
                    indicator.classList.add('show');
                } else {
                    indicator.classList.remove('show');
                }
            }
        }
    }

    // --- Status Buttons (P, A, NW, SL, SH) ---
    document.addEventListener('click', function(e) {
        var btn = e.target.closest('[data-set-status]');
        if (btn) {
            e.preventDefault();
            e.stopPropagation();
            var empId = btn.getAttribute('data-set-status');
            var status = btn.getAttribute('data-status-val');
            var input = document.querySelector('[data-status="' + empId + '"]');
            if (!input) return;

            // Toggle off if same
            if (input.value === status) {
                input.value = '';
                updateButtonClasses(empId, '');
                return;
            }
            
            input.value = status;
            updateButtonClasses(empId, status);
            return;
        }

        // --- Mark All Dept ---
        var markBtn = e.target.closest('[data-mark-all]');
        if (markBtn) {
            e.preventDefault();
            e.stopPropagation();
            var deptId = markBtn.getAttribute('data-mark-all');
            var markStatus = markBtn.getAttribute('data-mark-status');
            var rows = document.querySelectorAll('[data-emp-dept="' + deptId + '"]');
            rows.forEach(function(row) {
                var inp = row.querySelector('[data-status]');
                if (!inp) return;
                var eid = inp.getAttribute('data-status');
                inp.value = markStatus;
                updateButtonClasses(eid, markStatus);
            });
            // Expand department folder
            var body = document.querySelector('[data-dept-body="' + deptId + '"]');
            if (body) body.style.display = '';
            var arrow = document.querySelector('[data-arrow="' + deptId + '"]');
            if (arrow) arrow.style.transform = 'rotate(90deg)';
            
            // Expand all position folders
            if (body) {
                var posFolders = body.querySelectorAll('[data-toggle-position]');
                posFolders.forEach(function(posFolder) {
                    var posKey = posFolder.getAttribute('data-toggle-position');
                    var posBody = document.querySelector('[data-position-body="' + posKey + '"]');
                    var posArrow = document.querySelector('[data-pos-arrow="' + posKey + '"]');
                    if (posBody) posBody.style.display = '';
                    if (posArrow) posArrow.style.transform = 'rotate(90deg)';
                });
            }
            return;
        }

        // --- Mark All Position ---
        var markPosBtn = e.target.closest('[data-mark-pos]');
        if (markPosBtn) {
            e.preventDefault();
            e.stopPropagation();
            var posKey = markPosBtn.getAttribute('data-mark-pos');
            var markStatus = markPosBtn.getAttribute('data-mark-status');
            
            // Find position body and mark all employees in it
            var posBody = document.querySelector('[data-position-body="' + posKey + '"]');
            if (!posBody) return;
            
            var rows = posBody.querySelectorAll('[data-emp-row]');
            rows.forEach(function(row) {
                var inp = row.querySelector('[data-status]');
                if (!inp) return;
                var eid = inp.getAttribute('data-status');
                inp.value = markStatus;
                updateButtonClasses(eid, markStatus);
            });
            
            // Expand position folder
            if (posBody) posBody.style.display = '';
            var posArrow = document.querySelector('[data-pos-arrow="' + posKey + '"]');
            if (posArrow) posArrow.style.transform = 'rotate(90deg)';
            return;
        }

        // --- Toggle Folder ---
        var toggle = e.target.closest('[data-toggle-dept]');
        if (toggle && !e.target.closest('[data-mark-all]') && !e.target.closest('[data-edit-dept]')) {
            var did = toggle.getAttribute('data-toggle-dept');
            var body = document.querySelector('[data-dept-body="' + did + '"]');
            var arrow = document.querySelector('[data-arrow="' + did + '"]');
            if (body && body.style.display === 'none') {
                body.style.display = '';
                if (arrow) arrow.style.transform = 'rotate(90deg)';
            } else if (body) {
                body.style.display = 'none';
                if (arrow) arrow.style.transform = '';
            }
            return;
        }

        // --- Toggle Position Folder ---
        var posToggle = e.target.closest('[data-toggle-position]');
        if (posToggle) {
            var posKey = posToggle.getAttribute('data-toggle-position');
            var posBody = document.querySelector('[data-position-body="' + posKey + '"]');
            var posArrow = document.querySelector('[data-pos-arrow="' + posKey + '"]');
            if (posBody && posBody.style.display === 'none') {
                posBody.style.display = '';
                if (posArrow) posArrow.style.transform = 'rotate(90deg)';
            } else if (posBody) {
                posBody.style.display = 'none';
                if (posArrow) posArrow.style.transform = '';
            }
            return;
        }

        // --- Edit Department ---
        var editDept = e.target.closest('[data-edit-dept]');
        if (editDept) {
            e.preventDefault();
            e.stopPropagation();
            document.getElementById('editDeptId').value = editDept.getAttribute('data-edit-dept');
            document.getElementById('editDeptName').value = editDept.getAttribute('data-dept-current');
            document.getElementById('editDeptModal').classList.add('show');
            return;
        }

        // --- Edit Employee ---
        var editEmp = e.target.closest('[data-edit-emp]');
        if (editEmp) {
            e.preventDefault();
            e.stopPropagation();
            document.getElementById('editEmpId').value = editEmp.getAttribute('data-edit-emp');
            document.getElementById('editEmpFirst').value = editEmp.getAttribute('data-emp-first');
            document.getElementById('editEmpLast').value = editEmp.getAttribute('data-emp-last');
            document.getElementById('editEmpPosition').value = editEmp.getAttribute('data-emp-position');
            document.getElementById('editEmpModal').classList.add('show');
            return;
        }

        // --- Close Modal ---
        var closeBtn = e.target.closest('[data-close-modal]');
        if (closeBtn) {
            document.getElementById(closeBtn.getAttribute('data-close-modal')).classList.remove('show');
            return;
        }

        // --- Click modal backdrop to close ---
        if (e.target.classList.contains('modal-overlay')) {
            e.target.classList.remove('show');
        }
    });

    // --- Save Department ---
    document.getElementById('saveDeptBtn').addEventListener('click', function() {
        var id = document.getElementById('editDeptId').value;
        var name = document.getElementById('editDeptName').value.trim();
        if (!name) return;
        var fd = new FormData();
        fd.append('id', id);
        fd.append('name', name);
        fetch('/ATTENDANCE/api.php?action=update_department', { method: 'POST', body: fd })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success) {
                    var el = document.querySelector('[data-dept-name="' + id + '"]');
                    if (el) el.textContent = '📁 ' + data.name;
                    var editBtn = document.querySelector('[data-edit-dept="' + id + '"]');
                    if (editBtn) editBtn.setAttribute('data-dept-current', data.name);
                    document.getElementById('editDeptModal').classList.remove('show');
                } else {
                    alert(data.error || 'Error');
                }
            });
    });

    // --- Save Employee ---
    document.getElementById('saveEmpBtn').addEventListener('click', function() {
        var id = document.getElementById('editEmpId').value;
        var first = document.getElementById('editEmpFirst').value.trim();
        var last = document.getElementById('editEmpLast').value.trim();
        var pos = document.getElementById('editEmpPosition').value.trim();
        if (!first || !last) return;
        var fd = new FormData();
        fd.append('id', id);
        fd.append('first_name', first);
        fd.append('last_name', last);
        fd.append('position', pos);
        fetch('/ATTENDANCE/api.php?action=update_employee', { method: 'POST', body: fd })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success) {
                    var el = document.querySelector('[data-empname="' + id + '"]');
                    if (el) el.textContent = data.last_name + ', ' + data.first_name;
                    var pos = document.querySelector('[data-emppos="' + id + '"]');
                    if (pos) pos.textContent = '— ' + data.position;
                    document.getElementById('editEmpModal').classList.remove('show');
                } else {
                    alert(data.error || 'Error');
                }
            });
    });

    // --- Open Add Department Modal ---
    var addDeptOpener = document.querySelector('[data-open-add-dept]');
    if (addDeptOpener) {
        addDeptOpener.addEventListener('click', function(e) {
            e.preventDefault();
            document.getElementById('newDeptName').value = '';
            document.getElementById('addDeptModal').classList.add('show');
        });
    }

    // --- Add Department ---
    document.getElementById('addDeptBtn').addEventListener('click', function() {
        var name = document.getElementById('newDeptName').value.trim();
        if (!name) return;
        var fd = new FormData();
        fd.append('name', name);
        fetch('/ATTENDANCE/api.php?action=add_department', { method: 'POST', body: fd })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success) {
                    alert('✅ Department "' + data.name + '" created!');
                    window.location.reload();
                } else {
                    alert('❌ ' + (data.error || 'Error'));
                }
            });
    });

    // Departments start collapsed - no auto-expand
    // User must click to expand each department
});

function deleteEmployeeCoord(empId, empName) {
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

<!-- Enhanced Styling for Attendance Buttons -->
<style>
    /* Enhanced button styling */
    .att-btn-enhanced {
        border: 2px solid;
        border-radius: 8px;
        background-color: rgba(255, 255, 255, 0.05);
        border-color: rgba(255, 255, 255, 0.15);
        color: rgba(255, 255, 255, 0.6);
        transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
        position: relative;
    }

    .att-btn-enhanced:hover {
        background-color: rgba(255, 255, 255, 0.1);
        border-color: rgba(255, 255, 255, 0.25);
        color: rgba(255, 255, 255, 0.8);
    }

    /* Present Button */
    .att-btn-enhanced.present-btn {
        --color: #22c55e;
        --color-light: rgba(34, 197, 94, 0.2);
    }

    .att-btn-enhanced.present-btn.selected {
        background-color: #22c55e;
        border-color: #16a34a;
        color: white;
        font-weight: 700;
        box-shadow: 0 0 16px rgba(34, 197, 94, 0.6), inset 0 0 8px rgba(255, 255, 255, 0.2);
        animation: pulse-green 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
    }

    /* Absent Button */
    .att-btn-enhanced.absent-btn {
        --color: #ef4444;
    }

    .att-btn-enhanced.absent-btn.selected {
        background-color: #ef4444;
        border-color: #dc2626;
        color: white;
        font-weight: 700;
        box-shadow: 0 0 16px rgba(239, 68, 68, 0.6), inset 0 0 8px rgba(255, 255, 255, 0.2);
        animation: pulse-red 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
    }

    /* No Work Button */
    .att-btn-enhanced.nowork-btn {
        --color: #9ca3af;
    }

    .att-btn-enhanced.nowork-btn.selected {
        background-color: #6b7280;
        border-color: #4b5563;
        color: white;
        font-weight: 700;
        box-shadow: 0 0 16px rgba(107, 114, 128, 0.6), inset 0 0 8px rgba(255, 255, 255, 0.2);
        animation: pulse-gray 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
    }

    /* Leave Button */
    .att-btn-enhanced.leave-btn {
        --color: #a855f7;
    }

    .att-btn-enhanced.leave-btn.selected {
        background-color: #a855f7;
        border-color: #9333ea;
        color: white;
        font-weight: 700;
        box-shadow: 0 0 16px rgba(168, 85, 247, 0.6), inset 0 0 8px rgba(255, 255, 255, 0.2);
        animation: pulse-purple 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
    }

    /* Sent Home Button */
    .att-btn-enhanced.senthome-btn {
        --color: #14b8a6;
    }

    .att-btn-enhanced.senthome-btn.selected {
        background-color: #14b8a6;
        border-color: #0d9488;
        color: white;
        font-weight: 700;
        box-shadow: 0 0 16px rgba(20, 184, 166, 0.6), inset 0 0 8px rgba(255, 255, 255, 0.2);
        animation: pulse-teal 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
    }

    /* Rest Day Button */
    .att-btn-enhanced.restday-btn {
        --color: #f97316;
    }

    .att-btn-enhanced.restday-btn.selected {
        background-color: #f97316;
        border-color: #ea580c;
        color: white;
        font-weight: 700;
        box-shadow: 0 0 16px rgba(249, 115, 22, 0.6), inset 0 0 8px rgba(255, 255, 255, 0.2);
        animation: pulse-orange 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
    }

    /* Pulse Animations */
    @keyframes pulse-green {
        0%, 100% { box-shadow: 0 0 16px rgba(34, 197, 94, 0.6), inset 0 0 8px rgba(255, 255, 255, 0.2); }
        50% { box-shadow: 0 0 24px rgba(34, 197, 94, 0.8), inset 0 0 12px rgba(255, 255, 255, 0.3); }
    }

    @keyframes pulse-red {
        0%, 100% { box-shadow: 0 0 16px rgba(239, 68, 68, 0.6), inset 0 0 8px rgba(255, 255, 255, 0.2); }
        50% { box-shadow: 0 0 24px rgba(239, 68, 68, 0.8), inset 0 0 12px rgba(255, 255, 255, 0.3); }
    }

    @keyframes pulse-gray {
        0%, 100% { box-shadow: 0 0 16px rgba(107, 114, 128, 0.6), inset 0 0 8px rgba(255, 255, 255, 0.2); }
        50% { box-shadow: 0 0 24px rgba(107, 114, 128, 0.8), inset 0 0 12px rgba(255, 255, 255, 0.3); }
    }

    @keyframes pulse-purple {
        0%, 100% { box-shadow: 0 0 16px rgba(168, 85, 247, 0.6), inset 0 0 8px rgba(255, 255, 255, 0.2); }
        50% { box-shadow: 0 0 24px rgba(168, 85, 247, 0.8), inset 0 0 12px rgba(255, 255, 255, 0.3); }
    }

    @keyframes pulse-teal {
        0%, 100% { box-shadow: 0 0 16px rgba(20, 184, 166, 0.6), inset 0 0 8px rgba(255, 255, 255, 0.2); }
        50% { box-shadow: 0 0 24px rgba(20, 184, 166, 0.8), inset 0 0 12px rgba(255, 255, 255, 0.3); }
    }

    @keyframes pulse-orange {
        0%, 100% { box-shadow: 0 0 16px rgba(249, 115, 22, 0.6), inset 0 0 8px rgba(255, 255, 255, 0.2); }
        50% { box-shadow: 0 0 24px rgba(249, 115, 22, 0.8), inset 0 0 12px rgba(255, 255, 255, 0.3); }
    }

    /* Change Indicator */
    .change-indicator {
        transition: opacity 0.3s ease;
    }

    [data-emp-row] .change-indicator.show {
        opacity: 1;
    }

    /* Hover effects */
    [data-emp-row]:hover .att-btn-enhanced:not(.selected) {
        background-color: rgba(255, 255, 255, 0.08);
        border-color: rgba(255, 255, 255, 0.2);
    }

    /* Responsive spacing */
    @media (max-width: 768px) {
        .att-btn-enhanced {
            px-2.5;
            py-1.5;
            font-size: 0.75rem;
        }
    }
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
