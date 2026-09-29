<?php
/**
 * Visiting Card — Armor Fire brand layout (Front + Back)
 * Single design · Print/PDF + Save Image
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permission_helper.php';
require_once __DIR__ . '/../includes/employee_helper.php';
require_once __DIR__ . '/../includes/settings.php';

requireLogin();

$deptId = isset($_GET['department_id']) ? (int) $_GET['department_id'] : 0;
requireAccess('employees', 'view', $deptId);

$employeeId = isset($_GET['employee_id']) ? (int) $_GET['employee_id'] : 0;

$conn = getDBConnection();
ensureEmployeesTable($conn);

$sql = "SELECT e.id, e.employee_code, e.employee_name, e.designation, e.photo_file,
               e.mobile_number, e.office_mobile, e.office_email, e.department_id,
               d.department_name
        FROM employees e
        LEFT JOIN departments d ON d.id = e.department_id
        WHERE e.status = 1";
$types = '';
$params = [];
if ($deptId > 0) {
    $sql .= ' AND e.department_id = ?';
    $types .= 'i';
    $params[] = $deptId;
}
if ($employeeId > 0) {
    $sql .= ' AND e.id = ?';
    $types .= 'i';
    $params[] = $employeeId;
}
$sql .= ' ORDER BY d.department_name ASC, e.employee_code ASC';

$rows = [];
if ($types !== '') {
    $st = $conn->prepare($sql);
    $st->bind_param($types, ...$params);
    $st->execute();
    $res = $st->get_result();
    while ($r = $res->fetch_assoc()) {
        $rows[] = $r;
    }
    $st->close();
} else {
    $res = $conn->query($sql);
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $rows[] = $r;
        }
    }
}
$conn->close();

if (!$rows) {
    http_response_code(404);
    echo 'No employees found for visiting card.';
    exit;
}

$companyName = trim((string) getCompanyName());
if ($companyName === '' || strcasecmp($companyName, 'Armor Fire') === 0) {
    $companyName = 'Armor Steel Industries Pvt. Ltd.';
}
$companyLegal = 'ARMOR STEEL INDUSTRIES PVT. LTD.';

$logoRel = '';
if (function_exists('getLoginLogo')) {
    $logoRel = (string) getLoginLogo();
}
if (($logoRel === '' || (function_exists('isCustomLogo') && !isCustomLogo($logoRel))) && function_exists('getCompanyLogo')) {
    $logoRel = (string) getCompanyLogo();
}
$logoUrl = ($logoRel !== '' && function_exists('app_url')) ? app_url($logoRel) : '';

$headerDetails = function_exists('getCompanyHeaderDetails') ? trim((string) getCompanyHeaderDetails()) : '';
$companyWebsite = 'www.armorfire.in';
$companyAddress = 'Plot no. 43-45, Lothada, Rajkot, Gujarat - 360022';
if ($headerDetails !== '') {
    if (preg_match('/(?:www\.|https?:\/\/)?([a-z0-9.-]+\.[a-z]{2,})/i', $headerDetails, $m)) {
        $companyWebsite = strtolower($m[0]);
        $companyWebsite = preg_replace('#^https?://#i', '', $companyWebsite);
    }
    $lines = preg_split('/\r\n|\r|\n/', $headerDetails);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line !== '' && (stripos($line, 'plot') !== false || stripos($line, 'rajkot') !== false || stripos($line, 'gujarat') !== false || stripos($line, 'address') !== false)) {
            $companyAddress = preg_replace('/^address\s*:?\s*/i', '', $line);
            break;
        }
    }
}

$backUrl = app_url('employees/visiting_card.php?' . http_build_query([
    'department_id' => $deptId,
    'employee_id' => $employeeId,
    'show' => 1,
]));

function vc_h($v)
{
    return htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');
}

function vc_phone(array $e)
{
    $o = trim((string) ($e['office_mobile'] ?? ''));
    if ($o === '') {
        $o = trim((string) ($e['mobile_number'] ?? ''));
    }
    if ($o === '') {
        return '';
    }
    $digits = preg_replace('/\D+/', '', $o);
    if (strlen($digits) === 10) {
        return '+91 ' . substr($digits, 0, 5) . ' ' . substr($digits, 5);
    }
    if (strpos($o, '+') === 0) {
        return $o;
    }
    return $o;
}

function vc_email(array $e)
{
    return trim((string) ($e['office_email'] ?? ''));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Visiting Card — <?php echo vc_h($companyName); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        :root {
            --fire: #d2232a;
            --fire-dark: #b91c1c;
            --ink: #111111;
            --muted: #64748b;
            --line: #e5e7eb;
            --card-w: 90mm;
            --card-h: 55mm;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: Calibri, Candara, "Segoe UI", Arial, sans-serif;
            background: #eef1f5;
            color: var(--ink);
        }
        .vc-toolbar {
            position: sticky;
            top: 0;
            z-index: 30;
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            padding: 12px 16px;
            background: #fff;
            border-bottom: 1px solid var(--line);
            box-shadow: 0 2px 10px rgba(15, 23, 42, 0.06);
        }
        .vc-toolbar .hint { font-size: 13px; color: var(--muted); font-weight: 600; }
        .vc-toolbar .hint strong { color: var(--ink); }
        .vc-actions { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; }
        .vc-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border: 0;
            border-radius: 10px;
            padding: 10px 14px;
            font-weight: 800;
            font-size: 13px;
            cursor: pointer;
            text-decoration: none;
            font-family: inherit;
        }
        .vc-btn-secondary { background: #f1f5f9; color: var(--ink); }
        .vc-print-group {
            display: inline-flex;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 6px 16px rgba(210, 35, 42, 0.25);
        }
        .vc-print-group .vc-btn {
            border-radius: 0;
            box-shadow: none;
        }
        .vc-print-group .vc-btn + .vc-btn {
            border-left: 1px solid rgba(255,255,255,.28);
        }
        .vc-btn-primary { background: var(--fire); color: #fff; }
        .vc-btn-primary:hover { background: var(--fire-dark); }

        .vc-sheet { max-width: 980px; margin: 22px auto 48px; padding: 0 14px; }
        .vc-pair { margin-bottom: 30px; page-break-inside: avoid; }
        .vc-pair-label {
            font-size: 12px; font-weight: 800; color: var(--muted);
            margin: 0 0 10px; letter-spacing: .04em; text-transform: uppercase;
        }
        .vc-sides { display: flex; gap: 18px; flex-wrap: wrap; align-items: flex-start; }
        .side-tag {
            display: inline-block; font-size: 10px; font-weight: 800; color: var(--muted);
            margin-bottom: 6px; text-transform: uppercase; letter-spacing: .08em;
        }

        .vc-card {
            width: var(--card-w);
            height: var(--card-h);
            border-radius: 5.5mm;
            overflow: hidden;
            background: #fff;
            box-shadow: 0 14px 32px rgba(15, 23, 42, 0.16);
            position: relative;
            border: 0;
        }

        /* FRONT — logo center + company name (matches sample PDF) */
        .vc-card.front {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 6mm 9mm 5.5mm;
            text-align: center;
            background:
                radial-gradient(95% 80% at -5% -10%, rgba(210, 35, 42, 0.22) 0%, rgba(210, 35, 42, 0.08) 35%, rgba(210, 35, 42, 0) 62%),
                #ffffff;
        }
        .vc-card.front .logo-wrap {
            flex: 1 1 auto;
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 0;
            padding: 1mm 1mm 1.5mm;
        }
        .vc-card.front .logo-wrap img {
            max-width: 62%;
            max-height: 30mm;
            object-fit: contain;
            display: block;
        }
        .vc-card.front .logo-fallback {
            font-size: 20px;
            font-weight: 900;
            letter-spacing: .04em;
            color: var(--fire);
            line-height: 1.15;
        }
        .vc-card.front .logo-fallback span { color: #111; }
        .vc-card.front .divider {
            width: 82%;
            height: 1.5px;
            margin: 1.5mm 0 2.8mm;
            background: linear-gradient(90deg, var(--fire) 0%, var(--fire) 48%, #2f2f2f 52%, #2f2f2f 100%);
            flex-shrink: 0;
        }
        .vc-card.front .co-name {
            flex-shrink: 0;
            font-size: 10px;
            font-weight: 800;
            letter-spacing: .1em;
            text-transform: uppercase;
            color: #111;
            line-height: 1.3;
        }

        /* BACK — details left + logo right (matches sample PDF) */
        .vc-card.back {
            display: grid;
            grid-template-columns: 1.25fr 0.9fr;
            background:
                radial-gradient(90% 75% at 110% 115%, rgba(210, 35, 42, 0.2) 0%, rgba(210, 35, 42, 0.07) 38%, rgba(210, 35, 42, 0) 60%),
                #ffffff;
        }
        .vc-card.back .info-col {
            padding: 5.5mm 4mm 5mm 6mm;
            display: flex;
            flex-direction: column;
            justify-content: center;
            gap: 1.2mm;
            min-width: 0;
            position: relative;
        }
        .vc-card.back .info-col::after {
            content: "";
            position: absolute;
            top: 6mm;
            bottom: 6mm;
            right: 0;
            width: 1.4px;
            background: var(--fire);
        }
        .vc-card.back .name {
            font-size: 12.5px;
            font-weight: 900;
            line-height: 1.12;
            color: #111;
            text-transform: uppercase;
            letter-spacing: .03em;
        }
        .vc-card.back .role {
            font-size: 8.5px;
            font-weight: 800;
            color: var(--fire);
            text-transform: uppercase;
            letter-spacing: .1em;
            margin-top: 0.3mm;
        }
        .vc-card.back .mini-line {
            width: 16mm;
            height: 1.35px;
            margin: 1mm 0 1.6mm;
            background: linear-gradient(90deg, var(--fire) 0%, #2f2f2f 100%);
        }
        .vc-card.back .contacts {
            display: flex;
            flex-direction: column;
            gap: 1.8mm;
            margin-top: 0.2mm;
        }
        .vc-card.back .contact-row {
            display: grid;
            grid-template-columns: 4.6mm 1fr;
            gap: 2.2mm;
            align-items: flex-start;
            font-size: 7.4px;
            font-weight: 600;
            color: #1a1a1a;
            line-height: 1.28;
            word-break: break-word;
        }
        .vc-card.back .ico {
            width: 4.6mm;
            height: 4.6mm;
            border-radius: 0.9mm;
            background: var(--fire);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 5.4px;
            flex-shrink: 0;
            margin-top: 0.15mm;
        }
        .vc-card.back .brand-col {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 5mm 4mm;
        }
        .vc-card.back .brand-col img {
            max-width: 82%;
            max-height: 36mm;
            object-fit: contain;
            display: block;
        }
        .vc-card.back .brand-fallback {
            text-align: center;
            font-size: 13px;
            font-weight: 900;
            color: var(--fire);
            letter-spacing: .04em;
            line-height: 1.2;
        }
        .vc-card.back .brand-fallback span { color: #111; display: block; font-size: 11px; }

        @media print {
            body { background: #fff !important; }
            .vc-toolbar, .side-tag, .vc-pair-label { display: none !important; }
            .vc-sheet { margin: 0; padding: 0; max-width: none; }
            .vc-sides { display: block; }
            .vc-pair { margin: 0; }
            .vc-card {
                box-shadow: none !important;
                border: 0 !important;
                border-radius: 0 !important;
                page-break-after: always;
                break-after: page;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .vc-pair:last-child .vc-card.back {
                page-break-after: auto;
                break-after: auto;
            }
        }
        @page { size: 90mm 55mm; margin: 0; }
    </style>
</head>
<body>
    <div class="vc-toolbar no-print">
        <div class="hint">
            <strong><?php echo count($rows); ?></strong> card(s) · Front + Back · Armor Fire design
        </div>
        <div class="vc-actions">
            <a class="vc-btn vc-btn-secondary" href="<?php echo vc_h($backUrl); ?>">← Back</a>
            <div class="vc-print-group" title="Print / Save">
                <button type="button" class="vc-btn vc-btn-primary" onclick="window.print()">
                    <i class="fa-solid fa-print"></i> Print / PDF
                </button>
                <button type="button" class="vc-btn vc-btn-primary" id="vcSaveImageBtn">
                    <i class="fa-solid fa-image"></i> Image
                </button>
            </div>
        </div>
    </div>

    <div class="vc-sheet" id="vcSheet">
        <?php foreach ($rows as $e):
            $phone = vc_phone($e);
            $email = vc_email($e);
            $name = trim((string) ($e['employee_name'] ?? ''));
            $code = trim((string) ($e['employee_code'] ?? ''));
            $desig = trim((string) ($e['designation'] ?? ''));
            $safeFile = preg_replace('/[^a-zA-Z0-9_-]+/', '_', $code !== '' ? $code : ('emp_' . (int) $e['id']));
            ?>
            <div class="vc-pair" data-emp-code="<?php echo vc_h($safeFile); ?>">
                <p class="vc-pair-label"><?php echo vc_h(($code !== '' ? $code . ' — ' : '') . $name); ?></p>
                <div class="vc-sides">
                    <div>
                        <div class="side-tag">Front</div>
                        <div class="vc-card front" data-side="front">
                            <div class="logo-wrap">
                                <?php if ($logoUrl !== ''): ?>
                                    <img src="<?php echo vc_h($logoUrl); ?>" alt="<?php echo vc_h($companyName); ?>">
                                <?php else: ?>
                                    <div class="logo-fallback">ARMOR <span>FIRE</span></div>
                                <?php endif; ?>
                            </div>
                            <div class="divider"></div>
                            <div class="co-name"><?php echo vc_h($companyLegal); ?></div>
                        </div>
                    </div>

                    <div>
                        <div class="side-tag">Back</div>
                        <div class="vc-card back" data-side="back">
                            <div class="info-col">
                                <div class="name"><?php echo vc_h($name !== '' ? $name : '—'); ?></div>
                                <?php if ($desig !== ''): ?>
                                    <div class="role"><?php echo vc_h($desig); ?></div>
                                <?php endif; ?>
                                <div class="mini-line"></div>
                                <div class="contacts">
                                    <?php if ($phone !== ''): ?>
                                    <div class="contact-row">
                                        <span class="ico"><i class="fa-solid fa-phone"></i></span>
                                        <span><?php echo vc_h($phone); ?></span>
                                    </div>
                                    <?php endif; ?>
                                    <?php if ($email !== ''): ?>
                                    <div class="contact-row">
                                        <span class="ico"><i class="fa-solid fa-envelope"></i></span>
                                        <span><?php echo vc_h($email); ?></span>
                                    </div>
                                    <?php endif; ?>
                                    <div class="contact-row">
                                        <span class="ico"><i class="fa-solid fa-globe"></i></span>
                                        <span><?php echo vc_h($companyWebsite); ?></span>
                                    </div>
                                    <div class="contact-row">
                                        <span class="ico"><i class="fa-solid fa-location-dot"></i></span>
                                        <span><?php echo vc_h($companyAddress); ?></span>
                                    </div>
                                </div>
                            </div>
                            <div class="brand-col">
                                <?php if ($logoUrl !== ''): ?>
                                    <img src="<?php echo vc_h($logoUrl); ?>" alt="">
                                <?php else: ?>
                                    <div class="brand-fallback">ARMOR<span>FIRE</span></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script>
    (function () {
        var btn = document.getElementById('vcSaveImageBtn');
        if (!btn || !window.html2canvas) return;

        function downloadCanvas(canvas, filename) {
            var a = document.createElement('a');
            a.download = filename;
            a.href = canvas.toDataURL('image/png');
            document.body.appendChild(a);
            a.click();
            a.remove();
        }

        btn.addEventListener('click', function () {
            var pairs = document.querySelectorAll('.vc-pair');
            if (!pairs.length) return;
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving…';

            var jobs = [];
            pairs.forEach(function (pair) {
                var code = pair.getAttribute('data-emp-code') || 'employee';
                var front = pair.querySelector('.vc-card.front');
                var back = pair.querySelector('.vc-card.back');
                if (front) {
                    jobs.push(html2canvas(front, {
                        scale: 3,
                        backgroundColor: '#ffffff',
                        useCORS: true,
                        allowTaint: true
                    }).then(function (c) {
                        downloadCanvas(c, code + '_front.png');
                    }));
                }
                if (back) {
                    jobs.push(html2canvas(back, {
                        scale: 3,
                        backgroundColor: '#ffffff',
                        useCORS: true,
                        allowTaint: true
                    }).then(function (c) {
                        downloadCanvas(c, code + '_back.png');
                    }));
                }
            });

            Promise.all(jobs).finally(function () {
                btn.disabled = false;
                btn.innerHTML = '<i class="fa-solid fa-image"></i> Image';
            });
        });
    })();
    </script>
</body>
</html>
