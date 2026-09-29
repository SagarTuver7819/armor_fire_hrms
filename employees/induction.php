<?php
/**
 * Induction / Sales Training — Trainer & Trainee Signature Sheet (print)
 * ?id=EMP_ID&type=general|sales  (type optional; sales auto if sales role)
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permission_helper.php';
require_once __DIR__ . '/../includes/employee_helper.php';
require_once __DIR__ . '/../includes/settings.php';
require_once __DIR__ . '/../includes/document_print.php';
require_once __DIR__ . '/../includes/induction_helper.php';

requireLogin();

$id = (int) ($_GET['id'] ?? 0);
$emp = $id > 0 ? getEmployeeById($id) : null;
if (!$emp) {
    die('Employee not found.');
}

$deptId = (int) ($emp['department_id'] ?? 0);
if (!canAccess('employees', 'view', $deptId) && !isAdmin() && !isHR()) {
    requireAccess('employees', 'view', $deptId);
}

$type = inductionResolveType($emp, $_GET['type'] ?? '');
$sheet = inductionGetSheetDefinition($type);
$brand = getCompanyDocumentBranding();
$company = trim((string) ($brand['company_name'] ?? 'Armor Fire'));
if ($company === '' || strcasecmp($company, 'Armor Fire') === 0) {
    $company = 'Armor Steel Industries Pvt. Ltd.';
}

$empName = trim((string) ($emp['employee_name'] ?? ''));
$joinDate = !empty($emp['date_of_joining']) && $emp['date_of_joining'] !== '0000-00-00'
    ? formatDateDisplay((string) $emp['date_of_joining'])
    : '';
$designation = trim((string) ($emp['designation'] ?? ''));
$deptName = trim((string) ($emp['department_name'] ?? ''));
$code = trim((string) ($emp['employee_code'] ?? ''));

$trackType = employeeTrainingTrackType($emp);
$isMismatch = ($type !== $trackType);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($sheet['label']); ?> · <?php echo htmlspecialchars($empName); ?></title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            padding: 14px;
            background: #e5e7eb;
            color: #111;
            font-family: Calibri, Candara, Segoe UI, Arial, sans-serif;
            font-size: 12px;
            line-height: 1.35;
        }
        .bar {
            max-width: 210mm;
            margin: 0 auto 10px;
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            justify-content: space-between;
            align-items: center;
            background: #fff;
            border: 1px solid #cbd5e1;
            padding: 8px 12px;
        }
        .bar .links { display: flex; gap: 8px; flex-wrap: wrap; }
        .bar a, .bar button {
            display: inline-block;
            background: #d2232a;
            color: #fff;
            border: 0;
            padding: 8px 12px;
            font-weight: 700;
            cursor: pointer;
            text-decoration: none;
            font-family: Calibri, Candara, Segoe UI, Arial, sans-serif;
            font-size: 12px;
        }
        .bar a.secondary { background: #475569; }
        .sheet {
            width: 210mm;
            max-width: 100%;
            min-height: 297mm;
            margin: 0 auto;
            background: #fff;
            border: 2px solid #111;
            padding: 10mm 11mm 8mm;
            box-sizing: border-box;
        }
        <?php echo companyDocPrintCss(); ?>
        .cdoc-header {
            gap: 10px;
            padding-bottom: 8px;
            margin-bottom: 8px;
            align-items: center;
        }
        .cdoc-header-logo { width: 56px; height: 56px; }
        .cdoc-header-text .cdoc-company {
            font-size: 18px;
            font-weight: 800;
            color: #b91c1c;
            font-family: Calibri, Candara, Segoe UI, Arial, sans-serif;
            margin: 0 0 3px;
        }
        .cdoc-header-text .cdoc-header-details {
            font-size: 11px;
            font-weight: 700;
            color: #1e293b;
            font-family: Calibri, Candara, Segoe UI, Arial, sans-serif;
            line-height: 1.35;
        }
        .cdoc-footer {
            margin-top: 10px;
            padding-top: 6px;
            font-size: 9.5px;
            font-family: Calibri, Candara, Segoe UI, Arial, sans-serif;
        }
        .cdoc-watermark img {
            width: min(50%, 300px);
            max-height: 300px;
            opacity: 0.06;
        }

        .sheet-title {
            text-align: center;
            margin: 0 0 10px;
            font-size: 15px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .03em;
            text-decoration: underline;
            text-underline-offset: 3px;
        }
        .meta-grid {
            display: grid;
            grid-template-columns: 1.2fr 1fr 1fr;
            gap: 8px 12px;
            margin: 0 0 10px;
            font-size: 12.5px;
        }
        .meta-grid .fld span {
            display: block;
            font-size: 10px;
            font-weight: 700;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: .03em;
        }
        .meta-grid .fld strong {
            display: block;
            border-bottom: 1px solid #94a3b8;
            min-height: 20px;
            padding: 2px 0;
            font-weight: 700;
        }

        table.train {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            font-size: 11.5px;
        }
        table.train th,
        table.train td {
            border: 1px solid #334155;
            padding: 5px 6px;
            vertical-align: top;
        }
        table.train th {
            background: #f1f5f9;
            font-weight: 800;
            text-align: center;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: .02em;
        }
        table.train td.sr { width: 36px; text-align: center; font-weight: 700; }
        table.train td.topic { width: 22%; font-weight: 700; }
        table.train td.cov { width: 42%; }
        table.train td.sig { width: 14%; height: 34px; }
        table.train .type-pill {
            display: inline-block;
            margin-top: 4px;
            font-size: 9px;
            font-weight: 700;
            color: #9f1239;
            background: #ffe4e6;
            padding: 1px 5px;
            border-radius: 3px;
        }

        .foot-row {
            display: flex;
            justify-content: space-between;
            gap: 20px;
            margin-top: 14px;
            font-size: 12.5px;
            font-weight: 700;
        }
        .foot-row .line {
            display: inline-block;
            min-width: 140px;
            border-bottom: 1px solid #111;
            margin-left: 6px;
            height: 16px;
            vertical-align: bottom;
        }

        @media print {
            html, body {
                background: #fff !important;
                padding: 0 !important;
                margin: 0 !important;
            }
            .bar { display: none !important; }
            .sheet {
                width: 210mm;
                max-width: none;
                min-height: 285mm;
                height: auto;
                margin: 0 auto;
                border: 2.25pt solid #111 !important;
                outline: none;
                padding: 8mm 9mm 7mm;
                box-sizing: border-box;
                box-shadow: none;
                page-break-inside: avoid;
            }
            @page {
                size: A4 portrait;
                margin: 6mm;
            }
        }
    </style>
</head>
<body>
<div class="bar">
    <div>
        <strong><?php echo htmlspecialchars($code !== '' ? $code : ('#' . $id)); ?></strong>
        · <?php echo htmlspecialchars($sheet['label']); ?>
        <?php if ($type === 'sales'): ?>
            <span style="color:#9f1239;font-weight:700;">(Sales)</span>
        <?php else: ?>
            <span style="color:#15803d;font-weight:700;">(All Employees)</span>
        <?php endif; ?>
    </div>
    <div class="links">
        <?php if ($isMismatch): ?>
        <a class="secondary" href="<?php echo htmlspecialchars(app_url('employees/induction.php?id=' . $id . '&type=' . $trackType)); ?>">
            Open correct: <?php echo $trackType === 'sales' ? 'Sales Sheet' : 'General Induction'; ?>
        </a>
        <?php endif; ?>
        <button type="button" onclick="window.print()">Print / Save PDF</button>
    </div>
</div>

<div class="sheet cdoc-sheet">
    <?php echo companyDocRenderWatermark($brand); ?>
    <div class="cdoc-inner">
        <?php echo companyDocRenderHeader($brand, 'HR · Induction Training'); ?>

        <h1 class="sheet-title"><?php echo htmlspecialchars($sheet['sheet_title']); ?></h1>

        <div class="meta-grid">
            <div class="fld">
                <span>Name</span>
                <strong><?php echo htmlspecialchars($empName); ?></strong>
            </div>
            <div class="fld">
                <span>Designation / Dept</span>
                <strong><?php
                    $bits = array_filter([$designation, $deptName]);
                    echo htmlspecialchars($bits ? implode(' · ', $bits) : '—');
                ?></strong>
            </div>
            <div class="fld">
                <span>Training Start Date</span>
                <strong><?php echo htmlspecialchars($joinDate !== '' ? $joinDate : ' '); ?></strong>
            </div>
        </div>

        <table class="train">
            <thead>
                <tr>
                    <th style="width:36px;">Sr.</th>
                    <th style="width:22%;">Training Topic</th>
                    <th>Training Coverage</th>
                    <th style="width:14%;">Trainer</th>
                    <th style="width:14%;">Trainee</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($sheet['topics'] as $i => $t): ?>
                <tr>
                    <td class="sr"><?php echo (int) ($i + 1); ?></td>
                    <td class="topic"><?php echo htmlspecialchars((string) $t['topic']); ?></td>
                    <td class="cov"><?php echo htmlspecialchars((string) ($t['coverage'] ?? '')); ?></td>
                    <td class="sig"></td>
                    <td class="sig"></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <div class="foot-row">
            <div>Training End Date :<span class="line"></span></div>
            <div>Authorised Signature :<span class="line"></span></div>
        </div>

        <?php echo companyDocRenderFooter($brand); ?>
    </div>
</div>
</body>
</html>
