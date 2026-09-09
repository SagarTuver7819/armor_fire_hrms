<?php
/**
 * employees/index.php
 * Employee Listing (Department-wise OR All Departments Report)
 * Uses server-side DataTable → light page load even with many rows
 *
 * Usage:
 *   employees/index.php?department_id=1   → one department
 *   employees/index.php                   → ALL employees report
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/employee_helper.php';

$deptId = isset($_GET['department_id']) ? (int) $_GET['department_id'] : 0;
$view   = isset($_GET['view']) && $_GET['view'] === 'exit' ? 'exit' : 'active';
$isExit = ($view === 'exit');
$department = null;
$isAllReport = ($deptId <= 0);

if (!$isAllReport) {
    $department = getDepartmentById($deptId);
    if (!$department) {
        header('Location: ' . app_url('dashboard.php'));
        exit;
    }
}

if ($isExit) {
    $pageTitle = $isAllReport ? 'Exit Employee List (All Departments)' : ('Exit Employee List — ' . $department['department_name']);
} else {
    $pageTitle = $isAllReport ? 'All Employees Report' : 'Join Employee';
}

$extraCss = [
    'https://cdn.datatables.net/1.13.8/css/jquery.dataTables.min.css',
    'https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css',
];

// Sidebar layout for grid pages
$useSidebar = true;
$sidebarMode = $isAllReport ? 'employees' : 'department';
$sidebarDeptId = $deptId;
$sidebarActive = $isExit ? 'exit_employee' : ($isAllReport ? 'all_employees' : 'join_employee');

require_once __DIR__ . '/../includes/header.php';

$toastMsg = '';
$toastType = 'success';
if (isset($_GET['msg'])) {
    $map = [
        'added'   => 'Employee added successfully.',
        'updated' => 'Employee updated successfully.',
        'deleted' => 'Employee deleted successfully.',
        'saved'   => 'Employee saved successfully.',
    ];
    $toastMsg = $map[$_GET['msg']] ?? '';
}

$activeCount = countActiveEmployeesByDepartment($deptId);
$exitCount   = countExitEmployeesByDepartment($deptId);

$activeListUrl = app_url('employees/index.php' . ($deptId > 0 ? '?department_id=' . $deptId : ''));
$exitListUrl   = app_url('employees/exit_list.php' . ($deptId > 0 ? '?department_id=' . $deptId : ''));

$ajaxUrl = app_url('employees/ajax_list.php?view=' . $view . ($deptId > 0 ? ('&department_id=' . $deptId) : ''));
$backUrl = $isAllReport
    ? app_url('dashboard.php')
    : app_url('department.php?id=' . $deptId);
$addUrl = app_url('employees/edit.php' . ($deptId > 0 ? ('?department_id=' . $deptId) : ''));
$excelUrl = app_url('employees/export_excel.php?view=' . $view . ($deptId > 0 ? ('&department_id=' . $deptId) : ''));
?>

<main class="dashboard-main">
    <div class="page-toolbar flex-between">
        <a href="<?php echo htmlspecialchars($backUrl); ?>" class="back-link">
            <i class="fa-solid fa-arrow-left"></i>
            <?php echo $isAllReport ? 'Back to Dashboard' : 'Back to Modules'; ?>
        </a>
        <div class="toolbar-actions">
            <?php if (!$isAllReport): ?>
                <a href="<?php echo app_url('employees/index.php' . ($isExit ? '?view=exit' : '')); ?>" class="btn-secondary">
                    <i class="fa-solid fa-users"></i> All Employees
                </a>
            <?php endif; ?>
            <a href="<?php echo htmlspecialchars($excelUrl); ?>" class="btn-secondary">
                <i class="fa-solid fa-file-excel"></i> Excel
            </a>
            <?php if (!$isExit): ?>
            <a href="<?php echo htmlspecialchars(app_url('employees/import.php') . ($deptId > 0 ? ('?department_id=' . $deptId) : '')); ?>" class="btn-secondary">
                <i class="fa-solid fa-file-import"></i> Import Employee
            </a>
            <?php endif; ?>
            <?php if ($isAllReport && function_exists('isAdmin') && isAdmin() && !$isExit): ?>
                <a href="<?php echo app_url('employees/sync_reference.php'); ?>" class="btn-secondary">
                    <i class="fa-solid fa-cloud-arrow-down"></i> Sync from Reference
                </a>
            <?php endif; ?>
            <?php if ($deptId > 0): ?>
                <a href="<?php echo htmlspecialchars($addUrl); ?>" class="btn-primary">
                    <i class="fa-solid fa-plus"></i> Add Employee
                </a>
            <?php endif; ?>
        </div>
    </div>

    <div class="list-header">
        <div>
            <h1><?php echo $isExit ? 'Exit Employee List' : ($isAllReport ? 'All Employees Report' : 'Join Employee'); ?></h1>
            <p>
                <?php if ($isExit): ?>
                    <?php echo $isAllReport ? 'All departments · Deactive & Exited employees' : (htmlspecialchars($department['department_name']) . ' · Deactive & Exited employees'); ?>
                <?php elseif ($isAllReport): ?>
                    All departments · Server-side loading (fast with large data)
                <?php else: ?>
                    <?php echo htmlspecialchars($department['department_name']); ?> · Employee Listing
                <?php endif; ?>
            </p>
        </div>
    </div>

    <!-- Active vs Exit Employee Tabs -->
    <div class="emp-nav-tabs">
        <a href="<?php echo htmlspecialchars($activeListUrl); ?>" class="emp-nav-tab <?php echo !$isExit ? 'active' : ''; ?>">
            <i class="fa-solid fa-user-check"></i>
            <span>Active Employees</span>
            <span class="emp-tab-badge"><?php echo (int) $activeCount; ?></span>
        </a>
        <a href="<?php echo htmlspecialchars($exitListUrl); ?>" class="emp-nav-tab is-exit <?php echo $isExit ? 'active' : ''; ?>">
            <i class="fa-solid fa-user-xmark"></i>
            <span>Exit Employee List</span>
            <span class="emp-tab-badge"><?php echo (int) $exitCount; ?></span>
        </a>
    </div>

    <div class="data-card data-card-pad">
        <div class="table-wrap">
            <table id="employeesTable" class="display data-table nowrap" style="width:100%">
                <thead>
                    <tr>
                        <th>Sr.</th>
                        <th>Emp. Code</th>
                        <th>Employee Name</th>
                        <?php if ($isAllReport): ?>
                            <th>Department</th>
                        <?php endif; ?>
                        <th>Type</th>
                        <th>Designation</th>
                        <th>Mobile</th>
                        <th>Joining Date</th>
                        <?php if ($isExit): ?>
                            <th>Exit Date</th>
                        <?php endif; ?>
                        <th>Shift</th>
                        <?php if ($isExit): ?>
                            <th>Status</th>
                        <?php endif; ?>
                        <th>PDF</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</main>

<script>
    window.EMP_TOAST_MSG   = <?php echo json_encode($toastMsg); ?>;
    window.EMP_TOAST_TYPE  = <?php echo json_encode($toastType); ?>;
    window.EMP_AJAX_URL    = <?php echo json_encode($ajaxUrl); ?>;
    window.EMP_IS_ALL      = <?php echo $isAllReport ? 'true' : 'false'; ?>;
    window.EMP_IS_EXIT     = <?php echo $isExit ? 'true' : 'false'; ?>;
    window.EMP_ACTION_COLS = <?php echo json_encode($isExit ? ($isAllReport ? [11, 12] : [10, 11]) : ($isAllReport ? [9, 10] : [8, 9])); ?>;
    window.EMP_APP_BASE    = <?php echo json_encode(APP_BASE); ?>;
    window.EMP_DEPT_ID     = <?php echo (int) $deptId; ?>;
</script>

<?php
$extraJs = [
    'https://code.jquery.com/jquery-3.7.1.min.js',
    'https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js',
    'https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js',
    'assets/js/employees_list.js',
];
require_once __DIR__ . '/../includes/footer.php';
?>
