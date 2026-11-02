<?php
/**
 * AJAX: save Salary Register Memo (Sales On Field) → salary_diary
 * Memo is a deduction: Net = Gross − Deductions − Memo + Arrears.
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permission_helper.php';
require_once __DIR__ . '/../includes/payroll_helper.php';
require_once __DIR__ . '/../includes/payroll_reports_helper.php';
require_once __DIR__ . '/../includes/payroll_approval_helper.php';
require_once __DIR__ . '/../includes/employee_helper.php';

requireLogin();
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid method']);
    exit;
}

$employeeId = (int) ($_POST['employee_id'] ?? 0);
$month = (int) ($_POST['month'] ?? 0);
$year = (int) ($_POST['year'] ?? 0);
$deptId = (int) ($_POST['department_id'] ?? 0);
$type = (string) ($_POST['type'] ?? 'salary');
$memo = (float) ($_POST['memo'] ?? 0);
$memoRemark = trim((string) ($_POST['memo_remark'] ?? ''));
if (function_exists('mb_substr')) {
    $memoRemark = mb_substr($memoRemark, 0, 255);
} else {
    $memoRemark = substr($memoRemark, 0, 255);
}

if ($employeeId <= 0 || $month < 1 || $month > 12 || $year < 2000) {
    echo json_encode(['success' => false, 'message' => 'Invalid employee / month.']);
    exit;
}

requirePayrollRegisterAccess($deptId);

$emp = getEmployeeById($employeeId);
$isSalesField = $emp && (
    isSalesOnFieldDepartment($emp['department_name'] ?? '')
    || isSalesOnFieldDepartmentId((int) ($emp['department_id'] ?? 0))
);
if (!$isSalesField) {
    echo json_encode(['success' => false, 'message' => 'Memo is only for Sales On Field employees.']);
    exit;
}

if (function_exists('ensurePayrollApprovalTables')) {
    ensurePayrollApprovalTables();
}

if ($memo < 0) {
    $memo = 0;
}
$memo = round($memo, 2);

$conn = getDBConnection();
ensurePayrollTables($conn);
ensurePayrollReportTables($conn);

$memoRemarkDb = $memoRemark !== '' ? $memoRemark : null;
$stmt = $conn->prepare(
    "INSERT INTO salary_diary (employee_id, month_no, year_no, memo_amount, memo_remark)
     VALUES (?, ?, ?, ?, ?)
     ON DUPLICATE KEY UPDATE
        memo_amount = VALUES(memo_amount),
        memo_remark = VALUES(memo_remark)"
);
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'DB prepare failed.']);
    exit;
}
$stmt->bind_param('iiids', $employeeId, $month, $year, $memo, $memoRemarkDb);
$ok = $stmt->execute();
$err = $stmt->error;
$stmt->close();

if (!$ok) {
    $conn->close();
    echo json_encode(['success' => false, 'message' => $err ?: 'Save failed.']);
    exit;
}

$memoRemarkOut = $memoRemark !== '' ? $memoRemark : '-';

$netOut = null;
$locks = [];
$st = $conn->prepare(
    'SELECT id, department_id, snapshot_json, neft_json, total_net, total_gross
     FROM salary_register_locks
     WHERE month_no = ? AND year_no = ? AND register_type = ?
       AND (department_id = ? OR department_id = 0)'
);
if ($st) {
    $st->bind_param('iisi', $month, $year, $type, $deptId);
    $st->execute();
    $res = $st->get_result();
    while ($row = $res->fetch_assoc()) {
        $locks[] = $row;
    }
    $st->close();
}

foreach ($locks as $lockRow) {
    $snap = json_decode((string) ($lockRow['snapshot_json'] ?? ''), true);
    if (!is_array($snap)) {
        continue;
    }
    $changed = false;
    $totalNet = 0.0;
    $totalGross = 0.0;

    $patchRow = static function (&$r) use ($employeeId, $memo, $memoRemarkOut, &$netOut, &$changed, &$totalGross, &$totalNet) {
        if ((int) ($r['employee_id'] ?? 0) !== $employeeId) {
            $totalGross += (float) ($r['gross'] ?? 0);
            $totalNet += (float) ($r['net'] ?? 0);
            return;
        }
        $gross = (float) ($r['gross'] ?? 0);
        $ded = (float) ($r['total_deduction'] ?? 0);
        $arrears = (float) ($r['arrears'] ?? 0);
        $net = round($gross - $ded - $memo + $arrears, 2);
        if ($net < 0) {
            $net = 0;
        }
        $r['memo'] = $memo;
        $r['memo_remark'] = $memoRemarkOut;
        $r['net'] = $net;
        $netOut = $net;
        $changed = true;
        $totalGross += $gross;
        $totalNet += $net;
    };

    if (!empty($snap['groups']) && is_array($snap['groups'])) {
        foreach ($snap['groups'] as &$g) {
            foreach ($g['rows'] as &$r) {
                $patchRow($r);
            }
            unset($r);
        }
        unset($g);
    }

    if (!empty($snap['rows']) && is_array($snap['rows'])) {
        $totalNet = 0.0;
        $totalGross = 0.0;
        foreach ($snap['rows'] as &$r) {
            if ((int) ($r['employee_id'] ?? 0) !== $employeeId) {
                $totalGross += (float) ($r['gross'] ?? 0);
                $totalNet += (float) ($r['net'] ?? 0);
                continue;
            }
            $gross = (float) ($r['gross'] ?? 0);
            $ded = (float) ($r['total_deduction'] ?? 0);
            $arrears = (float) ($r['arrears'] ?? 0);
            $net = round($gross - $ded - $memo + $arrears, 2);
            if ($net < 0) {
                $net = 0;
            }
            $r['memo'] = $memo;
            $r['memo_remark'] = $memoRemarkOut;
            $r['net'] = $net;
            $netOut = $net;
            $changed = true;
            $totalGross += $gross;
            $totalNet += $net;
        }
        unset($r);
    }

    if (!$changed) {
        continue;
    }

    $neft = json_decode((string) ($lockRow['neft_json'] ?? '[]'), true);
    if (!is_array($neft)) {
        $neft = [];
    }
    foreach ($neft as &$nr) {
        if ((int) ($nr['employee_id'] ?? 0) !== $employeeId) {
            continue;
        }
        $nr['amount'] = $netOut !== null ? $netOut : (float) ($nr['amount'] ?? 0);
    }
    unset($nr);

    $snapJson = json_encode($snap, JSON_UNESCAPED_UNICODE);
    $neftJson = json_encode($neft, JSON_UNESCAPED_UNICODE);
    $lockId = (int) $lockRow['id'];
    $up = $conn->prepare(
        'UPDATE salary_register_locks
         SET snapshot_json = ?, neft_json = ?, total_net = ?, total_gross = ?
         WHERE id = ?'
    );
    if ($up) {
        $up->bind_param('ssddi', $snapJson, $neftJson, $totalNet, $totalGross, $lockId);
        $up->execute();
        $up->close();
    }
}

$conn->close();

echo json_encode([
    'success' => true,
    'message' => 'Memo saved.',
    'memo' => $memo,
    'memo_remark' => $memoRemarkOut,
    'net' => $netOut,
]);
