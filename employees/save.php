<?php
/**
 * Save Employee (Insert / Update)
 * Simple POST handler — easy to read for all developers.
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/employee_helper.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . app_url('dashboard.php'));
    exit;
}

$id            = (int) ($_POST['id'] ?? 0);
$departmentId  = (int) ($_POST['department_id'] ?? 0);

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

// Empty values as NULL for DB
$dob    = ($dob === '') ? null : $dob;
$doj    = ($doj === '') ? null : $doj;
$salary = ($salary === '') ? null : $salary;

if ($departmentId <= 0 || $employeeName === '') {
    die('Department and Employee Name are required. <a href="javascript:history.back()">Go Back</a>');
}

$conn = getDBConnection();
ensureEmployeesTable($conn);
$createdBy = (int) ($_SESSION['user_id'] ?? 0);

if ($id > 0) {
    $sql = "UPDATE employees SET
        department_id=?, employee_name=?, father_husband_name=?,
        permanent_address=?, present_address=?, mobile_number=?, emergency_mobile=?,
        aadhar_number=?, pan_number=?, date_of_birth=?, designation=?, date_of_joining=?,
        shift_type=?, shift_time=?, pf_deduction=?, uan_number=?,
        bank_name=?, bank_account_number=?, ifsc_code=?, bank_branch_address=?,
        decided_salary=?, reporting_head=?, extra_note=?, week_off_day=?,
        week_off_benefits=?, holiday_benefits=?, overtime_benefits=?
        WHERE id=?";

    $stmt = $conn->prepare($sql);
    // 28 params: i + 26 s/mixed + i  → use all strings except ids
    $stmt->bind_param(
        'issssssssssssssssssssssssssi',
        $departmentId,
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
        $id
    );
} else {
    $empCode = generateEmployeeCode($conn);

    $sql = "INSERT INTO employees (
        employee_code, department_id, employee_name, father_husband_name,
        permanent_address, present_address, mobile_number, emergency_mobile,
        aadhar_number, pan_number, date_of_birth, designation, date_of_joining,
        shift_type, shift_time, pf_deduction, uan_number,
        bank_name, bank_account_number, ifsc_code, bank_branch_address,
        decided_salary, reporting_head, extra_note, week_off_day,
        week_off_benefits, holiday_benefits, overtime_benefits, created_by
    ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param(
        'sissssssssssssssssssssssssssi',
        $empCode,
        $departmentId,
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
        $createdBy
    );
}

$ok = $stmt->execute();
$error = $stmt->error;
$stmt->close();
$conn->close();

if (!$ok) {
    die('Save failed: ' . htmlspecialchars($error) . ' <a href="javascript:history.back()">Go Back</a>');
}

// After ADD → list with toaster
// After EDIT → details page with toaster
if ($id > 0) {
    header('Location: ' . app_url('employees/view.php?id=' . $id . '&msg=updated'));
} else {
    header('Location: ' . app_url('employees/index.php?department_id=' . $departmentId . '&msg=added'));
}
exit;
