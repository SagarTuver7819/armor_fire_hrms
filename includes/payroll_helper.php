<?php
/**
 * Payroll helper — salary diary, jobwork, slabs mapping, generate
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/master_helper.php';
require_once __DIR__ . '/employee_helper.php';

function ensurePayrollTables($conn = null)
{
    $closeAfter = false;
    if ($conn === null) {
        $conn = getDBConnection();
        $closeAfter = true;
    }

    ensureMasterTables($conn);
    ensureEmployeesTable($conn);

    $conn->query("CREATE TABLE IF NOT EXISTS employee_salary_details (
        id INT AUTO_INCREMENT PRIMARY KEY,
        employee_id INT NOT NULL,
        line_type ENUM('component','slab') NOT NULL DEFAULT 'component',
        component_id INT DEFAULT NULL,
        slab_id INT DEFAULT NULL,
        label VARCHAR(150) NOT NULL,
        component_type VARCHAR(20) DEFAULT 'Earning',
        calculation VARCHAR(20) DEFAULT 'Fixed',
        amount DECIMAL(12,2) NOT NULL DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_esd_employee (employee_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $conn->query("CREATE TABLE IF NOT EXISTS salary_diary (
        id INT AUTO_INCREMENT PRIMARY KEY,
        employee_id INT NOT NULL,
        month_no TINYINT NOT NULL,
        year_no SMALLINT NOT NULL,
        working_days DECIMAL(6,2) NOT NULL DEFAULT 26,
        present_days DECIMAL(6,2) NOT NULL DEFAULT 26,
        overtime_hours DECIMAL(8,2) NOT NULL DEFAULT 0,
        remarks VARCHAR(255) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_diary_emp_month (employee_id, month_no, year_no)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $conn->query("CREATE TABLE IF NOT EXISTS jobwork_entries (
        id INT AUTO_INCREMENT PRIMARY KEY,
        employee_id INT NOT NULL,
        work_date DATE NOT NULL,
        item_name VARCHAR(150) DEFAULT NULL,
        quantity DECIMAL(12,3) NOT NULL DEFAULT 0,
        rate DECIMAL(12,2) NOT NULL DEFAULT 0,
        amount DECIMAL(12,2) NOT NULL DEFAULT 0,
        remarks VARCHAR(255) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_jw_emp_date (employee_id, work_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $conn->query("CREATE TABLE IF NOT EXISTS salary_payslips (
        id INT AUTO_INCREMENT PRIMARY KEY,
        employee_id INT NOT NULL,
        month_no TINYINT NOT NULL,
        year_no SMALLINT NOT NULL,
        pay_type VARCHAR(20) NOT NULL DEFAULT 'Salary',
        generated_from VARCHAR(30) NOT NULL DEFAULT 'attendance',
        present_days DECIMAL(6,2) DEFAULT NULL,
        working_days DECIMAL(6,2) DEFAULT NULL,
        jobwork_total DECIMAL(12,2) DEFAULT NULL,
        slab_id INT DEFAULT NULL,
        earnings DECIMAL(12,2) NOT NULL DEFAULT 0,
        deductions DECIMAL(12,2) NOT NULL DEFAULT 0,
        net_salary DECIMAL(12,2) NOT NULL DEFAULT 0,
        breakup_json TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_payslip_emp_month (employee_id, month_no, year_no)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    if ($closeAfter) {
        $conn->close();
    }
}

function getEmployeeSalaryDetails($employeeId)
{
    $conn = getDBConnection();
    ensurePayrollTables($conn);
    $employeeId = (int) $employeeId;
    $rows = [];
    $stmt = $conn->prepare("SELECT * FROM employee_salary_details WHERE employee_id = ? ORDER BY id ASC");
    $stmt->bind_param('i', $employeeId);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $rows[] = $row;
    }
    $stmt->close();
    $conn->close();
    return $rows;
}

function mapJobworkToSlab($amount)
{
    $slabs = getActiveMasterRows('salary_slabs', 'min_amount ASC, id ASC');
    $amount = (float) $amount;
    if (!$slabs) {
        return null;
    }
    foreach ($slabs as $slab) {
        $min = (float) $slab['min_amount'];
        $max = (float) $slab['max_amount'];
        if ($amount >= $min && $amount <= $max) {
            return $slab;
        }
    }
    $last = $slabs[count($slabs) - 1];
    if ($amount > (float) $last['max_amount']) {
        return $last;
    }
    return $slabs[0];
}

function getDiaryRow($employeeId, $month, $year)
{
    $conn = getDBConnection();
    ensurePayrollTables($conn);
    $stmt = $conn->prepare("SELECT * FROM salary_diary WHERE employee_id = ? AND month_no = ? AND year_no = ? LIMIT 1");
    $stmt->bind_param('iii', $employeeId, $month, $year);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $conn->close();
    return $row ?: null;
}

function getJobworkTotal($employeeId, $month, $year)
{
    $conn = getDBConnection();
    ensurePayrollTables($conn);
    $from = sprintf('%04d-%02d-01', $year, $month);
    $to = date('Y-m-t', strtotime($from));
    $stmt = $conn->prepare(
        "SELECT COALESCE(SUM(amount),0) AS total FROM jobwork_entries
         WHERE employee_id = ? AND work_date BETWEEN ? AND ?"
    );
    $stmt->bind_param('iss', $employeeId, $from, $to);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $conn->close();
    return (float) ($row['total'] ?? 0);
}

function calculateEmployeeSalary(array $emp, $month, $year)
{
    $payType = (($emp['pay_type'] ?? 'Salary') === 'Jobwork') ? 'Jobwork' : 'Salary';
    $employeeId = (int) $emp['id'];
    $lines = getEmployeeSalaryDetails($employeeId);
    $breakup = [];
    $earnings = 0.0;
    $deductions = 0.0;
    $source = 'attendance';
    $present = null;
    $working = null;
    $jwTotal = null;
    $slabId = null;

    if ($payType === 'Jobwork') {
        $source = 'jobwork';
        $jwTotal = getJobworkTotal($employeeId, $month, $year);
        $slab = null;
        foreach ($lines as $line) {
            if (($line['line_type'] ?? '') === 'slab' && !empty($line['slab_id'])) {
                $slab = getMasterRow('salary_slabs', (int) $line['slab_id']);
                break;
            }
        }
        if (!$slab) {
            $slab = mapJobworkToSlab($jwTotal);
        }
        if ($slab) {
            $slabId = (int) $slab['id'];
            $mapped = (float) $slab['monthly_salary'];
            $earnings = $mapped;
            $breakup[] = ['label' => 'Jobwork total', 'type' => 'Info', 'amount' => $jwTotal];
            $breakup[] = ['label' => 'Mapped slab: ' . $slab['slab_name'], 'type' => 'Earning', 'amount' => $mapped];
        } else {
            $earnings = $jwTotal;
            $breakup[] = ['label' => 'Jobwork amount (no slab)', 'type' => 'Earning', 'amount' => $jwTotal];
        }
    } else {
        $diary = getDiaryRow($employeeId, $month, $year);
        $working = $diary ? (float) $diary['working_days'] : 26;
        $present = $diary ? (float) $diary['present_days'] : $working;
        if ($working <= 0) {
            $working = 26;
        }
        $factor = $present / $working;
        if ($factor < 0) {
            $factor = 0;
        }
        if ($factor > 1) {
            $factor = 1;
        }

        $componentLines = array_filter($lines, function ($l) {
            return ($l['line_type'] ?? 'component') === 'component';
        });
        if (!$componentLines) {
            $masters = getActiveMasterRows('salary_components', 'id ASC');
            $basic = (float) ($emp['decided_salary'] ?? 0);
            foreach ($masters as $c) {
                $amt = (float) $c['default_value'];
                if (($c['calculation'] ?? '') === 'Percentage' && $basic > 0) {
                    $amt = round($basic * $amt / 100, 2);
                }
                $componentLines[] = [
                    'label' => $c['component_name'],
                    'component_type' => $c['component_type'],
                    'amount' => $amt,
                ];
            }
            if (!$componentLines && $basic > 0) {
                $componentLines[] = [
                    'label' => 'Decided Salary',
                    'component_type' => 'Earning',
                    'amount' => $basic,
                ];
            }
        }

        foreach ($componentLines as $line) {
            $amt = round((float) $line['amount'] * $factor, 2);
            $type = ($line['component_type'] ?? 'Earning') === 'Deduction' ? 'Deduction' : 'Earning';
            $breakup[] = ['label' => $line['label'], 'type' => $type, 'amount' => $amt];
            if ($type === 'Deduction') {
                $deductions += $amt;
            } else {
                $earnings += $amt;
            }
        }
    }

    $net = $earnings - $deductions;
    if ($net < 0) {
        $net = 0;
    }

    return [
        'pay_type'        => $payType,
        'generated_from'  => $source,
        'present_days'    => $present,
        'working_days'    => $working,
        'jobwork_total'   => $jwTotal,
        'slab_id'          => $slabId,
        'earnings'        => round($earnings, 2),
        'deductions'      => round($deductions, 2),
        'net_salary'      => round($net, 2),
        'breakup'         => $breakup,
    ];
}

function savePayslip($employeeId, $month, $year, array $calc)
{
    $conn = getDBConnection();
    ensurePayrollTables($conn);
    $json = json_encode($calc['breakup']);
    $from = $calc['generated_from'];
    $payType = $calc['pay_type'];
    $present = $calc['present_days'] !== null ? (float) $calc['present_days'] : 0;
    $working = $calc['working_days'] !== null ? (float) $calc['working_days'] : 0;
    $jw = $calc['jobwork_total'] !== null ? (float) $calc['jobwork_total'] : 0;
    $slabId = $calc['slab_id'] !== null ? (int) $calc['slab_id'] : 0;
    $earn = $calc['earnings'];
    $ded = $calc['deductions'];
    $net = $calc['net_salary'];

    $stmt = $conn->prepare(
        "INSERT INTO salary_payslips
         (employee_id, month_no, year_no, pay_type, generated_from, present_days, working_days,
          jobwork_total, slab_id, earnings, deductions, net_salary, breakup_json)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)
         ON DUPLICATE KEY UPDATE
            pay_type=VALUES(pay_type), generated_from=VALUES(generated_from),
            present_days=VALUES(present_days), working_days=VALUES(working_days),
            jobwork_total=VALUES(jobwork_total), slab_id=VALUES(slab_id),
            earnings=VALUES(earnings), deductions=VALUES(deductions),
            net_salary=VALUES(net_salary), breakup_json=VALUES(breakup_json)"
    );
    $stmt->bind_param(
        'iiissdddiddds',
        $employeeId,
        $month,
        $year,
        $payType,
        $from,
        $present,
        $working,
        $jw,
        $slabId,
        $earn,
        $ded,
        $net,
        $json
    );
    $ok = $stmt->execute();
    $stmt->close();
    $conn->close();
    return $ok;
}

function moneyInr($n)
{
    return '₹ ' . number_format((float) $n, 2);
}
