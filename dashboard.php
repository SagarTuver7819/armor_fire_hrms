<?php
/**
 * Dashboard — Workspace hub (quick access + departments)
 */

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/permission_helper.php';
require_once __DIR__ . '/includes/department_head_helper.php';
require_once __DIR__ . '/includes/masters_config.php';

requireLogin();
if (empty($_SESSION['role_code']) && !empty($_SESSION['custom_role_id'])) {
    refreshHeadedDepartmentsSession();
}
// Office Staff → dedicated employee dashboard (not Admin workspace)
if (function_exists('isOfficeStaffRole') && isOfficeStaffRole()) {
    $q = isset($_GET['msg']) ? ('?msg=' . rawurlencode((string) $_GET['msg'])) : '';
    header('Location: ' . app_url('employee/dashboard.php' . $q));
    exit;
}

$pageTitle = 'Dashboard';
// Main workspace: no sidebar (card hub layout, like before)
require_once __DIR__ . '/includes/header.php';

$conn = getDBConnection();
$sql = "SELECT id, department_name, icon_class, icon_color, sort_order
        FROM departments
        WHERE status = 1
        ORDER BY sort_order ASC, department_name ASC";
$result = $conn->query($sql);
$departments = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $departments[] = $row;
    }
}
$conn->close();

$masterCount = count(getMastersConfig());
$deptCount = count($departments);
$userLabel = function_exists('getUserName') ? getUserName() : 'User';
$hour = (int) date('G');
if ($hour < 12) {
    $greet = 'Good morning';
} elseif ($hour < 17) {
    $greet = 'Good afternoon';
} else {
    $greet = 'Good evening';
}
?>

<main class="dashboard-main dash-workspace">
    <div class="dash-hero">
        <div class="dash-hero-copy">
            <p class="dash-eyebrow"><?php echo htmlspecialchars(strtoupper(getCompanyName())); ?> · WORKSPACE</p>
            <h1><?php echo htmlspecialchars($greet); ?>, <?php echo htmlspecialchars($userLabel); ?></h1>
            <p class="dash-sub">Open HR tools, masters, contractor ops, or jump into a department.</p>
        </div>
        <div class="dash-hero-stats">
            <div class="dash-stat">
                <span class="dash-stat-num"><?php echo (int) $deptCount; ?></span>
                <span class="dash-stat-lab">Departments</span>
            </div>
            <div class="dash-stat">
                <span class="dash-stat-num"><?php echo (int) $masterCount; ?></span>
                <span class="dash-stat-lab">Masters</span>
            </div>
        </div>
    </div>

    <section class="module-section dash-section">
        <div class="section-heading">
            <i class="fa-solid fa-bolt"></i>
            <span>QUICK ACCESS</span>
        </div>
        <div class="dash-hub-grid">
            <a href="<?php echo htmlspecialchars(app_url('hr/dashboard.php')); ?>" class="dash-hub-card is-hr" title="Open HR Dashboard">
                <div class="dash-hub-icon" style="background:#0F766E;"><i class="fa-solid fa-chart-pie"></i></div>
                <div class="dash-hub-body">
                    <strong>HR Dashboard</strong>
                    <span>Birthdays · Attendance · Leaves</span>
                </div>
                <i class="fa-solid fa-arrow-right dash-hub-go"></i>
            </a>
            <a href="<?php echo htmlspecialchars(app_url('masters/index.php')); ?>" class="dash-hub-card is-masters" title="All Masters">
                <div class="dash-hub-icon" style="background:#F58220;"><i class="fa-solid fa-cubes"></i></div>
                <div class="dash-hub-body">
                    <strong>All Masters</strong>
                    <span><?php echo (int) $masterCount; ?> Masters · CRUD</span>
                </div>
                <i class="fa-solid fa-arrow-right dash-hub-go"></i>
            </a>
            <a href="<?php echo htmlspecialchars(app_url('contractor/index.php')); ?>" class="dash-hub-card is-contractor" title="Contractor Manage">
                <div class="dash-hub-icon" style="background:#8E44AD;"><i class="fa-solid fa-helmet-safety"></i></div>
                <div class="dash-hub-body">
                    <strong>Contractor Manage</strong>
                    <span>Employees · Products · Rate List</span>
                </div>
                <i class="fa-solid fa-arrow-right dash-hub-go"></i>
            </a>
            <a href="<?php echo htmlspecialchars(app_url('employees/index.php')); ?>" class="dash-hub-card is-emp" title="All Employees Report">
                <div class="dash-hub-icon" style="background:#059669;"><i class="fa-solid fa-users"></i></div>
                <div class="dash-hub-body">
                    <strong>All Employees</strong>
                    <span>Active staff directory</span>
                </div>
                <i class="fa-solid fa-arrow-right dash-hub-go"></i>
            </a>
            <a href="<?php echo htmlspecialchars(app_url('employees/exit_list.php')); ?>" class="dash-hub-card is-exit" title="Exit Employee List">
                <div class="dash-hub-icon" style="background:#DC2626;"><i class="fa-solid fa-user-xmark"></i></div>
                <div class="dash-hub-body">
                    <strong>Exit Employees</strong>
                    <span>Deactive &amp; exited staff</span>
                </div>
                <i class="fa-solid fa-arrow-right dash-hub-go"></i>
            </a>
            <?php if (isAdmin()): ?>
            <a href="<?php echo htmlspecialchars(app_url('company_settings.php')); ?>" class="dash-hub-card is-admin" title="Company Logo Setup">
                <div class="dash-hub-icon" style="background:#E85D75;"><i class="fa-solid fa-image"></i></div>
                <div class="dash-hub-body">
                    <strong>Company Settings</strong>
                    <span>Logo &amp; branding</span>
                </div>
                <i class="fa-solid fa-arrow-right dash-hub-go"></i>
            </a>
            <?php endif; ?>
        </div>
    </section>

    <section class="module-section dash-section" id="department-workspace">
        <div class="section-heading">
            <i class="fa-solid fa-building"></i>
            <span>DEPARTMENT WORKSPACE</span>
            <em class="dash-section-count"><?php echo (int) $deptCount; ?></em>
        </div>

        <div class="module-grid dash-dept-grid">
            <?php if ($deptCount === 0): ?>
                <p class="empty-msg">No departments found. Please run DB Sync / seed masters.</p>
            <?php else: ?>
                <?php foreach ($departments as $dept): ?>
                    <a href="<?php echo htmlspecialchars(app_url('department.php?id=' . (int) $dept['id'])); ?>"
                       class="module-card dash-dept-card"
                       title="<?php echo htmlspecialchars($dept['department_name']); ?>">
                        <div class="module-icon" style="background-color: <?php echo htmlspecialchars($dept['icon_color']); ?>;">
                            <i class="fa-solid <?php echo htmlspecialchars($dept['icon_class']); ?>"></i>
                        </div>
                        <div class="module-label"><?php echo htmlspecialchars($dept['department_name']); ?></div>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </section>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
