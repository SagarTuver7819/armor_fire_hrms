<?php
/**
 * Department Modules Page
 * After clicking a department box on dashboard.
 */

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/employee_helper.php';

$deptId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$department = $deptId > 0 ? getDepartmentById($deptId) : null;

$pageTitle = $department ? $department['department_name'] : 'Department';

$useSidebar = true;
$sidebarMode = 'department';
$sidebarDeptId = $deptId;
$sidebarActive = 'modules';

require_once __DIR__ . '/includes/header.php';

$empCount = $department ? countEmployeesByDepartment($department['id']) : 0;
?>

<main class="dashboard-main">
    <div class="page-toolbar flex-between">
        <a href="<?php echo app_url('dashboard.php'); ?>" class="back-link">
            <i class="fa-solid fa-arrow-left"></i> Back to Dashboard
        </a>
        <a href="<?php echo app_url('employees/index.php'); ?>" class="btn-secondary">
            <i class="fa-solid fa-users"></i> All Employees
        </a>
    </div>

    <?php if (!$department): ?>
        <div class="detail-card">
            <p class="empty-msg">Department not found.</p>
        </div>
    <?php else: ?>
        <div class="dashboard-title-block">
            <div class="dept-title-icon" style="background: <?php echo htmlspecialchars($department['icon_color']); ?>;">
                <i class="fa-solid <?php echo htmlspecialchars($department['icon_class']); ?>"></i>
            </div>
            <h1><?php echo htmlspecialchars($department['department_name']); ?></h1>
            <p>DEPARTMENT WORKSPACE · WORKERS &amp; STAFF</p>
        </div>

        <section class="module-section">
            <div class="section-heading">
                <i class="fa-solid fa-puzzle-piece"></i>
                <span>AVAILABLE MODULES</span>
            </div>

            <div class="module-grid module-grid-sm">
                <!-- Primary manufacturing action -->
                <a href="<?php echo app_url('employees/index.php?department_id=' . (int) $department['id']); ?>" class="module-card masters-entry-card">
                    <div class="module-icon" style="background-color: #F58220;">
                        <i class="fa-solid fa-user-plus"></i>
                    </div>
                    <div class="module-label">Join Employee</div>
                    <div class="module-meta"><?php echo (int) $empCount; ?> Employees · Open List</div>
                </a>

                <a href="#" class="module-card disabled-card" title="Coming soon">
                    <div class="module-icon" style="background-color: #2ECC71;">
                        <i class="fa-solid fa-file-invoice"></i>
                    </div>
                    <div class="module-label">Leave Request</div>
                </a>
                <a href="<?php echo app_url('employees/index.php?department_id=' . (int) $department['id']); ?>" class="module-card">
                    <div class="module-icon" style="background-color: #9B59B6;">
                        <i class="fa-solid fa-chart-simple"></i>
                    </div>
                    <div class="module-label">Dept. Employees Report</div>
                    <div class="module-meta">List + Excel download</div>
                </a>
                <a href="<?php echo app_url('payroll/diary.php?department_id=' . (int) $department['id']); ?>" class="module-card">
                    <div class="module-icon" style="background-color: #3498DB;">
                        <i class="fa-solid fa-calendar-check"></i>
                    </div>
                    <div class="module-label">Salary Diary</div>
                    <div class="module-meta">Attendance days</div>
                </a>
                <a href="<?php echo app_url('payroll/jobwork.php?department_id=' . (int) $department['id']); ?>" class="module-card">
                    <div class="module-icon" style="background-color: #E67E22;">
                        <i class="fa-solid fa-gears"></i>
                    </div>
                    <div class="module-label">Jobwork Entry</div>
                    <div class="module-meta">Qty × Rate</div>
                </a>
                <a href="<?php echo app_url('payroll/generate.php?department_id=' . (int) $department['id']); ?>" class="module-card">
                    <div class="module-icon" style="background-color: #16A085;">
                        <i class="fa-solid fa-indian-rupee-sign"></i>
                    </div>
                    <div class="module-label">Generate Salary</div>
                    <div class="module-meta">Diary / Jobwork + Slab</div>
                </a>
            </div>
        </section>
    <?php endif; ?>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
