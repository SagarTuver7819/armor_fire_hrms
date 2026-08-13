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
$department = null;
$isAllReport = ($deptId <= 0);

if (!$isAllReport) {
    $department = getDepartmentById($deptId);
    if (!$department) {
        header('Location: ' . app_url('dashboard.php'));
        exit;
    }
}

$pageTitle = $isAllReport ? 'All Employees Report' : 'Join Employee';
$extraCss = [
    'https://cdn.datatables.net/1.13.8/css/jquery.dataTables.min.css',
    'https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css',
];

// Sidebar layout for grid pages
$useSidebar = true;
$sidebarMode = $isAllReport ? 'employees' : 'department';
$sidebarDeptId = $deptId;
$sidebarActive = $isAllReport ? 'all_employees' : 'join_employee';

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

$ajaxUrl = app_url('employees/ajax_list.php') . ($deptId > 0 ? ('?department_id=' . $deptId) : '');
$backUrl = $isAllReport
    ? app_url('dashboard.php')
    : app_url('department.php?id=' . $deptId);
$addUrl = app_url('employees/edit.php' . ($deptId > 0 ? ('?department_id=' . $deptId) : ''));
?>

<main class="dashboard-main">
    <div class="page-toolbar flex-between">
        <a href="<?php echo htmlspecialchars($backUrl); ?>" class="back-link">
            <i class="fa-solid fa-arrow-left"></i>
            <?php echo $isAllReport ? 'Back to Dashboard' : 'Back to Modules'; ?>
        </a>
        <div class="toolbar-actions">
            <?php if (!$isAllReport): ?>
                <a href="<?php echo app_url('employees/index.php'); ?>" class="btn-secondary">
                    <i class="fa-solid fa-users"></i> All Employees
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
            <h1><?php echo $isAllReport ? 'All Employees Report' : 'Join Employee'; ?></h1>
            <p>
                <?php if ($isAllReport): ?>
                    All departments · Server-side loading (fast with large data)
                <?php else: ?>
                    <?php echo htmlspecialchars($department['department_name']); ?> · Employee Listing
                <?php endif; ?>
            </p>
        </div>
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
                        <th>Designation</th>
                        <th>Mobile</th>
                        <th>Joining Date</th>
                        <th>Shift</th>
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
