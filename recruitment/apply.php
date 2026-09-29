<?php
/**
 * Public Career Application — no login required
 * Fixed QR / web entry point
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/settings.php';
require_once __DIR__ . '/../includes/company_content_helper.php';
require_once __DIR__ . '/../includes/recruitment_helper.php';

ensureRecruitmentTables();
ensureCompanyContentTables();

$companyName = getCompanyName();
$displayCompany = 'Armor Steel Industries Pvt. Ltd.';
$history = getCompanyContent('history');
$hBody = is_array($history['body'] ?? null) ? $history['body'] : companyContentDefaultHistory();
if (!empty($hBody['company'])) {
    $displayCompany = (string) $hBody['company'];
}
$introParas = array_slice($hBody['paragraphs'] ?? [], 0, 2);

$logo = getLoginLogo();
if (!isCustomLogo($logo)) {
    $logo = getDashboardLogo();
}
$logoSrc = $logo;
if ($logoSrc && strpos($logoSrc, 'http') !== 0 && strpos($logoSrc, '/') !== 0) {
    $logoSrc = app_url($logoSrc);
}
if (!$logoSrc) {
    $logoSrc = app_url('assets/images/logo-placeholder.svg');
}

$error = trim((string) ($_GET['err'] ?? ''));
$cssV = (int) @filemtime(__DIR__ . '/../assets/css/recruitment_apply.css');
$jsV = (int) @filemtime(__DIR__ . '/../assets/js/recruitment_apply.js');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#d2232a">
    <title>Careers · <?php echo htmlspecialchars($displayCompany); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="<?php echo app_url('assets/css/recruitment_apply.css'); ?>?v=<?php echo $cssV; ?>">
</head>
<body class="rec-page">
<div class="rec-bg" aria-hidden="true">
    <div class="rec-bg-glow rec-bg-a"></div>
    <div class="rec-bg-glow rec-bg-b"></div>
</div>

<header class="rec-topbar">
    <div class="rec-topbar-inner">
        <div class="rec-brand">
            <img src="<?php echo htmlspecialchars($logoSrc); ?>"
                 alt="<?php echo htmlspecialchars($displayCompany); ?>"
                 class="rec-logo"
                 onerror="this.src='<?php echo app_url('assets/images/logo-placeholder.svg'); ?>'">
            <div>
                <strong><?php echo htmlspecialchars($displayCompany); ?></strong>
                <span>Career Application</span>
            </div>
        </div>
        <span class="rec-top-badge"><i class="fa-solid fa-shield-halved"></i> Secure · No login needed</span>
    </div>
</header>

<main class="rec-wrap">
    <section class="rec-intro" id="recIntro">
        <div class="rec-intro-card">
            <div class="rec-intro-logo">
                <img src="<?php echo htmlspecialchars($logoSrc); ?>"
                     alt=""
                     onerror="this.src='<?php echo app_url('assets/images/logo-placeholder.svg'); ?>'">
            </div>
            <p class="rec-eyebrow">Join Our Team</p>
            <h1><?php echo htmlspecialchars($displayCompany); ?></h1>
            <p class="rec-tagline">Brand · ARMOR FIRE · Protecting lives &amp; property</p>
            <div class="rec-intro-body">
                <?php foreach ($introParas as $p): ?>
                    <p><?php echo htmlspecialchars((string) $p); ?></p>
                <?php endforeach; ?>
            </div>
            <button type="button" class="rec-btn rec-btn-primary" id="recStartBtn">
                Start Application <i class="fa-solid fa-arrow-right"></i>
            </button>
        </div>
    </section>

    <section class="rec-form-shell" id="recFormShell" hidden>
        <div class="rec-steps" id="recSteps" role="tablist" aria-label="Application steps">
            <button type="button" class="rec-step is-active" data-step="1"><span>1</span> Position</button>
            <button type="button" class="rec-step" data-step="2"><span>2</span> Personal</button>
            <button type="button" class="rec-step" data-step="3"><span>3</span> Education</button>
            <button type="button" class="rec-step" data-step="4"><span>4</span> Experience</button>
            <button type="button" class="rec-step" data-step="5"><span>5</span> Salary</button>
        </div>

        <?php if ($error !== ''): ?>
            <div class="rec-alert"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST"
              action="<?php echo app_url('recruitment/apply_save.php'); ?>"
              enctype="multipart/form-data"
              class="rec-form"
              id="recApplyForm"
              novalidate>

            <!-- Step 1: Position -->
            <fieldset class="rec-panel is-active" data-panel="1">
                <legend>Apply for Position &amp; Department</legend>
                <div class="rec-grid">
                    <div class="rec-field">
                        <label for="department_name">Department <em>*</em></label>
                        <input type="text" name="department_name" id="department_name" required
                               maxlength="150" placeholder="e.g. Production, HR, Accounts"
                               autocomplete="organization-title">
                    </div>
                    <div class="rec-field">
                        <label for="position_name">Position / Designation <em>*</em></label>
                        <input type="text" name="position_name" id="position_name" required
                               maxlength="150" placeholder="e.g. Operator, Accountant, Engineer"
                               autocomplete="organization-title">
                    </div>
                </div>
            </fieldset>

            <!-- Step 2: Personal -->
            <fieldset class="rec-panel" data-panel="2" hidden>
                <legend>Personal Details</legend>
                <div class="rec-grid">
                    <div class="rec-field rec-span-2">
                        <label for="full_name">Full Name <em>*</em></label>
                        <input type="text" name="full_name" id="full_name" required maxlength="150"
                               autocomplete="name" placeholder="As per Aadhaar / ID">
                    </div>
                    <div class="rec-field">
                        <label for="mobile">Mobile <em>*</em></label>
                        <input type="tel" name="mobile" id="mobile" required maxlength="15"
                               inputmode="numeric" pattern="[0-9]{10,15}" placeholder="10-digit mobile">
                    </div>
                    <div class="rec-field">
                        <label for="alt_mobile">Alternate Mobile</label>
                        <input type="tel" name="alt_mobile" id="alt_mobile" maxlength="15" inputmode="numeric">
                    </div>
                    <div class="rec-field">
                        <label for="email">Email <em>*</em></label>
                        <input type="email" name="email" id="email" required maxlength="150"
                               autocomplete="email" placeholder="you@example.com">
                    </div>
                    <div class="rec-field">
                        <label for="dob">Date of Birth</label>
                        <input type="date" name="dob" id="dob" max="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div class="rec-field">
                        <label for="gender">Gender</label>
                        <select name="gender" id="gender">
                            <option value="">Select</option>
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div class="rec-field">
                        <label for="marital_status">Marital Status</label>
                        <select name="marital_status" id="marital_status">
                            <option value="">Select</option>
                            <option value="Single">Single</option>
                            <option value="Married">Married</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div class="rec-field rec-span-2">
                        <label for="address">Address <em>*</em></label>
                        <textarea name="address" id="address" rows="2" required maxlength="500"
                                  placeholder="Full residential address"></textarea>
                    </div>
                    <div class="rec-field">
                        <label for="city">City</label>
                        <input type="text" name="city" id="city" maxlength="100">
                    </div>
                    <div class="rec-field">
                        <label for="state_name">State</label>
                        <input type="text" name="state_name" id="state_name" maxlength="100" value="Gujarat">
                    </div>
                    <div class="rec-field">
                        <label for="pincode">Pincode</label>
                        <input type="text" name="pincode" id="pincode" maxlength="12" inputmode="numeric">
                    </div>
                </div>
            </fieldset>

            <!-- Step 3: Education -->
            <fieldset class="rec-panel" data-panel="3" hidden>
                <legend>Educational Details</legend>
                <p class="rec-hint">Add your qualifications (highest first).</p>
                <div id="eduRows" class="rec-repeat"></div>
                <button type="button" class="rec-btn rec-btn-ghost" id="addEduBtn">
                    <i class="fa-solid fa-plus"></i> Add Education
                </button>
            </fieldset>

            <!-- Step 4: Experience -->
            <fieldset class="rec-panel" data-panel="4" hidden>
                <legend>Experience Details</legend>
                <div class="rec-field" style="max-width:220px;margin-bottom:12px;">
                    <label for="total_experience">Total Experience</label>
                    <input type="text" name="total_experience" id="total_experience"
                           placeholder="e.g. 3 years 6 months" maxlength="40">
                </div>
                <p class="rec-hint">Fresher? Leave rows empty or mark “Fresher” in total experience.</p>
                <div id="expRows" class="rec-repeat"></div>
                <button type="button" class="rec-btn rec-btn-ghost" id="addExpBtn">
                    <i class="fa-solid fa-plus"></i> Add Experience
                </button>
            </fieldset>

            <!-- Step 5: Salary + docs -->
            <fieldset class="rec-panel" data-panel="5" hidden>
                <legend>Current Salary &amp; Documents</legend>
                <div class="rec-grid">
                    <div class="rec-field">
                        <label for="current_salary">Current / Last Salary (₹ / month)</label>
                        <input type="number" name="current_salary" id="current_salary"
                               min="0" step="0.01" inputmode="decimal" placeholder="e.g. 25000">
                    </div>
                    <div class="rec-field">
                        <label for="expected_salary">Expected Salary (₹ / month) <em>*</em></label>
                        <input type="number" name="expected_salary" id="expected_salary" required
                               min="0" step="0.01" inputmode="decimal" placeholder="e.g. 30000">
                    </div>
                    <div class="rec-field">
                        <label for="notice_period">Notice Period</label>
                        <input type="text" name="notice_period" id="notice_period"
                               maxlength="80" placeholder="e.g. 30 days / Immediate">
                    </div>
                    <div class="rec-field rec-span-2">
                        <label for="bank_statement">Last 3 Months Bank Statement <span class="rec-or">OR</span></label>
                        <input type="file" name="bank_statement" id="bank_statement"
                               accept=".pdf,.jpg,.jpeg,.png,.webp,application/pdf,image/*">
                        <small>PDF / Image · Max 5 MB</small>
                    </div>
                    <div class="rec-field rec-span-2">
                        <label for="salary_slip">Last 3 Months Salary Slip</label>
                        <input type="file" name="salary_slip" id="salary_slip"
                               accept=".pdf,.jpg,.jpeg,.png,.webp,application/pdf,image/*">
                        <small>Upload bank statement <strong>or</strong> salary slip (at least one required)</small>
                    </div>
                    <div class="rec-field rec-span-2">
                        <label for="resume_file">Resume / CV (optional)</label>
                        <input type="file" name="resume_file" id="resume_file"
                               accept=".pdf,.jpg,.jpeg,.png,.webp,application/pdf,image/*">
                    </div>
                </div>
                <p class="rec-declare">
                    <label>
                        <input type="checkbox" name="declare" id="declare" value="1" required>
                        I declare that the information provided is true and complete to the best of my knowledge.
                    </label>
                </p>
            </fieldset>

            <div class="rec-nav">
                <button type="button" class="rec-btn rec-btn-ghost" id="recPrevBtn" hidden>
                    <i class="fa-solid fa-arrow-left"></i> Back
                </button>
                <div class="rec-nav-right">
                    <button type="button" class="rec-btn rec-btn-primary" id="recNextBtn">
                        Next <i class="fa-solid fa-arrow-right"></i>
                    </button>
                    <button type="submit" class="rec-btn rec-btn-primary" id="recSubmitBtn" hidden>
                        <i class="fa-solid fa-paper-plane"></i> Submit Application
                    </button>
                </div>
            </div>
        </form>
    </section>
</main>

<footer class="rec-foot">
    <span>&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($displayCompany); ?></span>
    <span>Powered by ARMOR FIRE HRMS</span>
</footer>

<script src="<?php echo app_url('assets/js/recruitment_apply.js'); ?>?v=<?php echo $jsV; ?>"></script>
</body>
</html>
