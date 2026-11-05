<?php
require __DIR__ . '/config/database.php';
require __DIR__ . '/includes/contractor_helper.php';
require __DIR__ . '/includes/contractor_split_helper.php';
$conn = getDBConnection();
$bundle = getOpsCloneDepartmentStaff('CNC', 0, $conn);
$map = getOpsCloneStaffAttendanceMap($bundle['rows'], 11, 2026, $conn);
echo "staff " . count($bundle['rows']) . "\n";
foreach ($bundle['rows'] as $st) {
    $eid = (int) $st['id'];
    $line = $st['employee_code'] . ' ' . $st['employee_name'];
    for ($d = 1; $d <= 5; $d++) {
        $date = sprintf('2026-11-%02d', $d);
        $line .= ' d' . $d . '=' . opsCloneAttCodeFromDay($map[$eid][$date] ?? null);
    }
    echo $line . "\n";
}
$marks = fetchErpCncAttendanceMarks('2026-11-01', '2026-11-30');
echo "mark keys " . count($marks) . "\n";
foreach (['CO72142', 'CO72144', 'CO72313'] as $code) {
    $days = $marks['c:' . $code] ?? [];
    echo $code . ' ' . json_encode($days) . "\n";
}
$q = $conn->query("SELECT id, employee_code, employee_name FROM employees WHERE employee_code IN ('CO72142','CO72144','CO72147','CO72152','CO72156','CO72279','CO72312','CO72313')");
$real = [];
while ($q && $row = $q->fetch_assoc()) {
    $real[] = $row;
    echo "emp " . $row['id'] . ' ' . $row['employee_code'] . ' ' . $row['employee_name'] . "\n";
}
if ($real) {
    $map2 = getOpsCloneStaffAttendanceMap($real, 11, 2026, $conn);
    foreach ($real as $st) {
        $eid = (int) $st['id'];
        $line = $st['employee_code'];
        for ($d = 1; $d <= 5; $d++) {
            $date = sprintf('2026-11-%02d', $d);
            $line .= ' d' . $d . '=' . opsCloneAttCodeFromDay($map2[$eid][$date] ?? null);
        }
        echo $line . "\n";
    }
}
$conn->close();
