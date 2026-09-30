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
    <div class="page-toolbar flex-between no-print">
        <a href="<?php echo app_url('recruitment/index.php'); ?>" class="back-link">
            <i class="fa-solid fa-arrow-left"></i> Back to Applications
        </a>
        <div class="toolbar-actions" style="display:flex;gap:8px;flex-wrap:wrap;">
            <button type="button" class="btn-secondary" id="recCopyUrl">
                <i class="fa-solid fa-copy"></i> Copy Link
            </button>
            <button type="button" class="btn-primary" id="recPrintQr">
                <i class="fa-solid fa-print"></i> Print / Download PDF
            </button>
        </div>
    </div>

    <div class="form-page-card rec-qr-screen no-print" style="max-width:760px;">
        <div class="form-page-header">
            <h1>Fixed Career QR Code</h1>
            <p>Print / Save as PDF — large clear QR for notice board or WhatsApp share.</p>
        </div>
    </div>

    <!-- Print + on-screen poster -->
    <div class="rec-qr-poster-wrap" id="recQrPrintArea">
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

            <p class="rec-qr-poster-url" id="recQrUrlText"><?php echo htmlspecialchars($applyUrl); ?></p>
            <p class="rec-qr-poster-note">Fixed QR · reprint anytime · link stays same</p>
        </article>
    </div>
</main>

<style>
.rec-qr-poster-wrap {
    display: flex;
    justify-content: center;
    padding: 8px 12px 28px;
}
.rec-qr-poster {
    width: min(520px, 100%);
    background: #fff;
    border: 2px solid #111;
    border-radius: 16px;
    padding: 28px 24px 22px;
    text-align: center;
    box-shadow: 0 10px 28px rgba(15, 23, 42, 0.08);
}
.rec-qr-poster-top {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 14px;
    margin-bottom: 18px;
    padding-bottom: 14px;
    border-bottom: 3px solid #d2232a;
}
.rec-qr-poster-logo {
    width: 64px;
    height: 64px;
    object-fit: contain;
    flex-shrink: 0;
    background: #fff;
}
.rec-qr-poster-brand {
    text-align: left;
    min-width: 0;
}
.rec-qr-poster-brand strong {
    display: block;
    font-size: 15px;
    font-weight: 800;
    color: #b91c1c;
    line-height: 1.25;
    letter-spacing: .02em;
}
.rec-qr-poster-brand span {
    display: block;
    margin-top: 3px;
    font-size: 11px;
    font-weight: 800;
    color: #64748b;
    letter-spacing: .08em;
}
.rec-qr-poster-title {
    font-size: 28px;
    font-weight: 800;
    color: #0f172a;
    letter-spacing: .04em;
    text-transform: uppercase;
    margin: 4px 0 6px;
}
.rec-qr-poster-sub {
    margin: 0 0 18px;
    font-size: 14px;
    font-weight: 600;
    color: #475569;
}
.rec-qr-poster-frame {
    display: inline-flex;
    padding: 18px;
    background: #fff;
    border: 2px solid #e2e8f0;
    border-radius: 14px;
    margin: 0 auto 16px;
    box-shadow: inset 0 0 0 6px #f8fafc;
}
.rec-qr-canvas {
    display: inline-flex;
    line-height: 0;
}
.rec-qr-canvas img,
.rec-qr-canvas canvas {
    display: block;
    width: 280px !important;
    height: 280px !important;
    max-width: 100%;
}
.rec-qr-poster-url {
    margin: 0 auto 8px;
    max-width: 440px;
    font-size: 13px;
    font-weight: 700;
    color: #0f172a;
    word-break: break-all;
    line-height: 1.4;
}
.rec-qr-poster-note {
    margin: 0;
    font-size: 12px;
    font-weight: 600;
    color: #64748b;
}

@media print {
    @page {
        size: A4 portrait;
        margin: 12mm;
    }
    .no-print,
    .app-sidebar,
    .sidebar-fab,
    .top-header,
    .page-footer,
    .page-toolbar,
    .form-page-card.rec-qr-screen,
    .confirm-modal {
        display: none !important;
    }
    html, body {
        background: #fff !important;
        margin: 0 !important;
        padding: 0 !important;
        width: 100% !important;
        height: auto !important;
    }
    .app-shell,
    .app-content,
    .dashboard-main {
        display: block !important;
        width: 100% !important;
        margin: 0 !important;
        padding: 0 !important;
        background: #fff !important;
        min-height: 0 !important;
        box-shadow: none !important;
    }
    .rec-qr-poster-wrap {
        display: flex !important;
        align-items: center;
        justify-content: center;
        min-height: 260mm;
        padding: 0 !important;
        margin: 0 !important;
    }
    .rec-qr-poster {
        width: 170mm;
        max-width: 170mm;
        margin: 0 auto;
        padding: 14mm 12mm 12mm;
        border: 2.5px solid #111;
        border-radius: 0;
        box-shadow: none;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
    .rec-qr-poster-top {
        margin-bottom: 10mm;
        padding-bottom: 6mm;
        border-bottom-width: 3px;
        gap: 5mm;
    }
    .rec-qr-poster-logo {
        width: 22mm;
        height: 22mm;
    }
    .rec-qr-poster-brand strong {
        font-size: 16pt;
        color: #b91c1c !important;
    }
    .rec-qr-poster-brand span {
        font-size: 10pt;
        color: #475569 !important;
    }
    .rec-qr-poster-title {
        font-size: 26pt;
        margin: 2mm 0 3mm;
    }
    .rec-qr-poster-sub {
        font-size: 12pt;
        margin-bottom: 8mm;
        color: #334155 !important;
    }
    .rec-qr-poster-frame {
        padding: 6mm;
        margin-bottom: 7mm;
        border: 2px solid #cbd5e1;
        box-shadow: none;
    }
    .rec-qr-canvas img,
    .rec-qr-canvas canvas {
        width: 95mm !important;
        height: 95mm !important;
    }
    .rec-qr-poster-url {
        font-size: 11pt;
        max-width: 150mm;
        margin-bottom: 4mm;
        color: #0f172a !important;
    }
    .rec-qr-poster-note {
        font-size: 10pt;
        color: #64748b !important;
    }
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
    var printBtn = document.getElementById('recPrintQr');
    if (printBtn) {
        printBtn.addEventListener('click', function () { window.print(); });
    }
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
