<?php
/**
 * Canteen Use Report — active employees, department-wise Yes / No
 * Modes: screen (default) · ?export=print (A4 letterhead) · ?export=excel (letterhead Excel)
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permission_helper.php';
require_once __DIR__ . '/../includes/employee_helper.php';
require_once __DIR__ . '/../includes/settings.php';
require_once __DIR__ . '/../includes/document_print.php';

requireLogin();

$deptId = isset($_GET['department_id']) ? (int) $_GET['department_id'] : 0;
requireAccess('employees', 'view', $deptId);

$canteenFilter = (string) ($_GET['canteen'] ?? 'all');
if (!in_array($canteenFilter, ['all', 'Yes', 'No'], true)) {
    $canteenFilter = 'all';
}
$q = trim((string) ($_GET['q'] ?? ''));
$exportMode = (string) ($_GET['export'] ?? '');

$allowedDepts = allowedDepartmentsFor('employees', 'view');

$conn = getDBConnection();
ensureEmployeesTable($conn);

$departments = [];
$dres = $conn->query('SELECT id, department_name FROM departments WHERE status = 1 ORDER BY sort_order ASC, department_name ASC');
if ($dres) {
    while ($r = $dres->fetch_assoc()) {
        if (is_array($allowedDepts) && !in_array((int) $r['id'], array_map('intval', $allowedDepts), true)) {
            continue;
        }
        $departments[] = $r;
    }
}

$sql = "SELECT e.id, e.employee_code, e.employee_name, e.designation, e.mobile_number,
               e.shift_time, e.canteen_use, e.department_id, d.department_name
        FROM employees e
        LEFT JOIN departments d ON d.id = e.department_id
        WHERE e.status = 1";
$types = '';
$params = [];
if ($deptId > 0) {
    $sql .= ' AND e.department_id = ?';
    $types .= 'i';
    $params[] = $deptId;
} elseif (is_array($allowedDepts)) {
    $ids = array_values(array_filter(array_map('intval', $allowedDepts)));
    $sql .= $ids ? ' AND e.department_id IN (' . implode(',', $ids) . ')' : ' AND 1 = 0';
}
if ($q !== '') {
    $sql .= ' AND (e.employee_name LIKE ? OR e.employee_code LIKE ? OR e.designation LIKE ?)';
    $like = '%' . $q . '%';
    $types .= 'sss';
    array_push($params, $like, $like, $like);
}
$sql .= ' ORDER BY d.sort_order ASC, d.department_name ASC, e.employee_code ASC';

$allRows = [];
if ($types !== '') {
    $st = $conn->prepare($sql);
    $st->bind_param($types, ...$params);
    $st->execute();
    $res = $st->get_result();
} else {
    $st = null;
    $res = $conn->query($sql);
}
if ($res) {
    while ($r = $res->fetch_assoc()) {
        $r['canteen_use'] = (($r['canteen_use'] ?? 'No') === 'Yes') ? 'Yes' : 'No';
        $allRows[] = $r;
    }
}
if ($st) {
    $st->close();
}
$conn->close();

// Summary is always on the full set (before Yes/No filter); the list follows the filter
$totalAll = count($allRows);
$totalYes = 0;
$deptSummary = [];
foreach ($allRows as $r) {
    $dname = (string) ($r['department_name'] ?: '—');
    if (!isset($deptSummary[$dname])) {
        $deptSummary[$dname] = ['total' => 0, 'yes' => 0, 'no' => 0];
    }
    $deptSummary[$dname]['total']++;
    if ($r['canteen_use'] === 'Yes') {
        $deptSummary[$dname]['yes']++;
        $totalYes++;
    } else {
        $deptSummary[$dname]['no']++;
    }
}
$totalNo = $totalAll - $totalYes;
$yesPct = $totalAll > 0 ? round($totalYes * 100 / $totalAll) : 0;
$noPct = $totalAll > 0 ? 100 - $yesPct : 0;

$rows = $canteenFilter === 'all'
    ? $allRows
    : array_values(array_filter($allRows, static function ($r) use ($canteenFilter) {
        return $r['canteen_use'] === $canteenFilter;
    }));

$deptLabel = 'All Departments';
foreach ($departments as $d) {
    if ((int) $d['id'] === $deptId) {
        $deptLabel = (string) $d['department_name'];
    }
}
$filterLabel = $canteenFilter === 'all' ? 'All' : $canteenFilter;
$showDeptSummary = ($deptId === 0 && $deptSummary);
$printedAt = date('d-m-Y h:i A');

$baseQuery = ['department_id' => $deptId, 'canteen' => $canteenFilter, 'q' => $q];
$exportUrl = app_url('employees/canteen_report.php?' . http_build_query($baseQuery + ['export' => 'excel']));
$printUrl = app_url('employees/canteen_report.php?' . http_build_query($baseQuery + ['export' => 'print']));
$screenUrl = app_url('employees/canteen_report.php?' . http_build_query($baseQuery));

/* ===================== EXCEL (letterhead style) ===================== */
if ($exportMode === 'excel') {
    $brand = getCompanyDocumentBranding();
    $logoAbs = '';
    $logoSrc = trim((string) ($brand['logo_src'] ?? ''));
    if ($logoSrc !== '') {
        if (strpos($logoSrc, 'http') === 0) {
            $logoAbs = $logoSrc;
        } else {
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $logoAbs = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/' . ltrim($logoSrc, '/');
        }
    }
    $cols = 8;
    $fileDept = preg_replace('/[^A-Za-z0-9]+/', '_', $deptLabel);
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="Canteen_Use_Report_' . $fileDept . '_' . date('Ymd') . '.xls"');
    header('Cache-Control: max-age=0');

    $cellBorder = 'border:1px solid #94a3b8;';
    echo '<html><head><meta charset="UTF-8"></head><body style="font-family:Calibri,Arial,sans-serif;">';
    echo '<table cellspacing="0" cellpadding="5" style="border-collapse:collapse;">';

    // Letterhead
    echo '<tr>';
    echo '<td rowspan="3" style="width:90px;height:80px;vertical-align:middle;text-align:center;border-bottom:3px solid #d2232a;">';
    if ($logoAbs !== '') {
        echo '<img src="' . htmlspecialchars($logoAbs) . '" width="70" height="70">';
    }
    echo '</td>';
    echo '<td colspan="' . ($cols - 1) . '" style="font-size:20px;font-weight:bold;color:#b91c1c;text-transform:uppercase;">'
        . htmlspecialchars((string) $brand['company_name']) . '</td></tr>';
    echo '<tr><td colspan="' . ($cols - 1) . '" style="font-size:11px;font-weight:bold;color:#1e293b;white-space:pre-wrap;">'
        . nl2br(htmlspecialchars((string) $brand['header_details'])) . '</td></tr>';
    echo '<tr><td colspan="' . ($cols - 1) . '" style="border-bottom:3px solid #d2232a;">&nbsp;</td></tr>';

    echo '<tr><td colspan="' . $cols . '">&nbsp;</td></tr>';
    echo '<tr><td colspan="' . $cols . '" style="font-size:16px;font-weight:bold;text-align:center;text-decoration:underline;">CANTEEN USE REPORT</td></tr>';
    echo '<tr><td colspan="' . $cols . '" style="font-size:11px;font-weight:bold;color:#334155;text-align:center;">'
        . 'Department: ' . htmlspecialchars($deptLabel) . ' &nbsp;|&nbsp; Canteen Use: ' . htmlspecialchars($filterLabel)
        . ' &nbsp;|&nbsp; Printed: ' . htmlspecialchars($printedAt) . '</td></tr>';
    echo '<tr><td colspan="' . $cols . '">&nbsp;</td></tr>';

    // KPIs
    echo '<tr>'
        . '<td colspan="3" style="' . $cellBorder . 'background:#eff6ff;font-weight:bold;text-align:center;">Total Employees: ' . $totalAll . '</td>'
        . '<td colspan="2" style="' . $cellBorder . 'background:#dcfce7;color:#15803d;font-weight:bold;text-align:center;">Canteen Yes: ' . $totalYes . ' (' . $yesPct . '%)</td>'
        . '<td colspan="3" style="' . $cellBorder . 'background:#fee2e2;color:#b91c1c;font-weight:bold;text-align:center;">Canteen No: ' . $totalNo . ' (' . $noPct . '%)</td>'
        . '</tr>';
    echo '<tr><td colspan="' . $cols . '">&nbsp;</td></tr>';

    $th = 'style="' . $cellBorder . 'background:#d2232a;color:#ffffff;font-weight:bold;text-align:center;"';

    // Department summary
    if ($showDeptSummary) {
        echo '<tr><td colspan="' . $cols . '" style="font-size:13px;font-weight:bold;">Department-wise Summary</td></tr>';
        echo '<tr><th ' . $th . '>Sr</th><th colspan="4" ' . $th . '>Department</th><th ' . $th . '>Total</th><th ' . $th . '>Canteen Yes</th><th ' . $th . '>Canteen No</th></tr>';
        $sr = 1;
        foreach ($deptSummary as $dname => $s) {
            echo '<tr>'
                . '<td style="' . $cellBorder . 'text-align:center;">' . $sr++ . '</td>'
                . '<td colspan="4" style="' . $cellBorder . '">' . htmlspecialchars($dname) . '</td>'
                . '<td style="' . $cellBorder . 'text-align:center;">' . $s['total'] . '</td>'
                . '<td style="' . $cellBorder . 'text-align:center;color:#15803d;font-weight:bold;">' . $s['yes'] . '</td>'
                . '<td style="' . $cellBorder . 'text-align:center;color:#b91c1c;font-weight:bold;">' . $s['no'] . '</td>'
                . '</tr>';
        }
        echo '<tr style="background:#f1f5f9;font-weight:bold;">'
            . '<td colspan="5" style="' . $cellBorder . 'text-align:right;">TOTAL</td>'
            . '<td style="' . $cellBorder . 'text-align:center;">' . $totalAll . '</td>'
            . '<td style="' . $cellBorder . 'text-align:center;">' . $totalYes . '</td>'
            . '<td style="' . $cellBorder . 'text-align:center;">' . $totalNo . '</td></tr>';
        echo '<tr><td colspan="' . $cols . '">&nbsp;</td></tr>';
    }

    // Employee list
    echo '<tr><td colspan="' . $cols . '" style="font-size:13px;font-weight:bold;">Employee List (' . count($rows) . ')</td></tr>';
    echo '<tr><th ' . $th . '>Sr</th><th ' . $th . '>Emp. Code</th><th ' . $th . '>Employee Name</th><th ' . $th . '>Department</th><th ' . $th . '>Designation</th><th ' . $th . '>Mobile</th><th ' . $th . '>Shift</th><th ' . $th . '>Canteen Use</th></tr>';
    if (!$rows) {
        echo '<tr><td colspan="' . $cols . '" style="' . $cellBorder . 'text-align:center;">No employees found.</td></tr>';
    }
    foreach ($rows as $i => $r) {
        $isYes = $r['canteen_use'] === 'Yes';
        echo '<tr>'
            . '<td style="' . $cellBorder . 'text-align:center;">' . ($i + 1) . '</td>'
            . '<td style="' . $cellBorder . '">' . htmlspecialchars((string) $r['employee_code']) . '</td>'
            . '<td style="' . $cellBorder . '">' . htmlspecialchars((string) $r['employee_name']) . '</td>'
            . '<td style="' . $cellBorder . '">' . htmlspecialchars((string) ($r['department_name'] ?? '')) . '</td>'
            . '<td style="' . $cellBorder . '">' . htmlspecialchars((string) ($r['designation'] ?? '')) . '</td>'
            . '<td style="' . $cellBorder . 'mso-number-format:\'\@\';">' . htmlspecialchars((string) ($r['mobile_number'] ?? '')) . '</td>'
            . '<td style="' . $cellBorder . '">' . htmlspecialchars((string) ($r['shift_time'] ?? '')) . '</td>'
            . '<td style="' . $cellBorder . 'text-align:center;font-weight:bold;background:' . ($isYes ? '#dcfce7;color:#15803d;' : '#fee2e2;color:#b91c1c;') . '">' . $r['canteen_use'] . '</td>'
            . '</tr>';
    }

    $footer = trim((string) $brand['footer_details']);
    if ($footer !== '') {
        echo '<tr><td colspan="' . $cols . '">&nbsp;</td></tr>';
        echo '<tr><td colspan="' . $cols . '" style="border-top:2px solid #d2232a;font-size:10px;color:#475569;text-align:center;white-space:pre-wrap;">'
            . nl2br(htmlspecialchars($footer)) . '</td></tr>';
    }
    echo '</table></body></html>';
    exit;
}

/* ===================== PRINT (A4 letterhead) ===================== */
if ($exportMode === 'print') {
    $brand = getCompanyDocumentBranding();

    // First page carries KPIs + department summary, so it holds fewer list rows
    $pages = [];
    $firstCap = $showDeptSummary ? max(4, 24 - count($deptSummary)) : 24;
    $pages[] = array_slice($rows, 0, $firstCap);
    foreach (array_chunk(array_slice($rows, $firstCap), 30) as $chunk) {
        $pages[] = $chunk;
    }
    $totalPages = count($pages);
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Canteen Use Report</title>
    <style>
        <?php echo companyDocPrintCss(); ?>
        * { box-sizing: border-box; }
        body {
            margin: 0; background: #e5e7eb; color: #111;
            font-family: Calibri, Candara, Segoe UI, Arial, Helvetica, sans-serif; font-size: 12px;
            -webkit-print-color-adjust: exact; print-color-adjust: exact;
        }
        .cp-toolbar {
            position: sticky; top: 0; z-index: 40;
            display: flex; justify-content: space-between; align-items: center;
            gap: 10px; padding: 12px 18px; background: #111; color: #fff;
        }
        .cp-btn {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 8px 14px; border-radius: 8px; background: #333;
            color: #fff; text-decoration: none; border: 0; cursor: pointer;
            font-size: 13px; font-weight: 700; font-family: inherit;
        }
        .cp-btn.primary { background: #d2232a; }
        .cp-page {
            width: 210mm; min-height: 297mm; margin: 16px auto;
            background: #fff; position: relative; overflow: hidden;
            box-shadow: 0 8px 28px rgba(0,0,0,.12); border: 2.25px solid #000;
        }
        .cp-inner { min-height: 297mm; padding: 10mm 12mm; display: flex; flex-direction: column; }
        .cdoc-header { flex-shrink: 0; margin-bottom: 10px; }
        .cdoc-header-logo { width: 78px; height: 78px; }
        .cdoc-header-text .cdoc-company { font-size: 22px; text-transform: uppercase; margin: 0 0 4px; }
        .cdoc-header-text .cdoc-header-details { font-size: 12px; line-height: 1.4; }
        .cdoc-footer { margin-top: auto; padding-top: 8px; font-size: 10px; }
        .cdoc-watermark img { width: min(48%, 280px); max-height: 280px; opacity: .06; }
        .cp-title {
            text-align: center; margin: 4px 0 8px; font-size: 18px; font-weight: 800;
            letter-spacing: .04em; text-transform: uppercase; text-decoration: underline; text-underline-offset: 3px;
        }
        .cp-meta {
            display: flex; flex-wrap: wrap; justify-content: center; gap: 6px 18px;
            margin-bottom: 10px; font-size: 11.5px; font-weight: 700; color: #334155;
        }
        .cp-kpis { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; margin-bottom: 12px; }
        .cp-kpi { border: 1px solid #cbd5e1; border-radius: 6px; padding: 7px 8px; text-align: center; }
        .cp-kpi .l { font-size: 10px; font-weight: 800; color: #64748b; text-transform: uppercase; }
        .cp-kpi .v { font-size: 18px; font-weight: 800; color: #0f172a; }
        .cp-kpi.yes { background: #f0fdf4; border-color: #86efac; }
        .cp-kpi.yes .v, .cp-kpi.yes .l { color: #15803d; }
        .cp-kpi.no { background: #fef2f2; border-color: #fca5a5; }
        .cp-kpi.no .v, .cp-kpi.no .l { color: #b91c1c; }
        .cp-sub { margin: 4px 0 6px; font-size: 12.5px; font-weight: 800; text-transform: uppercase; color: #1e293b; }
        .cp-table { width: 100%; border-collapse: collapse; table-layout: fixed; font-size: 10.5px; margin-bottom: 10px; }
        .cp-table th, .cp-table td { border: 1px solid #1e293b; padding: 4px 5px; vertical-align: middle; word-break: break-word; }
        .cp-table th { background: #f1f5f9; font-weight: 800; text-transform: uppercase; font-size: 9.5px; text-align: center; }
        .cp-table .ctr { text-align: center; }
        .cp-table tfoot td { background: #f1f5f9; font-weight: 800; }
        .cp-yes { color: #15803d; font-weight: 800; }
        .cp-no { color: #b91c1c; font-weight: 800; }
        .cp-empty { text-align: center; padding: 24px 10px; color: #64748b; font-weight: 700; }
        .cp-page-no { text-align: right; font-size: 10px; color: #64748b; margin-top: 4px; font-weight: 700; }
        @media print {
            @page { size: A4; margin: 0; }
            .no-print { display: none !important; }
            body { background: #fff; }
            .cp-page {
                width: 210mm; min-height: 297mm; height: 297mm; margin: 0; box-shadow: none; border: 0;
                page-break-after: always; break-after: page;
            }
            .cp-page:last-child { page-break-after: auto; break-after: auto; }
            .cp-inner { min-height: 297mm; height: 297mm; }
        }
    </style>
</head>
<body>
<div class="cp-toolbar no-print">
    <a class="cp-btn" href="<?php echo htmlspecialchars($screenUrl); ?>">← Back to Report</a>
    <button type="button" class="cp-btn primary" onclick="window.print()">Print / Save as PDF</button>
</div>

<?php $srOffset = 0; foreach ($pages as $pageIndex => $pageRows): ?>
<section class="cp-page cdoc-sheet">
    <?php echo companyDocRenderWatermark($brand); ?>
    <div class="cp-inner cdoc-inner">
        <?php echo companyDocRenderHeader($brand, 'Human Resource · Canteen Use Report'); ?>

        <h1 class="cp-title">Canteen Use Report</h1>
        <div class="cp-meta">
            <span>Department: <?php echo htmlspecialchars($deptLabel); ?></span>
            <span>Canteen Use: <?php echo htmlspecialchars($filterLabel); ?></span>
            <span>Printed: <?php echo htmlspecialchars($printedAt); ?></span>
        </div>

        <?php if ($pageIndex === 0): ?>
        <div class="cp-kpis">
            <div class="cp-kpi"><div class="l">Total Employees</div><div class="v"><?php echo $totalAll; ?></div></div>
            <div class="cp-kpi yes"><div class="l">Canteen Yes</div><div class="v"><?php echo $totalYes; ?> <small>(<?php echo $yesPct; ?>%)</small></div></div>
            <div class="cp-kpi no"><div class="l">Canteen No</div><div class="v"><?php echo $totalNo; ?> <small>(<?php echo $noPct; ?>%)</small></div></div>
        </div>

        <?php if ($showDeptSummary): ?>
        <div class="cp-sub">Department-wise Summary</div>
        <table class="cp-table">
            <thead>
                <tr>
                    <th style="width:8%;">Sr</th>
                    <th style="width:50%;">Department</th>
                    <th style="width:14%;">Total</th>
                    <th style="width:14%;">Canteen Yes</th>
                    <th style="width:14%;">Canteen No</th>
                </tr>
            </thead>
            <tbody>
            <?php $sr = 1; foreach ($deptSummary as $dname => $s): ?>
                <tr>
                    <td class="ctr"><?php echo $sr++; ?></td>
                    <td><?php echo htmlspecialchars($dname); ?></td>
                    <td class="ctr"><?php echo $s['total']; ?></td>
                    <td class="ctr cp-yes"><?php echo $s['yes']; ?></td>
                    <td class="ctr cp-no"><?php echo $s['no']; ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="2" style="text-align:right;">TOTAL</td>
                    <td class="ctr"><?php echo $totalAll; ?></td>
                    <td class="ctr"><?php echo $totalYes; ?></td>
                    <td class="ctr"><?php echo $totalNo; ?></td>
                </tr>
            </tfoot>
        </table>
        <?php endif; ?>
        <?php endif; ?>

        <?php if ($pageRows || $pageIndex === 0): ?>
        <div class="cp-sub">Employee List (<?php echo count($rows); ?>)</div>
        <table class="cp-table">
            <thead>
                <tr>
                    <th style="width:6%;">Sr</th>
                    <th style="width:11%;">Emp. Code</th>
                    <th style="width:22%;">Employee Name</th>
                    <th style="width:18%;">Department</th>
                    <th style="width:16%;">Designation</th>
                    <th style="width:11%;">Mobile</th>
                    <th style="width:16%;">Shift</th>
                    <th style="width:10%;">Canteen</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$pageRows): ?>
                <tr><td colspan="8" class="cp-empty">No employees found for selected filters.</td></tr>
            <?php else: foreach ($pageRows as $j => $r): ?>
                <tr>
                    <td class="ctr"><?php echo $srOffset + $j + 1; ?></td>
                    <td><?php echo htmlspecialchars((string) $r['employee_code']); ?></td>
                    <td><?php echo htmlspecialchars((string) $r['employee_name']); ?></td>
                    <td><?php echo htmlspecialchars((string) ($r['department_name'] ?: '—')); ?></td>
                    <td><?php echo htmlspecialchars((string) ($r['designation'] ?: '—')); ?></td>
                    <td><?php echo htmlspecialchars((string) ($r['mobile_number'] ?: '—')); ?></td>
                    <td><?php echo htmlspecialchars((string) ($r['shift_time'] ?: '—')); ?></td>
                    <td class="ctr <?php echo $r['canteen_use'] === 'Yes' ? 'cp-yes' : 'cp-no'; ?>"><?php echo $r['canteen_use']; ?></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
        <?php endif; ?>

        <div class="cp-page-no">Page <?php echo $pageIndex + 1; ?> of <?php echo $totalPages; ?></div>
        <?php echo companyDocRenderFooter($brand); ?>
    </div>
</section>
<?php $srOffset += count($pageRows); endforeach; ?>
</body>
</html>
    <?php
    exit;
}

/* ===================== SCREEN ===================== */
$pageTitle = 'Canteen Use Report';
$useSidebar = true;
$sidebarMode = 'employees';
$sidebarActive = 'canteen_report';
$sidebarDeptId = $deptId;

require_once __DIR__ . '/../includes/header.php';
?>
<style>
.cr-hero {
    display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: wrap;
    padding: 18px 20px; margin-bottom: 18px; border-radius: 14px;
    background: linear-gradient(120deg, #fff5f5 0%, #ffffff 60%);
    border: 1px solid #fde2e2;
}
.cr-hero-left { display: flex; align-items: center; gap: 14px; }
.cr-hero-ico {
    width: 52px; height: 52px; border-radius: 14px; flex-shrink: 0;
    display: grid; place-items: center; font-size: 22px; color: #fff;
    background: linear-gradient(135deg, #d2232a, #9f1239);
    box-shadow: 0 8px 20px rgba(210, 35, 42, .25);
}
.cr-hero h1 { margin: 0; font-size: 22px; font-weight: 800; color: #0f172a; }
.cr-hero p { margin: 3px 0 0; font-size: 13px; color: #64748b; }
.cr-chip {
    display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px; border-radius: 999px;
    font-size: 12px; font-weight: 700; background: #f1f5f9; color: #334155;
}

.cr-kpis { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 14px; margin-bottom: 18px; }
.cr-kpi {
    position: relative; padding: 16px 18px; border-radius: 14px; background: #fff;
    border: 1px solid #e2e8f0; box-shadow: 0 2px 10px rgba(15, 23, 42, .04);
    display: flex; align-items: center; gap: 14px;
}
.cr-kpi-ico { width: 46px; height: 46px; border-radius: 12px; display: grid; place-items: center; font-size: 19px; flex-shrink: 0; }
.cr-kpi-lbl { font-size: 12px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: .04em; }
.cr-kpi-val { font-size: 26px; font-weight: 800; color: #0f172a; line-height: 1.15; }
.cr-kpi-val small { font-size: 13px; font-weight: 700; color: #64748b; margin-left: 4px; }
.cr-kpi.total .cr-kpi-ico { background: #eff6ff; color: #2563eb; }
.cr-kpi.yes { border-color: #bbf7d0; }
.cr-kpi.yes .cr-kpi-ico { background: #dcfce7; color: #15803d; }
.cr-kpi.yes .cr-kpi-val { color: #15803d; }
.cr-kpi.no { border-color: #fecaca; }
.cr-kpi.no .cr-kpi-ico { background: #fee2e2; color: #b91c1c; }
.cr-kpi.no .cr-kpi-val { color: #b91c1c; }

.cr-filters {
    padding: 14px 16px 4px; margin-bottom: 18px; border-radius: 12px;
    background: #f8fafc; border: 1px solid #e2e8f0;
}

.cr-section-title {
    display: flex; align-items: center; justify-content: space-between; gap: 10px;
    margin: 4px 0 10px; font-size: 15px; font-weight: 800; color: #0f172a;
}
.cr-section-title span.count {
    font-size: 12px; font-weight: 700; color: #64748b; background: #f1f5f9; padding: 3px 10px; border-radius: 999px;
}

.cr-table { width: 100%; border-collapse: separate; border-spacing: 0; }
.cr-table thead th {
    background: #1e293b; color: #fff; font-size: 12px; font-weight: 700; text-transform: uppercase;
    letter-spacing: .03em; padding: 11px 12px; text-align: left; white-space: nowrap;
}
.cr-table thead th:first-child { border-top-left-radius: 10px; }
.cr-table thead th:last-child { border-top-right-radius: 10px; }
.cr-table tbody td { padding: 10px 12px; font-size: 13px; border-bottom: 1px solid #eef2f7; color: #1e293b; }
.cr-table tbody tr:nth-child(even) td { background: #fafbfd; }
.cr-table tbody tr:hover td { background: #fff5f5; }
.cr-table .ctr { text-align: center; }
.cr-table tfoot td {
    padding: 11px 12px; font-weight: 800; font-size: 13px; background: #f1f5f9; border-top: 2px solid #cbd5e1;
}
.cr-table a.cr-emp { color: #0f172a; font-weight: 700; text-decoration: none; }
.cr-table a.cr-emp:hover { color: #d2232a; text-decoration: underline; }
.cr-code { font-weight: 800; color: #475569; }

.cr-pill { display: inline-flex; align-items: center; gap: 5px; padding: 3px 11px; border-radius: 999px; font-size: 12px; font-weight: 800; }
.cr-pill.yes { background: #dcfce7; color: #15803d; }
.cr-pill.no { background: #fee2e2; color: #b91c1c; }
.cr-num-yes { color: #15803d; font-weight: 800; }
.cr-num-no { color: #b91c1c; font-weight: 800; }

.cr-bar { display: flex; align-items: center; gap: 8px; min-width: 150px; }
.cr-bar-track { flex: 1; height: 8px; border-radius: 999px; background: #fee2e2; overflow: hidden; }
.cr-bar-fill { height: 100%; border-radius: 999px; background: linear-gradient(90deg, #22c55e, #15803d); }
.cr-bar-pct { font-size: 12px; font-weight: 800; color: #334155; min-width: 36px; text-align: right; }

.cr-empty { text-align: center; padding: 34px 10px !important; color: #64748b; }
.cr-empty i { display: block; font-size: 28px; color: #cbd5e1; margin-bottom: 8px; }

@media (max-width: 900px) { .cr-kpis { grid-template-columns: 1fr; } }
</style>

<main class="dashboard-main">
    <div class="page-toolbar flex-between">
        <a href="<?php echo app_url('employees/index.php' . ($deptId > 0 ? '?department_id=' . $deptId : '')); ?>" class="back-link">
            <i class="fa-solid fa-arrow-left"></i> Back to Employees
        </a>
        <div class="toolbar-actions" style="display:flex;gap:8px;flex-wrap:wrap;">
            <a href="<?php echo htmlspecialchars($exportUrl); ?>" class="btn-secondary">
                <i class="fa-solid fa-file-excel" style="color:#15803d;"></i> Excel
            </a>
            <a href="<?php echo htmlspecialchars($printUrl); ?>" class="btn-primary" target="_blank" rel="noopener">
                <i class="fa-solid fa-print"></i> Print / PDF
            </a>
        </div>
    </div>

    <div class="form-page-card">
        <div class="cr-hero">
            <div class="cr-hero-left">
                <div class="cr-hero-ico"><i class="fa-solid fa-utensils"></i></div>
                <div>
                    <h1>Canteen Use Report</h1>
                    <p>Active employees · Canteen facility usage (Yes / No)</p>
                </div>
            </div>
            <div style="display:flex;gap:8px;flex-wrap:wrap;">
                <span class="cr-chip"><i class="fa-solid fa-building"></i> <?php echo htmlspecialchars($deptLabel); ?></span>
                <span class="cr-chip"><i class="fa-regular fa-calendar"></i> <?php echo date('d-m-Y'); ?></span>
            </div>
        </div>

        <div class="cr-kpis">
            <div class="cr-kpi total">
                <div class="cr-kpi-ico"><i class="fa-solid fa-users"></i></div>
                <div>
                    <div class="cr-kpi-lbl">Total Employees</div>
                    <div class="cr-kpi-val"><?php echo $totalAll; ?></div>
                </div>
            </div>
            <div class="cr-kpi yes">
                <div class="cr-kpi-ico"><i class="fa-solid fa-circle-check"></i></div>
                <div>
                    <div class="cr-kpi-lbl">Canteen Yes</div>
                    <div class="cr-kpi-val"><?php echo $totalYes; ?><small><?php echo $yesPct; ?>%</small></div>
                </div>
            </div>
            <div class="cr-kpi no">
                <div class="cr-kpi-ico"><i class="fa-solid fa-circle-xmark"></i></div>
                <div>
                    <div class="cr-kpi-lbl">Canteen No</div>
                    <div class="cr-kpi-val"><?php echo $totalNo; ?><small><?php echo $noPct; ?>%</small></div>
                </div>
            </div>
        </div>

        <form method="GET" class="employee-form cr-filters">
            <div class="form-grid form-grid-3">
                <div class="form-group">
                    <label>Department</label>
                    <select name="department_id" class="form-control" onchange="this.form.submit()">
                        <option value="0">All Departments</option>
                        <?php foreach ($departments as $d): ?>
                            <option value="<?php echo (int) $d['id']; ?>" <?php echo $deptId === (int) $d['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($d['department_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Canteen Use</label>
                    <select name="canteen" class="form-control" onchange="this.form.submit()">
                        <option value="all" <?php echo $canteenFilter === 'all' ? 'selected' : ''; ?>>All</option>
                        <option value="Yes" <?php echo $canteenFilter === 'Yes' ? 'selected' : ''; ?>>Yes</option>
                        <option value="No" <?php echo $canteenFilter === 'No' ? 'selected' : ''; ?>>No</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Search</label>
                    <div style="display:flex;gap:8px;">
                        <input type="text" name="q" class="form-control" value="<?php echo htmlspecialchars($q); ?>"
                               placeholder="Name / Code / Designation">
                        <button type="submit" class="btn-primary"><i class="fa-solid fa-magnifying-glass"></i></button>
                    </div>
                </div>
            </div>
        </form>

        <?php if ($showDeptSummary): ?>
        <div class="cr-section-title">
            <div><i class="fa-solid fa-chart-column" style="color:#d2232a;"></i> Department-wise Summary</div>
            <span class="count"><?php echo count($deptSummary); ?> departments</span>
        </div>
        <div class="table-wrap" style="margin-bottom:24px;">
            <table class="cr-table">
                <thead>
                    <tr>
                        <th style="width:60px;">Sr</th>
                        <th>Department</th>
                        <th class="ctr">Total</th>
                        <th class="ctr">Canteen Yes</th>
                        <th class="ctr">Canteen No</th>
                        <th style="width:220px;">Usage %</th>
                    </tr>
                </thead>
                <tbody>
                <?php $sr = 1; foreach ($deptSummary as $dname => $s):
                    $pct = $s['total'] > 0 ? round($s['yes'] * 100 / $s['total']) : 0;
                ?>
                    <tr>
                        <td><?php echo $sr++; ?></td>
                        <td><strong><?php echo htmlspecialchars($dname); ?></strong></td>
                        <td class="ctr"><?php echo $s['total']; ?></td>
                        <td class="ctr cr-num-yes"><?php echo $s['yes']; ?></td>
                        <td class="ctr cr-num-no"><?php echo $s['no']; ?></td>
                        <td>
                            <div class="cr-bar">
                                <div class="cr-bar-track"><div class="cr-bar-fill" style="width:<?php echo $pct; ?>%;"></div></div>
                                <span class="cr-bar-pct"><?php echo $pct; ?>%</span>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td></td>
                        <td>TOTAL</td>
                        <td class="ctr"><?php echo $totalAll; ?></td>
                        <td class="ctr cr-num-yes"><?php echo $totalYes; ?></td>
                        <td class="ctr cr-num-no"><?php echo $totalNo; ?></td>
                        <td>
                            <div class="cr-bar">
                                <div class="cr-bar-track"><div class="cr-bar-fill" style="width:<?php echo $yesPct; ?>%;"></div></div>
                                <span class="cr-bar-pct"><?php echo $yesPct; ?>%</span>
                            </div>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <?php endif; ?>

        <div class="cr-section-title">
            <div><i class="fa-solid fa-list-ul" style="color:#d2232a;"></i> Employee List</div>
            <span class="count"><?php echo count($rows); ?> employees</span>
        </div>
        <div class="table-wrap">
            <table class="cr-table">
                <thead>
                    <tr>
                        <th style="width:60px;">Sr</th>
                        <th>Emp. Code</th>
                        <th>Employee</th>
                        <th>Department</th>
                        <th>Designation</th>
                        <th>Mobile</th>
                        <th>Shift</th>
                        <th class="ctr">Canteen Use</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$rows): ?>
                    <tr><td colspan="8" class="cr-empty"><i class="fa-solid fa-utensils"></i>No employees found for selected filters.</td></tr>
                <?php else: foreach ($rows as $i => $r): ?>
                    <tr>
                        <td><?php echo $i + 1; ?></td>
                        <td class="cr-code"><?php echo htmlspecialchars((string) $r['employee_code']); ?></td>
                        <td>
                            <a class="cr-emp" href="<?php echo app_url('employees/view.php?id=' . (int) $r['id']); ?>">
                                <?php echo htmlspecialchars((string) $r['employee_name']); ?>
                            </a>
                        </td>
                        <td><?php echo htmlspecialchars((string) ($r['department_name'] ?: '—')); ?></td>
                        <td><?php echo htmlspecialchars((string) ($r['designation'] ?: '—')); ?></td>
                        <td><?php echo htmlspecialchars((string) ($r['mobile_number'] ?: '—')); ?></td>
                        <td><?php echo htmlspecialchars((string) ($r['shift_time'] ?: '—')); ?></td>
                        <td class="ctr">
                            <?php if ($r['canteen_use'] === 'Yes'): ?>
                                <span class="cr-pill yes"><i class="fa-solid fa-check"></i> Yes</span>
                            <?php else: ?>
                                <span class="cr-pill no"><i class="fa-solid fa-xmark"></i> No</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
