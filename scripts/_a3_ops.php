<?php
$j = json_decode(file_get_contents(sys_get_temp_dir() . '/ops_oct.json'), true);
foreach (($j['data'] ?? []) as $r) {
    $blob = implode('|', array_slice($r, 0, 7));
    if (preg_match('/RRL|FLEX|ASS-|SAGAR|72135|72134|72128|PARAS|KEVAL|CHIRODIYA/i', $blob)) {
        echo $blob . PHP_EOL;
        if (preg_match('/edit\.php\?id=(\d+)/', (string) ($r[7] ?? ''), $m)) {
            echo "  sheet_id={$m[1]}\n";
        }
    }
}
