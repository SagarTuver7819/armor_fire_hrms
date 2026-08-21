<?php
/**
 * Department-wise manual attendance — In / Out + Shift times
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/employee_helper.php';
require_once __DIR__ . '/../includes/master_helper.php';
require_once __DIR__ . '/../includes/attendance_helper.php';

requireLogin();
ensureAttendanceTables();

$deptId = (int) ($_GET['department_id'] ?? 0);
$date = trim((string) ($_GET['attendance_date'] ?? date('Y-m-d')));
$shiftId = (int) ($_GET['shift_id'] ?? 0);
$show = isset($_GET['show']) || $deptId > 0;

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !strtotime($date)) {
    $date = date('Y-m-d');
}

$departments = getActiveMasterRows('departments', 'sort_order ASC, department_name ASC');
$shifts = getActiveMasterRows('shifts', 'name ASC');
$selectedShift = null;
foreach ($shifts as $s) {
    if ((int) $s['id'] === $shiftId) {
        $selectedShift = $s;
        break;
    }
}
if (!$selectedShift && $shifts) {
    // Prefer Day shift matching common type, else first
    foreach ($shifts as $s) {
        if (($s['shift_type'] ?? '') === 'Day') {
            $selectedShift = $s;
            $shiftId = (int) $s['id'];
            break;
        }
    }
    if (!$selectedShift) {
        $selectedShift = $shifts[0];
        $shiftId = (int) $selectedShift['id'];
    }
}

$defaultIn = $selectedShift ? attendanceTimeForInput($selectedShift['start_time'] ?? '') : '09:00';
$defaultOut = $selectedShift ? attendanceTimeForInput($selectedShift['end_time'] ?? '') : '18:00';
$shiftName = $selectedShift['name'] ?? '';

$rows = [];
$department = null;
if ($show && $deptId > 0) {
    $department = getDepartmentById($deptId);
    $rows = getDepartmentManualAttendanceRows($deptId, $date);
}

$pageTitle = 'Manual Attendance';
$useSidebar = true;
$sidebarMode = $deptId > 0 ? 'department' : 'attendance';
$sidebarDeptId = $deptId;
$sidebarActive = 'attendance_manual';
$extraCss = ['https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css'];

require_once __DIR__ . '/../includes/header.php';

$toast = '';
if (isset($_GET['msg']) && $_GET['msg'] === 'saved') {
    $toast = 'Manual attendance saved for ' . (int) ($_GET['count'] ?? 0) . ' employee(s).';
}
?>

<main class="dashboard-main">
    <div class="page-toolbar flex-between">
        <?php if ($deptId > 0): ?>
            <a href="<?php echo app_url('department.php?id=' . $deptId); ?>" class="back-link">
                <i class="fa-solid fa-arrow-left"></i> Back to Modules
            </a>
        <?php else: ?>
            <a href="<?php echo app_url('attendance/index.php'); ?>" class="back-link">
                <i class="fa-solid fa-arrow-left"></i> Back to Attendance
            </a>
        <?php endif; ?>
    </div>

    <div class="form-page-card">
        <div class="form-page-header">
            <div>
                <h1>Manual Attendance (Department Wise)</h1>
                <p>Select department + date + shift · Punch In / Out same as app · Shift time auto-fill</p>
            </div>
        </div>

        <form method="GET" class="employee-form" style="margin-bottom:12px;">
            <div class="form-grid form-grid-3">
                <div class="form-group">
                    <label>Department *</label>
                    <select name="department_id" class="form-control" required>
                        <option value="">Select Department</option>
                        <?php foreach ($departments as $d): ?>
                            <option value="<?php echo (int) $d['id']; ?>" <?php echo $deptId === (int) $d['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($d['department_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Attendance Date *</label>
                    <input type="date" name="attendance_date" class="form-control" required value="<?php echo htmlspecialchars($date); ?>">
                </div>
                <div class="form-group">
                    <label>Shift *</label>
                    <select name="shift_id" id="shiftSelect" class="form-control" required>
                        <?php if (!$shifts): ?>
                            <option value="0">No shifts — add in Shift Master</option>
                        <?php endif; ?>
                        <?php foreach ($shifts as $s): ?>
                            <option value="<?php echo (int) $s['id']; ?>"
                                    data-in="<?php echo htmlspecialchars(attendanceTimeForInput($s['start_time'] ?? '')); ?>"
                                    data-out="<?php echo htmlspecialchars(attendanceTimeForInput($s['end_time'] ?? '')); ?>"
                                    <?php echo $shiftId === (int) $s['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars(formatShiftOptionLabel($s)); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <input type="hidden" name="show" value="1">
            <div class="form-actions" style="margin-top:8px;">
                <button type="submit" class="btn-primary"><i class="fa-solid fa-users"></i> Load Employees</button>
            </div>
        </form>

        <?php if ($show && $deptId <= 0): ?>
            <p class="form-hint">Please select a department.</p>
        <?php elseif ($show && !$department): ?>
            <p class="form-hint">Department not found.</p>
        <?php elseif ($show && !$rows): ?>
            <p class="form-hint">No active Salary/Jobwork employees in this department.</p>
        <?php elseif ($show && $rows): ?>
            <div class="ops-live-summary" style="margin-bottom:12px;">
                <span class="ops-chip"><?php echo htmlspecialchars($department['department_name']); ?></span>
                <span class="ops-chip"><?php echo htmlspecialchars(date('d M Y', strtotime($date))); ?></span>
                <span class="ops-chip"><?php echo htmlspecialchars($shiftName ?: 'Shift'); ?> · In <?php echo htmlspecialchars($defaultIn ?: '-'); ?> / Out <?php echo htmlspecialchars($defaultOut ?: '-'); ?></span>
                <span class="ops-chip"><?php echo count($rows); ?> employees</span>
            </div>

            <form method="POST" action="<?php echo app_url('attendance/manual_save.php'); ?>" id="manualAttForm">
                <input type="hidden" name="department_id" value="<?php echo $deptId; ?>">
                <input type="hidden" name="attendance_date" value="<?php echo htmlspecialchars($date); ?>">
                <input type="hidden" name="shift_id" value="<?php echo $shiftId; ?>">
                <input type="hidden" name="shift_name" value="<?php echo htmlspecialchars($shiftName); ?>">

                <div class="form-actions" style="margin-bottom:12px; gap:8px; flex-wrap:wrap;">
                    <button type="button" class="btn-secondary" id="btnApplyShift">
                        <i class="fa-solid fa-clock"></i> Apply Shift Time to All
                    </button>
                    <button type="button" class="btn-secondary" id="btnMarkPresent">
                        <i class="fa-solid fa-check"></i> Mark All Present
                    </button>
                    <button type="submit" class="btn-primary">
                        <i class="fa-solid fa-floppy-disk"></i> Save Attendance
                    </button>
                </div>

                <div class="table-wrap">
                    <table class="data-table" id="manualAttTable" style="width:100%">
                        <thead>
                            <tr>
                                <th style="width:40px;"><input type="checkbox" id="checkAll" checked title="Include"></th>
                                <th>Code</th>
                                <th>Employee</th>
                                <th>Status</th>
                                <th>Punch In</th>
                                <th>Punch Out</th>
                                <th>Remarks</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($rows as $i => $r):
                            $eid = (int) $r['id'];
                            $existingStatus = $r['day_status'] ?: '';
                            $status = $existingStatus !== '' ? $existingStatus : 'Present';
                            $inVal = $r['punch_in'] ? attendanceTimeForInput($r['punch_in']) : $defaultIn;
                            $outVal = $r['punch_out'] ? attendanceTimeForInput($r['punch_out']) : $defaultOut;
                            if (in_array($status, ['Absent', 'Week Off', 'Holiday', 'Leave'], true)) {
                                $inVal = $r['punch_in'] ? attendanceTimeForInput($r['punch_in']) : '';
                                $outVal = $r['punch_out'] ? attendanceTimeForInput($r['punch_out']) : '';
                            }
                            ?>
                            <tr class="manual-att-row">
                                <td>
                                    <input type="checkbox" class="row-include" name="rows[<?php echo $i; ?>][include]" value="1" checked>
                                    <input type="hidden" name="rows[<?php echo $i; ?>][employee_id]" value="<?php echo $eid; ?>">
                                </td>
                                <td><?php echo htmlspecialchars($r['employee_code']); ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($r['employee_name']); ?></strong>
                                    <?php if (!empty($r['shift_time'])): ?>
                                        <div class="sr-code"><?php echo htmlspecialchars(($r['shift_type'] ?? '') . ' · ' . $r['shift_time']); ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <select name="rows[<?php echo $i; ?>][status]" class="form-control row-status">
                                        <?php foreach (['Present', 'Half Day', 'Absent', 'Week Off', 'Leave', 'Holiday'] as $st): ?>
                                            <option value="<?php echo $st; ?>" <?php echo $status === $st ? 'selected' : ''; ?>><?php echo $st; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td>
                                    <input type="time" name="rows[<?php echo $i; ?>][punch_in]" class="form-control row-in" value="<?php echo htmlspecialchars($inVal); ?>">
                                </td>
                                <td>
                                    <input type="time" name="rows[<?php echo $i; ?>][punch_out]" class="form-control row-out" value="<?php echo htmlspecialchars($outVal); ?>">
                                </td>
                                <td>
                                    <input type="text" name="rows[<?php echo $i; ?>][remarks]" class="form-control" placeholder="Remark" value="">
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="form-actions sticky-actions">
                    <button type="submit" class="btn-primary">
                        <i class="fa-solid fa-floppy-disk"></i> Save Attendance
                    </button>
                    <a href="<?php echo app_url('attendance/index.php'); ?>" class="btn-secondary">Cancel</a>
                </div>
            </form>
        <?php endif; ?>
    </div>
</main>

<script>
window.ATT_DEFAULT_IN = <?php echo json_encode($defaultIn); ?>;
window.ATT_DEFAULT_OUT = <?php echo json_encode($defaultOut); ?>;
<?php if ($toast): ?>
window.ATT_TOAST = <?php echo json_encode($toast); ?>;
<?php endif; ?>
</script>
<?php
$extraJs = [
    'https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js',
    'assets/js/attendance_manual.js',
];
require_once __DIR__ . '/../includes/footer.php';
?>
