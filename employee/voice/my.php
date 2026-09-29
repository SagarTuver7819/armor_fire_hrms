<?php
/**
 * Employee Voice — My Submissions (own tickets only)
 */

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/employee_helper.php';
require_once __DIR__ . '/../../includes/employee_voice_helper.php';

requireLogin();
if (!canSubmitEmployeeVoice()) {
    header('Location: ' . app_url('employee/dashboard.php'));
    exit;
}

$empId = (int) $_SESSION['employee_id'];
$typeFilter = strtoupper(trim((string) ($_GET['type'] ?? '')));
$filters = ['employee_id' => $empId];
if (isset(evModuleTypes()[$typeFilter])) {
    $filters['module_type'] = $typeFilter;
}
$rows = evListTickets($filters);
$types = evModuleTypes();

$pageTitle = 'My Submissions';
$useSidebar = true;
$sidebarMode = 'workspace';
$sidebarActive = 'employee_voice_my';

require_once __DIR__ . '/../../includes/header.php';
?>

<main class="dashboard-main">
    <div class="page-toolbar flex-between">
        <a href="<?php echo app_url('employee/voice/index.php'); ?>" class="back-link">
            <i class="fa-solid fa-arrow-left"></i> Employee Voice
        </a>
        <a href="<?php echo app_url('employee/voice/index.php'); ?>" class="btn-primary">
            <i class="fa-solid fa-plus"></i> New Submission
        </a>
    </div>

    <div class="form-page-card">
        <div class="form-page-header">
            <h1>My Submissions</h1>
            <p>Only your own tickets are listed here.</p>
        </div>

        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:14px;">
            <a class="btn-ghost" href="<?php echo app_url('employee/voice/my.php'); ?>">All</a>
            <?php foreach ($types as $t): ?>
                <a class="btn-ghost" href="<?php echo app_url('employee/voice/my.php?type=' . urlencode($t['key'])); ?>"
                   style="border-color:<?php echo htmlspecialchars($t['color']); ?>;color:<?php echo htmlspecialchars($t['color']); ?>;">
                    <?php echo htmlspecialchars($t['short']); ?>
                </a>
            <?php endforeach; ?>
        </div>

        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Ticket</th>
                        <th>Type</th>
                        <th>Subject</th>
                        <th>Priority</th>
                        <th>Status</th>
                        <th>Submitted</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$rows): ?>
                    <tr><td colspan="7" style="text-align:center;padding:24px;color:#64748b;">No submissions yet.</td></tr>
                <?php else: foreach ($rows as $r):
                    $mt = (string) $r['module_type'];
                    $meta = $types[$mt] ?? null;
                ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars((string) $r['ticket_no']); ?></strong></td>
                        <td>
                            <span class="status-badge" style="background:<?php echo htmlspecialchars($meta['bg'] ?? '#eee'); ?>;color:<?php echo htmlspecialchars($meta['color'] ?? '#333'); ?>;">
                                <?php echo htmlspecialchars($meta['short'] ?? $mt); ?>
                            </span>
                        </td>
                        <td><?php echo htmlspecialchars((string) $r['subject']); ?></td>
                        <td><span class="status-badge" style="<?php echo evPriorityBadgeStyle($r['priority']); ?>"><?php echo htmlspecialchars((string) $r['priority']); ?></span></td>
                        <td><span class="status-badge" style="<?php echo evStatusBadgeStyle($r['status']); ?>"><?php echo htmlspecialchars((string) $r['status']); ?></span></td>
                        <td><?php echo !empty($r['submitted_at']) ? htmlspecialchars(date('d M Y H:i', strtotime($r['submitted_at']))) : '—'; ?></td>
                        <td>
                            <a class="btn-ghost" style="padding:5px 10px;font-size:12px;"
                               href="<?php echo app_url('employee/voice/view.php?id=' . (int) $r['id']); ?>">View</a>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
