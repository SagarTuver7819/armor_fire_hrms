<?php
/**
 * Company Circulars — HR/Admin PDF uploads, department targeting, bell notifications
 */

function ensureCircularTables($conn = null)
{
    $closeAfter = false;
    if ($conn === null) {
        $conn = getDBConnection();
        $closeAfter = true;
    }

    $conn->query(
        "CREATE TABLE IF NOT EXISTS circulars (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            circular_no VARCHAR(100) DEFAULT NULL,
            circular_date DATE NOT NULL,
            added_date DATE NOT NULL,
            remarks TEXT DEFAULT NULL,
            pdf_file VARCHAR(255) NOT NULL,
            original_filename VARCHAR(255) DEFAULT NULL,
            apply_all_departments TINYINT(1) NOT NULL DEFAULT 0,
            created_by INT DEFAULT NULL,
            status TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_circular_date (circular_date),
            INDEX idx_circular_added (added_date),
            INDEX idx_circular_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $col = $conn->query("SHOW COLUMNS FROM circulars LIKE 'apply_all_departments'");
    if ($col && $col->num_rows === 0) {
        $conn->query(
            "ALTER TABLE circulars
             ADD COLUMN apply_all_departments TINYINT(1) NOT NULL DEFAULT 0 AFTER original_filename"
        );
    }

    $conn->query(
        "CREATE TABLE IF NOT EXISTS circular_departments (
            circular_id INT NOT NULL,
            department_id INT NOT NULL,
            PRIMARY KEY (circular_id, department_id),
            INDEX idx_cd_dept (department_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $conn->query(
        "CREATE TABLE IF NOT EXISTS circular_reads (
            circular_id INT NOT NULL,
            user_id INT NOT NULL,
            read_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (circular_id, user_id),
            INDEX idx_cr_user (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    circularEnsureUploadDir();

    if ($closeAfter) {
        $conn->close();
    }
}

function circularUploadDir()
{
    return dirname(__DIR__) . '/assets/uploads/circulars';
}

function circularEnsureUploadDir()
{
    $dir = circularUploadDir();
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $keep = $dir . '/.gitkeep';
    if (!is_file($keep)) {
        file_put_contents($keep, '');
    }
    return $dir;
}

function circularAbsolutePath($relativePath)
{
    $relativePath = str_replace('\\', '/', (string) $relativePath);
    if ($relativePath === '' || strpos($relativePath, 'assets/uploads/circulars/') !== 0 || strpos($relativePath, '..') !== false) {
        return '';
    }
    return dirname(__DIR__) . '/' . $relativePath;
}

function circularFileExists($relativePath)
{
    $abs = circularAbsolutePath($relativePath);
    return $abs !== '' && is_file($abs) && filesize($abs) > 0;
}

/**
 * @return array{ok:bool,path?:string,original?:string,error?:string}
 */
function circularStoreUploadedPdf(array $file)
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'error' => 'PDF upload failed. Please try again.'];
    }

    $original = (string) ($file['name'] ?? 'circular.pdf');
    $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
    if ($ext !== 'pdf') {
        return ['ok' => false, 'error' => 'Only PDF files are allowed.'];
    }

    $size = (int) ($file['size'] ?? 0);
    if ($size <= 0) {
        return ['ok' => false, 'error' => 'Empty PDF file.'];
    }
    if ($size > 10 * 1024 * 1024) {
        return ['ok' => false, 'error' => 'PDF must be under 10 MB. Please compress or re-scan.'];
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    if ($mime !== 'application/pdf' && $mime !== 'application/octet-stream') {
        return ['ok' => false, 'error' => 'Invalid PDF file.'];
    }

    circularEnsureUploadDir();
    $safeName = 'circular_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.pdf';
    $dest = circularUploadDir() . '/' . $safeName;
    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        return ['ok' => false, 'error' => 'Could not save PDF on server.'];
    }

    return [
        'ok' => true,
        'path' => 'assets/uploads/circulars/' . $safeName,
        'original' => $original,
    ];
}

function circularDeleteFile($relativePath)
{
    $abs = circularAbsolutePath($relativePath);
    if ($abs !== '' && is_file($abs)) {
        @unlink($abs);
    }
}

/**
 * Normalize selected department IDs from form
 * @return array{apply_all:bool,department_ids:int[]}
 */
function circularNormalizeDepartments($applyAll, $departmentIds)
{
    $ids = [];
    $sawAll = false;
    if (is_array($departmentIds)) {
        foreach ($departmentIds as $id) {
            if ((string) $id === 'all' || (string) $id === '0') {
                $sawAll = true;
                continue;
            }
            $id = (int) $id;
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }
    }
    $ids = array_values($ids);
    sort($ids);

    $applyAll = (int) $applyAll === 1
        || $applyAll === true
        || $applyAll === '1'
        || $applyAll === 'on'
        || $sawAll;

    if ($applyAll) {
        return ['apply_all' => true, 'department_ids' => []];
    }

    return ['apply_all' => false, 'department_ids' => $ids];
}

function syncCircularDepartments($conn, $circularId, $applyAll, array $departmentIds)
{
    $circularId = (int) $circularId;
    $st = $conn->prepare('DELETE FROM circular_departments WHERE circular_id = ?');
    $st->bind_param('i', $circularId);
    $st->execute();
    $st->close();

    if ($applyAll || !$departmentIds) {
        return;
    }

    $st = $conn->prepare('INSERT INTO circular_departments (circular_id, department_id) VALUES (?, ?)');
    foreach ($departmentIds as $deptId) {
        $deptId = (int) $deptId;
        if ($deptId <= 0) {
            continue;
        }
        $st->bind_param('ii', $circularId, $deptId);
        $st->execute();
    }
    $st->close();
}

/**
 * @return int[]
 */
function getCircularDepartmentIds($circularId, $conn = null)
{
    $circularId = (int) $circularId;
    $closeAfter = false;
    if ($conn === null) {
        $conn = getDBConnection();
        ensureCircularTables($conn);
        $closeAfter = true;
    }

    $ids = [];
    $st = $conn->prepare('SELECT department_id FROM circular_departments WHERE circular_id = ? ORDER BY department_id ASC');
    $st->bind_param('i', $circularId);
    $st->execute();
    $res = $st->get_result();
    while ($row = $res->fetch_assoc()) {
        $ids[] = (int) $row['department_id'];
    }
    $st->close();

    if ($closeAfter) {
        $conn->close();
    }
    return $ids;
}

function circularDepartmentsLabel(array $row)
{
    if (!empty($row['apply_all_departments'])) {
        return 'All Departments';
    }
    $names = trim((string) ($row['department_names'] ?? ''));
    if ($names !== '') {
        return $names;
    }
    $ids = $row['department_ids'] ?? [];
    if (is_array($ids) && $ids) {
        return count($ids) . ' department(s)';
    }
    return '—';
}

function attachCircularDepartments(array &$rows, $conn)
{
    if (!$rows) {
        return;
    }

    $ids = [];
    foreach ($rows as $r) {
        $cid = (int) ($r['id'] ?? 0);
        if ($cid > 0 && empty($r['apply_all_departments'])) {
            $ids[] = $cid;
        }
    }
    $ids = array_values(array_unique($ids));

    $map = [];
    $nameMap = [];
    if ($ids) {
        $in = implode(',', array_map('intval', $ids));
        $sql = "SELECT cd.circular_id, cd.department_id, d.department_name
                FROM circular_departments cd
                LEFT JOIN departments d ON d.id = cd.department_id
                WHERE cd.circular_id IN ({$in})
                ORDER BY d.sort_order ASC, d.department_name ASC";
        $res = $conn->query($sql);
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $cid = (int) $row['circular_id'];
                $map[$cid][] = (int) $row['department_id'];
                if (!empty($row['department_name'])) {
                    $nameMap[$cid][] = $row['department_name'];
                }
            }
        }
    }

    foreach ($rows as &$r) {
        $cid = (int) ($r['id'] ?? 0);
        if (!empty($r['apply_all_departments'])) {
            $r['department_ids'] = [];
            $r['department_names'] = 'All Departments';
            continue;
        }
        $r['department_ids'] = $map[$cid] ?? [];
        $r['department_names'] = !empty($nameMap[$cid]) ? implode(', ', $nameMap[$cid]) : '';
    }
    unset($r);
}

function fetchCirculars($year = 0, $departmentId = 0)
{
    $conn = getDBConnection();
    ensureCircularTables($conn);

    $sql = "SELECT c.*, u.full_name AS created_by_name
            FROM circulars c
            LEFT JOIN users u ON u.id = c.created_by
            WHERE c.status = 1";
    $params = [];
    $types = '';

    if ($year >= 2000 && $year <= 2100) {
        $sql .= " AND (YEAR(c.circular_date) = ? OR YEAR(c.added_date) = ?)";
        $params[] = $year;
        $params[] = $year;
        $types .= 'ii';
    }

    $departmentId = (int) $departmentId;
    if ($departmentId > 0) {
        $sql .= " AND (
            c.apply_all_departments = 1
            OR EXISTS (
                SELECT 1 FROM circular_departments cd
                WHERE cd.circular_id = c.id AND cd.department_id = ?
            )
        )";
        $params[] = $departmentId;
        $types .= 'i';
    }

    $sql .= " ORDER BY c.circular_date DESC, c.added_date DESC, c.id DESC";

    $rows = [];
    if ($types !== '') {
        $st = $conn->prepare($sql);
        $st->bind_param($types, ...$params);
        $st->execute();
        $res = $st->get_result();
        while ($row = $res->fetch_assoc()) {
            $rows[] = $row;
        }
        $st->close();
    } else {
        $res = $conn->query($sql);
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $rows[] = $row;
            }
        }
    }

    attachCircularDepartments($rows, $conn);
    $conn->close();
    return $rows;
}

function getCircularById($id)
{
    $id = (int) $id;
    if ($id <= 0) {
        return null;
    }

    $conn = getDBConnection();
    ensureCircularTables($conn);

    $st = $conn->prepare(
        "SELECT c.*, u.full_name AS created_by_name
         FROM circulars c
         LEFT JOIN users u ON u.id = c.created_by
         WHERE c.id = ? AND c.status = 1
         LIMIT 1"
    );
    $st->bind_param('i', $id);
    $st->execute();
    $row = $st->get_result()->fetch_assoc() ?: null;
    $st->close();

    if ($row) {
        $rows = [$row];
        attachCircularDepartments($rows, $conn);
        $row = $rows[0];
    }

    $conn->close();
    return $row;
}

/**
 * @return array{ok:bool,id?:int,error?:string}
 */
function saveCircular(array $data, $uploadedFile = null)
{
    $id = (int) ($data['id'] ?? 0);
    $title = trim((string) ($data['title'] ?? ''));
    $circularNo = trim((string) ($data['circular_no'] ?? ''));
    $circularDate = parseDateInput($data['circular_date'] ?? '');
    $addedDate = parseDateInput($data['added_date'] ?? '');
    $remarks = trim((string) ($data['remarks'] ?? ''));
    $userId = (int) ($data['created_by'] ?? ($_SESSION['user_id'] ?? 0));

    $deptNorm = circularNormalizeDepartments(
        $data['apply_all_departments'] ?? 0,
        $data['department_ids'] ?? []
    );
    $applyAll = $deptNorm['apply_all'] ? 1 : 0;
    $departmentIds = $deptNorm['department_ids'];

    if ($title === '') {
        return ['ok' => false, 'error' => 'Title is required.'];
    }
    if (!$circularDate) {
        return ['ok' => false, 'error' => 'Circular date is required (DD-MM-YYYY).'];
    }
    if (!$addedDate) {
        return ['ok' => false, 'error' => 'Added date is required (DD-MM-YYYY).'];
    }
    if (!$applyAll && !$departmentIds) {
        return ['ok' => false, 'error' => 'Select All Departments or at least one department.'];
    }

    $conn = getDBConnection();
    ensureCircularTables($conn);

    $existing = null;
    if ($id > 0) {
        $st = $conn->prepare("SELECT * FROM circulars WHERE id = ? AND status = 1 LIMIT 1");
        $st->bind_param('i', $id);
        $st->execute();
        $existing = $st->get_result()->fetch_assoc() ?: null;
        $st->close();
        if (!$existing) {
            $conn->close();
            return ['ok' => false, 'error' => 'Circular not found.'];
        }
    }

    $pdfPath = $existing['pdf_file'] ?? '';
    $original = $existing['original_filename'] ?? '';
    $oldPdf = $pdfPath;

    $hasUpload = is_array($uploadedFile)
        && (($uploadedFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE);

    if ($hasUpload) {
        $stored = circularStoreUploadedPdf($uploadedFile);
        if (!$stored['ok']) {
            $conn->close();
            return ['ok' => false, 'error' => $stored['error']];
        }
        $pdfPath = $stored['path'];
        $original = $stored['original'];
    } elseif ($id <= 0) {
        $conn->close();
        return ['ok' => false, 'error' => 'Please upload the scanned circular PDF.'];
    }

    if ($circularNo === '') {
        $circularNo = '';
    }
    if ($remarks === '') {
        $remarks = '';
    }
    if ($userId <= 0) {
        $userId = 0;
    }

    if ($id > 0) {
        $st = $conn->prepare(
            "UPDATE circulars
             SET title = ?, circular_no = ?, circular_date = ?, added_date = ?,
                 remarks = ?, pdf_file = ?, original_filename = ?, apply_all_departments = ?
             WHERE id = ? AND status = 1"
        );
        $st->bind_param(
            'sssssssii',
            $title,
            $circularNo,
            $circularDate,
            $addedDate,
            $remarks,
            $pdfPath,
            $original,
            $applyAll,
            $id
        );
        $ok = $st->execute();
        $st->close();

        if (!$ok) {
            $conn->close();
            if ($hasUpload && $pdfPath !== $oldPdf) {
                circularDeleteFile($pdfPath);
            }
            return ['ok' => false, 'error' => 'Could not update circular.'];
        }

        syncCircularDepartments($conn, $id, (bool) $applyAll, $departmentIds);
        // On update, clear old reads so staff get notified again about changes
        $st = $conn->prepare('DELETE FROM circular_reads WHERE circular_id = ?');
        $st->bind_param('i', $id);
        $st->execute();
        $st->close();
        $conn->close();

        if ($hasUpload && $oldPdf !== '' && $oldPdf !== $pdfPath) {
            circularDeleteFile($oldPdf);
        }

        return ['ok' => true, 'id' => $id];
    }

    $st = $conn->prepare(
        "INSERT INTO circulars
            (title, circular_no, circular_date, added_date, remarks, pdf_file, original_filename, apply_all_departments, created_by, status)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)"
    );
    $st->bind_param(
        'sssssssii',
        $title,
        $circularNo,
        $circularDate,
        $addedDate,
        $remarks,
        $pdfPath,
        $original,
        $applyAll,
        $userId
    );
    $ok = $st->execute();
    $newId = (int) $conn->insert_id;
    $st->close();

    if (!$ok || $newId <= 0) {
        $conn->close();
        circularDeleteFile($pdfPath);
        return ['ok' => false, 'error' => 'Could not save circular.'];
    }

    syncCircularDepartments($conn, $newId, (bool) $applyAll, $departmentIds);
    $conn->close();

    return ['ok' => true, 'id' => $newId];
}

/**
 * Soft-delete circular and remove PDF file
 * @return array{ok:bool,error?:string}
 */
function deleteCircular($id)
{
    $id = (int) $id;
    if ($id <= 0) {
        return ['ok' => false, 'error' => 'Invalid circular.'];
    }

    $conn = getDBConnection();
    ensureCircularTables($conn);

    $st = $conn->prepare("SELECT pdf_file FROM circulars WHERE id = ? AND status = 1 LIMIT 1");
    $st->bind_param('i', $id);
    $st->execute();
    $row = $st->get_result()->fetch_assoc() ?: null;
    $st->close();

    if (!$row) {
        $conn->close();
        return ['ok' => false, 'error' => 'Circular not found.'];
    }

    $st = $conn->prepare("UPDATE circulars SET status = 0 WHERE id = ?");
    $st->bind_param('i', $id);
    $ok = $st->execute();
    $st->close();

    if ($ok) {
        $st = $conn->prepare('DELETE FROM circular_departments WHERE circular_id = ?');
        $st->bind_param('i', $id);
        $st->execute();
        $st->close();
        $st = $conn->prepare('DELETE FROM circular_reads WHERE circular_id = ?');
        $st->bind_param('i', $id);
        $st->execute();
        $st->close();
    }

    $conn->close();

    if (!$ok) {
        return ['ok' => false, 'error' => 'Could not delete circular.'];
    }

    circularDeleteFile($row['pdf_file'] ?? '');
    return ['ok' => true];
}

/**
 * Unread circular notifications for logged-in staff
 * @return array<int,array>
 */
function fetchUnreadCircularNotifications($userId, $limit = 12)
{
    $userId = (int) $userId;
    if ($userId <= 0) {
        return [];
    }

    $conn = getDBConnection();
    ensureCircularTables($conn);

    // Portal is HR/Admin only — show all unread circulars in the bell.
    // Department targeting controls list filter / assignment display.
    $sql = "SELECT c.id, c.title, c.circular_no, c.circular_date, c.added_date,
                   c.apply_all_departments, c.created_at
            FROM circulars c
            WHERE c.status = 1
              AND NOT EXISTS (
                  SELECT 1 FROM circular_reads cr
                  WHERE cr.circular_id = c.id AND cr.user_id = ?
              )
            ORDER BY c.created_at DESC, c.id DESC
            LIMIT " . max(1, min(30, (int) $limit));

    $st = $conn->prepare($sql);
    $st->bind_param('i', $userId);
    $st->execute();
    $res = $st->get_result();
    $rows = [];
    while ($row = $res->fetch_assoc()) {
        $rows[] = $row;
    }
    $st->close();
    attachCircularDepartments($rows, $conn);
    $conn->close();
    return $rows;
}

function countUnreadCircularNotifications($userId)
{
    return count(fetchUnreadCircularNotifications($userId, 30));
}

function markCircularRead($circularId, $userId)
{
    $circularId = (int) $circularId;
    $userId = (int) $userId;
    if ($circularId <= 0 || $userId <= 0) {
        return false;
    }

    $conn = getDBConnection();
    ensureCircularTables($conn);
    $st = $conn->prepare(
        "INSERT INTO circular_reads (circular_id, user_id, read_at)
         VALUES (?, ?, NOW())
         ON DUPLICATE KEY UPDATE read_at = NOW()"
    );
    $st->bind_param('ii', $circularId, $userId);
    $ok = $st->execute();
    $st->close();
    $conn->close();
    return (bool) $ok;
}

function markAllCircularsRead($userId)
{
    $userId = (int) $userId;
    if ($userId <= 0) {
        return false;
    }

    $rows = fetchUnreadCircularNotifications($userId, 30);
    foreach ($rows as $r) {
        markCircularRead((int) $r['id'], $userId);
    }
    return true;
}
