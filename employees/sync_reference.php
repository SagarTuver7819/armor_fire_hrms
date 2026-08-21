<?php
/**
 * Sync / import All Employees from reference Ocean HRMS
 * Admin only — pulls Employees + Employment + Salary Details
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/employee_helper.php';
require_once __DIR__ . '/../includes/attendance_helper.php';
require_once __DIR__ . '/../includes/payroll_helper.php';

requireAdmin();

$pageTitle = 'Sync Employees from Reference HRMS';
$useSidebar = true;
$sidebarActive = 'all_employees';

$log = [];
$ok = true;
$defaults = [
    'base_url' => 'https://hrms.oceaninfotechcrm.com/software',
    'app_key' => 'Armor@2025',
    'username' => 'Armor@Admin',
    'password' => '',
];

function refHttpRequest($url, $cookieFile, $postFields = null, $headers = [])
{
    $ch = curl_init($url);
    $defaultHeaders = [
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        'Accept-Language: en-US,en;q=0.9',
    ];
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_COOKIEJAR => $cookieFile,
        CURLOPT_COOKIEFILE => $cookieFile,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => 180,
        CURLOPT_HTTPHEADER => array_merge($defaultHeaders, $headers),
    ]);
    if ($postFields !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
    }
    $body = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $finalUrl = (string) curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    $err = curl_error($ch);
    curl_close($ch);
    if ($body === false) {
        throw new RuntimeException('cURL error: ' . $err);
    }
    return [$code, $body, $finalUrl];
}

function refFetchDataTable($base, $path, $cookieFile, $csrfToken = '')
{
    $all = [];
    $start = 0;
    $total = null;
    $headers = [
        'X-Requested-With: XMLHttpRequest',
        'Accept: application/json, text/javascript, */*; q=0.01',
        'Referer: ' . rtrim($base, '/') . '/' . ltrim($path, '/'),
    ];
    if ($csrfToken !== '') {
        $headers[] = 'X-CSRF-TOKEN: ' . $csrfToken;
    }
    while (true) {
        $url = rtrim($base, '/') . '/' . ltrim($path, '/') . '?draw=1&start=' . $start . '&length=100';
        [$code, $body] = refHttpRequest($url, $cookieFile, null, $headers);
        if ($code >= 400) {
            throw new RuntimeException("Failed {$path}: HTTP {$code}");
        }
        $json = json_decode($body, true);
        if (!is_array($json) || !isset($json['data'])) {
            $snippet = substr(trim(strip_tags($body)), 0, 120);
            throw new RuntimeException("Invalid JSON from {$path} (HTTP {$code}). Got: " . $snippet);
        }
        if ($total === null) {
            $total = (int) ($json['recordsTotal'] ?? 0);
        }
        $chunk = $json['data'];
        $all = array_merge($all, $chunk);
        $start += count($chunk);
        if (!$chunk || count($all) >= $total) {
            break;
        }
    }
    return $all;
}

function refParseDate($value)
{
    $value = trim(html_entity_decode((string) $value));
    if ($value === '' || $value === '-') {
        return null;
    }
    if (preg_match('/^(\d{2})-(\d{2})-(\d{4})$/', $value, $m)) {
        return sprintf('%04d-%02d-%02d', (int) $m[3], (int) $m[2], (int) $m[1]);
    }
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $value, $m)) {
        return sprintf('%04d-%02d-%02d', (int) $m[1], (int) $m[2], (int) $m[3]);
    }
    $ts = strtotime($value);
    return $ts ? date('Y-m-d', $ts) : null;
}

function refWeekOffDay($raw)
{
    $raw = html_entity_decode((string) $raw);
    $arr = json_decode($raw, true);
    if (!is_array($arr) || !$arr) {
        // sometimes double-encoded html entities already decoded
        if (preg_match('/"(Monday|Tuesday|Wednesday|Thursday|Friday|Saturday|Sunday)"/i', $raw, $m)) {
            return ucfirst(strtolower($m[1]));
        }
        return 'Sunday';
    }
    $first = (string) ($arr[0] ?? 'Sunday');
    return ucfirst(strtolower($first));
}

function refEnsureDepartment($conn, $name)
{
    $name = trim(html_entity_decode(strip_tags((string) $name)));
    if ($name === '') {
        $name = 'General';
    }
    $stmt = $conn->prepare('SELECT id FROM departments WHERE department_name = ? LIMIT 1');
    $stmt->bind_param('s', $name);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($row) {
        return (int) $row['id'];
    }
    $icon = 'fa-building';
    $color = '#3498DB';
    $ins = $conn->prepare('INSERT INTO departments (department_name, icon_class, icon_color, sort_order, status) VALUES (?, ?, ?, 100, 1)');
    $ins->bind_param('sss', $name, $icon, $color);
    $ins->execute();
    $id = (int) $conn->insert_id;
    $ins->close();
    return $id;
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
            throw new RuntimeException('PHP cURL extension is required.');
        }
        $cookieFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'armor_ref_sync_cookies.txt';
        @unlink($cookieFile);

        // Login page for cookies / CSRF if any
        [$code, $loginHtml] = refHttpRequest($base . '/login', $cookieFile);
        $token = '';
        if (preg_match('/name="_token"\s+value="([^"]+)"/', $loginHtml, $m)) {
            $token = $m[1];
        } elseif (preg_match('/csrf-token"\s+content="([^"]+)"/', $loginHtml, $m)) {
            $token = $m[1];
        }
        if ($token === '') {
            throw new RuntimeException('Could not read login CSRF token from reference site.');
        }

        $post = [
            '_token' => $token,
            'app_key' => $appKey,
            'username' => $username,
            'password' => $password,
        ];
        // Reference form posts to /login-submit (not /login)
        [$code2, $after, $finalUrl] = refHttpRequest($base . '/login-submit', $cookieFile, http_build_query($post), [
            'Content-Type: application/x-www-form-urlencoded',
            'Accept: text/html,application/xhtml+xml',
            'Origin: https://hrms.oceaninfotechcrm.com',
            'Referer: ' . $base . '/login',
        ]);
        if ($code2 === 405) {
            throw new RuntimeException('Login endpoint rejected POST (HTTP 405).');
        }
        if (stripos($after, 'Welcome to HRMS') !== false && stripos($after, 'Sign in') !== false) {
            throw new RuntimeException('Login failed. Check App Key / Username / Password.');
        }
        if (stripos($finalUrl, 'login') !== false && stripos($after, 'password') !== false && stripos($after, 'Dashboard') === false) {
            throw new RuntimeException('Login failed or session not created. Check credentials.');
        }
        $log[] = 'Logged into reference HRMS.';

        // Open employees page to refresh CSRF for AJAX
        [$codeEmp, $empHtml] = refHttpRequest($base . '/employees', $cookieFile, null, [
            'Accept: text/html',
            'Referer: ' . $base . '/dashboard',
        ]);
        $csrf = $token;
        if (preg_match('/csrf-token"\s+content="([^"]+)"/', $empHtml, $m)) {
            $csrf = $m[1];
        }

        $employees = refFetchDataTable($base, 'employees', $cookieFile, $csrf);
        $employment = refFetchDataTable($base, 'employment-details', $cookieFile, $csrf);
        $salary = refFetchDataTable($base, 'employee-wise-salary-details', $cookieFile, $csrf);
        $log[] = 'Fetched employees: ' . count($employees) . ', employment: ' . count($employment) . ', salary: ' . count($salary);

        $empById = [];
        foreach ($employment as $row) {
            $empById[(int) ($row['employee_id'] ?? 0)] = $row;
        }
        $salById = [];
        foreach ($salary as $row) {
            $salById[(int) ($row['employee_id'] ?? 0)] = $row;
        }

        $conn = getDBConnection();
        ensureEmployeesTable($conn);
        ensureAttendanceTables($conn);
        ensurePayrollTables($conn);

        $inserted = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($employees as $e) {
            $refId = (int) ($e['id'] ?? 0);
            $code = trim((string) ($e['username'] ?? $e['biometric_user_id'] ?? ''));
            if ($code === '' && !empty($e['employee_code']) && preg_match('/<strong>([^<]+)<\/strong>/', $e['employee_code'], $mm)) {
                $code = trim($mm[1]);
            }
            if ($code === '') {
                $skipped++;
                continue;
            }
            $name = trim((string) ($e['proper_name'] ?? ''));
            if ($name === '') {
                $name = trim(($e['first_name'] ?? '') . ' ' . ($e['middle_name'] ?? '') . ' ' . ($e['father_name'] ?? ''));
            }
            $bio = trim((string) ($e['biometric_user_id'] ?? $code));
            $father = trim((string) ($e['father_name'] ?? ''));
            $mobile = preg_replace('/\D+/', '', (string) ($e['contact_number'] ?? ''));
            $emergency = preg_replace('/\D+/', '', (string) ($e['other_number'] ?? ''));
            $aadhar = trim((string) ($e['aadhar_card_number'] ?? ''));
            $pan = trim((string) ($e['pan_card_number'] ?? ''));
            $dob = refParseDate($e['date_of_birth'] ?? '') ?: '';
            $perm = trim((string) ($e['permanent_address'] ?? ''));
            $pres = trim((string) ($e['current_address'] ?? ''));
            $bank = trim((string) ($e['bank_name'] ?? ''));
            $acc = trim((string) ($e['bank_account_number'] ?? ''));
            $ifsc = trim((string) ($e['ifsc_code'] ?? ''));
            $statusRaw = strtolower(strip_tags((string) ($e['status'] ?? 'active')));
            $status = (strpos($statusRaw, 'inactive') !== false || strpos($statusRaw, 'resign') !== false) ? 0 : 1;

            $em = $empById[$refId] ?? null;
            $deptName = $em['department']['name'] ?? 'General';
            $deptId = refEnsureDepartment($conn, $deptName);
            $designation = trim((string) ($em['designation']['name'] ?? ''));
            $doj = refParseDate($em['date_of_joining'] ?? '') ?: '';
            $uan = trim((string) ($em['uan_no'] ?? ''));
            $shiftType = 'Day';

            $sal = $salById[$refId] ?? null;
            $ctc = $sal ? (float) ($sal['ctc'] ?? 0) : 0.0;
            $weekOff = $sal ? refWeekOffDay($sal['week_off'] ?? '') : 'Sunday';
            $pfType = strtoupper((string) ($sal['pf_type'] ?? 'NO-PF'));
            $pfDeduction = (strpos($pfType, 'NO') === false && $pfType !== '') ? 'Yes' : 'No';

            // Find existing
            $find = $conn->prepare('SELECT id FROM employees WHERE employee_code = ? LIMIT 1');
            $find->bind_param('s', $code);
            $find->execute();
            $ex = $find->get_result()->fetch_assoc();
            $find->close();

            if ($ex) {
                $id = (int) $ex['id'];
                $sql = "UPDATE employees SET
                            biometric_user_id=?, department_id=?, employee_name=?, father_husband_name=?,
                            permanent_address=?, present_address=?, mobile_number=?, emergency_mobile=?,
                            aadhar_number=?, pan_number=?,
                            date_of_birth=NULLIF(?, ''), designation=?, date_of_joining=NULLIF(?, ''),
                            shift_type=?, pf_deduction=?, uan_number=?, bank_name=?, bank_account_number=?,
                            ifsc_code=?, decided_salary=?, week_off_day=?, status=?, pay_type='Salary'
                         WHERE id=?";
                $upd = $conn->prepare($sql);
                $upd->bind_param(
                    'sisssssssssssssssssdsii',
                    $bio,
                    $deptId,
                    $name,
                    $father,
                    $perm,
                    $pres,
                    $mobile,
                    $emergency,
                    $aadhar,
                    $pan,
                    $dob,
                    $designation,
                    $doj,
                    $shiftType,
                    $pfDeduction,
                    $uan,
                    $bank,
                    $acc,
                    $ifsc,
                    $ctc,
                    $weekOff,
                    $status,
                    $id
                );
                if (!$upd->execute()) {
                    throw new RuntimeException('Update failed for ' . $code . ': ' . $upd->error);
                }
                $upd->close();
                $updated++;
            } else {
                $sql = "INSERT INTO employees
                        (employee_code, biometric_user_id, pay_type, department_id, employee_name, father_husband_name,
                         permanent_address, present_address, mobile_number, emergency_mobile, aadhar_number, pan_number,
                         date_of_birth, designation, date_of_joining, shift_type, pf_deduction, uan_number,
                         bank_name, bank_account_number, ifsc_code, decided_salary, week_off_day, status)
                     VALUES (?, ?, 'Salary', ?, ?, ?, ?, ?, ?, ?, ?, ?, NULLIF(?, ''), ?, NULLIF(?, ''), ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $ins = $conn->prepare($sql);
                $ins->bind_param(
                    'ssisssssssssssssssssdsi',
                    $code,
                    $bio,
                    $deptId,
                    $name,
                    $father,
                    $perm,
                    $pres,
                    $mobile,
                    $emergency,
                    $aadhar,
                    $pan,
                    $dob,
                    $designation,
                    $doj,
                    $shiftType,
                    $pfDeduction,
                    $uan,
                    $bank,
                    $acc,
                    $ifsc,
                    $ctc,
                    $weekOff,
                    $status
                );
                if (!$ins->execute()) {
                    throw new RuntimeException('Insert failed for ' . $code . ': ' . $ins->error);
                }
                $ins->close();
                $inserted++;
            }
        }

        $conn->close();
        $log[] = "Done. Inserted: {$inserted}, Updated: {$updated}, Skipped: {$skipped}.";
        @unlink($cookieFile);
    } catch (Throwable $e) {
        $ok = false;
        $log[] = 'Error: ' . $e->getMessage();
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<main class="dashboard-main">
    <div class="page-toolbar">
        <a href="<?php echo app_url('employees/index.php'); ?>" class="back-link">
            <i class="fa-solid fa-arrow-left"></i> Back to Employees
        </a>
    </div>

    <div class="form-page-card">
        <div class="form-page-header">
            <h1><?php echo $ok && $log ? 'Employee Sync complete' : 'Sync Employees from Reference HRMS'; ?></h1>
            <p>Pulls All Employees + Employment + Salary CTC from Ocean reference and upserts by employee code.</p>
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
                        <input type="text" name="base_url" class="form-control" required
                               value="<?php echo htmlspecialchars($defaults['base_url']); ?>">
                    </div>
                    <div class="form-group">
                        <label>App Key</label>
                        <input type="text" name="app_key" class="form-control" required
                               value="<?php echo htmlspecialchars($defaults['app_key']); ?>">
                    </div>
                    <div class="form-group">
                        <label>Username</label>
                        <input type="text" name="username" class="form-control" required
                               value="<?php echo htmlspecialchars($defaults['username']); ?>">
                    </div>
                    <div class="form-group">
                        <label>Password</label>
                        <input type="password" name="password" class="form-control" required
                               placeholder="Enter reference password">
                    </div>
                </div>
            </div>
            <div class="form-actions sticky-actions">
                <button type="submit" class="btn-primary" onclick="return confirm('Sync all employees from reference site into this software?');">
                    <i class="fa-solid fa-rotate"></i> Start Sync Now
                </button>
                <a href="<?php echo app_url('employees/index.php'); ?>" class="btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
