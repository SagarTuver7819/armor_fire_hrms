<?php
/**
 * Canteen meal booking (QR) — employees book Breakfast / Lunch / Dinner for the next day.
 * Booking for a date closes on the previous day at CANTEEN_CUTOFF_TIME (default 21:00).
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';

function canteenMeals()
{
    return [
        'breakfast' => 'Breakfast',
        'lunch'     => 'Lunch',
        'dinner'    => 'Dinner',
    ];
}

function ensureCanteenTables($conn = null)
{
    static $ready = false;
    if ($ready) {
        return;
    }
    $closeAfter = false;
    if ($conn === null) {
        $conn = getDBConnection();
        $closeAfter = true;
    }
    $conn->query(
        "CREATE TABLE IF NOT EXISTS canteen_meal_orders (
            id INT AUTO_INCREMENT PRIMARY KEY,
            meal_date DATE NOT NULL,
            employee_id INT NOT NULL,
            employee_code VARCHAR(30) NOT NULL DEFAULT '',
            department_id INT NOT NULL DEFAULT 0,
            breakfast TINYINT(1) NOT NULL DEFAULT 0,
            lunch TINYINT(1) NOT NULL DEFAULT 0,
            dinner TINYINT(1) NOT NULL DEFAULT 0,
            ip_address VARCHAR(45) DEFAULT NULL,
            user_agent VARCHAR(255) DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_canteen_date_emp (meal_date, employee_id),
            INDEX idx_canteen_date_dept (meal_date, department_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    $conn->query(
        "CREATE TABLE IF NOT EXISTS canteen_guest_orders (
            id INT AUTO_INCREMENT PRIMARY KEY,
            meal_date DATE NOT NULL,
            booking_type ENUM('Guest','Trainee') NOT NULL DEFAULT 'Guest',
            department_id INT NOT NULL DEFAULT 0,
            host_employee_id INT NOT NULL DEFAULT 0,
            host_employee_code VARCHAR(30) NOT NULL DEFAULT '',
            person_count INT NOT NULL DEFAULT 1,
            person_names TEXT NULL,
            remarks VARCHAR(255) NOT NULL DEFAULT '',
            breakfast TINYINT(1) NOT NULL DEFAULT 0,
            lunch TINYINT(1) NOT NULL DEFAULT 0,
            dinner TINYINT(1) NOT NULL DEFAULT 0,
            ip_address VARCHAR(45) DEFAULT NULL,
            user_agent VARCHAR(255) DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_canteen_guest_date_dept (meal_date, department_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    $col = $conn->query("SHOW COLUMNS FROM employees LIKE 'canteen_use'");
    if ($col && $col->num_rows === 0) {
        $conn->query("ALTER TABLE employees ADD COLUMN canteen_use ENUM('Yes','No') NOT NULL DEFAULT 'No'");
    }
    $ready = true;
    if ($closeAfter) {
        $conn->close();
    }
}

/** HH:MM, configurable via .env CANTEEN_CUTOFF_TIME */
function canteenCutoffTime()
{
    $t = trim((string) (function_exists('env') ? env('CANTEEN_CUTOFF_TIME', '21:00') : '21:00'));
    return preg_match('/^([01]?\d|2[0-3]):[0-5]\d$/', $t) ? $t : '21:00';
}

function canteenCutoffLabel()
{
    return date('h:i A', strtotime('2000-01-01 ' . canteenCutoffTime() . ':00'));
}

/** Bookings are always for tomorrow */
function canteenTargetDate()
{
    return date('Y-m-d', strtotime('+1 day'));
}

/** Unix time when booking for $mealDate closes (previous day at cutoff) */
function canteenCutoffAt($mealDate)
{
    $prevDay = date('Y-m-d', strtotime($mealDate . ' -1 day'));
    return strtotime($prevDay . ' ' . canteenCutoffTime() . ':00');
}

function canteenIsOpen($mealDate = null)
{
    $mealDate = $mealDate ?: canteenTargetDate();
    return time() < canteenCutoffAt($mealDate);
}

function canteenDateLabel($ymd)
{
    $ts = strtotime((string) $ymd);
    return $ts ? date('d-m-Y (l)', $ts) : (string) $ymd;
}

/** Public URL encoded in the QR — live URL even when opened from localhost */
function canteenOrderUrl()
{
    $path = 'canteen/order.php';
    $override = trim((string) (function_exists('env') ? env('CANTEEN_PUBLIC_URL', '') : ''));
    if ($override !== '') {
        return rtrim($override, '/');
    }

    $liveBase = 'https://armor-hrms.oceanhub.co.in';
    $appUrl = defined('APP_URL') ? rtrim((string) APP_URL, '/') : '';
    $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
    $isLocal = (
        strpos($host, 'localhost') !== false
        || strpos($host, '127.0.0.1') !== false
        || $appUrl === ''
        || stripos($appUrl, 'localhost') !== false
        || stripos($appUrl, '127.0.0.1') !== false
    );
    if ($isLocal) {
        return $liveBase . '/' . $path;
    }
    return $appUrl . '/' . $path;
}

/**
 * Active departments that have at least one active employee.
 * $canteenOnly = only employees marked Canteen Use = Yes (employee self-booking).
 */
function canteenDepartments($conn, $canteenOnly = false)
{
    $rows = [];
    $extra = $canteenOnly ? " AND e.canteen_use = 'Yes'" : '';
    $res = $conn->query(
        "SELECT d.id, d.department_name, COUNT(e.id) AS emp_count
         FROM departments d
         INNER JOIN employees e ON e.department_id = d.id AND e.status = 1{$extra}
         WHERE d.status = 1
         GROUP BY d.id, d.department_name
         ORDER BY d.sort_order ASC, d.department_name ASC"
    );
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $rows[] = $r;
        }
    }
    return $rows;
}

function canteenDepartmentEmployees($conn, $deptId, $canteenOnly = false)
{
    $rows = [];
    $extra = $canteenOnly ? " AND canteen_use = 'Yes'" : '';
    $st = $conn->prepare(
        "SELECT id, employee_code, employee_name, designation
         FROM employees
         WHERE status = 1 AND department_id = ?{$extra}
         ORDER BY employee_name ASC"
    );
    $st->bind_param('i', $deptId);
    $st->execute();
    $res = $st->get_result();
    while ($r = $res->fetch_assoc()) {
        $rows[] = $r;
    }
    $st->close();
    return $rows;
}

function canteenActiveEmployee($conn, $empId)
{
    $st = $conn->prepare(
        "SELECT id, employee_code, employee_name, department_id, canteen_use
         FROM employees WHERE id = ? AND status = 1 LIMIT 1"
    );
    $st->bind_param('i', $empId);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    $st->close();
    return $row ?: null;
}

/** [employee_id => order row] for one department + date */
function canteenOrdersMap($conn, $mealDate, $deptId)
{
    $map = [];
    $st = $conn->prepare(
        "SELECT employee_id, breakfast, lunch, dinner, updated_at
         FROM canteen_meal_orders WHERE meal_date = ? AND department_id = ?"
    );
    $st->bind_param('si', $mealDate, $deptId);
    $st->execute();
    $res = $st->get_result();
    while ($r = $res->fetch_assoc()) {
        $map[(int) $r['employee_id']] = $r;
    }
    $st->close();
    return $map;
}

/**
 * Insert / update one booking. No meal ticked = remove existing booking.
 * @return string saved | cancelled | empty
 */
function canteenSaveOrder($conn, $mealDate, array $emp, $breakfast, $lunch, $dinner)
{
    $b = $breakfast ? 1 : 0;
    $l = $lunch ? 1 : 0;
    $d = $dinner ? 1 : 0;
    $empId = (int) $emp['id'];

    if ($b + $l + $d === 0) {
        $del = $conn->prepare('DELETE FROM canteen_meal_orders WHERE meal_date = ? AND employee_id = ?');
        $del->bind_param('si', $mealDate, $empId);
        $del->execute();
        $removed = $del->affected_rows > 0;
        $del->close();
        return $removed ? 'cancelled' : 'empty';
    }

    $code = (string) $emp['employee_code'];
    $deptId = (int) $emp['department_id'];
    $ip = substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
    $ua = substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);

    $st = $conn->prepare(
        "INSERT INTO canteen_meal_orders
            (meal_date, employee_id, employee_code, department_id, breakfast, lunch, dinner, ip_address, user_agent)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
            employee_code = VALUES(employee_code), department_id = VALUES(department_id),
            breakfast = VALUES(breakfast), lunch = VALUES(lunch), dinner = VALUES(dinner),
            ip_address = VALUES(ip_address), user_agent = VALUES(user_agent)"
    );
    $st->bind_param('sisiiiiss', $mealDate, $empId, $code, $deptId, $b, $l, $d, $ip, $ua);
    $st->execute();
    $st->close();
    return 'saved';
}

/**
 * Bookings for a date (optionally one department / allowed departments), ordered department → employee.
 * @param int[]|null $allowedDeptIds null = all
 */
function canteenOrdersForDate($conn, $mealDate, $deptId = 0, $allowedDeptIds = null)
{
    $sql = "SELECT o.employee_id, o.breakfast, o.lunch, o.dinner, o.updated_at,
                   COALESCE(e.employee_code, o.employee_code) AS employee_code,
                   e.employee_name, e.designation,
                   o.department_id, COALESCE(d.department_name, '—') AS department_name, COALESCE(d.sort_order, 0) AS dept_sort
            FROM canteen_meal_orders o
            LEFT JOIN employees e ON e.id = o.employee_id
            LEFT JOIN departments d ON d.id = o.department_id
            WHERE o.meal_date = ?";
    $types = 's';
    $params = [$mealDate];
    if ($deptId > 0) {
        $sql .= ' AND o.department_id = ?';
        $types .= 'i';
        $params[] = $deptId;
    } elseif (is_array($allowedDeptIds)) {
        $ids = array_values(array_filter(array_map('intval', $allowedDeptIds)));
        $sql .= $ids ? ' AND o.department_id IN (' . implode(',', $ids) . ')' : ' AND 1 = 0';
    }
    $sql .= ' ORDER BY d.sort_order ASC, d.department_name ASC, e.employee_name ASC';

    $rows = [];
    $st = $conn->prepare($sql);
    $st->bind_param($types, ...$params);
    $st->execute();
    $res = $st->get_result();
    while ($r = $res->fetch_assoc()) {
        $rows[] = $r;
    }
    $st->close();
    return $rows;
}

/**
 * Department-wise totals. Meal counts are plates: an employee = 1, a guest/trainee entry = person_count.
 * @param array $rows       canteenOrdersForDate() rows
 * @param array $guestRows  canteenGuestOrdersForDate() rows
 */
function canteenSummarize(array $rows, array $guestRows = [])
{
    $blank = ['employees' => 0, 'guests' => 0, 'breakfast' => 0, 'lunch' => 0, 'dinner' => 0];
    $byDept = [];
    $sortKey = [];
    $total = $blank;
    $add = static function ($r, $field, $qty) use (&$byDept, &$sortKey, &$total, $blank) {
        $dn = (string) $r['department_name'];
        if (!isset($byDept[$dn])) {
            $byDept[$dn] = $blank;
            $sortKey[$dn] = sprintf('%06d|%s', (int) ($r['dept_sort'] ?? 0), $dn);
        }
        $byDept[$dn][$field] += $qty;
        $total[$field] += $qty;
        foreach (['breakfast', 'lunch', 'dinner'] as $m) {
            if ((int) $r[$m] === 1) {
                $byDept[$dn][$m] += $qty;
                $total[$m] += $qty;
            }
        }
    };
    foreach ($rows as $r) {
        $add($r, 'employees', 1);
    }
    foreach ($guestRows as $g) {
        $add($g, 'guests', max(1, (int) $g['person_count']));
    }
    uksort($byDept, static function ($a, $b) use ($sortKey) {
        return strcmp($sortKey[$a], $sortKey[$b]);
    });
    return ['by_dept' => $byDept, 'total' => $total];
}

/** HR department (trainee bookings belong here). Override via .env CANTEEN_TRAINEE_DEPARTMENT_ID */
function canteenTraineeDepartment($conn)
{
    $forced = (int) (function_exists('env') ? env('CANTEEN_TRAINEE_DEPARTMENT_ID', 0) : 0);
    $sql = $forced > 0
        ? 'SELECT id, department_name FROM departments WHERE id = ' . $forced . ' LIMIT 1'
        : "SELECT id, department_name FROM departments
           WHERE status = 1 AND (UPPER(department_name) LIKE '%HUMAN RESOURCE%' OR UPPER(TRIM(department_name)) IN ('HR', 'H.R.', 'H R'))
           ORDER BY sort_order ASC, id ASC LIMIT 1";
    $res = $conn->query($sql);
    $row = $res ? $res->fetch_assoc() : null;
    return $row ?: null;
}

function canteenGuestTypes()
{
    return ['Guest' => 'Guest', 'Trainee' => 'Trainee'];
}

/** Split stored names (one per line) */
function canteenGuestNames($stored)
{
    return array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', (string) $stored))));
}

/**
 * New guest / trainee booking (each submit is a separate entry).
 * @return int new id (0 on failure)
 */
function canteenSaveGuestOrder($conn, $mealDate, $type, $deptId, array $host, $count, array $names, $remarks, $breakfast, $lunch, $dinner)
{
    $type = $type === 'Trainee' ? 'Trainee' : 'Guest';
    $b = $breakfast ? 1 : 0;
    $l = $lunch ? 1 : 0;
    $d = $dinner ? 1 : 0;
    $hostId = (int) $host['id'];
    $hostCode = (string) $host['employee_code'];
    $count = max(1, (int) $count);
    $nameText = implode("\n", array_map(static function ($n) {
        return mb_substr(trim((string) $n), 0, 80);
    }, $names));
    $remarks = mb_substr(trim((string) $remarks), 0, 255);
    $ip = substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
    $ua = substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);

    $st = $conn->prepare(
        'INSERT INTO canteen_guest_orders
            (meal_date, booking_type, department_id, host_employee_id, host_employee_code, person_count, person_names, remarks,
             breakfast, lunch, dinner, ip_address, user_agent)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $st->bind_param('ssiisissiiiss', $mealDate, $type, $deptId, $hostId, $hostCode, $count, $nameText, $remarks, $b, $l, $d, $ip, $ua);
    $st->execute();
    $id = (int) $st->insert_id;
    $st->close();
    return $id;
}

function canteenDeleteGuestOrder($conn, $id)
{
    $id = (int) $id;
    $st = $conn->prepare('DELETE FROM canteen_guest_orders WHERE id = ?');
    $st->bind_param('i', $id);
    $st->execute();
    $ok = $st->affected_rows > 0;
    $st->close();
    return $ok;
}

/**
 * Guest / trainee bookings for a date, ordered department → type → entry.
 * @param int[]|null $allowedDeptIds null = all
 */
function canteenGuestOrdersForDate($conn, $mealDate, $deptId = 0, $allowedDeptIds = null)
{
    $sql = "SELECT g.id, g.booking_type, g.department_id, g.host_employee_id, g.person_count, g.person_names, g.remarks,
                   g.breakfast, g.lunch, g.dinner, g.updated_at,
                   COALESCE(e.employee_code, g.host_employee_code) AS host_code, e.employee_name AS host_name,
                   COALESCE(d.department_name, '—') AS department_name, COALESCE(d.sort_order, 0) AS dept_sort
            FROM canteen_guest_orders g
            LEFT JOIN employees e ON e.id = g.host_employee_id
            LEFT JOIN departments d ON d.id = g.department_id
            WHERE g.meal_date = ?";
    $types = 's';
    $params = [$mealDate];
    if ($deptId > 0) {
        $sql .= ' AND g.department_id = ?';
        $types .= 'i';
        $params[] = $deptId;
    } elseif (is_array($allowedDeptIds)) {
        $ids = array_values(array_filter(array_map('intval', $allowedDeptIds)));
        $sql .= $ids ? ' AND g.department_id IN (' . implode(',', $ids) . ')' : ' AND 1 = 0';
    }
    $sql .= ' ORDER BY d.sort_order ASC, d.department_name ASC, g.booking_type ASC, g.id ASC';

    $rows = [];
    $st = $conn->prepare($sql);
    $st->bind_param($types, ...$params);
    $st->execute();
    $res = $st->get_result();
    while ($r = $res->fetch_assoc()) {
        $rows[] = $r;
    }
    $st->close();
    return $rows;
}
