<?php
/**
 * CNC New Operation List — one letterhead PDF.
 * Job work (rate × qty) and attendance salary, same document.
 */
require_once __DIR__ . '/../_bootstrap.php';
require_once __DIR__ . '/../../includes/settings.php';
require_once __DIR__ . '/../../includes/document_print.php';
require_once __DIR__ . '/../../includes/contractor_split_helper.php';
require_once __DIR__ . '/../../includes/ops_clone_report.php';
require_once __DIR__ . '/../../includes/number_helper.php';

$employeeId = (int) ($_GET['employee_id'] ?? 0);
$month = (int) ($_GET['month'] ?? date('n'));
$year = (int) ($_GET['year'] ?? date('Y'));
$sheetId = (int) ($_GET['id'] ?? 0);
if ($month < 1 || $month > 12) {
    $month = (int) date('n');
}

$conn = getDBConnection();
ensureContractorTables($conn);
$report = opsCloneBuildCncReport($conn, $employeeId, $month, $year, $sheetId);
$conn->close();

$brand = getCompanyDocumentBranding();
$months = contractorMonths();
$monthLabel = ($months[$report['month']] ?? (string) $report['month']) . ' ' . $report['year'];
$emp = $report['employee'];
$empLabel = trim((string) ($emp['employee_code'] ?? '') . ' — ' . (string) ($emp['employee_name'] ?? ''));
$periodLabel = date('d-m-Y', strtotime($report['period_start'])) . ' to ' . date('d-m-Y', strtotime($report['period_end']));

$h = static function ($v) {
    return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
};
$money = static function ($n) {
    return formatIndianNumber((float) $n, 2);
};
$qtyFmt = static function ($n) {
    $n = (float) $n;
    if (abs($n - round($n)) < 0.001) {
        return formatIndianNumber($n, 0);
    }
    return formatIndianNumber($n, 2);
};

$backUrl = app_url('contractor/operations_clone/edit.php?' . http_build_query([
    'view' => '1',
    'operation' => 'CNC',
    'employee_id' => (int) ($emp['id'] ?? $employeeId),
    'month' => $report['month'],
    'year' => $report['year'],
    'id' => $sheetId,
]));

$pages = [];
$qtyChunks = array_chunk($report['lines'], 16);
if (!$qtyChunks) {
    $qtyChunks = [[]];
}
$sr = 0;
foreach ($qtyChunks as $i => $chunk) {
    $pages[] = [
        'kind' => 'qty',
        'rows' => $chunk,
        'sr' => $sr,
        'last' => $i === count($qtyChunks) - 1,
        'cont' => $i > 0,
    ];
    $sr += count($chunk);
}
$staffChunks = array_chunk($report['staff'], 14);
if (!$staffChunks) {
    $staffChunks = [[]];
}
$sr = 0;
foreach ($staffChunks as $i => $chunk) {
    $pages[] = [
        'kind' => 'salary',
        'rows' => $chunk,
        'sr' => $sr,
        'last' => $i === count($staffChunks) - 1,
        'cont' => $i > 0,
    ];
    $sr += count($chunk);
}

$title = 'Job work and Attendance Report — ' . $monthLabel;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo $h($title); ?></title>
    <style>
        <?php echo companyDocPrintCss(); ?>
        <?php echo companyDocLetterheadPageCss($brand, false); ?>
        * { box-sizing: border-box; }
        body { margin: 0; background: #e8edf3; font-family: Calibri, Candara, "Segoe UI", Arial, sans-serif; color: #0f172a; }
        .toolbar {
            position: sticky; top: 0; z-index: 20;
            background: #0f172a; color: #fff;
            padding: 10px 14px; display: flex; gap: 8px; align-items: center; flex-wrap: wrap;
        }
        .toolbar a, .toolbar button {
            font-size: 13px; padding: 7px 12px; border-radius: 6px; border: 0;
            cursor: pointer; text-decoration: none;
        }
        .btn-print { background: #d2232a; color: #fff; }
        .btn-back { background: #e2e8f0; color: #111; }
        .btn-on { background: #0ea5e9; color: #fff; }
        .btn-off { background: #334155; color: #fff; }
        .sheet-stack { padding: 16px; }
        .page {
            width: 210mm; height: 297mm; margin: 0 auto 18px;
            background: #fff; position: relative; overflow: hidden;
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.18);
        }
        .page .cdoc-letterhead {
            position: absolute !important; inset: 0 !important;
            background-size: 100% 100% !important; background-position: center top !important;
            z-index: 0; pointer-events: none;
            -webkit-print-color-adjust: exact; print-color-adjust: exact;
        }
        .page > .cdoc-inner {
            position: relative; z-index: 1; height: 100%;
            display: flex; flex-direction: column; overflow: hidden;
        }
        .page.has-letterhead > .cdoc-inner,
        .page:has(.cdoc-letterhead) > .cdoc-inner {
            padding: 42mm 12mm 28mm 12mm !important;
            height: 100% !important; max-height: 100% !important; overflow: hidden !important;
        }
        .doc-title { text-align: center; font-size: 16px; font-weight: 800; margin: 0; letter-spacing: .02em; }
        .doc-sub { text-align: center; font-size: 12px; color: #9f1239; font-weight: 700; margin: 2px 0 8px; }
        .sec-title { font-size: 13px; font-weight: 800; margin: 0 0 6px; color: #9f1239; }
        .meta {
            display: grid; grid-template-columns: 1fr 1fr; gap: 2px 16px;
            font-size: 12px; margin-bottom: 8px; border: 1px solid #e2e8f0; padding: 6px 8px;
        }
        table { width: 100%; border-collapse: collapse; font-size: 11.5px; }
        th, td { border: 1px solid #94a3b8; padding: 4px 6px; vertical-align: middle; }
        th { background: #fde8e9; font-weight: 800; text-align: center; }
        td.num, th.num { text-align: right; font-variant-numeric: tabular-nums; }
        td.sr, th.sr { text-align: center; width: 36px; }
        tr.main td { background: #fff7ed; font-weight: 800; }
        tr.total td { background: #fef2f2; font-weight: 800; }
        .note { margin-top: 8px; font-size: 11px; color: #475569; }
        .signs { margin-top: auto; display: flex; justify-content: space-between; font-size: 11px; padding-top: 10px; }
        .signs div { width: 30%; text-align: center; border-top: 1px solid #0f172a; padding-top: 4px; }
        .page-no { text-align: center; font-size: 10px; color: #64748b; margin-top: 4px; }
        @media print {
            body { background: #fff; }
            .toolbar { display: none !important; }
            .sheet-stack { padding: 0; }
            .page { margin: 0; box-shadow: none; page-break-after: always; }
            .page:last-child { page-break-after: auto; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <button type="button" class="btn-print" onclick="window.print()">Print / Save PDF</button>
        <a class="btn-back" href="<?php echo $h($backUrl); ?>">Back</a>
    </div>
    <div class="sheet-stack">
        <?php foreach ($pages as $pi => $pg): ?>
            <div class="<?php echo $h(companyDocSheetClass($brand, 'page')); ?>">
                <?php echo companyDocRenderWatermark($brand); ?>
                <div class="cdoc-inner">
                    <?php echo companyDocRenderHeader($brand, 'CNC Department'); ?>
                    <div class="doc-title">Job work and Attendance Report</div>
                    <div class="doc-sub">CNC Department · <?php echo $h($periodLabel); ?><?php echo ($pi > 0) ? ' · Continued' : ''; ?></div>
                    <?php if ($pg['kind'] === 'qty'): ?>
                        <div class="sec-title">Job Work — Rate &amp; Quantity<?php echo $pg['cont'] ? ' (continued)' : ''; ?></div>
                        <?php if (!$pg['cont']): ?>
                        <div class="meta">
                            <div><strong>Department:</strong> CNC</div>
                            <div><strong>Period:</strong> <?php echo $h($periodLabel); ?></div>
                            <div><strong>Contractor:</strong> <?php echo $h($empLabel); ?></div>
                            <div><strong>Qty source:</strong> ERP KRA</div>
                        </div>
                        <?php endif; ?>
                        <table>
                            <thead>
                                <tr>
                                    <th class="sr">Sr</th>
                                    <th>Contract Process</th>
                                    <th class="num">Rate</th>
                                    <th class="num">Qty</th>
                                    <th class="num">Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!$pg['rows']): ?>
                                    <tr><td colspan="5" style="text-align:center;">No CNC parts for this month.</td></tr>
                                <?php endif; ?>
                                <?php foreach ($pg['rows'] as $ri => $line): ?>
                                    <tr>
                                        <td class="sr"><?php echo (int) $pg['sr'] + $ri + 1; ?></td>
                                        <td><?php echo $h($line['name']); ?></td>
                                        <td class="num"><?php echo $money($line['rate']); ?></td>
                                        <td class="num"><?php echo $qtyFmt($line['qty']); ?></td>
                                        <td class="num"><?php echo $money($line['amount']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if ($pg['last']): ?>
                                    <tr class="total">
                                        <td colspan="3" style="text-align:right;">Total</td>
                                        <td class="num"><?php echo $qtyFmt($report['qty_total']); ?></td>
                                        <td class="num"><?php echo $money($report['amount_total']); ?></td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                        <?php if ($pg['last']): ?>
                            <p class="note">Amount = Qty × Rate. This total is the CNC contract amount for <?php echo $h($empLabel); ?>.</p>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="sec-title">Attendance — Salary<?php echo $pg['cont'] ? ' (continued)' : ''; ?></div>
                        <?php if (!$pg['cont']): ?>
                        <div class="meta">
                            <div><strong>Department:</strong> CNC</div>
                            <div><strong>Period:</strong> <?php echo $h($periodLabel); ?></div>
                            <div><strong>Main Contractor:</strong> <?php echo $h($empLabel); ?></div>
                            <div><strong>Contract total:</strong> <?php echo $money($report['pool']); ?></div>
                        </div>
                        <?php endif; ?>
                        <table>
                            <thead>
                                <tr>
                                    <th class="sr">Sr</th>
                                    <th>Emp Code</th>
                                    <th>Employee Name</th>
                                    <th class="num">Salary</th>
                                    <th class="num">Working Days</th>
                                    <th class="num">P Days</th>
                                    <th class="num">Final Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!$pg['rows']): ?>
                                    <tr><td colspan="7" style="text-align:center;">No salary employees.</td></tr>
                                <?php endif; ?>
                                <?php foreach ($pg['rows'] as $ri => $st): ?>
                                    <tr class="<?php echo !empty($st['is_main']) ? 'main' : ''; ?>">
                                        <td class="sr"><?php echo (int) $pg['sr'] + $ri + 1; ?></td>
                                        <td style="text-align:center;"><?php echo $h($st['code']); ?></td>
                                        <td><?php echo $h($st['name']); ?><?php echo !empty($st['is_main']) ? ' (Main Contractor)' : ''; ?></td>
                                        <td class="num"><?php echo $money($st['salary']); ?></td>
                                        <td class="num"><?php echo !empty($st['is_main']) ? '—' : (int) $st['working_days']; ?></td>
                                        <td class="num"><?php echo !empty($st['is_main']) ? '—' : $qtyFmt($st['paid_days']); ?></td>
                                        <td class="num"><?php echo $money($st['final']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if ($pg['last']): ?>
                                    <tr class="total">
                                        <td colspan="6" style="text-align:right;">Total (matches contract amount)</td>
                                        <td class="num"><?php echo $money($report['pool']); ?></td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                        <?php if ($pg['last']): ?>
                            <p class="note">Staff final = salary ÷ month working days (week-off skipped) × P days from <?php echo $h($periodLabel); ?>. A week-off counts only when it is P. The balance stays with the main contractor.</p>
                        <?php endif; ?>
                    <?php endif; ?>
                    <div class="signs">
                        <div>Prepared by</div>
                        <div>Main Contractor</div>
                        <div>HR</div>
                    </div>
                    <div class="page-no">Page <?php echo (int) $pi + 1; ?> / <?php echo count($pages); ?></div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</body>
</html>
