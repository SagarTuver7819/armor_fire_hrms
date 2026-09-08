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

requireLogin();
header('Content-Type: application/json; charset=utf-8');

$deptId = isset($_GET['department_id']) ? (int) $_GET['department_id'] : 0;

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

// Column map depends on All vs Department list
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
        8 => 'e.shift_type',
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
$orderBy = $columns[$orderCol] ?? 'e.id';

$conn = getDBConnection();
ensureEmployeesTable($conn);

// Default list: active only. Search also finds inactive (soft-deleted) so codes still in DB are discoverable.
$where = ($search !== '') ? '1=1' : 'e.status = 1';
$types = '';
$params = [];

if (!$isAll) {
    $where .= ' AND e.department_id = ?';
    $types .= 'i';
    $params[] = $deptId;
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

// Total records (filtered by dept if needed, no search)
if ($isAll) {
    $totalRes = $conn->query("SELECT COUNT(*) AS c FROM employees e WHERE e.status = 1");
} else {
    $stmtT = $conn->prepare("SELECT COUNT(*) AS c FROM employees e WHERE e.status = 1 AND e.department_id = ?");
    $stmtT->bind_param('i', $deptId);
    $stmtT->execute();
    $totalRes = $stmtT->get_result();
}
$totalRow = $totalRes->fetch_assoc();
$recordsTotal = (int) ($totalRow['c'] ?? 0);
if (isset($stmtT)) {
    $stmtT->close();
}

// Filtered count
$countSql = "SELECT COUNT(*) AS c
             FROM employees e
             LEFT JOIN departments d ON d.id = e.department_id
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
                   e.date_of_joining, e.shift_type, e.department_id, e.pay_type, e.status, d.department_name
            FROM employees e
            LEFT JOIN departments d ON d.id = e.department_id
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
    $viewUrl = app_url('employees/view.php?id=' . $id . ($isAll ? '&from=all' : ''));
    $editUrl = app_url('employees/edit.php?id=' . $id . '&department_id=' . (int) $row['department_id']);
    $delUrl  = app_url('employees/delete.php?id=' . $id . '&department_id=' . (int) $row['department_id'] . ($isAll ? '&all=1' : ''));
    $pdfEn   = app_url('employees/pdf.php?id=' . $id . '&lang=en');
    $pdfHi   = app_url('employees/pdf.php?id=' . $id . '&lang=hi');
    $salaryUrl = app_url('employees/salary.php?id=' . $id);
    $payType = function_exists('normalizePayType') ? normalizePayType($row['pay_type'] ?? 'Salary') : ((($row['pay_type'] ?? '') === 'Jobwork') ? 'Jobwork' : 'Salary');
    $payClass = function_exists('payTypeCssClass') ? payTypeCssClass($payType) : ($payType === 'Jobwork' ? 'is-jobwork' : 'is-salary');
    $payLabel = function_exists('payTypeLabel') ? payTypeLabel($payType) : $payType;

    $isInactive = ((int) ($row['status'] ?? 1) !== 1);
    $nameHtml = '<a class="emp-name-link" href="' . htmlspecialchars($viewUrl) . '">' . htmlspecialchars($row['employee_name']) . '</a>';
    if ($isInactive) {
        $nameHtml .= ' <span class="pay-pill is-jobwork" title="Soft-deleted / inactive">Inactive</span>';
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
    $item[] = htmlspecialchars($row['mobile_number'] ?? '-');
    $item[] = htmlspecialchars(formatDateDisplay($row['date_of_joining']));
    $item[] = htmlspecialchars($row['shift_type'] ?? '-');
    $item[] = '<div class="pdf-links" onclick="event.stopPropagation();">'
            . '<a href="' . htmlspecialchars($pdfEn) . '" target="_blank" class="pdf-btn en">EN</a>'
            . '<a href="' . htmlspecialchars($pdfHi) . '" target="_blank" class="pdf-btn hi">HI</a>'
            . '</div>';
    $item[] = '<div class="action-links" onclick="event.stopPropagation();">'
            . '<a href="' . htmlspecialchars($viewUrl) . '" class="action-btn view" title="View"><i class="fa-solid fa-eye"></i></a>'
            . '<a href="' . htmlspecialchars($editUrl) . '" class="action-btn edit" title="Edit"><i class="fa-solid fa-pen"></i></a>'
            . '<a href="' . htmlspecialchars($salaryUrl) . '" class="action-btn view" title="Salary Details"><i class="fa-solid fa-indian-rupee-sign"></i></a>'
            . '<a href="' . htmlspecialchars($delUrl) . '" class="action-btn delete btn-delete" data-name="' . htmlspecialchars($row['employee_name']) . '" title="Delete"><i class="fa-solid fa-trash"></i></a>'
            . '</div>';

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
