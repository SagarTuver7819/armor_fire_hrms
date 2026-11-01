<?php
/**
 * Full audit: Attendance Report Total Pay Days vs Salary Register total_days
 * for ALL Normal Salary employees / ALL departments (live calc path).
 */
error_reporting(E_ALL);
ini_set('display_errors', '1');
@set_time_limit(600);
@ini_set('memory_limit', '1024M');

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/attendance_helper.php';
require_once __DIR__ . '/includes/salary_register_helper.php';
require_once __DIR__ . '/includes/payroll_reports_helper.php';
require_once __DIR__ . '/includes/payroll_helper.php';
require_once __DIR__ . '/includes/employee_helper.php';

$month = (int) ($argv[1] ?? 10);
$year = (int) ($argv[2] ?? 2026);

echo "=== FULL Attendance vs Salary Register audit ===\n";
echo "Month={$month} Year={$year}\n";

try {
    $conn = getDBConnection();
    echo "DB_OK\n";
} catch (Throwable $e) {
    echo 'CONNECT_FAIL: ' . $e->getMessage() . "\n";
    exit(1);
}

ensureAttendanceTables($conn);
ensureEmployeesTable($conn);

$from = sprintf('%04d-%02d-01', $year, $month);
$to = date('Y-m-t', strtotime($from));

// Live register bundle (includes lock→live attendance refresh)
$bundle = getSalaryRegisterDisplayBundle('salary', $month, $year, 0, 0);
$regById = [];
foreach ($bundle['rows'] as $r) {
    $eid = (int) ($r['employee_id'] ?? 0);
    if ($eid > 0) {
        $regById[$eid] = $r;
    }
}
echo 'Register source: ' . (!empty($bundle['from_lock']) ? 'LOCK+live-att-sync' : 'LIVE') . "\n";
echo 'Register rows: ' . count($regById) . "\n";

$emps = fetchNormalRegisterEmployees(0, 0, $month, $year);
echo 'Eligible Normal employees: ' . count($emps) . "\n";

$holidayCache = [];
$checked = 0;
$mismatch = [];
$missingOnReg = [];
$ok = 0;
$byDept = [];

foreach ($emps as $emp) {
    $eid = (int) $emp['id'];
    $deptId = (int) ($emp['department_id'] ?? 0);
    $deptName = (string) ($emp['department_name'] ?? '(none)');
    $checked++;
    if (!isset($byDept[$deptName])) {
        $byDept[$deptName] = ['ok' => 0, 'bad' => 0, 'missing' => 0];
    }

    if (!isset($holidayCache[$deptId])) {
        $holidayCache[$deptId] = attendanceHolidaySet($conn, $year, $month, $deptId);
    }

    $st = $conn->prepare(
        "SELECT attendance_date, day_status, remarks, working_minutes, punch_in, punch_out, source
         FROM attendance_day_status
         WHERE employee_id = ? AND attendance_date BETWEEN ? AND ?"
    );
    $st->bind_param('iss', $eid, $from, $to);
    $st->execute();
    $res = $st->get_result();
    $dayMap = [];
    while ($row = $res->fetch_assoc()) {
        $date = substr((string) ($row['attendance_date'] ?? ''), 0, 10);
        if ($date !== '') {
            $dayMap[$date] = $row;
        }
    }
    $st->close();

    $attTotals = attendanceBuildEmployeeMonthTotals($emp, $dayMap, $month, $year, $holidayCache[$deptId]);
    $attPay = round((float) ($attTotals['total_pay_days'] ?? 0), 2);
    $attP = round((float) ($attTotals['present'] ?? 0), 2);
    $attWo = round((float) ($attTotals['week_off'] ?? 0), 2);
    $attWoPay = round((float) ($attTotals['week_off_paid'] ?? 0), 2);
    $attHol = round((float) ($attTotals['holiday'] ?? 0), 2);
    $attHolPay = round((float) ($attTotals['holiday_paid'] ?? 0), 2);
    $attPl = round((float) ($attTotals['PL'] ?? 0), 2);
    $attSl = round((float) ($attTotals['SL'] ?? 0), 2);
    $attDl = round((float) ($attTotals['DL'] ?? 0), 2);
    $attCoff = round((float) ($attTotals['C-Off'] ?? 0), 2);

    if (!isset($regById[$eid])) {
        $missingOnReg[] = [
            'dept' => $deptName,
            'code' => $emp['employee_code'] ?? '',
            'name' => $emp['employee_name'] ?? '',
            'att_pay' => $attPay,
        ];
        $byDept[$deptName]['missing']++;
        continue;
    }

    $r = $regById[$eid];
    $regDays = round((float) ($r['total_days'] ?? 0), 2);
    $regP = round((float) ($r['present'] ?? 0), 2);
    $regWo = round((float) ($r['week_off'] ?? 0), 2);
    $regHol = round((float) ($r['holiday'] ?? 0), 2);
    $regPl = round((float) ($r['pl'] ?? 0), 2);
    $regSl = round((float) ($r['sl'] ?? 0), 2);
    $regDl = round((float) ($r['dl'] ?? 0), 2);
    $regCoff = round((float) ($r['coff'] ?? 0), 2);

    $diffs = [
        'pay' => round($attPay - $regDays, 2),
        'P' => round($attP - $regP, 2),
        'WO' => round($attWoPay - $regWo, 2),
        'H' => round($attHolPay - $regHol, 2),
        'PL' => round($attPl - $regPl, 2),
        'SL' => round($attSl - $regSl, 2),
        'DL' => round($attDl - $regDl, 2),
        'CO' => round($attCoff - $regCoff, 2),
    ];
    $bad = false;
    foreach ($diffs as $d) {
        if (abs($d) >= 0.01) {
            $bad = true;
            break;
        }
    }

    if ($bad) {
        $byDept[$deptName]['bad']++;
        $mismatch[] = [
            'dept' => $deptName,
            'code' => $emp['employee_code'] ?? '',
            'name' => $emp['employee_name'] ?? '',
            'wo_ben' => $emp['week_off_benefits'] ?? '',
            'hol_ben' => $emp['holiday_benefits'] ?? '',
            'att' => compact('attPay', 'attP', 'attWo', 'attWoPay', 'attHol', 'attHolPay', 'attPl', 'attSl', 'attDl', 'attCoff'),
            'reg' => compact('regDays', 'regP', 'regWo', 'regHol', 'regPl', 'regSl', 'regDl', 'regCoff'),
            'diffs' => $diffs,
        ];
    } else {
        $byDept[$deptName]['ok']++;
        $ok++;
    }
}

echo "\nChecked: {$checked} | OK: {$ok} | Mismatch: " . count($mismatch) . " | Missing on register: " . count($missingOnReg) . "\n";

echo "\n--- Department summary ---\n";
ksort($byDept);
foreach ($byDept as $dn => $c) {
    $flag = ($c['bad'] > 0 || $c['missing'] > 0) ? ' !!' : '';
    echo sprintf(
        "%s | ok=%d bad=%d missing=%d%s\n",
        $dn,
        $c['ok'],
        $c['bad'],
        $c['missing'],
        $flag
    );
}

if ($mismatch) {
    echo "\n--- Mismatches ---\n";
    foreach ($mismatch as $m) {
        echo sprintf(
            "[%s] %s %s | ben WO=%s HOL=%s\n",
            $m['dept'],
            $m['code'],
            $m['name'],
            $m['wo_ben'],
            $m['hol_ben']
        );
        $a = $m['att'];
        $r = $m['reg'];
        echo sprintf(
            "  ATT pay=%.2f P=%.2f WOpay=%.2f Hpay=%.2f PL=%.2f SL=%.2f DL=%.2f CO=%.2f\n",
            $a['attPay'], $a['attP'], $a['attWoPay'], $a['attHolPay'], $a['attPl'], $a['attSl'], $a['attDl'], $a['attCoff']
        );
        echo sprintf(
            "  REG days=%.2f P=%.2f WO=%.2f H=%.2f PL=%.2f SL=%.2f DL=%.2f CO=%.2f\n",
            $r['regDays'], $r['regP'], $r['regWo'], $r['regHol'], $r['regPl'], $r['regSl'], $r['regDl'], $r['regCoff']
        );
        $parts = [];
        foreach ($m['diffs'] as $k => $v) {
            if (abs($v) >= 0.01) {
                $parts[] = "{$k}={$v}";
            }
        }
        echo '  DIFF ' . implode(' ', $parts) . "\n";
    }
}

if ($missingOnReg) {
    echo "\n--- On Attendance eligibility but NOT on Register ---\n";
    foreach ($missingOnReg as $m) {
        echo sprintf("  [%s] %s %s att_pay=%.2f\n", $m['dept'], $m['code'], $m['name'], $m['att_pay']);
    }
}

$conn->close();
$pass = count($mismatch) === 0 && count($missingOnReg) === 0;
echo "\n=== RESULT: " . ($pass ? 'ALL MATCH' : 'HAS MISMATCHES') . " ===\n";
exit($pass ? 0 : 2);
