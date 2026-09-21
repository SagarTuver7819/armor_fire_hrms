<?php
// One-shot cleanup of agent debug instrumentation
$files = [
    __DIR__ . '/includes/payroll_reports_helper.php',
    __DIR__ . '/includes/salary_register_helper.php',
];
foreach ($files as $f) {
    $c = file_get_contents($f);
    $before = strlen($c);
    $c = preg_replace('/\r?\n\/\/ #region agent log.*?\r?\n\/\/ #endregion\r?\n/s', "\n", $c);
    $c = preg_replace("/\r?\n[ \t]*\/\/ #region agent log.*?\r?\n[ \t]*\/\/ #endregion\r?\n/s", "\n", $c);
    file_put_contents($f, $c);
    echo basename($f) . ' ' . $before . ' -> ' . strlen($c) . PHP_EOL;
}
$suite = __DIR__ . '/_agent_test_suite.php';
if (is_file($suite)) {
    unlink($suite);
    echo "removed _agent_test_suite.php\n";
}
$log = __DIR__ . '/debug-06893f.log';
if (is_file($log)) {
    unlink($log);
    echo "removed debug-06893f.log\n";
}
echo "done\n";
