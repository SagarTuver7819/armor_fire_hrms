<?php
require_once __DIR__ . '/../_bootstrap.php';

$pageTitle = 'Contractor Employee';
$extraCss = [
    'https://cdn.datatables.net/1.13.8/css/jquery.dataTables.min.css',
    'https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css',
];
$useSidebar = true;
$sidebarMode = 'contractor';
$sidebarActive = 'contractor_employees';
require_once __DIR__ . '/../../includes/header.php';

$toastMsg = '';
if (isset($_GET['msg'])) {
    $map = [
        'added' => 'Jobwork employee added successfully.',
        'updated' => 'Jobwork employee updated successfully.',
        'deleted' => 'Jobwork employee deleted successfully.',
    ];
    $toastMsg = $map[$_GET['msg']] ?? '';
}
?>
<main class="dashboard-main">
    <div class="page-toolbar flex-between">
        <a href="<?php echo app_url('contractor/index.php'); ?>" class="back-link"><i class="fa-solid fa-arrow-left"></i> Back to Contractor Hub</a>
        <a href="<?php echo app_url('contractor/employees/edit.php'); ?>" class="btn-primary"><i class="fa-solid fa-plus"></i> Add Jobwork Employee</a>
    </div>
    <div class="list-header">
        <div class="master-list-title">
            <div class="master-list-icon" style="background:#F58220;"><i class="fa-solid fa-id-card"></i></div>
            <div>
                <h1>Contractor Employee</h1>
                <p>Join Employee records with Pay Type = Jobwork</p>
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
                        <th>Full Name</th>
                        <th>Department</th>
                        <th>Designation</th>
                        <th>Mobile</th>
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
    window.C_AJAX_URL = <?php echo json_encode(app_url('contractor/employees/ajax_list.php')); ?>;
    window.C_ACTION_COL = 6;
</script>
<?php
$extraJs = [
    'https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js',
    'https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js',
    'assets/js/contractor_list.js',
];
require_once __DIR__ . '/../../includes/footer.php';
