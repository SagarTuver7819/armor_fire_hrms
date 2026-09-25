<?php
/**
 * Employee Portal Dashboard — Office Staff home (not Admin/HR workspace)
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permission_helper.php';
require_once __DIR__ . '/../includes/department_head_helper.php';
require_once __DIR__ . '/../includes/employee_helper.php';
require_once __DIR__ . '/../includes/leave_helper.php';
require_once __DIR__ . '/../includes/attendance_helper.php';

requireLogin();

if (empty($_SESSION['role_code']) && !empty($_SESSION['custom_role_id'])) {
    refreshHeadedDepartmentsSession();
}

// Only employee portal users — Admin/HR go to main dashboards
if (isAdmin()) {
    header('Location: ' . app_url('dashboard.php'));
    exit;
}
if (isHR()) {
    header('Location: ' . app_url('hr/dashboard.php'));
    exit;
}

$empId = (int) ($_SESSION['employee_id'] ?? 0);
$emp = $empId > 0 ? getEmployeeById($empId) : null;
$deptName = '';
if ($emp && !empty($emp['department_id'])) {
    require_once __DIR__ . '/../includes/master_helper.php';
    $d = getDepartmentById((int) $emp['department_id']);
    $deptName = (string) ($d['department_name'] ?? '');
}

ensureLeaveTables();
$pendingLeave = 0;
$leaveBalYear = (int) date('Y');
$leaveBalMap = []; // code => remaining
$leaveUsedMap = []; // code => used
$leaveApprovedMap = []; // code => approved days this year
if ($empId > 0) {
    $conn = getDBConnection();
    $st = $conn->prepare(
        "SELECT
            SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) AS pending_c
         FROM leave_requests WHERE employee_id = ?"
    );
    $st->bind_param('i', $empId);
    $st->execute();
    $lr = $st->get_result()->fetch_assoc();
    $st->close();
    $pendingLeave = (int) ($lr['pending_c'] ?? 0);

    $stAp = $conn->prepare(
        "SELECT UPPER(TRIM(lt.code)) AS code, COALESCE(SUM(lr.days), 0) AS days_c
         FROM leave_requests lr
         INNER JOIN leave_types lt ON lt.id = lr.leave_type_id
         WHERE lr.employee_id = ?
           AND lr.status = 'Approved'
           AND YEAR(lr.from_date) = ?
         GROUP BY UPPER(TRIM(lt.code))"
    );
    $stAp->bind_param('ii', $empId, $leaveBalYear);
    $stAp->execute();
    $apRes = $stAp->get_result();
    while ($apRow = $apRes->fetch_assoc()) {
        $c = (string) ($apRow['code'] ?? '');
        if ($c !== '') {
            $leaveApprovedMap[$c] = (float) ($apRow['days_c'] ?? 0);
        }
    }
    $stAp->close();

    foreach (getActiveLeaveTypes($conn) as $lt) {
        leaveEnsureBalanceRow($conn, $empId, (int) $lt['id'], $leaveBalYear);
    }
    foreach (getEmployeeLeaveBalances($empId, $leaveBalYear, $conn) as $br) {
        $code = strtoupper(trim((string) ($br['code'] ?? '')));
        if ($code === '') {
            continue;
        }
        $leaveBalMap[$code] = (float) ($br['remaining_days'] ?? 0);
        $leaveUsedMap[$code] = (float) ($br['used_days'] ?? 0);
    }
    $conn->close();
}

$pickLeaveBal = static function (array $codes) use ($leaveBalMap, $leaveUsedMap) {
    foreach ($codes as $code) {
        $code = strtoupper($code);
        if (array_key_exists($code, $leaveBalMap)) {
            return [
                'code' => $code,
                'remaining' => $leaveBalMap[$code],
                'used' => $leaveUsedMap[$code] ?? 0.0,
                'found' => true,
            ];
        }
    }
    return ['code' => $codes[0] ?? '', 'remaining' => 0.0, 'used' => 0.0, 'found' => false];
};

$pickLeaveApproved = static function (array $codes) use ($leaveApprovedMap) {
    $total = 0.0;
    $found = false;
    foreach ($codes as $code) {
        $code = strtoupper($code);
        if (array_key_exists($code, $leaveApprovedMap)) {
            $total += (float) $leaveApprovedMap[$code];
            $found = true;
        }
    }
    return ['days' => $total, 'found' => $found];
};

$codesPL = ['PL', 'CL', 'EL'];
$codesSL = ['SL'];
$codesCoff = ['COFF', 'C-OFF', 'C_OFF', 'COMP', 'COMPOFF', 'CO', 'C OFF'];
$codesDL = ['DL'];

$balPL = $pickLeaveBal($codesPL);
$balSL = $pickLeaveBal($codesSL);
$balCoff = $pickLeaveBal($codesCoff);
$balDL = $pickLeaveBal($codesDL);
$showDL = $balDL['found'] && ((float) $balDL['used'] > 0 || (float) $balDL['remaining'] != 0.0);

$apPL = $pickLeaveApproved($codesPL);
$apSL = $pickLeaveApproved($codesSL);
$apCoff = $pickLeaveApproved($codesCoff);
$apDL = $pickLeaveApproved($codesDL);
$showApprovedDL = $apDL['found'] && (float) $apDL['days'] > 0;

$fmtLeaveDays = static function ($n) {
    $n = (float) $n;
    if (abs($n - round($n)) < 0.001) {
        return (string) (int) round($n);
    }
    return rtrim(rtrim(number_format($n, 2, '.', ''), '0'), '.');
};

$unreadCirc = 0;
$unreadPol = 0;
$uid = (int) ($_SESSION['user_id'] ?? 0);
if ($uid > 0) {
    require_once __DIR__ . '/../includes/circular_helper.php';
    require_once __DIR__ . '/../includes/policy_helper.php';
    if (function_exists('ensureCircularTables')) {
        ensureCircularTables();
        $unreadCirc = count(fetchUnreadCircularNotifications($uid, 50));
    }
    if (function_exists('ensurePolicyTables')) {
        ensurePolicyTables();
        $unreadPol = count(fetchUnreadPolicyNotifications($uid, 50));
    }
}

$hour = (int) date('G');
if ($hour < 12) {
    $greet = 'Good Morning';
} elseif ($hour < 17) {
    $greet = 'Good Afternoon';
} else {
    $greet = 'Good Evening';
}
$userLabel = getUserName();
$roleLabel = getUserRoleLabel();
$empCode = $emp ? trim((string) ($emp['employee_code'] ?? '')) : '';
$empName = $emp ? trim((string) ($emp['employee_name'] ?? '')) : $userLabel;
$greetLine = $greet . ', '
    . ($empCode !== '' ? $empCode . ' - ' : '')
    . ($empName !== '' ? $empName : $userLabel);
$photoUrl = $emp ? employeeDocumentPublicUrl($emp['photo_file'] ?? '') : '';
$empInitials = '';
if ($empName !== '') {
    $parts = preg_split('/\s+/', $empName);
    $empInitials = strtoupper(substr($parts[0] ?? '', 0, 1) . substr($parts[1] ?? '', 0, 1));
    if ($empInitials === '') {
        $empInitials = strtoupper(substr($empName, 0, 2));
    }
}

// Today attendance strip: shift · punch in/out · late
$parseClockToParts = static function ($raw) {
    $raw = trim((string) $raw);
    if ($raw === '' || $raw === '00:00:00' || $raw === '—') {
        return null;
    }
    // Already HH:MM:SS from DB
    if (preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/', $raw, $m)) {
        return ['h' => (int) $m[1], 'i' => (int) $m[2]];
    }
    $compact = preg_replace('/\s+/', '', $raw);
    if (preg_match('/^(\d{1,2})(?::(\d{2}))?(am|pm)$/i', $compact, $m)) {
        $hh = (int) $m[1];
        $mm = isset($m[2]) && $m[2] !== '' ? (int) $m[2] : 0;
        $ap = strtolower($m[3]);
        if ($ap === 'pm' && $hh < 12) {
            $hh += 12;
        }
        if ($ap === 'am' && $hh === 12) {
            $hh = 0;
        }
        return ['h' => $hh, 'i' => $mm];
    }
    $ts = strtotime($raw);
    if ($ts) {
        return ['h' => (int) date('G', $ts), 'i' => (int) date('i', $ts)];
    }
    return null;
};
$fmtClock12 = static function ($raw) use ($parseClockToParts) {
    $parts = $parseClockToParts($raw);
    if (!$parts) {
        return '—';
    }
    $h24 = $parts['h'];
    $i = $parts['i'];
    $ap = $h24 >= 12 ? 'PM' : 'AM';
    $h12 = $h24 % 12;
    if ($h12 === 0) {
        $h12 = 12;
    }
    return sprintf('%02d:%02d %s', $h12, $i, $ap);
};

$shiftTypeLabel = $emp ? trim((string) ($emp['shift_type'] ?? '')) : '';
$shiftTimeLabel = $emp ? trim((string) ($emp['shift_time'] ?? '')) : '';
$shiftStartShow = '';
$shiftEndShow = '';
if ($shiftTimeLabel !== '') {
    $startRaw = $shiftTimeLabel;
    $endRaw = '';
    if (preg_match('/^(.+?)\s*(?:[-–]|to)\s*(.+)$/i', $shiftTimeLabel, $m)) {
        $startRaw = trim($m[1]);
        $endRaw = trim($m[2]);
    }
    $shiftStartShow = $fmtClock12($startRaw);
    $shiftEndShow = $endRaw !== '' ? $fmtClock12($endRaw) : '';
}
$shiftRangeShow = ($shiftStartShow !== '' && $shiftStartShow !== '—' && $shiftEndShow !== '' && $shiftEndShow !== '—')
    ? ($shiftStartShow . ' to ' . $shiftEndShow)
    : ($shiftStartShow !== '' && $shiftStartShow !== '—' ? $shiftStartShow : '');
$shiftDisplay = trim(
    ($shiftTypeLabel !== '' ? $shiftTypeLabel : '')
    . ($shiftRangeShow !== '' ? (($shiftTypeLabel !== '' ? ' · ' : '') . $shiftRangeShow) : '')
);
if ($shiftDisplay === '') {
    $shiftDisplay = 'Not set';
}

$todayDate = date('Y-m-d');
$todayPunchIn = null;
$todayPunchOut = null;
$todayStatus = '';
if ($empId > 0) {
    $connAtt = getDBConnection();
    ensureAttendanceTables($connAtt);
    $stAtt = $connAtt->prepare(
        'SELECT day_status, punch_in, punch_out
         FROM attendance_day_status
         WHERE employee_id = ? AND attendance_date = ?
         LIMIT 1'
    );
    $stAtt->bind_param('is', $empId, $todayDate);
    $stAtt->execute();
    $dayRow = $stAtt->get_result()->fetch_assoc();
    $stAtt->close();
    if (!$dayRow) {
        // Fallback: first IN / last OUT from punches
        $stP = $connAtt->prepare(
            "SELECT
                MIN(CASE WHEN punch_type = 'in' THEN punch_time END) AS pin,
                MAX(CASE WHEN punch_type = 'out' THEN punch_time END) AS pout
             FROM attendance_punches
             WHERE employee_id = ? AND attendance_date = ?"
        );
        $stP->bind_param('is', $empId, $todayDate);
        $stP->execute();
        $pRow = $stP->get_result()->fetch_assoc();
        $stP->close();
        if ($pRow) {
            $todayPunchIn = $pRow['pin'] ?? null;
            $todayPunchOut = $pRow['pout'] ?? null;
        }
    } else {
        $todayStatus = (string) ($dayRow['day_status'] ?? '');
        $todayPunchIn = $dayRow['punch_in'] ?? null;
        $todayPunchOut = $dayRow['punch_out'] ?? null;
    }
    $connAtt->close();
}

$punchInShow = $fmtClock12($todayPunchIn);
$punchOutShow = $fmtClock12($todayPunchOut);

$isLateToday = false;
$lateByMins = 0;
$lateLabel = 'On time';
$shiftStartParts = $parseClockToParts(
    (preg_match('/^(.+?)\s*(?:[-–]|to)\s*(.+)$/i', $shiftTimeLabel, $mm)
        ? trim($mm[1])
        : $shiftTimeLabel)
);
$punchParts = $parseClockToParts($todayPunchIn);
if ($punchParts && $shiftStartParts) {
    $shiftStartSec = $shiftStartParts['h'] * 3600 + $shiftStartParts['i'] * 60;
    $punchSec = $punchParts['h'] * 3600 + $punchParts['i'] * 60;
    if ($punchSec > $shiftStartSec) {
        $isLateToday = true;
        $lateByMins = (int) floor(($punchSec - $shiftStartSec) / 60);
    }
}
if ($todayPunchIn === null || $todayPunchIn === '') {
    $lateLabel = 'No punch yet';
} elseif ($isLateToday) {
    $lateLabel = $lateByMins > 0 ? ('Late by ' . $lateByMins . ' min') : 'Late';
} else {
    $lateLabel = 'On time';
}

// ── Dashboard panels: Policies / Circulars / Anniversaries / Birthdays ──
$empDeptId = $emp ? (int) ($emp['department_id'] ?? 0) : 0;
$panelLimit = 10;
$expand = strtolower(trim((string) ($_GET['expand'] ?? '')));

$recentPolicies = [];
$recentPoliciesTotal = 0;
$recentCirculars = [];
$recentCircularsTotal = 0;
if (function_exists('canAccess') && canAccess('policies', 'view')) {
    require_once __DIR__ . '/../includes/policy_helper.php';
    ensurePolicyTables();
    $allPol = fetchPolicies(0, $empDeptId);
    $recentPoliciesTotal = count($allPol);
    $recentPolicies = array_slice($allPol, 0, $expand === 'policies' ? 50 : $panelLimit);
}
if (function_exists('canAccess') && canAccess('circulars', 'view')) {
    require_once __DIR__ . '/../includes/circular_helper.php';
    ensureCircularTables();
    $allCirc = fetchCirculars(0, $empDeptId);
    $recentCircularsTotal = count($allCirc);
    $recentCirculars = array_slice($allCirc, 0, $expand === 'circulars' ? 50 : $panelLimit);
}

$workAnniversaries = [];
$workAnniversariesTotal = 0;
$upcomingBirthdays = [];
$upcomingBirthdaysTotal = 0;
$connPeople = getDBConnection();
$todayMd = date('m-d');
$todayYmd = date('Y-m-d');

// Upcoming work anniversaries (next 60 days, excl. year 0 joiners today as anniversary)
$stAnn = $connPeople->query(
    "SELECT e.id, e.employee_code, e.employee_name, e.date_of_joining, e.designation, e.photo_file,
            d.department_name,
            DATE_FORMAT(e.date_of_joining, '%m-%d') AS md
     FROM employees e
     LEFT JOIN departments d ON d.id = e.department_id
     WHERE e.status = 1
       AND e.date_of_joining IS NOT NULL
       AND e.date_of_joining != ''
       AND e.date_of_joining != '0000-00-00'
       AND YEAR(e.date_of_joining) < YEAR(CURDATE())
     ORDER BY e.employee_name ASC
     LIMIT 400"
);
$annCandidates = [];
$cutoff = new DateTime('today');
$annLimitDt = (clone $cutoff)->modify('+60 days');
if ($stAnn) {
    while ($row = $stAnn->fetch_assoc()) {
        $md = (string) ($row['md'] ?? '');
        if (!preg_match('/^\d{2}-\d{2}$/', $md)) {
            continue;
        }
        $y = (int) $cutoff->format('Y');
        $candidate = DateTime::createFromFormat('Y-m-d', $y . '-' . $md);
        if (!$candidate) {
            continue;
        }
        if ($candidate < $cutoff) {
            $candidate->modify('+1 year');
        }
        if ($candidate > $annLimitDt) {
            continue;
        }
        $joinY = (int) date('Y', strtotime((string) $row['date_of_joining']));
        $row['next_on'] = $candidate->format('Y-m-d');
        $row['in_days'] = (int) $cutoff->diff($candidate)->days;
        $row['years'] = max(1, (int) $candidate->format('Y') - $joinY);
        $annCandidates[] = $row;
    }
}
usort($annCandidates, static function ($a, $b) {
    return [$a['in_days'], $a['employee_name']] <=> [$b['in_days'], $b['employee_name']];
});
$workAnniversariesTotal = count($annCandidates);
$workAnniversaries = array_slice($annCandidates, 0, $expand === 'anniv' ? 50 : $panelLimit);

// Upcoming birthdays (next 45 days, include today)
$stBday = $connPeople->query(
    "SELECT e.id, e.employee_code, e.employee_name, e.date_of_birth, e.designation, e.photo_file,
            d.department_name,
            DATE_FORMAT(e.date_of_birth, '%m-%d') AS md
     FROM employees e
     LEFT JOIN departments d ON d.id = e.department_id
     WHERE e.status = 1
       AND e.date_of_birth IS NOT NULL
       AND e.date_of_birth != ''
       AND e.date_of_birth != '0000-00-00'
     ORDER BY e.employee_name ASC
     LIMIT 500"
);
$bdayCandidates = [];
$bdayLimitDt = (clone $cutoff)->modify('+45 days');
if ($stBday) {
    while ($row = $stBday->fetch_assoc()) {
        $md = (string) ($row['md'] ?? '');
        if (!preg_match('/^\d{2}-\d{2}$/', $md)) {
            continue;
        }
        $y = (int) $cutoff->format('Y');
        $candidate = DateTime::createFromFormat('Y-m-d', $y . '-' . $md);
        if (!$candidate) {
            continue;
        }
        if ($candidate < $cutoff) {
            $candidate->modify('+1 year');
        }
        if ($candidate > $bdayLimitDt) {
            continue;
        }
        $row['next_on'] = $candidate->format('Y-m-d');
        $row['in_days'] = (int) $cutoff->diff($candidate)->days;
        $bdayCandidates[] = $row;
    }
}
usort($bdayCandidates, static function ($a, $b) {
    return [$a['in_days'], $a['employee_name']] <=> [$b['in_days'], $b['employee_name']];
});
$upcomingBirthdaysTotal = count($bdayCandidates);
$upcomingBirthdays = array_slice($bdayCandidates, 0, $expand === 'bday' ? 50 : $panelLimit);

// Monthly joiners / leavers (current calendar month)
$monthStart = date('Y-m-01');
$monthEnd = date('Y-m-t');
$monthLabel = date('M Y');
$monthlyJoinEmployees = [];
$monthlyLeftEmployees = [];
$stJoin = $connPeople->prepare(
    "SELECT e.employee_code, e.employee_name, d.department_name, e.date_of_joining
     FROM employees e
     LEFT JOIN departments d ON d.id = e.department_id
     WHERE e.status = 1
       AND e.date_of_joining IS NOT NULL
       AND e.date_of_joining != ''
       AND e.date_of_joining != '0000-00-00'
       AND e.date_of_joining BETWEEN ? AND ?
     ORDER BY e.date_of_joining ASC, e.employee_name ASC
     LIMIT 60"
);
if ($stJoin) {
    $stJoin->bind_param('ss', $monthStart, $monthEnd);
    $stJoin->execute();
    $resJoin = $stJoin->get_result();
    while ($row = $resJoin->fetch_assoc()) {
        $monthlyJoinEmployees[] = $row;
    }
    $stJoin->close();
}
$stLeft = $connPeople->prepare(
    "SELECT e.employee_code, e.employee_name, d.department_name, e.date_of_exit
     FROM employees e
     LEFT JOIN departments d ON d.id = e.department_id
     WHERE e.date_of_exit IS NOT NULL
       AND e.date_of_exit != ''
       AND e.date_of_exit != '0000-00-00'
       AND e.date_of_exit BETWEEN ? AND ?
     ORDER BY e.date_of_exit ASC, e.employee_name ASC
     LIMIT 60"
);
if ($stLeft) {
    $stLeft->bind_param('ss', $monthStart, $monthEnd);
    $stLeft->execute();
    $resLeft = $stLeft->get_result();
    while ($row = $resLeft->fetch_assoc()) {
        $monthlyLeftEmployees[] = $row;
    }
    $stLeft->close();
}
$connPeople->close();

$canManageHeads = canManageDepartmentHeads();
$employeeMatrix = fetchEmployeeMatrixHeads();

$pageTitle = 'My Dashboard';
$useSidebar = true;
$sidebarMode = 'workspace';
$sidebarActive = 'emp_home';

require_once __DIR__ . '/../includes/header.php';

$denied = isset($_GET['msg']) && $_GET['msg'] === 'denied';
?>

<main class="dashboard-main">
    <?php if ($denied): ?>
        <div class="alert alert-danger" style="margin-bottom:14px;">
            You do not have access to that page. Use the menu below.
        </div>
    <?php endif; ?>

    <div class="form-page-card" style="margin-bottom:14px;">
        <div class="form-page-header" style="margin-bottom:0;">
            <div class="master-list-title" style="width:100%;align-items:flex-start;flex-wrap:wrap;gap:16px;">
                <div style="display:flex;align-items:center;gap:14px;min-width:220px;flex:1;">
                    <div class="emp-avatar" style="width:64px;height:64px;border-radius:16px;font-size:22px;" aria-hidden="true">
                        <?php if ($photoUrl !== ''): ?>
                            <img src="<?php echo htmlspecialchars($photoUrl); ?>" alt="">
                        <?php else: ?>
                            <span><?php echo htmlspecialchars($empInitials !== '' ? $empInitials : 'E'); ?></span>
                        <?php endif; ?>
                    </div>
                    <div>
                        <h1><?php echo htmlspecialchars($greetLine); ?></h1>
                        <p>
                            Employee Portal
                            <?php if ($deptName !== ''): ?>
                                · <?php echo htmlspecialchars($deptName); ?>
                            <?php endif; ?>
                            <?php if ($roleLabel !== ''): ?>
                                · <?php echo htmlspecialchars($roleLabel); ?>
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
                <div class="emp-dash-att-strip">
                    <div class="emp-dash-att-chip is-shift">
                        <div class="emp-dash-att-ico"><i class="fa-solid fa-clock"></i></div>
                        <div>
                            <div class="emp-dash-att-label">Shift Time</div>
                            <div class="emp-dash-att-value"><?php echo htmlspecialchars($shiftDisplay); ?></div>
                        </div>
                    </div>
                    <div class="emp-dash-att-chip is-punch">
                        <div class="emp-dash-att-ico"><i class="fa-solid fa-fingerprint"></i></div>
                        <div>
                            <div class="emp-dash-att-label">Today Punch</div>
                            <div class="emp-dash-att-value">
                                In <?php echo htmlspecialchars($punchInShow); ?>
                                <span class="emp-dash-att-sep">to</span>
                                Out <?php echo htmlspecialchars($punchOutShow); ?>
                            </div>
                        </div>
                    </div>
                    <div class="emp-dash-att-chip <?php echo $isLateToday ? 'is-late' : 'is-ok'; ?>">
                        <div class="emp-dash-att-ico"><i class="fa-solid <?php echo $isLateToday ? 'fa-triangle-exclamation' : 'fa-circle-check'; ?>"></i></div>
                        <div>
                            <div class="emp-dash-att-label">Status</div>
                            <div class="emp-dash-att-value"><?php echo htmlspecialchars($lateLabel); ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="dash-hero-stats emp-dash-kpi emp-dash-kpi-2">
        <div class="form-page-card emp-leave-bal-card is-approved">
            <div class="emp-leave-bal-top">
                <div>
                    <div class="emp-leave-bal-title">Approved (This Year)</div>
                    <div class="emp-leave-bal-sub"><?php echo (int) $leaveBalYear; ?> · Approved leave days by type</div>
                </div>
                <a class="emp-leave-bal-link" href="<?php echo app_url('leave/index.php'); ?>" title="My Leave">
                    <i class="fa-solid fa-circle-check"></i>
                </a>
            </div>
            <div class="emp-leave-bal-grid<?php echo $showApprovedDL ? ' has-dl' : ''; ?>">
                <div class="emp-leave-chip is-pl">
                    <span class="emp-leave-chip-line">
                        <b>PL</b><span class="emp-leave-chip-dash">-</span><strong><?php echo htmlspecialchars($fmtLeaveDays($apPL['days'])); ?></strong>
                    </span>
                </div>
                <div class="emp-leave-chip is-sl">
                    <span class="emp-leave-chip-line">
                        <b>SL</b><span class="emp-leave-chip-dash">-</span><strong><?php echo htmlspecialchars($fmtLeaveDays($apSL['days'])); ?></strong>
                    </span>
                </div>
                <div class="emp-leave-chip is-coff">
                    <span class="emp-leave-chip-line">
                        <b>C-Off</b><span class="emp-leave-chip-dash">-</span><strong><?php echo htmlspecialchars($fmtLeaveDays($apCoff['days'])); ?></strong>
                    </span>
                </div>
                <?php if ($showApprovedDL): ?>
                <div class="emp-leave-chip is-dl">
                    <span class="emp-leave-chip-line">
                        <b>DL</b><span class="emp-leave-chip-dash">-</span><strong><?php echo htmlspecialchars($fmtLeaveDays($apDL['days'])); ?></strong>
                    </span>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="form-page-card emp-leave-bal-card">
            <div class="emp-leave-bal-top">
                <div>
                    <div class="emp-leave-bal-title">Leave Balance</div>
                    <div class="emp-leave-bal-sub"><?php echo (int) $leaveBalYear; ?> · Remaining days
                        <?php if ($pendingLeave > 0): ?>
                            <span class="emp-leave-pending-pill"><?php echo (int) $pendingLeave; ?> pending</span>
                        <?php endif; ?>
                    </div>
                </div>
                <a class="emp-leave-bal-link" href="<?php echo app_url('leave/index.php'); ?>" title="My Leave">
                    <i class="fa-solid fa-arrow-up-right-from-square"></i>
                </a>
            </div>
            <div class="emp-leave-bal-grid<?php echo $showDL ? ' has-dl' : ''; ?>">
                <div class="emp-leave-chip is-pl">
                    <span class="emp-leave-chip-line">
                        <b>PL</b><span class="emp-leave-chip-dash">-</span><strong><?php echo htmlspecialchars($fmtLeaveDays($balPL['remaining'])); ?></strong>
                    </span>
                </div>
                <div class="emp-leave-chip is-sl">
                    <span class="emp-leave-chip-line">
                        <b>SL</b><span class="emp-leave-chip-dash">-</span><strong><?php echo htmlspecialchars($fmtLeaveDays($balSL['remaining'])); ?></strong>
                    </span>
                </div>
                <div class="emp-leave-chip is-coff">
                    <span class="emp-leave-chip-line">
                        <b>C-Off</b><span class="emp-leave-chip-dash">-</span><strong><?php echo htmlspecialchars($fmtLeaveDays($balCoff['remaining'])); ?></strong>
                    </span>
                </div>
                <?php if ($showDL): ?>
                <div class="emp-leave-chip is-dl">
                    <span class="emp-leave-chip-line">
                        <b>DL</b><span class="emp-leave-chip-dash">-</span><strong><?php echo htmlspecialchars($fmtLeaveDays($balDL['remaining'])); ?></strong>
                    </span>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="dash-hero-stats emp-dash-kpi emp-dash-kpi-2">
        <div class="form-page-card emp-leave-bal-card is-join">
            <div class="emp-leave-bal-top">
                <div>
                    <div class="emp-leave-bal-title">Monthly Join Employee</div>
                    <div class="emp-leave-bal-sub"><?php echo htmlspecialchars($monthLabel); ?> · <?php echo count($monthlyJoinEmployees); ?> joined</div>
                </div>
                <span class="emp-leave-bal-link" title="Joined this month" aria-hidden="true">
                    <i class="fa-solid fa-user-plus"></i>
                </span>
            </div>
            <div class="emp-move-list">
                <?php if (!$monthlyJoinEmployees): ?>
                    <div class="emp-move-empty">No employees joined this month.</div>
                <?php else: ?>
                    <?php foreach ($monthlyJoinEmployees as $je): ?>
                        <div class="emp-move-chip is-join">
                            <b><?php echo htmlspecialchars((string) ($je['employee_code'] ?? '—')); ?></b>
                            <span><?php echo htmlspecialchars((string) ($je['employee_name'] ?? '—')); ?></span>
                            <em><?php echo htmlspecialchars(trim((string) ($je['department_name'] ?? '')) !== '' ? $je['department_name'] : '—'); ?></em>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="form-page-card emp-leave-bal-card is-left">
            <div class="emp-leave-bal-top">
                <div>
                    <div class="emp-leave-bal-title">Monthly Left Employee</div>
                    <div class="emp-leave-bal-sub"><?php echo htmlspecialchars($monthLabel); ?> · <?php echo count($monthlyLeftEmployees); ?> left</div>
                </div>
                <span class="emp-leave-bal-link" title="Left this month" aria-hidden="true">
                    <i class="fa-solid fa-user-minus"></i>
                </span>
            </div>
            <div class="emp-move-list">
                <?php if (!$monthlyLeftEmployees): ?>
                    <div class="emp-move-empty">No employees left this month.</div>
                <?php else: ?>
                    <?php foreach ($monthlyLeftEmployees as $le): ?>
                        <div class="emp-move-chip is-left">
                            <b><?php echo htmlspecialchars((string) ($le['employee_code'] ?? '—')); ?></b>
                            <span><?php echo htmlspecialchars((string) ($le['employee_name'] ?? '—')); ?></span>
                            <em><?php echo htmlspecialchars(trim((string) ($le['department_name'] ?? '')) !== '' ? $le['department_name'] : '—'); ?></em>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="emp-dash-panels">
        <section class="form-page-card emp-dash-panel is-policies">
            <div class="emp-dash-panel-head">
                <div>
                    <h3><i class="fa-solid fa-scroll" style="color:#7C3AED;"></i> Recent Policies</h3>
                    <p><?php echo (int) $recentPoliciesTotal; ?> total</p>
                </div>
                <?php if ($recentPoliciesTotal > $panelLimit && $expand !== 'policies'): ?>
                    <a class="btn-ghost emp-dash-more" href="<?php echo app_url('employee/dashboard.php?expand=policies#panel-policies'); ?>">More</a>
                <?php elseif ($expand === 'policies'): ?>
                    <a class="btn-ghost emp-dash-more" href="<?php echo app_url('policies/index.php'); ?>">View all</a>
                <?php elseif ($recentPoliciesTotal > 0): ?>
                    <a class="btn-ghost emp-dash-more" href="<?php echo app_url('policies/index.php'); ?>">View all</a>
                <?php endif; ?>
            </div>
            <div class="emp-dash-panel-body" id="panel-policies">
                <?php if (!$recentPolicies): ?>
                    <div class="emp-dash-empty">No policies yet.</div>
                <?php else: ?>
                    <ul class="emp-dash-list">
                        <?php foreach ($recentPolicies as $p): ?>
                            <li>
                                <a class="emp-dash-row" href="<?php echo app_url('policies/view.php?id=' . (int) $p['id']); ?>">
                                    <em class="emp-dash-date"><?php echo htmlspecialchars(formatDateDisplay($p['policy_date'] ?? ($p['added_date'] ?? ''))); ?></em>
                                    <strong class="emp-dash-detail"><?php echo htmlspecialchars((string) ($p['title'] ?? 'Policy')); ?></strong>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </section>

        <section class="form-page-card emp-dash-panel is-circulars">
            <div class="emp-dash-panel-head">
                <div>
                    <h3><i class="fa-solid fa-file-circle-plus" style="color:#1D4ED8;"></i> Recent Circulars</h3>
                    <p><?php echo (int) $recentCircularsTotal; ?> total</p>
                </div>
                <?php if ($recentCircularsTotal > $panelLimit && $expand !== 'circulars'): ?>
                    <a class="btn-ghost emp-dash-more" href="<?php echo app_url('employee/dashboard.php?expand=circulars#panel-circulars'); ?>">More</a>
                <?php elseif ($recentCircularsTotal > 0): ?>
                    <a class="btn-ghost emp-dash-more" href="<?php echo app_url('circulars/index.php'); ?>">View all</a>
                <?php endif; ?>
            </div>
            <div class="emp-dash-panel-body" id="panel-circulars">
                <?php if (!$recentCirculars): ?>
                    <div class="emp-dash-empty">No circulars yet.</div>
                <?php else: ?>
                    <ul class="emp-dash-list">
                        <?php foreach ($recentCirculars as $c): ?>
                            <li>
                                <a class="emp-dash-row" href="<?php echo app_url('circulars/view.php?id=' . (int) $c['id']); ?>">
                                    <em class="emp-dash-date"><?php echo htmlspecialchars(formatDateDisplay($c['circular_date'] ?? ($c['added_date'] ?? ''))); ?></em>
                                    <strong class="emp-dash-detail"><?php echo htmlspecialchars((string) ($c['title'] ?? 'Circular')); ?></strong>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </section>

        <section class="form-page-card emp-dash-panel is-anniv">
            <div class="emp-dash-panel-head">
                <div>
                    <h3><i class="fa-solid fa-award" style="color:#B45309;"></i> Work Anniversaries</h3>
                    <p>Next 60 days</p>
                </div>
                <?php if ($workAnniversariesTotal > $panelLimit && $expand !== 'anniv'): ?>
                    <a class="btn-ghost emp-dash-more" href="<?php echo app_url('employee/dashboard.php?expand=anniv#panel-anniv'); ?>">More</a>
                <?php elseif ($expand === 'anniv'): ?>
                    <a class="btn-ghost emp-dash-more" href="<?php echo app_url('employee/dashboard.php#panel-anniv'); ?>">Less</a>
                <?php endif; ?>
            </div>
            <div class="emp-dash-panel-body" id="panel-anniv">
                <?php if (!$workAnniversaries): ?>
                    <div class="emp-dash-empty">No upcoming work anniversaries.</div>
                <?php else: ?>
                    <ul class="emp-dash-list">
                        <?php foreach ($workAnniversaries as $a):
                            $when = ((int) ($a['in_days'] ?? 0) === 0)
                                ? 'Today'
                                : (((int) $a['in_days'] === 1) ? 'Tomorrow' : ('In ' . (int) $a['in_days'] . ' days'));
                            $detail = trim((string) ($a['employee_name'] ?? ''))
                                . ' · ' . (int) ($a['years'] ?? 0) . ' yr'
                                . ' · ' . $when;
                            ?>
                            <li>
                                <div class="emp-dash-row">
                                    <em class="emp-dash-date"><?php echo htmlspecialchars(formatDateDisplay($a['next_on'] ?? '')); ?></em>
                                    <strong class="emp-dash-detail"><?php echo htmlspecialchars($detail); ?></strong>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </section>

        <section class="form-page-card emp-dash-panel is-bday">
            <div class="emp-dash-panel-head">
                <div>
                    <h3><i class="fa-solid fa-cake-candles" style="color:#DB2777;"></i> Upcoming Birthdays</h3>
                    <p>Next 45 days</p>
                </div>
                <?php if ($upcomingBirthdaysTotal > $panelLimit && $expand !== 'bday'): ?>
                    <a class="btn-ghost emp-dash-more" href="<?php echo app_url('employee/dashboard.php?expand=bday#panel-bday'); ?>">More</a>
                <?php elseif ($expand === 'bday'): ?>
                    <a class="btn-ghost emp-dash-more" href="<?php echo app_url('employee/dashboard.php#panel-bday'); ?>">Less</a>
                <?php endif; ?>
            </div>
            <div class="emp-dash-panel-body" id="panel-bday">
                <?php if (!$upcomingBirthdays): ?>
                    <div class="emp-dash-empty">No upcoming birthdays.</div>
                <?php else: ?>
                    <ul class="emp-dash-list">
                        <?php foreach ($upcomingBirthdays as $b):
                            $when = ((int) ($b['in_days'] ?? 0) === 0)
                                ? 'Today'
                                : (((int) $b['in_days'] === 1) ? 'Tomorrow' : ('In ' . (int) $b['in_days'] . ' days'));
                            $dn = trim((string) ($b['department_name'] ?? ''));
                            $detail = trim((string) ($b['employee_name'] ?? ''))
                                . ' · ' . $when
                                . ($dn !== '' ? (' · ' . $dn) : '');
                            ?>
                            <li>
                                <div class="emp-dash-row">
                                    <em class="emp-dash-date"><?php echo htmlspecialchars(formatDateDisplay($b['next_on'] ?? '')); ?></em>
                                    <strong class="emp-dash-detail"><?php echo htmlspecialchars($detail); ?></strong>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </section>
    </div>

    <section class="form-page-card emp-matrix-card" id="employee-matrix">
        <div class="emp-matrix-head">
            <div>
                <h2><i class="fa-solid fa-sitemap"></i> Employee Matrix</h2>
                <p>Department / HR / Payroll Heads · Desk · Contact</p>
            </div>
            <?php if ($canManageHeads): ?>
                <div style="display:flex;gap:8px;flex-wrap:wrap;">
                    <a class="btn-secondary" href="<?php echo app_url('roles/assign.php'); ?>">
                        <i class="fa-solid fa-user-check"></i> Assign Head Login
                    </a>
                    <a class="btn-primary" href="<?php echo app_url('roles/department_heads.php'); ?>">
                        <i class="fa-solid fa-user-tie"></i> Set Department Heads
                    </a>
                </div>
            <?php endif; ?>
        </div>
        <div class="table-wrap emp-matrix-wrap">
            <table class="data-table emp-matrix-table">
                <thead>
                    <tr>
                        <th>Sr</th>
                        <th>Emp Desk No</th>
                        <th>Emp Code</th>
                        <th>Name</th>
                        <th>Designation</th>
                        <th>Department</th>
                        <th>Official Mobile</th>
                        <th>Official Email</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$employeeMatrix): ?>
                    <tr>
                        <td colspan="8" class="emp-matrix-empty">
                            No heads set yet.
                            <?php if ($canManageHeads): ?>
                                First set department-wise heads from
                                <a href="<?php echo app_url('roles/department_heads.php'); ?>">Department Heads</a>
                                or assign <strong>HR Head / Dept Head</strong> from
                                <a href="<?php echo app_url('roles/assign.php'); ?>">Assign Role</a>.
                            <?php else: ?>
                                Contact Admin / HR Head.
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($employeeMatrix as $i => $row):
                        $headCode = strtoupper(trim((string) ($row['head_role_code'] ?? 'DEPT_HEAD')));
                        $rowTone = 'is-dept';
                        if ($headCode === 'HR_HEAD') {
                            $rowTone = 'is-hr';
                        } elseif ($headCode === 'PAYROLL_HEAD') {
                            $rowTone = 'is-pay';
                        }
                        $desk = trim((string) ($row['desk_no'] ?? ''));
                        $code = trim((string) ($row['employee_code'] ?? ''));
                        $name = trim((string) ($row['employee_name'] ?? ''));
                        $desig = trim((string) ($row['designation'] ?? ''));
                        $dept = trim((string) ($row['department_name'] ?? ''));
                        $mobile = trim((string) ($row['office_mobile'] ?? ''));
                        $mail = trim((string) ($row['office_email'] ?? ''));
                        ?>
                        <tr class="emp-matrix-row <?php echo $rowTone; ?>">
                            <td><span class="emp-matrix-sr"><?php echo $i + 1; ?></span></td>
                            <td><?php if ($desk !== ''): ?>
                                <span class="emp-matrix-chip emp-matrix-desk"><?php echo htmlspecialchars($desk); ?></span>
                            <?php else: ?><span class="emp-matrix-na">—</span><?php endif; ?></td>
                            <td><?php if ($code !== ''): ?>
                                <span class="emp-matrix-chip emp-matrix-code"><?php echo htmlspecialchars($code); ?></span>
                            <?php else: ?><span class="emp-matrix-na">—</span><?php endif; ?></td>
                            <td><span class="emp-matrix-name"><?php echo htmlspecialchars($name !== '' ? $name : '—'); ?></span></td>
                            <td><?php if ($desig !== ''): ?>
                                <span class="emp-matrix-chip emp-matrix-desig"><?php echo htmlspecialchars($desig); ?></span>
                            <?php else: ?><span class="emp-matrix-na">—</span><?php endif; ?></td>
                            <td><?php if ($dept !== ''): ?>
                                <span class="emp-matrix-chip emp-matrix-dept"><?php echo htmlspecialchars($dept); ?></span>
                            <?php else: ?><span class="emp-matrix-na">—</span><?php endif; ?></td>
                            <td><?php if ($mobile !== ''): ?>
                                <a class="emp-matrix-chip emp-matrix-mobile" href="tel:<?php echo htmlspecialchars($mobile); ?>">
                                    <i class="fa-solid fa-phone"></i> <?php echo htmlspecialchars($mobile); ?>
                                </a>
                            <?php else: ?><span class="emp-matrix-na">—</span><?php endif; ?></td>
                            <td class="emp-matrix-mail"><?php if ($mail !== ''): ?>
                                <a class="emp-matrix-chip emp-matrix-email" href="mailto:<?php echo htmlspecialchars($mail); ?>">
                                    <i class="fa-solid fa-envelope"></i> <?php echo htmlspecialchars($mail); ?>
                                </a>
                            <?php else: ?><span class="emp-matrix-na">—</span><?php endif; ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
