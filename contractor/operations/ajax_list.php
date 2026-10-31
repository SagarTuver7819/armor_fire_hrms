<?php
require_once __DIR__ . '/../_bootstrap.php';
require_once __DIR__ . '/../../includes/payroll_helper.php';
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
ensurePayrollTables($conn);

$filterOp = trim((string) ($_POST['filter_operation'] ?? ''));
$filterEmp = (int) ($_POST['filter_employee'] ?? 0);
$filterMonth = (int) ($_POST['filter_month'] ?? 0);
$filterYear = (int) ($_POST['filter_year'] ?? 0);

$total = (int) $conn->query('SELECT COUNT(*) AS c FROM contractor_operation_sheets s WHERE s.status = 1')->fetch_assoc()['c'];

$buildSheetActions = static function ($sheetId, $empCode, $operation) {
    $sid = (int) $sheetId;
    $empCode = htmlspecialchars((string) $empCode);
    $operation = htmlspecialchars((string) $operation);
    return '<div class="action-links" onclick="event.stopPropagation();">'
        . '<a href="' . htmlspecialchars(app_url('contractor/operations/edit.php?id=' . $sid)) . '" class="action-btn edit" title="Edit"><i class="fa-solid fa-pen"></i></a>'
        . '<a href="' . htmlspecialchars(app_url('contractor/operations/print_sheet.php?id=' . $sid)) . '" class="action-btn print" title="Print PDF" target="_blank"><i class="fa-solid fa-print"></i></a>'
        . '<a href="' . htmlspecialchars(app_url('contractor/operations/print_sheet.php?id=' . $sid . '&format=excel')) . '" class="action-btn excel" title="Excel"><i class="fa-solid fa-file-excel"></i></a>'
        . '<a href="' . htmlspecialchars(app_url('contractor/operations/generate.php?id=' . $sid)) . '" class="action-btn salary" title="Generate Salary"><i class="fa-solid fa-indian-rupee-sign"></i></a>'
        . '<a href="' . htmlspecialchars(app_url('contractor/operations/delete.php?id=' . $sid)) . '" class="action-btn delete btn-delete" data-name="' . $empCode . ' ' . $operation . '" title="Delete"><i class="fa-solid fa-trash"></i></a>'
        . '</div>';
};

$buildManualActions = static function ($deptId, $empId, $operation, $month, $year) {
    $deptId = (int) $deptId;
    $qs = http_build_query([
        'department_id' => $deptId,
        'month' => (int) $month,
        'year' => (int) $year,
        'from' => 'modules',
    ]);
    $addOps = http_build_query([
        'operation' => $operation,
        'employee_id' => (int) $empId,
    ]);
    return '<div class="action-links" onclick="event.stopPropagation();">'
        . '<a href="' . htmlspecialchars(app_url('payroll/jobwork.php?' . $qs)) . '" class="action-btn excel" title="Open Jobwork Entry"><i class="fa-solid fa-list"></i> Entries</a>'
        . '<a href="' . htmlspecialchars(app_url('contractor/operations/edit.php?' . $addOps)) . '" class="action-btn edit" title="Add Ops Rate Sheet"><i class="fa-solid fa-plus"></i> Ops</a>'
        . '</div>';
};

$buildPendingActions = static function ($empId, $operation) {
    $addOps = http_build_query([
        'operation' => $operation,
        'employee_id' => (int) $empId,
    ]);
    return '<div class="action-links" onclick="event.stopPropagation();">'
        . '<a href="' . htmlspecialchars(app_url('contractor/operations/edit.php?' . $addOps)) . '" class="action-btn edit" title="Add Rate List"><i class="fa-solid fa-plus"></i> Add</a>'
        . '</div>';
};

$data = [];
$filtered = 0;

if ($filterOp !== '') {
    // Resolve department id for this operation name (e.g. CUTTING ↔ dept CUTTING)
    $deptId = 0;
    $stD = $conn->prepare(
        "SELECT id FROM departments
         WHERE status = 1 AND (
            UPPER(TRIM(department_name)) = UPPER(?)
            OR UPPER(TRIM(department_name)) LIKE CONCAT('%', UPPER(?), '%')
         )
         ORDER BY (UPPER(TRIM(department_name)) = UPPER(?)) DESC, id ASC
         LIMIT 1"
    );
    if ($stD) {
        $stD->bind_param('sss', $filterOp, $filterOp, $filterOp);
        $stD->execute();
        $dr = $stD->get_result()->fetch_assoc();
        $stD->close();
        $deptId = (int) ($dr['id'] ?? 0);
    }

    /** @var array<int, array<string, mixed>> $rows */
    $rows = [];
    $sheetKeys = []; // empId-month-year that already have ops sheet

    // 1) Ops Rate sheets for this operation
    $whereS = 's.status = 1 AND s.operation = ?';
    $typesS = 's';
    $paramsS = [$filterOp];
    if ($filterEmp > 0) {
        $whereS .= ' AND s.employee_id = ?';
        $typesS .= 'i';
        $paramsS[] = $filterEmp;
    }
    if ($filterMonth > 0) {
        $whereS .= ' AND s.month_no = ?';
        $typesS .= 'i';
        $paramsS[] = $filterMonth;
    }
    if ($filterYear > 0) {
        $whereS .= ' AND s.year_no = ?';
        $typesS .= 'i';
        $paramsS[] = $filterYear;
    }
    if ($search !== '') {
        $whereS .= ' AND (e.employee_name LIKE ? OR e.employee_code LIKE ?)';
        $like = '%' . $search . '%';
        $typesS .= 'ss';
        $paramsS[] = $like;
        $paramsS[] = $like;
    }
    $sqlS = "SELECT s.id, s.employee_id, s.operation, s.month_no, s.year_no, s.total_qty, s.total_amount,
                    e.employee_code, e.employee_name, e.department_id
             FROM contractor_operation_sheets s
             INNER JOIN employees e ON e.id = s.employee_id
             WHERE {$whereS}";
    $st = $conn->prepare($sqlS);
    $st->bind_param($typesS, ...$paramsS);
    $st->execute();
    $res = $st->get_result();
    while ($row = $res->fetch_assoc()) {
        $eid = (int) $row['employee_id'];
        $m = (int) $row['month_no'];
        $y = (int) $row['year_no'];
        $sheetKeys[$eid . '-' . $m . '-' . $y] = true;
        $rows[] = [
            'kind' => 'sheet',
            'sheet_id' => (int) $row['id'],
            'employee_id' => $eid,
            'employee_code' => $row['employee_code'],
            'employee_name' => $row['employee_name'],
            'department_id' => (int) ($row['department_id'] ?? $deptId),
            'operation' => (string) $row['operation'],
            'month_no' => $m,
            'year_no' => $y,
            'total_qty' => (float) $row['total_qty'],
            'total_amount' => (float) $row['total_amount'],
            'sort' => sprintf('%04d-%02d-%s', $y, $m, $row['employee_code']),
        ];
    }
    $st->close();

    // 2) Manual Jobwork Entry monthly totals for matching department (CUTTING etc.)
    //    Same source as payroll/jobwork.php — show like other ops sheets.
    if ($deptId > 0) {
        $whereJ = 'e.department_id = ? AND e.status = 1';
        $typesJ = 'i';
        $paramsJ = [$deptId];
        if ($filterEmp > 0) {
            $whereJ .= ' AND e.id = ?';
            $typesJ .= 'i';
            $paramsJ[] = $filterEmp;
        }
        if ($filterMonth > 0) {
            $whereJ .= ' AND MONTH(j.work_date) = ?';
            $typesJ .= 'i';
            $paramsJ[] = $filterMonth;
        }
        if ($filterYear > 0) {
            $whereJ .= ' AND YEAR(j.work_date) = ?';
            $typesJ .= 'i';
            $paramsJ[] = $filterYear;
        }
        if ($search !== '') {
            $whereJ .= ' AND (e.employee_name LIKE ? OR e.employee_code LIKE ?)';
            $like = '%' . $search . '%';
            $typesJ .= 'ss';
            $paramsJ[] = $like;
            $paramsJ[] = $like;
        }
        $sqlJ = "SELECT e.id AS employee_id, e.employee_code, e.employee_name, e.department_id,
                        MONTH(j.work_date) AS month_no, YEAR(j.work_date) AS year_no,
                        COALESCE(SUM(j.quantity),0) AS total_qty,
                        COALESCE(SUM(j.amount),0) AS total_amount
                 FROM jobwork_entries j
                 INNER JOIN employees e ON e.id = j.employee_id
                 WHERE {$whereJ}
                 GROUP BY e.id, e.employee_code, e.employee_name, e.department_id,
                          YEAR(j.work_date), MONTH(j.work_date)";
        $st = $conn->prepare($sqlJ);
        $st->bind_param($typesJ, ...$paramsJ);
        $st->execute();
        $res = $st->get_result();
        while ($row = $res->fetch_assoc()) {
            $eid = (int) $row['employee_id'];
            $m = (int) $row['month_no'];
            $y = (int) $row['year_no'];
            $key = $eid . '-' . $m . '-' . $y;
            if (!empty($sheetKeys[$key])) {
                // Sheet already covers this month — skip duplicate manual row
                continue;
            }
            $rows[] = [
                'kind' => 'manual',
                'sheet_id' => 0,
                'employee_id' => $eid,
                'employee_code' => $row['employee_code'],
                'employee_name' => $row['employee_name'],
                'department_id' => (int) ($row['department_id'] ?? $deptId),
                'operation' => $filterOp,
                'month_no' => $m,
                'year_no' => $y,
                'total_qty' => (float) $row['total_qty'],
                'total_amount' => (float) $row['total_amount'],
                'sort' => sprintf('%04d-%02d-%s', $y, $m, $row['employee_code']),
            ];
        }
        $st->close();
    }

    // 3) Pending employees in dept with no sheet and no manual entries (optional roster)
    $haveEmp = [];
    foreach ($rows as $r) {
        $haveEmp[(int) $r['employee_id']] = true;
    }
    if ($deptId > 0 && $filterMonth <= 0 && $filterYear <= 0) {
        $jw = contractorJobworkWhere();
        $whereP = "{$jw} AND e.department_id = ?";
        $typesP = 'i';
        $paramsP = [$deptId];
        if ($filterEmp > 0) {
            $whereP .= ' AND e.id = ?';
            $typesP .= 'i';
            $paramsP[] = $filterEmp;
        }
        if ($search !== '') {
            $whereP .= ' AND (e.employee_name LIKE ? OR e.employee_code LIKE ?)';
            $like = '%' . $search . '%';
            $typesP .= 'ss';
            $paramsP[] = $like;
            $paramsP[] = $like;
        }
        $sqlP = "SELECT e.id, e.employee_code, e.employee_name, e.department_id
                 FROM employees e
                 WHERE {$whereP}
                 ORDER BY e.employee_code ASC";
        $st = $conn->prepare($sqlP);
        $st->bind_param($typesP, ...$paramsP);
        $st->execute();
        $res = $st->get_result();
        while ($row = $res->fetch_assoc()) {
            $eid = (int) $row['id'];
            if (!empty($haveEmp[$eid])) {
                continue;
            }
            $rows[] = [
                'kind' => 'pending',
                'sheet_id' => 0,
                'employee_id' => $eid,
                'employee_code' => $row['employee_code'],
                'employee_name' => $row['employee_name'],
                'department_id' => (int) ($row['department_id'] ?? $deptId),
                'operation' => $filterOp,
                'month_no' => 0,
                'year_no' => 0,
                'total_qty' => 0.0,
                'total_amount' => 0.0,
                'sort' => '0000-00-' . $row['employee_code'],
            ];
        }
        $st->close();
    }

    usort($rows, static function ($a, $b) use ($orderDir) {
        $cmp = strcmp((string) ($b['sort'] ?? ''), (string) ($a['sort'] ?? ''));
        return $orderDir === 'ASC' ? -$cmp : $cmp;
    });

    $filtered = count($rows);
    $slice = array_slice($rows, $start, $length);
    $sr = $start;
    foreach ($slice as $row) {
        $sr++;
        $kind = $row['kind'];
        $name = htmlspecialchars((string) $row['employee_code'] . ' - ' . $row['employee_name']);
        if ($kind === 'manual') {
            $name .= ' <span class="form-hint">(Jobwork Entry)</span>';
        } elseif ($kind === 'pending') {
            $name .= ' <span class="form-hint">(no rate list yet)</span>';
        }
        if ($kind === 'sheet') {
            $actions = $buildSheetActions($row['sheet_id'], $row['employee_code'], $row['operation']);
            $monthLab = htmlspecialchars($months[(int) $row['month_no']] ?? (string) $row['month_no']);
            $yearLab = (int) $row['year_no'];
            $qtyLab = number_format((float) $row['total_qty'], 2);
            $amtLab = number_format((float) $row['total_amount'], 2);
        } elseif ($kind === 'manual') {
            $actions = $buildManualActions(
                (int) $row['department_id'],
                (int) $row['employee_id'],
                $filterOp,
                (int) $row['month_no'],
                (int) $row['year_no']
            );
            $monthLab = htmlspecialchars($months[(int) $row['month_no']] ?? (string) $row['month_no']);
            $yearLab = (int) $row['year_no'];
            $qtyLab = number_format((float) $row['total_qty'], 2);
            $amtLab = number_format((float) $row['total_amount'], 2);
        } else {
            $actions = $buildPendingActions((int) $row['employee_id'], $filterOp);
            $monthLab = '—';
            $yearLab = '—';
            $qtyLab = '—';
            $amtLab = '—';
        }
        $data[] = [
            $sr,
            htmlspecialchars((string) $row['operation']),
            $name,
            $monthLab,
            $yearLab,
            $qtyLab,
            $amtLab,
            $actions,
        ];
    }
} else {
    // All operations: existing sheet list only
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

    $sql = "SELECT s.id, s.operation, s.month_no, s.year_no, s.total_qty, s.total_amount,
                   e.employee_code, e.employee_name
            {$from} WHERE {$where} ORDER BY {$orderBy} {$orderDir} LIMIT ?, ?";
    $types2 = $types . 'ii';
    $params2 = array_merge($params, [$start, $length]);
    $st = $conn->prepare($sql);
    $st->bind_param($types2, ...$params2);
    $st->execute();
    $res = $st->get_result();
    $sr = $start;
    while ($row = $res->fetch_assoc()) {
        $sr++;
        $name = htmlspecialchars((string) $row['employee_code'] . ' - ' . $row['employee_name']);
        $data[] = [
            $sr,
            htmlspecialchars((string) $row['operation']),
            $name,
            htmlspecialchars($months[(int) $row['month_no']] ?? (string) $row['month_no']),
            (int) $row['year_no'],
            number_format((float) $row['total_qty'], 2),
            number_format((float) $row['total_amount'], 2),
            $buildSheetActions((int) $row['id'], $row['employee_code'], $row['operation']),
        ];
    }
    $st->close();
}

$conn->close();
echo json_encode(['draw' => $draw, 'recordsTotal' => $total, 'recordsFiltered' => $filtered, 'data' => $data]);
