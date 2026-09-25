<?php
/**
 * Visiting Card PDF — both sides
 * Front: company logo · Back: name+code, photo, designation, company, phone, email
 *
 * Color options (logo-matched):
 *   fire     — Option 1 Fire Orange
 *   charcoal — Option 2 Charcoal + Orange
 *   white    — Option 3 Clean White + Orange
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
$allowedFormats = ['fire', 'charcoal', 'white'];
$format = isset($_GET['format']) ? strtolower(trim((string) $_GET['format'])) : 'fire';
if (!in_array($format, $allowedFormats, true)) {
    // legacy aliases
    if ($format === 'classic' || $format === 'modern') {
        $format = 'fire';
    } else {
        $format = 'fire';
    }
}

$formatLabels = [
    'fire' => 'Option 1 — Fire Orange',
    'charcoal' => 'Option 2 — Charcoal + Orange',
    'white' => 'Option 3 — Clean White + Orange',
];

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

$companyName = getCompanyName();
$logoRel = getCompanyLogo();
$logoUrl = ($logoRel !== '' && function_exists('app_url')) ? app_url($logoRel) : '';
$backUrl = app_url('employees/visiting_card.php?' . http_build_query([
    'department_id' => $deptId,
    'employee_id' => $employeeId,
    'format' => $format,
    'show' => 1,
]));

function vc_h($v)
{
    return htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');
}

function vc_phone(array $e)
{
    $o = trim((string) ($e['office_mobile'] ?? ''));
    if ($o !== '') {
        return $o;
    }
    return trim((string) ($e['mobile_number'] ?? ''));
}

function vc_email(array $e)
{
    return trim((string) ($e['office_email'] ?? ''));
}

function vc_photo(array $e)
{
    return employeeDocumentPublicUrl($e['photo_file'] ?? '');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Visiting Card — <?php echo vc_h($companyName); ?></title>
    <style>
        /* Armor FIRE brand: orange #F58220 · charcoal #1A1A1A · white */
        :root {
            --fire: #F58220;
            --fire-dark: #d96a0f;
            --fire-deep: #c45c12;
            --charcoal: #1A1A1A;
            --charcoal-2: #2a2a2a;
            --ink: #1A1A1A;
            --muted: #6b7280;
            --line: #e5e7eb;
            --paper: #ffffff;
        }

        /* Theme: Fire Orange */
        body.theme-fire {
            --front-bg: linear-gradient(145deg, #F58220 0%, #e87312 45%, #c45c12 100%);
            --front-fg: #ffffff;
            --front-tag: rgba(255,255,255,.88);
            --front-logo-bg: rgba(255,255,255,.96);
            --back-name: #1A1A1A;
            --back-code: #F58220;
            --back-role: #374151;
            --back-meta: #6b7280;
            --back-photo-border: #F58220;
            --back-photo-col: #fff7ed;
            --accent: linear-gradient(90deg, #F58220, #1A1A1A);
            --card-border: #f0a45a;
        }
        /* Theme: Charcoal + Orange */
        body.theme-charcoal {
            --front-bg: linear-gradient(145deg, #111111 0%, #1A1A1A 50%, #2a2a2a 100%);
            --front-fg: #ffffff;
            --front-tag: rgba(245,130,32,.95);
            --front-logo-bg: rgba(255,255,255,.98);
            --back-name: #1A1A1A;
            --back-code: #F58220;
            --back-role: #374151;
            --back-meta: #6b7280;
            --back-photo-border: #1A1A1A;
            --back-photo-col: #f3f4f6;
            --accent: linear-gradient(90deg, #1A1A1A, #F58220);
            --card-border: #374151;
        }
        /* Theme: Clean White + Orange */
        body.theme-white {
            --front-bg: linear-gradient(180deg, #ffffff 0%, #fff7ed 100%);
            --front-fg: #1A1A1A;
            --front-tag: #F58220;
            --front-logo-bg: transparent;
            --back-name: #1A1A1A;
            --back-code: #F58220;
            --back-role: #374151;
            --back-meta: #6b7280;
            --back-photo-border: #F58220;
            --back-photo-col: #fff7ed;
            --accent: linear-gradient(90deg, #F58220, #F58220);
            --card-border: #F58220;
        }

        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            background: #f3f4f6;
            color: var(--ink);
        }
        .vc-toolbar {
            position: sticky;
            top: 0;
            z-index: 20;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            padding: 12px 16px;
            background: #fff;
            border-bottom: 1px solid var(--line);
        }
        .vc-toolbar .hint { font-size: 13px; color: var(--muted); }
        .vc-theme-switch { display: flex; gap: 6px; flex-wrap: wrap; }
        .vc-theme-switch a {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 7px 10px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 700;
            text-decoration: none;
            color: var(--ink);
            background: #f3f4f6;
            border: 2px solid transparent;
        }
        .vc-theme-switch a.is-active { border-color: var(--fire); background: #fff7ed; }
        .vc-dot {
            width: 12px; height: 12px; border-radius: 50%; display: inline-block;
        }
        .vc-dot.fire { background: #F58220; }
        .vc-dot.charcoal { background: #1A1A1A; }
        .vc-dot.white { background: #fff; border: 2px solid #F58220; }
        .vc-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border: none;
            border-radius: 8px;
            padding: 10px 14px;
            font-weight: 700;
            font-size: 13px;
            cursor: pointer;
            text-decoration: none;
        }
        .vc-btn-primary { background: #F58220; color: #fff; }
        .vc-btn-secondary { background: #f3f4f6; color: var(--ink); }
        .vc-sheet { max-width: 920px; margin: 20px auto 40px; padding: 0 12px; }
        .vc-pair { margin-bottom: 28px; page-break-inside: avoid; }
        .vc-pair-label {
            font-size: 12px; font-weight: 700; color: var(--muted);
            margin: 0 0 8px; letter-spacing: .04em; text-transform: uppercase;
        }
        .vc-sides { display: flex; gap: 16px; flex-wrap: wrap; align-items: flex-start; }
        .side-tag {
            display: inline-block; font-size: 10px; font-weight: 700; color: var(--muted);
            margin-bottom: 4px; text-transform: uppercase; letter-spacing: .06em;
        }

        /* Card size — classic landscape visiting card */
        .vc-card {
            width: 90mm;
            height: 55mm;
            border-radius: 3mm;
            overflow: hidden;
            background: var(--paper);
            box-shadow: 0 8px 24px rgba(26,26,26,.12);
            position: relative;
            border: 1.5px solid var(--card-border);
        }

        /* FRONT */
        .vc-card.front {
            background: var(--front-bg);
            color: var(--front-fg);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 6px;
            padding: 6mm 8mm;
            text-align: center;
        }
        body.theme-white .vc-card.front {
            border-width: 2.5px;
        }
        .vc-card.front .logo-wrap {
            width: 100%;
            height: 28mm;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--front-logo-bg);
            border-radius: 2mm;
            padding: 2.5mm 4mm;
        }
        body.theme-white .vc-card.front .logo-wrap {
            background: transparent;
            padding: 0;
        }
        .vc-card.front .logo-wrap img {
            max-width: 78%;
            max-height: 24mm;
            object-fit: contain;
        }
        .vc-card.front .co-name {
            font-size: 11px;
            font-weight: 800;
            letter-spacing: .1em;
            text-transform: uppercase;
        }
        .vc-card.front .co-tag {
            font-size: 8.5px;
            font-weight: 700;
            letter-spacing: .14em;
            text-transform: uppercase;
            color: var(--front-tag);
        }
        body.theme-white .vc-card.front .co-name { color: #1A1A1A; }
        body.theme-white .vc-card.front::after {
            content: "";
            position: absolute;
            left: 0; right: 0; bottom: 0;
            height: 3mm;
            background: linear-gradient(90deg, #F58220, #1A1A1A);
        }

        /* BACK */
        .vc-card.back {
            display: grid;
            grid-template-columns: 22mm 1fr;
            gap: 0;
            background: #fff;
        }
        .vc-card.back .photo-col {
            background: var(--back-photo-col);
            border-right: 1px solid var(--line);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 3mm;
        }
        .vc-card.back .photo-col img,
        .vc-card.back .photo-ph {
            width: 16mm;
            height: 16mm;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--back-photo-border);
        }
        .vc-card.back .photo-ph {
            display: flex;
            align-items: center;
            justify-content: center;
            background: #e5e7eb;
            color: #6b7280;
            font-size: 9px;
            font-weight: 800;
        }
        .vc-card.back .info-col {
            padding: 3.2mm 4mm 4.5mm;
            display: flex;
            flex-direction: column;
            justify-content: center;
            gap: 1.2mm;
            min-width: 0;
        }
        .vc-card.back .name {
            font-size: 12.5px;
            font-weight: 800;
            line-height: 1.15;
            color: var(--back-name);
        }
        .vc-card.back .code {
            font-size: 9.5px;
            font-weight: 800;
            color: var(--back-code);
            letter-spacing: .04em;
        }
        .vc-card.back .role {
            font-size: 9.5px;
            font-weight: 650;
            color: var(--back-role);
        }
        .vc-card.back .meta {
            font-size: 8.5px;
            color: var(--back-meta);
            display: flex;
            flex-direction: column;
            gap: 0.9mm;
            margin-top: 1mm;
        }
        .vc-card.back .meta span {
            display: block;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .vc-card.back .meta .co { font-weight: 700; color: #374151; }
        .vc-card.back .accent {
            position: absolute;
            left: 0; right: 0; bottom: 0;
            height: 2.4mm;
            background: var(--accent);
        }

        @media print {
            body { background: #fff; }
            .vc-toolbar { display: none !important; }
            .vc-sheet { margin: 0; padding: 0; max-width: none; }
            .vc-pair-label, .side-tag { display: none; }
            .vc-sides { display: block; gap: 0; }
            .vc-card {
                box-shadow: none !important;
                page-break-after: always;
                break-after: page;
            }
            .vc-pair { margin: 0; }
            .vc-pair:last-child .vc-card.back { page-break-after: auto; }
        }
        @page { size: 90mm 55mm; margin: 0; }
    </style>
</head>
<body class="theme-<?php echo vc_h($format); ?>">
    <div class="vc-toolbar">
        <div>
            <div class="hint">
                <strong><?php echo count($rows); ?></strong> card(s) ·
                <?php echo vc_h($formatLabels[$format]); ?> ·
                Front + Back · Print → Save as PDF
            </div>
            <div class="vc-theme-switch" style="margin-top:8px;">
                <?php foreach ($formatLabels as $key => $label):
                    $qs = http_build_query([
                        'department_id' => $deptId,
                        'employee_id' => $employeeId,
                        'format' => $key,
                    ]);
                    ?>
                    <a class="<?php echo $format === $key ? 'is-active' : ''; ?>"
                       href="<?php echo vc_h(app_url('employees/visiting_card_pdf.php?' . $qs)); ?>">
                        <span class="vc-dot <?php echo vc_h($key); ?>"></span>
                        <?php echo vc_h(explode(' — ', $label)[0]); ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <a class="vc-btn vc-btn-secondary" href="<?php echo vc_h($backUrl); ?>">← Back</a>
            <button type="button" class="vc-btn vc-btn-primary" onclick="window.print()">Print / Save PDF</button>
        </div>
    </div>

    <div class="vc-sheet">
        <?php foreach ($rows as $e):
            $photo = vc_photo($e);
            $phone = vc_phone($e);
            $email = vc_email($e);
            $name = trim((string) ($e['employee_name'] ?? ''));
            $code = trim((string) ($e['employee_code'] ?? ''));
            $desig = trim((string) ($e['designation'] ?? ''));
            $initials = '';
            if ($name !== '') {
                $parts = preg_split('/\s+/', $name);
                $initials = strtoupper(substr($parts[0], 0, 1) . (isset($parts[1]) ? substr($parts[1], 0, 1) : ''));
            }
            ?>
            <div class="vc-pair">
                <p class="vc-pair-label"><?php echo vc_h($code . ' — ' . $name); ?></p>
                <div class="vc-sides">
                    <div>
                        <div class="side-tag">Front</div>
                        <div class="vc-card front">
                            <div class="logo-wrap">
                                <?php if ($logoUrl !== ''): ?>
                                    <img src="<?php echo vc_h($logoUrl); ?>" alt="<?php echo vc_h($companyName); ?>">
                                <?php else: ?>
                                    <div style="font-size:16px;font-weight:800;letter-spacing:.06em;color:#1A1A1A;">
                                        Armor <span style="color:#F58220;">FIRE</span>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="co-name"><?php echo vc_h($companyName); ?></div>
                            <div class="co-tag">Official Visiting Card</div>
                        </div>
                    </div>

                    <div>
                        <div class="side-tag">Back</div>
                        <div class="vc-card back">
                            <div class="photo-col">
                                <?php if ($photo !== ''): ?>
                                    <img src="<?php echo vc_h($photo); ?>" alt="">
                                <?php else: ?>
                                    <div class="photo-ph"><?php echo vc_h($initials !== '' ? $initials : '?'); ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="info-col">
                                <div class="name"><?php echo vc_h($name); ?></div>
                                <div class="code"><?php echo vc_h($code); ?></div>
                                <?php if ($desig !== ''): ?>
                                    <div class="role"><?php echo vc_h($desig); ?></div>
                                <?php endif; ?>
                                <div class="meta">
                                    <span class="co"><?php echo vc_h($companyName); ?></span>
                                    <?php if ($phone !== ''): ?>
                                        <span><?php echo vc_h($phone); ?></span>
                                    <?php endif; ?>
                                    <?php if ($email !== ''): ?>
                                        <span><?php echo vc_h($email); ?></span>
                                    <?php else: ?>
                                        <span style="opacity:.55;">Email —</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="accent"></div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</body>
</html>
