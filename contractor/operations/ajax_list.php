<?php
require_once __DIR__ . '/../_bootstrap.php';
header('Content-Type: application/json; charset=utf-8');

$draw = (int) ($_POST['draw'] ?? 1);
$start = max(0, (int) ($_POST['start'] ?? 0));
$length = (int) ($_POST['length'] ?? 10);
if ($length <= 0 || $length > 100) {
    $length = 10;
}
$search = trim($_POST['search']['value'] ?? '');
$orderDir = (strtolower($_POST['order'][0]['dir'] ?? 'desc') === 'asc') ? 'ASC' : 'DESC';
$months = contractorMonths();

$conn = getDBConnection();
ensureContractorTables($conn);

$filterOp = trim((string) ($_POST['filter_operation'] ?? ''));
$filterEmp = (int) ($_POST['filter_employee'] ?? 0);
$filterMonth = (int) ($_POST['filter_month'] ?? 0);
$filterYear = (int) ($_POST['filter_year'] ?? 0);

$total = (int) $conn->query('SELECT COUNT(*) AS c FROM contractor_operation_sheets s WHERE s.status = 1')->fetch_assoc()['c'];

/**
 * Build action HTML for an existing sheet or "Add" for employee without sheet.
 */
$buildActions = static function ($sheetId, $empId, $operation, $empCode) {
    $empCode = htmlspecialchars((string) $empCode);
    $operation = htmlspecialchars((string) $operation);
    if ($sheetId > 0) {
        $sid = (int) $sheetId;
        return '<div class="action-links" onclick="event.stopPropagation();">'
            . '<a href="' . htmlspecialchars(app_url('contractor/operations/edit.php?id=' . $sid)) . '" class="action-btn edit" title="Edit"><i class="fa-solid fa-pen"></i></a>'
            . '<a href="' . htmlspecialchars(app_url('contractor/operations/print_sheet.php?id=' . $sid)) . '" class="action-btn print" title="Print PDF" target="_blank"><i class="fa-solid fa-print"></i></a>'
            . '<a href="' . htmlspecialchars(app_url('contractor/operations/print_sheet.php?id=' . $sid . '&format=excel')) . '" class="action-btn excel" title="Excel"><i class="fa-solid fa-file-excel"></i></a>'
            . '<a href="' . htmlspecialchars(app_url('contractor/operations/generate.php?id=' . $sid)) . '" class="action-btn salary" title="Generate Salary"><i class="fa-solid fa-indian-rupee-sign"></i></a>'
            . '<a href="' . htmlspecialchars(app_url('contractor/operations/delete.php?id=' . $sid)) . '" class="action-btn delete btn-delete" data-name="' . $empCode . ' ' . $operation . '" title="Delete"><i class="fa-solid fa-trash"></i></a>'
            . '</div>';
    }
    $addQs = http_build_query([
        'operation' => $operation,
        'employee_id' => (int) $empId,
    ]);
    return '<div class="action-links" onclick="event.stopPropagation();">'
        . '<a href="' . htmlspecialchars(app_url('contractor/operations/edit.php?' . $addQs)) . '" class="action-btn edit" title="Add Rate List"><i class="fa-solid fa-plus"></i> Add</a>'
        . '</div>';
};

$data = [];
$filtered = 0;

if ($filterOp !== '') {
    // Operation selected: show dept employees (+ any sheets for this operation)
    // so CUTTING etc. appear even before first rate list is saved.
    $jw = contractorJobworkWhere();
    $where = "{$jw}
              AND (
                    UPPER(TRIM(IFNULL(d.department_name,''))) = UPPER(?)
                    OR EXISTS (
                        SELECT 1 FROM contractor_operation_sheets sx
                        WHERE sx.employee_id = e.id AND sx.status = 1 AND sx.operation = ?
                    )
              )";
    $types = 'ss';
    $params = [$filterOp, $filterOp];

    if ($filterEmp > 0) {
        $where .= ' AND e.id = ?';
        $types .= 'i';
        $params[] = $filterEmp;
    }
    if ($search !== '') {
        $where .= ' AND (e.employee_name LIKE ? OR e.employee_code LIKE ? OR ? LIKE ?)';
        $like = '%' . $search . '%';
        $types .= 'ssss';
        $params[] = $like;
        $params[] = $like;
        $params[] = $filterOp;
        $params[] = $like;
    }

    $joinSheet = 'LEFT JOIN contractor_operation_sheets s
                    ON s.employee_id = e.id AND s.status = 1 AND s.operation = ?';
    $typesJoin = 's';
    $paramsJoin = [$filterOp];
    if ($filterMonth > 0) {
        $joinSheet .= ' AND s.month_no = ?';
        $typesJoin .= 'i';
        $paramsJoin[] = $filterMonth;
    }
    if ($filterYear > 0) {
        $joinSheet .= ' AND s.year_no = ?';
        $typesJoin .= 'i';
        $paramsJoin[] = $filterYear;
    }

    // Count: employees matching (each emp once) — for pending rows
    // Display rows = sheets for emp+op (+filters) OR 1 pending row if no sheet
    // Use a subquery approach for accurate filtered count of display rows.
    $countSql = "SELECT COUNT(*) AS c FROM (
                    SELECT e.id, s.id AS sheet_id
                    FROM employees e
                    LEFT JOIN departments d ON d.id = e.department_id
                    {$joinSheet}
                    WHERE {$where}
                 ) t";
    $typesCount = $typesJoin . $types;
    $paramsCount = array_merge($paramsJoin, $params);
    $st = $conn->prepare($countSql);
    $st->bind_param($typesCount, ...$paramsCount);
    $st->execute();
    $filtered = (int) $st->get_result()->fetch_assoc()['c'];
    $st->close();

    // When join finds no sheet, sheet_id is null — still one row per employee.
    // When multiple sheets (no month filter), multiple rows — correct.
    // Deduplicate null-sheet: MySQL LEFT JOIN one null row per emp — OK.

    $orderBy = 'e.employee_name ASC, s.year_no DESC, s.month_no DESC';
    $orderCol = (int) ($_POST['order'][0]['column'] ?? 2);
    $orderMap = [
        0 => 'e.id',
        1 => 's.operation',
        2 => 'e.employee_name',
        3 => 's.month_no',
        4 => 's.year_no',
        5 => 's.total_qty',
        6 => 's.total_amount',
    ];
    if (isset($orderMap[$orderCol])) {
        $orderBy = $orderMap[$orderCol] . ' ' . $orderDir;
    }

    $sql = "SELECT e.id AS employee_id, e.employee_code, e.employee_name,
                   s.id AS sheet_id, s.operation, s.month_no, s.year_no,
                   s.total_qty, s.total_amount, s.salary_generated
            FROM employees e
            LEFT JOIN departments d ON d.id = e.department_id
            {$joinSheet}
            WHERE {$where}
            ORDER BY {$orderBy}
            LIMIT ?, ?";
    $typesSql = $typesJoin . $types . 'ii';
    $paramsSql = array_merge($paramsJoin, $params, [$start, $length]);
    $st = $conn->prepare($sql);
    $st->bind_param($typesSql, ...$paramsSql);
    $st->execute();
    $res = $st->get_result();
    $sr = $start;
    while ($row = $res->fetch_assoc()) {
        $sr++;
        $sheetId = (int) ($row['sheet_id'] ?? 0);
        $empId = (int) $row['employee_id'];
        $opLabel = $sheetId > 0 ? (string) $row['operation'] : $filterOp;
        $name = htmlspecialchars((string) $row['employee_code'] . ' - ' . $row['employee_name']);
        if ($sheetId <= 0) {
            $name .= ' <span class="form-hint">(no rate list yet)</span>';
        }
        $data[] = [
            $sr,
            htmlspecialchars($opLabel),
            $name,
            $sheetId > 0 ? htmlspecialchars($months[(int) $row['month_no']] ?? (string) $row['month_no']) : '—',
            $sheetId > 0 ? (int) $row['year_no'] : '—',
            $sheetId > 0 ? number_format((float) $row['total_qty'], 2) : '—',
            $sheetId > 0 ? number_format((float) $row['total_amount'], 2) : '—',
            $buildActions($sheetId, $empId, $filterOp, $row['employee_code']),
        ];
    }
    $st->close();
} else {
    // All operations: existing sheet list
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

    $where = 's.status = 1';
    $types = '';
    $params = [];
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
    if ($search !== '') {
        $where .= ' AND (s.operation LIKE ? OR e.employee_name LIKE ? OR e.employee_code LIKE ?)';
        $like = '%' . $search . '%';
        $types .= 'sss';
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }

    $from = 'FROM contractor_operation_sheets s
             INNER JOIN employees e ON e.id = s.employee_id';
    $countSql = "SELECT COUNT(*) AS c {$from} WHERE {$where}";
    if ($params) {
        $st = $conn->prepare($countSql);
        $st->bind_param($types, ...$params);
        $st->execute();
        $filtered = (int) $st->get_result()->fetch_assoc()['c'];
        $st->close();
    } else {
        $filtered = (int) $conn->query($countSql)->fetch_assoc()['c'];
    }

    $sql = "SELECT s.id, s.operation, s.month_no, s.year_no, s.total_qty, s.total_amount, s.salary_generated,
                   e.id AS employee_id, e.employee_code, e.employee_name
            {$from} WHERE {$where} ORDER BY {$orderBy} {$orderDir} LIMIT ?, ?";
    $types2 = $types . 'ii';
    $params2 = $params;
    $params2[] = $start;
    $params2[] = $length;
    $st = $conn->prepare($sql);
    $st->bind_param($types2, ...$params2);
    $st->execute();
    $res = $st->get_result();
    $sr = $start;
    while ($row = $res->fetch_assoc()) {
        $sr++;
        $name = htmlspecialchars((string) $row['employee_code'] . ' - ' . $row['employee_name']);
        $sid = (int) $row['id'];
        $data[] = [
            $sr,
            htmlspecialchars((string) $row['operation']),
            $name,
            htmlspecialchars($months[(int) $row['month_no']] ?? (string) $row['month_no']),
            (int) $row['year_no'],
            number_format((float) $row['total_qty'], 2),
            number_format((float) $row['total_amount'], 2),
            $buildActions($sid, (int) $row['employee_id'], (string) $row['operation'], $row['employee_code']),
        ];
    }
    $st->close();
}

$conn->close();
echo json_encode(['draw' => $draw, 'recordsTotal' => $total, 'recordsFiltered' => $filtered, 'data' => $data]);
