<?php
/**
 * Simple Offer Letter print view for Selected candidates
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permission_helper.php';
require_once __DIR__ . '/../includes/settings.php';
require_once __DIR__ . '/../includes/recruitment_helper.php';

requireLogin();
if (!isAdmin() && !isHR() && !(function_exists('isStaffUser') && isStaffUser()) && !canAccess('recruitment', 'view')) {
    header('Location: ' . app_url('dashboard.php'));
    exit;
}

$id = (int) ($_GET['id'] ?? 0);
$row = $id > 0 ? getRecruitmentApplication($id) : null;
if (!$row || ($row['status'] ?? '') !== 'selected') {
    header('Location: ' . app_url('recruitment/interviews.php'));
    exit;
}

$conn = getDBConnection();
$conn->query(
    'UPDATE recruitment_applications SET offer_generated_at = IFNULL(offer_generated_at, NOW()) WHERE id = ' . (int) $id
);
$conn->close();

$company = getCompanyName();
$logo = getLoginLogo();
$logoSrc = $logo;
if ($logoSrc && strpos($logoSrc, 'http') !== 0 && strpos($logoSrc, '/') !== 0) {
    $logoSrc = app_url($logoSrc);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Offer Letter · <?php echo htmlspecialchars((string) $row['application_no']); ?></title>
    <style>
        body { font-family: Georgia, serif; color: #111; max-width: 760px; margin: 24px auto; padding: 0 16px; line-height: 1.55; }
        .top { display: flex; gap: 14px; align-items: center; border-bottom: 2px solid #d2232a; padding-bottom: 12px; margin-bottom: 22px; }
        .top img { width: 64px; height: 64px; object-fit: contain; }
        h1 { margin: 0; font-size: 22px; color: #d2232a; }
        .meta { color: #555; font-size: 13px; }
        .actions { margin: 18px 0; }
        .actions button { background: #d2232a; color: #fff; border: 0; padding: 10px 16px; border-radius: 8px; cursor: pointer; font-weight: 700; }
        @media print { .actions { display: none; } }
    </style>
</head>
<body>
<div class="actions"><button type="button" onclick="window.print()">Print / Save PDF</button></div>
<div class="top">
    <?php if ($logoSrc): ?><img src="<?php echo htmlspecialchars($logoSrc); ?>" alt=""><?php endif; ?>
    <div>
        <h1><?php echo htmlspecialchars($company); ?></h1>
        <div class="meta">Offer Letter · <?php echo htmlspecialchars((string) $row['application_no']); ?></div>
    </div>
</div>
<p>Date: <?php echo date('d M Y'); ?></p>
<p>To,<br><strong><?php echo htmlspecialchars((string) $row['full_name']); ?></strong><br>
<?php echo htmlspecialchars((string) $row['mobile']); ?><br>
<?php echo htmlspecialchars((string) $row['email']); ?></p>
<p>Dear <?php echo htmlspecialchars((string) $row['full_name']); ?>,</p>
<p>
We are pleased to offer you the position of
<strong><?php echo htmlspecialchars((string) $row['position_name']); ?></strong>
in the <strong><?php echo htmlspecialchars((string) $row['department_name']); ?></strong> department
at <?php echo htmlspecialchars($company); ?>.
</p>
<?php if ($row['expected_salary'] !== null): ?>
<p>Offered / discussed CTC reference: <strong>₹ <?php echo number_format((float) $row['expected_salary'], 0); ?></strong> per month (as per discussion; final as per appointment letter).</p>
<?php endif; ?>
<p>Please confirm your joining and complete onboarding formalities with HR.</p>
<p>Welcome to the team.</p>
<p style="margin-top:40px;">For <?php echo htmlspecialchars($company); ?><br><br>_________________________<br>Authorized Signatory · HR</p>
</body>
</html>
