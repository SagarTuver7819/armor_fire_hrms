<?php
/**
 * Recruitment — View application
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permission_helper.php';
require_once __DIR__ . '/../includes/recruitment_helper.php';

requireLogin();
if (!isAdmin() && !isHR() && !(function_exists('isStaffUser') && isStaffUser()) && !canAccess('recruitment', 'view')) {
    header('Location: ' . app_url('dashboard.php'));
    exit;
}

$id = (int) ($_GET['id'] ?? 0);
$row = $id > 0 ? getRecruitmentApplication($id) : null;
if (!$row) {
    header('Location: ' . app_url('recruitment/index.php'));
    exit;
}

$labels = recruitmentStatusLabels();
$canEdit = isAdmin() || isHR() || (function_exists('isStaffUser') && isStaffUser()) || canAccess('recruitment', 'edit');

$pageTitle = 'Application ' . $row['application_no'];
$useSidebar = true;
$sidebarActive = 'recruitment';
$extraCss = ['https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css'];

require_once __DIR__ . '/../includes/header.php';

$toast = '';
$toastType = 'success';
if (isset($_GET['msg']) && $_GET['msg'] === 'saved') {
    $toast = 'Status updated.';
}
?>

<main class="dashboard-main">
    <div class="page-toolbar flex-between">
        <a href="<?php echo app_url('recruitment/index.php'); ?>" class="back-link">
            <i class="fa-solid fa-arrow-left"></i> Back to Applications
        </a>
    </div>

    <div class="form-page-card" style="max-width:980px;">
        <div class="form-page-header" style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;align-items:flex-start;">
            <div>
                <h1><?php echo htmlspecialchars((string) $row['full_name']); ?></h1>
                <p>
                    <?php echo htmlspecialchars((string) $row['application_no']); ?> ·
                    <?php echo htmlspecialchars((string) $row['position_name']); ?> ·
                    <?php echo htmlspecialchars((string) $row['department_name']); ?>
                </p>
            </div>
            <span class="status-badge status-<?php echo htmlspecialchars((string) $row['status']); ?>">
                <?php echo htmlspecialchars($labels[$row['status']] ?? $row['status']); ?>
            </span>
        </div>

        <div class="rec-view-grid">
            <section>
                <h3>Personal</h3>
                <dl class="rec-dl">
                    <div><dt>Mobile</dt><dd><?php echo htmlspecialchars((string) $row['mobile']); ?></dd></div>
                    <div><dt>Alt Mobile</dt><dd><?php echo htmlspecialchars((string) ($row['alt_mobile'] ?: '—')); ?></dd></div>
                    <div><dt>Email</dt><dd><?php echo htmlspecialchars((string) $row['email']); ?></dd></div>
                    <div><dt>DOB</dt><dd><?php echo !empty($row['dob']) ? htmlspecialchars(date('d M Y', strtotime($row['dob']))) : '—'; ?></dd></div>
                    <div><dt>Gender</dt><dd><?php echo htmlspecialchars((string) ($row['gender'] ?: '—')); ?></dd></div>
                    <div><dt>Marital</dt><dd><?php echo htmlspecialchars((string) ($row['marital_status'] ?: '—')); ?></dd></div>
                    <div class="full"><dt>Address</dt><dd><?php echo nl2br(htmlspecialchars((string) ($row['address'] ?: '—'))); ?></dd></div>
                    <div><dt>City</dt><dd><?php echo htmlspecialchars((string) ($row['city'] ?: '—')); ?></dd></div>
                    <div><dt>State</dt><dd><?php echo htmlspecialchars((string) ($row['state_name'] ?: '—')); ?></dd></div>
                    <div><dt>Pincode</dt><dd><?php echo htmlspecialchars((string) ($row['pincode'] ?: '—')); ?></dd></div>
                </dl>
            </section>

            <section>
                <h3>Salary</h3>
                <dl class="rec-dl">
                    <div><dt>Current</dt><dd><?php echo $row['current_salary'] !== null ? '₹ ' . number_format((float) $row['current_salary'], 2) : '—'; ?></dd></div>
                    <div><dt>Expected</dt><dd><?php echo $row['expected_salary'] !== null ? '₹ ' . number_format((float) $row['expected_salary'], 2) : '—'; ?></dd></div>
                    <div><dt>Notice</dt><dd><?php echo htmlspecialchars((string) ($row['notice_period'] ?: '—')); ?></dd></div>
                    <div><dt>Total Exp.</dt><dd><?php echo htmlspecialchars((string) ($row['total_experience'] ?: '—')); ?></dd></div>
                </dl>
                <h3 style="margin-top:16px;">Documents</h3>
                <ul class="rec-docs">
                    <?php
                    $docs = [
                        'Bank Statement' => $row['bank_statement_file'] ?? '',
                        'Salary Slip' => $row['salary_slip_file'] ?? '',
                        'Resume' => $row['resume_file'] ?? '',
                    ];
                    foreach ($docs as $label => $path):
                        $url = recruitmentPublicPath($path);
                    ?>
                        <li>
                            <?php if ($url): ?>
                                <a href="<?php echo htmlspecialchars($url); ?>" target="_blank" rel="noopener">
                                    <i class="fa-solid fa-paperclip"></i> <?php echo htmlspecialchars($label); ?>
                                </a>
                            <?php else: ?>
                                <span class="muted"><?php echo htmlspecialchars($label); ?> — not uploaded</span>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </section>
        </div>

        <section style="margin-top:18px;">
            <h3>Education</h3>
            <?php if (empty($row['education'])): ?>
                <p class="muted">No education details.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                        <tr><th>Degree</th><th>Institution</th><th>Specialization</th><th>Year</th><th>% / CGPA</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($row['education'] as $ed): ?>
                            <tr>
                                <td><?php echo htmlspecialchars((string) $ed['degree']); ?></td>
                                <td><?php echo htmlspecialchars((string) $ed['institution']); ?></td>
                                <td><?php echo htmlspecialchars((string) ($ed['specialization'] ?: '—')); ?></td>
                                <td><?php echo htmlspecialchars((string) ($ed['year_of_passing'] ?: '—')); ?></td>
                                <td><?php echo htmlspecialchars((string) ($ed['percentage'] ?: '—')); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>

        <section style="margin-top:18px;">
            <h3>Experience</h3>
            <?php if (empty($row['experience'])): ?>
                <p class="muted">No experience details.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                        <tr><th>Company</th><th>Designation</th><th>From</th><th>To</th><th>Salary</th><th>Notes</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($row['experience'] as $ex): ?>
                            <tr>
                                <td><?php echo htmlspecialchars((string) $ex['company_name']); ?></td>
                                <td><?php echo htmlspecialchars((string) $ex['designation']); ?></td>
                                <td><?php echo htmlspecialchars((string) ($ex['from_date'] ?: '—')); ?></td>
                                <td><?php echo !empty($ex['is_current']) ? 'Present' : htmlspecialchars((string) ($ex['to_date'] ?: '—')); ?></td>
                                <td><?php echo $ex['last_salary'] !== null ? '₹ ' . number_format((float) $ex['last_salary'], 0) : '—'; ?></td>
                                <td><?php echo nl2br(htmlspecialchars((string) ($ex['responsibilities'] ?: '—'))); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>

        <?php if ($canEdit): ?>
        <section style="margin-top:22px;padding-top:16px;border-top:1px solid #e2e8f0;">
            <h3>Update Status</h3>
            <form method="POST" action="<?php echo app_url('recruitment/status_save.php'); ?>" class="employee-form">
                <input type="hidden" name="id" value="<?php echo (int) $row['id']; ?>">
                <div class="form-grid form-grid-2">
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status" class="form-control" required>
                            <?php foreach ($labels as $k => $lab): ?>
                                <option value="<?php echo htmlspecialchars($k); ?>" <?php echo $row['status'] === $k ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($lab); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>HR Remarks</label>
                        <textarea name="hr_remarks" class="form-control" rows="2"><?php echo htmlspecialchars((string) ($row['hr_remarks'] ?? '')); ?></textarea>
                    </div>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn-primary"><i class="fa-solid fa-floppy-disk"></i> Save Status</button>
                </div>
            </form>
        </section>
        <?php endif; ?>
    </div>
</main>

<style>
.rec-view-grid {
    display: grid;
    grid-template-columns: 1.2fr 0.8fr;
    gap: 18px;
}
.rec-view-grid h3, .form-page-card h3 {
    margin: 0 0 10px;
    font-size: 13px;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: #64748b;
}
.rec-dl {
    margin: 0;
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px 14px;
}
.rec-dl .full { grid-column: 1 / -1; }
.rec-dl dt { font-size: 11px; font-weight: 700; color: #94a3b8; text-transform: uppercase; }
.rec-dl dd { margin: 2px 0 0; font-size: 14px; font-weight: 600; color: #0f172a; }
.rec-docs { list-style: none; margin: 0; padding: 0; }
.rec-docs li { margin: 0 0 8px; }
.rec-docs a { font-weight: 700; color: #d2232a; text-decoration: none; }
.muted { color: #94a3b8; }
.status-badge {
    display: inline-flex; padding: 6px 12px; border-radius: 999px;
    font-size: 12px; font-weight: 800;
}
.status-new { background: #dbeafe; color: #1d4ed8; }
.status-review { background: #fef3c7; color: #b45309; }
.status-shortlisted { background: #dcfce7; color: #15803d; }
.status-rejected { background: #fee2e2; color: #b91c1c; }
.status-hired { background: #e0e7ff; color: #3730a3; }
@media (max-width: 800px) {
    .rec-view-grid { grid-template-columns: 1fr; }
    .rec-dl { grid-template-columns: 1fr; }
}
</style>

<?php
$extraJs = ['https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js'];
require_once __DIR__ . '/../includes/footer.php';
?>
<?php if ($toast !== ''): ?>
<script>
toastr.options = { closeButton: true, progressBar: true, positionClass: 'toast-top-right' };
toastr[<?php echo json_encode($toastType); ?>](<?php echo json_encode($toast); ?>);
</script>
<?php endif; ?>
