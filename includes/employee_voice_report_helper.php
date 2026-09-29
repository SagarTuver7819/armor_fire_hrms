<?php
/**
 * Shared filter loader for Employee Voice reports
 * @return array{types:array,filters:array,rows:array,meta:array,query:array,departments:array}
 */
function evReportLoadData()
{
    ensureEmployeeVoiceTables();
    $types = evModuleTypes();

    $typeFilter = strtoupper(trim((string) ($_GET['type'] ?? '')));
    $statusFilter = trim((string) ($_GET['status'] ?? ''));
    $deptId = (int) ($_GET['department_id'] ?? 0);
    $dateFrom = trim((string) ($_GET['date_from'] ?? ''));
    $dateTo = trim((string) ($_GET['date_to'] ?? ''));
    $q = trim((string) ($_GET['q'] ?? ''));

    if ($dateFrom === '' && $dateTo === '') {
        $dateFrom = date('Y-m-01');
        $dateTo = date('Y-m-d');
    }

    $filters = [];
    if (isset($types[$typeFilter])) {
        $filters['module_type'] = $typeFilter;
    }
    if ($statusFilter !== '') {
        $filters['status'] = $statusFilter;
    }
    if ($deptId > 0) {
        $filters['department_id'] = $deptId;
    }
    if ($dateFrom !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
        $filters['date_from'] = $dateFrom;
    }
    if ($dateTo !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
        $filters['date_to'] = $dateTo;
    }
    if ($q !== '') {
        $filters['q'] = $q;
    }

    $conn = getDBConnection();
    $departments = [];
    $dres = $conn->query('SELECT id, department_name FROM departments WHERE status = 1 ORDER BY sort_order ASC, department_name ASC');
    if ($dres) {
        while ($r = $dres->fetch_assoc()) {
            $departments[] = $r;
        }
    }
    $rows = evListTickets($filters, $conn);
    $conn->close();

    $byType = ['GRIEVANCE' => 0, 'SUGGESTION' => 0, 'SAFETY' => 0];
    $openCount = 0;
    $closedCount = 0;
    foreach ($rows as $r) {
        $mt = strtoupper((string) ($r['module_type'] ?? ''));
        if (isset($byType[$mt])) {
            $byType[$mt]++;
        }
        $st = trim((string) ($r['status'] ?? ''));
        if (in_array($st, ['Closed', 'Withdrawn', 'Resolved', 'Verified'], true)) {
            $closedCount++;
        } else {
            $openCount++;
        }
    }

    $statusOptions = [];
    foreach (array_keys($types) as $tk) {
        foreach (evStatusesByModule($tk) as $st) {
            $statusOptions[$st] = true;
        }
    }
    $statusOptions = array_keys($statusOptions);
    sort($statusOptions);

    $query = [
        'type' => $typeFilter,
        'status' => $statusFilter,
        'department_id' => $deptId,
        'date_from' => $dateFrom,
        'date_to' => $dateTo,
        'q' => $q,
    ];

    return [
        'types' => $types,
        'filters' => $filters,
        'rows' => $rows,
        'departments' => $departments,
        'status_options' => $statusOptions,
        'query' => $query,
        'meta' => [
            'type' => $typeFilter,
            'status' => $statusFilter,
            'department_id' => $deptId,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'q' => $q,
            'by_type' => $byType,
            'open' => $openCount,
            'closed' => $closedCount,
            'total' => count($rows),
        ],
    ];
}

function evReportQueryUrl($path, array $query, array $override = [])
{
    $q = array_merge($query, $override);
    $clean = array_filter(
        $q,
        static function ($v) {
            return $v !== '' && $v !== null && $v !== 0 && $v !== '0';
        }
    );
    $qs = http_build_query($clean);
    return app_url($path . ($qs !== '' ? '?' . $qs : ''));
}
