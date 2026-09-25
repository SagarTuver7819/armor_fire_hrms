<?php
require __DIR__ . '/config/database.php';
$c = getDBConnection();
$r = $c->query('SELECT id, code, leave_type, days_allowed FROM leave_types WHERE status = 1 ORDER BY id');
while ($row = $r->fetch_assoc()) {
    echo $row['id'] . ' | ' . $row['code'] . ' | ' . $row['leave_type'] . ' | ' . $row['days_allowed'] . "\n";
}
