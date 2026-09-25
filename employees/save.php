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
require_once __DIR__ . '/../includes/permission_helper.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . app_url('dashboard.php'));
    exit;
}

$id            = (int) ($_POST['id'] ?? 0);
$departmentId  = (int) ($_POST['department_id'] ?? 0);
requireAccess('employees', $id > 0 ? 'edit' : 'add', $departmentId);
$subDeptId     = (int) ($_POST['sub_department_id'] ?? 0);
if ($subDeptId > 0 && getSubDepartmentNameById($subDeptId, $departmentId) === '') {
    $subDeptId = 0;
}
$payType       = normalizePayType($_POST['pay_type'] ?? 'Salary');
$mainContractorId = (int) ($_POST['main_contractor_id'] ?? 0);
if ($payType !== 'Jobwork') {
    $mainContractorId = 0;
}
$empCode       = forceDetailUpper($_POST['employee_code'] ?? '');
$biometricId   = forceDetailUpper($_POST['biometric_user_id'] ?? '');

$employeeName  = forceDetailUpper($_POST['employee_name'] ?? '');
$fatherName    = forceDetailUpper($_POST['father_husband_name'] ?? '');
$permanentAddr = forceDetailUpper($_POST['permanent_address'] ?? '');
$presentAddr   = forceDetailUpper($_POST['present_address'] ?? '');
$mobile        = forceDetailUpper($_POST['mobile_number'] ?? '');
$emergency     = forceDetailUpper($_POST['emergency_mobile'] ?? '');
$officeEmail   = forceEmailLower($_POST['office_email'] ?? '');
$officeMobile  = forceDetailUpper($_POST['office_mobile'] ?? '');
$deskNo        = forceDetailUpper($_POST['desk_no'] ?? '');
$gender        = trim($_POST['gender'] ?? '');
if (!in_array($gender, ['Male', 'Female'], true)) {
    $gender = '';
}
$maritalStatus = trim($_POST['marital_status'] ?? '');
if (!in_array($maritalStatus, ['Married', 'Unmarried', 'Other'], true)) {
    $maritalStatus = '';
}
$maritalRemark = ($maritalStatus === 'Other') ? forceDetailUpper($_POST['marital_remark'] ?? '') : '';
$aadhar        = forceDetailUpper($_POST['aadhar_number'] ?? '');
$pan           = forceDetailUpper($_POST['pan_number'] ?? '');
$dob           = normalizeDatePost($_POST['date_of_birth'] ?? '', false);
$designation   = forceDetailUpper($_POST['designation'] ?? '');
$doj           = normalizeDatePost($_POST['date_of_joining'] ?? '', false);
$doe           = normalizeDatePost($_POST['date_of_exit'] ?? '', false);
$statusPosted  = isset($_POST['status']) ? ((int) $_POST['status'] === 0 ? 0 : 1) : 1;
$todayYmd      = date('Y-m-d');
$doeStr        = ($doe !== null && $doe !== '') ? (string) $doe : '';

if ($doeStr !== '') {
    // Exit date entered → save it (reflects in Exit Employees tab)
    if ($doeStr <= $todayYmd) {
        $status = 0; // today / past → Deactive
    } else {
        $status = 1; // future exit → Active until that day
    }
} else {
    // No exit date: Active = rejoin / running again; Deactive = inactive without exit date
    $status = $statusPosted;
}
$doe = $doeStr;
$shiftType     = ($_POST['shift_type'] ?? 'Day') === 'Night' ? 'Night' : 'Day';
$shiftTime     = trim($_POST['shift_time'] ?? '');
$shiftId       = (int) ($_POST['shift_id'] ?? 0);
$reportingId   = (int) ($_POST['reporting_employee_id'] ?? 0);
$pfDeduction   = ($_POST['pf_deduction'] ?? 'No') === 'Yes' ? 'Yes' : 'No';
$pfStartDate   = normalizeDatePost($_POST['pf_start_date'] ?? '', false);
$pfEmpContrib  = trim((string) ($_POST['pf_employee_contribution'] ?? ''));
$pfEmpContrib  = ($pfEmpContrib === '') ? null : (float) $pfEmpContrib;
$uan           = forceDetailUpper($_POST['uan_number'] ?? '');
$bankName      = forceDetailUpper($_POST['bank_name'] ?? '');
$bankAccount   = forceDetailUpper($_POST['bank_account_number'] ?? '');
$bankAccountConfirm = forceDetailUpper($_POST['bank_account_number_confirm'] ?? '');
$ifsc          = forceDetailUpper($_POST['ifsc_code'] ?? '');
$bankBranch    = forceDetailUpper($_POST['bank_branch_address'] ?? '');
$salary        = trim($_POST['decided_salary'] ?? '');
$reportingHead = forceDetailUpper($_POST['reporting_head'] ?? '');
$extraNote     = forceDetailUpper($_POST['extra_note'] ?? '');
$weekOffDay    = trim($_POST['week_off_day'] ?? '');
$weekOffBen    = ($_POST['week_off_benefits'] ?? 'No') === 'Yes' ? 'Yes' : 'No';
$holidayBen    = ($_POST['holiday_benefits'] ?? 'No') === 'Yes' ? 'Yes' : 'No';
$overtimeBen   = ($_POST['overtime_benefits'] ?? 'No') === 'Yes' ? 'Yes' : 'No';

if ($bankAccount !== '' || $bankAccountConfirm !== '') {
    if ($bankAccount !== $bankAccountConfirm) {
        die('Bank Account Number and Confirm Account Number do not match. <a href="javascript:history.back()">Go Back</a>');
    }
}

// Bind empty string for optional DATE columns (SQL uses NULLIF → NULL)
$dob = $dob ?? '';
$doj = $doj ?? '';
$doe = $doe ?? '';
$pfStartDate = $pfStartDate ?? '';

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
    $dup = findEmployeeByCode($conn, $empCode, $id);
    $conn->close();
    $dupName = trim((string) ($dup['employee_name'] ?? ''));
    $dupMsg = 'Employee code <strong>' . htmlspecialchars($empCode) . '</strong> already exists';
    if ($dupName !== '') {
        $dupMsg .= ' for <strong>' . htmlspecialchars($dupName) . '</strong>';
    }
    if ($dup && (int) ($dup['status'] ?? 1) !== 1) {
        $dupMsg .= ' (inactive/deleted employee)';
    }
    $dupMsg .= '. Click refresh on the code field to get the next available code.';
    die($dupMsg . ' <a href="javascript:history.back()">Go Back</a>');
}

$createdBy = (int) ($_SESSION['user_id'] ?? 0);
$oldAadharFile = '';
$oldPanFile = '';
$oldPhotoFile = '';

if ($id > 0) {
    $oldStmt = $conn->prepare('SELECT aadhar_file, pan_file, photo_file FROM employees WHERE id = ? LIMIT 1');
    $oldStmt->bind_param('i', $id);
    $oldStmt->execute();
    $oldRow = $oldStmt->get_result()->fetch_assoc();
    $oldStmt->close();
    if ($oldRow) {
        $oldAadharFile = (string) ($oldRow['aadhar_file'] ?? '');
        $oldPanFile = (string) ($oldRow['pan_file'] ?? '');
        $oldPhotoFile = (string) ($oldRow['photo_file'] ?? '');
    }
}

if ($id > 0) {
    $sql = "UPDATE employees SET
        employee_code=?, biometric_user_id=?, pay_type=?, department_id=?, sub_department_id=?, employee_name=?, father_husband_name=?,
        permanent_address=?, present_address=?, mobile_number=?, emergency_mobile=?,
        aadhar_number=?, pan_number=?, date_of_birth=NULLIF(?,''), designation=?, date_of_joining=NULLIF(?,''), date_of_exit=NULLIF(?,''),
        shift_type=?, shift_time=?, pf_deduction=?, uan_number=?,
        bank_name=?, bank_account_number=?, ifsc_code=?, bank_branch_address=?,
        decided_salary=?, reporting_head=?,         extra_note=?, week_off_day=?,
        week_off_benefits=?, holiday_benefits=?, overtime_benefits=?, main_contractor_id=?,
        status=?
        WHERE id=?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param(
        'sssiisssssssssssssssssssssssssssiii',
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
        $doe,
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
        $status,
        $id
    );
} else {
    $sql = "INSERT INTO employees (
        employee_code, biometric_user_id, pay_type, department_id, sub_department_id, employee_name, father_husband_name,
        permanent_address, present_address, mobile_number, emergency_mobile,
        aadhar_number, pan_number, date_of_birth, designation, date_of_joining, date_of_exit,
        shift_type, shift_time, pf_deduction, uan_number,
        bank_name, bank_account_number, ifsc_code, bank_branch_address,
        decided_salary, reporting_head, extra_note, week_off_day,
        week_off_benefits, holiday_benefits, overtime_benefits, main_contractor_id, created_by, status
    ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,NULLIF(?,''),?,NULLIF(?,''),NULLIF(?,''),?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param(
        'sssiisssssssssssssssssssssssssssiii',
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
        $doe,
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
        $createdBy,
        $status
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
    $pfEmpBind = $pfEmpContrib === null ? '' : (string) $pfEmpContrib;
    $extraStmt = $conn->prepare(
        'UPDATE employees SET office_email = ?, office_mobile = ?, desk_no = ?, gender = ?, marital_status = ?, marital_remark = ?,
         pf_start_date = NULLIF(?, \'\'), pf_employee_contribution = NULLIF(?, \'\'), pf_employer_contribution = NULL WHERE id = ?'
    );
    $extraStmt->bind_param(
        'ssssssssi',
        $officeEmail,
        $officeMobile,
        $deskNo,
        $gender,
        $maritalStatus,
        $maritalRemark,
        $pfStartDate,
        $pfEmpBind,
        $savedId
    );
    $extraStmt->execute();
    $extraStmt->close();

    try {
        $aadharFile = applyEmployeeDocumentUpload('aadhar_file', $savedId, 'aadhar', $oldAadharFile);
        $panFile = applyEmployeeDocumentUpload('pan_file', $savedId, 'pan', $oldPanFile);
        $photoFile = applyEmployeeDocumentUpload('photo_file', $savedId, 'photo', $oldPhotoFile);
    } catch (RuntimeException $ex) {
        $conn->close();
        die('Employee saved, but attachment failed: ' . htmlspecialchars($ex->getMessage()) . ' <a href="javascript:history.back()">Go Back</a>');
    }
    if ($aadharFile !== $oldAadharFile || $panFile !== $oldPanFile || $photoFile !== $oldPhotoFile) {
        $fileStmt = $conn->prepare('UPDATE employees SET aadhar_file = ?, pan_file = ?, photo_file = ? WHERE id = ?');
        $fileStmt->bind_param('sssi', $aadharFile, $panFile, $photoFile, $savedId);
        $fileStmt->execute();
        $fileStmt->close();
    }

    if ($savedId > 0) {
        $assignedStateId = (int) ($_POST['assigned_state_id'] ?? 0);
        $assignedLocationId = (int) ($_POST['assigned_location_id'] ?? 0);
        if (!isSalesOnFieldDepartmentId($departmentId, $conn)) {
            $assignedStateId = 0;
            $assignedLocationId = 0;
        } elseif ($assignedStateId > 0 && $assignedLocationId > 0) {
            // Location must belong to selected state
            $chk = $conn->prepare('SELECT id FROM assigned_locations WHERE id = ? AND state_id = ? AND status = 1 LIMIT 1');
            $chk->bind_param('ii', $assignedLocationId, $assignedStateId);
            $chk->execute();
            if (!$chk->get_result()->fetch_assoc()) {
                $assignedLocationId = 0;
            }
            $chk->close();
        }

        $oldStateId = 0;
        $oldLocationId = 0;
        $prev = $conn->prepare('SELECT assigned_state_id, assigned_location_id FROM employees WHERE id = ? LIMIT 1');
        $prev->bind_param('i', $savedId);
        $prev->execute();
        $prevRow = $prev->get_result()->fetch_assoc();
        $prev->close();
        if ($prevRow) {
            $oldStateId = (int) ($prevRow['assigned_state_id'] ?? 0);
            $oldLocationId = (int) ($prevRow['assigned_location_id'] ?? 0);
        }

        $asNull = $assignedStateId > 0 ? (string) $assignedStateId : '';
        $alNull = $assignedLocationId > 0 ? (string) $assignedLocationId : '';
        $asStmt = $conn->prepare(
            'UPDATE employees SET assigned_state_id = NULLIF(?, \'\'), assigned_location_id = NULLIF(?, \'\') WHERE id = ?'
        );
        $asStmt->bind_param('ssi', $asNull, $alNull, $savedId);
        $asStmt->execute();
        $asStmt->close();

        employeeRecordLocationChange(
            $conn,
            $savedId,
            $oldStateId,
            $oldLocationId,
            $assignedStateId,
            $assignedLocationId,
            $createdBy ?? null
        );
    }

    $familyNames = $_POST['family_name'] ?? [];
    $familyRelations = $_POST['family_relation'] ?? [];
    $familyOccupations = $_POST['family_occupation'] ?? [];
    if (!is_array($familyNames)) {
        $familyNames = [];
    }
    if (!is_array($familyRelations)) {
        $familyRelations = [];
    }
    if (!is_array($familyOccupations)) {
        $familyOccupations = [];
    }
    saveEmployeeFamilyMembers($conn, $savedId, $familyNames, $familyRelations, $familyOccupations);

    // Seed / keep salary history in sync with decided salary on join / edit
    if ($salary !== null && (float) $salary > 0) {
        $seedEff = ($doj !== '' && $doj !== '0000-00-00') ? $doj : date('Y-m-d');
        employeeSeedSalaryHistoryIfEmpty($conn, $savedId, (float) $salary, $seedEff, $createdBy ?? null);
        if ($id <= 0) {
            // New join already seeded; decided_salary is set
        } else {
            // Edit without revise popup: if no history change, keep current row; sync as-of today
            employeeSyncDecidedSalaryFromHistory($conn, $savedId);
        }
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
