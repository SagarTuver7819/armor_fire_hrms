<?php
/**
 * Recruitment Application — Print / PDF
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/settings.php';
require_once __DIR__ . '/../includes/permission_helper.php';
require_once __DIR__ . '/../includes/recruitment_helper.php';

requireLogin();
if (!isAdmin() && !isHR() && !(function_exists('isStaffUser') && isStaffUser()) && !canAccess('recruitment', 'view')) {
    header('Location: ' . app_url('dashboard.php'));
    exit;
}

$id = (int) ($_GET['id'] ?? 0);
$row = $id > 0 ? getRecruitmentApplication($id) : null;
if (!$row) {
    die('Application not found.');
}

$labels = recruitmentStatusLabels();
$modes = recruitmentInterviewModes();
$sides = recruitmentAwaitedSides();
$company = getCompanyName();
$logo = getLoginLogo();
$logoSrc = $logo;
if ($logoSrc && strpos($logoSrc, 'http') !== 0 && strpos($logoSrc, '/') !== 0) {
    $logoSrc = app_url($logoSrc);
}

$criteria = getRecruitmentCriteriaForPosition((string) ($row['position_name'] ?? ''));
$marks = getRecruitmentApplicationMarks((int) $row['id']);
// Merge any saved marks not in criteria list
foreach ($marks as $m) {
    $found = false;
    foreach ($criteria as $c) {
        if (strcasecmp((string) $c['criteria_label'], (string) $m['criteria_label']) === 0) {
            $found = true;
            break;
        }
    }
    if (!$found) {
        $criteria[] = [
            'id' => (int) ($m['criteria_id'] ?? 0),
            'criteria_label' => $m['criteria_label'],
            'position_name' => '',
        ];
    }
}

$v = static function ($x) {
    $x = trim((string) $x);
    return $x !== '' ? $x : '—';
};
$money = static function ($x) {
    if ($x === null || $x === '') {
        return '—';
    }
    return '₹ ' . number_format((float) $x, 0);
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Application PDF · <?php echo htmlspecialchars((string) $row['application_no']); ?></title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: Arial, Helvetica, sans-serif; color: #111; margin: 0; padding: 18px; font-size: 12px; line-height: 1.45; }
        .bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px; }
        .bar button { background: #d2232a; color: #fff; border: 0; padding: 10px 14px; border-radius: 8px; font-weight: 700; cursor: pointer; }
        .head { display: flex; gap: 12px; align-items: center; border-bottom: 2px solid #d2232a; padding-bottom: 10px; margin-bottom: 14px; }
        .head img { width: 56px; height: 56px; object-fit: contain; }
        .head h1 { margin: 0; font-size: 18px; color: #d2232a; }
        .head p { margin: 2px 0 0; color: #555; }
        h2 { margin: 16px 0 8px; font-size: 13px; text-transform: uppercase; letter-spacing: .04em; color: #d2232a; border-left: 3px solid #d2232a; padding-left: 8px; }
        table.info { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
        table.info td { width: 50%; vertical-align: top; padding: 4px 8px 4px 0; }
        table.info .lbl { display: block; font-size: 10px; color: #777; text-transform: uppercase; font-weight: 700; }
        table.info .val { font-size: 12px; font-weight: 700; }
        table.grid { width: 100%; border-collapse: collapse; }
        table.grid th, table.grid td { border: 1px solid #ddd; padding: 6px 8px; text-align: left; font-size: 11px; }
        table.grid th { background: #f8fafc; }
        .mark-yes { color: #15803d; font-weight: 800; }
        .mark-no { color: #94a3b8; }
        .badge { display: inline-block; padding: 3px 8px; border-radius: 999px; background: #fee2e2; color: #9f1239; font-size: 11px; font-weight: 800; }
        @media print {
            .bar { display: none !important; }
            body { padding: 0; }
        }
    </style>
</head>
<body>
<div class="bar">
    <div><strong><?php echo htmlspecialchars((string) $row['application_no']); ?></strong> · Application Print</div>
    <button type="button" onclick="window.print()">Print / Save PDF</button>
</div>

<div class="head">
    <?php if ($logoSrc): ?><img src="<?php echo htmlspecialchars($logoSrc); ?>" alt=""><?php endif; ?>
    <div>
        <h1><?php echo htmlspecialchars($company); ?></h1>
        <p>Recruitment Application · Interview Evaluation</p>
    </div>
</div>

<p>
    <strong style="font-size:16px;"><?php echo htmlspecialchars((string) $row['full_name']); ?></strong>
    <span class="badge"><?php echo htmlspecialchars($labels[$row['status']] ?? $row['status']); ?></span><br>
    <?php echo htmlspecialchars((string) $row['application_no']); ?> ·
    <?php echo htmlspecialchars((string) $row['position_name']); ?> ·
    <?php echo htmlspecialchars((string) $row['department_name']); ?>
</p>

<h2>Personal Details</h2>
<table class="info">
    <tr>
        <td><span class="lbl">Mobile</span><span class="val"><?php echo htmlspecialchars($v($row['mobile'] ?? '')); ?></span></td>
        <td><span class="lbl">Email</span><span class="val"><?php echo htmlspecialchars($v($row['email'] ?? '')); ?></span></td>
    </tr>
    <tr>
        <td><span class="lbl">DOB / Age</span><span class="val"><?php
            echo !empty($row['dob']) ? htmlspecialchars(date('d M Y', strtotime($row['dob']))) : '—';
            echo !empty($row['age_years']) ? (' / ' . (int) $row['age_years'] . ' yrs') : '';
        ?></span></td>
        <td><span class="lbl">Gender / Marital</span><span class="val"><?php echo htmlspecialchars($v($row['gender'] ?? '') . ' / ' . $v($row['marital_status'] ?? '')); ?></span></td>
    </tr>
    <tr>
        <td colspan="2"><span class="lbl">Address</span><span class="val"><?php echo nl2br(htmlspecialchars($v($row['address'] ?? ''))); ?></span></td>
    </tr>
</table>

<h2>ID &amp; Bank</h2>
<table class="info">
    <tr>
        <td><span class="lbl">Aadhaar</span><span class="val"><?php echo htmlspecialchars($v($row['aadhaar_no'] ?? '')); ?></span></td>
        <td><span class="lbl">PAN</span><span class="val"><?php echo htmlspecialchars($v($row['pan_no'] ?? '')); ?></span></td>
    </tr>
    <tr>
        <td><span class="lbl">Bank</span><span class="val"><?php echo htmlspecialchars($v($row['bank_name'] ?? '')); ?></span></td>
        <td><span class="lbl">A/C / IFSC</span><span class="val"><?php echo htmlspecialchars(trim(($row['bank_account'] ?? '') . ' / ' . ($row['bank_ifsc'] ?? ''), ' /') ?: '—'); ?></span></td>
    </tr>
</table>

<h2>Salary</h2>
<table class="info">
    <tr>
        <td><span class="lbl">Current</span><span class="val"><?php echo $money($row['current_salary'] ?? null); ?></span></td>
        <td><span class="lbl">Expected</span><span class="val"><?php echo $money($row['expected_salary'] ?? null); ?></span></td>
    </tr>
    <tr>
        <td><span class="lbl">Notice</span><span class="val"><?php echo htmlspecialchars($v($row['notice_period'] ?? '')); ?></span></td>
        <td><span class="lbl">Total Experience</span><span class="val"><?php echo htmlspecialchars($v($row['total_experience'] ?? '')); ?></span></td>
    </tr>
</table>

<h2>Education</h2>
<table class="grid">
    <thead><tr><th>Degree</th><th>Institution</th><th>Year</th><th>% / CGPA</th></tr></thead>
    <tbody>
    <?php if (empty($row['education'])): ?>
        <tr><td colspan="4">—</td></tr>
    <?php else: foreach ($row['education'] as $ed): ?>
        <tr>
            <td><?php echo htmlspecialchars($v($ed['degree'] ?? '')); ?></td>
            <td><?php echo htmlspecialchars($v($ed['institution'] ?? '')); ?></td>
            <td><?php echo htmlspecialchars($v($ed['year_of_passing'] ?? '')); ?></td>
            <td><?php echo htmlspecialchars($v($ed['percentage'] ?? '')); ?></td>
        </tr>
    <?php endforeach; endif; ?>
    </tbody>
</table>

<h2>Experience</h2>
<table class="grid">
    <thead><tr><th>Company</th><th>Designation</th><th>From</th><th>To</th><th>Salary</th></tr></thead>
    <tbody>
    <?php if (empty($row['experience'])): ?>
        <tr><td colspan="5">—</td></tr>
    <?php else: foreach ($row['experience'] as $ex): ?>
        <tr>
            <td><?php echo htmlspecialchars($v($ex['company_name'] ?? '')); ?></td>
            <td><?php echo htmlspecialchars($v($ex['designation'] ?? '')); ?></td>
            <td><?php echo htmlspecialchars($v($ex['from_date'] ?? '')); ?></td>
            <td><?php echo !empty($ex['is_current']) ? 'Present' : htmlspecialchars($v($ex['to_date'] ?? '')); ?></td>
            <td><?php echo $money($ex['last_salary'] ?? null); ?></td>
        </tr>
    <?php endforeach; endif; ?>
    </tbody>
</table>

<h2>HR Round · Interview Matrix
    <small style="font-weight:600;color:#666;text-transform:none;">
        (<?php echo htmlspecialchars((string) ($row['position_name'] ?: 'All Positions')); ?>)
    </small>
</h2>
<table class="grid">
    <thead><tr><th style="width:40px;">#</th><th>Question / Criteria</th><th style="width:220px;">Answer</th></tr></thead>
    <tbody>
    <?php if (!$criteria): ?>
        <tr><td colspan="3">No criteria marked.</td></tr>
    <?php else: $i = 1; foreach ($criteria as $c):
        $key = strtolower(trim((string) $c['criteria_label']));
        $type = (string) ($c['answer_type'] ?? 'yesno');
        $mark = $marks[$key] ?? [];
        $display = function_exists('recruitmentFormatMarkAnswer')
            ? recruitmentFormatMarkAnswer($mark, $type)
            : '—';
        $isYes = ($display === 'Yes');
        $isNo = ($display === 'No');
    ?>
        <tr>
            <td><?php echo $i++; ?></td>
            <td><?php echo htmlspecialchars((string) $c['criteria_label']); ?></td>
            <td class="<?php echo $isYes ? 'mark-yes' : ($isNo ? 'mark-no' : ''); ?>">
                <?php echo nl2br(htmlspecialchars($display)); ?>
            </td>
        </tr>
    <?php endforeach; endif; ?>
    </tbody>
</table>

<h2>Interview Result</h2>
<table class="info">
    <tr>
        <td><span class="lbl">Mode</span><span class="val"><?php
            $m = (string) ($row['interview_mode'] ?? '');
            echo htmlspecialchars($modes[$m] ?? $v($m));
        ?></span></td>
        <td><span class="lbl">Date</span><span class="val"><?php echo !empty($row['interview_date']) ? htmlspecialchars(date('d M Y', strtotime($row['interview_date']))) : '—'; ?></span></td>
    </tr>
    <tr>
        <td><span class="lbl">Awaited With</span><span class="val"><?php
            $aw = (string) ($row['awaited_with'] ?? '');
            echo htmlspecialchars($sides[$aw] ?? $v($aw));
        ?></span></td>
        <td><span class="lbl">Status</span><span class="val"><?php echo htmlspecialchars($labels[$row['status']] ?? $row['status']); ?></span></td>
    </tr>
    <tr>
        <td colspan="2"><span class="lbl">Notes</span><span class="val"><?php echo nl2br(htmlspecialchars($v($row['interview_notes'] ?? ''))); ?></span></td>
    </tr>
    <tr>
        <td colspan="2"><span class="lbl">Not Selected Reason</span><span class="val"><?php echo nl2br(htmlspecialchars($v($row['not_selected_reason'] ?? ''))); ?></span></td>
    </tr>
</table>

<p style="margin-top:28px;color:#666;">Generated on <?php echo date('d M Y H:i'); ?> · <?php echo htmlspecialchars($company); ?> HRMS</p>
</body>
</html>
