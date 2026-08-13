<?php
/**
 * Seed 5 test employees for EVERY active department.
 * Run once:  http://localhost/armor_new_hrms/sql/seed_employees.php
 * Or CLI:    php sql/seed_employees.php
 *
 * Safe to re-run: skips if that department already has >= 5 active employees.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/employee_helper.php';

$isCli = (PHP_SAPI === 'cli');
if (!$isCli) {
    header('Content-Type: text/plain; charset=utf-8');
}

$conn = getDBConnection();
ensureEmployeesTable($conn);

$firstNames = ['Rahul', 'Priya', 'Amit', 'Sneha', 'Vikram', 'Neha', 'Rohan', 'Pooja', 'Karan', 'Anjali'];
$lastNames  = ['Sharma', 'Patel', 'Singh', 'Mehta', 'Verma', 'Joshi', 'Shah', 'Khan', 'Desai', 'Gupta'];
$fathers    = ['Ramesh', 'Suresh', 'Mahesh', 'Dinesh', 'Naresh'];
$designations = ['Operator', 'Assistant', 'Executive', 'Supervisor', 'Technician'];
$banks = ['SBI', 'HDFC Bank', 'ICICI Bank', 'Axis Bank', 'Bank of Baroda'];
$weekOffs = ['Sunday', 'Saturday', 'Monday', 'Friday', 'Wednesday'];

$depts = $conn->query("SELECT id, department_name FROM departments WHERE status = 1 ORDER BY id ASC");
if (!$depts) {
    echo "ERROR: departments table missing.\n";
    exit(1);
}

$created = 0;
$skipped = 0;

while ($dept = $depts->fetch_assoc()) {
    $deptId = (int) $dept['id'];
    $stmtC = $conn->prepare("SELECT COUNT(*) AS c FROM employees WHERE department_id = ? AND status = 1");
    $stmtC->bind_param('i', $deptId);
    $stmtC->execute();
    $have = (int) $stmtC->get_result()->fetch_assoc()['c'];
    $stmtC->close();

    $need = max(0, 5 - $have);
    if ($need === 0) {
        echo "[SKIP] {$dept['department_name']} already has {$have} employees\n";
        $skipped++;
        continue;
    }

    for ($i = 0; $i < $need; $i++) {
        $code = generateEmployeeCode($conn);
        $fn = $firstNames[($deptId + $i) % count($firstNames)];
        $ln = $lastNames[($deptId * 3 + $i) % count($lastNames)];
        $name = $fn . ' ' . $ln;
        $father = $fathers[$i % count($fathers)] . ' ' . $ln;
        $desig = $designations[$i % count($designations)];
        $mobile = '98' . str_pad((string) (($deptId * 10 + $i + 11) % 100000000), 8, '0', STR_PAD_LEFT);
        $emergency = '97' . str_pad((string) (($deptId * 7 + $i + 21) % 100000000), 8, '0', STR_PAD_LEFT);
        $aadhar = str_pad((string) (100000000000 + $deptId * 100 + $i), 12, '0', STR_PAD_LEFT);
        $pan = 'ABCDE' . str_pad((string) (($deptId * 5 + $i) % 10000), 4, '0', STR_PAD_LEFT) . 'F';
        $dobYear = 1988 + (($deptId + $i) % 12);
        $dob = sprintf('%d-%02d-%02d', $dobYear, (($i + 1) % 12) + 1, (($i * 3) % 27) + 1);
        $dojYear = 2020 + (($deptId + $i) % 5);
        $doj = sprintf('%d-%02d-0%d', $dojYear, (($i + 3) % 12) + 1, ($i % 8) + 1);
        $shift = ($i % 2 === 0) ? 'Day' : 'Night';
        $shiftTime = $shift === 'Day' ? '09:00 AM - 06:00 PM' : '09:00 PM - 06:00 AM';
        $pf = ($i % 2 === 0) ? 'Yes' : 'No';
        $uan = $pf === 'Yes' ? ('100' . str_pad((string) ($deptId * 10 + $i), 9, '0', STR_PAD_LEFT)) : '';
        $bank = $banks[$i % count($banks)];
        $acc = '10' . str_pad((string) ($deptId * 1000 + $i + 500), 10, '0', STR_PAD_LEFT);
        $ifsc = 'SBIN000' . str_pad((string) (($deptId + $i) % 1000), 3, '0', STR_PAD_LEFT);
        $salary = 15000 + (($deptId + $i) * 500);
        $weekOff = $weekOffs[$i % count($weekOffs)];
        $perm = "House No. " . (10 + $i) . ", Test Colony, City";
        $pres = "Flat " . (100 + $i) . ", Demo Residency, City";
        $head = 'Reporting Head ' . (($deptId % 5) + 1);
        $note = 'Seed test data for ' . $dept['department_name'];
        $createdBy = 1;

        $sql = "INSERT INTO employees (
            employee_code, department_id, employee_name, father_husband_name,
            permanent_address, present_address, mobile_number, emergency_mobile,
            aadhar_number, pan_number, date_of_birth, designation, date_of_joining,
            shift_type, shift_time, pf_deduction, uan_number,
            bank_name, bank_account_number, ifsc_code, bank_branch_address,
            decided_salary, reporting_head, extra_note, week_off_day,
            week_off_benefits, holiday_benefits, overtime_benefits, created_by, status
        ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,1)";

        $stmt = $conn->prepare($sql);
        $branch = $bank . ' Main Branch';
        $wob = ($i % 2 === 0) ? 'Yes' : 'No';
        $hb = 'Yes';
        $ot = ($i % 3 === 0) ? 'Yes' : 'No';

        $stmt->bind_param(
            'sisssssssssssssssssssdssssssi',
            $code,
            $deptId,
            $name,
            $father,
            $perm,
            $pres,
            $mobile,
            $emergency,
            $aadhar,
            $pan,
            $dob,
            $desig,
            $doj,
            $shift,
            $shiftTime,
            $pf,
            $uan,
            $bank,
            $acc,
            $ifsc,
            $branch,
            $salary,
            $head,
            $note,
            $weekOff,
            $wob,
            $hb,
            $ot,
            $createdBy
        );

        if ($stmt->execute()) {
            $created++;
            echo "[OK] {$dept['department_name']} → {$code} {$name}\n";
        } else {
            echo "[ERR] {$dept['department_name']} → {$stmt->error}\n";
        }
        $stmt->close();
    }
}

$total = $conn->query("SELECT COUNT(*) AS c FROM employees WHERE status = 1")->fetch_assoc()['c'];
$conn->close();

echo "\nDone. Created: {$created}, Depts skipped: {$skipped}, Active employees now: {$total}\n";
if (!$isCli) {
    echo "\nYou can close this tab. Open employees/index.php for All Employees Report.\n";
}
