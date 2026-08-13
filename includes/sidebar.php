<?php
/**
 * App Sidebar — Dashboard / Masters / Departments (submenu → boxes)
 *
 * Enable before header:
 *   $useSidebar = true;
 *   $sidebarMode = 'workspace' | 'masters' | 'employees';
 *   $sidebarDeptId = 5;          // optional current department
 *   $sidebarActive = 'join_employee' | 'modules' | 'hub' | master key | 'all_employees';
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';

$sidebarMode = $sidebarMode ?? 'workspace';
$sidebarDeptId = isset($sidebarDeptId) ? (int) $sidebarDeptId : 0;
$sidebarActive = $sidebarActive ?? '';

$sidebarDepartments = [];
$connSb = getDBConnection();
$resSb = $connSb->query(
    "SELECT id, department_name, icon_class, icon_color
     FROM departments WHERE status = 1
     ORDER BY sort_order ASC, department_name ASC"
);
if ($resSb) {
    while ($r = $resSb->fetch_assoc()) {
        $sidebarDepartments[] = $r;
    }
}
$connSb->close();

$sidebarDept = null;
if ($sidebarDeptId > 0) {
    foreach ($sidebarDepartments as $d) {
        if ((int) $d['id'] === $sidebarDeptId) {
            $sidebarDept = $d;
            break;
        }
    }
}

if (!function_exists('getMastersConfig')) {
    require_once __DIR__ . '/masters_config.php';
}
$mastersNav = getMastersConfig();

$openDepartments = ($sidebarDeptId > 0 || $sidebarMode === 'department' || $sidebarMode === 'employees' || $sidebarActive === 'modules');
$openMasters = ($sidebarMode === 'masters' || $sidebarActive === 'hub' || isset($mastersNav[$sidebarActive]));
?>

<aside class="app-sidebar" id="appSidebar" aria-label="Sidebar navigation">
    <div class="sidebar-top">
        <div class="sidebar-brand-mini">
            <i class="fa-solid fa-bars-staggered"></i>
            <span class="sidebar-brand-text">Menu</span>
        </div>
        <button type="button" class="sidebar-toggle" id="sidebarToggle" title="Hide / Show sidebar" aria-label="Toggle sidebar">
            <i class="fa-solid fa-angles-left"></i>
        </button>
    </div>

    <div class="sidebar-scroll">
        <div class="sidebar-section">
            <div class="sidebar-section-title">Main</div>
            <nav class="sidebar-nav">
                <a href="<?php echo app_url('dashboard.php'); ?>"
                   class="sidebar-link <?php echo $sidebarActive === 'dashboard' ? 'active' : ''; ?>">
                    <i class="fa-solid fa-house"></i>
                    <span>Dashboard</span>
                </a>
                <a href="<?php echo app_url('employees/index.php'); ?>"
                   class="sidebar-link <?php echo $sidebarActive === 'all_employees' ? 'active' : ''; ?>">
                    <i class="fa-solid fa-users"></i>
                    <span>All Employees</span>
                </a>
                <?php if (function_exists('isAdmin') && isAdmin()): ?>
                <a href="<?php echo app_url('company_settings.php'); ?>"
                   class="sidebar-link <?php echo $sidebarActive === 'settings' ? 'active' : ''; ?>">
                    <i class="fa-solid fa-image"></i>
                    <span>Company Settings</span>
                </a>
                <?php endif; ?>
            </nav>
        </div>

        <!-- Masters accordion -->
        <div class="sidebar-section">
            <div class="sidebar-section-title">Masters</div>
            <div class="sidebar-accordion <?php echo $openMasters ? 'is-open' : ''; ?>" data-accordion="masters">
                <button type="button" class="sidebar-acc-btn" aria-expanded="<?php echo $openMasters ? 'true' : 'false'; ?>">
                    <span class="sidebar-acc-left">
                        <i class="fa-solid fa-database"></i>
                        <span>All Masters</span>
                    </span>
                    <i class="fa-solid fa-chevron-down sidebar-acc-caret"></i>
                </button>
                <div class="sidebar-submenu">
                    <a href="<?php echo app_url('masters/index.php'); ?>"
                       class="sidebar-link sidebar-sublink <?php echo $sidebarActive === 'hub' ? 'active' : ''; ?>">
                        <i class="fa-solid fa-cubes"></i>
                        <span>Masters Hub (Boxes)</span>
                    </a>
                    <?php foreach ($mastersNav as $m): ?>
                        <a href="<?php echo app_url('masters/' . $m['folder'] . '/index.php'); ?>"
                           class="sidebar-link sidebar-sublink <?php echo $sidebarActive === $m['key'] ? 'active' : ''; ?>">
                            <i class="fa-solid <?php echo htmlspecialchars($m['icon']); ?>"></i>
                            <span><?php echo htmlspecialchars($m['title']); ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Departments accordion → opens department boxes page -->
        <div class="sidebar-section">
            <div class="sidebar-section-title">Departments</div>
            <div class="sidebar-accordion <?php echo $openDepartments ? 'is-open' : ''; ?>" data-accordion="departments">
                <button type="button" class="sidebar-acc-btn" aria-expanded="<?php echo $openDepartments ? 'true' : 'false'; ?>">
                    <span class="sidebar-acc-left">
                        <i class="fa-solid fa-building"></i>
                        <span>Department List</span>
                    </span>
                    <i class="fa-solid fa-chevron-down sidebar-acc-caret"></i>
                </button>
                <div class="sidebar-submenu sidebar-submenu-scroll">
                    <a href="<?php echo app_url('dashboard.php#department-workspace'); ?>"
                       class="sidebar-link sidebar-sublink <?php echo $sidebarActive === 'dashboard' ? 'active' : ''; ?>">
                        <i class="fa-solid fa-border-all"></i>
                        <span>All Department Boxes</span>
                    </a>
                    <?php foreach ($sidebarDepartments as $d): ?>
                        <a href="<?php echo app_url('department.php?id=' . (int) $d['id']); ?>"
                           class="sidebar-link sidebar-sublink <?php echo ((int) $d['id'] === $sidebarDeptId && $sidebarActive === 'modules') ? 'active' : ''; ?>"
                           title="<?php echo htmlspecialchars($d['department_name']); ?>">
                            <span class="sidebar-dot" style="background: <?php echo htmlspecialchars($d['icon_color']); ?>;"></span>
                            <span><?php echo htmlspecialchars($d['department_name']); ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <?php if ($sidebarDept): ?>
            <div class="sidebar-section">
                <div class="sidebar-section-title">Current Department</div>
                <div class="sidebar-dept-card">
                    <span class="sidebar-dept-icon" style="background: <?php echo htmlspecialchars($sidebarDept['icon_color']); ?>;">
                        <i class="fa-solid <?php echo htmlspecialchars($sidebarDept['icon_class']); ?>"></i>
                    </span>
                    <div>
                        <strong><?php echo htmlspecialchars($sidebarDept['department_name']); ?></strong>
                        <small>Module boxes</small>
                    </div>
                </div>
                <nav class="sidebar-nav">
                    <a href="<?php echo app_url('department.php?id=' . $sidebarDeptId); ?>"
                       class="sidebar-link <?php echo $sidebarActive === 'modules' ? 'active' : ''; ?>">
                        <i class="fa-solid fa-puzzle-piece"></i>
                        <span>Module Boxes</span>
                    </a>
                    <a href="<?php echo app_url('employees/index.php?department_id=' . $sidebarDeptId); ?>"
                       class="sidebar-link <?php echo $sidebarActive === 'join_employee' ? 'active' : ''; ?>">
                        <i class="fa-solid fa-user-plus"></i>
                        <span>Join Employee</span>
                    </a>
                    <span class="sidebar-link disabled" title="Coming soon">
                        <i class="fa-solid fa-calendar-check"></i>
                        <span>Attendance</span>
                    </span>
                    <span class="sidebar-link disabled" title="Coming soon">
                        <i class="fa-solid fa-file-invoice"></i>
                        <span>Leave Request</span>
                    </span>
                </nav>
            </div>
        <?php endif; ?>
    </div>

    <div class="sidebar-footer-mini">
        <a href="<?php echo app_url('dashboard.php'); ?>" class="sidebar-link">
            <i class="fa-solid fa-house"></i>
            <span>Dashboard</span>
        </a>
    </div>
</aside>

<button type="button" class="sidebar-fab" id="sidebarFab" title="Show sidebar" aria-label="Show sidebar">
    <i class="fa-solid fa-bars"></i>
</button>
