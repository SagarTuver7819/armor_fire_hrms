<?php
require_once __DIR__ . '/../_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . app_url('contractor/employment/index.php'));
    exit;
}

$id = (int) ($_POST['id'] ?? 0);
$employeeId = (int) ($_POST['employee_id'] ?? 0);
$desType = (($_POST['designation_type'] ?? 'worker') === 'staff') ? 'staff' : 'worker';
$designation = trim($_POST['designation'] ?? '');
$deptId = (int) ($_POST['department_id'] ?? 0);
$subDeptId = (int) ($_POST['sub_department_id'] ?? 0);
$process = trim($_POST['process'] ?? '');
$doj = trim($_POST['date_of_joining'] ?? '');
$conf = trim($_POST['confirmation_date'] ?? '');
$conf = $conf === '' ? null : $conf;
$pf = trim($_POST['employee_pf_no'] ?? '');
$uan = trim($_POST['uan_no'] ?? '');
$pay = (string) ($_POST['payment_mode'] ?? 'Bank');
if (!isset(contractorPaymentModes()[$pay])) {
    $pay = 'Bank';
}
$empType = (string) ($_POST['employment_type'] ?? 'Contract');
if (!isset(contractorEmploymentTypes()[$empType])) {
    $empType = 'Contract';
}
$shiftId = (int) ($_POST['shift_id'] ?? 0);
$outdoor = (($_POST['outdoor_attendance'] ?? 'No') === 'Yes') ? 'Yes' : 'No';

if ($employeeId <= 0 || $designation === '' || $deptId <= 0 || $doj === '' || $shiftId <= 0) {
    die('Employee, designation, department, joining date and shift are required. <a href="javascript:history.back()">Go Back</a>');
}

$conn = getDBConnection();
ensureContractorTables($conn);
ensureMasterTables($conn);
$subDept = getSubDepartmentNameById($subDeptId, $deptId, $conn);

$jw = $conn->prepare("SELECT id FROM employees WHERE id = ? AND status = 1 AND pay_type = 'Jobwork' LIMIT 1");
$jw->bind_param('i', $employeeId);
$jw->execute();
$jwOk = $jw->get_result()->fetch_assoc();
$jw->close();
if (!$jwOk) {
    $conn->close();
    die('Select a Jobwork employee from Join Employee. <a href="javascript:history.back()">Go Back</a>');
}

if ($id > 0) {
    $stmt = $conn->prepare('UPDATE contractor_employment SET
        employee_id=?, designation_type=?, designation=?, department_id=?, sub_department_id=?, sub_department=?, process=?,
        date_of_joining=?, confirmation_date=?, employee_pf_no=?, uan_no=?, payment_mode=?, employment_type=?,
        shift_id=?, outdoor_attendance=?
        WHERE id=? AND status=1');
    $stmt->bind_param(
        'issiissssssssisi',
        $employeeId,
        $desType,
        $designation,
        $deptId,
        $subDeptId,
        $subDept,
        $process,
        $doj,
        $conf,
        $pf,
        $uan,
        $pay,
        $empType,
        $shiftId,
        $outdoor,
        $id
    );
} else {
    $stmt = $conn->prepare('INSERT INTO contractor_employment (
        employee_id, designation_type, designation, department_id, sub_department_id, sub_department, process,
        date_of_joining, confirmation_date, employee_pf_no, uan_no, payment_mode, employment_type,
        shift_id, outdoor_attendance, status
    ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,1)');
    $stmt->bind_param(
        'issiissssssssis',
        $employeeId,
        $desType,
        $designation,
        $deptId,
        $subDeptId,
        $subDept,
        $process,
        $doj,
        $conf,
        $pf,
        $uan,
        $pay,
        $empType,
        $shiftId,
        $outdoor
    );
}
$ok = $stmt->execute();
$error = $stmt->error;
$stmt->close();
$conn->close();
if (!$ok) {
    die('Save failed: ' . htmlspecialchars($error) . ' <a href="javascript:history.back()">Go Back</a>');
}
header('Location: ' . app_url('contractor/employment/index.php?msg=' . ($id > 0 ? 'updated' : 'added')));
exit;
