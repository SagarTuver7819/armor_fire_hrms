<?php
require __DIR__ . '/config/database.php';
$c = getDBConnection();
$r = $c->query("SELECT id, total_qty FROM contractor_operation_sheets WHERE id=5");
$s = $r->fetch_assoc();
echo "sheet=" . json_encode($s) . "\n";
$i = $c->query("SELECT product_id, grade_id, total_qty FROM contractor_operation_items WHERE sheet_id=5 ORDER BY sort_order,id");
$n = 0;
while ($x = $i->fetch_assoc()) {
    echo "item " . (++$n) . " " . json_encode($x) . "\n";
}
echo "count=$n\n";
