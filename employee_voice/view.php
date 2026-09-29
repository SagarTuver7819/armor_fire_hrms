<?php
/**
 * Employee Voice — Admin ticket detail
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permission_helper.php';
require_once __DIR__ . '/../includes/employee_voice_helper.php';
require_once __DIR__ . '/../includes/employee_voice_ui.php';

requireLogin();
if (!canManageEmployeeVoice()) {
    header('Location: ' . app_url('dashboard.php'));
    exit;
}

$id = (int) ($_GET['id'] ?? 0);
$msg = '';
$msgType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $id > 0) {
    $action = (string) ($_POST['action'] ?? 'status');
    if ($action === 'status') {
        $res = evUpdateStatus($id, $_POST['status'] ?? '', $_POST['reason'] ?? '', (int) ($_SESSION['user_id'] ?? 0));
        $msg = !empty($res['ok']) ? 'Status updated.' : ($res['error'] ?? 'Update failed');
        $msgType = !empty($res['ok']) ? 'success' : 'error';
    } elseif ($action === 'notes') {
        evSaveAdminNotes($id, $_POST['admin_notes'] ?? '');
        $msg = 'Notes saved.';
        $msgType = 'success';
    } elseif ($action === 'comment') {
        $res = evAddComment(
            $id,
            $_POST['comment_text'] ?? '',
            !empty($_POST['visible_to_employee']),
            (int) ($_SESSION['user_id'] ?? 0)
        );
        $msg = !empty($res['ok']) ? 'Comment added.' : ($res['error'] ?? 'Could not add comment');
        $msgType = !empty($res['ok']) ? 'success' : 'error';
    }
}

$ticket = $id > 0 ? evGetTicket($id) : null;
if (!$ticket) {
    header('Location: ' . app_url('employee_voice/index.php'));
    exit;
}

$types = evModuleTypes();
$meta = $types[$ticket['module_type']] ?? ['label' => $ticket['module_type'], 'color' => '#333', 'icon' => 'fa-ticket', 'bg' => '#eee'];
$detail = $ticket['detail'] ?? [];
$statuses = evStatusesByModule($ticket['module_type']);
$accentClass = $ticket['module_type'] === 'SUGGESTION' ? 'is-green' : ($ticket['module_type'] === 'SAFETY' ? 'is-blue' : 'is-red');

$pageTitle = $ticket['ticket_no'];
$useSidebar = true;
$sidebarMode = 'employee_voice';
$sidebarActive = 'ev_admin';

require_once __DIR__ . '/../includes/header.php';
?>

<main class="dashboard-main">
    <div class="page-toolbar flex-between">
        <a href="<?php echo app_url('employee_voice/index.php'); ?>" class="back-link">
            <i class="fa-solid fa-arrow-left"></i> Employee Voice
        </a>
    </div>

    <?php if ($msg !== ''): ?>
    <div class="alert <?php echo $msgType === 'success' ? 'alert-success' : 'alert-error'; ?>" style="margin-bottom:14px;">
        <?php echo htmlspecialchars($msg); ?>
    </div>
    <?php endif; ?>

    <div class="ev-wrap">
        <div class="ev-ticket-card">
            <div class="ev-ticket-hero">
                <div class="ev-ticket-hero-left">
                    <div class="ev-ticket-icon" style="background:<?php echo htmlspecialchars($meta['color']); ?>;">
                        <i class="fa-solid <?php echo htmlspecialchars($meta['icon']); ?>"></i>
                    </div>
                    <div>
                        <h1 style="color:<?php echo htmlspecialchars($meta['color']); ?>;">
                            <?php echo htmlspecialchars((string) $ticket['ticket_no']); ?>
                        </h1>
                        <div class="ev-ticket-meta-line">
                            <span><?php echo htmlspecialchars($meta['label']); ?></span>
                            <span>·</span>
                            <span class="status-badge" style="<?php echo evStatusBadgeStyle($ticket['status']); ?>">
                                <?php echo htmlspecialchars((string) $ticket['status']); ?>
                            </span>
                            <span class="status-badge" style="<?php echo evPriorityBadgeStyle($ticket['priority']); ?>">
                                <?php echo htmlspecialchars((string) $ticket['priority']); ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="ev-section">
                <h2 class="ev-section-title"><span class="bar"></span> Ticket overview</h2>
                <div class="ev-kv-grid">
                    <?php
                    evKv(
                        'Employee',
                        (string) ($ticket['employee_name'] ?: '—'),
                        1,
                        trim(($ticket['employee_code'] ?? '') . ' · ' . ($ticket['department_name'] ?? ''))
                    );
                    evKv('Category', $ticket['category'] ?? '');
                    evKv('Submitted', !empty($ticket['submitted_at']) ? formatDateTimeDisplay($ticket['submitted_at']) : '—');
                    evKv('Location', $ticket['location_name'] ?? '');
                    evKv('Confidentiality', $ticket['confidentiality'] ?? '');
                    evKv('Mode', $ticket['submission_mode'] ?? '');
                    ?>
                </div>
                <div style="margin-top:14px;">
                    <?php evBlock('Subject', $ticket['subject'] ?? ''); ?>
                    <?php evBlock('Description', $ticket['description'] ?? ''); ?>
                </div>
            </div>

            <?php if ($ticket['module_type'] === 'GRIEVANCE'): ?>
            <div class="ev-section">
                <h2 class="ev-section-title is-red"><span class="bar"></span> Complaint details</h2>
                <div class="ev-kv-grid">
                    <?php
                    evKv('Against', $detail['complaint_against'] ?? '');
                    evKv('Incident date', $detail['incident_date'] ?? '');
                    evKv('Incident location', $detail['incident_location'] ?? '');
                    evKv('Preferred contact', $detail['preferred_contact'] ?? '');
                    evKv('Immediate assistance', !empty($detail['immediate_assistance']) ? 'Yes' : 'No');
                    evKv('Confidential handling', !empty($detail['confidential_handling']) ? 'Yes' : 'No');
                    ?>
                </div>
                <div style="margin-top:14px;"><?php evBlock('Requested resolution', $detail['requested_resolution'] ?? ''); ?></div>
            </div>
            <?php elseif ($ticket['module_type'] === 'SUGGESTION'): ?>
            <div class="ev-section">
                <h2 class="ev-section-title is-green"><span class="bar"></span> Suggestion details</h2>
                <?php evBlock('Current problem / process', $detail['current_problem'] ?? ''); ?>
                <?php evBlock('Proposed improvement', $detail['proposed_improvement'] ?? ''); ?>
                <div class="ev-kv-grid" style="margin-top:14px;">
                    <?php
                    evKv('Expected benefit', $detail['expected_benefit'] ?? '');
                    evKv('Est. cost saving', $detail['estimated_saving'] ?? '');
                    evKv('Est. implementation cost', $detail['estimated_impl_cost'] ?? '');
                    evKv('Help implement', !empty($detail['help_implement']) ? 'Yes' : 'No');
                    ?>
                </div>
            </div>
            <?php else: ?>
            <div class="ev-section">
                <h2 class="ev-section-title is-blue"><span class="bar"></span> Safety details</h2>
                <div class="ev-kv-grid">
                    <?php
                    evKv('Hazard type', $detail['hazard_type'] ?? '');
                    evKv('Exact location', $detail['exact_location'] ?? '');
                    evKv('Equipment / machine', $detail['equipment_ref'] ?? '');
                    evKv('Risk severity', $detail['risk_severity'] ?? '');
                    evKv('Injury / near miss', !empty($detail['injury_near_miss']) ? 'Yes' : 'No');
                    evKv('Immediate danger', !empty($detail['immediate_danger']) ? 'YES' : 'No');
                    ?>
                </div>
                <div style="margin-top:14px;"><?php evBlock('Immediate action taken', $detail['immediate_action_taken'] ?? ''); ?></div>
            </div>
            <?php endif; ?>

            <?php if (!empty($ticket['attachments'])): ?>
            <div class="ev-section">
                <h2 class="ev-section-title"><span class="bar"></span> Attachments</h2>
                <ul class="ev-attach-list">
                    <?php foreach ($ticket['attachments'] as $a): ?>
                    <li>
                        <a target="_blank" href="<?php echo app_url('employee/voice/attachment.php?id=' . (int) $a['id']); ?>">
                            <i class="fa-solid fa-paperclip" style="color:#d2232a;"></i>
                            <?php echo htmlspecialchars((string) $a['file_name']); ?>
                            <small><?php echo number_format(((int) $a['file_size']) / 1024, 1); ?> KB</small>
                        </a>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>

            <div class="ev-section ev-admin-panel">
                <h2 class="ev-section-title <?php echo $accentClass; ?>"><span class="bar"></span> Update status</h2>
                <form method="POST" class="employee-form">
                    <input type="hidden" name="action" value="status">
                    <div class="ev-form-row">
                        <div class="form-group" style="margin:0;">
                            <label>New status</label>
                            <select name="status" class="form-control" required>
                                <?php foreach ($statuses as $s): ?>
                                    <option value="<?php echo htmlspecialchars($s); ?>" <?php echo $ticket['status'] === $s ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($s); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group" style="margin:0;">
                            <label>Reason / note</label>
                            <input type="text" name="reason" class="form-control" placeholder="Optional reason for status change">
                        </div>
                    </div>
                    <div class="ev-actions">
                        <button type="submit" class="btn-primary"><i class="fa-solid fa-floppy-disk"></i> Save Status</button>
                    </div>
                </form>
            </div>

            <div class="ev-section">
                <h2 class="ev-section-title"><span class="bar"></span> Internal admin notes</h2>
                <form method="POST" class="employee-form">
                    <input type="hidden" name="action" value="notes">
                    <textarea name="admin_notes" class="form-control" rows="3" placeholder="Internal notes (not shown to employee)"><?php echo htmlspecialchars((string) ($ticket['admin_notes'] ?? '')); ?></textarea>
                    <div class="ev-actions" style="margin-top:10px;">
                        <button type="submit" class="btn-secondary">Save Notes</button>
                    </div>
                </form>
            </div>

            <div class="ev-section">
                <h2 class="ev-section-title"><span class="bar"></span> Add comment / reply</h2>
                <form method="POST" class="employee-form">
                    <input type="hidden" name="action" value="comment">
                    <textarea name="comment_text" class="form-control" rows="3" required placeholder="Write a comment"></textarea>
                    <div class="form-group checkbox-group" style="margin-top:10px;">
                        <label><input type="checkbox" name="visible_to_employee" value="1"> Visible to employee</label>
                    </div>
                    <div class="ev-actions">
                        <button type="submit" class="btn-secondary">Add Comment</button>
                    </div>
                </form>

                <?php if (!empty($ticket['comments'])): ?>
                <div style="margin-top:16px;">
                    <?php foreach ($ticket['comments'] as $c): ?>
                        <div class="ev-comment">
                            <div class="body"><?php echo htmlspecialchars((string) $c['comment_text']); ?></div>
                            <div class="meta">
                                <?php echo htmlspecialchars(formatDateTimeDisplay($c['created_at'])); ?>
                                · <?php echo !empty($c['is_internal']) ? 'Internal' : 'Employee-visible'; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <?php if (!empty($ticket['status_history'])): ?>
            <div class="ev-section">
                <h2 class="ev-section-title"><span class="bar"></span> Status history</h2>
                <ul class="ev-history">
                    <?php foreach ($ticket['status_history'] as $h): ?>
                        <li>
                            <?php echo htmlspecialchars(formatDateTimeDisplay($h['changed_at'])); ?> —
                            <strong><?php echo htmlspecialchars(trim(($h['from_status'] ?: '—') . ' → ' . $h['to_status'])); ?></strong>
                            <?php if (!empty($h['reason'])): ?>
                                <span style="color:#94a3b8;">(<?php echo htmlspecialchars((string) $h['reason']); ?>)</span>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
