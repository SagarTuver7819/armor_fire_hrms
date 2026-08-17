<?php
require_once __DIR__ . '/../_bootstrap.php';

$pageTitle = 'Grade Master';
$extraCss = [
    'https://cdn.datatables.net/1.13.8/css/jquery.dataTables.min.css',
    'https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css',
];
$useSidebar = true;
$sidebarMode = 'contractor';
$sidebarActive = 'contractor_grades';
require_once __DIR__ . '/../../includes/header.php';

$toastMsg = '';
if (isset($_GET['msg'])) {
    $map = ['added' => 'Grade added successfully.', 'updated' => 'Grade updated successfully.', 'deleted' => 'Grade deleted successfully.'];
    $toastMsg = $map[$_GET['msg']] ?? '';
}
?>
<main class="dashboard-main">
    <div class="page-toolbar flex-between">
        <a href="<?php echo app_url('contractor/index.php'); ?>" class="back-link"><i class="fa-solid fa-arrow-left"></i> Back to Contractor Hub</a>
        <a href="<?php echo app_url('contractor/grades/edit.php'); ?>" class="btn-primary"><i class="fa-solid fa-plus"></i> Add Grade</a>
    </div>
    <div class="list-header">
        <div class="master-list-title">
            <div class="master-list-icon" style="background:#0F766E;"><i class="fa-solid fa-layer-group"></i></div>
            <div>
                <h1>Grade Master</h1>
                <p>Add · Edit · Delete contractor grades</p>
            </div>
        </div>
    </div>
    <div class="data-card data-card-pad">
        <div class="table-wrap">
            <table id="contractorTable" class="display data-table nowrap" style="width:100%">
                <thead>
                    <tr>
                        <th>Sr.</th>
                        <th>Grade Name</th>
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
    window.C_AJAX_URL = <?php echo json_encode(app_url('contractor/grades/ajax_list.php')); ?>;
    window.C_ACTION_COL = 2;
</script>
<?php
$extraJs = [
    'https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js',
    'https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js',
    'assets/js/contractor_list.js',
];
require_once __DIR__ . '/../../includes/footer.php';
