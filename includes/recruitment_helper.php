<?php
/**
 * Recruitment — applications, uploads, QR apply URL
 */

if (!function_exists('ensureRecruitmentTables')) {

    function ensureRecruitmentTables($conn = null)
    {
        $close = false;
        if ($conn === null) {
            $conn = getDBConnection();
            $close = true;
        }

        $conn->query(
            "CREATE TABLE IF NOT EXISTS recruitment_applications (
                id INT AUTO_INCREMENT PRIMARY KEY,
                application_no VARCHAR(40) NOT NULL,
                department_id INT DEFAULT NULL,
                designation_id INT DEFAULT NULL,
                department_name VARCHAR(150) NOT NULL DEFAULT '',
                position_name VARCHAR(150) NOT NULL DEFAULT '',
                full_name VARCHAR(150) NOT NULL,
                email VARCHAR(150) NOT NULL DEFAULT '',
                mobile VARCHAR(20) NOT NULL,
                alt_mobile VARCHAR(20) DEFAULT NULL,
                gender VARCHAR(20) DEFAULT NULL,
                dob DATE DEFAULT NULL,
                marital_status VARCHAR(30) DEFAULT NULL,
                address TEXT,
                city VARCHAR(100) DEFAULT NULL,
                state_name VARCHAR(100) DEFAULT NULL,
                pincode VARCHAR(12) DEFAULT NULL,
                current_salary DECIMAL(12,2) DEFAULT NULL,
                expected_salary DECIMAL(12,2) DEFAULT NULL,
                notice_period VARCHAR(80) DEFAULT NULL,
                total_experience VARCHAR(40) DEFAULT NULL,
                bank_statement_file VARCHAR(255) DEFAULT NULL,
                salary_slip_file VARCHAR(255) DEFAULT NULL,
                resume_file VARCHAR(255) DEFAULT NULL,
                status VARCHAR(30) NOT NULL DEFAULT 'new',
                hr_remarks TEXT,
                ip_address VARCHAR(64) DEFAULT NULL,
                user_agent VARCHAR(255) DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_recruitment_app_no (application_no),
                INDEX idx_recruitment_status (status),
                INDEX idx_recruitment_dept (department_id),
                INDEX idx_recruitment_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $conn->query(
            "CREATE TABLE IF NOT EXISTS recruitment_education (
                id INT AUTO_INCREMENT PRIMARY KEY,
                application_id INT NOT NULL,
                degree VARCHAR(150) NOT NULL DEFAULT '',
                institution VARCHAR(200) NOT NULL DEFAULT '',
                year_of_passing VARCHAR(20) DEFAULT NULL,
                percentage VARCHAR(20) DEFAULT NULL,
                specialization VARCHAR(150) DEFAULT NULL,
                sort_order INT NOT NULL DEFAULT 0,
                INDEX idx_rec_edu_app (application_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $conn->query(
            "CREATE TABLE IF NOT EXISTS recruitment_experience (
                id INT AUTO_INCREMENT PRIMARY KEY,
                application_id INT NOT NULL,
                company_name VARCHAR(200) NOT NULL DEFAULT '',
                designation VARCHAR(150) NOT NULL DEFAULT '',
                from_date VARCHAR(40) DEFAULT NULL,
                to_date VARCHAR(40) DEFAULT NULL,
                is_current TINYINT(1) NOT NULL DEFAULT 0,
                last_salary DECIMAL(12,2) DEFAULT NULL,
                responsibilities TEXT,
                sort_order INT NOT NULL DEFAULT 0,
                INDEX idx_rec_exp_app (application_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $conn->query(
            "CREATE TABLE IF NOT EXISTS recruitment_followups (
                id INT AUTO_INCREMENT PRIMARY KEY,
                application_id INT NOT NULL,
                call_no INT NOT NULL DEFAULT 1,
                call_at DATETIME NOT NULL,
                next_followup_at DATE DEFAULT NULL,
                notes TEXT,
                outcome VARCHAR(80) DEFAULT NULL,
                created_by INT DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_rec_fu_app (application_id),
                INDEX idx_rec_fu_next (next_followup_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $conn->query(
            "CREATE TABLE IF NOT EXISTS recruitment_criteria (
                id INT AUTO_INCREMENT PRIMARY KEY,
                position_name VARCHAR(150) NOT NULL DEFAULT '',
                criteria_label VARCHAR(500) NOT NULL,
                answer_type VARCHAR(20) NOT NULL DEFAULT 'yesno',
                sort_order INT NOT NULL DEFAULT 0,
                status TINYINT(1) NOT NULL DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_rec_crit_pos (position_name),
                INDEX idx_rec_crit_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $conn->query(
            "CREATE TABLE IF NOT EXISTS recruitment_application_marks (
                id INT AUTO_INCREMENT PRIMARY KEY,
                application_id INT NOT NULL,
                criteria_id INT DEFAULT NULL,
                criteria_label VARCHAR(500) NOT NULL DEFAULT '',
                is_checked TINYINT(1) NOT NULL DEFAULT 0,
                score_value INT DEFAULT NULL,
                answer_value TEXT,
                remarks TEXT,
                updated_by INT DEFAULT NULL,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_rec_mark_app_label (application_id, criteria_label(191)),
                INDEX idx_rec_mark_app (application_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        // Column upgrades for existing installs
        $critCols = [
            'answer_type' => "VARCHAR(20) NOT NULL DEFAULT 'yesno' AFTER criteria_label",
        ];
        foreach ($critCols as $col => $def) {
            $chk = $conn->query("SHOW COLUMNS FROM recruitment_criteria LIKE '" . $conn->real_escape_string($col) . "'");
            if ($chk && $chk->num_rows === 0) {
                @$conn->query("ALTER TABLE recruitment_criteria ADD COLUMN {$col} {$def}");
            }
        }
        $markCols = [
            'score_value' => 'INT DEFAULT NULL AFTER is_checked',
            'answer_value' => 'TEXT AFTER score_value',
        ];
        foreach ($markCols as $col => $def) {
            $chk = $conn->query("SHOW COLUMNS FROM recruitment_application_marks LIKE '" . $conn->real_escape_string($col) . "'");
            if ($chk && $chk->num_rows === 0) {
                @$conn->query("ALTER TABLE recruitment_application_marks ADD COLUMN {$col} {$def}");
            }
        }
        // Widen remarks if still VARCHAR
        $rm = $conn->query("SHOW COLUMNS FROM recruitment_application_marks LIKE 'remarks'");
        if ($rm && ($rr = $rm->fetch_assoc()) && stripos((string) ($rr['Type'] ?? ''), 'varchar') !== false) {
            @$conn->query('ALTER TABLE recruitment_application_marks MODIFY remarks TEXT');
        }
        $cl = $conn->query("SHOW COLUMNS FROM recruitment_criteria LIKE 'criteria_label'");
        if ($cl && ($cr = $cl->fetch_assoc()) && stripos((string) ($cr['Type'] ?? ''), 'varchar(200)') !== false) {
            @$conn->query('ALTER TABLE recruitment_criteria MODIFY criteria_label VARCHAR(500) NOT NULL');
        }

        // Seed / refresh HR interview matrix (blank position = all positions)
        recruitmentEnsureHrMatrixSeeded($conn);

        $extraCols = [
            'age_years' => "INT DEFAULT NULL AFTER dob",
            'aadhaar_no' => "VARCHAR(20) DEFAULT NULL AFTER pincode",
            'pan_no' => "VARCHAR(20) DEFAULT NULL AFTER aadhaar_no",
            'bank_name' => "VARCHAR(150) DEFAULT NULL AFTER pan_no",
            'bank_account' => "VARCHAR(40) DEFAULT NULL AFTER bank_name",
            'bank_ifsc' => "VARCHAR(20) DEFAULT NULL AFTER bank_account",
            'interview_mode' => "VARCHAR(40) DEFAULT NULL AFTER status",
            'interview_date' => "DATE DEFAULT NULL AFTER interview_mode",
            'interview_notes' => "TEXT AFTER interview_date",
            'awaited_with' => "VARCHAR(40) DEFAULT NULL AFTER interview_notes",
            'not_selected_reason' => "TEXT AFTER awaited_with",
            'offer_generated_at' => "DATETIME DEFAULT NULL AFTER not_selected_reason",
            'selected_at' => "DATETIME DEFAULT NULL AFTER offer_generated_at",
            'next_followup_at' => "DATE DEFAULT NULL AFTER selected_at",
            'last_call_at' => "DATETIME DEFAULT NULL AFTER next_followup_at",
            'call_count' => "INT NOT NULL DEFAULT 0 AFTER last_call_at",
            'employee_id' => "INT DEFAULT NULL AFTER call_count",
        ];
        foreach ($extraCols as $col => $def) {
            $chk = $conn->query("SHOW COLUMNS FROM recruitment_applications LIKE '" . $conn->real_escape_string($col) . "'");
            if ($chk && $chk->num_rows === 0) {
                $conn->query("ALTER TABLE recruitment_applications ADD COLUMN {$col} {$def}");
            }
        }

        // Normalize legacy statuses toward interview flow
        $conn->query("UPDATE recruitment_applications SET status = 'interview' WHERE status IN ('review','shortlisted')");
        $conn->query("UPDATE recruitment_applications SET status = 'not_selected' WHERE status = 'rejected'");
        $conn->query("UPDATE recruitment_applications SET status = 'selected' WHERE status = 'hired'");

        if ($close) {
            $conn->close();
        }
    }

    function recruitmentSqlStr($conn, $value)
    {
        if ($value === null) {
            return 'NULL';
        }
        return "'" . $conn->real_escape_string((string) $value) . "'";
    }

    function recruitmentSqlNum($value)
    {
        if ($value === null || $value === '') {
            return 'NULL';
        }
        return (string) (float) $value;
    }

    function recruitmentSqlIntOrNull($value)
    {
        $value = (int) $value;
        return $value > 0 ? (string) $value : 'NULL';
    }

    function recruitmentApplyUrl()
    {
        $path = 'recruitment/apply.php';

        // Optional override in .env: RECRUITMENT_PUBLIC_URL=https://armor-hrms.oceanhub.co.in/recruitment/apply.php
        $override = trim((string) (function_exists('env') ? env('RECRUITMENT_PUBLIC_URL', '') : ''));
        if ($override !== '') {
            return rtrim($override, '/');
        }

        $liveBase = 'https://armor-hrms.oceanhub.co.in';
        $appUrl = defined('APP_URL') ? rtrim((string) APP_URL, '/') : '';
        $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
        $isLocalHost = (
            strpos($host, 'localhost') !== false
            || strpos($host, '127.0.0.1') !== false
            || $appUrl === ''
            || stripos($appUrl, 'localhost') !== false
            || stripos($appUrl, '127.0.0.1') !== false
        );

        // Fixed QR for phones must use live public URL (not localhost)
        if ($isLocalHost) {
            return $liveBase . '/' . $path;
        }

        if ($appUrl !== '') {
            return $appUrl . '/' . $path;
        }

        return app_full_url($path);
    }

    function recruitmentUploadDir()
    {
        return dirname(__DIR__) . '/assets/uploads/recruitment';
    }

    function recruitmentPublicPath($relative)
    {
        $relative = trim((string) $relative);
        if ($relative === '' || strpos($relative, 'assets/uploads/recruitment/') !== 0 || strpos($relative, '..') !== false) {
            return '';
        }
        return app_url($relative);
    }

    function recruitmentGenerateAppNo($conn)
    {
        $prefix = 'REC-' . date('Ymd') . '-';
        $like = $prefix . '%';
        $st = $conn->prepare(
            'SELECT application_no FROM recruitment_applications
             WHERE application_no LIKE ?
             ORDER BY id DESC LIMIT 1'
        );
        $st->bind_param('s', $like);
        $st->execute();
        $row = $st->get_result()->fetch_assoc();
        $st->close();
        $next = 1;
        if ($row && preg_match('/-(\d+)$/', (string) $row['application_no'], $m)) {
            $next = (int) $m[1] + 1;
        }
        return $prefix . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    function recruitmentUploadFile($fileKey, $prefix = 'doc')
    {
        if (empty($_FILES[$fileKey]) || !is_array($_FILES[$fileKey])) {
            return ['ok' => true, 'path' => ''];
        }
        $file = $_FILES[$fileKey];
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return ['ok' => true, 'path' => ''];
        }
        if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            return ['ok' => false, 'error' => 'Upload failed. Please try again.'];
        }
        $size = (int) ($file['size'] ?? 0);
        if ($size <= 0 || $size > 5 * 1024 * 1024) {
            return ['ok' => false, 'error' => 'Each file must be under 5 MB.'];
        }
        $ext = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
        $allowed = ['pdf', 'jpg', 'jpeg', 'png', 'webp'];
        if (!in_array($ext, $allowed, true)) {
            return ['ok' => false, 'error' => 'Allowed file types: PDF, JPG, PNG, WEBP.'];
        }
        $dir = recruitmentUploadDir();
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            return ['ok' => false, 'error' => 'Could not create upload folder.'];
        }
        $safe = preg_replace('/[^a-zA-Z0-9_-]/', '', $prefix)
            . '_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $dest = $dir . DIRECTORY_SEPARATOR . $safe;
        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            return ['ok' => false, 'error' => 'Could not save uploaded file.'];
        }
        return ['ok' => true, 'path' => 'assets/uploads/recruitment/' . $safe];
    }

    function recruitmentFormatExperienceMonths($months)
    {
        $months = max(0, (int) $months);
        if ($months <= 0) {
            return '';
        }
        $y = intdiv($months, 12);
        $m = $months % 12;
        $parts = [];
        if ($y > 0) {
            $parts[] = $y . ($y === 1 ? ' year' : ' years');
        }
        if ($m > 0) {
            $parts[] = $m . ($m === 1 ? ' month' : ' months');
        }
        return implode(' ', $parts);
    }

    /**
     * Sum company-wise From/To (YYYY-MM). Current job uses today's month.
     */
    function recruitmentCalculateTotalExperience(array $experience)
    {
        $total = 0;
        $nowYm = date('Y-m');
        foreach ($experience as $ex) {
            $from = trim((string) ($ex['from_date'] ?? ''));
            $to = trim((string) ($ex['to_date'] ?? ''));
            if (!empty($ex['is_current'])) {
                $to = $nowYm;
            }
            if (!preg_match('/^\d{4}-\d{2}$/', $from) || !preg_match('/^\d{4}-\d{2}$/', $to)) {
                continue;
            }
            [$fy, $fm] = array_map('intval', explode('-', $from));
            [$ty, $tm] = array_map('intval', explode('-', $to));
            $diff = ($ty - $fy) * 12 + ($tm - $fm);
            if ($diff < 0) {
                continue;
            }
            $total += ($diff === 0 ? 1 : $diff);
        }
        return recruitmentFormatExperienceMonths($total);
    }

    function recruitmentStatusLabels()
    {
        return [
            'new' => 'New Application',
            'interview' => 'Interview',
            'awaited' => 'Awaited',
            'selected' => 'Selected',
            'not_selected' => 'Not Selected',
            // legacy aliases (display only if old rows remain)
            'review' => 'Interview',
            'shortlisted' => 'Awaited',
            'rejected' => 'Not Selected',
            'hired' => 'Selected',
        ];
    }

    function recruitmentInterviewModes()
    {
        return [
            'in_house' => 'In-house',
            'in_person' => 'In-person',
            'virtual' => 'Virtual',
        ];
    }

    function recruitmentAwaitedSides()
    {
        return [
            'hr' => 'HR',
            'management' => 'Management',
            'employee' => 'Employee side',
        ];
    }

    /**
     * HR Round interview matrix (reference checklist).
     * type: yesno | score | text
     */
    function recruitmentDefaultHrCriteria()
    {
        return [
            ['label' => 'Candidate behavior proper during interview?', 'type' => 'yesno'],
            ['label' => 'English communication skill (out of 10)', 'type' => 'score'],
            ['label' => 'Candidate has worked anywhere as team member / leader previously (social / college / any company)?', 'type' => 'yesno'],
            ['label' => 'Candidate has made new initiate / innovation / change into previous / current company, which is beneficial to him / her / company?', 'type' => 'text'],
            ['label' => 'What is important when candidate doing work — work or money?', 'type' => 'text'],
            ['label' => 'Any self-learning done last 1 month?', 'type' => 'yesno'],
            ['label' => 'Candidate has deep learning habits?', 'type' => 'yesno'],
            ['label' => 'What is important from fundamental / framework for candidate? (Yes = fundamental)', 'type' => 'yesno'],
            ['label' => 'Is candidate 5 years personal / professional goals defined?', 'type' => 'yesno'],
            ['label' => 'Why candidate planning to work in Rajkot / Ahmedabad?', 'type' => 'text'],
            ['label' => 'What family member (father, mother, brother, sister) background of candidate?', 'type' => 'text'],
            ['label' => 'Do candidate / family member has any health issue (admitted into hospital in last 3 years)?', 'type' => 'text'],
            ['label' => 'Candidate from outside city — how do they manage office / family life?', 'type' => 'text'],
            ['label' => 'Candidate want to looking for master degree?', 'type' => 'yesno'],
            ['label' => 'Candidate want to looking for government job?', 'type' => 'yesno'],
            ['label' => 'Candidate want to move into foreign in future?', 'type' => 'yesno'],
            ['label' => 'If married female candidate — any short term family planning?', 'type' => 'text'],
            ['label' => 'If unmarried female candidate — when plan for marriage? If short term plan then Rajkot or other city?', 'type' => 'text'],
            ['label' => 'Is candidate doing study / internal then want to appear into campus interview?', 'type' => 'yesno'],
            ['label' => 'What is candidate strength?', 'type' => 'text'],
            ['label' => 'What is candidate weakness?', 'type' => 'text'],
            ['label' => 'Why we hire you?', 'type' => 'text'],
            ['label' => 'Any reference received for interview / how appear into company?', 'type' => 'text'],
            ['label' => 'Reason to Change', 'type' => 'text'],
        ];
    }

    /**
     * Ensure global HR matrix is seeded. Replaces old short checklist once.
     */
    function recruitmentEnsureHrMatrixSeeded($conn)
    {
        $hasMatrix = false;
        $st = @$conn->query("SELECT id FROM recruitment_criteria WHERE position_name = '' AND criteria_label LIKE 'Reason to Change%' LIMIT 1");
        if ($st && $st->num_rows > 0) {
            $hasMatrix = true;
        }
        if ($hasMatrix) {
            // Keep answer_type in sync for existing matrix rows
            foreach (recruitmentDefaultHrCriteria() as $item) {
                $label = $item['label'];
                $type = $item['type'];
                $up = $conn->prepare(
                    "UPDATE recruitment_criteria SET answer_type = ? WHERE position_name = '' AND criteria_label = ?"
                );
                if ($up) {
                    $up->bind_param('ss', $type, $label);
                    $up->execute();
                    $up->close();
                }
            }
            return;
        }

        // Remove old global seed (Confidence / Communication Skills checklist)
        $conn->query("DELETE FROM recruitment_criteria WHERE position_name = ''");

        $ins = $conn->prepare(
            'INSERT INTO recruitment_criteria (position_name, criteria_label, answer_type, sort_order, status) VALUES (\'\', ?, ?, ?, 1)'
        );
        if (!$ins) {
            return;
        }
        $ord = 1;
        foreach (recruitmentDefaultHrCriteria() as $item) {
            $label = $item['label'];
            $type = $item['type'];
            $ins->bind_param('ssi', $label, $type, $ord);
            $ins->execute();
            $ord++;
        }
        $ins->close();
    }

    /**
     * Criteria for a position: global (blank) + matching position_name.
     */
    function getRecruitmentCriteriaForPosition($positionName, $conn = null)
    {
        $close = false;
        if ($conn === null) {
            $conn = getDBConnection();
            $close = true;
        }
        ensureRecruitmentTables($conn);
        $positionName = trim((string) $positionName);
        $rows = [];
        $st = $conn->prepare(
            "SELECT * FROM recruitment_criteria
             WHERE status = 1 AND (position_name = '' OR position_name = ?)
             ORDER BY (position_name = '') DESC, sort_order ASC, id ASC"
        );
        $st->bind_param('s', $positionName);
        $st->execute();
        $res = $st->get_result();
        while ($r = $res->fetch_assoc()) {
            if (empty($r['answer_type'])) {
                $r['answer_type'] = 'yesno';
            }
            $rows[] = $r;
        }
        $st->close();
        if ($close) {
            $conn->close();
        }
        return $rows;
    }

    function getRecruitmentApplicationMarks($applicationId, $conn = null)
    {
        $close = false;
        if ($conn === null) {
            $conn = getDBConnection();
            $close = true;
        }
        ensureRecruitmentTables($conn);
        $applicationId = (int) $applicationId;
        $map = [];
        $st = $conn->prepare(
            'SELECT * FROM recruitment_application_marks WHERE application_id = ?'
        );
        $st->bind_param('i', $applicationId);
        $st->execute();
        $res = $st->get_result();
        while ($r = $res->fetch_assoc()) {
            $key = strtolower(trim((string) $r['criteria_label']));
            $map[$key] = $r;
        }
        $st->close();
        if ($close) {
            $conn->close();
        }
        return $map;
    }

    /**
     * Save marks. $items = [ label, criteria_id, type, checked, score, answer, remarks ]
     */
    function saveRecruitmentApplicationMarks($applicationId, array $items, $userId = 0, $conn = null)
    {
        $close = false;
        if ($conn === null) {
            $conn = getDBConnection();
            $close = true;
        }
        ensureRecruitmentTables($conn);
        $applicationId = (int) $applicationId;
        $userId = (int) $userId;

        foreach ($items as $item) {
            $label = trim((string) ($item['label'] ?? ''));
            if ($label === '') {
                continue;
            }
            $type = trim((string) ($item['type'] ?? 'yesno'));
            $criteriaId = (int) ($item['criteria_id'] ?? 0);
            $remarks = trim((string) ($item['remarks'] ?? ''));
            $answer = trim((string) ($item['answer'] ?? ''));
            $score = isset($item['score']) && $item['score'] !== '' ? (int) $item['score'] : null;
            $checked = 0;

            if ($type === 'yesno') {
                if ($answer === 'Yes' || $answer === '1' || !empty($item['checked'])) {
                    $checked = 1;
                    $answer = 'Yes';
                } elseif ($answer === 'No' || (isset($item['checked']) && $item['checked'] === '0')) {
                    $checked = 0;
                    $answer = 'No';
                } elseif ($answer === '') {
                    // leave unanswered
                }
            } elseif ($type === 'score') {
                if ($score !== null) {
                    $answer = (string) $score;
                    $checked = $score > 0 ? 1 : 0;
                }
            } else {
                // text
                if ($answer === '' && $remarks !== '') {
                    $answer = $remarks;
                }
                $checked = $answer !== '' ? 1 : 0;
            }

            $scoreSql = ($score !== null) ? (string) (int) $score : 'NULL';
            $sql = 'INSERT INTO recruitment_application_marks
                (application_id, criteria_id, criteria_label, is_checked, score_value, answer_value, remarks, updated_by)
                VALUES (' .
                $applicationId . ',' .
                ($criteriaId > 0 ? $criteriaId : 'NULL') . ',' .
                recruitmentSqlStr($conn, $label) . ',' .
                $checked . ',' .
                $scoreSql . ',' .
                recruitmentSqlStr($conn, $answer) . ',' .
                recruitmentSqlStr($conn, $remarks) . ',' .
                ($userId > 0 ? $userId : 'NULL') .
            ') ON DUPLICATE KEY UPDATE
                is_checked = VALUES(is_checked),
                score_value = VALUES(score_value),
                answer_value = VALUES(answer_value),
                remarks = VALUES(remarks),
                criteria_id = VALUES(criteria_id),
                updated_by = VALUES(updated_by)';
            $conn->query($sql);
        }

        if ($close) {
            $conn->close();
        }
        return ['ok' => true];
    }

    /**
     * Add a custom / position criteria row.
     */
    function addRecruitmentCriteria($positionName, $label, $conn = null, $answerType = 'yesno')
    {
        $close = false;
        if ($conn === null) {
            $conn = getDBConnection();
            $close = true;
        }
        ensureRecruitmentTables($conn);
        $positionName = trim((string) $positionName);
        $label = trim((string) $label);
        $answerType = in_array($answerType, ['yesno', 'score', 'text'], true) ? $answerType : 'yesno';
        if ($label === '') {
            if ($close) {
                $conn->close();
            }
            return ['ok' => false, 'error' => 'Criteria label required'];
        }
        $st = $conn->prepare(
            'SELECT id FROM recruitment_criteria WHERE position_name = ? AND criteria_label = ? LIMIT 1'
        );
        $st->bind_param('ss', $positionName, $label);
        $st->execute();
        $ex = $st->get_result()->fetch_assoc();
        $st->close();
        if ($ex) {
            $id = (int) $ex['id'];
            if ($close) {
                $conn->close();
            }
            return ['ok' => true, 'id' => $id, 'exists' => true];
        }
        $ord = 100;
        $mx = $conn->query('SELECT COALESCE(MAX(sort_order),0) AS m FROM recruitment_criteria')->fetch_assoc();
        $ord = (int) ($mx['m'] ?? 0) + 1;
        $ins = $conn->prepare(
            'INSERT INTO recruitment_criteria (position_name, criteria_label, answer_type, sort_order, status) VALUES (?, ?, ?, ?, 1)'
        );
        $ins->bind_param('sssi', $positionName, $label, $answerType, $ord);
        $ok = $ins->execute();
        $id = (int) $ins->insert_id;
        $err = $ins->error;
        $ins->close();
        if ($close) {
            $conn->close();
        }
        return $ok ? ['ok' => true, 'id' => $id] : ['ok' => false, 'error' => $err];
    }

    function recruitmentFormatMarkAnswer(array $mark, $type = 'yesno')
    {
        $type = $type ?: 'yesno';
        $answer = trim((string) ($mark['answer_value'] ?? ''));
        if ($type === 'score') {
            if ($answer !== '') {
                return $answer . ' / 10';
            }
            if (isset($mark['score_value']) && $mark['score_value'] !== null && $mark['score_value'] !== '') {
                return ((int) $mark['score_value']) . ' / 10';
            }
            return '—';
        }
        if ($type === 'yesno') {
            if ($answer === 'Yes' || $answer === 'No') {
                return $answer;
            }
            if ($answer === '' && array_key_exists('is_checked', $mark)) {
                // legacy checkbox-only rows without explicit No
                return !empty($mark['is_checked']) ? 'Yes' : '—';
            }
            return $answer !== '' ? $answer : '—';
        }
        if ($answer !== '') {
            return $answer;
        }
        $remarks = trim((string) ($mark['remarks'] ?? ''));
        return $remarks !== '' ? $remarks : '—';
    }

    function recruitmentCalcAgeYears($dob)
    {
        $dob = trim((string) $dob);
        if ($dob === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dob)) {
            return null;
        }
        try {
            $birth = new DateTime($dob);
            $now = new DateTime('today');
            if ($birth > $now) {
                return null;
            }
            return (int) $birth->diff($now)->y;
        } catch (Throwable $e) {
            return null;
        }
    }

    function recruitmentFollowups($applicationId, $conn = null)
    {
        $close = false;
        if ($conn === null) {
            $conn = getDBConnection();
            $close = true;
        }
        ensureRecruitmentTables($conn);
        $applicationId = (int) $applicationId;
        $rows = [];
        $st = $conn->prepare(
            'SELECT * FROM recruitment_followups WHERE application_id = ? ORDER BY call_no ASC, id ASC'
        );
        $st->bind_param('i', $applicationId);
        $st->execute();
        $res = $st->get_result();
        while ($r = $res->fetch_assoc()) {
            $rows[] = $r;
        }
        $st->close();
        if ($close) {
            $conn->close();
        }
        return $rows;
    }

    function recruitmentIsFollowupDue(array $row)
    {
        if (($row['status'] ?? '') !== 'awaited') {
            return false;
        }
        $next = trim((string) ($row['next_followup_at'] ?? ''));
        if ($next === '') {
            return false;
        }
        return $next <= date('Y-m-d');
    }

    function saveRecruitmentInterviewUpdate($id, array $data, $userId = 0, $conn = null)
    {
        $close = false;
        if ($conn === null) {
            $conn = getDBConnection();
            $close = true;
        }
        ensureRecruitmentTables($conn);
        $id = (int) $id;
        $status = trim((string) ($data['status'] ?? 'interview'));
        $allowed = ['new', 'interview', 'awaited', 'selected', 'not_selected'];
        if (!in_array($status, $allowed, true)) {
            if ($close) {
                $conn->close();
            }
            return ['ok' => false, 'error' => 'Invalid status'];
        }

        $mode = trim((string) ($data['interview_mode'] ?? ''));
        $interviewDate = trim((string) ($data['interview_date'] ?? ''));
        $notes = trim((string) ($data['interview_notes'] ?? ''));
        $awaitedWith = trim((string) ($data['awaited_with'] ?? ''));
        $notReason = trim((string) ($data['not_selected_reason'] ?? ''));
        $hrRemarks = trim((string) ($data['hr_remarks'] ?? ''));

        if ($status === 'not_selected' && $notReason === '') {
            if ($close) {
                $conn->close();
            }
            return ['ok' => false, 'error' => 'Please enter reason for Not Selected.'];
        }
        if ($status === 'awaited' && $awaitedWith === '') {
            if ($close) {
                $conn->close();
            }
            return ['ok' => false, 'error' => 'Please select Awaited with (HR / Management / Employee).'];
        }

        $sets = [
            'status = ' . recruitmentSqlStr($conn, $status),
            'interview_mode = ' . recruitmentSqlStr($conn, $mode),
            'interview_date = ' . ($interviewDate !== '' ? recruitmentSqlStr($conn, $interviewDate) : 'NULL'),
            'interview_notes = ' . recruitmentSqlStr($conn, $notes),
            'awaited_with = ' . recruitmentSqlStr($conn, $awaitedWith),
            'not_selected_reason = ' . recruitmentSqlStr($conn, $notReason),
            'hr_remarks = ' . recruitmentSqlStr($conn, $hrRemarks),
        ];

        if ($status === 'awaited') {
            // Default 6-day reminder from today if not already set / refresh on status change
            $sets[] = 'next_followup_at = DATE_ADD(CURDATE(), INTERVAL 6 DAY)';
        }
        if ($status === 'selected') {
            $sets[] = 'selected_at = IFNULL(selected_at, NOW())';
            $sets[] = 'awaited_with = NULL';
            $sets[] = 'not_selected_reason = ' . recruitmentSqlStr($conn, '');
        }
        if ($status === 'not_selected') {
            $sets[] = 'awaited_with = NULL';
            $sets[] = 'next_followup_at = NULL';
        }

        $ok = $conn->query(
            'UPDATE recruitment_applications SET ' . implode(', ', $sets) . ' WHERE id = ' . $id
        );
        $err = $ok ? '' : $conn->error;
        if ($close) {
            $conn->close();
        }
        return $ok ? ['ok' => true] : ['ok' => false, 'error' => $err ?: 'Update failed'];
    }

    function saveRecruitmentFollowup($applicationId, array $data, $userId = 0, $conn = null)
    {
        $close = false;
        if ($conn === null) {
            $conn = getDBConnection();
            $close = true;
        }
        ensureRecruitmentTables($conn);
        $applicationId = (int) $applicationId;
        $notes = trim((string) ($data['notes'] ?? ''));
        $outcome = trim((string) ($data['outcome'] ?? ''));
        $callAt = trim((string) ($data['call_at'] ?? ''));
        if ($callAt === '') {
            $callAt = date('Y-m-d H:i:s');
        } elseif (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}/', $callAt)) {
            $callAt = str_replace('T', ' ', substr($callAt, 0, 16)) . ':00';
        }
        $next = trim((string) ($data['next_followup_at'] ?? ''));
        if ($next === '') {
            $next = date('Y-m-d', strtotime('+6 days'));
        }

        $st = $conn->prepare('SELECT COALESCE(MAX(call_no), 0) AS m FROM recruitment_followups WHERE application_id = ?');
        $st->bind_param('i', $applicationId);
        $st->execute();
        $callNo = (int) ($st->get_result()->fetch_assoc()['m'] ?? 0) + 1;
        $st->close();

        $userId = (int) $userId;
        $sql = 'INSERT INTO recruitment_followups
            (application_id, call_no, call_at, next_followup_at, notes, outcome, created_by)
            VALUES (' .
            $applicationId . ',' .
            $callNo . ',' .
            recruitmentSqlStr($conn, $callAt) . ',' .
            recruitmentSqlStr($conn, $next) . ',' .
            recruitmentSqlStr($conn, $notes) . ',' .
            recruitmentSqlStr($conn, $outcome) . ',' .
            ($userId > 0 ? $userId : 'NULL') .
        ')';
        if (!$conn->query($sql)) {
            $err = $conn->error;
            if ($close) {
                $conn->close();
            }
            return ['ok' => false, 'error' => $err ?: 'Could not save follow-up'];
        }

        $conn->query(
            'UPDATE recruitment_applications SET
                last_call_at = ' . recruitmentSqlStr($conn, $callAt) . ',
                next_followup_at = ' . recruitmentSqlStr($conn, $next) . ',
                call_count = ' . (int) $callNo . '
             WHERE id = ' . $applicationId
        );

        if ($close) {
            $conn->close();
        }
        return ['ok' => true, 'call_no' => $callNo];
    }

    function getRecruitmentApplication($id, $conn = null)
    {
        $close = false;
        if ($conn === null) {
            $conn = getDBConnection();
            $close = true;
        }
        ensureRecruitmentTables($conn);
        $id = (int) $id;
        $st = $conn->prepare('SELECT * FROM recruitment_applications WHERE id = ? LIMIT 1');
        $st->bind_param('i', $id);
        $st->execute();
        $row = $st->get_result()->fetch_assoc();
        $st->close();
        if (!$row) {
            if ($close) {
                $conn->close();
            }
            return null;
        }

        $edu = [];
        $st = $conn->prepare(
            'SELECT * FROM recruitment_education WHERE application_id = ? ORDER BY sort_order ASC, id ASC'
        );
        $st->bind_param('i', $id);
        $st->execute();
        $res = $st->get_result();
        while ($r = $res->fetch_assoc()) {
            $edu[] = $r;
        }
        $st->close();

        $exp = [];
        $st = $conn->prepare(
            'SELECT * FROM recruitment_experience WHERE application_id = ? ORDER BY sort_order ASC, id ASC'
        );
        $st->bind_param('i', $id);
        $st->execute();
        $res = $st->get_result();
        while ($r = $res->fetch_assoc()) {
            $exp[] = $r;
        }
        $st->close();

        $row['education'] = $edu;
        $row['experience'] = $exp;
        $row['followups'] = recruitmentFollowups($id, $conn);
        if (empty($row['age_years']) && !empty($row['dob'])) {
            $row['age_years'] = recruitmentCalcAgeYears($row['dob']);
        }
        if ($close) {
            $conn->close();
        }
        return $row;
    }

    function saveRecruitmentApplication(array $data, array $education, array $experience, $conn = null)
    {
        $close = false;
        if ($conn === null) {
            $conn = getDBConnection();
            $close = true;
        }
        ensureRecruitmentTables($conn);
        $appNo = recruitmentGenerateAppNo($conn);

        $sql = 'INSERT INTO recruitment_applications (
            application_no, department_id, designation_id, department_name, position_name,
            full_name, email, mobile, alt_mobile, gender, dob, age_years, marital_status,
            address, city, state_name, pincode,
            aadhaar_no, pan_no, bank_name, bank_account, bank_ifsc,
            current_salary, expected_salary, notice_period, total_experience,
            bank_statement_file, salary_slip_file, resume_file,
            status, ip_address, user_agent
        ) VALUES (' .
            recruitmentSqlStr($conn, $appNo) . ',' .
            recruitmentSqlIntOrNull($data['department_id'] ?? 0) . ',' .
            recruitmentSqlIntOrNull($data['designation_id'] ?? 0) . ',' .
            recruitmentSqlStr($conn, $data['department_name'] ?? '') . ',' .
            recruitmentSqlStr($conn, $data['position_name'] ?? '') . ',' .
            recruitmentSqlStr($conn, $data['full_name'] ?? '') . ',' .
            recruitmentSqlStr($conn, $data['email'] ?? '') . ',' .
            recruitmentSqlStr($conn, $data['mobile'] ?? '') . ',' .
            recruitmentSqlStr($conn, $data['alt_mobile'] ?? '') . ',' .
            recruitmentSqlStr($conn, $data['gender'] ?? '') . ',' .
            (!empty($data['dob']) ? recruitmentSqlStr($conn, $data['dob']) : 'NULL') . ',' .
            (isset($data['age_years']) && $data['age_years'] !== null && $data['age_years'] !== ''
                ? (string) (int) $data['age_years'] : 'NULL') . ',' .
            recruitmentSqlStr($conn, $data['marital_status'] ?? '') . ',' .
            recruitmentSqlStr($conn, $data['address'] ?? '') . ',' .
            recruitmentSqlStr($conn, $data['city'] ?? '') . ',' .
            recruitmentSqlStr($conn, $data['state_name'] ?? '') . ',' .
            recruitmentSqlStr($conn, $data['pincode'] ?? '') . ',' .
            recruitmentSqlStr($conn, $data['aadhaar_no'] ?? '') . ',' .
            recruitmentSqlStr($conn, $data['pan_no'] ?? '') . ',' .
            recruitmentSqlStr($conn, $data['bank_name'] ?? '') . ',' .
            recruitmentSqlStr($conn, $data['bank_account'] ?? '') . ',' .
            recruitmentSqlStr($conn, $data['bank_ifsc'] ?? '') . ',' .
            recruitmentSqlNum($data['current_salary'] ?? null) . ',' .
            recruitmentSqlNum($data['expected_salary'] ?? null) . ',' .
            recruitmentSqlStr($conn, $data['notice_period'] ?? '') . ',' .
            recruitmentSqlStr($conn, $data['total_experience'] ?? '') . ',' .
            recruitmentSqlStr($conn, $data['bank_statement_file'] ?? '') . ',' .
            recruitmentSqlStr($conn, $data['salary_slip_file'] ?? '') . ',' .
            recruitmentSqlStr($conn, $data['resume_file'] ?? '') . ',' .
            recruitmentSqlStr($conn, 'new') . ',' .
            recruitmentSqlStr($conn, $data['ip_address'] ?? '') . ',' .
            recruitmentSqlStr($conn, substr((string) ($data['user_agent'] ?? ''), 0, 255)) .
        ')';

        if (!$conn->query($sql)) {
            $err = $conn->error;
            if ($close) {
                $conn->close();
            }
            return ['ok' => false, 'error' => $err ?: 'Could not save application.'];
        }

        $appId = (int) $conn->insert_id;
        $i = 0;
        foreach ($education as $ed) {
            $degree = trim((string) ($ed['degree'] ?? ''));
            $inst = trim((string) ($ed['institution'] ?? ''));
            if ($degree === '' && $inst === '') {
                continue;
            }
            $conn->query(
                'INSERT INTO recruitment_education
                 (application_id, degree, institution, year_of_passing, percentage, specialization, sort_order)
                 VALUES (' .
                    (int) $appId . ',' .
                    recruitmentSqlStr($conn, $degree) . ',' .
                    recruitmentSqlStr($conn, $inst) . ',' .
                    recruitmentSqlStr($conn, $ed['year_of_passing'] ?? '') . ',' .
                    recruitmentSqlStr($conn, $ed['percentage'] ?? '') . ',' .
                    recruitmentSqlStr($conn, $ed['specialization'] ?? '') . ',' .
                    (int) $i .
                ')'
            );
            $i++;
        }

        $j = 0;
        foreach ($experience as $ex) {
            $cname = trim((string) ($ex['company_name'] ?? ''));
            $desig = trim((string) ($ex['designation'] ?? ''));
            if ($cname === '' && $desig === '') {
                continue;
            }
            $conn->query(
                'INSERT INTO recruitment_experience
                 (application_id, company_name, designation, from_date, to_date, is_current, last_salary, responsibilities, sort_order)
                 VALUES (' .
                    (int) $appId . ',' .
                    recruitmentSqlStr($conn, $cname) . ',' .
                    recruitmentSqlStr($conn, $desig) . ',' .
                    recruitmentSqlStr($conn, $ex['from_date'] ?? '') . ',' .
                    recruitmentSqlStr($conn, $ex['to_date'] ?? '') . ',' .
                    (!empty($ex['is_current']) ? 1 : 0) . ',' .
                    recruitmentSqlNum($ex['last_salary'] ?? null) . ',' .
                    recruitmentSqlStr($conn, $ex['responsibilities'] ?? '') . ',' .
                    (int) $j .
                ')'
            );
            $j++;
        }

        if ($close) {
            $conn->close();
        }
        return ['ok' => true, 'id' => $appId, 'application_no' => $appNo];
    }

    function updateRecruitmentStatus($id, $status, $remarks = '', $conn = null)
    {
        $labels = recruitmentStatusLabels();
        if (!isset($labels[$status])) {
            return ['ok' => false, 'error' => 'Invalid status'];
        }
        $close = false;
        if ($conn === null) {
            $conn = getDBConnection();
            $close = true;
        }
        ensureRecruitmentTables($conn);
        $id = (int) $id;
        $st = $conn->prepare(
            'UPDATE recruitment_applications SET status = ?, hr_remarks = ? WHERE id = ?'
        );
        $st->bind_param('ssi', $status, $remarks, $id);
        $ok = $st->execute();
        $err = $st->error;
        $st->close();
        if ($close) {
            $conn->close();
        }
        return $ok ? ['ok' => true] : ['ok' => false, 'error' => $err];
    }
}
