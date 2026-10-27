<?php
require_once __DIR__ . '/config/database.php';
$conn = getDBConnection();
echo "DB=" . DB_NAME . PHP_EOL;
$cols = [];
$r = $conn->query('SHOW COLUMNS FROM employees');
while ($x = $r->fetch_assoc()) {
    $cols[] = $x['Field'];
}
$r->free();
$need = [
    'erp_join_form_id', 'source', 'production_department', 'employee_name', 'blood_group',
    'decided_salary', 'reporting_head', 'week_off_day', 'overtime_benefits', 'biometric_user_id',
];
foreach ($need as $c) {
    echo (in_array($c, $cols, true) ? 'OK ' : 'MISSING ') . $c . PHP_EOL;
}
echo "---DEPTS---\n";
$d = $conn->query('SELECT id, department_name FROM departments WHERE status=1 ORDER BY department_name');
while ($x = $d->fetch_assoc()) {
    echo $x['id'] . '|' . $x['department_name'] . PHP_EOL;
}
$d->free();
$er = $conn->query("SELECT COUNT(*) AS c FROM employees WHERE source='erp' OR (erp_join_form_id IS NOT NULL AND erp_join_form_id>0)");
$row = $er->fetch_assoc();
echo 'ERP_COUNT=' . (int)$row['c'] . PHP_EOL;
$conn->close();
