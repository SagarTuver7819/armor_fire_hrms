<?php
function parseSalTotals($path, $label)
{
    echo "===== $label =====\n";
    if (!is_file($path)) {
        echo "missing\n";
        return;
    }
    $h = file_get_contents($path);
    if (preg_match('/data-emp-search="CO72216[^"]*"([\s\S]*?)<\/tr>/', $h, $m)) {
        echo 'PURAN: ' . substr(preg_replace('/\s+/', ' ', strip_tags($m[0])), 0, 450) . "\n";
    } else {
        echo "PURAN: (not on this sheet)\n";
    }
    if (preg_match('/sr-normal-gross-total[\s\S]*?<\/tr>/', $h, $m)) {
        echo 'FUND ROW: ' . preg_replace('/\s+/', ' ', strip_tags($m[0])) . "\n";
    }
    if (preg_match('/<tfoot>([\s\S]*?)<\/tfoot>/', $h, $m)) {
        echo 'TFOOT: ' . preg_replace('/\s+/', ' ', strip_tags($m[1])) . "\n";
    }
    // Locked?
    if (preg_match('/Finalized[^<]{0,40}/', $h, $m)) {
        echo 'LOCK: ' . trim(strip_tags($m[0])) . "\n";
    }
    if (strpos($h, 'Added from live') !== false) {
        echo "HAS: Added from live section\n";
    }

    $gross = $pt = $net = $arrears = 0.0;
    $n = 0;
    preg_match_all('/<tr data-emp-search="([^"]+)"([\s\S]*?)<\/tr>/', $h, $all, PREG_SET_ORDER);
    foreach ($all as $row) {
        $name = $row[1];
        if (stripos($name, 'PURAN') !== false || stripos($name, 'normal salary') !== false || stripos($name, 'fund total') !== false) {
            continue;
        }
        // salary sheet: all rows; main sheet: only Under Puran
        if (strpos($path, 'main') !== false && strpos($row[0], 'Under: CO72216') === false) {
            continue;
        }
        if (!preg_match_all('/<td class="num"[^>]*>([\s\S]*?)<\/td>/', $row[0], $nums)) {
            continue;
        }
        $vals = [];
        foreach ($nums[1] as $x) {
            $txt = trim(html_entity_decode(strip_tags($x)));
            $txt = str_replace([',', '−', '–'], ['', '-', '-'], $txt);
            if ($txt === '-' || $txt === '') {
                $vals[] = 0.0;
            } else {
                $vals[] = (float) $txt;
            }
        }
        // Detect layout: salary has many nums; actual has fewer
        $isActual = (bool) preg_match('/>\s*Qty\s*</', $h);
        if ($isActual) {
            // qty, actual, gross, pf, pt, loan, adv, totded, arrears?, net
            $g = $vals[2] ?? 0;
            $p = $vals[4] ?? 0;
        } else {
            // salary,p,wo,hol,pl,sl,dl,coff,tdays,gross,pf,pt,loan,adv,totded, arrears(btn), net
            // Better: read sr-net-value and find gross near pf
            $g = $vals[9] ?? 0;
            $p = $vals[11] ?? 0;
        }
        $nv = 0.0;
        if (preg_match('/sr-net-value[^>]*>([^<]+)/', $row[0], $nm)) {
            $nv = (float) str_replace(',', '', $nm[1]);
        }
        $ar = 0.0;
        if (preg_match('/data-arrears="([^"]+)"/', $row[0], $am)) {
            $ar = (float) $am[1];
        } elseif (preg_match('/sr-arrears-value[^>]*>([^<]+)/', $row[0], $am)) {
            $ar = (float) str_replace(',', '', $am[1]);
        }
        $gross += $g;
        $pt += $p;
        $net += $nv;
        $arrears += $ar;
        $n++;
    }
    echo sprintf(
        "UNDER/SAL rows=%d gross=%.2f pt=%.2f net=%.2f arrears=%.2f | net+pt=%.2f | gross-arrears?=%.2f\n",
        $n,
        $gross,
        $pt,
        $net,
        $arrears,
        $net + $pt,
        $gross
    );
}

$tmp = sys_get_temp_dir();
parseSalTotals($tmp . DIRECTORY_SEPARATOR . 'm1_sal2.html', 'SALARY');
parseSalTotals($tmp . DIRECTORY_SEPARATOR . 'm1_jw2.html', 'JOBWORK');
parseSalTotals($tmp . DIRECTORY_SEPARATOR . 'm1_main2.html', 'MAIN');
