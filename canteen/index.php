<?php
/**
 * Canteen Meal List — bookings for a date, department-wise + employee-wise.
 * ?export=print → A4 letterhead (Print / Save as PDF) for the Canteen Co-ordinator.
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permission_helper.php';
require_once __DIR__ . '/../includes/department_head_helper.php';
require_once __DIR__ . '/../includes/settings.php';
require_once __DIR__ . '/../includes/document_print.php';
require_once __DIR__ . '/../includes/canteen_helper.php';

requireLogin();
$canView = isAdmin() || isHR() || (canAccess('employees', 'view') && !(function_exists('isOfficeStaffRole') && isOfficeStaffRole()));
if (!$canView) {
    header('Location: ' . app_url('dashboard.php'));
    exit;
}

$mealDate = (string) ($_GET['date'] ?? '');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $mealDate) || !strtotime($mealDate)) {
    $mealDate = canteenTargetDate();
}
$deptId = (int) ($_GET['department_id'] ?? 0);
$exportMode = (string) ($_GET['export'] ?? '');

$allowedDepts = isAdmin() || isHR() ? null : allowedDepartmentsFor('employees', 'view');

$conn = getDBConnection();
ensureCanteenTables($conn);

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
if ($deptId > 0 && is_array($allowedDepts) && !in_array($deptId, array_map('intval', $allowedDepts), true)) {
    $deptId = 0;
}

$rows = canteenOrdersForDate($conn, $mealDate, $deptId, $allowedDepts);
$conn->close();

$summary = canteenSummarize($rows);
$byDept = $summary['by_dept'];
$tot = $summary['total'];

$deptLabel = 'All Departments';
foreach ($departments as $d) {
    if ((int) $d['id'] === $deptId) {
        $deptLabel = (string) $d['department_name'];
    }
}
$isTomorrow = ($mealDate === canteenTargetDate());
$isOpen = canteenIsOpen($mealDate);
$cutoffAt = canteenCutoffAt($mealDate);
$cutoffText = date('d-m-Y h:i A', $cutoffAt);
$printedAt = date('d-m-Y h:i A');

$baseQuery = ['date' => $mealDate, 'department_id' => $deptId];
$printUrl = app_url('canteen/index.php?' . http_build_query($baseQuery + ['export' => 'print']));
$screenUrl = app_url('canteen/index.php?' . http_build_query($baseQuery));

// Employee rows grouped by department (keeps department order from the query)
$grouped = [];
foreach ($rows as $r) {
    $grouped[(string) $r['department_name']][] = $r;
}

/* ===================== PRINT (A4 letterhead) ===================== */
if ($exportMode === 'print') {
    $brand = getCompanyDocumentBranding();

    // Flatten into printable lines: department header + employee rows + subtotal
    $lines = [];
    $sr = 0;
    foreach ($grouped as $dname => $list) {
        $lines[] = ['type' => 'dept', 'name' => $dname, 'count' => count($list)];
        foreach ($list as $r) {
            $sr++;
            $lines[] = ['type' => 'emp', 'sr' => $sr, 'row' => $r];
        }
        $lines[] = ['type' => 'sub', 'name' => $dname, 's' => $byDept[$dname]];
    }

    $pages = [];
    $firstCap = max(6, 26 - count($byDept));
    $pages[] = array_slice($lines, 0, $firstCap);
    foreach (array_chunk(array_slice($lines, $firstCap), 34) as $chunk) {
        $pages[] = $chunk;
    }
    $totalPages = count($pages);
    $mark = static function ($v) {
        return (int) $v === 1 ? '&#10004;' : '—';
    };
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Canteen Meal List <?php echo htmlspecialchars(date('d-m-Y', strtotime($mealDate))); ?></title>
    <style>
        <?php echo companyDocPrintCss(); ?>
        * { box-sizing: border-box; }
        body {
            margin: 0; background: #e5e7eb; color: #111;
            font-family: Calibri, Candara, Segoe UI, Arial, Helvetica, sans-serif; font-size: 12px;
            -webkit-print-color-adjust: exact; print-color-adjust: exact;
        }
        .cp-toolbar {
            position: sticky; top: 0; z-index: 40; display: flex; justify-content: space-between; align-items: center;
            gap: 10px; padding: 12px 18px; background: #111; color: #fff;
        }
        .cp-btn {
            display: inline-flex; align-items: center; gap: 6px; padding: 8px 14px; border-radius: 8px; background: #333;
            color: #fff; text-decoration: none; border: 0; cursor: pointer; font-size: 13px; font-weight: 700; font-family: inherit;
        }
        .cp-btn.primary { background: #d2232a; }
        .cp-page {
            width: 210mm; min-height: 297mm; margin: 16px auto; background: #fff; position: relative; overflow: hidden;
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
            text-align: center; margin: 4px 0 6px; font-size: 18px; font-weight: 800; letter-spacing: .04em;
            text-transform: uppercase; text-decoration: underline; text-underline-offset: 3px;
        }
        .cp-date { text-align: center; font-size: 14px; font-weight: 800; color: #b91c1c; margin-bottom: 4px; }
        .cp-meta {
            display: flex; flex-wrap: wrap; justify-content: center; gap: 4px 18px;
            margin-bottom: 10px; font-size: 11px; font-weight: 700; color: #334155;
        }
        .cp-kpis { display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; margin-bottom: 12px; }
        .cp-kpi { border: 1px solid #cbd5e1; border-radius: 6px; padding: 7px 8px; text-align: center; }
        .cp-kpi .l { font-size: 10px; font-weight: 800; color: #64748b; text-transform: uppercase; }
        .cp-kpi .v { font-size: 20px; font-weight: 800; color: #0f172a; }
        .cp-kpi.b { background: #fffbeb; border-color: #fcd34d; } .cp-kpi.b .v, .cp-kpi.b .l { color: #b45309; }
        .cp-kpi.l2 { background: #f0fdf4; border-color: #86efac; } .cp-kpi.l2 .v, .cp-kpi.l2 .l { color: #15803d; }
        .cp-kpi.d { background: #eef2ff; border-color: #a5b4fc; } .cp-kpi.d .v, .cp-kpi.d .l { color: #4338ca; }
        .cp-sub { margin: 4px 0 6px; font-size: 12.5px; font-weight: 800; text-transform: uppercase; color: #1e293b; }
        .cp-table { width: 100%; border-collapse: collapse; table-layout: fixed; font-size: 11px; margin-bottom: 10px; }
        .cp-table th, .cp-table td { border: 1px solid #1e293b; padding: 4px 6px; vertical-align: middle; word-break: break-word; }
        .cp-table th { background: #f1f5f9; font-weight: 800; text-transform: uppercase; font-size: 10px; text-align: center; }
        .cp-table .ctr { text-align: center; }
        .cp-table tfoot td, .cp-table tr.sub td { background: #f1f5f9; font-weight: 800; }
        .cp-table tr.dept td { background: #fee2e2; color: #991b1b; font-weight: 800; text-transform: uppercase; font-size: 10.5px; }
        .cp-table tr.grand td { background: #1e293b; color: #fff; font-weight: 800; }
        .cp-empty { text-align: center; padding: 26px 10px; color: #64748b; font-weight: 700; }
        .cp-sign { display: flex; justify-content: space-between; gap: 30px; margin-top: 26px; font-size: 11.5px; font-weight: 700; }
        .cp-sign div { flex: 1; border-top: 1.5px solid #111; padding-top: 6px; text-align: center; }
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
    <a class="cp-btn" href="<?php echo htmlspecialchars($screenUrl); ?>">← Back to Meal List</a>
    <button type="button" class="cp-btn primary" onclick="window.print()">Print / Save as PDF</button>
</div>

<?php foreach ($pages as $pageIndex => $pageLines): $isLast = ($pageIndex === $totalPages - 1); ?>
<section class="cp-page cdoc-sheet">
    <?php echo companyDocRenderWatermark($brand); ?>
    <div class="cp-inner cdoc-inner">
        <?php echo companyDocRenderHeader($brand, 'Human Resource · Canteen Meal List'); ?>

        <h1 class="cp-title">Canteen Meal List</h1>
        <div class="cp-date">Meal Date: <?php echo htmlspecialchars(canteenDateLabel($mealDate)); ?></div>
        <div class="cp-meta">
            <span>Department: <?php echo htmlspecialchars($deptLabel); ?></span>
            <span>Booking closed: <?php echo htmlspecialchars($cutoffText); ?></span>
            <span>Printed: <?php echo htmlspecialchars($printedAt); ?></span>
        </div>

        <?php if ($pageIndex === 0): ?>
        <div class="cp-kpis">
            <div class="cp-kpi"><div class="l">Employees</div><div class="v"><?php echo $tot['employees']; ?></div></div>
            <div class="cp-kpi b"><div class="l">Breakfast</div><div class="v"><?php echo $tot['breakfast']; ?></div></div>
            <div class="cp-kpi l2"><div class="l">Lunch</div><div class="v"><?php echo $tot['lunch']; ?></div></div>
            <div class="cp-kpi d"><div class="l">Dinner</div><div class="v"><?php echo $tot['dinner']; ?></div></div>
        </div>

        <?php if ($byDept): ?>
        <div class="cp-sub">Department-wise Summary</div>
        <table class="cp-table">
            <thead>
                <tr>
                    <th style="width:7%;">Sr</th>
                    <th style="width:41%;">Department</th>
                    <th style="width:13%;">Employees</th>
                    <th style="width:13%;">Breakfast</th>
                    <th style="width:13%;">Lunch</th>
                    <th style="width:13%;">Dinner</th>
                </tr>
            </thead>
            <tbody>
            <?php $i = 1; foreach ($byDept as $dname => $s): ?>
                <tr>
                    <td class="ctr"><?php echo $i++; ?></td>
                    <td><?php echo htmlspecialchars($dname); ?></td>
                    <td class="ctr"><?php echo $s['employees']; ?></td>
                    <td class="ctr"><?php echo $s['breakfast']; ?></td>
                    <td class="ctr"><?php echo $s['lunch']; ?></td>
                    <td class="ctr"><?php echo $s['dinner']; ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="2" style="text-align:right;">TOTAL</td>
                    <td class="ctr"><?php echo $tot['employees']; ?></td>
                    <td class="ctr"><?php echo $tot['breakfast']; ?></td>
                    <td class="ctr"><?php echo $tot['lunch']; ?></td>
                    <td class="ctr"><?php echo $tot['dinner']; ?></td>
                </tr>
            </tfoot>
        </table>
        <?php endif; ?>
        <?php endif; ?>

        <?php if ($pageLines || $pageIndex === 0): ?>
        <div class="cp-sub">Employee-wise List</div>
        <table class="cp-table">
            <thead>
                <tr>
                    <th style="width:7%;">Sr</th>
                    <th style="width:14%;">Emp. Code</th>
                    <th style="width:34%;">Employee Name</th>
                    <th style="width:15%;">Breakfast</th>
                    <th style="width:15%;">Lunch</th>
                    <th style="width:15%;">Dinner</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$rows): ?>
                <tr><td colspan="6" class="cp-empty">No meal booking for this date.</td></tr>
            <?php endif; ?>
            <?php foreach ($pageLines as $ln): ?>
                <?php if ($ln['type'] === 'dept'): ?>
                <tr class="dept"><td colspan="6"><?php echo htmlspecialchars($ln['name']); ?> (<?php echo (int) $ln['count']; ?>)</td></tr>
                <?php elseif ($ln['type'] === 'emp'): $r = $ln['row']; ?>
                <tr>
                    <td class="ctr"><?php echo (int) $ln['sr']; ?></td>
                    <td><?php echo htmlspecialchars((string) $r['employee_code']); ?></td>
                    <td><?php echo htmlspecialchars((string) ($r['employee_name'] ?? '')); ?></td>
                    <td class="ctr"><?php echo $mark($r['breakfast']); ?></td>
                    <td class="ctr"><?php echo $mark($r['lunch']); ?></td>
                    <td class="ctr"><?php echo $mark($r['dinner']); ?></td>
                </tr>
                <?php else: $s = $ln['s']; ?>
                <tr class="sub">
                    <td colspan="3" style="text-align:right;">Sub Total — <?php echo htmlspecialchars($ln['name']); ?></td>
                    <td class="ctr"><?php echo $s['breakfast']; ?></td>
                    <td class="ctr"><?php echo $s['lunch']; ?></td>
                    <td class="ctr"><?php echo $s['dinner']; ?></td>
                </tr>
                <?php endif; ?>
            <?php endforeach; ?>
            <?php if ($isLast && $rows): ?>
                <tr class="grand">
                    <td colspan="3" style="text-align:right;">GRAND TOTAL (<?php echo $tot['employees']; ?> employees)</td>
                    <td class="ctr"><?php echo $tot['breakfast']; ?></td>
                    <td class="ctr"><?php echo $tot['lunch']; ?></td>
                    <td class="ctr"><?php echo $tot['dinner']; ?></td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
        <?php endif; ?>

        <?php if ($isLast): ?>
        <div class="cp-sign">
            <div>Prepared By (HR)</div>
            <div>Received By (Canteen Co-ordinator)</div>
        </div>
        <?php endif; ?>

        <div class="cp-page-no">Page <?php echo $pageIndex + 1; ?> of <?php echo $totalPages; ?></div>
        <?php echo companyDocRenderFooter($brand); ?>
    </div>
</section>
<?php endforeach; ?>
</body>
</html>
    <?php
    exit;
}

/* ===================== SCREEN ===================== */
$pageTitle = 'Canteen Meal List';
$useSidebar = true;
$sidebarMode = 'canteen';
$sidebarActive = 'canteen_orders';

require_once __DIR__ . '/../includes/header.php';
?>
<style>
.cm-hero {
    display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: wrap;
    padding: 18px 20px; margin-bottom: 18px; border-radius: 14px;
    background: linear-gradient(120deg, #fff5f5 0%, #ffffff 60%); border: 1px solid #fde2e2;
}
.cm-hero-left { display: flex; align-items: center; gap: 14px; }
.cm-hero-ico {
    width: 52px; height: 52px; border-radius: 14px; display: grid; place-items: center; font-size: 22px; color: #fff;
    background: linear-gradient(135deg, #d2232a, #9f1239); box-shadow: 0 8px 20px rgba(210, 35, 42, .25);
}
.cm-hero h1 { margin: 0; font-size: 22px; font-weight: 800; color: #0f172a; }
.cm-hero p { margin: 3px 0 0; font-size: 13px; color: #64748b; }
.cm-status { display: inline-flex; align-items: center; gap: 6px; padding: 6px 12px; border-radius: 999px; font-size: 12.5px; font-weight: 800; }
.cm-status.open { background: #dcfce7; color: #15803d; }
.cm-status.closed { background: #fee2e2; color: #b91c1c; }

.cm-kpis { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 14px; margin-bottom: 18px; }
.cm-kpi {
    padding: 16px 18px; border-radius: 14px; background: #fff; border: 1px solid #e2e8f0;
    box-shadow: 0 2px 10px rgba(15, 23, 42, .04); display: flex; align-items: center; gap: 14px;
}
.cm-kpi-ico { width: 46px; height: 46px; border-radius: 12px; display: grid; place-items: center; font-size: 19px; flex-shrink: 0; }
.cm-kpi-lbl { font-size: 12px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: .04em; }
.cm-kpi-val { font-size: 26px; font-weight: 800; line-height: 1.15; }
.cm-kpi.emp .cm-kpi-ico { background: #eff6ff; color: #2563eb; } .cm-kpi.emp .cm-kpi-val { color: #0f172a; }
.cm-kpi.bf .cm-kpi-ico { background: #fef3c7; color: #b45309; } .cm-kpi.bf .cm-kpi-val { color: #b45309; }
.cm-kpi.ln .cm-kpi-ico { background: #dcfce7; color: #15803d; } .cm-kpi.ln .cm-kpi-val { color: #15803d; }
.cm-kpi.dn .cm-kpi-ico { background: #e0e7ff; color: #4338ca; } .cm-kpi.dn .cm-kpi-val { color: #4338ca; }

.cm-filters { padding: 14px 16px 4px; margin-bottom: 18px; border-radius: 12px; background: #f8fafc; border: 1px solid #e2e8f0; }
.cm-quick { display: flex; gap: 6px; flex-wrap: wrap; margin-top: 6px; }
.cm-quick a { font-size: 12px; font-weight: 700; padding: 4px 10px; border-radius: 999px; background: #fff; border: 1px solid #e2e8f0; color: #334155; text-decoration: none; }
.cm-quick a.on, .cm-quick a:hover { background: #d2232a; border-color: #d2232a; color: #fff; }

.cm-section-title { display: flex; align-items: center; justify-content: space-between; gap: 10px; margin: 4px 0 10px; font-size: 15px; font-weight: 800; color: #0f172a; }
.cm-section-title .count { font-size: 12px; font-weight: 700; color: #64748b; background: #f1f5f9; padding: 3px 10px; border-radius: 999px; }

.cm-table { width: 100%; border-collapse: separate; border-spacing: 0; }
.cm-table thead th {
    background: #1e293b; color: #fff; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: .03em;
    padding: 11px 12px; text-align: left; white-space: nowrap;
}
.cm-table thead th:first-child { border-top-left-radius: 10px; }
.cm-table thead th:last-child { border-top-right-radius: 10px; }
.cm-table tbody td { padding: 10px 12px; font-size: 13px; border-bottom: 1px solid #eef2f7; color: #1e293b; }
.cm-table tbody tr.emp:hover td { background: #fff5f5; }
.cm-table .ctr { text-align: center; }
.cm-table tr.dept td { background: #fef2f2; color: #991b1b; font-weight: 800; text-transform: uppercase; font-size: 12px; letter-spacing: .03em; }
.cm-table tr.sub td { background: #f8fafc; font-weight: 800; font-size: 12.5px; }
.cm-table tfoot td { padding: 11px 12px; font-weight: 800; font-size: 13px; background: #f1f5f9; border-top: 2px solid #cbd5e1; }
.cm-yes { display: inline-grid; place-items: center; width: 26px; height: 26px; border-radius: 50%; background: #dcfce7; color: #15803d; font-size: 12px; }
.cm-no { color: #cbd5e1; font-weight: 800; }
.cm-empty { text-align: center; padding: 38px 10px !important; color: #64748b; }
.cm-empty i { display: block; font-size: 30px; color: #cbd5e1; margin-bottom: 8px; }
@media (max-width: 900px) { .cm-kpis { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
</style>

<main class="dashboard-main">
    <div class="page-toolbar flex-between">
        <a href="<?php echo app_url('dashboard.php'); ?>" class="back-link">
            <i class="fa-solid fa-arrow-left"></i> Back to Dashboard
        </a>
        <div class="toolbar-actions" style="display:flex;gap:8px;flex-wrap:wrap;">
            <a href="<?php echo app_url('canteen/qr.php'); ?>" class="btn-secondary" target="_blank" rel="noopener">
                <i class="fa-solid fa-qrcode"></i> Canteen QR
            </a>
            <a href="<?php echo htmlspecialchars($printUrl); ?>" class="btn-primary" target="_blank" rel="noopener">
                <i class="fa-solid fa-file-pdf"></i> Print / PDF
            </a>
        </div>
    </div>

    <div class="form-page-card">
        <div class="cm-hero">
            <div class="cm-hero-left">
                <div class="cm-hero-ico"><i class="fa-solid fa-utensils"></i></div>
                <div>
                    <h1>Canteen Meal List</h1>
                    <p>Meal date: <strong><?php echo htmlspecialchars(canteenDateLabel($mealDate)); ?></strong> · <?php echo htmlspecialchars($deptLabel); ?></p>
                </div>
            </div>
            <?php if ($isOpen): ?>
                <span class="cm-status open"><i class="fa-solid fa-lock-open"></i> Booking open till <?php echo htmlspecialchars($cutoffText); ?></span>
            <?php else: ?>
                <span class="cm-status closed"><i class="fa-solid fa-lock"></i> Booking closed (<?php echo htmlspecialchars($cutoffText); ?>)</span>
            <?php endif; ?>
        </div>

        <div class="cm-kpis">
            <div class="cm-kpi emp"><div class="cm-kpi-ico"><i class="fa-solid fa-users"></i></div><div><div class="cm-kpi-lbl">Employees</div><div class="cm-kpi-val"><?php echo $tot['employees']; ?></div></div></div>
            <div class="cm-kpi bf"><div class="cm-kpi-ico"><i class="fa-solid fa-mug-hot"></i></div><div><div class="cm-kpi-lbl">Breakfast</div><div class="cm-kpi-val"><?php echo $tot['breakfast']; ?></div></div></div>
            <div class="cm-kpi ln"><div class="cm-kpi-ico"><i class="fa-solid fa-bowl-rice"></i></div><div><div class="cm-kpi-lbl">Lunch</div><div class="cm-kpi-val"><?php echo $tot['lunch']; ?></div></div></div>
            <div class="cm-kpi dn"><div class="cm-kpi-ico"><i class="fa-solid fa-moon"></i></div><div><div class="cm-kpi-lbl">Dinner</div><div class="cm-kpi-val"><?php echo $tot['dinner']; ?></div></div></div>
        </div>

        <form method="GET" class="employee-form cm-filters">
            <div class="form-grid form-grid-3">
                <div class="form-group">
                    <label>Meal Date</label>
                    <input type="date" name="date" class="form-control" value="<?php echo htmlspecialchars($mealDate); ?>" onchange="this.form.submit()">
                    <div class="cm-quick">
                        <?php
                        $today = date('Y-m-d');
                        $quick = [$today => 'Today', canteenTargetDate() => 'Tomorrow'];
                        foreach ($quick as $qd => $ql):
                        ?>
                            <a href="<?php echo htmlspecialchars(app_url('canteen/index.php?' . http_build_query(['date' => $qd, 'department_id' => $deptId]))); ?>"
                               class="<?php echo $mealDate === $qd ? 'on' : ''; ?>"><?php echo $ql; ?></a>
                        <?php endforeach; ?>
                    </div>
                </div>
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
                <div class="form-group" style="display:flex;align-items:flex-start;padding-top:24px;">
                    <button type="submit" class="btn-primary"><i class="fa-solid fa-filter"></i> Show</button>
                </div>
            </div>
        </form>

        <?php if ($byDept): ?>
        <div class="cm-section-title">
            <div><i class="fa-solid fa-chart-column" style="color:#d2232a;"></i> Department-wise Summary</div>
            <span class="count"><?php echo count($byDept); ?> departments</span>
        </div>
        <div class="table-wrap" style="margin-bottom:24px;">
            <table class="cm-table">
                <thead>
                    <tr>
                        <th style="width:60px;">Sr</th>
                        <th>Department</th>
                        <th class="ctr">Employees</th>
                        <th class="ctr">Breakfast</th>
                        <th class="ctr">Lunch</th>
                        <th class="ctr">Dinner</th>
                    </tr>
                </thead>
                <tbody>
                <?php $i = 1; foreach ($byDept as $dname => $s): ?>
                    <tr class="emp">
                        <td><?php echo $i++; ?></td>
                        <td><strong><?php echo htmlspecialchars($dname); ?></strong></td>
                        <td class="ctr"><?php echo $s['employees']; ?></td>
                        <td class="ctr" style="color:#b45309;font-weight:800;"><?php echo $s['breakfast']; ?></td>
                        <td class="ctr" style="color:#15803d;font-weight:800;"><?php echo $s['lunch']; ?></td>
                        <td class="ctr" style="color:#4338ca;font-weight:800;"><?php echo $s['dinner']; ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td></td>
                        <td>TOTAL</td>
                        <td class="ctr"><?php echo $tot['employees']; ?></td>
                        <td class="ctr"><?php echo $tot['breakfast']; ?></td>
                        <td class="ctr"><?php echo $tot['lunch']; ?></td>
                        <td class="ctr"><?php echo $tot['dinner']; ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <?php endif; ?>

        <div class="cm-section-title">
            <div><i class="fa-solid fa-list-ul" style="color:#d2232a;"></i> Employee-wise List</div>
            <span class="count"><?php echo $tot['employees']; ?> employees</span>
        </div>
        <div class="table-wrap">
            <table class="cm-table">
                <thead>
                    <tr>
                        <th style="width:60px;">Sr</th>
                        <th>Emp. Code</th>
                        <th>Employee</th>
                        <th>Designation</th>
                        <th class="ctr">Breakfast</th>
                        <th class="ctr">Lunch</th>
                        <th class="ctr">Dinner</th>
                        <th>Booked / Updated</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$rows): ?>
                    <tr><td colspan="8" class="cm-empty"><i class="fa-solid fa-utensils"></i>No meal booking for <?php echo htmlspecialchars(canteenDateLabel($mealDate)); ?>.</td></tr>
                <?php else: $sr = 0; foreach ($grouped as $dname => $list): ?>
                    <tr class="dept"><td colspan="8"><i class="fa-solid fa-building"></i> <?php echo htmlspecialchars($dname); ?> (<?php echo count($list); ?>)</td></tr>
                    <?php foreach ($list as $r): $sr++; ?>
                    <tr class="emp">
                        <td><?php echo $sr; ?></td>
                        <td><strong><?php echo htmlspecialchars((string) $r['employee_code']); ?></strong></td>
                        <td>
                            <a href="<?php echo app_url('employees/view.php?id=' . (int) $r['employee_id']); ?>" style="color:#0f172a;font-weight:700;text-decoration:none;">
                                <?php echo htmlspecialchars((string) ($r['employee_name'] ?? '')); ?>
                            </a>
                        </td>
                        <td><?php echo htmlspecialchars((string) ($r['designation'] ?: '—')); ?></td>
                        <?php foreach (['breakfast', 'lunch', 'dinner'] as $m): ?>
                        <td class="ctr"><?php echo (int) $r[$m] === 1 ? '<span class="cm-yes"><i class="fa-solid fa-check"></i></span>' : '<span class="cm-no">—</span>'; ?></td>
                        <?php endforeach; ?>
                        <td style="font-size:12px;color:#64748b;"><?php echo htmlspecialchars(date('d-m-Y h:i A', strtotime((string) $r['updated_at']))); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <tr class="sub">
                        <td colspan="4" style="text-align:right;">Sub Total — <?php echo htmlspecialchars($dname); ?></td>
                        <td class="ctr"><?php echo $byDept[$dname]['breakfast']; ?></td>
                        <td class="ctr"><?php echo $byDept[$dname]['lunch']; ?></td>
                        <td class="ctr"><?php echo $byDept[$dname]['dinner']; ?></td>
                        <td></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
