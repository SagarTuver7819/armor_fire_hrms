<?php
/**
 * Holiday Master list — global or department-scoped
 */
$masterKey = 'holidays';
require_once __DIR__ . '/../_core/bootstrap.php';
require_once __DIR__ . '/../../includes/employee_helper.php';

$scopeDeptId = (int) ($_GET['department_id'] ?? 0);
$scopeDept = $scopeDeptId > 0 ? getDepartmentById($scopeDeptId) : null;
if ($scopeDeptId > 0 && !$scopeDept) {
    $scopeDeptId = 0;
}

$pageTitle = $master['title'] . ($scopeDept ? (' · ' . $scopeDept['department_name']) : '');
$extraCss = [
    'https://cdn.datatables.net/1.13.8/css/jquery.dataTables.min.css',
    'https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css',
];

$useSidebar = true;
if ($scopeDeptId > 0) {
    $sidebarMode = 'department';
    $sidebarDeptId = $scopeDeptId;
} else {
    $sidebarMode = 'masters';
}
$sidebarActive = $master['key'];

$deptQs = $scopeDeptId > 0 ? ('?department_id=' . $scopeDeptId) : '';
$masterListUrl = app_url('masters/holidays/index.php' . $deptQs);
$masterAddUrl = app_url('masters/holidays/edit.php' . ($scopeDeptId > 0 ? ('?department_id=' . $scopeDeptId) : ''));
$masterAjaxUrl = app_url('masters/holidays/ajax_list.php');
$monthlyUrl = app_url('masters/holidays/monthly.php' . $deptQs);
$backUrl = $scopeDeptId > 0
    ? app_url('department.php?id=' . $scopeDeptId)
    : $mastersHubUrl;
$backText = $scopeDeptId > 0 ? 'Back to Modules' : 'Back to Masters';

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
?>

<main class="dashboard-main">
    <div class="page-toolbar flex-between">
        <a href="<?php echo htmlspecialchars($backUrl); ?>" class="back-link">
            <i class="fa-solid fa-arrow-left"></i> <?php echo htmlspecialchars($backText); ?>
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
                <p>
                    <?php if ($scopeDept): ?>
                        Department: <strong><?php echo htmlspecialchars($scopeDept['department_name']); ?></strong>
                        · Shows this department + All-Departments holidays
                    <?php else: ?>
                        Set holidays · Monthly set · Department wise or All Departments
                    <?php endif; ?>
                </p>
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
    window.MASTER_DEPT_ID = <?php echo (int) $scopeDeptId; ?>;
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
