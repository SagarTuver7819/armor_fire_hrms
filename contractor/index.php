<?php
/**
 * Contractor Manage hub
 */

require_once __DIR__ . '/_bootstrap.php';

$pageTitle = 'Contractor Manage';
$useSidebar = true;
$sidebarMode = 'contractor';
$sidebarActive = 'contractor_hub';

require_once __DIR__ . '/../includes/header.php';

$modules = [
    ['key' => 'employees', 'title' => 'Contractor Employee', 'icon' => 'fa-id-card', 'color' => '#F58220', 'url' => 'contractor/employees/index.php', 'meta' => 'Jobwork employees'],
    ['key' => 'employment', 'title' => 'Contractor Employment Details', 'icon' => 'fa-briefcase', 'color' => '#3498DB', 'url' => 'contractor/employment/index.php', 'meta' => 'Dept · Shift · PF / UAN'],
    ['key' => 'products', 'title' => 'Product Master', 'icon' => 'fa-box', 'color' => '#C0392B', 'url' => 'contractor/products/index.php', 'meta' => 'Operation · Rate · OT / R'],
    ['key' => 'grades', 'title' => 'Grade Master', 'icon' => 'fa-layer-group', 'color' => '#0F766E', 'url' => 'contractor/grades/index.php', 'meta' => 'Grade list'],
    ['key' => 'operations', 'title' => 'Operations Rate List', 'icon' => 'fa-table', 'color' => '#8E44AD', 'url' => 'contractor/operations/index.php', 'meta' => 'Daily qty × rate'],
    ['key' => 'register_govt', 'title' => 'Jobwork Register (Government)', 'icon' => 'fa-landmark', 'color' => '#1e3a5f', 'url' => 'payroll/register.php?type=jobwork_govt', 'meta' => 'Actual ÷ month days'],
    ['key' => 'register_actual', 'title' => 'Jobwork Register (Actual)', 'icon' => 'fa-scale-balanced', 'color' => '#0F766E', 'url' => 'payroll/register.php?type=jobwork_actual', 'meta' => 'Qty × rate as earned'],
    ['key' => 'register_main', 'title' => 'Contractor Main Register', 'icon' => 'fa-users-line', 'color' => '#C0392B', 'url' => 'payroll/register.php?type=contractor_main', 'meta' => 'Under employee salaries'],
];
?>

<main class="dashboard-main">
    <div class="page-toolbar">
        <a href="<?php echo app_url('dashboard.php'); ?>" class="back-link">
            <i class="fa-solid fa-arrow-left"></i> Back to Dashboard
        </a>
    </div>

    <div class="dashboard-title-block">
        <h1>Contractor Manage</h1>
        <p>CONTRACT WORKFORCE · PRODUCTS · DAILY RATE SHEET</p>
    </div>

    <section class="module-section">
        <div class="section-heading">
            <i class="fa-solid fa-helmet-safety"></i>
            <span>MODULES</span>
        </div>
        <div class="module-grid module-grid-sm">
            <?php foreach ($modules as $m): ?>
                <a href="<?php echo app_url($m['url']); ?>" class="module-card">
                    <div class="module-icon" style="background-color: <?php echo htmlspecialchars($m['color']); ?>;">
                        <i class="fa-solid <?php echo htmlspecialchars($m['icon']); ?>"></i>
                    </div>
                    <div class="module-label"><?php echo htmlspecialchars($m['title']); ?></div>
                    <div class="module-meta"><?php echo htmlspecialchars($m['meta']); ?></div>
                </a>
            <?php endforeach; ?>
        </div>
    </section>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
