<?php
/**
 * Recruitment — Applications list (HR / Admin)
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

ensureRecruitmentTables();
$conn = getDBConnection();

$status = trim((string) ($_GET['status'] ?? ''));
$q = trim((string) ($_GET['q'] ?? ''));
$labels = recruitmentStatusLabels();

$where = ['1=1'];
$types = '';
$params = [];
if ($status !== '' && isset($labels[$status])) {
    $where[] = 'status = ?';
    $types .= 's';
    $params[] = $status;
}
if ($q !== '') {
    $where[] = '(full_name LIKE ? OR mobile LIKE ? OR email LIKE ? OR application_no LIKE ? OR position_name LIKE ? OR department_name LIKE ?)';
    $like = '%' . $q . '%';
    $types .= 'ssssss';
    array_push($params, $like, $like, $like, $like, $like, $like);
}

$sql = 'SELECT id, application_no, full_name, mobile, email, department_name, position_name,
               current_salary, expected_salary, status, created_at
        FROM recruitment_applications
        WHERE ' . implode(' AND ', $where) . '
        ORDER BY id DESC
        LIMIT 300';

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

$countNew = 0;
$cn = $conn->query("SELECT COUNT(*) AS c FROM recruitment_applications WHERE status = 'new'");
if ($cn) {
    $countNew = (int) ($cn->fetch_assoc()['c'] ?? 0);
}
$conn->close();

$pageTitle = 'Recruitment';
$useSidebar = true;
$sidebarActive = 'recruitment';

require_once __DIR__ . '/../includes/header.php';
?>

<main class="dashboard-main">
    <div class="page-toolbar flex-between">
        <div>
            <h1 style="margin:0;font-size:22px;font-weight:800;">Recruitment Applications</h1>
            <p style="margin:4px 0 0;color:#64748b;font-size:13px;">
                <?php echo (int) $countNew; ?> new · Public form via fixed QR
            </p>
        </div>
        <div class="toolbar-actions" style="display:flex;gap:8px;flex-wrap:wrap;">
            <a href="<?php echo app_url('recruitment/qr.php'); ?>" class="btn-primary">
                <i class="fa-solid fa-qrcode"></i> Fixed QR Code
            </a>
            <a href="<?php echo htmlspecialchars(recruitmentApplyUrl()); ?>" class="btn-secondary" target="_blank" rel="noopener">
                <i class="fa-solid fa-arrow-up-right-from-square"></i> Open Apply Form
            </a>
        </div>
    </div>

    <form method="GET" class="filters-bar" style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:14px;">
        <input type="search" name="q" class="form-control" style="max-width:280px;"
               placeholder="Search name, mobile, app no…"
               value="<?php echo htmlspecialchars($q); ?>">
        <select name="status" class="form-control" style="max-width:180px;">
            <option value="">All Status</option>
            <?php foreach ($labels as $k => $lab): ?>
                <option value="<?php echo htmlspecialchars($k); ?>" <?php echo $status === $k ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($lab); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn-secondary"><i class="fa-solid fa-filter"></i> Filter</button>
    </form>

    <div class="table-card">
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                <tr>
                    <th>App No</th>
                    <th>Applicant</th>
                    <th>Position</th>
                    <th>Department</th>
                    <th>Expected</th>
                    <th>Status</th>
                    <th>Applied</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                <?php if (!$rows): ?>
                    <tr><td colspan="8" class="text-center">No applications yet. Share the QR / apply link.</td></tr>
                <?php else: ?>
                    <?php foreach ($rows as $r): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars((string) $r['application_no']); ?></strong></td>
                            <td>
                                <strong><?php echo htmlspecialchars((string) $r['full_name']); ?></strong><br>
                                <small><?php echo htmlspecialchars((string) $r['mobile']); ?></small>
                            </td>
                            <td><?php echo htmlspecialchars((string) $r['position_name']); ?></td>
                            <td><?php echo htmlspecialchars((string) $r['department_name']); ?></td>
                            <td><?php echo $r['expected_salary'] !== null ? '₹ ' . number_format((float) $r['expected_salary'], 0) : '—'; ?></td>
                            <td>
                                <span class="status-badge status-<?php echo htmlspecialchars((string) $r['status']); ?>">
                                    <?php echo htmlspecialchars($labels[$r['status']] ?? $r['status']); ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars(date('d M Y', strtotime((string) $r['created_at']))); ?></td>
                            <td>
                                <a href="<?php echo app_url('recruitment/view.php?id=' . (int) $r['id']); ?>"
                                   class="action-btn edit" title="View"><i class="fa-solid fa-eye"></i></a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<style>
.status-badge {
    display: inline-flex; padding: 4px 10px; border-radius: 999px;
    font-size: 11px; font-weight: 800;
}
.status-new { background: #dbeafe; color: #1d4ed8; }
.status-review { background: #fef3c7; color: #b45309; }
.status-shortlisted { background: #dcfce7; color: #15803d; }
.status-rejected { background: #fee2e2; color: #b91c1c; }
.status-hired { background: #e0e7ff; color: #3730a3; }
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
