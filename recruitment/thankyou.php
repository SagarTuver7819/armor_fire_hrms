<?php
/**
 * Application submitted — thank you
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/settings.php';

$appNo = trim((string) ($_GET['no'] ?? ''));
$companyName = getCompanyName();
$logo = getLoginLogo();
$logoSrc = $logo;
if ($logoSrc && strpos($logoSrc, 'http') !== 0 && strpos($logoSrc, '/') !== 0) {
    $logoSrc = app_url($logoSrc);
}
if (!$logoSrc) {
    $logoSrc = app_url('assets/images/logo-placeholder.svg');
}
$cssV = (int) @filemtime(__DIR__ . '/../assets/css/recruitment_apply.css');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>Application Submitted</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="<?php echo app_url('assets/css/recruitment_apply.css'); ?>?v=<?php echo $cssV; ?>">
</head>
<body class="rec-page">
<div class="rec-bg" aria-hidden="true">
    <div class="rec-bg-glow rec-bg-a"></div>
    <div class="rec-bg-glow rec-bg-b"></div>
</div>
<main class="rec-wrap rec-thanks-wrap">
    <div class="rec-thanks">
        <img src="<?php echo htmlspecialchars($logoSrc); ?>" alt="" class="rec-thanks-logo"
             onerror="this.src='<?php echo app_url('assets/images/logo-placeholder.svg'); ?>'">
        <div class="rec-thanks-ico"><i class="fa-solid fa-circle-check"></i></div>
        <h1>Application Submitted</h1>
        <p>Thank you for applying to <strong><?php echo htmlspecialchars($companyName); ?></strong>.</p>
        <?php if ($appNo !== ''): ?>
            <p class="rec-app-no">Application No · <strong><?php echo htmlspecialchars($appNo); ?></strong></p>
        <?php endif; ?>
        <p class="rec-muted">Our HR team will review your application and contact you if shortlisted. Please save your application number for reference.</p>
        <a href="<?php echo app_url('recruitment/apply.php'); ?>" class="rec-btn rec-btn-primary">Submit Another Application</a>
    </div>
</main>
</body>
</html>
