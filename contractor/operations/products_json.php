<?php
require_once __DIR__ . '/../_bootstrap.php';
header('Content-Type: application/json; charset=utf-8');

$operation = (string) ($_GET['operation'] ?? '');
$kraOnly = !empty($_GET['kra']) && (string) $_GET['kra'] !== '0';
$asOf = trim((string) ($_GET['as_of'] ?? ''));
if ($asOf === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $asOf)) {
    $y = (int) ($_GET['year'] ?? 0);
    $m = (int) ($_GET['month'] ?? 0);
    if ($y >= 2000 && $m >= 1 && $m <= 12) {
        // Prefer mid-month day used on ERP screens (02), else last day of month
        $asOf = sprintf('%04d-%02d-02', $y, $m);
        if (!checkdate($m, 2, $y)) {
            $asOf = date('Y-m-t', mktime(0, 0, 0, $m, 1, $y));
        }
    } else {
        $asOf = date('Y-m-d');
    }
}

$keepIds = [];
if (!empty($_GET['keep_ids'])) {
    foreach (explode(',', (string) $_GET['keep_ids']) as $kid) {
        $kid = (int) trim($kid);
        if ($kid > 0) {
            $keepIds[$kid] = true;
        }
    }
}

$conn = getDBConnection();
ensureContractorTables($conn);

// CNC (and mapped ops): pull Production Planning KRA part names from Armor ERP (as-of date)
$erpSync = null;
$kraNameSet = [];
if ($operation !== '' && contractorOperationErpDepartmentName($operation) !== '') {
    try {
        $erpSync = syncErpKraPartsToContractorProducts($operation, $conn, $asOf);
        foreach (($erpSync['names'] ?? []) as $pn) {
            $kraNameSet[strtolower(trim((string) $pn))] = true;
            foreach (contractorKraPartNameAliases($pn) as $al) {
                $kraNameSet[strtolower(trim((string) $al))] = true;
            }
        }
    } catch (Throwable $e) {
        $erpSync = [
            'ok' => false,
            'added' => 0,
            'updated' => 0,
            'total' => 0,
            'message' => $e->getMessage(),
            'names' => [],
            'as_of' => $asOf,
        ];
        if (strcasecmp($operation, 'CNC') === 0) {
            foreach (contractorCncKraBaselinePartNames() as $pn) {
                $kraNameSet[strtolower($pn)] = true;
                foreach (contractorKraPartNameAliases($pn) as $al) {
                    $kraNameSet[strtolower($al)] = true;
                }
                contractorEnsureProduct($conn, $operation, $pn, 0.0);
            }
        }
    }
}

$products = getContractorProductsByOperation($operation, $conn);

// CNC Contract Process: show only ERP KRA part names (same list as Production Planning KRA)
if ($kraOnly && $kraNameSet) {
    $filtered = [];
    $bestByKey = [];
    foreach ($products as $r) {
        $id = (int) ($r['id'] ?? 0);
        if (isset($keepIds[$id])) {
            $filtered[] = $r;
            continue;
        }
        if (!contractorProductMatchesKraNames($r, $kraNameSet)) {
            continue;
        }
        $nk = strtolower(trim((string) ($r['product_name'] ?? '')));
        if ($nk === '') {
            $nk = strtolower(trim((string) ($r['process'] ?? '')));
        }
        // Map aliases to canonical KRA key so ARMOR BODY / Armor Hydrant Body collapse
        foreach (($erpSync['names'] ?? []) as $canon) {
            $ck = strtolower(trim((string) $canon));
            $aliases = array_map('strtolower', contractorKraPartNameAliases($canon));
            if ($nk === $ck || in_array($nk, $aliases, true)) {
                $nk = $ck;
                break;
            }
        }
        $rate = (float) ($r['rate'] ?? 0);
        if (!isset($bestByKey[$nk]) || $rate > (float) ($bestByKey[$nk]['rate'] ?? 0)) {
            $bestByKey[$nk] = $r;
        }
    }
    foreach ($bestByKey as $r) {
        $filtered[] = $r;
    }
    $products = $filtered;
}

// Also pull keep_ids even if operation label drifted / duplicate was filtered
if ($keepIds) {
    $have = [];
    foreach ($products as $r) {
        $have[(int) $r['id']] = true;
    }
    $missing = array_keys(array_diff_key($keepIds, $have));
    if ($missing) {
        $in = implode(',', array_map('intval', $missing));
        $extra = $conn->query("SELECT * FROM contractor_products WHERE status = 1 AND id IN ($in)");
        if ($extra) {
            while ($row = $extra->fetch_assoc()) {
                $products[] = $row;
            }
        }
    }
}

// Sort CNC KRA rows in ERP part-name order
if ($kraOnly && !empty($erpSync['names']) && strcasecmp($operation, 'CNC') === 0) {
    $order = [];
    foreach ($erpSync['names'] as $i => $pn) {
        $order[strtolower(trim((string) $pn))] = $i;
        foreach (contractorKraPartNameAliases($pn) as $al) {
            $k = strtolower(trim((string) $al));
            if (!isset($order[$k])) {
                $order[$k] = $i;
            }
        }
    }
    $resolveOrder = static function ($row) use ($order) {
        $candidates = [
            strtolower(trim((string) ($row['product_name'] ?? ''))),
            strtolower(trim((string) ($row['process'] ?? ''))),
        ];
        foreach ($candidates as $c) {
            if ($c !== '' && isset($order[$c])) {
                return $order[$c];
            }
        }
        return 9999;
    };
    usort($products, static function ($a, $b) use ($resolveOrder) {
        $oa = $resolveOrder($a);
        $ob = $resolveOrder($b);
        if ($oa !== $ob) {
            return $oa <=> $ob;
        }
        return strcasecmp(
            (string) ($a['product_name'] ?? ''),
            (string) ($b['product_name'] ?? '')
        );
    });
}

$grades = contractorOperationShowsGrade($operation) ? getContractorGrades($conn) : [];
$conn->close();

$outProducts = [];
$seenLabels = [];
$canonByKey = [];
foreach (($erpSync['names'] ?? []) as $canon) {
    $ck = strtolower(trim((string) $canon));
    $canonByKey[$ck] = $canon;
    foreach (contractorKraPartNameAliases($canon) as $al) {
        $canonByKey[strtolower(trim((string) $al))] = $canon;
    }
}
foreach ($products as $r) {
    $id = (int) $r['id'];
    $process = trim(html_entity_decode((string) ($r['process'] ?? '')));
    $name = trim(html_entity_decode((string) ($r['product_name'] ?? '')));
    // Prefer Product Name (what users add in Product Master). Include process when different.
    if ($name !== '' && $process !== '' && $process !== '-' && strcasecmp($name, $process) !== 0) {
        $label = $name . ' · ' . $process;
    } elseif ($name !== '') {
        $label = $name;
    } else {
        $label = $process;
    }
    // Clone KRA mode: show ERP part name casing (Lite Female, Armor Hydrant Body, …)
    if ($kraOnly && $label !== '') {
        $lk = strtolower($name !== '' ? $name : $process);
        if (isset($canonByKey[$lk])) {
            $label = $canonByKey[$lk];
            $name = $label;
        }
    }
    if ($label === '') {
        continue;
    }
    $key = function_exists('mb_strtolower')
        ? mb_strtolower($label, 'UTF-8')
        : strtolower($label);

    // Duplicate Contract Process labels: keep ALL ids (append #id for clarity).
    if (isset($seenLabels[$key])) {
        $label = $label . ' #' . $id;
    } else {
        $seenLabels[$key] = $id;
    }

    $outProducts[] = [
        'id' => $id,
        'name' => $label,
        'product_name' => $name,
        'process' => $process,
        'rate' => (float) $r['rate'],
        'ot_rate' => parseOtRate($r['ot_text'] ?? ''),
        'rejection_rate' => (float) $r['rejection_rate'],
    ];
}

$outGrades = [];
foreach ($grades as $g) {
    $outGrades[] = [
        'id' => (int) ($g['id'] ?? 0),
        'name' => (string) ($g['name'] ?? ''),
    ];
}

// #region agent log
if ($kraOnly && is_array($erpSync)) {
    @file_put_contents(
        dirname(__DIR__, 2) . '/debug-219c73.log',
        json_encode([
            'sessionId' => '219c73',
            'runId' => 'live-sync',
            'hypothesisId' => 'H-poll',
            'location' => 'products_json.php',
            'message' => 'KRA products_json sync result',
            'data' => [
                'as_of' => $asOf,
                'product_count' => count($outProducts),
                'added' => (int) ($erpSync['added'] ?? 0),
                'total' => (int) ($erpSync['total'] ?? 0),
                'ok' => !empty($erpSync['ok']),
                'msg' => (string) ($erpSync['message'] ?? ''),
                'names_tail' => array_slice(array_map('strval', $erpSync['names'] ?? []), -3),
            ],
            'timestamp' => (int) round(microtime(true) * 1000),
        ]) . "\n",
        FILE_APPEND
    );
}
// #endregion

echo json_encode([
    'products' => $outProducts,
    'grades' => $outGrades,
    'show_grade' => contractorOperationShowsGrade($operation),
    'erp_sync' => $erpSync,
    'as_of' => $asOf,
    'kra_only' => $kraOnly,
]);
