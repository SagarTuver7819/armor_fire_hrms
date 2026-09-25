<?php
/**
 * Common Header
 * Used on authenticated pages (dashboard etc.)
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/settings.php';
requireLogin();

$companyName = getCompanyName();
$companyLogo = getDashboardLogo();
// Logo path may be relative - prefix with APP_BASE if needed
$logoSrc = $companyLogo;
if ($logoSrc && strpos($logoSrc, 'http') !== 0 && strpos($logoSrc, '/') !== 0) {
    $logoSrc = app_url($logoSrc);
}

$headerNotifyItems = [];
$headerNotifyUnread = 0;
$headerEmpPhotoUrl = '';
$headerEmpProfileUrl = '';
$headerSessionEmpId = (int) ($_SESSION['employee_id'] ?? 0);
if ($headerSessionEmpId > 0) {
    if (!function_exists('getEmployeeById')) {
        require_once __DIR__ . '/employee_helper.php';
    }
    $headerEmpRow = getEmployeeById($headerSessionEmpId);
    if ($headerEmpRow) {
        $headerEmpPhotoUrl = employeeDocumentPublicUrl($headerEmpRow['photo_file'] ?? '');
        $headerEmpProfileUrl = app_url('employees/view.php?id=' . $headerSessionEmpId);
    }
}
if (function_exists('isPortalUser') && isPortalUser()) {
    $uid = (int) ($_SESSION['user_id'] ?? 0);
    require_once __DIR__ . '/circular_helper.php';
    require_once __DIR__ . '/policy_helper.php';
    if (function_exists('ensureCircularTables')) {
        ensureCircularTables();
        foreach (fetchUnreadCircularNotifications($uid, 8) as $n) {
            $n['_type'] = 'circular';
            $n['_date'] = $n['circular_date'] ?? '';
            $n['_dept_label'] = circularDepartmentsLabel($n);
            $n['_url'] = app_url('circulars/mark_read.php?id=' . (int) $n['id'] . '&go=view');
            $n['_icon'] = 'fa-file-circle-plus';
            $n['_label'] = 'Circular';
            $headerNotifyItems[] = $n;
        }
    }
    if (function_exists('ensurePolicyTables')) {
        ensurePolicyTables();
        foreach (fetchUnreadPolicyNotifications($uid, 8) as $n) {
            $n['_type'] = 'policy';
            $n['_date'] = $n['policy_date'] ?? '';
            $n['_dept_label'] = policyDepartmentsLabel($n);
            $n['_url'] = app_url('policies/mark_read.php?id=' . (int) $n['id'] . '&go=view');
            $n['_icon'] = 'fa-scroll';
            $n['_label'] = 'Policy';
            $headerNotifyItems[] = $n;
        }
    }
    usort($headerNotifyItems, static function ($a, $b) {
        return strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? ''));
    });
    $headerNotifyItems = array_slice($headerNotifyItems, 0, 12);
    $headerNotifyUnread = count($headerNotifyItems);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle . ' - ' : ''; ?><?php echo htmlspecialchars($companyName); ?> HRMS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo app_url('assets/css/style.css'); ?>?v=<?php echo (int) @filemtime(__DIR__ . '/../assets/css/style.css'); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <?php if (!empty($extraCss) && is_array($extraCss)): ?>
        <?php foreach ($extraCss as $css): ?>
            <link rel="stylesheet" href="<?php echo htmlspecialchars((strpos($css, 'http') === 0) ? $css : app_url($css)); ?>">
        <?php endforeach; ?>
    <?php endif; ?>
</head>
<body<?php echo !empty($useSidebar) ? ' class="has-app-sidebar"' : ''; ?>>
    <!-- Top Header Bar -->
    <header class="top-header anim-header">
        <div class="header-left">
            <?php if (!empty($useSidebar)): ?>
                <button type="button" class="header-sidebar-btn" id="headerSidebarBtn" title="Toggle sidebar" aria-label="Toggle sidebar">
                    <i class="fa-solid fa-bars"></i>
                </button>
            <?php endif; ?>
            <a href="<?php echo app_url('dashboard.php'); ?>" class="brand">
                <img src="<?php echo htmlspecialchars($logoSrc); ?>"
                     alt="<?php echo htmlspecialchars($companyName); ?>"
                     class="brand-logo"
                     onerror="this.src='<?php echo app_url('assets/images/logo-placeholder.svg'); ?>'">
                <span class="brand-text"><?php echo htmlspecialchars($companyName); ?></span>
            </a>
        </div>
        <div class="header-right">
            <div class="header-status" id="headerStatus" aria-live="polite">
                <div class="header-status-item">
                    <i class="fa-regular fa-clock" aria-hidden="true"></i>
                    <span class="header-status-line">
                        Time : <strong class="header-clock" id="headerClock">--:--:-- --</strong>
                    </span>
                </div>
                <div class="header-status-divider" aria-hidden="true"></div>
                <div class="header-status-item">
                    <i class="fa-regular fa-calendar" aria-hidden="true"></i>
                    <span class="header-status-line">
                        Day : <strong class="header-day" id="headerDay"><?php echo htmlspecialchars(date('l')); ?></strong>
                    </span>
                </div>
                <div class="header-status-divider" aria-hidden="true"></div>
                <div class="header-status-item">
                    <i class="fa-solid fa-user-check" aria-hidden="true"></i>
                    <span class="header-status-line">
                        Logged in as :
                        <strong class="header-login-user"><?php echo htmlspecialchars(getUserName()); ?></strong>
                        <?php if (!empty($_SESSION['username'])): ?>
                            <em class="header-login-handle">(@<?php echo htmlspecialchars((string) $_SESSION['username']); ?>)</em>
                        <?php endif; ?>
                    </span>
                </div>
            </div>

            <div class="header-notify" id="headerNotify">
                <button type="button"
                        class="header-bell-btn"
                        id="headerBellBtn"
                        aria-haspopup="true"
                        aria-expanded="false"
                        aria-controls="headerNotifyPanel"
                        title="Notifications">
                    <i class="fa-solid fa-bell"></i>
                    <?php if ($headerNotifyUnread > 0): ?>
                        <span class="header-bell-badge"><?php echo $headerNotifyUnread > 9 ? '9+' : (int) $headerNotifyUnread; ?></span>
                    <?php endif; ?>
                </button>
                <div class="header-notify-panel" id="headerNotifyPanel" hidden>
                    <div class="header-notify-head">
                        <strong>Notifications</strong>
                        <?php if ($headerNotifyUnread > 0): ?>
                            <span class="header-notify-head-links">
                                <a href="<?php echo app_url('circulars/mark_read.php?all=1'); ?>">Read circulars</a>
                                ·
                                <a href="<?php echo app_url('policies/mark_read.php?all=1'); ?>">Read policies</a>
                            </span>
                        <?php endif; ?>
                    </div>
                    <div class="header-notify-body">
                        <?php if (!$headerNotifyItems): ?>
                            <div class="header-notify-empty">No new circulars or policies</div>
                        <?php else: ?>
                            <?php foreach ($headerNotifyItems as $n): ?>
                                <a class="header-notify-item" href="<?php echo htmlspecialchars($n['_url']); ?>">
                                    <span class="header-notify-ico"><i class="fa-solid <?php echo htmlspecialchars($n['_icon']); ?>"></i></span>
                                    <span class="header-notify-meta">
                                        <strong><?php echo htmlspecialchars($n['title']); ?></strong>
                                        <small>
                                            <?php echo htmlspecialchars($n['_label']); ?>
                                            · <?php echo htmlspecialchars(formatDateDisplay($n['_date'] ?? '')); ?>
                                            · <?php echo htmlspecialchars($n['_dept_label'] ?? ''); ?>
                                        </small>
                                    </span>
                                </a>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <div class="header-notify-foot">
                        <a href="<?php echo app_url('circulars/index.php'); ?>">Circulars</a>
                        <span aria-hidden="true">·</span>
                        <a href="<?php echo app_url('policies/index.php'); ?>">Policies</a>
                    </div>
                </div>
            </div>

            <div class="user-info">
                <?php if ($headerEmpProfileUrl !== ''): ?>
                <a href="<?php echo htmlspecialchars($headerEmpProfileUrl); ?>"
                   class="user-avatar<?php echo $headerEmpPhotoUrl !== '' ? ' has-photo' : ''; ?>"
                   title="<?php echo htmlspecialchars(getUserName() . ' · My Profile'); ?>">
                    <?php if ($headerEmpPhotoUrl !== ''): ?>
                        <img src="<?php echo htmlspecialchars($headerEmpPhotoUrl); ?>" alt="">
                    <?php else: ?>
                        <i class="fa-solid fa-user"></i>
                    <?php endif; ?>
                </a>
                <?php else: ?>
                <div class="user-avatar" title="<?php echo htmlspecialchars(getUserName() . ' · ' . getUserRoleLabel()); ?>">
                    <i class="fa-solid fa-user"></i>
                </div>
                <?php endif; ?>
                <a href="<?php echo app_url('logout.php'); ?>" class="logout-btn" title="Logout">
                    <i class="fa-solid fa-right-from-bracket"></i>
                </a>
            </div>
        </div>
    </header>
<?php if (!empty($useSidebar)): ?>
    <div class="app-shell" id="appShell">
        <?php require_once __DIR__ . '/sidebar.php'; ?>
        <div class="app-content">
<?php endif; ?>
