<?php
/**
 * CNC clone register rows and NEFT lines.
 * Amounts stay at 2 decimals (no payroll rupee round-off).
 */

function cncCloneNum($n, $decimals = 2)
{
    return formatIndianNumber((float) $n, (int) $decimals);
}

function cncCloneDate($value)
{
    $formatted = formatDateDisplay($value);
    return $formatted !== '' ? $formatted : '-';
}

function cncCloneNfpsSettings()
{
    return [
        'pymt_prod_type_code' => 'PAB_VENDOR',
        'pymt_mode' => 'NEFT',
        'debit_acc_no' => '239451000033',
        'email_id' => 'accounts@armorfire.co.in',
    ];
}

function cncCloneBeneName($name)
{
    $name = strtoupper(trim((string) $name));
    $name = preg_replace('/[^A-Z0-9 ]+/', ' ', $name);
    $name = preg_replace('/\s+/', ' ', (string) $name);
    return substr(trim((string) $name), 0, 500);
}

/**
 * @return array{before_nov:bool,main_id:int,main_label:string,rows:array<int,array>}
 */
function cncCloneRegisterBundle($month, $year)
{
    $month = (int) $month;
    $year = (int) $year;
    $beforeNov = ($year < 2026) || ($year === 2026 && $month < 11);
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
    if ($beforeNov || $mainId <= 0) {
        return [
            'before_nov' => $beforeNov,
            'main_id' => $mainId,
            'main_label' => $mainLabel,
            'rows' => $rows,
        ];
    }

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
        $sql = "SELECT employee_code, designation, date_of_joining, uan_number, bank_account_number, ifsc_code, mobile_number
                FROM employees WHERE UPPER(TRIM(employee_code)) IN ($ph)";
        $st = $conn->prepare($sql);
        if (!$st) {
            $sql = "SELECT employee_code, designation, date_of_joining, uan_number, bank_account_number, ifsc_code
                    FROM employees WHERE UPPER(TRIM(employee_code)) IN ($ph)";
            $st = $conn->prepare($sql);
        }
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
        $pt = $final >= 12001 ? 200.0 : 0.0;
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
            'pt' => $pt,
            'deduction' => $pt,
            'net' => round($final - $pt, 2),
            'account' => trim((string) ($ex['bank_account_number'] ?? '')),
            'ifsc' => trim((string) ($ex['ifsc_code'] ?? '')),
            'mobile' => trim((string) ($ex['mobile_number'] ?? '')),
            'is_main' => $isMain,
        ];
    }

    return [
        'before_nov' => false,
        'main_id' => $mainId,
        'main_label' => $mainLabel,
        'rows' => $rows,
    ];
}

function cncCloneNeftRows(array $registerRows)
{
    $neft = [];
    $sr = 0;
    foreach ($registerRows as $r) {
        $amount = round((float) ($r['net'] ?? 0), 2);
        if ($amount <= 0) {
            continue;
        }
        $account = trim((string) ($r['account'] ?? ''));
        if ($account === '-' ) {
            $account = '';
        }
        $sr++;
        $neft[] = [
            'sr' => $sr,
            'employee_code' => (string) ($r['code'] ?? ''),
            'employee_name' => (string) ($r['name'] ?? ''),
            'department' => 'CNC',
            'account' => $account !== '' ? $account : '-',
            'ifsc' => trim((string) ($r['ifsc'] ?? '')),
            'amount' => $amount,
            'mobile' => (string) ($r['mobile'] ?? ''),
            'missing_bank' => ($account === ''),
            'register_kind' => !empty($r['is_main']) ? 'Jobwork' : 'Normal',
        ];
    }
    return $neft;
}

function cncCloneNeftSummary(array $neftRows)
{
    $normalCount = 0;
    $jobworkCount = 0;
    $normalTotal = 0.0;
    $jobworkTotal = 0.0;
    $missingBank = 0;
    foreach ($neftRows as $r) {
        $amt = round((float) ($r['amount'] ?? 0), 2);
        if (!empty($r['missing_bank'])) {
            $missingBank++;
        }
        if (($r['register_kind'] ?? '') === 'Jobwork') {
            $jobworkCount++;
            $jobworkTotal += $amt;
        } else {
            $normalCount++;
            $normalTotal += $amt;
        }
    }
    return [
        'normal_count' => $normalCount,
        'jobwork_count' => $jobworkCount,
        'normal_total' => round($normalTotal, 2),
        'jobwork_total' => round($jobworkTotal, 2),
        'final_total' => round($normalTotal + $jobworkTotal, 2),
        'missing_bank' => $missingBank,
    ];
}

function cncCloneNfpsExport(array $neftRows, $month, $year)
{
    $cfg = cncCloneNfpsSettings();
    $month = (int) $month;
    $year = (int) $year;
    $monthName = strtoupper(date('F', mktime(0, 0, 0, $month, 1, $year)));
    $creditNarr = substr('SALARY ' . $monthName . ' ' . $year, 0, 30);
    $payDate = date('d-m-Y');
    $headers = [
        'PYMT_PROD_TYPE_CODE',
        'PYMT_MODE',
        'DEBIT_ACC_NO',
        'BNF_NAME',
        'BENE_ACC_NO',
        'BENE_IFSC',
        'AMOUNT',
        'CREDIT_NARR',
        'PYMT_DATE',
        'MOBILE_NUM',
        'EMAIL_ID',
        'REMARK',
        'REF_NO',
    ];
    $rows = [];
    $total = 0.0;
    foreach ($neftRows as $r) {
        $amount = round((float) ($r['amount'] ?? 0), 2);
        if ($amount <= 0) {
            continue;
        }
        $account = preg_replace('/\s+/', '', (string) ($r['account'] ?? ''));
        $account = preg_replace('/[^0-9A-Za-z]/', '', (string) $account);
        if ($account === '-') {
            $account = '';
        }
        $ifsc = strtoupper(preg_replace('/\s+/', '', (string) ($r['ifsc'] ?? '')));
        if ($ifsc === '-') {
            $ifsc = '';
        }
        $mobile = preg_replace('/\D+/', '', (string) ($r['mobile'] ?? ''));
        if (strlen($mobile) > 10) {
            $mobile = substr($mobile, -10);
        }
        if (strlen($mobile) !== 10) {
            $mobile = '';
        }
        $remarkParts = ['CNC CLONE'];
        if ($account === '') {
            $remarkParts[] = 'MISSING BANK A/C';
        }
        $total += $amount;
        $rows[] = [
            $cfg['pymt_prod_type_code'],
            $cfg['pymt_mode'],
            $cfg['debit_acc_no'],
            cncCloneBeneName($r['employee_name'] ?? ''),
            $account,
            $ifsc,
            $amount,
            $creditNarr,
            $payDate,
            $mobile,
            $cfg['email_id'],
            implode(' · ', $remarkParts),
            trim((string) ($r['employee_code'] ?? '')),
        ];
    }
    return [
        'headers' => $headers,
        'rows' => $rows,
        'total' => round($total, 2),
        'pay_date' => $payDate,
        'credit_narr' => $creditNarr,
        'cfg' => $cfg,
    ];
}
