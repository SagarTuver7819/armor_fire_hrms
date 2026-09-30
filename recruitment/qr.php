<?php
/**
 * Recruitment — Fixed QR Code (print / share poster)
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permission_helper.php';
require_once __DIR__ . '/../includes/settings.php';
require_once __DIR__ . '/../includes/recruitment_helper.php';

requireLogin();
if (!isAdmin() && !isHR() && !(function_exists('isStaffUser') && isStaffUser()) && !canAccess('recruitment', 'view')) {
    header('Location: ' . app_url('dashboard.php'));
    exit;
}

ensureRecruitmentTables();
$applyUrl = recruitmentApplyUrl();
$companyName = function_exists('getCompanyName') ? getCompanyName() : 'Armor Fire';

$logoPath = function_exists('getLoginLogo') ? getLoginLogo() : '';
if (!$logoPath && function_exists('getCompanyLogo')) {
    $logoPath = getCompanyLogo();
}
if (!$logoPath && function_exists('getDashboardLogo')) {
    $logoPath = getDashboardLogo();
}
$logoSrc = $logoPath;
if ($logoSrc && strpos($logoSrc, 'http') !== 0 && strpos($logoSrc, '/') !== 0) {
    $logoSrc = app_url($logoSrc);
}
if (!$logoSrc) {
    $logoSrc = app_url('assets/images/logo-placeholder.svg');
}

$pageTitle = 'Recruitment QR';
$useSidebar = true;
$sidebarMode = 'recruitment';
$sidebarActive = 'recruitment_qr';

require_once __DIR__ . '/../includes/header.php';
?>

<main class="dashboard-main">
    <div class="page-toolbar flex-between">
        <a href="<?php echo app_url('recruitment/index.php'); ?>" class="back-link">
            <i class="fa-solid fa-arrow-left"></i> Back to Applications
        </a>
        <div class="toolbar-actions" style="display:flex;gap:8px;flex-wrap:wrap;">
            <button type="button" class="btn-secondary" id="recCopyUrl">
                <i class="fa-solid fa-copy"></i> Copy Link
            </button>
            <a href="<?php echo app_url('recruitment/qr_print.php'); ?>" class="btn-primary" target="_blank" rel="noopener">
                <i class="fa-solid fa-print"></i> Print / Download PDF
            </a>
        </div>
    </div>

    <div class="form-page-card" style="max-width:760px;margin-bottom:16px;">
        <div class="form-page-header">
            <h1>Fixed Career QR Code</h1>
            <p>Click <strong>Print / Download PDF</strong> — opens clean white A4 page. Then use browser Print → Save as PDF.</p>
        </div>
    </div>

    <div class="rec-qr-poster-wrap">
        <article class="rec-qr-poster">
            <header class="rec-qr-poster-top">
                <img src="<?php echo htmlspecialchars($logoSrc); ?>"
                     alt="<?php echo htmlspecialchars($companyName); ?>"
                     class="rec-qr-poster-logo"
                     onerror="this.src='<?php echo app_url('assets/images/logo-placeholder.svg'); ?>'">
                <div class="rec-qr-poster-brand">
                    <strong><?php echo htmlspecialchars(strtoupper($companyName)); ?></strong>
                    <span>CAREERS · SCAN TO APPLY</span>
                </div>
            </header>

            <div class="rec-qr-poster-title">Scan to Apply</div>
            <p class="rec-qr-poster-sub">Open camera / QR scanner → fill career application form</p>

            <div class="rec-qr-poster-frame">
                <div id="recQrCode" class="rec-qr-canvas" aria-label="Career application QR code"></div>
            </div>

            <p class="rec-qr-poster-url"><?php echo htmlspecialchars($applyUrl); ?></p>
            <p class="rec-qr-poster-note">Fixed QR · reprint anytime · link stays same</p>
        </article>
    </div>
</main>

<style>
.rec-qr-poster-wrap {
    display: flex;
    justify-content: center;
    padding: 8px 12px 28px;
    background: transparent;
}
.rec-qr-poster {
    width: min(520px, 100%);
    background: #ffffff !important;
    border: 2px solid #111;
    border-radius: 16px;
    padding: 28px 24px 22px;
    text-align: center;
    box-shadow: 0 10px 28px rgba(15, 23, 42, 0.08);
    color: #0f172a;
}
.rec-qr-poster-top {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 14px;
    margin-bottom: 18px;
    padding-bottom: 14px;
    border-bottom: 3px solid #d2232a;
    background: #ffffff !important;
}
.rec-qr-poster-logo {
    width: 64px;
    height: 64px;
    object-fit: contain;
    flex-shrink: 0;
    background: #ffffff !important;
}
.rec-qr-poster-brand { text-align: left; min-width: 0; }
.rec-qr-poster-brand strong {
    display: block;
    font-size: 15px;
    font-weight: 800;
    color: #b91c1c !important;
    line-height: 1.25;
}
.rec-qr-poster-brand span {
    display: block;
    margin-top: 3px;
    font-size: 11px;
    font-weight: 800;
    color: #64748b !important;
    letter-spacing: .08em;
}
.rec-qr-poster-title {
    font-size: 28px;
    font-weight: 800;
    color: #0f172a !important;
    letter-spacing: .04em;
    text-transform: uppercase;
    margin: 4px 0 6px;
}
.rec-qr-poster-sub {
    margin: 0 0 18px;
    font-size: 14px;
    font-weight: 600;
    color: #334155 !important;
}
.rec-qr-poster-frame {
    display: inline-flex;
    padding: 16px;
    background: #ffffff !important;
    border: 2px solid #cbd5e1;
    border-radius: 12px;
    margin: 0 auto 16px;
}
.rec-qr-canvas {
    display: inline-flex;
    line-height: 0;
    background: #ffffff !important;
}
.rec-qr-canvas img,
.rec-qr-canvas canvas {
    display: block;
    width: 280px !important;
    height: 280px !important;
    max-width: 100%;
    background: #ffffff !important;
}
.rec-qr-canvas table { border-collapse: collapse; margin: 0 auto; background: #fff !important; }
.rec-qr-canvas table td { border: 0 !important; padding: 0 !important; }
.rec-qr-poster-url {
    margin: 0 auto 8px;
    max-width: 440px;
    font-size: 13px;
    font-weight: 700;
    color: #0f172a !important;
    word-break: break-all;
    line-height: 1.4;
}
.rec-qr-poster-note {
    margin: 0;
    font-size: 12px;
    font-weight: 600;
    color: #64748b !important;
}
</style>

<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
(function () {
    var url = <?php echo json_encode($applyUrl); ?>;
    var el = document.getElementById('recQrCode');
    if (el && window.QRCode) {
        new QRCode(el, {
            text: url,
            width: 320,
            height: 320,
            colorDark: '#000000',
            colorLight: '#ffffff',
            correctLevel: QRCode.CorrectLevel.H
        });
    }
    var copyBtn = document.getElementById('recCopyUrl');
    if (copyBtn) {
        copyBtn.addEventListener('click', function () {
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(url).then(function () {
                    copyBtn.innerHTML = '<i class="fa-solid fa-check"></i> Copied';
                    setTimeout(function () {
                        copyBtn.innerHTML = '<i class="fa-solid fa-copy"></i> Copy Link';
                    }, 1600);
                });
            } else {
                prompt('Copy this link:', url);
            }
        });
    }
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
