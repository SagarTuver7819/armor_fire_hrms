<?php
/**
 * Employee Voice Report — Admin / HR
 * Grievance · Suggestions · Safety
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permission_helper.php';
require_once __DIR__ . '/../includes/employee_voice_helper.php';

requireLogin();
if (!canManageEmployeeVoice()) {
    header('Location: ' . app_url('dashboard.php'));
    exit;
}

ensureEmployeeVoiceTables();
$types = evModuleTypes();

$typeFilter = strtoupper(trim((string) ($_GET['type'] ?? '')));
$statusFilter = trim((string) ($_GET['status'] ?? ''));
$deptId = (int) ($_GET['department_id'] ?? 0);
$dateFrom = trim((string) ($_GET['date_from'] ?? ''));
$dateTo = trim((string) ($_GET['date_to'] ?? ''));
$q = trim((string) ($_GET['q'] ?? ''));

if ($dateFrom === '' && $dateTo === '') {
    $dateFrom = date('Y-m-01');
    $dateTo = date('Y-m-d');
}

$filters = [];
if (isset($types[$typeFilter])) {
    $filters['module_type'] = $typeFilter;
}
if ($statusFilter !== '') {
    $filters['status'] = $statusFilter;
}
if ($deptId > 0) {
    $filters['department_id'] = $deptId;
}
if ($dateFrom !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
    $filters['date_from'] = $dateFrom;
}
if ($dateTo !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
    $filters['date_to'] = $dateTo;
}
if ($q !== '') {
    $filters['q'] = $q;
}

$conn = getDBConnection();
$departments = [];
$dres = $conn->query('SELECT id, department_name FROM departments WHERE status = 1 ORDER BY sort_order ASC, department_name ASC');
if ($dres) {
    while ($r = $dres->fetch_assoc()) {
        $departments[] = $r;
    }
}

$rows = evListTickets($filters, $conn);
$conn->close();

$byType = ['GRIEVANCE' => 0, 'SUGGESTION' => 0, 'SAFETY' => 0];
$byStatus = [];
$openCount = 0;
$closedCount = 0;
foreach ($rows as $r) {
    $mt = strtoupper((string) ($r['module_type'] ?? ''));
    if (isset($byType[$mt])) {
        $byType[$mt]++;
    }
    $st = trim((string) ($r['status'] ?? ''));
    if ($st !== '') {
        $byStatus[$st] = ($byStatus[$st] ?? 0) + 1;
    }
    if (in_array($st, ['Closed', 'Withdrawn', 'Resolved', 'Verified'], true)) {
        $closedCount++;
    } else {
        $openCount++;
    }
}

$statusOptions = [];
foreach (array_keys($types) as $tk) {
    foreach (evStatusesByModule($tk) as $st) {
        $statusOptions[$st] = true;
    }
}
$statusOptions = array_keys($statusOptions);
sort($statusOptions);

$queryBase = [
    'type' => $typeFilter,
    'status' => $statusFilter,
    'department_id' => $deptId,
    'date_from' => $dateFrom,
    'date_to' => $dateTo,
    'q' => $q,
];
$excelUrl = app_url('employee_voice/report_excel.php?' . http_build_query(array_filter(
    $queryBase,
    static function ($v) {
        return $v !== '' && $v !== 0 && $v !== '0';
    }
)));

$pageTitle = 'Employee Voice Report';
$useSidebar = true;
$sidebarMode = 'employee_voice';
$sidebarActive = 'ev_report';

require_once __DIR__ . '/../includes/header.php';
?>

<main class="dashboard-main">
    <div class="page-toolbar flex-between">
        <a href="<?php echo app_url('employee_voice/index.php'); ?>" class="back-link">
            <i class="fa-solid fa-arrow-left"></i> Employee Voice
        </a>
        <div class="toolbar-actions">
            <a href="<?php echo htmlspecialchars($excelUrl); ?>" class="btn-secondary">
                <i class="fa-solid fa-file-excel"></i> Export Excel
            </a>
        </div>
    </div>

    <div class="form-page-card">
        <div class="form-page-header">
            <h1><i class="fa-solid fa-chart-column" style="color:#d2232a;"></i> Employee Voice Report</h1>
            <p>Employee-submitted complaints report — Grievance, Suggestions &amp; Safety</p>
        </div>

        <div class="leave-rpt-rules" style="margin-bottom:14px;">
            <a href="<?php echo app_url('employee_voice/report.php?' . http_build_query(array_merge($queryBase, ['type' => '']))); ?>"
               class="leave-rpt-rule" style="text-decoration:none;<?php echo $typeFilter === '' ? 'background:#fef2f2;border-color:#fecaca;color:#b91c1c;font-weight:800;' : ''; ?>">
                <i class="fa-solid fa-list"></i> All Types
            </a>
            <?php foreach ($types as $t): ?>
            <a href="<?php echo app_url('employee_voice/report.php?' . http_build_query(array_merge($queryBase, ['type' => $t['key']]))); ?>"
               class="leave-rpt-rule" style="text-decoration:none;<?php echo $typeFilter === $t['key'] ? 'background:' . htmlspecialchars($t['bg']) . ';border-color:' . htmlspecialchars($t['color']) . ';color:' . htmlspecialchars($t['color']) . ';font-weight:800;' : ''; ?>">
                <i class="fa-solid <?php echo htmlspecialchars($t['icon']); ?>" style="color:<?php echo htmlspecialchars($t['color']); ?>;"></i>
                <?php echo htmlspecialchars($t['short']); ?>
            </a>
            <?php endforeach; ?>
        </div>

        <form method="get" class="employee-form leave-rpt-filters">
            <div class="form-grid form-grid-4">
                <div class="form-group">
                    <label>Type</label>
                    <select name="type" class="form-control">
                        <option value="">All (Grievance / Suggestions / Safety)</option>
                        <?php foreach ($types as $t): ?>
                            <option value="<?php echo htmlspecialchars($t['key']); ?>" <?php echo $typeFilter === $t['key'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($t['label']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Department</label>
                    <select name="department_id" class="form-control">
                        <option value="0">All Departments</option>
                        <?php foreach ($departments as $d): ?>
                            <option value="<?php echo (int) $d['id']; ?>" <?php echo $deptId === (int) $d['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($d['department_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <select name="status" class="form-control">
                        <option value="">All Status</option>
                        <?php foreach ($statusOptions as $st): ?>
                            <option value="<?php echo htmlspecialchars($st); ?>" <?php echo $statusFilter === $st ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($st); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Search</label>
                    <input type="text" name="q" class="form-control" value="<?php echo htmlspecialchars($q); ?>" placeholder="Ticket / Name / Subject">
                </div>
                <div class="form-group">
                    <label>From Date</label>
                    <input type="date" name="date_from" class="form-control" value="<?php echo htmlspecialchars($dateFrom); ?>">
                </div>
                <div class="form-group">
                    <label>To Date</label>
                    <input type="date" name="date_to" class="form-control" value="<?php echo htmlspecialchars($dateTo); ?>">
                </div>
                <div class="form-group" style="display:flex;align-items:flex-end;">
                    <button type="submit" class="btn-primary" style="width:100%;">
                        <i class="fa-solid fa-filter"></i> Show Report
                    </button>
                </div>
            </div>
        </form>

        <div class="leave-rpt-kpis">
            <div class="leave-rpt-kpi kpi-slate">
                <div class="leave-rpt-kpi-label">Total Tickets</div>
                <div class="leave-rpt-kpi-value"><?php echo count($rows); ?></div>
                <div class="leave-rpt-kpi-sub">In selected period</div>
            </div>
            <div class="leave-rpt-kpi kpi-amber">
                <div class="leave-rpt-kpi-label">Open / In Progress</div>
                <div class="leave-rpt-kpi-value"><?php echo (int) $openCount; ?></div>
                <div class="leave-rpt-kpi-sub">Needs attention</div>
            </div>
            <div class="leave-rpt-kpi kpi-green">
                <div class="leave-rpt-kpi-label">Closed / Resolved</div>
                <div class="leave-rpt-kpi-value green"><?php echo (int) $closedCount; ?></div>
                <div class="leave-rpt-kpi-sub">Completed</div>
            </div>
            <?php foreach ($types as $t): ?>
            <div class="leave-rpt-kpi" style="border-color:<?php echo htmlspecialchars($t['color']); ?>;background:<?php echo htmlspecialchars($t['bg']); ?>;">
                <div class="leave-rpt-kpi-label" style="color:<?php echo htmlspecialchars($t['color']); };">
                    <i class="fa-solid <?php echo htmlspecialchars($t['icon']); ?>"></i>
                    <?php echo htmlspecialchars($t['short']); ?>
                </div>
                <div class="leave-rpt-kpi-value" style="color:<?php echo htmlspecialchars($t['color']); ?>;">
                    <?php echo (int) ($byType[$t['key']] ?? 0); ?>
                </div>
                <div class="leave-rpt-kpi-sub">Employee complaints</div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="leave-rpt-table-wrap">
            <table class="leave-rpt-table">
                <thead>
                    <tr>
                        <th class="sr">Sr</th>
                        <th class="txt">Ticket No</th>
                        <th class="txt">Type</th>
                        <th class="txt">Code</th>
                        <th class="txt">Employee</th>
                        <th class="txt">Department</th>
                        <th class="txt">Category</th>
                        <th class="txt">Subject</th>
                        <th class="ctr">Priority</th>
                        <th class="ctr">Status</th>
                        <th class="ctr">Confidential</th>
                        <th class="date">Submitted</th>
                        <th class="actions"></th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$rows): ?>
                    <tr>
                        <td colspan="13">
                            <div class="leave-rpt-empty">
                                <i class="fa-regular fa-file-lines"></i>
                                No employee voice tickets found for selected filters.
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($rows as $i => $r):
                        $mt = (string) ($r['module_type'] ?? '');
                        $meta = $types[$mt] ?? null;
                        $isAnon = (($r['confidentiality'] ?? '') === 'Anonymous');
                    ?>
                    <tr>
                        <td class="sr"><?php echo $i + 1; ?></td>
                        <td class="emp-code"><?php echo htmlspecialchars((string) ($r['ticket_no'] ?? '')); ?></td>
                        <td class="txt">
                            <span class="status-badge" style="background:<?php echo htmlspecialchars($meta['bg'] ?? '#eee'); ?>;color:<?php echo htmlspecialchars($meta['color'] ?? '#333'); ?>;">
                                <?php echo htmlspecialchars($meta['short'] ?? $mt); ?>
                            </span>
                        </td>
                        <td class="emp-code"><?php echo htmlspecialchars((string) ($r['employee_code'] ?? '')); ?></td>
                        <td class="emp-name">
                            <?php if ($isAnon): ?>
                                <em style="color:#64748b;">Anonymous</em>
                                <div style="font-size:11px;color:#94a3b8;"><?php echo htmlspecialchars((string) ($r['employee_name'] ?? '')); ?></div>
                            <?php else: ?>
                                <?php echo htmlspecialchars((string) ($r['employee_name'] ?? '—')); ?>
                            <?php endif; ?>
                        </td>
                        <td class="txt"><?php echo htmlspecialchars((string) ($r['department_name'] ?? '—')); ?></td>
                        <td class="txt"><?php echo htmlspecialchars((string) ($r['category'] ?? '—')); ?></td>
                        <td class="txt"><?php echo htmlspecialchars((string) ($r['subject'] ?? '')); ?></td>
                        <td class="ctr"><span class="status-badge" style="<?php echo evPriorityBadgeStyle($r['priority'] ?? ''); ?>"><?php echo htmlspecialchars((string) ($r['priority'] ?? '')); ?></span></td>
                        <td class="ctr"><span class="status-badge" style="<?php echo evStatusBadgeStyle($r['status'] ?? ''); ?>"><?php echo htmlspecialchars((string) ($r['status'] ?? '')); ?></span></td>
                        <td class="ctr"><?php echo htmlspecialchars((string) ($r['confidentiality'] ?? 'Normal')); ?></td>
                        <td class="date"><?php echo !empty($r['submitted_at']) ? htmlspecialchars(formatDateTimeDisplay($r['submitted_at'])) : '—'; ?></td>
                        <td class="actions">
                            <a class="btn-ghost" href="<?php echo app_url('employee_voice/view.php?id=' . (int) $r['id']); ?>">
                                <i class="fa-solid fa-eye"></i> View
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
