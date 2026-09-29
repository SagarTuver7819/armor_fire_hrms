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
$filters = ['employee_id' => $empId, 'with_hr_reply' => true];
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
            <p>Type-wise list with status and HR department response.</p>
        </div>

        <div class="ev-type-filters" style="margin-bottom:14px;">
            <a class="ev-type-chip<?php echo $typeFilter === '' ? ' is-active' : ''; ?>" href="<?php echo app_url('employee/voice/my.php'); ?>">All</a>
            <?php foreach ($types as $t): ?>
                <a class="ev-type-chip<?php echo $typeFilter === $t['key'] ? ' is-active' : ''; ?>"
                   href="<?php echo app_url('employee/voice/my.php?type=' . urlencode($t['key'])); ?>"
                   style="--ev-accent:<?php echo htmlspecialchars($t['color']); ?>;">
                    <i class="fa-solid <?php echo htmlspecialchars($t['icon']); ?>"></i>
                    <?php echo htmlspecialchars($t['short']); ?>
                </a>
            <?php endforeach; ?>
        </div>

        <div class="table-wrap">
            <table class="data-table ev-my-table">
                <thead>
                    <tr>
                        <th>Ticket</th>
                        <th>Type</th>
                        <th>Subject</th>
                        <th>Priority</th>
                        <th>Status</th>
                        <th>HR Response</th>
                        <th>Submitted</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$rows): ?>
                    <tr><td colspan="8" class="ev-empty-cell">No submissions yet.</td></tr>
                <?php else: foreach ($rows as $r):
                    $mt = (string) $r['module_type'];
                    $meta = $types[$mt] ?? null;
                    $canMod = evEmployeeCanModifyTicket($r);
                    $hrReply = trim((string) ($r['hr_reply'] ?? ''));
                    $hrAt = (string) ($r['hr_reply_at'] ?? '');
                    $hrPreview = $hrReply !== ''
                        ? ((strlen($hrReply) > 90) ? (substr($hrReply, 0, 87) . '…') : $hrReply)
                        : '';
                ?>
                    <tr>
                        <td><strong class="ev-ticket-no"><?php echo htmlspecialchars((string) $r['ticket_no']); ?></strong></td>
                        <td>
                            <span class="status-badge" style="background:<?php echo htmlspecialchars($meta['bg'] ?? '#eee'); ?>;color:<?php echo htmlspecialchars($meta['color'] ?? '#333'); ?>;">
                                <?php echo htmlspecialchars($meta['short'] ?? $mt); ?>
                            </span>
                        </td>
                        <td>
                            <div class="ev-subj"><?php echo htmlspecialchars((string) $r['subject']); ?></div>
                            <div class="ev-subj-cat"><?php echo htmlspecialchars((string) ($r['category'] ?? '')); ?></div>
                        </td>
                        <td><span class="status-badge" style="<?php echo evPriorityBadgeStyle($r['priority']); ?>"><?php echo htmlspecialchars((string) $r['priority']); ?></span></td>
                        <td><span class="status-badge" style="<?php echo evStatusBadgeStyle($r['status']); ?>"><?php echo htmlspecialchars((string) $r['status']); ?></span></td>
                        <td class="ev-hr-cell">
                            <?php if ($hrPreview !== ''): ?>
                                <div class="ev-hr-reply">
                                    <i class="fa-solid fa-reply"></i>
                                    <span><?php echo htmlspecialchars($hrPreview); ?></span>
                                </div>
                                <?php if ($hrAt !== ''): ?>
                                    <div class="ev-hr-at"><?php echo htmlspecialchars(formatDateTimeDisplay($hrAt)); ?></div>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="ev-hr-pending">Awaiting HR response</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo !empty($r['submitted_at']) ? htmlspecialchars(formatDateTimeDisplay($r['submitted_at'])) : '—'; ?></td>
                        <td>
                            <div class="ev-row-actions">
                                <a class="btn-ghost ev-view-btn"
                                   href="<?php echo app_url('employee/voice/view.php?id=' . (int) $r['id']); ?>">View</a>
                                <?php if ($canMod): ?>
                                <a class="btn-ghost ev-view-btn"
                                   href="<?php echo app_url('employee/voice/submit.php?id=' . (int) $r['id']); ?>">
                                    <i class="fa-solid fa-pen"></i> Edit
                                </a>
                                <form method="POST" action="<?php echo app_url('employee/voice/withdraw.php'); ?>"
                                      class="ev-inline-form"
                                      onsubmit="return confirm('Withdraw this ticket? You cannot undo this.');">
                                    <input type="hidden" name="id" value="<?php echo (int) $r['id']; ?>">
                                    <button type="submit" class="btn-ghost ev-view-btn ev-withdraw-btn">
                                        <i class="fa-solid fa-trash-can"></i> Withdraw
                                    </button>
                                </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
