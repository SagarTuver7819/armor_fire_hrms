<?php
/**
 * New Operation List — CNC month view from November 2026 (Main Contractor Ravi).
 * Existing Operations Rate List is unchanged.
 */
require_once __DIR__ . '/../_bootstrap.php';

$pageTitle = 'New Operation List';
$extraCss = [
    'https://cdn.datatables.net/1.13.8/css/jquery.dataTables.min.css',
    'https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css',
];
$useSidebar = true;
$sidebarMode = 'contractor';
$sidebarActive = 'contractor_operations_clone';

$conn = getDBConnection();
ensureContractorTables($conn);
$ops = contractorOperations();
$months = contractorMonths();
$employees = getActiveContractorEmployees($conn);
$conn->close();

require_once __DIR__ . '/../../includes/header.php';

$toastMsg = '';
if (isset($_GET['msg'])) {
    $map = [
        'added' => 'Clone rate list saved.',
        'updated' => 'Clone rate list + CNC staff salaries updated.',
        'deleted' => 'Deleted.',
    ];
    $toastMsg = $map[$_GET['msg']] ?? '';
}
?>
<main class="dashboard-main">
    <div class="page-toolbar flex-between">
        <a href="<?php echo app_url('contractor/index.php'); ?>" class="back-link"><i class="fa-solid fa-arrow-left"></i> Back to Contractor Hub</a>
        <div class="toolbar-actions">
            <a href="#" id="btnOpsPrint" class="btn-secondary" title="Print / PDF">
                <i class="fa-solid fa-print"></i> Print PDF
            </a>
            <a href="#" id="btnOpsExcel" class="btn-secondary" title="Excel — all employees day-wise">
                <i class="fa-solid fa-file-excel"></i> Excel (All Employees)
            </a>
            <a href="#" id="btnOpsExcelSummary" class="btn-secondary" title="Excel — product-wise summary">
                <i class="fa-solid fa-table"></i> Excel Summary
            </a>
        </div>
    </div>
    <div class="list-header">
        <div class="master-list-title">
            <div class="master-list-icon" style="background:#0f766e;"><i class="fa-solid fa-table-list"></i></div>
            <div>
                <h1>New Operation List</h1>
                <p>
                    CNC · Main Contractor <strong>Ravi (SOLANKI RAVI KANABHAI)</strong> · months from <strong>November 2026</strong>.
                    Eye = View (last set ERP KRA grid). Add / Edit removed.
                    Past <em>Operations Rate List</em> is unchanged.
                </p>
            </div>
        </div>
    </div>
    <div class="data-card data-card-pad">
        <div class="ops-filter-bar form-grid form-grid-3">
            <div class="form-group">
                <label>Operation</label>
                <select id="filter_operation" class="form-control">
                    <option value="">All Operations</option>
                    <?php foreach ($ops as $op): ?>
                        <option value="<?php echo htmlspecialchars($op); ?>" <?php echo $op === 'CNC' ? 'selected' : ''; ?>><?php echo htmlspecialchars($op); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Employee</label>
                <select id="filter_employee" class="form-control">
                    <option value="">All Employees</option>
                    <?php foreach ($employees as $emp): ?>
                        <option value="<?php echo (int) $emp['id']; ?>"><?php echo htmlspecialchars(contractorEmployeeOptionLabel($emp)); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Month</label>
                <select id="filter_month" class="form-control">
                    <option value="">All Months</option>
                    <?php foreach ($months as $num => $lab): ?>
                        <option value="<?php echo (int) $num; ?>"><?php echo htmlspecialchars($lab); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Year</label>
                <select id="filter_year" class="form-control">
                    <option value="">All Years</option>
                    <?php for ($y = 2024; $y <= 2032; $y++): ?>
                        <option value="<?php echo $y; ?>"><?php echo $y; ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="form-group ops-filter-actions">
                <label>&nbsp;</label>
                <button type="button" class="btn-primary" id="btnApplyOpsFilter"><i class="fa-solid fa-filter"></i> Apply Filter</button>
            </div>
        </div>
        <div class="table-wrap">
            <table id="contractorTable" class="display data-table nowrap" style="width:100%">
                <thead>
                    <tr>
                        <th>Sr.</th>
                        <th>Operation</th>
                        <th>Name</th>
                        <th>Month</th>
                        <th>Year</th>
                        <th>Group Total Qty</th>
                        <th>Group Total Amount</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</main>
<script>
    window.C_TOAST_MSG = <?php echo json_encode($toastMsg); ?>;
    window.C_AJAX_URL = <?php echo json_encode(app_url('contractor/operations_clone/ajax_list.php')); ?>;
    window.C_OPS_PRINT_URL = <?php echo json_encode(app_url('contractor/operations/print.php')); ?>;
    window.C_OPS_EXCEL_URL = <?php echo json_encode(app_url('contractor/operations/excel.php')); ?>;
    window.C_ACTION_COL = 7;
    window.C_AJAX_EXTRA = function () {
        return {
            filter_operation: document.getElementById('filter_operation').value,
            filter_employee: document.getElementById('filter_employee').value,
            filter_month: document.getElementById('filter_month').value,
            filter_year: document.getElementById('filter_year').value
        };
    };
    function opsExportQs() {
        var q = new URLSearchParams(window.C_AJAX_EXTRA());
        return q.toString();
    }
    document.getElementById('btnOpsPrint').addEventListener('click', function (e) {
        e.preventDefault();
        window.open(window.C_OPS_PRINT_URL + '?' + opsExportQs(), '_blank');
    });
    document.getElementById('btnOpsExcel').addEventListener('click', function (e) {
        e.preventDefault();
        window.location.href = window.C_OPS_EXCEL_URL + '?' + opsExportQs();
    });
    var btnSum = document.getElementById('btnOpsExcelSummary');
    if (btnSum) {
        btnSum.addEventListener('click', function (e) {
            e.preventDefault();
            window.location.href = window.C_OPS_EXCEL_URL + '?' + opsExportQs() + '&mode=summary';
        });
    }
</script>
<?php
$extraJs = [
    'https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js',
    'https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js',
    'assets/js/contractor_list.js',
    'assets/js/contractor_ops_filter.js',
];
require_once __DIR__ . '/../../includes/footer.php';
