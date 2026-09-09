<?php
/**
 * Example Employee Import Excel (HTML .xls) template
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/employee_helper.php';

requireLogin();

$deptId = isset($_GET['department_id']) ? (int) $_GET['department_id'] : 0;
$deptName = '';
if ($deptId > 0) {
    $dept = getDepartmentById($deptId);
    if ($dept) {
        $deptName = (string) $dept['department_name'];
    }
}
if ($deptName === '') {
    $deptName = 'ADMINISTRATION';
}

$filename = 'Employee_Import_Example_' . date('Ymd') . '.xls';
header('Content-Type: application/vnd.ms-excel; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$headers = employeeImportHeaders();

$sample = [
    [
        '', // auto code
        '',
        'Salary',
        'SAMPLE EMPLOYEE ONE',
        'FATHER NAME',
        $deptName,
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
        'Example row — replace with real data',
    ],
    [
        'AS76098',
        'AS76098',
        'Salary',
        'SAMPLE EMPLOYEE TWO',
        'HUSBAND NAME',
        $deptName,
        'Helper',
        '20-08-1998',
        '15-06-2025',
        '',
        '9123456780',
        '',
        '',
        '',
        '',
        '',
        'Night',
        '20:00 - 05:00',
        'Yes',
        '100123456789',
        '',
        '',
        '',
        '',
        '12000',
        '',
        'Sunday',
        'No',
        'No',
        'Yes',
        '',
    ],
];

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
echo '<p>Dates = DD-MM-YYYY · Leave employee_code blank to auto-generate · pay_type = Salary / Jobwork / ContractorMain</p>';
echo '</body></html>';
exit;
