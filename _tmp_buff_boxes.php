<?php
require __DIR__ . '/config/database.php';
$erp = getErpDBConnection();
$dept = 9;
$doc = 44;
$date = '2026-11-05';
$key = 'armor hydrant body';
$erp->query("DELETE FROM buff_kra_rework WHERE department_id=$dept AND document_id=$doc AND entry_date='$date' AND product_key='" . $erp->real_escape_string($key) . "'");
$erp->query("DELETE FROM buff_kra_argon WHERE department_id=$dept AND document_id=$doc AND entry_date='$date' AND product_key='" . $erp->real_escape_string($key) . "'");
echo "cleaned\n";
