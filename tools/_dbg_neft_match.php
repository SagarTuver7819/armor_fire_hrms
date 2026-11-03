<?php
$tmp = sys_get_temp_dir();

function sumFromScreen($path)
{
    $h = file_get_contents($path);
    $out = ['normal' => 0.0, 'jobwork' => 0.0, 'rows' => 0, 'foot' => '', 'chips' => []];
    if (preg_match('/Final NEFT Total[^<]*<\/strong><\/td>\s*<td class="num"><strong>([^<]+)/', $h, $m)) {
        $out['foot'] = trim($m[1]);
    }
    if (preg_match_all('/ops-chip[^>]*>(.*?)<\/span>/s', $h, $ms)) {
        foreach ($ms[1] as $c) {
            $t = trim(html_entity_decode(strip_tags($c)));
            if ($t !== '') {
                $out['chips'][] = preg_replace('/\s+/', ' ', $t);
            }
        }
    }
    preg_match_all('/<tr[^>]*>([\s\S]*?)<\/tr>/', $h, $trs);
    foreach ($trs[1] as $tr) {
        if (stripos($tr, 'Final NEFT') !== false || stripos($tr, '<th') !== false) {
            continue;
        }
        if (!preg_match('/ops-chip[^>]*>(Normal|Jobwork)</', $tr, $k)) {
            continue;
        }
        if (!preg_match('/class="num"><strong>([^<]+)/', $tr, $a)) {
            continue;
        }
        $amt = (float) str_replace(',', '', $a[1]);
        if ($k[1] === 'Jobwork') {
            $out['jobwork'] += $amt;
        } else {
            $out['normal'] += $amt;
        }
        $out['rows']++;
    }
    return $out;
}

function sumFromPrint($path)
{
    $h = file_get_contents($path);
    $out = ['rows' => 0, 'sum' => 0.0, 'foot' => ''];
    if (preg_match('/Total NEFT Amount<\/td>\s*<td class="num">([^<]+)/', $h, $m)) {
        $out['foot'] = trim($m[1]);
    }
    preg_match_all('/<tr>\s*<td>(\d+)<\/td>[\s\S]*?<td class="num">([^<]+)<\/td>\s*<\/tr>/', $h, $ms, PREG_SET_ORDER);
    foreach ($ms as $m) {
        $out['rows']++;
        $out['sum'] += (float) str_replace(',', '', $m[2]);
    }
    return $out;
}

function sumFromXls($path)
{
    $h = file_get_contents($path);
    $out = ['rows' => 0, 'sum' => 0.0, 'foot' => '', 'normal' => 0, 'jobwork' => 0];
    if (preg_match('/>TOTAL<\/td>[\s\S]*?mso-number-format:0\.00;">([^<]+)/', $h, $m)) {
        $out['foot'] = trim($m[1]);
    }
    preg_match_all('/<tr>([\s\S]*?)<\/tr>/', $h, $trs);
    foreach ($trs[1] as $tr) {
        if (stripos($tr, '<th') !== false || stripos($tr, '>TOTAL<') !== false) {
            continue;
        }
        preg_match_all('/<td[^>]*>([\s\S]*?)<\/td>/', $tr, $cells);
        $vals = array_map(static function ($c) {
            return trim(html_entity_decode(strip_tags($c)));
        }, $cells[1]);
        if (count($vals) < 7) {
            continue;
        }
        if ($vals[0] !== 'PAB_VENDOR') {
            continue;
        }
        $amt = (float) $vals[6];
        $out['sum'] += $amt;
        $out['rows']++;
        $remark = $vals[11] ?? '';
        if (stripos($remark, 'JOBWORK') !== false) {
            $out['jobwork']++;
        } else {
            $out['normal']++;
        }
    }
    return $out;
}

echo "SCREEN:\n";
print_r(sumFromScreen($tmp . '/neft_all.html'));
echo "PRINT:\n";
print_r(sumFromPrint($tmp . '/neft_print.html'));
echo "XLS:\n";
print_r(sumFromXls($tmp . '/neft_xls.xls'));
