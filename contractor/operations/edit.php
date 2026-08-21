<?php
require_once __DIR__ . '/../_bootstrap.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$conn = getDBConnection();
ensureContractorTables($conn);
$sheet = $id > 0 ? getContractorSheetById($id, $conn) : null;
if ($id > 0 && !$sheet) {
    $conn->close();
    header('Location: ' . app_url('contractor/operations/index.php'));
    exit;
}
$employees = getActiveContractorEmployees($conn);
$conn->close();

$ops = contractorOperations();
$months = contractorMonths();
$curOp = (string) ($sheet['operation'] ?? 'Lathe Employee wise');
$curEmp = (int) ($sheet['employee_id'] ?? 0);
$curMonth = (int) ($sheet['month_no'] ?? date('n'));
$curYear = (int) ($sheet['year_no'] ?? date('Y'));
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

$pageTitle = $sheet ? 'Edit Operations Rate List' : 'Add Operations Rate List';
$useSidebar = true;
$sidebarMode = 'contractor';
$sidebarActive = 'contractor_operations';
$extraJs = ['assets/js/contractor_ops.js'];
require_once __DIR__ . '/../../includes/header.php';
$repairJson = json_encode(contractorRepairOps());
$otJson = json_encode(contractorOtRepairOps());
?>
<main class="dashboard-main">
    <div class="page-toolbar">
        <a href="<?php echo app_url('contractor/operations/index.php'); ?>" class="back-link"><i class="fa-solid fa-arrow-left"></i> Back to Rate List</a>
    </div>
    <div class="form-page-card ops-page-card">
        <div class="form-page-header">
            <div>
                <h1><?php echo $sheet ? 'Edit' : 'Add'; ?> Operations Rate List</h1>
                <p>Select employee &amp; product, then type daily qty. Totals calculate automatically.</p>
            </div>
        </div>

        <form method="POST" action="<?php echo app_url('contractor/operations/save.php'); ?>" id="opsForm" class="employee-form" autocomplete="off">
            <input type="hidden" name="id" value="<?php echo (int) $id; ?>">
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
                        <select name="employee_id" class="form-control" required>
                            <option value="">Select Employee</option>
                            <?php foreach ($employees as $emp): ?>
                                <option value="<?php echo (int) $emp['id']; ?>" <?php echo $curEmp === (int) $emp['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars(contractorEmployeeOptionLabel($emp)); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (!$employees): ?>
                            <small class="form-hint">Jobwork employees from Join Employee appear here.</small>
                        <?php endif; ?>
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

            <p class="ops-hint">
                Product stays on the left while you scroll dates.
                Press <strong>Enter</strong> to jump to the next day.
                Extra days of the month stay locked.
            </p>
            <div class="ops-toolbar">
                <button type="button" class="btn-primary" id="btnAddProduct"><i class="fa-solid fa-plus"></i> Add Product Row</button>
                <div class="ops-live-summary">
                    <span class="ops-chip">Qty <b id="sum_qty">0</b></span>
                    <span class="ops-chip">R <b id="sum_r">0</b></span>
                    <span class="ops-chip amt">Amount <b id="sum_amt">0.00</b></span>
                </div>
            </div>

            <div class="ops-grid-wrap">
                <table class="ops-grid hide-grade" id="opsGrid">
                    <thead>
                        <tr>
                            <th class="sticky-col sticky-action">Action</th>
                            <th class="sticky-col sticky-sr">Sr</th>
                            <th class="sticky-col sticky-name" id="col_header_name">Product Name</th>
                            <?php for ($d = 1; $d <= 31; $d++):
                                $wd = checkdate($curMonth, $d, $curYear) ? date('D', strtotime(sprintf('%04d-%02d-%02d', $curYear, $curMonth, $d))) : '';
                                ?>
                                <th class="day-head" data-day="<?php echo $d; ?>">
                                    <span class="day-no"><?php echo $d; ?></span>
                                    <span class="day-wd"><?php echo htmlspecialchars($wd); ?></span>
                                    <div class="ops-subhead"><span>Q</span><span class="r-lab">R</span><span class="ot-lab">OT</span></div>
                                </th>
                            <?php endfor; ?>
                            <th id="th_total_qty">TOTAL QTY</th>
                            <th>RATE</th>
                            <th id="th_total_r">TOTAL R</th>
                            <th>OT RATE</th>
                            <th>REJ. RATE</th>
                            <th id="th_amount">TOTAL AMT</th>
                        </tr>
                    </thead>
                    <tbody id="opsRows">
                    <?php foreach ($items as $i => $item):
                        $days = mergeDayMap($item['days'] ?? [], $curMonth, $curYear);
                        ?>
                        <tr class="ops-row">
                            <td class="sticky-col sticky-action">
                                <button type="button" class="btn-icon btn-remove-row" title="Remove row"><i class="fa-solid fa-xmark"></i></button>
                            </td>
                            <td class="sticky-col sticky-sr ops-sr"><?php echo $i + 1; ?></td>
                            <td class="sticky-col sticky-name">
                                <select name="items[<?php echo $i; ?>][product_id]" class="form-control product-select">
                                    <option value="">Select Product</option>
                                </select>
                                <input type="hidden" class="row-product-id" value="<?php echo (int) ($item['product_id'] ?? 0); ?>">
                                <input type="hidden" name="items[<?php echo $i; ?>][grade_id]" class="row-grade-id" value="0">
                            </td>
                            <?php for ($d = 1; $d <= 31; $d++):
                                $cell = $days[(string) $d];
                                $dis = !empty($cell['disabled']) ? 'disabled' : '';
                                ?>
                                <td class="day-cell" data-day="<?php echo $d; ?>">
                                    <div class="ops-day-triple">
                                        <input type="number" step="0.01" class="grid-input day-qty" <?php echo $dis; ?>
                                               name="items[<?php echo $i; ?>][days][<?php echo $d; ?>][q]" value="<?php echo htmlspecialchars((string) $cell['q']); ?>">
                                        <input type="number" step="0.01" class="grid-input day-qty-r r-field" <?php echo $dis; ?>
                                               name="items[<?php echo $i; ?>][days][<?php echo $d; ?>][r]" value="<?php echo htmlspecialchars((string) $cell['r']); ?>">
                                        <input type="number" step="0.01" class="grid-input day-qty-ot ot-field" <?php echo $dis; ?>
                                               name="items[<?php echo $i; ?>][days][<?php echo $d; ?>][ot]" value="<?php echo htmlspecialchars((string) $cell['ot']); ?>">
                                    </div>
                                </td>
                            <?php endfor; ?>
                            <td><input type="text" name="items[<?php echo $i; ?>][total_qty]" class="form-control grid-input total-qty" readonly value="<?php echo htmlspecialchars((string) ($item['total_qty'] ?? 0)); ?>"></td>
                            <td><input type="number" step="0.01" name="items[<?php echo $i; ?>][rate]" class="form-control grid-input rate" readonly value="<?php echo htmlspecialchars((string) ($item['rate'] ?? 0)); ?>"></td>
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
                            <td class="sticky-col sticky-action" colspan="3" id="tf_grand_total_label">GRAND TOTAL</td>
                            <?php for ($d = 1; $d <= 31; $d++): ?>
                                <td class="day-foot" data-day="<?php echo $d; ?>"></td>
                            <?php endfor; ?>
                            <td id="tf_total_qty">0</td>
                            <td></td>
                            <td id="tf_total_r">0</td>
                            <td></td>
                            <td></td>
                            <td id="tf_amount">0.00</td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <div class="form-actions sticky-actions">
                <button type="submit" class="btn-primary"><i class="fa-solid fa-check"></i> <?php echo $sheet ? 'Update Records' : 'Save Records'; ?></button>
                <a href="<?php echo app_url('contractor/operations/index.php'); ?>" class="btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</main>
<script>
    window.OPS_PRODUCTS_URL = <?php echo json_encode(app_url('contractor/operations/products_json.php')); ?>;
    window.OPS_REPAIR = <?php echo $repairJson; ?>;
    window.OPS_OT_REPAIR = <?php echo $otJson; ?>;
</script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
