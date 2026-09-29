<?php
/**
 * Employee Experience Letter — A4
 * Running: Joining → Till Date (today) — present tense grammar
 * Left: Joining → Exit Date — past tense grammar
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permission_helper.php';
require_once __DIR__ . '/../includes/settings.php';
require_once __DIR__ . '/../includes/document_print.php';
require_once __DIR__ . '/../includes/employee_helper.php';

requireLogin();
if (!isAdmin() && !isHR() && !(function_exists('isStaffUser') && isStaffUser()) && !canAccess('employees', 'view')) {
    header('Location: ' . app_url('dashboard.php'));
    exit;
}

$id = (int) ($_GET['id'] ?? 0);
$emp = $id > 0 ? getEmployeeById($id) : null;
if (!$emp) {
    header('Location: ' . app_url('employees/index.php'));
    exit;
}

$rowDeptId = (int) ($emp['department_id'] ?? 0);
if (!isAdmin() && !isHR() && !canAccess('employees', 'view', $rowDeptId)) {
    header('Location: ' . app_url('dashboard.php'));
    exit;
}

$brand = getCompanyDocumentBranding();
$company = trim((string) ($brand['company_name'] ?? ''));
if ($company === '') {
    $company = 'Armor Steel Industries Pvt Ltd';
}

$h = static function ($s) {
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
};

$fullName = strtoupper(trim((string) ($emp['employee_name'] ?? '')));
$empCode = trim((string) ($emp['employee_code'] ?? ''));
$position = trim((string) ($emp['designation'] ?? ''));
if ($position === '') {
    $position = '—';
}
$department = trim((string) ($emp['department_name'] ?? ''));
if ($department === '') {
    $department = '—';
}

$gender = strtolower(trim((string) ($emp['gender'] ?? '')));
$title = 'Mr.';
$pronounObj = 'him';
$pronounPoss = 'his';
if (in_array($gender, ['female', 'f', 'woman', 'lady'], true)) {
    $title = 'Ms.';
    $pronounObj = 'her';
    $pronounPoss = 'her';
} elseif (in_array($gender, ['male', 'm', 'man'], true)) {
    $title = 'Mr.';
    $pronounObj = 'him';
    $pronounPoss = 'his';
} elseif (in_array($gender, ['other', 'o'], true)) {
    $title = '';
    $pronounObj = 'them';
    $pronounPoss = 'their';
} else {
    // Unknown gender — neutral readable phrasing
    $title = '';
    $pronounObj = 'them';
    $pronounPoss = 'their';
}
$displayName = trim(($title !== '' ? $title . ' ' : '') . $fullName);

$joinRaw = (string) ($emp['date_of_joining'] ?? '');
$joinDisp = ($joinRaw !== '' && $joinRaw !== '0000-00-00')
    ? formatDateDisplay($joinRaw)
    : '—';

$isLeft = isEmployeeDeactive($emp);
$exitRaw = (string) ($emp['date_of_exit'] ?? '');
$exitValid = ($exitRaw !== '' && $exitRaw !== '0000-00-00' && preg_match('/^\d{4}-\d{2}-\d{2}/', $exitRaw));

if ($isLeft && $exitValid) {
    $toDisp = formatDateDisplay($exitRaw);
    $periodNote = 'Left Employee';
} else {
    $toDisp = formatDateDisplay(date('Y-m-d'));
    $periodNote = 'Running Employee · Till Date';
    $isLeft = false;
}

$letterDate = formatDateDisplay(date('Y-m-d'));
$backUrl = app_url('employees/view.php?id=' . $id . ($isLeft ? '&from=exit' : ''));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Experience Letter · <?php echo $h($empCode ?: $fullName); ?></title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            padding: 12px;
            background: #e5e7eb;
            color: #111;
            font-family: Calibri, Candara, Segoe UI, Arial, Helvetica, sans-serif;
            font-size: 16px;
            line-height: 1.6;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        .bar {
            width: 210mm;
            max-width: 100%;
            margin: 0 auto 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
            background: #111;
            color: #fff;
            padding: 10px 14px;
            font-size: 13px;
            font-weight: 700;
        }
        .bar a, .bar button {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #333;
            color: #fff;
            border: 0;
            padding: 8px 14px;
            border-radius: 8px;
            font-weight: 700;
            cursor: pointer;
            text-decoration: none;
            font-family: inherit;
            font-size: 13px;
        }
        .bar button.primary { background: #d2232a; }
        .bar .meta { opacity: .9; font-size: 12px; }
        <?php echo companyDocPrintCss(); ?>
        .page {
            width: 210mm;
            max-width: 100%;
            min-height: 297mm;
            margin: 0 auto 14px;
            border: 2.25px solid #000;
            background: #fff;
            position: relative;
            overflow: hidden;
            box-shadow: 0 8px 28px rgba(0,0,0,.12);
        }
        .page-inner {
            min-height: 297mm;
            padding: 10mm 12mm 10mm;
            display: flex;
            flex-direction: column;
            position: relative;
            z-index: 1;
        }
        .cdoc-header {
            gap: 14px;
            padding-bottom: 10px;
            margin-bottom: 18px;
            border-bottom-width: 2.5px;
            align-items: center;
            flex-shrink: 0;
        }
        .cdoc-header-logo { width: 96px; height: 96px; border: 0; padding: 0; background: transparent; }
        .cdoc-header-text .cdoc-company {
            font-size: 26px;
            font-weight: 800;
            color: #b91c1c;
            margin: 0 0 5px;
            line-height: 1.12;
            text-transform: uppercase;
            font-family: Calibri, Candara, Segoe UI, Arial, sans-serif;
        }
        .cdoc-header-text .cdoc-header-details {
            font-size: 13.5px;
            font-weight: 700;
            color: #1e293b;
            line-height: 1.45;
            font-family: Calibri, Candara, Segoe UI, Arial, sans-serif;
        }
        .cdoc-footer {
            margin-top: auto;
            padding-top: 12px;
            border-top-width: 1.5px;
            font-size: 11px;
            line-height: 1.45;
            flex-shrink: 0;
            font-family: Calibri, Candara, Segoe UI, Arial, sans-serif;
        }
        .cdoc-watermark img {
            width: min(55%, 340px);
            max-height: 340px;
            opacity: 0.055;
        }
        .el-title {
            text-align: center;
            margin: 10px 0 8px;
            font-size: 26px;
            font-weight: 800;
            letter-spacing: .08em;
            text-transform: uppercase;
            text-decoration: underline;
            text-underline-offset: 5px;
        }
        .el-concern {
            text-align: center;
            margin: 0 0 28px;
            font-size: 17px;
            font-weight: 800;
            letter-spacing: .05em;
            text-transform: uppercase;
        }
        .el-body {
            flex: 1 1 auto;
            font-size: 16.5px;
            line-height: 1.85;
            text-align: justify;
        }
        .el-body p { margin: 0 0 22px; }
        .el-body strong { font-weight: 800; }
        .el-period {
            display: inline-block;
            margin: 0 0 16px;
            padding: 5px 12px;
            border: 1px solid #fecaca;
            background: #fef2f2;
            color: #b91c1c;
            font-size: 12.5px;
            font-weight: 800;
            border-radius: 6px;
        }
        .el-sign {
            margin-top: 48px;
            font-size: 16px;
            line-height: 1.6;
            font-weight: 700;
        }
        .el-sign .for { margin-bottom: 10px; }
        .el-sign .auth {
            margin-top: 56px;
            border-top: 1.75px solid #111;
            padding-top: 10px;
            display: inline-block;
            min-width: 260px;
        }
        .el-sign .dept { margin-top: 6px; font-weight: 800; }
        .el-meta-row {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            margin: 0 0 24px;
            font-size: 15px;
            font-weight: 700;
            color: #334155;
        }
        @media print {
            @page { size: A4; margin: 0; }
            .no-print { display: none !important; }
            body { background: #fff; padding: 0; margin: 0; }
            .page {
                width: 210mm;
                min-height: 297mm;
                height: 297mm;
                margin: 0;
                box-shadow: none;
                border: 0;
            }
            .page-inner { min-height: 297mm; height: 297mm; }
        }
    </style>
</head>
<body>
<div class="bar no-print">
    <a href="<?php echo $h($backUrl); ?>">← Back to Profile</a>
    <span class="meta"><?php echo $h($periodNote); ?> · <?php echo $h($empCode); ?></span>
    <button type="button" class="primary" onclick="window.print()">Print / Save as PDF</button>
</div>

<section class="page cdoc-sheet">
    <?php echo companyDocRenderWatermark($brand); ?>
    <div class="page-inner cdoc-inner">
        <?php echo companyDocRenderHeader($brand, 'Human Resource · Experience Letter'); ?>

        <h1 class="el-title">Experience Letter</h1>
        <div class="el-concern">To Whomsoever It May Concern</div>

        <div class="el-period no-print"><?php echo $h($periodNote); ?></div>
        <div class="el-meta-row">
            <span>Employee Code: <?php echo $h($empCode !== '' ? $empCode : '—'); ?></span>
            <span>Date: <?php echo $h($letterDate); ?></span>
        </div>

        <div class="el-body">
            <?php if ($isLeft): ?>
            <p>
                This is to certify that <strong><?php echo $h($displayName); ?></strong>
                was employed with <strong><?php echo $h($company); ?></strong>
                from <strong><?php echo $h($joinDisp); ?></strong>
                to <strong><?php echo $h($toDisp); ?></strong>
                as <strong><?php echo $h($position); ?></strong>
                in the <strong><?php echo $h($department); ?></strong> department.
            </p>
            <p>
                During <?php echo $h($pronounPoss); ?> tenure with us, we found <?php echo $h($pronounObj); ?>
                to be sincere, hardworking and professional in <?php echo $h($pronounPoss); ?> conduct.
            </p>
            <p>
                We wish <?php echo $h($pronounObj); ?> success in <?php echo $h($pronounPoss); ?> future assignments.
            </p>
            <?php else: ?>
            <p>
                This is to certify that <strong><?php echo $h($displayName); ?></strong>
                is employed with <strong><?php echo $h($company); ?></strong>
                from <strong><?php echo $h($joinDisp); ?></strong>
                to <strong><?php echo $h($toDisp); ?></strong> (till date)
                as <strong><?php echo $h($position); ?></strong>
                in the <strong><?php echo $h($department); ?></strong> department.
            </p>
            <p>
                During <?php echo $h($pronounPoss); ?> tenure with us, we have found <?php echo $h($pronounObj); ?>
                to be sincere, hardworking and professional in <?php echo $h($pronounPoss); ?> conduct.
            </p>
            <p>
                We wish <?php echo $h($pronounObj); ?> continued success in <?php echo $h($pronounPoss); ?> future assignments.
            </p>
            <?php endif; ?>

            <div class="el-sign">
                <div class="for">For, <strong><?php echo $h($company); ?></strong></div>
                <div class="auth">Authorized Signatory</div>
                <div class="dept">HR Department</div>
            </div>
        </div>

        <?php echo companyDocRenderFooter($brand); ?>
    </div>
</section>
</body>
</html>
