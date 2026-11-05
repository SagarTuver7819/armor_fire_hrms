<?php
/**
 * Public letterhead view for a shared CNC SOP. Token is the only access.
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/settings.php';
require_once __DIR__ . '/../includes/document_print.php';
require_once __DIR__ . '/../includes/sop_helper.php';
require_once __DIR__ . '/../includes/sop_letter.php';

$token = (string) ($_GET['t'] ?? '');
$conn = getDBConnection();
$row = cncSopGetByToken($conn, $token);
$conn->close();
if (!$row) {
    http_response_code(404);
    echo 'This Contractor Agreement link is not available.';
    exit;
}
$brand = getCompanyDocumentBranding();
$title = (string) $row['title'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo cncSopH($title); ?></title>
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
        @media print {
            html, body { background: #fff !important; padding: 0 !important; margin: 0 !important; }
            @page { size: A4 portrait; margin: 6mm; }
        }
    </style>
</head>
<body>
    <div class="sop-toolbar no-print">
        <button type="button" id="contractorSave">Save Contractor Sign</button>
        <button type="button" onclick="window.print()">Print / Save PDF</button>
    </div>
    <div class="sop-sheet-wrap">
        <?php echo cncSopLetterSheet($row, $brand, false, true); ?>
    </div>
    <script>
    (function () {
        var pad = document.getElementById('contractorPad');
        var saveBtn = document.getElementById('contractorSave');
        if (!pad || !saveBtn) return;
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
            body.append('token', <?php echo json_encode((string) ($row['share_token'] ?? '')); ?>);
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
    })();
    </script>
</body>
</html>
