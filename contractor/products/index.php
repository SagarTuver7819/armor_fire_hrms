<?php
require_once __DIR__ . '/../_bootstrap.php';

$pageTitle = 'Product Master';
$extraCss = [
    'https://cdn.datatables.net/1.13.8/css/jquery.dataTables.min.css',
    'https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css',
];
$useSidebar = true;
$sidebarMode = 'contractor';
$sidebarActive = 'contractor_products';
require_once __DIR__ . '/../../includes/header.php';

$toastMsg = '';
if (isset($_GET['msg'])) {
    $map = ['added' => 'Product added successfully.', 'updated' => 'Product updated successfully.', 'deleted' => 'Product deleted successfully.'];
    $toastMsg = $map[$_GET['msg']] ?? '';
}
?>
<main class="dashboard-main">
    <div class="page-toolbar flex-between">
        <a href="<?php echo app_url('contractor/index.php'); ?>" class="back-link"><i class="fa-solid fa-arrow-left"></i> Back to Contractor Hub</a>
        <div class="toolbar-actions">
            <?php if (function_exists('isAdmin') && isAdmin()): ?>
            <a href="<?php echo app_url('contractor/products/sync_reference.php'); ?>" class="btn-secondary">
                <i class="fa-solid fa-cloud-arrow-down"></i> Sync from Reference
            </a>
            <?php endif; ?>
            <a href="<?php echo app_url('contractor/products/edit.php'); ?>" class="btn-primary"><i class="fa-solid fa-plus"></i> Add Product</a>
        </div>
    </div>
    <div class="list-header">
        <div class="master-list-title">
            <div class="master-list-icon" style="background:#C0392B;"><i class="fa-solid fa-box"></i></div>
            <div>
                <h1>Product Master</h1>
                <p>Operation · Rate · OT · Rejection — used by Operations Rate List</p>
            </div>
        </div>
    </div>
    <div class="data-card data-card-pad">
        <div class="table-wrap">
            <table id="contractorTable" class="display data-table nowrap" style="width:100%">
                <thead>
                    <tr>
                        <th>Sr.</th>
                        <th>Name</th>
                        <th>Operation</th>
                        <th>Process</th>
                        <th>Unit</th>
                        <th>OT Text</th>
                        <th>Rate</th>
                        <th>Rejection Rate</th>
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
    window.C_AJAX_URL = <?php echo json_encode(app_url('contractor/products/ajax_list.php')); ?>;
    window.C_ACTION_COL = 8;
</script>
<?php
$extraJs = [
    'https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js',
    'https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js',
    'assets/js/contractor_list.js',
];
require_once __DIR__ . '/../../includes/footer.php';
