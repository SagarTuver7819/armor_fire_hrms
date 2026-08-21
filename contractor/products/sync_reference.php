<?php
/**
 * Sync Product Master from reference Ocean HRMS (same as reference Product Master)
 */

require_once __DIR__ . '/../_bootstrap.php';
requireAdmin();

$pageTitle = 'Sync Products from Reference';
$useSidebar = true;
$sidebarMode = 'contractor';
$sidebarActive = 'contractor_products';

$log = [];
$ok = true;
$defaults = [
    'base_url' => 'https://hrms.oceaninfotechcrm.com/software',
    'app_key' => 'Armor@2025',
    'username' => 'Armor@Admin',
    'password' => '',
];

function prodRefHttp($url, $cookieFile, $postFields = null, $headers = [])
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_COOKIEJAR => $cookieFile,
        CURLOPT_COOKIEFILE => $cookieFile,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => 180,
        CURLOPT_HTTPHEADER => array_merge([
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        ], $headers),
    ]);
    if ($postFields !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
    }
    $body = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    if ($body === false) {
        throw new RuntimeException('cURL error: ' . $err);
    }
    return [$code, $body];
}

function prodRefFetchAll($base, $cookieFile, $csrf = '')
{
    $all = [];
    $start = 0;
    $total = null;
    $headers = [
        'X-Requested-With: XMLHttpRequest',
        'Accept: application/json, text/javascript, */*; q=0.01',
        'Referer: ' . rtrim($base, '/') . '/products',
    ];
    if ($csrf !== '') {
        $headers[] = 'X-CSRF-TOKEN: ' . $csrf;
    }
    while (true) {
        $url = rtrim($base, '/') . '/products?draw=1&start=' . $start . '&length=100';
        [$code, $body] = prodRefHttp($url, $cookieFile, null, $headers);
        $json = json_decode($body, true);
        if (!is_array($json) || !isset($json['data'])) {
            throw new RuntimeException('Invalid JSON from products. HTTP ' . $code);
        }
        if ($total === null) {
            $total = (int) ($json['recordsTotal'] ?? 0);
        }
        $all = array_merge($all, $json['data']);
        $start += count($json['data']);
        if (!$json['data'] || count($all) >= $total) {
            break;
        }
    }
    return $all;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $base = rtrim(trim($_POST['base_url'] ?? $defaults['base_url']), '/');
    $appKey = trim($_POST['app_key'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = (string) ($_POST['password'] ?? '');
    $defaults['base_url'] = $base;
    $defaults['app_key'] = $appKey;
    $defaults['username'] = $username;

    try {
        if (!function_exists('curl_init')) {
            throw new RuntimeException('PHP cURL is required.');
        }
        $cookieFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'armor_ref_prod_cookies.txt';
        @unlink($cookieFile);

        [$c1, $loginHtml] = prodRefHttp($base . '/login', $cookieFile);
        $token = '';
        if (preg_match('/name="_token"\s+value="([^"]+)"/', $loginHtml, $m)) {
            $token = $m[1];
        }
        if ($token === '') {
            throw new RuntimeException('CSRF token missing on login page.');
        }

        [$c2, $after] = prodRefHttp($base . '/login-submit', $cookieFile, http_build_query([
            '_token' => $token,
            'app_key' => $appKey,
            'username' => $username,
            'password' => $password,
        ]), [
            'Content-Type: application/x-www-form-urlencoded',
            'Accept: text/html',
            'Origin: https://hrms.oceaninfotechcrm.com',
            'Referer: ' . $base . '/login',
        ]);
        if (stripos($after, 'Welcome to HRMS') !== false && stripos($after, 'Sign in') !== false) {
            throw new RuntimeException('Login failed. Check credentials.');
        }
        $log[] = 'Logged into reference HRMS.';

        [$c3, $prodPage] = prodRefHttp($base . '/products', $cookieFile, null, [
            'Accept: text/html',
            'Referer: ' . $base . '/dashboard',
        ]);
        $csrf = $token;
        if (preg_match('/csrf-token"\s+content="([^"]+)"/', $prodPage, $m)) {
            $csrf = $m[1];
        }

        $rows = prodRefFetchAll($base, $cookieFile, $csrf);
        $log[] = 'Fetched products from reference: ' . count($rows);

        $conn = getDBConnection();
        ensureContractorTables($conn);

        // Soft-deactivate all, then upsert from reference (exact same master)
        $conn->query('UPDATE contractor_products SET status = 0');

        $find = $conn->prepare(
            'SELECT id FROM contractor_products WHERE operation = ? AND product_name = ? AND process = ? LIMIT 1'
        );
        $ins = $conn->prepare(
            'INSERT INTO contractor_products (operation, product_name, process, unit, rate, ot_text, rejection_rate, status)
             VALUES (?,?,?,?,?,?,?,1)'
        );
        $upd = $conn->prepare(
            'UPDATE contractor_products
             SET unit=?, rate=?, ot_text=?, rejection_rate=?, status=1
             WHERE id=?'
        );

        $inserted = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($rows as $r) {
            $operation = trim(html_entity_decode((string) ($r['operation'] ?? '')));
            $name = trim(html_entity_decode((string) ($r['name'] ?? '')));
            $process = trim(html_entity_decode((string) ($r['process'] ?? '')));
            if ($process === '') {
                $process = $name;
            }
            if ($operation === '' || $name === '') {
                $skipped++;
                continue;
            }
            $unit = trim((string) ($r['unit'] ?? 'PCS'));
            if ($unit === '') {
                $unit = 'PCS';
            }
            $rate = (float) ($r['rate'] ?? 0);
            $ot = trim((string) ($r['ot_text'] ?? ''));
            $rej = (float) ($r['rejection_rate'] ?? 0);

            $find->bind_param('sss', $operation, $name, $process);
            $find->execute();
            $ex = $find->get_result()->fetch_assoc();
            if ($ex) {
                $id = (int) $ex['id'];
                $upd->bind_param('sdsdi', $unit, $rate, $ot, $rej, $id);
                $upd->execute();
                $updated++;
            } else {
                $ins->bind_param('ssssdsd', $operation, $name, $process, $unit, $rate, $ot, $rej);
                $ins->execute();
                $inserted++;
            }
        }
        $find->close();
        $ins->close();
        $upd->close();

        $active = (int) $conn->query('SELECT COUNT(*) AS c FROM contractor_products WHERE status = 1')->fetch_assoc()['c'];
        $conn->close();
        @unlink($cookieFile);

        $log[] = "Sync done. Inserted: {$inserted}, Updated: {$updated}, Skipped: {$skipped}, Active now: {$active}.";
    } catch (Throwable $e) {
        $ok = false;
        $log[] = 'Error: ' . $e->getMessage();
    }
}

require_once __DIR__ . '/../../includes/header.php';
?>
<main class="dashboard-main">
    <div class="page-toolbar">
        <a href="<?php echo app_url('contractor/products/index.php'); ?>" class="back-link">
            <i class="fa-solid fa-arrow-left"></i> Back to Product Master
        </a>
    </div>
    <div class="form-page-card">
        <div class="form-page-header">
            <h1><?php echo $ok && $log ? 'Product Sync complete' : 'Sync Products from Reference HRMS'; ?></h1>
            <p>Pulls Product Master (operation, name, process, rate, OT, rejection) same as reference and replaces local active list.</p>
        </div>
        <?php if ($log): ?>
            <div class="form-section">
                <h3>Result</h3>
                <ul>
                    <?php foreach ($log as $line): ?>
                        <li><?php echo htmlspecialchars($line); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        <form method="POST" class="employee-form" autocomplete="off">
            <div class="form-section">
                <h3><i class="fa-solid fa-cloud-arrow-down"></i> Reference Login</h3>
                <div class="form-grid form-grid-2">
                    <div class="form-group span-2">
                        <label>Base URL</label>
                        <input type="text" name="base_url" class="form-control" required value="<?php echo htmlspecialchars($defaults['base_url']); ?>">
                    </div>
                    <div class="form-group">
                        <label>App Key</label>
                        <input type="text" name="app_key" class="form-control" required value="<?php echo htmlspecialchars($defaults['app_key']); ?>">
                    </div>
                    <div class="form-group">
                        <label>Username</label>
                        <input type="text" name="username" class="form-control" required value="<?php echo htmlspecialchars($defaults['username']); ?>">
                    </div>
                    <div class="form-group">
                        <label>Password</label>
                        <input type="password" name="password" class="form-control" required placeholder="Enter reference password">
                    </div>
                </div>
            </div>
            <div class="form-actions sticky-actions">
                <button type="submit" class="btn-primary" onclick="return confirm('Sync all products from reference into this software?');">
                    <i class="fa-solid fa-rotate"></i> Start Product Sync
                </button>
                <a href="<?php echo app_url('contractor/products/index.php'); ?>" class="btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</main>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
