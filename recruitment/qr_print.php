<?php
/**
 * Recruitment QR — clean A4 print / Save as PDF (no app chrome)
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

$h = static function ($s) {
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Career QR · <?php echo $h($companyName); ?></title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        html, body {
            background: #ffffff !important;
            color: #111;
            font-family: Calibri, Candara, Segoe UI, Arial, Helvetica, sans-serif;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        .bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
            padding: 12px 16px;
            background: #111;
            color: #fff;
        }
        .bar a, .bar button {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #333;
            color: #fff;
            border: 0;
            padding: 8px 14px;
            border-radius: 8px;
            font-weight: 700;
            cursor: pointer;
            text-decoration: none;
            font-family: inherit;
            font-size: 13px;
        }
        .bar button.primary { background: #d2232a; }
        .sheet {
            min-height: calc(100vh - 56px);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px 16px;
            background: #ffffff !important;
        }
        .card {
            width: min(170mm, 100%);
            background: #ffffff !important;
            border: 2.5px solid #111;
            padding: 28px 24px 22px;
            text-align: center;
        }
        .top {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 14px;
            padding-bottom: 14px;
            margin-bottom: 18px;
            border-bottom: 3px solid #d2232a;
            background: #ffffff !important;
        }
        .logo {
            width: 64px;
            height: 64px;
            object-fit: contain;
            background: #ffffff !important;
        }
        .brand { text-align: left; }
        .brand strong {
            display: block;
            font-size: 16px;
            font-weight: 800;
            color: #b91c1c;
            line-height: 1.2;
        }
        .brand span {
            display: block;
            margin-top: 4px;
            font-size: 11px;
            font-weight: 800;
            color: #475569;
            letter-spacing: .06em;
        }
        h1 {
            font-size: 28px;
            font-weight: 800;
            letter-spacing: .04em;
            text-transform: uppercase;
            margin: 0 0 8px;
            color: #0f172a;
        }
        .sub {
            font-size: 14px;
            font-weight: 600;
            color: #334155;
            margin: 0 0 18px;
        }
        .frame {
            display: inline-block;
            padding: 16px;
            border: 2px solid #cbd5e1;
            background: #ffffff !important;
            margin: 0 auto 16px;
        }
        #qr {
            display: inline-flex;
            line-height: 0;
            background: #ffffff !important;
        }
        #qr img, #qr canvas {
            display: block;
            width: 320px !important;
            height: 320px !important;
            background: #ffffff !important;
        }
        #qr table { border-collapse: collapse; margin: 0 auto; background: #fff !important; }
        #qr table td { border: 0 !important; padding: 0 !important; }
        .url {
            font-size: 13px;
            font-weight: 700;
            color: #0f172a;
            word-break: break-all;
            max-width: 480px;
            margin: 0 auto 8px;
            line-height: 1.4;
        }
        .note {
            font-size: 12px;
            font-weight: 600;
            color: #64748b;
        }
        @media print {
            @page { size: A4 portrait; margin: 12mm; }
            .bar { display: none !important; }
            html, body, .sheet, .card, .top, .frame, .logo, #qr, #qr img, #qr canvas {
                background: #ffffff !important;
            }
            .sheet {
                min-height: 0;
                padding: 0;
                align-items: center;
                justify-content: center;
            }
            .card {
                width: 170mm;
                padding: 12mm 10mm 10mm;
                box-shadow: none;
            }
            .logo { width: 22mm; height: 22mm; }
            .brand strong { font-size: 16pt; }
            .brand span { font-size: 10pt; }
            h1 { font-size: 24pt; margin-bottom: 3mm; }
            .sub { font-size: 11pt; margin-bottom: 8mm; }
            .frame { padding: 5mm; margin-bottom: 6mm; }
            #qr img, #qr canvas {
                width: 95mm !important;
                height: 95mm !important;
            }
            .url { font-size: 11pt; max-width: 150mm; }
            .note { font-size: 10pt; }
        }
    </style>
</head>
<body>
<div class="bar no-print">
    <a href="<?php echo $h(app_url('recruitment/qr.php')); ?>">← Back</a>
    <button type="button" class="primary" id="btnPrint">Print / Save as PDF</button>
</div>

<div class="sheet">
    <div class="card">
        <div class="top">
            <img class="logo" src="<?php echo $h($logoSrc); ?>" alt=""
                 onerror="this.src='<?php echo $h(app_url('assets/images/logo-placeholder.svg')); ?>'">
            <div class="brand">
                <strong><?php echo $h(strtoupper($companyName)); ?></strong>
                <span>CAREERS · SCAN TO APPLY</span>
            </div>
        </div>
        <h1>Scan to Apply</h1>
        <p class="sub">Open camera / QR scanner → fill career application form</p>
        <div class="frame">
            <div id="qr"></div>
        </div>
        <p class="url"><?php echo $h($applyUrl); ?></p>
        <p class="note">Fixed QR · reprint anytime · link stays same</p>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
(function () {
    var url = <?php echo json_encode($applyUrl); ?>;
    var el = document.getElementById('qr');
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
    var btn = document.getElementById('btnPrint');
    if (btn) {
        btn.addEventListener('click', function () {
            window.print();
        });
    }
    if (location.search.indexOf('autoprint=1') !== -1) {
        window.addEventListener('load', function () {
            setTimeout(function () { window.print(); }, 400);
        });
    }
})();
</script>
</body>
</html>
