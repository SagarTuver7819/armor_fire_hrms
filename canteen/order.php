<?php
/**
 * Canteen meal booking — public mobile page opened from the Canteen QR.
 * Home: Employee Booking  |  Guest / Trainee Booking
 *  - Employee: department → employee → Breakfast / Lunch / Dinner (one booking per employee per day)
 *  - Guest:    host department → host employee → count + names → meals
 *  - Trainee:  HR department (fixed) → booked by HR employee → count + names → meals
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/settings.php';
require_once __DIR__ . '/../includes/canteen_helper.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (empty($_SESSION['canteen_token'])) {
    $_SESSION['canteen_token'] = bin2hex(random_bytes(16));
}

const CANTEEN_MAX_PERSONS = 100;

$conn = getDBConnection();
ensureCanteenTables($conn);

$mealDate = canteenTargetDate();
$isOpen = canteenIsOpen($mealDate);
$cutoffLabel = canteenCutoffLabel();
$meals = canteenMeals();
$hrDept = canteenTraineeDepartment($conn);

$mode = (string) ($_POST['mode'] ?? $_GET['mode'] ?? '');
if (!in_array($mode, ['employee', 'visitor'], true)) {
    $mode = '';
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $mode !== '') {
    $postDate = (string) ($_POST['meal_date'] ?? '');
    $b = !empty($_POST['breakfast']);
    $l = !empty($_POST['lunch']);
    $d = !empty($_POST['dinner']);
    $picked = [];
    foreach ($meals as $k => $label) {
        if (!empty($_POST[$k])) {
            $picked[] = $label;
        }
    }

    if (!hash_equals((string) $_SESSION['canteen_token'], (string) ($_POST['token'] ?? ''))) {
        $error = 'Session expired. Please reload the page and try again.';
    } elseif ($postDate !== $mealDate || !$isOpen) {
        $error = 'Booking for ' . canteenDateLabel($postDate !== '' ? $postDate : $mealDate) . ' is closed (last time ' . $cutoffLabel . ').';
    } elseif ($mode === 'employee') {
        $deptId = (int) ($_POST['department_id'] ?? 0);
        $empId = (int) ($_POST['employee_id'] ?? 0);
        $emp = $empId > 0 ? canteenActiveEmployee($conn, $empId) : null;
        if (!$emp || (int) $emp['department_id'] !== $deptId) {
            $error = 'Please select a valid department and employee.';
        } else {
            $result = canteenSaveOrder($conn, $mealDate, $emp, $b, $l, $d);
            if ($result === 'empty') {
                $error = 'Please tick at least one meal (Breakfast / Lunch / Dinner).';
            } else {
                $_SESSION['canteen_done'] = [
                    'mode'   => 'employee',
                    'result' => $result,
                    'code'   => (string) $emp['employee_code'],
                    'name'   => (string) $emp['employee_name'],
                    'meals'  => $picked,
                    'date'   => $mealDate,
                ];
            }
        }
    } else {
        $type = ($_POST['booking_type'] ?? '') === 'Trainee' ? 'Trainee' : 'Guest';
        $deptId = $type === 'Trainee' && $hrDept ? (int) $hrDept['id'] : (int) ($_POST['department_id'] ?? 0);
        $hostId = (int) ($_POST['employee_id'] ?? 0);
        $host = $hostId > 0 ? canteenActiveEmployee($conn, $hostId) : null;
        $count = (int) ($_POST['person_count'] ?? 0);
        $names = array_values(array_filter(array_map('trim', (array) ($_POST['person_names'] ?? [])), 'strlen'));
        $remarks = (string) ($_POST['remarks'] ?? '');

        if ($deptId <= 0 || !$host || (int) $host['department_id'] !== $deptId) {
            $error = $type === 'Trainee'
                ? 'Please select the HR employee who is booking for trainees.'
                : 'Please select the department and the employee the guest is with.';
        } elseif ($count < 1 || $count > CANTEEN_MAX_PERSONS) {
            $error = 'Number of ' . strtolower($type) . 's must be between 1 and ' . CANTEEN_MAX_PERSONS . '.';
        } elseif (count($names) !== $count) {
            $error = 'Please enter the name of every ' . strtolower($type) . ' (' . $count . ' required).';
        } elseif (!$b && !$l && !$d) {
            $error = 'Please tick at least one meal (Breakfast / Lunch / Dinner).';
        } else {
            canteenSaveGuestOrder($conn, $mealDate, $type, $deptId, $host, $count, $names, $remarks, $b, $l, $d);
            $deptName = '';
            foreach (canteenDepartments($conn) as $dep) {
                if ((int) $dep['id'] === $deptId) {
                    $deptName = (string) $dep['department_name'];
                }
            }
            $_SESSION['canteen_done'] = [
                'mode'   => 'visitor',
                'result' => 'saved',
                'type'   => $type,
                'dept'   => $deptName,
                'code'   => (string) $host['employee_code'],
                'name'   => (string) $host['employee_name'],
                'count'  => $count,
                'names'  => $names,
                'meals'  => $picked,
                'date'   => $mealDate,
            ];
        }
    }

    if ($error === '' && !empty($_SESSION['canteen_done'])) {
        $conn->close();
        header('Location: ' . app_url('canteen/order.php?done=1'));
        exit;
    }
}

$done = null;
if (isset($_GET['done']) && !empty($_SESSION['canteen_done'])) {
    $done = $_SESSION['canteen_done'];
    unset($_SESSION['canteen_done']);
}

$departments = canteenDepartments($conn);
$conn->close();

$companyName = function_exists('getCompanyName') ? getCompanyName() : 'Armor Fire';
$logoPath = function_exists('getLoginLogo') ? getLoginLogo() : '';
$logoSrc = $logoPath;
if ($logoSrc && strpos($logoSrc, 'http') !== 0 && strpos($logoSrc, '/') !== 0) {
    $logoSrc = app_url($logoSrc);
}
if (!$logoSrc) {
    $logoSrc = app_url('assets/images/logo-placeholder.svg');
}

$h = static function ($s) {
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
};
$postedDept = (int) ($_POST['department_id'] ?? 0);
$postedEmp = (int) ($_POST['employee_id'] ?? 0);
$postedType = ($_POST['booking_type'] ?? 'Guest') === 'Trainee' ? 'Trainee' : 'Guest';
$postedCount = max(1, min(CANTEEN_MAX_PERSONS, (int) ($_POST['person_count'] ?? 1)));
$postedNames = array_values((array) ($_POST['person_names'] ?? []));
$postedRemarks = (string) ($_POST['remarks'] ?? '');
$homeUrl = app_url('canteen/order.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Canteen Meal Booking · <?php echo $h($companyName); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css">
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0; min-height: 100vh; font-family: "Outfit", Segoe UI, Arial, sans-serif; color: #0f172a;
            background: radial-gradient(circle at top, #fff1f2 0%, #f8fafc 45%, #eef2f7 100%);
        }
        .wrap { max-width: 480px; margin: 0 auto; padding: 18px 14px 28px; }
        .card {
            background: #fff; border-radius: 20px; padding: 20px 18px 22px;
            box-shadow: 0 14px 40px rgba(15, 23, 42, .08); border: 1px solid #f1f5f9;
        }
        .brand { display: flex; align-items: center; gap: 12px; padding-bottom: 14px; border-bottom: 3px solid #d2232a; }
        .brand img { width: 56px; height: 56px; object-fit: contain; }
        .brand strong { display: block; font-size: 15px; font-weight: 800; color: #b91c1c; text-transform: uppercase; line-height: 1.2; }
        .brand span { display: block; font-size: 11px; font-weight: 700; color: #64748b; letter-spacing: .08em; margin-top: 3px; }
        h1 { margin: 16px 0 4px; font-size: 22px; font-weight: 800; display: flex; align-items: center; gap: 8px; }
        h1 i { color: #d2232a; }
        .back { display: inline-flex; align-items: center; gap: 6px; margin-top: 14px; font-size: 13px; font-weight: 700; color: #475569; text-decoration: none; }
        .back:hover { color: #d2232a; }
        .date-box {
            margin: 10px 0 16px; padding: 12px 14px; border-radius: 14px;
            background: linear-gradient(135deg, #d2232a, #9f1239); color: #fff;
        }
        .date-box small { display: block; font-size: 11px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; opacity: .85; }
        .date-box b { display: block; font-size: 18px; margin-top: 2px; }
        .date-box em { display: block; font-style: normal; font-size: 12px; margin-top: 6px; opacity: .95; }

        .choices { display: grid; gap: 12px; }
        .choice {
            display: flex; align-items: center; gap: 14px; padding: 16px; border-radius: 16px; text-decoration: none; color: #0f172a;
            border: 1.5px solid #e2e8f0; background: #fff; transition: border-color .15s, background .15s, transform .12s;
        }
        .choice:hover { border-color: #d2232a; background: #fff5f5; }
        .choice:active { transform: scale(.98); }
        .choice .ico { width: 54px; height: 54px; border-radius: 14px; display: grid; place-items: center; font-size: 23px; flex-shrink: 0; }
        .choice.emp .ico { background: #fee2e2; color: #d2232a; }
        .choice.vis .ico { background: #e0f2fe; color: #0369a1; }
        .choice strong { display: block; font-size: 16.5px; }
        .choice small { display: block; font-size: 12.5px; color: #64748b; margin-top: 2px; line-height: 1.35; }
        .choice .go { margin-left: auto; color: #cbd5e1; font-size: 16px; }

        .seg { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
        .seg label {
            display: flex; flex-direction: column; align-items: center; gap: 4px; padding: 12px 8px; border-radius: 14px; cursor: pointer;
            border: 1.5px solid #e2e8f0; background: #fff; text-align: center; user-select: none; transition: border-color .15s, background .15s;
        }
        .seg input { display: none; }
        .seg i { font-size: 20px; color: #64748b; }
        .seg strong { font-size: 15px; }
        .seg small { font-size: 11.5px; color: #64748b; }
        .seg label.on { border-color: #d2232a; background: #fff5f5; }
        .seg label.on i { color: #d2232a; }

        .field { margin-bottom: 16px; }
        .field > label { display: block; font-size: 13px; font-weight: 700; margin-bottom: 6px; color: #334155; }
        .field > label .step {
            display: inline-grid; place-items: center; width: 20px; height: 20px; border-radius: 50%;
            background: #d2232a; color: #fff; font-size: 11px; margin-right: 6px;
        }
        .fixed-dept { padding: 13px 14px; border-radius: 12px; background: #f1f5f9; font-weight: 700; font-size: 15px; color: #0f172a; border: 1.5px solid #e2e8f0; }
        .fixed-dept i { color: #d2232a; margin-right: 6px; }
        .input { width: 100%; height: 48px; padding: 0 14px; border-radius: 12px; border: 1.5px solid #e2e8f0; font: 500 15px "Outfit", sans-serif; color: #0f172a; background: #fff; }
        .input:focus { outline: none; border-color: #d2232a; }
        .counter { display: flex; align-items: center; gap: 10px; }
        .counter button { width: 48px; height: 48px; border-radius: 12px; border: 1.5px solid #e2e8f0; background: #fff; font-size: 18px; color: #0f172a; cursor: pointer; flex-shrink: 0; }
        .counter button:active { background: #fff5f5; border-color: #d2232a; }
        .counter .input { text-align: center; font-size: 20px; font-weight: 800; }
        .names { display: grid; gap: 8px; }
        .name-row { display: flex; align-items: center; gap: 8px; }
        .name-row span { width: 28px; height: 28px; border-radius: 50%; background: #f1f5f9; display: grid; place-items: center; font-size: 12px; font-weight: 800; color: #475569; flex-shrink: 0; }
        .name-row .input { height: 44px; }

        select { width: 100%; }
        .select2-container { width: 100% !important; }
        .select2-container--default .select2-selection--single {
            height: 48px; border: 1.5px solid #e2e8f0; border-radius: 12px; display: flex; align-items: center;
        }
        .select2-container--default .select2-selection--single .select2-selection__rendered { line-height: 46px; padding-left: 14px; font-size: 15px; color: #0f172a; }
        .select2-container--default .select2-selection--single .select2-selection__arrow { height: 46px; right: 8px; }
        .select2-container--default.select2-container--focus .select2-selection--single,
        .select2-container--default.select2-container--open .select2-selection--single { border-color: #d2232a; }
        .select2-search--dropdown .select2-search__field { height: 42px; border-radius: 10px; font-size: 16px; padding: 6px 10px; }
        .select2-results__option { padding: 10px 12px; font-size: 14px; }
        .select2-container--default .select2-results__option--highlighted[aria-selected] { background: #d2232a; }
        .select2-dropdown { border-radius: 12px; border-color: #e2e8f0; overflow: hidden; }
        .meals { display: grid; gap: 10px; }
        .meal {
            display: flex; align-items: center; gap: 12px; padding: 14px; border-radius: 14px;
            border: 1.5px solid #e2e8f0; background: #fff; cursor: pointer; user-select: none;
            transition: border-color .15s, background .15s;
        }
        .meal input { width: 22px; height: 22px; accent-color: #d2232a; flex-shrink: 0; }
        .meal .ico { width: 40px; height: 40px; border-radius: 12px; display: grid; place-items: center; font-size: 18px; flex-shrink: 0; }
        .meal strong { display: block; font-size: 15px; }
        .meal small { display: block; font-size: 12px; color: #64748b; }
        .meal.on { border-color: #d2232a; background: #fff5f5; }
        .meal.breakfast .ico { background: #fef3c7; color: #b45309; }
        .meal.lunch .ico { background: #dcfce7; color: #15803d; }
        .meal.dinner .ico { background: #e0e7ff; color: #4338ca; }
        .hint { font-size: 12px; color: #64748b; margin: 8px 2px 0; }
        .existing {
            display: none; margin-bottom: 12px; padding: 10px 12px; border-radius: 12px;
            background: #eff6ff; color: #1d4ed8; font-size: 13px; font-weight: 600;
        }
        .btn {
            width: 100%; border: 0; border-radius: 14px; padding: 15px; margin-top: 18px; cursor: pointer;
            font: 800 15px "Outfit", sans-serif; letter-spacing: .04em; text-transform: uppercase; color: #fff;
            background: #d2232a; box-shadow: 0 10px 24px rgba(210, 35, 42, .3);
            transition: background .2s, transform .12s;
        }
        .btn:hover { background: #a51c22; }
        .btn:active { transform: scale(.98); }
        .btn:disabled { background: #cbd5e1; box-shadow: none; cursor: not-allowed; }
        .alert { padding: 12px 14px; border-radius: 12px; font-size: 14px; font-weight: 600; margin-bottom: 14px; }
        .alert.err { background: #fee2e2; color: #b91c1c; }
        .closed { text-align: center; padding: 18px 6px 6px; }
        .closed i { font-size: 44px; color: #d2232a; }
        .closed h2 { margin: 12px 0 6px; font-size: 20px; }
        .closed p { margin: 0; color: #475569; font-size: 14px; line-height: 1.5; }
        .done { text-align: center; padding: 10px 4px 4px; }
        .done .tick {
            width: 72px; height: 72px; margin: 6px auto 12px; border-radius: 50%; display: grid; place-items: center;
            background: #dcfce7; color: #15803d; font-size: 34px;
        }
        .done h2 { margin: 0 0 6px; font-size: 21px; }
        .done .who { font-size: 15px; font-weight: 700; color: #0f172a; }
        .done .sub { font-size: 13px; color: #475569; margin-top: 3px; }
        .done .chips { display: flex; justify-content: center; flex-wrap: wrap; gap: 8px; margin: 12px 0 4px; }
        .done .chip { padding: 6px 12px; border-radius: 999px; background: #fff5f5; color: #b91c1c; font-weight: 700; font-size: 13px; border: 1px solid #fecaca; }
        .done .namelist { text-align: left; margin: 10px auto 0; padding: 10px 14px; border-radius: 12px; background: #f8fafc; font-size: 13.5px; max-width: 320px; }
        .done .namelist ol { margin: 4px 0 0; padding-left: 20px; }
        .done p { color: #475569; font-size: 13px; }
        .btn.secondary { background: #0f172a; box-shadow: none; text-decoration: none; display: block; text-align: center; }
        .foot { text-align: center; margin-top: 16px; font-size: 11px; color: #94a3b8; font-weight: 600; letter-spacing: .06em; text-transform: uppercase; }
    </style>
</head>
<body>
<div class="wrap">
    <div class="card">
        <div class="brand">
            <img src="<?php echo $h($logoSrc); ?>" alt="" onerror="this.src='<?php echo $h(app_url('assets/images/logo-placeholder.svg')); ?>'">
            <div>
                <strong><?php echo $h($companyName); ?></strong>
                <span>CANTEEN · MEAL BOOKING</span>
            </div>
        </div>

        <?php if ($done): ?>
            <div class="done">
                <div class="tick"><i class="fa-solid fa-check"></i></div>
                <?php if ($done['mode'] === 'visitor'): ?>
                    <h2><?php echo $h($done['type']); ?> Meal Booked!</h2>
                    <div class="who"><?php echo (int) $done['count']; ?> <?php echo $h(strtolower($done['type']) . ((int) $done['count'] === 1 ? '' : 's')); ?></div>
                    <div class="sub"><?php echo $h($done['dept']); ?> · <?php echo $h(($done['type'] === 'Trainee' ? 'Booked by ' : 'With ') . $done['code'] . ' — ' . $done['name']); ?></div>
                    <div class="chips">
                        <?php foreach ($done['meals'] as $m): ?>
                            <span class="chip"><?php echo $h($m); ?></span>
                        <?php endforeach; ?>
                    </div>
                    <div class="namelist"><strong>Names</strong>
                        <ol><?php foreach ($done['names'] as $n): ?><li><?php echo $h($n); ?></li><?php endforeach; ?></ol>
                    </div>
                    <p>For <strong><?php echo $h(canteenDateLabel($done['date'])); ?></strong>.<br>
                       To change or cancel, please contact HR.</p>
                <?php elseif ($done['result'] === 'cancelled'): ?>
                    <h2>Booking Cancelled</h2>
                    <div class="who"><?php echo $h($done['code'] . ' — ' . $done['name']); ?></div>
                    <p>No meal booked for <?php echo $h(canteenDateLabel($done['date'])); ?>.</p>
                <?php else: ?>
                    <h2>Meal Booked!</h2>
                    <div class="who"><?php echo $h($done['code'] . ' — ' . $done['name']); ?></div>
                    <div class="chips">
                        <?php foreach ($done['meals'] as $m): ?>
                            <span class="chip"><?php echo $h($m); ?></span>
                        <?php endforeach; ?>
                    </div>
                    <p>For <strong><?php echo $h(canteenDateLabel($done['date'])); ?></strong>.<br>
                       You can change it again till <?php echo $h($cutoffLabel); ?> today.</p>
                <?php endif; ?>
                <a href="<?php echo $h($homeUrl); ?>" class="btn secondary">New Entry</a>
            </div>

        <?php elseif (!$isOpen): ?>
            <div class="closed">
                <i class="fa-solid fa-clock"></i>
                <h2>Booking Closed</h2>
                <p>Meal booking for <strong><?php echo $h(canteenDateLabel($mealDate)); ?></strong>
                   closed at <strong><?php echo $h($cutoffLabel); ?></strong> today.<br>
                   Booking for the next day opens after 12:00 midnight.</p>
            </div>

        <?php elseif ($mode === ''): ?>
            <h1><i class="fa-solid fa-utensils"></i> Tomorrow's Meal List</h1>
            <div class="date-box">
                <small>Booking for</small>
                <b><?php echo $h(canteenDateLabel($mealDate)); ?></b>
                <em><i class="fa-regular fa-clock"></i> Last time to book: today <?php echo $h($cutoffLabel); ?></em>
            </div>
            <div class="choices">
                <a class="choice emp" href="<?php echo $h($homeUrl . '?mode=employee'); ?>">
                    <span class="ico"><i class="fa-solid fa-id-badge"></i></span>
                    <span><strong>Employee Booking</strong><small>Regular employee — select department &amp; your name</small></span>
                    <i class="fa-solid fa-chevron-right go"></i>
                </a>
                <a class="choice vis" href="<?php echo $h($homeUrl . '?mode=visitor'); ?>">
                    <span class="ico"><i class="fa-solid fa-user-group"></i></span>
                    <span><strong>Guest / Trainee Booking</strong><small>Guest with an employee, or trainees (HR)</small></span>
                    <i class="fa-solid fa-chevron-right go"></i>
                </a>
            </div>

        <?php else: ?>
            <a href="<?php echo $h($homeUrl); ?>" class="back"><i class="fa-solid fa-arrow-left"></i> Change booking type</a>
            <h1 style="margin-top:8px;">
                <?php if ($mode === 'employee'): ?><i class="fa-solid fa-id-badge"></i> Employee Booking
                <?php else: ?><i class="fa-solid fa-user-group"></i> Guest / Trainee<?php endif; ?>
            </h1>
            <div class="date-box">
                <small>Tomorrow's meal list</small>
                <b><?php echo $h(canteenDateLabel($mealDate)); ?></b>
                <em><i class="fa-regular fa-clock"></i> Last time to book: today <?php echo $h($cutoffLabel); ?></em>
            </div>

            <?php if ($error !== ''): ?>
                <div class="alert err"><i class="fa-solid fa-triangle-exclamation"></i> <?php echo $h($error); ?></div>
            <?php endif; ?>

            <form method="POST" id="canteenForm" autocomplete="off">
                <input type="hidden" name="token" value="<?php echo $h($_SESSION['canteen_token']); ?>">
                <input type="hidden" name="meal_date" value="<?php echo $h($mealDate); ?>">
                <input type="hidden" name="mode" value="<?php echo $h($mode); ?>">

                <?php $step = 1; ?>
                <?php if ($mode === 'visitor'): ?>
                <div class="field">
                    <label><span class="step"><?php echo $step++; ?></span>Booking For</label>
                    <div class="seg">
                        <label class="<?php echo $postedType === 'Guest' ? 'on' : ''; ?>">
                            <input type="radio" name="booking_type" value="Guest" <?php echo $postedType === 'Guest' ? 'checked' : ''; ?>>
                            <i class="fa-solid fa-user-tie"></i><strong>Guest</strong><small>Visitor with an employee</small>
                        </label>
                        <label class="<?php echo $postedType === 'Trainee' ? 'on' : ''; ?>">
                            <input type="radio" name="booking_type" value="Trainee" <?php echo $postedType === 'Trainee' ? 'checked' : ''; ?>>
                            <i class="fa-solid fa-user-graduate"></i><strong>Trainee</strong><small>Booked by HR</small>
                        </label>
                    </div>
                </div>
                <?php endif; ?>

                <div class="field" id="deptField">
                    <label for="deptSel"><span class="step"><?php echo $step; ?></span><span data-lbl-dept>Department</span></label>
                    <select name="department_id" id="deptSel">
                        <option value="">Search / select department</option>
                        <?php foreach ($departments as $d): ?>
                            <option value="<?php echo (int) $d['id']; ?>" <?php echo $postedDept === (int) $d['id'] ? 'selected' : ''; ?>>
                                <?php echo $h($d['department_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php if ($mode === 'visitor' && $hrDept): ?>
                <div class="field" id="hrField" style="display:none;">
                    <label><span class="step"><?php echo $step; ?></span>Department</label>
                    <div class="fixed-dept"><i class="fa-solid fa-building-user"></i><?php echo $h($hrDept['department_name']); ?></div>
                </div>
                <?php endif; ?>
                <?php $step++; ?>

                <div class="field" id="empField" style="display:none;">
                    <label for="empSel"><span class="step"><?php echo $step++; ?></span><span data-lbl-emp>Employee</span></label>
                    <select name="employee_id" id="empSel">
                        <option value="">Search by code / name</option>
                    </select>
                </div>

                <div id="mealField" style="display:none;">
                    <?php if ($mode === 'visitor'): ?>
                    <div class="field">
                        <label for="personCount"><span class="step"><?php echo $step++; ?></span>How many <span data-lbl-who>guests</span>?</label>
                        <div class="counter">
                            <button type="button" data-step="-1" aria-label="Less"><i class="fa-solid fa-minus"></i></button>
                            <input type="number" class="input" id="personCount" name="person_count" min="1" max="<?php echo CANTEEN_MAX_PERSONS; ?>" value="<?php echo (int) $postedCount; ?>" inputmode="numeric">
                            <button type="button" data-step="1" aria-label="More"><i class="fa-solid fa-plus"></i></button>
                        </div>
                    </div>
                    <div class="field">
                        <label><span class="step"><?php echo $step++; ?></span>Name of each <span data-lbl-who1>guest</span></label>
                        <div class="names" id="nameList"></div>
                    </div>
                    <div class="field">
                        <label for="remarks"><span data-lbl-remark>Guest company / purpose</span> <small style="color:#94a3b8;font-weight:600;">(optional)</small></label>
                        <input type="text" class="input" id="remarks" name="remarks" maxlength="255" value="<?php echo $h($postedRemarks); ?>">
                    </div>
                    <?php else: ?>
                    <div class="existing" id="existingNote"><i class="fa-solid fa-circle-info"></i> Already booked — you can change it below.</div>
                    <?php endif; ?>

                    <div class="field" style="margin-bottom:0;">
                        <label><span class="step"><?php echo $step++; ?></span>Select Meals</label>
                        <div class="meals">
                            <label class="meal breakfast">
                                <input type="checkbox" name="breakfast" value="1" <?php echo !empty($_POST['breakfast']) ? 'checked' : ''; ?>>
                                <span class="ico"><i class="fa-solid fa-mug-hot"></i></span>
                                <span><strong>Breakfast</strong><small>Morning nasto</small></span>
                            </label>
                            <label class="meal lunch">
                                <input type="checkbox" name="lunch" value="1" <?php echo !empty($_POST['lunch']) ? 'checked' : ''; ?>>
                                <span class="ico"><i class="fa-solid fa-bowl-rice"></i></span>
                                <span><strong>Lunch</strong><small>Afternoon jaman</small></span>
                            </label>
                            <label class="meal dinner">
                                <input type="checkbox" name="dinner" value="1" <?php echo !empty($_POST['dinner']) ? 'checked' : ''; ?>>
                                <span class="ico"><i class="fa-solid fa-moon"></i></span>
                                <span><strong>Dinner</strong><small>Night jaman</small></span>
                            </label>
                        </div>
                        <?php if ($mode === 'employee'): ?>
                        <p class="hint">Tick what you need. Untick all and submit to cancel an existing booking.</p>
                        <?php else: ?>
                        <p class="hint">Meals are booked for every person in this entry.</p>
                        <?php endif; ?>
                    </div>
                    <button type="submit" class="btn" id="btnSubmit"><i class="fa-solid fa-paper-plane"></i> Submit</button>
                </div>
            </form>
        <?php endif; ?>
    </div>
    <div class="foot">Designed &amp; Developed by Ocean Infotech</div>
</div>

<?php if (!$done && $isOpen && $mode !== ''): ?>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js"></script>
<script>
(function ($) {
    var jsonUrl = <?php echo json_encode(app_url('canteen/employees_json.php')); ?>;
    var mode = <?php echo json_encode($mode); ?>;
    var hrDeptId = <?php echo (int) ($hrDept['id'] ?? 0); ?>;
    var maxPersons = <?php echo CANTEEN_MAX_PERSONS; ?>;
    var preEmp = <?php echo (int) $postedEmp; ?>;
    var preNames = <?php echo json_encode(array_map('strval', $postedNames)); ?>;
    var employees = {};
    var $dept = $('#deptSel'), $emp = $('#empSel');

    $dept.select2({ placeholder: 'Search / select department', width: '100%' });
    $emp.select2({ placeholder: 'Search by code / name', width: '100%' });

    function bookingType() {
        return mode === 'visitor' ? ($('input[name=booking_type]:checked').val() || 'Guest') : 'Employee';
    }

    function syncMealCards() {
        $('.meal').each(function () {
            $(this).toggleClass('on', $(this).find('input').is(':checked'));
        });
    }

    function renderNames() {
        var $list = $('#nameList');
        if (!$list.length) return;
        var n = parseInt($('#personCount').val(), 10) || 1;
        n = Math.max(1, Math.min(maxPersons, n));
        var who = bookingType() === 'Trainee' ? 'Trainee' : 'Guest';
        var current = $list.find('input').map(function () { return this.value; }).get();
        if (!current.length && preNames.length) { current = preNames; }
        $list.empty();
        for (var i = 0; i < n; i++) {
            var $row = $('<div class="name-row"><span>' + (i + 1) + '</span></div>');
            $('<input type="text" class="input" name="person_names[]" maxlength="80" required>')
                .attr('placeholder', who + ' ' + (i + 1) + ' name')
                .val(current[i] || '')
                .appendTo($row);
            $list.append($row);
        }
    }

    function applyEmployee() {
        var id = $emp.val();
        var e = id ? employees[id] : null;
        if (mode === 'employee') {
            $('.meal input').prop('checked', false);
            $('#existingNote').hide();
        }
        if (!e) {
            $('#mealField').hide();
            return;
        }
        if (mode === 'employee' && e.order) {
            $('input[name=breakfast]').prop('checked', e.order.breakfast === 1);
            $('input[name=lunch]').prop('checked', e.order.lunch === 1);
            $('input[name=dinner]').prop('checked', e.order.dinner === 1);
            $('#existingNote').show();
        }
        syncMealCards();
        $('#mealField').show();
    }

    function loadEmployees(deptId) {
        employees = {};
        $emp.empty().append(new Option('Search by code / name', '', true, true)).trigger('change');
        $('#mealField').hide();
        if (!deptId) {
            $('#empField').hide();
            return;
        }
        $('#empField').show();
        $emp.prop('disabled', true);
        $.getJSON(jsonUrl, { department_id: deptId }).done(function (res) {
            if (!res || !res.ok) {
                return;
            }
            res.employees.forEach(function (e) {
                employees[e.id] = e;
                var label = e.code + ' — ' + e.name + (mode === 'employee' && e.order ? '  ✓' : '');
                $emp.append(new Option(label, e.id, false, e.id === preEmp));
            });
            $emp.prop('disabled', false).trigger('change');
            if (preEmp) {
                preEmp = 0;
                applyEmployee();
            } else {
                $emp.select2('open');
            }
        }).fail(function () {
            alert('Could not load employees. Please check internet and try again.');
            $emp.prop('disabled', false);
        });
    }

    function applyType(first) {
        if (mode !== 'visitor') return;
        var t = bookingType();
        var trainee = t === 'Trainee';
        $('.seg label').each(function () { $(this).toggleClass('on', $(this).find('input').is(':checked')); });
        $('[data-lbl-dept]').text(trainee ? 'Department' : 'Guest of which department?');
        $('[data-lbl-emp]').text(trainee ? 'Booked by (HR employee)' : 'Guest is with which employee?');
        $('[data-lbl-who]').text(trainee ? 'trainees' : 'guests');
        $('[data-lbl-who1]').text(trainee ? 'trainee' : 'guest');
        $('[data-lbl-remark]').text(trainee ? 'Training batch / remark' : 'Guest company / purpose');
        renderNames();

        if (trainee && hrDeptId) {
            $('#deptField').hide();
            $('#hrField').show();
            if (String($dept.val()) !== String(hrDeptId) || !first) {
                $dept.val(String(hrDeptId)).trigger('change.select2');
                loadEmployees(hrDeptId);
            } else {
                loadEmployees(hrDeptId);
            }
        } else {
            $('#hrField').hide();
            $('#deptField').show();
            if (!first) {
                $dept.val('').trigger('change.select2');
                loadEmployees('');
            } else if ($dept.val()) {
                loadEmployees($dept.val());
            }
        }
    }

    $dept.on('change', function () { loadEmployees($(this).val()); });
    $emp.on('select2:select', applyEmployee);
    $(document).on('change', '.meal input', syncMealCards);
    $('input[name=booking_type]').on('change', function () { applyType(false); });
    $('[data-step]').on('click', function () {
        var $c = $('#personCount');
        var n = (parseInt($c.val(), 10) || 1) + parseInt($(this).data('step'), 10);
        $c.val(Math.max(1, Math.min(maxPersons, n)));
        renderNames();
    });
    $('#personCount').on('input change', renderNames);

    $('#canteenForm').on('submit', function (e) {
        if (!$emp.val()) {
            e.preventDefault();
            alert(bookingType() === 'Trainee' ? 'Please select the HR employee.' : 'Please select the employee.');
            return;
        }
        if (mode === 'visitor' && !$('.meal input:checked').length) {
            e.preventDefault();
            alert('Please tick at least one meal.');
            return;
        }
        $('#btnSubmit').prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin"></i> Saving…');
    });

    if (mode === 'visitor') {
        applyType(true);
    } else if ($dept.val()) {
        loadEmployees($dept.val());
    }
})(jQuery);
</script>
<?php endif; ?>
</body>
</html>
