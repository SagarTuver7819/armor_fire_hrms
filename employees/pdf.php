<?php
/**
 * Employee Application Form PDF — 3 pages
 * Header / Footer / Watermark = same as Offer Letter (document_print)
 * lang=en | lang=hi
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/employee_helper.php';
require_once __DIR__ . '/../includes/settings.php';
require_once __DIR__ . '/../includes/document_print.php';
require_once __DIR__ . '/../includes/permission_helper.php';
require_once __DIR__ . '/../includes/department_head_helper.php';

requireLogin();

$id   = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$lang = (isset($_GET['lang']) && $_GET['lang'] === 'hi') ? 'hi' : 'en';

$emp = $id > 0 ? getEmployeeById($id) : null;
if (!$emp) {
    die('Employee not found.');
}

$deptId = (int) ($emp['department_id'] ?? 0);
$sessionEmpId = (int) ($_SESSION['employee_id'] ?? 0);
$isOwn = $sessionEmpId > 0 && $sessionEmpId === $id;
if (isOfficeStaffRole() && $isOwn) {
    header('Location: ' . app_url('employees/view.php?id=' . $id . '&msg=denied'));
    exit;
}
if (!canAccess('employees', 'edit', $deptId) && !isAdmin() && !isHR()) {
    requireAccess('employees', 'edit', $deptId);
}

$brand = getCompanyDocumentBranding();
$company = trim((string) ($brand['company_name'] ?? ''));
if ($company === '') {
    $company = 'Armor Fire';
    $brand['company_name'] = $company;
}

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
    return (strcasecmp(trim((string) $selected), trim((string) $value)) === 0) ? '●' : '○';
}

function blank($v)
{
    $v = trim((string) ($v ?? ''));
    return $v !== '' ? $v : '';
}

$isHi = ($lang === 'hi');

$dob = (string) ($emp['date_of_birth'] ?? '');
$age = '';
if ($dob !== '' && $dob !== '0000-00-00' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dob)) {
    try {
        $age = (string) (new DateTime($dob))->diff(new DateTime('today'))->y;
    } catch (Throwable $ex) {
        $age = '';
    }
}

$gender = trim((string) ($emp['gender'] ?? ''));
$marital = trim((string) ($emp['marital_status'] ?? ''));
$blood = trim((string) ($emp['blood_group'] ?? ''));
$email = trim((string) ($emp['office_email'] ?? ''));
$officeMobile = trim((string) ($emp['office_mobile'] ?? ''));
$mobile = trim((string) ($emp['mobile_number'] ?? ''));
$altMobile = trim((string) ($emp['emergency_mobile'] ?? ''));
$emergencyName = trim((string) ($emp['emergency_contact_name'] ?? ''));
$emergencyRel = trim((string) ($emp['emergency_contact_relation'] ?? ''));
$aadhar = trim((string) ($emp['aadhar_number'] ?? ''));
$pan = trim((string) ($emp['pan_number'] ?? ''));
$name = trim((string) ($emp['employee_name'] ?? ''));
$father = trim((string) ($emp['father_husband_name'] ?? ''));
$permAddr = trim((string) ($emp['permanent_address'] ?? ''));
$currAddr = trim((string) ($emp['present_address'] ?? ''));
$designation = trim((string) ($emp['designation'] ?? ''));
$department = trim((string) ($emp['department_name'] ?? ''));
$joining = (string) ($emp['date_of_joining'] ?? '');
$uan = trim((string) ($emp['uan_number'] ?? ''));
$pf = trim((string) ($emp['pf_deduction'] ?? ''));
$salary = '';
if ($emp['decided_salary'] !== null && $emp['decided_salary'] !== '') {
    $salary = number_format((float) $emp['decided_salary'], 2);
}

$L = $isHi ? [
    'back' => 'वापस सूची पर',
    'print' => 'प्रिंट / PDF सेव करें',
    'app_title' => 'आवेदन पत्र',
    'personal' => 'व्यक्तिगत विवरण',
    'education' => 'शैक्षणिक विवरण',
    'experience' => 'व्यावसायिक अनुभव',
    'job' => 'आवेदित पद',
    'reference' => 'संदर्भ',
    'declaration' => 'घोषणा',
    'hr_block' => 'एचआर हेड एवं प्लांट ऑपरेशन',
    'header_sub' => 'मानव संसाधन · आवेदन पत्र',
    'emp_code' => 'कर्मचारी कोड',
    'full_name' => 'पूरा नाम',
    'father' => 'पिता / पति का नाम',
    'perm_addr' => 'स्थायी पता',
    'curr_addr' => 'वर्तमान पता',
    'dob' => 'जन्म तिथि',
    'gender' => 'लिंग',
    'age' => 'आयु',
    'marital' => 'वैवाहिक स्थिति',
    'married' => 'विवाहित',
    'unmarried' => 'अविवाहित',
    'widow' => 'विधवा',
    'blood' => 'ब्लड ग्रुप',
    'email' => 'आधिकारिक ईमेल आईडी',
    'office_mobile' => 'आधिकारिक मोबाइल नंबर',
    'mobile' => 'मोबाइल नंबर',
    'alt_mobile' => 'आपातकालीन मोबाइल',
    'emergency_name' => 'आपातकालीन संपर्क नाम',
    'emergency_relation' => 'आपातकालीन संपर्क संबंध',
    'aadhar' => 'आधार नंबर',
    'pan' => 'पैन नंबर',
    'deg_name' => 'डिग्री का नाम',
    'board' => 'बोर्ड / विश्वविद्यालय',
    'month_year' => 'माह – वर्ष',
    'percentage' => 'प्रतिशत',
    'company_name' => 'कंपनी का नाम',
    'designation' => 'पदनाम',
    'department' => 'विभाग',
    'from' => 'से',
    'to' => 'तक',
    'salary' => 'वेतन',
    'position' => 'आवेदित पद',
    'curr_salary' => 'वर्तमान वेतन',
    'exp_salary' => 'अपेक्षित वेतन',
    'joining_time' => 'जॉइनिंग हेतु समय',
    'uan' => 'यदि पीएफ कटौती — यूएएन नं.',
    'esic' => 'ईएसआईसी नं. (यदि हो)',
    'ref_name' => 'नाम',
    'ref_contact' => 'संपर्क नंबर',
    'ref_relation' => 'संबंध',
    'decl_text' => 'मैं एतद्द्वारा घोषणा करता / करती हूँ कि मेरे द्वारा दी गई उपरोक्त जानकारी मेरे ज्ञान और विश्वास के अनुसार सत्य एवं सही है। मैं समझता / समझती हूँ कि कोई भी गलत या असत्य जानकारी कंपनी द्वारा उचित कार्रवाई का आधार बन सकती है।',
    'applicant_name' => 'आवेदक का नाम :',
    'applicant_sign' => 'आवेदक के हस्ताक्षर :',
    'date' => 'दिनांक :',
    'offered_desig' => 'प्रस्तावित पदनाम',
    'offered_ctc' => 'प्रस्तावित सीटीसी',
    'pf' => 'पीएफ (कर्मचारी / नियोक्ता)',
    'pt' => 'पीटी (सरकारी नियमों के अनुसार)',
    'salary_hand' => 'हाथ में वेतन',
    'confirm_text' => 'मैं पुष्टि करता / करती हूँ कि मैंने उपरोक्त जानकारी पढ़कर समझ ली है तथा',
    'confirm_with' => 'के साथ रोजगार की शर्तों को स्वीकार करता / करती हूँ।',
    'sig_applicant' => 'आवेदक के हस्ताक्षर',
    'sig_hr' => 'एचआर हेड एवं प्लांट ऑपरेशन',
    'male' => 'पुरुष',
    'female' => 'महिला',
    'other' => 'अन्य',
] : [
    'back' => 'Back to List',
    'print' => 'Print / Save as PDF',
    'app_title' => 'APPLICATION FORM',
    'personal' => 'PERSONAL DETAILS',
    'education' => 'EDUCATIONAL DETAILS',
    'experience' => 'PROFESSIONAL EXPERIENCE',
    'job' => 'JOB APPLIED FOR',
    'reference' => 'REFERENCE',
    'declaration' => 'DECLARATION',
    'hr_block' => 'HR HEAD & PLANT OPERATION',
    'header_sub' => 'Human Resource · Application Form',
    'emp_code' => 'Employee Code',
    'full_name' => 'FULL NAME',
    'father' => 'FATHER / HUSBAND NAME',
    'perm_addr' => 'PERMANENT ADDRESS',
    'curr_addr' => 'CURRENT ADDRESS',
    'dob' => 'DATE OF BIRTH',
    'gender' => 'GENDER',
    'age' => 'AGE',
    'marital' => 'MARITAL STATUS',
    'married' => 'MARRIED',
    'unmarried' => 'UNMARRIED',
    'widow' => 'WIDOW',
    'blood' => 'BLOOD GROUP',
    'email' => 'OFFICIAL MAIL ID',
    'office_mobile' => 'OFFICIAL MOBILE NO.',
    'mobile' => 'MOBILE NUMBER',
    'alt_mobile' => 'EMERGENCY MOBILE',
    'emergency_name' => 'EMERGENCY CONTACT NAME',
    'emergency_relation' => 'EMERGENCY CONTACT RELATION',
    'aadhar' => 'AADHAR NO.',
    'pan' => 'PAN NO',
    'deg_name' => 'NAME OF DEGREE',
    'board' => 'BOARD / UNIVERSITY',
    'month_year' => 'MONTH – YEAR',
    'percentage' => 'PERCENTAGE',
    'company_name' => 'COMPANY NAME',
    'designation' => 'DESIGNATION',
    'department' => 'DEPARTMENT',
    'from' => 'FROM',
    'to' => 'TO',
    'salary' => 'SALARY',
    'position' => 'POSITION APPLIED',
    'curr_salary' => 'CURRENT SALARY',
    'exp_salary' => 'EXPECTED SALARY',
    'joining_time' => 'JOINING TIME REQUIRED',
    'uan' => 'IF PF DEDUCTED UAN NO.',
    'esic' => 'ESIC NO (IF AVAILABLE)',
    'ref_name' => 'NAME',
    'ref_contact' => 'CONTACT NUMBER',
    'ref_relation' => 'RELATION',
    'decl_text' => 'I HEREBY DECLARE THAT THE ABOVE INFORMATION PROVIDED BY ME IS TRUE AND CORRECT TO THE BEST OF MY KNOWLEDGE AND BELIEF. I UNDERSTAND THAT ANY FALSE OR INCORRECT INFORMATION MAY LEAD TO APPROPRIATE ACTION BY THE COMPANY.',
    'applicant_name' => 'NAME OF APPLICANT :',
    'applicant_sign' => 'SIGNATURE OF APPLICANT :',
    'date' => 'DATE :',
    'offered_desig' => 'Offered Designation',
    'offered_ctc' => 'Offered CTC',
    'pf' => 'PF (Employee / Employer)',
    'pt' => 'PT (as per Govt. Rules)',
    'salary_hand' => 'Salary in Hand',
    'confirm_text' => 'I CONFIRM THAT I HAVE READ AND UNDERSTOOD THE ABOVE INFORMATION AND ACCEPT THE TERMS OF EMPLOYMENT WITH',
    'confirm_with' => '.',
    'sig_applicant' => 'APPLICANT SIGNATURE',
    'sig_hr' => 'HR HEAD & PLANT OPERATION',
    'male' => 'Male',
    'female' => 'Female',
    'other' => 'Other',
];

$headerSubtitle = $L['header_sub'];
$empCode = trim((string) ($emp['employee_code'] ?? ''));

$genderDisp = blank($gender);
if ($isHi && $genderDisp !== '') {
    $gLow = strtolower($genderDisp);
    if (in_array($gLow, ['male', 'm', 'पुरुष'], true)) {
        $genderDisp = $L['male'];
    } elseif (in_array($gLow, ['female', 'f', 'महिला'], true)) {
        $genderDisp = $L['female'];
    } elseif (in_array($gLow, ['other', 'o', 'अन्य'], true)) {
        $genderDisp = $L['other'];
    }
}
?>
<!DOCTYPE html>
<html lang="<?php echo $isHi ? 'hi' : 'en'; ?>">
<head>
    <meta charset="UTF-8">
    <title>Application Form · <?php echo e($emp['employee_code'] ?? $name); ?></title>
    <link rel="stylesheet" href="<?php echo app_url('assets/css/employee_pdf.css'); ?>?v=<?php echo (int) @filemtime(__DIR__ . '/../assets/css/employee_pdf.css'); ?>">
    <style>
        <?php echo companyDocPrintCss(); ?>
        .cdoc-header {
            gap: 12px;
            padding-bottom: 6px;
            margin-bottom: 4px;
            border-bottom-width: 2px;
            align-items: center;
            flex-shrink: 0;
        }
        .cdoc-header-logo {
            width: 72px;
            height: 72px;
            border: 0;
            padding: 0;
            background: transparent;
        }
        .cdoc-header-text .cdoc-company {
            font-size: 20px;
            font-weight: 800;
            color: #b91c1c;
            font-family: Calibri, Candara, Segoe UI, Arial, sans-serif;
            margin: 0 0 3px;
            line-height: 1.1;
            text-transform: uppercase;
        }
        .cdoc-header-text .cdoc-header-details {
            font-size: 11px;
            font-weight: 700;
            color: #1e293b;
            font-family: Calibri, Candara, Segoe UI, Arial, sans-serif;
            line-height: 1.3;
        }
        .cdoc-footer {
            margin-top: auto;
            padding-top: 6px;
            border-top-width: 1.5px;
            font-size: 10px;
            line-height: 1.35;
            font-family: Calibri, Candara, Segoe UI, Arial, sans-serif;
            flex-shrink: 0;
        }
        .cdoc-watermark img {
            width: min(42%, 240px);
            max-height: 240px;
            opacity: 0.05;
        }
    </style>
</head>
<body class="af-body<?php echo $isHi ? ' af-hi' : ''; ?>">

<div class="pdf-toolbar no-print">
    <a href="<?php echo app_url('employees/index.php?department_id=' . (int) $emp['department_id']); ?>" class="tb-btn">← <?php echo e($L['back']); ?></a>
    <div class="tb-right">
        <a href="<?php echo app_url('employees/pdf.php?id=' . (int) $emp['id'] . '&lang=en'); ?>" class="tb-btn <?php echo !$isHi ? 'active' : ''; ?>">English</a>
        <a href="<?php echo app_url('employees/pdf.php?id=' . (int) $emp['id'] . '&lang=hi'); ?>" class="tb-btn <?php echo $isHi ? 'active' : ''; ?>">हिंदी</a>
        <button type="button" class="tb-btn primary" onclick="window.print()"><?php echo e($L['print']); ?></button>
    </div>
</div>

<!-- ===================== PAGE 1 ===================== -->
<section class="af-page cdoc-sheet">
    <?php echo companyDocRenderWatermark($brand); ?>
    <div class="af-page-inner cdoc-inner">
        <?php echo companyDocRenderHeader($brand, $headerSubtitle); ?>

        <?php if ($empCode !== ''): ?>
        <div class="af-emp-code-wrap">
            <span class="af-emp-code"><?php echo e($L['emp_code']); ?>: <?php echo e($empCode); ?></span>
        </div>
        <?php endif; ?>

        <div class="af-fill">
            <h1 class="af-doc-title"><?php echo e($L['app_title']); ?></h1>
            <h2 class="af-sec-title"><?php echo e($L['personal']); ?></h2>

            <table class="af-grid af-personal">
                <tr>
                    <td class="lbl"><?php echo e($L['full_name']); ?></td>
                    <td class="val" colspan="5"><?php echo e(blank($name)); ?></td>
                </tr>
                <tr>
                    <td class="lbl"><?php echo e($L['father']); ?></td>
                    <td class="val" colspan="5"><?php echo e(blank($father)); ?></td>
                </tr>
                <tr>
                    <td class="lbl"><?php echo e($L['perm_addr']); ?></td>
                    <td class="val tall" colspan="5"><?php echo nl2br(e(blank($permAddr))); ?></td>
                </tr>
                <tr>
                    <td class="lbl"><?php echo e($L['curr_addr']); ?></td>
                    <td class="val tall" colspan="5"><?php echo nl2br(e(blank($currAddr))); ?></td>
                </tr>
                <tr>
                    <td class="lbl"><?php echo e($L['dob']); ?></td>
                    <td class="val sm"><?php echo d($dob); ?></td>
                    <td class="lbl sm"><?php echo e($L['gender']); ?></td>
                    <td class="val sm"><?php echo e($genderDisp); ?></td>
                    <td class="lbl sm"><?php echo e($L['age']); ?></td>
                    <td class="val sm"><?php echo e(blank($age)); ?></td>
                </tr>
                <tr>
                    <td class="lbl"><?php echo e($L['marital']); ?></td>
                    <td class="val" colspan="3">
                        <span class="opt"><?php echo mark($marital, 'Married'); ?> <?php echo e($L['married']); ?></span>
                        <span class="opt"><?php echo mark($marital, 'Unmarried'); ?> <?php echo e($L['unmarried']); ?></span>
                        <span class="opt"><?php echo (strcasecmp($marital, 'Widow') === 0 || strcasecmp($marital, 'Other') === 0) ? '●' : '○'; ?> <?php echo e($L['widow']); ?></span>
                    </td>
                    <td class="lbl sm"><?php echo e($L['blood']); ?></td>
                    <td class="val sm"><?php echo e(blank($blood)); ?></td>
                </tr>
                <tr>
                    <td class="lbl"><?php echo e($L['email']); ?></td>
                    <td class="val" colspan="2"><?php echo e(blank($email)); ?></td>
                    <td class="lbl"><?php echo e($L['office_mobile']); ?></td>
                    <td class="val" colspan="2"><?php echo e(blank($officeMobile)); ?></td>
                </tr>
                <tr>
                    <td class="lbl"><?php echo e($L['mobile']); ?></td>
                    <td class="val" colspan="2"><?php echo e(blank($mobile)); ?></td>
                    <td class="lbl"><?php echo e($L['alt_mobile']); ?></td>
                    <td class="val" colspan="2"><?php echo e(blank($altMobile)); ?></td>
                </tr>
                <tr>
                    <td class="lbl"><?php echo e($L['emergency_name']); ?></td>
                    <td class="val" colspan="2"><?php echo e(blank($emergencyName)); ?></td>
                    <td class="lbl"><?php echo e($L['emergency_relation']); ?></td>
                    <td class="val" colspan="2"><?php echo e(blank($emergencyRel)); ?></td>
                </tr>
                <tr>
                    <td class="lbl"><?php echo e($L['aadhar']); ?></td>
                    <td class="val" colspan="2"><?php echo e(blank($aadhar)); ?></td>
                    <td class="lbl"><?php echo e($L['pan']); ?></td>
                    <td class="val" colspan="2"><?php echo e(blank($pan)); ?></td>
                </tr>
            </table>

            <h2 class="af-sec-title"><?php echo e($L['education']); ?></h2>
            <table class="af-table af-stretch af-edu">
                <thead>
                    <tr>
                        <th style="width:28%;"><?php echo e($L['deg_name']); ?></th>
                        <th style="width:36%;"><?php echo e($L['board']); ?></th>
                        <th style="width:18%;"><?php echo e($L['month_year']); ?></th>
                        <th style="width:18%;"><?php echo e($L['percentage']); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php for ($i = 0; $i < 4; $i++): ?>
                    <tr>
                        <td>&nbsp;</td>
                        <td>&nbsp;</td>
                        <td>&nbsp;</td>
                        <td>&nbsp;</td>
                    </tr>
                    <?php endfor; ?>
                </tbody>
            </table>
        </div>

        <?php echo companyDocRenderFooter($brand); ?>
    </div>
</section>

<!-- ===================== PAGE 2 ===================== -->
<section class="af-page cdoc-sheet">
    <?php echo companyDocRenderWatermark($brand); ?>
    <div class="af-page-inner cdoc-inner">
        <?php echo companyDocRenderHeader($brand, $headerSubtitle); ?>

        <div class="af-fill">
            <h2 class="af-sec-title"><?php echo e($L['experience']); ?></h2>
            <table class="af-table af-stretch">
                <thead>
                    <tr>
                        <th style="width:24%;"><?php echo e($L['company_name']); ?></th>
                        <th style="width:18%;"><?php echo e($L['designation']); ?></th>
                        <th style="width:18%;"><?php echo e($L['department']); ?></th>
                        <th style="width:12%;"><?php echo e($L['from']); ?></th>
                        <th style="width:12%;"><?php echo e($L['to']); ?></th>
                        <th style="width:16%;"><?php echo e($L['salary']); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php for ($i = 0; $i < 6; $i++): ?>
                    <tr>
                        <td>&nbsp;</td>
                        <td>&nbsp;</td>
                        <td>&nbsp;</td>
                        <td>&nbsp;</td>
                        <td>&nbsp;</td>
                        <td>&nbsp;</td>
                    </tr>
                    <?php endfor; ?>
                </tbody>
            </table>

            <h2 class="af-sec-title"><?php echo e($L['job']); ?></h2>
            <table class="af-grid af-job af-stretch">
                <tr>
                    <td class="lbl"><?php echo e($L['position']); ?></td>
                    <td class="val"><?php echo e(blank($designation)); ?></td>
                </tr>
                <tr>
                    <td class="lbl"><?php echo e($L['department']); ?></td>
                    <td class="val"><?php echo e(blank($department)); ?></td>
                </tr>
                <tr>
                    <td class="lbl"><?php echo e($L['curr_salary']); ?></td>
                    <td class="val"><?php echo e(blank($salary)); ?></td>
                </tr>
                <tr>
                    <td class="lbl"><?php echo e($L['exp_salary']); ?></td>
                    <td class="val">&nbsp;</td>
                </tr>
                <tr>
                    <td class="lbl"><?php echo e($L['joining_time']); ?></td>
                    <td class="val"><?php echo d($joining); ?></td>
                </tr>
                <tr>
                    <td class="lbl"><?php echo e($L['uan']); ?></td>
                    <td class="val"><?php echo e(blank($uan)); ?></td>
                </tr>
                <tr>
                    <td class="lbl"><?php echo e($L['esic']); ?></td>
                    <td class="val">&nbsp;</td>
                </tr>
            </table>

            <h2 class="af-sec-title"><?php echo e($L['reference']); ?></h2>
            <table class="af-table af-stretch">
                <thead>
                    <tr>
                        <th style="width:8%;">#</th>
                        <th style="width:34%;"><?php echo e($L['ref_name']); ?></th>
                        <th style="width:30%;"><?php echo e($L['ref_contact']); ?></th>
                        <th style="width:28%;"><?php echo e($L['ref_relation']); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="ctr">1</td>
                        <td>&nbsp;</td>
                        <td>&nbsp;</td>
                        <td>&nbsp;</td>
                    </tr>
                    <tr>
                        <td class="ctr">2</td>
                        <td>&nbsp;</td>
                        <td>&nbsp;</td>
                        <td>&nbsp;</td>
                    </tr>
                    <tr>
                        <td class="ctr">3</td>
                        <td>&nbsp;</td>
                        <td>&nbsp;</td>
                        <td>&nbsp;</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <?php echo companyDocRenderFooter($brand); ?>
    </div>
</section>

<!-- ===================== PAGE 3 ===================== -->
<section class="af-page cdoc-sheet">
    <?php echo companyDocRenderWatermark($brand); ?>
    <div class="af-page-inner cdoc-inner">
        <?php echo companyDocRenderHeader($brand, $headerSubtitle); ?>

        <div class="af-fill">
            <h1 class="af-doc-title"><?php echo e($L['declaration']); ?></h1>
            <p class="af-decl-text">
                <?php echo e($L['decl_text']); ?>
            </p>

            <div class="af-sign-lines">
                <div class="af-sign-row">
                    <span class="af-sign-lbl"><?php echo e($L['applicant_name']); ?></span>
                    <span class="af-sign-line"><?php echo e(blank($name)); ?></span>
                </div>
                <div class="af-sign-row">
                    <span class="af-sign-lbl"><?php echo e($L['applicant_sign']); ?></span>
                    <span class="af-sign-line">&nbsp;</span>
                </div>
                <div class="af-sign-row">
                    <span class="af-sign-lbl"><?php echo e($L['date']); ?></span>
                    <span class="af-sign-line af-sign-date"><?php echo e(date('d-m-Y')); ?></span>
                </div>
            </div>

            <div class="af-blue-rule"></div>
            <h2 class="af-sec-title"><?php echo e($L['hr_block']); ?></h2>

            <table class="af-grid af-job af-stretch">
                <tr>
                    <td class="lbl"><?php echo e($L['offered_desig']); ?></td>
                    <td class="val"><?php echo e(blank($designation)); ?></td>
                </tr>
                <tr>
                    <td class="lbl"><?php echo e($L['department']); ?></td>
                    <td class="val"><?php echo e(blank($department)); ?></td>
                </tr>
                <tr>
                    <td class="lbl"><?php echo e($L['offered_ctc']); ?></td>
                    <td class="val">&nbsp;</td>
                </tr>
                <tr>
                    <td class="lbl"><?php echo e($L['pf']); ?></td>
                    <td class="val">&nbsp;</td>
                </tr>
                <tr>
                    <td class="lbl"><?php echo e($L['pt']); ?></td>
                    <td class="val">&nbsp;</td>
                </tr>
                <tr>
                    <td class="lbl"><?php echo e($L['salary_hand']); ?></td>
                    <td class="val">&nbsp;</td>
                </tr>
            </table>

            <p class="af-confirm">
                <span class="af-check">○</span>
                <?php echo e($L['confirm_text']); ?>
                <strong><?php echo e($company); ?></strong><?php echo e($L['confirm_with']); ?>
            </p>

            <div class="af-bottom-signs">
                <div>
                    <div class="af-sig-space"></div>
                    <div class="af-sig-cap"><?php echo e($L['sig_applicant']); ?></div>
                    <div class="af-sig-date-line"><?php echo e($L['date']); ?> _______________</div>
                </div>
                <div>
                    <div class="af-sig-space"></div>
                    <div class="af-sig-cap"><?php echo e($L['sig_hr']); ?></div>
                    <div class="af-sig-company"><?php echo e($company); ?></div>
                </div>
            </div>
        </div>

        <?php echo companyDocRenderFooter($brand); ?>
    </div>
</section>

<script>
(function () {
    function fitTable(table, targetH) {
        if (!table || targetH < 40) return;
        table.style.height = targetH + 'px';
        var rows = table.querySelectorAll('tr');
        if (!rows.length) return;

        var weights = [];
        var total = 0;
        rows.forEach(function (tr) {
            var w = tr.querySelector('.val.tall') ? 1.45 : 1;
            weights.push(w);
            total += w;
        });

        var minRow = table.classList.contains('af-edu') ? 18 : 22;
        rows.forEach(function (tr, i) {
            var per = Math.max(minRow, Math.floor((targetH * weights[i]) / total));
            tr.querySelectorAll('th, td').forEach(function (td) {
                td.style.height = per + 'px';
                td.style.paddingTop = '4px';
                td.style.paddingBottom = '4px';
            });
        });
    }

    function fitPages() {
        document.querySelectorAll('.af-page-inner').forEach(function (inner) {
            var fill = inner.querySelector('.af-fill');
            if (!fill) return;

            var tables = [].slice.call(fill.querySelectorAll('table.af-stretch'));
            if (!tables.length) return;

            tables.forEach(function (t) {
                t.style.height = 'auto';
                t.querySelectorAll('th, td').forEach(function (td) {
                    td.style.height = '';
                    td.style.paddingTop = '';
                    td.style.paddingBottom = '';
                });
            });

            var used = 0;
            [].slice.call(fill.children).forEach(function (el) {
                if (el.matches('table.af-stretch')) return;
                used += el.offsetHeight;
                var cs = window.getComputedStyle(el);
                used += (parseFloat(cs.marginTop) || 0) + (parseFloat(cs.marginBottom) || 0);
            });

            var avail = fill.clientHeight - used - 4;
            if (avail < 48) {
                // Page too full — shrink personal rows slightly
                var personal = fill.querySelector('table.af-personal');
                if (personal) {
                    personal.querySelectorAll('th, td').forEach(function (td) {
                        td.style.paddingTop = '3px';
                        td.style.paddingBottom = '3px';
                    });
                    used = 0;
                    [].slice.call(fill.children).forEach(function (el) {
                        if (el.matches('table.af-stretch')) return;
                        used += el.offsetHeight;
                        var cs = window.getComputedStyle(el);
                        used += (parseFloat(cs.marginTop) || 0) + (parseFloat(cs.marginBottom) || 0);
                    });
                    avail = fill.clientHeight - used - 4;
                }
            }
            if (avail < 40) return;

            var weights = tables.map(function (t, i) {
                if (tables.length === 1) return 1;
                if (t.classList.contains('af-edu')) return 1.1;
                if (t.classList.contains('af-job')) return 1.15;
                if (i === 0) return 1.2;
                return 1;
            });
            var wSum = weights.reduce(function (a, b) { return a + b; }, 0);

            tables.forEach(function (t, i) {
                fitTable(t, Math.floor((avail * weights[i]) / wSum));
            });

            var overflow = fill.scrollHeight - fill.clientHeight;
            var guard = 0;
            while (overflow > 2 && guard < 4) {
                tables.forEach(function (t) {
                    var nh = Math.max(64, t.offsetHeight - Math.ceil(overflow / tables.length) - 2);
                    fitTable(t, nh);
                });
                overflow = fill.scrollHeight - fill.clientHeight;
                guard++;
            }
        });
    }

    function run() {
        fitPages();
        requestAnimationFrame(fitPages);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', run);
    } else {
        run();
    }
    window.addEventListener('load', run);
    window.addEventListener('beforeprint', fitPages);
    window.addEventListener('resize', run);
})();
</script>

</body>
</html>
