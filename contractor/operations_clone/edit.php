<?php
/**
 * Clone Operation Rate List — Edit
 * For CNC: product qty grid + CNC department Normal Salary staff (excl. Main Contractor).
 * Existing Operations Rate List (operations/edit.php) is unchanged.
 */
require_once __DIR__ . '/../_bootstrap.php';
require_once __DIR__ . '/../../includes/contractor_split_helper.php';

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
$curMonth = (int) ($sheet['month_no'] ?? date('n'));
$curYear = (int) ($sheet['year_no'] ?? date('Y'));
$employees = ensureOpsEmployeeInList($employees, $curEmp, $conn);
$staffBundle = getOpsCloneDepartmentStaff($curOp, $curEmp, $conn);
$conn->close();

$showGrade = contractorOperationShowsGrade($curOp);
$showStaff = in_array($curOp, opsCloneStaffSalaryOperations(), true);
$items = $sheet['items'] ?? [];
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

$pageTitle = $sheet ? 'Edit Clone Operation Rate List' : 'Add Clone Operation Rate List';
$useSidebar = true;
$sidebarMode = 'contractor';
$sidebarActive = 'contractor_operations_clone';
require_once __DIR__ . '/../../includes/header.php';
$repairJson = json_encode(contractorRepairOps());
$otJson = json_encode(contractorOtRepairOps());
$mainLabel = '';
if (!empty($staffBundle['main'])) {
    $mainLabel = trim((string) ($staffBundle['main']['employee_code'] ?? '')) . ' — '
        . trim((string) ($staffBundle['main']['employee_name'] ?? ''));
}
?>
<main class="dashboard-main">
    <div class="page-toolbar">
        <a href="<?php echo app_url('contractor/operations_clone/index.php'); ?>" class="back-link"><i class="fa-solid fa-arrow-left"></i> Back to Clone Rate List</a>
    </div>
    <div class="form-page-card ops-page-card">
        <div class="form-page-header">
            <div>
                <h1><?php echo $sheet ? 'Edit' : 'Add'; ?> Clone Operation Rate List</h1>
                <p>
                    Clone screen for CNC · Product qty grid same as Ops Rate List.
                    <?php if ($showStaff): ?>
                        Below: <strong><?php echo htmlspecialchars($staffBundle['department_name'] ?: 'CNC'); ?></strong>
                        Normal Salary employees (Main Contractor excluded) — set salary here.
                    <?php endif; ?>
                </p>
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
                <button type="button" class="btn-primary" id="btnAddProduct"><i class="fa-solid fa-plus"></i> Add Product Row</button>
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
                        <tr class="ops-row" data-saved-product-id="<?php echo $itemProductId; ?>" data-saved-grade-id="<?php echo $itemGradeId; ?>">
                            <td class="sticky-col sticky-action">
                                <button type="button" class="btn-icon btn-remove-row" title="Delete row">
                                    <i class="fa-solid fa-trash-can"></i>
                                </button>
                            </td>
                            <td class="sticky-col sticky-sr ops-sr"><?php echo $i + 1; ?></td>
                            <td class="sticky-col sticky-name">
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

            <?php if ($showStaff): ?>
            <div class="ops-clone-staff-block" id="opsCloneStaffBlock">
                <div class="ops-clone-staff-head">
                    <h2><i class="fa-solid fa-users"></i> CNC Department — Normal Salary Employees</h2>
                    <p>
                        Main Contractor
                        <?php echo $mainLabel !== '' ? '<strong>' . htmlspecialchars($mainLabel) . '</strong>' : '(auto)'; ?>
                        excluded · Emp Code · Name · Salary (attendance fund from Main pool)
                    </p>
                </div>
                <div class="table-wrap">
                    <table class="data-table ops-clone-staff-table">
                        <thead>
                            <tr>
                                <th style="width:56px;">Sr.</th>
                                <th style="width:120px;">Emp Code</th>
                                <th>Employee Name</th>
                                <th>Designation</th>
                                <th class="num" style="width:180px;">Salary</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($staffBundle['rows'])): ?>
                                <tr>
                                    <td colspan="5" class="empty-msg">No Normal Salary employees found in CNC (excluding Main Contractor).</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($staffBundle['rows'] as $si => $staff): ?>
                                    <?php $sid = (int) $staff['id']; ?>
                                    <tr>
                                        <td><?php echo $si + 1; ?></td>
                                        <td><span class="code-badge"><?php echo htmlspecialchars((string) $staff['employee_code']); ?></span></td>
                                        <td><?php echo htmlspecialchars((string) $staff['employee_name']); ?></td>
                                        <td><?php echo htmlspecialchars((string) ($staff['designation'] ?? '')); ?></td>
                                        <td class="num">
                                            <input type="number" step="0.01" min="0"
                                                   name="staff_salary[<?php echo $sid; ?>]"
                                                   class="form-control grid-input"
                                                   value="<?php echo htmlspecialchars((string) round((float) ($staff['decided_salary'] ?? 0), 2)); ?>">
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>

            <div class="form-actions sticky-actions">
                <button type="submit" class="btn-primary"><i class="fa-solid fa-check"></i> <?php echo $sheet ? 'Update Records' : 'Save Records'; ?></button>
                <a href="<?php echo app_url('contractor/operations_clone/index.php'); ?>" class="btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</main>
<script>
    window.OPS_PRODUCTS_URL = <?php echo json_encode(app_url('contractor/operations/products_json.php')); ?>;
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
    // Reload when operation changes so CNC staff block appears/hides with correct list
    (function () {
        var sel = document.getElementById('operationSelect');
        if (!sel) return;
        var last = sel.value;
        sel.addEventListener('change', function () {
            if (sel.value === last) return;
            var params = new URLSearchParams(window.location.search);
            params.set('operation', sel.value);
            if (<?php echo (int) $id; ?> > 0) {
                params.set('id', String(<?php echo (int) $id; ?>));
            }
            window.location.search = params.toString();
        });
    })();
</script>
<?php
$extraJs = [
    'assets/js/contractor_ops.js?v=' . (string) @filemtime(__DIR__ . '/../../assets/js/contractor_ops.js'),
];
require_once __DIR__ . '/../../includes/footer.php';
