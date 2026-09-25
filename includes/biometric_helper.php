<?php
/**
 * Biometric / Old CRM machines — link, sync, machine-wise logs
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/employee_helper.php';
require_once __DIR__ . '/attendance_helper.php';

function ensureBiometricTables($conn = null)
{
    $closeAfter = false;
    if ($conn === null) {
        $conn = getDBConnection();
        $closeAfter = true;
    }

    ensureAttendanceTables($conn);

    $conn->query(
        "CREATE TABLE IF NOT EXISTS biometric_machines (
            id INT AUTO_INCREMENT PRIMARY KEY,
            machine_code VARCHAR(50) NOT NULL,
            machine_name VARCHAR(150) NOT NULL,
            provider_type VARCHAR(40) NOT NULL DEFAULT 'old_crm',
            api_url VARCHAR(500) NOT NULL,
            auth_type VARCHAR(30) NOT NULL DEFAULT 'basic',
            api_username VARCHAR(120) DEFAULT NULL,
            api_password VARCHAR(255) DEFAULT NULL,
            corporate_id VARCHAR(120) DEFAULT NULL,
            ip_address VARCHAR(80) DEFAULT NULL,
            port VARCHAR(20) DEFAULT NULL,
            ocean_machine_id INT DEFAULT NULL,
            sync_interval_minutes INT NOT NULL DEFAULT 60,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            last_sync_at DATETIME DEFAULT NULL,
            last_sync_status VARCHAR(30) DEFAULT NULL,
            last_sync_message VARCHAR(500) DEFAULT NULL,
            last_sync_count INT NOT NULL DEFAULT 0,
            remarks VARCHAR(255) DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_bio_machine_code (machine_code),
            INDEX idx_bio_active (is_active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $conn->query(
        "CREATE TABLE IF NOT EXISTS machine_attendance_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            machine_id INT DEFAULT NULL,
            ocean_log_id BIGINT DEFAULT NULL,
            employee_id INT DEFAULT NULL,
            employee_code VARCHAR(50) DEFAULT NULL,
            employee_name VARCHAR(150) DEFAULT NULL,
            biometric_user_id VARCHAR(50) DEFAULT NULL,
            attendance_date DATE NOT NULL,
            punch_time TIME NOT NULL,
            punch_type VARCHAR(20) NOT NULL DEFAULT 'in',
            records_source VARCHAR(60) DEFAULT NULL,
            device_ip VARCHAR(80) DEFAULT NULL,
            device_serial VARCHAR(120) DEFAULT NULL,
            txn_id VARCHAR(120) DEFAULT NULL,
            remark VARCHAR(255) DEFAULT NULL,
            raw_json MEDIUMTEXT DEFAULT NULL,
            synced_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_mal_ocean (ocean_log_id),
            UNIQUE KEY uq_mal_txn (txn_id),
            INDEX idx_mal_date (attendance_date),
            INDEX idx_mal_emp (employee_id, attendance_date),
            INDEX idx_mal_machine (machine_id, attendance_date),
            INDEX idx_mal_code (employee_code)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $conn->query(
        "CREATE TABLE IF NOT EXISTS biometric_sync_settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            setting_key VARCHAR(80) NOT NULL,
            setting_value TEXT,
            UNIQUE KEY uq_bio_setting (setting_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $conn->query(
        "CREATE TABLE IF NOT EXISTS biometric_employee_map (
            id INT AUTO_INCREMENT PRIMARY KEY,
            biometric_code VARCHAR(50) NOT NULL,
            employee_id INT NOT NULL,
            employee_code VARCHAR(50) DEFAULT NULL,
            notes VARCHAR(255) DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_bio_map_code (biometric_code),
            INDEX idx_bio_map_emp (employee_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    seedBiometricSyncDefaults($conn);
    // Do NOT auto-seed machines here — that recreated deleted machines on every page load.
    // Defaults are seeded once only via seedArmorBiometricMachinesOnce() from db_sync.

    if ($closeAfter) {
        $conn->close();
    }
}

function seedBiometricSyncDefaults($conn)
{
    $defaults = [
        'ocean_base_url' => 'https://hrms.oceaninfotechcrm.com/software',
        'ocean_app_key' => 'office@2016',
        'ocean_username' => 'admincrm@crmocean.com',
        'ocean_password' => 'Ocean@92',
        'ocean_company_id' => '2',
    ];
    foreach ($defaults as $k => $v) {
        $st = $conn->prepare(
            "INSERT IGNORE INTO biometric_sync_settings (setting_key, setting_value) VALUES (?, ?)"
        );
        $st->bind_param('ss', $k, $v);
        $st->execute();
        $st->close();
    }
}

function seedArmorBiometricMachines($conn)
{
    seedArmorBiometricMachinesOnce($conn);
}

/**
 * Seed default Armor machines ONCE only.
 * After first seed (or if any machine already exists / user deleted), never recreate.
 */
function seedArmorBiometricMachinesOnce($conn)
{
    seedBiometricSyncDefaults($conn);

    $flag = '';
    $st = $conn->prepare(
        "SELECT setting_value FROM biometric_sync_settings WHERE setting_key = 'biometric_machines_seeded' LIMIT 1"
    );
    if ($st) {
        $st->execute();
        $row = $st->get_result()->fetch_assoc();
        $st->close();
        $flag = (string) ($row['setting_value'] ?? '');
    }
    if ($flag === '1') {
        return;
    }

    $cntRes = $conn->query('SELECT COUNT(*) AS c FROM biometric_machines');
    $cntRow = $cntRes ? $cntRes->fetch_assoc() : null;
    $count = (int) ($cntRow['c'] ?? 0);

    // Already have machines (or user manages list) — lock seed forever
    if ($count > 0) {
        biometricMarkMachinesSeeded($conn);
        return;
    }

    $machines = [
        [
            'machine_code' => 'ARMOR_OLD_CRM_SALARY',
            'machine_name' => 'Old Crm Hrms To Crm',
            'provider_type' => 'old_crm',
            'api_url' => 'http://armorfire-crm.oceanhub.co.in/api/salary-logs',
            'api_username' => 'admin',
            'api_password' => '',
            'corporate_id' => '',
            'ip_address' => '127.0.0.1',
            'port' => '80',
            'ocean_machine_id' => 4,
            'sync_interval_minutes' => 60,
            'remarks' => 'Old CRM salary-logs pull (Armor)',
        ],
        [
            'machine_code' => 'ARMOR_OLD_CRM_ATT',
            'machine_name' => 'Old Crm',
            'provider_type' => 'old_crm',
            'api_url' => 'http://armorfire-crm.oceanhub.co.in/api/attendance-logs',
            'api_username' => 'admin',
            'api_password' => '',
            'corporate_id' => '',
            'ip_address' => '127.0.0.1',
            'port' => '80',
            'ocean_machine_id' => 3,
            'sync_interval_minutes' => 60,
            'remarks' => 'Old CRM attendance-logs pull (use HTTP; HTTPS SSL expired)',
        ],
        [
            'machine_code' => 'ARMOR_EASYBIO',
            'machine_name' => 'EASyBio',
            'provider_type' => 'etimeoffice',
            'api_url' => 'https://api.etimeoffice.com/api/',
            'api_username' => 'ARMORSTEEL99',
            'api_password' => '',
            'corporate_id' => 'ARMORSTEEL99',
            'ip_address' => '192.168.1.199',
            'port' => '5005',
            'ocean_machine_id' => 2,
            'sync_interval_minutes' => 5,
            'remarks' => 'Devi Electronic / eTimeOffice live biometric',
        ],
    ];

    foreach ($machines as $m) {
        $code = $m['machine_code'];
        $chk = $conn->prepare('SELECT id FROM biometric_machines WHERE machine_code = ? LIMIT 1');
        $chk->bind_param('s', $code);
        $chk->execute();
        $exist = $chk->get_result()->fetch_assoc();
        $chk->close();
        if ($exist) {
            continue;
        }
        $ins = $conn->prepare(
            "INSERT INTO biometric_machines
                (machine_code, machine_name, provider_type, api_url, auth_type, api_username, api_password,
                 corporate_id, ip_address, port, ocean_machine_id, sync_interval_minutes, is_active, remarks)
             VALUES (?, ?, ?, ?, 'basic', ?, ?, ?, ?, ?, ?, ?, 1, ?)"
        );
        $ins->bind_param(
            'sssssssssiis',
            $m['machine_code'],
            $m['machine_name'],
            $m['provider_type'],
            $m['api_url'],
            $m['api_username'],
            $m['api_password'],
            $m['corporate_id'],
            $m['ip_address'],
            $m['port'],
            $m['ocean_machine_id'],
            $m['sync_interval_minutes'],
            $m['remarks']
        );
        $ins->execute();
        $ins->close();
    }

    biometricMarkMachinesSeeded($conn);
}

function biometricMarkMachinesSeeded($conn = null)
{
    $closeAfter = false;
    if ($conn === null) {
        $conn = getDBConnection();
        $closeAfter = true;
    }
    $key = 'biometric_machines_seeded';
    $val = '1';
    $st = $conn->prepare(
        "INSERT INTO biometric_sync_settings (setting_key, setting_value) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)"
    );
    $st->bind_param('ss', $key, $val);
    $st->execute();
    $st->close();
    if ($closeAfter) {
        $conn->close();
    }
}

function biometricGetSetting($key, $default = '')
{
    $conn = getDBConnection();
    ensureBiometricTables($conn);
    $st = $conn->prepare('SELECT setting_value FROM biometric_sync_settings WHERE setting_key = ? LIMIT 1');
    $st->bind_param('s', $key);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    $st->close();
    $conn->close();
    if (!$row) {
        return $default;
    }
    return (string) ($row['setting_value'] ?? $default);
}

function biometricSetSetting($key, $value)
{
    $conn = getDBConnection();
    ensureBiometricTables($conn);
    $st = $conn->prepare(
        "INSERT INTO biometric_sync_settings (setting_key, setting_value) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)"
    );
    $st->bind_param('ss', $key, $value);
    $st->execute();
    $st->close();
    $conn->close();
}

function fetchBiometricMachines($activeOnly = false)
{
    $conn = getDBConnection();
    ensureBiometricTables($conn);
    $sql = 'SELECT * FROM biometric_machines';
    if ($activeOnly) {
        $sql .= ' WHERE is_active = 1';
    }
    $sql .= ' ORDER BY id ASC';
    $rows = [];
    $res = $conn->query($sql);
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $rows[] = $r;
        }
    }
    $conn->close();
    return $rows;
}

function getBiometricMachineById($id)
{
    $id = (int) $id;
    if ($id <= 0) {
        return null;
    }
    $conn = getDBConnection();
    ensureBiometricTables($conn);
    $st = $conn->prepare('SELECT * FROM biometric_machines WHERE id = ? LIMIT 1');
    $st->bind_param('i', $id);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    $st->close();
    $conn->close();
    return $row ?: null;
}

function biometricHttpRequest($url, $cookieFile, $postFields = null, $headers = [])
{
    $ch = curl_init($url);
    $defaultHeaders = [
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        'Accept-Language: en-US,en;q=0.9',
    ];
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 8,
        CURLOPT_COOKIEJAR => $cookieFile,
        CURLOPT_COOKIEFILE => $cookieFile,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_TIMEOUT => 180,
        CURLOPT_HTTPHEADER => array_merge($defaultHeaders, $headers),
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

/**
 * Login to Ocean HRMS and return [cookieFile, csrfToken]
 */
function biometricOceanLogin()
{
    $base = rtrim(biometricGetSetting('ocean_base_url', 'https://hrms.oceaninfotechcrm.com/software'), '/');
    $appKey = biometricGetSetting('ocean_app_key', 'office@2016');
    $username = biometricGetSetting('ocean_username', 'admincrm@crmocean.com');
    $password = biometricGetSetting('ocean_password', 'Ocean@92');

    $cookieFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'armor_bio_ocean_cookies.txt';
    @unlink($cookieFile);

    [$code, $loginHtml] = biometricHttpRequest($base . '/login', $cookieFile);
    $token = '';
    if (preg_match('/name="_token"\\s+value="([^"]+)"/', $loginHtml, $m)) {
        $token = $m[1];
    } elseif (preg_match('/csrf-token"\\s+content="([^"]+)"/', $loginHtml, $m)) {
        $token = $m[1];
    }
    if ($token === '') {
        throw new RuntimeException('Could not read Ocean HRMS CSRF token.');
    }

    $post = http_build_query([
        '_token' => $token,
        'app_key' => $appKey,
        'username' => $username,
        'password' => $password,
    ]);
    [$code2, $after] = biometricHttpRequest($base . '/login-submit', $cookieFile, $post, [
        'Content-Type: application/x-www-form-urlencoded',
        'Origin: https://hrms.oceaninfotechcrm.com',
        'Referer: ' . $base . '/login',
    ]);
    if (stripos($after, 'Welcome to HRMS') !== false && stripos($after, 'Sign in') !== false) {
        throw new RuntimeException('Ocean HRMS login failed. Check sync settings credentials.');
    }

    // Refresh CSRF from dashboard/attendance
    [$c3, $html] = biometricHttpRequest($base . '/attendance', $cookieFile, null, [
        'Accept: text/html',
        'Referer: ' . $base . '/dashboard',
    ]);
    $csrf = $token;
    if (preg_match('/csrf-token"\\s+content="([^"]+)"/', $html, $m)) {
        $csrf = $m[1];
    }

    return [$cookieFile, $csrf, $base];
}

function biometricOceanTriggerMachineSync($oceanMachineId, $cookieFile, $csrf, $base)
{
    $oceanMachineId = (int) $oceanMachineId;
    if ($oceanMachineId <= 0) {
        return ['ok' => false, 'message' => 'No Ocean machine id'];
    }
    $url = rtrim($base, '/') . '/biometric-machines/' . $oceanMachineId . '/sync-attendance';
    [$code, $body] = biometricHttpRequest($url, $cookieFile, '{}', [
        'Content-Type: application/json',
        'Accept: application/json',
        'X-Requested-With: XMLHttpRequest',
        'X-CSRF-TOKEN: ' . $csrf,
        'Referer: ' . rtrim($base, '/') . '/biometric-machines',
    ]);
    $json = json_decode($body, true);
    if (!is_array($json)) {
        return ['ok' => false, 'message' => 'Invalid sync response HTTP ' . $code];
    }
    return [
        'ok' => !empty($json['status']),
        'message' => (string) ($json['message'] ?? ''),
        'fetched' => (int) ($json['data']['records_fetched'] ?? 0),
    ];
}

function biometricParseOceanDate($value)
{
    $value = trim((string) $value);
    if ($value === '') {
        return null;
    }
    if (preg_match('/^(\\d{2})\\/(\\d{2})\\/(\\d{4})$/', $value, $m)) {
        return $m[3] . '-' . $m[2] . '-' . $m[1];
    }
    if (preg_match('/^\\d{4}-\\d{2}-\\d{2}/', $value)) {
        return substr($value, 0, 10);
    }
    $ts = strtotime($value);
    return $ts ? date('Y-m-d', $ts) : null;
}

function biometricParseOceanTime($value)
{
    $value = trim((string) $value);
    if ($value === '') {
        return null;
    }
    if (preg_match('/^(\\d{1,2}):(\\d{2})(?::(\\d{2}))?$/', $value, $m)) {
        return sprintf('%02d:%02d:%02d', (int) $m[1], (int) $m[2], isset($m[3]) ? (int) $m[3] : 0);
    }
    $ts = strtotime($value);
    return $ts ? date('H:i:s', $ts) : null;
}

function biometricResolveLocalEmployeeId($conn, $employeeCode, $biometricId = '')
{
    $employeeCode = trim((string) $employeeCode);
    $biometricId = trim((string) $biometricId);
    $keys = [];
    if ($employeeCode !== '') {
        $keys[] = $employeeCode;
    }
    if ($biometricId !== '' && strcasecmp($biometricId, $employeeCode) !== 0) {
        $keys[] = $biometricId;
    }

    // 1) Explicit biometric → employee map (manual link)
    foreach ($keys as $key) {
        $st = $conn->prepare(
            "SELECT employee_id FROM biometric_employee_map
             WHERE UPPER(TRIM(biometric_code)) = UPPER(TRIM(?))
             LIMIT 1"
        );
        $st->bind_param('s', $key);
        $st->execute();
        $row = $st->get_result()->fetch_assoc();
        $st->close();
        if ($row && (int) $row['employee_id'] > 0) {
            return (int) $row['employee_id'];
        }
    }

    if ($employeeCode !== '') {
        $st = $conn->prepare(
            "SELECT id FROM employees
             WHERE UPPER(TRIM(employee_code)) = UPPER(TRIM(?))
             LIMIT 1"
        );
        $st->bind_param('s', $employeeCode);
        $st->execute();
        $row = $st->get_result()->fetch_assoc();
        $st->close();
        if ($row) {
            return (int) $row['id'];
        }
    }
    if ($biometricId !== '') {
        $st = $conn->prepare(
            "SELECT id FROM employees
             WHERE UPPER(TRIM(biometric_user_id)) = UPPER(TRIM(?))
                OR UPPER(TRIM(employee_code)) = UPPER(TRIM(?))
             LIMIT 1"
        );
        $st->bind_param('ss', $biometricId, $biometricId);
        $st->execute();
        $row = $st->get_result()->fetch_assoc();
        $st->close();
        if ($row) {
            return (int) $row['id'];
        }
    }
    return 0;
}

function biometricMatchMachineId(array $machines, $recordsSource, $deviceIp)
{
    $recordsSource = strtolower(trim((string) $recordsSource));
    $deviceIp = trim((string) $deviceIp);
    foreach ($machines as $m) {
        $prov = strtolower((string) ($m['provider_type'] ?? ''));
        $ip = trim((string) ($m['ip_address'] ?? ''));
        if ($deviceIp !== '' && $ip !== '' && $deviceIp === $ip) {
            return (int) $m['id'];
        }
        if ($recordsSource !== '' && strpos($recordsSource, 'etime') !== false && $prov === 'etimeoffice') {
            return (int) $m['id'];
        }
        if ($recordsSource !== '' && (strpos($recordsSource, 'old') !== false || strpos($recordsSource, 'crm') !== false) && $prov === 'old_crm') {
            return (int) $m['id'];
        }
    }
    // fallback first active
    return !empty($machines[0]['id']) ? (int) $machines[0]['id'] : 0;
}

/**
 * Sync punches from Ocean HRMS attendance store (already machine-pulled).
 * @return array{ok:bool,inserted:int,updated:int,skipped:int,message:string}
 */
function biometricSyncFromOcean($fromDate = '', $toDate = '', $machineIdFilter = 0)
{
    $conn = getDBConnection();
    ensureBiometricTables($conn);
    $machines = [];
    $resM = $conn->query('SELECT * FROM biometric_machines WHERE is_active = 1 ORDER BY id ASC');
    while ($r = $resM->fetch_assoc()) {
        $machines[] = $r;
    }

    if ($fromDate === '' && $toDate === '') {
        $toDate = date('Y-m-d');
        $fromDate = date('Y-m-d', strtotime('-30 days'));
    }

    try {
        [$cookieFile, $csrf, $base] = biometricOceanLogin();
    } catch (Throwable $e) {
        $conn->close();
        return ['ok' => false, 'inserted' => 0, 'updated' => 0, 'skipped' => 0, 'message' => $e->getMessage()];
    }

    // Optionally trigger machine sync(s) on Ocean first
    $triggerMsgs = [];
    $toTrigger = $machines;
    if ($machineIdFilter > 0) {
        $toTrigger = array_values(array_filter($machines, static function ($m) use ($machineIdFilter) {
            return (int) $m['id'] === (int) $machineIdFilter;
        }));
    }
    foreach ($toTrigger as $m) {
        $oid = (int) ($m['ocean_machine_id'] ?? 0);
        if ($oid > 0) {
            $tr = biometricOceanTriggerMachineSync($oid, $cookieFile, $csrf, $base);
            $triggerMsgs[] = ($m['machine_name'] ?? 'Machine') . ': ' . ($tr['message'] ?: ($tr['ok'] ? 'OK' : 'Fail'));
        }
    }

    $companyId = biometricGetSetting('ocean_company_id', '2');
    $inserted = 0;
    $updated = 0;
    $skipped = 0;
    $start = 0;
    $page = 200;
    $total = null;

    while (true) {
        $url = rtrim($base, '/') . '/attendance?draw=1&start=' . $start . '&length=' . $page
            . '&company_id=' . rawurlencode($companyId);
        [$code, $body] = biometricHttpRequest($url, $cookieFile, null, [
            'X-Requested-With: XMLHttpRequest',
            'Accept: application/json, text/javascript, */*; q=0.01',
            'X-CSRF-TOKEN: ' . $csrf,
            'Referer: ' . rtrim($base, '/') . '/attendance',
        ]);
        if ($code >= 400) {
            $conn->close();
            return [
                'ok' => false,
                'inserted' => $inserted,
                'updated' => $updated,
                'skipped' => $skipped,
                'message' => 'Ocean attendance fetch HTTP ' . $code,
            ];
        }
        $json = json_decode($body, true);
        if (!is_array($json) || !isset($json['data'])) {
            $conn->close();
            return [
                'ok' => false,
                'inserted' => $inserted,
                'updated' => $updated,
                'skipped' => $skipped,
                'message' => 'Invalid Ocean attendance JSON',
            ];
        }
        if ($total === null) {
            $total = (int) ($json['recordsTotal'] ?? 0);
        }
        $chunk = $json['data'];
        if (!$chunk) {
            break;
        }

        foreach ($chunk as $row) {
            $empCode = '';
            $empName = '';
            $bioId = '';
            if (!empty($row['employee']) && is_array($row['employee'])) {
                $empCode = (string) ($row['employee']['employee_code'] ?? '');
                $empName = (string) ($row['employee']['full_name'] ?? '');
                $bioId = (string) ($row['employee']['biometric_user_id'] ?? '');
            }
            if ($empCode === '' && !empty($row['employee_name'])) {
                // "CO72030 - NAME"
                if (preg_match('/^(\\S+)\\s*-\\s*(.+)$/', (string) $row['employee_name'], $mm)) {
                    $empCode = trim($mm[1]);
                    $empName = trim($mm[2]);
                }
            }

            $date = biometricParseOceanDate($row['attendance_date'] ?? '');
            $time = biometricParseOceanTime($row['punch_in_time'] ?? '');
            if (!$date || !$time) {
                $skipped++;
                continue;
            }
            if ($fromDate !== '' && $date < $fromDate) {
                $skipped++;
                continue;
            }
            if ($toDate !== '' && $date > $toDate) {
                $skipped++;
                continue;
            }

            $ptype = strtolower(trim((string) ($row['attendace_type'] ?? $row['attendance_type'] ?? 'in')));
            if (!in_array($ptype, ['in', 'out'], true)) {
                $ptype = (strpos($ptype, 'out') !== false) ? 'out' : 'in';
            }

            $source = (string) ($row['records_source'] ?? '');
            $deviceIp = (string) ($row['device_ip'] ?? '');
            $mid = biometricMatchMachineId($machines, $source, $deviceIp);
            if ($machineIdFilter > 0 && $mid !== (int) $machineIdFilter) {
                // still allow if source/ip didn't match but filter set — skip unmatched
                if ($mid === 0 || $mid !== (int) $machineIdFilter) {
                    // If filter machine is etimeoffice and source matches, keep
                    $filterMachine = null;
                    foreach ($machines as $mx) {
                        if ((int) $mx['id'] === (int) $machineIdFilter) {
                            $filterMachine = $mx;
                            break;
                        }
                    }
                    if ($filterMachine) {
                        $prov = strtolower((string) $filterMachine['provider_type']);
                        $srcL = strtolower($source);
                        $okMatch = ($prov === 'etimeoffice' && strpos($srcL, 'etime') !== false)
                            || ($prov === 'old_crm' && (strpos($srcL, 'old') !== false || strpos($srcL, 'crm') !== false || $srcL === ''))
                            || (trim((string) $filterMachine['ip_address']) !== '' && $deviceIp === trim((string) $filterMachine['ip_address']));
                        if (!$okMatch) {
                            $skipped++;
                            continue;
                        }
                        $mid = (int) $machineIdFilter;
                    } else {
                        $skipped++;
                        continue;
                    }
                }
            }

            $localEmpId = biometricResolveLocalEmployeeId($conn, $empCode, $bioId !== '' ? $bioId : $empCode);
            $oceanId = (int) ($row['id'] ?? 0);
            $txn = trim((string) ($row['txn_id'] ?? ''));
            if ($txn === '' && $oceanId > 0) {
                $txn = 'ocean-' . $oceanId;
            }
            $remark = trim((string) ($row['remark'] ?? ''));
            $serial = (string) ($row['device_serial'] ?? '');
            $raw = json_encode([
                'ocean_id' => $oceanId,
                'records_source' => $source,
                'device_ip' => $deviceIp,
            ], JSON_UNESCAPED_UNICODE);

            $empIdBind = $localEmpId > 0 ? $localEmpId : null;
            $machineBind = $mid > 0 ? $mid : null;
            $oceanBind = $oceanId > 0 ? $oceanId : null;

            // Upsert by ocean_log_id or txn_id
            $existingId = 0;
            if ($oceanBind) {
                $f = $conn->prepare('SELECT id FROM machine_attendance_logs WHERE ocean_log_id = ? LIMIT 1');
                $f->bind_param('i', $oceanBind);
                $f->execute();
                $ex = $f->get_result()->fetch_assoc();
                $f->close();
                $existingId = (int) ($ex['id'] ?? 0);
            }
            if ($existingId <= 0 && $txn !== '') {
                $f = $conn->prepare('SELECT id FROM machine_attendance_logs WHERE txn_id = ? LIMIT 1');
                $f->bind_param('s', $txn);
                $f->execute();
                $ex = $f->get_result()->fetch_assoc();
                $f->close();
                $existingId = (int) ($ex['id'] ?? 0);
            }

            if ($existingId > 0) {
                $machineBindI = $machineBind ?: 0;
                $empIdBindI = $empIdBind ?: 0;
                $up = $conn->prepare(
                    "UPDATE machine_attendance_logs SET
                        machine_id = NULLIF(?, 0),
                        employee_id = NULLIF(?, 0),
                        employee_code=?, employee_name=?, biometric_user_id=?,
                        attendance_date=?, punch_time=?, punch_type=?, records_source=?, device_ip=?,
                        device_serial=?, remark=?, raw_json=?, synced_at=NOW()
                     WHERE id=?"
                );
                $up->bind_param(
                    'iisssssssssssi',
                    $machineBindI,
                    $empIdBindI,
                    $empCode,
                    $empName,
                    $bioId,
                    $date,
                    $time,
                    $ptype,
                    $source,
                    $deviceIp,
                    $serial,
                    $remark,
                    $raw,
                    $existingId
                );
                $up->execute();
                $up->close();
                $updated++;
            } else {
                $oceanBindI = $oceanBind ?: 0;
                $machineBindI = $machineBind ?: 0;
                $empIdBindI = $empIdBind ?: 0;
                $ins = $conn->prepare(
                    "INSERT INTO machine_attendance_logs
                        (machine_id, ocean_log_id, employee_id, employee_code, employee_name, biometric_user_id,
                         attendance_date, punch_time, punch_type, records_source, device_ip, device_serial,
                         txn_id, remark, raw_json, synced_at)
                     VALUES (NULLIF(?,0), NULLIF(?,0), NULLIF(?,0), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())"
                );
                $ins->bind_param(
                    'iiissssssssssss',
                    $machineBindI,
                    $oceanBindI,
                    $empIdBindI,
                    $empCode,
                    $empName,
                    $bioId,
                    $date,
                    $time,
                    $ptype,
                    $source,
                    $deviceIp,
                    $serial,
                    $txn,
                    $remark,
                    $raw
                );
                if ($ins->execute()) {
                    $inserted++;
                } else {
                    $skipped++;
                }
                $ins->close();
            }
        }

        $start += count($chunk);
        if ($start >= $total || count($chunk) < $page) {
            break;
        }
        // Safety cap for first sync run size — allow large, but stop runaway
        if ($start >= 20000) {
            break;
        }
    }

    // Update machine sync stamps
    foreach ($toTrigger as $m) {
        $mid = (int) $m['id'];
        $msg = 'Ocean sync OK';
        $st = $conn->prepare(
            "UPDATE biometric_machines
             SET last_sync_at = NOW(), last_sync_status = 'success',
                 last_sync_message = ?, last_sync_count = ?
             WHERE id = ?"
        );
        $cnt = $inserted + $updated;
        $st->bind_param('sii', $msg, $cnt, $mid);
        $st->execute();
        $st->close();
    }

    $conn->close();
    @unlink($cookieFile);

    $msg = 'Synced from Ocean HRMS. Inserted ' . $inserted . ', updated ' . $updated . ', skipped ' . $skipped . '.';
    if ($triggerMsgs) {
        $msg .= ' Machine pulls: ' . implode(' | ', $triggerMsgs);
    }
    return [
        'ok' => true,
        'inserted' => $inserted,
        'updated' => $updated,
        'skipped' => $skipped,
        'message' => $msg,
    ];
}

/**
 * Sync one machine: trigger Ocean pull for that machine, then import Ocean attendance filtered to it.
 */
function biometricSyncMachine($machineId, $fromDate = '', $toDate = '')
{
    $machineId = (int) $machineId;
    $m = getBiometricMachineById($machineId);
    if (!$m) {
        return ['ok' => false, 'inserted' => 0, 'updated' => 0, 'skipped' => 0, 'message' => 'Machine not found'];
    }
    return biometricSyncFromOcean($fromDate, $toDate, $machineId);
}

function biometricProviderOptions()
{
    return [
        'old_crm' => 'Old CRM API',
        'etimeoffice' => 'EASyBio / eTimeOffice',
        'custom' => 'Custom API',
    ];
}

function biometricProviderLabel($type)
{
    $opts = biometricProviderOptions();
    $type = strtolower(trim((string) $type));
    return $opts[$type] ?? ucfirst(str_replace('_', ' ', $type));
}

function biometricSlugCode($name)
{
    $code = strtoupper(preg_replace('/[^A-Za-z0-9]+/', '_', trim((string) $name)));
    $code = trim($code, '_');
    if ($code === '') {
        $code = 'MACHINE_' . date('His');
    }
    if (strlen($code) > 45) {
        $code = substr($code, 0, 45);
    }
    return $code;
}

/**
 * @return array{ok:bool,id:int,message:string}
 */
function saveBiometricMachine(array $data, $id = 0)
{
    $id = (int) $id;
    $name = trim((string) ($data['machine_name'] ?? ''));
    $code = trim((string) ($data['machine_code'] ?? ''));
    $provider = strtolower(trim((string) ($data['provider_type'] ?? 'old_crm')));
    $apiUrl = trim((string) ($data['api_url'] ?? ''));
    $authType = trim((string) ($data['auth_type'] ?? 'basic'));
    $username = trim((string) ($data['api_username'] ?? ''));
    $password = (string) ($data['api_password'] ?? '');
    $keepPassword = !empty($data['keep_password']);
    $corporate = trim((string) ($data['corporate_id'] ?? ''));
    $ip = trim((string) ($data['ip_address'] ?? ''));
    $port = trim((string) ($data['port'] ?? ''));
    $oceanId = (int) ($data['ocean_machine_id'] ?? 0);
    $interval = (int) ($data['sync_interval_minutes'] ?? 60);
    $isActive = !empty($data['is_active']) ? 1 : 0;
    $remarks = trim((string) ($data['remarks'] ?? ''));

    if ($name === '') {
        return ['ok' => false, 'id' => $id, 'message' => 'Machine name is required.'];
    }
    if ($apiUrl === '') {
        return ['ok' => false, 'id' => $id, 'message' => 'API URL is required.'];
    }
    $allowed = array_keys(biometricProviderOptions());
    if (!in_array($provider, $allowed, true)) {
        $provider = 'custom';
    }
    if ($authType === '') {
        $authType = 'basic';
    }
    if ($interval < 1) {
        $interval = 5;
    }
    if ($interval > 1440) {
        $interval = 1440;
    }
    if ($code === '') {
        $code = biometricSlugCode($name);
    }
    $code = strtoupper(preg_replace('/\s+/', '_', $code));

    $conn = getDBConnection();
    ensureBiometricTables($conn);

    $dup = $conn->prepare('SELECT id FROM biometric_machines WHERE machine_code = ? AND id <> ? LIMIT 1');
    $dup->bind_param('si', $code, $id);
    $dup->execute();
    if ($dup->get_result()->fetch_assoc()) {
        $dup->close();
        $conn->close();
        return ['ok' => false, 'id' => $id, 'message' => 'Machine code already exists. Use a unique code.'];
    }
    $dup->close();

    if ($id > 0) {
        $existing = null;
        $g = $conn->prepare('SELECT api_password FROM biometric_machines WHERE id = ? LIMIT 1');
        $g->bind_param('i', $id);
        $g->execute();
        $existing = $g->get_result()->fetch_assoc();
        $g->close();
        if (!$existing) {
            $conn->close();
            return ['ok' => false, 'id' => 0, 'message' => 'Machine not found.'];
        }
        if ($keepPassword || $password === '') {
            $password = (string) ($existing['api_password'] ?? '');
        }

        $st = $conn->prepare(
            "UPDATE biometric_machines SET
                machine_code=?, machine_name=?, provider_type=?, api_url=?, auth_type=?,
                api_username=?, api_password=?, corporate_id=?, ip_address=?, port=?,
                ocean_machine_id=NULLIF(?,0), sync_interval_minutes=?, is_active=?, remarks=?
             WHERE id=?"
        );
        $st->bind_param(
            'ssssssssssiiisi',
            $code,
            $name,
            $provider,
            $apiUrl,
            $authType,
            $username,
            $password,
            $corporate,
            $ip,
            $port,
            $oceanId,
            $interval,
            $isActive,
            $remarks,
            $id
        );
        $ok = $st->execute();
        $err = $st->error;
        $st->close();
        $conn->close();
        if (!$ok) {
            return ['ok' => false, 'id' => $id, 'message' => 'Update failed: ' . $err];
        }
        return ['ok' => true, 'id' => $id, 'message' => 'Machine updated successfully.'];
    }

    $st = $conn->prepare(
        "INSERT INTO biometric_machines
            (machine_code, machine_name, provider_type, api_url, auth_type,
             api_username, api_password, corporate_id, ip_address, port,
             ocean_machine_id, sync_interval_minutes, is_active, remarks)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NULLIF(?,0), ?, ?, ?)"
    );
    $st->bind_param(
        'ssssssssssiiis',
        $code,
        $name,
        $provider,
        $apiUrl,
        $authType,
        $username,
        $password,
        $corporate,
        $ip,
        $port,
        $oceanId,
        $interval,
        $isActive,
        $remarks
    );
    $ok = $st->execute();
    $newId = (int) $conn->insert_id;
    $err = $st->error;
    $st->close();
    $conn->close();
    if (!$ok) {
        return ['ok' => false, 'id' => 0, 'message' => 'Save failed: ' . $err];
    }
    return ['ok' => true, 'id' => $newId, 'message' => 'Machine added successfully.'];
}

function deleteBiometricMachine($id)
{
    $id = (int) $id;
    if ($id <= 0) {
        return ['ok' => false, 'message' => 'Invalid machine.'];
    }
    $conn = getDBConnection();
    ensureBiometricTables($conn);
    // Lock seed forever so deleted machine is never auto-recreated
    biometricMarkMachinesSeeded($conn);
    $st = $conn->prepare('DELETE FROM biometric_machines WHERE id = ?');
    $st->bind_param('i', $id);
    $ok = $st->execute();
    $affected = (int) $st->affected_rows;
    $st->close();
    // Keep logs; just unlink machine_id
    $conn->query('UPDATE machine_attendance_logs SET machine_id = NULL WHERE machine_id = ' . $id);
    $conn->close();
    if (!$ok || $affected < 1) {
        return ['ok' => false, 'message' => 'Delete failed or machine not found.'];
    }
    return ['ok' => true, 'message' => 'Machine deleted. Logs kept (unlinked).'];
}

function toggleBiometricMachine($id)
{
    $id = (int) $id;
    $conn = getDBConnection();
    ensureBiometricTables($conn);
    $conn->query('UPDATE biometric_machines SET is_active = IF(is_active=1,0,1) WHERE id = ' . $id);
    $st = $conn->prepare('SELECT is_active FROM biometric_machines WHERE id = ? LIMIT 1');
    $st->bind_param('i', $id);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    $st->close();
    $conn->close();
    $active = (int) ($row['is_active'] ?? 0) === 1;
    return [
        'ok' => true,
        'message' => $active ? 'Machine activated.' : 'Machine deactivated.',
        'is_active' => $active ? 1 : 0,
    ];
}

function biometricDashboardStats()
{
    $conn = getDBConnection();
    ensureBiometricTables($conn);
    $stats = [
        'machines_total' => 0,
        'machines_active' => 0,
        'logs_total' => 0,
        'logs_today' => 0,
        'logs_matched' => 0,
        'last_sync_at' => null,
    ];
    $r = $conn->query(
        "SELECT COUNT(*) AS t,
                SUM(CASE WHEN is_active=1 THEN 1 ELSE 0 END) AS a,
                MAX(last_sync_at) AS last_sync
         FROM biometric_machines"
    );
    if ($r && ($row = $r->fetch_assoc())) {
        $stats['machines_total'] = (int) $row['t'];
        $stats['machines_active'] = (int) $row['a'];
        $stats['last_sync_at'] = $row['last_sync'];
    }
    $r2 = $conn->query(
        "SELECT COUNT(*) AS t,
                SUM(CASE WHEN attendance_date = CURDATE() THEN 1 ELSE 0 END) AS today,
                SUM(CASE WHEN employee_id IS NOT NULL AND employee_id > 0 THEN 1 ELSE 0 END) AS matched
         FROM machine_attendance_logs"
    );
    if ($r2 && ($row = $r2->fetch_assoc())) {
        $stats['logs_total'] = (int) $row['t'];
        $stats['logs_today'] = (int) $row['today'];
        $stats['logs_matched'] = (int) $row['matched'];
    }
    $conn->close();
    return $stats;
}

function biometricLogCountByMachine()
{
    $conn = getDBConnection();
    ensureBiometricTables($conn);
    $map = [];
    $res = $conn->query(
        "SELECT machine_id, COUNT(*) AS c
         FROM machine_attendance_logs
         WHERE machine_id IS NOT NULL
         GROUP BY machine_id"
    );
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $map[(int) $r['machine_id']] = (int) $r['c'];
        }
    }
    $conn->close();
    return $map;
}

/**
 * Link machine punch code(s) to a local employee.
 * Writes ONLY to machine_attendance_logs + biometric_employee_map.
 * Does NOT touch attendance_punches / attendance_day_status / manual attendance.
 *
 * @return array{ok:bool,updated:int,message:string}
 */
function linkMachineLogsToEmployee($employeeId, $biometricCode = '', $logId = 0, $applySameCode = true)
{
    $employeeId = (int) $employeeId;
    $logId = (int) $logId;
    $biometricCode = trim((string) $biometricCode);
    $applySameCode = (bool) $applySameCode;

    if ($employeeId <= 0) {
        return ['ok' => false, 'updated' => 0, 'message' => 'Select an employee.'];
    }

    $conn = getDBConnection();
    ensureBiometricTables($conn);

    $est = $conn->prepare(
        "SELECT id, employee_code, employee_name, biometric_user_id
         FROM employees WHERE id = ? LIMIT 1"
    );
    $est->bind_param('i', $employeeId);
    $est->execute();
    $emp = $est->get_result()->fetch_assoc();
    $est->close();
    if (!$emp) {
        $conn->close();
        return ['ok' => false, 'updated' => 0, 'message' => 'Employee not found.'];
    }

    $codes = [];
    if ($logId > 0) {
        $lst = $conn->prepare('SELECT id, employee_code, biometric_user_id FROM machine_attendance_logs WHERE id = ? LIMIT 1');
        $lst->bind_param('i', $logId);
        $lst->execute();
        $log = $lst->get_result()->fetch_assoc();
        $lst->close();
        if (!$log) {
            $conn->close();
            return ['ok' => false, 'updated' => 0, 'message' => 'Punch log not found.'];
        }
        foreach ([(string) ($log['biometric_user_id'] ?? ''), (string) ($log['employee_code'] ?? '')] as $c) {
            $c = trim($c);
            if ($c !== '') {
                $codes[$c] = true;
            }
        }
    }
    if ($biometricCode !== '') {
        $codes[$biometricCode] = true;
    }
    $codeList = array_keys($codes);
    if (!$codeList) {
        $conn->close();
        return ['ok' => false, 'updated' => 0, 'message' => 'No biometric / machine code to link.'];
    }

    $empCode = (string) ($emp['employee_code'] ?? '');
    $empName = (string) ($emp['employee_name'] ?? '');
    $updated = 0;

    foreach ($codeList as $code) {
        $mst = $conn->prepare(
            "INSERT INTO biometric_employee_map (biometric_code, employee_id, employee_code, notes)
             VALUES (?, ?, ?, 'manual link')
             ON DUPLICATE KEY UPDATE
                employee_id = VALUES(employee_id),
                employee_code = VALUES(employee_code),
                notes = VALUES(notes)"
        );
        $mst->bind_param('sis', $code, $employeeId, $empCode);
        $mst->execute();
        $mst->close();

        if ($applySameCode || $logId <= 0) {
            $ust = $conn->prepare(
                "UPDATE machine_attendance_logs
                 SET employee_id = ?, employee_name = ?
                 WHERE UPPER(TRIM(employee_code)) = UPPER(TRIM(?))
                    OR UPPER(TRIM(biometric_user_id)) = UPPER(TRIM(?))"
            );
            $ust->bind_param('isss', $employeeId, $empName, $code, $code);
            $ust->execute();
            $updated += (int) $ust->affected_rows;
            $ust->close();
        } else {
            $ust = $conn->prepare(
                "UPDATE machine_attendance_logs
                 SET employee_id = ?, employee_name = ?
                 WHERE id = ?"
            );
            $ust->bind_param('isi', $employeeId, $empName, $logId);
            $ust->execute();
            $updated += (int) $ust->affected_rows;
            $ust->close();
        }
    }

    // Optionally set employees.biometric_user_id if empty (helps future match; does not affect attendance tables)
    $primary = $codeList[0];
    $bst = $conn->prepare(
        "UPDATE employees
         SET biometric_user_id = ?
         WHERE id = ? AND (biometric_user_id IS NULL OR TRIM(biometric_user_id) = '')"
    );
    $bst->bind_param('si', $primary, $employeeId);
    $bst->execute();
    $bst->close();

    $conn->close();
    return [
        'ok' => true,
        'updated' => $updated,
        'message' => 'Linked to ' . $empCode . ' — ' . $empName . '. Updated ' . $updated . ' machine punch(es). Attendance Report / Manual not changed.',
    ];
}

function unlinkMachineLogEmployee($logId, $clearMap = false)
{
    $logId = (int) $logId;
    $conn = getDBConnection();
    ensureBiometricTables($conn);
    $lst = $conn->prepare('SELECT employee_code, biometric_user_id FROM machine_attendance_logs WHERE id = ? LIMIT 1');
    $lst->bind_param('i', $logId);
    $lst->execute();
    $log = $lst->get_result()->fetch_assoc();
    $lst->close();
    if (!$log) {
        $conn->close();
        return ['ok' => false, 'message' => 'Log not found.'];
    }
    $ust = $conn->prepare('UPDATE machine_attendance_logs SET employee_id = NULL WHERE id = ?');
    $ust->bind_param('i', $logId);
    $ust->execute();
    $ust->close();
    if ($clearMap) {
        foreach ([(string) $log['biometric_user_id'], (string) $log['employee_code']] as $code) {
            $code = trim($code);
            if ($code === '') {
                continue;
            }
            $dst = $conn->prepare('DELETE FROM biometric_employee_map WHERE UPPER(TRIM(biometric_code)) = UPPER(TRIM(?))');
            $dst->bind_param('s', $code);
            $dst->execute();
            $dst->close();
        }
    }
    $conn->close();
    return ['ok' => true, 'message' => 'Employee unlinked from this punch.'];
}

/**
 * Re-apply map + code match to unmatched machine logs only.
 * Never writes to attendance_punches / day_status.
 */
function autoLinkMachineLogs($limit = 50000)
{
    $conn = getDBConnection();
    ensureBiometricTables($conn);
    $limit = max(100, (int) $limit);
    $res = $conn->query(
        "SELECT id, employee_code, biometric_user_id
         FROM machine_attendance_logs
         WHERE employee_id IS NULL OR employee_id = 0
         ORDER BY id DESC
         LIMIT " . $limit
    );
    $updated = 0;
    $checked = 0;
    while ($row = $res->fetch_assoc()) {
        $checked++;
        $eid = biometricResolveLocalEmployeeId(
            $conn,
            (string) ($row['employee_code'] ?? ''),
            (string) ($row['biometric_user_id'] ?? '')
        );
        if ($eid <= 0) {
            continue;
        }
        $empName = '';
        $nst = $conn->prepare('SELECT employee_name FROM employees WHERE id = ? LIMIT 1');
        $nst->bind_param('i', $eid);
        $nst->execute();
        $er = $nst->get_result()->fetch_assoc();
        $nst->close();
        $empName = (string) ($er['employee_name'] ?? '');
        $id = (int) $row['id'];
        $ust = $conn->prepare('UPDATE machine_attendance_logs SET employee_id = ?, employee_name = ? WHERE id = ?');
        $ust->bind_param('isi', $eid, $empName, $id);
        $ust->execute();
        if ($ust->affected_rows > 0) {
            $updated++;
        }
        $ust->close();
    }
    $conn->close();
    return [
        'ok' => true,
        'updated' => $updated,
        'checked' => $checked,
        'message' => 'Auto-link done. Checked ' . $checked . ', linked ' . $updated . '. (Machine logs only — Attendance Report untouched.)',
    ];
}

function fetchEmployeesForMachineLink()
{
    $conn = getDBConnection();
    $rows = [];
    $res = $conn->query(
        "SELECT e.id, e.employee_code, e.employee_name, e.biometric_user_id, e.department_id,
                d.department_name
         FROM employees e
         LEFT JOIN departments d ON d.id = e.department_id
         WHERE e.status = 1
         ORDER BY e.employee_code ASC, e.employee_name ASC"
    );
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $rows[] = $r;
        }
    }
    $conn->close();
    return $rows;
}

function biometricFormatPunchTime($time)
{
    $time = trim((string) $time);
    if ($time === '') {
        return '';
    }
    $ts = strtotime($time);
    return $ts ? date('g:i A', $ts) : substr($time, 0, 8);
}

/**
 * Build machine-wise month grid (SEPARATE from attendance report data).
 * Source: machine_attendance_logs only.
 * Shows linked employees AND unlinked machine codes (so data is visible without link).
 */
function getMachineWiseMonthGrid($month, $year, $machineId = 0, $deptId = 0, $employeeId = 0, $conn = null)
{
    $closeAfter = false;
    if ($conn === null) {
        $conn = getDBConnection();
        $closeAfter = true;
    }
    ensureBiometricTables($conn);
    $month = (int) $month;
    $year = (int) $year;
    $machineId = (int) $machineId;
    $deptId = (int) $deptId;
    $employeeId = (int) $employeeId;
    if ($month < 1 || $month > 12) {
        $month = (int) date('n');
    }
    $monthDays = (int) date('t', mktime(0, 0, 0, $month, 1, $year));
    $from = sprintf('%04d-%02d-01', $year, $month);
    $to = sprintf('%04d-%02d-%02d', $year, $month, $monthDays);

    $where = 'l.attendance_date BETWEEN ? AND ?';
    $types = 'ss';
    $params = [$from, $to];
    if ($machineId > 0) {
        $where .= ' AND l.machine_id = ?';
        $types .= 'i';
        $params[] = $machineId;
    }
    if ($employeeId > 0) {
        $where .= ' AND l.employee_id = ?';
        $types .= 'i';
        $params[] = $employeeId;
    }
    if ($deptId > 0) {
        $where .= ' AND e.department_id = ?';
        $types .= 'i';
        $params[] = $deptId;
    }

    // Pull all punches in range (left join employee for dept filter / names)
    $sql = "SELECT l.employee_id, l.employee_code, l.employee_name, l.biometric_user_id,
                   l.attendance_date, l.punch_time, l.punch_type, l.machine_id,
                   e.employee_code AS local_code, e.employee_name AS local_name,
                   e.designation, e.date_of_joining, e.department_id, d.department_name
            FROM machine_attendance_logs l
            LEFT JOIN employees e ON e.id = l.employee_id
            LEFT JOIN departments d ON d.id = e.department_id
            WHERE {$where}
            ORDER BY COALESCE(e.employee_code, l.employee_code) ASC,
                     l.attendance_date ASC, l.punch_time ASC, l.id ASC";
    $st = $conn->prepare($sql);
    $st->bind_param($types, ...$params);
    $st->execute();
    $res = $st->get_result();

    $employees = []; // key => emp row
    $punchMap = [];  // key => date => punches

    while ($r = $res->fetch_assoc()) {
        $eid = (int) ($r['employee_id'] ?? 0);
        $code = trim((string) ($r['employee_code'] ?? ''));
        if ($code === '') {
            $code = trim((string) ($r['biometric_user_id'] ?? ''));
        }
        if ($eid > 0) {
            $key = 'E' . $eid;
        } elseif ($code !== '') {
            $key = 'C' . strtoupper($code);
        } else {
            continue;
        }

        if (!isset($employees[$key])) {
            $linked = $eid > 0;
            $employees[$key] = [
                'id' => $eid,
                'row_key' => $key,
                'employee_code' => $linked
                    ? (string) ($r['local_code'] ?: $code)
                    : $code,
                'employee_name' => $linked
                    ? (string) ($r['local_name'] ?: ($r['employee_name'] ?? ''))
                    : (string) ($r['employee_name'] ?: 'Not linked'),
                'designation' => $linked ? (string) ($r['designation'] ?? '') : '',
                'date_of_joining' => $linked ? ($r['date_of_joining'] ?? '') : '',
                'department_id' => $linked ? (int) ($r['department_id'] ?? 0) : 0,
                'department_name' => $linked
                    ? (string) ($r['department_name'] ?? '')
                    : 'Machine code (unlinked)',
                'is_linked' => $linked ? 1 : 0,
                'machine_code' => $code,
            ];
        }

        $date = $r['attendance_date'];
        $punchMap[$key][$date][] = [
            'punch_time' => $r['punch_time'],
            'punch_type' => $r['punch_type'],
            'machine_id' => $r['machine_id'],
        ];
    }
    $st->close();

    $dayMap = [];
    $totals = [];
    foreach ($employees as $key => $emp) {
        $totals[$key] = [
            'present' => 0,
            'punch_days' => 0,
            'in_count' => 0,
            'out_count' => 0,
            'total_punches' => 0,
        ];
        for ($d = 1; $d <= $monthDays; $d++) {
            $date = sprintf('%04d-%02d-%02d', $year, $month, $d);
            $punches = $punchMap[$key][$date] ?? [];
            if (!$punches) {
                $dayMap[$key][$date] = null;
                continue;
            }
            $ins = 0;
            $outs = 0;
            foreach ($punches as $p) {
                $pt = strtolower((string) ($p['punch_type'] ?? 'in'));
                if ($pt === 'out') {
                    $outs++;
                } else {
                    $ins++;
                }
            }
            $dayMap[$key][$date] = [
                'punches' => $punches,
                'in_count' => $ins,
                'out_count' => $outs,
                'has_data' => true,
            ];
            $totals[$key]['punch_days']++;
            $totals[$key]['present']++;
            $totals[$key]['in_count'] += $ins;
            $totals[$key]['out_count'] += $outs;
            $totals[$key]['total_punches'] += count($punches);
        }
    }

    if ($closeAfter) {
        $conn->close();
    }

    return [
        'employees' => array_values($employees),
        'days' => $dayMap,
        'totals' => $totals,
        'month_days' => $monthDays,
        'from' => $from,
        'to' => $to,
        'month' => $month,
        'year' => $year,
        'machine_id' => $machineId,
    ];
}

/**
 * Day-wise punches for one machine employee code (Ocean-style O/X multi-punch sheet).
 */
function getMachineWiseDaySheet($codeOrEmpId, $fromDate, $toDate, $machineId = 0, $conn = null)
{
    $closeAfter = false;
    if ($conn === null) {
        $conn = getDBConnection();
        $closeAfter = true;
    }
    ensureBiometricTables($conn);
    $machineId = (int) $machineId;
    $fromDate = trim((string) $fromDate);
    $toDate = trim((string) $toDate);
    if ($fromDate === '' || $toDate === '') {
        $toDate = date('Y-m-d');
        $fromDate = date('Y-m-d', strtotime('-15 days'));
    }

    $where = 'attendance_date BETWEEN ? AND ?';
    $types = 'ss';
    $params = [$fromDate, $toDate];
    $empId = 0;
    $code = '';
    if (is_numeric($codeOrEmpId) && (int) $codeOrEmpId > 0 && strpos((string) $codeOrEmpId, 'C') !== 0) {
        // Could be employee id — try both
        $empId = (int) $codeOrEmpId;
    }
    $raw = trim((string) $codeOrEmpId);
    if (stripos($raw, 'E') === 0 && is_numeric(substr($raw, 1))) {
        $empId = (int) substr($raw, 1);
    } elseif (stripos($raw, 'C') === 0) {
        $code = substr($raw, 1);
    } elseif ($empId <= 0) {
        $code = $raw;
    }

    if ($empId > 0) {
        $where .= ' AND employee_id = ?';
        $types .= 'i';
        $params[] = $empId;
    } elseif ($code !== '') {
        $where .= ' AND (UPPER(TRIM(employee_code)) = UPPER(TRIM(?)) OR UPPER(TRIM(biometric_user_id)) = UPPER(TRIM(?)))';
        $types .= 'ss';
        $params[] = $code;
        $params[] = $code;
    } else {
        if ($closeAfter) {
            $conn->close();
        }
        return ['meta' => [], 'days' => [], 'from' => $fromDate, 'to' => $toDate];
    }
    if ($machineId > 0) {
        $where .= ' AND machine_id = ?';
        $types .= 'i';
        $params[] = $machineId;
    }

    $st = $conn->prepare(
        "SELECT attendance_date, punch_time, punch_type, employee_code, employee_name, employee_id, biometric_user_id
         FROM machine_attendance_logs
         WHERE {$where}
         ORDER BY attendance_date ASC, punch_time ASC, id ASC"
    );
    $st->bind_param($types, ...$params);
    $st->execute();
    $res = $st->get_result();
    $byDate = [];
    $meta = ['employee_code' => $code, 'employee_name' => '', 'employee_id' => $empId];
    while ($r = $res->fetch_assoc()) {
        if ($meta['employee_code'] === '' || $meta['employee_code'] === null) {
            $meta['employee_code'] = (string) ($r['employee_code'] ?: $r['biometric_user_id']);
        }
        if ($meta['employee_name'] === '' && !empty($r['employee_name'])) {
            $meta['employee_name'] = (string) $r['employee_name'];
        }
        if ($meta['employee_id'] <= 0 && !empty($r['employee_id'])) {
            $meta['employee_id'] = (int) $r['employee_id'];
        }
        $byDate[$r['attendance_date']][] = $r;
    }
    $st->close();

    if ($meta['employee_id'] > 0) {
        $es = $conn->prepare('SELECT employee_code, employee_name FROM employees WHERE id = ? LIMIT 1');
        $es->bind_param('i', $meta['employee_id']);
        $es->execute();
        $er = $es->get_result()->fetch_assoc();
        $es->close();
        if ($er) {
            $meta['employee_code'] = (string) $er['employee_code'];
            $meta['employee_name'] = (string) $er['employee_name'];
        }
    }

    $days = [];
    $ts = strtotime($fromDate);
    $te = strtotime($toDate);
    for ($t = $ts; $t <= $te; $t += 86400) {
        $date = date('Y-m-d', $t);
        $punches = $byDate[$date] ?? [];
        $times = [];
        foreach ($punches as $p) {
            $times[] = [
                'time' => substr((string) $p['punch_time'], 0, 5),
                'type' => strtolower((string) ($p['punch_type'] ?? 'in')) === 'out' ? 'out' : 'in',
            ];
        }
        $first = $times[0]['time'] ?? '';
        $last = count($times) > 1 ? $times[count($times) - 1]['time'] : '';
        $worked = '00:00';
        if ($first !== '' && $last !== '' && count($times) >= 2) {
            $mins = (int) ((strtotime($date . ' ' . $last) - strtotime($date . ' ' . $first)) / 60);
            if ($mins < 0) {
                $mins = 0;
            }
            $worked = sprintf('%02d:%02d', intdiv($mins, 60), $mins % 60);
        }
        $days[] = [
            'date' => $date,
            'status' => $punches ? 'O' : 'X',
            'punches' => $times,
            'first' => $first !== '' ? $first : '--:--',
            'last' => $last !== '' ? $last : '--:--',
            'worked' => $worked,
            'count' => count($times),
        ];
    }

    // Max punch slots for table columns
    $maxSlots = 2;
    foreach ($days as $d) {
        $maxSlots = max($maxSlots, (int) $d['count']);
    }
    if ($maxSlots > 12) {
        $maxSlots = 12;
    }

    if ($closeAfter) {
        $conn->close();
    }

    return [
        'meta' => $meta,
        'days' => $days,
        'from' => $fromDate,
        'to' => $toDate,
        'max_slots' => $maxSlots,
    ];
}

function machineWiseDayCellText(array $day = null)
{
    if (!$day || empty($day['punches'])) {
        return '';
    }
    $lines = [];
    $pair = [];
    foreach ($day['punches'] as $p) {
        $t = biometricFormatPunchTime($p['punch_time'] ?? '');
        if ($t === '') {
            continue;
        }
        $pt = strtolower((string) ($p['punch_type'] ?? 'in')) === 'out' ? 'out' : 'in';
        if ($pt === 'in') {
            if ($pair) {
                $lines[] = implode(' | ', $pair);
                $pair = [];
            }
            $pair[] = $t;
        } else {
            if (!$pair) {
                $pair[] = '—';
            }
            $pair[] = $t;
            $lines[] = implode(' | ', $pair);
            $pair = [];
        }
    }
    if ($pair) {
        $lines[] = implode(' | ', $pair) . (count($pair) === 1 ? ' | —' : '');
    }
    return implode("\n", $lines);
}

function machineWiseRenderMonthTableHtml(array $grid)
{
    $monthDays = (int) ($grid['month_days'] ?? 0);
    $month = (int) ($grid['month'] ?? date('n'));
    $year = (int) ($grid['year'] ?? date('Y'));
    $employees = $grid['employees'] ?? [];
    $dayMap = $grid['days'] ?? [];
    $totals = $grid['totals'] ?? [];

    $dayNames = [];
    for ($d = 1; $d <= $monthDays; $d++) {
        $dayNames[$d] = date('D', mktime(0, 0, 0, $month, $d, $year));
    }

    $html = '<table class="data-table excel-att-table machine-att-table" style="width:100%;border-collapse:collapse;">';
    $html .= '<thead><tr>';
    $html .= '<th>Employee Code</th><th>Employee Name</th><th>Designation</th><th>Department</th><th>Date of Joining</th>';
    for ($d = 1; $d <= $monthDays; $d++) {
        $html .= '<th style="text-align:center;">' . $d . '<br><span style="font-weight:500;font-size:10px;opacity:0.9;">'
            . htmlspecialchars($dayNames[$d]) . '</span></th>';
    }
    $html .= '<th style="text-align:center;">Punch Days</th>';
    $html .= '<th style="text-align:center;">IN</th>';
    $html .= '<th style="text-align:center;">OUT</th>';
    $html .= '<th style="text-align:center;">Total Punches</th>';
    $html .= '</tr></thead><tbody>';

    if (!$employees) {
        $cols = 5 + $monthDays + 4;
        $html .= '<tr><td colspan="' . $cols . '">No machine punches for this month/filter. Run Sync first.</td></tr>';
    }

    foreach ($employees as $emp) {
        $key = (string) ($emp['row_key'] ?? ('E' . (int) ($emp['id'] ?? 0)));
        $tot = $totals[$key] ?? ['punch_days' => 0, 'in_count' => 0, 'out_count' => 0, 'total_punches' => 0];
        $linked = !empty($emp['is_linked']);
        $html .= '<tr>';
        $codeCell = htmlspecialchars((string) ($emp['employee_code'] ?? ''));
        $dayUrl = app_url('attendance/machine_day.php?code=' . rawurlencode((string) ($emp['machine_code'] ?? $emp['employee_code'] ?? ''))
            . '&from_date=' . rawurlencode((string) ($grid['from'] ?? ''))
            . '&to_date=' . rawurlencode((string) ($grid['to'] ?? '')));
        $html .= '<td><a href="' . htmlspecialchars($dayUrl) . '">' . $codeCell . '</a>'
            . ($linked ? '' : ' <span class="bio-unmatched">unlinked</span>') . '</td>';
        $html .= '<td>' . htmlspecialchars((string) ($emp['employee_name'] ?? '')) . '</td>';
        $html .= '<td>' . htmlspecialchars((string) ($emp['designation'] ?? '')) . '</td>';
        $html .= '<td>' . htmlspecialchars((string) ($emp['department_name'] ?? '')) . '</td>';
        $doj = '';
        if (function_exists('formatDateDisplay')) {
            $doj = formatDateDisplay($emp['date_of_joining'] ?? '');
        } elseif (!empty($emp['date_of_joining'])) {
            $ts = strtotime((string) $emp['date_of_joining']);
            $doj = $ts ? date('d-m-Y', $ts) : '';
        }
        $html .= '<td>' . htmlspecialchars($doj) . '</td>';

        for ($d = 1; $d <= $monthDays; $d++) {
            $date = sprintf('%04d-%02d-%02d', $year, $month, $d);
            $day = $dayMap[$key][$date] ?? null;
            $text = machineWiseDayCellText($day);
            $has = $text !== '';
            $cls = 'att-day' . ($has ? ' is-present machine-has-punch' : '');
            $html .= '<td class="' . $cls . '" style="text-align:center;vertical-align:middle;font-size:11px;">';
            if ($has) {
                $lines = preg_split("/\r\n|\n|\r/", $text);
                foreach ($lines as $i => $line) {
                    $line = trim((string) $line);
                    if ($line === '') {
                        continue;
                    }
                    if ($i === 0) {
                        $html .= '<strong class="att-cell-status">Present</strong>';
                    }
                    $html .= '<span class="att-cell-time">' . htmlspecialchars($line) . '</span>';
                }
                $pc = count($day['punches'] ?? []);
                if ($pc > 2) {
                    $html .= '<span class="machine-punch-count">' . $pc . ' punches</span>';
                }
            }
            $html .= '</td>';
        }

        $html .= '<td style="text-align:center;">' . (int) ($tot['punch_days'] ?? 0) . '</td>';
        $html .= '<td style="text-align:center;">' . (int) ($tot['in_count'] ?? 0) . '</td>';
        $html .= '<td style="text-align:center;">' . (int) ($tot['out_count'] ?? 0) . '</td>';
        $html .= '<td style="text-align:center;">' . (int) ($tot['total_punches'] ?? 0) . '</td>';
        $html .= '</tr>';
    }

    $html .= '</tbody></table>';
    return $html;
}
