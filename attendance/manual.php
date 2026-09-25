<?php
/**
 * Manual Attendance — Excel-style monthly grid (same as Attendance Report.xlsx)
 * Columns: Code, Name, Designation, Department, DOJ, Days 1-31,
 *          Present Days, Week Off, PL, SL, DL, C-Off, Holiday, Total Days, Total Pay Days
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/employee_helper.php';
require_once __DIR__ . '/../includes/master_helper.php';
require_once __DIR__ . '/../includes/attendance_helper.php';

requireLogin();
require_once __DIR__ . '/../includes/permission_helper.php';
$deptId = isset($_GET['department_id']) ? (int) $_GET['department_id'] : 0;
requireAccess('attendance', 'edit', $deptId);
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
$toastType = 'success';
if (isset($_GET['msg']) && $_GET['msg'] === 'saved') {
    $toast = 'Manual attendance saved for ' . (int) ($_GET['count'] ?? 0) . ' day cell(s).';
} elseif (isset($_GET['msg']) && $_GET['msg'] === 'error') {
    $toastType = 'error';
    $err = (string) ($_GET['err'] ?? '');
    $toast = $err === 'nocells'
        ? 'Nothing to save. Edit cells (or Fill Present / Week Off), then Save again.'
        : 'Could not save attendance. Please try again.';
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
                <p>Mark attendance like HR software · Click any day cell · Present / Leave / FHL / SHL / Week Off</p>
            </div>
        </div>

        <form method="GET" class="employee-form att-filter-bar">
            <div class="form-grid form-grid-4">
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
                    <label>Default Shift</label>
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
                <button type="submit" class="btn-primary"><i class="fa-solid fa-table"></i> Load Attendance Sheet</button>
            </div>
        </form>

        <?php if ($show && $deptId > 0 && !$department): ?>
            <p class="form-hint">Department not found.</p>
        <?php elseif ($show && $monthData && empty($monthData['employees'])): ?>
            <p class="form-hint">No active employees found.</p>
        <?php elseif ($show && $monthData): ?>
            <div class="att-sheet-toolbar">
                <div class="ops-live-summary" style="margin:0;">
                    <span class="ops-chip"><?php echo htmlspecialchars($deptLabel); ?></span>
                    <span class="ops-chip"><?php echo htmlspecialchars(date('F Y', mktime(0, 0, 0, $month, 1, $year))); ?></span>
                    <span class="ops-chip"><?php echo htmlspecialchars($shiftName ?: 'Shift'); ?> · <?php echo htmlspecialchars($defaultInDisp . ' → ' . $defaultOutDisp); ?></span>
                    <span class="ops-chip"><?php echo count($monthData['employees']); ?> employees</span>
                </div>
                <div class="att-legend">
                    <span class="att-leg is-present"><b>P</b> Present</span>
                    <span class="att-leg is-half"><b>FHL/SHL</b> Half</span>
                    <span class="att-leg is-leave"><b>L</b> Leave</span>
                    <span class="att-leg is-absent"><b>A</b> Absent</span>
                    <span class="att-leg is-weekoff"><b>WO</b> Week Off</span>
                    <span class="att-leg is-holiday"><b>H</b> Holiday</span>
                </div>
            </div>

            <form method="POST" action="<?php echo app_url('attendance/manual_save.php'); ?>" id="manualAttForm">
                <input type="hidden" name="department_id" value="<?php echo $deptId; ?>">
                <input type="hidden" name="month" value="<?php echo $month; ?>">
                <input type="hidden" name="year" value="<?php echo $year; ?>">
                <input type="hidden" name="shift_id" value="<?php echo $shiftId; ?>">
                <input type="hidden" name="shift_name" value="<?php echo htmlspecialchars($shiftName); ?>">
                <input type="hidden" name="cells_json" id="cellsJson" value="">

                <div class="att-quick-actions">
                    <button type="button" class="btn-secondary" id="btnFillPresent">
                        <i class="fa-solid fa-user-check"></i> Mark All Present
                    </button>
                    <button type="button" class="btn-secondary" id="btnFillWeekOff">
                        <i class="fa-solid fa-calendar-week"></i> Mark Week Offs
                    </button>
                    <button type="submit" class="btn-primary js-save-att" id="btnSaveAttendance">
                        <i class="fa-solid fa-floppy-disk"></i> Save Attendance
                    </button>
                </div>

                <div class="table-wrap excel-att-wrap att-facto-sheet">
                    <table class="data-table excel-att-table" id="manualAttTable">
                        <thead>
                            <tr>
                                <th class="sticky-col">Code</th>
                                <th class="sticky-col-2">Employee</th>
                                <th>Designation</th>
                                <th>Department</th>
                                <th>DOJ</th>
                                <?php for ($d = 1; $d <= $monthDays; $d++):
                                    $dow = $dayNames[$d];
                                    $isWe = in_array($dow, ['Sat', 'Sun'], true);
                                    ?>
                                    <th class="day-col <?php echo $isWe ? 'is-weekend-col' : ''; ?>">
                                        <span class="day-num"><?php echo $d; ?></span>
                                        <span class="day-name"><?php echo htmlspecialchars($dow); ?></span>
                                    </th>
                                <?php endfor; ?>
                                <th class="sum-col">Present Days</th>
                                <th class="sum-col">Week Off</th>
                                <th class="sum-col">PL</th>
                                <th class="sum-col">SL</th>
                                <th class="sum-col">DL</th>
                                <th class="sum-col">C-Off</th>
                                <th class="sum-col">Holiday</th>
                                <th class="sum-col">Total Days</th>
                                <th class="sum-col">Total Pay Days</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($monthData['employees'] as $emp):
                            $eid = (int) $emp['id'];
                            $totals = $monthData['leave_totals'][$eid] ?? attendanceEmptyLeaveTotals();
                            $empWeekOff = trim((string) ($emp['week_off_day'] ?? ''));
                            if ($empWeekOff === '') {
                                $empWeekOff = 'Sunday';
                            }
                            $empShift = attendanceResolveEmployeeShiftTimes($emp, $shifts, $defaultInDisp, $defaultOutDisp);
                            $fmtTot = static function ($n) {
                                $n = (float) $n;
                                if ($n <= 0) {
                                    return '';
                                }
                                return rtrim(rtrim(number_format($n, 1, '.', ''), '0'), '.');
                            };
                            ?>
                            <tr class="excel-emp-row"
                                data-emp="<?php echo $eid; ?>"
                                data-week-off="<?php echo htmlspecialchars($empWeekOff); ?>"
                                data-shift-in="<?php echo htmlspecialchars($empShift['in']); ?>"
                                data-shift-out="<?php echo htmlspecialchars($empShift['out']); ?>">
                                <td class="sticky-col"><span class="code-badge"><?php echo htmlspecialchars($emp['employee_code']); ?></span></td>
                                <td class="sticky-col-2">
                                    <strong><?php echo htmlspecialchars($emp['employee_name']); ?></strong>
                                    <div class="emp-shift-meta">
                                        <span><i class="fa-regular fa-calendar"></i> <?php echo htmlspecialchars($empWeekOff); ?></span>
                                        <span><i class="fa-regular fa-clock"></i> <?php echo htmlspecialchars($empShift['in'] . ' – ' . $empShift['out']); ?></span>
                                    </div>
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
                                    $half = strtoupper((string) ($parsed['leave_half'] ?? ''));
                                    $css = '';
                                    if (in_array($half, ['FHL', 'SHL', 'FHF', 'SHF'], true) || $status === 'Half Day') {
                                        $css = 'is-half';
                                    } elseif ($status === 'Week Off') {
                                        $css = 'is-weekoff';
                                    } elseif ($status === 'Leave') {
                                        $css = 'is-leave';
                                    } elseif ($status === 'Present') {
                                        $css = 'is-present';
                                    } elseif ($status === 'Holiday') {
                                        $css = 'is-holiday';
                                    } elseif ($status === 'Absent') {
                                        $css = 'is-absent';
                                    }
                                    $dow = $dayNames[$d];
                                    if (in_array($dow, ['Sat', 'Sun'], true)) {
                                        $css .= ' is-weekend-col';
                                    }
                                    $cellInner = '';
                                    if ($text !== '') {
                                        $lines = preg_split("/\r\n|\n|\r/", $text);
                                        $cellInner = '<span class="att-mark">' . htmlspecialchars($lines[0]) . '</span>';
                                        if (!empty($lines[1])) {
                                            $cellInner .= '<span class="att-time">' . htmlspecialchars($lines[1]) . '</span>';
                                        }
                                    } else {
                                        $cellInner = '<span class="cell-empty">+</span>';
                                    }
                                    ?>
                                    <td class="day-cell <?php echo trim($css); ?>"
                                        data-emp="<?php echo $eid; ?>"
                                        data-date="<?php echo $date; ?>"
                                        data-day="<?php echo $d; ?>"
                                        data-status="<?php echo htmlspecialchars($status); ?>"
                                        data-in="<?php echo htmlspecialchars($inDisp); ?>"
                                        data-out="<?php echo htmlspecialchars($outDisp); ?>"
                                        data-leave-type="<?php echo htmlspecialchars($parsed['leave_type']); ?>"
                                        data-leave-half="<?php echo htmlspecialchars($parsed['leave_half']); ?>"
                                        title="Click to edit">
                                        <div class="cell-text"><?php echo $cellInner; ?></div>
                                        <input type="hidden" class="cell-status" name="cells[<?php echo $eid; ?>][<?php echo $date; ?>][status]" value="<?php echo htmlspecialchars($status); ?>">
                                        <input type="hidden" class="cell-in" name="cells[<?php echo $eid; ?>][<?php echo $date; ?>][punch_in]" value="<?php echo htmlspecialchars($inDisp); ?>">
                                        <input type="hidden" class="cell-out" name="cells[<?php echo $eid; ?>][<?php echo $date; ?>][punch_out]" value="<?php echo htmlspecialchars($outDisp); ?>">
                                        <input type="hidden" class="cell-leave-type" name="cells[<?php echo $eid; ?>][<?php echo $date; ?>][leave_type]" value="<?php echo htmlspecialchars($parsed['leave_type']); ?>">
                                        <input type="hidden" class="cell-leave-half" name="cells[<?php echo $eid; ?>][<?php echo $date; ?>][leave_half]" value="<?php echo htmlspecialchars($parsed['leave_half']); ?>">
                                        <input type="hidden" class="cell-touched" name="cells[<?php echo $eid; ?>][<?php echo $date; ?>][touched]" value="0">
                                    </td>
                                <?php endfor; ?>
                                <td class="tot-present sum-col"><?php echo $fmtTot($totals['present'] ?? 0) !== '' ? $fmtTot($totals['present'] ?? 0) : '0'; ?></td>
                                <td class="tot-wo sum-col"><?php echo $fmtTot($totals['week_off'] ?? 0); ?></td>
                                <td class="tot-pl sum-col"><?php echo $fmtTot($totals['PL'] ?? 0); ?></td>
                                <td class="tot-sl sum-col"><?php echo $fmtTot($totals['SL'] ?? 0); ?></td>
                                <td class="tot-dl sum-col"><?php echo $fmtTot($totals['DL'] ?? 0); ?></td>
                                <td class="tot-coff sum-col"><?php echo $fmtTot($totals['C-Off'] ?? 0); ?></td>
                                <td class="tot-holiday sum-col"><?php echo $fmtTot($totals['holiday'] ?? 0); ?></td>
                                <td class="tot-days sum-col"><?php echo $fmtTot($totals['total_days'] ?? 0) !== '' ? $fmtTot($totals['total_days'] ?? 0) : '0'; ?></td>
                                <td class="tot-pay-days sum-col"><?php echo $fmtTot($totals['total_pay_days'] ?? 0) !== '' ? $fmtTot($totals['total_pay_days'] ?? 0) : '0'; ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="att-help-bar">
                    <span><i class="fa-solid fa-hand-pointer"></i> Click day cell to mark</span>
                    <span><strong>FHL / SHL</strong> = half leave + punch time</span>
                    <span><strong>PL / SL</strong> = full leave</span>
                    <span>Empty <strong>+</strong> = not marked</span>
                </div>
            </form>
        <?php endif; ?>
    </div>
</main>

<!-- Cell editor modal -->
<div id="attCellModal" class="att-cell-modal" hidden>
    <div class="att-cell-modal-backdrop"></div>
    <div class="att-cell-modal-box att-facto-modal">
        <div class="att-modal-head">
            <h3 id="attCellTitle">Mark Attendance</h3>
            <p class="att-modal-sub">Select status · set leave / time · Apply</p>
        </div>

        <div class="att-status-chips" role="group" aria-label="Attendance status">
            <button type="button" class="att-status-chip is-present" data-status="Present"><b>P</b> Present</button>
            <button type="button" class="att-status-chip is-half" data-status="Half Day"><b>HD</b> Half</button>
            <button type="button" class="att-status-chip is-leave" data-status="Leave"><b>L</b> Leave</button>
            <button type="button" class="att-status-chip is-weekoff" data-status="Week Off"><b>WO</b> Week Off</button>
            <button type="button" class="att-status-chip is-holiday" data-status="Holiday"><b>H</b> Holiday</button>
            <button type="button" class="att-status-chip is-absent" data-status="Absent"><b>A</b> Absent</button>
            <button type="button" class="att-status-chip is-clear" data-status=""><b>×</b> Clear</button>
        </div>

        <div class="form-grid form-grid-2" style="margin-top:12px;">
            <div class="form-group" style="display:none;">
                <label>Status</label>
                <select id="mStatus" class="form-control no-select2">
                    <option value="Present">Present</option>
                    <option value="Half Day">Half Day + Leave</option>
                    <option value="Leave">Leave</option>
                    <option value="Week Off">Week Off</option>
                    <option value="Holiday">Holiday</option>
                    <option value="Absent">Absent</option>
                    <option value="">Clear</option>
                </select>
            </div>
            <div class="form-group" id="mLeaveWrap" style="grid-column: span 2;">
                <label>Leave Type &amp; Duration</label>
                <div class="leave-box-inline">
                    <select id="mLeaveType" class="form-control no-select2">
                        <option value="">Leave type</option>
                        <option value="PL">PL — Privilege</option>
                        <option value="SL">SL — Sick</option>
                        <option value="C-Off">C-Off</option>
                        <option value="DL">DL</option>
                        <option value="LWP">LWP</option>
                    </select>
                    <select id="mLeaveHalf" class="form-control no-select2">
                        <option value="FULL">Full Day</option>
                        <option value="FHL">FHL — First Half</option>
                        <option value="SHL">SHL — Second Half</option>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label>Punch In</label>
                <div class="att-time-wrap">
                    <input type="text" id="mIn" class="form-control js-time-modal" placeholder="Type e.g. 9:00 AM" autocomplete="off" inputmode="text">
                    <input type="text" id="mInPicker" class="att-time-picker-proxy" tabindex="-1" aria-hidden="true" readonly>
                    <button type="button" class="att-time-clock-btn" data-input="#mIn" data-picker="#mInPicker" title="Open clock" aria-label="Open clock">
                        <i class="fa-regular fa-clock"></i>
                    </button>
                </div>
            </div>
            <div class="form-group">
                <label>Punch Out</label>
                <div class="att-time-wrap">
                    <input type="text" id="mOut" class="form-control js-time-modal" placeholder="Type e.g. 6:00 PM" autocomplete="off" inputmode="text">
                    <input type="text" id="mOutPicker" class="att-time-picker-proxy" tabindex="-1" aria-hidden="true" readonly>
                    <button type="button" class="att-time-clock-btn" data-input="#mOut" data-picker="#mOutPicker" title="Open clock" aria-label="Open clock">
                        <i class="fa-regular fa-clock"></i>
                    </button>
                </div>
            </div>
        </div>
        <div class="excel-cell-preview att-live-preview" id="mPreview">-</div>
        <div class="form-actions" style="gap:8px;margin-top:12px;">
            <button type="button" class="btn-primary" id="mApply"><i class="fa-solid fa-check"></i> Apply</button>
            <button type="button" class="btn-secondary" id="mCancel">Cancel</button>
        </div>
    </div>
</div>

<script>
window.ATT_DEFAULT_IN = <?php echo json_encode($defaultInDisp); ?>;
window.ATT_DEFAULT_OUT = <?php echo json_encode($defaultOutDisp); ?>;
window.ATT_MONTH_DAYS = <?php echo (int) $monthDays; ?>;
<?php
$attHolidayDates = [];
if ($show && $deptId > 0) {
    $connH = getDBConnection();
    $hSet = attendanceHolidaySet($connH, $year, $month, $deptId);
    $connH->close();
    foreach (array_keys($hSet) as $hd) {
        $attHolidayDates[$hd] = 1;
    }
}
?>
window.ATT_HOLIDAY_DATES = <?php echo json_encode($attHolidayDates); ?>;
<?php if ($toast): ?>
window.ATT_TOAST = <?php echo json_encode($toast); ?>;
window.ATT_TOAST_TYPE = <?php echo json_encode($toastType); ?>;
<?php endif; ?>
</script>
<?php
$extraJs = [
    'https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js',
    'https://cdn.jsdelivr.net/npm/@dmuy/timepicker@2.0.1/dist/mdtimepicker.min.js',
    'assets/js/attendance_manual.js?v=' . (string) @filemtime(__DIR__ . '/../assets/js/attendance_manual.js'),
];
require_once __DIR__ . '/../includes/footer.php';
?>
