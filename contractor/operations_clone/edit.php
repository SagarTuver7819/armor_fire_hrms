<?php
/**
 * Clone Operation Rate List — Edit
 * Two separate boxes (each with own Save):
 *  1) Product qty grid (Ops Rate List style)
 *  2) CNC Normal Salary staff + month-wise attendance days 1–31
 */
require_once __DIR__ . '/../_bootstrap.php';
require_once __DIR__ . '/../../includes/contractor_split_helper.php';
require_once __DIR__ . '/../../includes/attendance_helper.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$conn = getDBConnection();
ensureContractorTables($conn);
$sheet = $id > 0 ? getContractorSheetById($id, $conn) : null;
if ($id > 0 && !$sheet) {
    $conn->close();
    header('Location: ' . app_url('contractor/operations_clone/index.php'));
    exit;
}
$employees = getActiveContractorEmployees($conn);
$ops = contractorOperations();
$months = contractorMonths();
$prefillOp = trim((string) ($_GET['operation'] ?? ''));
$prefillEmp = (int) ($_GET['employee_id'] ?? 0);
$curOp = (string) ($sheet['operation'] ?? ($prefillOp !== '' && isset($ops[$prefillOp]) ? $prefillOp : 'CNC'));
$curEmp = (int) ($sheet['employee_id'] ?? 0);
if ($curEmp <= 0 && $prefillEmp > 0) {
    $curEmp = $prefillEmp;
}
$curMonth = (int) ($_GET['month'] ?? ($sheet['month_no'] ?? date('n')));
$curYear = (int) ($_GET['year'] ?? ($sheet['year_no'] ?? date('Y')));
if ($curMonth < 1 || $curMonth > 12) {
    $curMonth = (int) date('n');
}
if ($curYear < 2000 || $curYear > 2100) {
    $curYear = (int) date('Y');
}
$employees = ensureOpsEmployeeInList($employees, $curEmp, $conn);
$staffBundle = getOpsCloneDepartmentStaff($curOp, $curEmp, $conn);
$attMap = [];
if (!empty($staffBundle['rows'])) {
    $attMap = getOpsCloneStaffAttendanceMap($staffBundle['rows'], $curMonth, $curYear, $conn);
}

$items = $sheet['items'] ?? [];
$kraAsOf = sprintf('%04d-%02d-02', $curYear, $curMonth);
$kraPrefill = [];
if (strcasecmp($curOp, 'CNC') === 0) {
    // Auto rows = ERP CNC Production Planning KRA part names (fixed — no dropdown)
    $kraSync = syncErpKraPartsToContractorProducts('CNC', $conn, $kraAsOf);
    $kraNameSet = [];
    foreach (($kraSync['names'] ?? []) as $pn) {
        $kraNameSet[strtolower(trim((string) $pn))] = true;
        foreach (contractorKraPartNameAliases($pn) as $al) {
            $kraNameSet[strtolower(trim((string) $al))] = true;
        }
    }
    $allCnc = getContractorProductsByOperation('CNC', $conn);
    $best = [];
    foreach ($allCnc as $pr) {
        if (!contractorProductMatchesKraNames($pr, $kraNameSet)) {
            continue;
        }
        $nk = strtolower(trim((string) ($pr['product_name'] ?? '')));
        if ($nk === '') {
            $nk = strtolower(trim((string) ($pr['process'] ?? '')));
        }
        $matchedCanon = (string) ($pr['product_name'] ?? $pr['process'] ?? '');
        foreach (($kraSync['names'] ?? []) as $canon) {
            $ck = strtolower(trim((string) $canon));
            $aliases = array_map('strtolower', contractorKraPartNameAliases($canon));
            if ($nk === $ck || in_array($nk, $aliases, true)) {
                $nk = $ck;
                $matchedCanon = (string) $canon;
                break;
            }
        }
        $rate = (float) ($pr['rate'] ?? 0);
        if (!isset($best[$nk]) || $rate > (float) ($best[$nk]['rate'] ?? 0)) {
            $best[$nk] = $pr;
            $best[$nk]['_canon'] = $matchedCanon;
        }
    }
    foreach (($kraSync['names'] ?? []) as $canon) {
        $ck = strtolower(trim((string) $canon));
        if (!isset($best[$ck])) {
            continue;
        }
        $pr = $best[$ck];
        $pid = (int) ($pr['id'] ?? 0);
        $saved = null;
        foreach ($items as $it) {
            if ((int) ($it['product_id'] ?? 0) === $pid) {
                $saved = $it;
                break;
            }
        }
        $kraPrefill[] = [
            'product_id' => $pid,
            'product_label' => (string) $canon,
            'grade_id' => (int) ($saved['grade_id'] ?? 0),
            'grade_name' => (string) ($saved['grade_name'] ?? ''),
            'rate' => $saved !== null ? ($saved['rate'] ?? $pr['rate']) : ($pr['rate'] ?? 0),
            'ot_rate' => $saved !== null ? ($saved['ot_rate'] ?? 0) : parseOtRate($pr['ot_text'] ?? ''),
            'rejection_rate' => $saved !== null ? ($saved['rejection_rate'] ?? 0) : ($pr['rejection_rate'] ?? 0),
            'days' => $saved['days'] ?? [],
            'total_qty' => $saved['total_qty'] ?? 0,
            'total_r' => $saved['total_r'] ?? 0,
            'total_amount' => $saved['total_amount'] ?? 0,
        ];
        unset($best[$ck]);
    }
    foreach ($best as $pr) {
        $pid = (int) ($pr['id'] ?? 0);
        $saved = null;
        foreach ($items as $it) {
            if ((int) ($it['product_id'] ?? 0) === $pid) {
                $saved = $it;
                break;
            }
        }
        $kraPrefill[] = [
            'product_id' => $pid,
            'product_label' => (string) ($pr['_canon'] ?? $pr['product_name'] ?? $pr['process'] ?? ''),
            'grade_id' => (int) ($saved['grade_id'] ?? 0),
            'grade_name' => (string) ($saved['grade_name'] ?? ''),
            'rate' => $saved !== null ? ($saved['rate'] ?? $pr['rate']) : ($pr['rate'] ?? 0),
            'ot_rate' => $saved !== null ? ($saved['ot_rate'] ?? 0) : parseOtRate($pr['ot_text'] ?? ''),
            'rejection_rate' => $saved !== null ? ($saved['rejection_rate'] ?? 0) : ($pr['rejection_rate'] ?? 0),
            'days' => $saved['days'] ?? [],
            'total_qty' => $saved['total_qty'] ?? 0,
            'total_r' => $saved['total_r'] ?? 0,
            'total_amount' => $saved['total_amount'] ?? 0,
        ];
    }
    if ($kraPrefill) {
        $items = $kraPrefill;
    }
}
if (!$items) {
    $items = [[
        'product_id' => 0,
        'grade_id' => 0,
        'rate' => 0,
        'ot_rate' => 0,
        'rejection_rate' => 0,
        'days' => [],
        'total_qty' => 0,
        'total_r' => 0,
        'total_amount' => 0,
    ]];
}
$conn->close();

$showGrade = contractorOperationShowsGrade($curOp);
$showStaff = in_array($curOp, opsCloneStaffSalaryOperations(), true);
$monthDays = (int) date('t', mktime(0, 0, 0, $curMonth, 1, $curYear));

$pageTitle = $sheet ? 'Edit Clone Operation Rate List' : 'Add Clone Operation Rate List';
$useSidebar = true;
$sidebarMode = 'contractor';
$sidebarActive = 'contractor_operations_clone';
$toastMsg = '';
if (isset($_GET['msg'])) {
    $map = [
        'products_saved' => 'Product rate list saved.',
        'staff_saved' => 'CNC staff salary + attendance saved.',
    ];
    $toastMsg = $map[$_GET['msg']] ?? '';
}
$extraCss = ['https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css'];
require_once __DIR__ . '/../../includes/header.php';
$repairJson = json_encode(contractorRepairOps());
$otJson = json_encode(contractorOtRepairOps());
?>
<main class="dashboard-main">
    <div class="page-toolbar flex-between">
        <a href="<?php echo app_url('contractor/operations_clone/index.php'); ?>" class="back-link"><i class="fa-solid fa-arrow-left"></i> Back to Clone Rate List</a>
        <?php if ($id > 0 && $showStaff): ?>
            <div class="toolbar-actions" style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;">
                <label for="dailyPrintDay" style="font-size:13px;margin:0;">Daily Print Day</label>
                <select id="dailyPrintDay" class="form-control" style="width:auto;min-width:70px;">
                    <?php
                    $todayDay = (int) date('j');
                    $defaultDay = ($curMonth === (int) date('n') && $curYear === (int) date('Y'))
                        ? min($todayDay, $monthDays)
                        : 1;
                    for ($d = 1; $d <= $monthDays; $d++):
                        ?>
                        <option value="<?php echo $d; ?>" <?php echo $d === $defaultDay ? 'selected' : ''; ?>><?php echo $d; ?></option>
                    <?php endfor; ?>
                </select>
                <a href="#" id="btnDailyPortrait" class="btn-secondary" title="Letter pad · Portrait">
                    <i class="fa-solid fa-print"></i> Daily Portrait
                </a>
                <a href="#" id="btnDailyLandscape" class="btn-secondary" title="Letter pad · Landscape">
                    <i class="fa-solid fa-print"></i> Daily Landscape
                </a>
            </div>
        <?php endif; ?>
    </div>

    <!-- ========== BOX 1: Product qty grid ========== -->
    <div class="form-page-card ops-page-card ops-clone-box">
        <div class="form-page-header">
            <div>
                <h1><?php echo $sheet ? 'Edit' : 'Add'; ?> — Product Rate Grid</h1>
                <p>Box 1 · Contract process qty × rate · Own Save (does not save staff attendance).</p>
            </div>
        </div>

        <form method="POST" action="<?php echo app_url('contractor/operations_clone/save.php'); ?>" id="opsForm" class="employee-form" autocomplete="off">
            <input type="hidden" name="id" value="<?php echo (int) $id; ?>">
            <input type="hidden" name="items_json" id="opsItemsJson" value="">
            <div class="form-section">
                <div class="form-grid form-grid-3">
                    <div class="form-group">
                        <label>Operation <span class="req">*</span></label>
                        <select name="operation" id="operationSelect" class="form-control" required>
                            <?php foreach ($ops as $op): ?>
                                <option value="<?php echo htmlspecialchars($op); ?>" <?php echo $curOp === $op ? 'selected' : ''; ?>><?php echo htmlspecialchars($op); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Name <span class="req">*</span></label>
                        <select name="employee_id" id="opsEmployeeSelect" class="form-control" required
                                data-current-emp="<?php echo (int) $curEmp; ?>">
                            <option value="">Select Employee</option>
                            <?php foreach ($employees as $emp): ?>
                                <option value="<?php echo (int) $emp['id']; ?>" <?php echo $curEmp === (int) $emp['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars(contractorEmployeeOptionLabel($emp)); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Month <span class="req">*</span></label>
                        <select name="month_no" id="monthSelect" class="form-control" required>
                            <?php foreach ($months as $num => $lab): ?>
                                <option value="<?php echo (int) $num; ?>" <?php echo $curMonth === (int) $num ? 'selected' : ''; ?>><?php echo htmlspecialchars($lab); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Year <span class="req">*</span></label>
                        <select name="year_no" id="yearSelect" class="form-control" required>
                            <?php for ($y = 2024; $y <= 2032; $y++): ?>
                                <option value="<?php echo $y; ?>" <?php echo $curYear === $y ? 'selected' : ''; ?>><?php echo $y; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>
            </div>

            <div class="ops-toolbar">
                <?php if (strcasecmp($curOp, 'CNC') !== 0): ?>
                <button type="button" class="btn-primary" id="btnAddProduct"><i class="fa-solid fa-plus"></i> Add Product Row</button>
                <?php else: ?>
                <div class="ops-kra-hint" style="font-size:13px;color:#64748b;">
                    <i class="fa-solid fa-link"></i> Contract Process = ERP CNC Production Planning KRA (fixed). Rate edit કરો; નવું ERP partname આપમેળે આવશે.
                </div>
                <?php endif; ?>
                <div class="ops-live-summary">
                    <span class="ops-chip"><span id="sum_qty_lab">Qty</span> <b id="sum_qty">0</b></span>
                    <span class="ops-chip"><span id="sum_r_lab">R</span> <b id="sum_r">0</b></span>
                    <span class="ops-chip amt">Amount <b id="sum_amt">0.00</b></span>
                </div>
            </div>

            <div class="ops-grid-wrap">
                <table class="ops-grid <?php echo $showGrade ? 'show-grade' : 'hide-grade'; ?>" id="opsGrid">
                    <thead>
                        <tr>
                            <th class="sticky-col sticky-action">Action</th>
                            <th class="sticky-col sticky-sr">Sr</th>
                            <th class="sticky-col sticky-name" id="col_header_name">Contract Process</th>
                            <th class="sticky-col sticky-grade" id="col_header_grade">GRADE</th>
                            <th class="sticky-col sticky-rate">Rate</th>
                            <?php for ($d = 1; $d <= 31; $d++):
                                $wd = checkdate($curMonth, $d, $curYear) ? date('D', strtotime(sprintf('%04d-%02d-%02d', $curYear, $curMonth, $d))) : '';
                                ?>
                                <th class="day-head" data-day="<?php echo $d; ?>">
                                    <span class="day-no"><?php echo $d; ?></span>
                                    <span class="day-wd"><?php echo htmlspecialchars($wd); ?></span>
                                    <div class="ops-subhead"><span class="q-lab">Q</span><span class="r-lab">R</span><span class="ot-lab">OT</span></div>
                                </th>
                            <?php endfor; ?>
                            <th id="th_total_qty">TOTAL QTY</th>
                            <th id="th_total_r">TOTAL R</th>
                            <th>OT RATE</th>
                            <th>REJ. RATE</th>
                            <th id="th_amount">TOTAL AMT</th>
                        </tr>
                    </thead>
                    <tbody id="opsRows">
                    <?php foreach ($items as $i => $item):
                        $days = mergeDayMap($item['days'] ?? [], $curMonth, $curYear);
                        $itemGradeId = (int) ($item['grade_id'] ?? 0);
                        $itemProductId = (int) ($item['product_id'] ?? 0);
                        $itemProductLabel = trim((string) ($item['product_label'] ?? ''));
                        if ($itemProductLabel === '' && $itemProductId > 0) {
                            $itemProductLabel = 'Product #' . $itemProductId;
                        }
                        $itemGradeName = trim((string) ($item['grade_name'] ?? ''));
                        ?>
                        <tr class="ops-row<?php echo strcasecmp($curOp, 'CNC') === 0 ? ' ops-kra-fixed-row' : ''; ?>" data-saved-product-id="<?php echo $itemProductId; ?>" data-saved-grade-id="<?php echo $itemGradeId; ?>">
                            <td class="sticky-col sticky-action">
                                <?php if (strcasecmp($curOp, 'CNC') !== 0): ?>
                                <button type="button" class="btn-icon btn-remove-row" title="Delete row">
                                    <i class="fa-solid fa-trash-can"></i>
                                </button>
                                <?php else: ?>
                                <span class="ops-kra-lock" title="ERP KRA fixed row"><i class="fa-solid fa-lock" style="color:#94a3b8;font-size:12px;"></i></span>
                                <?php endif; ?>
                            </td>
                            <td class="sticky-col sticky-sr ops-sr"><?php echo $i + 1; ?></td>
                            <td class="sticky-col sticky-name">
                                <?php if (strcasecmp($curOp, 'CNC') === 0): ?>
                                <div class="ops-fixed-process" title="ERP CNC KRA part name"><?php echo htmlspecialchars($itemProductLabel !== '' ? $itemProductLabel : '—'); ?></div>
                                <select name="items[<?php echo $i; ?>][product_id]" class="form-control product-select no-select2 ops-kra-hidden-select"
                                        data-saved-label="<?php echo htmlspecialchars($itemProductLabel); ?>" tabindex="-1" aria-hidden="true">
                                    <?php if ($itemProductId > 0): ?>
                                        <option value="<?php echo $itemProductId; ?>" selected
                                                data-rate="<?php echo htmlspecialchars((string) ($item['rate'] ?? 0)); ?>"
                                                data-ot="<?php echo htmlspecialchars((string) ($item['ot_rate'] ?? 0)); ?>"
                                                data-rej="<?php echo htmlspecialchars((string) ($item['rejection_rate'] ?? 0)); ?>">
                                            <?php echo htmlspecialchars($itemProductLabel); ?>
                                        </option>
                                    <?php else: ?>
                                        <option value="">Select Process</option>
                                    <?php endif; ?>
                                </select>
                                <?php else: ?>
                                <select name="items[<?php echo $i; ?>][product_id]" class="form-control product-select no-select2"
                                        data-saved-label="<?php echo htmlspecialchars($itemProductLabel); ?>">
                                    <option value="">Select Process</option>
                                    <?php if ($itemProductId > 0): ?>
                                        <option value="<?php echo $itemProductId; ?>" selected
                                                data-rate="<?php echo htmlspecialchars((string) ($item['rate'] ?? 0)); ?>"
                                                data-ot="<?php echo htmlspecialchars((string) ($item['ot_rate'] ?? 0)); ?>"
                                                data-rej="<?php echo htmlspecialchars((string) ($item['rejection_rate'] ?? 0)); ?>">
                                            <?php echo htmlspecialchars($itemProductLabel); ?>
                                        </option>
                                    <?php endif; ?>
                                </select>
                                <?php endif; ?>
                                <input type="hidden" class="row-product-id" value="<?php echo $itemProductId; ?>">
                            </td>
                            <td class="sticky-col sticky-grade td-grade">
                                <select name="items[<?php echo $i; ?>][grade_id]" class="form-control grade-select no-select2"
                                        data-saved-label="<?php echo htmlspecialchars($itemGradeName); ?>">
                                    <option value="">Select Grade</option>
                                    <?php if ($itemGradeId > 0): ?>
                                        <option value="<?php echo $itemGradeId; ?>" selected>
                                            <?php echo htmlspecialchars($itemGradeName !== '' ? $itemGradeName : ('Grade #' . $itemGradeId)); ?>
                                        </option>
                                    <?php endif; ?>
                                </select>
                                <input type="hidden" class="row-grade-id" value="<?php echo $itemGradeId; ?>">
                            </td>
                            <td class="sticky-col sticky-rate">
                                <input type="number" step="0.0001" min="0" name="items[<?php echo $i; ?>][rate]" class="form-control grid-input rate" value="<?php echo htmlspecialchars((string) ($item['rate'] ?? 0)); ?>">
                            </td>
                            <?php for ($d = 1; $d <= 31; $d++):
                                $cell = $days[(string) $d];
                                $dis = !empty($cell['disabled']) ? 'disabled' : '';
                                ?>
                                <td class="day-cell" data-day="<?php echo $d; ?>">
                                    <div class="ops-day-triple">
                                        <input type="text" inputmode="decimal" class="grid-input day-qty" <?php echo $dis; ?>
                                               name="items[<?php echo $i; ?>][days][<?php echo $d; ?>][q]" value="<?php echo htmlspecialchars((string) $cell['q']); ?>" autocomplete="off">
                                        <input type="text" inputmode="decimal" class="grid-input day-qty-r r-field" <?php echo $dis; ?>
                                               name="items[<?php echo $i; ?>][days][<?php echo $d; ?>][r]" value="<?php echo htmlspecialchars((string) $cell['r']); ?>" autocomplete="off">
                                        <input type="text" inputmode="decimal" class="grid-input day-qty-ot ot-field" <?php echo $dis; ?>
                                               name="items[<?php echo $i; ?>][days][<?php echo $d; ?>][ot]" value="<?php echo htmlspecialchars((string) $cell['ot']); ?>" autocomplete="off">
                                    </div>
                                </td>
                            <?php endfor; ?>
                            <td><input type="text" name="items[<?php echo $i; ?>][total_qty]" class="form-control grid-input total-qty" readonly value="<?php echo htmlspecialchars((string) ($item['total_qty'] ?? 0)); ?>"></td>
                            <td><input type="text" name="items[<?php echo $i; ?>][total_r]" class="form-control grid-input total-r" readonly value="<?php echo htmlspecialchars((string) ($item['total_r'] ?? 0)); ?>"></td>
                            <td>
                                <input type="number" step="0.01" name="items[<?php echo $i; ?>][ot_rate]" class="form-control grid-input row-ot-rate" readonly value="<?php echo htmlspecialchars((string) ($item['ot_rate'] ?? 0)); ?>">
                            </td>
                            <td>
                                <input type="number" step="0.01" name="items[<?php echo $i; ?>][rejection_rate]" class="form-control grid-input row-rejection-rate" readonly value="<?php echo htmlspecialchars((string) ($item['rejection_rate'] ?? 0)); ?>">
                            </td>
                            <td><input type="text" name="items[<?php echo $i; ?>][total_amount]" class="form-control grid-input total-amount" readonly value="<?php echo htmlspecialchars((string) ($item['total_amount'] ?? 0)); ?>"></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td class="sticky-col sticky-action" colspan="<?php echo $showGrade ? 5 : 4; ?>" id="tf_grand_total_label">GRAND TOTAL</td>
                            <?php for ($d = 1; $d <= 31; $d++): ?>
                                <td class="day-foot" data-day="<?php echo $d; ?>">
                                    <div class="ops-day-triple foot-totals">
                                        <span class="foot-q"></span>
                                        <span class="foot-r"></span>
                                        <span class="foot-ot"></span>
                                    </div>
                                </td>
                            <?php endfor; ?>
                            <td id="tf_total_qty">0</td>
                            <td id="tf_total_r">0</td>
                            <td></td>
                            <td></td>
                            <td id="tf_amount">0.00</td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <div class="form-actions sticky-actions">
                <button type="submit" class="btn-primary"><i class="fa-solid fa-check"></i> Save Product Grid</button>
                <a href="<?php echo app_url('contractor/operations_clone/index.php'); ?>" class="btn-secondary">Cancel</a>
            </div>
        </form>
    </div>

    <?php if ($showStaff): ?>
    <!-- ========== BOX 2: CNC staff salary + attendance ========== -->
    <div class="form-page-card ops-page-card ops-clone-box ops-clone-staff-card">
        <div class="ops-clone-staff-head">
            <h2><i class="fa-solid fa-users"></i> CNC Department — Normal Salary Employees</h2>
        </div>

        <form method="POST" action="<?php echo app_url('contractor/operations_clone/save_staff.php'); ?>" id="opsStaffForm" class="employee-form" autocomplete="off">
            <input type="hidden" name="sheet_id" value="<?php echo (int) $id; ?>">
            <input type="hidden" name="operation" value="<?php echo htmlspecialchars($curOp); ?>">
            <input type="hidden" name="employee_id" value="<?php echo (int) $curEmp; ?>">
            <input type="hidden" name="month_no" value="<?php echo (int) $curMonth; ?>">
            <input type="hidden" name="year_no" value="<?php echo (int) $curYear; ?>">
            <input type="hidden" name="staff_att_json" id="staffAttJson" value="">

            <div class="ops-clone-staff-wrap">
                <table class="ops-clone-staff-att-table" id="opsCloneStaffAttTable">
                    <thead>
                        <tr>
                            <th class="sticky-staff sticky-sr">Sr.</th>
                            <th class="sticky-staff sticky-code">Emp Code</th>
                            <th class="sticky-staff sticky-name">Employee Name</th>
                            <th class="sticky-staff sticky-desig">Designation</th>
                            <th class="sticky-staff sticky-sal num">Salary</th>
                            <?php for ($d = 1; $d <= $monthDays; $d++):
                                $wd = date('D', mktime(0, 0, 0, $curMonth, $d, $curYear));
                                $wk = in_array($wd, ['Sat', 'Sun'], true) ? ' is-weekend' : '';
                                ?>
                                <th class="att-day-head<?php echo $wk; ?>">
                                    <span class="day-no"><?php echo $d; ?></span>
                                    <span class="day-wd"><?php echo htmlspecialchars($wd); ?></span>
                                </th>
                            <?php endfor; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($staffBundle['rows'])): ?>
                            <tr>
                                <td colspan="<?php echo 5 + $monthDays; ?>" class="empty-msg">No Normal Salary employees found in CNC (excluding Main Contractor).</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($staffBundle['rows'] as $si => $staff):
                                $sid = (int) $staff['id'];
                                $empDays = $attMap[$sid] ?? [];
                                ?>
                                <tr data-emp="<?php echo $sid; ?>">
                                    <td class="sticky-staff sticky-sr"><?php echo $si + 1; ?></td>
                                    <td class="sticky-staff sticky-code"><span class="code-badge"><?php echo htmlspecialchars((string) $staff['employee_code']); ?></span></td>
                                    <td class="sticky-staff sticky-name"><?php echo htmlspecialchars((string) $staff['employee_name']); ?></td>
                                    <td class="sticky-staff sticky-desig"><?php echo htmlspecialchars((string) ($staff['designation'] ?? '')); ?></td>
                                    <td class="sticky-staff sticky-sal num">
                                        <input type="number" step="0.01" min="0"
                                               name="staff_salary[<?php echo $sid; ?>]"
                                               class="form-control grid-input staff-sal-input"
                                               value="<?php echo htmlspecialchars((string) round((float) ($staff['decided_salary'] ?? 0), 2)); ?>">
                                    </td>
                                    <?php for ($d = 1; $d <= $monthDays; $d++):
                                        $date = sprintf('%04d-%02d-%02d', $curYear, $curMonth, $d);
                                        $code = opsCloneAttCodeFromDay($empDays[$date] ?? null);
                                        $wd = date('D', mktime(0, 0, 0, $curMonth, $d, $curYear));
                                        $wk = in_array($wd, ['Sat', 'Sun'], true) ? ' is-weekend' : '';
                                        $css = '';
                                        $cu = strtoupper($code);
                                        if ($cu === 'P') {
                                            $css = 'is-present';
                                        } elseif ($cu === 'WO') {
                                            $css = 'is-weekoff';
                                        } elseif ($cu === 'A' || $cu === 'LWP') {
                                            $css = 'is-absent';
                                        } elseif ($cu === 'H') {
                                            $css = 'is-holiday';
                                        } elseif ($cu !== '') {
                                            $css = 'is-leave';
                                        }
                                        ?>
                                        <td class="att-day-cell<?php echo $wk; ?>">
                                            <input type="text"
                                                   class="staff-att-input <?php echo $css; ?>"
                                                   data-emp="<?php echo $sid; ?>"
                                                   data-day="<?php echo $d; ?>"
                                                   maxlength="10"
                                                   value="<?php echo htmlspecialchars($code); ?>"
                                                   title="<?php echo htmlspecialchars($date); ?>"
                                                   autocomplete="off">
                                        </td>
                                    <?php endfor; ?>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="form-actions sticky-actions">
                <button type="submit" class="btn-primary" id="btnSaveStaff"><i class="fa-solid fa-check"></i> Save Staff Salary + Attendance</button>
                <a href="<?php echo app_url('contractor/operations_clone/index.php'); ?>" class="btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
    <?php endif; ?>
</main>
<script>
    window.C_TOAST_MSG = <?php echo json_encode($toastMsg); ?>;
    window.OPS_PRODUCTS_URL = <?php echo json_encode(app_url('contractor/operations/products_json.php')); ?>;
    window.OPS_KRA_PARTS = <?php echo strcasecmp($curOp, 'CNC') === 0 ? 'true' : 'false'; ?>;
    window.OPS_KRA_FIXED = <?php echo strcasecmp($curOp, 'CNC') === 0 ? 'true' : 'false'; ?>; // fixed rows, no process dropdown
    window.OPS_KRA_AS_OF = <?php echo json_encode($kraAsOf); ?>;
    window.OPS_REPAIR = <?php echo $repairJson; ?>;
    window.OPS_OT_REPAIR = <?php echo $otJson; ?>;
    window.OPS_GRADE_OPS = <?php echo json_encode(array_values(contractorShowGradeOps())); ?>;
    window.OPS_KEEP_PRODUCT_IDS = <?php
        $keepPids = [];
        foreach ($items as $itRow) {
            $pid = (int) ($itRow['product_id'] ?? 0);
            if ($pid > 0) {
                $keepPids[] = $pid;
            }
        }
        echo json_encode(array_values(array_unique($keepPids)));
    ?>;
    window.OPS_CLONE_DAILY_PRINT = <?php echo json_encode(app_url('contractor/operations_clone/print_daily.php?id=' . (int) $id)); ?>;

    (function () {
        function openDailyPrint(orient) {
            var dayEl = document.getElementById('dailyPrintDay');
            var day = dayEl ? dayEl.value : '1';
            var url = window.OPS_CLONE_DAILY_PRINT
                + '&day=' + encodeURIComponent(day)
                + '&orient=' + encodeURIComponent(orient || 'portrait');
            window.open(url, '_blank');
        }
        var bp = document.getElementById('btnDailyPortrait');
        var bl = document.getElementById('btnDailyLandscape');
        if (bp) bp.addEventListener('click', function (e) { e.preventDefault(); openDailyPrint('portrait'); });
        if (bl) bl.addEventListener('click', function (e) { e.preventDefault(); openDailyPrint('landscape'); });

        function reloadWithMeta() {
            var params = new URLSearchParams();
            if (<?php echo (int) $id; ?> > 0) {
                params.set('id', String(<?php echo (int) $id; ?>));
            }
            var op = document.getElementById('operationSelect');
            var mo = document.getElementById('monthSelect');
            var yr = document.getElementById('yearSelect');
            var emp = document.getElementById('opsEmployeeSelect');
            if (op) params.set('operation', op.value);
            if (mo) params.set('month', mo.value);
            if (yr) params.set('year', yr.value);
            if (emp && emp.value) params.set('employee_id', emp.value);
            window.location.search = params.toString();
        }
        ['operationSelect', 'monthSelect', 'yearSelect'].forEach(function (id) {
            var el = document.getElementById(id);
            if (!el) return;
            el.addEventListener('change', reloadWithMeta);
        });

        var staffForm = document.getElementById('opsStaffForm');
        if (staffForm) {
            staffForm.addEventListener('submit', function () {
                var payload = {};
                document.querySelectorAll('.staff-att-input').forEach(function (inp) {
                    var eid = String(inp.getAttribute('data-emp') || '');
                    var day = String(inp.getAttribute('data-day') || '');
                    if (!eid || !day) return;
                    if (!payload[eid]) payload[eid] = {};
                    payload[eid][day] = (inp.value || '').trim();
                });
                var hid = document.getElementById('staffAttJson');
                if (hid) hid.value = JSON.stringify(payload);
            });
        }

        if (window.toastr && window.C_TOAST_MSG) {
            toastr.options = { closeButton: true, progressBar: true, positionClass: 'toast-top-right', timeOut: 3500 };
            toastr.success(window.C_TOAST_MSG);
        }
    })();
</script>
<?php
$extraJs = [
    'https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js',
    'assets/js/contractor_ops.js?v=' . (string) @filemtime(__DIR__ . '/../../assets/js/contractor_ops.js'),
];
require_once __DIR__ . '/../../includes/footer.php';
