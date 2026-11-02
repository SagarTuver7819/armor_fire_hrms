<?php
$path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'm1_main.html';
echo 'exists=' . (is_file($path) ? 'yes size=' . filesize($path) : 'no') . "\n";
$h = is_file($path) ? file_get_contents($path) : '';
if ($h === '') {
    exit;
}
if (preg_match('/<thead>([\s\S]*?)<\/thead>/', $h, $m)) {
    if (preg_match_all('/<tr>([\s\S]*?)<\/tr>/', $m[1], $trs)) {
        $last = $trs[1][count($trs[1]) - 1];
        preg_match_all('/<th[^>]*>([\s\S]*?)<\/th>/', $last, $c);
        $labels = array_map(static fn($x) => trim(html_entity_decode(strip_tags($x))), $c[1]);
        echo 'HDR2 (' . count($labels) . '): ' . implode(' | ', $labels) . "\n";
    }
}
if (preg_match('/data-emp-search="CO72216[^"]*"([\s\S]*?)<\/tr>/', $h, $m)) {
    preg_match_all('/<td\b/i', $m[0], $t);
    echo 'PURAN td=' . count($t[0]) . ' ' . substr(preg_replace('/\s+/', ' ', strip_tags($m[0])), 0, 500) . "\n";
}
if (preg_match('/sr-normal-gross-total[\s\S]*?<\/tr>/', $h, $m)) {
    preg_match_all('/<td\b/i', $m[0], $t);
    echo 'NGT td=' . count($t[0]) . ' ' . preg_replace('/\s+/', ' ', strip_tags($m[0])) . "\n";
}
if (preg_match('/<tfoot>([\s\S]*?)<\/tfoot>/', $h, $m)) {
    preg_match_all('/<td\b/i', $m[1], $t);
    echo 'TFOOT td=' . count($t[0]) . ' ' . preg_replace('/\s+/', ' ', strip_tags($m[1])) . "\n";
}
// First salary under row
if (preg_match('/Under: CO72216[\s\S]*?<\/tr>/', $h, $m)) {
    preg_match_all('/<td\b/i', $m[0], $t);
    echo 'UNDER td=' . count($t[0]) . ' ' . substr(preg_replace('/\s+/', ' ', strip_tags($m[0])), 0, 300) . "\n";
}
// Sum net/pt/gross from under only (exclude puran and total row)
$netSum = $ptSum = $grossSum = 0.0;
preg_match_all('/<tr data-emp-search="([^"]+)"([\s\S]*?)<\/tr>/', $h, $all, PREG_SET_ORDER);
foreach ($all as $row) {
    if (stripos($row[1], 'PURAN') !== false || stripos($row[1], 'normal salary') !== false) {
        continue;
    }
    if (strpos($row[0], 'Under: CO72216') === false) {
        continue;
    }
    preg_match_all('/<td class="num"[^>]*>(.*?)<\/td>/s', $row[0], $nums);
    $vals = [];
    foreach ($nums[1] as $x) {
        $vals[] = (float) str_replace(',', '', strip_tags($x));
    }
    // salary layout: 0 salary ... 9 gross, 10 pf, 11 pt ... net in sr-net-value
    $gross = $vals[9] ?? 0;
    $pt = $vals[11] ?? 0;
    $net = 0.0;
    if (preg_match('/sr-net-value[^>]*>([^<]+)/', $row[0], $nm)) {
        $net = (float) str_replace(',', '', $nm[1]);
    }
    $grossSum += $gross;
    $ptSum += $pt;
    $netSum += $net;
}
echo sprintf("MAIN under SUM gross=%.2f pt=%.2f net=%.2f net+pt=%.2f\n", $grossSum, $ptSum, $netSum, $netSum + $ptSum);

// Also JW
$jw = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'm1_jw.html';
if (is_file($jw)) {
    $h2 = file_get_contents($jw);
    if (preg_match('/data-emp-search="CO72216[^"]*"([\s\S]*?)<\/tr>/', $h2, $m)) {
        echo 'JW PURAN: ' . substr(preg_replace('/\s+/', ' ', strip_tags($m[0])), 0, 400) . "\n";
    }
}
