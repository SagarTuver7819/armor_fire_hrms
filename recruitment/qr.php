<?php
/**
 * Recruitment — Fixed QR Code (print / share)
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permission_helper.php';
require_once __DIR__ . '/../includes/recruitment_helper.php';

requireLogin();
if (!isAdmin() && !isHR() && !(function_exists('isStaffUser') && isStaffUser()) && !canAccess('recruitment', 'view')) {
    header('Location: ' . app_url('dashboard.php'));
    exit;
}

ensureRecruitmentTables();
$applyUrl = recruitmentApplyUrl();

$pageTitle = 'Recruitment QR';
$useSidebar = true;
$sidebarActive = 'recruitment_qr';
$extraCss = [];

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
            <button type="button" class="btn-primary" id="recPrintQr">
                <i class="fa-solid fa-print"></i> Print QR
            </button>
        </div>
    </div>

    <div class="form-page-card" style="max-width:720px;">
        <div class="form-page-header">
            <h1>Fixed Career QR Code</h1>
            <p>Scan with phone camera or any QR scanner — opens the career application form (no login).</p>
        </div>

        <div class="rec-qr-box" id="recQrPrintArea">
            <div class="rec-qr-brand">
                <?php
                $logoSrc = $logoSrc ?? app_url('assets/images/logo-placeholder.svg');
                ?>
                <img src="<?php echo htmlspecialchars($logoSrc); ?>" alt="" class="rec-qr-logo"
                     onerror="this.style.display='none'">
                <div>
                    <strong><?php echo htmlspecialchars(getCompanyName()); ?></strong>
                    <span>Scan to Apply</span>
                </div>
            </div>
            <div id="recQrCode" class="rec-qr-canvas"></div>
            <p class="rec-qr-url" id="recQrUrlText"><?php echo htmlspecialchars($applyUrl); ?></p>
            <p class="rec-qr-note">This QR is fixed — reprint anytime. Link never changes unless app URL changes.</p>
        </div>
    </div>
</main>

<style>
.rec-qr-box {
    text-align: center;
    padding: 24px 16px;
    border: 1px dashed #e2e8f0;
    border-radius: 14px;
    background: #fafafa;
}
.rec-qr-brand {
    display: flex; align-items: center; justify-content: center; gap: 12px;
    margin-bottom: 16px;
}
.rec-qr-logo {
    width: 52px; height: 52px; object-fit: contain;
    background: #fff; border-radius: 10px; border: 1px solid #e5e7eb; padding: 4px;
}
.rec-qr-brand strong { display: block; font-size: 16px; }
.rec-qr-brand span { font-size: 12px; color: #64748b; font-weight: 700; }
.rec-qr-canvas {
    display: inline-flex; padding: 14px; background: #fff;
    border-radius: 12px; border: 1px solid #e5e7eb;
    margin-bottom: 12px;
}
.rec-qr-canvas img, .rec-qr-canvas canvas { display: block; }
.rec-qr-url {
    font-size: 12px; word-break: break-all; color: #334155; font-weight: 600;
    max-width: 520px; margin: 0 auto 8px;
}
.rec-qr-note { font-size: 12px; color: #64748b; margin: 0; }
@media print {
    .sidebar, .top-header, .page-toolbar, .form-page-header { display: none !important; }
    .rec-qr-box { border: 0; background: #fff; }
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
            width: 240,
            height: 240,
            colorDark: '#111111',
            colorLight: '#ffffff',
            correctLevel: QRCode.CorrectLevel.M
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
    var printBtn = document.getElementById('recPrintQr');
    if (printBtn) {
        printBtn.addEventListener('click', function () { window.print(); });
    }
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
