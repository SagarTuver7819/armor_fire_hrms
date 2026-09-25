<?php
require __DIR__ . '/config/app.php';
require __DIR__ . '/config/database.php';
$c = getDBConnection();
$r = $c->query("SELECT username, password, role, status FROM users WHERE role IN ('admin','hr') ORDER BY id");
while ($x = $r->fetch_assoc()) {
    echo $x['username'] . ' | ' . $x['password'] . ' | ' . $x['role'] . ' | status=' . $x['status'] . PHP_EOL;
}
$c->close();
