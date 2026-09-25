<?php
require __DIR__ . '/config/app.php';
require __DIR__ . '/config/database.php';
require __DIR__ . '/includes/permission_helper.php';

ensureRoleTables();
$rows = fetchEmployeePortalAssignments(0);
echo "LIST COUNT: " . count($rows) . "\n";
foreach ($rows as $r) {
    echo sprintf(
        "id=%s | %s | %s | role=%s | status=%s\n",
        $r['id'],
        $r['employee_code'] ?? '-',
        $r['username'],
        $r['role_name'] ?? '-',
        $r['status']
    );
}
