<?php
/**
 * Clone Operation Rate List — save sheet + CNC Normal Salary (excl. Main).
 * Sheet data uses same tables as live Ops Rate List; staff salaries update employees.decided_salary.
 * Live operations/edit.php UI stays without staff grid.
 */
require_once __DIR__ . '/../_bootstrap.php';
require_once __DIR__ . '/../../includes/contractor_split_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . app_url('contractor/operations_clone/index.php'));
    exit;
}

$id = (int) ($_POST['id'] ?? 0);
$ops = contractorOperations();
$operation = (string) ($_POST['operation'] ?? '');
$employeeId = (int) ($_POST['employee_id'] ?? 0);
$month = (int) ($_POST['month_no'] ?? 0);
$year = (int) ($_POST['year_no'] ?? 0);

$itemsIn = [];
$jsonRaw = trim((string) ($_POST['items_json'] ?? ''));
if ($jsonRaw !== '') {
    $decoded = json_decode($jsonRaw, true);
    if (is_array($decoded)) {
        $itemsIn = $decoded;
    }
}
if (!$itemsIn && isset($_POST['items']) && is_array($_POST['items'])) {
    $itemsIn = $_POST['items'];
}

if (!isset($ops[$operation]) || $employeeId <= 0 || $month < 1 || $month > 12 || $year < 2000) {
    die('Operation, employee, month and year are required. <a href="javascript:history.back()">Go Back</a>');
}

$conn = getDBConnection();
ensureContractorTables($conn);

if ($employeeId <= 0 && $id > 0) {
    $keepEmp = $conn->prepare('SELECT employee_id FROM contractor_operation_sheets WHERE id = ? AND status = 1 LIMIT 1');
    $keepEmp->bind_param('i', $id);
    $keepEmp->execute();
    $keepRow = $keepEmp->get_result()->fetch_assoc();
    $keepEmp->close();
    if ($keepRow) {
        $employeeId = (int) ($keepRow['employee_id'] ?? 0);
    }
}

$jwOk = false;
$chkEmp = $conn->prepare('SELECT id FROM employees e WHERE e.id = ? AND ' . contractorJobworkWhere() . ' LIMIT 1');
$chkEmp->bind_param('i', $employeeId);
$chkEmp->execute();
$jwOk = (bool) $chkEmp->get_result()->fetch_assoc();
$chkEmp->close();
if (!$jwOk && $id > 0) {
    $own = $conn->prepare('SELECT id FROM contractor_operation_sheets WHERE id = ? AND employee_id = ? AND status = 1 LIMIT 1');
    $own->bind_param('ii', $id, $employeeId);
    $own->execute();
    $jwOk = (bool) $own->get_result()->fetch_assoc();
    $own->close();
}
if (!$jwOk) {
    $conn->close();
    die('Select a Jobwork / Operations employee from the list. <a href="javascript:history.back()">Go Back</a>');
}

$dupSql = 'SELECT id FROM contractor_operation_sheets WHERE employee_id=? AND operation=? AND month_no=? AND year_no=? AND status=1 AND id<>? LIMIT 1';
$dup = $conn->prepare($dupSql);
$dup->bind_param('isiii', $employeeId, $operation, $month, $year, $id);
$dup->execute();
$exists = $dup->get_result()->fetch_assoc();
$dup->close();
if ($exists) {
    $conn->close();
    die('This employee already has a rate list for that operation / month / year. <a href="javascript:history.back()">Go Back</a>');
}

$productsById = [];
$pr = $conn->query("SELECT id, rate, ot_text, rejection_rate FROM contractor_products WHERE status = 1");
if ($pr) {
    while ($p = $pr->fetch_assoc()) {
        $productsById[(int) $p['id']] = $p;
    }
}

$built = [];
$grandQty = 0;
$grandR = 0;
$grandAmt = 0;
$sort = 0;
foreach ((array) $itemsIn as $it) {
    $pid = (int) ($it['product_id'] ?? 0);
    if ($pid <= 0 || !isset($productsById[$pid])) {
        continue;
    }
    $gradeId = (int) ($it['grade_id'] ?? 0);
    $prod = $productsById[$pid];
    $rate = isset($it['rate']) && $it['rate'] !== '' ? (float) $it['rate'] : (float) $prod['rate'];
    $otRate = isset($it['ot_rate']) && $it['ot_rate'] !== '' ? (float) $it['ot_rate'] : parseOtRate($prod['ot_text'] ?? '');
    $rej = isset($it['rejection_rate']) && $it['rejection_rate'] !== '' ? (float) $it['rejection_rate'] : (float) $prod['rejection_rate'];
    $daysRaw = [];
    foreach ((array) ($it['days'] ?? []) as $d => $cell) {
        $d = (int) $d;
        if ($d < 1 || $d > 31) {
            continue;
        }
        $daysRaw[(string) $d] = [
            'q' => (float) ($cell['q'] ?? 0),
            'r' => (float) ($cell['r'] ?? 0),
            'ot' => (float) ($cell['ot'] ?? 0),
        ];
    }
    $calc = calculateContractorRow($operation, $rate, $otRate, $rej, $daysRaw);
    $grandQty += $calc['total_qty'];
    $grandR += $calc['total_r'];
    $grandAmt += $calc['total_amount'];
    $built[] = [
        'product_id' => $pid,
        'grade_id' => $gradeId,
        'rate' => $rate,
        'ot_rate' => $otRate,
        'rejection_rate' => $rej,
        'days_json' => json_encode($daysRaw),
        'total_qty' => $calc['total_qty'],
        'total_r' => $calc['total_r'],
        'total_amount' => $calc['total_amount'],
        'sort_order' => $sort++,
    ];
}

if (!$built) {
    $conn->close();
    die('Add at least one product with a selection. <a href="javascript:history.back()">Go Back</a>');
}

if ($id > 0) {
    $stmt = $conn->prepare('UPDATE contractor_operation_sheets SET employee_id=?, operation=?, month_no=?, year_no=?, total_qty=?, total_r=?, total_amount=? WHERE id=? AND status=1');
    $stmt->bind_param('isiidddi', $employeeId, $operation, $month, $year, $grandQty, $grandR, $grandAmt, $id);
    $ok = $stmt->execute();
    $error = $stmt->error;
    $stmt->close();
    if ($ok) {
        $conn->query('DELETE FROM contractor_operation_items WHERE sheet_id = ' . (int) $id);
    }
} else {
    $stmt = $conn->prepare('INSERT INTO contractor_operation_sheets (employee_id, operation, month_no, year_no, total_qty, total_r, total_amount, status) VALUES (?,?,?,?,?,?,?,1)');
    $stmt->bind_param('isiiddd', $employeeId, $operation, $month, $year, $grandQty, $grandR, $grandAmt);
    $ok = $stmt->execute();
    $error = $stmt->error;
    $id = (int) $conn->insert_id;
    $stmt->close();
}

if (!$ok || $id <= 0) {
    $conn->close();
    die('Save failed: ' . htmlspecialchars($error ?: 'unknown') . ' <a href="javascript:history.back()">Go Back</a>');
}

$ins = $conn->prepare('INSERT INTO contractor_operation_items (sheet_id, product_id, grade_id, rate, ot_rate, rejection_rate, days_json, total_qty, total_r, total_amount, sort_order) VALUES (?,?,?,?,?,?,?,?,?,?,?)');
foreach ($built as $b) {
    $ins->bind_param(
        'iiidddsdddi',
        $id,
        $b['product_id'],
        $b['grade_id'],
        $b['rate'],
        $b['ot_rate'],
        $b['rejection_rate'],
        $b['days_json'],
        $b['total_qty'],
        $b['total_r'],
        $b['total_amount'],
        $b['sort_order']
    );
    $ins->execute();
}
$ins->close();

// CNC (clone only): update Normal Salary decided_salary for dept staff excl. Main Contractor
if (in_array($operation, opsCloneStaffSalaryOperations(), true) && isset($_POST['staff_salary']) && is_array($_POST['staff_salary'])) {
    $staffBundle = getOpsCloneDepartmentStaff($operation, $employeeId, $conn);
    $allowed = [];
    foreach ($staffBundle['rows'] as $r) {
        $allowed[(int) $r['id']] = true;
    }
    $deptId = resolveDepartmentIdByName($staffBundle['department_name'] ?: opsCloneStaffDepartmentName($operation));
    $up = $conn->prepare(
        "UPDATE employees
         SET decided_salary = ?
         WHERE id = ? AND department_id = ? AND pay_type = 'Salary' AND status = 1"
    );
    foreach ($_POST['staff_salary'] as $empKey => $amtRaw) {
        $eid = (int) $empKey;
        if ($eid <= 0 || empty($allowed[$eid]) || $deptId <= 0) {
            continue;
        }
        $amt = round(max(0, (float) $amtRaw), 2);
        $up->bind_param('dii', $amt, $eid, $deptId);
        $up->execute();
    }
    $up->close();
}

$conn->close();

header('Location: ' . app_url('contractor/operations_clone/index.php?msg=' . ((int) ($_POST['id'] ?? 0) > 0 ? 'updated' : 'added')));
exit;
