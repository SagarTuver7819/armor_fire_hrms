<?php
/**
 * Offline unit matrix: Week Off sandwich + benefits → paid WO / total_pay_days
 * (no DB). Same engine as Attendance Report + Salary Register.
 */
error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/../includes/attendance_helper.php';

$month = 10;
$year = 2026;
$holidaySet = [];

function makeEmp($woBen, $holBen = 'No')
{
    return [
        'id' => 0, // offline: skip week-off history DB lookup
        'week_off_day' => 'Sunday',
        'week_off_benefits' => $woBen,
        'holiday_benefits' => $holBen,
        'date_of_joining' => '2020-01-01',
        'date_of_exit' => null,
        'department_id' => 1,
    ];
}

function day($status, $extra = [])
{
    return array_merge([
        'day_status' => $status,
        'remarks' => '',
        'working_minutes' => 0,
        'punch_in' => null,
        'punch_out' => null,
        'source' => 'manual',
    ], $extra);
}

/**
 * Build Oct 2026 map: Sundays as Week Off; fill working days with $fill.
 * Override specific dates via $overrides [Y-m-d => cell].
 */
function buildDayMap($fillStatus, array $overrides = [])
{
    $map = [];
    $start = strtotime('2026-10-01');
    $end = strtotime('2026-10-31');
    for ($ts = $start; $ts <= $end; $ts = strtotime('+1 day', $ts)) {
        $d = date('Y-m-d', $ts);
        if (date('w', $ts) == 0) {
            $map[$d] = day('Week Off');
        } else {
            $map[$d] = day($fillStatus, $fillStatus === 'Leave' ? ['remarks' => 'PL'] : []);
        }
    }
    foreach ($overrides as $d => $cell) {
        $map[$d] = $cell;
    }
    return $map;
}

$cases = [];
$fail = 0;

// 1) Benefits No → paid WO = 0 even if Present beside
$map = buildDayMap('Present');
$t = attendanceBuildEmployeeMonthTotals(makeEmp('No'), $map, $month, $year, $holidaySet);
$cases[] = ['Benefits No → WOpay=0', (float) $t['week_off_paid'] === 0.0 && (float) $t['week_off'] === 0.0, $t];

// 2) Benefits Yes + Present all sides → all Sundays paid (4 in Oct 2026: 4,11,18,25)
$map = buildDayMap('Present');
$t = attendanceBuildEmployeeMonthTotals(makeEmp('Yes'), $map, $month, $year, $holidaySet);
$cases[] = ['Benefits Yes + Present beside → WO=4', (float) $t['week_off_paid'] === 4.0 && (float) $t['week_off'] === 4.0, $t];

// 3) Benefits Yes + Leave both sides of one Sunday (Oct 11: Fri 9 + Sat 10 leave, Mon 12 leave)
$map = buildDayMap('Present', [
    '2026-10-10' => day('Leave', ['remarks' => 'PL']),
    '2026-10-12' => day('Leave', ['remarks' => 'PL']),
]);
$t = attendanceBuildEmployeeMonthTotals(makeEmp('Yes'), $map, $month, $year, $holidaySet);
$cases[] = ['Leave both sides of Oct11 → WO=3', (float) $t['week_off_paid'] === 3.0, $t];

// 4) Benefits Yes + only one side Present (before leave, after present) → keep WO
$map = buildDayMap('Present', [
    '2026-10-10' => day('Leave', ['remarks' => 'PL']),
]);
$t = attendanceBuildEmployeeMonthTotals(makeEmp('Yes'), $map, $month, $year, $holidaySet);
$cases[] = ['One side Present → keep WO=4', (float) $t['week_off_paid'] === 4.0, $t];

// 5) No attendance beside (blank working days) → WO unpaid
$map = [];
$start = strtotime('2026-10-01');
$end = strtotime('2026-10-31');
for ($ts = $start; $ts <= $end; $ts = strtotime('+1 day', $ts)) {
    $d = date('Y-m-d', $ts);
    if (date('w', $ts) == 0) {
        $map[$d] = day('Week Off');
    }
}
$t = attendanceBuildEmployeeMonthTotals(makeEmp('Yes'), $map, $month, $year, $holidaySet);
$cases[] = ['No att beside → WOpay=0', (float) $t['week_off_paid'] === 0.0, $t];

// 6) total_days === total_pay_days always
$map = buildDayMap('Present');
$t = attendanceBuildEmployeeMonthTotals(makeEmp('Yes'), $map, $month, $year, $holidaySet);
$cases[] = [
    'total_days === total_pay_days (Yes)',
    abs((float) $t['total_days'] - (float) $t['total_pay_days']) < 0.001,
    $t,
];
$t2 = attendanceBuildEmployeeMonthTotals(makeEmp('No'), $map, $month, $year, $holidaySet);
$cases[] = [
    'total_days === total_pay_days (No)',
    abs((float) $t2['total_days'] - (float) $t2['total_pay_days']) < 0.001
        && (float) $t2['week_off'] === 0.0,
    $t2,
];

// 7) Pay days = P + WOpay + leaves (Benefits Yes full present)
$expected = (float) $t['present'] + 4.0; // no leaves
$cases[] = [
    'pay = present + paid WO',
    abs((float) $t['total_pay_days'] - $expected) < 0.001,
    $t,
];

// 8) Bogus Week Off marks on non-Sunday must NOT inflate WO (was 6 bug)
$map = buildDayMap('Present', [
    '2026-10-01' => day('Week Off'), // Thursday — no punch
    '2026-10-02' => day('Week Off'), // Friday — no punch
]);
$t = attendanceBuildEmployeeMonthTotals(makeEmp('Yes'), $map, $month, $year, $holidaySet);
$cases[] = [
    'Bogus WO no-punch → not in WOpay (still 4 Sundays)',
    (float) $t['week_off_paid'] === 4.0 && (float) $t['week_off_raw'] === 4.0,
    $t,
];

// 9) Bogus WO WITH punches on working days → count Present (Arjun-style lost days)
$map = buildDayMap('Present', [
    '2026-10-01' => day('Week Off', [
        'punch_in' => '09:00:00',
        'punch_out' => '18:00:00',
        'working_minutes' => 480,
    ]),
    '2026-10-02' => day('Week Off', [
        'punch_in' => '09:00:00',
        'punch_out' => '18:00:00',
        'working_minutes' => 480,
    ]),
]);
$t = attendanceBuildEmployeeMonthTotals(makeEmp('Yes'), $map, $month, $year, $holidaySet);
// Full Oct Present all non-Sun + 4 Sun WO = 27 + 4 = 31 (Thu+Fri still present via reclassify)
$cases[] = [
    'Bogus WO with punch → Present, pay=31',
    (float) $t['week_off_paid'] === 4.0
        && (float) $t['present'] === 27.0
        && abs((float) $t['total_pay_days'] - 31.0) < 0.001,
    $t,
];

echo "=== Week Off / Pay Days unit matrix ===\n";
foreach ($cases as $c) {
    $ok = !empty($c[1]);
    if (!$ok) {
        $fail++;
    }
    echo ($ok ? 'PASS' : 'FAIL') . ' | ' . $c[0] . "\n";
    if (!$ok) {
        $x = $c[2];
        echo sprintf(
            "      WO=%.2f WOpay=%.2f WOraw=%.2f P=%.2f pay=%.2f days=%.2f sandwich=%.0f\n",
            (float) ($x['week_off'] ?? -1),
            (float) ($x['week_off_paid'] ?? -1),
            (float) ($x['week_off_raw'] ?? -1),
            (float) ($x['present'] ?? -1),
            (float) ($x['total_pay_days'] ?? -1),
            (float) ($x['total_days'] ?? -1),
            (float) ($x['sandwich_wo_removed'] ?? 0)
        );
    }
}

echo "\n=== RESULT: " . ($fail === 0 ? 'ALL PASS' : "{$fail} FAILED") . " ===\n";
exit($fail === 0 ? 0 : 1);
