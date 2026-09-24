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
            <div class="user-info">
                <div class="user-avatar" title="<?php echo htmlspecialchars(getUserName() . ' · ' . getUserRoleLabel()); ?>">
                    <i class="fa-solid fa-user"></i>
                </div>
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
