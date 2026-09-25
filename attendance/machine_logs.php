<?php
/**
 * Old Attendance (Machine Wise) — logs + employee link
 * Separate store — does not affect Attendance Report / Manual
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permission_helper.php';
require_once __DIR__ . '/../includes/biometric_helper.php';

requireLogin();
requireAccess('attendance', 'view');
ensureBiometricTables();

$toast = '';
$toastType = 'success';
if (!empty($_SESSION['bio_flash'])) {
    $toast = (string) ($_SESSION['bio_flash']['message'] ?? '');
    $toastType = !empty($_SESSION['bio_flash']['ok']) ? 'success' : 'error';
    unset($_SESSION['bio_flash']);
}

$machineId = (int) ($_GET['machine_id'] ?? 0);
$fromDate = trim((string) ($_GET['from_date'] ?? date('Y-m-d', strtotime('-7 days'))));
$toDate = trim((string) ($_GET['to_date'] ?? date('Y-m-d')));
$punchType = strtolower(trim((string) ($_GET['punch_type'] ?? '')));
$match = strtolower(trim((string) ($_GET['match'] ?? '')));
$q = trim((string) ($_GET['q'] ?? ''));
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 80;
$offset = ($page - 1) * $perPage;
$canEdit = canAccess('attendance', 'edit');

$machines = fetchBiometricMachines(false);
$employees = $canEdit ? fetchEmployeesForMachineLink() : [];
$conn = getDBConnection();
ensureBiometricTables($conn);

$where = ['1=1'];
$types = '';
$params = [];

if ($machineId > 0) {
    $where[] = 'l.machine_id = ?';
    $types .= 'i';
    $params[] = $machineId;
}
if ($fromDate !== '') {
    $where[] = 'l.attendance_date >= ?';
    $types .= 's';
    $params[] = $fromDate;
}
if ($toDate !== '') {
    $where[] = 'l.attendance_date <= ?';
    $types .= 's';
    $params[] = $toDate;
}
if ($punchType === 'in' || $punchType === 'out') {
    $where[] = 'l.punch_type = ?';
    $types .= 's';
    $params[] = $punchType;
}
if ($match === 'yes') {
    $where[] = 'l.employee_id IS NOT NULL AND l.employee_id > 0';
} elseif ($match === 'no') {
    $where[] = '(l.employee_id IS NULL OR l.employee_id = 0)';
}
if ($q !== '') {
    $where[] = '(l.employee_code LIKE ? OR l.employee_name LIKE ? OR l.biometric_user_id LIKE ?)';
    $types .= 'sss';
    $like = '%' . $q . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

$sqlWhere = implode(' AND ', $where);

$cst = $conn->prepare("SELECT COUNT(*) AS c FROM machine_attendance_logs l WHERE $sqlWhere");
if ($types !== '') {
    $cst->bind_param($types, ...$params);
}
$cst->execute();
$total = (int) ($cst->get_result()->fetch_assoc()['c'] ?? 0);
$cst->close();

$inOut = ['in' => 0, 'out' => 0];
$sumSt = $conn->prepare(
    "SELECT punch_type, COUNT(*) AS c FROM machine_attendance_logs l WHERE $sqlWhere GROUP BY punch_type"
);
if ($types !== '') {
    $sumSt->bind_param($types, ...$params);
}
$sumSt->execute();
$sumRes = $sumSt->get_result();
while ($sr = $sumRes->fetch_assoc()) {
    $pt = strtolower((string) $sr['punch_type']);
    if (isset($inOut[$pt])) {
        $inOut[$pt] = (int) $sr['c'];
    }
}
$sumSt->close();

$listSql = "SELECT l.*, m.machine_name, m.provider_type,
                   e.employee_code AS local_emp_code, e.employee_name AS local_emp_name
            FROM machine_attendance_logs l
            LEFT JOIN biometric_machines m ON m.id = l.machine_id
            LEFT JOIN employees e ON e.id = l.employee_id
            WHERE $sqlWhere
            ORDER BY l.attendance_date DESC, l.punch_time DESC
            LIMIT ? OFFSET ?";
$typesList = $types . 'ii';
$paramsList = $params;
$paramsList[] = $perPage;
$paramsList[] = $offset;
$st = $conn->prepare($listSql);
$st->bind_param($typesList, ...$paramsList);
$st->execute();
$res = $st->get_result();
$rows = [];
while ($r = $res->fetch_assoc()) {
    $rows[] = $r;
}
$st->close();
$conn->close();

$totalPages = max(1, (int) ceil($total / $perPage));
$redirectQs = http_build_query([
    'machine_id' => $machineId,
    'from_date' => $fromDate,
    'to_date' => $toDate,
    'punch_type' => $punchType,
    'match' => $match,
    'q' => $q,
    'page' => $page,
]);

function machineLogsUrl(array $overrides = [])
{
    $base = [
        'machine_id' => (int) ($_GET['machine_id'] ?? 0),
        'from_date' => (string) ($_GET['from_date'] ?? date('Y-m-d', strtotime('-7 days'))),
        'to_date' => (string) ($_GET['to_date'] ?? date('Y-m-d')),
        'punch_type' => (string) ($_GET['punch_type'] ?? ''),
        'match' => (string) ($_GET['match'] ?? ''),
        'q' => (string) ($_GET['q'] ?? ''),
        'page' => (int) ($_GET['page'] ?? 1),
    ];
    return app_url('attendance/machine_logs.php?' . http_build_query(array_merge($base, $overrides)));
}

$pageTitle = 'Old Attendance (Machine Wise)';
$useSidebar = true;
$sidebarMode = 'attendance';
$sidebarActive = 'attendance_machine_logs';
$extraCss = ['https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css'];
$extraJs = ['https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js'];

require_once __DIR__ . '/../includes/header.php';
?>

<main class="dashboard-main">
    <div class="page-toolbar flex-between">
        <a href="<?php echo app_url('attendance/machines.php'); ?>" class="back-link">
            <i class="fa-solid fa-arrow-left"></i> Machines
        </a>
        <div class="toolbar-actions">
            <a href="<?php echo app_url('attendance/machine_report.php?show=1'); ?>" class="btn-secondary">
                <i class="fa-solid fa-table"></i> Machine Wise Report
            </a>
            <a href="<?php echo app_url('attendance/machine_sync.php'); ?>" class="btn-primary">
                <i class="fa-solid fa-rotate"></i> Sync
            </a>
        </div>
    </div>

    <div class="form-page-card">
        <div class="form-page-header">
            <h1>Old Attendance (Machine Wise)</h1>
            <p>
                Machine punches only · Link biometric code → local employee ·
                <strong>No effect</strong> on Attendance Report / Manual Entry
            </p>
        </div>

        <div class="bio-stat-grid bio-stat-grid-3">
            <div class="bio-stat">
                <span class="bio-stat-label">Records</span>
                <span class="bio-stat-value"><?php echo number_format($total); ?></span>
            </div>
            <div class="bio-stat">
                <span class="bio-stat-label">In</span>
                <span class="bio-stat-value"><?php echo number_format($inOut['in']); ?></span>
            </div>
            <div class="bio-stat">
                <span class="bio-stat-label">Out</span>
                <span class="bio-stat-value"><?php echo number_format($inOut['out']); ?></span>
            </div>
        </div>

        <?php if ($canEdit): ?>
        <div class="bio-link-toolbar">
            <form method="post" action="<?php echo app_url('attendance/machine_link.php'); ?>" class="bio-auto-link-form"
                  onsubmit="return confirm('Auto-link unmatched punches using saved map / employee codes?\n\nAttendance Report will NOT change.');">
                <input type="hidden" name="action" value="auto_link">
                <input type="hidden" name="redirect" value="<?php echo htmlspecialchars('attendance/machine_logs.php?' . $redirectQs); ?>">
                <button type="submit" class="btn-secondary">
                    <i class="fa-solid fa-wand-magic-sparkles"></i> Auto-link unmatched
                </button>
            </form>
            <span class="bio-meta">Machine code (e.g. CO72036) → pick local employee → all that code’s punches link</span>
        </div>
        <?php endif; ?>

        <form method="get" class="bio-filter-bar">
            <div class="form-group">
                <label>Machine</label>
                <select name="machine_id" class="form-control">
                    <option value="0">All machines</option>
                    <?php foreach ($machines as $m): ?>
                        <option value="<?php echo (int) $m['id']; ?>" <?php echo $machineId === (int) $m['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($m['machine_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>From</label>
                <input type="date" name="from_date" class="form-control" value="<?php echo htmlspecialchars($fromDate); ?>">
            </div>
            <div class="form-group">
                <label>To</label>
                <input type="date" name="to_date" class="form-control" value="<?php echo htmlspecialchars($toDate); ?>">
            </div>
            <div class="form-group">
                <label>In / Out</label>
                <select name="punch_type" class="form-control">
                    <option value="">All</option>
                    <option value="in" <?php echo $punchType === 'in' ? 'selected' : ''; ?>>In</option>
                    <option value="out" <?php echo $punchType === 'out' ? 'selected' : ''; ?>>Out</option>
                </select>
            </div>
            <div class="form-group">
                <label>Employee Link</label>
                <select name="match" class="form-control">
                    <option value="">All</option>
                    <option value="yes" <?php echo $match === 'yes' ? 'selected' : ''; ?>>Linked</option>
                    <option value="no" <?php echo $match === 'no' ? 'selected' : ''; ?>>Not linked</option>
                </select>
            </div>
            <div class="form-group bio-filter-search">
                <label>Search</label>
                <input type="text" name="q" class="form-control" value="<?php echo htmlspecialchars($q); ?>"
                       placeholder="Machine code / Name / Bio ID">
            </div>
            <div class="form-group bio-filter-actions">
                <label>&nbsp;</label>
                <div class="bio-filter-btns">
                    <button type="submit" class="btn-primary"><i class="fa-solid fa-filter"></i> Filter</button>
                    <a href="<?php echo app_url('attendance/machine_logs.php'); ?>" class="btn-secondary">Reset</a>
                </div>
            </div>
        </form>

        <div class="table-wrap bio-table-wrap">
            <table class="data-table bio-logs-table" style="width:100%;">
                <thead>
                    <tr>
                        <th width="48">Sr</th>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Type</th>
                        <th>Machine Code</th>
                        <th>Linked Employee</th>
                        <th>Machine</th>
                        <?php if ($canEdit): ?><th width="280">Link Employee</th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$rows): ?>
                    <tr>
                        <td colspan="<?php echo $canEdit ? 8 : 7; ?>" class="bio-empty">
                            No punches for this filter.
                            <a href="<?php echo app_url('attendance/machine_sync.php'); ?>">Run Sync</a>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($rows as $i => $r): ?>
                        <?php
                        $pt = strtolower((string) $r['punch_type']) === 'out' ? 'out' : 'in';
                        $matched = (int) ($r['employee_id'] ?? 0) > 0;
                        $bioCode = trim((string) ($r['biometric_user_id'] ?: $r['employee_code']));
                        ?>
                        <tr>
                            <td><?php echo $offset + $i + 1; ?></td>
                            <td><?php echo htmlspecialchars(date('d-m-Y', strtotime($r['attendance_date']))); ?></td>
                            <td class="bio-time"><?php echo htmlspecialchars(substr((string) $r['punch_time'], 0, 8)); ?></td>
                            <td>
                                <span class="bio-punch bio-punch-<?php echo $pt; ?>">
                                    <?php echo $pt === 'out' ? 'OUT' : 'IN'; ?>
                                </span>
                            </td>
                            <td>
                                <div class="bio-emp-code"><?php echo htmlspecialchars((string) $r['employee_code']); ?></div>
                                <?php if (!empty($r['employee_name']) && !$matched): ?>
                                    <div class="bio-emp-name"><?php echo htmlspecialchars($r['employee_name']); ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($matched): ?>
                                    <div class="bio-emp-code"><?php echo htmlspecialchars((string) ($r['local_emp_code'] ?: '')); ?></div>
                                    <div class="bio-emp-name"><?php echo htmlspecialchars((string) ($r['local_emp_name'] ?: $r['employee_name'])); ?></div>
                                    <span class="bio-linked-ok">Linked</span>
                                <?php else: ?>
                                    <span class="bio-unmatched">Not linked</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php echo htmlspecialchars((string) ($r['machine_name'] ?? '—')); ?>
                                <div class="bio-meta"><?php echo htmlspecialchars((string) ($r['records_source'] ?? '')); ?></div>
                            </td>
                            <?php if ($canEdit): ?>
                            <td>
                                <form method="post" action="<?php echo app_url('attendance/machine_link.php'); ?>" class="bio-link-form">
                                    <input type="hidden" name="action" value="link">
                                    <input type="hidden" name="log_id" value="<?php echo (int) $r['id']; ?>">
                                    <input type="hidden" name="biometric_code" value="<?php echo htmlspecialchars($bioCode); ?>">
                                    <input type="hidden" name="apply_same_code" value="1">
                                    <input type="hidden" name="redirect" value="<?php echo htmlspecialchars('attendance/machine_logs.php?' . $redirectQs); ?>">
                                    <select name="employee_id" class="form-control bio-link-select" required>
                                        <option value="">— Select employee —</option>
                                        <?php foreach ($employees as $emp): ?>
                                            <option value="<?php echo (int) $emp['id']; ?>"
                                                <?php echo $matched && (int) $r['employee_id'] === (int) $emp['id'] ? 'selected' : ''; ?>>
                                                <?php
                                                echo htmlspecialchars($emp['employee_code'] . ' — ' . $emp['employee_name']);
                                                if (!empty($emp['department_name'])) {
                                                    echo ' (' . htmlspecialchars($emp['department_name']) . ')';
                                                }
                                                ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="bio-link-actions">
                                        <button type="submit" class="btn-primary bio-btn" title="Link all punches with this machine code">
                                            <i class="fa-solid fa-link"></i> Link
                                        </button>
                                        <?php if ($matched): ?>
                                        <button type="submit" class="btn-secondary bio-btn" formnovalidate
                                                onclick="var a=this.form.querySelector('[name=action]'); if(a) a.value='unlink'; return confirm('Unlink this punch only?');"
                                                title="Unlink">
                                            <i class="fa-solid fa-unlink"></i>
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                </form>
                            </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPages > 1): ?>
            <div class="bio-pager">
                <span>Page <?php echo $page; ?> of <?php echo $totalPages; ?></span>
                <div class="bio-pager-links">
                    <?php if ($page > 1): ?>
                        <a class="btn-secondary" href="<?php echo htmlspecialchars(machineLogsUrl(['page' => $page - 1])); ?>">Prev</a>
                    <?php endif; ?>
                    <?php if ($page < $totalPages): ?>
                        <a class="btn-secondary" href="<?php echo htmlspecialchars(machineLogsUrl(['page' => $page + 1])); ?>">Next</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

<script>
(function () {
    <?php if ($toast !== ''): ?>
    if (typeof toastr !== 'undefined') {
        toastr.options = { closeButton: true, progressBar: true, positionClass: 'toast-top-right', timeOut: 4500 };
        toastr.<?php echo $toastType === 'error' ? 'error' : 'success'; ?>(<?php echo json_encode($toast); ?>);
    } else {
        alert(<?php echo json_encode($toast); ?>);
    }
    <?php endif; ?>
})();
</script>
