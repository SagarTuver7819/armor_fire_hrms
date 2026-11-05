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
$isJobReport = in_array(strtoupper($curOp), ['CNC', 'BUFF'], true);
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
    $erpDeptLike = strcasecmp($curOp, 'BUFF') === 0 ? 'buff%' : 'cnc%';
    $attMap = getOpsCloneStaffAttendanceMap($attRows, $curMonth, $curYear, $conn, $erpDeptLike);
}
$staffShares = opsCloneCncSalaryShare($staffBundle['rows'], $attMap, $curMonth, $curYear);

$items = $sheet['items'] ?? [];
// Live as-of = today so ERP KRA "Add item" appears immediately (not frozen to day 02)
$kraAsOf = date('Y-m-d');
$kraPrefill = [];
$erpDayQtyByPart = [];
$erpBuffExtraByPart = [];
$isBuffReport = strcasecmp($curOp, 'BUFF') === 0;
if ($isJobReport) {
    // Auto rows = ERP CNC Production Planning KRA part names (fixed — no dropdown)
    try {
        $kraSync = syncErpKraPartsToContractorProducts($curOp, $conn, $kraAsOf);
    } catch (Throwable $e) {
        $kraSync = [
            'ok' => false,
            'added' => 0,
            'updated' => 0,
            'total' => 0,
            'message' => $e->getMessage(),
            'names' => strcasecmp($curOp, 'CNC') === 0 ? contractorCncKraBaselinePartNames() : [],
            'as_of' => $kraAsOf,
        ];
        if (strcasecmp($curOp, 'CNC') === 0) {
            foreach (contractorCncKraBaselinePartNames() as $pn) {
                contractorEnsureProduct($conn, 'CNC', $pn, 0.0);
            }
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
    $allCnc = getContractorProductsByOperation($curOp, $conn);
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
        $erpDayQtyByPart = fetchErpKraDateWiseQtys($curOp, $curYear, $curMonth);
    } catch (Throwable $e) {
        $erpDayQtyByPart = [];
    }
    if ($isBuffReport) {
        // Buff KRA: Rework → RW, Argon Polish → AP (same part + date as Total → Q)
        try {
            $erpBuffExtraByPart = fetchErpBuffKraDayExtras($curYear, $curMonth);
        } catch (Throwable $e) {
            $erpBuffExtraByPart = [];
        }
    }

    if ($isBuffReport && !empty($kraSync['names']) && is_array($kraSync['names'])) {
        usort($kraSync['names'], static function ($a, $b) {
            $oa = contractorBuffKraSortRank($a);
            $ob = contractorBuffKraSortRank($b);
            if ($oa !== $ob) {
                return $oa <=> $ob;
            }
            return strcasecmp((string) $a, (string) $b);
        });
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
        if ($isBuffReport) {
            $buffExtra = contractorErpBuffExtrasForPartName($canon, $erpBuffExtraByPart);
            $days = mergeErpBuffExtrasIntoMap(is_array($days) ? $days : [], $buffExtra);
        }
        $kraPrefill[] = [
            'product_id' => $pid,
            'product_label' => (string) $canon,
            'grade_id' => (int) ($saved['grade_id'] ?? 0),
            'grade_name' => (string) ($saved['grade_name'] ?? ''),
            'rate' => $saved !== null ? ($saved['rate'] ?? $pr['rate']) : ($pr['rate'] ?? 0),
            'ot_rate' => $saved !== null ? ($saved['ot_rate'] ?? 0) : parseOtRate($pr['ot_text'] ?? ''),
            'rejection_rate' => $saved !== null ? ($saved['rejection_rate'] ?? 0) : ($pr['rejection_rate'] ?? 0),
            'argon_rate' => $saved !== null ? ($saved['argon_rate'] ?? ($pr['argon_rate'] ?? 0)) : ($pr['argon_rate'] ?? 0),
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
    $erpBuffCanonMap = [];
    foreach (($kraSync['names'] ?? []) as $canon) {
        $ck = strtolower(trim((string) $canon));
        $erpCanonMap[$ck] = contractorErpDaysForPartName($canon, $erpDayQtyByPart);
        if ($isBuffReport) {
            $erpBuffCanonMap[$ck] = contractorErpBuffExtrasForPartName($canon, $erpBuffExtraByPart);
        }
    }
    $erpDayQtyByPart = $erpCanonMap;
    $erpBuffExtraByPart = $erpBuffCanonMap;
}
if (!$items) {
    $items = [[
        'product_id' => 0,
        'grade_id' => 0,
        'rate' => 0,
        'ot_rate' => 0,
        'rejection_rate' => 0,
        'argon_rate' => 0,
        'days' => [],
        'total_qty' => 0,
        'total_r' => 0,
        'total_amount' => 0,
    ]];
}
$staffLoanById = [];
if ($isBuffReport) {
    $loanCut = opsCloneSubtractLoansFromShares(
        $staffShares,
        $staffBundle['rows'],
        fetchErpBuffLoanAmounts($curYear, $curMonth)
    );
    $staffShares = $loanCut['shares'];
    $staffLoanById = $loanCut['by_id'];
}
$staffBuffExpense = $isBuffReport ? fetchErpBuffDeptExpense($curYear, $curMonth) : 0.0;
$staffPool = 0.0;
foreach ($items as $poolItem) {
    $calc = calculateContractorRow(
        $curOp,
        (float) ($poolItem['rate'] ?? 0),
        (float) ($poolItem['ot_rate'] ?? 0),
        (float) ($poolItem['rejection_rate'] ?? 0),
        is_array($poolItem['days'] ?? null) ? $poolItem['days'] : [],
        (float) ($poolItem['argon_rate'] ?? 0)
    );
    $staffPool += (float) ($calc['total_amount'] ?? 0);
}
$staffSplit = opsCloneApplyContractorPool($staffShares, $staffPool);
$conn->close();

// CNC clone: no GRADE column (ERP KRA part rows only — qty × rate)
$showGrade = $isJobReport ? false : contractorOperationShowsGrade($curOp);
$showStaff = in_array($curOp, opsCloneStaffSalaryOperations(), true);
$monthDays = (int) date('t', mktime(0, 0, 0, $curMonth, 1, $curYear));

$reportName = strcasecmp($curOp, 'BUFF') === 0 ? 'Buff Job Work and Attendance Report' : 'CNC Job Work and Attendance Report';
$reportDeptKey = strcasecmp($curOp, 'BUFF') === 0 ? 'BUFF' : 'CNC';
$listUrl = app_url('contractor/operations_clone/index.php' . ($reportDeptKey === 'BUFF' ? '?op=BUFF' : ''));
$pageTitle = 'View — ' . $reportName;
$useSidebar = true;
$sidebarActive = $reportDeptKey === 'BUFF' ? 'buff_job_report' : 'cnc_job_report';
if (!function_exists('resolveDepartmentIdByName')) {
    require_once __DIR__ . '/../../includes/contractor_split_helper.php';
}
$cncDeptId = (int) resolveDepartmentIdByName($reportDeptKey);
$sidebarMode = $cncDeptId > 0 ? 'department' : 'contractor';
$sidebarDeptId = $cncDeptId;
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
        <a href="<?php echo htmlspecialchars($listUrl); ?>" class="back-link"><i class="fa-solid fa-arrow-left"></i> Back to <?php echo htmlspecialchars($reportName); ?></a>
    </div>

    <!-- ========== BOX 1: Product qty grid ========== -->
    <div class="form-page-card ops-page-card ops-clone-box<?php echo $opsViewOnly ? ' ops-view-only' : ''; ?>">
        <div class="form-page-header">
            <div>
                <h1>View — Product Rate Grid</h1>
                <p><?php echo htmlspecialchars($reportDeptKey); ?> · <?php echo htmlspecialchars($months[$curMonth] ?? (string) $curMonth); ?> <?php echo (int) $curYear; ?> · last set ERP KRA grid (view only).</p>
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
                <?php if (!$isJobReport): ?>
                <button type="button" class="btn-primary" id="btnAddProduct"><i class="fa-solid fa-plus"></i> Add Product Row</button>
                <?php else: ?>
                <div class="ops-kra-hint" style="font-size:13px;color:#64748b;">
                    <i class="fa-solid fa-eye"></i>
                    <?php if ($isBuffReport): ?>
                    Daily boxes ERP mathi: <b>Q</b> = Total, <b>RW</b> = Rework, <b>AP</b> = Argon Polish.
                    Amount = (Q × Qty Rate) + (RW × Rework Rate) + (AP × Argon Polish Rate).
                    Rate click kari change karo — remark required, history save thase.
                    <?php else: ?>
                    Day qty locked (ERP). <b>Rate</b> click kari change karo — remark required, history save thase.
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                <div class="ops-live-summary">
                    <span class="ops-chip"><span id="sum_qty_lab">Qty</span> <b id="sum_qty">0</b></span>
                    <?php if ($isBuffReport): ?>
                    <span class="ops-chip"><span>RW</span> <b id="sum_rw">0</b></span>
                    <span class="ops-chip"><span>AP</span> <b id="sum_ap">0</b></span>
                    <?php endif; ?>
                    <span class="ops-chip ops-chip-r<?php echo $isJobReport ? ' ops-cnc-hide' : ''; ?>"><span id="sum_r_lab">R</span> <b id="sum_r">0</b></span>
                    <span class="ops-chip amt">Amount <b id="sum_amt">0.00</b></span>
                </div>
            </div>

            <div class="ops-grid-wrap">
                <table class="ops-grid <?php echo $showGrade ? 'show-grade' : 'hide-grade'; ?><?php echo $isJobReport ? ' ops-kra-qty-locked ops-cnc-plain' : ''; ?><?php echo $isBuffReport ? ' show-r show-buff-ap' : ''; ?>" id="opsGrid">
                    <thead>
                        <tr>
                            <th class="sticky-col sticky-action">Action</th>
                            <th class="sticky-col sticky-sr">Sr</th>
                            <th class="sticky-col sticky-name" id="col_header_name">Contract Process</th>
                            <?php if ($showGrade): ?>
                            <th class="sticky-col sticky-grade" id="col_header_grade">GRADE</th>
                            <?php endif; ?>
                            <th class="sticky-col sticky-rate"><?php echo $isBuffReport ? 'Qty Rate' : 'Rate'; ?></th>
                            <?php if ($isBuffReport): ?>
                            <th class="sticky-col sticky-rate-rw">Rework Rate</th>
                            <th class="sticky-col sticky-rate-ap">Argon Polish Rate</th>
                            <?php endif; ?>
                            <?php for ($d = 1; $d <= 31; $d++):
                                $wd = checkdate($curMonth, $d, $curYear) ? date('D', strtotime(sprintf('%04d-%02d-%02d', $curYear, $curMonth, $d))) : '';
                                ?>
                                <th class="day-head" data-day="<?php echo $d; ?>">
                                    <span class="day-no"><?php echo $d; ?></span>
                                    <span class="day-wd"><?php echo htmlspecialchars($wd); ?></span>
                                    <div class="ops-subhead"><span class="q-lab">Q</span><span class="r-lab"><?php echo $isBuffReport ? 'RW' : 'R'; ?></span><span class="ap-lab">AP</span><span class="ot-lab">OT</span></div>
                                </th>
                            <?php endfor; ?>
                            <th id="th_total_qty"><?php if ($isBuffReport): ?><span class="th-qty-lab">TOTAL QTY</span><div class="ops-subhead"><span class="q-lab">Q</span><span class="r-lab">RW</span><span class="ap-lab">AP</span></div><?php else: ?>TOTAL QTY<?php endif; ?></th>
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
                        <tr class="ops-row<?php echo $isJobReport ? ' ops-kra-fixed-row' : ''; ?>" data-saved-product-id="<?php echo $itemProductId; ?>" data-saved-grade-id="<?php echo $itemGradeId; ?>">
                            <td class="sticky-col sticky-action">
                                <?php if (!$isJobReport): ?>
                                <button type="button" class="btn-icon btn-remove-row" title="Delete row">
                                    <i class="fa-solid fa-trash-can"></i>
                                </button>
                                <?php else: ?>
                                <span class="ops-kra-lock" title="ERP KRA fixed row"><i class="fa-solid fa-lock" style="color:#94a3b8;font-size:12px;"></i></span>
                                <?php endif; ?>
                            </td>
                            <td class="sticky-col sticky-sr ops-sr"><?php echo $i + 1; ?></td>
                            <td class="sticky-col sticky-name">
                                <?php if ($isJobReport): ?>
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
                                <input type="number" step="0.01" min="0" name="items[<?php echo $i; ?>][rate]" class="form-control grid-input rate" data-rate-kind="qty" value="<?php echo htmlspecialchars(number_format((float) ($item['rate'] ?? 0), 2, '.', '')); ?>" title="Qty Rate">
                            </td>
                            <?php if ($isBuffReport): ?>
                            <td class="sticky-col sticky-rate-rw">
                                <input type="number" step="0.01" min="0" name="items[<?php echo $i; ?>][rejection_rate]" class="form-control grid-input rate-rw row-rejection-rate" data-rate-kind="rework" value="<?php echo htmlspecialchars(number_format((float) ($item['rejection_rate'] ?? 0), 2, '.', '')); ?>" title="Rework Rate">
                            </td>
                            <td class="sticky-col sticky-rate-ap">
                                <input type="number" step="0.01" min="0" name="items[<?php echo $i; ?>][argon_rate]" class="form-control grid-input rate-ap" data-rate-kind="argon" value="<?php echo htmlspecialchars(number_format((float) ($item['argon_rate'] ?? 0), 2, '.', '')); ?>" title="Argon Polish Rate">
                            </td>
                            <?php endif; ?>
                            <?php for ($d = 1; $d <= 31; $d++):
                                $cell = $days[(string) $d];
                                $dis = !empty($cell['disabled']) ? 'disabled' : '';
                                ?>
                                <td class="day-cell" data-day="<?php echo $d; ?>">
                                    <?php $kraRo = $isJobReport ? ' readonly tabindex="-1"' : ''; ?>
                                    <div class="ops-day-triple">
                                        <input type="text" inputmode="decimal" class="grid-input day-qty" <?php echo $dis . $kraRo; ?>
                                               name="items[<?php echo $i; ?>][days][<?php echo $d; ?>][q]" value="<?php echo htmlspecialchars((string) $cell['q']); ?>" autocomplete="off" title="Q — Daily Total">
                                        <input type="text" inputmode="decimal" class="grid-input day-qty-r r-field" <?php echo $dis . $kraRo; ?>
                                               name="items[<?php echo $i; ?>][days][<?php echo $d; ?>][r]" value="<?php echo htmlspecialchars((string) $cell['r']); ?>" autocomplete="off" title="<?php echo $isBuffReport ? 'RW — Rework' : 'R'; ?>">
                                        <input type="text" inputmode="decimal" class="grid-input day-qty-ap ap-field" <?php echo $dis . $kraRo; ?>
                                               name="items[<?php echo $i; ?>][days][<?php echo $d; ?>][ap]" value="<?php echo htmlspecialchars((string) ($cell['ap'] ?? '')); ?>" autocomplete="off" title="AP — Argon Polish">
                                        <input type="text" inputmode="decimal" class="grid-input day-qty-ot ot-field" <?php echo $dis . $kraRo; ?>
                                               name="items[<?php echo $i; ?>][days][<?php echo $d; ?>][ot]" value="<?php echo htmlspecialchars((string) $cell['ot']); ?>" autocomplete="off">
                                    </div>
                                </td>
                            <?php endfor; ?>
                            <?php
                            $rowQ = 0.0;
                            $rowR = 0.0;
                            $rowAp = 0.0;
                            foreach ($days as $sumCell) {
                                if (!empty($sumCell['disabled'])) {
                                    continue;
                                }
                                $rowQ += (float) ($sumCell['q'] ?? 0);
                                $rowR += (float) ($sumCell['r'] ?? 0);
                                $rowAp += (float) ($sumCell['ap'] ?? 0);
                            }
                            $fmtQty = static function ($n) {
                                $n = round((float) $n, 2);
                                if (abs($n - round($n)) < 0.001) {
                                    return (string) (int) round($n);
                                }
                                return number_format($n, 2, '.', '');
                            };
                            $rowAmt = $isBuffReport
                                ? round(
                                    ($rowQ * (float) ($item['rate'] ?? 0))
                                    + ($rowR * (float) ($item['rejection_rate'] ?? 0))
                                    + ($rowAp * (float) ($item['argon_rate'] ?? 0)),
                                    2
                                )
                                : (float) ($item['total_amount'] ?? 0);
                            ?>
                            <td class="<?php echo $isBuffReport ? 'ops-total-split' : ''; ?>">
                                <?php if ($isBuffReport): ?>
                                <div class="ops-day-triple">
                                    <input type="text" name="items[<?php echo $i; ?>][total_qty]" class="form-control grid-input total-qty" readonly title="Q total" value="<?php echo htmlspecialchars($fmtQty($rowQ)); ?>">
                                    <input type="text" name="items[<?php echo $i; ?>][total_r]" class="form-control grid-input total-r" readonly title="RW total" value="<?php echo htmlspecialchars($fmtQty($rowR)); ?>">
                                    <input type="text" class="form-control grid-input total-ap" readonly title="AP total" value="<?php echo htmlspecialchars($fmtQty($rowAp)); ?>">
                                </div>
                                <?php else: ?>
                                <input type="text" name="items[<?php echo $i; ?>][total_qty]" class="form-control grid-input total-qty" readonly value="<?php echo htmlspecialchars((string) ($item['total_qty'] ?? 0)); ?>">
                                <?php endif; ?>
                            </td>
                            <td class="ops-col-r"><?php if (!$isBuffReport): ?><input type="text" name="items[<?php echo $i; ?>][total_r]" class="form-control grid-input total-r" readonly value="<?php echo htmlspecialchars((string) ($item['total_r'] ?? 0)); ?>"><?php endif; ?></td>
                            <td class="ops-col-ot">
                                <input type="number" step="0.01" name="items[<?php echo $i; ?>][ot_rate]" class="form-control grid-input row-ot-rate" readonly value="<?php echo htmlspecialchars((string) ($item['ot_rate'] ?? 0)); ?>">
                            </td>
                            <td class="ops-col-rej">
                                <?php if (!$isBuffReport): ?>
                                <input type="number" step="0.01" name="items[<?php echo $i; ?>][rejection_rate]" class="form-control grid-input row-rejection-rate" readonly value="<?php echo htmlspecialchars((string) ($item['rejection_rate'] ?? 0)); ?>">
                                <?php endif; ?>
                            </td>
                            <td><input type="text" name="items[<?php echo $i; ?>][total_amount]" class="form-control grid-input total-amount" readonly title="<?php echo $isBuffReport ? 'Q × Qty Rate + RW × Rework Rate + AP × Argon Polish Rate' : ''; ?>" value="<?php echo htmlspecialchars(number_format($rowAmt, 2, '.', '')); ?>"></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td class="sticky-col sticky-action" colspan="<?php echo ($showGrade ? 4 : 3) + ($isBuffReport ? 3 : 1); ?>" id="tf_grand_total_label">GRAND TOTAL</td>
                            <?php for ($d = 1; $d <= 31; $d++): ?>
                                <td class="day-foot" data-day="<?php echo $d; ?>">
                                    <?php if ($isBuffReport): ?>
                                    <div class="ops-day-triple foot-totals">
                                        <input type="text" class="grid-input foot-q" readonly tabindex="-1" value="0" title="Q total">
                                        <input type="text" class="grid-input foot-r r-field" readonly tabindex="-1" value="0" title="RW total">
                                        <input type="text" class="grid-input foot-ap ap-field" readonly tabindex="-1" value="0" title="AP total">
                                    </div>
                                    <?php else: ?>
                                    <div class="ops-day-triple foot-totals">
                                        <span class="foot-q"></span>
                                        <span class="foot-r"></span>
                                        <span class="foot-ap"></span>
                                        <span class="foot-ot"></span>
                                    </div>
                                    <?php endif; ?>
                                </td>
                            <?php endfor; ?>
                            <td id="tf_total_qty"><?php if ($isBuffReport): ?><div class="ops-day-triple foot-totals"><span id="tf_sum_q">0</span><span id="tf_sum_rw">0</span><span id="tf_sum_ap">0</span></div><?php else: ?>0<?php endif; ?></td>
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
                <a href="<?php echo htmlspecialchars($listUrl); ?>" class="btn-secondary">Cancel</a>
            </div>
            <?php else: ?>
            <div class="form-actions sticky-actions">
                <a href="<?php echo htmlspecialchars($listUrl); ?>" class="btn-secondary">Back to List</a>
            </div>
            <?php endif; ?>
        </form>
    </div>

    <?php if ($showStaff): ?>
    <!-- ========== BOX 2: CNC staff salary + attendance ========== -->
    <div class="form-page-card ops-page-card ops-clone-box ops-clone-staff-card<?php echo $opsViewOnly ? ' ops-view-only' : ''; ?>">
        <div class="ops-clone-staff-head">
            <h2><i class="fa-solid fa-users"></i> <?php echo htmlspecialchars($reportDeptKey); ?> Department — Normal Salary Employees</h2>
        </div>

        <form method="POST" action="<?php echo app_url('contractor/operations_clone/save_staff.php'); ?>" id="opsStaffForm" class="employee-form" autocomplete="off">
            <input type="hidden" name="sheet_id" value="<?php echo (int) $id; ?>">
            <input type="hidden" name="operation" value="<?php echo htmlspecialchars($curOp); ?>">
            <input type="hidden" name="employee_id" value="<?php echo (int) $curEmp; ?>">
            <input type="hidden" name="month_no" value="<?php echo (int) $curMonth; ?>">
            <input type="hidden" name="year_no" value="<?php echo (int) $curYear; ?>">
            <input type="hidden" name="staff_att_json" id="staffAttJson" value="">

            <div class="ops-clone-staff-wrap">
                <table class="ops-clone-staff-att-table<?php echo $isBuffReport ? ' show-buff-loan' : ''; ?>" id="opsCloneStaffAttTable" data-buff-expense="<?php echo htmlspecialchars(number_format((float) $staffBuffExpense, 2, '.', '')); ?>">
                    <thead>
                        <tr>
                            <th class="sticky-staff sticky-sr">Sr.</th>
                            <th class="sticky-staff sticky-code">Emp Code</th>
                            <th class="sticky-staff sticky-name">Employee Name</th>
                            <th class="sticky-staff sticky-sal num">Salary</th>
                            <th class="sticky-staff sticky-pdays num">P Days</th>
                            <th class="sticky-staff sticky-adays num">A Days</th>
                            <?php if ($isBuffReport): ?>
                            <th class="sticky-staff sticky-loan num">Loan</th>
                            <th class="sticky-staff sticky-expense num">Buff Expense</th>
                            <?php endif; ?>
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
                                <td colspan="<?php echo 7 + ($isBuffReport ? 2 : 0) + $monthDays; ?>" class="empty-msg">No Normal Salary employees found in <?php echo htmlspecialchars($reportDeptKey); ?>.</td>
                            </tr>
                        <?php else: ?>
                            <?php $staffFinalSum = 0.0; $staffPSum = 0; $staffASum = 0; $staffLoanSum = 0.0; ?>
                            <?php foreach ($staffDisplay as $si => $staff):
                                $sid = (int) $staff['id'];
                                $isMainRow = !empty($staff['_is_main']);
                                $empDays = $attMap[$sid] ?? [];
                                $share = $staffSplit['shares'][$sid] ?? null;
                                $finalAmt = $isMainRow
                                    ? round((float) ($staffSplit['remainder'] ?? 0) - (float) $staffBuffExpense, 2)
                                    : (float) ($share['final'] ?? 0);
                                $staffFinalSum += $finalAmt;
                                $pDays = 0;
                                $aDays = 0;
                                for ($cd = 1; $cd <= $monthDays; $cd++) {
                                    $cDate = sprintf('%04d-%02d-%02d', $curYear, $curMonth, $cd);
                                    $cCode = strtoupper(opsCloneAttCodeFromDay($empDays[$cDate] ?? null));
                                    if ($cCode === 'P') {
                                        $pDays++;
                                    } elseif ($cCode === 'A') {
                                        $aDays++;
                                    }
                                }
                                $staffPSum += $pDays;
                                $staffASum += $aDays;
                                $rowLoan = $isMainRow ? 0.0 : (float) ($staffLoanById[$sid] ?? 0);
                                $staffLoanSum += $rowLoan;
                                $baseAmt = $isMainRow ? 0 : (float) ($share['amount'] ?? 0);
                                $rowExpense = ($isMainRow && $staffBuffExpense > 0) ? (float) $staffBuffExpense : 0.0;
                                $finalTitle = $isMainRow
                                    ? ('Contract total minus staff salary, plus staff loan'
                                        . ($rowExpense > 0 ? ' − Buff Expense ' . number_format($rowExpense, 2, '.', '') : ''))
                                    : ('Salary ' . number_format((float) ($share['salary'] ?? 0), 2, '.', '')
                                        . ' / ' . (int) ($share['working_days'] ?? 0) . ' working days × '
                                        . (float) ($share['paid_days'] ?? 0) . ' P days'
                                        . ($rowLoan > 0 ? ' − loan ' . number_format($rowLoan, 2, '.', '') : ''));
                                ?>
                                <tr data-emp="<?php echo $sid; ?>" class="<?php echo $isMainRow ? 'is-main-contractor' : ''; ?>">
                                    <td class="sticky-staff sticky-sr"><?php echo $si + 1; ?></td>
                                    <td class="sticky-staff sticky-code"><span class="code-badge"><?php echo htmlspecialchars((string) $staff['employee_code']); ?></span></td>
                                    <td class="sticky-staff sticky-name" title="<?php echo htmlspecialchars((string) $staff['employee_name']); ?>"><?php echo htmlspecialchars((string) $staff['employee_name']); ?><?php echo $isMainRow ? ' (Main Contractor)' : ''; ?></td>
                                    <td class="sticky-staff sticky-sal num"><?php echo number_format((float) ($staff['decided_salary'] ?? 0), 0, '.', ''); ?></td>
                                    <td class="sticky-staff sticky-pdays num staff-p-days"><?php echo (int) $pDays; ?></td>
                                    <td class="sticky-staff sticky-adays num staff-a-days"><?php echo (int) $aDays; ?></td>
                                    <?php if ($isBuffReport): ?>
                                    <td class="sticky-staff sticky-loan num"><?php echo $rowLoan > 0 ? number_format($rowLoan, 2, '.', '') : ''; ?></td>
                                    <td class="sticky-staff sticky-expense num"><?php echo $rowExpense > 0 ? number_format($rowExpense, 2, '.', '') : ''; ?></td>
                                    <?php endif; ?>
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
                    <?php if (!empty($staffDisplay)): ?>
                    <tfoot>
                        <tr>
                            <td class="sticky-staff sticky-sr"></td>
                            <td class="sticky-staff sticky-code"></td>
                            <td class="sticky-staff sticky-name">Total</td>
                            <td class="sticky-staff sticky-sal"></td>
                            <td class="sticky-staff sticky-pdays num" id="opsStaffPSum"><?php echo (int) $staffPSum; ?></td>
                            <td class="sticky-staff sticky-adays num" id="opsStaffASum"><?php echo (int) $staffASum; ?></td>
                            <?php if ($isBuffReport): ?>
                            <td class="sticky-staff sticky-loan num"><?php echo $staffLoanSum > 0 ? number_format($staffLoanSum, 2, '.', '') : ''; ?></td>
                            <td class="sticky-staff sticky-expense num"><?php echo $staffBuffExpense > 0 ? number_format((float) $staffBuffExpense, 2, '.', '') : ''; ?></td>
                            <?php endif; ?>
                            <td class="sticky-staff sticky-final num" id="opsStaffFinalSum"><?php echo number_format($staffFinalSum, 2, '.', ''); ?></td>
                            <td colspan="<?php echo (int) $monthDays; ?>"></td>
                        </tr>
                    </tfoot>
                    <?php endif; ?>
                </table>
            </div>

            <?php if (!$opsViewOnly): ?>
            <div class="form-actions sticky-actions">
                <button type="submit" class="btn-primary" id="btnSaveStaff"><i class="fa-solid fa-check"></i> Save Staff Salary + Attendance</button>
                <a href="<?php echo htmlspecialchars($listUrl); ?>" class="btn-secondary">Cancel</a>
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
    window.OPS_KRA_PARTS = <?php echo $isJobReport ? 'true' : 'false'; ?>;
    window.OPS_KRA_FIXED = <?php echo $isJobReport ? 'true' : 'false'; ?>; // fixed rows, no process dropdown
    window.OPS_KRA_AS_OF = <?php echo json_encode($kraAsOf); ?>;
    window.OPS_KRA_DAY_QTY = <?php echo json_encode($erpDayQtyByPart ?? [], JSON_UNESCAPED_UNICODE); ?>;
    window.OPS_BUFF_DAY_EXTRA = <?php echo json_encode($erpBuffExtraByPart ?? [], JSON_UNESCAPED_UNICODE); ?>;
    window.OPS_REPAIR = <?php echo $repairJson; ?>;
    window.OPS_OT_REPAIR = <?php echo $otJson; ?>;
    window.OPS_GRADE_OPS = <?php
        $gradeOpsJs = array_values(contractorShowGradeOps());
        if ($isJobReport) {
            $gradeOpsJs = array_values(array_filter($gradeOpsJs, static function ($op) use ($curOp) {
                return strcasecmp((string) $op, $curOp) !== 0;
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
    (function () {
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
        function refreshStaffDayCounts() {
            var pTotal = 0;
            var aTotal = 0;
            document.querySelectorAll('#opsCloneStaffAttTable tbody tr[data-emp]').forEach(function (tr) {
                var p = 0;
                var a = 0;
                tr.querySelectorAll('.staff-att-input').forEach(function (inp) {
                    var v = (inp.value || '').trim().toUpperCase();
                    if (v === 'P') p++;
                    else if (v === 'A') a++;
                });
                var pEl = tr.querySelector('.staff-p-days');
                var aEl = tr.querySelector('.staff-a-days');
                if (pEl) pEl.textContent = String(p);
                if (aEl) aEl.textContent = String(a);
                pTotal += p;
                aTotal += a;
            });
            var pSum = document.getElementById('opsStaffPSum');
            var aSum = document.getElementById('opsStaffASum');
            if (pSum) pSum.textContent = String(pTotal);
            if (aSum) aSum.textContent = String(aTotal);
        }
        if (staffForm) {
            staffForm.addEventListener('input', function (ev) {
                if (ev.target && ev.target.classList && ev.target.classList.contains('staff-att-input')) {
                    refreshStaffDayCounts();
                }
            });
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
            var expenseTable = document.getElementById('opsCloneStaffAttTable');
            var expense = expenseTable ? (parseFloat(expenseTable.getAttribute('data-buff-expense')) || 0) : 0;
            var rem = Math.round((pool - distributed - expense) * 100) / 100;
            var mainTd = document.querySelector('#opsCloneStaffAttTable td.staff-final[data-main="1"]');
            if (mainTd) mainTd.textContent = rem.toFixed(2);
            var grand = 0;
            document.querySelectorAll('#opsCloneStaffAttTable td.staff-final').forEach(function (td) {
                grand += parseFloat(td.textContent) || 0;
            });
            var sumEl = document.getElementById('opsStaffFinalSum');
            if (sumEl) sumEl.textContent = (Math.round(grand * 100) / 100).toFixed(2);
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
