<?php
/**
 * Holiday Master — Monthly Holiday Set
 */

$masterKey = 'holidays';
require_once __DIR__ . '/../_core/bootstrap.php';
require_once __DIR__ . '/../../includes/master_helper.php';

ensureMasterTables();

$month = (int) ($_GET['month'] ?? date('n'));
$year = (int) ($_GET['year'] ?? date('Y'));
if ($month < 1 || $month > 12) {
    $month = (int) date('n');
}
if ($year < 2000 || $year > 2100) {
    $year = (int) date('Y');
}

$monthDays = (int) date('t', strtotime(sprintf('%04d-%02d-01', $year, $month)));
$from = sprintf('%04d-%02d-01', $year, $month);
$to = sprintf('%04d-%02d-%02d', $year, $month, $monthDays);

$conn = getDBConnection();
$stmt = $conn->prepare(
    "SELECT id, title, holiday_date, is_paid, remarks
     FROM holidays
     WHERE status = 1 AND holiday_type = 'Holiday' AND holiday_date BETWEEN ? AND ?
     ORDER BY holiday_date ASC"
);
$stmt->bind_param('ss', $from, $to);
$stmt->execute();
$existing = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$conn->close();

$byDate = [];
foreach ($existing as $r) {
    $d = substr((string) $r['holiday_date'], 0, 10);
    $byDate[$d] = $r;
}

$pageTitle = 'Monthly Holiday Set';
$useSidebar = true;
$sidebarMode = 'masters';
$sidebarActive = 'holidays';
$msg = (string) ($_GET['msg'] ?? '');

require_once __DIR__ . '/../../includes/header.php';
?>

<main class="dashboard-main">
    <div class="page-toolbar flex-between">
        <a href="<?php echo app_url('masters/holidays/index.php'); ?>" class="back-link">
            <i class="fa-solid fa-arrow-left"></i> Back to Holiday Master
        </a>
    </div>

    <div class="form-page-card">
        <div class="form-page-header">
            <div>
                <h1>Monthly Holiday Set</h1>
                <p>Mark holidays for the month. Paid holidays count in salary only when employee <strong>Holiday Benefits = Yes</strong>.</p>
            </div>
        </div>

        <?php if ($msg === 'saved'): ?>
            <div class="login-alert" style="background:#ecfdf5;border-color:#a7f3d0;color:#047857;margin-bottom:16px;">
                Monthly holidays saved.
            </div>
        <?php endif; ?>

        <form method="GET" class="employee-form" style="margin-bottom:16px;">
            <div class="form-grid form-grid-3">
                <div class="form-group">
                    <label>Month</label>
                    <select name="month" class="form-control" onchange="this.form.submit()">
                        <?php for ($m = 1; $m <= 12; $m++): ?>
                            <option value="<?php echo $m; ?>" <?php echo $m === $month ? 'selected' : ''; ?>>
                                <?php echo date('F', mktime(0, 0, 0, $m, 1)); ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Year</label>
                    <input type="number" name="year" class="form-control" value="<?php echo $year; ?>" onchange="this.form.submit()">
                </div>
            </div>
        </form>

        <form method="POST" action="<?php echo app_url('masters/holidays/monthly_save.php'); ?>" class="employee-form">
            <input type="hidden" name="month" value="<?php echo $month; ?>">
            <input type="hidden" name="year" value="<?php echo $year; ?>">

            <div class="table-wrap">
                <table class="data-table" style="width:100%">
                    <thead>
                        <tr>
                            <th style="width:70px;">Select</th>
                            <th>Date</th>
                            <th>Day</th>
                            <th>Title</th>
                            <th>Paid</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php for ($d = 1; $d <= $monthDays; $d++):
                        $date = sprintf('%04d-%02d-%02d', $year, $month, $d);
                        $dayName = date('l', strtotime($date));
                        $row = $byDate[$date] ?? null;
                        $checked = $row ? 'checked' : '';
                        $title = $row ? (string) $row['title'] : '';
                        $paid = $row ? (string) ($row['is_paid'] ?? 'Yes') : 'Yes';
                        ?>
                        <tr>
                            <td class="center">
                                <input type="checkbox" name="days[<?php echo $d; ?>][on]" value="1" <?php echo $checked; ?>>
                            </td>
                            <td><?php echo date('d/m/Y', strtotime($date)); ?></td>
                            <td><?php echo htmlspecialchars($dayName); ?></td>
                            <td>
                                <input type="text" name="days[<?php echo $d; ?>][title]" class="form-control"
                                       value="<?php echo htmlspecialchars($title); ?>"
                                       placeholder="e.g. Independence Day">
                            </td>
                            <td>
                                <select name="days[<?php echo $d; ?>][is_paid]" class="form-control">
                                    <option value="Yes" <?php echo $paid === 'Yes' ? 'selected' : ''; ?>>Yes (Paid)</option>
                                    <option value="No" <?php echo $paid === 'No' ? 'selected' : ''; ?>>No</option>
                                </select>
                            </td>
                        </tr>
                    <?php endfor; ?>
                    </tbody>
                </table>
            </div>

            <div class="form-actions sticky-actions">
                <button type="submit" class="btn-primary">
                    <i class="fa-solid fa-floppy-disk"></i> Save Monthly Holidays
                </button>
                <a href="<?php echo app_url('masters/holidays/index.php'); ?>" class="btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</main>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
