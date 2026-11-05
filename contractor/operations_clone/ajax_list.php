<?php
/**
 * New Operation List — month rows from November 2026 for CNC Main Contractor (Ravi).
 * View only. Does not change the existing Operations Rate List.
 */
require_once __DIR__ . '/../_bootstrap.php';
require_once __DIR__ . '/../../includes/contractor_split_helper.php';
header('Content-Type: application/json; charset=utf-8');

$draw = (int) ($_POST['draw'] ?? 1);
$start = max(0, (int) ($_POST['start'] ?? 0));
$length = (int) ($_POST['length'] ?? 10);
if ($length <= 0 || $length > 100) {
    $length = 10;
}
$search = trim($_POST['search']['value'] ?? '');
$orderDir = (strtolower($_POST['order'][0]['dir'] ?? 'desc') === 'asc') ? 'ASC' : 'DESC';
$orderMap = [
    0 => 's.id',
    1 => 's.operation',
    2 => 'e.employee_name',
    3 => 's.month_no',
    4 => 's.year_no',
    5 => 's.total_qty',
    6 => 's.total_amount',
];
$orderCol = (int) ($_POST['order'][0]['column'] ?? 0);
$orderBy = $orderMap[$orderCol] ?? 's.id';
$months = contractorMonths();

$conn = getDBConnection();
ensureContractorTables($conn);

$filterOp = trim((string) ($_POST['filter_operation'] ?? ''));
$filterEmp = (int) ($_POST['filter_employee'] ?? 0);
$filterMonth = (int) ($_POST['filter_month'] ?? 0);
$filterYear = (int) ($_POST['filter_year'] ?? 0);

$cncRule = null;
foreach (contractorAttendanceSplitRules() as $rule) {
    if (strcasecmp((string) ($rule['department_name'] ?? ''), 'CNC') === 0) {
        $cncRule = $rule;
        break;
    }
}
$main = $cncRule ? resolveContractorAttendanceSplitMain($cncRule) : null;
$mainId = (int) ($main['id'] ?? 0);
$mainCode = trim((string) ($main['employee_code'] ?? 'CO72141'));
$mainName = trim((string) ($main['employee_name'] ?? 'SOLANKI RAVI KANABHAI'));
$nameLabel = $mainCode . ' - ' . $mainName;

$sheets = [];
if ($mainId > 0) {
    $st = $conn->prepare(
        "SELECT id, month_no, year_no, total_qty, total_amount
         FROM contractor_operation_sheets
         WHERE status = 1 AND operation = 'CNC' AND employee_id = ?
           AND (year_no > 2026 OR (year_no = 2026 AND month_no >= 11))"
    );
    $st->bind_param('i', $mainId);
    $st->execute();
    $res = $st->get_result();
    while ($row = $res->fetch_assoc()) {
        $sheets[(int) $row['year_no'] . '-' . (int) $row['month_no']] = $row;
    }
    $st->close();
}
$conn->close();

$endYear = (int) date('Y');
$endMonth = (int) date('n');
if ($endYear < 2026 || ($endYear === 2026 && $endMonth < 11)) {
    $endYear = 2026;
    $endMonth = 11;
}

$all = [];
$y = 2026;
$m = 11;
while ($y < $endYear || ($y === $endYear && $m <= $endMonth)) {
    $all[] = ['year' => $y, 'month' => $m];
    $m++;
    if ($m > 12) {
        $m = 1;
        $y++;
    }
}
$total = count($all);

$rows = [];
foreach ($all as $slot) {
    $y = (int) $slot['year'];
    $m = (int) $slot['month'];
    if ($filterOp !== '' && strcasecmp($filterOp, 'CNC') !== 0) {
        continue;
    }
    if ($filterEmp > 0 && $mainId > 0 && $filterEmp !== $mainId) {
        continue;
    }
    if ($filterMonth > 0 && $filterMonth !== $m) {
        continue;
    }
    if ($filterYear > 0 && $filterYear !== $y) {
        continue;
    }
    $monthLab = (string) ($months[$m] ?? $m);
    if ($search !== '') {
        $hay = strtolower('CNC ' . $nameLabel . ' ' . $monthLab . ' ' . $y);
        if (strpos($hay, strtolower($search)) === false) {
            continue;
        }
    }
    $key = $y . '-' . $m;
    $sheet = $sheets[$key] ?? null;
    $rows[] = [
        'id' => (int) ($sheet['id'] ?? 0),
        'month' => $m,
        'year' => $y,
        'month_lab' => $monthLab,
        'qty' => (float) ($sheet['total_qty'] ?? 0),
        'amount' => (float) ($sheet['total_amount'] ?? 0),
    ];
}

$sortKey = [
    's.operation' => 'op',
    'e.employee_name' => 'name',
    's.month_no' => 'month',
    's.year_no' => 'year',
    's.total_qty' => 'qty',
    's.total_amount' => 'amount',
    's.id' => 'year',
][$orderBy] ?? 'year';
usort($rows, static function ($a, $b) use ($sortKey, $orderDir) {
    $ym = static function ($row) {
        return ((int) $row['year'] * 100) + (int) $row['month'];
    };
    if (!isset($a[$sortKey]) || $sortKey === 'year') {
        $av = $ym($a);
        $bv = $ym($b);
    } else {
        $av = $a[$sortKey];
        $bv = $b[$sortKey];
    }
    if ($av == $bv) {
        return 0;
    }
    $cmp = ($av < $bv) ? -1 : 1;
    return $orderDir === 'ASC' ? $cmp : -$cmp;
});

$filtered = count($rows);
$page = array_slice($rows, $start, $length);
$data = [];
$sr = $start;
foreach ($page as $row) {
    $sr++;
    $sid = (int) $row['id'];
    $viewQs = [
        'view' => 1,
        'operation' => 'CNC',
        'employee_id' => $mainId,
        'month' => (int) $row['month'],
        'year' => (int) $row['year'],
    ];
    if ($sid > 0) {
        $viewQs['id'] = $sid;
    }
    $actions = '<div class="action-links" onclick="event.stopPropagation();">'
        . '<a href="' . htmlspecialchars(app_url('contractor/operations_clone/edit.php?' . http_build_query($viewQs))) . '" class="action-btn view" title="View"><i class="fa-solid fa-eye"></i></a>';
    if ($sid > 0) {
        $actions .= '<a href="' . htmlspecialchars(app_url('contractor/operations/print_sheet.php?id=' . $sid)) . '" class="action-btn print" title="Print PDF" target="_blank"><i class="fa-solid fa-print"></i></a>'
            . '<a href="' . htmlspecialchars(app_url('contractor/operations/print_sheet.php?id=' . $sid . '&format=excel')) . '" class="action-btn excel" title="Excel"><i class="fa-solid fa-file-excel"></i></a>';
    }
    $actions .= '</div>';
    $data[] = [
        $sr,
        'CNC',
        htmlspecialchars($nameLabel),
        htmlspecialchars((string) $row['month_lab']),
        (int) $row['year'],
        formatIndianNumber((float) $row['qty'], 2),
        formatIndianNumber((float) $row['amount'], 2),
        $actions,
    ];
}

echo json_encode(['draw' => $draw, 'recordsTotal' => $total, 'recordsFiltered' => $filtered, 'data' => $data]);
