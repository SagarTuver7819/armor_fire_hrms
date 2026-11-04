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
            $ck = contractorKraCanonicalKey($pn);
            if ($ck !== '') {
                $kraNameSet[$ck] = true;
            }
            foreach (contractorKraPartNameLookupCandidates($pn) as $al) {
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
                $ck = contractorKraCanonicalKey($pn);
                if ($ck !== '') {
                    $kraNameSet[$ck] = true;
                }
                foreach (contractorKraPartNameLookupCandidates($pn) as $al) {
                    $kraNameSet[strtolower(trim((string) $al))] = true;
                }
                contractorEnsureProduct($conn, $operation, $pn, 0.0);
            }
        }
    }
}

$products = getContractorProductsByOperation($operation, $conn);

// CNC Contract Process: show only ERP KRA part names (same list as Production Planning KRA)
if ($kraOnly && $kraNameSet) {
    $bestByKey = [];
    foreach ($products as $r) {
        $id = (int) ($r['id'] ?? 0);
        $forceKeep = isset($keepIds[$id]);
        if (!$forceKeep && !contractorProductMatchesKraNames($r, $kraNameSet)) {
            continue;
        }
        $raw = trim((string) ($r['product_name'] ?? ''));
        if ($raw === '') {
            $raw = trim((string) ($r['process'] ?? ''));
        }
        $nk = contractorKraCanonicalKey($raw);
        if ($nk === '') {
            $nk = 'id:' . $id;
        }
        // Prefer ERP snapshot spelling as collapse key when available
        foreach (($erpSync['names'] ?? []) as $canon) {
            if (contractorKraCanonicalKey($canon) === $nk) {
                $nk = contractorKraCanonicalKey($canon);
                break;
            }
        }
        $rate = (float) ($r['rate'] ?? 0);
        if (!isset($bestByKey[$nk]) || $rate > (float) ($bestByKey[$nk]['rate'] ?? 0)) {
            $bestByKey[$nk] = $r;
        }
    }
    $products = array_values($bestByKey);
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
                if ($kraOnly) {
                    // Collapse into existing canonical row — never reintroduce Armor Hydrant Body #273
                    $raw = trim((string) ($row['product_name'] ?? $row['process'] ?? ''));
                    $nk = contractorKraCanonicalKey($raw);
                    $dup = false;
                    foreach ($products as $ex) {
                        $exRaw = trim((string) ($ex['product_name'] ?? $ex['process'] ?? ''));
                        if ($nk !== '' && $nk === contractorKraCanonicalKey($exRaw)) {
                            $dup = true;
                            break;
                        }
                    }
                    if ($dup) {
                        continue;
                    }
                }
                $products[] = $row;
            }
        }
    }
}

// Sort CNC KRA rows in ERP part-name order
if ($kraOnly && !empty($erpSync['names']) && strcasecmp($operation, 'CNC') === 0) {
    $order = [];
    foreach ($erpSync['names'] as $i => $pn) {
        $ck = contractorKraCanonicalKey($pn);
        if ($ck !== '' && !isset($order[$ck])) {
            $order[$ck] = $i;
        }
        foreach (contractorKraPartNameLookupCandidates($pn) as $al) {
            $k = strtolower(trim((string) $al));
            if ($k !== '' && !isset($order[$k])) {
                $order[$k] = $i;
            }
        }
    }
    $resolveOrder = static function ($row) use ($order) {
        foreach (['product_name', 'process'] as $col) {
            $raw = trim((string) ($row[$col] ?? ''));
            if ($raw === '') {
                continue;
            }
            $ck = contractorKraCanonicalKey($raw);
            if ($ck !== '' && isset($order[$ck])) {
                return $order[$ck];
            }
            $c = strtolower($raw);
            if (isset($order[$c])) {
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
    $ck = contractorKraCanonicalKey($canon);
    if ($ck === '') {
        continue;
    }
    $canonByKey[$ck] = $canon;
    foreach (contractorKraPartNameLookupCandidates($canon) as $al) {
        $canonByKey[strtolower(trim((string) $al))] = $canon;
        $canonByKey[contractorKraCanonicalKey($al)] = $canon;
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
    // Clone KRA mode: show ERP part name casing (Lite Female, Lurning Bush, …)
    if ($kraOnly && $label !== '') {
        $lk = contractorKraCanonicalKey($name !== '' ? $name : $process);
        if ($lk !== '' && isset($canonByKey[$lk])) {
            $label = $canonByKey[$lk];
            $name = $label;
        }
    }
    if ($label === '') {
        continue;
    }
    $key = $kraOnly
        ? contractorKraCanonicalKey($label)
        : (function_exists('mb_strtolower')
            ? mb_strtolower($label, 'UTF-8')
            : strtolower($label));
    if ($key === '') {
        $key = strtolower($label);
    }

    // KRA mode: one row per ERP part — skip alias duplicates (no "#273" labels)
    if (isset($seenLabels[$key])) {
        if ($kraOnly) {
            continue;
        }
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

echo json_encode([
    'products' => $outProducts,
    'grades' => $outGrades,
    'show_grade' => contractorOperationShowsGrade($operation),
    'erp_sync' => $erpSync,
    'as_of' => $asOf,
    'kra_only' => $kraOnly,
]);
