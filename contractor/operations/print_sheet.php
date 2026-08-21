<?php
/**
 * Single Operations Rate sheet — Print / PDF + optional Excel
 */

require_once __DIR__ . '/../_bootstrap.php';
require_once __DIR__ . '/../../includes/settings.php';

$id = (int) ($_GET['id'] ?? 0);
$format = strtolower((string) ($_GET['format'] ?? 'print')); // print | excel
if ($id <= 0) {
    header('Location: ' . app_url('contractor/operations/index.php'));
    exit;
}

$conn = getDBConnection();
$sheet = getContractorSheetById($id, $conn);
$conn->close();
if (!$sheet) {
    header('Location: ' . app_url('contractor/operations/index.php'));
    exit;
}

$months = contractorMonths();
$monthLabel = $months[(int) $sheet['month_no']] ?? $sheet['month_no'];
$year = (int) $sheet['year_no'];
$month = (int) $sheet['month_no'];
$daysInMonth = daysInMonthNum($month, $year);
$company = function_exists('getCompanyName') ? getCompanyName() : 'Company';
$empLabel = trim(($sheet['employee_code'] ?? '') . ' — ' . ($sheet['employee_name'] ?? $sheet['full_name'] ?? ''));
$items = $sheet['items'] ?? [];

// Resolve product labels
$productNames = [];
$conn = getDBConnection();
ensureContractorTables($conn);
$ids = [];
foreach ($items as $it) {
    if (!empty($it['product_id'])) {
        $ids[(int) $it['product_id']] = true;
    }
}
if ($ids) {
    $idList = implode(',', array_map('intval', array_keys($ids)));
    $res = $conn->query("SELECT id, product_name, process FROM contractor_products WHERE id IN ({$idList})");
    if ($res) {
        while ($p = $res->fetch_assoc()) {
            $label = trim((string) ($p['process'] ?? ''));
            if ($label === '') {
                $label = (string) $p['product_name'];
            }
            $productNames[(int) $p['id']] = $label;
        }
    }
}
$conn->close();

if ($format === 'excel') {
    $filename = 'Ops_Sheet_' . preg_replace('/[^A-Za-z0-9_-]+/', '_', $sheet['employee_code'] ?? 'emp')
        . '_' . $month . '_' . $year . '.xls';
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    echo '<html><head><meta charset="UTF-8"></head><body>';
    echo '<table border="1" cellspacing="0" cellpadding="3">';
    echo '<tr><th colspan="' . (4 + $daysInMonth) . '" style="background:#F58220;color:#fff;">'
        . htmlspecialchars($company) . ' — Operations Rate Sheet</th></tr>';
    echo '<tr><td colspan="' . (4 + $daysInMonth) . '"><strong>Operation:</strong> '
        . htmlspecialchars($sheet['operation']) . ' | <strong>Employee:</strong> '
        . htmlspecialchars($empLabel) . ' | <strong>Period:</strong> '
        . htmlspecialchars($monthLabel . ' ' . $year) . '</td></tr>';
    echo '<tr>';
    echo '<th>Sr</th><th>Contract Process</th><th>Rate</th>';
    for ($d = 1; $d <= $daysInMonth; $d++) {
        echo '<th>' . $d . '</th>';
    }
    echo '<th>Row Qty</th><th>Row Amt</th>';
    echo '</tr>';
    $sr = 0;
    foreach ($items as $it) {
        $sr++;
        $days = mergeDayMap($it['days'] ?? [], $month, $year);
        $label = $productNames[(int) ($it['product_id'] ?? 0)] ?? ('Product #' . (int) ($it['product_id'] ?? 0));
        echo '<tr>';
        echo '<td>' . $sr . '</td>';
        echo '<td>' . htmlspecialchars($label) . '</td>';
        echo '<td>' . number_format((float) ($it['rate'] ?? 0), 2) . '</td>';
        for ($d = 1; $d <= $daysInMonth; $d++) {
            $q = $days[(string) $d]['q'] ?? '';
            echo '<td>' . htmlspecialchars((string) $q) . '</td>';
        }
        echo '<td>' . number_format((float) ($it['total_qty'] ?? 0), 2) . '</td>';
        echo '<td>' . number_format((float) ($it['total_amount'] ?? 0), 2) . '</td>';
        echo '</tr>';
    }
    echo '<tr style="font-weight:bold;"><td colspan="' . (3 + $daysInMonth) . '">GROUP TOTAL</td>';
    echo '<td>' . number_format((float) $sheet['total_qty'], 2) . '</td>';
    echo '<td>' . number_format((float) $sheet['total_amount'], 2) . '</td></tr>';
    echo '</table></body></html>';
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Operations Sheet Print</title>
    <style>
        @page {
            size: A4 landscape;
            margin: 8mm;
        }
        * { box-sizing: border-box; }
        html, body {
            font-family: Arial, Helvetica, sans-serif;
            margin: 0;
            padding: 0;
            color: #111;
            background: #fff;
        }
        body { padding: 12px; }
        h1 { font-size: 16px; margin: 0 0 4px; }
        .meta { font-size: 12px; color: #444; margin-bottom: 10px; }
        .toolbar { margin-bottom: 12px; }
        .toolbar a, .toolbar button {
            display: inline-block; padding: 8px 12px; margin-right: 6px; border-radius: 6px;
            border: 0; text-decoration: none; cursor: pointer; font-size: 13px;
        }
        .btn-print { background: #F58220; color: #fff; }
        .btn-excel { background: #16a34a; color: #fff; }
        .btn-back { background: #e2e8f0; color: #111; }
        .wrap {
            width: 100%;
            overflow: visible;
        }
        table.ops-print-table {
            width: 100%;
            max-width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            font-size: 8px;
        }
        table.ops-print-table th,
        table.ops-print-table td {
            border: 1px solid #94a3b8;
            padding: 2px 1px;
            text-align: center;
            vertical-align: middle;
            word-wrap: break-word;
            overflow: hidden;
        }
        table.ops-print-table th { background: #fff4e8; font-weight: 700; }
        table.ops-print-table td.name,
        table.ops-print-table th.name {
            text-align: left;
            padding-left: 4px;
            width: 12%;
        }
        table.ops-print-table th.sr,
        table.ops-print-table td.sr { width: 2.2%; }
        table.ops-print-table th.rate,
        table.ops-print-table td.rate { width: 3.5%; }
        table.ops-print-table th.tot,
        table.ops-print-table td.tot { width: 4%; font-weight: 600; }
        table.ops-print-table tfoot td { font-weight: bold; background: #f8fafc; }
        @media print {
            .toolbar { display: none !important; }
            html, body {
                margin: 0 !important;
                padding: 0 !important;
                width: 100% !important;
                overflow: hidden !important;
            }
            .wrap {
                overflow: visible !important;
                width: 100% !important;
            }
            table.ops-print-table {
                width: 100% !important;
                max-width: 100% !important;
                font-size: 7.5px !important;
            }
            table.ops-print-table th,
            table.ops-print-table td {
                padding: 1px !important;
            }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <button type="button" class="btn-print" onclick="window.print()">Print / Save PDF</button>
        <a class="btn-excel" href="<?php echo htmlspecialchars(app_url('contractor/operations/print_sheet.php?id=' . $id . '&format=excel')); ?>">Excel</a>
        <a class="btn-back" href="<?php echo htmlspecialchars(app_url('contractor/operations/index.php')); ?>">Back</a>
    </div>
    <h1><?php echo htmlspecialchars($company); ?> — Operations Rate Sheet</h1>
    <div class="meta">
        <strong>Operation:</strong> <?php echo htmlspecialchars($sheet['operation']); ?> ·
        <strong>Employee:</strong> <?php echo htmlspecialchars($empLabel); ?> ·
        <strong>Period:</strong> <?php echo htmlspecialchars($monthLabel . ' ' . $year); ?>
        <span style="margin-left:8px;color:#64748b;">(Use Landscape · Fit to page width)</span>
    </div>
    <div class="wrap">
        <table class="ops-print-table">
            <thead>
                <tr>
                    <th class="sr">Sr</th>
                    <th class="name">Contract Process</th>
                    <th class="rate">Rate</th>
                    <?php for ($d = 1; $d <= $daysInMonth; $d++): ?>
                        <th><?php echo $d; ?></th>
                    <?php endfor; ?>
                    <th class="tot">Qty</th>
                    <th class="tot">Amt</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$items): ?>
                <tr><td colspan="<?php echo 5 + $daysInMonth; ?>">No product rows</td></tr>
            <?php endif; ?>
            <?php foreach ($items as $i => $it):
                $days = mergeDayMap($it['days'] ?? [], $month, $year);
                $label = $productNames[(int) ($it['product_id'] ?? 0)] ?? ('Product #' . (int) ($it['product_id'] ?? 0));
                ?>
                <tr>
                    <td class="sr"><?php echo $i + 1; ?></td>
                    <td class="name"><?php echo htmlspecialchars($label); ?></td>
                    <td class="rate"><?php echo number_format((float) ($it['rate'] ?? 0), 2); ?></td>
                    <?php for ($d = 1; $d <= $daysInMonth; $d++):
                        $q = (string) ($days[(string) $d]['q'] ?? '');
                        if ($q === '0' || $q === '0.00') {
                            $q = '';
                        }
                        ?>
                        <td><?php echo htmlspecialchars($q); ?></td>
                    <?php endfor; ?>
                    <td class="tot"><?php echo number_format((float) ($it['total_qty'] ?? 0), 2); ?></td>
                    <td class="tot"><?php echo number_format((float) ($it['total_amount'] ?? 0), 2); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="<?php echo 3 + $daysInMonth; ?>">GROUP TOTAL</td>
                    <td class="tot"><?php echo number_format((float) $sheet['total_qty'], 2); ?></td>
                    <td class="tot"><?php echo number_format((float) $sheet['total_amount'], 2); ?></td>
                </tr>
            </tfoot>
        </table>
    </div>
</body>
</html>
