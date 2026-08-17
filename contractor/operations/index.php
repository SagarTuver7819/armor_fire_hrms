<?php
require_once __DIR__ . '/../_bootstrap.php';

$pageTitle = 'Operations Rate List';
$extraCss = [
    'https://cdn.datatables.net/1.13.8/css/jquery.dataTables.min.css',
    'https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css',
];
$useSidebar = true;
$sidebarMode = 'contractor';
$sidebarActive = 'contractor_operations';

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
        'added' => 'Rate list saved successfully.',
        'updated' => 'Rate list updated successfully.',
        'deleted' => 'Rate list deleted successfully.',
        'generated' => 'Salary generated successfully.',
    ];
    $toastMsg = $map[$_GET['msg']] ?? '';
}
?>
<main class="dashboard-main">
    <div class="page-toolbar flex-between">
        <a href="<?php echo app_url('contractor/index.php'); ?>" class="back-link"><i class="fa-solid fa-arrow-left"></i> Back to Contractor Hub</a>
        <a href="<?php echo app_url('contractor/operations/edit.php'); ?>" class="btn-primary"><i class="fa-solid fa-plus"></i> Add</a>
    </div>
    <div class="list-header">
        <div class="master-list-title">
            <div class="master-list-icon" style="background:#8E44AD;"><i class="fa-solid fa-table"></i></div>
            <div>
                <h1>Operations Rate List</h1>
                <p>Search by operation, employee, month or year. Click Add to enter daily qty.</p>
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
                        <option value="<?php echo htmlspecialchars($op); ?>"><?php echo htmlspecialchars($op); ?></option>
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
    window.C_AJAX_URL = <?php echo json_encode(app_url('contractor/operations/ajax_list.php')); ?>;
    window.C_ACTION_COL = 7;
    window.C_AJAX_EXTRA = function () {
        return {
            filter_operation: document.getElementById('filter_operation').value,
            filter_employee: document.getElementById('filter_employee').value,
            filter_month: document.getElementById('filter_month').value,
            filter_year: document.getElementById('filter_year').value
        };
    };
</script>
<?php
$extraJs = [
    'https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js',
    'https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js',
    'assets/js/contractor_list.js',
    'assets/js/contractor_ops_filter.js',
];
require_once __DIR__ . '/../../includes/footer.php';
