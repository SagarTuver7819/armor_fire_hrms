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
require_once __DIR__ . '/../includes/sop_terms.php';

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
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo cncSopH($title); ?></title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Noto+Sans+Gujarati:wght@600;700;800&display=swap">
    <style>
        body {
            margin: 0;
            padding: 14px;
            background: #e5e7eb;
            color: #111;
            font-family: Calibri, Candara, Segoe UI, Arial, sans-serif;
        }
        <?php echo companyDocPrintCss(); ?>
        <?php echo cncSopLetterCss(); ?>
        <?php echo cncSopTermsCss(); ?>
        @media print {
            html, body { background: #fff !important; padding: 0 !important; margin: 0 !important; }
            @page { size: A4 portrait; margin: 6mm; }
        }
    </style>
</head>
<body>
    <div class="sop-toolbar no-print">
        <a class="secondary" href="<?php echo app_url('sop/index.php?department_id=' . $deptId); ?>">Back to Contractor Agreement</a>
        <a class="secondary" href="<?php echo app_url('sop/edit.php?id=' . (int) $row['id'] . '&department_id=' . $deptId); ?>">Edit</a>
        <button type="button" id="contractorSave">Save Contractor Sign</button>
        <button type="button" onclick="window.print()">Print / Save PDF</button>
        <button type="button" id="sopCopy">Copy share link</button>
        <button type="button" id="sopWhatsapp">WhatsApp</button>
        <button type="button" id="sopNative">Share</button>
    </div>
    <?php if ($saved): ?>
        <p class="sop-note no-print">Saved on the letterhead.</p>
    <?php endif; ?>
    <div class="sop-sheet-wrap">
        <?php echo cncSopLetterSheet($row, $brand, false, true); ?>
        <?php echo cncSopTermsSheetsHtml($brand); ?>
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
        var pad = document.getElementById('contractorPad');
        var saveBtn = document.getElementById('contractorSave');
        if (pad && saveBtn) {
            var ctx = pad.getContext('2d');
            var drawing = false;
            var dirty = false;
            ctx.lineWidth = 4.2;
            ctx.lineCap = 'round';
            ctx.lineJoin = 'round';
            ctx.strokeStyle = '#111';
            var existing = <?php echo json_encode((string) ($row['contractor_signature'] ?? ''), JSON_UNESCAPED_SLASHES); ?>;
            if (existing && existing.indexOf('data:image/png') === 0) {
                var img = new Image();
                img.onload = function () { ctx.drawImage(img, 0, 0, pad.width, pad.height); };
                img.src = existing;
            }
            function pos(e) {
                var r = pad.getBoundingClientRect();
                return {
                    x: (e.clientX - r.left) * (pad.width / r.width),
                    y: (e.clientY - r.top) * (pad.height / r.height)
                };
            }
            pad.addEventListener('pointerdown', function (e) {
                drawing = true;
                dirty = true;
                pad.setPointerCapture(e.pointerId);
                var p = pos(e);
                ctx.beginPath();
                ctx.moveTo(p.x, p.y);
            });
            pad.addEventListener('pointermove', function (e) {
                if (!drawing) return;
                var p = pos(e);
                ctx.lineTo(p.x, p.y);
                ctx.stroke();
            });
            function stop() { drawing = false; }
            pad.addEventListener('pointerup', stop);
            pad.addEventListener('pointercancel', stop);
            saveBtn.addEventListener('click', function () {
                if (!dirty && !existing) {
                    saveBtn.textContent = 'Sign first';
                    return;
                }
                saveBtn.disabled = true;
                saveBtn.textContent = 'Saving...';
                var body = new FormData();
                body.append('id', <?php echo (int) $row['id']; ?>);
                body.append('department_id', <?php echo (int) $deptId; ?>);
                body.append('contractor_signature', dirty ? pad.toDataURL('image/png') : existing);
                fetch(<?php echo json_encode(app_url('sop/sign.php')); ?>, { method: 'POST', body: body })
                    .then(function (r) { return r.json(); })
                    .then(function (res) {
                        if (res && res.ok) {
                            window.location.reload();
                            return;
                        }
                        saveBtn.disabled = false;
                        saveBtn.textContent = 'Save failed';
                    })
                    .catch(function () {
                        saveBtn.disabled = false;
                        saveBtn.textContent = 'Save failed';
                    });
            });
        }
    })();
    </script>
</body>
</html>
