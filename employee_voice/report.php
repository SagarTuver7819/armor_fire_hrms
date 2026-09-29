<?php
/**
 * Employee Voice Report — Admin / HR
 * Category-wise: Grievance · Suggestions · Safety
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permission_helper.php';
require_once __DIR__ . '/../includes/employee_voice_helper.php';
require_once __DIR__ . '/../includes/employee_voice_report_helper.php';

requireLogin();
if (!canManageEmployeeVoice()) {
    header('Location: ' . app_url('dashboard.php'));
    exit;
}

$data = evReportLoadData();
$types = $data['types'] ?? [];
$rows = $data['rows'] ?? [];
$departments = $data['departments'] ?? [];
$statusOptions = $data['status_options'] ?? [];
$query = $data['query'] ?? [];
$m = is_array($data['meta'] ?? null) ? $data['meta'] : [];

$typeFilter = (string) ($m['type'] ?? '');
$statusFilter = (string) ($m['status'] ?? '');
$deptId = (int) ($m['department_id'] ?? 0);
$dateFrom = (string) ($m['date_from'] ?? '');
$dateTo = (string) ($m['date_to'] ?? '');
$q = (string) ($m['q'] ?? '');
$byType = is_array($m['by_type'] ?? null) ? $m['by_type'] : ['GRIEVANCE' => 0, 'SUGGESTION' => 0, 'SAFETY' => 0];
$openCount = (int) ($m['open'] ?? 0);
$closedCount = (int) ($m['closed'] ?? 0);
$totalCount = isset($m['total']) ? (int) $m['total'] : count($rows);

// All-time category totals (ignore date filter) for top tabs clarity
$allTime = ['GRIEVANCE' => 0, 'SUGGESTION' => 0, 'SAFETY' => 0, 'total' => 0];
try {
    $c = getDBConnection();
    ensureEmployeeVoiceTables($c);
    $rs = $c->query("SELECT module_type, COUNT(*) AS c FROM ev_ticket WHERE is_deleted = 0 GROUP BY module_type");
    if ($rs) {
        while ($row = $rs->fetch_assoc()) {
            $mt = strtoupper((string) ($row['module_type'] ?? ''));
            $cnt = (int) ($row['c'] ?? 0);
            if (isset($allTime[$mt])) {
                $allTime[$mt] = $cnt;
            }
            $allTime['total'] += $cnt;
        }
    }
    $c->close();
} catch (Throwable $e) {
    // keep zeros
}

$excelUrl = evReportQueryUrl('employee_voice/report_excel.php', $query);
$printUrl = evReportQueryUrl('employee_voice/report_print.php', $query);

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
        <div class="toolbar-actions" style="display:flex;gap:8px;flex-wrap:wrap;">
            <a href="<?php echo htmlspecialchars($printUrl); ?>" class="btn-secondary" target="_blank" rel="noopener">
                <i class="fa-solid fa-print"></i> Print / PDF
            </a>
            <a href="<?php echo htmlspecialchars($excelUrl); ?>" class="btn-primary">
                <i class="fa-solid fa-file-excel"></i> Export Excel
            </a>
        </div>
    </div>

    <div class="form-page-card">
        <div class="form-page-header">
            <h1><i class="fa-solid fa-chart-column" style="color:#d2232a;"></i> Employee Voice Report</h1>
            <p>Admin / HR report — employee complaints by Grievance, Suggestions &amp; Safety</p>
        </div>

        <div class="ev-rpt-type-tabs">
            <a href="<?php echo htmlspecialchars(evReportQueryUrl('employee_voice/report.php', $query, ['type' => ''])); ?>"
               class="ev-rpt-type-tab <?php echo $typeFilter === '' ? 'is-active' : ''; ?>">
                <i class="fa-solid fa-list"></i>
                <span>All</span>
                <strong><?php echo (int) $totalCount; ?></strong>
            </a>
            <?php foreach ($types as $t): ?>
            <a href="<?php echo htmlspecialchars(evReportQueryUrl('employee_voice/report.php', $query, ['type' => $t['key']])); ?>"
               class="ev-rpt-type-tab <?php echo $typeFilter === $t['key'] ? 'is-active' : ''; ?>"
               style="--ev-c:<?php echo htmlspecialchars($t['color']); ?>;--ev-bg:<?php echo htmlspecialchars($t['bg']); ?>;">
                <i class="fa-solid <?php echo htmlspecialchars($t['icon']); ?>" style="color:<?php echo htmlspecialchars($t['color']); ?>;"></i>
                <span><?php echo htmlspecialchars($t['short']); ?></span>
                <strong style="color:<?php echo htmlspecialchars($t['color']); ?>;"><?php echo (int) ($byType[$t['key']] ?? 0); ?></strong>
            </a>
            <?php endforeach; ?>
        </div>

        <?php if ($allTime['total'] > 0 && $totalCount === 0): ?>
        <div style="margin:0 0 14px;padding:10px 14px;border-radius:10px;background:#fff7ed;border:1px solid #fed7aa;color:#9a3412;font-weight:700;">
            <i class="fa-solid fa-circle-info"></i>
            Selected date range ma 0 tickets che. Database ma total <?php echo (int) $allTime['total']; ?> tickets available che —
            date range badli ne “Show Report” dabavo.
        </div>
        <?php elseif ($allTime['total'] === 0): ?>
        <div style="margin:0 0 14px;padding:10px 14px;border-radius:10px;background:#f8fafc;border:1px solid #e2e8f0;color:#475569;font-weight:700;">
            <i class="fa-solid fa-inbox"></i>
            Haji koi Employee Voice ticket submit thayelo nathi (Grievance / Suggestions / Safety).
        </div>
        <?php endif; ?>

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
                <div class="leave-rpt-kpi-value"><?php echo (int) $totalCount; ?></div>
                <div class="leave-rpt-kpi-sub"><?php echo htmlspecialchars($dateFrom); ?> → <?php echo htmlspecialchars($dateTo); ?></div>
            </div>
            <div class="leave-rpt-kpi kpi-amber">
                <div class="leave-rpt-kpi-label">Open / In Progress</div>
                <div class="leave-rpt-kpi-value"><?php echo $openCount; ?></div>
                <div class="leave-rpt-kpi-sub">Needs attention</div>
            </div>
            <div class="leave-rpt-kpi kpi-green">
                <div class="leave-rpt-kpi-label">Closed / Resolved</div>
                <div class="leave-rpt-kpi-value green"><?php echo $closedCount; ?></div>
                <div class="leave-rpt-kpi-sub">Completed</div>
            </div>
            <?php foreach ($types as $t): ?>
            <div class="leave-rpt-kpi" style="border-color:<?php echo htmlspecialchars($t['color']); ?>;background:<?php echo htmlspecialchars($t['bg']); ?>;">
                <div class="leave-rpt-kpi-label" style="color:<?php echo htmlspecialchars($t['color']); ?>;">
                    <i class="fa-solid <?php echo htmlspecialchars($t['icon']); ?>"></i>
                    <?php echo htmlspecialchars($t['short']); ?>
                </div>
                <div class="leave-rpt-kpi-value" style="color:<?php echo htmlspecialchars($t['color']); ?>;">
                    <?php echo (int) ($byType[$t['key']] ?? 0); ?>
                </div>
                <div class="leave-rpt-kpi-sub">Category count</div>
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

<style>
.ev-rpt-type-tabs {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 10px;
    margin: 0 0 16px;
}
.ev-rpt-type-tab {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px 14px;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    background: #fff;
    text-decoration: none;
    color: #334155;
    font-weight: 700;
    transition: .15s ease;
}
.ev-rpt-type-tab span { flex: 1; }
.ev-rpt-type-tab strong { font-size: 18px; }
.ev-rpt-type-tab:hover { border-color: #cbd5e1; box-shadow: 0 4px 14px rgba(15,23,42,.06); }
.ev-rpt-type-tab.is-active {
    background: var(--ev-bg, #fef2f2);
    border-color: var(--ev-c, #dc2626);
    color: var(--ev-c, #b91c1c);
}
@media (max-width: 900px) {
    .ev-rpt-type-tabs { grid-template-columns: 1fr 1fr; }
}
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
