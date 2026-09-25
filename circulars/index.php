<?php
/**
 * Circulars — list (HR / Admin)
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/master_helper.php';
require_once __DIR__ . '/../includes/circular_helper.php';

requireStaff();
ensureCircularTables();

$year = (int) ($_GET['year'] ?? 0);
$deptFilter = (int) ($_GET['department_id'] ?? 0);
$rows = fetchCirculars($year, $deptFilter);
$departments = getActiveMasterRows('departments', 'sort_order ASC, department_name ASC');

$pageTitle = 'Circulars';
$useSidebar = true;
$sidebarMode = 'workspace';
$sidebarActive = 'circulars';
$extraCss = ['https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css'];

require_once __DIR__ . '/../includes/header.php';

$toast = '';
$toastType = 'success';
if (isset($_GET['msg'])) {
    $map = [
        'added' => 'Circular added successfully. Notification sent to bell.',
        'updated' => 'Circular updated successfully.',
        'deleted' => 'Circular deleted.',
        'error' => (string) ($_GET['err'] ?? 'Something went wrong.'),
    ];
    $toast = $map[$_GET['msg']] ?? '';
    if ($_GET['msg'] === 'error') {
        $toastType = 'error';
    }
}
?>

<main class="dashboard-main">
    <div class="page-toolbar flex-between">
        <a href="<?php echo app_url('hr/dashboard.php'); ?>" class="back-link">
            <i class="fa-solid fa-arrow-left"></i> Back to HR Dashboard
        </a>
        <div class="toolbar-actions" style="display:flex;gap:8px;flex-wrap:wrap;">
            <a href="<?php echo app_url('circulars/edit.php'); ?>" class="btn-primary">
                <i class="fa-solid fa-plus"></i> Add Circular
            </a>
        </div>
    </div>

    <div class="form-page-card">
        <div class="form-page-header">
            <div class="master-list-title">
                <div class="master-list-icon" style="background:#0F766E;">
                    <i class="fa-solid fa-file-circle-plus"></i>
                </div>
                <div>
                    <h1>Company Circulars</h1>
                    <p>Department-wise PDF circulars · Bell notifications for unread</p>
                </div>
            </div>
        </div>

        <form method="GET" class="employee-form register-filters">
            <div class="form-grid form-grid-3">
                <div class="form-group">
                    <label>Year</label>
                    <select name="year" class="form-control" onchange="this.form.submit()">
                        <option value="0">All years</option>
                        <?php for ($y = (int) date('Y') + 1; $y >= 2020; $y--): ?>
                            <option value="<?php echo $y; ?>" <?php echo $year === $y ? 'selected' : ''; ?>><?php echo $y; ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Department</label>
                    <select name="department_id" class="form-control" onchange="this.form.submit()">
                        <option value="0">All / Any</option>
                        <?php foreach ($departments as $d): ?>
                            <option value="<?php echo (int) $d['id']; ?>" <?php echo $deptFilter === (int) $d['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($d['department_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </form>
    </div>

    <div class="form-page-card" style="margin-top:14px;">
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Sr</th>
                        <th>Title</th>
                        <th>Departments</th>
                        <th>Circular No</th>
                        <th>Circular Date</th>
                        <th>Added On</th>
                        <th>Uploaded By</th>
                        <th>PDF</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$rows): ?>
                    <tr><td colspan="9" class="empty-cell">No circulars yet. Click <strong>Add Circular</strong> to upload a scanned PDF.</td></tr>
                <?php endif; ?>
                <?php foreach ($rows as $i => $r): ?>
                    <tr>
                        <td><?php echo $i + 1; ?></td>
                        <td>
                            <strong><?php echo htmlspecialchars($r['title']); ?></strong>
                            <?php if (!empty($r['remarks'])): ?>
                                <div class="sr-code"><?php echo htmlspecialchars(strlen($r['remarks']) > 80 ? substr($r['remarks'], 0, 77) . '…' : $r['remarks']); ?></div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="circ-dept-tag <?php echo !empty($r['apply_all_departments']) ? 'is-all' : ''; ?>">
                                <?php echo htmlspecialchars(circularDepartmentsLabel($r)); ?>
                            </span>
                        </td>
                        <td><?php echo htmlspecialchars($r['circular_no'] ?: '—'); ?></td>
                        <td><?php echo htmlspecialchars(formatDateDisplay($r['circular_date'])); ?></td>
                        <td><?php echo htmlspecialchars(formatDateDisplay($r['added_date'])); ?></td>
                        <td><?php echo htmlspecialchars($r['created_by_name'] ?: '—'); ?></td>
                        <td>
                            <?php if (circularFileExists($r['pdf_file'] ?? '')): ?>
                                <a class="action-btn edit" title="View PDF"
                                   href="<?php echo app_url('circulars/view.php?id=' . (int) $r['id']); ?>">
                                    <i class="fa-solid fa-file-pdf"></i>
                                </a>
                            <?php else: ?>
                                <span class="sr-code">Missing</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a class="action-btn edit" title="Edit"
                               href="<?php echo app_url('circulars/edit.php?id=' . (int) $r['id']); ?>">
                                <i class="fa-solid fa-pen"></i>
                            </a>
                            <a class="action-btn delete btn-delete" title="Delete"
                               href="<?php echo app_url('circulars/delete.php?id=' . (int) $r['id']); ?>"
                               data-name="<?php echo htmlspecialchars($r['title']); ?>">
                                <i class="fa-solid fa-trash"></i>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>
<?php if ($toast): ?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
<script>
toastr.options = { closeButton: true, progressBar: true, positionClass: 'toast-top-right' };
toastr.<?php echo $toastType === 'error' ? 'error' : 'success'; ?>('<?php echo addslashes($toast); ?>');
</script>
<?php endif; ?>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
