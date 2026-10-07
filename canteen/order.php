<?php
/**
 * Canteen meal booking — public mobile page opened from the Canteen QR.
 * Employee picks department → employee → Breakfast / Lunch / Dinner for tomorrow.
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

$conn = getDBConnection();
ensureCanteenTables($conn);

$mealDate = canteenTargetDate();
$isOpen = canteenIsOpen($mealDate);
$cutoffLabel = canteenCutoffLabel();
$meals = canteenMeals();

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postDate = (string) ($_POST['meal_date'] ?? '');
    $deptId = (int) ($_POST['department_id'] ?? 0);
    $empId = (int) ($_POST['employee_id'] ?? 0);
    $emp = $empId > 0 ? canteenActiveEmployee($conn, $empId) : null;

    if (!hash_equals((string) $_SESSION['canteen_token'], (string) ($_POST['token'] ?? ''))) {
        $error = 'Session expired. Please reload the page and try again.';
    } elseif ($postDate !== $mealDate || !$isOpen) {
        $error = 'Booking for ' . canteenDateLabel($postDate !== '' ? $postDate : $mealDate) . ' is closed (last time ' . $cutoffLabel . ').';
    } elseif (!$emp || (int) $emp['department_id'] !== $deptId) {
        $error = 'Please select a valid department and employee.';
    } else {
        $result = canteenSaveOrder(
            $conn,
            $mealDate,
            $emp,
            !empty($_POST['breakfast']),
            !empty($_POST['lunch']),
            !empty($_POST['dinner'])
        );
        if ($result === 'empty') {
            $error = 'Please tick at least one meal (Breakfast / Lunch / Dinner).';
        } else {
            $picked = [];
            foreach ($meals as $k => $label) {
                if (!empty($_POST[$k])) {
                    $picked[] = $label;
                }
            }
            $_SESSION['canteen_done'] = [
                'result' => $result,
                'code'   => (string) $emp['employee_code'],
                'name'   => (string) $emp['employee_name'],
                'meals'  => $picked,
                'date'   => $mealDate,
            ];
            $conn->close();
            header('Location: ' . app_url('canteen/order.php?done=1'));
            exit;
        }
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
        .date-box {
            margin: 10px 0 16px; padding: 12px 14px; border-radius: 14px;
            background: linear-gradient(135deg, #d2232a, #9f1239); color: #fff;
        }
        .date-box small { display: block; font-size: 11px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; opacity: .85; }
        .date-box b { display: block; font-size: 18px; margin-top: 2px; }
        .date-box em { display: block; font-style: normal; font-size: 12px; margin-top: 6px; opacity: .95; }
        .field { margin-bottom: 16px; }
        .field > label { display: block; font-size: 13px; font-weight: 700; margin-bottom: 6px; color: #334155; }
        .field > label .step {
            display: inline-grid; place-items: center; width: 20px; height: 20px; border-radius: 50%;
            background: #d2232a; color: #fff; font-size: 11px; margin-right: 6px;
        }
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
        .done .chips { display: flex; justify-content: center; flex-wrap: wrap; gap: 8px; margin: 12px 0 4px; }
        .done .chip { padding: 6px 12px; border-radius: 999px; background: #fff5f5; color: #b91c1c; font-weight: 700; font-size: 13px; border: 1px solid #fecaca; }
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
                <?php if ($done['result'] === 'cancelled'): ?>
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
                <a href="<?php echo $h(app_url('canteen/order.php')); ?>" class="btn secondary">New Entry</a>
            </div>

        <?php elseif (!$isOpen): ?>
            <div class="closed">
                <i class="fa-solid fa-clock"></i>
                <h2>Booking Closed</h2>
                <p>Meal booking for <strong><?php echo $h(canteenDateLabel($mealDate)); ?></strong>
                   closed at <strong><?php echo $h($cutoffLabel); ?></strong> today.<br>
                   Booking for the next day opens after 12:00 midnight.</p>
            </div>

        <?php else: ?>
            <h1><i class="fa-solid fa-utensils"></i> Tomorrow's Meal List</h1>
            <div class="date-box">
                <small>Booking for</small>
                <b><?php echo $h(canteenDateLabel($mealDate)); ?></b>
                <em><i class="fa-regular fa-clock"></i> Last time to book: today <?php echo $h($cutoffLabel); ?></em>
            </div>

            <?php if ($error !== ''): ?>
                <div class="alert err"><i class="fa-solid fa-triangle-exclamation"></i> <?php echo $h($error); ?></div>
            <?php endif; ?>

            <form method="POST" id="canteenForm" autocomplete="off">
                <input type="hidden" name="token" value="<?php echo $h($_SESSION['canteen_token']); ?>">
                <input type="hidden" name="meal_date" value="<?php echo $h($mealDate); ?>">

                <div class="field">
                    <label for="deptSel"><span class="step">1</span>Department</label>
                    <select name="department_id" id="deptSel" required>
                        <option value="">Search / select department</option>
                        <?php foreach ($departments as $d): ?>
                            <option value="<?php echo (int) $d['id']; ?>" <?php echo $postedDept === (int) $d['id'] ? 'selected' : ''; ?>>
                                <?php echo $h($d['department_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field" id="empField" style="display:none;">
                    <label for="empSel"><span class="step">2</span>Employee</label>
                    <select name="employee_id" id="empSel" required>
                        <option value="">Search by code / name</option>
                    </select>
                </div>

                <div id="mealField" style="display:none;">
                    <div class="existing" id="existingNote"><i class="fa-solid fa-circle-info"></i> Already booked — you can change it below.</div>
                    <div class="field" style="margin-bottom:0;">
                        <label><span class="step">3</span>Select Meals</label>
                        <div class="meals">
                            <label class="meal breakfast">
                                <input type="checkbox" name="breakfast" value="1">
                                <span class="ico"><i class="fa-solid fa-mug-hot"></i></span>
                                <span><strong>Breakfast</strong><small>Morning nasto</small></span>
                            </label>
                            <label class="meal lunch">
                                <input type="checkbox" name="lunch" value="1">
                                <span class="ico"><i class="fa-solid fa-bowl-rice"></i></span>
                                <span><strong>Lunch</strong><small>Afternoon jaman</small></span>
                            </label>
                            <label class="meal dinner">
                                <input type="checkbox" name="dinner" value="1">
                                <span class="ico"><i class="fa-solid fa-moon"></i></span>
                                <span><strong>Dinner</strong><small>Night jaman</small></span>
                            </label>
                        </div>
                        <p class="hint">Tick what you need. Untick all and submit to cancel an existing booking.</p>
                    </div>
                    <button type="submit" class="btn" id="btnSubmit"><i class="fa-solid fa-paper-plane"></i> Submit</button>
                </div>
            </form>
        <?php endif; ?>
    </div>
    <div class="foot">Designed &amp; Developed by Ocean Infotech</div>
</div>

<?php if (!$done && $isOpen): ?>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js"></script>
<script>
(function ($) {
    var jsonUrl = <?php echo json_encode(app_url('canteen/employees_json.php')); ?>;
    var preEmp = <?php echo (int) $postedEmp; ?>;
    var employees = {};
    var $dept = $('#deptSel'), $emp = $('#empSel');

    $dept.select2({ placeholder: 'Search / select department', width: '100%' });
    $emp.select2({ placeholder: 'Search by code / name', width: '100%' });

    function syncMealCards() {
        $('.meal').each(function () {
            $(this).toggleClass('on', $(this).find('input').is(':checked'));
        });
    }

    function applyEmployee() {
        var id = $emp.val();
        var e = id ? employees[id] : null;
        $('.meal input').prop('checked', false);
        $('#existingNote').hide();
        if (!e) {
            $('#mealField').hide();
            return;
        }
        if (e.order) {
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
                var label = e.code + ' — ' + e.name + (e.order ? '  ✓' : '');
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

    $dept.on('change', function () { loadEmployees($(this).val()); });
    $emp.on('select2:select', applyEmployee);
    $(document).on('change', '.meal input', syncMealCards);

    $('#canteenForm').on('submit', function () {
        $('#btnSubmit').prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin"></i> Saving…');
    });

    if ($dept.val()) {
        loadEmployees($dept.val());
    }
})(jQuery);
</script>
<?php endif; ?>
</body>
</html>
