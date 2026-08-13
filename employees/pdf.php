<?php
/**
 * Employee Information Form - Print / PDF View
 * lang=en  → English format
 * lang=hi  → Hindi format
 *
 * Open in browser → Click "Print / Save PDF"
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/employee_helper.php';
require_once __DIR__ . '/../includes/settings.php';

requireLogin();

$id   = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$lang = (isset($_GET['lang']) && $_GET['lang'] === 'hi') ? 'hi' : 'en';

$emp = $id > 0 ? getEmployeeById($id) : null;
if (!$emp) {
    die('Employee not found.');
}

$companyName = getCompanyName();
// Use full company title like the paper form
$companyTitle = 'ARMOR STEEL INDUSTRIES PVT LTD';

function e($v)
{
    return htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');
}

function d($v)
{
    return e(formatDateDisplay($v));
}

function mark($selected, $value)
{
    return ($selected === $value) ? '●' : '○';
}

$isHi = ($lang === 'hi');

// Labels EN / HI
$L = $isHi ? [
    'form_title' => 'कर्मचारी जानकारी प्रपत्र',
    'emp_code'   => 'कर्मचारी कोड',
    '1'  => 'कर्मचारी का नाम (आधार कार्ड के अनुसार)',
    '2'  => 'पिता या पति का नाम',
    '3'  => 'स्थायी पता (आधार कार्ड के अनुसार)',
    '4'  => 'वर्तमान पता',
    '5'  => 'मोबाइल नंबर (आधार से लिंक)',
    '6'  => 'आपातकालीन मोबाइल नंबर',
    '7'  => 'आधार कार्ड नंबर',
    '8'  => 'पैन कार्ड नंबर',
    '9'  => 'जन्म तिथि',
    '10' => 'विभाग',
    '11' => 'पदनाम',
    '12' => 'ज्वाइनिंग की तिथि',
    '13' => 'शिफ्ट समय',
    'day'=> 'दिन',
    'night'=>'रात',
    'time'=> 'समय',
    '14' => 'पीएफ कटौती',
    'yes'=> 'हाँ',
    'no' => 'नहीं',
    '15' => 'UAN नंबर (पीएफ कटौती होने पर)',
    '16' => 'बैंक का नाम',
    '17' => 'बैंक खाता नंबर',
    '18' => 'IFSC कोड',
    '19' => 'बैंक शाखा का पता',
    '20' => 'तय वेतन / रिपोर्टिंग हेड',
    '21' => 'अतिरिक्त टिप्पणी',
    '22' => 'साप्ताहिक अवकाश (Week-Off) का दिन',
    '23' => 'साप्ताहिक अवकाश (Week-Off) का लाभ',
    '24' => 'अवकाश / ओवरटाइम का लाभ',
    'decl'=> 'मैं एतद्द्वारा घोषणा करता/करती हूँ कि जॉइनिंग फॉर्म में मेरे द्वारा दी गई सभी जानकारी मेरे ज्ञान और विश्वास के अनुसार सत्य, पूर्ण और सही है। मैं समझता/समझती हूँ कि कोई भी गलत या असत्य जानकारी देने पर कंपनी द्वारा उचित कार्रवाई की जा सकती है।',
    'sig_emp' => 'कर्मचारी के हस्ताक्षर',
    'sig_hod' => 'HOD के हस्ताक्षर',
    'sig_hr'  => 'HR के हस्ताक्षर',
    'print'   => 'प्रिंट / PDF सेव करें',
    'back'    => 'वापस सूची पर',
] : [
    'form_title' => 'EMPLOYEE INFORMATION FORM',
    'emp_code'   => 'Employee Code',
    '1'  => 'Employee Name (As per Aadhar Card)',
    '2'  => 'Father or Husband Name',
    '3'  => 'Permanent Address (As per Aadhar Card)',
    '4'  => 'Present Address (Current Address)',
    '5'  => 'Mobile Number (Linked with Aadhar)',
    '6'  => 'Emergency Mobile Number',
    '7'  => 'Aadhar Card Number',
    '8'  => 'PAN Card Number',
    '9'  => 'Date of Birth',
    '10' => 'Department',
    '11' => 'Designation',
    '12' => 'Date of Joining',
    '13' => 'Shift Time',
    'day'=> 'Day',
    'night'=>'Night',
    'time'=> 'Time',
    '14' => 'PF Deduction',
    'yes'=> 'Yes',
    'no' => 'No',
    '15' => 'UAN number (In case of PF Deduction)',
    '16' => 'Bank Name',
    '17' => 'Bank Account Number',
    '18' => 'IFSC Code',
    '19' => 'Bank Branch Address',
    '20' => 'Decided Salary / Reporting Head',
    '21' => 'Extra Note',
    '22' => 'Week-off Day',
    '23' => 'Week-off Benefits',
    '24' => 'Holiday / Overtime Benefits',
    'decl'=> 'I hereby declare that all Information provided by me in the joining form is true, complete, and correct to the best of my knowledge and belief. I understand that any false or incorrect information may lead to appropriate action by the Company.',
    'sig_emp' => 'Employee Signature',
    'sig_hod' => 'HOD Signature',
    'sig_hr'  => 'HR Signature',
    'print'   => 'Print / Save as PDF',
    'back'    => 'Back to List',
];

$salaryText = '';
if ($emp['decided_salary'] !== null && $emp['decided_salary'] !== '') {
    $salaryText = number_format((float) $emp['decided_salary'], 2);
}
if (!empty($emp['reporting_head'])) {
    $salaryText .= ($salaryText !== '' ? '  |  ' : '') . $emp['reporting_head'];
}
?>
<!DOCTYPE html>
<html lang="<?php echo $isHi ? 'hi' : 'en'; ?>">
<head>
    <meta charset="UTF-8">
    <title><?php echo e($L['form_title']); ?> - <?php echo e($emp['employee_code']); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Devanagari:wght@400;600;700&family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo app_url('assets/css/employee_pdf.css'); ?>">
</head>
<body class="pdf-body <?php echo $isHi ? 'lang-hi' : 'lang-en'; ?>">

<div class="pdf-toolbar no-print">
    <a href="<?php echo app_url('employees/index.php?department_id=' . (int) $emp['department_id']); ?>" class="tb-btn">← <?php echo e($L['back']); ?></a>
    <div class="tb-right">
        <a href="<?php echo app_url('employees/pdf.php?id=' . (int) $emp['id'] . '&lang=en'); ?>" class="tb-btn <?php echo !$isHi ? 'active' : ''; ?>">English</a>
        <a href="<?php echo app_url('employees/pdf.php?id=' . (int) $emp['id'] . '&lang=hi'); ?>" class="tb-btn <?php echo $isHi ? 'active' : ''; ?>">हिंदी</a>
        <button type="button" class="tb-btn primary" onclick="window.print()"><?php echo e($L['print']); ?></button>
    </div>
</div>

<div class="pdf-sheet">
    <div class="pdf-head">
        <div class="pdf-company"><?php echo e($companyTitle); ?></div>
        <div class="pdf-title"><?php echo e($L['form_title']); ?></div>
    </div>

    <table class="pdf-form">
        <tr class="code-row">
            <td class="no"></td>
            <td class="label"><?php echo e($L['emp_code']); ?></td>
            <td class="val" colspan="2"><strong><?php echo e($emp['employee_code']); ?></strong></td>
        </tr>
        <tr>
            <td class="no">1.</td>
            <td class="label"><?php echo e($L['1']); ?></td>
            <td class="val" colspan="2"><?php echo e($emp['employee_name']); ?></td>
        </tr>
        <tr>
            <td class="no">2.</td>
            <td class="label"><?php echo e($L['2']); ?></td>
            <td class="val" colspan="2"><?php echo e($emp['father_husband_name']); ?></td>
        </tr>
        <tr>
            <td class="no">3.</td>
            <td class="label"><?php echo e($L['3']); ?></td>
            <td class="val" colspan="2"><?php echo nl2br(e($emp['permanent_address'])); ?></td>
        </tr>
        <tr>
            <td class="no">4.</td>
            <td class="label"><?php echo e($L['4']); ?></td>
            <td class="val" colspan="2"><?php echo nl2br(e($emp['present_address'])); ?></td>
        </tr>
        <tr>
            <td class="no">5.</td>
            <td class="label"><?php echo e($L['5']); ?></td>
            <td class="val" colspan="2"><?php echo e($emp['mobile_number']); ?></td>
        </tr>
        <tr>
            <td class="no">6.</td>
            <td class="label"><?php echo e($L['6']); ?></td>
            <td class="val" colspan="2"><?php echo e($emp['emergency_mobile']); ?></td>
        </tr>
        <tr>
            <td class="no">7.</td>
            <td class="label"><?php echo e($L['7']); ?></td>
            <td class="val" colspan="2"><?php echo e($emp['aadhar_number']); ?></td>
        </tr>
        <tr>
            <td class="no">8.</td>
            <td class="label"><?php echo e($L['8']); ?></td>
            <td class="val" colspan="2"><?php echo e($emp['pan_number']); ?></td>
        </tr>
        <tr>
            <td class="no">9.</td>
            <td class="label"><?php echo e($L['9']); ?></td>
            <td class="val" colspan="2"><?php echo d($emp['date_of_birth']); ?></td>
        </tr>
        <tr>
            <td class="no">10.</td>
            <td class="label"><?php echo e($L['10']); ?></td>
            <td class="val" colspan="2"><?php echo e($emp['department_name']); ?></td>
        </tr>
        <tr>
            <td class="no">11.</td>
            <td class="label"><?php echo e($L['11']); ?></td>
            <td class="val" colspan="2"><?php echo e($emp['designation']); ?></td>
        </tr>
        <tr>
            <td class="no">12.</td>
            <td class="label"><?php echo e($L['12']); ?></td>
            <td class="val" colspan="2"><?php echo d($emp['date_of_joining']); ?></td>
        </tr>
        <tr>
            <td class="no">13.</td>
            <td class="label"><?php echo e($L['13']); ?></td>
            <td class="val" colspan="2">
                <?php echo mark($emp['shift_type'], 'Day'); ?> <?php echo e($L['day']); ?>
                &nbsp;&nbsp;
                <?php echo mark($emp['shift_type'], 'Night'); ?> <?php echo e($L['night']); ?>
                &nbsp;&nbsp; <?php echo e($L['time']); ?>:
                <span class="uline"><?php echo e($emp['shift_time']); ?></span>
            </td>
        </tr>
        <tr>
            <td class="no">14.</td>
            <td class="label"><?php echo e($L['14']); ?></td>
            <td class="val" colspan="2">
                <?php echo mark($emp['pf_deduction'], 'Yes'); ?> <?php echo e($L['yes']); ?>
                &nbsp;&nbsp;
                <?php echo mark($emp['pf_deduction'], 'No'); ?> <?php echo e($L['no']); ?>
            </td>
        </tr>
        <tr>
            <td class="no">15.</td>
            <td class="label"><?php echo e($L['15']); ?></td>
            <td class="val" colspan="2"><?php echo e($emp['uan_number']); ?></td>
        </tr>
        <tr>
            <td class="no">16.</td>
            <td class="label"><?php echo e($L['16']); ?></td>
            <td class="val" colspan="2"><?php echo e($emp['bank_name']); ?></td>
        </tr>
        <tr>
            <td class="no">17.</td>
            <td class="label"><?php echo e($L['17']); ?></td>
            <td class="val" colspan="2"><?php echo e($emp['bank_account_number']); ?></td>
        </tr>
        <tr>
            <td class="no">18.</td>
            <td class="label"><?php echo e($L['18']); ?></td>
            <td class="val" colspan="2"><?php echo e($emp['ifsc_code']); ?></td>
        </tr>
        <tr>
            <td class="no">19.</td>
            <td class="label"><?php echo e($L['19']); ?></td>
            <td class="val" colspan="2"><?php echo nl2br(e($emp['bank_branch_address'])); ?></td>
        </tr>
        <tr>
            <td class="no">20.</td>
            <td class="label"><?php echo e($L['20']); ?></td>
            <td class="val" colspan="2"><?php echo e($salaryText); ?></td>
        </tr>
        <tr>
            <td class="no">21.</td>
            <td class="label"><?php echo e($L['21']); ?></td>
            <td class="val" colspan="2"><?php echo nl2br(e($emp['extra_note'])); ?></td>
        </tr>
        <tr>
            <td class="no">22.</td>
            <td class="label"><?php echo e($L['22']); ?></td>
            <td class="val" colspan="2"><?php echo e($emp['week_off_day']); ?></td>
        </tr>
        <tr>
            <td class="no">23.</td>
            <td class="label"><?php echo e($L['23']); ?></td>
            <td class="val" colspan="2">
                <?php echo mark($emp['week_off_benefits'], 'Yes'); ?> <?php echo e($L['yes']); ?>
                &nbsp;&nbsp;
                <?php echo mark($emp['week_off_benefits'], 'No'); ?> <?php echo e($L['no']); ?>
            </td>
        </tr>
        <tr>
            <td class="no">24.</td>
            <td class="label"><?php echo e($L['24']); ?></td>
            <td class="val" colspan="2">
                Holiday:
                <?php echo mark($emp['holiday_benefits'], 'Yes'); ?> <?php echo e($L['yes']); ?>
                &nbsp;
                <?php echo mark($emp['holiday_benefits'], 'No'); ?> <?php echo e($L['no']); ?>
                &nbsp;&nbsp;|&nbsp;&nbsp;
                OT:
                <?php echo mark($emp['overtime_benefits'], 'Yes'); ?> <?php echo e($L['yes']); ?>
                &nbsp;
                <?php echo mark($emp['overtime_benefits'], 'No'); ?> <?php echo e($L['no']); ?>
            </td>
        </tr>
    </table>

    <div class="pdf-declare">
        <?php echo e($L['decl']); ?>
    </div>

    <div class="pdf-signs">
        <div>
            <div class="sig-line"></div>
            <div><?php echo e($L['sig_emp']); ?></div>
        </div>
        <div>
            <div class="sig-line"></div>
            <div><?php echo e($L['sig_hod']); ?></div>
        </div>
        <div>
            <div class="sig-line"></div>
            <div><?php echo e($L['sig_hr']); ?></div>
        </div>
    </div>
</div>

</body>
</html>
