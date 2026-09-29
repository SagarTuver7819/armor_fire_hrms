<?php
/**
 * Employee Voice — view own ticket
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
$id = (int) ($_GET['id'] ?? 0);
$ticket = $id > 0 ? evGetTicket($id) : null;
if (!$ticket || (int) ($ticket['employee_id'] ?? 0) !== $empId) {
    header('Location: ' . app_url('employee/voice/my.php'));
    exit;
}

$types = evModuleTypes();
$meta = $types[$ticket['module_type']] ?? ['label' => $ticket['module_type'], 'color' => '#333', 'icon' => 'fa-ticket'];
$detail = $ticket['detail'] ?? [];
$created = isset($_GET['created']);

$pageTitle = $ticket['ticket_no'];
$useSidebar = true;
$sidebarMode = 'workspace';
$sidebarActive = 'employee_voice_my';

require_once __DIR__ . '/../../includes/header.php';
?>

<main class="dashboard-main">
    <div class="page-toolbar flex-between">
        <a href="<?php echo app_url('employee/voice/my.php'); ?>" class="back-link">
            <i class="fa-solid fa-arrow-left"></i> My Submissions
        </a>
    </div>

    <?php if ($created): ?>
    <div class="alert alert-success" style="margin-bottom:12px;">
        <i class="fa-solid fa-circle-check"></i>
        Ticket <strong><?php echo htmlspecialchars((string) $ticket['ticket_no']); ?></strong> submitted successfully.
    </div>
    <?php endif; ?>

    <div class="form-page-card">
        <div class="form-page-header">
            <h1 style="color:<?php echo htmlspecialchars($meta['color']); ?>;">
                <i class="fa-solid <?php echo htmlspecialchars($meta['icon']); ?>"></i>
                <?php echo htmlspecialchars((string) $ticket['ticket_no']); ?>
            </h1>
            <p><?php echo htmlspecialchars($meta['label']); ?> ·
                <span class="status-badge" style="<?php echo evStatusBadgeStyle($ticket['status']); ?>"><?php echo htmlspecialchars((string) $ticket['status']); ?></span>
                <span class="status-badge" style="<?php echo evPriorityBadgeStyle($ticket['priority']); ?>"><?php echo htmlspecialchars((string) $ticket['priority']); ?></span>
            </p>
        </div>

        <div class="form-grid form-grid-3">
            <div class="form-group"><label>Category</label><div><strong><?php echo htmlspecialchars((string) $ticket['category']); ?></strong></div></div>
            <div class="form-group"><label>Confidentiality</label><div><strong><?php echo htmlspecialchars((string) $ticket['confidentiality']); ?></strong></div></div>
            <div class="form-group"><label>Submitted</label><div><strong><?php echo htmlspecialchars(date('d M Y H:i', strtotime($ticket['submitted_at']))); ?></strong></div></div>
        </div>
        <div class="form-group">
            <label>Subject</label>
            <div><strong><?php echo htmlspecialchars((string) $ticket['subject']); ?></strong></div>
        </div>
        <div class="form-group">
            <label>Description</label>
            <div style="white-space:pre-wrap;"><?php echo htmlspecialchars((string) $ticket['description']); ?></div>
        </div>

        <?php if ($ticket['module_type'] === 'GRIEVANCE' && $detail): ?>
        <h3 style="font-size:14px;margin:16px 0 8px;">Complaint details</h3>
        <div class="form-grid form-grid-3">
            <div class="form-group"><label>Against</label><div><?php echo htmlspecialchars((string) ($detail['complaint_against'] ?: '—')); ?></div></div>
            <div class="form-group"><label>Incident date</label><div><?php echo !empty($detail['incident_date']) ? htmlspecialchars($detail['incident_date']) : '—'; ?></div></div>
            <div class="form-group"><label>Location</label><div><?php echo htmlspecialchars((string) ($detail['incident_location'] ?: '—')); ?></div></div>
        </div>
        <?php elseif ($ticket['module_type'] === 'SUGGESTION' && $detail): ?>
        <h3 style="font-size:14px;margin:16px 0 8px;">Suggestion details</h3>
        <div class="form-group"><label>Current problem</label><div style="white-space:pre-wrap;"><?php echo htmlspecialchars((string) ($detail['current_problem'] ?: '—')); ?></div></div>
        <div class="form-group"><label>Proposed improvement</label><div style="white-space:pre-wrap;"><?php echo htmlspecialchars((string) ($detail['proposed_improvement'] ?: '—')); ?></div></div>
        <?php elseif ($ticket['module_type'] === 'SAFETY' && $detail): ?>
        <h3 style="font-size:14px;margin:16px 0 8px;">Safety details</h3>
        <div class="form-grid form-grid-3">
            <div class="form-group"><label>Severity</label><div><strong><?php echo htmlspecialchars((string) ($detail['risk_severity'] ?: '—')); ?></strong></div></div>
            <div class="form-group"><label>Exact location</label><div><?php echo htmlspecialchars((string) ($detail['exact_location'] ?: '—')); ?></div></div>
            <div class="form-group"><label>Equipment</label><div><?php echo htmlspecialchars((string) ($detail['equipment_ref'] ?: '—')); ?></div></div>
        </div>
        <?php endif; ?>

        <?php if (!empty($ticket['attachments'])): ?>
        <h3 style="font-size:14px;margin:16px 0 8px;">Attachments</h3>
        <ul>
            <?php foreach ($ticket['attachments'] as $a): ?>
                <li>
                    <a href="<?php echo app_url('employee/voice/attachment.php?id=' . (int) $a['id']); ?>" target="_blank">
                        <?php echo htmlspecialchars((string) $a['file_name']); ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>

        <?php
        $visibleComments = array_filter($ticket['comments'] ?? [], static function ($c) {
            return empty($c['is_internal']);
        });
        if ($visibleComments):
        ?>
        <h3 style="font-size:14px;margin:16px 0 8px;">Updates</h3>
        <?php foreach ($visibleComments as $c): ?>
            <div style="border:1px solid #e5e7eb;border-radius:8px;padding:10px;margin-bottom:8px;white-space:pre-wrap;">
                <?php echo htmlspecialchars((string) $c['comment_text']); ?>
                <div style="font-size:11px;color:#64748b;margin-top:4px;"><?php echo htmlspecialchars(date('d M Y H:i', strtotime($c['created_at']))); ?></div>
            </div>
        <?php endforeach; endif; ?>
    </div>
</main>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
