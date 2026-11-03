<?php
/**
 * Monthly salary register — Normal Fixed Salary / Jobwork Regular / Actual (Qty × Rate)
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/employee_helper.php';
require_once __DIR__ . '/../includes/master_helper.php';
require_once __DIR__ . '/../includes/salary_register_helper.php';
require_once __DIR__ . '/../includes/payroll_reports_helper.php';
require_once __DIR__ . '/../includes/settings.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permission_helper.php';
require_once __DIR__ . '/../includes/payroll_approval_helper.php';

requireLogin();

$deptId = isset($_GET['department_id']) ? (int) $_GET['department_id'] : 0;
requirePayrollRegisterAccess($deptId);
ensurePayrollApprovalTables();
$employeeId = isset($_GET['employee_id']) ? (int) $_GET['employee_id'] : 0;
$month = (int) ($_GET['month'] ?? date('n'));
$year = (int) ($_GET['year'] ?? date('Y'));
$type = (string) ($_GET['type'] ?? 'salary');
$view = (($_GET['view'] ?? 'department') === 'employee') ? 'employee' : 'department';
if ($month < 1 || $month > 12) {
    $month = (int) date('n');
}
// Main Contractor is not a separate sheet — same as NEFT (Normal + Jobwork only)
if ($type === 'contractor_main' || $type === 'jobwork_govt') {
    $type = 'jobwork_actual';
}
if (!isset(salaryRegisterTypes()[$type])) {
    $type = 'salary';
}

// Same source as NEFT: locked snapshot when locked, else live
$displayBundle = getSalaryRegisterDisplayBundle($type, $month, $year, $deptId, $employeeId);
$lock = $displayBundle['lock'];
$isLocked = !empty($displayBundle['from_lock']);
// Arrears always editable on every department — value adds to Net (also patches lock)
$canEditArrears = true;
// Memo (Sales On Field): clickable deduction like Arrears
$canEditMemo = true;
$approvalStatus = $isLocked ? (string) ($lock['approval_status'] ?? 'approved') : '';
$approvalLabels = payrollApprovalStatusLabels();
$myApprovalAction = $isLocked ? payrollApprovalPendingActionFor($lock) : '';
$approvalLog = fetchPayrollApprovalLog($month, $year, $type, $deptId);

$department = $deptId > 0 ? getDepartmentById($deptId) : null;
$monthDays = (int) date('t', strtotime(sprintf('%04d-%02d-01', $year, $month)));

$groups = $displayBundle['groups'];

if ($view === 'employee' && $type !== 'contractor_main') {
    $flat = [];
    foreach ($groups as $g) {
        foreach ($g['rows'] as $row) {
            $flat[] = $row;
        }
    }
    usort($flat, function ($a, $b) {
        return strcasecmp($a['employee_name'], $b['employee_name']);
    });
    $groups = [[
        'title' => salaryRegisterTypes()[$type] . ' · Employee wise',
        'subtitle' => $department ? $department['department_name'] : 'All departments',
        'mode' => $type,
        'rows' => $flat,
    ]];
}

$filterPay = 'Salary';
if ($type === 'jobwork_govt' || $type === 'jobwork_actual') {
    $filterPay = 'Jobwork';
} elseif ($type === 'contractor_main') {
    $filterPay = 'ContractorMain';
}
$empOptions = fetchSalaryRegisterEmployees($deptId, 0, [$filterPay]);

$pageTitle = 'Salary Register';
$useSidebar = true;
$sidebarMode = $deptId > 0 ? 'department' : 'workspace';
$sidebarDeptId = $deptId;
$sidebarActive = 'salary_register';
$companyName = function_exists('getCompanyName') ? getCompanyName() : 'Company';
$monthLabel = date('F Y', mktime(0, 0, 0, $month, 1, $year));
$isActual = ($type === 'jobwork_actual');
$extraJs = ['assets/js/report_emp_search.js'];

$excelQs = http_build_query([
    'type' => $type,
    'month' => $month,
    'year' => $year,
    'department_id' => $deptId,
    'employee_id' => $employeeId,
    'view' => $view,
]);
// NEFT always opens Normal + Jobwork combined
$neftQs = http_build_query([
    'type' => 'all',
    'month' => $month,
    'year' => $year,
    'department_id' => $deptId,
]);

require_once __DIR__ . '/../includes/header.php';
?>

<main class="dashboard-main">
    <div class="page-toolbar flex-between">
        <?php if ($deptId > 0): ?>
            <a href="<?php echo app_url('department.php?id=' . $deptId); ?>" class="back-link">
                <i class="fa-solid fa-arrow-left"></i> Back to Modules
            </a>
        <?php else: ?>
            <a href="<?php echo app_url('dashboard.php'); ?>" class="back-link">
                <i class="fa-solid fa-arrow-left"></i> Back to Dashboard
            </a>
        <?php endif; ?>
        <div class="register-toolbar-actions">
            <a class="btn-primary" href="<?php echo app_url('payroll/neft.php?' . $neftQs); ?>">
                <i class="fa-solid fa-building-columns"></i> NEFT Sheet (Normal + Jobwork)
            </a>
            <a class="btn-secondary" href="<?php echo app_url('payroll/register_excel.php?' . $excelQs); ?>">
                <i class="fa-solid fa-file-excel"></i> Excel
            </a>
            <a class="btn-primary" href="<?php echo app_url('payroll/register_print.php?' . $excelQs); ?>" target="_blank">
                <i class="fa-solid fa-print"></i> Print
            </a>
        </div>
    </div>

    <?php if (isset($_GET['lock_msg'])): ?>
        <?php
        $lm = (string) $_GET['lock_msg'];
        $lockNote = '';
        if ($lm === 'unlocked') {
            $lockNote = 'Register unlocked. You can edit attendance / regenerate and lock again.';
        } elseif ($lm === 'locked') {
            $lockNote = 'Salary Register locked and sent for approval.';
        } elseif ($lm === 'appr_pending_accounts') {
            $lockNote = 'Checked and approved. Sent to Accounts for final approval.';
        } elseif ($lm === 'appr_approved') {
            $lockNote = 'Approved. NEFT sheet is now final.';
        } elseif ($lm === 'appr_sent_back') {
            $lockNote = 'Sent back to Payroll Manager. Register is unlocked for correction.';
        } elseif ($lm === 'error') {
            $lockNote = 'Lock failed: ' . htmlspecialchars((string) ($_GET['lock_err'] ?? 'Unknown error'));
        }
        ?>
        <?php if ($lockNote !== ''): ?>
            <div class="form-page-card" style="margin-bottom:12px;border-left:4px solid <?php echo $lm === 'error' ? '#ef4444' : '#16a085'; ?>;">
                <p style="margin:0;"><?php echo $lockNote; ?></p>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <div class="sr-print-heading">
        <strong><?php echo htmlspecialchars($companyName); ?></strong>
        — Salary Register · <?php echo htmlspecialchars($monthLabel); ?>
    </div>

    <div class="form-page-card no-print-shadow">
        <div class="form-page-header">
            <div>
                <h1>Salary Register <?php if ($isLocked): ?><span class="status-pill status-deactive" style="vertical-align:middle;"><i class="fa-solid fa-lock"></i> Locked</span><?php endif; ?></h1>
                <p><?php echo htmlspecialchars($companyName); ?> · <?php echo htmlspecialchars($monthLabel); ?> · Month days <?php echo $monthDays; ?></p>
            </div>
        </div>

        <?php
        // Live sheet counts (match table) — not stale lock snapshot metadata
        $liveSheetRows = [];
        foreach ($groups as $gChip) {
            foreach ($gChip['rows'] ?? [] as $rChip) {
                if (!empty($rChip['is_normal_gross_total'])) {
                    continue;
                }
                $liveSheetRows[] = $rChip;
            }
        }
        $liveSheetSum = registerSumMoneyColumns($liveSheetRows);
        $liveSheetNeft = 0;
        foreach ($liveSheetRows as $rChip) {
            if ((float) ($rChip['net'] ?? 0) > 0) {
                $liveSheetNeft++;
            }
        }
        // Same combined totals as NEFT Sheet (Normal + Jobwork)
        $neftCombo = getCombinedNeftSheetData($month, $year, $deptId);
        $neftComboNormalCnt = (int) ($neftCombo['normal_count'] ?? 0);
        $neftComboJwCnt = (int) ($neftCombo['jobwork_count'] ?? 0);
        $neftComboNormalTot = (float) ($neftCombo['normal_total'] ?? 0);
        $neftComboJwTot = (float) ($neftCombo['jobwork_total'] ?? 0);
        $neftComboFinal = (float) ($neftCombo['final_total'] ?? $neftCombo['neft_total'] ?? 0);
        ?>
        <?php if ($isLocked): ?>
            <div class="ops-live-summary" style="margin-bottom:12px;">
                <span class="ops-chip"><i class="fa-solid fa-lock"></i> Finalized <?php echo htmlspecialchars((string) ($lock['locked_at'] ?? '')); ?></span>
                <span class="ops-chip"><strong><?php echo count($liveSheetRows); ?></strong> on this sheet</span>
                <span class="ops-chip">Sheet Net ₹ <?php echo formatIndianNumber((float) ($liveSheetSum['net'] ?? 0), 2); ?></span>
                <span class="ops-chip"><?php echo (int) $liveSheetNeft; ?> with Net &gt; 0</span>
                <span class="ops-chip" style="font-weight:700;color:<?php echo $approvalStatus === 'approved' ? '#047857' : '#b45309'; ?>;">
                    <i class="fa-solid <?php echo $approvalStatus === 'approved' ? 'fa-circle-check' : 'fa-hourglass-half'; ?>"></i>
                    <?php echo htmlspecialchars($approvalLabels[$approvalStatus] ?? $approvalStatus); ?>
                </span>
                <span class="ops-chip" style="color:#047857;"><i class="fa-solid fa-calendar-check"></i> Days/Gross = live Attendance Report</span>
            </div>
            <div class="ops-live-summary" style="margin-bottom:12px;">
                <span class="ops-chip" style="background:#ecfdf5;"><strong>NEFT Normal</strong> <?php echo $neftComboNormalCnt; ?> · ₹ <?php echo formatIndianNumber($neftComboNormalTot, 2); ?></span>
                <span class="ops-chip" style="background:#eff6ff;"><strong>NEFT Jobwork</strong> <?php echo $neftComboJwCnt; ?> · ₹ <?php echo formatIndianNumber($neftComboJwTot, 2); ?></span>
                <span class="ops-chip" style="background:#fef3c7;"><strong>NEFT Final</strong> <?php echo $neftComboNormalCnt + $neftComboJwCnt; ?> · ₹ <?php echo formatIndianNumber($neftComboFinal, 2); ?></span>
                <span class="ops-chip" style="color:#16a085;"><i class="fa-solid fa-equals"></i> same as NEFT Sheet</span>
            </div>
        <?php else: ?>
            <div class="ops-live-summary" style="margin-bottom:12px;">
                <span class="ops-chip"><strong><?php echo count($liveSheetRows); ?></strong> on this sheet</span>
                <span class="ops-chip">Sheet Net ₹ <?php echo formatIndianNumber((float) ($liveSheetSum['net'] ?? 0), 2); ?></span>
                <span class="ops-chip"><?php echo (int) $liveSheetNeft; ?> with Net &gt; 0</span>
                <span class="ops-chip" style="background:#ecfdf5;"><strong>NEFT Normal</strong> <?php echo $neftComboNormalCnt; ?> · ₹ <?php echo formatIndianNumber($neftComboNormalTot, 2); ?></span>
                <span class="ops-chip" style="background:#eff6ff;"><strong>NEFT Jobwork</strong> <?php echo $neftComboJwCnt; ?> · ₹ <?php echo formatIndianNumber($neftComboJwTot, 2); ?></span>
                <span class="ops-chip" style="background:#fef3c7;"><strong>NEFT Final</strong> <?php echo $neftComboNormalCnt + $neftComboJwCnt; ?> · ₹ <?php echo formatIndianNumber($neftComboFinal, 2); ?></span>
            </div>
        <?php endif; ?>
        <?php if ($isLocked): ?>
            <?php if ($myApprovalAction !== ''): ?>
            <div class="form-page-card" style="margin-bottom:12px;border-left:4px solid #d2232a;background:#fff7f7;">
                <p style="margin:0 0 8px;">
                    <strong><i class="fa-solid fa-user-check" style="color:#d2232a;"></i>
                    <?php echo $myApprovalAction === 'check' ? 'Your check is pending (HR Head)' : 'Your final approval is pending (Accounts)'; ?></strong>
                    · Review the register below, then approve or send back with a remark.
                </p>
                <form method="POST" action="<?php echo app_url('payroll/approval_action.php'); ?>" class="employee-form">
                    <input type="hidden" name="lock_id" value="<?php echo (int) $lock['id']; ?>">
                    <div class="form-group" style="margin-bottom:8px;">
                        <textarea name="remarks" class="form-control" rows="2" placeholder="Remark (required for Send Back)"></textarea>
                    </div>
                    <div style="display:flex;gap:8px;flex-wrap:wrap;">
                        <?php if ($myApprovalAction === 'check'): ?>
                            <button type="submit" name="action" value="check_approve" class="btn-primary" onclick="return confirm('Approve this salary register and send it to Accounts?');">
                                <i class="fa-solid fa-check"></i> Checked · Approve
                            </button>
                        <?php else: ?>
                            <button type="submit" name="action" value="accounts_done" class="btn-primary" onclick="return confirm('Mark Done? NEFT sheet will become final.');">
                                <i class="fa-solid fa-check-double"></i> Done · Finalize NEFT
                            </button>
                        <?php endif; ?>
                        <button type="submit" name="action" value="send_back" class="btn-secondary" onclick="return confirm('Send back to Payroll Manager? The register will be unlocked.');">
                            <i class="fa-solid fa-rotate-left"></i> Send Back
                        </button>
                    </div>
                </form>
            </div>
            <?php endif; ?>
            <div class="form-actions" style="margin-bottom:12px;gap:8px;">
                <a class="btn-primary" href="<?php echo app_url('payroll/neft.php?' . $neftQs); ?>">
                    <i class="fa-solid fa-building-columns"></i> <?php echo $approvalStatus === 'approved' ? 'Open NEFT Sheet' : 'NEFT Sheet (Draft)'; ?>
                </a>
                <?php if (function_exists('isAdmin') && isAdmin()): ?>
                    <form method="POST" action="<?php echo app_url('payroll/register_lock.php'); ?>" style="display:inline;" onsubmit="return confirm('Unlock this finalized register?');">
                        <input type="hidden" name="action" value="unlock">
                        <input type="hidden" name="type" value="<?php echo htmlspecialchars($type); ?>">
                        <input type="hidden" name="month" value="<?php echo $month; ?>">
                        <input type="hidden" name="year" value="<?php echo $year; ?>">
                        <input type="hidden" name="department_id" value="<?php echo $deptId; ?>">
                        <button type="submit" class="btn-secondary"><i class="fa-solid fa-lock-open"></i> Unlock (Admin)</button>
                    </form>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <?php if (payrollCanLock()): ?>
            <div class="form-actions" style="margin-bottom:12px;">
                <form method="POST" action="<?php echo app_url('payroll/register_lock.php'); ?>" onsubmit="return confirm('Lock this Salary Register and send it for approval? It cannot be changed unless it is sent back or Admin unlocks it.');">
                    <input type="hidden" name="action" value="lock">
                    <input type="hidden" name="type" value="<?php echo htmlspecialchars($type); ?>">
                    <input type="hidden" name="month" value="<?php echo $month; ?>">
                    <input type="hidden" name="year" value="<?php echo $year; ?>">
                    <input type="hidden" name="department_id" value="<?php echo $deptId; ?>">
                    <button type="submit" class="btn-primary"><i class="fa-solid fa-lock"></i> Lock &amp; Send for Approval</button>
                </form>
            </div>
            <?php endif; ?>
        <?php endif; ?>

        <?php if ($approvalLog): ?>
            <?php
            $logLabels = [
                'locked' => 'Locked by Maker',
                'checked' => 'Checked & approved (HR Head)',
                'approved' => 'Done (Accounts) · NEFT final',
                'sent_back_checker' => 'Sent back by HR Head',
                'sent_back_accounts' => 'Sent back by Accounts',
                'unlocked_admin' => 'Unlocked by Admin',
            ];
            ?>
            <details class="form-page-card" style="margin-bottom:12px;padding:10px 14px;" <?php echo !$isLocked ? 'open' : ''; ?>>
                <summary style="cursor:pointer;font-weight:700;"><i class="fa-solid fa-clock-rotate-left" style="color:#d2232a;"></i> Approval History (<?php echo count($approvalLog); ?>)</summary>
                <ul style="margin:10px 0 0;padding-left:18px;line-height:1.7;">
                    <?php foreach ($approvalLog as $lg): ?>
                        <li>
                            <strong><?php echo htmlspecialchars($logLabels[$lg['action']] ?? $lg['action']); ?></strong>
                            · <?php echo htmlspecialchars(payrollUserDisplayName($lg['user_id']) ?: '-'); ?>
                            · <small><?php echo htmlspecialchars((string) $lg['created_at']); ?></small>
                            <?php if (trim((string) $lg['remarks']) !== ''): ?>
                                <br><em><?php echo htmlspecialchars((string) $lg['remarks']); ?></em>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </details>
        <?php endif; ?>

        <form method="GET" class="employee-form register-filters">
            <div class="form-grid form-grid-3">
                <div class="form-group">
                    <label>Register Type</label>
                    <select name="type" class="form-control" onchange="this.form.submit()">
                        <?php foreach (salaryRegisterTypes() as $key => $label): ?>
                            <option value="<?php echo htmlspecialchars($key); ?>" <?php echo $type === $key ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>View</label>
                    <select name="view" class="form-control" onchange="this.form.submit()">
                        <option value="department" <?php echo $view === 'department' ? 'selected' : ''; ?>>Department wise</option>
                        <option value="employee" <?php echo $view === 'employee' ? 'selected' : ''; ?>>Employee wise</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Department</label>
                    <select name="department_id" class="form-control" onchange="this.form.submit()">
                        <option value="0">All departments</option>
                        <?php foreach (getActiveMasterRows('departments', 'sort_order ASC, department_name ASC') as $d): ?>
                            <option value="<?php echo (int) $d['id']; ?>" <?php echo $deptId === (int) $d['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($d['department_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Month</label>
                    <select name="month" class="form-control" onchange="this.form.submit()">
                        <?php for ($m = 1; $m <= 12; $m++): ?>
                            <option value="<?php echo $m; ?>" <?php echo $m === $month ? 'selected' : ''; ?>>
                                <?php echo date('F', mktime(0, 0, 0, $m, 1)); ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Year</label>
                    <input type="number" name="year" class="form-control" value="<?php echo $year; ?>" onchange="this.form.submit()">
                </div>
                <div class="form-group">
                    <label>Employee</label>
                    <select name="employee_id" class="form-control" onchange="this.form.submit()">
                        <option value="0">All employees</option>
                        <?php foreach ($empOptions as $eo): ?>
                            <option value="<?php echo (int) $eo['id']; ?>" <?php echo $employeeId === (int) $eo['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars(($eo['employee_code'] ?? '') . ' — ' . ($eo['employee_name'] ?? '')); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </form>

        <p class="form-hint"><i class="fa-solid fa-pen-to-square" style="color:#d2232a;"></i> <strong>Arrears</strong> cell પર click → amount + remark save · Net = Gross − Deduction + Arrears.</p>
        <p class="form-hint"><i class="fa-solid fa-file-lines" style="color:#d2232a;"></i> Sales On Field: <strong>Memo</strong> cell પર click → amount + remark · save પછી <strong>deduction</strong> · Net = Gross − Deduction − Memo + Arrears.</p>
    </div>

    <?php
    $grandRows = [];
    foreach ($groups as $g) {
        foreach ($g['rows'] as $gr) {
            if (!empty($gr['is_normal_gross_total'])) {
                continue; // subtotal row — do not double-count Gross
            }
            $grandRows[] = $gr;
        }
    }
    $grandSum = registerSumMoneyColumns($grandRows);
    ?>
    <div class="report-toolbar-row">
        <div class="ops-live-summary">
            <span class="ops-chip"><?php echo htmlspecialchars($monthLabel); ?></span>
            <span class="ops-chip"><?php echo count($grandRows); ?> employees</span>
        </div>
        <div class="report-emp-search">
            <label class="report-emp-search-box" for="srEmpSearch">
                <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                <input type="search" id="srEmpSearch" placeholder="Search employee code or name…"
                       data-report-emp-search=".salary-register-table tbody tr"
                       data-report-emp-count="#srEmpSearchCount"
                       autocomplete="off">
                <button type="button" class="report-emp-search-clear" data-report-emp-clear title="Clear">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </label>
            <span class="report-emp-search-count" id="srEmpSearchCount"></span>
        </div>
    </div>
    <?php foreach ($groups as $g): ?>
        <?php
        $rows = $g['rows'];
        $deptSumRows = array_values(array_filter($rows, static function ($r) {
            return empty($r['is_normal_gross_total']);
        }));
        $deptSum = registerSumMoneyColumns($deptSumRows);
        $groupDeptName = (string) (($rows[0]['department'] ?? '') ?: '');
        $groupDeptId = (int) ($rows[0]['department_id'] ?? 0);
        $showMemo = $groupDeptId > 0
            ? isSalesOnFieldDepartmentId($groupDeptId)
            : isSalesOnFieldDepartment($groupDeptName);
        if (!$showMemo && $deptId > 0) {
            $showMemo = isSalesOnFieldDepartmentId($deptId) || isSalesOnFieldDepartment($department['department_name'] ?? '');
        }
        // NET group: Tot. Ded. + [Memo] + Arrears + Net
        $netColspan = $showMemo ? 4 : 3;
        ?>
        <div class="salary-register-sheet" <?php echo $showMemo ? 'data-sr-memo="1"' : ''; ?>>
            <div class="salary-register-banner">
                <strong><?php echo htmlspecialchars($g['title']); ?></strong>
                <span><?php echo htmlspecialchars($g['subtitle']); ?> · <?php echo htmlspecialchars($monthLabel); ?></span>
            </div>
            <div class="table-wrap">
                <table class="salary-register-table">
                    <thead>
                        <tr class="sr-group-row">
                            <th colspan="5"></th>
                            <?php if ($isActual): ?>
                                <th colspan="3" class="sr-earn">Earning</th>
                            <?php else: ?>
                                <th colspan="8" class="sr-earn">Earning</th>
                                <th colspan="2" class="sr-gross">Gross</th>
                            <?php endif; ?>
                            <th colspan="4" class="sr-ded">Deduction</th>
                            <th colspan="<?php echo (int) $netColspan; ?>" class="sr-net">NET</th>
                            <th colspan="3"></th>
                        </tr>
                        <tr>
                            <th class="sr-sticky sr-sticky-1">Emp Code</th>
                            <th class="sr-sticky sr-sticky-2">Employee Name</th>
                            <th>Desig.</th>
                            <th>D.O.J.</th>
                            <th>UAN</th>
                            <?php if ($isActual): ?>
                                <th>Qty</th>
                                <th>Actual Amt</th>
                                <th>Gross</th>
                            <?php else: ?>
                                <th>Salary</th>
                                <th>P.Days</th>
                                <th>W.Off</th>
                                <th>Holiday</th>
                                <th>P.L.</th>
                                <th>S.L.</th>
                                <th>D.L.</th>
                                <th>C-Off</th>
                                <th>Total Days</th>
                                <th>Gross</th>
                            <?php endif; ?>
                            <th>Emp PF</th>
                            <th>P.T.</th>
                            <th>Loan</th>
                            <th>Adv.</th>
                            <th>Tot. Ded.</th>
                            <?php if ($showMemo): ?>
                                <th>Memo</th>
                            <?php endif; ?>
                            <th>Arrears</th>
                            <th>Net</th>
                            <th>A/C No.</th>
                            <th>IFSC</th>
                            <th>Remarks</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!$rows): ?>
                        <tr><td colspan="<?php echo $showMemo ? 27 : 26; ?>" class="empty-cell">No employees for this register.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($rows as $r): ?>
                        <?php
                        if (!empty($r['is_normal_gross_total'])) {
                            $colBeforeGross = $isActual ? 7 : 14; // emp cols + earning cols before Gross
                            $ngtTitle = trim((string) ($r['employee_name'] ?? 'Normal Salary Gross Total'));
                            if ($ngtTitle === '') {
                                $ngtTitle = 'Normal Salary Gross Total';
                            }
                            ?>
                            <tr class="sr-normal-gross-total" style="background:#fef3c7;font-weight:700;" data-emp-search="normal salary fund total">
                                <td class="sr-sticky sr-sticky-1" colspan="2"><strong><?php echo htmlspecialchars($ngtTitle); ?></strong></td>
                                <td colspan="<?php echo (int) ($colBeforeGross - 2); ?>"></td>
                                <td class="num"><strong><?php echo registerNum($r['gross'] ?? 0, 0); ?></strong></td>
                                <td class="num">0</td>
                                <td class="num"><strong><?php echo registerNum($r['pt'] ?? 0, 0); ?></strong></td>
                                <td class="num">0</td>
                                <td class="num">0</td>
                                <td class="num"><strong><?php echo registerNum($r['total_deduction'] ?? ($r['pt'] ?? 0), 0); ?></strong></td>
                                <?php if ($showMemo): ?><td class="num">0</td><?php endif; ?>
                                <td class="num">0</td>
                                <td class="num"><strong><?php echo registerNum($r['net'] ?? 0, 0); ?></strong></td>
                                <td colspan="3" class="sr-code" style="text-align:left;padding-left:10px;">
                                    <?php echo htmlspecialchars((string) ($r['remarks'] ?? 'Σ under Main')); ?>
                                </td>
                            </tr>
                            <?php
                            continue;
                        }
                        $srEmpSearch = trim((string) ($r['employee_code'] ?? '') . ' ' . (string) ($r['employee_name'] ?? ''));
                        ?>
                        <tr data-emp-search="<?php echo htmlspecialchars($srEmpSearch, ENT_QUOTES, 'UTF-8'); ?>">
                            <td class="sr-sticky sr-sticky-1"><?php echo htmlspecialchars($r['employee_code'] ?: '-'); ?></td>
                            <td class="sr-sticky sr-sticky-2">
                                <strong><?php echo htmlspecialchars($r['employee_name']); ?></strong>
                                <?php if ($r['main_contractor']): ?>
                                    <div class="sr-code">Under: <?php echo htmlspecialchars($r['main_contractor']); ?></div>
                                <?php endif; ?>
                                <?php if (!empty($r['contractor_main_share']) && (float) $r['contractor_main_share'] > 0): ?>
                                    <div class="sr-code" style="color:#047857;">+ Contractor share ₹ <?php echo registerNum($r['contractor_main_share']); ?></div>
                                <?php endif; ?>
                                <?php if (!empty($r['contractor_split_out'])): ?>
                                    <?php $fundLbl = trim((string) ($r['contractor_fund_label'] ?? 'Normal Gross')); if ($fundLbl === '') { $fundLbl = 'Normal Gross'; } ?>
                                    <div class="sr-code" style="color:#b45309;">
                                        Pool ₹ <?php echo registerNum($r['contractor_pool'] ?? 0); ?>
                                        − <?php echo htmlspecialchars($fundLbl); ?> ₹ <?php echo registerNum($r['contractor_distributed'] ?? 0); ?>
                                        = <strong>Remainder ₹ <?php echo registerNum($r['contractor_remainder'] ?? $r['net'] ?? 0); ?></strong>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($r['designation']); ?></td>
                            <td><?php echo htmlspecialchars($r['doj']); ?></td>
                            <td><?php echo htmlspecialchars($r['uan'] ?: '-'); ?></td>
                            <?php if ($isActual): ?>
                                <td class="num"><?php echo registerNum($r['qty'], 2); ?></td>
                                <td class="num"><?php echo registerNum($r['actual'], 0); ?></td>
                                <td class="num"><?php echo registerNum($r['gross'], 0); ?></td>
                            <?php else: ?>
                                <td class="num"><?php echo registerNum($r['salary'], 0); ?></td>
                                <td class="num"><?php echo registerNum($r['present'], 1); ?></td>
                                <td class="num"><?php echo registerNum($r['week_off'], 1); ?></td>
                                <td class="num"><?php echo registerNum($r['holiday'] ?? 0, 1); ?></td>
                                <td class="num"><?php echo registerNum($r['pl'], 1); ?></td>
                                <td class="num"><?php echo registerNum($r['sl'], 1); ?></td>
                                <td class="num"><?php echo registerNum($r['dl'], 1); ?></td>
                                <td class="num"><?php echo registerNum($r['coff'] ?? 0, 1); ?></td>
                                <td class="num"><?php echo registerNum($r['total_days'], 1); ?></td>
                                <td class="num"><?php echo registerNum($r['gross'], 0); ?></td>
                            <?php endif; ?>
                            <td class="num"><?php echo registerNum($r['pf'], 0); ?></td>
                            <td class="num"><?php echo registerNum($r['pt'], 0); ?></td>
                            <td class="num"><?php echo registerNum($r['loan'], 0); ?></td>
                            <td class="num"><?php echo registerNum($r['advance'], 0); ?></td>
                            <td class="num"><?php echo registerNum($r['total_deduction'], 0); ?></td>
                            <?php
                            $memoVal = (float) ($r['memo'] ?? 0);
                            $memoRemarkShow = trim((string) ($r['memo_remark'] ?? ''));
                            if ($memoRemarkShow === '' || $memoRemarkShow === '-') {
                                $memoRemarkShow = '';
                            }
                            $arrearsVal = (float) ($r['arrears'] ?? 0);
                            // Arrears edit: Net = (Gross − Ded − Memo) + Arrears
                            $netBaseArrears = (float) ($r['gross'] ?? 0) - (float) ($r['total_deduction'] ?? 0) - $memoVal;
                            // Memo edit: Net = (Gross − Ded + Arrears) − Memo
                            $netBaseMemo = (float) ($r['gross'] ?? 0) - (float) ($r['total_deduction'] ?? 0) + $arrearsVal;
                            $netShow = (float) ($r['net'] ?? 0);
                            $remarkShow = trim((string) ($r['remarks'] ?? ''));
                            if ($remarkShow === '') {
                                $remarkShow = '-';
                            }
                            ?>
                            <?php if ($showMemo): ?>
                            <td class="num sr-memo-cell">
                                <?php if ($canEditMemo): ?>
                                <button type="button"
                                        class="sr-arrears-btn sr-memo-btn"
                                        data-employee-id="<?php echo (int) $r['employee_id']; ?>"
                                        data-employee-name="<?php echo htmlspecialchars((string) ($r['employee_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                        data-employee-code="<?php echo htmlspecialchars((string) ($r['employee_code'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                        data-net-base="<?php echo htmlspecialchars((string) $netBaseMemo); ?>"
                                        data-memo="<?php echo htmlspecialchars(number_format($memoVal, 0, '.', '')); ?>"
                                        data-memo-remark="<?php echo htmlspecialchars($memoRemarkShow, ENT_QUOTES, 'UTF-8'); ?>"
                                        title="Click to edit Memo + Remark (deduction)">
                                    <span class="sr-memo-value"><?php echo $memoVal != 0.0 ? registerNum($memoVal, 0) : '0'; ?></span>
                                </button>
                                <?php else: ?>
                                <span class="sr-memo-value"><?php echo registerNum($memoVal, 0); ?></span>
                                <?php endif; ?>
                            </td>
                            <?php endif; ?>
                            <td class="num sr-arrears-cell">
                                <?php if ($canEditArrears): ?>
                                <button type="button"
                                        class="sr-arrears-btn"
                                        data-employee-id="<?php echo (int) $r['employee_id']; ?>"
                                        data-employee-name="<?php echo htmlspecialchars((string) ($r['employee_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                        data-employee-code="<?php echo htmlspecialchars((string) ($r['employee_code'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                        data-net-base="<?php echo htmlspecialchars((string) $netBaseArrears); ?>"
                                        data-memo="<?php echo htmlspecialchars(number_format($memoVal, 0, '.', '')); ?>"
                                        data-arrears="<?php echo htmlspecialchars(number_format($arrearsVal, 0, '.', '')); ?>"
                                        data-remarks="<?php echo htmlspecialchars($remarkShow === '-' ? '' : $remarkShow, ENT_QUOTES, 'UTF-8'); ?>"
                                        title="Click to edit Arrears + Remark">
                                    <span class="sr-arrears-value"><?php echo $arrearsVal != 0.0 ? registerNum($arrearsVal, 0) : '0'; ?></span>
                                </button>
                                <?php else: ?>
                                <span class="sr-arrears-value"><?php echo registerNum($arrearsVal, 0); ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="num sr-net-cell"><strong class="sr-net-value"><?php echo registerNum($netShow, 0); ?></strong></td>
                            <td><?php echo htmlspecialchars($r['account'] ?: '-'); ?></td>
                            <td><?php echo htmlspecialchars($r['ifsc'] ?: '-'); ?></td>
                            <td class="sr-remarks-cell"><?php echo htmlspecialchars($remarkShow); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                    <?php if ($rows): ?>
                    <tfoot>
                        <tr style="background:#f1f5f9;font-weight:700;">
                            <td colspan="5"><strong>Department Total</strong></td>
                            <?php if ($isActual): ?>
                                <td></td>
                                <td class="num"><strong><?php echo registerNum($deptSum['actual'], 0); ?></strong></td>
                                <td class="num"><strong><?php echo registerNum($deptSum['gross'], 0); ?></strong></td>
                            <?php else: ?>
                                <td class="num"><strong><?php echo registerNum($deptSum['salary'], 0); ?></strong></td>
                                <?php /* P.Days..C-Off (7) + Total Days (1) = 8 before Gross — was 7 and shifted all totals */ ?>
                                <td colspan="8"></td>
                                <td class="num"><strong><?php echo registerNum($deptSum['gross'], 0); ?></strong></td>
                            <?php endif; ?>
                            <td class="num"><strong><?php echo registerNum($deptSum['pf'], 0); ?></strong></td>
                            <td class="num"><strong><?php echo registerNum($deptSum['pt'], 0); ?></strong></td>
                            <td class="num"><strong><?php echo registerNum($deptSum['loan'], 0); ?></strong></td>
                            <td class="num"><strong><?php echo registerNum($deptSum['advance'], 0); ?></strong></td>
                            <td class="num"><strong><?php echo registerNum($deptSum['total_deduction'], 0); ?></strong></td>
                            <?php if ($showMemo): ?>
                            <td class="num"><strong class="sr-memo-total"><?php echo registerNum($deptSum['memo'] ?? 0, 0); ?></strong></td>
                            <?php endif; ?>
                            <td class="num"><strong class="sr-arrears-total"><?php echo registerNum($deptSum['arrears'], 0); ?></strong></td>
                            <td class="num"><strong class="sr-net-total"><?php echo registerNum($deptSum['net'], 0); ?></strong></td>
                            <td colspan="3"></td>
                        </tr>
                    </tfoot>
                    <?php endif; ?>
                </table>
            </div>
        </div>
    <?php endforeach; ?>

    <?php
    $pageShowMemo = false;
    if ($deptId > 0) {
        $pageShowMemo = isSalesOnFieldDepartmentId($deptId)
            || isSalesOnFieldDepartment($department['department_name'] ?? '');
    }
    if (!$pageShowMemo) {
        foreach ($grandRows as $gr) {
            if (isSalesOnFieldDepartmentId((int) ($gr['department_id'] ?? 0))
                || isSalesOnFieldDepartment((string) ($gr['department'] ?? ''))) {
                $pageShowMemo = true;
                break;
            }
        }
    }
    ?>
    <?php if ($grandRows): ?>
    <div class="form-page-card" style="margin-top:12px;">
        <div class="table-wrap">
            <table class="salary-register-table">
                <tfoot>
                    <tr class="sr-grand-total">
                        <td colspan="5"><strong>Grand Total</strong></td>
                        <?php if ($isActual): ?>
                            <td></td>
                            <td class="num"><strong><?php echo registerNum($grandSum['actual'], 0); ?></strong></td>
                            <td class="num"><strong><?php echo registerNum($grandSum['gross'], 0); ?></strong></td>
                        <?php else: ?>
                            <td class="num"><strong><?php echo registerNum($grandSum['salary'], 0); ?></strong></td>
                            <td colspan="8"></td>
                            <td class="num"><strong><?php echo registerNum($grandSum['gross'], 0); ?></strong></td>
                        <?php endif; ?>
                        <td class="num"><strong><?php echo registerNum($grandSum['pf'], 0); ?></strong></td>
                        <td class="num"><strong><?php echo registerNum($grandSum['pt'], 0); ?></strong></td>
                        <td class="num"><strong><?php echo registerNum($grandSum['loan'], 0); ?></strong></td>
                        <td class="num"><strong><?php echo registerNum($grandSum['advance'], 0); ?></strong></td>
                        <td class="num"><strong><?php echo registerNum($grandSum['total_deduction'], 0); ?></strong></td>
                        <?php if ($pageShowMemo): ?>
                        <td class="num"><strong><?php echo registerNum($grandSum['memo'] ?? 0, 0); ?></strong></td>
                        <?php endif; ?>
                        <td class="num"><strong><?php echo registerNum($grandSum['arrears'], 0); ?></strong></td>
                        <td class="num"><strong><?php echo registerNum($grandSum['net'], 0); ?></strong></td>
                        <td colspan="3"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <p class="form-hint" style="margin:8px 0 0;">Amounts rounded to nearest ₹ (.50 and above → up).</p>
    </div>
    <?php endif; ?>
</main>
<?php if ($canEditArrears): ?>
<div id="srArrearsModal" class="sr-arrears-modal" hidden aria-hidden="true">
    <div class="sr-arrears-modal-backdrop" data-sr-arrears-close></div>
    <div class="sr-arrears-modal-box" role="dialog" aria-modal="true" aria-labelledby="srArrearsModalTitle">
        <h3 id="srArrearsModalTitle">Arrears + Remark</h3>
        <p class="sr-arrears-modal-sub" id="srArrearsModalEmp">—</p>
        <div class="form-group">
            <label for="srArrearsAmount">Arrears (₹)</label>
            <input type="number" id="srArrearsAmount" class="form-control" min="0" step="1" placeholder="0">
        </div>
        <div class="form-group">
            <label for="srArrearsRemark">Remark</label>
            <textarea id="srArrearsRemark" class="form-control" rows="3" maxlength="255" placeholder="Remark for this arrears…"></textarea>
        </div>
        <div class="sr-arrears-modal-actions">
            <button type="button" class="btn-secondary" data-sr-arrears-close>Cancel</button>
            <button type="button" class="btn-primary" id="srArrearsSaveBtn">
                <i class="fa-solid fa-check"></i> OK · Save
            </button>
        </div>
    </div>
</div>
<script>
(function () {
    var saveUrl = <?php echo json_encode(app_url('payroll/register_arrears_save.php')); ?>;
    var month = <?php echo (int) $month; ?>;
    var year = <?php echo (int) $year; ?>;
    var deptId = <?php echo (int) $deptId; ?>;
    var regType = <?php echo json_encode($type); ?>;
    var modal = document.getElementById('srArrearsModal');
    var amountInput = document.getElementById('srArrearsAmount');
    var remarkInput = document.getElementById('srArrearsRemark');
    var empLabel = document.getElementById('srArrearsModalEmp');
    var saveBtn = document.getElementById('srArrearsSaveBtn');
    var activeBtn = null;

    function fmt(n) {
        n = Math.round(parseFloat(n) || 0);
        return n.toLocaleString('en-IN', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
    }

    function closeModal() {
        if (!modal) return;
        modal.hidden = true;
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('sr-arrears-modal-open');
        activeBtn = null;
    }

    function openModal(btn) {
        activeBtn = btn;
        var code = btn.getAttribute('data-employee-code') || '';
        var name = btn.getAttribute('data-employee-name') || '';
        empLabel.textContent = (code ? code + ' — ' : '') + name;
        amountInput.value = btn.getAttribute('data-arrears') || '';
        remarkInput.value = btn.getAttribute('data-remarks') || '';
        modal.hidden = false;
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('sr-arrears-modal-open');
        setTimeout(function () { amountInput.focus(); amountInput.select(); }, 30);
    }

    function recalcSheetTotal(table) {
        if (!table) return;
        var total = 0;
        var arrTotal = 0;
        table.querySelectorAll('.sr-net-value').forEach(function (el) {
            var t = (el.textContent || '').replace(/,/g, '');
            total += parseFloat(t) || 0;
        });
        table.querySelectorAll('.sr-arrears-btn:not(.sr-memo-btn)').forEach(function (btn) {
            var a = parseFloat(btn.getAttribute('data-arrears'));
            if (!isNaN(a) && a > 0) arrTotal += a;
        });
        var foot = table.querySelector('.sr-net-total');
        if (foot) foot.textContent = fmt(total);
        var footArr = table.querySelector('.sr-arrears-total');
        if (footArr) footArr.textContent = fmt(arrTotal);
    }

    function applyToRow(btn, arr, remarks, netFromServer) {
        var base = parseFloat(btn.getAttribute('data-net-base')) || 0;
        arr = Math.round(parseFloat(arr) || 0);
        if (arr < 0) arr = 0;
        var net = netFromServer != null ? Math.round(parseFloat(netFromServer) || 0) : Math.round(base + arr);
        if (net < 0) net = 0;
        btn.setAttribute('data-arrears', String(arr));
        btn.setAttribute('data-remarks', remarks === '-' ? '' : (remarks || ''));
        var valEl = btn.querySelector('.sr-arrears-value');
        if (valEl) valEl.textContent = fmt(arr);
        var tr = btn.closest('tr');
        if (tr) {
            var netEl = tr.querySelector('.sr-net-value');
            if (netEl) netEl.textContent = fmt(net);
            var remEl = tr.querySelector('.sr-remarks-cell');
            if (remEl) remEl.textContent = remarks && remarks !== '' ? remarks : '-';
            // Keep Memo button's net-base in sync: Gross−Ded+Arrears
            var memoBtn = tr.querySelector('.sr-memo-btn');
            if (memoBtn) {
                var memoAmt = parseFloat(memoBtn.getAttribute('data-memo')) || 0;
                memoBtn.setAttribute('data-net-base', String(base + arr + memoAmt));
            }
        }
        recalcSheetTotal(btn.closest('table'));
    }

    function saveArrears() {
        if (!activeBtn) return;
        var empId = parseInt(activeBtn.getAttribute('data-employee-id'), 10) || 0;
        if (!empId) return;
        var arr = parseFloat(amountInput.value);
        if (isNaN(arr) || arr < 0) arr = 0;
        arr = Math.round(arr);
        var remarks = String(remarkInput.value || '').trim();
        saveBtn.disabled = true;
        var body = new FormData();
        body.append('employee_id', empId);
        body.append('month', month);
        body.append('year', year);
        body.append('department_id', deptId);
        body.append('type', regType);
        body.append('arrears', arr);
        body.append('remarks', remarks);
        fetch(saveUrl, { method: 'POST', body: body, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                saveBtn.disabled = false;
                if (!res || !res.success) {
                    if (window.toastr) toastr.error((res && res.message) || 'Arrears save failed');
                    else alert((res && res.message) || 'Arrears save failed');
                    return;
                }
                applyToRow(activeBtn, res.arrears != null ? res.arrears : arr, res.remarks || remarks || '-', res.net);
                if (window.toastr) toastr.success('Arrears & remark saved');
                closeModal();
            })
            .catch(function () {
                saveBtn.disabled = false;
                if (window.toastr) toastr.error('Arrears save failed');
            });
    }

    document.querySelectorAll('.sr-arrears-btn:not(.sr-memo-btn)').forEach(function (btn) {
        btn.addEventListener('click', function () { openModal(btn); });
    });
    if (modal) {
        modal.querySelectorAll('[data-sr-arrears-close]').forEach(function (el) {
            el.addEventListener('click', closeModal);
        });
    }
    if (saveBtn) saveBtn.addEventListener('click', saveArrears);
    document.addEventListener('keydown', function (ev) {
        if (!modal || modal.hidden) return;
        if (ev.key === 'Escape') closeModal();
    });
})();
</script>
<?php endif; ?>
<?php if ($canEditMemo && !empty($pageShowMemo)): ?>
<div id="srMemoModal" class="sr-arrears-modal" hidden aria-hidden="true">
    <div class="sr-arrears-modal-backdrop" data-sr-memo-close></div>
    <div class="sr-arrears-modal-box" role="dialog" aria-modal="true" aria-labelledby="srMemoModalTitle">
        <h3 id="srMemoModalTitle">Memo + Remark</h3>
        <p class="sr-arrears-modal-sub" id="srMemoModalEmp">—</p>
        <div class="form-group">
            <label for="srMemoAmount">Memo (₹) — deduction</label>
            <input type="number" id="srMemoAmount" class="form-control" min="0" step="1" placeholder="0">
        </div>
        <div class="form-group">
            <label for="srMemoRemark">Remark</label>
            <textarea id="srMemoRemark" class="form-control" rows="3" maxlength="255" placeholder="Remark for this memo…"></textarea>
        </div>
        <div class="sr-arrears-modal-actions">
            <button type="button" class="btn-secondary" data-sr-memo-close>Cancel</button>
            <button type="button" class="btn-primary" id="srMemoSaveBtn">
                <i class="fa-solid fa-check"></i> OK · Save
            </button>
        </div>
    </div>
</div>
<script>
(function () {
    var saveUrl = <?php echo json_encode(app_url('payroll/register_memo_save.php')); ?>;
    var month = <?php echo (int) $month; ?>;
    var year = <?php echo (int) $year; ?>;
    var deptId = <?php echo (int) $deptId; ?>;
    var regType = <?php echo json_encode($type); ?>;
    var modal = document.getElementById('srMemoModal');
    var amountInput = document.getElementById('srMemoAmount');
    var remarkInput = document.getElementById('srMemoRemark');
    var empLabel = document.getElementById('srMemoModalEmp');
    var saveBtn = document.getElementById('srMemoSaveBtn');
    var activeBtn = null;

    function fmt(n) {
        n = Math.round(parseFloat(n) || 0);
        return n.toLocaleString('en-IN', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
    }

    function closeModal() {
        if (!modal) return;
        modal.hidden = true;
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('sr-arrears-modal-open');
        activeBtn = null;
    }

    function openModal(btn) {
        activeBtn = btn;
        var code = btn.getAttribute('data-employee-code') || '';
        var name = btn.getAttribute('data-employee-name') || '';
        empLabel.textContent = (code ? code + ' — ' : '') + name;
        amountInput.value = btn.getAttribute('data-memo') || '';
        remarkInput.value = btn.getAttribute('data-memo-remark') || '';
        modal.hidden = false;
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('sr-arrears-modal-open');
        setTimeout(function () { amountInput.focus(); amountInput.select(); }, 30);
    }

    function recalcSheetTotal(table) {
        if (!table) return;
        var total = 0;
        var memoTotal = 0;
        table.querySelectorAll('.sr-net-value').forEach(function (el) {
            var t = (el.textContent || '').replace(/,/g, '');
            total += parseFloat(t) || 0;
        });
        table.querySelectorAll('.sr-memo-btn').forEach(function (btn) {
            var a = parseFloat(btn.getAttribute('data-memo'));
            if (!isNaN(a) && a > 0) memoTotal += a;
        });
        var foot = table.querySelector('.sr-net-total');
        if (foot) foot.textContent = fmt(total);
        var footMemo = table.querySelector('.sr-memo-total');
        if (footMemo) footMemo.textContent = fmt(memoTotal);
    }

    function applyToRow(btn, memo, memoRemark, netFromServer) {
        var base = parseFloat(btn.getAttribute('data-net-base')) || 0;
        memo = Math.round(parseFloat(memo) || 0);
        if (memo < 0) memo = 0;
        var net = netFromServer != null ? Math.round(parseFloat(netFromServer) || 0) : Math.round(base - memo);
        if (net < 0) net = 0;
        btn.setAttribute('data-memo', String(memo));
        btn.setAttribute('data-memo-remark', memoRemark === '-' ? '' : (memoRemark || ''));
        var valEl = btn.querySelector('.sr-memo-value');
        if (valEl) valEl.textContent = fmt(memo);
        var tr = btn.closest('tr');
        if (tr) {
            var netEl = tr.querySelector('.sr-net-value');
            if (netEl) netEl.textContent = fmt(net);
            // Keep Arrears button's net-base in sync: Gross−Ded−Memo
            var arrBtn = tr.querySelector('.sr-arrears-btn:not(.sr-memo-btn)');
            if (arrBtn) {
                var arr = parseFloat(arrBtn.getAttribute('data-arrears')) || 0;
                arrBtn.setAttribute('data-memo', String(memo));
                arrBtn.setAttribute('data-net-base', String(base - arr - memo));
            }
        }
        recalcSheetTotal(btn.closest('table'));
    }

    function saveMemo() {
        if (!activeBtn) return;
        var empId = parseInt(activeBtn.getAttribute('data-employee-id'), 10) || 0;
        if (!empId) return;
        var memo = parseFloat(amountInput.value);
        if (isNaN(memo) || memo < 0) memo = 0;
        memo = Math.round(memo);
        var memoRemark = String(remarkInput.value || '').trim();
        saveBtn.disabled = true;
        var body = new FormData();
        body.append('employee_id', empId);
        body.append('month', month);
        body.append('year', year);
        body.append('department_id', deptId);
        body.append('type', regType);
        body.append('memo', memo);
        body.append('memo_remark', memoRemark);
        fetch(saveUrl, { method: 'POST', body: body, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                saveBtn.disabled = false;
                if (!res || !res.success) {
                    if (window.toastr) toastr.error((res && res.message) || 'Memo save failed');
                    else alert((res && res.message) || 'Memo save failed');
                    return;
                }
                applyToRow(activeBtn, res.memo != null ? res.memo : memo, res.memo_remark || memoRemark || '', res.net);
                if (window.toastr) toastr.success('Memo saved · deducted from Net');
                closeModal();
            })
            .catch(function () {
                saveBtn.disabled = false;
                if (window.toastr) toastr.error('Memo save failed');
            });
    }

    document.querySelectorAll('.sr-memo-btn').forEach(function (btn) {
        btn.addEventListener('click', function () { openModal(btn); });
    });
    if (modal) {
        modal.querySelectorAll('[data-sr-memo-close]').forEach(function (el) {
            el.addEventListener('click', closeModal);
        });
    }
    if (saveBtn) saveBtn.addEventListener('click', saveMemo);
    document.addEventListener('keydown', function (ev) {
        if (!modal || modal.hidden) return;
        if (ev.key === 'Escape') closeModal();
    });
})();
</script>
<?php endif; ?>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
