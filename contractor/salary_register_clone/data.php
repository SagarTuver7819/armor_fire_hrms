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

/**
 * Copy the NFPS workbook and write payment rows under the original heading row.
 *
 * @param array<int,array<int,string|float>> $rows
 */
function cncCloneFillNfpsTemplate($templatePath, array $rows)
{
    $zip = new ZipArchive();
    $flags = defined('ZipArchive::RDONLY') ? ZipArchive::RDONLY : 0;
    if ($zip->open($templatePath, $flags) !== true) {
        return '';
    }
    $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
    $zip->close();
    if (!is_string($sheet) || !preg_match('/<row r="1"[^>]*>.*?<\/row>/s', $sheet, $headerMatch)) {
        return '';
    }

    $last = max(49, count($rows) + 1);
    $sheetData = '<sheetData>' . $headerMatch[0];
    $dataIndex = 0;
    for ($r = 2; $r <= $last; $r++) {
        if ($dataIndex < count($rows)) {
            $sheetData .= cncCloneNfpsDataRow($r, $rows[$dataIndex]);
            $dataIndex++;
        } else {
            $sheetData .= cncCloneNfpsBlankRow($r);
        }
    }
    $sheetData .= '</sheetData>';

    $sheet = preg_replace('/<sheetData>.*?<\/sheetData>/s', $sheetData, $sheet, 1);
    $sheet = preg_replace('/<dimension ref="[^"]+"/', '<dimension ref="A1:M' . $last . '"', $sheet, 1);
    if (!is_string($sheet)) {
        return '';
    }

    $tmp = tempnam(sys_get_temp_dir(), 'cnc_nfps_');
    if ($tmp === false || !copy($templatePath, $tmp)) {
        return '';
    }
    $out = new ZipArchive();
    if ($out->open($tmp) !== true) {
        @unlink($tmp);
        return '';
    }
    $out->addFromString('xl/worksheets/sheet1.xml', $sheet);
    $out->close();
    $bytes = file_get_contents($tmp);
    @unlink($tmp);
    return is_string($bytes) ? $bytes : '';
}

function cncCloneNfpsDataRow($r, array $cells)
{
    $r = (int) $r;
    $text = static function ($value) {
        return htmlspecialchars((string) $value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    };
    $inline = static function ($col, $style, $value) use ($r, $text) {
        $value = (string) $value;
        if ($value === '') {
            return '<c r="' . $col . $r . '" s="' . $style . '"/>';
        }
        return '<c r="' . $col . $r . '" s="' . $style . '" t="inlineStr"><is><t>' . $text($value) . '</t></is></c>';
    };
    $amount = number_format((float) ($cells[6] ?? 0), 2, '.', '');
    $xml = '<row r="' . $r . '" spans="1:13">';
    $xml .= $inline('A', '4', $cells[0] ?? '');
    $xml .= $inline('B', '4', $cells[1] ?? '');
    $xml .= $inline('C', '6', $cells[2] ?? '');
    $xml .= $inline('D', '6', $cells[3] ?? '');
    $xml .= $inline('E', '9', $cells[4] ?? '');
    $xml .= $inline('F', '4', $cells[5] ?? '');
    $xml .= '<c r="G' . $r . '" s="4"><v>' . $amount . '</v></c>';
    $xml .= $inline('H', '4', $cells[7] ?? '');
    $xml .= $inline('I', '4', $cells[8] ?? '');
    $xml .= $inline('J', '4', $cells[9] ?? '');
    $xml .= $inline('K', '7', $cells[10] ?? '');
    $xml .= $inline('L', '4', '');
    $xml .= $inline('M', '4', '');
    $xml .= '</row>';
    return $xml;
}

function cncCloneNfpsBlankRow($r)
{
    $r = (int) $r;
    $styles = [
        'A' => '4', 'B' => '4', 'C' => '6', 'D' => '6', 'E' => '9', 'F' => '4',
        'G' => '4', 'H' => '4', 'I' => '4', 'J' => '4', 'K' => '7', 'L' => '4', 'M' => '4',
    ];
    $xml = '<row r="' . $r . '" spans="1:13">';
    foreach ($styles as $col => $style) {
        $xml .= '<c r="' . $col . $r . '" s="' . $style . '"/>';
    }
    $xml .= '</row>';
    return $xml;
}
