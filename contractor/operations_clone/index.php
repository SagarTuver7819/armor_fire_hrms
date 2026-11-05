<?php
/**
 * New Operation List — CNC month view from November 2026 (Main Contractor Ravi).
 * Existing Operations Rate List is unchanged.
 */
require_once __DIR__ . '/../_bootstrap.php';

$pageTitle = 'CNC Job Work and Attendance Report';
$extraCss = [
    'https://cdn.datatables.net/1.13.8/css/jquery.dataTables.min.css',
    'https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css',
];
$useSidebar = true;
$sidebarActive = 'cnc_job_report';
if (!function_exists('resolveDepartmentIdByName')) {
    require_once __DIR__ . '/../../includes/contractor_split_helper.php';
}
$cncDeptId = (int) resolveDepartmentIdByName('CNC');
$sidebarMode = $cncDeptId > 0 ? 'department' : 'contractor';
$sidebarDeptId = $cncDeptId;

$conn = getDBConnection();
ensureContractorTables($conn);
$conn->close();

if (!function_exists('contractorAttendanceSplitRules')) {
    require_once __DIR__ . '/../../includes/contractor_split_helper.php';
}
$cncRule = null;
foreach (contractorAttendanceSplitRules() as $rule) {
    if (strcasecmp((string) ($rule['department_name'] ?? ''), 'CNC') === 0) {
        $cncRule = $rule;
        break;
    }
}
$mainContractor = $cncRule ? resolveContractorAttendanceSplitMain($cncRule) : null;
$mainContractorId = (int) ($mainContractor['id'] ?? 0);
$mainContractorLabel = trim((string) ($mainContractor['employee_code'] ?? 'CO72141'))
    . ' - '
    . trim((string) ($mainContractor['employee_name'] ?? 'SOLANKI RAVI KANABHAI'));

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
        <a href="<?php echo $cncDeptId > 0 ? app_url('department.php?id=' . $cncDeptId) : app_url('contractor/index.php'); ?>" class="back-link"><i class="fa-solid fa-arrow-left"></i> Back to CNC Department</a>
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
                <h1>CNC Job Work and Attendance Report</h1>
                <p>Main Contractor · <?php echo htmlspecialchars($mainContractorLabel); ?></p>
            </div>
        </div>
    </div>
    <div class="data-card data-card-pad">
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
    window.C_MAIN_ID = <?php echo (int) $mainContractorId; ?>;
    window.C_AJAX_EXTRA = function () {
        return {
            filter_operation: 'CNC',
            filter_employee: String(window.C_MAIN_ID || ''),
            filter_month: '',
            filter_year: ''
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
];
require_once __DIR__ . '/../../includes/footer.php';
