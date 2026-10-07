<?php
/**
 * Canteen QR — clean A4 poster (Print / Save as PDF) linking to canteen/order.php
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permission_helper.php';
require_once __DIR__ . '/../includes/department_head_helper.php';
require_once __DIR__ . '/../includes/settings.php';
require_once __DIR__ . '/../includes/canteen_helper.php';

requireLogin();
$canView = isAdmin() || isHR() || (canAccess('employees', 'view') && !(function_exists('isOfficeStaffRole') && isOfficeStaffRole()));
if (!$canView) {
    header('Location: ' . app_url('dashboard.php'));
    exit;
}

$orderUrl = canteenOrderUrl();
$cutoffLabel = canteenCutoffLabel();
$companyName = function_exists('getCompanyName') ? getCompanyName() : 'Armor Fire';
$logoPath = function_exists('getLoginLogo') ? getLoginLogo() : '';
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
    <title>Canteen QR · <?php echo $h($companyName); ?></title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        html, body {
            background: #fff; color: #111; font-family: Calibri, Candara, Segoe UI, Arial, Helvetica, sans-serif;
            -webkit-print-color-adjust: exact; print-color-adjust: exact;
        }
        .bar { display: flex; justify-content: space-between; align-items: center; gap: 10px; padding: 12px 16px; background: #111; }
        .bar a, .bar button {
            display: inline-flex; align-items: center; gap: 6px; background: #333; color: #fff; border: 0;
            padding: 8px 14px; border-radius: 8px; font-weight: 700; cursor: pointer; text-decoration: none; font-family: inherit; font-size: 13px;
        }
        .bar button.primary { background: #d2232a; }
        .bar .grp { display: flex; gap: 8px; }
        .sheet { min-height: calc(100vh - 56px); display: flex; align-items: center; justify-content: center; padding: 24px 16px; }
        .card { width: min(170mm, 100%); border: 2.5px solid #111; padding: 28px 24px 22px; text-align: center; background: #fff; }
        .top { display: flex; align-items: center; justify-content: center; gap: 14px; padding-bottom: 14px; margin-bottom: 18px; border-bottom: 3px solid #d2232a; }
        .logo { width: 64px; height: 64px; object-fit: contain; }
        .brand { text-align: left; }
        .brand strong { display: block; font-size: 16px; font-weight: 800; color: #b91c1c; line-height: 1.2; }
        .brand span { display: block; margin-top: 4px; font-size: 11px; font-weight: 800; color: #475569; letter-spacing: .06em; }
        h1 { font-size: 30px; font-weight: 800; letter-spacing: .04em; text-transform: uppercase; margin-bottom: 6px; color: #0f172a; }
        .sub { font-size: 15px; font-weight: 700; color: #334155; margin-bottom: 4px; }
        .meals { display: flex; justify-content: center; gap: 10px; margin: 12px 0 16px; flex-wrap: wrap; }
        .meals span { padding: 6px 14px; border-radius: 999px; font-weight: 800; font-size: 14px; border: 1.5px solid; }
        .m1 { color: #b45309; border-color: #fcd34d; background: #fffbeb; }
        .m2 { color: #15803d; border-color: #86efac; background: #f0fdf4; }
        .m3 { color: #4338ca; border-color: #a5b4fc; background: #eef2ff; }
        .frame { display: inline-block; padding: 16px; border: 2px solid #cbd5e1; margin: 0 auto 14px; }
        #qr { display: inline-flex; line-height: 0; }
        #qr img, #qr canvas { display: block; width: 300px !important; height: 300px !important; }
        .cut {
            display: inline-block; margin-bottom: 10px; padding: 8px 16px; border-radius: 10px;
            background: #d2232a; color: #fff; font-weight: 800; font-size: 15px;
        }
        .url { font-size: 12px; font-weight: 700; color: #0f172a; word-break: break-all; max-width: 480px; margin: 0 auto 6px; }
        .note { font-size: 12px; font-weight: 600; color: #64748b; }
        @media print {
            @page { size: A4 portrait; margin: 12mm; }
            .bar { display: none !important; }
            .sheet { min-height: 0; padding: 0; }
            .card { width: 170mm; padding: 12mm 10mm 10mm; }
            .logo { width: 22mm; height: 22mm; }
            h1 { font-size: 26pt; }
            .sub { font-size: 13pt; }
            #qr img, #qr canvas { width: 95mm !important; height: 95mm !important; }
        }
    </style>
</head>
<body>
<div class="bar">
    <a href="<?php echo $h(app_url('canteen/index.php')); ?>">← Back to Meal List</a>
    <div class="grp">
        <button type="button" id="btnCopy">Copy Link</button>
        <button type="button" class="primary" onclick="window.print()">Print / Save as PDF</button>
    </div>
</div>

<div class="sheet">
    <div class="card">
        <div class="top">
            <img class="logo" src="<?php echo $h($logoSrc); ?>" alt="" onerror="this.src='<?php echo $h(app_url('assets/images/logo-placeholder.svg')); ?>'">
            <div class="brand">
                <strong><?php echo $h(strtoupper($companyName)); ?></strong>
                <span>CANTEEN · MEAL BOOKING</span>
            </div>
        </div>
        <h1>Scan for Canteen</h1>
        <p class="sub">Book tomorrow's meal from your mobile</p>
        <div class="meals">
            <span class="m1">Breakfast</span>
            <span class="m2">Lunch</span>
            <span class="m3">Dinner</span>
        </div>
        <div class="frame"><div id="qr"></div></div>
        <div><span class="cut">Book daily before <?php echo $h($cutoffLabel); ?> for next day</span></div>
        <p class="url"><?php echo $h($orderUrl); ?></p>
        <p class="note">Scan → Select Department → Select your name / code → Tick meals → Submit</p>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
(function () {
    var url = <?php echo json_encode($orderUrl); ?>;
    var el = document.getElementById('qr');
    if (el && window.QRCode) {
        new QRCode(el, { text: url, width: 320, height: 320, colorDark: '#000000', colorLight: '#ffffff', correctLevel: QRCode.CorrectLevel.H });
    }
    var btn = document.getElementById('btnCopy');
    btn.addEventListener('click', function () {
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(url).then(function () {
                btn.textContent = 'Copied ✓';
                setTimeout(function () { btn.textContent = 'Copy Link'; }, 1600);
            });
        } else {
            prompt('Copy this link:', url);
        }
    });
})();
</script>
</body>
</html>
