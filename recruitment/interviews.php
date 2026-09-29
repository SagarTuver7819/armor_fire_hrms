<?php
/**
 * Recruitment — Interview Candidates list
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
$dueOnly = isset($_GET['due']) && (string) $_GET['due'] === '1';
$labels = recruitmentStatusLabels();
$modes = recruitmentInterviewModes();
$sides = recruitmentAwaitedSides();

$where = ["status IN ('new','interview','awaited','selected','not_selected','review','shortlisted','rejected','hired')"];
$types = '';
$params = [];

if ($status !== '' && isset($labels[$status])) {
    $where = ['status = ?'];
    $types .= 's';
    $params[] = $status;
}
if ($dueOnly) {
    $where[] = "status = 'awaited' AND next_followup_at IS NOT NULL AND next_followup_at <= CURDATE()";
}
if ($q !== '') {
    $where[] = '(full_name LIKE ? OR mobile LIKE ? OR email LIKE ? OR application_no LIKE ? OR position_name LIKE ? OR department_name LIKE ?)';
    $like = '%' . $q . '%';
    $types .= 'ssssss';
    array_push($params, $like, $like, $like, $like, $like, $like);
}

$sql = 'SELECT id, application_no, full_name, mobile, email, department_name, position_name,
               interview_mode, interview_date, awaited_with, status, next_followup_at, call_count,
               total_experience, age_years, created_at
        FROM recruitment_applications
        WHERE ' . implode(' AND ', $where) . '
        ORDER BY
            CASE WHEN status = \'awaited\' AND next_followup_at IS NOT NULL AND next_followup_at <= CURDATE() THEN 0 ELSE 1 END,
            id DESC
        LIMIT 400';

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

$dueCount = 0;
$dc = $conn->query(
    "SELECT COUNT(*) AS c FROM recruitment_applications
     WHERE status = 'awaited' AND next_followup_at IS NOT NULL AND next_followup_at <= CURDATE()"
);
if ($dc) {
    $dueCount = (int) ($dc->fetch_assoc()['c'] ?? 0);
}
$conn->close();

$pageTitle = 'Interview Candidates';
$useSidebar = true;
$sidebarMode = 'recruitment';
$sidebarActive = 'recruitment_interview';

require_once __DIR__ . '/../includes/header.php';
?>

<main class="dashboard-main">
    <div class="page-toolbar flex-between">
        <div>
            <h1 style="margin:0;font-size:22px;font-weight:800;">Interview Candidates</h1>
            <p style="margin:4px 0 0;color:#64748b;font-size:13px;">
                Application → Interview → Selected / Not Selected / Awaited · Follow-up every 6 days
            </p>
        </div>
        <div class="toolbar-actions" style="display:flex;gap:8px;flex-wrap:wrap;">
            <?php if ($dueCount > 0): ?>
            <a href="<?php echo app_url('recruitment/interviews.php?due=1'); ?>" class="btn-primary">
                <i class="fa-solid fa-bell"></i> Due Follow-ups (<?php echo (int) $dueCount; ?>)
            </a>
            <?php endif; ?>
            <a href="<?php echo app_url('recruitment/index.php'); ?>" class="btn-secondary">
                <i class="fa-solid fa-list"></i> All Applications
            </a>
            <a href="<?php echo app_url('recruitment/qr.php'); ?>" class="btn-secondary">
                <i class="fa-solid fa-qrcode"></i> QR Code
            </a>
        </div>
    </div>

    <form method="GET" class="filters-bar" style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:14px;">
        <input type="search" name="q" class="form-control" style="max-width:280px;"
               placeholder="Search name, mobile, app no…"
               value="<?php echo htmlspecialchars($q); ?>">
        <select name="status" class="form-control" style="max-width:200px;">
            <option value="">All Status</option>
            <?php foreach (['new', 'interview', 'awaited', 'selected', 'not_selected'] as $k): ?>
                <option value="<?php echo htmlspecialchars($k); ?>" <?php echo $status === $k ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($labels[$k]); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <label style="display:flex;align-items:center;gap:6px;font-size:13px;font-weight:700;">
            <input type="checkbox" name="due" value="1" <?php echo $dueOnly ? 'checked' : ''; ?>>
            Follow-up due only
        </label>
        <button type="submit" class="btn-secondary"><i class="fa-solid fa-filter"></i> Filter</button>
    </form>

    <div class="table-card">
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                <tr>
                    <th>App No</th>
                    <th>Candidate</th>
                    <th>Position</th>
                    <th>Interview</th>
                    <th>Status</th>
                    <th>Follow-up</th>
                    <th>Calls</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                <?php if (!$rows): ?>
                    <tr><td colspan="8" class="text-center">No candidates found.</td></tr>
                <?php else: ?>
                    <?php foreach ($rows as $r):
                        $isDue = recruitmentIsFollowupDue($r);
                        $stKey = (string) $r['status'];
                    ?>
                        <tr class="<?php echo $isDue ? 'rec-row-due' : ''; ?>">
                            <td><strong><?php echo htmlspecialchars((string) $r['application_no']); ?></strong></td>
                            <td>
                                <strong><?php echo htmlspecialchars((string) $r['full_name']); ?></strong>
                                <?php if (!empty($r['age_years'])): ?>
                                    <small> · <?php echo (int) $r['age_years']; ?> yrs</small>
                                <?php endif; ?>
                                <br><small><?php echo htmlspecialchars((string) $r['mobile']); ?></small>
                            </td>
                            <td>
                                <?php echo htmlspecialchars((string) $r['position_name']); ?><br>
                                <small><?php echo htmlspecialchars((string) $r['department_name']); ?></small>
                            </td>
                            <td>
                                <?php
                                $mode = (string) ($r['interview_mode'] ?? '');
                                echo htmlspecialchars($modes[$mode] ?? ($mode ?: '—'));
                                if (!empty($r['interview_date'])) {
                                    echo '<br><small>' . htmlspecialchars(date('d M Y', strtotime($r['interview_date']))) . '</small>';
                                }
                                ?>
                            </td>
                            <td>
                                <span class="status-badge status-<?php echo htmlspecialchars($stKey); ?>">
                                    <?php echo htmlspecialchars($labels[$stKey] ?? $stKey); ?>
                                </span>
                                <?php if ($stKey === 'awaited' && !empty($r['awaited_with'])): ?>
                                    <br><small><?php echo htmlspecialchars($sides[$r['awaited_with']] ?? $r['awaited_with']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($r['next_followup_at'])): ?>
                                    <?php echo htmlspecialchars(date('d M Y', strtotime($r['next_followup_at']))); ?>
                                    <?php if ($isDue): ?><br><span class="rec-due-tag">Due</span><?php endif; ?>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
                            <td><?php echo (int) ($r['call_count'] ?? 0); ?></td>
                            <td>
                                <a href="<?php echo app_url('recruitment/interview.php?id=' . (int) $r['id']); ?>"
                                   class="action-btn edit" title="Open"><i class="fa-solid fa-clipboard-user"></i></a>
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
.status-badge { display:inline-flex;padding:4px 10px;border-radius:999px;font-size:11px;font-weight:800; }
.status-new { background:#dbeafe;color:#1d4ed8; }
.status-interview,.status-review { background:#fef3c7;color:#b45309; }
.status-awaited,.status-shortlisted { background:#ffedd5;color:#c2410c; }
.status-selected,.status-hired { background:#dcfce7;color:#15803d; }
.status-not_selected,.status-rejected { background:#fee2e2;color:#b91c1c; }
.rec-due-tag { display:inline-flex;padding:2px 8px;border-radius:999px;background:#fee2e2;color:#b91c1c;font-size:10px;font-weight:800; }
.rec-row-due td { background:#fff7ed !important; }
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
