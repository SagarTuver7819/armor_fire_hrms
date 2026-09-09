<?php
$c = file_get_contents('C:/Users/Admin/.cursor/projects/c-xampp-htdocs-armor-new-hrms/agent-tools/f5111280-28a2-4af3-9214-bf8da13167dd.txt');
foreach (['setValue', 'show', 'hide', 'destroy', 'theme', 'green', 'blue', 'orange', 'timeChanged', 'events', 'readOnly'] as $k) {
    echo $k . ':' . substr_count($c, $k) . PHP_EOL;
}
