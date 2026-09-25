<?php
/**
 * Operations Rate Sheet — Excel export
 * Default: day-wise sheets for ALL employees matching filters (department / operation / period)
 * ?mode=summary → old product-wise list
 */

require_once __DIR__ . '/../_bootstrap.php';
require_once __DIR__ . '/../../includes/settings.php';

$filterOp = trim((string) ($_GET['filter_operation'] ?? ''));
$filterEmp = (int) ($_GET['filter_employee'] ?? 0);
$filterMonth = (int) ($_GET['filter_month'] ?? 0);
$filterYear = (int) ($_GET['filter_year'] ?? 0);
$mode = strtolower(trim((string) ($_GET['mode'] ?? 'days'))); // days | summary
$months = contractorMonths();
$company = function_exists('getCompanyName') ? getCompanyName() : 'Company';

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

// ── Product-wise summary (legacy) ───────────────────────────────────
if ($mode === 'summary') {
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
}

// ── Day-wise sheets for all employees (default) ─────────────────────
$sqlIds = "SELECT s.id
           FROM contractor_operation_sheets s
           INNER JOIN employees e ON e.id = s.employee_id
           WHERE {$where}
           ORDER BY s.year_no ASC, s.month_no ASC, e.employee_name ASC, s.operation ASC, s.id ASC";

if ($params) {
    $st = $conn->prepare($sqlIds);
    $st->bind_param($types, ...$params);
    $st->execute();
    $res = $st->get_result();
} else {
    $res = $conn->query($sqlIds);
}

$sheetIds = [];
while ($row = $res->fetch_assoc()) {
    $sheetIds[] = (int) $row['id'];
}
if (isset($st)) {
    $st->close();
}

$sheets = [];
foreach ($sheetIds as $sid) {
    $sheet = getContractorSheetById($sid, $conn);
    if ($sheet) {
        $sheets[] = $sheet;
    }
}
$conn->close();

$opPart = $filterOp !== '' ? preg_replace('/[^A-Za-z0-9_-]+/', '_', $filterOp) : 'AllOps';
$periodPart = ($filterMonth > 0 && $filterYear > 0)
    ? ($filterMonth . '_' . $filterYear)
    : 'AllPeriods';
$filename = 'Ops_Rate_Sheet_AllEmployees_' . $opPart . '_' . $periodPart . '.xls';

header('Content-Type: application/vnd.ms-excel; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$filterBits = [];
if ($filterOp !== '') {
    $filterBits[] = 'Operation: ' . $filterOp;
} else {
    $filterBits[] = 'Operation: All';
}
if ($filterEmp > 0 && $sheets) {
    $filterBits[] = 'Employee: ' . trim(($sheets[0]['employee_code'] ?? '') . ' — ' . ($sheets[0]['employee_name'] ?? ''));
} else {
    $filterBits[] = 'Employees: All (department / filter)';
}
if ($filterMonth > 0 && $filterYear > 0) {
    $filterBits[] = 'Period: ' . ($months[$filterMonth] ?? $filterMonth) . ' ' . $filterYear;
} elseif ($filterYear > 0) {
    $filterBits[] = 'Year: ' . $filterYear;
} else {
    $filterBits[] = 'Period: All matching';
}

echo '<html><head><meta charset="UTF-8"></head><body>';
echo '<table border="1" cellspacing="0" cellpadding="3">';

if (!$sheets) {
    echo '<tr><th style="background:#F58220;color:#fff;">'
        . htmlspecialchars($company) . ' — Operations Rate Sheet</th></tr>';
    echo '<tr><td>No rate sheets found for selected filters.</td></tr>';
    echo '</table></body></html>';
    exit;
}

$grandQty = 0.0;
$grandAmt = 0.0;
$empCount = 0;
$lastPeriodKey = null;

foreach ($sheets as $sheet) {
    $month = (int) $sheet['month_no'];
    $year = (int) $sheet['year_no'];
    $daysInMonth = daysInMonthNum($month, $year);
    $colSpan = 5 + $daysInMonth; // Sr + Process + Rate + days + Qty + Amt
    $periodKey = $year . '-' . $month;
    $monthLabel = $months[$month] ?? (string) $month;
    $empLabel = trim(($sheet['employee_code'] ?? '') . ' — ' . ($sheet['employee_name'] ?? $sheet['full_name'] ?? ''));
    $items = $sheet['items'] ?? [];
    $empCount++;

    // Title once at top, then period break headers when period changes
    if ($lastPeriodKey === null) {
        echo '<tr><th colspan="' . $colSpan . '" style="background:#F58220;color:#fff;font-size:15px;">'
            . htmlspecialchars($company) . ' — Operations Rate Sheet (All Employees)</th></tr>';
        echo '<tr><td colspan="' . $colSpan . '"><strong>'
            . htmlspecialchars(implode(' | ', $filterBits))
            . '</strong></td></tr>';
    } elseif ($periodKey !== $lastPeriodKey) {
        echo '<tr><td colspan="' . $colSpan . '" style="height:10px;border:none;"></td></tr>';
        echo '<tr><th colspan="' . $colSpan . '" style="background:#fb923c;color:#fff;">'
            . htmlspecialchars('Period: ' . $monthLabel . ' ' . $year) . '</th></tr>';
    }
    $lastPeriodKey = $periodKey;

    // Employee block header
    echo '<tr><td colspan="' . $colSpan . '" style="background:#fff4e8;font-weight:bold;">'
        . '<strong>Operation:</strong> ' . htmlspecialchars((string) ($sheet['operation'] ?? ''))
        . ' &nbsp;|&nbsp; <strong>Employee:</strong> ' . htmlspecialchars($empLabel)
        . ' &nbsp;|&nbsp; <strong>Period:</strong> ' . htmlspecialchars($monthLabel . ' ' . $year)
        . '</td></tr>';

    echo '<tr>';
    echo '<th style="background:#fef3c7;">Sr</th>';
    echo '<th style="background:#fef3c7;">Contract Process</th>';
    echo '<th style="background:#fef3c7;">Rate</th>';
    for ($d = 1; $d <= $daysInMonth; $d++) {
        echo '<th style="background:#fef3c7;">' . $d . '</th>';
    }
    echo '<th style="background:#fef3c7;">Row Qty</th>';
    echo '<th style="background:#fef3c7;">Row Amt</th>';
    echo '</tr>';

    $sr = 0;
    if (!$items) {
        echo '<tr><td colspan="' . $colSpan . '">No product rows</td></tr>';
    }
    foreach ($items as $it) {
        $sr++;
        $days = mergeDayMap($it['days'] ?? [], $month, $year);
        $label = trim((string) ($it['product_label'] ?? ''));
        if ($label === '') {
            $label = 'Product #' . (int) ($it['product_id'] ?? 0);
        }
        echo '<tr>';
        echo '<td>' . $sr . '</td>';
        echo '<td>' . htmlspecialchars($label) . '</td>';
        echo '<td>' . number_format((float) ($it['rate'] ?? 0), 2) . '</td>';
        for ($d = 1; $d <= $daysInMonth; $d++) {
            $q = (string) ($days[(string) $d]['q'] ?? '');
            if ($q === '0' || $q === '0.00') {
                $q = '';
            }
            echo '<td>' . htmlspecialchars($q) . '</td>';
        }
        echo '<td>' . number_format((float) ($it['total_qty'] ?? 0), 2) . '</td>';
        echo '<td>' . number_format((float) ($it['total_amount'] ?? 0), 2) . '</td>';
        echo '</tr>';
    }

    $sheetQty = (float) ($sheet['total_qty'] ?? 0);
    $sheetAmt = (float) ($sheet['total_amount'] ?? 0);
    $grandQty += $sheetQty;
    $grandAmt += $sheetAmt;

    echo '<tr style="font-weight:bold;background:#f8fafc;">';
    echo '<td colspan="' . (3 + $daysInMonth) . '">GROUP TOTAL — ' . htmlspecialchars($empLabel) . '</td>';
    echo '<td>' . number_format($sheetQty, 2) . '</td>';
    echo '<td>' . number_format($sheetAmt, 2) . '</td>';
    echo '</tr>';

    // Spacer between employees
    echo '<tr><td colspan="' . $colSpan . '" style="height:8px;border:none;background:#fff;"></td></tr>';
}

// Department / filter grand total
$maxDays = 31;
if ($filterMonth > 0 && $filterYear > 0) {
    $maxDays = daysInMonthNum($filterMonth, $filterYear);
} elseif ($sheets) {
    $maxDays = daysInMonthNum((int) $sheets[0]['month_no'], (int) $sheets[0]['year_no']);
}
$footSpan = 5 + $maxDays;
echo '<tr style="font-weight:bold;background:#F58220;color:#fff;">';
echo '<td colspan="' . (3 + $maxDays) . '">DEPARTMENT TOTAL (' . (int) $empCount . ' employee sheet(s))</td>';
echo '<td>' . number_format($grandQty, 2) . '</td>';
echo '<td>' . number_format($grandAmt, 2) . '</td>';
echo '</tr>';

echo '</table></body></html>';
exit;
