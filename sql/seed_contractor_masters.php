<?php
/**
 * Import Armor Steel Product + Grade masters into Armor Fire contractor tables.
 * Safe re-run: skips/updates by operation + product name + process.
 *
 * CLI: php sql/seed_contractor_masters.php
 * Live: open db_sync.php (Admin)
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/contractor_helper.php';

function seedContractorProductMasters($conn)
{
    $localJson = __DIR__ . '/armor_steel_products.json';
    $products = [];
    if (is_file($localJson)) {
        $products = json_decode((string) file_get_contents($localJson), true);
    }

    if (!is_array($products) || !$products) {
        return [
            'ok' => false,
            'error' => 'sql/armor_steel_products.json not found.',
            'grades_inserted' => 0,
            'grades_active' => 0,
            'products_inserted' => 0,
            'products_updated' => 0,
            'products_skipped' => 0,
            'products_active' => 0,
            'source' => 0,
        ];
    }

    $grades = ['ALUMINIUM', 'GUNMETAL', '202', '304'];
    $ops = contractorOperations();
    ensureContractorTables($conn);

    $gradeInserted = 0;
    $gCheck = $conn->prepare('SELECT id FROM contractor_grades WHERE grade_name = ? LIMIT 1');
    $gIns = $conn->prepare('INSERT INTO contractor_grades (grade_name, status) VALUES (?, 1)');
    foreach ($grades as $name) {
        $gCheck->bind_param('s', $name);
        $gCheck->execute();
        $exists = $gCheck->get_result()->fetch_assoc();
        if ($exists) {
            $conn->query('UPDATE contractor_grades SET status = 1 WHERE id = ' . (int) $exists['id']);
            continue;
        }
        $gIns->bind_param('s', $name);
        $gIns->execute();
        $gradeInserted++;
    }
    $gCheck->close();
    $gIns->close();

    $pCheck = $conn->prepare(
        'SELECT id FROM contractor_products
         WHERE operation = ? AND product_name = ? AND IFNULL(process, "") = ?
         LIMIT 1'
    );
    $pIns = $conn->prepare(
        'INSERT INTO contractor_products (operation, product_name, process, unit, rate, ot_text, rejection_rate, status)
         VALUES (?,?,?,?,?,?,?,1)'
    );
    $pUpd = $conn->prepare(
        'UPDATE contractor_products
         SET unit=?, rate=?, ot_text=?, rejection_rate=?, status=1
         WHERE id=?'
    );

    $inserted = 0;
    $updated = 0;
    $skipped = 0;

    foreach ($products as $p) {
        $operation = html_entity_decode(trim((string) ($p['operation'] ?? '')), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $name = html_entity_decode(trim((string) ($p['name'] ?? '')), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $process = html_entity_decode(trim((string) ($p['process'] ?? '')), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $unit = strtoupper(trim((string) ($p['unit'] ?? 'PCS')));
        if ($unit !== 'KG') {
            $unit = 'PCS';
        }
        $rate = (float) ($p['rate'] ?? 0);
        $otText = trim((string) ($p['ot_text'] ?? ''));
        $rej = (float) ($p['rejection_rate'] ?? 0);
        $active = !empty($p['active']);

        if ($name === '' || !isset($ops[$operation])) {
            $skipped++;
            continue;
        }

        $pCheck->bind_param('sss', $operation, $name, $process);
        $pCheck->execute();
        $row = $pCheck->get_result()->fetch_assoc();
        if ($row) {
            $id = (int) $row['id'];
            $pUpd->bind_param('sdsdi', $unit, $rate, $otText, $rej, $id);
            $pUpd->execute();
            if (!$active) {
                $conn->query('UPDATE contractor_products SET status = 0 WHERE id = ' . $id);
            }
            $updated++;
            continue;
        }
        if (!$active) {
            $skipped++;
            continue;
        }
        $pIns->bind_param('ssssdsd', $operation, $name, $process, $unit, $rate, $otText, $rej);
        $pIns->execute();
        $inserted++;
    }

    $pCheck->close();
    $pIns->close();
    $pUpd->close();

    $totalP = (int) $conn->query('SELECT COUNT(*) AS c FROM contractor_products WHERE status = 1')->fetch_assoc()['c'];
    $totalG = (int) $conn->query('SELECT COUNT(*) AS c FROM contractor_grades WHERE status = 1')->fetch_assoc()['c'];

    return [
        'ok' => true,
        'error' => '',
        'grades_inserted' => $gradeInserted,
        'grades_active' => $totalG,
        'products_inserted' => $inserted,
        'products_updated' => $updated,
        'products_skipped' => $skipped,
        'products_active' => $totalP,
        'source' => count($products),
    ];
}

if (PHP_SAPI === 'cli' && realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? __FILE__)) === realpath(__FILE__)) {
    $conn = getDBConnection();
    $out = seedContractorProductMasters($conn);
    $conn->close();
    if (!$out['ok']) {
        echo $out['error'] . PHP_EOL;
        exit(1);
    }
    echo "Grades inserted: {$out['grades_inserted']} (active now: {$out['grades_active']})\n";
    echo "Products inserted: {$out['products_inserted']}, updated: {$out['products_updated']}, skipped: {$out['products_skipped']}\n";
    echo "Active contractor products: {$out['products_active']}\n";
    echo "Source dump records: {$out['source']}\n";
}
