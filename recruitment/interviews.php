<?php
/**
 * Recruitment — Interview Candidates (List + Grid views)
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
$view = strtolower(trim((string) ($_GET['view'] ?? '')));
if (!in_array($view, ['list', 'grid'], true)) {
    $view = '';
}
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
               total_experience, age_years, expected_salary, created_at
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

$ini = static function ($name) {
    $parts = preg_split('/\s+/', trim((string) $name));
    return strtoupper(substr($parts[0] ?? 'C', 0, 1) . substr($parts[count($parts) - 1] ?? '', 0, 1));
};

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
        <div class="toolbar-actions" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
            <div class="rec-view-toggle" role="group" aria-label="View mode">
                <button type="button" class="rec-view-btn" data-view="list" title="List view">
                    <i class="fa-solid fa-list"></i> List
                </button>
                <button type="button" class="rec-view-btn" data-view="grid" title="Grid view">
                    <i class="fa-solid fa-border-all"></i> Grid
                </button>
            </div>
            <?php if ($dueCount > 0): ?>
            <a href="<?php echo app_url('recruitment/interviews.php?due=1'); ?>" class="btn-primary">
                <i class="fa-solid fa-bell"></i> Due (<?php echo (int) $dueCount; ?>)
            </a>
            <?php endif; ?>
            <a href="<?php echo app_url('recruitment/index.php'); ?>" class="btn-secondary">
                <i class="fa-solid fa-inbox"></i> Applications
            </a>
            <a href="<?php echo app_url('recruitment/qr.php'); ?>" class="btn-secondary">
                <i class="fa-solid fa-qrcode"></i> QR
            </a>
        </div>
    </div>

    <form method="GET" class="filters-bar rec-list-filters">
        <input type="hidden" name="view" id="recViewInput" value="<?php echo htmlspecialchars($view ?: 'grid'); ?>">
        <input type="search" name="q" class="form-control"
               placeholder="Search name, mobile, app no…"
               value="<?php echo htmlspecialchars($q); ?>">
        <select name="status" class="form-control">
            <option value="">All Status</option>
            <?php foreach (['new', 'interview', 'awaited', 'selected', 'not_selected'] as $k): ?>
                <option value="<?php echo htmlspecialchars($k); ?>" <?php echo $status === $k ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($labels[$k]); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <label class="rec-due-check">
            <input type="checkbox" name="due" value="1" <?php echo $dueOnly ? 'checked' : ''; ?>>
            Follow-up due only
        </label>
        <button type="submit" class="btn-secondary"><i class="fa-solid fa-filter"></i> Filter</button>
    </form>

    <?php if (!$rows): ?>
        <div class="rec-empty-card">
            <i class="fa-solid fa-user-slash"></i>
            <p>No candidates found.</p>
        </div>
    <?php else: ?>

    <div class="rec-panel" id="recListPanel" hidden>
        <div class="table-card rec-table-card">
            <div class="table-responsive">
                <table class="data-table rec-data-table">
                    <thead>
                    <tr>
                        <th>#</th>
                        <th>App No</th>
                        <th>Candidate</th>
                        <th>Position</th>
                        <th>Interview</th>
                        <th>Status</th>
                        <th>Follow-up</th>
                        <th>Calls</th>
                        <th style="width:110px;">Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php $n = 1; foreach ($rows as $r):
                        $isDue = recruitmentIsFollowupDue($r);
                        $stKey = (string) $r['status'];
                        $mode = (string) ($r['interview_mode'] ?? '');
                        $href = app_url('recruitment/interview.php?id=' . (int) $r['id']);
                    ?>
                        <tr class="rec-list-row <?php echo $isDue ? 'is-due' : ''; ?>" data-href="<?php echo htmlspecialchars($href); ?>">
                            <td><?php echo $n++; ?></td>
                            <td><strong class="rec-code"><?php echo htmlspecialchars((string) $r['application_no']); ?></strong></td>
                            <td>
                                <div class="rec-list-person">
                                    <span class="rec-list-avatar"><?php echo htmlspecialchars($ini($r['full_name'])); ?></span>
                                    <div>
                                        <strong><?php echo htmlspecialchars((string) $r['full_name']); ?></strong>
                                        <small><?php echo htmlspecialchars((string) $r['mobile']); ?>
                                            <?php if (!empty($r['age_years'])): ?> · <?php echo (int) $r['age_years']; ?> yrs<?php endif; ?>
                                        </small>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <strong><?php echo htmlspecialchars((string) ($r['position_name'] ?: '—')); ?></strong><br>
                                <small><?php echo htmlspecialchars((string) ($r['department_name'] ?: '—')); ?></small>
                            </td>
                            <td>
                                <?php echo htmlspecialchars($modes[$mode] ?? ($mode ?: '—')); ?>
                                <?php if (!empty($r['interview_date'])): ?>
                                    <br><small><?php echo htmlspecialchars(date('d M Y', strtotime($r['interview_date']))); ?></small>
                                <?php endif; ?>
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
                                <?php else: ?>—<?php endif; ?>
                            </td>
                            <td><?php echo (int) ($r['call_count'] ?? 0); ?></td>
                            <td class="rec-list-actions" onclick="event.stopPropagation();">
                                <a href="<?php echo app_url('recruitment/pdf.php?id=' . (int) $r['id']); ?>" class="action-btn edit" target="_blank" title="PDF"><i class="fa-solid fa-file-pdf"></i></a>
                                <a href="<?php echo htmlspecialchars($href); ?>" class="action-btn edit" title="Open"><i class="fa-solid fa-clipboard-user"></i></a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="rec-panel" id="recGridPanel" hidden>
        <div class="rec-grid">
            <?php foreach ($rows as $r):
                $isDue = recruitmentIsFollowupDue($r);
                $stKey = (string) $r['status'];
                $mode = (string) ($r['interview_mode'] ?? '');
                $href = app_url('recruitment/interview.php?id=' . (int) $r['id']);
            ?>
            <article class="rec-cand-card <?php echo $isDue ? 'is-due' : ''; ?>">
                <div class="rec-cand-top">
                    <div class="rec-cand-avatar"><?php echo htmlspecialchars($ini($r['full_name'])); ?></div>
                    <div class="rec-cand-head">
                        <span class="status-badge status-<?php echo htmlspecialchars($stKey); ?>">
                            <?php echo htmlspecialchars($labels[$stKey] ?? $stKey); ?>
                        </span>
                        <?php if ($isDue): ?><span class="rec-due-tag">Follow-up Due</span><?php endif; ?>
                        <strong class="rec-cand-code"><?php echo htmlspecialchars((string) $r['application_no']); ?></strong>
                        <h3><?php echo htmlspecialchars((string) $r['full_name']); ?></h3>
                    </div>
                </div>
                <div class="rec-cand-meta">
                    <div><i class="fa-solid fa-briefcase"></i>
                        <span><?php echo htmlspecialchars((string) ($r['position_name'] ?: '—')); ?>
                        <small><?php echo htmlspecialchars((string) ($r['department_name'] ?: '')); ?></small></span>
                    </div>
                    <div><i class="fa-solid fa-phone"></i>
                        <span><?php echo htmlspecialchars((string) ($r['mobile'] ?: '—')); ?></span>
                    </div>
                    <div><i class="fa-solid fa-cake-candles"></i>
                        <span><?php echo !empty($r['age_years']) ? ((int) $r['age_years'] . ' yrs') : '—'; ?>
                        · <?php echo htmlspecialchars((string) ($r['total_experience'] ?: 'Exp —')); ?></span>
                    </div>
                    <div><i class="fa-solid fa-calendar-check"></i>
                        <span><?php
                            echo htmlspecialchars($modes[$mode] ?? ($mode ?: 'Interview not set'));
                            if (!empty($r['interview_date'])) {
                                echo ' · ' . htmlspecialchars(date('d M Y', strtotime($r['interview_date'])));
                            }
                        ?></span>
                    </div>
                </div>
                <div class="rec-cand-foot">
                    <div class="rec-cand-stats">
                        <span><i class="fa-solid fa-phone-volume"></i> <?php echo (int) ($r['call_count'] ?? 0); ?> calls</span>
                        <span><i class="fa-solid fa-bell"></i> <?php
                            echo !empty($r['next_followup_at'])
                                ? htmlspecialchars(date('d M Y', strtotime($r['next_followup_at'])))
                                : 'No follow-up';
                        ?></span>
                    </div>
                    <div class="rec-cand-actions">
                        <a href="<?php echo app_url('recruitment/pdf.php?id=' . (int) $r['id']); ?>" class="btn-ghost" target="_blank"><i class="fa-solid fa-file-pdf"></i></a>
                        <a href="<?php echo htmlspecialchars($href); ?>" class="btn-primary"><i class="fa-solid fa-clipboard-user"></i> Open</a>
                    </div>
                </div>
            </article>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
</main>

<style>
.rec-list-filters { display:flex; gap:10px; flex-wrap:wrap; margin-bottom:16px; align-items:center; }
.rec-list-filters .form-control { max-width:280px; }
.rec-due-check { display:flex; align-items:center; gap:6px; font-size:13px; font-weight:700; color:#334155; }
.rec-view-toggle { display:inline-flex; border:1px solid #e2e8f0; border-radius:10px; overflow:hidden; background:#fff; }
.rec-view-btn { border:0; background:transparent; padding:8px 12px; font-size:12px; font-weight:800; color:#64748b; cursor:pointer; display:inline-flex; align-items:center; gap:6px; }
.rec-view-btn + .rec-view-btn { border-left:1px solid #e2e8f0; }
.rec-view-btn.is-active { background:#fff5f5; color:#b91c1c; }
.rec-empty-card { background:#fff; border:1px dashed #cbd5e1; border-radius:16px; padding:48px 20px; text-align:center; color:#94a3b8; }
.rec-empty-card i { font-size:28px; margin-bottom:10px; display:block; }
.rec-table-card { width:100%; border-radius:16px; overflow:hidden; }
.rec-data-table { width:100%; margin:0; }
.rec-data-table th { background:#fff8f8; color:#7f1d1d; font-size:11px; text-transform:uppercase; letter-spacing:.03em; white-space:nowrap; }
.rec-data-table td { vertical-align:middle; }
.rec-list-row { cursor:pointer; }
.rec-list-row:hover td { background:#fffafa; }
.rec-list-row.is-due td { background:#fff7ed; }
.rec-code { color:#d2232a; font-size:12px; }
.rec-list-person { display:flex; gap:10px; align-items:center; }
.rec-list-avatar { width:36px; height:36px; border-radius:10px; flex-shrink:0; background:linear-gradient(145deg,#d2232a,#b01c22); color:#fff; font-size:12px; font-weight:800; display:flex; align-items:center; justify-content:center; }
.rec-list-person strong { display:block; font-size:14px; }
.rec-list-person small, .rec-data-table small { color:#94a3b8; font-weight:600; }
.rec-list-actions { white-space:nowrap; }
.rec-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(300px, 1fr)); gap:16px; width:100%; }
.rec-cand-card { background:#fff; border:1px solid #e2e8f0; border-radius:16px; padding:16px; box-shadow:0 1px 3px rgba(15,23,42,.06); display:flex; flex-direction:column; gap:12px; min-height:100%; transition:.2s ease; }
.rec-cand-card:hover { box-shadow:0 8px 24px rgba(210,35,42,.12); border-color:rgba(210,35,42,.35); }
.rec-cand-card.is-due { border-color:#fdba74; background:linear-gradient(180deg,#fff7ed 0%,#fff 40%); }
.rec-cand-top { display:flex; gap:12px; align-items:flex-start; }
.rec-cand-avatar { width:48px; height:48px; border-radius:14px; flex-shrink:0; background:linear-gradient(145deg,#d2232a,#b01c22); color:#fff; font-weight:800; font-size:15px; display:flex; align-items:center; justify-content:center; }
.rec-cand-head { min-width:0; flex:1; }
.rec-cand-head h3 { margin:4px 0 0; font-size:16px; font-weight:800; color:#0f172a; line-height:1.25; }
.rec-cand-code { display:block; margin-top:6px; font-size:12px; color:#d2232a; font-weight:800; }
.rec-cand-meta { display:flex; flex-direction:column; gap:7px; padding:10px 0; border-top:1px dashed #f1f5f9; border-bottom:1px dashed #f1f5f9; }
.rec-cand-meta > div { display:flex; gap:8px; align-items:flex-start; font-size:13px; font-weight:600; color:#334155; }
.rec-cand-meta i { color:#d2232a; width:14px; margin-top:2px; flex-shrink:0; }
.rec-cand-meta small { display:block; color:#94a3b8; font-weight:600; font-size:11px; }
.rec-cand-foot { display:flex; justify-content:space-between; align-items:center; gap:10px; flex-wrap:wrap; margin-top:auto; }
.rec-cand-stats { display:flex; flex-direction:column; gap:2px; font-size:11px; font-weight:700; color:#64748b; }
.rec-cand-actions { display:flex; gap:6px; align-items:center; }
.rec-cand-actions .btn-primary, .rec-cand-actions .btn-ghost { padding:7px 12px; font-size:12px; }
.status-badge { display:inline-flex;padding:4px 10px;border-radius:999px;font-size:11px;font-weight:800; }
.status-new { background:#dbeafe;color:#1d4ed8; }
.status-interview,.status-review { background:#fef3c7;color:#b45309; }
.status-awaited,.status-shortlisted { background:#ffedd5;color:#c2410c; }
.status-selected,.status-hired { background:#dcfce7;color:#15803d; }
.status-not_selected,.status-rejected { background:#fee2e2;color:#b91c1c; }
.rec-due-tag { display:inline-flex;padding:3px 8px;border-radius:999px; background:#fee2e2;color:#b91c1c;font-size:10px;font-weight:800;margin-left:4px; }
@media (max-width:640px) { .rec-list-filters .form-control { max-width:none; width:100%; } }
</style>
<script>
(function () {
    var KEY = 'armor_rec_iv_view';
    var serverView = <?php echo json_encode($view); ?>;
    var input = document.getElementById('recViewInput');
    var listPanel = document.getElementById('recListPanel');
    var gridPanel = document.getElementById('recGridPanel');
    var buttons = document.querySelectorAll('.rec-view-btn');
    function applyView(v) {
        v = (v === 'list') ? 'list' : 'grid';
        if (input) input.value = v;
        if (listPanel) listPanel.hidden = (v !== 'list');
        if (gridPanel) gridPanel.hidden = (v !== 'grid');
        buttons.forEach(function (b) { b.classList.toggle('is-active', b.getAttribute('data-view') === v); });
        try { localStorage.setItem(KEY, v); } catch (e) {}
        try {
            var url = new URL(window.location.href);
            url.searchParams.set('view', v);
            window.history.replaceState({}, '', url.toString());
        } catch (e2) {}
    }
    var initial = serverView || '';
    if (!initial) { try { initial = localStorage.getItem(KEY) || 'grid'; } catch (e) { initial = 'grid'; } }
    applyView(initial);
    buttons.forEach(function (b) { b.addEventListener('click', function () { applyView(b.getAttribute('data-view')); }); });
    document.querySelectorAll('.rec-list-row').forEach(function (row) {
        row.addEventListener('click', function () {
            var href = row.getAttribute('data-href');
            if (href) window.location.href = href;
        });
    });
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
