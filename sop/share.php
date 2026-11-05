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
    echo 'This SOP link is not available.';
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
        body { margin: 0; background: #eef2f6; font-family: Calibri, Candara, "Segoe UI", Arial, sans-serif; }
        <?php echo companyDocPrintCss(); ?>
        <?php echo cncSopLetterCss(); ?>
        <?php echo companyDocLetterheadPageCss($brand, true); ?>
    </style>
</head>
<body>
    <div class="sop-toolbar no-print">
        <button type="button" class="is-primary" onclick="window.print()">Print / Save PDF</button>
    </div>
    <div class="sop-sheet-wrap">
        <?php echo cncSopLetterSheet($row, $brand, false); ?>
    </div>
</body>
</html>
