<?php
/**
 * Save Employee (Insert / Update)
 * Simple POST handler — easy to read for all developers.
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/employee_helper.php';
require_once __DIR__ . '/../includes/master_helper.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . app_url('dashboard.php'));
    exit;
}

$id            = (int) ($_POST['id'] ?? 0);
$departmentId  = (int) ($_POST['department_id'] ?? 0);
$subDeptId     = (int) ($_POST['sub_department_id'] ?? 0);
if ($subDeptId > 0 && getSubDepartmentNameById($subDeptId, $departmentId) === '') {
    $subDeptId = 0;
}
$payType       = normalizePayType($_POST['pay_type'] ?? 'Salary');
$mainContractorId = (int) ($_POST['main_contractor_id'] ?? 0);
if ($payType !== 'Jobwork') {
    $mainContractorId = 0;
}
$empCode       = strtoupper(trim($_POST['employee_code'] ?? ''));
$biometricId   = trim($_POST['biometric_user_id'] ?? '');

$employeeName  = trim($_POST['employee_name'] ?? '');
$fatherName    = trim($_POST['father_husband_name'] ?? '');
$permanentAddr = trim($_POST['permanent_address'] ?? '');
$presentAddr   = trim($_POST['present_address'] ?? '');
$mobile        = trim($_POST['mobile_number'] ?? '');
$emergency     = trim($_POST['emergency_mobile'] ?? '');
$aadhar        = trim($_POST['aadhar_number'] ?? '');
$pan           = trim($_POST['pan_number'] ?? '');
$dob           = trim($_POST['date_of_birth'] ?? '');
$designation   = trim($_POST['designation'] ?? '');
$doj           = trim($_POST['date_of_joining'] ?? '');
$shiftType     = ($_POST['shift_type'] ?? 'Day') === 'Night' ? 'Night' : 'Day';
$shiftTime     = trim($_POST['shift_time'] ?? '');
$shiftId       = (int) ($_POST['shift_id'] ?? 0);
$reportingId   = (int) ($_POST['reporting_employee_id'] ?? 0);
$pfDeduction   = ($_POST['pf_deduction'] ?? 'No') === 'Yes' ? 'Yes' : 'No';
$uan           = trim($_POST['uan_number'] ?? '');
$bankName      = trim($_POST['bank_name'] ?? '');
$bankAccount   = trim($_POST['bank_account_number'] ?? '');
$ifsc          = trim($_POST['ifsc_code'] ?? '');
$bankBranch    = trim($_POST['bank_branch_address'] ?? '');
$salary        = trim($_POST['decided_salary'] ?? '');
$reportingHead = trim($_POST['reporting_head'] ?? '');
$extraNote     = trim($_POST['extra_note'] ?? '');
$weekOffDay    = trim($_POST['week_off_day'] ?? '');
$weekOffBen    = ($_POST['week_off_benefits'] ?? 'No') === 'Yes' ? 'Yes' : 'No';
$holidayBen    = ($_POST['holiday_benefits'] ?? 'No') === 'Yes' ? 'Yes' : 'No';
$overtimeBen   = ($_POST['overtime_benefits'] ?? 'No') === 'Yes' ? 'Yes' : 'No';

if ($shiftId > 0) {
    $shift = getMasterRow('shifts', $shiftId);
    if ($shift) {
        $stype = (($shift['shift_type'] ?? '') === 'Night') ? 'Night' : 'Day';
        $shiftType = $stype;
        $range = formatShiftTimeRange($shift);
        $shiftTime = $range !== '' ? $range : trim((string) ($shift['name'] ?? $shiftTime));
    }
}

if ($reportingId > 0 && $reportingId !== $id) {
    $reporter = getEmployeeById($reportingId);
    if ($reporter) {
        $reportingHead = trim(($reporter['employee_code'] ?? '') . ' — ' . ($reporter['employee_name'] ?? ''), " —");
    }
} else {
    $reportingHead = '';
}

// Empty values as NULL for DB
$dob    = ($dob === '') ? null : $dob;
$doj    = ($doj === '') ? null : $doj;
$salary = ($salary === '') ? null : $salary;

if ($departmentId <= 0 || $employeeName === '') {
    die('Department and Employee Name are required. <a href="javascript:history.back()">Go Back</a>');
}

if (!getDepartmentById($departmentId)) {
    die('Selected department is not valid. <a href="javascript:history.back()">Go Back</a>');
}

$conn = getDBConnection();
ensureEmployeesTable($conn);

if ($empCode === '') {
    $empCode = generateEmployeeCode($conn, $payType);
}
if (!preg_match('/^[A-Z0-9][A-Z0-9\-_\/]{0,29}$/i', $empCode)) {
    $conn->close();
    die('Employee code is invalid. Use letters, numbers, - or /. <a href="javascript:history.back()">Go Back</a>');
}
if (!isEmployeeCodeUnique($conn, $empCode, $id)) {
    $conn->close();
    die('Employee code already exists. Use another code. <a href="javascript:history.back()">Go Back</a>');
}

$createdBy = (int) ($_SESSION['user_id'] ?? 0);
$oldAadharFile = '';
$oldPanFile = '';

if ($id > 0) {
    $oldStmt = $conn->prepare('SELECT aadhar_file, pan_file FROM employees WHERE id = ? LIMIT 1');
    $oldStmt->bind_param('i', $id);
    $oldStmt->execute();
    $oldRow = $oldStmt->get_result()->fetch_assoc();
    $oldStmt->close();
    if ($oldRow) {
        $oldAadharFile = (string) ($oldRow['aadhar_file'] ?? '');
        $oldPanFile = (string) ($oldRow['pan_file'] ?? '');
    }
}

if ($id > 0) {
    $sql = "UPDATE employees SET
        employee_code=?, biometric_user_id=?, pay_type=?, department_id=?, sub_department_id=?, employee_name=?, father_husband_name=?,
        permanent_address=?, present_address=?, mobile_number=?, emergency_mobile=?,
        aadhar_number=?, pan_number=?, date_of_birth=?, designation=?, date_of_joining=?,
        shift_type=?, shift_time=?, pf_deduction=?, uan_number=?,
        bank_name=?, bank_account_number=?, ifsc_code=?, bank_branch_address=?,
        decided_salary=?, reporting_head=?,         extra_note=?, week_off_day=?,
        week_off_benefits=?, holiday_benefits=?, overtime_benefits=?, main_contractor_id=?
        WHERE id=?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param(
        'sssiissssssssssssssssssssssssssii',
        $empCode,
        $biometricId,
        $payType,
        $departmentId,
        $subDeptId,
        $employeeName,
        $fatherName,
        $permanentAddr,
        $presentAddr,
        $mobile,
        $emergency,
        $aadhar,
        $pan,
        $dob,
        $designation,
        $doj,
        $shiftType,
        $shiftTime,
        $pfDeduction,
        $uan,
        $bankName,
        $bankAccount,
        $ifsc,
        $bankBranch,
        $salary,
        $reportingHead,
        $extraNote,
        $weekOffDay,
        $weekOffBen,
        $holidayBen,
        $overtimeBen,
        $mainContractorId,
        $id
    );
} else {
    $sql = "INSERT INTO employees (
        employee_code, biometric_user_id, pay_type, department_id, sub_department_id, employee_name, father_husband_name,
        permanent_address, present_address, mobile_number, emergency_mobile,
        aadhar_number, pan_number, date_of_birth, designation, date_of_joining,
        shift_type, shift_time, pf_deduction, uan_number,
        bank_name, bank_account_number, ifsc_code, bank_branch_address,
        decided_salary, reporting_head, extra_note, week_off_day,
        week_off_benefits, holiday_benefits, overtime_benefits, main_contractor_id, created_by
    ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param(
        'sssiissssssssssssssssssssssssssii',
        $empCode,
        $biometricId,
        $payType,
        $departmentId,
        $subDeptId,
        $employeeName,
        $fatherName,
        $permanentAddr,
        $presentAddr,
        $mobile,
        $emergency,
        $aadhar,
        $pan,
        $dob,
        $designation,
        $doj,
        $shiftType,
        $shiftTime,
        $pfDeduction,
        $uan,
        $bankName,
        $bankAccount,
        $ifsc,
        $bankBranch,
        $salary,
        $reportingHead,
        $extraNote,
        $weekOffDay,
        $weekOffBen,
        $holidayBen,
        $overtimeBen,
        $mainContractorId,
        $createdBy
    );
}

$ok = $stmt->execute();
$error = $stmt->error;
$savedId = $id > 0 ? $id : (int) $conn->insert_id;
$stmt->close();

if (!$ok) {
    $conn->close();
    die('Save failed: ' . htmlspecialchars($error) . ' <a href="javascript:history.back()">Go Back</a>');
}

if ($savedId > 0) {
    try {
        $aadharFile = applyEmployeeDocumentUpload('aadhar_file', $savedId, 'aadhar', $oldAadharFile);
        $panFile = applyEmployeeDocumentUpload('pan_file', $savedId, 'pan', $oldPanFile);
    } catch (RuntimeException $ex) {
        $conn->close();
        die('Employee saved, but attachment failed: ' . htmlspecialchars($ex->getMessage()) . ' <a href="javascript:history.back()">Go Back</a>');
    }
    if ($aadharFile !== $oldAadharFile || $panFile !== $oldPanFile) {
        $fileStmt = $conn->prepare('UPDATE employees SET aadhar_file = ?, pan_file = ? WHERE id = ?');
        $fileStmt->bind_param('ssi', $aadharFile, $panFile, $savedId);
        $fileStmt->execute();
        $fileStmt->close();
    }
}

$conn->close();

$fromContractor = (($_POST['from'] ?? '') === 'contractor');

// After ADD → list with toaster
// After EDIT → details page with toaster
if ($fromContractor) {
    header('Location: ' . app_url('contractor/employees/index.php?msg=' . ($id > 0 ? 'updated' : 'added')));
} elseif ($id > 0) {
    header('Location: ' . app_url('employees/view.php?id=' . $id . '&msg=updated'));
} else {
    header('Location: ' . app_url('employees/index.php?department_id=' . $departmentId . '&msg=added'));
}
exit;
