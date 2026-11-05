<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/employee_helper.php';
require_once __DIR__ . '/../includes/permission_helper.php';
require_once __DIR__ . '/../includes/sop_helper.php';

requireLogin();
$deptId = (int) ($_GET['department_id'] ?? $_POST['department_id'] ?? 0);
$id = (int) ($_GET['id'] ?? 0);
requireAccess('departments', $id > 0 ? 'edit' : 'add', $deptId);
$department = $deptId > 0 ? getDepartmentById($deptId) : null;
if (!$department || !cncSopIsCncDepartment($department)) {
    header('Location: ' . app_url('dashboard.php'));
    exit;
}

$conn = getDBConnection();
$row = $id > 0 ? cncSopGet($conn, $id) : null;
$conn->close();
if ($id > 0 && (!$row || (int) $row['department_id'] !== $deptId)) {
    header('Location: ' . app_url('sop/index.php?department_id=' . $deptId));
    exit;
}

$formError = '';
if (!empty($_SESSION['sop_form_error'])) {
    $formError = (string) $_SESSION['sop_form_error'];
    unset($_SESSION['sop_form_error']);
    if (!empty($_SESSION['sop_form_data']) && is_array($_SESSION['sop_form_data'])) {
        $row = array_merge($row ?: [], $_SESSION['sop_form_data']);
        unset($_SESSION['sop_form_data']);
    }
}

if (!$row) {
    $row = [
        'title' => '',
        'sop_date' => date('Y-m-d'),
        'subject' => '',
        'department_name' => (string) $department['department_name'],
        'body' => '',
        'signer_name' => '',
        'signature_data' => '',
    ];
}

$val = static function ($key, $fallback = '') use ($row) {
    return htmlspecialchars((string) ($row[$key] ?? $fallback), ENT_QUOTES, 'UTF-8');
};

$pageTitle = $id > 0 ? 'Edit Contractor Agreement' : 'Add Contractor Agreement';
$useSidebar = true;
$sidebarMode = 'department';
$sidebarDeptId = $deptId;
$sidebarActive = 'cnc_sop';
require_once __DIR__ . '/../includes/header.php';
$existingSign = (string) ($row['signature_data'] ?? '');
$dateShow = dateInputValue($row['sop_date'] ?? date('Y-m-d'));
?>
<main class="dashboard-main">
    <div class="page-toolbar">
        <a href="<?php echo app_url('sop/index.php?department_id=' . $deptId); ?>" class="back-link">
            <i class="fa-solid fa-arrow-left"></i> Back to Contractor Agreement
        </a>
    </div>

    <div class="form-page-card master-form-card sop-form">
        <div class="form-page-header">
            <div class="master-list-title">
                <div class="master-list-icon" style="background:#d2232a;">
                    <i class="fa-solid fa-file-signature"></i>
                </div>
                <div>
                    <h1><?php echo htmlspecialchars($pageTitle); ?></h1>
                    <p>CNC Contractor Agreement · fields here · letterhead after save</p>
                </div>
            </div>
        </div>

        <?php if ($formError !== ''): ?>
            <div class="form-alert form-alert-error" role="alert" style="margin:0 0 16px;padding:12px 14px;border-radius:8px;background:#fef2f2;border:1px solid #fecaca;color:#b91c1c;font-weight:600;">
                <?php echo htmlspecialchars($formError); ?>
            </div>
        <?php endif; ?>

        <form method="post" action="<?php echo app_url('sop/save.php'); ?>" id="sopForm" class="employee-form" autocomplete="off">
            <input type="hidden" name="id" value="<?php echo (int) $id; ?>">
            <input type="hidden" name="department_id" value="<?php echo $deptId; ?>">
            <input type="hidden" name="signature_data" id="signatureData" value="">
            <input type="hidden" name="clear_signature" id="clearSignature" value="0">

            <div class="form-section">
                <h3><i class="fa-solid fa-file-lines"></i> Agreement Details</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="sopTitle">Agreement Title <span class="req">*</span></label>
                        <input type="text" name="title" id="sopTitle" class="form-control" required maxlength="255"
                               placeholder="Agreement title" value="<?php echo $val('title'); ?>">
                    </div>
                    <div class="form-group">
                        <label for="sopDate">Date <span class="req">*</span> <small>(DD-MM-YYYY)</small></label>
                        <input type="text" name="sop_date" id="sopDate" class="form-control js-date" required maxlength="10"
                               placeholder="DD-MM-YYYY" value="<?php echo htmlspecialchars($dateShow); ?>">
                    </div>
                    <div class="form-group">
                        <label for="sopSubject">Subject <span class="req">*</span></label>
                        <input type="text" name="subject" id="sopSubject" class="form-control" required maxlength="500"
                               placeholder="Subject" value="<?php echo $val('subject'); ?>">
                    </div>
                    <div class="form-group">
                        <label for="sopDept">Department Name <span class="req">*</span></label>
                        <input type="text" name="department_name" id="sopDept" class="form-control" required maxlength="150"
                               value="<?php echo $val('department_name'); ?>">
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h3><i class="fa-solid fa-align-left"></i> Description</h3>
                <div class="form-group full">
                    <label for="sopBody">Body <span class="req">*</span></label>
                    <textarea name="body" id="sopBody" class="form-control" required rows="8"
                                  placeholder="Write the agreement description. Add as much as needed."><?php echo $val('body'); ?></textarea>
                </div>
            </div>

            <div class="form-section">
                <h3><i class="fa-solid fa-signature"></i> Authorized Signature</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="sopSigner">Authorized Person</label>
                        <input type="text" name="signer_name" id="sopSigner" class="form-control" maxlength="150"
                               placeholder="Name under the signature" value="<?php echo $val('signer_name'); ?>">
                    </div>
                    <div class="form-group">
                        <label>Draw signature</label>
                        <div class="sop-sign-pad">
                            <canvas id="sopPad" width="640" height="280"></canvas>
                        </div>
                        <button type="button" class="btn-secondary" id="sopPadClear">Clear signature</button>
                    </div>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn-primary"><i class="fa-solid fa-check"></i> Save</button>
                <a href="<?php echo app_url('sop/index.php?department_id=' . $deptId); ?>" class="btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</main>
<script>
(function () {
    var canvas = document.getElementById('sopPad');
    var form = document.getElementById('sopForm');
    if (!canvas || !form) return;
    var ctx = canvas.getContext('2d');
    var dirty = false;
    var cleared = false;
    var drawing = false;
    ctx.lineWidth = 3.2;
    ctx.lineCap = 'round';
    ctx.lineJoin = 'round';
    ctx.strokeStyle = '#111827';
    var existing = <?php echo json_encode($existingSign, JSON_UNESCAPED_SLASHES); ?>;
    if (existing && existing.indexOf('data:image/png') === 0) {
        var img = new Image();
        img.onload = function () { ctx.drawImage(img, 0, 0, canvas.width, canvas.height); };
        img.src = existing;
    }
    function pos(e) {
        var r = canvas.getBoundingClientRect();
        return {
            x: (e.clientX - r.left) * (canvas.width / r.width),
            y: (e.clientY - r.top) * (canvas.height / r.height)
        };
    }
    canvas.addEventListener('pointerdown', function (e) {
        drawing = true;
        dirty = true;
        cleared = false;
        canvas.setPointerCapture(e.pointerId);
        var p = pos(e);
        ctx.beginPath();
        ctx.moveTo(p.x, p.y);
    });
    canvas.addEventListener('pointermove', function (e) {
        if (!drawing) return;
        var p = pos(e);
        ctx.lineTo(p.x, p.y);
        ctx.stroke();
    });
    function stop() { drawing = false; }
    canvas.addEventListener('pointerup', stop);
    canvas.addEventListener('pointercancel', stop);
    document.getElementById('sopPadClear').addEventListener('click', function () {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        dirty = false;
        cleared = true;
    });
    form.addEventListener('submit', function () {
        var data = document.getElementById('signatureData');
        var clear = document.getElementById('clearSignature');
        if (dirty) {
            data.value = canvas.toDataURL('image/png');
            clear.value = '0';
        } else if (cleared) {
            data.value = '';
            clear.value = '1';
        }
    });
})();
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
