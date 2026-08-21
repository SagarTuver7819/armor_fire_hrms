<?php
/**
 * Holiday Master list with Monthly Set shortcut
 */
$masterKey = 'holidays';
require_once __DIR__ . '/../_core/bootstrap.php';

$pageTitle = $master['title'];
$extraCss = [
    'https://cdn.datatables.net/1.13.8/css/jquery.dataTables.min.css',
    'https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css',
];

$useSidebar = true;
$sidebarMode = 'masters';
$sidebarActive = $master['key'];

require_once __DIR__ . '/../../includes/header.php';

$toastMsg = '';
if (isset($_GET['msg'])) {
    $map = [
        'added'   => $master['singular'] . ' added successfully.',
        'updated' => $master['singular'] . ' updated successfully.',
        'deleted' => $master['singular'] . ' deleted successfully.',
    ];
    $toastMsg = $map[$_GET['msg']] ?? '';
}

$colCount = 1 + count($master['list_columns']) + 1;
$actionColIndex = $colCount - 1;
$monthlyUrl = app_url('masters/holidays/monthly.php');
?>

<main class="dashboard-main">
    <div class="page-toolbar flex-between">
        <a href="<?php echo htmlspecialchars($mastersHubUrl); ?>" class="back-link">
            <i class="fa-solid fa-arrow-left"></i> Back to Masters
        </a>
        <div class="toolbar-actions" style="display:flex;gap:8px;flex-wrap:wrap;">
            <a href="<?php echo htmlspecialchars($monthlyUrl); ?>" class="btn-secondary">
                <i class="fa-solid fa-calendar-plus"></i> Monthly Holiday Set
            </a>
            <a href="<?php echo htmlspecialchars($masterAddUrl); ?>" class="btn-primary">
                <i class="fa-solid fa-plus"></i> Add <?php echo htmlspecialchars($master['singular']); ?>
            </a>
        </div>
    </div>

    <div class="list-header">
        <div class="master-list-title">
            <div class="master-list-icon" style="background: <?php echo htmlspecialchars($master['color']); ?>;">
                <i class="fa-solid <?php echo htmlspecialchars($master['icon']); ?>"></i>
            </div>
            <div>
                <h1><?php echo htmlspecialchars($master['title']); ?></h1>
                <p>Set holidays · Monthly set · Paid holidays apply in salary when employee Holiday Benefits = Yes</p>
            </div>
        </div>
    </div>

    <div class="data-card data-card-pad">
        <div class="table-wrap">
            <table id="mastersTable" class="display data-table nowrap" style="width:100%">
                <thead>
                    <tr>
                        <th>Sr.</th>
                        <?php foreach ($master['list_columns'] as $col): ?>
                            <th><?php echo htmlspecialchars($col['label']); ?></th>
                        <?php endforeach; ?>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</main>

<script>
    window.MASTER_TOAST_MSG = <?php echo json_encode($toastMsg); ?>;
    window.MASTER_AJAX_URL  = <?php echo json_encode($masterAjaxUrl); ?>;
    window.MASTER_ACTION_COL = <?php echo (int) $actionColIndex; ?>;
    window.MASTER_LABEL = <?php echo json_encode($master['singular']); ?>;
</script>

<?php
$extraJs = [
    'https://code.jquery.com/jquery-3.7.1.min.js',
    'https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js',
    'https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js',
    'assets/js/masters_list.js',
];
require_once __DIR__ . '/../../includes/footer.php';
?>
