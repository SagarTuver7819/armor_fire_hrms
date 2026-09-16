<?php
/**
 * Example Employee Import Excel (HTML .xls) template
 * Department-wise: department column omitted (locked to selected department)
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/employee_helper.php';

requireLogin();

$deptId = isset($_GET['department_id']) ? (int) $_GET['department_id'] : 0;
$deptName = '';
$omitDepartment = false;
if ($deptId > 0) {
    $dept = getDepartmentById($deptId);
    if ($dept) {
        $deptName = (string) $dept['department_name'];
        $omitDepartment = true;
    }
}
if ($deptName === '') {
    $deptName = 'ADMINISTRATION';
}

$safeDept = preg_replace('/[^A-Za-z0-9_\-]+/', '_', $deptName);
$filename = $omitDepartment
    ? ('Employee_Import_' . $safeDept . '_' . date('Ymd') . '.xls')
    : ('Employee_Import_Example_' . date('Ymd') . '.xls');
header('Content-Type: application/vnd.ms-excel; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$headers = employeeImportHeaders($omitDepartment);

// Sample rows — without department column when locked to a department page
$row1 = [
    '', // auto code
    '',
    'Salary',
    'SAMPLE EMPLOYEE ONE',
    'FATHER NAME',
];
if (!$omitDepartment) {
    $row1[] = $deptName;
}
$row1 = array_merge($row1, [
    'Operator',
    '15-01-1995',
    '01-04-2024',
    '',
    '9876543210',
    '9876500000',
    '1234 5678 9012',
    'ABCDE1234F',
    'Kanpur Nagar, UP',
    'Kanpur Nagar, UP',
    'Day',
    '09:00 - 18:00',
    'No',
    '',
    'SBI',
    '12345678901',
    'SBIN0001234',
    'Kanpur Branch',
    '15000',
    '',
    'Sunday',
    'Yes',
    'Yes',
    'No',
    $omitDepartment
        ? ('Import into ' . $deptName . ' — replace with real data')
        : 'Example row — replace with real data',
]);

$row2 = [
    'AS76098',
    'AS76098',
    'Salary',
    'SAMPLE EMPLOYEE TWO',
    'HUSBAND NAME',
];
if (!$omitDepartment) {
    $row2[] = $deptName;
}
$row2 = array_merge($row2, [
    'Helper',
    '20-08-1998',
    '15-06-2025',
    '',
    '9123456780',
    '',
    '',
    '',
    'Kanpur',
    'Kanpur',
    'Day',
    '09:00 - 18:00',
    'Yes',
    '100123456789',
    'BOB',
    '998877665544',
    'BARB0KANPUR',
    'Kanpur',
    '12000',
    '',
    'Sunday',
    'No',
    'No',
    'Yes',
    'Second sample row',
]);

$sample = [$row1, $row2];

echo '<html><head><meta charset="UTF-8"></head><body>';
echo '<table border="1">';
echo '<tr>';
foreach ($headers as $h) {
    echo '<th>' . htmlspecialchars($h) . '</th>';
}
echo '</tr>';
foreach ($sample as $row) {
    echo '<tr>';
    foreach ($row as $cell) {
        echo '<td>' . htmlspecialchars((string) $cell) . '</td>';
    }
    echo '</tr>';
}
echo '</table>';
if ($omitDepartment) {
    echo '<p>Department locked: <strong>' . htmlspecialchars($deptName) . '</strong> · No department column needed · Dates = DD-MM-YYYY · Blank employee_code = auto</p>';
} else {
    echo '<p>Dates = DD-MM-YYYY · Leave employee_code blank to auto-generate · pay_type = Salary / Jobwork / ContractorMain · department = exact master name</p>';
}
echo '</body></html>';
exit;
