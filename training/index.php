<?php
/**
 * Training module — General (all non-sales) / Sales (sales staff only)
 * Status follows track: general OR sales — not both.
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permission_helper.php';
require_once __DIR__ . '/../includes/employee_helper.php';
require_once __DIR__ . '/../includes/induction_helper.php';

requireLogin();

$deptId = isset($_GET['department_id']) ? (int) $_GET['department_id'] : 0;
requireAccess('employees', 'view', $deptId);

$canEditStatus = isAdmin() || isHR();
$q = trim((string) ($_GET['q'] ?? ''));
$typeFilter = strtolower(trim((string) ($_GET['type'] ?? 'all')));
if (!in_array($typeFilter, ['all', 'general', 'sales'], true)) {
    $typeFilter = 'all';
}
$statusFilter = strtolower(trim((string) ($_GET['status'] ?? 'all')));
$statusLabels = employeeTrainingStatusLabels();
if ($statusFilter !== 'all' && !isset($statusLabels[$statusFilter])) {
    $statusFilter = 'all';
}

$conn = getDBConnection();
ensureEmployeesTable($conn);
ensureEmployeeTrainingTables($conn);

$departments = [];
$dres = $conn->query('SELECT id, department_name FROM departments WHERE status = 1 ORDER BY sort_order ASC, department_name ASC');
if ($dres) {
    while ($r = $dres->fetch_assoc()) {
        $departments[] = $r;
    }
}

$sql = "SELECT e.id, e.employee_code, e.employee_name, e.designation, e.date_of_joining,
               e.department_id, d.department_name
        FROM employees e
        LEFT JOIN departments d ON d.id = e.department_id
        WHERE e.status = 1";
$types = '';
$params = [];
if ($deptId > 0) {
    $sql .= ' AND e.department_id = ?';
    $types .= 'i';
    $params[] = $deptId;
}
if ($q !== '') {
    $sql .= ' AND (e.employee_name LIKE ? OR e.employee_code LIKE ? OR e.designation LIKE ?)';
    $like = '%' . $q . '%';
    $types .= 'sss';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}
$sql .= ' ORDER BY COALESCE(e.date_of_joining, \'1970-01-01\') DESC, e.employee_name ASC LIMIT 300';

$rows = [];
if ($types !== '') {
    $st = $conn->prepare($sql);
    $st->bind_param($types, ...$params);
    $st->execute();
    $res = $st->get_result();
    while ($r = $res->fetch_assoc()) {
        $rows[] = $r;
    }
    $st->close();
} else {
    $res = $conn->query($sql);
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $rows[] = $r;
        }
    }
}

$ids = array_map(static function ($r) {
    return (int) $r['id'];
}, $rows);
$statusMap = getEmployeeTrainingStatusMap($ids, $conn);
$conn->close();

$filtered = [];
foreach ($rows as $r) {
    $track = employeeTrainingTrackType($r);
    $isSales = ($track === 'sales');
    if ($typeFilter === 'sales' && !$isSales) {
        continue;
    }
    if ($typeFilter === 'general' && $isSales) {
        continue;
    }
    $eid = (int) $r['id'];
    $trackStatus = (string) ($statusMap[$eid][$track]['status'] ?? 'pending');
    if ($statusFilter !== 'all' && $trackStatus !== $statusFilter) {
        continue;
    }
    $r['_is_sales'] = $isSales;
    $r['_track'] = $track;
    $r['_track_status'] = $trackStatus;
    $filtered[] = $r;
}

$pageTitle = 'Training';
$useSidebar = true;
$sidebarMode = 'training';
$sidebarActive = $typeFilter === 'sales' ? 'training_sales' : ($typeFilter === 'general' ? 'training_induction' : 'training');
$sidebarDeptId = $deptId;
$extraCss = ['https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css'];
$extraJs = ['https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js'];

require_once __DIR__ . '/../includes/header.php';

$statusSaveUrl = app_url('training/status_save.php');
?>

<main class="dashboard-main">
    <div class="page-toolbar flex-between">
        <a href="<?php echo app_url('dashboard.php'); ?>" class="back-link">
            <i class="fa-solid fa-arrow-left"></i> Back to Dashboard
        </a>
    </div>

    <div class="form-page-card">
        <div class="form-page-header">
            <h1><i class="fa-solid fa-chalkboard-user" style="color:#d2232a;"></i> Training</h1>
            <p>
                <strong>Sales &amp; Marketing – Back Office</strong> ane
                <strong>Sales &amp; Marketing – On Field</strong> → Sales Training + Sales status.
                Baki badha departments → General Induction + General status.
            </p>
        </div>

        <div class="leave-rpt-rules" style="margin-bottom:14px;">
            <span class="leave-rpt-rule"><i class="fa-solid fa-headset" style="color:#7c3aed;"></i> Back Office → Sales Training</span>
            <span class="leave-rpt-rule"><i class="fa-solid fa-handshake" style="color:#0d9488;"></i> On Field → Sales Training</span>
            <span class="leave-rpt-rule"><i class="fa-solid fa-users" style="color:#15803d;"></i> Other depts → General Induction</span>
        </div>

        <form method="GET" class="employee-form leave-rpt-filters" style="margin-bottom:16px;">
            <div class="form-grid form-grid-4">
                <div class="form-group">
                    <label>Department</label>
                    <select name="department_id" class="form-control" onchange="this.form.submit()">
                        <option value="0">All Departments</option>
                        <?php foreach ($departments as $d): ?>
                            <option value="<?php echo (int) $d['id']; ?>" <?php echo $deptId === (int) $d['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($d['department_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Staff Type</label>
                    <select name="type" class="form-control" onchange="this.form.submit()">
                        <option value="all" <?php echo $typeFilter === 'all' ? 'selected' : ''; ?>>All</option>
                        <option value="general" <?php echo $typeFilter === 'general' ? 'selected' : ''; ?>>General staff</option>
                        <option value="sales" <?php echo $typeFilter === 'sales' ? 'selected' : ''; ?>>Sales staff</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Training Status</label>
                    <select name="status" class="form-control" onchange="this.form.submit()">
                        <option value="all" <?php echo $statusFilter === 'all' ? 'selected' : ''; ?>>All Status</option>
                        <?php foreach ($statusLabels as $sk => $sl): ?>
                            <option value="<?php echo htmlspecialchars($sk); ?>" <?php echo $statusFilter === $sk ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($sl); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Search</label>
                    <div style="display:flex;gap:8px;">
                        <input type="text" name="q" class="form-control" value="<?php echo htmlspecialchars($q); ?>"
                               placeholder="Name / Code / Designation">
                        <button type="submit" class="btn-primary"><i class="fa-solid fa-magnifying-glass"></i></button>
                    </div>
                </div>
            </div>
        </form>

        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Employee</th>
                        <th>Designation</th>
                        <th>Department</th>
                        <th>Joining</th>
                        <th>Training Track</th>
                        <th>Training Status</th>
                        <th style="width:180px;">Print Sheet</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$filtered): ?>
                    <tr><td colspan="8" style="text-align:center;padding:24px;color:#64748b;">No employees found.</td></tr>
                <?php else: foreach ($filtered as $r):
                    $eid = (int) $r['id'];
                    $track = (string) $r['_track'];
                    $trackStatus = (string) $r['_track_status'];
                    $isSales = !empty($r['_is_sales']);
                ?>
                    <tr data-emp="<?php echo $eid; ?>">
                        <td><strong><?php echo htmlspecialchars((string) $r['employee_code']); ?></strong></td>
                        <td><?php echo htmlspecialchars((string) $r['employee_name']); ?></td>
                        <td><?php echo htmlspecialchars((string) ($r['designation'] ?: '—')); ?></td>
                        <td><?php echo htmlspecialchars((string) ($r['department_name'] ?: '—')); ?></td>
                        <td><?php
                            echo !empty($r['date_of_joining']) && $r['date_of_joining'] !== '0000-00-00'
                                ? htmlspecialchars(formatDateDisplay($r['date_of_joining']))
                                : '—';
                        ?></td>
                        <td>
                            <?php if ($isSales): ?>
                                <span class="status-badge" style="background:#fee2e2;color:#9f1239;">Sales Training</span>
                            <?php else: ?>
                                <span class="status-badge" style="background:#dcfce7;color:#15803d;">General Induction</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($canEditStatus): ?>
                            <select class="form-control js-train-status"
                                    data-type="<?php echo htmlspecialchars($track); ?>"
                                    data-emp="<?php echo $eid; ?>"
                                    style="min-width:140px;font-size:12px;padding:6px 8px;">
                                <?php foreach ($statusLabels as $sk => $sl): ?>
                                    <option value="<?php echo htmlspecialchars($sk); ?>" <?php echo $trackStatus === $sk ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($sl); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php else: ?>
                                <span class="status-badge" style="<?php echo employeeTrainingStatusBadgeStyle($trackStatus); ?>">
                                    <?php echo htmlspecialchars($statusLabels[$trackStatus] ?? $trackStatus); ?>
                                </span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($isSales): ?>
                            <a class="btn-ghost" style="padding:6px 10px;font-size:12px;" target="_blank"
                               href="<?php echo app_url('employees/induction.php?id=' . $eid . '&type=sales'); ?>">
                                <i class="fa-solid fa-handshake"></i> Sales Sheet
                            </a>
                            <?php else: ?>
                            <a class="btn-ghost" style="padding:6px 10px;font-size:12px;" target="_blank"
                               href="<?php echo app_url('employees/induction.php?id=' . $eid . '&type=general'); ?>">
                                <i class="fa-solid fa-clipboard-list"></i> Induction
                            </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<?php if ($canEditStatus): ?>
<script>
(function () {
    var saveUrl = <?php echo json_encode($statusSaveUrl); ?>;
    document.querySelectorAll('.js-train-status').forEach(function (sel) {
        sel.addEventListener('change', function () {
            var empId = sel.getAttribute('data-emp');
            var type = sel.getAttribute('data-type');
            var status = sel.value;
            sel.disabled = true;
            var fd = new FormData();
            fd.append('ajax', '1');
            fd.append('employee_id', empId);
            fd.append('training_type', type);
            fd.append('status', status);
            fetch(saveUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    sel.disabled = false;
                    if (data && data.ok) {
                        if (window.toastr) {
                            toastr.success((type === 'sales' ? 'Sales' : 'General') + ' → ' + (data.status_label || status));
                        }
                    } else {
                        if (window.toastr) {
                            toastr.error((data && data.error) ? data.error : 'Save failed');
                        } else {
                            alert((data && data.error) ? data.error : 'Save failed');
                        }
                    }
                })
                .catch(function () {
                    sel.disabled = false;
                    if (window.toastr) toastr.error('Network error');
                    else alert('Network error');
                });
        });
    });
})();
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
