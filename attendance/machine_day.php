<?php
/**
 * Machine day-wise punch sheet (O/X + multiple punch columns)
 * Data from machine_attendance_logs only — separate from Attendance Report
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permission_helper.php';
require_once __DIR__ . '/../includes/biometric_helper.php';

requireLogin();
requireAccess('attendance', 'view');
ensureBiometricTables();

$code = trim((string) ($_GET['code'] ?? ''));
$machineId = (int) ($_GET['machine_id'] ?? 0);
$fromDate = trim((string) ($_GET['from_date'] ?? date('Y-m-d', strtotime('-14 days'))));
$toDate = trim((string) ($_GET['to_date'] ?? date('Y-m-d')));

// Code list for dropdown
$conn = getDBConnection();
$codes = [];
$cr = $conn->query(
    "SELECT employee_code, MAX(employee_name) AS employee_name, COUNT(*) AS c
     FROM machine_attendance_logs
     WHERE employee_code IS NOT NULL AND TRIM(employee_code) <> ''
     GROUP BY employee_code
     ORDER BY employee_code ASC
     LIMIT 2000"
);
if ($cr) {
    while ($r = $cr->fetch_assoc()) {
        $codes[] = $r;
    }
}
$machines = fetchBiometricMachines(false);
$sheet = null;
if ($code !== '') {
    $sheet = getMachineWiseDaySheet($code, $fromDate, $toDate, $machineId, $conn);
}
$conn->close();

$pageTitle = 'Machine Day Punches';
$useSidebar = true;
$sidebarMode = 'attendance';
$sidebarActive = 'attendance_machine_report';

require_once __DIR__ . '/../includes/header.php';
?>

<main class="dashboard-main">
    <div class="page-toolbar flex-between">
        <a href="<?php echo app_url('attendance/machine_report.php?show=1'); ?>" class="back-link">
            <i class="fa-solid fa-arrow-left"></i> Machine Wise Report
        </a>
        <a href="<?php echo app_url('attendance/machine_sync.php'); ?>" class="btn-primary">
            <i class="fa-solid fa-rotate"></i> Sync
        </a>
    </div>

    <div class="form-page-card">
        <div class="form-page-header">
            <h1>Machine Day Punch Sheet</h1>
            <p>O = punch present · X = no punch · Multiple IN/OUT columns · Machine data only (not Attendance Report)</p>
        </div>

        <form method="get" class="employee-form" style="margin-bottom:16px;">
            <div class="form-grid form-grid-4">
                <div class="form-group">
                    <label>Machine Code / Bio ID</label>
                    <input type="text" name="code" class="form-control" list="machineCodes"
                           value="<?php echo htmlspecialchars($code); ?>" placeholder="e.g. AS76114 / CO72036" required>
                    <datalist id="machineCodes">
                        <?php foreach ($codes as $c): ?>
                            <option value="<?php echo htmlspecialchars($c['employee_code']); ?>">
                                <?php echo htmlspecialchars($c['employee_code'] . ' (' . (int) $c['c'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </datalist>
                </div>
                <div class="form-group">
                    <label>Machine</label>
                    <select name="machine_id" class="form-control">
                        <option value="0">All</option>
                        <?php foreach ($machines as $m): ?>
                            <option value="<?php echo (int) $m['id']; ?>" <?php echo $machineId === (int) $m['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($m['machine_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>From</label>
                    <input type="date" name="from_date" class="form-control" value="<?php echo htmlspecialchars($fromDate); ?>">
                </div>
                <div class="form-group">
                    <label>To</label>
                    <input type="date" name="to_date" class="form-control" value="<?php echo htmlspecialchars($toDate); ?>">
                </div>
                <div class="form-group" style="display:flex;align-items:flex-end;">
                    <button type="submit" class="btn-primary" style="width:100%;">
                        <i class="fa-solid fa-filter"></i> Show
                    </button>
                </div>
            </div>
        </form>

        <?php if ($sheet): ?>
            <?php
            $meta = $sheet['meta'];
            $max = (int) $sheet['max_slots'];
            ?>
            <div class="ops-live-summary" style="margin-bottom:12px;">
                <span class="ops-chip"><?php echo htmlspecialchars((string) ($meta['employee_code'] ?? $code)); ?></span>
                <?php if (!empty($meta['employee_name'])): ?>
                    <span class="ops-chip"><?php echo htmlspecialchars($meta['employee_name']); ?></span>
                <?php endif; ?>
                <span class="ops-chip"><?php echo htmlspecialchars($fromDate); ?> → <?php echo htmlspecialchars($toDate); ?></span>
            </div>

            <div class="table-wrap">
                <table class="data-table machine-day-sheet" style="width:100%;border-collapse:collapse;font-size:13px;">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Status</th>
                            <?php for ($i = 1; $i <= $max; $i++): ?>
                                <th>P<?php echo $i; ?></th>
                            <?php endfor; ?>
                            <th>First</th>
                            <th>Last</th>
                            <th>Worked</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($sheet['days'] as $day): ?>
                        <tr class="<?php echo $day['status'] === 'O' ? 'is-present-row' : 'is-absent-row'; ?>">
                            <td><?php echo htmlspecialchars(date('d/m/Y', strtotime($day['date']))); ?></td>
                            <td style="text-align:center;font-weight:800;"><?php echo $day['status']; ?></td>
                            <?php for ($i = 0; $i < $max; $i++): ?>
                                <td style="text-align:center;">
                                    <?php
                                    if (!empty($day['punches'][$i])) {
                                        $p = $day['punches'][$i];
                                        echo htmlspecialchars($p['time']);
                                        echo ' <small class="bio-meta">' . strtoupper($p['type']) . '</small>';
                                    } else {
                                        echo '<span class="bio-meta">--:--</span>';
                                    }
                                    ?>
                                </td>
                            <?php endfor; ?>
                            <td style="text-align:center;"><?php echo htmlspecialchars($day['first']); ?></td>
                            <td style="text-align:center;"><?php echo htmlspecialchars($day['last']); ?></td>
                            <td style="text-align:center;"><?php echo htmlspecialchars($day['worked']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php elseif ($code === ''): ?>
            <div class="alert alert-success">Enter a machine employee code (e.g. AS76114) to view day-wise punches.</div>
        <?php endif; ?>
    </div>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
