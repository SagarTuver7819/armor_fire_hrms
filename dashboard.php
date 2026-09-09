<?php
/**
 * Dashboard - Department Master as Cards
 * Looks like ERP Workspace Dashboard with department boxes.
 */

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/masters_config.php';

$pageTitle = 'Dashboard';
require_once __DIR__ . '/includes/header.php';

$conn = getDBConnection();

// Admin: see all departments
// Employee: see all departments for now (master view) - can restrict later
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
?>

<main class="dashboard-main">
    <div class="dashboard-title-block">
        <h1><?php echo strtoupper(htmlspecialchars(getCompanyName())); ?> HRMS</h1>
        <p>WORKSPACE DASHBOARD · MANUFACTURING HRMS</p>
    </div>

    <!-- Masters Hub entry -->
    <section class="module-section">
        <div class="section-heading">
            <i class="fa-solid fa-database"></i>
            <span>MASTERS</span>
        </div>
        <div class="module-grid module-grid-sm">
            <a href="<?php echo app_url('masters/index.php'); ?>" class="module-card masters-entry-card" title="All Masters">
                <div class="module-icon" style="background-color: #F58220;">
                    <i class="fa-solid fa-cubes"></i>
                </div>
                <div class="module-label">All Masters</div>
                <div class="module-meta"><?php echo count(getMastersConfig()); ?> Masters · CRUD</div>
            </a>
        </div>
    </section>

    <!-- Contractor Manage -->
    <section class="module-section">
        <div class="section-heading">
            <i class="fa-solid fa-helmet-safety"></i>
            <span>CONTRACTOR MANAGE</span>
        </div>
        <div class="module-grid module-grid-sm">
            <a href="<?php echo app_url('contractor/index.php'); ?>" class="module-card masters-entry-card" title="Contractor Manage">
                <div class="module-icon" style="background-color: #8E44AD;">
                    <i class="fa-solid fa-helmet-safety"></i>
                </div>
                <div class="module-label">Contractor Manage</div>
                <div class="module-meta">Employees · Products · Rate List</div>
            </a>
        </div>
    </section>

    <!-- Department workspace (Join Employee flow) -->
    <section class="module-section" id="department-workspace">
        <div class="section-heading">
            <i class="fa-solid fa-building"></i>
            <span>DEPARTMENT WORKSPACE</span>
        </div>

        <div class="module-grid">
            <?php if (count($departments) === 0): ?>
                <p class="empty-msg">No departments found. Please import the SQL file.</p>
            <?php else: ?>
                <?php foreach ($departments as $dept): ?>
                    <a href="<?php echo app_url('department.php?id=' . (int) $dept['id']); ?>" class="module-card" title="<?php echo htmlspecialchars($dept['department_name']); ?>">
                        <div class="module-icon" style="background-color: <?php echo htmlspecialchars($dept['icon_color']); ?>;">
                            <i class="fa-solid <?php echo htmlspecialchars($dept['icon_class']); ?>"></i>
                        </div>
                        <div class="module-label">
                            <?php echo htmlspecialchars($dept['department_name']); ?>
                        </div>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </section>

    <!-- All employees report (all departments) -->
    <section class="module-section">
        <div class="section-heading">
            <i class="fa-solid fa-users"></i>
            <span>EMPLOYEE REPORTS</span>
        </div>
        <div class="module-grid module-grid-sm">
            <a href="<?php echo app_url('employees/index.php'); ?>" class="module-card" title="All Employees Report">
                <div class="module-icon" style="background-color: #2ECC71;">
                    <i class="fa-solid fa-list"></i>
                </div>
                <div class="module-label">All Employees Report</div>
                <div class="module-meta">Active staff directory</div>
            </a>
            <a href="<?php echo app_url('employees/exit_list.php'); ?>" class="module-card" title="Exit Employee List">
                <div class="module-icon" style="background-color: #EF4444;">
                    <i class="fa-solid fa-user-xmark"></i>
                </div>
                <div class="module-label">Exit Employee List</div>
                <div class="module-meta">Deactive &amp; Exited staff</div>
            </a>
        </div>
    </section>

    <?php if (isAdmin()): ?>
    <!-- Admin-only quick links (placeholder for next phase) -->
    <section class="module-section">
        <div class="section-heading">
            <i class="fa-solid fa-chart-pie"></i>
            <span>ADMIN CONTROLS</span>
        </div>
        <div class="module-grid module-grid-sm">
            <a href="<?php echo app_url('company_settings.php'); ?>" class="module-card" title="Company Logo Setup">
                <div class="module-icon" style="background-color: #E85D75;">
                    <i class="fa-solid fa-image"></i>
                </div>
                <div class="module-label">Company Logo Setup</div>
            </a>
            <a href="#" class="module-card disabled-card" title="Coming soon">
                <div class="module-icon" style="background-color: #5B6CFF;">
                    <i class="fa-solid fa-user-gear"></i>
                </div>
                <div class="module-label">Manage Users</div>
            </a>
        </div>
    </section>
    <?php endif; ?>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
