<?php
/**
 * Payroll approval flow: Maker (Payroll Manager) → Checker (HR Head) → Accounts (final) → NEFT final
 *
 * salary_register_locks.approval_status:
 *   pending_check     locked by maker, waiting for checker
 *   pending_accounts  checked, waiting for accounts
 *   approved          final — NEFT sheet can be downloaded / printed
 */

require_once __DIR__ . '/../config/database.php';

function ensurePayrollApprovalTables($conn = null)
{
    static $done = false;
    if ($done) {
        return;
    }
    $close = false;
    if ($conn === null) {
        $conn = getDBConnection();
        $close = true;
    }
    if (function_exists('ensurePayrollReportTables')) {
        ensurePayrollReportTables($conn);
    }

    $cols = [
        'approval_status' => "VARCHAR(20) NOT NULL DEFAULT 'approved' AFTER remarks",
        'checked_by' => 'INT DEFAULT NULL AFTER approval_status',
        'checked_at' => 'DATETIME DEFAULT NULL AFTER checked_by',
        'approved_by' => 'INT DEFAULT NULL AFTER checked_at',
        'approved_at' => 'DATETIME DEFAULT NULL AFTER approved_by',
    ];
    foreach ($cols as $col => $def) {
        $chk = $conn->query("SHOW COLUMNS FROM salary_register_locks LIKE '" . $conn->real_escape_string($col) . "'");
        if ($chk && $chk->num_rows === 0) {
            $conn->query("ALTER TABLE salary_register_locks ADD COLUMN {$col} {$def}");
        }
    }

    $conn->query(
        "CREATE TABLE IF NOT EXISTS payroll_approval_settings (
            setting_key VARCHAR(40) NOT NULL PRIMARY KEY,
            setting_value VARCHAR(255) DEFAULT NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    $conn->query(
        "CREATE TABLE IF NOT EXISTS payroll_approval_log (
            id INT AUTO_INCREMENT PRIMARY KEY,
            month_no TINYINT NOT NULL,
            year_no SMALLINT NOT NULL,
            register_type VARCHAR(40) NOT NULL,
            department_id INT NOT NULL DEFAULT 0,
            action VARCHAR(30) NOT NULL,
            user_id INT DEFAULT NULL,
            remarks TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_pal_period (year_no, month_no, register_type, department_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    $conn->query(
        "CREATE TABLE IF NOT EXISTS payroll_notifications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            event_type VARCHAR(30) NOT NULL,
            title VARCHAR(200) NOT NULL,
            body VARCHAR(255) DEFAULT NULL,
            url VARCHAR(255) DEFAULT NULL,
            is_read TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_pn_user (user_id, is_read)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $done = true;
    if ($close) {
        $conn->close();
    }
}

function payrollApprovalStatusLabels()
{
    return [
        'pending_check' => 'Pending HR Head check',
        'pending_accounts' => 'Pending Accounts approval',
        'approved' => 'Final · Approved',
    ];
}

/** @return array{maker_user_id:int, checker_user_id:int, accounts_user_id:int} */
function getPayrollApprovalSettings()
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $conn = getDBConnection();
    ensurePayrollApprovalTables($conn);
    $cache = ['maker_user_id' => 0, 'checker_user_id' => 0, 'accounts_user_id' => 0];
    $res = $conn->query('SELECT setting_key, setting_value FROM payroll_approval_settings');
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            if (array_key_exists($r['setting_key'], $cache)) {
                $cache[$r['setting_key']] = (int) $r['setting_value'];
            }
        }
    }
    $conn->close();
    return $cache;
}

function savePayrollApprovalSettings(array $data)
{
    $conn = getDBConnection();
    ensurePayrollApprovalTables($conn);
    $st = $conn->prepare(
        'INSERT INTO payroll_approval_settings (setting_key, setting_value) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
    );
    foreach (['maker_user_id', 'checker_user_id', 'accounts_user_id'] as $key) {
        $val = (string) max(0, (int) ($data[$key] ?? 0));
        $st->bind_param('ss', $key, $val);
        $st->execute();
    }
    $st->close();
    $conn->close();
    return true;
}

/** Users that can be picked for a stage (staff + employee portal logins). */
function fetchPayrollApprovalCandidateUsers()
{
    $conn = getDBConnection();
    $rows = [];
    $res = $conn->query(
        "SELECT u.id, u.username, u.full_name, u.role, e.employee_code, e.employee_name, e.designation,
                d.department_name, r.name AS role_name
         FROM users u
         LEFT JOIN employees e ON e.id = u.employee_id
         LEFT JOIN departments d ON d.id = COALESCE(e.department_id, u.department_id)
         LEFT JOIN roles r ON r.id = u.custom_role_id
         WHERE u.status = 1 AND u.role IN ('admin', 'hr', 'employee')
         ORDER BY d.department_name ASC, COALESCE(e.employee_name, u.full_name) ASC"
    );
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $rows[] = $r;
        }
    }
    $conn->close();
    return $rows;
}

function payrollUserDisplayName($userId)
{
    $userId = (int) $userId;
    if ($userId <= 0) {
        return '';
    }
    static $cache = [];
    if (isset($cache[$userId])) {
        return $cache[$userId];
    }
    $conn = getDBConnection();
    $st = $conn->prepare(
        'SELECT u.username, u.full_name, e.employee_code, e.employee_name
         FROM users u LEFT JOIN employees e ON e.id = u.employee_id WHERE u.id = ? LIMIT 1'
    );
    $st->bind_param('i', $userId);
    $st->execute();
    $r = $st->get_result()->fetch_assoc();
    $st->close();
    $conn->close();
    $name = '';
    if ($r) {
        $name = trim((string) ($r['employee_name'] ?: $r['full_name'] ?: $r['username']));
        if (!empty($r['employee_code'])) {
            $name = $r['employee_code'] . ' · ' . $name;
        }
    }
    return $cache[$userId] = $name;
}

function payrollCurrentUserId()
{
    return (int) ($_SESSION['user_id'] ?? 0);
}

/** Stages the current user is assigned to: maker / checker / accounts */
function payrollApprovalUserStages($userId = null)
{
    $userId = $userId === null ? payrollCurrentUserId() : (int) $userId;
    if ($userId <= 0) {
        return [];
    }
    $s = getPayrollApprovalSettings();
    $stages = [];
    if ($s['maker_user_id'] === $userId) {
        $stages[] = 'maker';
    }
    if ($s['checker_user_id'] === $userId) {
        $stages[] = 'checker';
    }
    if ($s['accounts_user_id'] === $userId) {
        $stages[] = 'accounts';
    }
    return $stages;
}

function payrollApprovalIsMember($userId = null)
{
    return payrollApprovalUserStages($userId) !== [];
}

/** Assigned approval users may open Salary Register / NEFT even without the payroll permission. */
function requirePayrollRegisterAccess($deptId = 0)
{
    if (payrollApprovalIsMember()) {
        return;
    }
    requireAccess('payroll', 'view', $deptId);
}

/** Maker may lock; when no maker is configured anyone with register access may lock. */
function payrollCanLock()
{
    if (function_exists('isAdmin') && isAdmin()) {
        return true;
    }
    $s = getPayrollApprovalSettings();
    if ($s['maker_user_id'] <= 0) {
        return true;
    }
    return $s['maker_user_id'] === payrollCurrentUserId();
}

/** Action the current user can take on a lock row: 'check' / 'accounts' / '' */
function payrollApprovalPendingActionFor(array $lock)
{
    $status = (string) ($lock['approval_status'] ?? 'approved');
    $s = getPayrollApprovalSettings();
    $uid = payrollCurrentUserId();
    $admin = function_exists('isAdmin') && isAdmin();
    if ($status === 'pending_check' && ($s['checker_user_id'] === $uid || ($admin && $s['checker_user_id'] <= 0))) {
        return 'check';
    }
    if ($status === 'pending_accounts' && ($s['accounts_user_id'] === $uid || ($admin && $s['accounts_user_id'] <= 0))) {
        return 'accounts';
    }
    return '';
}

function payrollLockPeriodLabel(array $lock)
{
    $types = function_exists('salaryRegisterTypes') ? salaryRegisterTypes() : [];
    $label = date('F Y', mktime(0, 0, 0, (int) $lock['month_no'], 1, (int) $lock['year_no']));
    $typeLabel = $types[$lock['register_type']] ?? (string) $lock['register_type'];
    $dept = 'All departments';
    if ((int) $lock['department_id'] > 0 && function_exists('getDepartmentById')) {
        $d = getDepartmentById((int) $lock['department_id']);
        if ($d) {
            $dept = (string) $d['department_name'];
        }
    }
    return $typeLabel . ' · ' . $label . ' · ' . $dept;
}

function payrollLockRegisterUrl(array $lock)
{
    return 'payroll/register.php?' . http_build_query([
        'type' => $lock['register_type'],
        'month' => (int) $lock['month_no'],
        'year' => (int) $lock['year_no'],
        'department_id' => (int) $lock['department_id'],
    ]);
}

function payrollApprovalLog(array $lock, $action, $remarks = '', $conn = null)
{
    $close = false;
    if ($conn === null) {
        $conn = getDBConnection();
        $close = true;
    }
    ensurePayrollApprovalTables($conn);
    $m = (int) $lock['month_no'];
    $y = (int) $lock['year_no'];
    $t = (string) $lock['register_type'];
    $d = (int) $lock['department_id'];
    $uid = payrollCurrentUserId();
    $remarks = (string) $remarks;
    $st = $conn->prepare(
        'INSERT INTO payroll_approval_log (month_no, year_no, register_type, department_id, action, user_id, remarks)
         VALUES (?,?,?,?,?,NULLIF(?,0),?)'
    );
    $st->bind_param('iisisis', $m, $y, $t, $d, $action, $uid, $remarks);
    $st->execute();
    $st->close();
    if ($close) {
        $conn->close();
    }
}

function fetchPayrollApprovalLog($month, $year, $type, $deptId = 0)
{
    $conn = getDBConnection();
    ensurePayrollApprovalTables($conn);
    $month = (int) $month;
    $year = (int) $year;
    $type = (string) $type;
    $deptId = (int) $deptId;
    $st = $conn->prepare(
        'SELECT * FROM payroll_approval_log
         WHERE month_no = ? AND year_no = ? AND register_type = ? AND department_id = ?
         ORDER BY id ASC'
    );
    $st->bind_param('iisi', $month, $year, $type, $deptId);
    $st->execute();
    $rows = $st->get_result()->fetch_all(MYSQLI_ASSOC);
    $st->close();
    $conn->close();
    return $rows;
}

function payrollNotify($userIds, $eventType, $title, $body, $url)
{
    $userIds = array_values(array_unique(array_filter(array_map('intval', (array) $userIds))));
    $self = payrollCurrentUserId();
    if (!$userIds) {
        return;
    }
    $conn = getDBConnection();
    ensurePayrollApprovalTables($conn);
    $st = $conn->prepare(
        'INSERT INTO payroll_notifications (user_id, event_type, title, body, url) VALUES (?,?,?,?,?)'
    );
    foreach ($userIds as $uid) {
        if ($uid === $self) {
            continue;
        }
        $st->bind_param('issss', $uid, $eventType, $title, $body, $url);
        $st->execute();
    }
    $st->close();
    $conn->close();
}

function fetchUnreadPayrollNotifications($userId, $limit = 8)
{
    $userId = (int) $userId;
    $limit = max(1, (int) $limit);
    if ($userId <= 0) {
        return [];
    }
    $conn = getDBConnection();
    ensurePayrollApprovalTables($conn);
    $st = $conn->prepare(
        'SELECT * FROM payroll_notifications WHERE user_id = ? AND is_read = 0 ORDER BY id DESC LIMIT ' . $limit
    );
    $st->bind_param('i', $userId);
    $st->execute();
    $rows = $st->get_result()->fetch_all(MYSQLI_ASSOC);
    $st->close();
    $conn->close();
    return $rows;
}

/** @return string|null redirect url (relative) */
function markPayrollNotificationRead($id, $userId)
{
    $id = (int) $id;
    $userId = (int) $userId;
    $conn = getDBConnection();
    ensurePayrollApprovalTables($conn);
    $url = null;
    if ($id > 0) {
        $st = $conn->prepare('SELECT url FROM payroll_notifications WHERE id = ? AND user_id = ? LIMIT 1');
        $st->bind_param('ii', $id, $userId);
        $st->execute();
        $r = $st->get_result()->fetch_assoc();
        $st->close();
        $url = $r ? (string) $r['url'] : null;
        $st = $conn->prepare('UPDATE payroll_notifications SET is_read = 1 WHERE id = ? AND user_id = ?');
        $st->bind_param('ii', $id, $userId);
    } else {
        $st = $conn->prepare('UPDATE payroll_notifications SET is_read = 1 WHERE user_id = ?');
        $st->bind_param('i', $userId);
    }
    $st->execute();
    $st->close();
    $conn->close();
    return $url;
}

function getSalaryRegisterLockById($id)
{
    $id = (int) $id;
    $conn = getDBConnection();
    ensurePayrollApprovalTables($conn);
    $st = $conn->prepare(
        'SELECT id, month_no, year_no, register_type, department_id, locked_by, locked_at, total_gross, total_net,
                employee_count, neft_count, approval_status, checked_by, checked_at, approved_by, approved_at
         FROM salary_register_locks WHERE id = ? LIMIT 1'
    );
    $st->bind_param('i', $id);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    $st->close();
    $conn->close();
    return $row ?: null;
}

/** Called right after the maker locks the register. */
function payrollApprovalOnLocked($lockId)
{
    $lock = getSalaryRegisterLockById($lockId);
    if (!$lock) {
        return;
    }
    $s = getPayrollApprovalSettings();
    if ($s['checker_user_id'] > 0) {
        $status = 'pending_check';
    } elseif ($s['accounts_user_id'] > 0) {
        $status = 'pending_accounts';
    } else {
        $status = 'approved';
    }
    $conn = getDBConnection();
    ensurePayrollApprovalTables($conn);
    $st = $conn->prepare(
        'UPDATE salary_register_locks SET approval_status = ?,
            approved_by = IF(? = \'approved\', locked_by, NULL),
            approved_at = IF(? = \'approved\', NOW(), NULL)
         WHERE id = ?'
    );
    $st->bind_param('sssi', $status, $status, $status, $lockId);
    $st->execute();
    $st->close();
    payrollApprovalLog($lock, 'locked', '', $conn);
    $conn->close();

    $period = payrollLockPeriodLabel($lock);
    $url = payrollLockRegisterUrl($lock);
    $net = 'Net ₹ ' . number_format((float) $lock['total_net'], 2);
    if ($status === 'pending_check') {
        payrollNotify($s['checker_user_id'], 'check', 'Salary ready for your check', $period . ' · ' . $net, $url);
    } elseif ($status === 'pending_accounts') {
        payrollNotify($s['accounts_user_id'], 'accounts', 'Salary ready for final approval', $period . ' · ' . $net, $url);
    }
}

/**
 * Checker approve / Accounts done / Send back (by checker or accounts).
 *
 * @param string $action check_approve | accounts_done | send_back
 */
function payrollApprovalAct($lockId, $action, $remarks = '')
{
    $lock = getSalaryRegisterLockById($lockId);
    if (!$lock) {
        return ['ok' => false, 'error' => 'Locked register not found'];
    }
    $pending = payrollApprovalPendingActionFor($lock);
    if ($pending === '') {
        return ['ok' => false, 'error' => 'You are not allowed to act on this register at this stage'];
    }
    $remarks = trim((string) $remarks);
    $s = getPayrollApprovalSettings();
    $period = payrollLockPeriodLabel($lock);
    $url = payrollLockRegisterUrl($lock);
    $net = 'Net ₹ ' . number_format((float) $lock['total_net'], 2);
    $makerIds = [(int) $lock['locked_by'], $s['maker_user_id']];
    $uid = payrollCurrentUserId();
    $lockId = (int) $lock['id'];

    $conn = getDBConnection();
    ensurePayrollApprovalTables($conn);

    if ($action === 'send_back') {
        if ($remarks === '') {
            $conn->close();
            return ['ok' => false, 'error' => 'Please enter a remark for sending back'];
        }
        $st = $conn->prepare('DELETE FROM salary_register_locks WHERE id = ?');
        $st->bind_param('i', $lockId);
        $ok = $st->execute();
        $st->close();
        payrollApprovalLog($lock, $pending === 'check' ? 'sent_back_checker' : 'sent_back_accounts', $remarks, $conn);
        $conn->close();
        $who = $pending === 'check' ? 'HR Head' : 'Accounts';
        $notifyIds = $makerIds;
        if ($pending === 'accounts') {
            $notifyIds[] = (int) $lock['checked_by'];
        }
        payrollNotify($notifyIds, 'sent_back', 'Salary sent back by ' . $who, $period . ' · ' . $remarks, $url);
        return ['ok' => (bool) $ok, 'status' => 'sent_back'];
    }

    if ($action === 'check_approve' && $pending === 'check') {
        $next = $s['accounts_user_id'] > 0 ? 'pending_accounts' : 'approved';
        $st = $conn->prepare(
            'UPDATE salary_register_locks SET approval_status = ?, checked_by = NULLIF(?,0), checked_at = NOW(),
                approved_by = IF(? = \'approved\', NULLIF(?,0), NULL), approved_at = IF(? = \'approved\', NOW(), NULL)
             WHERE id = ?'
        );
        $st->bind_param('sisisi', $next, $uid, $next, $uid, $next, $lockId);
        $ok = $st->execute();
        $st->close();
        payrollApprovalLog($lock, 'checked', $remarks, $conn);
        $conn->close();
        if ($next === 'pending_accounts') {
            payrollNotify($s['accounts_user_id'], 'accounts', 'Salary checked · ready for final approval', $period . ' · ' . $net, $url);
        } else {
            payrollNotify($makerIds, 'approved', 'Salary approved · NEFT sheet final', $period . ' · ' . $net, $url);
        }
        return ['ok' => (bool) $ok, 'status' => $next];
    }

    if ($action === 'accounts_done' && $pending === 'accounts') {
        $st = $conn->prepare(
            'UPDATE salary_register_locks SET approval_status = \'approved\', approved_by = NULLIF(?,0), approved_at = NOW()
             WHERE id = ?'
        );
        $st->bind_param('ii', $uid, $lockId);
        $ok = $st->execute();
        $st->close();
        payrollApprovalLog($lock, 'approved', $remarks, $conn);
        $conn->close();
        $notifyIds = $makerIds;
        $notifyIds[] = (int) $lock['checked_by'];
        payrollNotify($notifyIds, 'approved', 'Salary done by Accounts · NEFT sheet final', $period . ' · ' . $net, $url);
        return ['ok' => (bool) $ok, 'status' => 'approved'];
    }

    $conn->close();
    return ['ok' => false, 'error' => 'Invalid action'];
}

/** Locks waiting on someone (for the approvals list). */
function fetchPayrollApprovalQueue($limit = 50)
{
    $conn = getDBConnection();
    ensurePayrollApprovalTables($conn);
    $limit = max(1, (int) $limit);
    $rows = [];
    $res = $conn->query(
        "SELECT id, month_no, year_no, register_type, department_id, locked_by, locked_at, total_net, employee_count,
                neft_count, approval_status, checked_by, checked_at, approved_by, approved_at
         FROM salary_register_locks
         ORDER BY FIELD(approval_status, 'pending_check', 'pending_accounts', 'approved'), year_no DESC, month_no DESC, id DESC
         LIMIT {$limit}"
    );
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $rows[] = $r;
        }
    }
    $conn->close();
    return $rows;
}
