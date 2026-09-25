<?php
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/permission_helper.php';

$conn = getDBConnection();
ensureRoleTables($conn);

$checks = ['roles', 'role_permissions'];
foreach ($checks as $t) {
    $r = $conn->query("SHOW TABLES LIKE '{$t}'");
    echo $t . ': ' . ($r && $r->num_rows ? 'OK' : 'MISSING') . PHP_EOL;
}
$r = $conn->query("SHOW COLUMNS FROM users LIKE 'custom_role_id'");
echo 'users.custom_role_id: ' . ($r && $r->num_rows ? 'OK' : 'MISSING') . PHP_EOL;
$conn->close();
