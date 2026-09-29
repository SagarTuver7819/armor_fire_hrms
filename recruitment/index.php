<?php
/**
 * Recruitment — Applications (List + Grid views)
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
$view = strtolower(trim((string) ($_GET['view'] ?? '')));
if (!in_array($view, ['list', 'grid'], true)) {
    $view = ''; // client JS may restore from localStorage
}
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
               current_salary, expected_salary, status, created_at, age_years, total_experience
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

$ini = static function ($name) {
    $parts = preg_split('/\s+/', trim((string) $name));
    return strtoupper(substr($parts[0] ?? 'C', 0, 1) . substr($parts[count($parts) - 1] ?? '', 0, 1));
};
$money = static function ($v) {
    return $v !== null && $v !== '' ? ('₹ ' . number_format((float) $v, 0)) : '—';
};

$pageTitle = 'Recruitment';
$useSidebar = true;
$sidebarMode = 'recruitment';
$sidebarActive = 'recruitment';

require_once __DIR__ . '/../includes/header.php';

$qsBase = http_build_query(array_filter([
    'q' => $q !== '' ? $q : null,
    'status' => $status !== '' ? $status : null,
]));
?>

<main class="dashboard-main">
    <div class="page-toolbar flex-between">
        <div>
            <h1 style="margin:0;font-size:22px;font-weight:800;">Recruitment Applications</h1>
            <p style="margin:4px 0 0;color:#64748b;font-size:13px;">
                <?php echo (int) $countNew; ?> new · Public form via fixed QR
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
            <a href="<?php echo app_url('recruitment/interviews.php'); ?>" class="btn-secondary">
                <i class="fa-solid fa-clipboard-user"></i> Interview
            </a>
            <a href="<?php echo app_url('recruitment/qr.php'); ?>" class="btn-primary">
                <i class="fa-solid fa-qrcode"></i> Fixed QR Code
            </a>
            <a href="<?php echo htmlspecialchars(recruitmentApplyUrl()); ?>" class="btn-secondary" target="_blank" rel="noopener">
                <i class="fa-solid fa-arrow-up-right-from-square"></i> Apply Form
            </a>
        </div>
    </div>

    <form method="GET" class="filters-bar rec-list-filters" id="recFilterForm">
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
        <button type="submit" class="btn-secondary"><i class="fa-solid fa-filter"></i> Filter</button>
    </form>

    <?php if (!$rows): ?>
        <div class="rec-empty-card">
            <i class="fa-solid fa-inbox"></i>
            <p>No applications yet. Share the QR / apply link.</p>
        </div>
    <?php else: ?>

    <!-- LIST VIEW -->
    <div class="rec-panel" id="recListPanel" hidden>
        <div class="table-card rec-table-card">
            <div class="table-responsive">
                <table class="data-table rec-data-table">
                    <thead>
                    <tr>
                        <th style="width:40px;">#</th>
                        <th>App No</th>
                        <th>Candidate</th>
                        <th>Position / Dept</th>
                        <th>Contact</th>
                        <th>Expected</th>
                        <th>Status</th>
                        <th>Applied</th>
                        <th style="width:140px;">Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php $n = 1; foreach ($rows as $r):
                        $stKey = (string) $r['status'];
                        $viewUrl = app_url('recruitment/view.php?id=' . (int) $r['id']);
                        $ivUrl = app_url('recruitment/interview.php?id=' . (int) $r['id']);
                        $pdfUrl = app_url('recruitment/pdf.php?id=' . (int) $r['id']);
                    ?>
                        <tr class="rec-list-row" data-href="<?php echo htmlspecialchars($viewUrl); ?>">
                            <td><?php echo $n++; ?></td>
                            <td><strong class="rec-code"><?php echo htmlspecialchars((string) $r['application_no']); ?></strong></td>
                            <td>
                                <div class="rec-list-person">
                                    <span class="rec-list-avatar"><?php echo htmlspecialchars($ini($r['full_name'])); ?></span>
                                    <div>
                                        <strong><?php echo htmlspecialchars((string) $r['full_name']); ?></strong>
                                        <small><?php
                                            echo !empty($r['age_years']) ? ((int) $r['age_years'] . ' yrs') : '—';
                                            echo ' · ';
                                            echo htmlspecialchars((string) ($r['total_experience'] ?: 'Exp —'));
                                        ?></small>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <strong><?php echo htmlspecialchars((string) ($r['position_name'] ?: '—')); ?></strong><br>
                                <small><?php echo htmlspecialchars((string) ($r['department_name'] ?: '—')); ?></small>
                            </td>
                            <td>
                                <?php echo htmlspecialchars((string) ($r['mobile'] ?: '—')); ?><br>
                                <small><?php echo htmlspecialchars((string) ($r['email'] ?: '—')); ?></small>
                            </td>
                            <td><?php echo $money($r['expected_salary'] ?? null); ?></td>
                            <td>
                                <span class="status-badge status-<?php echo htmlspecialchars($stKey); ?>">
                                    <?php echo htmlspecialchars($labels[$stKey] ?? $stKey); ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars(date('d M Y', strtotime((string) $r['created_at']))); ?></td>
                            <td class="rec-list-actions" onclick="event.stopPropagation();">
                                <a href="<?php echo htmlspecialchars($pdfUrl); ?>" class="action-btn edit" target="_blank" title="PDF"><i class="fa-solid fa-file-pdf"></i></a>
                                <a href="<?php echo htmlspecialchars($viewUrl); ?>" class="action-btn edit" title="View"><i class="fa-solid fa-eye"></i></a>
                                <a href="<?php echo htmlspecialchars($ivUrl); ?>" class="action-btn edit" title="Interview"><i class="fa-solid fa-clipboard-user"></i></a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- GRID VIEW -->
    <div class="rec-panel" id="recGridPanel" hidden>
        <div class="rec-grid">
            <?php foreach ($rows as $r):
                $stKey = (string) $r['status'];
                $viewUrl = app_url('recruitment/view.php?id=' . (int) $r['id']);
                $ivUrl = app_url('recruitment/interview.php?id=' . (int) $r['id']);
            ?>
            <article class="rec-cand-card">
                <div class="rec-cand-top">
                    <div class="rec-cand-avatar"><?php echo htmlspecialchars($ini($r['full_name'])); ?></div>
                    <div class="rec-cand-head">
                        <span class="status-badge status-<?php echo htmlspecialchars($stKey); ?>">
                            <?php echo htmlspecialchars($labels[$stKey] ?? $stKey); ?>
                        </span>
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
                        <span><?php echo htmlspecialchars((string) ($r['mobile'] ?: '—')); ?>
                        <?php if (!empty($r['email'])): ?><small><?php echo htmlspecialchars((string) $r['email']); ?></small><?php endif; ?>
                        </span>
                    </div>
                    <div><i class="fa-solid fa-indian-rupee-sign"></i>
                        <span>Expected <?php echo $money($r['expected_salary'] ?? null); ?>
                        <small><?php
                            echo !empty($r['age_years']) ? ((int) $r['age_years'] . ' yrs') : 'Age —';
                            echo ' · ';
                            echo htmlspecialchars((string) ($r['total_experience'] ?: 'Exp —'));
                        ?></small></span>
                    </div>
                    <div><i class="fa-solid fa-calendar"></i>
                        <span>Applied <?php echo htmlspecialchars(date('d M Y', strtotime((string) $r['created_at']))); ?></span>
                    </div>
                </div>
                <div class="rec-cand-foot">
                    <div class="rec-cand-stats">
                        <span><i class="fa-solid fa-building"></i> <?php echo htmlspecialchars((string) ($r['department_name'] ?: '—')); ?></span>
                    </div>
                    <div class="rec-cand-actions">
                        <a href="<?php echo app_url('recruitment/pdf.php?id=' . (int) $r['id']); ?>" class="btn-ghost" target="_blank" title="PDF"><i class="fa-solid fa-file-pdf"></i></a>
                        <a href="<?php echo htmlspecialchars($viewUrl); ?>" class="btn-secondary" title="View"><i class="fa-solid fa-eye"></i> View</a>
                        <a href="<?php echo htmlspecialchars($ivUrl); ?>" class="btn-primary" title="Interview"><i class="fa-solid fa-clipboard-user"></i></a>
                    </div>
                </div>
            </article>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
</main>

<style>
.rec-list-filters {
    display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 16px; align-items: center;
}
.rec-list-filters .form-control { max-width: 280px; }
.rec-view-toggle {
    display: inline-flex; border: 1px solid #e2e8f0; border-radius: 10px; overflow: hidden; background: #fff;
}
.rec-view-btn {
    border: 0; background: transparent; padding: 8px 12px; font-size: 12px; font-weight: 800;
    color: #64748b; cursor: pointer; display: inline-flex; align-items: center; gap: 6px;
}
.rec-view-btn + .rec-view-btn { border-left: 1px solid #e2e8f0; }
.rec-view-btn.is-active { background: #fff5f5; color: #b91c1c; }
.rec-empty-card {
    background: #fff; border: 1px dashed #cbd5e1; border-radius: 16px;
    padding: 48px 20px; text-align: center; color: #94a3b8;
}
.rec-empty-card i { font-size: 28px; margin-bottom: 10px; display: block; }

/* Full-width list */
.rec-table-card { width: 100%; border-radius: 16px; overflow: hidden; }
.rec-data-table { width: 100%; margin: 0; }
.rec-data-table th {
    background: #fff8f8; color: #7f1d1d; font-size: 11px; text-transform: uppercase;
    letter-spacing: .03em; white-space: nowrap;
}
.rec-data-table td { vertical-align: middle; }
.rec-list-row { cursor: pointer; }
.rec-list-row:hover td { background: #fffafa; }
.rec-code { color: #d2232a; font-size: 12px; }
.rec-list-person { display: flex; gap: 10px; align-items: center; }
.rec-list-avatar {
    width: 36px; height: 36px; border-radius: 10px; flex-shrink: 0;
    background: linear-gradient(145deg, #d2232a, #b01c22); color: #fff;
    font-size: 12px; font-weight: 800; display: flex; align-items: center; justify-content: center;
}
.rec-list-person strong { display: block; font-size: 14px; }
.rec-list-person small, .rec-data-table small { color: #94a3b8; font-weight: 600; }
.rec-list-actions { white-space: nowrap; }

/* Full-width grid */
.rec-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 16px;
    width: 100%;
}
.rec-cand-card {
    background: #fff; border: 1px solid #e2e8f0; border-radius: 16px; padding: 16px;
    box-shadow: 0 1px 3px rgba(15,23,42,.06);
    display: flex; flex-direction: column; gap: 12px;
    transition: box-shadow .2s ease, border-color .2s ease;
    min-height: 100%;
}
.rec-cand-card:hover {
    box-shadow: 0 8px 24px rgba(210, 35, 42, 0.12);
    border-color: rgba(210, 35, 42, 0.35);
}
.rec-cand-top { display: flex; gap: 12px; align-items: flex-start; }
.rec-cand-avatar {
    width: 48px; height: 48px; border-radius: 14px; flex-shrink: 0;
    background: linear-gradient(145deg, #d2232a, #b01c22); color: #fff;
    font-weight: 800; font-size: 15px;
    display: flex; align-items: center; justify-content: center;
}
.rec-cand-head { min-width: 0; flex: 1; }
.rec-cand-head h3 { margin: 4px 0 0; font-size: 16px; font-weight: 800; color: #0f172a; line-height: 1.25; }
.rec-cand-code { display: block; margin-top: 6px; font-size: 12px; color: #d2232a; font-weight: 800; }
.rec-cand-meta {
    display: flex; flex-direction: column; gap: 7px;
    padding: 10px 0; border-top: 1px dashed #f1f5f9; border-bottom: 1px dashed #f1f5f9;
}
.rec-cand-meta > div { display: flex; gap: 8px; align-items: flex-start; font-size: 13px; font-weight: 600; color: #334155; }
.rec-cand-meta i { color: #d2232a; width: 14px; margin-top: 2px; flex-shrink: 0; }
.rec-cand-meta small { display: block; color: #94a3b8; font-weight: 600; font-size: 11px; }
.rec-cand-foot { display: flex; justify-content: space-between; align-items: center; gap: 10px; flex-wrap: wrap; margin-top: auto; }
.rec-cand-stats { font-size: 11px; font-weight: 700; color: #64748b; }
.rec-cand-actions { display: flex; gap: 6px; align-items: center; }
.rec-cand-actions .btn-primary,
.rec-cand-actions .btn-secondary,
.rec-cand-actions .btn-ghost { padding: 7px 12px; font-size: 12px; }
.status-badge { display:inline-flex;padding:4px 10px;border-radius:999px;font-size:11px;font-weight:800; }
.status-new { background:#dbeafe;color:#1d4ed8; }
.status-interview,.status-review { background:#fef3c7;color:#b45309; }
.status-awaited,.status-shortlisted { background:#ffedd5;color:#c2410c; }
.status-selected,.status-hired { background:#dcfce7;color:#15803d; }
.status-not_selected,.status-rejected { background:#fee2e2;color:#b91c1c; }
@media (max-width: 640px) {
    .rec-list-filters .form-control { max-width: none; width: 100%; }
}
</style>
<script>
(function () {
    var KEY = 'armor_rec_apps_view';
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
        buttons.forEach(function (b) {
            b.classList.toggle('is-active', b.getAttribute('data-view') === v);
        });
        try { localStorage.setItem(KEY, v); } catch (e) {}
        // keep URL in sync without reload when toggling
        try {
            var url = new URL(window.location.href);
            url.searchParams.set('view', v);
            window.history.replaceState({}, '', url.toString());
        } catch (e2) {}
    }

    var initial = serverView || '';
    if (!initial) {
        try { initial = localStorage.getItem(KEY) || 'grid'; } catch (e) { initial = 'grid'; }
    }
    applyView(initial);

    buttons.forEach(function (b) {
        b.addEventListener('click', function () {
            applyView(b.getAttribute('data-view'));
        });
    });

    document.querySelectorAll('.rec-list-row').forEach(function (row) {
        row.addEventListener('click', function () {
            var href = row.getAttribute('data-href');
            if (href) window.location.href = href;
        });
    });
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
