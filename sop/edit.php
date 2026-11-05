<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/employee_helper.php';
require_once __DIR__ . '/../includes/permission_helper.php';
require_once __DIR__ . '/../includes/settings.php';
require_once __DIR__ . '/../includes/document_print.php';
require_once __DIR__ . '/../includes/sop_helper.php';
require_once __DIR__ . '/../includes/sop_letter.php';

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

$brand = getCompanyDocumentBranding();
$pageTitle = $id > 0 ? 'Edit SOP' : 'Add SOP';
$useSidebar = true;
$sidebarMode = 'department';
$sidebarDeptId = $deptId;
$sidebarActive = 'cnc_sop';
require_once __DIR__ . '/../includes/header.php';
$existingSign = (string) ($row['signature_data'] ?? '');
?>
<main class="dashboard-main">
    <style><?php echo companyDocPrintCss() . cncSopLetterCss(); ?></style>
    <div class="page-toolbar">
        <a href="<?php echo app_url('sop/index.php?department_id=' . $deptId); ?>" class="back-link">
            <i class="fa-solid fa-arrow-left"></i> Back to SOP
        </a>
    </div>
    <?php if ($formError !== ''): ?>
        <div style="max-width:210mm;margin:0 auto 10px;padding:10px 12px;border-radius:8px;background:#fef2f2;color:#b91c1c;font-weight:700;">
            <?php echo htmlspecialchars($formError); ?>
        </div>
    <?php endif; ?>
    <form method="post" action="<?php echo app_url('sop/save.php'); ?>" id="sopForm">
        <input type="hidden" name="id" value="<?php echo (int) $id; ?>">
        <input type="hidden" name="department_id" value="<?php echo $deptId; ?>">
        <div class="sop-toolbar">
            <button type="submit" class="is-primary">Save on Letterhead</button>
            <a href="<?php echo app_url('sop/index.php?department_id=' . $deptId); ?>">Cancel</a>
        </div>
        <div class="sop-sheet-wrap">
            <?php echo cncSopLetterSheet($row, $brand, true); ?>
        </div>
    </form>
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
    var clearBtn = document.getElementById('sopPadClear');
    if (clearBtn) {
        clearBtn.addEventListener('click', function () {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            dirty = false;
            cleared = true;
        });
    }
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
