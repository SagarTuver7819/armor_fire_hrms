<?php
/**
 * Employee Voice — hub (3 cards) + My Submissions grid
 */

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/employee_voice_helper.php';

requireLogin();
if (!canSubmitEmployeeVoice()) {
    header('Location: ' . app_url('employee/dashboard.php'));
    exit;
}

$empId = (int) $_SESSION['employee_id'];
ensureEmployeeVoiceTables();
$types = evModuleTypes();

$typeFilter = strtoupper(trim((string) ($_GET['type'] ?? '')));
$filters = [
    'employee_id' => $empId,
    'with_hr_reply' => true,
];
if (isset($types[$typeFilter])) {
    $filters['module_type'] = $typeFilter;
}
$myRows = evListTickets($filters);

$pageTitle = 'Employee Voice';
$useSidebar = true;
$sidebarMode = 'workspace';
$sidebarActive = 'employee_voice';

require_once __DIR__ . '/../../includes/header.php';
?>

<main class="dashboard-main">
    <div class="page-toolbar flex-between">
        <a href="<?php echo app_url('employee/dashboard.php'); ?>" class="back-link">
            <i class="fa-solid fa-arrow-left"></i> Back to Home
        </a>
        <a href="#my-submissions" class="btn-secondary">
            <i class="fa-solid fa-inbox"></i> My Submissions
        </a>
    </div>

    <div class="form-page-card">
        <div class="form-page-header">
            <h1><i class="fa-solid fa-comments" style="color:#d2232a;"></i> Employee Voice</h1>
            <p>Submit a grievance, share a suggestion, or report a safety concern. You can only view your own tickets.</p>
        </div>

        <div class="ev-hub-cards">
            <?php foreach ($types as $t): ?>
            <a href="<?php echo app_url('employee/voice/submit.php?type=' . urlencode($t['key'])); ?>"
               class="ev-hub-card"
               style="--ev-accent:<?php echo htmlspecialchars($t['color']); ?>;--ev-bg:<?php echo htmlspecialchars($t['bg']); ?>;">
                <div class="ev-hub-card-ico">
                    <i class="fa-solid <?php echo htmlspecialchars($t['icon']); ?>"></i>
                </div>
                <div class="ev-hub-card-title"><?php echo htmlspecialchars($t['label']); ?></div>
                <div class="ev-hub-card-desc">
                    <?php
                    if ($t['key'] === 'GRIEVANCE') {
                        echo 'Workplace complaints, relations, facilities &amp; discipline';
                    } elseif ($t['key'] === 'SUGGESTION') {
                        echo 'Cost saving, productivity, quality &amp; improvement ideas';
                    } else {
                        echo 'Unsafe machines, PPE, fire hazards, near misses';
                    }
                    ?>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="form-page-card ev-my-grid-card" id="my-submissions">
        <div class="form-page-header flex-between" style="align-items:flex-start;gap:12px;flex-wrap:wrap;">
            <div>
                <h1><i class="fa-solid fa-list-check" style="color:#d2232a;"></i> My Submissions</h1>
                <p>Type-wise list with status and HR department response. Click View for full details.</p>
            </div>
            <div class="ev-type-filters">
                <a class="ev-type-chip<?php echo $typeFilter === '' ? ' is-active' : ''; ?>"
                   href="<?php echo app_url('employee/voice/index.php#my-submissions'); ?>">All</a>
                <?php foreach ($types as $t): ?>
                <a class="ev-type-chip<?php echo $typeFilter === $t['key'] ? ' is-active' : ''; ?>"
                   href="<?php echo app_url('employee/voice/index.php?type=' . urlencode($t['key']) . '#my-submissions'); ?>"
                   style="--ev-accent:<?php echo htmlspecialchars($t['color']); ?>;">
                    <i class="fa-solid <?php echo htmlspecialchars($t['icon']); ?>"></i>
                    <?php echo htmlspecialchars($t['short']); ?>
                </a>
                <?php endforeach; ?>
            </div>
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
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$myRows): ?>
                    <tr>
                        <td colspan="8" class="ev-empty-cell">
                            No submissions yet. Choose a card above to raise your first ticket.
                        </td>
                    </tr>
                <?php else: foreach ($myRows as $r):
                    $mt = (string) $r['module_type'];
                    $meta = $types[$mt] ?? null;
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
                                <i class="fa-solid <?php echo htmlspecialchars($meta['icon'] ?? 'fa-ticket'); ?>"></i>
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
                            <a class="btn-ghost ev-view-btn"
                               href="<?php echo app_url('employee/voice/view.php?id=' . (int) $r['id']); ?>">
                                View
                            </a>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
