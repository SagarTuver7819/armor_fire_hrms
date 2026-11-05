<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/employee_helper.php';
require_once __DIR__ . '/../includes/permission_helper.php';
require_once __DIR__ . '/../includes/sop_helper.php';

requireLogin();
$deptId = (int) ($_GET['department_id'] ?? 0);
requireAccess('departments', 'view', $deptId);
$department = $deptId > 0 ? getDepartmentById($deptId) : null;
if (!$department || !cncSopIsCncDepartment($department)) {
    header('Location: ' . app_url('dashboard.php'));
    exit;
}

$conn = getDBConnection();
$rows = cncSopList($conn, $deptId);
$conn->close();

$pageTitle = 'SOP';
$useSidebar = true;
$sidebarMode = 'department';
$sidebarDeptId = $deptId;
$sidebarActive = 'cnc_sop';
require_once __DIR__ . '/../includes/header.php';

$toast = '';
if (isset($_GET['msg'])) {
    $map = [
        'added' => 'SOP saved on the letterhead.',
        'updated' => 'SOP updated.',
        'deleted' => 'SOP deleted.',
    ];
    $toast = $map[$_GET['msg']] ?? '';
}
?>
<main class="dashboard-main">
    <div class="page-toolbar flex-between">
        <a href="<?php echo app_url('department.php?id=' . $deptId); ?>" class="back-link">
            <i class="fa-solid fa-arrow-left"></i> Back to CNC Department
        </a>
        <?php if (canAccess('departments', 'add', $deptId)): ?>
        <a href="<?php echo app_url('sop/edit.php?department_id=' . $deptId); ?>" class="btn-primary">
            <i class="fa-solid fa-plus"></i> Add SOP
        </a>
        <?php endif; ?>
    </div>

    <div class="form-page-card sop-list">
        <div class="form-page-header">
            <div class="master-list-title">
                <div class="master-list-icon" style="background:#d2232a;">
                    <i class="fa-solid fa-file-signature"></i>
                </div>
                <div>
                    <h1>SOP</h1>
                    <p>Letterhead · Title, Date, Subject, Department, Body · Digital signature · Share</p>
                </div>
            </div>
        </div>

        <?php if ($toast !== ''): ?>
            <div class="form-alert" style="margin:0 0 14px;padding:10px 12px;border-radius:8px;background:#ecfdf5;color:#166534;font-weight:700;">
                <?php echo htmlspecialchars($toast); ?>
            </div>
        <?php endif; ?>

        <div class="table-wrap">
            <table class="data-table" style="width:100%;">
                <thead>
                    <tr>
                        <th>Sr</th>
                        <th>Title</th>
                        <th>Date</th>
                        <th>Subject</th>
                        <th>Department</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$rows): ?>
                    <tr><td colspan="6" style="text-align:center;padding:18px;">No SOP yet. Add one on the letterhead.</td></tr>
                <?php else: ?>
                    <?php foreach ($rows as $i => $row): ?>
                        <?php $shareUrl = cncSopShareUrl($row); ?>
                        <tr>
                            <td><?php echo $i + 1; ?></td>
                            <td><?php echo htmlspecialchars($row['title']); ?></td>
                            <td><?php echo htmlspecialchars(formatDateDisplay($row['sop_date'])); ?></td>
                            <td><?php echo htmlspecialchars($row['subject']); ?></td>
                            <td><?php echo htmlspecialchars($row['department_name']); ?></td>
                            <td>
                                <div class="sop-actions">
                                    <a class="sop-act letter" href="<?php echo app_url('sop/print.php?id=' . (int) $row['id'] . '&department_id=' . $deptId); ?>"><i class="fa-solid fa-file-lines"></i> Letterhead</a>
                                    <?php if (canAccess('departments', 'edit', $deptId)): ?>
                                    <a class="sop-act edit" href="<?php echo app_url('sop/edit.php?id=' . (int) $row['id'] . '&department_id=' . $deptId); ?>"><i class="fa-solid fa-pen"></i> Edit</a>
                                    <?php endif; ?>
                                    <button type="button" class="sop-act share js-sop-share" data-url="<?php echo htmlspecialchars($shareUrl); ?>" data-title="<?php echo htmlspecialchars($row['title']); ?>"><i class="fa-solid fa-share-nodes"></i> Share</button>
                                    <?php if (canAccess('departments', 'delete', $deptId)): ?>
                                    <form method="post" action="<?php echo app_url('sop/delete.php'); ?>" onsubmit="return confirm('Delete this SOP?');">
                                        <input type="hidden" name="id" value="<?php echo (int) $row['id']; ?>">
                                        <input type="hidden" name="department_id" value="<?php echo $deptId; ?>">
                                        <button type="submit" class="sop-act delete"><i class="fa-solid fa-trash"></i> Delete</button>
                                    </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>
<script>
document.querySelectorAll('.js-sop-share').forEach(function (btn) {
    btn.addEventListener('click', function () {
        var url = btn.getAttribute('data-url') || '';
        var title = btn.getAttribute('data-title') || 'SOP';
        var text = title + '\n' + url;
        if (navigator.share) {
            navigator.share({ title: title, text: title, url: url }).catch(function () {});
            return;
        }
        window.open('https://wa.me/?text=' + encodeURIComponent(text), '_blank', 'noopener');
    });
});
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
