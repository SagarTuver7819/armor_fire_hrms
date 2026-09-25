<?php
/**
 * CLI: provision Office Staff logins for all active employees
 */
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/department_head_helper.php';

$result = bulkProvisionOfficeStaffLogins(true);
echo "created={$result['created']} updated={$result['updated']} skipped={$result['skipped']}\n";
echo "password={$result['password']}\n";
if (!empty($result['errors'])) {
    echo "errors:\n";
    foreach (array_slice($result['errors'], 0, 20) as $e) {
        echo " - $e\n";
    }
}
echo "samples:\n";
foreach ($result['samples'] as $s) {
    echo "  {$s['username']} / {$s['password']}  ({$s['name']} · {$s['department']})\n";
}
