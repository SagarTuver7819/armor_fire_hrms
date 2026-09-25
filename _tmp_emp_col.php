<?php
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/permission_helper.php';
$conn = getDBConnection();
ensureRoleTables($conn);
$r = $conn->query("SHOW COLUMNS FROM users LIKE 'employee_id'");
echo 'employee_id: ' . ($r && $r->num_rows ? 'OK' : 'MISSING') . PHP_EOL;
$conn->close();
