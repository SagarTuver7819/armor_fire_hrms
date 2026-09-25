<?php
/**
 * employees/ajax_list.php
 * Server-side DataTables JSON
 * Loads only requested page of rows → prevents heavy page load
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/employee_helper.php';
require_once __DIR__ . '/../includes/permission_helper.php';
require_once __DIR__ . '/../includes/department_head_helper.php';

requireLogin();
$deptId = isset($_GET['department_id']) ? (int) $_GET['department_id'] : 0;
if (isset($_POST['department_id'])) {
    $deptId = (int) $_POST['department_id'];
}
requireAccess('employees', 'view', $deptId);
header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['role_code']) && !empty($_SESSION['custom_role_id'])) {
    refreshHeadedDepartmentsSession();
}

$view   = isset($_GET['view']) && $_GET['view'] === 'exit' ? 'exit' : (isset($_POST['view']) && $_POST['view'] === 'exit' ? 'exit' : 'active');
$isExit = ($view === 'exit');
$isOnField = (isset($_GET['on_field']) && (string) $_GET['on_field'] === '1');
$allowedEmpDepts = allowedDepartmentsFor('employees', 'view');

$sessionEmpId = (int) ($_SESSION['employee_id'] ?? 0);
$selfEmpOnly = function_exists('isEmployee') && isEmployee()
    && $sessionEmpId > 0
    && isOfficeStaffRole();

$draw   = (int) ($_POST['draw'] ?? 1);
$start  = max(0, (int) ($_POST['start'] ?? 0));
$length = (int) ($_POST['length'] ?? 10);
if ($length <= 0 || $length > 100) {
    $length = 10;
}
$search = trim($_POST['search']['value'] ?? '');

$orderCol = (int) ($_POST['order'][0]['column'] ?? 1);
$orderDir = (strtolower($_POST['order'][0]['dir'] ?? 'desc') === 'asc') ? 'ASC' : 'DESC';

$isAll = ($deptId <= 0);

// Column map depends on All vs Department and Active vs Exit list
if ($isExit) {
    if ($isAll) {
        $columns = [
            0 => 'e.id',
            1 => 'e.employee_code',
            2 => 'e.employee_name',
            3 => 'd.department_name',
            4 => 'e.pay_type',
            5 => 'e.designation',
            6 => 'e.mobile_number',
            7 => 'e.date_of_joining',
            8 => 'e.date_of_exit',
            9 => 'e.shift_type',
            10 => 'e.status',
        ];
    } else {
        if ($isOnField) {
            $columns = [
                0 => 'e.id',
                1 => 'e.employee_code',
                2 => 'e.employee_name',
                3 => 'e.pay_type',
                4 => 'e.designation',
                5 => 'ast.name',
                6 => 'aloc.name',
                7 => 'e.mobile_number',
                8 => 'e.date_of_joining',
                9 => 'e.date_of_exit',
                10 => 'e.shift_type',
                11 => 'e.status',
            ];
        } else {
            $columns = [
                0 => 'e.id',
                1 => 'e.employee_code',
                2 => 'e.employee_name',
                3 => 'e.pay_type',
                4 => 'e.designation',
                5 => 'e.mobile_number',
                6 => 'e.date_of_joining',
                7 => 'e.date_of_exit',
                8 => 'e.shift_type',
                9 => 'e.status',
            ];
        }
    }
} elseif ($isAll) {
    $columns = [
        0 => 'e.id',
        1 => 'e.employee_code',
        2 => 'e.employee_name',
        3 => 'd.department_name',
        4 => 'e.pay_type',
        5 => 'e.designation',
        6 => 'e.mobile_number',
        7 => 'e.date_of_joining',
        8 => 'e.shift_type',
    ];
} else {
    if ($isOnField) {
        $columns = [
            0 => 'e.id',
            1 => 'e.employee_code',
            2 => 'e.employee_name',
            3 => 'e.pay_type',
            4 => 'e.designation',
            5 => 'ast.name',
            6 => 'aloc.name',
            7 => 'e.mobile_number',
            8 => 'e.date_of_joining',
            9 => 'e.shift_type',
        ];
    } else {
        $columns = [
            0 => 'e.id',
            1 => 'e.employee_code',
            2 => 'e.employee_name',
            3 => 'e.pay_type',
            4 => 'e.designation',
            5 => 'e.mobile_number',
            6 => 'e.date_of_joining',
            7 => 'e.shift_type',
        ];
    }
}
$orderBy = $columns[$orderCol] ?? 'e.id';

$conn = getDBConnection();
ensureEmployeesTable($conn);
if (function_exists('ensureMasterTables')) {
    require_once __DIR__ . '/../includes/master_helper.php';
    ensureMasterTables($conn);
}
if (!$isOnField && $deptId > 0) {
    $isOnField = isSalesOnFieldDepartmentId($deptId, $conn);
}

// Active list vs Exit list base condition
if ($isExit) {
    $baseCondition = "(e.status = 0 OR (e.date_of_exit IS NOT NULL AND e.date_of_exit != '' AND e.date_of_exit != '0000-00-00'))";
} else {
    $baseCondition = "(e.status = 1 AND (e.date_of_exit IS NULL OR e.date_of_exit = '' OR e.date_of_exit = '0000-00-00'))";
}

$where = $baseCondition;
$types = '';
$params = [];

if (!$isAll) {
    $where .= ' AND e.department_id = ?';
    $types .= 'i';
    $params[] = $deptId;
} elseif (is_array($allowedEmpDepts)) {
    if ($allowedEmpDepts === []) {
        $where .= ' AND 1=0';
    } else {
        $placeholders = implode(',', array_fill(0, count($allowedEmpDepts), '?'));
        $where .= " AND e.department_id IN ({$placeholders})";
        $types .= str_repeat('i', count($allowedEmpDepts));
        foreach ($allowedEmpDepts as $ad) {
            $params[] = (int) $ad;
        }
    }
}

if (!empty($selfEmpOnly) && $sessionEmpId > 0) {
    $where .= ' AND e.id = ?';
    $types .= 'i';
    $params[] = $sessionEmpId;
}

if ($search !== '') {
    $where .= ' AND (
        e.employee_name LIKE ?
        OR e.employee_code LIKE ?
        OR UPPER(TRIM(e.employee_code)) = ?
        OR e.biometric_user_id LIKE ?
        OR e.mobile_number LIKE ?
        OR d.department_name LIKE ?
        OR e.designation LIKE ?
    )';
    $like = '%' . $search . '%';
    $exactCode = strtoupper($search);
    $types .= 'sssssss';
    array_push($params, $like, $like, $exactCode, $like, $like, $like, $like);
}

// Total records (filtered by base condition and dept if needed, no search)
if ($isAll) {
    if (is_array($allowedEmpDepts)) {
        if ($allowedEmpDepts === []) {
            $recordsTotal = 0;
            $totalRes = null;
        } else {
            $ph = implode(',', array_fill(0, count($allowedEmpDepts), '?'));
            $stmtT = $conn->prepare(
                "SELECT COUNT(*) AS c FROM employees e WHERE {$baseCondition} AND e.department_id IN ({$ph})"
            );
            $tTypes = str_repeat('i', count($allowedEmpDepts));
            $stmtT->bind_param($tTypes, ...$allowedEmpDepts);
            $stmtT->execute();
            $totalRes = $stmtT->get_result();
        }
    } else {
        $totalRes = $conn->query("SELECT COUNT(*) AS c FROM employees e WHERE {$baseCondition}");
    }
} else {
    $stmtT = $conn->prepare("SELECT COUNT(*) AS c FROM employees e WHERE {$baseCondition} AND e.department_id = ?");
    $stmtT->bind_param('i', $deptId);
    $stmtT->execute();
    $totalRes = $stmtT->get_result();
}
if (!isset($recordsTotal)) {
    $totalRow = $totalRes ? $totalRes->fetch_assoc() : null;
    $recordsTotal = (int) ($totalRow['c'] ?? 0);
}
if (isset($stmtT)) {
    $stmtT->close();
}

// Filtered count
$countSql = "SELECT COUNT(*) AS c
             FROM employees e
             LEFT JOIN departments d ON d.id = e.department_id
             LEFT JOIN assigned_states ast ON ast.id = e.assigned_state_id
             LEFT JOIN assigned_locations aloc ON aloc.id = e.assigned_location_id
             WHERE $where";
$stmtC = $conn->prepare($countSql);
if ($types !== '') {
    $stmtC->bind_param($types, ...$params);
}
$stmtC->execute();
$recordsFiltered = (int) ($stmtC->get_result()->fetch_assoc()['c'] ?? 0);
$stmtC->close();

// Data page
$dataSql = "SELECT e.id, e.employee_code, e.employee_name, e.designation, e.mobile_number,
                   e.date_of_joining, e.date_of_exit, e.shift_type, e.department_id, e.pay_type, e.status, d.department_name,
                   ast.name AS assigned_state_name, aloc.name AS assigned_location_name
            FROM employees e
            LEFT JOIN departments d ON d.id = e.department_id
            LEFT JOIN assigned_states ast ON ast.id = e.assigned_state_id
            LEFT JOIN assigned_locations aloc ON aloc.id = e.assigned_location_id
            WHERE $where
            ORDER BY $orderBy $orderDir
            LIMIT ?, ?";

$typesData = $types . 'ii';
$paramsData = $params;
$paramsData[] = $start;
$paramsData[] = $length;

$stmt = $conn->prepare($dataSql);
$stmt->bind_param($typesData, ...$paramsData);
$stmt->execute();
$result = $stmt->get_result();

$data = [];
$sr = $start + 1;
while ($row = $result->fetch_assoc()) {
    $id = (int) $row['id'];
    $viewUrl = app_url('employees/view.php?id=' . $id . ($isExit ? '&from=exit' : ($isAll ? '&from=all' : '')));
    $editUrl = app_url('employees/edit.php?id=' . $id . '&department_id=' . (int) $row['department_id']);
    $delUrl  = app_url('employees/delete.php?id=' . $id . '&department_id=' . (int) $row['department_id'] . ($isAll ? '&all=1' : ''));
    $pdfEn   = app_url('employees/pdf.php?id=' . $id . '&lang=en');
    $pdfHi   = app_url('employees/pdf.php?id=' . $id . '&lang=hi');
    $salaryUrl = app_url('employees/salary.php?id=' . $id);
    $payType = function_exists('normalizePayType') ? normalizePayType($row['pay_type'] ?? 'Salary') : ((($row['pay_type'] ?? '') === 'Jobwork') ? 'Jobwork' : 'Salary');
    $payClass = function_exists('payTypeCssClass') ? payTypeCssClass($payType) : ($payType === 'Jobwork' ? 'is-jobwork' : 'is-salary');
    $payLabel = function_exists('payTypeLabel') ? payTypeLabel($payType) : $payType;

    $isDeactive = isEmployeeDeactive($row);
    $nameHtml = '<a class="emp-name-link" href="' . htmlspecialchars($viewUrl) . '">' . htmlspecialchars($row['employee_name']) . '</a>';
    if ($isDeactive && !$isExit) {
        $nameHtml .= ' <span class="pay-pill is-jobwork" title="Soft-deleted / deactive">Deactive</span>';
    }

    $item = [
        $sr++,
        '<span class="code-badge">' . htmlspecialchars($row['employee_code']) . '</span>',
        $nameHtml,
    ];

    if ($isAll) {
        $item[] = htmlspecialchars($row['department_name'] ?? '-');
    }

    $item[] = '<span class="pay-pill ' . $payClass . '">' . htmlspecialchars($payLabel) . '</span>';
    $item[] = htmlspecialchars($row['designation'] ?? '-');
    if ($isOnField && !$isAll) {
        $item[] = htmlspecialchars($row['assigned_state_name'] ?? '-');
        $item[] = htmlspecialchars($row['assigned_location_name'] ?? '-');
    }
    $item[] = htmlspecialchars($row['mobile_number'] ?? '-');
    $item[] = htmlspecialchars(formatDateDisplay($row['date_of_joining']));

    if ($isExit) {
        $exitDateFormatted = formatDateDisplay($row['date_of_exit'] ?? '');
        $exitDateShow = $exitDateFormatted !== '' ? $exitDateFormatted : 'Deactive';
        $item[] = '<span class="exit-date-badge"><i class="fa-solid fa-door-open"></i> ' . htmlspecialchars($exitDateShow) . '</span>';
    }

    $item[] = htmlspecialchars($row['shift_type'] ?? '-');

    if ($isExit) {
        $item[] = '<span class="status-pill status-deactive"><i class="fa-solid fa-circle"></i> Deactive</span>';
    }

    $item[] = '<div class="pdf-links" onclick="event.stopPropagation();">'
            . '<a href="' . htmlspecialchars($pdfEn) . '" target="_blank" class="pdf-btn en">EN</a>'
            . '<a href="' . htmlspecialchars($pdfHi) . '" target="_blank" class="pdf-btn hi">HI</a>'
            . '</div>';

    $rowDeptId = (int) $row['department_id'];
    $canView = canAccess('employees', 'view', $rowDeptId);
    $canEdit = canAccess('employees', 'edit', $rowDeptId);
    $canDelete = canAccess('employees', 'delete', $rowDeptId);
    $actions = '<div class="action-links" onclick="event.stopPropagation();">';
    if ($canView) {
        $actions .= '<a href="' . htmlspecialchars($viewUrl) . '" class="action-btn view" title="View"><i class="fa-solid fa-eye"></i></a>';
    }
    if ($canEdit) {
        $actions .= '<a href="' . htmlspecialchars($editUrl) . '" class="action-btn edit" title="Edit"><i class="fa-solid fa-pen"></i></a>';
        $actions .= '<a href="' . htmlspecialchars($salaryUrl) . '" class="action-btn view" title="Salary Details"><i class="fa-solid fa-indian-rupee-sign"></i></a>';
    }
    if ($canDelete) {
        $actions .= '<a href="' . htmlspecialchars($delUrl) . '" class="action-btn delete btn-delete" data-name="' . htmlspecialchars($row['employee_name']) . '" title="Delete"><i class="fa-solid fa-trash"></i></a>';
    }
    $actions .= '</div>';
    $item[] = $actions;

    $data[] = $item;
}

$stmt->close();
$conn->close();

echo json_encode([
    'draw'            => $draw,
    'recordsTotal'    => $recordsTotal,
    'recordsFiltered' => $recordsFiltered,
    'data'            => $data,
]);
