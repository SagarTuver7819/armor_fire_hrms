<?php
/**
 * Operations Rate List — Print / PDF
 * Product-wise rows: Rate, Qty, Rework Rate, Rework Qty, Amount
 */

require_once __DIR__ . '/../_bootstrap.php';
require_once __DIR__ . '/../../includes/settings.php';

$filterOp = trim((string) ($_GET['filter_operation'] ?? ''));
$filterEmp = (int) ($_GET['filter_employee'] ?? 0);
$filterMonth = (int) ($_GET['filter_month'] ?? 0);
$filterYear = (int) ($_GET['filter_year'] ?? 0);
$months = contractorMonths();
$autoPrint = !isset($_GET['noprint']);

$conn = getDBConnection();
ensureContractorTables($conn);

$where = 's.status = 1';
$types = '';
$params = [];
if ($filterOp !== '') {
    $where .= ' AND s.operation = ?';
    $types .= 's';
    $params[] = $filterOp;
}
if ($filterEmp > 0) {
    $where .= ' AND s.employee_id = ?';
    $types .= 'i';
    $params[] = $filterEmp;
}
if ($filterMonth > 0) {
    $where .= ' AND s.month_no = ?';
    $types .= 'i';
    $params[] = $filterMonth;
}
if ($filterYear > 0) {
    $where .= ' AND s.year_no = ?';
    $types .= 'i';
    $params[] = $filterYear;
}

$sql = "SELECT s.id, s.operation, s.month_no, s.year_no, s.total_qty AS sheet_qty, s.total_amount AS sheet_amount,
               e.employee_code, e.employee_name,
               i.id AS item_id, i.product_id, i.rate, i.ot_rate, i.rejection_rate,
               i.total_qty, i.total_r, i.total_amount, i.sort_order,
               COALESCE(NULLIF(TRIM(p.process), ''), NULLIF(TRIM(p.product_name), ''), CONCAT('Product #', i.product_id)) AS product_label
        FROM contractor_operation_sheets s
        INNER JOIN employees e ON e.id = s.employee_id
        LEFT JOIN contractor_operation_items i ON i.sheet_id = s.id
        LEFT JOIN contractor_products p ON p.id = i.product_id
        WHERE {$where}
        ORDER BY s.year_no DESC, s.month_no DESC, e.employee_name ASC, i.sort_order ASC, i.id ASC";

if ($params) {
    $st = $conn->prepare($sql);
    $st->bind_param($types, ...$params);
    $st->execute();
    $res = $st->get_result();
} else {
    $res = $conn->query($sql);
}

$rows = [];
while ($row = $res->fetch_assoc()) {
    $rows[] = $row;
}
if (isset($st)) {
    $st->close();
}
$conn->close();

$company = function_exists('getCompanyName') ? getCompanyName() : 'Company';
$sumQty = 0;
$sumReworkQty = 0;
$sumCalcAmt = 0;
$sumReworkCalcAmt = 0;
$sumGrand = 0;
foreach ($rows as $r) {
    $hasItem = !empty($r['item_id']);
    $rate = $hasItem ? (float) ($r['rate'] ?? 0) : 0;
    $qty = $hasItem ? (float) ($r['total_qty'] ?? 0) : (float) ($r['sheet_qty'] ?? 0);
    $rejRate = $hasItem ? (float) ($r['rejection_rate'] ?? 0) : 0;
    $rejQty = $hasItem ? (float) ($r['total_r'] ?? 0) : 0;
    $calcAmt = round($qty * $rate, 2);
    $reworkCalcAmt = round($rejQty * $rejRate, 2);
    $grand = round($calcAmt + $reworkCalcAmt, 2);
    $sumQty += $qty;
    $sumReworkQty += $rejQty;
    $sumCalcAmt += $calcAmt;
    $sumReworkCalcAmt += $reworkCalcAmt;
    $sumGrand += $grand;
}
$colCount = 12;

// Period label for header (above table, center)
$periodLabel = '';
if ($filterMonth > 0 && $filterYear > 0) {
    $periodLabel = ($months[$filterMonth] ?? (string) $filterMonth) . ' ' . $filterYear;
} elseif ($rows) {
    $periodKeys = [];
    foreach ($rows as $r) {
        $mk = (int) $r['month_no'] . '-' . (int) $r['year_no'];
        $periodKeys[$mk] = ($months[(int) $r['month_no']] ?? $r['month_no']) . ' ' . (int) $r['year_no'];
    }
    $periodLabel = count($periodKeys) === 1
        ? reset($periodKeys)
        : implode(' · ', array_values($periodKeys));
} else {
    $periodLabel = 'All Periods';
}

// Merge consecutive rows for same Operation + EMP Code + Name
$empSpan = [];
$nRows = count($rows);
for ($i = 0; $i < $nRows; $i++) {
    if (isset($empSpan[$i])) {
        continue;
    }
    $key = ($rows[$i]['operation'] ?? '') . '|' . ($rows[$i]['employee_code'] ?? '') . '|' . ($rows[$i]['employee_name'] ?? '');
    $span = 1;
    for ($j = $i + 1; $j < $nRows; $j++) {
        $key2 = ($rows[$j]['operation'] ?? '') . '|' . ($rows[$j]['employee_code'] ?? '') . '|' . ($rows[$j]['employee_name'] ?? '');
        if ($key2 !== $key) {
            break;
        }
        $span++;
    }
    $empSpan[$i] = $span;
    for ($k = 1; $k < $span; $k++) {
        $empSpan[$i + $k] = 0;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Operations Rate List — Print</title>
    <style>
        @page { size: A4 landscape; margin: 8mm; }
        body { font-family: Arial, Helvetica, sans-serif; color: #111; margin: 18px; }
        h1 { margin: 0 0 4px; font-size: 18px; text-align: center; }
        .meta {
            color: #555;
            margin: 12px 0 0;
            font-size: 11px;
            text-align: right;
        }
        .period-head {
            text-align: center;
            font-size: 16px;
            font-weight: 700;
            margin: 6px 0 14px;
            padding: 8px 12px;
            background: #fff4e8;
            border: 1px solid #f5c89a;
            border-radius: 6px;
            color: #d96a0f;
        }
        table { width: 100%; border-collapse: collapse; font-size: 9.5px; table-layout: fixed; }
        th, td { border: 1px solid #94a3b8; padding: 4px 4px; text-align: left; word-wrap: break-word; vertical-align: top; }
        th { background: #fff4e8; font-weight: 700; color: #1a2332; }
        td.num, th.num { text-align: right; }
        td.center, th.center { text-align: center; }
        td.merge-mid {
            text-align: center;
            vertical-align: middle;
            font-weight: 600;
        }
        td.grand, th.grand { background: #ffe8cc; font-weight: 700; }
        tfoot td { font-weight: bold; background: #fffaf5; }
        .toolbar { margin-bottom: 12px; text-align: left; }
        .toolbar button, .toolbar a {
            display: inline-block; padding: 8px 14px; margin-right: 8px;
            border: 0; border-radius: 6px; text-decoration: none; cursor: pointer; font-size: 13px;
        }
        .btn-print { background: #F58220; color: #fff; }
        .btn-back { background: #e2e8f0; color: #111; }
        @media print {
            .toolbar { display: none !important; }
            body { margin: 0 !important; overflow: hidden !important; }
            table { width: 100% !important; font-size: 8.5px !important; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <button type="button" class="btn-print" onclick="window.print()">Print / Save PDF</button>
        <a class="btn-back" href="<?php echo htmlspecialchars(app_url('contractor/operations/index.php')); ?>">Back</a>
    </div>
    <h1><?php echo htmlspecialchars($company); ?> — Operations Rate List (Product Wise)</h1>
    <div class="period-head"><?php echo htmlspecialchars($periodLabel); ?></div>
    <table>
        <thead>
            <tr>
                <th class="center" style="width:3%;">Sr.</th>
                <th style="width:11%;">Operation</th>
                <th style="width:8%;">EMP Code</th>
                <th style="width:11%;">Name</th>
                <th style="width:14%;">Product / Contract Process</th>
                <th class="num" style="width:7%;">Qty</th>
                <th class="num" style="width:6%;">Rate</th>
                <th class="num" style="width:9%;">Calculation Amount</th>
                <th class="num" style="width:7%;">Rework Qty</th>
                <th class="num" style="width:7%;">Rework Rate</th>
                <th class="num" style="width:9%;">Rework Calculation Amount</th>
                <th class="num grand" style="width:8%;">Final Grand Total</th>
            </tr>
        </thead>
        <tbody>
        <?php if (!$rows): ?>
            <tr><td colspan="<?php echo $colCount; ?>">No records</td></tr>
        <?php endif; ?>
        <?php foreach ($rows as $i => $r):
            $hasItem = !empty($r['item_id']);
            $product = $hasItem ? (string) ($r['product_label'] ?? '-') : '-';
            $rate = $hasItem ? (float) ($r['rate'] ?? 0) : 0;
            $qty = $hasItem ? (float) ($r['total_qty'] ?? 0) : (float) ($r['sheet_qty'] ?? 0);
            $rejRate = $hasItem ? (float) ($r['rejection_rate'] ?? 0) : 0;
            $rejQty = $hasItem ? (float) ($r['total_r'] ?? 0) : 0;
            $calcAmt = round($qty * $rate, 2);
            $reworkCalcAmt = round($rejQty * $rejRate, 2);
            $grand = round($calcAmt + $reworkCalcAmt, 2);
            $span = (int) ($empSpan[$i] ?? 1);
            ?>
            <tr>
                <td class="center"><?php echo $i + 1; ?></td>
                <?php if ($span > 0): ?>
                    <td class="merge-mid" rowspan="<?php echo $span; ?>"><?php echo htmlspecialchars($r['operation']); ?></td>
                    <td class="merge-mid" rowspan="<?php echo $span; ?>"><?php echo htmlspecialchars($r['employee_code']); ?></td>
                    <td class="merge-mid" rowspan="<?php echo $span; ?>"><?php echo htmlspecialchars($r['employee_name']); ?></td>
                <?php endif; ?>
                <td><?php echo htmlspecialchars($product); ?></td>
                <td class="num"><?php echo number_format($qty, 2); ?></td>
                <td class="num"><?php echo $hasItem ? number_format($rate, 2) : '-'; ?></td>
                <td class="num"><?php echo number_format($calcAmt, 2); ?></td>
                <td class="num"><?php echo $hasItem ? number_format($rejQty, 2) : '-'; ?></td>
                <td class="num"><?php echo $hasItem ? number_format($rejRate, 2) : '-'; ?></td>
                <td class="num"><?php echo number_format($reworkCalcAmt, 2); ?></td>
                <td class="num grand"><?php echo number_format($grand, 2); ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
        <?php if ($rows): ?>
        <tfoot>
            <tr>
                <td colspan="5">TOTAL</td>
                <td class="num"><?php echo number_format($sumQty, 2); ?></td>
                <td></td>
                <td class="num"><?php echo number_format($sumCalcAmt, 2); ?></td>
                <td class="num"><?php echo number_format($sumReworkQty, 2); ?></td>
                <td></td>
                <td class="num"><?php echo number_format($sumReworkCalcAmt, 2); ?></td>
                <td class="num grand"><?php echo number_format($sumGrand, 2); ?></td>
            </tr>
        </tfoot>
        <?php endif; ?>
    </table>
    <div class="meta">Printed <?php echo date('d/m/Y H:i'); ?> · Product rows: <?php echo count($rows); ?></div>
    <?php if ($autoPrint): ?>
    <script>window.addEventListener('load', function () { window.print(); });</script>
    <?php endif; ?>
</body>
</html>
