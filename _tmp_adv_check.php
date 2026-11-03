<?php
require __DIR__ . '/config/database.php';
$c = getDBConnection();
$codes = ['AS76177', 'AS76179', 'AS76183'];
foreach ($codes as $code) {
    $st = $c->prepare('SELECT id, employee_code, employee_name, department_id FROM employees WHERE employee_code = ?');
    $st->bind_param('s', $code);
    $st->execute();
    $e = $st->get_result()->fetch_assoc();
    $st->close();
    if (!$e) {
        echo "$code NOT FOUND\n";
        continue;
    }
    echo "=== {$e['employee_code']} id={$e['id']} {$e['employee_name']} dept={$e['department_id']} ===\n";
    $eid = (int) $e['id'];
    $r = $c->query("SELECT id, kind, txn_date, amount, installment_amount, start_month, start_year, status, remarks FROM employee_recoveries WHERE employee_id=$eid ORDER BY id");
    while ($row = $r->fetch_assoc()) {
        echo '  recovery: ' . json_encode($row) . "\n";
    }
    $d = $c->query("SELECT id, month, year, advance_amount, loan_amount, arrears_amount FROM salary_diary WHERE employee_id=$eid AND (year=2026 OR advance_amount<>0) ORDER BY year, month");
    while ($row = $d->fetch_assoc()) {
        echo '  diary: ' . json_encode($row) . "\n";
    }
}
echo "\n--- Advances amount 47795 or Oct 2026 txn ---\n";
$q = $c->query("SELECT r.id, e.employee_code, e.employee_name, r.kind, r.txn_date, r.amount, r.status, r.department_id
FROM employee_recoveries r JOIN employees e ON e.id=r.employee_id
WHERE r.kind='Advance' AND (r.amount=47795 OR (r.txn_date BETWEEN '2026-10-01' AND '2026-10-31'))
ORDER BY e.employee_code, r.id");
while ($row = $q->fetch_assoc()) {
    echo json_encode($row) . "\n";
}

require __DIR__ . '/includes/advance_loan_helper.php';
echo "\n--- Month due Oct 2026 ---\n";
foreach ($codes as $code) {
    $st = $c->prepare('SELECT id, employee_code FROM employees WHERE employee_code = ?');
    $st->bind_param('s', $code);
    $st->execute();
    $e = $st->get_result()->fetch_assoc();
    $st->close();
    if (!$e) continue;
    $due = advanceLoanMonthDue($c, (int) $e['id'], 10, 2026);
    echo "{$e['employee_code']}: " . json_encode($due) . "\n";
}
