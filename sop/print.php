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
$deptId = (int) ($_GET['department_id'] ?? 0);
$id = (int) ($_GET['id'] ?? 0);
requireAccess('departments', 'view', $deptId);
$department = $deptId > 0 ? getDepartmentById($deptId) : null;
if (!$department || !cncSopIsCncDepartment($department)) {
    header('Location: ' . app_url('dashboard.php'));
    exit;
}
$conn = getDBConnection();
$row = cncSopGet($conn, $id);
$conn->close();
if (!$row || (int) $row['department_id'] !== $deptId) {
    header('Location: ' . app_url('sop/index.php?department_id=' . $deptId));
    exit;
}
$brand = getCompanyDocumentBranding();
$shareUrl = cncSopShareUrl($row);
$title = (string) $row['title'];
$saved = isset($_GET['msg']) && in_array($_GET['msg'], ['added', 'updated'], true);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title><?php echo cncSopH($title); ?></title>
    <style>
        body { margin: 0; background: #eef2f6; font-family: Calibri, Candara, "Segoe UI", Arial, sans-serif; }
        <?php echo companyDocPrintCss(); ?>
        <?php echo cncSopLetterCss(); ?>
        <?php echo companyDocLetterheadPageCss($brand, true); ?>
        .sop-note { max-width: 210mm; margin: 8px auto 0; color: #166534; font-weight: 700; }
    </style>
</head>
<body>
    <div class="sop-toolbar no-print">
        <a href="<?php echo app_url('sop/index.php?department_id=' . $deptId); ?>">Back to SOP</a>
        <a href="<?php echo app_url('sop/edit.php?id=' . (int) $row['id'] . '&department_id=' . $deptId); ?>">Edit</a>
        <button type="button" class="is-primary" onclick="window.print()">Print / Save PDF</button>
        <button type="button" id="sopCopy">Copy share link</button>
        <button type="button" id="sopWhatsapp">WhatsApp</button>
        <button type="button" id="sopNative">Share</button>
    </div>
    <?php if ($saved): ?>
        <p class="sop-note no-print">Saved on the letterhead.</p>
    <?php endif; ?>
    <div class="sop-sheet-wrap">
        <?php echo cncSopLetterSheet($row, $brand, false); ?>
    </div>
    <script>
    (function () {
        var url = <?php echo json_encode($shareUrl); ?>;
        var title = <?php echo json_encode($title); ?>;
        var text = title + '\n' + url;
        document.getElementById('sopCopy').addEventListener('click', function () {
            var btn = this;
            var done = function () { btn.textContent = 'Link copied'; };
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(url).then(done).catch(function () {
                    window.prompt('Copy this share link', url);
                });
            } else {
                window.prompt('Copy this share link', url);
            }
        });
        document.getElementById('sopWhatsapp').addEventListener('click', function () {
            window.open('https://wa.me/?text=' + encodeURIComponent(text), '_blank', 'noopener');
        });
        document.getElementById('sopNative').addEventListener('click', function () {
            if (navigator.share) {
                navigator.share({ title: title, text: title, url: url }).catch(function () {});
            } else {
                window.open('https://wa.me/?text=' + encodeURIComponent(text), '_blank', 'noopener');
            }
        });
    })();
    </script>
</body>
</html>
