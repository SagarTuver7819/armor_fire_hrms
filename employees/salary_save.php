<?php
/**
 * Save employee-wise salary details
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/employee_helper.php';
require_once __DIR__ . '/../includes/master_helper.php';
require_once __DIR__ . '/../includes/payroll_helper.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . app_url('dashboard.php'));
    exit;
}

$employeeId = (int) ($_POST['employee_id'] ?? 0);
$emp = $employeeId > 0 ? getEmployeeById($employeeId) : null;
if (!$emp) {
    die('Employee not found.');
}

$payType = (($emp['pay_type'] ?? 'Salary') === 'Jobwork') ? 'Jobwork' : 'Salary';
$conn = getDBConnection();
ensurePayrollTables($conn);

$stmtDel = $conn->prepare("DELETE FROM employee_salary_details WHERE employee_id = ?");
$stmtDel->bind_param('i', $employeeId);
$stmtDel->execute();
$stmtDel->close();

if ($payType === 'Jobwork') {
    $slabId = (int) ($_POST['slab_id'] ?? 0);
    $slab = $slabId > 0 ? getMasterRow('salary_slabs', $slabId) : null;
    if ($slab) {
        $lineType = 'slab';
        $label = $slab['slab_name'];
        $ctype = 'Earning';
        $calc = 'Fixed';
        $amount = (float) $slab['monthly_salary'];
        $compId = 0;
        $stmt = $conn->prepare(
            "INSERT INTO employee_salary_details
             (employee_id, line_type, component_id, slab_id, label, component_type, calculation, amount)
             VALUES (?,?,?,?,?,?,?,?)"
        );
        $stmt->bind_param('isiisssd', $employeeId, $lineType, $compId, $slabId, $label, $ctype, $calc, $amount);
        $stmt->execute();
        $stmt->close();
    }
} else {
    $ids = $_POST['comp_id'] ?? [];
    $labels = $_POST['comp_label'] ?? [];
    $types = $_POST['comp_type'] ?? [];
    $calcs = $_POST['comp_calc'] ?? [];
    $amounts = $_POST['comp_amount'] ?? [];
    $stmt = $conn->prepare(
        "INSERT INTO employee_salary_details
         (employee_id, line_type, component_id, slab_id, label, component_type, calculation, amount)
         VALUES (?,?,?,?,?,?,?,?)"
    );
    $lineType = 'component';
    $slabId = 0;
    foreach ($ids as $i => $cid) {
        $cid = (int) $cid;
        $label = trim((string) ($labels[$i] ?? ''));
        $ctype = trim((string) ($types[$i] ?? 'Earning'));
        $calc = trim((string) ($calcs[$i] ?? 'Fixed'));
        $amount = (float) ($amounts[$i] ?? 0);
        $stmt->bind_param('isiisssd', $employeeId, $lineType, $cid, $slabId, $label, $ctype, $calc, $amount);
        $stmt->execute();
    }
    $stmt->close();
}

$conn->close();
header('Location: ' . app_url('employees/salary.php?id=' . $employeeId . '&msg=saved'));
exit;
