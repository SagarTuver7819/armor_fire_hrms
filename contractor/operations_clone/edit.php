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
// New Operation List is view-only (Add / Edit removed). Old Ops Rate List is a different page.
$opsViewOnly = isset($_GET['view']) && (string) $_GET['view'] !== '0';
if (!$opsViewOnly) {
    $conn->close();
    if ($id > 0 || $prefillEmp > 0 || isset($_GET['month']) || isset($_GET['year'])) {
        $qs = $_GET;
        $qs['view'] = '1';
        header('Location: ' . app_url('contractor/operations_clone/edit.php?' . http_build_query($qs)));
    } else {
        header('Location: ' . app_url('contractor/operations_clone/index.php'));
    }
    exit;
}
$beforeNov = ($curYear < 2026) || ($curYear === 2026 && $curMonth < 11);
if ($beforeNov) {
    $conn->close();
    header('Location: ' . app_url('contractor/operations_clone/index.php'));
    exit;
}
$employees = ensureOpsEmployeeInList($employees, $curEmp, $conn);
$staffBundle = getOpsCloneDepartmentStaff($curOp, $curEmp, $conn);
$attMap = [];
$attRows = $staffBundle['rows'];
$mainStaff = $staffBundle['main'] ?? null;
if (is_array($mainStaff) && (int) ($mainStaff['id'] ?? 0) > 0) {
    $attRows[] = $mainStaff;
}
if (!empty($attRows)) {
    $attMap = getOpsCloneStaffAttendanceMap($attRows, $curMonth, $curYear, $conn);
}
$staffShares = opsCloneCncSalaryShare($staffBundle['rows'], $attMap, $curMonth, $curYear);

$items = $sheet['items'] ?? [];
// Live as-of = today so ERP KRA "Add item" appears immediately (not frozen to day 02)
$kraAsOf = date('Y-m-d');
$kraPrefill = [];
$erpDayQtyByPart = [];
if (strcasecmp($curOp, 'CNC') === 0) {
    // Auto rows = ERP CNC Production Planning KRA part names (fixed — no dropdown)
    try {
        $kraSync = syncErpKraPartsToContractorProducts('CNC', $conn, $kraAsOf);
    } catch (Throwable $e) {
        $kraSync = [
            'ok' => false,
            'added' => 0,
            'updated' => 0,
            'total' => 0,
            'message' => $e->getMessage(),
            'names' => contractorCncKraBaselinePartNames(),
            'as_of' => $kraAsOf,
        ];
        foreach (contractorCncKraBaselinePartNames() as $pn) {
            contractorEnsureProduct($conn, 'CNC', $pn, 0.0);
        }
    }
    $kraNameSet = [];
    $canonLabelByKey = [];
    foreach (($kraSync['names'] ?? []) as $pn) {
        $ck = contractorKraCanonicalKey($pn);
        if ($ck === '') {
            continue;
        }
        $kraNameSet[$ck] = true;
        $canonLabelByKey[$ck] = (string) $pn;
        foreach (contractorKraPartNameLookupCandidates($pn) as $al) {
            $kraNameSet[strtolower(trim((string) $al))] = true;
        }
    }
    $allCnc = getContractorProductsByOperation('CNC', $conn);
    $best = [];
    foreach ($allCnc as $pr) {
        if (!contractorProductMatchesKraNames($pr, $kraNameSet)) {
            continue;
        }
        $raw = trim((string) ($pr['product_name'] ?? ''));
        if ($raw === '') {
            $raw = trim((string) ($pr['process'] ?? ''));
        }
        $nk = contractorKraCanonicalKey($raw);
        if ($nk === '') {
            continue;
        }
        $matchedCanon = $canonLabelByKey[$nk] ?? $raw;
        $rate = (float) ($pr['rate'] ?? 0);
        if (!isset($best[$nk]) || $rate > (float) ($best[$nk]['rate'] ?? 0)) {
            $best[$nk] = $pr;
            $best[$nk]['_canon'] = $matchedCanon;
        }
    }
    // ERP KRA Total qty → day boxes (e.g. 02/11/2026 → column day 2)
    try {
        $erpDayQtyByPart = fetchErpKraDateWiseQtys('CNC', $curYear, $curMonth);
    } catch (Throwable $e) {
        $erpDayQtyByPart = [];
    }

    foreach (($kraSync['names'] ?? []) as $canon) {
        $ck = contractorKraCanonicalKey($canon);
        if ($ck === '' || !isset($best[$ck])) {
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
        // Also accept saved qty under an alias product_id for the same part
        if ($saved === null) {
            foreach ($items as $it) {
                $itPid = (int) ($it['product_id'] ?? 0);
                if ($itPid <= 0) {
                    continue;
                }
                foreach ($allCnc as $candPr) {
                    if ((int) ($candPr['id'] ?? 0) !== $itPid) {
                        continue;
                    }
                    $candKey = contractorKraCanonicalKey((string) ($candPr['product_name'] ?? $candPr['process'] ?? ''));
                    if ($candKey === $ck) {
                        $saved = $it;
                        break 2;
                    }
                }
            }
        }
        $days = $saved['days'] ?? [];
        $erpDays = contractorErpDaysForPartName($canon, $erpDayQtyByPart);
        // Always prefer live ERP KRA Total over previously saved (alias-tripled) day qty
        $days = mergeErpKraDaysIntoMap(is_array($days) ? $days : [], $erpDays, true);
        $kraPrefill[] = [
            'product_id' => $pid,
            'product_label' => (string) $canon,
            'grade_id' => (int) ($saved['grade_id'] ?? 0),
            'grade_name' => (string) ($saved['grade_name'] ?? ''),
            'rate' => $saved !== null ? ($saved['rate'] ?? $pr['rate']) : ($pr['rate'] ?? 0),
            'ot_rate' => $saved !== null ? ($saved['ot_rate'] ?? 0) : parseOtRate($pr['ot_text'] ?? ''),
            'rejection_rate' => $saved !== null ? ($saved['rejection_rate'] ?? 0) : ($pr['rejection_rate'] ?? 0),
            'days' => $days,
            'total_qty' => $saved['total_qty'] ?? 0,
            'total_r' => $saved['total_r'] ?? 0,
            'total_amount' => $saved['total_amount'] ?? 0,
        ];
        unset($best[$ck]);
    }
    // Do not append leftover alias duplicates (e.g. Turning Bush when ERP has Lurning Bush)
    if ($kraPrefill) {
        $items = $kraPrefill;
    }
    // Remap ERP day qty under canonical KRA names for JS (single key, no alias fan-out)
    $erpCanonMap = [];
    foreach (($kraSync['names'] ?? []) as $canon) {
        $ck = strtolower(trim((string) $canon));
        $erpCanonMap[$ck] = contractorErpDaysForPartName($canon, $erpDayQtyByPart);
    }
    $erpDayQtyByPart = $erpCanonMap;
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
$staffPool = 0.0;
foreach ($items as $poolItem) {
    $calc = calculateContractorRow(
        $curOp,
        (float) ($poolItem['rate'] ?? 0),
        (float) ($poolItem['ot_rate'] ?? 0),
        (float) ($poolItem['rejection_rate'] ?? 0),
        is_array($poolItem['days'] ?? null) ? $poolItem['days'] : []
    );
    $staffPool += (float) ($calc['total_amount'] ?? 0);
}
$staffSplit = opsCloneApplyContractorPool($staffShares, $staffPool);
$conn->close();

// CNC clone: no GRADE column (ERP KRA part rows only — qty × rate)
$showGrade = strcasecmp($curOp, 'CNC') === 0 ? false : contractorOperationShowsGrade($curOp);
$showStaff = in_array($curOp, opsCloneStaffSalaryOperations(), true);
$monthDays = (int) date('t', mktime(0, 0, 0, $curMonth, 1, $curYear));

$pageTitle = 'View — New Operation List';
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
        <a href="<?php echo app_url('contractor/operations_clone/index.php'); ?>" class="back-link"><i class="fa-solid fa-arrow-left"></i> Back to New Operation List</a>
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
    <div class="form-page-card ops-page-card ops-clone-box<?php echo $opsViewOnly ? ' ops-view-only' : ''; ?>">
        <div class="form-page-header">
            <div>
                <h1>View — Product Rate Grid</h1>
                <p>CNC · <?php echo htmlspecialchars($months[$curMonth] ?? (string) $curMonth); ?> <?php echo (int) $curYear; ?> · last set ERP KRA grid (view only).</p>
            </div>
        </div>

        <form method="POST" action="<?php echo app_url('contractor/operations_clone/save.php'); ?>" id="opsForm" class="employee-form" autocomplete="off">
            <input type="hidden" name="id" value="<?php echo (int) $id; ?>">
            <input type="hidden" name="items_json" id="opsItemsJson" value="">
            <div class="form-section">
                <div class="form-grid form-grid-3">
                    <div class="form-group">
                        <label>Operation <span class="req">*</span></label>
                        <select name="operation" id="operationSelect" class="form-control" required<?php echo $opsViewOnly ? ' disabled' : ''; ?>>
                            <?php foreach ($ops as $op): ?>
                                <option value="<?php echo htmlspecialchars($op); ?>" <?php echo $curOp === $op ? 'selected' : ''; ?>><?php echo htmlspecialchars($op); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Name <span class="req">*</span></label>
                        <select name="employee_id" id="opsEmployeeSelect" class="form-control" required
                                data-current-emp="<?php echo (int) $curEmp; ?>"<?php echo $opsViewOnly ? ' disabled' : ''; ?>>
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
                        <select name="month_no" id="monthSelect" class="form-control" required<?php echo $opsViewOnly ? ' disabled' : ''; ?>>
                            <?php foreach ($months as $num => $lab): ?>
                                <option value="<?php echo (int) $num; ?>" <?php echo $curMonth === (int) $num ? 'selected' : ''; ?>><?php echo htmlspecialchars($lab); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Year <span class="req">*</span></label>
                        <select name="year_no" id="yearSelect" class="form-control" required<?php echo $opsViewOnly ? ' disabled' : ''; ?>>
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
                    <i class="fa-solid fa-eye"></i> Day qty locked (ERP). <b>Rate</b> click kari change karo — remark required, history save thase.
                </div>
                <?php endif; ?>
                <div class="ops-live-summary">
                    <span class="ops-chip"><span id="sum_qty_lab">Qty</span> <b id="sum_qty">0</b></span>
                    <span class="ops-chip ops-chip-r<?php echo strcasecmp($curOp, 'CNC') === 0 ? ' ops-cnc-hide' : ''; ?>"><span id="sum_r_lab">R</span> <b id="sum_r">0</b></span>
                    <span class="ops-chip amt">Amount <b id="sum_amt">0.00</b></span>
                </div>
            </div>

            <div class="ops-grid-wrap">
                <table class="ops-grid <?php echo $showGrade ? 'show-grade' : 'hide-grade'; ?><?php echo strcasecmp($curOp, 'CNC') === 0 ? ' ops-kra-qty-locked ops-cnc-plain' : ''; ?>" id="opsGrid">
                    <thead>
                        <tr>
                            <th class="sticky-col sticky-action">Action</th>
                            <th class="sticky-col sticky-sr">Sr</th>
                            <th class="sticky-col sticky-name" id="col_header_name">Contract Process</th>
                            <?php if ($showGrade): ?>
                            <th class="sticky-col sticky-grade" id="col_header_grade">GRADE</th>
                            <?php endif; ?>
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
                            <th id="th_total_r" class="ops-col-r">TOTAL R</th>
                            <th class="ops-col-ot">OT RATE</th>
                            <th class="ops-col-rej">REJ. RATE</th>
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
                                <?php if (!$showGrade): ?>
                                <input type="hidden" name="items[<?php echo $i; ?>][grade_id]" class="row-grade-id" value="0">
                                <?php endif; ?>
                            </td>
                            <?php if ($showGrade): ?>
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
                            <?php endif; ?>
                            <td class="sticky-col sticky-rate">
                                <input type="number" step="0.0001" min="0" name="items[<?php echo $i; ?>][rate]" class="form-control grid-input rate" value="<?php echo htmlspecialchars((string) ($item['rate'] ?? 0)); ?>">
                            </td>
                            <?php for ($d = 1; $d <= 31; $d++):
                                $cell = $days[(string) $d];
                                $dis = !empty($cell['disabled']) ? 'disabled' : '';
                                ?>
                                <td class="day-cell" data-day="<?php echo $d; ?>">
                                    <?php $kraRo = strcasecmp($curOp, 'CNC') === 0 ? ' readonly tabindex="-1"' : ''; ?>
                                    <div class="ops-day-triple">
                                        <input type="text" inputmode="decimal" class="grid-input day-qty" <?php echo $dis . $kraRo; ?>
                                               name="items[<?php echo $i; ?>][days][<?php echo $d; ?>][q]" value="<?php echo htmlspecialchars((string) $cell['q']); ?>" autocomplete="off">
                                        <input type="text" inputmode="decimal" class="grid-input day-qty-r r-field" <?php echo $dis . $kraRo; ?>
                                               name="items[<?php echo $i; ?>][days][<?php echo $d; ?>][r]" value="<?php echo htmlspecialchars((string) $cell['r']); ?>" autocomplete="off">
                                        <input type="text" inputmode="decimal" class="grid-input day-qty-ot ot-field" <?php echo $dis . $kraRo; ?>
                                               name="items[<?php echo $i; ?>][days][<?php echo $d; ?>][ot]" value="<?php echo htmlspecialchars((string) $cell['ot']); ?>" autocomplete="off">
                                    </div>
                                </td>
                            <?php endfor; ?>
                            <td><input type="text" name="items[<?php echo $i; ?>][total_qty]" class="form-control grid-input total-qty" readonly value="<?php echo htmlspecialchars((string) ($item['total_qty'] ?? 0)); ?>"></td>
                            <td class="ops-col-r"><input type="text" name="items[<?php echo $i; ?>][total_r]" class="form-control grid-input total-r" readonly value="<?php echo htmlspecialchars((string) ($item['total_r'] ?? 0)); ?>"></td>
                            <td class="ops-col-ot">
                                <input type="number" step="0.01" name="items[<?php echo $i; ?>][ot_rate]" class="form-control grid-input row-ot-rate" readonly value="<?php echo htmlspecialchars((string) ($item['ot_rate'] ?? 0)); ?>">
                            </td>
                            <td class="ops-col-rej">
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
                            <td id="tf_total_r" class="ops-col-r">0</td>
                            <td class="ops-col-ot"></td>
                            <td class="ops-col-rej"></td>
                            <td id="tf_amount">0.00</td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <?php if (!$opsViewOnly): ?>
            <div class="form-actions sticky-actions">
                <button type="submit" class="btn-primary"><i class="fa-solid fa-check"></i> Save Product Grid</button>
                <a href="<?php echo app_url('contractor/operations_clone/index.php'); ?>" class="btn-secondary">Cancel</a>
            </div>
            <?php else: ?>
            <div class="form-actions sticky-actions">
                <a href="<?php echo app_url('contractor/operations_clone/index.php'); ?>" class="btn-secondary">Back to List</a>
            </div>
            <?php endif; ?>
        </form>
    </div>

    <?php if ($showStaff): ?>
    <!-- ========== BOX 2: CNC staff salary + attendance ========== -->
    <div class="form-page-card ops-page-card ops-clone-box ops-clone-staff-card<?php echo $opsViewOnly ? ' ops-view-only' : ''; ?>">
        <div class="ops-clone-staff-head">
            <h2><i class="fa-solid fa-users"></i> CNC Department — Normal Salary Employees</h2>
            <p>Contract total <strong id="opsStaffPool"><?php echo number_format((float) $staffSplit['pool'], 2, '.', ''); ?></strong> divide: salary ÷ month working days (week-off sivay), 01-11-2026 thi aaj sudhi. Week-off par P hoy to e day pan count. Baki rakan Main Contractor.</p>
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
                            <th class="sticky-staff sticky-sal num">Salary</th>
                            <th class="sticky-staff sticky-final num">Final Total</th>
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
                        <?php
                        $staffDisplay = $staffBundle['rows'];
                        if (is_array($mainStaff) && (int) ($mainStaff['id'] ?? 0) > 0) {
                            $mainStaff['_is_main'] = true;
                            $staffDisplay[] = $mainStaff;
                        }
                        ?>
                        <?php if (empty($staffDisplay)): ?>
                            <tr>
                                <td colspan="<?php echo 5 + $monthDays; ?>" class="empty-msg">No Normal Salary employees found in CNC.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($staffDisplay as $si => $staff):
                                $sid = (int) $staff['id'];
                                $isMainRow = !empty($staff['_is_main']);
                                $empDays = $attMap[$sid] ?? [];
                                $share = $staffSplit['shares'][$sid] ?? null;
                                $finalAmt = $isMainRow
                                    ? (float) ($staffSplit['remainder'] ?? 0)
                                    : (float) ($share['final'] ?? 0);
                                $baseAmt = $isMainRow ? 0 : (float) ($share['amount'] ?? 0);
                                $finalTitle = $isMainRow
                                    ? 'Contract total minus staff salary'
                                    : ('Salary ' . number_format((float) ($share['salary'] ?? 0), 2, '.', '')
                                        . ' / ' . (int) ($share['working_days'] ?? 0) . ' working days × '
                                        . (float) ($share['paid_days'] ?? 0) . ' P days');
                                ?>
                                <tr data-emp="<?php echo $sid; ?>" class="<?php echo $isMainRow ? 'is-main-contractor' : ''; ?>">
                                    <td class="sticky-staff sticky-sr"><?php echo $si + 1; ?></td>
                                    <td class="sticky-staff sticky-code"><span class="code-badge"><?php echo htmlspecialchars((string) $staff['employee_code']); ?></span></td>
                                    <td class="sticky-staff sticky-name" title="<?php echo htmlspecialchars((string) $staff['employee_name']); ?>"><?php echo htmlspecialchars((string) $staff['employee_name']); ?><?php echo $isMainRow ? ' (Main Contractor)' : ''; ?></td>
                                    <td class="sticky-staff sticky-sal num"><?php echo number_format((float) ($staff['decided_salary'] ?? 0), 0, '.', ''); ?></td>
                                    <td class="sticky-staff sticky-final num staff-final<?php echo $isMainRow ? ' is-remainder' : ''; ?>"
                                        <?php echo $isMainRow ? 'data-main="1"' : 'data-base="' . htmlspecialchars((string) $baseAmt) . '"'; ?>
                                        title="<?php echo htmlspecialchars($finalTitle); ?>"><?php echo number_format($finalAmt, 2, '.', ''); ?></td>
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

            <?php if (!$opsViewOnly): ?>
            <div class="form-actions sticky-actions">
                <button type="submit" class="btn-primary" id="btnSaveStaff"><i class="fa-solid fa-check"></i> Save Staff Salary + Attendance</button>
                <a href="<?php echo app_url('contractor/operations_clone/index.php'); ?>" class="btn-secondary">Cancel</a>
            </div>
            <?php endif; ?>
        </form>
    </div>
    <?php endif; ?>
</main>
<script>
    window.C_TOAST_MSG = <?php echo json_encode($toastMsg); ?>;
    window.OPS_VIEW_ONLY = <?php echo $opsViewOnly ? 'true' : 'false'; ?>;
    window.OPS_PRODUCTS_URL = <?php echo json_encode(app_url('contractor/operations/products_json.php')); ?>;
    window.OPS_RATE_HISTORY_URL = <?php echo json_encode(app_url('contractor/operations/rate_history.php')); ?>;
    window.OPS_SHEET_ID = <?php echo (int) $id; ?>;
    window.OPS_KRA_PARTS = <?php echo strcasecmp($curOp, 'CNC') === 0 ? 'true' : 'false'; ?>;
    window.OPS_KRA_FIXED = <?php echo strcasecmp($curOp, 'CNC') === 0 ? 'true' : 'false'; ?>; // fixed rows, no process dropdown
    window.OPS_KRA_AS_OF = <?php echo json_encode($kraAsOf); ?>;
    window.OPS_KRA_DAY_QTY = <?php echo json_encode($erpDayQtyByPart ?? [], JSON_UNESCAPED_UNICODE); ?>;
    window.OPS_REPAIR = <?php echo $repairJson; ?>;
    window.OPS_OT_REPAIR = <?php echo $otJson; ?>;
    window.OPS_GRADE_OPS = <?php
        $gradeOpsJs = array_values(contractorShowGradeOps());
        if (strcasecmp($curOp, 'CNC') === 0) {
            $gradeOpsJs = array_values(array_filter($gradeOpsJs, static function ($op) {
                return strcasecmp((string) $op, 'CNC') !== 0;
            }));
        }
        echo json_encode($gradeOpsJs);
    ?>;
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

        window.opsOnGrandTotal = function (pool) {
            pool = Math.round((parseFloat(pool) || 0) * 100) / 100;
            var poolEl = document.getElementById('opsStaffPool');
            if (poolEl) poolEl.textContent = pool.toFixed(2);
            var cells = Array.prototype.slice.call(document.querySelectorAll('#opsCloneStaffAttTable td.staff-final[data-base]'));
            var sum = 0;
            cells.forEach(function (td) { sum += parseFloat(td.getAttribute('data-base')) || 0; });
            sum = Math.round(sum * 100) / 100;
            var scale = 1;
            if (pool <= 0) scale = 0;
            else if (sum > pool && sum > 0) scale = pool / sum;
            var distributed = 0;
            cells.forEach(function (td, i) {
                var base = parseFloat(td.getAttribute('data-base')) || 0;
                var amt;
                if (scale === 1 || pool <= 0) amt = Math.round(base * scale * 100) / 100;
                else if (i === cells.length - 1) amt = Math.round((pool - distributed) * 100) / 100;
                else amt = Math.round(base * scale * 100) / 100;
                if (amt < 0) amt = 0;
                distributed += amt;
                td.textContent = amt.toFixed(2);
            });
            distributed = Math.round(distributed * 100) / 100;
            var rem = Math.max(0, Math.round((pool - distributed) * 100) / 100);
            var mainTd = document.querySelector('#opsCloneStaffAttTable td.staff-final[data-main="1"]');
            if (mainTd) mainTd.textContent = rem.toFixed(2);
        };

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
