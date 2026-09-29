<?php
/**
 * Employee Voice Report — A4 Print / PDF
 * Uses company document header / footer / watermark
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permission_helper.php';
require_once __DIR__ . '/../includes/settings.php';
require_once __DIR__ . '/../includes/document_print.php';
require_once __DIR__ . '/../includes/employee_voice_helper.php';
require_once __DIR__ . '/../includes/employee_voice_report_helper.php';

requireLogin();
if (!canManageEmployeeVoice()) {
    header('Location: ' . app_url('dashboard.php'));
    exit;
}

$data = evReportLoadData();
$types = $data['types'];
$rows = $data['rows'];
$m = $data['meta'];
$query = $data['query'];
$brand = getCompanyDocumentBranding();

$typeLabel = 'All Categories';
if ($m['type'] !== '' && isset($types[$m['type']])) {
    $typeLabel = $types[$m['type']]['label'];
}

$backUrl = evReportQueryUrl('employee_voice/report.php', $query);
$periodLabel = formatDateDisplay($m['date_from']) . ' — ' . formatDateDisplay($m['date_to']);
$printedAt = date('d-m-Y H:i');

// Split rows into pages (~18 data rows per A4 page after header/summary)
$perPage = 18;
$chunks = $rows ? array_chunk($rows, $perPage) : [[]];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Employee Voice Report</title>
    <style>
        <?php echo companyDocPrintCss(); ?>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            background: #e5e7eb;
            color: #111;
            font-family: Calibri, Candara, Segoe UI, Arial, Helvetica, sans-serif;
            font-size: 12px;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        .evp-toolbar {
            position: sticky; top: 0; z-index: 40;
            display: flex; justify-content: space-between; align-items: center;
            gap: 10px; padding: 12px 18px; background: #111; color: #fff;
        }
        .evp-btn {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 8px 14px; border-radius: 8px; background: #333;
            color: #fff; text-decoration: none; border: 0; cursor: pointer;
            font-size: 13px; font-weight: 700; font-family: inherit;
        }
        .evp-btn.primary { background: #d2232a; }
        .evp-page {
            width: 210mm; min-height: 297mm; margin: 16px auto;
            background: #fff; position: relative; overflow: hidden;
            box-shadow: 0 8px 28px rgba(0,0,0,.12);
            border: 2.25px solid #000;
        }
        .evp-inner {
            min-height: 297mm; padding: 10mm 12mm 10mm;
            display: flex; flex-direction: column;
        }
        .cdoc-header { flex-shrink: 0; margin-bottom: 10px; }
        .cdoc-header-logo { width: 78px; height: 78px; }
        .cdoc-header-text .cdoc-company {
            font-size: 22px; font-weight: 800; color: #b91c1c; margin: 0 0 4px;
            text-transform: uppercase;
        }
        .cdoc-header-text .cdoc-header-details {
            font-size: 12px; font-weight: 700; color: #1e293b; line-height: 1.4;
        }
        .cdoc-footer {
            margin-top: auto; padding-top: 8px; flex-shrink: 0;
            font-size: 10px; line-height: 1.4;
        }
        .cdoc-watermark img { width: min(48%, 280px); max-height: 280px; opacity: .06; }
        .evp-title {
            text-align: center; margin: 4px 0 8px;
            font-size: 18px; font-weight: 800; letter-spacing: .04em;
            text-transform: uppercase; text-decoration: underline;
            text-underline-offset: 3px;
        }
        .evp-meta {
            display: flex; flex-wrap: wrap; gap: 8px 16px;
            margin-bottom: 10px; font-size: 11.5px; font-weight: 700; color: #334155;
        }
        .evp-meta span { white-space: nowrap; }
        .evp-kpis {
            display: grid; grid-template-columns: repeat(6, 1fr); gap: 6px;
            margin-bottom: 10px;
        }
        .evp-kpi {
            border: 1px solid #cbd5e1; border-radius: 6px; padding: 6px 8px; text-align: center;
        }
        .evp-kpi .l { font-size: 9.5px; font-weight: 800; color: #64748b; text-transform: uppercase; }
        .evp-kpi .v { font-size: 16px; font-weight: 800; color: #0f172a; }
        .evp-table {
            width: 100%; border-collapse: collapse; table-layout: fixed; font-size: 10.5px;
        }
        .evp-table th, .evp-table td {
            border: 1px solid #1e293b; padding: 5px 5px; vertical-align: top; word-break: break-word;
        }
        .evp-table th {
            background: #f1f5f9; font-weight: 800; text-transform: uppercase;
            font-size: 9.5px; text-align: center;
        }
        .evp-table .ctr { text-align: center; }
        .evp-badge {
            display: inline-block; padding: 1px 6px; border-radius: 999px;
            font-size: 9.5px; font-weight: 800;
        }
        .evp-empty { text-align: center; padding: 28px 10px; color: #64748b; font-weight: 700; }
        .evp-page-no {
            text-align: right; font-size: 10px; color: #64748b; margin-top: 6px; font-weight: 700;
        }
        @media print {
            @page { size: A4; margin: 0; }
            .no-print { display: none !important; }
            body { background: #fff; }
            .evp-page {
                width: 210mm; min-height: 297mm; height: 297mm;
                margin: 0; box-shadow: none; border: 0;
                page-break-after: always; break-after: page;
            }
            .evp-page:last-child { page-break-after: auto; break-after: auto; }
            .evp-inner { min-height: 297mm; height: 297mm; }
        }
    </style>
</head>
<body>
<div class="evp-toolbar no-print">
    <a class="evp-btn" href="<?php echo htmlspecialchars($backUrl); ?>">← Back to Report</a>
    <button type="button" class="evp-btn primary" onclick="window.print()">Print / Save as PDF</button>
</div>

<?php foreach ($chunks as $pageIndex => $pageRows): ?>
<section class="evp-page cdoc-sheet">
    <?php echo companyDocRenderWatermark($brand); ?>
    <div class="evp-inner cdoc-inner">
        <?php echo companyDocRenderHeader($brand, 'Human Resource · Employee Voice Report'); ?>

        <h1 class="evp-title">Employee Voice Report</h1>
        <div class="evp-meta">
            <span>Type: <?php echo htmlspecialchars($typeLabel); ?></span>
            <span>Period: <?php echo htmlspecialchars($periodLabel); ?></span>
            <span>Printed: <?php echo htmlspecialchars($printedAt); ?></span>
        </div>

        <?php if ($pageIndex === 0): ?>
        <div class="evp-kpis">
            <div class="evp-kpi"><div class="l">Total</div><div class="v"><?php echo (int) ($m['total'] ?? count($rows)); ?></div></div>
            <div class="evp-kpi"><div class="l">Open</div><div class="v"><?php echo (int) ($m['open'] ?? 0); ?></div></div>
            <div class="evp-kpi"><div class="l">Closed</div><div class="v"><?php echo (int) ($m['closed'] ?? 0); ?></div></div>
            <?php foreach ($types as $t): ?>
            <div class="evp-kpi" style="background:<?php echo htmlspecialchars($t['bg']); ?>;border-color:<?php echo htmlspecialchars($t['color']); ?>;">
                <div class="l" style="color:<?php echo htmlspecialchars($t['color']); ?>;"><?php echo htmlspecialchars($t['short']); ?></div>
                <div class="v" style="color:<?php echo htmlspecialchars($t['color']); ?>;"><?php echo (int) (($m['by_type'][$t['key']] ?? 0)); ?></div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <table class="evp-table">
            <thead>
                <tr>
                    <th style="width:5%;">Sr</th>
                    <th style="width:12%;">Ticket</th>
                    <th style="width:9%;">Type</th>
                    <th style="width:16%;">Employee</th>
                    <th style="width:12%;">Department</th>
                    <th style="width:12%;">Category</th>
                    <th style="width:18%;">Subject</th>
                    <th style="width:8%;">Priority</th>
                    <th style="width:8%;">Status</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$pageRows): ?>
                <tr><td colspan="9" class="evp-empty">No employee voice tickets found for selected filters.</td></tr>
            <?php else: ?>
                <?php foreach ($pageRows as $j => $r):
                    $sr = ($pageIndex * $perPage) + $j + 1;
                    $mt = (string) ($r['module_type'] ?? '');
                    $meta = $types[$mt] ?? null;
                    $isAnon = (($r['confidentiality'] ?? '') === 'Anonymous');
                    $empLabel = $isAnon
                        ? 'Anonymous'
                        : trim((string) (($r['employee_code'] ?? '') . ' ' . ($r['employee_name'] ?? '')));
                ?>
                <tr>
                    <td class="ctr"><?php echo $sr; ?></td>
                    <td><?php echo htmlspecialchars((string) ($r['ticket_no'] ?? '')); ?></td>
                    <td class="ctr">
                        <span class="evp-badge" style="background:<?php echo htmlspecialchars($meta['bg'] ?? '#eee'); ?>;color:<?php echo htmlspecialchars($meta['color'] ?? '#333'); ?>;">
                            <?php echo htmlspecialchars($meta['short'] ?? $mt); ?>
                        </span>
                    </td>
                    <td><?php echo htmlspecialchars($empLabel !== '' ? $empLabel : '—'); ?></td>
                    <td><?php echo htmlspecialchars((string) ($r['department_name'] ?? '—')); ?></td>
                    <td><?php echo htmlspecialchars((string) ($r['category'] ?? '—')); ?></td>
                    <td><?php echo htmlspecialchars((string) ($r['subject'] ?? '')); ?></td>
                    <td class="ctr"><?php echo htmlspecialchars((string) ($r['priority'] ?? '')); ?></td>
                    <td class="ctr"><?php echo htmlspecialchars((string) ($r['status'] ?? '')); ?></td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>

        <div class="evp-page-no">Page <?php echo $pageIndex + 1; ?> of <?php echo count($chunks); ?></div>
        <?php echo companyDocRenderFooter($brand); ?>
    </div>
</section>
<?php endforeach; ?>
</body>
</html>
