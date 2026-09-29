<?php
/**
 * Employee Voice — Admin / HR list + dashboard
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
$counts = evDashboardCounts();
$types = evModuleTypes();

$typeFilter = strtoupper(trim((string) ($_GET['type'] ?? '')));
$statusFilter = trim((string) ($_GET['status'] ?? ''));
$q = trim((string) ($_GET['q'] ?? ''));
$filters = [];
if (isset($types[$typeFilter])) {
    $filters['module_type'] = $typeFilter;
}
if ($statusFilter !== '') {
    $filters['status'] = $statusFilter;
}
if ($q !== '') {
    $filters['q'] = $q;
}
$rows = evListTickets($filters);

$pageTitle = 'Employee Voice';
$useSidebar = true;
$sidebarMode = 'employee_voice';
$sidebarActive = 'ev_admin';

require_once __DIR__ . '/../includes/header.php';
?>

<main class="dashboard-main">
    <div class="page-toolbar flex-between">
        <a href="<?php echo app_url('dashboard.php'); ?>" class="back-link">
            <i class="fa-solid fa-arrow-left"></i> Dashboard
        </a>
    </div>

    <div class="form-page-card">
        <div class="form-page-header">
            <h1><i class="fa-solid fa-comments" style="color:#d2232a;"></i> Employee Voice</h1>
            <p>Admin view — grievances, suggestions &amp; safety reports with attachments.</p>
        </div>

        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:12px;margin-bottom:18px;">
            <div style="border:1px solid #e5e7eb;border-radius:12px;padding:14px;background:#fff;">
                <div style="font-size:12px;color:#64748b;font-weight:700;">TOTAL</div>
                <div style="font-size:26px;font-weight:800;"><?php echo (int) $counts['total']; ?></div>
            </div>
            <div style="border:1px solid #e5e7eb;border-radius:12px;padding:14px;background:#fff;">
                <div style="font-size:12px;color:#64748b;font-weight:700;">OPEN</div>
                <div style="font-size:26px;font-weight:800;color:#b45309;"><?php echo (int) $counts['open']; ?></div>
            </div>
            <div style="border:1px solid #fecaca;border-radius:12px;padding:14px;background:#fef2f2;">
                <div style="font-size:12px;color:#b91c1c;font-weight:700;">CRITICAL / HIGH</div>
                <div style="font-size:26px;font-weight:800;color:#b91c1c;"><?php echo (int) $counts['critical']; ?></div>
            </div>
            <?php foreach ($types as $t): ?>
            <a href="<?php echo app_url('employee_voice/index.php?type=' . urlencode($t['key'])); ?>"
               style="text-decoration:none;border:1px solid #e5e7eb;border-radius:12px;padding:14px;background:<?php echo htmlspecialchars($t['bg']); ?>;">
                <div style="font-size:12px;color:<?php echo htmlspecialchars($t['color']); ?>;font-weight:700;"><?php echo htmlspecialchars(strtoupper($t['short'])); ?></div>
                <div style="font-size:26px;font-weight:800;color:<?php echo htmlspecialchars($t['color']); ?>;">
                    <?php echo (int) ($counts['by_module'][$t['key']] ?? 0); ?>
                </div>
            </a>
            <?php endforeach; ?>
        </div>

        <form method="GET" class="leave-rpt-filters" style="margin-bottom:14px;">
            <div class="form-grid form-grid-4">
                <div class="form-group">
                    <label>Type</label>
                    <select name="type" class="form-control" onchange="this.form.submit()">
                        <option value="">All</option>
                        <?php foreach ($types as $t): ?>
                            <option value="<?php echo htmlspecialchars($t['key']); ?>" <?php echo $typeFilter === $t['key'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($t['label']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <input type="text" name="status" class="form-control" value="<?php echo htmlspecialchars($statusFilter); ?>" placeholder="e.g. Submitted">
                </div>
                <div class="form-group">
                    <label>Search</label>
                    <input type="text" name="q" class="form-control" value="<?php echo htmlspecialchars($q); ?>" placeholder="Ticket / Name / Subject">
                </div>
                <div class="form-group" style="display:flex;align-items:flex-end;">
                    <button type="submit" class="btn-primary"><i class="fa-solid fa-magnifying-glass"></i> Filter</button>
                </div>
            </div>
        </form>

        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Ticket</th>
                        <th>Type</th>
                        <th>Employee</th>
                        <th>Department</th>
                        <th>Subject</th>
                        <th>Priority</th>
                        <th>Status</th>
                        <th>Submitted</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$rows): ?>
                    <tr><td colspan="9" style="text-align:center;padding:24px;color:#64748b;">No tickets found.</td></tr>
                <?php else: foreach ($rows as $r):
                    $mt = (string) $r['module_type'];
                    $meta = $types[$mt] ?? null;
                    $showName = true;
                    if (($r['confidentiality'] ?? '') === 'Anonymous' && !isAdmin() && !isHR()) {
                        // still show for admin/HR managers — they need identity for investigation
                    }
                ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars((string) $r['ticket_no']); ?></strong></td>
                        <td>
                            <span class="status-badge" style="background:<?php echo htmlspecialchars($meta['bg'] ?? '#eee'); ?>;color:<?php echo htmlspecialchars($meta['color'] ?? '#333'); ?>;">
                                <?php echo htmlspecialchars($meta['short'] ?? $mt); ?>
                            </span>
                        </td>
                        <td>
                            <?php if (($r['confidentiality'] ?? '') === 'Anonymous'): ?>
                                <em style="color:#64748b;">Anonymous</em>
                                <div style="font-size:11px;color:#94a3b8;"><?php echo htmlspecialchars(trim(($r['employee_code'] ?? '') . ' ' . ($r['employee_name'] ?? ''))); ?> <small>(admin only)</small></div>
                            <?php else: ?>
                                <strong><?php echo htmlspecialchars((string) ($r['employee_name'] ?: '—')); ?></strong>
                                <div style="font-size:11px;color:#64748b;"><?php echo htmlspecialchars((string) ($r['employee_code'] ?: '')); ?></div>
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars((string) ($r['department_name'] ?: '—')); ?></td>
                        <td><?php echo htmlspecialchars((string) $r['subject']); ?></td>
                        <td><span class="status-badge" style="<?php echo evPriorityBadgeStyle($r['priority']); ?>"><?php echo htmlspecialchars((string) $r['priority']); ?></span></td>
                        <td><span class="status-badge" style="<?php echo evStatusBadgeStyle($r['status']); ?>"><?php echo htmlspecialchars((string) $r['status']); ?></span></td>
                        <td><?php echo !empty($r['submitted_at']) ? htmlspecialchars(formatDateTimeDisplay($r['submitted_at'])) : '—'; ?></td>
                        <td>
                            <a class="btn-ghost" style="padding:5px 10px;font-size:12px;"
                               href="<?php echo app_url('employee_voice/view.php?id=' . (int) $r['id']); ?>">Open</a>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
