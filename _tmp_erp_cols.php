<?php
require __DIR__ . '/config/database.php';
$e = getErpDBConnection();
foreach (['buff_kra_argon', 'buff_kra_rework'] as $t) {
    echo "==== $t ====\n";
    $r = $e->query("SHOW COLUMNS FROM `$t`");
    while ($c = $r->fetch_assoc()) {
        echo $c['Field'] . ' ' . $c['Type'] . "\n";
    }
    echo "-- sample --\n";
    $r = $e->query("SELECT * FROM `$t` ORDER BY id DESC LIMIT 8");
    if ($r) {
        while ($row = $r->fetch_assoc()) {
            echo json_encode($row, JSON_UNESCAPED_UNICODE) . "\n";
        }
    }
}
echo "==== departments buff ====\n";
$r = $e->query("SELECT id, department_name FROM departments WHERE department_name LIKE '%buff%' OR department_name LIKE '%BUFF%'");
while ($row = $r->fetch_assoc()) {
    echo json_encode($row) . "\n";
}
echo "==== document_master buff ====\n";
$r = $e->query("SELECT id, department_id, document_name, document_number FROM document_master WHERE department_id IN (SELECT id FROM departments WHERE department_name LIKE '%buff%') OR document_name LIKE '%buff%' OR document_name LIKE '%planning kra%' LIMIT 30");
while ($row = $r->fetch_assoc()) {
    echo json_encode($row, JSON_UNESCAPED_UNICODE) . "\n";
}
