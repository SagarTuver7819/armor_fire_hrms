<?php
/**
 * Manual Attendance — Excel-style monthly grid (same as Attendance Report.xlsx)
 * Columns: Code, Name, Designation, Department, DOJ, Days 1-31, PL, SL, C-Off, DL, LWP, Total Days
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
$month = (int) ($_GET['month'] ?? date('n'));
$year = (int) ($_GET['year'] ?? date('Y'));
$shiftId = (int) ($_GET['shift_id'] ?? 0);
$show = isset($_GET['show']);

if ($month < 1 || $month > 12) {
    $month = (int) date('n');
}
if ($year < 2000 || $year > 2100) {
    $year = (int) date('Y');
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
$defaultInDisp = $defaultIn ? date('g:i A', strtotime($defaultIn)) : '9:00 AM';
$defaultOutDisp = $defaultOut ? date('g:i A', strtotime($defaultOut)) : '6:00 PM';
$shiftName = $selectedShift['name'] ?? '';

$monthData = null;
$department = null;
$deptLabel = 'All Employees';
$monthDays = (int) date('t', mktime(0, 0, 0, $month, 1, $year));
$dayNames = [];
for ($d = 1; $d <= $monthDays; $d++) {
    $dayNames[$d] = date('D', mktime(0, 0, 0, $month, $d, $year));
}

if ($show) {
    if ($deptId > 0) {
        $department = getDepartmentById($deptId);
        $deptLabel = $department ? (string) $department['department_name'] : 'Department';
        if ($department) {
            $monthData = getAttendanceExcelMonthGrid($month, $year, $deptId, 0);
            $monthDays = (int) $monthData['month_days'];
        }
    } else {
        $monthData = getAttendanceExcelMonthGrid($month, $year, 0, 0);
        $monthDays = (int) $monthData['month_days'];
    }
    for ($d = 1; $d <= $monthDays; $d++) {
        $dayNames[$d] = date('D', mktime(0, 0, 0, $month, $d, $year));
    }
}

$excelQs = http_build_query([
    'department_id' => $deptId,
    'month' => $month,
    'year' => $year,
]);

$pageTitle = 'Manual Attendance';
$useSidebar = true;
$sidebarMode = $deptId > 0 ? 'department' : 'attendance';
$sidebarDeptId = $deptId;
$sidebarActive = 'attendance_manual';
$extraCss = [
    'https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css',
    'https://cdn.jsdelivr.net/npm/@dmuy/timepicker@2.0.1/dist/mdtimepicker.min.css',
];

require_once __DIR__ . '/../includes/header.php';

$toast = '';
if (isset($_GET['msg']) && $_GET['msg'] === 'saved') {
    $toast = 'Manual attendance saved for ' . (int) ($_GET['count'] ?? 0) . ' day cell(s).';
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
        <?php if ($show && $monthData): ?>
            <div class="toolbar-actions">
                <a class="btn-secondary" href="<?php echo htmlspecialchars(app_url('attendance/report_excel.php?' . $excelQs)); ?>">
                    <i class="fa-solid fa-file-excel"></i> Export Excel
                </a>
                <a class="btn-secondary" href="<?php echo htmlspecialchars(app_url('attendance/muster.php?' . $excelQs)); ?>">
                    <i class="fa-solid fa-table-cells"></i> Muster Report
                </a>
            </div>
        <?php endif; ?>
    </div>

    <div class="form-page-card">
        <div class="form-page-header">
            <div>
                <h1>Manual Attendance</h1>
                <p>All employees ek saath · Same Excel format · Click day cell for In/Out or Leave · Export same format</p>
            </div>
        </div>

        <form method="GET" class="employee-form" style="margin-bottom:12px;">
            <div class="form-grid form-grid-3">
                <div class="form-group">
                    <label>Department</label>
                    <select name="department_id" class="form-control">
                        <option value="0" <?php echo $deptId === 0 ? 'selected' : ''; ?>>All Departments / All Employees</option>
                        <?php foreach ($departments as $d): ?>
                            <option value="<?php echo (int) $d['id']; ?>" <?php echo $deptId === (int) $d['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($d['department_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Month *</label>
                    <select name="month" class="form-control" required>
                        <?php for ($m = 1; $m <= 12; $m++): ?>
                            <option value="<?php echo $m; ?>" <?php echo $month === $m ? 'selected' : ''; ?>>
                                <?php echo date('F', mktime(0, 0, 0, $m, 1)); ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Year *</label>
                    <select name="year" class="form-control" required>
                        <?php for ($y = (int) date('Y') - 1; $y <= (int) date('Y') + 1; $y++): ?>
                            <option value="<?php echo $y; ?>" <?php echo $year === $y ? 'selected' : ''; ?>><?php echo $y; ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Default Shift (for times)</label>
                    <select name="shift_id" id="shiftSelect" class="form-control">
                        <?php foreach ($shifts as $s): ?>
                            <?php
                            $inH = attendanceTimeForInput($s['start_time'] ?? '');
                            $outH = attendanceTimeForInput($s['end_time'] ?? '');
                            $inA = $inH ? date('g:i A', strtotime($inH)) : '';
                            $outA = $outH ? date('g:i A', strtotime($outH)) : '';
                            ?>
                            <option value="<?php echo (int) $s['id']; ?>"
                                    data-in="<?php echo htmlspecialchars($inA); ?>"
                                    data-out="<?php echo htmlspecialchars($outA); ?>"
                                    <?php echo $shiftId === (int) $s['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars(formatShiftOptionLabel($s)); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <input type="hidden" name="show" value="1">
            <div class="form-actions" style="margin-top:8px;">
                <button type="submit" class="btn-primary"><i class="fa-solid fa-table"></i> Load Excel Grid</button>
            </div>
        </form>

        <?php if ($show && $deptId > 0 && !$department): ?>
            <p class="form-hint">Department not found.</p>
        <?php elseif ($show && $monthData && empty($monthData['employees'])): ?>
            <p class="form-hint">No active employees found.</p>
        <?php elseif ($show && $monthData): ?>
            <div class="ops-live-summary" style="margin-bottom:12px;">
                <span class="ops-chip"><?php echo htmlspecialchars($deptLabel); ?></span>
                <span class="ops-chip"><?php echo htmlspecialchars(date('F Y', mktime(0, 0, 0, $month, 1, $year))); ?></span>
                <span class="ops-chip"><?php echo htmlspecialchars($shiftName ?: 'Shift'); ?> · <?php echo htmlspecialchars($defaultInDisp . ' → ' . $defaultOutDisp); ?></span>
                <span class="ops-chip"><?php echo count($monthData['employees']); ?> employees</span>
            </div>

            <form method="POST" action="<?php echo app_url('attendance/manual_save.php'); ?>" id="manualAttForm">
                <input type="hidden" name="department_id" value="<?php echo $deptId; ?>">
                <input type="hidden" name="month" value="<?php echo $month; ?>">
                <input type="hidden" name="year" value="<?php echo $year; ?>">
                <input type="hidden" name="shift_id" value="<?php echo $shiftId; ?>">
                <input type="hidden" name="shift_name" value="<?php echo htmlspecialchars($shiftName); ?>">

                <div class="form-actions" style="margin-bottom:12px; gap:8px; flex-wrap:wrap;">
                    <button type="button" class="btn-secondary" id="btnFillPresent">
                        <i class="fa-solid fa-check"></i> Fill Empty = Present (shift time)
                    </button>
                    <button type="button" class="btn-secondary" id="btnFillWeekOff">
                        <i class="fa-solid fa-calendar-week"></i> Mark Week Off (employee-wise)
                    </button>
                    <button type="submit" class="btn-primary">
                        <i class="fa-solid fa-floppy-disk"></i> Save Attendance
                    </button>
                </div>

                <div class="table-wrap excel-att-wrap">
                    <table class="data-table excel-att-table" id="manualAttTable">
                        <thead>
                            <tr>
                                <th class="sticky-col">Employee Code</th>
                                <th class="sticky-col-2">Employee Name</th>
                                <th>Designation</th>
                                <th>Department</th>
                                <th>Date of Joining</th>
                                <?php for ($d = 1; $d <= $monthDays; $d++): ?>
                                    <th class="day-col"><?php echo $d; ?></th>
                                <?php endfor; ?>
                                <th>PL</th>
                                <th>SL</th>
                                <th>C-Off</th>
                                <th>DL</th>
                                <th>LWP</th>
                                <th>Total Days</th>
                            </tr>
                            <tr class="dayname-row">
                                <th class="sticky-col"></th>
                                <th class="sticky-col-2"></th>
                                <th></th>
                                <th></th>
                                <th></th>
                                <?php for ($d = 1; $d <= $monthDays; $d++): ?>
                                    <th class="day-col day-name"><?php echo htmlspecialchars($dayNames[$d]); ?></th>
                                <?php endfor; ?>
                                <th></th><th></th><th></th><th></th><th></th>
                                <th><?php echo $monthDays; ?></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($monthData['employees'] as $emp):
                            $eid = (int) $emp['id'];
                            $totals = $monthData['leave_totals'][$eid] ?? ['PL' => 0, 'SL' => 0, 'C-Off' => 0, 'DL' => 0, 'LWP' => 0, 'total_days' => $monthDays];
                            $empWeekOff = trim((string) ($emp['week_off_day'] ?? ''));
                            if ($empWeekOff === '') {
                                $empWeekOff = 'Sunday';
                            }
                            ?>
                            <tr class="excel-emp-row" data-emp="<?php echo $eid; ?>" data-week-off="<?php echo htmlspecialchars($empWeekOff); ?>">
                                <td class="sticky-col"><span class="code-badge"><?php echo htmlspecialchars($emp['employee_code']); ?></span></td>
                                <td class="sticky-col-2">
                                    <strong><?php echo htmlspecialchars($emp['employee_name']); ?></strong>
                                    <div class="form-hint" style="margin:2px 0 0;font-size:11px;">Off: <?php echo htmlspecialchars($empWeekOff); ?></div>
                                </td>
                                <td><?php echo htmlspecialchars($emp['designation'] ?: '-'); ?></td>
                                <td><?php echo htmlspecialchars($emp['department_name'] ?: '-'); ?></td>
                                <td><?php echo htmlspecialchars(formatDateDisplay($emp['date_of_joining'] ?? '')); ?></td>
                                <?php for ($d = 1; $d <= $monthDays; $d++):
                                    $date = sprintf('%04d-%02d-%02d', $year, $month, $d);
                                    $day = $monthData['days'][$eid][$date] ?? null;
                                    $text = attendanceDayToExcelText($day ?: []);
                                    $parsed = attendanceParseLeaveRemark($day['remarks'] ?? '');
                                    $status = $day['day_status'] ?? '';
                                    $inDisp = !empty($day['punch_in']) ? date('g:i A', strtotime($day['punch_in'])) : '';
                                    $outDisp = !empty($day['punch_out']) ? date('g:i A', strtotime($day['punch_out'])) : '';
                                    $css = '';
                                    if ($status === 'Week Off') {
                                        $css = 'is-weekoff';
                                    } elseif ($status === 'Leave') {
                                        $css = 'is-leave';
                                    } elseif ($status === 'Half Day') {
                                        $css = 'is-half';
                                    } elseif ($status === 'Present') {
                                        $css = 'is-present';
                                    } elseif ($status === 'Holiday') {
                                        $css = 'is-holiday';
                                    } elseif ($status === 'Absent') {
                                        $css = 'is-absent';
                                    }
                                    ?>
                                    <td class="day-cell <?php echo $css; ?>"
                                        data-emp="<?php echo $eid; ?>"
                                        data-date="<?php echo $date; ?>"
                                        data-day="<?php echo $d; ?>"
                                        data-status="<?php echo htmlspecialchars($status); ?>"
                                        data-in="<?php echo htmlspecialchars($inDisp); ?>"
                                        data-out="<?php echo htmlspecialchars($outDisp); ?>"
                                        data-leave-type="<?php echo htmlspecialchars($parsed['leave_type']); ?>"
                                        data-leave-half="<?php echo htmlspecialchars($parsed['leave_half']); ?>"
                                        title="Click to edit">
                                        <div class="cell-text"><?php echo $text !== '' ? nl2br(htmlspecialchars($text)) : '<span class="cell-empty">+</span>'; ?></div>
                                        <input type="hidden" class="cell-status" name="cells[<?php echo $eid; ?>][<?php echo $date; ?>][status]" value="<?php echo htmlspecialchars($status); ?>">
                                        <input type="hidden" class="cell-in" name="cells[<?php echo $eid; ?>][<?php echo $date; ?>][punch_in]" value="<?php echo htmlspecialchars($inDisp); ?>">
                                        <input type="hidden" class="cell-out" name="cells[<?php echo $eid; ?>][<?php echo $date; ?>][punch_out]" value="<?php echo htmlspecialchars($outDisp); ?>">
                                        <input type="hidden" class="cell-leave-type" name="cells[<?php echo $eid; ?>][<?php echo $date; ?>][leave_type]" value="<?php echo htmlspecialchars($parsed['leave_type']); ?>">
                                        <input type="hidden" class="cell-leave-half" name="cells[<?php echo $eid; ?>][<?php echo $date; ?>][leave_half]" value="<?php echo htmlspecialchars($parsed['leave_half']); ?>">
                                        <input type="hidden" class="cell-touched" name="cells[<?php echo $eid; ?>][<?php echo $date; ?>][touched]" value="0">
                                    </td>
                                <?php endfor; ?>
                                <td class="tot-pl"><?php echo $totals['PL'] > 0 ? rtrim(rtrim(number_format($totals['PL'], 1), '0'), '.') : ''; ?></td>
                                <td class="tot-sl"><?php echo $totals['SL'] > 0 ? rtrim(rtrim(number_format($totals['SL'], 1), '0'), '.') : ''; ?></td>
                                <td class="tot-coff"><?php echo $totals['C-Off'] > 0 ? rtrim(rtrim(number_format($totals['C-Off'], 1), '0'), '.') : ''; ?></td>
                                <td class="tot-dl"><?php echo $totals['DL'] > 0 ? rtrim(rtrim(number_format($totals['DL'], 1), '0'), '.') : ''; ?></td>
                                <td class="tot-lwp"><?php echo $totals['LWP'] > 0 ? rtrim(rtrim(number_format($totals['LWP'], 1), '0'), '.') : ''; ?></td>
                                <td class="tot-days"><?php echo (int) $totals['total_days']; ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="form-hint" style="margin-top:10px;">
                    Click day cell → set like Excel:
                    <code>9:00 AM | 06:00 PM</code> ·
                    <code>PL SHF</code> ·
                    <code>PL FHF</code> ·
                    <code>PL</code>/<code>SL</code> ·
                    <code>week off</code>
                </div>

                <div class="form-actions sticky-actions">
                    <button type="submit" class="btn-primary">
                        <i class="fa-solid fa-floppy-disk"></i> Save Attendance
                    </button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</main>

<!-- Cell editor modal -->
<div id="attCellModal" class="att-cell-modal" hidden>
    <div class="att-cell-modal-backdrop"></div>
    <div class="att-cell-modal-box">
        <h3 id="attCellTitle">Edit Day</h3>
        <div class="form-grid form-grid-2">
            <div class="form-group">
                <label>Status</label>
                <select id="mStatus" class="form-control no-select2">
                    <option value="Present">Present (full day)</option>
                    <option value="Half Day">Half Day + Leave</option>
                    <option value="Leave">Leave (full day)</option>
                    <option value="Week Off">Week Off</option>
                    <option value="Holiday">Holiday</option>
                    <option value="Absent">Absent</option>
                    <option value="">Clear</option>
                </select>
            </div>
            <div class="form-group" id="mLeaveWrap">
                <label>Leave Box</label>
                <div class="leave-box-inline">
                    <select id="mLeaveType" class="form-control no-select2">
                        <option value="">Leave type</option>
                        <option value="PL">PL</option>
                        <option value="SL">SL</option>
                        <option value="C-Off">C-Off</option>
                        <option value="DL">DL</option>
                        <option value="LWP">LWP</option>
                    </select>
                    <select id="mLeaveHalf" class="form-control no-select2">
                        <option value="FULL">Full Day</option>
                        <option value="FHF">FHF (1st half leave)</option>
                        <option value="SHF">SHF (2nd half leave)</option>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label>In Time</label>
                <div class="att-time-wrap">
                    <input type="text" id="mIn" class="form-control js-time-modal mdtimepicker-input" placeholder="Click for round clock" autocomplete="off" readonly>
                </div>
            </div>
            <div class="form-group">
                <label>Out Time</label>
                <div class="att-time-wrap">
                    <input type="text" id="mOut" class="form-control js-time-modal mdtimepicker-input" placeholder="Click for round clock" autocomplete="off" readonly>
                </div>
            </div>
        </div>
        <div class="excel-cell-preview" id="mPreview" style="margin:10px 0;">-</div>
        <div class="form-actions" style="gap:8px;">
            <button type="button" class="btn-primary" id="mApply">Apply</button>
            <button type="button" class="btn-secondary" id="mCancel">Cancel</button>
        </div>
    </div>
</div>

<script>
window.ATT_DEFAULT_IN = <?php echo json_encode($defaultInDisp); ?>;
window.ATT_DEFAULT_OUT = <?php echo json_encode($defaultOutDisp); ?>;
window.ATT_MONTH_DAYS = <?php echo (int) $monthDays; ?>;
<?php if ($toast): ?>
window.ATT_TOAST = <?php echo json_encode($toast); ?>;
<?php endif; ?>
</script>
<?php
$extraJs = [
    'https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js',
    'https://cdn.jsdelivr.net/npm/@dmuy/timepicker@2.0.1/dist/mdtimepicker.min.js',
    'assets/js/attendance_manual.js',
];
require_once __DIR__ . '/../includes/footer.php';
?>
