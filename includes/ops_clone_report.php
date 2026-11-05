<?php
/**
 * CNC New Operation List — figures for the two letterhead PDFs.
 * Qty and amount match the on-screen grid (Q × rate). Salary rows match the staff split.
 */

require_once __DIR__ . '/contractor_helper.php';

function opsCloneFindCncSheetId($conn, $employeeId, $month, $year)
{
    $employeeId = (int) $employeeId;
    $month = (int) $month;
    $year = (int) $year;
    if ($employeeId <= 0) {
        return 0;
    }
    $st = $conn->prepare(
        "SELECT id FROM contractor_operation_sheets
         WHERE employee_id = ? AND operation = 'CNC' AND month_no = ? AND year_no = ? AND status = 1
         ORDER BY id DESC LIMIT 1"
    );
    if (!$st) {
        return 0;
    }
    $st->bind_param('iii', $employeeId, $month, $year);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    $st->close();
    return (int) ($row['id'] ?? 0);
}

/**
 * @return array<string,mixed>
 */
function opsCloneBuildCncReport($conn, $employeeId, $month, $year, $sheetId = 0)
{
    $employeeId = (int) $employeeId;
    $month = (int) $month;
    $year = (int) $year;
    $sheetId = (int) $sheetId;
    if ($sheetId <= 0) {
        $sheetId = opsCloneFindCncSheetId($conn, $employeeId, $month, $year);
    }
    $sheet = $sheetId > 0 ? getContractorSheetById($sheetId, $conn) : null;
    if ($employeeId <= 0 && $sheet) {
        $employeeId = (int) ($sheet['employee_id'] ?? 0);
    }

    $emp = ['id' => $employeeId, 'employee_code' => '', 'employee_name' => ''];
    if ($sheet) {
        $emp['employee_code'] = (string) ($sheet['employee_code'] ?? '');
        $emp['employee_name'] = (string) ($sheet['employee_name'] ?? $sheet['full_name'] ?? '');
    }
    if ($emp['employee_name'] === '' && $employeeId > 0) {
        $st = $conn->prepare('SELECT id, employee_code, employee_name FROM employees WHERE id = ? LIMIT 1');
        if ($st) {
            $st->bind_param('i', $employeeId);
            $st->execute();
            $row = $st->get_result()->fetch_assoc();
            $st->close();
            if ($row) {
                $emp = $row;
            }
        }
    }

    $items = $sheet['items'] ?? [];
    $kraAsOf = date('Y-m-d');
    try {
        $kraSync = syncErpKraPartsToContractorProducts('CNC', $conn, $kraAsOf);
    } catch (Throwable $e) {
        $kraSync = ['names' => contractorCncKraBaselinePartNames()];
        foreach (contractorCncKraBaselinePartNames() as $pn) {
            contractorEnsureProduct($conn, 'CNC', $pn, 0.0);
        }
    }
    $kraNameSet = [];
    $canonLabelByKey = [];
    foreach (($kraSync['names'] ?? []) as $pn) {
        $ck = contractorKraCanonicalKey($pn);
        if ($ck === '') {
            continue;
        }
        $kraNameSet[$ck] = true;
        $canonLabelByKey[$ck] = (string) $pn;
        foreach (contractorKraPartNameLookupCandidates($pn) as $al) {
            $kraNameSet[strtolower(trim((string) $al))] = true;
        }
    }
    $allCnc = getContractorProductsByOperation('CNC', $conn);
    $best = [];
    foreach ($allCnc as $pr) {
        if (!contractorProductMatchesKraNames($pr, $kraNameSet)) {
            continue;
        }
        $raw = trim((string) ($pr['product_name'] ?? ''));
        if ($raw === '') {
            $raw = trim((string) ($pr['process'] ?? ''));
        }
        $nk = contractorKraCanonicalKey($raw);
        if ($nk === '') {
            continue;
        }
        $matchedCanon = $canonLabelByKey[$nk] ?? $raw;
        $rate = (float) ($pr['rate'] ?? 0);
        if (!isset($best[$nk]) || $rate > (float) ($best[$nk]['rate'] ?? 0)) {
            $best[$nk] = $pr;
            $best[$nk]['_canon'] = $matchedCanon;
        }
    }
    try {
        $erpDayQtyByPart = fetchErpKraDateWiseQtys('CNC', $year, $month);
    } catch (Throwable $e) {
        $erpDayQtyByPart = [];
    }

    $lines = [];
    $qtyTotal = 0.0;
    $amountTotal = 0.0;
    foreach (($kraSync['names'] ?? []) as $canon) {
        $ck = contractorKraCanonicalKey($canon);
        if ($ck === '' || !isset($best[$ck])) {
            continue;
        }
        $pr = $best[$ck];
        $pid = (int) ($pr['id'] ?? 0);
        $saved = null;
        foreach ($items as $it) {
            if ((int) ($it['product_id'] ?? 0) === $pid) {
                $saved = $it;
                break;
            }
        }
        if ($saved === null) {
            foreach ($items as $it) {
                $itPid = (int) ($it['product_id'] ?? 0);
                if ($itPid <= 0) {
                    continue;
                }
                foreach ($allCnc as $candPr) {
                    if ((int) ($candPr['id'] ?? 0) !== $itPid) {
                        continue;
                    }
                    $candKey = contractorKraCanonicalKey((string) ($candPr['product_name'] ?? $candPr['process'] ?? ''));
                    if ($candKey === $ck) {
                        $saved = $it;
                        break 2;
                    }
                }
            }
        }
        $days = is_array($saved['days'] ?? null) ? $saved['days'] : [];
        $erpDays = contractorErpDaysForPartName($canon, $erpDayQtyByPart);
        $days = mergeErpKraDaysIntoMap($days, $erpDays, true);
        $rate = (float) ($saved !== null ? ($saved['rate'] ?? $pr['rate']) : ($pr['rate'] ?? 0));
        $calc = calculateContractorRow('CNC', $rate, 0, 0, $days);
        $qty = (float) ($calc['total_qty'] ?? 0);
        $amount = (float) ($calc['total_amount'] ?? 0);
        $lines[] = [
            'name' => (string) $canon,
            'rate' => $rate,
            'qty' => $qty,
            'amount' => $amount,
        ];
        $qtyTotal += $qty;
        $amountTotal += $amount;
    }
    $qtyTotal = round($qtyTotal, 2);
    $amountTotal = round($amountTotal, 2);

    $staffBundle = getOpsCloneDepartmentStaff('CNC', $employeeId, $conn);
    $main = $staffBundle['main'] ?? null;
    $attRows = $staffBundle['rows'];
    if (is_array($main) && (int) ($main['id'] ?? 0) > 0) {
        $attRows[] = $main;
    }
    $attMap = $attRows ? getOpsCloneStaffAttendanceMap($attRows, $month, $year, $conn) : [];
    $shares = opsCloneCncSalaryShare($staffBundle['rows'], $attMap, $month, $year);
    $split = opsCloneApplyContractorPool($shares, $amountTotal);

    $staff = [];
    foreach ($staffBundle['rows'] as $row) {
        $eid = (int) ($row['id'] ?? 0);
        $share = $split['shares'][$eid] ?? [];
        $staff[] = [
            'code' => (string) ($row['employee_code'] ?? ''),
            'name' => (string) ($row['employee_name'] ?? ''),
            'salary' => (float) ($row['decided_salary'] ?? 0),
            'working_days' => (int) ($share['working_days'] ?? 0),
            'paid_days' => (float) ($share['paid_days'] ?? 0),
            'final' => (float) ($share['final'] ?? 0),
            'is_main' => false,
        ];
    }
    if (is_array($main) && (int) ($main['id'] ?? 0) > 0) {
        $mainShare = opsCloneCncSalaryShare([$main], $attMap, $month, $year);
        $mainEid = (int) ($main['id'] ?? 0);
        $staff[] = [
            'code' => (string) ($main['employee_code'] ?? ''),
            'name' => (string) ($main['employee_name'] ?? ''),
            'salary' => (float) ($main['decided_salary'] ?? 0),
            'working_days' => (int) ($mainShare[$mainEid]['working_days'] ?? 0),
            'paid_days' => (float) ($mainShare[$mainEid]['paid_days'] ?? 0),
            'final' => (float) ($split['remainder'] ?? 0),
            'is_main' => true,
        ];
    }

    $monthEnd = sprintf('%04d-%02d-%02d', $year, $month, (int) date('t', mktime(0, 0, 0, $month, 1, $year)));
    $today = date('Y-m-d');
    $periodStart = sprintf('%04d-%02d-01', $year, $month);
    if ($periodStart < '2026-11-01') {
        $periodStart = '2026-11-01';
    }
    $periodEnd = $monthEnd > $today ? $today : $monthEnd;

    return [
        'employee' => $emp,
        'month' => $month,
        'year' => $year,
        'period_start' => $periodStart,
        'period_end' => $periodEnd,
        'lines' => $lines,
        'qty_total' => $qtyTotal,
        'amount_total' => $amountTotal,
        'staff' => $staff,
        'pool' => $amountTotal,
        'remainder' => (float) ($split['remainder'] ?? 0),
    ];
}
