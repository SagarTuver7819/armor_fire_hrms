<?php
require_once __DIR__ . '/../_bootstrap.php';
require_once __DIR__ . '/../../includes/master_helper.php';

$pageTitle = 'Contractor Employment Details';
$extraCss = [
    'https://cdn.datatables.net/1.13.8/css/jquery.dataTables.min.css',
    'https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css',
];
$useSidebar = true;
$sidebarMode = 'contractor';
$sidebarActive = 'contractor_employment';
require_once __DIR__ . '/../../includes/header.php';

$toastMsg = '';
if (isset($_GET['msg'])) {
    $map = ['added' => 'Employment details added.', 'updated' => 'Employment details updated.', 'deleted' => 'Employment details deleted.'];
    $toastMsg = $map[$_GET['msg']] ?? '';
}
?>
<main class="dashboard-main">
    <div class="page-toolbar flex-between">
        <a href="<?php echo app_url('contractor/index.php'); ?>" class="back-link"><i class="fa-solid fa-arrow-left"></i> Back to Contractor Hub</a>
        <a href="<?php echo app_url('contractor/employment/edit.php'); ?>" class="btn-primary"><i class="fa-solid fa-plus"></i> Add</a>
    </div>
    <div class="list-header">
        <div class="master-list-title">
            <div class="master-list-icon" style="background:#3498DB;"><i class="fa-solid fa-briefcase"></i></div>
            <div>
                <h1>Contractor Employment Details</h1>
                <p>Assign department, designation, shift, PF / UAN</p>
            </div>
        </div>
    </div>
    <div class="data-card data-card-pad">
        <div class="table-wrap">
            <table id="contractorTable" class="display data-table nowrap" style="width:100%">
                <thead>
                    <tr>
                        <th>Sr.</th>
                        <th>Employee Code</th>
                        <th>Employee Name</th>
                        <th>Designation Type</th>
                        <th>Designation</th>
                        <th>Department</th>
                        <th>Sub Department</th>
                        <th>Process</th>
                        <th>Date</th>
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
    window.C_AJAX_URL = <?php echo json_encode(app_url('contractor/employment/ajax_list.php')); ?>;
    window.C_ACTION_COL = 9;
</script>
<?php
$extraJs = [
    'https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js',
    'https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js',
    'assets/js/contractor_list.js',
];
require_once __DIR__ . '/../../includes/footer.php';
