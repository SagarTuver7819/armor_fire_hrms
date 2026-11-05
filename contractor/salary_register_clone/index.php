<?php
/**
 * CNC Clone Salary Register.
 * Reads only the New Operation List split. Does not load or write the payroll salary register.
 */
require_once __DIR__ . '/../_bootstrap.php';
require_once __DIR__ . '/../../includes/contractor_split_helper.php';
require_once __DIR__ . '/../../includes/ops_clone_report.php';

function cncCloneNum($n, $decimals = 2)
{
    return formatIndianNumber((float) $n, (int) $decimals);
}

function cncCloneDate($value)
{
    $formatted = formatDateDisplay($value);
    return $formatted !== '' ? $formatted : '-';
}

$month = (int) ($_GET['month'] ?? date('n'));
$year = (int) ($_GET['year'] ?? date('Y'));
if ($month < 1 || $month > 12) {
    $month = (int) date('n');
}
if ($year < 2000 || $year > 2100) {
    $year = (int) date('Y');
}
$beforeNov = ($year < 2026) || ($year === 2026 && $month < 11);

$pageTitle = 'Clone Salary Register';
$useSidebar = true;
$sidebarMode = 'contractor';
$sidebarActive = 'contractor_salary_clone';
require_once __DIR__ . '/../../includes/header.php';

$main = null;
foreach (contractorAttendanceSplitRules() as $rule) {
    if (strcasecmp((string) ($rule['department_name'] ?? ''), 'CNC') === 0) {
        $main = resolveContractorAttendanceSplitMain($rule);
        break;
    }
}
$mainId = (int) ($main['id'] ?? 0);
$mainLabel = trim((string) ($main['employee_code'] ?? '') . ' — ' . (string) ($main['employee_name'] ?? ''));

$rows = [];
$report = null;
if (!$beforeNov && $mainId > 0) {
    $conn = getDBConnection();
    ensureContractorTables($conn);
    $report = opsCloneBuildCncReport($conn, $mainId, $month, $year, 0);
    $ids = [];
    foreach ($report['staff'] as $st) {
        $code = strtoupper(trim((string) ($st['code'] ?? '')));
        if ($code !== '') {
            $ids[$code] = $st;
        }
    }
    $extra = [];
    if ($ids) {
        $codes = array_keys($ids);
        $ph = implode(',', array_fill(0, count($codes), '?'));
        $types = str_repeat('s', count($codes));
        $sql = "SELECT employee_code, designation, date_of_joining, uan_number, bank_account_number, ifsc_code
                FROM employees WHERE UPPER(TRIM(employee_code)) IN ($ph)";
        $st = $conn->prepare($sql);
        if ($st) {
            $st->bind_param($types, ...$codes);
            $st->execute();
            $res = $st->get_result();
            while ($er = $res->fetch_assoc()) {
                $extra[strtoupper(trim((string) $er['employee_code']))] = $er;
            }
            $st->close();
        }
    }
    $conn->close();
    foreach ($report['staff'] as $st) {
        $code = strtoupper(trim((string) ($st['code'] ?? '')));
        $ex = $extra[$code] ?? [];
        $paid = (float) ($st['paid_days'] ?? 0);
        $final = round((float) ($st['final'] ?? 0), 2);
        $isMain = !empty($st['is_main']);
        $rows[] = [
            'code' => (string) ($st['code'] ?? ''),
            'name' => (string) ($st['name'] ?? ''),
            'designation' => trim((string) ($ex['designation'] ?? '')),
            'doj' => cncCloneDate($ex['date_of_joining'] ?? ''),
            'uan' => trim((string) ($ex['uan_number'] ?? '')),
            'salary' => $isMain ? $final : (float) ($st['salary'] ?? 0),
            'present' => $paid,
            'total_days' => $paid,
            'gross' => $final,
            'net' => $final,
            'account' => trim((string) ($ex['bank_account_number'] ?? '')),
            'ifsc' => trim((string) ($ex['ifsc_code'] ?? '')),
            'is_main' => $isMain,
        ];
    }
}

$sumSalary = 0.0;
$sumGross = 0.0;
foreach ($rows as $r) {
    $sumSalary += $r['salary'];
    $sumGross += $r['gross'];
}
$monthLabel = date('F Y', mktime(0, 0, 0, $month, 1, $year));
?>
<main class="dashboard-main">
    <div class="page-toolbar">
        <a href="<?php echo app_url('contractor/index.php'); ?>" class="back-link"><i class="fa-solid fa-arrow-left"></i> Back to Contractor Hub</a>
    </div>
    <div class="form-page-card">
        <div class="form-page-header">
            <div>
                <h1>Clone Salary Register</h1>
                <p>CNC · New Operation List only. Main Salary Register is not used.</p>
            </div>
        </div>
        <form method="GET" class="employee-form register-filters">
            <div class="form-grid form-grid-3">
                <div class="form-group">
                    <label>Department</label>
                    <input type="text" class="form-control" value="CNC" readonly>
                </div>
                <div class="form-group">
                    <label>Month</label>
                    <select name="month" class="form-control" onchange="this.form.submit()">
                        <?php for ($m = 1; $m <= 12; $m++): ?>
                            <option value="<?php echo $m; ?>" <?php echo $m === $month ? 'selected' : ''; ?>><?php echo date('F', mktime(0, 0, 0, $m, 1)); ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Year</label>
                    <input type="number" name="year" class="form-control" value="<?php echo (int) $year; ?>" onchange="this.form.submit()">
                </div>
            </div>
        </form>
        <?php if ($beforeNov): ?>
            <p class="form-hint">Clone Salary Register starts from November 2026.</p>
        <?php elseif ($mainId <= 0): ?>
            <p class="form-hint">CNC main contractor was not found.</p>
        <?php else: ?>
            <p class="form-hint">P.Days are Present marks from 01-11-2026 through today. Week-off counts only when it is P. Staff Gross and Net follow the New Operation List final total. Main contractor <?php echo htmlspecialchars($mainLabel); ?> is the last row: his Salary, Gross and Net are the remainder that is left after the staff split.</p>
        <?php endif; ?>
    </div>

    <?php if (!$beforeNov && $mainId > 0): ?>
    <div class="cnc-clone-sheet">
        <div class="cnc-clone-banner">
            <strong>Salaried Person (Fixed) · CNC</strong>
            <span>New Operation List · <?php echo htmlspecialchars($monthLabel); ?></span>
        </div>
        <div class="table-wrap">
            <table class="cnc-clone-table">
                <thead>
                    <tr class="cnc-clone-group">
                        <th colspan="5"></th>
                        <th colspan="8" class="cnc-clone-earn">Earning</th>
                        <th colspan="2" class="cnc-clone-gross">Gross</th>
                        <th colspan="4" class="cnc-clone-ded">Deduction</th>
                        <th colspan="3" class="cnc-clone-net">NET</th>
                        <th colspan="3"></th>
                    </tr>
                    <tr>
                        <th class="cnc-clone-sticky cnc-clone-sticky-1">Emp Code</th>
                        <th class="cnc-clone-sticky cnc-clone-sticky-2">Employee Name</th>
                        <th>Desig.</th>
                        <th>D.O.J.</th>
                        <th>UAN</th>
                        <th>Salary</th>
                        <th>P.Days</th>
                        <th>W.Off</th>
                        <th>Holiday</th>
                        <th>P.L.</th>
                        <th>S.L.</th>
                        <th>D.L.</th>
                        <th>C-Off</th>
                        <th>Total Days</th>
                        <th>Gross</th>
                        <th>Emp PF</th>
                        <th>P.T.</th>
                        <th>Loan</th>
                        <th>Adv.</th>
                        <th>Tot. Ded.</th>
                        <th>Arrears</th>
                        <th>Net</th>
                        <th>A/C No.</th>
                        <th>IFSC</th>
                        <th>Remarks</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$rows): ?>
                    <tr><td colspan="25" class="empty-cell">No CNC salary employees for this month.</td></tr>
                <?php endif; ?>
                <?php foreach ($rows as $r): ?>
                    <tr class="<?php echo !empty($r['is_main']) ? 'cnc-clone-main' : ''; ?>">
                        <td class="cnc-clone-sticky cnc-clone-sticky-1"><?php echo htmlspecialchars($r['code'] !== '' ? $r['code'] : '-'); ?></td>
                        <td class="cnc-clone-sticky cnc-clone-sticky-2">
                            <strong><?php echo htmlspecialchars($r['name']); ?></strong>
                            <?php if (!empty($r['is_main'])): ?>
                                <div class="cnc-clone-code">Main Contractor</div>
                            <?php elseif ($mainLabel !== ''): ?>
                                <div class="cnc-clone-code">Under: <?php echo htmlspecialchars($mainLabel); ?></div>
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($r['designation'] !== '' ? $r['designation'] : '-'); ?></td>
                        <td><?php echo htmlspecialchars($r['doj']); ?></td>
                        <td><?php echo htmlspecialchars($r['uan'] !== '' ? $r['uan'] : '-'); ?></td>
                        <td class="num"><?php echo cncCloneNum($r['salary'], !empty($r['is_main']) ? 2 : 0); ?></td>
                        <td class="num"><?php echo cncCloneNum($r['present'], 1); ?></td>
                        <td class="num">0.0</td>
                        <td class="num">0.0</td>
                        <td class="num">0.0</td>
                        <td class="num">0.0</td>
                        <td class="num">0.0</td>
                        <td class="num">0.0</td>
                        <td class="num"><?php echo cncCloneNum($r['total_days'], 1); ?></td>
                        <td class="num"><?php echo cncCloneNum($r['gross'], 2); ?></td>
                        <td class="num">0</td>
                        <td class="num">0</td>
                        <td class="num">0</td>
                        <td class="num">0</td>
                        <td class="num">0</td>
                        <td class="num">0</td>
                        <td class="num"><strong><?php echo cncCloneNum($r['net'], 2); ?></strong></td>
                        <td><?php echo htmlspecialchars($r['account'] !== '' ? $r['account'] : '-'); ?></td>
                        <td><?php echo htmlspecialchars($r['ifsc'] !== '' ? $r['ifsc'] : '-'); ?></td>
                        <td>-</td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                <?php if ($rows): ?>
                <tfoot>
                    <tr>
                        <td colspan="5"><strong>Department Total</strong></td>
                        <td class="num"><strong><?php echo cncCloneNum($sumSalary, 2); ?></strong></td>
                        <td colspan="8"></td>
                        <td class="num"><strong><?php echo cncCloneNum($sumGross, 2); ?></strong></td>
                        <td class="num">0</td>
                        <td class="num">0</td>
                        <td class="num">0</td>
                        <td class="num">0</td>
                        <td class="num">0</td>
                        <td class="num">0</td>
                        <td class="num"><strong><?php echo cncCloneNum($sumGross, 2); ?></strong></td>
                        <td colspan="3"></td>
                    </tr>
                </tfoot>
                <?php endif; ?>
            </table>
        </div>
    </div>
    <?php endif; ?>
</main>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
