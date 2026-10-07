<?php
/**
 * Public (QR form) — active employees of a department + their booking for tomorrow
 * ?scope=canteen → only Canteen Use = Yes (employee self-booking)
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/canteen_helper.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$deptId = (int) ($_GET['department_id'] ?? 0);
if ($deptId <= 0) {
    echo json_encode(['ok' => false, 'error' => 'Select department']);
    exit;
}

$conn = getDBConnection();
ensureCanteenTables($conn);
$mealDate = canteenTargetDate();
$canteenOnly = ($_GET['scope'] ?? '') === 'canteen';
$employees = canteenDepartmentEmployees($conn, $deptId, $canteenOnly);
$orders = canteenOrdersMap($conn, $mealDate, $deptId);
$conn->close();

$out = [];
foreach ($employees as $e) {
    $eid = (int) $e['id'];
    $o = $orders[$eid] ?? null;
    $out[] = [
        'id'    => $eid,
        'code'  => (string) $e['employee_code'],
        'name'  => (string) $e['employee_name'],
        'order' => $o ? [
            'breakfast' => (int) $o['breakfast'],
            'lunch'     => (int) $o['lunch'],
            'dinner'    => (int) $o['dinner'],
        ] : null,
    ];
}

echo json_encode(['ok' => true, 'meal_date' => $mealDate, 'employees' => $out]);
