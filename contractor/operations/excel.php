<?php
/**
 * Operations Rate List — Excel export (product wise)
 */

require_once __DIR__ . '/../_bootstrap.php';
require_once __DIR__ . '/../../includes/settings.php';

$filterOp = trim((string) ($_GET['filter_operation'] ?? ''));
$filterEmp = (int) ($_GET['filter_employee'] ?? 0);
$filterMonth = (int) ($_GET['filter_month'] ?? 0);
$filterYear = (int) ($_GET['filter_year'] ?? 0);
$months = contractorMonths();

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
               i.id AS item_id, i.rate, i.rejection_rate, i.total_qty, i.total_r, i.total_amount,
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
$filename = 'Operations_Rate_List_ProductWise_' . date('Ymd_His') . '.xls';
header('Content-Type: application/vnd.ms-excel; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$headers = [
    'Sr.', 'Operation', 'EMP Code', 'Name', 'Product / Contract Process',
    'Qty', 'Rate', 'Calculation Amount', 'Rework Qty', 'Rework Rate',
    'Rework Calculation Amount', 'Final Grand Total', 'Month', 'Year',
];

echo '<html><head><meta charset="UTF-8"></head><body>';
echo '<table border="1" cellspacing="0" cellpadding="4">';
echo '<tr><th colspan="' . count($headers) . '" style="background:#F58220;color:#fff;font-size:15px;">'
    . htmlspecialchars($company) . ' — Operations Rate List (Product Wise)</th></tr>';
echo '<tr>';
foreach ($headers as $h) {
    echo '<th style="background:#fff4e8;">' . htmlspecialchars($h) . '</th>';
}
echo '</tr>';

$sr = 0;
$sumQty = 0;
$sumReworkQty = 0;
$sumCalcAmt = 0;
$sumReworkCalcAmt = 0;
$sumGrand = 0;
foreach ($rows as $r) {
    $sr++;
    $hasItem = !empty($r['item_id']);
    $product = $hasItem ? (string) ($r['product_label'] ?? '-') : '-';
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

    echo '<tr>';
    echo '<td>' . $sr . '</td>';
    echo '<td>' . htmlspecialchars($r['operation']) . '</td>';
    echo '<td>' . htmlspecialchars($r['employee_code']) . '</td>';
    echo '<td>' . htmlspecialchars($r['employee_name']) . '</td>';
    echo '<td>' . htmlspecialchars($product) . '</td>';
    echo '<td>' . number_format($qty, 2) . '</td>';
    echo '<td>' . ($hasItem ? number_format($rate, 2) : '-') . '</td>';
    echo '<td>' . number_format($calcAmt, 2) . '</td>';
    echo '<td>' . ($hasItem ? number_format($rejQty, 2) : '-') . '</td>';
    echo '<td>' . ($hasItem ? number_format($rejRate, 2) : '-') . '</td>';
    echo '<td>' . number_format($reworkCalcAmt, 2) . '</td>';
    echo '<td>' . number_format($grand, 2) . '</td>';
    echo '<td>' . htmlspecialchars($months[(int) $r['month_no']] ?? $r['month_no']) . '</td>';
    echo '<td>' . (int) $r['year_no'] . '</td>';
    echo '</tr>';
}
if ($sr === 0) {
    echo '<tr><td colspan="' . count($headers) . '">No records</td></tr>';
} else {
    echo '<tr style="font-weight:bold;background:#fff4e8;">';
    echo '<td colspan="5">TOTAL</td>';
    echo '<td>' . number_format($sumQty, 2) . '</td>';
    echo '<td></td>';
    echo '<td>' . number_format($sumCalcAmt, 2) . '</td>';
    echo '<td>' . number_format($sumReworkQty, 2) . '</td>';
    echo '<td></td>';
    echo '<td>' . number_format($sumReworkCalcAmt, 2) . '</td>';
    echo '<td>' . number_format($sumGrand, 2) . '</td>';
    echo '<td colspan="2"></td>';
    echo '</tr>';
}
echo '</table></body></html>';
exit;
