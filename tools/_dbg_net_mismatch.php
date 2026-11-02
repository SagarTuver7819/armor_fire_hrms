<?php
function sumNets($path, $label)
{
    echo "===== $label =====\n";
    $h = file_get_contents($path);
    $netSum = 0.0;
    $arrSum = 0.0;
    $grossSum = 0.0;
    $rows = 0;
    preg_match_all('/<tr[^>]*>([\s\S]*?)<\/tr>/', $h, $trs);
    foreach ($trs[1] as $tr) {
        if (stripos($tr, 'Department Total') !== false || stripos($tr, 'Grand Total') !== false) {
            $t = preg_replace('/\s+/', ' ', strip_tags($tr));
            echo "TOTAL ROW: $t\n";
            continue;
        }
        // employee row: has Net Salary cell or sr-net-value
        $net = null;
        if (preg_match('/sr-net-value[^>]*>([^<]+)/', $tr, $m)) {
            $net = (float) str_replace(',', '', $m[1]);
        } elseif (preg_match_all('/<td[^>]*>([\s\S]*?)<\/td>/', $tr, $cells) && count($cells[1]) >= 22) {
            // excel salary: ... totded(19), arrears(20), net(21) 0-index from start of money - count all td
            $vals = [];
            foreach ($cells[1] as $c) {
                $vals[] = trim(html_entity_decode(strip_tags($c)));
            }
            // skip header-like
            if ($vals[0] === 'Employee Code' || $vals[0] === '' && stripos($tr, '<th') !== false) {
                continue;
            }
            // Net Salary is column index 21 in excel (0-based among headers)
            if (count($vals) >= 22 && is_numeric(str_replace(',', '', $vals[21]))) {
                $net = (float) str_replace(',', '', $vals[21]);
                $arr = (float) str_replace(',', '', $vals[20]);
                $gross = (float) str_replace(',', '', $vals[14]);
                $grossSum += $gross;
                $arrSum += $arr;
            }
        }
        if ($net === null) {
            continue;
        }
        if (!preg_match('/data-emp-search|Employee Code|<td>\s*[A-Z]{2}\d+/', $tr) && strpos($path, '.xls') === false) {
            // html needs data-emp-search
        }
        if (strpos($path, '.html') !== false) {
            if (!preg_match('/data-emp-search="([^"]+)"/', $tr, $nm)) {
                // try previous - actually tr in this loop may not include opening tag attrs
                continue;
            }
        }
        $netSum += $net;
        $rows++;
    }

    // Better dedicated parsers
}

function parseHtml($path)
{
    echo "===== HTML SCREEN =====\n";
    $h = file_get_contents($path);
    if (preg_match('/Finalized[^<]{0,40}/', $h, $m)) {
        echo 'LOCK: ' . trim(strip_tags($m[0])) . "\n";
    }
    $netSum = $arrSum = $grossSum = 0.0;
    $n = 0;
    $byCode = [];
    preg_match_all('/<tr data-emp-search="([^"]+)"([\s\S]*?)<\/tr>/', $h, $all, PREG_SET_ORDER);
    foreach ($all as $row) {
        $name = $row[1];
        preg_match_all('/<td class="num"[^>]*>([\s\S]*?)<\/td>/', $row[0], $nums);
        $vals = [];
        foreach ($nums[1] as $x) {
            $t = trim(strip_tags($x));
            $vals[] = (float) str_replace(',', '', $t === '-' ? '0' : $t);
        }
        $gross = $vals[9] ?? 0;
        $net = 0.0;
        if (preg_match('/sr-net-value[^>]*>([^<]+)/', $row[0], $m)) {
            $net = (float) str_replace(',', '', $m[1]);
        }
        $arr = 0.0;
        if (preg_match('/data-arrears="([^"]+)"/', $row[0], $m)) {
            $arr = (float) $m[1];
        }
        $grossSum += $gross;
        $netSum += $net;
        $arrSum += $arr;
        $byCode[$name] = ['gross' => $gross, 'net' => $net, 'arr' => $arr];
        $n++;
    }
    echo sprintf("rows=%d gross=%.0f arr=%.0f net=%.0f\n", $n, $grossSum, $arrSum, $netSum);
    if (preg_match('/sr-net-total[^>]*>([^<]+)/', $h, $m)) {
        echo 'FOOT NET: ' . $m[1] . "\n";
    }
    return $byCode;
}

function parseXls($path)
{
    echo "===== EXCEL =====\n";
    $h = file_get_contents($path);
    $netSum = $arrSum = $grossSum = 0.0;
    $n = 0;
    $byCode = [];
    preg_match_all('/<tr>([\s\S]*?)<\/tr>/', $h, $trs);
    foreach ($trs[1] as $tr) {
        if (stripos($tr, '<th') !== false) {
            continue;
        }
        if (stripos($tr, 'Department Total') !== false || stripos($tr, 'Grand Total') !== false) {
            echo 'TOTAL: ' . preg_replace('/\s+/', ' ', strip_tags($tr)) . "\n";
            continue;
        }
        if (stripos($tr, 'Normal Salary') !== false) {
            continue;
        }
        preg_match_all('/<td[^>]*>([\s\S]*?)<\/td>/', $tr, $cells);
        $vals = array_map(static function ($c) {
            return trim(html_entity_decode(strip_tags($c)));
        }, $cells[1]);
        if (count($vals) < 22) {
            continue;
        }
        // Emp code, name, ... gross=14, arrears=20, net=21
        $code = $vals[0];
        $name = $vals[1];
        if ($code === '' && $name === '') {
            continue;
        }
        if (!preg_match('/^[A-Z]{2}\d+/i', $code) && !preg_match('/^CO\d+/i', $code) && !preg_match('/^AS\d+/i', $code) && !preg_match('/^EMP/i', $code)) {
            // still allow
        }
        $gross = (float) str_replace(',', '', $vals[14]);
        $arr = (float) str_replace(',', '', $vals[20]);
        $net = (float) str_replace(',', '', $vals[21]);
        if ($gross == 0 && $net == 0 && $arr == 0 && $code === '') {
            continue;
        }
        $key = trim($code . ' ' . $name);
        $grossSum += $gross;
        $arrSum += $arr;
        $netSum += $net;
        $byCode[$key] = ['gross' => $gross, 'net' => $net, 'arr' => $arr, 'code' => $code, 'name' => $name];
        $n++;
    }
    echo sprintf("rows=%d gross=%.0f arr=%.0f net=%.0f\n", $n, $grossSum, $arrSum, $netSum);
    return $byCode;
}

$tmp = sys_get_temp_dir();
$html = parseHtml($tmp . DIRECTORY_SEPARATOR . 'm1_sal_chk.html');
$xls = parseXls($tmp . DIRECTORY_SEPARATOR . 'm1_sal_chk.xls');

echo "===== DIFF (in HTML not matching XLS net) =====\n";
foreach ($html as $k => $hrow) {
    // find xls by similar key
    $match = null;
    foreach ($xls as $xk => $xrow) {
        if (strcasecmp(trim($k), trim($xk)) === 0 || stripos($xk, explode(' ', $k)[0]) === 0) {
            $code = explode(' ', $k)[0];
            if (stripos($xk, $code) !== false) {
                $match = $xrow;
                break;
            }
        }
    }
    if (!$match) {
        echo "ONLY HTML: $k net={$hrow['net']} arr={$hrow['arr']}\n";
        continue;
    }
    if (abs($hrow['net'] - $match['net']) > 0.5 || abs($hrow['arr'] - $match['arr']) > 0.5) {
        echo sprintf(
            "DIFF %s | html net=%.0f arr=%.0f gross=%.0f | xls net=%.0f arr=%.0f gross=%.0f | dNet=%.0f\n",
            $k,
            $hrow['net'],
            $hrow['arr'],
            $hrow['gross'],
            $match['net'],
            $match['arr'],
            $match['gross'],
            $hrow['net'] - $match['net']
        );
    }
}
foreach ($xls as $xk => $xrow) {
    $code = preg_match('/^(\S+)/', $xk, $m) ? $m[1] : '';
    $found = false;
    foreach ($html as $k => $_) {
        if ($code !== '' && strpos($k, $code) === 0) {
            $found = true;
            break;
        }
    }
    if (!$found) {
        echo "ONLY XLS: $xk net={$xrow['net']} arr={$xrow['arr']}\n";
    }
}
