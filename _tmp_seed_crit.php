<?php
require __DIR__ . '/config/app.php';
require __DIR__ . '/config/database.php';
require __DIR__ . '/includes/recruitment_helper.php';
$c = getDBConnection();
ensureRecruitmentTables($c);
$r = $c->query("SELECT COUNT(*) AS c FROM recruitment_criteria WHERE position_name = ''");
echo 'global criteria: ' . $r->fetch_assoc()['c'] . PHP_EOL;
$r = $c->query("SELECT answer_type, criteria_label FROM recruitment_criteria WHERE position_name = '' ORDER BY sort_order");
while ($row = $r->fetch_assoc()) {
    echo '[' . $row['answer_type'] . '] ' . substr($row['criteria_label'], 0, 70) . PHP_EOL;
}
