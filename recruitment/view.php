<?php
/**
 * Recruitment — Application view (full-width profile layout)
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
$statusKey = (string) ($row['status'] ?? 'new');

$v = static function ($value, $fallback = '—') {
    $value = trim((string) $value);
    return $value !== '' ? $value : $fallback;
};
$money = static function ($value) {
    if ($value === null || $value === '') {
        return '—';
    }
    return '₹ ' . number_format((float) $value, 0);
};
$show = static function ($label, $value) {
    echo '<div class="view-row"><span>' . htmlspecialchars($label) . '</span><strong>' . $value . '</strong></div>';
};

$pageTitle = 'Application · ' . $row['application_no'];
$useSidebar = true;
$sidebarMode = 'recruitment';
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
        <div class="toolbar-actions" style="display:flex;gap:8px;flex-wrap:wrap;">
            <a href="<?php echo app_url('recruitment/pdf.php?id=' . (int) $row['id']); ?>" class="btn-secondary" target="_blank" rel="noopener">
                <i class="fa-solid fa-file-pdf"></i> Print / PDF
            </a>
            <a href="<?php echo app_url('recruitment/interview.php?id=' . (int) $row['id']); ?>" class="btn-primary">
                <i class="fa-solid fa-clipboard-user"></i> Open Interview
            </a>
        </div>
    </div>

    <section class="emp-id-card rec-app-id">
        <div class="emp-id-top">
            <div class="emp-id-identity">
                <div class="emp-avatar emp-avatar--profile" aria-hidden="true">
                    <span><?php
                        $parts = preg_split('/\s+/', trim((string) $row['full_name']));
                        $ini = strtoupper(substr($parts[0] ?? 'C', 0, 1) . substr($parts[count($parts) - 1] ?? '', 0, 1));
                        echo htmlspecialchars($ini);
                    ?></span>
                </div>
                <div class="emp-id-copy">
                    <div class="emp-id-tags">
                        <span class="status-badge status-<?php echo htmlspecialchars($statusKey); ?>">
                            <?php echo htmlspecialchars($labels[$statusKey] ?? $statusKey); ?>
                        </span>
                    </div>
                    <div class="emp-id-title-row">
                        <strong class="emp-id-code"><?php echo htmlspecialchars((string) $row['application_no']); ?></strong>
                        <h1><?php echo htmlspecialchars((string) $row['full_name']); ?></h1>
                    </div>
                    <p class="emp-id-role">
                        <span><i class="fa-solid fa-briefcase"></i> <?php echo htmlspecialchars($v($row['position_name'] ?? '')); ?></span>
                        <span class="sep">|</span>
                        <span><i class="fa-solid fa-building"></i> <?php echo htmlspecialchars($v($row['department_name'] ?? '')); ?></span>
                    </p>
                </div>
            </div>
            <div class="emp-id-actions">
                <a href="<?php echo app_url('recruitment/pdf.php?id=' . (int) $row['id']); ?>" target="_blank" class="btn-ghost">
                    <i class="fa-solid fa-file-pdf"></i> PDF
                </a>
                <a href="<?php echo app_url('recruitment/interview.php?id=' . (int) $row['id']); ?>" class="btn-primary">
                    <i class="fa-solid fa-clipboard-check"></i> Interview
                </a>
            </div>
        </div>
        <div class="emp-id-stats">
            <div class="emp-stat-item">
                <div class="emp-stat-icon"><i class="fa-solid fa-cake-candles"></i></div>
                <div><span>Age</span><strong><?php echo !empty($row['age_years']) ? ((int) $row['age_years'] . ' years') : '—'; ?></strong></div>
            </div>
            <div class="emp-stat-item">
                <div class="emp-stat-icon"><i class="fa-solid fa-clock-rotate-left"></i></div>
                <div><span>Experience</span><strong><?php echo htmlspecialchars($v($row['total_experience'] ?? '')); ?></strong></div>
            </div>
            <div class="emp-stat-item">
                <div class="emp-stat-icon"><i class="fa-solid fa-indian-rupee-sign"></i></div>
                <div><span>Expected</span><strong><?php echo $money($row['expected_salary'] ?? null); ?></strong></div>
            </div>
            <div class="emp-stat-item">
                <div class="emp-stat-icon"><i class="fa-solid fa-calendar"></i></div>
                <div><span>Applied</span><strong><?php echo !empty($row['created_at']) ? htmlspecialchars(formatDateDisplay($row['created_at'])) : '—'; ?></strong></div>
            </div>
        </div>
    </section>

    <div class="view-grid rec-app-grid">
        <div class="view-card">
            <div class="view-card-head">
                <i class="fa-solid fa-briefcase"></i>
                <div><h3>Applied Position</h3><p>Department &amp; role</p></div>
            </div>
            <div class="view-body">
                <?php
                $show('Department', htmlspecialchars($v($row['department_name'] ?? '')));
                $show('Position / Designation', htmlspecialchars($v($row['position_name'] ?? '')));
                ?>
            </div>
        </div>

        <div class="view-card">
            <div class="view-card-head">
                <i class="fa-solid fa-phone"></i>
                <div><h3>Contact</h3><p>Mobile &amp; email</p></div>
            </div>
            <div class="view-body">
                <?php
                $show('Mobile', htmlspecialchars($v($row['mobile'] ?? '')));
                $show('Alternate Mobile', htmlspecialchars($v($row['alt_mobile'] ?? '')));
                $show('Email', htmlspecialchars($v($row['email'] ?? '')));
                ?>
            </div>
        </div>

        <div class="view-card view-card-wide">
            <div class="view-card-head">
                <i class="fa-solid fa-id-card"></i>
                <div><h3>Personal Details</h3><p>Identity &amp; address</p></div>
            </div>
            <div class="view-body view-body-3">
                <?php
                $show('Date of Birth', !empty($row['dob']) ? htmlspecialchars(formatDateDisplay($row['dob'])) : '—');
                $show('Age', !empty($row['age_years']) ? ((int) $row['age_years'] . ' years') : '—');
                $show('Gender', htmlspecialchars($v($row['gender'] ?? '')));
                $show('Marital Status', htmlspecialchars($v($row['marital_status'] ?? '')));
                $show('City', htmlspecialchars($v($row['city'] ?? '')));
                $show('State', htmlspecialchars($v($row['state_name'] ?? '')));
                $show('Pincode', htmlspecialchars($v($row['pincode'] ?? '')));
                echo '<div class="view-row view-row-full"><span>Address</span><strong>' . nl2br(htmlspecialchars($v($row['address'] ?? ''))) . '</strong></div>';
                ?>
            </div>
        </div>

        <div class="view-card">
            <div class="view-card-head">
                <i class="fa-solid fa-building-columns"></i>
                <div><h3>ID &amp; Bank</h3><p>Aadhaar · PAN · Bank</p></div>
            </div>
            <div class="view-body">
                <?php
                $show('Aadhaar No.', htmlspecialchars($v($row['aadhaar_no'] ?? '')));
                $show('PAN No.', htmlspecialchars($v($row['pan_no'] ?? '')));
                $show('Bank Name', htmlspecialchars($v($row['bank_name'] ?? '')));
                $show('Account No.', htmlspecialchars($v($row['bank_account'] ?? '')));
                $show('IFSC', htmlspecialchars($v($row['bank_ifsc'] ?? '')));
                ?>
            </div>
        </div>

        <div class="view-card">
            <div class="view-card-head">
                <i class="fa-solid fa-indian-rupee-sign"></i>
                <div><h3>Salary &amp; Documents</h3><p>Expectation &amp; uploads</p></div>
            </div>
            <div class="view-body">
                <?php
                $show('Current / Last Salary', $money($row['current_salary'] ?? null));
                $show('Expected Salary', $money($row['expected_salary'] ?? null));
                $show('Notice Period', htmlspecialchars($v($row['notice_period'] ?? '')));
                $show('Total Experience', htmlspecialchars($v($row['total_experience'] ?? '')));
                ?>
                <div class="rec-app-docs">
                    <?php
                    $docs = [
                        'Bank Statement' => $row['bank_statement_file'] ?? '',
                        'Salary Slip' => $row['salary_slip_file'] ?? '',
                        'Resume' => $row['resume_file'] ?? '',
                    ];
                    foreach ($docs as $lab => $path):
                        $url = recruitmentPublicPath($path);
                    ?>
                        <?php if ($url): ?>
                            <a class="rec-app-doc" href="<?php echo htmlspecialchars($url); ?>" target="_blank" rel="noopener">
                                <i class="fa-solid fa-paperclip"></i> <?php echo htmlspecialchars($lab); ?>
                            </a>
                        <?php else: ?>
                            <span class="rec-app-doc is-empty"><?php echo htmlspecialchars($lab); ?> — N/A</span>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="view-card view-card-wide">
            <div class="view-card-head">
                <i class="fa-solid fa-graduation-cap"></i>
                <div><h3>Educational Details</h3><p>Qualifications submitted</p></div>
            </div>
            <div class="view-body">
                <?php if (empty($row['education'])): ?>
                    <p class="rec-app-empty">No education details.</p>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead><tr><th>Degree</th><th>Institution</th><th>Specialization</th><th>Year</th><th>% / CGPA</th></tr></thead>
                        <tbody>
                        <?php foreach ($row['education'] as $ed): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($v($ed['degree'] ?? '')); ?></td>
                                <td><?php echo htmlspecialchars($v($ed['institution'] ?? '')); ?></td>
                                <td><?php echo htmlspecialchars($v($ed['specialization'] ?? '')); ?></td>
                                <td><?php echo htmlspecialchars($v($ed['year_of_passing'] ?? '')); ?></td>
                                <td><?php echo htmlspecialchars($v($ed['percentage'] ?? '')); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="view-card view-card-wide">
            <div class="view-card-head">
                <i class="fa-solid fa-briefcase"></i>
                <div><h3>Experience Details</h3><p><?php echo htmlspecialchars($v($row['total_experience'] ?? '—')); ?></p></div>
            </div>
            <div class="view-body">
                <?php if (empty($row['experience'])): ?>
                    <p class="rec-app-empty">No experience details.</p>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead><tr><th>Company</th><th>Designation</th><th>From</th><th>To</th><th>Salary</th><th>Notes</th></tr></thead>
                        <tbody>
                        <?php foreach ($row['experience'] as $ex): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($v($ex['company_name'] ?? '')); ?></td>
                                <td><?php echo htmlspecialchars($v($ex['designation'] ?? '')); ?></td>
                                <td><?php echo htmlspecialchars($v($ex['from_date'] ?? '')); ?></td>
                                <td><?php echo !empty($ex['is_current']) ? 'Present' : htmlspecialchars($v($ex['to_date'] ?? '')); ?></td>
                                <td><?php echo $money($ex['last_salary'] ?? null); ?></td>
                                <td><?php echo nl2br(htmlspecialchars($v($ex['responsibilities'] ?? ''))); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($canEdit): ?>
        <div class="view-card view-card-wide">
            <div class="view-card-head">
                <i class="fa-solid fa-pen-to-square"></i>
                <div><h3>Update Status</h3><p>Application status &amp; HR remarks</p></div>
            </div>
            <div class="view-body">
                <form method="POST" action="<?php echo app_url('recruitment/status_save.php'); ?>" class="employee-form">
                    <input type="hidden" name="id" value="<?php echo (int) $row['id']; ?>">
                    <div class="form-grid form-grid-2">
                        <div class="form-group">
                            <label>Status</label>
                            <select name="status" class="form-control" required>
                                <?php foreach ($labels as $k => $lab): ?>
                                    <option value="<?php echo htmlspecialchars($k); ?>" <?php echo $statusKey === $k ? 'selected' : ''; ?>>
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
                        <a href="<?php echo app_url('recruitment/interview.php?id=' . (int) $row['id']); ?>" class="btn-secondary">
                            <i class="fa-solid fa-clipboard-user"></i> Go to Interview Marking
                        </a>
                    </div>
                </form>
            </div>
        </div>
        <?php endif; ?>
    </div>
</main>

<style>
.rec-app-id { margin-bottom: 16px; }
.rec-app-grid { width: 100%; max-width: none; }
.view-body { padding: 12px 16px 16px; }
.view-body-3 { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 10px 14px; }
.view-row { display: flex; flex-direction: column; gap: 2px; padding: 6px 0; border-bottom: 1px dashed #f1f5f9; }
.view-row span { font-size: 11px; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: .03em; }
.view-row strong { font-size: 14px; font-weight: 700; color: #0f172a; line-height: 1.4; word-break: break-word; }
.view-row-full { grid-column: 1 / -1; }
.rec-app-docs { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 10px; }
.rec-app-doc {
    display: inline-flex; align-items: center; gap: 6px; padding: 7px 10px; border-radius: 9px;
    border: 1px solid #fecaca; background: #fff5f5; color: #b91c1c; font-size: 12px; font-weight: 700; text-decoration: none;
}
.rec-app-doc.is-empty { background: #f8fafc; border-color: #e2e8f0; color: #94a3b8; }
.rec-app-empty { margin: 0; color: #94a3b8; font-weight: 600; }
.status-badge { display:inline-flex;padding:5px 11px;border-radius:999px;font-size:11px;font-weight:800; }
.status-new { background:#dbeafe;color:#1d4ed8; }
.status-review { background:#fef3c7;color:#b45309; }
.status-shortlisted { background:#dcfce7;color:#15803d; }
.status-interview { background:#fef3c7;color:#b45309; }
.status-awaited { background:#ffedd5;color:#c2410c; }
.status-selected { background:#dcfce7;color:#15803d; }
.status-not_selected,.status-rejected { background:#fee2e2;color:#b91c1c; }
.status-hired { background:#e0e7ff;color:#3730a3; }
@media (max-width: 900px) {
    .view-body-3 { grid-template-columns: 1fr; }
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
