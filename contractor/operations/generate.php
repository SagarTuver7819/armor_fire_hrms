<?php
/**
 * Generate Jobwork salary from Operations Rate List sheet
 * Actual = sheet total; Government payslip = attendance-day split
 */

require_once __DIR__ . '/../_bootstrap.php';
require_once __DIR__ . '/../../includes/payroll_helper.php';
require_once __DIR__ . '/../../includes/attendance_helper.php';
require_once __DIR__ . '/../../includes/employee_helper.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id <= 0) {
    header('Location: ' . app_url('contractor/operations/index.php'));
    exit;
}

$conn = getDBConnection();
$sheet = getContractorSheetById($id, $conn);
if (!$sheet) {
    $conn->close();
    header('Location: ' . app_url('contractor/operations/index.php'));
    exit;
}

$employeeId = (int) $sheet['employee_id'];
$month = (int) $sheet['month_no'];
$year = (int) $sheet['year_no'];
$actualQty = (float) $sheet['total_qty'];
$actualAmount = (float) $sheet['total_amount'];

// Sync attendance → diary, then calculate Jobwork salary (govt = attendance based)
ensurePayrollDiaryFromAttendance($employeeId, $month, $year, $conn);
$emp = getEmployeeById($employeeId);
if ($emp) {
    // Ensure pay type treated as Jobwork for calculation
    if (normalizePayType($emp['pay_type'] ?? '') !== 'Jobwork') {
        $emp['pay_type'] = 'Jobwork';
    }
    $calc = calculateEmployeeSalary($emp, $month, $year);
    // Prefer sheet totals as actual source of truth for this generate action
    $calc['jobwork_total'] = $actualAmount;
    $calc['actual_amount'] = $actualAmount;
    if (($calc['govt_gross'] ?? 0) <= 0 && $actualAmount > 0) {
        $att = getPayrollAttendanceBundle($emp, $month, $year, $actualAmount);
        $calc['govt_gross'] = $att['govt_gross'];
        $calc['earnings'] = $att['govt_gross'] + ($att['arrears'] ?? 0);
        $calc['deductions'] = ($att['pf'] ?? 0) + ($att['pt'] ?? 0) + ($att['loan'] ?? 0) + ($att['advance'] ?? 0);
        $calc['net_salary'] = max(0, $calc['earnings'] - $calc['deductions']);
        $calc['present_days'] = $att['present'];
        $calc['working_days'] = $att['month_days'];
        $calc['breakup'] = [
            ['label' => 'Actual (Ops Qty × Rate)', 'type' => 'Info', 'amount' => $actualAmount],
            ['label' => 'Govt gross (attendance days)', 'type' => 'Earning', 'amount' => $att['govt_gross']],
        ];
    }
    $calc['generated_from'] = 'operations_rate_list';
    savePayslip($employeeId, $month, $year, $calc);
}

$up = $conn->prepare('UPDATE contractor_operation_sheets SET salary_generated = 1 WHERE id = ?');
$up->bind_param('i', $id);
$up->execute();
$up->close();

$chk = $conn->prepare('SELECT id FROM contractor_salary WHERE sheet_id = ? LIMIT 1');
$chk->bind_param('i', $id);
$chk->execute();
$have = $chk->get_result()->fetch_assoc();
$chk->close();

if (!$have) {
    $ins = $conn->prepare(
        'INSERT INTO contractor_salary (sheet_id, employee_id, operation, month_no, year_no, total_qty, total_amount)
         VALUES (?,?,?,?,?,?,?)'
    );
    $ins->bind_param(
        'iisiidd',
        $id,
        $employeeId,
        $sheet['operation'],
        $month,
        $year,
        $actualQty,
        $actualAmount
    );
    $ins->execute();
    $ins->close();
} else {
    $upd = $conn->prepare('UPDATE contractor_salary SET total_qty=?, total_amount=? WHERE sheet_id=?');
    $upd->bind_param('ddi', $actualQty, $actualAmount, $id);
    $upd->execute();
    $upd->close();
}

$conn->close();
header('Location: ' . app_url('contractor/operations/index.php?msg=generated'));
exit;
