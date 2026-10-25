<?php
$c = @new mysqli('localhost', 'root', '', 'armor_erp');
if ($c->connect_error) {
    echo 'connect fail ' . $c->connect_error . "\n";
    exit(1);
}
$c->set_charset('utf8mb4');
echo "=== erp departments ===\n";
$res = $c->query('SHOW COLUMNS FROM departments');
if ($res) {
    while ($r = $res->fetch_assoc()) echo $r['Field'], ' ', $r['Type'], "\n";
}
$res = $c->query('SELECT id, department_name FROM departments ORDER BY display_order, department_name');
if ($res) {
    while ($r = $res->fetch_assoc()) echo json_encode($r), "\n";
} else {
    echo $c->error, "\n";
}
echo "=== ppc groups ===\n";
$res = $c->query('SHOW TABLES LIKE "ppc_groups"');
if ($res && $res->num_rows) {
    $res = $c->query('SELECT id, section_name, is_active, display_order FROM ppc_groups ORDER BY display_order, id');
    while ($r = $res->fetch_assoc()) echo json_encode($r), "\n";
} else {
    echo "no ppc_groups\n";
}
$c->close();
