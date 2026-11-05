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
$extraCss = [
    'https://cdn.datatables.net/1.13.8/css/jquery.dataTables.min.css',
    'https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css',
];
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

    <div class="list-header">
        <div>
            <h1>SOP</h1>
            <p><?php echo htmlspecialchars($department['department_name']); ?> · Letterhead · Share</p>
        </div>
    </div>

    <div class="data-card data-card-pad">
        <div class="table-wrap">
            <table id="sopTable" class="display data-table nowrap" style="width:100%">
                <thead>
                    <tr>
                        <th>Sr.</th>
                        <th>Title</th>
                        <th>Date</th>
                        <th>Subject</th>
                        <th>Department</th>
                        <th>Letterhead</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $i => $row): ?>
                    <?php $shareUrl = cncSopShareUrl($row); ?>
                    <tr>
                        <td><?php echo $i + 1; ?></td>
                        <td><?php echo htmlspecialchars($row['title']); ?></td>
                        <td data-order="<?php echo htmlspecialchars((string) $row['sop_date']); ?>"><?php echo htmlspecialchars(formatDateDisplay($row['sop_date'])); ?></td>
                        <td><?php echo htmlspecialchars($row['subject']); ?></td>
                        <td><?php echo htmlspecialchars($row['department_name']); ?></td>
                        <td class="pdf-links">
                            <a class="pdf-btn hi" title="Letterhead" href="<?php echo app_url('sop/print.php?id=' . (int) $row['id'] . '&department_id=' . $deptId); ?>">PDF</a>
                        </td>
                        <td>
                            <div class="action-links">
                                <?php if (canAccess('departments', 'edit', $deptId)): ?>
                                <a class="action-btn edit" title="Edit" href="<?php echo app_url('sop/edit.php?id=' . (int) $row['id'] . '&department_id=' . $deptId); ?>"><i class="fa-solid fa-pen"></i></a>
                                <?php endif; ?>
                                <button type="button" class="action-btn share js-sop-share" title="Share" data-url="<?php echo htmlspecialchars($shareUrl); ?>" data-title="<?php echo htmlspecialchars($row['title']); ?>"><i class="fa-solid fa-share-nodes"></i></button>
                                <?php if (canAccess('departments', 'delete', $deptId)): ?>
                                <form method="post" class="sop-del-form" action="<?php echo app_url('sop/delete.php'); ?>" onsubmit="return confirm('Delete this SOP?');">
                                    <input type="hidden" name="id" value="<?php echo (int) $row['id']; ?>">
                                    <input type="hidden" name="department_id" value="<?php echo $deptId; ?>">
                                    <button type="submit" class="action-btn delete" title="Delete"><i class="fa-solid fa-trash"></i></button>
                                </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>
<script>
    window.SOP_TOAST_MSG = <?php echo json_encode($toast); ?>;
</script>
<?php
$extraJs = [
    'https://code.jquery.com/jquery-3.7.1.min.js',
    'https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js',
    'https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js',
    'assets/js/sop_list.js',
];
require_once __DIR__ . '/../includes/footer.php';
?>
