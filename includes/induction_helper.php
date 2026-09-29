<?php
/**
 * Employee Induction / Sales Training topics
 */

/**
 * Sales training only for these 2 departments:
 * - Sales & Marketing – Back Office
 * - Sales & Marketing – On Field
 * All other departments → General Induction only.
 */
function employeeIsSalesRole(array $emp)
{
    $dept = strtolower(trim((string) ($emp['department_name'] ?? '')));
    if ($dept === '') {
        return false;
    }

    // Normalize: dashes/ampersands → spaces
    $norm = strtolower(preg_replace('/[^a-z0-9]+/i', ' ', $dept));
    $norm = trim(preg_replace('/\s+/', ' ', $norm));

    $isSalesMarketing = (
        (strpos($norm, 'sales') !== false && strpos($norm, 'marketing') !== false)
        || strpos($norm, 'sales marketing') !== false
    );
    if (!$isSalesMarketing) {
        return false;
    }

    // Back Office
    if (strpos($norm, 'back office') !== false || strpos($norm, 'backoffice') !== false) {
        return true;
    }

    // On Field / Outdoor sales
    if (
        strpos($norm, 'on field') !== false
        || strpos($norm, 'onfield') !== false
        || strpos($norm, 'outdoor') !== false
        || preg_match('/\bfield\b/', $norm)
    ) {
        return true;
    }

    return false;
}

/**
 * Resolve induction sheet type: general | sales
 */
function inductionResolveType(array $emp, $requested = '')
{
    $requested = strtolower(trim((string) $requested));
    if (in_array($requested, ['general', 'sales'], true)) {
        return $requested;
    }
    return employeeIsSalesRole($emp) ? 'sales' : 'general';
}

/**
 * Which training track applies for status:
 * - Sales & Marketing Back Office / On Field → sales only
 * - All other departments → general only
 */
function employeeTrainingTrackType(array $emp)
{
    return employeeIsSalesRole($emp) ? 'sales' : 'general';
}

/**
 * Topic lists with coverage (matches ASIPL Trainer & Trainee Signature Sheet)
 *
 * @return array{key:string,label:string,topics:array<int,array{topic:string,coverage:string}>}
 */
function inductionGetSheetDefinition($type)
{
    $type = strtolower(trim((string) $type)) === 'sales' ? 'sales' : 'general';

    $companyProfile = [
        'topic' => 'Company Profile',
        'coverage' => 'Industries Introduction, Company History, Company Business & Products, Product Portfolio, Corporate Video',
    ];
    $coreValues = [
        'topic' => 'ARMOR Core Purpose & Values',
        'coverage' => 'Core Purpose, Core Values, Company Vision & Goal',
    ];
    $productKnowledge = [
        'topic' => 'Product Knowledge',
        'coverage' => 'Fire Extinguishers, Hydrant System, Hose & Hose Reel, Valves & Inlets, Sprinkler System, Deluge & Alarm Valves',
    ];
    $hrPolicy = [
        'topic' => 'HR Policy',
        'coverage' => 'Working Hours & Attendance, Leave Rules & Procedure, Code of Conduct, Employee Discipline',
    ];
    $itAssetsGeneral = [
        'topic' => 'IT & other Asset Allocation',
        'coverage' => 'TO DO book, I.D card, CUG sim/Mobile, Email I.D, Uniform',
    ];
    $itAssetsSales = [
        'topic' => 'IT / Asset Allocation',
        'coverage' => 'TO DO book, I.D card, Visiting card (Sales Team), CUG sim/Mobile, Email I.D, Uniform, CRM (ID)',
    ];
    $standardKnowledge = [
        'topic' => 'Standard Knowledge',
        'coverage' => 'Process flow chart with standard knowledge',
    ];

    if ($type === 'general') {
        return [
            'key' => 'general',
            'label' => 'General Induction Training',
            'sheet_title' => 'Trainer & Trainee Signature Sheet — General Induction',
            'topics' => [
                $companyProfile,
                $coreValues,
                $productKnowledge,
                $hrPolicy,
                $itAssetsGeneral,
                $standardKnowledge,
            ],
        ];
    }

    return [
        'key' => 'sales',
        'label' => 'Sales Executive Training',
        'sheet_title' => 'Trainer & Trainee Signature Sheet — Sales Executive',
        'topics' => [
            $companyProfile,
            $coreValues,
            $productKnowledge,
            [
                'topic' => 'Customer Knowledge',
                'coverage' => 'Traders, Contractors, MEP Consultants, BOQ & Make List, Customer Relationship',
            ],
            [
                'topic' => 'Approval & Project Development',
                'coverage' => 'Private & Government Approval, CPWD / PWD, AE / EE, Brand Approval, Project Follow-up',
            ],
            [
                'topic' => 'KRA & KPI',
                'coverage' => 'Customer Targets, Visit Targets, Lead & Follow-up, Weekly KPI, CRM Reporting',
            ],
            [
                'topic' => 'Personality Development',
                'coverage' => 'Grooming, Professional Dress, Communication, Customer Meeting Etiquette',
            ],
            [
                'topic' => 'CRM Training',
                'coverage' => 'Customer Entry, Daily Visit, Lead & Follow-up, Project & Approval Entry, Daily Reporting',
            ],
            [
                'topic' => 'Practical Sales Training / Role Play',
                'coverage' => 'Customer List Preparation, Cold Calling, Sales Pitch, Objection Handling, Customer Visit Role Play, Performance Review',
            ],
            $hrPolicy,
            $itAssetsSales,
            [
                'topic' => 'Plant Visit',
                'coverage' => '',
            ],
            [
                'topic' => 'Training with Sales Co-ordinator',
                'coverage' => 'For 1 day',
            ],
            $standardKnowledge,
        ],
    ];
}

/**
 * Training status labels
 */
function employeeTrainingStatusLabels()
{
    return [
        'pending' => 'Pending',
        'in_progress' => 'In Progress',
        'completed' => 'Completed',
        'not_required' => 'Not Required',
    ];
}

function employeeTrainingStatusBadgeStyle($status)
{
    $map = [
        'pending' => 'background:#fef3c7;color:#b45309;',
        'in_progress' => 'background:#dbeafe;color:#1d4ed8;',
        'completed' => 'background:#dcfce7;color:#15803d;',
        'not_required' => 'background:#f1f5f9;color:#64748b;',
    ];
    $status = strtolower(trim((string) $status));
    return $map[$status] ?? $map['pending'];
}

/**
 * Create employee_training_status table
 */
function ensureEmployeeTrainingTables($conn = null)
{
    $close = false;
    if ($conn === null) {
        $conn = getDBConnection();
        $close = true;
    }
    $conn->query(
        "CREATE TABLE IF NOT EXISTS employee_training_status (
            id INT AUTO_INCREMENT PRIMARY KEY,
            employee_id INT NOT NULL,
            training_type VARCHAR(20) NOT NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'pending',
            start_date DATE DEFAULT NULL,
            end_date DATE DEFAULT NULL,
            remarks TEXT,
            updated_by INT DEFAULT NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_emp_training (employee_id, training_type),
            KEY idx_emp_training_status (status),
            KEY idx_emp_training_emp (employee_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    if ($close) {
        $conn->close();
    }
}

/**
 * Get one training status row (or defaults)
 */
function getEmployeeTrainingStatus($employeeId, $trainingType, $conn = null)
{
    $close = false;
    if ($conn === null) {
        $conn = getDBConnection();
        $close = true;
    }
    ensureEmployeeTrainingTables($conn);
    $employeeId = (int) $employeeId;
    $trainingType = strtolower(trim((string) $trainingType)) === 'sales' ? 'sales' : 'general';

    $defaults = [
        'employee_id' => $employeeId,
        'training_type' => $trainingType,
        'status' => 'pending',
        'start_date' => null,
        'end_date' => null,
        'remarks' => '',
        'updated_by' => null,
        'updated_at' => null,
    ];

    $st = $conn->prepare(
        'SELECT employee_id, training_type, status, start_date, end_date, remarks, updated_by, updated_at
         FROM employee_training_status
         WHERE employee_id = ? AND training_type = ? LIMIT 1'
    );
    $st->bind_param('is', $employeeId, $trainingType);
    $st->execute();
    $res = $st->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $st->close();
    if ($close) {
        $conn->close();
    }
    return $row ?: $defaults;
}

/**
 * Map of statuses for many employees: [empId][type] => row
 */
function getEmployeeTrainingStatusMap(array $employeeIds, $conn = null)
{
    $map = [];
    $ids = array_values(array_unique(array_filter(array_map('intval', $employeeIds))));
    if ($ids === []) {
        return $map;
    }
    $close = false;
    if ($conn === null) {
        $conn = getDBConnection();
        $close = true;
    }
    ensureEmployeeTrainingTables($conn);
    $in = implode(',', $ids);
    $res = $conn->query(
        "SELECT employee_id, training_type, status, start_date, end_date, remarks, updated_by, updated_at
         FROM employee_training_status
         WHERE employee_id IN ({$in})"
    );
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $eid = (int) $r['employee_id'];
            $tt = (string) $r['training_type'];
            $map[$eid][$tt] = $r;
        }
    }
    if ($close) {
        $conn->close();
    }
    return $map;
}

/**
 * Admin / HR update training status
 */
function saveEmployeeTrainingStatus($employeeId, $trainingType, $status, $opts = [], $conn = null)
{
    $close = false;
    if ($conn === null) {
        $conn = getDBConnection();
        $close = true;
    }
    ensureEmployeeTrainingTables($conn);

    $employeeId = (int) $employeeId;
    $trainingType = strtolower(trim((string) $trainingType)) === 'sales' ? 'sales' : 'general';
    $status = strtolower(trim((string) $status));
    $labels = employeeTrainingStatusLabels();
    if (!isset($labels[$status])) {
        if ($close) {
            $conn->close();
        }
        return ['ok' => false, 'error' => 'Invalid status'];
    }

    $startDate = trim((string) ($opts['start_date'] ?? ''));
    $endDate = trim((string) ($opts['end_date'] ?? ''));
    $remarks = trim((string) ($opts['remarks'] ?? ''));
    $updatedBy = (int) ($opts['updated_by'] ?? ($_SESSION['user_id'] ?? 0));

    if ($startDate !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)) {
        $startDate = '';
    }
    if ($endDate !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) {
        $endDate = '';
    }
    if ($status === 'completed' && $endDate === '') {
        $endDate = date('Y-m-d');
    }
    if ($status === 'in_progress' && $startDate === '') {
        $startDate = date('Y-m-d');
    }

    $startSql = $startDate !== '' ? ("'" . $conn->real_escape_string($startDate) . "'") : 'NULL';
    $endSql = $endDate !== '' ? ("'" . $conn->real_escape_string($endDate) . "'") : 'NULL';
    $remarksSql = "'" . $conn->real_escape_string($remarks) . "'";
    $statusSql = "'" . $conn->real_escape_string($status) . "'";
    $typeSql = "'" . $conn->real_escape_string($trainingType) . "'";
    $by = $updatedBy > 0 ? $updatedBy : 'NULL';

    $ok = $conn->query(
        "INSERT INTO employee_training_status
            (employee_id, training_type, status, start_date, end_date, remarks, updated_by)
         VALUES
            ({$employeeId}, {$typeSql}, {$statusSql}, {$startSql}, {$endSql}, {$remarksSql}, {$by})
         ON DUPLICATE KEY UPDATE
            status = VALUES(status),
            start_date = COALESCE(VALUES(start_date), start_date),
            end_date = COALESCE(VALUES(end_date), end_date),
            remarks = VALUES(remarks),
            updated_by = VALUES(updated_by),
            updated_at = CURRENT_TIMESTAMP"
    );

    if ($close) {
        $conn->close();
    }
    if (!$ok) {
        return ['ok' => false, 'error' => 'Could not save training status'];
    }
    return ['ok' => true, 'status' => $status];
}
