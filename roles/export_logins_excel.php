<?php
/**
 * Admin — Export all employee Login ID / Password sheet (Excel)
 * Columns: Sr, Employee Code, Employee Name, Username, Password, Designation, Department
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permission_helper.php';
require_once __DIR__ . '/../includes/settings.php';

requireAdmin();
ensureRoleTables();

$deptId = isset($_GET['department_id']) ? (int) $_GET['department_id'] : 0;
$rows = fetchAllEmployeePortalCredentials($deptId);
$company = getCompanyName();

$filename = 'Employee_Logins_' . date('Ymd_His') . '.xls';
header('Content-Type: application/vnd.ms-excel; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$headers = [
    'Sr',
    'Employee Code',
    'Employee Name',
    'Username (Login ID)',
    'Password',
    'Designation',
    'Department',
];

echo '<html><head><meta charset="UTF-8"></head><body>';
echo '<h3>' . htmlspecialchars($company) . ' — Employee Login Credentials</h3>';
echo '<p>Generated: ' . htmlspecialchars(date('d-m-Y H:i')) . ' · Active employees · Admin confidential</p>';
echo '<table border="1" cellpadding="4" cellspacing="0">';
echo '<tr>';
foreach ($headers as $h) {
    echo '<th>' . htmlspecialchars($h) . '</th>';
}
echo '</tr>';

$sr = 1;
foreach ($rows as $row) {
    $cells = [
        $sr++,
        (string) ($row['employee_code'] ?? ''),
        (string) ($row['employee_name'] ?? ''),
        (string) ($row['login_id'] ?? ''),
        (string) ($row['login_password'] ?? ''),
        (string) ($row['designation'] ?? ''),
        (string) ($row['department_name'] ?? ''),
    ];
    echo '<tr>';
    foreach ($cells as $c) {
        echo '<td>' . htmlspecialchars($c !== '' ? $c : '—') . '</td>';
    }
    echo '</tr>';
}

echo '</table></body></html>';
exit;
