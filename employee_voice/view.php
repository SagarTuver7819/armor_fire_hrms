<?php
/**
 * Employee Voice — Admin ticket detail + status update
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

$id = (int) ($_GET['id'] ?? 0);
$msg = '';
$msgType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $id > 0) {
    $action = (string) ($_POST['action'] ?? 'status');
    if ($action === 'status') {
        $res = evUpdateStatus($id, $_POST['status'] ?? '', $_POST['reason'] ?? '', (int) ($_SESSION['user_id'] ?? 0));
        if (!empty($res['ok'])) {
            $msg = 'Status updated.';
            $msgType = 'success';
        } else {
            $msg = $res['error'] ?? 'Update failed';
            $msgType = 'error';
        }
    } elseif ($action === 'notes') {
        evSaveAdminNotes($id, $_POST['admin_notes'] ?? '');
        $msg = 'Notes saved.';
        $msgType = 'success';
    } elseif ($action === 'comment') {
        $text = trim((string) ($_POST['comment_text'] ?? ''));
        $visible = !empty($_POST['visible_to_employee']);
        if ($text !== '') {
            $conn = getDBConnection();
            ensureEmployeeVoiceTables($conn);
            $by = (int) ($_SESSION['user_id'] ?? 0);
            $conn->query(
                'INSERT INTO ev_comments (ticket_id, comment_text, is_internal, created_by, created_at) VALUES ('
                . $id . ', ' . evSqlStr($conn, $text) . ', ' . ($visible ? '0' : '1') . ', '
                . ($by > 0 ? $by : 'NULL') . ', NOW())'
            );
            $conn->close();
            $msg = 'Comment added.';
            $msgType = 'success';
        }
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
    <div class="alert <?php echo $msgType === 'success' ? 'alert-success' : 'alert-error'; ?>" style="margin-bottom:12px;">
        <?php echo htmlspecialchars($msg); ?>
    </div>
    <?php endif; ?>

    <div class="form-page-card">
        <div class="form-page-header">
            <h1 style="color:<?php echo htmlspecialchars($meta['color']); ?>;">
                <i class="fa-solid <?php echo htmlspecialchars($meta['icon']); ?>"></i>
                <?php echo htmlspecialchars((string) $ticket['ticket_no']); ?>
            </h1>
            <p>
                <?php echo htmlspecialchars($meta['label']); ?>
                · <span class="status-badge" style="<?php echo evStatusBadgeStyle($ticket['status']); ?>"><?php echo htmlspecialchars((string) $ticket['status']); ?></span>
                · <span class="status-badge" style="<?php echo evPriorityBadgeStyle($ticket['priority']); ?>"><?php echo htmlspecialchars((string) $ticket['priority']); ?></span>
            </p>
        </div>

        <div class="form-grid form-grid-3">
            <div class="form-group">
                <label>Employee</label>
                <div>
                    <strong><?php echo htmlspecialchars((string) ($ticket['employee_name'] ?: '—')); ?></strong>
                    <div style="font-size:12px;color:#64748b;">
                        <?php echo htmlspecialchars(trim(($ticket['employee_code'] ?? '') . ' · ' . ($ticket['department_name'] ?? ''))); ?>
                    </div>
                    <?php if (($ticket['confidentiality'] ?? '') === 'Anonymous'): ?>
                        <span class="status-badge" style="background:#f1f5f9;color:#64748b;">Marked Anonymous</span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="form-group"><label>Category</label><div><strong><?php echo htmlspecialchars((string) $ticket['category']); ?></strong></div></div>
            <div class="form-group"><label>Submitted</label><div><strong><?php echo htmlspecialchars(date('d M Y H:i', strtotime($ticket['submitted_at']))); ?></strong></div></div>
            <div class="form-group"><label>Location</label><div><?php echo htmlspecialchars((string) ($ticket['location_name'] ?: '—')); ?></div></div>
            <div class="form-group"><label>Confidentiality</label><div><?php echo htmlspecialchars((string) $ticket['confidentiality']); ?></div></div>
            <div class="form-group"><label>Mode</label><div><?php echo htmlspecialchars((string) $ticket['submission_mode']); ?></div></div>
        </div>

        <div class="form-group">
            <label>Subject</label>
            <div><strong><?php echo htmlspecialchars((string) $ticket['subject']); ?></strong></div>
        </div>
        <div class="form-group">
            <label>Description</label>
            <div style="white-space:pre-wrap;border:1px solid #e5e7eb;border-radius:10px;padding:12px;background:#fafafa;">
                <?php echo htmlspecialchars((string) $ticket['description']); ?>
            </div>
        </div>

        <?php if ($ticket['module_type'] === 'GRIEVANCE'): ?>
        <h3 style="font-size:14px;color:#dc2626;">Complaint details</h3>
        <div class="form-grid form-grid-3">
            <div class="form-group"><label>Against</label><div><?php echo htmlspecialchars((string) ($detail['complaint_against'] ?? '—')); ?></div></div>
            <div class="form-group"><label>Incident date</label><div><?php echo htmlspecialchars((string) ($detail['incident_date'] ?? '—')); ?></div></div>
            <div class="form-group"><label>Incident location</label><div><?php echo htmlspecialchars((string) ($detail['incident_location'] ?? '—')); ?></div></div>
            <div class="form-group"><label>Preferred contact</label><div><?php echo htmlspecialchars((string) ($detail['preferred_contact'] ?? '—')); ?></div></div>
            <div class="form-group"><label>Immediate assistance</label><div><?php echo !empty($detail['immediate_assistance']) ? 'Yes' : 'No'; ?></div></div>
            <div class="form-group"><label>Confidential handling</label><div><?php echo !empty($detail['confidential_handling']) ? 'Yes' : 'No'; ?></div></div>
        </div>
        <div class="form-group"><label>Requested resolution</label><div style="white-space:pre-wrap;"><?php echo htmlspecialchars((string) ($detail['requested_resolution'] ?? '—')); ?></div></div>

        <?php elseif ($ticket['module_type'] === 'SUGGESTION'): ?>
        <h3 style="font-size:14px;color:#16a34a;">Suggestion details</h3>
        <div class="form-group"><label>Current problem</label><div style="white-space:pre-wrap;"><?php echo htmlspecialchars((string) ($detail['current_problem'] ?? '—')); ?></div></div>
        <div class="form-group"><label>Proposed improvement</label><div style="white-space:pre-wrap;"><?php echo htmlspecialchars((string) ($detail['proposed_improvement'] ?? '—')); ?></div></div>
        <div class="form-grid form-grid-3">
            <div class="form-group"><label>Expected benefit</label><div><?php echo htmlspecialchars((string) ($detail['expected_benefit'] ?? '—')); ?></div></div>
            <div class="form-group"><label>Est. saving</label><div><?php echo htmlspecialchars((string) ($detail['estimated_saving'] ?? '—')); ?></div></div>
            <div class="form-group"><label>Est. impl. cost</label><div><?php echo htmlspecialchars((string) ($detail['estimated_impl_cost'] ?? '—')); ?></div></div>
        </div>

        <?php else: ?>
        <h3 style="font-size:14px;color:#2563eb;">Safety details</h3>
        <div class="form-grid form-grid-3">
            <div class="form-group"><label>Hazard type</label><div><?php echo htmlspecialchars((string) ($detail['hazard_type'] ?? '—')); ?></div></div>
            <div class="form-group"><label>Exact location</label><div><?php echo htmlspecialchars((string) ($detail['exact_location'] ?? '—')); ?></div></div>
            <div class="form-group"><label>Equipment</label><div><?php echo htmlspecialchars((string) ($detail['equipment_ref'] ?? '—')); ?></div></div>
            <div class="form-group"><label>Risk severity</label><div><strong><?php echo htmlspecialchars((string) ($detail['risk_severity'] ?? '—')); ?></strong></div></div>
            <div class="form-group"><label>Near miss / injury</label><div><?php echo !empty($detail['injury_near_miss']) ? 'Yes' : 'No'; ?></div></div>
            <div class="form-group"><label>Immediate danger</label><div><strong style="color:<?php echo !empty($detail['immediate_danger']) ? '#b91c1c' : 'inherit'; ?>;"><?php echo !empty($detail['immediate_danger']) ? 'YES' : 'No'; ?></strong></div></div>
        </div>
        <div class="form-group"><label>Immediate action taken</label><div style="white-space:pre-wrap;"><?php echo htmlspecialchars((string) ($detail['immediate_action_taken'] ?? '—')); ?></div></div>
        <?php endif; ?>

        <?php if (!empty($ticket['attachments'])): ?>
        <h3 style="font-size:14px;margin-top:16px;">Attachments</h3>
        <ul>
            <?php foreach ($ticket['attachments'] as $a): ?>
                <li>
                    <a target="_blank" href="<?php echo app_url('employee/voice/attachment.php?id=' . (int) $a['id']); ?>">
                        <i class="fa-solid fa-paperclip"></i> <?php echo htmlspecialchars((string) $a['file_name']); ?>
                    </a>
                    <small style="color:#64748b;">(<?php echo number_format(((int) $a['file_size']) / 1024, 1); ?> KB)</small>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>

        <hr style="margin:20px 0;border:0;border-top:1px solid #e5e7eb;">

        <form method="POST" class="employee-form" style="margin-bottom:18px;">
            <input type="hidden" name="action" value="status">
            <h3 style="font-size:15px;margin:0 0 10px;">Update status</h3>
            <div class="form-grid form-grid-3">
                <div class="form-group">
                    <label>New status</label>
                    <select name="status" class="form-control" required>
                        <?php foreach ($statuses as $s): ?>
                            <option value="<?php echo htmlspecialchars($s); ?>" <?php echo $ticket['status'] === $s ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($s); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" style="grid-column:span 2;">
                    <label>Reason / note</label>
                    <input type="text" name="reason" class="form-control" placeholder="Optional reason for status change">
                </div>
            </div>
            <button type="submit" class="btn-primary"><i class="fa-solid fa-floppy-disk"></i> Save Status</button>
        </form>

        <form method="POST" class="employee-form" style="margin-bottom:18px;">
            <input type="hidden" name="action" value="notes">
            <h3 style="font-size:15px;margin:0 0 10px;">Internal admin notes</h3>
            <textarea name="admin_notes" class="form-control" rows="3"><?php echo htmlspecialchars((string) ($ticket['admin_notes'] ?? '')); ?></textarea>
            <button type="submit" class="btn-secondary" style="margin-top:8px;">Save Notes</button>
        </form>

        <form method="POST" class="employee-form" style="margin-bottom:18px;">
            <input type="hidden" name="action" value="comment">
            <h3 style="font-size:15px;margin:0 0 10px;">Add comment / reply</h3>
            <textarea name="comment_text" class="form-control" rows="3" required placeholder="Comment text"></textarea>
            <div class="form-group checkbox-group" style="margin-top:8px;">
                <label><input type="checkbox" name="visible_to_employee" value="1"> Visible to employee</label>
            </div>
            <button type="submit" class="btn-secondary">Add Comment</button>
        </form>

        <?php if (!empty($ticket['comments'])): ?>
        <h3 style="font-size:14px;">Comments</h3>
        <?php foreach ($ticket['comments'] as $c): ?>
            <div style="border:1px solid #e5e7eb;border-radius:8px;padding:10px;margin-bottom:8px;">
                <div style="white-space:pre-wrap;"><?php echo htmlspecialchars((string) $c['comment_text']); ?></div>
                <div style="font-size:11px;color:#64748b;margin-top:4px;">
                    <?php echo htmlspecialchars(date('d M Y H:i', strtotime($c['created_at']))); ?>
                    · <?php echo !empty($c['is_internal']) ? 'Internal' : 'Employee-visible'; ?>
                </div>
            </div>
        <?php endforeach; endif; ?>

        <?php if (!empty($ticket['status_history'])): ?>
        <h3 style="font-size:14px;margin-top:16px;">Status history</h3>
        <ul style="font-size:13px;color:#475569;">
            <?php foreach ($ticket['status_history'] as $h): ?>
                <li>
                    <?php echo htmlspecialchars(date('d M Y H:i', strtotime($h['changed_at']))); ?> —
                    <?php echo htmlspecialchars(trim(($h['from_status'] ?: '—') . ' → ' . $h['to_status'])); ?>
                    <?php if (!empty($h['reason'])): ?> (<?php echo htmlspecialchars((string) $h['reason']); ?>)<?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
    </div>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
