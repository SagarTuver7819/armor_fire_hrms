<?php
/**
 * Canonical Armor Fire departments (34) + duplicate merge / alias resolution.
 */

require_once __DIR__ . '/department_icons.php';

function canonicalDepartmentsCatalog()
{
    return [
        ['ADMINISTRATION', 'fa-landmark', '#5B6CFF', 1],
        ['HUMAN RESOURCE MANAGEMENT', 'fa-users', '#E85D75', 2],
        ['ACCOUNTS AND FINANCE', 'fa-file-invoice-dollar', '#2ECC71', 3],
        ['COLLECTION', 'fa-hand-holding-dollar', '#F39C12', 4],
        ['SALES & MARKETING - BACK OFFICE', 'fa-headset', '#9B59B6', 5],
        ['SALES & MARKETING - ON FIELD', 'fa-handshake', '#1ABC9C', 6],
        ['IT AND NETWORKING', 'fa-network-wired', '#3498DB', 7],
        ['TENDER', 'fa-file-contract', '#E67E22', 8],
        ['QA AND QC', 'fa-clipboard-check', '#16A085', 9],
        ['NPD', 'fa-lightbulb', '#F1C40F', 10],
        ['DESIGN', 'fa-ruler-combined', '#8E44AD', 11],
        ['PRODUCTION', 'fa-industry', '#E74C3C', 12],
        ['PURCHASE', 'fa-cart-shopping', '#2980B9', 13],
        ['STORE', 'fa-warehouse', '#D35400', 14],
        ['LABORATORY', 'fa-flask', '#27AE60', 15],
        ['CORE', 'fa-cubes', '#7F8C8D', 16],
        ['MELTING 1', 'fa-fire', '#C0392B', 17],
        ['MELTING 2', 'fa-fire-flame-curved', '#E74C3C', 18],
        ['CUTTING', 'fa-scissors', '#34495E', 19],
        ['GRINDING', 'fa-gear', '#95A5A6', 20],
        ['LATHE', 'fa-gears', '#2C3E50', 21],
        ['CNC', 'fa-microchip', '#1ABC9C', 22],
        ['BUFF', 'fa-sparkles', '#F39C12', 23],
        ['CLEANING', 'fa-broom', '#3498DB', 24],
        ['ASSEMBLY 1 & COATING', 'fa-layer-group', '#9B59B6', 25],
        ['ASSEMBLY 2', 'fa-object-group', '#8E44AD', 26],
        ['ASSEMBLY 3 RRL & FLEXIBLE', 'fa-diagram-project', '#6C5CE7', 27],
        ['ASSEMBLY 4 ALARM & DELUGE VALVE', 'fa-bell', '#E17055', 28],
        ['SPRINKLER', 'fa-shower', '#00CEC9', 29],
        ['ARGON', 'fa-atom', '#0984E3', 30],
        ['MAINTENANCE', 'fa-wrench', '#FD79A8', 31],
        ['CANTEEN', 'fa-utensils', '#FDCB6E', 32],
        ['DISPATCH', 'fa-truck', '#00B894', 33],
        ['TRANSPORT', 'fa-truck-fast', '#636E72', 34],
        ['BUTTERFLY VALVE', 'fa-circle-dot', '#00B894', 35],
        ['DRUM', 'fa-drum', '#6C5CE7', 36],
    ];
}

function normalizeDepartmentKey($name)
{
    $name = strtoupper(trim(html_entity_decode(strip_tags((string) $name))));
    $name = str_replace(['&', '-', '_', '/', '\\', '.', ','], ' ', $name);
    $name = preg_replace('/\s+/', ' ', $name);
    return trim($name);
}

function departmentAliasMap()
{
    static $map = null;
    if ($map !== null) {
        return $map;
    }

    $raw = [
        'HUMAN RESOURCE' => 'HUMAN RESOURCE MANAGEMENT',
        'HR ADMIN' => 'HUMAN RESOURCE MANAGEMENT',
        'HR AND ADMIN' => 'HUMAN RESOURCE MANAGEMENT',
        'HR MANAGEMENT' => 'HUMAN RESOURCE MANAGEMENT',
        'ACCOUNTS' => 'ACCOUNTS AND FINANCE',
        'ACCOUNT' => 'ACCOUNTS AND FINANCE',
        'ACCOUNTS AND FINACE' => 'ACCOUNTS AND FINANCE',
        'FINANCE' => 'ACCOUNTS AND FINANCE',
        'SALES AND MARKETING' => 'SALES & MARKETING - BACK OFFICE',
        'SALES MARKETING BACK OFFICE' => 'SALES & MARKETING - BACK OFFICE',
        'SALES MARKETING ON FIELD' => 'SALES & MARKETING - ON FIELD',
        'SALES BUSINESS DEVELOPMENT' => 'SALES & MARKETING - ON FIELD',
        'DIGITAL MARKETING' => 'SALES & MARKETING - BACK OFFICE',
        'INFORMATION TECHNOLOGY' => 'IT AND NETWORKING',
        'IT NETWORKING' => 'IT AND NETWORKING',
        'IT AND NETWORK' => 'IT AND NETWORKING',
        'QAQC' => 'QA AND QC',
        'QA QC' => 'QA AND QC',
        'QUALITY ASSURANCE' => 'QA AND QC',
        'STORE AND PURCHASE' => 'PURCHASE',
        'STORE PURCHASE' => 'PURCHASE',
        'BUTTERFLY' => 'BUTTERFLY VALVE',
        'BUTTER FLY VALVE' => 'BUTTERFLY VALVE',
        'BUTTERFLY VALVE DEPARTMENT' => 'BUTTERFLY VALVE',
        'DRUM DEPARTMENT' => 'DRUM',
        'DRUMS' => 'DRUM',
    ];

    $map = [];
    foreach ($raw as $alias => $canonical) {
        $map[normalizeDepartmentKey($alias)] = $canonical;
    }
    foreach (canonicalDepartmentsCatalog() as $row) {
        $map[normalizeDepartmentKey($row[0])] = $row[0];
    }
    return $map;
}

/**
 * Map any department label to one of the 34 canonical names, or empty if unknown.
 */
function resolveCanonicalDepartmentName($name)
{
    $name = trim(html_entity_decode(strip_tags((string) $name)));
    if ($name === '') {
        return '';
    }

    $key = normalizeDepartmentKey($name);
    $aliases = departmentAliasMap();
    if (isset($aliases[$key])) {
        return $aliases[$key];
    }

    foreach (canonicalDepartmentsCatalog() as $row) {
        $canonical = $row[0];
        $cKey = normalizeDepartmentKey($canonical);
        if ($key === $cKey) {
            return $canonical;
        }
        if ($key !== '' && (strpos($key, $cKey) !== false || strpos($cKey, $key) !== false)) {
            return $canonical;
        }
    }

    return '';
}

function getDepartmentIdByName($conn, $departmentName)
{
    $stmt = $conn->prepare('SELECT id FROM departments WHERE department_name = ? LIMIT 1');
    $stmt->bind_param('s', $departmentName);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ? (int) $row['id'] : 0;
}

function ensureCanonicalDepartments($conn)
{
    $inserted = 0;
    $stmt = $conn->prepare(
        'INSERT INTO departments (department_name, icon_class, icon_color, sort_order, status)
         SELECT ?, ?, ?, ?, 1 FROM DUAL
         WHERE NOT EXISTS (SELECT 1 FROM departments WHERE department_name = ? LIMIT 1)'
    );

    foreach (canonicalDepartmentsCatalog() as $row) {
        [$name, $icon, $color, $sort] = $row;
        $stmt->bind_param('ssiis', $name, $icon, $color, $sort, $name);
        $stmt->execute();
        if ($stmt->affected_rows > 0) {
            $inserted++;
        }
    }
    $stmt->close();

    $upd = $conn->prepare(
        'UPDATE departments SET icon_class = ?, icon_color = ?, sort_order = ?, status = 1
         WHERE department_name = ?'
    );
    foreach (canonicalDepartmentsCatalog() as $row) {
        [$name, $icon, $color, $sort] = $row;
        $upd->bind_param('ssis', $icon, $color, $sort, $name);
        $upd->execute();
    }
    $upd->close();

    return $inserted;
}

function reassignDepartmentForeignKeys($conn, $fromId, $toId)
{
    if ($fromId <= 0 || $toId <= 0 || $fromId === $toId) {
        return ['employees' => 0, 'users' => 0, 'sub_departments' => 0, 'contractor_employment' => 0];
    }

    $counts = ['employees' => 0, 'users' => 0, 'sub_departments' => 0, 'contractor_employment' => 0];

    $stmt = $conn->prepare('UPDATE employees SET department_id = ? WHERE department_id = ?');
    $stmt->bind_param('ii', $toId, $fromId);
    $stmt->execute();
    $counts['employees'] = $stmt->affected_rows;
    $stmt->close();

    $stmt = $conn->prepare('UPDATE users SET department_id = ? WHERE department_id = ?');
    $stmt->bind_param('ii', $toId, $fromId);
    $stmt->execute();
    $counts['users'] = $stmt->affected_rows;
    $stmt->close();

    $res = $conn->query(
        "SELECT id, name FROM sub_departments WHERE department_id = {$fromId} AND status = 1"
    );
    if ($res) {
        while ($sub = $res->fetch_assoc()) {
            $subId = (int) $sub['id'];
            $subName = (string) $sub['name'];
            $targetSubId = 0;
            $find = $conn->prepare(
                'SELECT id FROM sub_departments
                 WHERE department_id = ? AND name = ? AND status = 1 LIMIT 1'
            );
            $find->bind_param('is', $toId, $subName);
            $find->execute();
            $existing = $find->get_result()->fetch_assoc();
            $find->close();
            if ($existing) {
                $targetSubId = (int) $existing['id'];
                $conn->query("UPDATE sub_departments SET status = 0 WHERE id = {$subId}");
            } else {
                $move = $conn->prepare('UPDATE sub_departments SET department_id = ? WHERE id = ?');
                $move->bind_param('ii', $toId, $subId);
                $move->execute();
                $move->close();
                $targetSubId = $subId;
                $counts['sub_departments']++;
            }
            if ($targetSubId > 0) {
                $fix = $conn->prepare(
                    'UPDATE employees SET sub_department_id = ? WHERE sub_department_id = ?'
                );
                $fix->bind_param('ii', $targetSubId, $subId);
                $fix->execute();
                $fix->close();
            }
        }
    }

    $tableCheck = $conn->query("SHOW TABLES LIKE 'contractor_employment'");
    if ($tableCheck && $tableCheck->num_rows > 0) {
        $stmt = $conn->prepare(
            'UPDATE contractor_employment SET department_id = ? WHERE department_id = ?'
        );
        $stmt->bind_param('ii', $toId, $fromId);
        $stmt->execute();
        $counts['contractor_employment'] = $stmt->affected_rows;
        $stmt->close();
    }

    return $counts;
}

/**
 * Merge duplicate / alias departments into the canonical set.
 * Only merges known aliases / exact duplicate names.
 * Does NOT remove extra legitimate departments.
 */
function mergeDuplicateDepartments($conn)
{
    ensureCanonicalDepartments($conn);

    $canonicalNames = [];
    foreach (canonicalDepartmentsCatalog() as $row) {
        $canonicalNames[$row[0]] = true;
    }

    $log = [];
    $merged = 0;
    $deactivated = 0;

    $res = $conn->query(
        'SELECT id, department_name, status FROM departments ORDER BY id ASC'
    );
    if (!$res) {
        return ['ok' => false, 'log' => ['Could not read departments table.'], 'active' => 0];
    }

    $rows = [];
    while ($row = $res->fetch_assoc()) {
        $rows[] = $row;
    }

    $canonicalIds = [];
    foreach (canonicalDepartmentsCatalog() as $row) {
        $id = getDepartmentIdByName($conn, $row[0]);
        if ($id > 0) {
            $canonicalIds[$row[0]] = $id;
            // Reactivate canonical rows if previously soft-deleted
            $conn->query('UPDATE departments SET status = 1 WHERE id = ' . (int) $id);
        }
    }

    // Also reactivate common variants of Butterfly / Drum if inactive
    $conn->query(
        "UPDATE departments SET status = 1
         WHERE status = 0
           AND (
                UPPER(department_name) LIKE '%BUTTERFLY%'
             OR UPPER(department_name) = 'DRUM'
             OR UPPER(department_name) LIKE 'DRUM %'
           )"
    );

    $seenCanonical = [];
    foreach ($rows as $row) {
        $id = (int) $row['id'];
        $name = (string) $row['department_name'];
        $canonical = resolveCanonicalDepartmentName($name);

        // Only merge when name maps to a different canonical department id
        if ($canonical !== '' && isset($canonicalIds[$canonical])) {
            $targetId = $canonicalIds[$canonical];
            if ($id === $targetId) {
                if (!isset($seenCanonical[$canonical])) {
                    $seenCanonical[$canonical] = $id;
                }
                continue;
            }
            // Do not merge a different real department into another just because of partial name match
            // unless the alias map explicitly mapped it (exact normalize key).
            $aliases = departmentAliasMap();
            $key = normalizeDepartmentKey($name);
            $explicitAlias = isset($aliases[$key]) && $aliases[$key] === $canonical && $name !== $canonical;
            $exactDup = (normalizeDepartmentKey($name) === normalizeDepartmentKey($canonical) && $name !== $canonical);
            if (!$explicitAlias && !$exactDup && !isset($canonicalNames[$name])) {
                // Keep unmatched/extra department as-is
                continue;
            }
            if ($name === $canonical) {
                // Same display name duplicate rows
            }
            $counts = reassignDepartmentForeignKeys($conn, $id, $targetId);
            $conn->query("UPDATE departments SET status = 0 WHERE id = {$id}");
            $merged++;
            $deactivated++;
            $log[] = "Merged \"{$name}\" (id {$id}) → \"{$canonical}\" (id {$targetId}); "
                . "employees {$counts['employees']}, users {$counts['users']}.";
            continue;
        }

        // Extra departments (not in canonical list) are kept active — do not force to ADMINISTRATION
    }

    foreach ($rows as $row) {
        $name = (string) $row['department_name'];
        if (!isset($canonicalNames[$name])) {
            continue;
        }
        $canonical = $name;
        if (!isset($seenCanonical[$canonical])) {
            $seenCanonical[$canonical] = (int) $row['id'];
            continue;
        }
        $keepId = (int) $seenCanonical[$canonical];
        $dupId = (int) $row['id'];
        if ($dupId === $keepId || (int) $row['status'] !== 1) {
            continue;
        }
        $counts = reassignDepartmentForeignKeys($conn, $dupId, $keepId);
        $conn->query("UPDATE departments SET status = 0 WHERE id = {$dupId}");
        $merged++;
        $deactivated++;
        $log[] = "Duplicate canonical \"{$name}\" (id {$dupId}) merged into id {$keepId}; "
            . "employees {$counts['employees']}.";
    }

    syncDepartmentIcons($conn);

    $active = (int) $conn->query('SELECT COUNT(*) AS c FROM departments WHERE status = 1')
        ->fetch_assoc()['c'];

    if ($merged === 0 && $deactivated === 0) {
        $log[] = 'Canonical departments ensured (incl. BUTTERFLY VALVE, DRUM). Active: ' . $active . '.';
    } else {
        $log[] = "Done: {$merged} merge(s), {$deactivated} deactivated. Active departments: {$active}.";
    }

    return ['ok' => true, 'log' => $log, 'active' => $active, 'merged' => $merged];
}

/**
 * Used by employee sync — never create alias department rows.
 */
function ensureDepartmentByName($conn, $name)
{
    $name = trim(html_entity_decode(strip_tags((string) $name)));
    if ($name === '') {
        $name = 'General';
    }

    $canonical = resolveCanonicalDepartmentName($name);
    if ($canonical !== '') {
        $name = $canonical;
    }

    $id = getDepartmentIdByName($conn, $name);
    if ($id > 0) {
        return $id;
    }

    foreach (canonicalDepartmentsCatalog() as $row) {
        if ($row[0] === $name) {
            [$n, $icon, $color, $sort] = $row;
            $ins = $conn->prepare(
                'INSERT INTO departments (department_name, icon_class, icon_color, sort_order, status)
                 VALUES (?, ?, ?, ?, 1)'
            );
            $ins->bind_param('sssi', $n, $icon, $color, $sort);
            $ins->execute();
            $id = (int) $conn->insert_id;
            $ins->close();
            return $id;
        }
    }

    [$icon, $color] = resolveDepartmentIcon($name);
    $ins = $conn->prepare(
        'INSERT INTO departments (department_name, icon_class, icon_color, sort_order, status)
         VALUES (?, ?, ?, 100, 1)'
    );
    $ins->bind_param('sss', $name, $icon, $color);
    $ins->execute();
    $id = (int) $conn->insert_id;
    $ins->close();
    return $id;
}
