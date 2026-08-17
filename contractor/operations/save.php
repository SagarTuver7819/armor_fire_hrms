<?php
require_once __DIR__ . '/../_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . app_url('contractor/operations/index.php'));
    exit;
}

$id = (int) ($_POST['id'] ?? 0);
$ops = contractorOperations();
$operation = (string) ($_POST['operation'] ?? '');
$employeeId = (int) ($_POST['employee_id'] ?? 0);
$month = (int) ($_POST['month_no'] ?? 0);
$year = (int) ($_POST['year_no'] ?? 0);
$itemsIn = $_POST['items'] ?? [];

if (!isset($ops[$operation]) || $employeeId <= 0 || $month < 1 || $month > 12 || $year < 2000) {
    die('Operation, employee, month and year are required. <a href="javascript:history.back()">Go Back</a>');
}

$conn = getDBConnection();
ensureContractorTables($conn);

$jw = $conn->prepare("SELECT id FROM employees WHERE id = ? AND status = 1 AND pay_type = 'Jobwork' LIMIT 1");
$jw->bind_param('i', $employeeId);
$jw->execute();
$jwOk = $jw->get_result()->fetch_assoc();
$jw->close();
if (!$jwOk) {
    $conn->close();
    die('Select a Jobwork employee from Join Employee. <a href="javascript:history.back()">Go Back</a>');
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
$conn->close();

header('Location: ' . app_url('contractor/operations/index.php?msg=' . ((int) ($_POST['id'] ?? 0) > 0 ? 'updated' : 'added')));
exit;
