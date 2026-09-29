<?php
/**
 * Employee Voice — view own ticket (employee side)
 */

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/employee_helper.php';
require_once __DIR__ . '/../../includes/employee_voice_helper.php';
require_once __DIR__ . '/../../includes/employee_voice_ui.php';

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
$meta = $types[$ticket['module_type']] ?? ['label' => $ticket['module_type'], 'color' => '#333', 'icon' => 'fa-ticket', 'bg' => '#eee'];
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
        <a href="<?php echo app_url('employee/voice/index.php'); ?>" class="btn-secondary">
            <i class="fa-solid fa-plus"></i> New Submission
        </a>
    </div>

    <?php if ($created): ?>
    <div class="alert alert-success" style="margin-bottom:14px;">
        <i class="fa-solid fa-circle-check"></i>
        Ticket <strong><?php echo htmlspecialchars((string) $ticket['ticket_no']); ?></strong> submitted successfully.
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
                    evKv('Category', $ticket['category'] ?? '');
                    evKv('Confidentiality', $ticket['confidentiality'] ?? '');
                    evKv('Submitted', !empty($ticket['submitted_at']) ? formatDateTimeDisplay($ticket['submitted_at']) : '—');
                    evKv('Location', $ticket['location_name'] ?? '');
                    evKv('Department', $ticket['department_name'] ?? '');
                    evKv('Mode', $ticket['submission_mode'] ?? '');
                    ?>
                </div>
                <div style="margin-top:14px;">
                    <?php evBlock('Subject', $ticket['subject'] ?? ''); ?>
                    <?php evBlock('Description', $ticket['description'] ?? ''); ?>
                </div>
            </div>

            <?php if ($ticket['module_type'] === 'GRIEVANCE' && $detail): ?>
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
            <?php elseif ($ticket['module_type'] === 'SUGGESTION' && $detail): ?>
            <div class="ev-section">
                <h2 class="ev-section-title is-green"><span class="bar"></span> Suggestion details</h2>
                <?php evBlock('Current problem / process', $detail['current_problem'] ?? ''); ?>
                <?php evBlock('Proposed improvement', $detail['proposed_improvement'] ?? ''); ?>
                <div class="ev-kv-grid" style="margin-top:14px;">
                    <?php
                    evKv('Expected benefit', $detail['expected_benefit'] ?? '');
                    evKv('Est. cost saving', $detail['estimated_saving'] ?? '');
                    evKv('Est. implementation cost', $detail['estimated_impl_cost'] ?? '');
                    ?>
                </div>
            </div>
            <?php elseif ($ticket['module_type'] === 'SAFETY' && $detail): ?>
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

            <?php
            $visibleComments = array_filter($ticket['comments'] ?? [], static function ($c) {
                return empty($c['is_internal']);
            });
            if ($visibleComments):
            ?>
            <div class="ev-section">
                <h2 class="ev-section-title"><span class="bar"></span> Updates from HR</h2>
                <?php foreach ($visibleComments as $c): ?>
                    <div class="ev-comment">
                        <div class="body"><?php echo htmlspecialchars((string) $c['comment_text']); ?></div>
                        <div class="meta"><?php echo htmlspecialchars(formatDateTimeDisplay($c['created_at'])); ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
