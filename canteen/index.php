<?php
/**
 * Canteen Meal List — bookings for a date, department-wise + employee-wise.
 * ?export=print&layout=full|summary|dept_pages → A4 letterhead (Print / Save as PDF)
 * ?export=excel&layout=full|summary            → letterhead Excel
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permission_helper.php';
require_once __DIR__ . '/../includes/department_head_helper.php';
require_once __DIR__ . '/../includes/settings.php';
require_once __DIR__ . '/../includes/document_print.php';
require_once __DIR__ . '/../includes/canteen_helper.php';

requireLogin();
$canView = isAdmin() || isHR() || (canAccess('employees', 'view') && !(function_exists('isOfficeStaffRole') && isOfficeStaffRole()));
if (!$canView) {
    header('Location: ' . app_url('dashboard.php'));
    exit;
}

$mealDate = (string) ($_GET['date'] ?? '');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $mealDate) || !strtotime($mealDate)) {
    $mealDate = canteenTargetDate();
}
$deptId = (int) ($_GET['department_id'] ?? 0);
$mealFilter = (string) ($_GET['meal'] ?? '');
if (!in_array($mealFilter, ['breakfast', 'lunch', 'dinner'], true)) {
    $mealFilter = '';
}
$q = trim((string) ($_GET['q'] ?? ''));
$exportMode = (string) ($_GET['export'] ?? '');
$layout = (string) ($_GET['layout'] ?? 'full');
if (!in_array($layout, ['full', 'summary', 'dept_pages'], true)) {
    $layout = 'full';
}

$allowedDepts = isAdmin() || isHR() ? null : allowedDepartmentsFor('employees', 'view');

$conn = getDBConnection();
ensureCanteenTables($conn);

$departments = [];
$dres = $conn->query('SELECT id, department_name FROM departments WHERE status = 1 ORDER BY sort_order ASC, department_name ASC');
if ($dres) {
    while ($r = $dres->fetch_assoc()) {
        if (is_array($allowedDepts) && !in_array((int) $r['id'], array_map('intval', $allowedDepts), true)) {
            continue;
        }
        $departments[] = $r;
    }
}
if ($deptId > 0 && is_array($allowedDepts) && !in_array($deptId, array_map('intval', $allowedDepts), true)) {
    $deptId = 0;
}

if (empty($_SESSION['canteen_admin_token'])) {
    $_SESSION['canteen_admin_token'] = bin2hex(random_bytes(16));
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_guest') {
    $flash = 'Could not delete the entry.';
    if (hash_equals((string) $_SESSION['canteen_admin_token'], (string) ($_POST['token'] ?? ''))) {
        $gid = (int) ($_POST['guest_id'] ?? 0);
        $chk = $conn->prepare('SELECT department_id FROM canteen_guest_orders WHERE id = ?');
        $chk->bind_param('i', $gid);
        $chk->execute();
        $gRow = $chk->get_result()->fetch_assoc();
        $chk->close();
        $deptOk = $gRow && (!is_array($allowedDepts) || in_array((int) $gRow['department_id'], array_map('intval', $allowedDepts), true));
        if ($deptOk && canteenDeleteGuestOrder($conn, $gid)) {
            $flash = 'Guest / trainee entry deleted.';
        }
    }
    $_SESSION['canteen_admin_flash'] = $flash;
    $conn->close();
    header('Location: ' . app_url('canteen/index.php?' . http_build_query(['date' => $mealDate, 'department_id' => $deptId, 'meal' => $mealFilter, 'q' => $q, 'tab' => 'guest'])));
    exit;
}
$flashMsg = (string) ($_SESSION['canteen_admin_flash'] ?? '');
unset($_SESSION['canteen_admin_flash']);

$rows = canteenOrdersForDate($conn, $mealDate, $deptId, $allowedDepts);
$guestRows = canteenGuestOrdersForDate($conn, $mealDate, $deptId, $allowedDepts);
$conn->close();

if ($mealFilter !== '' || $q !== '') {
    $needle = function_exists('mb_strtolower') ? mb_strtolower($q) : strtolower($q);
    $keep = static function ($r, $hay) use ($mealFilter, $needle) {
        if ($mealFilter !== '' && (int) $r[$mealFilter] !== 1) {
            return false;
        }
        if ($needle !== '') {
            $hay = function_exists('mb_strtolower') ? mb_strtolower($hay) : strtolower($hay);
            if (strpos($hay, $needle) === false) {
                return false;
            }
        }
        return true;
    };
    $rows = array_values(array_filter($rows, static function ($r) use ($keep) {
        return $keep($r, (string) $r['employee_code'] . ' ' . (string) ($r['employee_name'] ?? '') . ' ' . (string) ($r['designation'] ?? ''));
    }));
    $guestRows = array_values(array_filter($guestRows, static function ($g) use ($keep) {
        return $keep($g, $g['booking_type'] . ' ' . (string) $g['host_code'] . ' ' . (string) ($g['host_name'] ?? '') . ' ' . (string) $g['person_names'] . ' ' . (string) $g['remarks']);
    }));
}

// Combined (employees + guests/trainees) for department summary; employee-only for the employee list
$summary = canteenSummarize($rows, $guestRows);
$byDept = $summary['by_dept'];
$tot = $summary['total'];
$totalMeals = $tot['breakfast'] + $tot['lunch'] + $tot['dinner'];
$empSummary = canteenSummarize($rows);
$empByDept = $empSummary['by_dept'];
$empTot = $empSummary['total'];
$guestSummary = canteenSummarize([], $guestRows);
$guestTot = $guestSummary['total'];

$mealLabels = ['breakfast' => 'Breakfast', 'lunch' => 'Lunch', 'dinner' => 'Dinner'];
$deptLabel = 'All Departments';
foreach ($departments as $d) {
    if ((int) $d['id'] === $deptId) {
        $deptLabel = (string) $d['department_name'];
    }
}
$filterParts = ['Department: ' . $deptLabel];
if ($mealFilter !== '') {
    $filterParts[] = 'Meal: ' . $mealLabels[$mealFilter] . ' only';
}
if ($q !== '') {
    $filterParts[] = 'Search: "' . $q . '"';
}
$filterText = implode('  |  ', $filterParts);

$today = date('Y-m-d');
$isTomorrow = ($mealDate === canteenTargetDate());
$dayTag = $mealDate === $today ? 'Today' : ($isTomorrow ? 'Tomorrow' : ($mealDate < $today ? 'Past date' : 'Upcoming'));
$isOpen = canteenIsOpen($mealDate);
$cutoffAt = canteenCutoffAt($mealDate);
$cutoffText = date('d-m-Y h:i A', $cutoffAt);
$printedAt = date('d-m-Y h:i A');
$dateLong = date('l, d M Y', strtotime($mealDate));

$baseQuery = ['date' => $mealDate, 'department_id' => $deptId, 'meal' => $mealFilter, 'q' => $q];
$baseQuery = array_filter($baseQuery, static function ($v) {
    return $v !== '' && $v !== 0;
});
$baseQuery['date'] = $mealDate;
$urlFor = static function (array $extra = []) use ($baseQuery) {
    return app_url('canteen/index.php?' . http_build_query(array_merge($baseQuery, $extra)));
};
$screenUrl = $urlFor();

// Employee rows grouped by department (keeps department order from the query)
$grouped = [];
foreach ($rows as $r) {
    $grouped[(string) $r['department_name']][] = $r;
}

$guestGrouped = [];
foreach ($guestRows as $g) {
    $guestGrouped[(string) $g['department_name']][] = $g;
}

$mealCount = static function ($r) {
    return (int) $r['breakfast'] + (int) $r['lunch'] + (int) $r['dinner'];
};
$guestHost = static function ($g) {
    return trim((string) $g['host_code'] . ' — ' . (string) ($g['host_name'] ?? ''), ' —');
};
$guestNamesText = static function ($g) {
    return implode(', ', canteenGuestNames($g['person_names']));
};
$hasAny = $rows || $guestRows;
$fileStamp = date('d-m-Y', strtotime($mealDate));
$fileDept = trim(preg_replace('/[^A-Za-z0-9]+/', '_', $deptLabel), '_');

/* ===================== EXCEL (letterhead style) ===================== */
if ($exportMode === 'excel') {
    $brand = getCompanyDocumentBranding();
    $logoAbs = '';
    $logoSrc = trim((string) ($brand['logo_src'] ?? ''));
    if ($logoSrc !== '') {
        if (strpos($logoSrc, 'http') === 0) {
            $logoAbs = $logoSrc;
        } else {
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $logoAbs = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/' . ltrim($logoSrc, '/');
        }
    }
    $cols = 8;
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="Canteen_Meal_List_' . $fileStamp . '_' . $fileDept . '.xls"');
    header('Cache-Control: max-age=0');

    $bd = 'border:1px solid #94a3b8;';
    $th = 'style="' . $bd . 'background:#d2232a;color:#ffffff;font-weight:bold;text-align:center;"';
    $ctr = $bd . 'text-align:center;';
    $tick = static function ($v) use ($ctr) {
        return (int) $v === 1
            ? '<td style="' . $ctr . 'background:#dcfce7;color:#15803d;font-weight:bold;">&#10004;</td>'
            : '<td style="' . $ctr . 'color:#94a3b8;">-</td>';
    };

    echo '<html><head><meta charset="UTF-8"></head><body style="font-family:Calibri,Arial,sans-serif;">';
    echo '<table cellspacing="0" cellpadding="5" style="border-collapse:collapse;">';

    // Letterhead
    echo '<tr><td rowspan="3" style="width:90px;height:80px;vertical-align:middle;text-align:center;border-bottom:3px solid #d2232a;">';
    if ($logoAbs !== '') {
        echo '<img src="' . htmlspecialchars($logoAbs) . '" width="70" height="70">';
    }
    echo '</td><td colspan="' . ($cols - 1) . '" style="font-size:20px;font-weight:bold;color:#b91c1c;text-transform:uppercase;">'
        . htmlspecialchars((string) $brand['company_name']) . '</td></tr>';
    echo '<tr><td colspan="' . ($cols - 1) . '" style="font-size:11px;font-weight:bold;color:#1e293b;white-space:pre-wrap;">'
        . nl2br(htmlspecialchars((string) $brand['header_details'])) . '</td></tr>';
    echo '<tr><td colspan="' . ($cols - 1) . '" style="border-bottom:3px solid #d2232a;">&nbsp;</td></tr>';

    echo '<tr><td colspan="' . $cols . '">&nbsp;</td></tr>';
    echo '<tr><td colspan="' . $cols . '" style="font-size:16px;font-weight:bold;text-align:center;text-decoration:underline;">CANTEEN MEAL LIST</td></tr>';
    echo '<tr><td colspan="' . $cols . '" style="font-size:14px;font-weight:bold;color:#b91c1c;text-align:center;">Meal Date: '
        . htmlspecialchars(canteenDateLabel($mealDate)) . '</td></tr>';
    echo '<tr><td colspan="' . $cols . '" style="font-size:11px;font-weight:bold;color:#334155;text-align:center;">'
        . htmlspecialchars($filterText) . ' &nbsp;|&nbsp; Booking closed: ' . htmlspecialchars($cutoffText)
        . ' &nbsp;|&nbsp; Printed: ' . htmlspecialchars($printedAt) . '</td></tr>';
    echo '<tr><td colspan="' . $cols . '">&nbsp;</td></tr>';

    // Meal totals
    echo '<tr>'
        . '<td colspan="2" style="' . $ctr . 'background:#eff6ff;font-weight:bold;">Employees: ' . $tot['employees'] . '</td>'
        . '<td colspan="2" style="' . $ctr . 'background:#e0f2fe;color:#0369a1;font-weight:bold;">Guests / Trainees: ' . $tot['guests'] . '</td>'
        . '<td style="' . $ctr . 'background:#fef3c7;color:#b45309;font-weight:bold;">Breakfast: ' . $tot['breakfast'] . '</td>'
        . '<td style="' . $ctr . 'background:#dcfce7;color:#15803d;font-weight:bold;">Lunch: ' . $tot['lunch'] . '</td>'
        . '<td colspan="2" style="' . $ctr . 'background:#e0e7ff;color:#4338ca;font-weight:bold;">Dinner: ' . $tot['dinner'] . '</td>'
        . '</tr>';
    echo '<tr><td colspan="' . $cols . '" style="font-size:10px;color:#64748b;text-align:center;">Meal counts are plates (employees + guests + trainees).</td></tr>';

    // Department-wise summary
    echo '<tr><td colspan="' . $cols . '" style="font-size:13px;font-weight:bold;">Department-wise Summary</td></tr>';
    echo '<tr><th ' . $th . '>Sr</th><th ' . $th . '>Department</th><th ' . $th . '>Employees</th><th ' . $th . '>Guests / Trainees</th><th ' . $th . '>Breakfast</th><th ' . $th . '>Lunch</th><th ' . $th . '>Dinner</th><th ' . $th . '>Total Meals</th></tr>';
    if (!$byDept) {
        echo '<tr><td colspan="' . $cols . '" style="' . $ctr . '">No meal booking for this date.</td></tr>';
    }
    $i = 1;
    foreach ($byDept as $dname => $s) {
        echo '<tr>'
            . '<td style="' . $ctr . '">' . $i++ . '</td>'
            . '<td style="' . $bd . 'font-weight:bold;">' . htmlspecialchars($dname) . '</td>'
            . '<td style="' . $ctr . '">' . $s['employees'] . '</td>'
            . '<td style="' . $ctr . 'color:#0369a1;font-weight:bold;">' . $s['guests'] . '</td>'
            . '<td style="' . $ctr . 'color:#b45309;font-weight:bold;">' . $s['breakfast'] . '</td>'
            . '<td style="' . $ctr . 'color:#15803d;font-weight:bold;">' . $s['lunch'] . '</td>'
            . '<td style="' . $ctr . 'color:#4338ca;font-weight:bold;">' . $s['dinner'] . '</td>'
            . '<td style="' . $ctr . 'font-weight:bold;">' . ($s['breakfast'] + $s['lunch'] + $s['dinner']) . '</td>'
            . '</tr>';
    }
    echo '<tr style="background:#1e293b;color:#ffffff;font-weight:bold;">'
        . '<td colspan="2" style="' . $bd . 'text-align:right;">GRAND TOTAL</td>'
        . '<td style="' . $ctr . '">' . $tot['employees'] . '</td>'
        . '<td style="' . $ctr . '">' . $tot['guests'] . '</td>'
        . '<td style="' . $ctr . '">' . $tot['breakfast'] . '</td>'
        . '<td style="' . $ctr . '">' . $tot['lunch'] . '</td>'
        . '<td style="' . $ctr . '">' . $tot['dinner'] . '</td>'
        . '<td style="' . $ctr . '">' . $totalMeals . '</td></tr>';

    // Employee-wise list
    if ($layout !== 'summary') {
        echo '<tr><td colspan="' . $cols . '">&nbsp;</td></tr>';
        echo '<tr><td colspan="' . $cols . '" style="font-size:13px;font-weight:bold;">Employee-wise List (' . $empTot['employees'] . ')</td></tr>';
        echo '<tr><th ' . $th . '>Sr</th><th ' . $th . '>Emp. Code</th><th ' . $th . '>Employee Name</th><th ' . $th . '>Designation</th><th ' . $th . '>Breakfast</th><th ' . $th . '>Lunch</th><th ' . $th . '>Dinner</th><th ' . $th . '>Booked At</th></tr>';
        if (!$rows) {
            echo '<tr><td colspan="' . $cols . '" style="' . $ctr . '">No employee booking for this date.</td></tr>';
        }
        $sr = 0;
        foreach ($grouped as $dname => $list) {
            echo '<tr><td colspan="' . $cols . '" style="' . $bd . 'background:#fee2e2;color:#991b1b;font-weight:bold;text-transform:uppercase;">'
                . htmlspecialchars($dname) . ' (' . count($list) . ')</td></tr>';
            foreach ($list as $r) {
                $sr++;
                echo '<tr>'
                    . '<td style="' . $ctr . '">' . $sr . '</td>'
                    . '<td style="' . $bd . 'mso-number-format:\'\@\';font-weight:bold;">' . htmlspecialchars((string) $r['employee_code']) . '</td>'
                    . '<td style="' . $bd . '">' . htmlspecialchars((string) ($r['employee_name'] ?? '')) . '</td>'
                    . '<td style="' . $bd . '">' . htmlspecialchars((string) ($r['designation'] ?? '')) . '</td>'
                    . $tick($r['breakfast']) . $tick($r['lunch']) . $tick($r['dinner'])
                    . '<td style="' . $ctr . 'font-size:10px;color:#475569;">' . htmlspecialchars(date('d-m-Y h:i A', strtotime((string) $r['updated_at']))) . '</td>'
                    . '</tr>';
            }
            $s = $empByDept[$dname];
            echo '<tr style="background:#f1f5f9;font-weight:bold;">'
                . '<td colspan="4" style="' . $bd . 'text-align:right;">Sub Total - ' . htmlspecialchars($dname) . '</td>'
                . '<td style="' . $ctr . '">' . $s['breakfast'] . '</td>'
                . '<td style="' . $ctr . '">' . $s['lunch'] . '</td>'
                . '<td style="' . $ctr . '">' . $s['dinner'] . '</td>'
                . '<td style="' . $bd . '"></td></tr>';
        }
        if ($rows) {
            echo '<tr style="background:#1e293b;color:#ffffff;font-weight:bold;">'
                . '<td colspan="4" style="' . $bd . 'text-align:right;">EMPLOYEE TOTAL (' . $empTot['employees'] . ' employees)</td>'
                . '<td style="' . $ctr . '">' . $empTot['breakfast'] . '</td>'
                . '<td style="' . $ctr . '">' . $empTot['lunch'] . '</td>'
                . '<td style="' . $ctr . '">' . $empTot['dinner'] . '</td>'
                . '<td style="' . $bd . '"></td></tr>';
        }

        if ($guestRows) {
            $qty = static function ($g, $m) use ($ctr) {
                return (int) $g[$m] === 1
                    ? '<td style="' . $ctr . 'background:#dcfce7;color:#15803d;font-weight:bold;">' . (int) $g['person_count'] . '</td>'
                    : '<td style="' . $ctr . 'color:#94a3b8;">-</td>';
            };
            echo '<tr><td colspan="' . $cols . '">&nbsp;</td></tr>';
            echo '<tr><td colspan="' . $cols . '" style="font-size:13px;font-weight:bold;">Guest / Trainee List (' . $guestTot['guests'] . ' persons)</td></tr>';
            echo '<tr><th ' . $th . '>Sr</th><th ' . $th . '>Type</th><th ' . $th . '>Department</th><th ' . $th . '>With / Booked By</th><th ' . $th . '>Names (Persons)</th><th ' . $th . '>Breakfast</th><th ' . $th . '>Lunch</th><th ' . $th . '>Dinner</th></tr>';
            foreach ($guestRows as $gi => $g) {
                $remark = trim((string) $g['remarks']);
                echo '<tr>'
                    . '<td style="' . $ctr . '">' . ($gi + 1) . '</td>'
                    . '<td style="' . $ctr . 'font-weight:bold;color:' . ($g['booking_type'] === 'Trainee' ? '#7c3aed' : '#0369a1') . ';">' . htmlspecialchars($g['booking_type']) . '</td>'
                    . '<td style="' . $bd . '">' . htmlspecialchars((string) $g['department_name']) . '</td>'
                    . '<td style="' . $bd . '">' . htmlspecialchars($guestHost($g)) . '</td>'
                    . '<td style="' . $bd . '">' . htmlspecialchars($guestNamesText($g)) . ' <b>(' . (int) $g['person_count'] . ')</b>'
                    . ($remark !== '' ? '<br><span style="color:#64748b;font-size:10px;">' . htmlspecialchars($remark) . '</span>' : '') . '</td>'
                    . $qty($g, 'breakfast') . $qty($g, 'lunch') . $qty($g, 'dinner')
                    . '</tr>';
            }
            echo '<tr style="background:#1e293b;color:#ffffff;font-weight:bold;">'
                . '<td colspan="5" style="' . $bd . 'text-align:right;">GUEST / TRAINEE TOTAL (' . $guestTot['guests'] . ' persons)</td>'
                . '<td style="' . $ctr . '">' . $guestTot['breakfast'] . '</td>'
                . '<td style="' . $ctr . '">' . $guestTot['lunch'] . '</td>'
                . '<td style="' . $ctr . '">' . $guestTot['dinner'] . '</td></tr>';
        }
    }

    // Signatures + footer
    echo '<tr><td colspan="' . $cols . '">&nbsp;</td></tr><tr><td colspan="' . $cols . '">&nbsp;</td></tr>';
    echo '<tr><td colspan="3" style="border-top:1px solid #111;text-align:center;font-weight:bold;">Prepared By (HR)</td><td colspan="2"></td>'
        . '<td colspan="3" style="border-top:1px solid #111;text-align:center;font-weight:bold;">Received By (Canteen Co-ordinator)</td></tr>';
    $footer = trim((string) $brand['footer_details']);
    if ($footer !== '') {
        echo '<tr><td colspan="' . $cols . '">&nbsp;</td></tr>';
        echo '<tr><td colspan="' . $cols . '" style="border-top:2px solid #d2232a;font-size:10px;color:#475569;text-align:center;white-space:pre-wrap;">'
            . nl2br(htmlspecialchars($footer)) . '</td></tr>';
    }
    echo '</table></body></html>';
    exit;
}

/* ===================== PRINT (A4 letterhead) ===================== */
if ($exportMode === 'print') {
    $brand = getCompanyDocumentBranding();
    $perPage = 32;

    // Printable lines per department: header + employee rows + subtotal
    $deptLines = [];
    $sr = 0;
    foreach ($grouped as $dname => $list) {
        $lines = [['type' => 'dept', 'name' => $dname, 'count' => count($list), 'w' => 1]];
        foreach ($list as $r) {
            $sr++;
            $lines[] = ['type' => 'emp', 'sr' => $sr, 'row' => $r, 'w' => 1];
        }
        $lines[] = ['type' => 'sub', 'name' => $dname, 's' => $empByDept[$dname], 'w' => 1];
        $deptLines[$dname] = $lines;
    }
    // Guest lines; 'gstart' reserves room for the section title + table head
    $guestLinesFor = static function (array $list) use ($guestNamesText) {
        $out = [['type' => 'gstart', 'w' => 3]];
        foreach ($list as $g) {
            $out[] = ['type' => 'guest', 'row' => $g, 'w' => 1 + intdiv(strlen($guestNamesText($g)) + strlen((string) $g['remarks']), 60)];
        }
        return $out;
    };
    $gsr = 0;
    $numberGuests = static function (array $lines) use (&$gsr) {
        foreach ($lines as &$ln) {
            if ($ln['type'] === 'guest') {
                $ln['sr'] = ++$gsr;
            }
        }
        unset($ln);
        return $lines;
    };
    // Split lines into pages by weight
    $paginate = static function (array $lines, $firstCap, $cap) {
        $out = [];
        $cur = [];
        $used = 0;
        $limit = $firstCap;
        foreach ($lines as $ln) {
            if ($cur && $used + $ln['w'] > $limit) {
                $out[] = $cur;
                $cur = [];
                $used = 0;
                $limit = $cap;
            }
            $cur[] = $ln;
            $used += $ln['w'];
        }
        $out[] = $cur;
        return $out;
    };

    // Page = ['summary' => bool, 'lines' => [...], 'heading' => string]
    $pages = [];
    if ($layout === 'summary') {
        $pages[] = ['summary' => true, 'lines' => [], 'heading' => ''];
    } elseif ($layout === 'dept_pages') {
        $pages[] = ['summary' => true, 'lines' => [], 'heading' => ''];
        foreach (array_keys($byDept) as $dname) {
            $lines = $deptLines[$dname] ?? [];
            if (!empty($guestGrouped[$dname])) {
                $lines = array_merge($lines, $numberGuests($guestLinesFor($guestGrouped[$dname])));
            }
            foreach ($paginate($lines, $perPage, $perPage) as $ci => $chunk) {
                $pages[] = ['summary' => false, 'lines' => $chunk, 'heading' => $dname . ($ci > 0 ? ' (contd.)' : '')];
            }
        }
    } else {
        $all = [];
        foreach ($deptLines as $lines) {
            $all = array_merge($all, $lines);
        }
        if ($guestRows) {
            $all = array_merge($all, $numberGuests($guestLinesFor($guestRows)));
        }
        $firstCap = max(6, 21 - count($byDept));
        foreach ($paginate($all, $firstCap, $perPage) as $pi => $chunk) {
            $pages[] = ['summary' => $pi === 0, 'lines' => $chunk, 'heading' => ''];
        }
    }
    $totalPages = count($pages);
    $empLastPage = -1;
    $guestLastPage = -1;
    foreach ($pages as $pi => $pg) {
        foreach ($pg['lines'] as $ln) {
            if ($ln['type'] === 'emp') {
                $empLastPage = $pi;
            } elseif ($ln['type'] === 'guest') {
                $guestLastPage = $pi;
            }
        }
    }
    $qtyMark = static function ($g, $m) {
        return (int) $g[$m] === 1 ? '<span class="tk">' . (int) $g['person_count'] . '</span>' : '<span class="nx">—</span>';
    };
    $mark = static function ($v) {
        return (int) $v === 1 ? '<span class="tk">&#10004;</span>' : '<span class="nx">—</span>';
    };
    $layoutTitle = ['full' => 'Summary + Employee List', 'summary' => 'Department Summary', 'dept_pages' => 'Department-wise Sheets'][$layout];
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Canteen Meal List <?php echo htmlspecialchars($fileStamp . ' ' . $deptLabel); ?></title>
    <style>
        <?php echo companyDocPrintCss(); ?>
        * { box-sizing: border-box; }
        body {
            margin: 0; background: #e5e7eb; color: #111;
            font-family: Calibri, Candara, Segoe UI, Arial, Helvetica, sans-serif; font-size: 12px;
            -webkit-print-color-adjust: exact; print-color-adjust: exact;
        }
        .cp-toolbar {
            position: sticky; top: 0; z-index: 40; display: flex; justify-content: space-between; align-items: center;
            flex-wrap: wrap; gap: 10px; padding: 12px 18px; background: #111; color: #fff;
        }
        .cp-toolbar .grp { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; }
        .cp-btn {
            display: inline-flex; align-items: center; gap: 6px; padding: 8px 14px; border-radius: 8px; background: #333;
            color: #fff; text-decoration: none; border: 0; cursor: pointer; font-size: 13px; font-weight: 700; font-family: inherit;
        }
        .cp-btn.on { background: #fff; color: #111; }
        .cp-btn.primary { background: #d2232a; }
        .cp-page {
            width: 210mm; min-height: 297mm; margin: 16px auto; background: #fff; position: relative; overflow: hidden;
            box-shadow: 0 8px 28px rgba(0,0,0,.12); border: 2.25px solid #000;
        }
        .cp-inner { min-height: 297mm; padding: 10mm 12mm; display: flex; flex-direction: column; }
        .cdoc-header { flex-shrink: 0; margin-bottom: 8px; }
        .cdoc-header-logo { width: 78px; height: 78px; }
        .cdoc-header-text .cdoc-company { font-size: 22px; text-transform: uppercase; margin: 0 0 4px; }
        .cdoc-header-text .cdoc-header-details { font-size: 12px; line-height: 1.4; }
        .cdoc-footer { margin-top: auto; padding-top: 8px; font-size: 10px; }
        .cdoc-watermark img { width: min(48%, 280px); max-height: 280px; opacity: .06; }
        .cp-titlebar {
            display: flex; justify-content: space-between; align-items: stretch; gap: 10px;
            border: 1.5px solid #1e293b; border-radius: 6px; overflow: hidden; margin: 2px 0 8px;
        }
        .cp-titlebar .t { padding: 7px 12px; }
        .cp-titlebar .t h1 { margin: 0; font-size: 17px; font-weight: 800; letter-spacing: .05em; text-transform: uppercase; }
        .cp-titlebar .t small { display: block; font-size: 10.5px; font-weight: 700; color: #475569; margin-top: 1px; }
        .cp-titlebar .d { background: #d2232a; color: #fff; padding: 6px 14px; text-align: center; min-width: 46mm; }
        .cp-titlebar .d .l { font-size: 9.5px; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; opacity: .9; }
        .cp-titlebar .d .v { font-size: 15px; font-weight: 800; }
        .cp-titlebar .d .w { font-size: 10.5px; font-weight: 700; }
        .cp-meta { display: flex; flex-wrap: wrap; justify-content: space-between; gap: 4px 14px; margin-bottom: 8px; font-size: 10.5px; font-weight: 700; color: #334155; }
        .cp-kpis { display: grid; grid-template-columns: repeat(5, 1fr); gap: 8px; margin-bottom: 10px; }
        .cp-kpi.g { background: #f0f9ff; border-color: #7dd3fc; } .cp-kpi.g .v, .cp-kpi.g .l { color: #0369a1; }
        .cp-table .gt { display: inline-block; padding: 1px 6px; border-radius: 4px; background: #e0f2fe; color: #0369a1; font-weight: 800; font-size: 10px; text-transform: uppercase; }
        .cp-table .gt.tr { background: #ede9fe; color: #6d28d9; }
        .cp-table .rm { font-size: 9.5px; color: #64748b; margin-top: 1px; }
        .cp-kpi { border: 1.5px solid #cbd5e1; border-radius: 6px; padding: 6px 8px; text-align: center; }
        .cp-kpi .l { font-size: 10px; font-weight: 800; color: #64748b; text-transform: uppercase; letter-spacing: .05em; }
        .cp-kpi .v { font-size: 22px; font-weight: 800; color: #0f172a; line-height: 1.1; }
        .cp-kpi.b { background: #fffbeb; border-color: #fcd34d; } .cp-kpi.b .v, .cp-kpi.b .l { color: #b45309; }
        .cp-kpi.l2 { background: #f0fdf4; border-color: #86efac; } .cp-kpi.l2 .v, .cp-kpi.l2 .l { color: #15803d; }
        .cp-kpi.d { background: #eef2ff; border-color: #a5b4fc; } .cp-kpi.d .v, .cp-kpi.d .l { color: #4338ca; }
        .cp-sub {
            margin: 4px 0 5px; padding-left: 7px; border-left: 4px solid #d2232a;
            font-size: 12px; font-weight: 800; text-transform: uppercase; color: #1e293b; letter-spacing: .03em;
        }
        .cp-table { width: 100%; border-collapse: collapse; table-layout: fixed; font-size: 11px; margin-bottom: 10px; }
        .cp-table th, .cp-table td { border: 1px solid #334155; padding: 4px 6px; vertical-align: middle; word-break: break-word; }
        .cp-table th { background: #1e293b; color: #fff; font-weight: 800; text-transform: uppercase; font-size: 9.5px; text-align: center; letter-spacing: .03em; }
        .cp-table .ctr { text-align: center; }
        .cp-table tbody tr:nth-child(even) td { background: #f8fafc; }
        .cp-table tr.sub td { background: #f1f5f9 !important; font-weight: 800; }
        .cp-table tr.dept td { background: #fee2e2 !important; color: #991b1b; font-weight: 800; text-transform: uppercase; font-size: 10.5px; }
        .cp-table tr.grand td { background: #1e293b !important; color: #fff; font-weight: 800; }
        .cp-table .code { font-weight: 800; }
        .cp-table .tk { color: #15803d; font-weight: 800; font-size: 12px; }
        .cp-table .nx { color: #94a3b8; }
        .cp-empty { text-align: center; padding: 26px 10px; color: #64748b; font-weight: 700; }
        .cp-sign { display: flex; justify-content: space-between; gap: 30px; margin-top: 30px; font-size: 11.5px; font-weight: 700; }
        .cp-sign div { flex: 1; border-top: 1.5px solid #111; padding-top: 6px; text-align: center; }
        .cp-page-no { display: flex; justify-content: space-between; font-size: 10px; color: #64748b; margin-top: 4px; font-weight: 700; }
        @media print {
            @page { size: A4; margin: 0; }
            .no-print { display: none !important; }
            body { background: #fff; }
            .cp-page {
                width: 210mm; min-height: 297mm; height: 297mm; margin: 0; box-shadow: none; border: 0;
                page-break-after: always; break-after: page;
            }
            .cp-page:last-child { page-break-after: auto; break-after: auto; }
            .cp-inner { min-height: 297mm; height: 297mm; }
        }
    </style>
</head>
<body>
<div class="cp-toolbar no-print">
    <a class="cp-btn" href="<?php echo htmlspecialchars($screenUrl); ?>">← Back to Meal List</a>
    <div class="grp">
        <a class="cp-btn <?php echo $layout === 'full' ? 'on' : ''; ?>" href="<?php echo htmlspecialchars($urlFor(['export' => 'print', 'layout' => 'full'])); ?>">Summary + List</a>
        <a class="cp-btn <?php echo $layout === 'summary' ? 'on' : ''; ?>" href="<?php echo htmlspecialchars($urlFor(['export' => 'print', 'layout' => 'summary'])); ?>">Summary only</a>
        <a class="cp-btn <?php echo $layout === 'dept_pages' ? 'on' : ''; ?>" href="<?php echo htmlspecialchars($urlFor(['export' => 'print', 'layout' => 'dept_pages'])); ?>">Department-wise pages</a>
        <button type="button" class="cp-btn primary" onclick="window.print()">Print / Save as PDF</button>
    </div>
</div>

<?php foreach ($pages as $pageIndex => $pg): $isLast = ($pageIndex === $totalPages - 1); $pageLines = $pg['lines']; ?>
<section class="cp-page cdoc-sheet">
    <?php echo companyDocRenderWatermark($brand); ?>
    <div class="cp-inner cdoc-inner">
        <?php echo companyDocRenderHeader($brand, 'Human Resource · Canteen Meal List'); ?>

        <div class="cp-titlebar">
            <div class="t">
                <h1>Canteen Meal List</h1>
                <small><?php echo htmlspecialchars($pg['heading'] !== '' ? 'Department: ' . $pg['heading'] : $layoutTitle); ?></small>
            </div>
            <div class="d">
                <div class="l">Meal Date</div>
                <div class="v"><?php echo htmlspecialchars(date('d-m-Y', strtotime($mealDate))); ?></div>
                <div class="w"><?php echo htmlspecialchars(date('l', strtotime($mealDate))); ?></div>
            </div>
        </div>
        <div class="cp-meta">
            <span><?php echo htmlspecialchars($filterText); ?></span>
            <span>Booking closed: <?php echo htmlspecialchars($cutoffText); ?></span>
        </div>

        <?php if ($pg['summary']): ?>
        <div class="cp-kpis">
            <div class="cp-kpi"><div class="l">Employees</div><div class="v"><?php echo $tot['employees']; ?></div></div>
            <div class="cp-kpi g"><div class="l">Guests / Trainees</div><div class="v"><?php echo $tot['guests']; ?></div></div>
            <div class="cp-kpi b"><div class="l">Breakfast</div><div class="v"><?php echo $tot['breakfast']; ?></div></div>
            <div class="cp-kpi l2"><div class="l">Lunch</div><div class="v"><?php echo $tot['lunch']; ?></div></div>
            <div class="cp-kpi d"><div class="l">Dinner</div><div class="v"><?php echo $tot['dinner']; ?></div></div>
        </div>

        <div class="cp-sub">Department-wise Summary <span style="text-transform:none;font-weight:600;color:#64748b;font-size:10px;">(meal counts = plates incl. guests &amp; trainees)</span></div>
        <table class="cp-table">
            <thead>
                <tr>
                    <th style="width:6%;">Sr</th>
                    <th style="width:32%;">Department</th>
                    <th style="width:11%;">Employees</th>
                    <th style="width:11%;">Guests / Trainees</th>
                    <th style="width:10%;">Breakfast</th>
                    <th style="width:10%;">Lunch</th>
                    <th style="width:10%;">Dinner</th>
                    <th style="width:10%;">Total Meals</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$byDept): ?>
                <tr><td colspan="8" class="cp-empty">No meal booking for this date.</td></tr>
            <?php endif; ?>
            <?php $i = 1; foreach ($byDept as $dname => $s): ?>
                <tr>
                    <td class="ctr"><?php echo $i++; ?></td>
                    <td><strong><?php echo htmlspecialchars($dname); ?></strong></td>
                    <td class="ctr"><?php echo $s['employees']; ?></td>
                    <td class="ctr"><?php echo $s['guests']; ?></td>
                    <td class="ctr"><?php echo $s['breakfast']; ?></td>
                    <td class="ctr"><?php echo $s['lunch']; ?></td>
                    <td class="ctr"><?php echo $s['dinner']; ?></td>
                    <td class="ctr"><strong><?php echo $s['breakfast'] + $s['lunch'] + $s['dinner']; ?></strong></td>
                </tr>
            <?php endforeach; ?>
                <tr class="grand">
                    <td colspan="2" style="text-align:right;">GRAND TOTAL</td>
                    <td class="ctr"><?php echo $tot['employees']; ?></td>
                    <td class="ctr"><?php echo $tot['guests']; ?></td>
                    <td class="ctr"><?php echo $tot['breakfast']; ?></td>
                    <td class="ctr"><?php echo $tot['lunch']; ?></td>
                    <td class="ctr"><?php echo $tot['dinner']; ?></td>
                    <td class="ctr"><?php echo $totalMeals; ?></td>
                </tr>
            </tbody>
        </table>
        <?php endif; ?>

        <?php
        $empLines = array_values(array_filter($pageLines, static function ($ln) {
            return in_array($ln['type'], ['dept', 'emp', 'sub'], true);
        }));
        $gLines = array_values(array_filter($pageLines, static function ($ln) {
            return $ln['type'] === 'guest';
        }));
        $showEmpTable = $layout !== 'summary' && ($empLines || ($pageIndex === 0 && $layout === 'full' && !$rows));
        ?>
        <?php if ($showEmpTable): ?>
        <div class="cp-sub">Employee-wise List</div>
        <table class="cp-table">
            <thead>
                <tr>
                    <th style="width:7%;">Sr</th>
                    <th style="width:13%;">Emp. Code</th>
                    <th style="width:31%;">Employee Name</th>
                    <th style="width:22%;">Designation</th>
                    <th style="width:9%;">Breakfast</th>
                    <th style="width:9%;">Lunch</th>
                    <th style="width:9%;">Dinner</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$rows): ?>
                <tr><td colspan="7" class="cp-empty">No employee booking for this date.</td></tr>
            <?php endif; ?>
            <?php foreach ($empLines as $ln): ?>
                <?php if ($ln['type'] === 'dept'): ?>
                <tr class="dept"><td colspan="7"><?php echo htmlspecialchars($ln['name']); ?> (<?php echo (int) $ln['count']; ?>)</td></tr>
                <?php elseif ($ln['type'] === 'emp'): $r = $ln['row']; ?>
                <tr>
                    <td class="ctr"><?php echo (int) $ln['sr']; ?></td>
                    <td class="code"><?php echo htmlspecialchars((string) $r['employee_code']); ?></td>
                    <td><?php echo htmlspecialchars((string) ($r['employee_name'] ?? '')); ?></td>
                    <td><?php echo htmlspecialchars((string) ($r['designation'] ?? '')); ?></td>
                    <td class="ctr"><?php echo $mark($r['breakfast']); ?></td>
                    <td class="ctr"><?php echo $mark($r['lunch']); ?></td>
                    <td class="ctr"><?php echo $mark($r['dinner']); ?></td>
                </tr>
                <?php else: $s = $ln['s']; ?>
                <tr class="sub">
                    <td colspan="4" style="text-align:right;">Sub Total — <?php echo htmlspecialchars($ln['name']); ?></td>
                    <td class="ctr"><?php echo $s['breakfast']; ?></td>
                    <td class="ctr"><?php echo $s['lunch']; ?></td>
                    <td class="ctr"><?php echo $s['dinner']; ?></td>
                </tr>
                <?php endif; ?>
            <?php endforeach; ?>
            <?php if ($pageIndex === $empLastPage && $layout === 'full'): ?>
                <tr class="grand">
                    <td colspan="4" style="text-align:right;">EMPLOYEE TOTAL (<?php echo $empTot['employees']; ?> employees)</td>
                    <td class="ctr"><?php echo $empTot['breakfast']; ?></td>
                    <td class="ctr"><?php echo $empTot['lunch']; ?></td>
                    <td class="ctr"><?php echo $empTot['dinner']; ?></td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
        <?php endif; ?>

        <?php if ($gLines): ?>
        <div class="cp-sub">Guest / Trainee List</div>
        <table class="cp-table">
            <thead>
                <tr>
                    <th style="width:6%;">Sr</th>
                    <th style="width:10%;">Type</th>
                    <?php if ($layout !== 'dept_pages'): ?><th style="width:17%;">Department</th><?php endif; ?>
                    <th style="width:<?php echo $layout !== 'dept_pages' ? 20 : 26; ?>%;">With / Booked By</th>
                    <th>Names (Persons)</th>
                    <th style="width:8%;">Bfast</th>
                    <th style="width:8%;">Lunch</th>
                    <th style="width:8%;">Dinner</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($gLines as $ln): $g = $ln['row']; $remark = trim((string) $g['remarks']); ?>
                <tr>
                    <td class="ctr"><?php echo (int) $ln['sr']; ?></td>
                    <td class="ctr"><span class="gt <?php echo $g['booking_type'] === 'Trainee' ? 'tr' : ''; ?>"><?php echo htmlspecialchars($g['booking_type']); ?></span></td>
                    <?php if ($layout !== 'dept_pages'): ?><td><?php echo htmlspecialchars((string) $g['department_name']); ?></td><?php endif; ?>
                    <td><?php echo htmlspecialchars($guestHost($g)); ?></td>
                    <td><?php echo htmlspecialchars($guestNamesText($g)); ?> <strong>(<?php echo (int) $g['person_count']; ?>)</strong>
                        <?php if ($remark !== ''): ?><div class="rm"><?php echo htmlspecialchars($remark); ?></div><?php endif; ?></td>
                    <td class="ctr"><?php echo $qtyMark($g, 'breakfast'); ?></td>
                    <td class="ctr"><?php echo $qtyMark($g, 'lunch'); ?></td>
                    <td class="ctr"><?php echo $qtyMark($g, 'dinner'); ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if ($pageIndex === $guestLastPage && $layout === 'full'): ?>
                <tr class="grand">
                    <td colspan="5" style="text-align:right;">GUEST / TRAINEE TOTAL (<?php echo $guestTot['guests']; ?> persons)</td>
                    <td class="ctr"><?php echo $guestTot['breakfast']; ?></td>
                    <td class="ctr"><?php echo $guestTot['lunch']; ?></td>
                    <td class="ctr"><?php echo $guestTot['dinner']; ?></td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
        <?php endif; ?>

        <?php if ($isLast || $layout === 'dept_pages'): ?>
        <div class="cp-sign">
            <div>Prepared By (HR)</div>
            <div>Received By (Canteen Co-ordinator)</div>
        </div>
        <?php endif; ?>

        <div class="cp-page-no">
            <span>Printed: <?php echo htmlspecialchars($printedAt); ?></span>
            <span>Page <?php echo $pageIndex + 1; ?> of <?php echo $totalPages; ?></span>
        </div>
        <?php echo companyDocRenderFooter($brand); ?>
    </div>
</section>
<?php endforeach; ?>
</body>
</html>
    <?php
    exit;
}

/* ===================== SCREEN ===================== */
$pageTitle = 'Canteen Meal List';
$useSidebar = true;
$sidebarMode = 'canteen';
$sidebarActive = 'canteen_orders';

$prevDate = date('Y-m-d', strtotime($mealDate . ' -1 day'));
$nextDate = date('Y-m-d', strtotime($mealDate . ' +1 day'));
$pct = static function ($n) use ($tot) {
    return $tot['employees'] > 0 ? round($n * 100 / $tot['employees']) : 0;
};

require_once __DIR__ . '/../includes/header.php';
?>
<style>
.cm-wrap { display: flex; flex-direction: column; gap: 16px; }
.cm-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 14px; box-shadow: 0 2px 12px rgba(15, 23, 42, .04); }

/* Hero */
.cm-hero { display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: wrap; padding: 18px 20px;
    background: linear-gradient(120deg, #fff5f5 0%, #ffffff 55%); border-color: #fde2e2; }
.cm-hero-left { display: flex; align-items: center; gap: 14px; }
.cm-hero-ico { width: 54px; height: 54px; border-radius: 14px; display: grid; place-items: center; font-size: 22px; color: #fff;
    background: linear-gradient(135deg, #d2232a, #9f1239); box-shadow: 0 8px 20px rgba(210, 35, 42, .25); flex-shrink: 0; }
.cm-hero h1 { margin: 0; font-size: 22px; font-weight: 800; color: #0f172a; }
.cm-hero-sub { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; margin-top: 5px; font-size: 13px; color: #475569; }
.cm-date-pill { display: inline-flex; align-items: center; gap: 6px; padding: 4px 11px; border-radius: 999px; background: #0f172a; color: #fff; font-weight: 700; font-size: 12.5px; }
.cm-tag { padding: 3px 9px; border-radius: 999px; font-size: 11.5px; font-weight: 800; background: #fee2e2; color: #b91c1c; text-transform: uppercase; letter-spacing: .04em; }
.cm-hero-right { display: flex; flex-direction: column; align-items: flex-end; gap: 10px; }
.cm-status { display: inline-flex; align-items: center; gap: 6px; padding: 6px 12px; border-radius: 999px; font-size: 12.5px; font-weight: 800; }
.cm-status.open { background: #dcfce7; color: #15803d; }
.cm-status.closed { background: #fee2e2; color: #b91c1c; }
.cm-actions { display: flex; gap: 8px; flex-wrap: wrap; justify-content: flex-end; }
.cm-btn { display: inline-flex; align-items: center; gap: 7px; padding: 9px 14px; border-radius: 10px; font-size: 13px; font-weight: 700;
    text-decoration: none; border: 1px solid #e2e8f0; background: #fff; color: #1e293b; cursor: pointer; font-family: inherit; line-height: 1; }
.cm-btn:hover { border-color: #cbd5e1; background: #f8fafc; }
.cm-btn.xl i { color: #15803d; }
.cm-btn.pdf { background: #d2232a; border-color: #d2232a; color: #fff; }
.cm-btn.pdf:hover { background: #b91c1c; }
.cm-drop { position: relative; }
.cm-drop-menu { display: none; position: absolute; right: 0; top: calc(100% + 6px); z-index: 30; min-width: 250px; padding: 6px;
    background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; box-shadow: 0 14px 34px rgba(15, 23, 42, .14); }
.cm-drop.open .cm-drop-menu { display: block; }
.cm-drop-menu a { display: flex; gap: 10px; align-items: flex-start; padding: 9px 10px; border-radius: 8px; text-decoration: none; color: #0f172a; }
.cm-drop-menu a:hover { background: #fff5f5; }
.cm-drop-menu a i { margin-top: 2px; width: 16px; color: #d2232a; }
.cm-drop-menu a strong { display: block; font-size: 13px; }
.cm-drop-menu a small { display: block; font-size: 11.5px; color: #64748b; }

/* Filters */
.cm-filters { padding: 14px 16px; }
.cm-filter-grid { display: grid; grid-template-columns: minmax(250px, 1.3fr) 1.3fr minmax(140px, .9fr) 1.2fr auto; gap: 12px; align-items: start; }
.cm-filter-btns { padding-top: 23px; }
.cm-filter-grid label { display: block; font-size: 12px; font-weight: 700; color: #475569; margin-bottom: 5px; }
.cm-date-row { display: flex; gap: 6px; }
.cm-date-row .form-control { flex: 1; min-width: 0; }
.cm-icon-btn { width: 38px; flex-shrink: 0; display: grid; place-items: center; border: 1px solid #e2e8f0; border-radius: 8px; background: #fff; color: #334155; text-decoration: none; }
.cm-icon-btn:hover { background: #fff5f5; color: #d2232a; border-color: #fecaca; }
.cm-quick { display: flex; gap: 6px; margin-top: 8px; }
.cm-quick a { font-size: 12px; font-weight: 700; padding: 4px 11px; border-radius: 999px; background: #fff; border: 1px solid #e2e8f0; color: #334155; text-decoration: none; }
.cm-quick a.on, .cm-quick a:hover { background: #d2232a; border-color: #d2232a; color: #fff; }
.cm-filter-btns { display: flex; gap: 8px; }
.cm-filter-btns .btn-primary, .cm-filter-btns .btn-secondary { white-space: nowrap; }

/* KPIs */
.cm-kpis { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: 14px; }
.cm-kpi.gs { --k: #0369a1; --kb: #e0f2fe; }
.cm-chip.gs { background: #e0f2fe; color: #0369a1; }
.cm-type { display: inline-flex; align-items: center; gap: 5px; padding: 3px 9px; border-radius: 999px; font-size: 11.5px; font-weight: 800; background: #e0f2fe; color: #0369a1; }
.cm-type.tr { background: #ede9fe; color: #6d28d9; }
.cm-persons { display: inline-block; padding: 2px 8px; border-radius: 6px; background: #0f172a; color: #fff; font-size: 11.5px; font-weight: 800; }
.cm-namelist { margin-top: 4px; font-size: 12.5px; color: #334155; line-height: 1.45; }
.cm-remark { margin-top: 3px; font-size: 11.5px; color: #64748b; }
.cm-del { width: 32px; height: 32px; border-radius: 8px; border: 1px solid #fecaca; background: #fff5f5; color: #b91c1c; cursor: pointer; }
.cm-del:hover { background: #d2232a; color: #fff; border-color: #d2232a; }
.cm-kpi { padding: 16px 18px; display: flex; align-items: center; gap: 14px; position: relative; overflow: hidden; }
.cm-kpi::after { content: ''; position: absolute; left: 0; top: 0; bottom: 0; width: 4px; background: var(--k); }
.cm-kpi-ico { width: 48px; height: 48px; border-radius: 12px; display: grid; place-items: center; font-size: 20px; flex-shrink: 0; background: var(--kb); color: var(--k); }
.cm-kpi-lbl { font-size: 12px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: .05em; }
.cm-kpi-val { font-size: 28px; font-weight: 800; line-height: 1.1; color: var(--k); }
.cm-kpi-note { font-size: 11.5px; color: #94a3b8; font-weight: 600; }
.cm-kpi.emp { --k: #2563eb; --kb: #eff6ff; } .cm-kpi.emp .cm-kpi-val { color: #0f172a; }
.cm-kpi.bf { --k: #b45309; --kb: #fef3c7; }
.cm-kpi.ln { --k: #15803d; --kb: #dcfce7; }
.cm-kpi.dn { --k: #4338ca; --kb: #e0e7ff; }

/* Tabs */
.cm-panel { padding: 0; overflow: hidden; }
.cm-tabs { display: flex; align-items: center; justify-content: space-between; gap: 10px; flex-wrap: wrap; padding: 10px 14px; border-bottom: 1px solid #eef2f7; background: #fbfcfe; }
.cm-tab-btns { display: inline-flex; padding: 4px; border-radius: 10px; background: #f1f5f9; gap: 4px; }
.cm-tab-btn { border: 0; background: transparent; padding: 8px 14px; border-radius: 8px; font-size: 13px; font-weight: 700; color: #475569; cursor: pointer; font-family: inherit; display: inline-flex; gap: 7px; align-items: center; }
.cm-tab-btn .n { font-size: 11px; padding: 1px 7px; border-radius: 999px; background: #e2e8f0; color: #334155; }
.cm-tab-btn.on { background: #fff; color: #d2232a; box-shadow: 0 1px 4px rgba(15, 23, 42, .1); }
.cm-tab-btn.on .n { background: #fee2e2; color: #b91c1c; }
.cm-legend { display: flex; gap: 12px; font-size: 12px; color: #64748b; font-weight: 600; flex-wrap: wrap; }
.cm-tab-pane { display: none; padding: 14px; }
.cm-tab-pane.on { display: block; }

/* Department groups (employee-wise) */
.cm-group { border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; margin-bottom: 14px; }
.cm-group:last-child { margin-bottom: 0; }
.cm-group-head { display: flex; align-items: center; justify-content: space-between; gap: 10px; flex-wrap: wrap; padding: 10px 14px;
    background: #fef2f2; border-bottom: 1px solid #fde2e2; cursor: pointer; user-select: none; }
.cm-group-title { display: flex; align-items: center; gap: 9px; font-weight: 800; color: #991b1b; font-size: 13.5px; text-transform: uppercase; letter-spacing: .03em; }
.cm-group-title .cnt { font-size: 11.5px; padding: 2px 9px; border-radius: 999px; background: #fff; color: #b91c1c; border: 1px solid #fecaca; text-transform: none; letter-spacing: 0; }
.cm-group-title .car { transition: transform .15s ease; font-size: 12px; }
.cm-group.collapsed .cm-group-title .car { transform: rotate(-90deg); }
.cm-group.collapsed .cm-group-body { display: none; }
.cm-chips { display: flex; gap: 6px; flex-wrap: wrap; }
.cm-chip { display: inline-flex; align-items: center; gap: 5px; padding: 3px 10px; border-radius: 999px; font-size: 12px; font-weight: 800; }
.cm-chip.bf { background: #fef3c7; color: #b45309; }
.cm-chip.ln { background: #dcfce7; color: #15803d; }
.cm-chip.dn { background: #e0e7ff; color: #4338ca; }

.cm-table { width: 100%; border-collapse: collapse; }
.cm-table thead th { background: #f8fafc; color: #475569; font-size: 11.5px; font-weight: 800; text-transform: uppercase; letter-spacing: .04em;
    padding: 10px 12px; text-align: left; white-space: nowrap; border-bottom: 1px solid #e2e8f0; }
.cm-table tbody td { padding: 10px 12px; font-size: 13px; border-bottom: 1px solid #f1f5f9; color: #1e293b; vertical-align: middle; }
.cm-table tbody tr:last-child td { border-bottom: 0; }
.cm-table tbody tr:hover td { background: #fffafa; }
.cm-table .ctr { text-align: center; }
.cm-code { display: inline-block; padding: 3px 8px; border-radius: 6px; background: #f1f5f9; font-weight: 800; font-size: 12px; color: #0f172a; font-family: Consolas, monospace; }
.cm-name { color: #0f172a; font-weight: 700; text-decoration: none; }
.cm-name:hover { color: #d2232a; }
.cm-desig { display: block; font-size: 11.5px; color: #64748b; margin-top: 1px; }
.cm-yes { display: inline-grid; place-items: center; width: 28px; height: 28px; border-radius: 50%; font-size: 12px; }
.cm-yes.bf { background: #fef3c7; color: #b45309; }
.cm-yes.ln { background: #dcfce7; color: #15803d; }
.cm-yes.dn { background: #e0e7ff; color: #4338ca; }
.cm-no { color: #cbd5e1; font-weight: 800; }
.cm-meals { display: inline-block; min-width: 26px; padding: 2px 8px; border-radius: 999px; background: #f1f5f9; font-weight: 800; font-size: 12px; text-align: center; }
.cm-time { font-size: 12px; color: #64748b; white-space: nowrap; }
.cm-table tfoot td { padding: 10px 12px; font-weight: 800; font-size: 13px; background: #f8fafc; border-top: 1px solid #e2e8f0; }
.cm-table.sum tfoot td { background: #1e293b; color: #fff; }
.cm-bar { height: 7px; border-radius: 999px; background: #f1f5f9; overflow: hidden; min-width: 90px; }
.cm-bar span { display: block; height: 100%; border-radius: 999px; background: linear-gradient(90deg, #f87171, #d2232a); }

.cm-empty { text-align: center; padding: 46px 10px; color: #64748b; }
.cm-empty i { display: block; font-size: 34px; color: #cbd5e1; margin-bottom: 10px; }
.cm-empty strong { display: block; color: #0f172a; font-size: 15px; margin-bottom: 4px; }

@media (max-width: 1100px) { .cm-filter-grid { grid-template-columns: 1fr 1fr; } .cm-filter-btns { grid-column: 1 / -1; } }
@media (max-width: 900px) { .cm-kpis { grid-template-columns: repeat(2, minmax(0, 1fr)); } .cm-hero-right { align-items: flex-start; } }
@media (max-width: 560px) { .cm-filter-grid { grid-template-columns: 1fr; } }
</style>

<main class="dashboard-main">
    <div class="page-toolbar">
        <a href="<?php echo app_url('dashboard.php'); ?>" class="back-link">
            <i class="fa-solid fa-arrow-left"></i> Back to Dashboard
        </a>
    </div>

    <div class="cm-wrap">
        <div class="cm-card cm-hero">
            <div class="cm-hero-left">
                <div class="cm-hero-ico"><i class="fa-solid fa-utensils"></i></div>
                <div>
                    <h1>Canteen Meal List</h1>
                    <div class="cm-hero-sub">
                        <span class="cm-date-pill"><i class="fa-regular fa-calendar"></i> <?php echo htmlspecialchars($dateLong); ?></span>
                        <span class="cm-tag"><?php echo htmlspecialchars($dayTag); ?></span>
                        <span><i class="fa-solid fa-building" style="color:#94a3b8;"></i> <?php echo htmlspecialchars($deptLabel); ?></span>
                    </div>
                </div>
            </div>
            <div class="cm-hero-right">
                <?php if ($isOpen): ?>
                    <span class="cm-status open"><i class="fa-solid fa-lock-open"></i> Booking open till <?php echo htmlspecialchars($cutoffText); ?></span>
                <?php else: ?>
                    <span class="cm-status closed"><i class="fa-solid fa-lock"></i> Booking closed on <?php echo htmlspecialchars($cutoffText); ?></span>
                <?php endif; ?>
                <div class="cm-actions">
                    <a href="<?php echo app_url('canteen/qr.php'); ?>" class="cm-btn" target="_blank" rel="noopener"><i class="fa-solid fa-qrcode"></i> QR Code</a>
                    <div class="cm-drop" data-drop>
                        <button type="button" class="cm-btn xl" data-drop-btn><i class="fa-solid fa-file-excel"></i> Excel <i class="fa-solid fa-chevron-down" style="font-size:10px;"></i></button>
                        <div class="cm-drop-menu">
                            <a href="<?php echo htmlspecialchars($urlFor(['export' => 'excel', 'layout' => 'full'])); ?>">
                                <i class="fa-solid fa-table-list"></i><span><strong>Summary + Employee List</strong><small>Full list with department subtotals</small></span>
                            </a>
                            <a href="<?php echo htmlspecialchars($urlFor(['export' => 'excel', 'layout' => 'summary'])); ?>">
                                <i class="fa-solid fa-chart-column"></i><span><strong>Department Summary only</strong><small>Meal count per department</small></span>
                            </a>
                        </div>
                    </div>
                    <div class="cm-drop" data-drop>
                        <button type="button" class="cm-btn pdf" data-drop-btn><i class="fa-solid fa-file-pdf"></i> Print / PDF <i class="fa-solid fa-chevron-down" style="font-size:10px;"></i></button>
                        <div class="cm-drop-menu">
                            <a href="<?php echo htmlspecialchars($urlFor(['export' => 'print', 'layout' => 'full'])); ?>" target="_blank" rel="noopener">
                                <i class="fa-solid fa-table-list"></i><span><strong>Summary + Employee List</strong><small>For Canteen Co-ordinator</small></span>
                            </a>
                            <a href="<?php echo htmlspecialchars($urlFor(['export' => 'print', 'layout' => 'summary'])); ?>" target="_blank" rel="noopener">
                                <i class="fa-solid fa-chart-column"></i><span><strong>Department Summary only</strong><small>One-page meal count</small></span>
                            </a>
                            <a href="<?php echo htmlspecialchars($urlFor(['export' => 'print', 'layout' => 'dept_pages'])); ?>" target="_blank" rel="noopener">
                                <i class="fa-solid fa-copy"></i><span><strong>Department-wise Pages</strong><small>Each department on a new page</small></span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <form method="GET" class="cm-card cm-filters" id="cmFilter">
            <div class="cm-filter-grid">
                <div>
                    <label>Meal Date</label>
                    <div class="cm-date-row">
                        <a class="cm-icon-btn" title="Previous day" href="<?php echo htmlspecialchars($urlFor(['date' => $prevDate])); ?>"><i class="fa-solid fa-chevron-left"></i></a>
                        <input type="date" name="date" class="form-control" value="<?php echo htmlspecialchars($mealDate); ?>" onchange="this.form.submit()">
                        <a class="cm-icon-btn" title="Next day" href="<?php echo htmlspecialchars($urlFor(['date' => $nextDate])); ?>"><i class="fa-solid fa-chevron-right"></i></a>
                    </div>
                    <div class="cm-quick">
                        <?php foreach ([$today => 'Today', canteenTargetDate() => 'Tomorrow'] as $qd => $ql): ?>
                            <a href="<?php echo htmlspecialchars($urlFor(['date' => $qd])); ?>" class="<?php echo $mealDate === $qd ? 'on' : ''; ?>"><?php echo $ql; ?></a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div>
                    <label>Department</label>
                    <select name="department_id" class="form-control" data-autosubmit>
                        <option value="0">All Departments</option>
                        <?php foreach ($departments as $d): ?>
                            <option value="<?php echo (int) $d['id']; ?>" <?php echo $deptId === (int) $d['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($d['department_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label>Meal</label>
                    <select name="meal" class="form-control" data-autosubmit>
                        <option value="all" <?php echo $mealFilter === '' ? 'selected' : ''; ?>>All Meals</option>
                        <?php foreach ($mealLabels as $mk => $ml): ?>
                            <option value="<?php echo $mk; ?>" <?php echo $mealFilter === $mk ? 'selected' : ''; ?>><?php echo $ml; ?> only</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label>Search Employee</label>
                    <input type="text" name="q" class="form-control" value="<?php echo htmlspecialchars($q); ?>" placeholder="Code / name / designation">
                </div>
                <div class="cm-filter-btns">
                    <button type="submit" class="btn-primary"><i class="fa-solid fa-filter"></i> Show</button>
                    <a href="<?php echo app_url('canteen/index.php'); ?>" class="btn-secondary"><i class="fa-solid fa-rotate-left"></i> Reset</a>
                </div>
            </div>
        </form>

        <div class="cm-kpis">
            <div class="cm-card cm-kpi emp"><div class="cm-kpi-ico"><i class="fa-solid fa-users"></i></div><div><div class="cm-kpi-lbl">Employees</div><div class="cm-kpi-val"><?php echo $tot['employees']; ?></div><div class="cm-kpi-note"><?php echo $totalMeals; ?> plates in total</div></div></div>
            <div class="cm-card cm-kpi gs"><div class="cm-kpi-ico"><i class="fa-solid fa-user-group"></i></div><div><div class="cm-kpi-lbl">Guests / Trainees</div><div class="cm-kpi-val"><?php echo $tot['guests']; ?></div><div class="cm-kpi-note"><?php echo count($guestRows); ?> entr<?php echo count($guestRows) === 1 ? 'y' : 'ies'; ?></div></div></div>
            <div class="cm-card cm-kpi bf"><div class="cm-kpi-ico"><i class="fa-solid fa-mug-hot"></i></div><div><div class="cm-kpi-lbl">Breakfast</div><div class="cm-kpi-val"><?php echo $tot['breakfast']; ?></div><div class="cm-kpi-note">plates</div></div></div>
            <div class="cm-card cm-kpi ln"><div class="cm-kpi-ico"><i class="fa-solid fa-bowl-rice"></i></div><div><div class="cm-kpi-lbl">Lunch</div><div class="cm-kpi-val"><?php echo $tot['lunch']; ?></div><div class="cm-kpi-note">plates</div></div></div>
            <div class="cm-card cm-kpi dn"><div class="cm-kpi-ico"><i class="fa-solid fa-moon"></i></div><div><div class="cm-kpi-lbl">Dinner</div><div class="cm-kpi-val"><?php echo $tot['dinner']; ?></div><div class="cm-kpi-note">plates</div></div></div>
        </div>

        <?php if ($flashMsg !== ''): ?>
        <div class="alert alert-success" style="margin:0;"><i class="fa-solid fa-circle-check"></i> <?php echo htmlspecialchars($flashMsg); ?></div>
        <?php endif; ?>

        <div class="cm-card cm-panel">
            <div class="cm-tabs">
                <div class="cm-tab-btns">
                    <button type="button" class="cm-tab-btn on" data-tab="emp"><i class="fa-solid fa-list-ul"></i> Employee-wise <span class="n"><?php echo $empTot['employees']; ?></span></button>
                    <button type="button" class="cm-tab-btn" data-tab="guest"><i class="fa-solid fa-user-group"></i> Guest / Trainee <span class="n"><?php echo $tot['guests']; ?></span></button>
                    <button type="button" class="cm-tab-btn" data-tab="dept"><i class="fa-solid fa-chart-column"></i> Department-wise <span class="n"><?php echo count($byDept); ?></span></button>
                </div>
                <div class="cm-legend">
                    <span><span class="cm-yes bf" style="width:18px;height:18px;font-size:9px;"><i class="fa-solid fa-check"></i></span> Booked</span>
                    <span><span class="cm-no">—</span> Not booked</span>
                </div>
            </div>

            <?php if (!$hasAny): ?>
            <div class="cm-empty">
                <i class="fa-solid fa-utensils"></i>
                <strong>No meal booking found</strong>
                for <?php echo htmlspecialchars(canteenDateLabel($mealDate)); ?><?php echo ($mealFilter !== '' || $q !== '' || $deptId > 0) ? ' with the selected filters' : ''; ?>.
            </div>
            <?php else: ?>

            <div class="cm-tab-pane on" data-pane="emp">
                <?php if (!$rows): ?>
                <div class="cm-empty"><i class="fa-solid fa-id-badge"></i><strong>No employee booking</strong>Only guest / trainee entries for this date.</div>
                <?php endif; ?>
                <?php $sr = 0; foreach ($grouped as $dname => $list): $s = $empByDept[$dname]; ?>
                <div class="cm-group">
                    <div class="cm-group-head" data-toggle-group>
                        <div class="cm-group-title">
                            <i class="fa-solid fa-chevron-down car"></i>
                            <i class="fa-solid fa-building"></i> <?php echo htmlspecialchars($dname); ?>
                            <span class="cnt"><?php echo count($list); ?> employee<?php echo count($list) === 1 ? '' : 's'; ?></span>
                        </div>
                        <div class="cm-chips">
                            <span class="cm-chip bf"><i class="fa-solid fa-mug-hot"></i> <?php echo $s['breakfast']; ?></span>
                            <span class="cm-chip ln"><i class="fa-solid fa-bowl-rice"></i> <?php echo $s['lunch']; ?></span>
                            <span class="cm-chip dn"><i class="fa-solid fa-moon"></i> <?php echo $s['dinner']; ?></span>
                        </div>
                    </div>
                    <div class="cm-group-body table-wrap">
                        <table class="cm-table">
                            <thead>
                                <tr>
                                    <th style="width:56px;">Sr</th>
                                    <th style="width:120px;">Emp. Code</th>
                                    <th>Employee</th>
                                    <th class="ctr" style="width:100px;">Breakfast</th>
                                    <th class="ctr" style="width:100px;">Lunch</th>
                                    <th class="ctr" style="width:100px;">Dinner</th>
                                    <th class="ctr" style="width:80px;">Meals</th>
                                    <th style="width:170px;">Booked At</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($list as $r): $sr++; ?>
                                <tr>
                                    <td><?php echo $sr; ?></td>
                                    <td><span class="cm-code"><?php echo htmlspecialchars((string) $r['employee_code']); ?></span></td>
                                    <td>
                                        <a class="cm-name" href="<?php echo app_url('employees/view.php?id=' . (int) $r['employee_id']); ?>"><?php echo htmlspecialchars((string) ($r['employee_name'] ?? '')); ?></a>
                                        <span class="cm-desig"><?php echo htmlspecialchars((string) ($r['designation'] ?: '—')); ?></span>
                                    </td>
                                    <?php foreach (['breakfast' => 'bf', 'lunch' => 'ln', 'dinner' => 'dn'] as $m => $cls): ?>
                                    <td class="ctr"><?php echo (int) $r[$m] === 1 ? '<span class="cm-yes ' . $cls . '"><i class="fa-solid fa-check"></i></span>' : '<span class="cm-no">—</span>'; ?></td>
                                    <?php endforeach; ?>
                                    <td class="ctr"><span class="cm-meals"><?php echo $mealCount($r); ?></span></td>
                                    <td class="cm-time"><i class="fa-regular fa-clock"></i> <?php echo htmlspecialchars(date('d-m-Y h:i A', strtotime((string) $r['updated_at']))); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="3" style="text-align:right;">Sub Total</td>
                                    <td class="ctr" style="color:#b45309;"><?php echo $s['breakfast']; ?></td>
                                    <td class="ctr" style="color:#15803d;"><?php echo $s['lunch']; ?></td>
                                    <td class="ctr" style="color:#4338ca;"><?php echo $s['dinner']; ?></td>
                                    <td class="ctr"><?php echo $s['breakfast'] + $s['lunch'] + $s['dinner']; ?></td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="cm-tab-pane" data-pane="guest">
                <?php if (!$guestRows): ?>
                <div class="cm-empty"><i class="fa-solid fa-user-group"></i><strong>No guest / trainee booking</strong>Guests and trainees are booked from the QR page → "Guest / Trainee Booking".</div>
                <?php else: ?>
                <div class="table-wrap" style="border:1px solid #e2e8f0;border-radius:12px;overflow:hidden;">
                    <table class="cm-table">
                        <thead>
                            <tr>
                                <th style="width:50px;">Sr</th>
                                <th style="width:90px;">Type</th>
                                <th>Department</th>
                                <th>With / Booked By</th>
                                <th>Names</th>
                                <th class="ctr" style="width:90px;">Breakfast</th>
                                <th class="ctr" style="width:90px;">Lunch</th>
                                <th class="ctr" style="width:90px;">Dinner</th>
                                <th style="width:150px;">Booked At</th>
                                <th class="ctr" style="width:60px;"></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($guestRows as $gi => $g): $names = canteenGuestNames($g['person_names']); $isTr = $g['booking_type'] === 'Trainee'; ?>
                            <tr>
                                <td><?php echo $gi + 1; ?></td>
                                <td><span class="cm-type <?php echo $isTr ? 'tr' : ''; ?>"><i class="fa-solid <?php echo $isTr ? 'fa-user-graduate' : 'fa-user-tie'; ?>"></i> <?php echo htmlspecialchars($g['booking_type']); ?></span></td>
                                <td><strong><?php echo htmlspecialchars((string) $g['department_name']); ?></strong></td>
                                <td>
                                    <span class="cm-code"><?php echo htmlspecialchars((string) $g['host_code']); ?></span>
                                    <span class="cm-desig" style="display:block;margin-top:3px;font-size:12.5px;color:#0f172a;font-weight:600;"><?php echo htmlspecialchars((string) ($g['host_name'] ?? '')); ?></span>
                                </td>
                                <td>
                                    <span class="cm-persons"><?php echo (int) $g['person_count']; ?> person<?php echo (int) $g['person_count'] === 1 ? '' : 's'; ?></span>
                                    <div class="cm-namelist"><?php echo htmlspecialchars(implode(', ', $names)); ?></div>
                                    <?php if (trim((string) $g['remarks']) !== ''): ?><div class="cm-remark"><i class="fa-regular fa-note-sticky"></i> <?php echo htmlspecialchars((string) $g['remarks']); ?></div><?php endif; ?>
                                </td>
                                <?php foreach (['breakfast' => 'bf', 'lunch' => 'ln', 'dinner' => 'dn'] as $m => $cls): ?>
                                <td class="ctr"><?php echo (int) $g[$m] === 1 ? '<span class="cm-chip ' . $cls . '">' . (int) $g['person_count'] . '</span>' : '<span class="cm-no">—</span>'; ?></td>
                                <?php endforeach; ?>
                                <td class="cm-time"><i class="fa-regular fa-clock"></i> <?php echo htmlspecialchars(date('d-m-Y h:i A', strtotime((string) $g['updated_at']))); ?></td>
                                <td class="ctr">
                                    <form method="POST" onsubmit="return confirm('Delete this <?php echo htmlspecialchars(strtolower($g['booking_type'])); ?> entry (<?php echo (int) $g['person_count']; ?> persons)?');" style="margin:0;">
                                        <input type="hidden" name="action" value="delete_guest">
                                        <input type="hidden" name="guest_id" value="<?php echo (int) $g['id']; ?>">
                                        <input type="hidden" name="token" value="<?php echo htmlspecialchars($_SESSION['canteen_admin_token']); ?>">
                                        <button type="submit" class="cm-del" title="Delete entry"><i class="fa-solid fa-trash-can"></i></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="5" style="text-align:right;">Total — <?php echo $guestTot['guests']; ?> persons</td>
                                <td class="ctr" style="color:#b45309;"><?php echo $guestTot['breakfast']; ?></td>
                                <td class="ctr" style="color:#15803d;"><?php echo $guestTot['lunch']; ?></td>
                                <td class="ctr" style="color:#4338ca;"><?php echo $guestTot['dinner']; ?></td>
                                <td colspan="2"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <?php endif; ?>
            </div>

            <div class="cm-tab-pane" data-pane="dept">
                <div class="table-wrap" style="border:1px solid #e2e8f0;border-radius:12px;overflow:hidden;">
                    <table class="cm-table sum">
                        <thead>
                            <tr>
                                <th style="width:56px;">Sr</th>
                                <th>Department</th>
                                <th class="ctr">Employees</th>
                                <th class="ctr">Guests / Trainees</th>
                                <th class="ctr">Breakfast</th>
                                <th class="ctr">Lunch</th>
                                <th class="ctr">Dinner</th>
                                <th class="ctr">Total Meals</th>
                                <th style="width:160px;">Share</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php $i = 1; foreach ($byDept as $dname => $s): $dm = $s['breakfast'] + $s['lunch'] + $s['dinner']; ?>
                            <tr>
                                <td><?php echo $i++; ?></td>
                                <td><strong><?php echo htmlspecialchars($dname); ?></strong></td>
                                <td class="ctr"><?php echo $s['employees']; ?></td>
                                <td class="ctr"><?php echo $s['guests'] > 0 ? '<span class="cm-chip gs">' . $s['guests'] . '</span>' : '<span class="cm-no">—</span>'; ?></td>
                                <td class="ctr"><span class="cm-chip bf"><?php echo $s['breakfast']; ?></span></td>
                                <td class="ctr"><span class="cm-chip ln"><?php echo $s['lunch']; ?></span></td>
                                <td class="ctr"><span class="cm-chip dn"><?php echo $s['dinner']; ?></span></td>
                                <td class="ctr"><strong><?php echo $dm; ?></strong></td>
                                <td><div class="cm-bar" title="<?php echo $totalMeals > 0 ? round($dm * 100 / $totalMeals) : 0; ?>%"><span style="width:<?php echo $totalMeals > 0 ? round($dm * 100 / $totalMeals) : 0; ?>%;"></span></div></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="2" style="text-align:right;">GRAND TOTAL</td>
                                <td class="ctr"><?php echo $tot['employees']; ?></td>
                                <td class="ctr"><?php echo $tot['guests']; ?></td>
                                <td class="ctr"><?php echo $tot['breakfast']; ?></td>
                                <td class="ctr"><?php echo $tot['lunch']; ?></td>
                                <td class="ctr"><?php echo $tot['dinner']; ?></td>
                                <td class="ctr"><?php echo $totalMeals; ?></td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<script>
(function () {
    var form = document.getElementById('cmFilter');
    document.querySelectorAll('[data-autosubmit]').forEach(function (el) {
        el.addEventListener('change', function () { form.submit(); });
        if (window.jQuery) { window.jQuery(el).on('select2:select', function () { form.submit(); }); }
    });
    form.addEventListener('submit', function () {
        var meal = form.querySelector('[name="meal"]');
        if (meal && meal.value === 'all') { meal.disabled = true; }
    });

    document.querySelectorAll('[data-drop]').forEach(function (drop) {
        drop.querySelector('[data-drop-btn]').addEventListener('click', function (e) {
            e.stopPropagation();
            var wasOpen = drop.classList.contains('open');
            document.querySelectorAll('[data-drop].open').forEach(function (d) { d.classList.remove('open'); });
            if (!wasOpen) { drop.classList.add('open'); }
        });
    });
    document.addEventListener('click', function () {
        document.querySelectorAll('[data-drop].open').forEach(function (d) { d.classList.remove('open'); });
    });

    var KEY = 'canteenMealTab';
    function showTab(name) {
        document.querySelectorAll('.cm-tab-btn').forEach(function (b) { b.classList.toggle('on', b.dataset.tab === name); });
        document.querySelectorAll('.cm-tab-pane').forEach(function (p) { p.classList.toggle('on', p.dataset.pane === name); });
    }
    document.querySelectorAll('.cm-tab-btn').forEach(function (b) {
        b.addEventListener('click', function () {
            showTab(b.dataset.tab);
            try { localStorage.setItem(KEY, b.dataset.tab); } catch (e) {}
        });
    });
    var urlTab = new URLSearchParams(location.search).get('tab');
    var savedTab = urlTab;
    try { savedTab = savedTab || localStorage.getItem(KEY); } catch (e) {}
    if (savedTab === 'dept' || savedTab === 'guest') { showTab(savedTab); }

    document.querySelectorAll('[data-toggle-group]').forEach(function (h) {
        h.addEventListener('click', function () { h.parentElement.classList.toggle('collapsed'); });
    });
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
