<?php
/**
 * Export department / all employees as Excel (all columns)
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/employee_helper.php';

requireLogin();

$deptId = isset($_GET['department_id']) ? (int) $_GET['department_id'] : 0;
$conn = getDBConnection();
ensureEmployeesTable($conn);

$sql = "SELECT e.*, d.department_name
        FROM employees e
        LEFT JOIN departments d ON d.id = e.department_id
        WHERE e.status = 1";
$params = [];
$types = '';
if ($deptId > 0) {
    $sql .= " AND e.department_id = ?";
    $types = 'i';
    $params[] = $deptId;
}
$sql .= " ORDER BY d.department_name ASC, e.employee_code ASC";

if ($types !== '') {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $conn->query($sql);
    $stmt = null;
}

$deptName = 'All_Departments';
if ($deptId > 0) {
    $dept = getDepartmentById($deptId);
    if ($dept) {
        $deptName = preg_replace('/[^A-Za-z0-9]+/', '_', $dept['department_name']);
    }
}

$filename = 'Dept_Employee_Report_' . $deptName . '_' . date('Ymd') . '.xls';
header('Content-Type: application/vnd.ms-excel; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$headers = [
    'Sr', 'Employee Code', 'Pay Type', 'Employee Name', 'Father / Husband Name',
    'Department', 'Designation', 'Date of Birth', 'Date of Joining',
    'Mobile', 'Emergency Mobile', 'Aadhar', 'PAN',
    'Permanent Address', 'Present Address',
    'Shift Type', 'Shift Time', 'PF Deduction', 'UAN',
    'Bank Name', 'Account Number', 'IFSC', 'Bank Branch',
    'Decided Salary', 'Reporting Person',
    'Week-off Day', 'Week-off Benefits', 'Holiday Benefits', 'Overtime Benefits',
    'Extra Note', 'Status',
];

echo '<html><head><meta charset="UTF-8"></head><body>';
echo '<table border="1">';
echo '<tr>';
foreach ($headers as $h) {
    echo '<th>' . htmlspecialchars($h) . '</th>';
}
echo '</tr>';

$sr = 1;
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $cells = [
            $sr++,
            $row['employee_code'] ?? '',
            $row['pay_type'] ?? 'Salary',
            $row['employee_name'] ?? '',
            $row['father_husband_name'] ?? '',
            $row['department_name'] ?? '',
            $row['designation'] ?? '',
            formatDateDisplay($row['date_of_birth'] ?? ''),
            formatDateDisplay($row['date_of_joining'] ?? ''),
            $row['mobile_number'] ?? '',
            $row['emergency_mobile'] ?? '',
            $row['aadhar_number'] ?? '',
            $row['pan_number'] ?? '',
            $row['permanent_address'] ?? '',
            $row['present_address'] ?? '',
            $row['shift_type'] ?? '',
            $row['shift_time'] ?? '',
            $row['pf_deduction'] ?? '',
            $row['uan_number'] ?? '',
            $row['bank_name'] ?? '',
            $row['bank_account_number'] ?? '',
            $row['ifsc_code'] ?? '',
            $row['bank_branch_address'] ?? '',
            $row['decided_salary'] ?? '',
            $row['reporting_head'] ?? '',
            $row['week_off_day'] ?? '',
            $row['week_off_benefits'] ?? '',
            $row['holiday_benefits'] ?? '',
            $row['overtime_benefits'] ?? '',
            $row['extra_note'] ?? '',
            ((int) ($row['status'] ?? 1) === 1) ? 'Active' : 'Inactive',
        ];
        echo '<tr>';
        foreach ($cells as $c) {
            echo '<td>' . htmlspecialchars((string) $c) . '</td>';
        }
        echo '</tr>';
    }
}
echo '</table></body></html>';

if ($stmt) {
    $stmt->close();
}
$conn->close();
exit;
