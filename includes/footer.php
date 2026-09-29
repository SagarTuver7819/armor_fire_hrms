<?php
/**
 * Common Footer
 */
require_once __DIR__ . '/../config/app.php';
?>
<?php if (!empty($useSidebar)): ?>
        </div><!-- /.app-content -->
    </div><!-- /.app-shell -->
<?php endif; ?>
    <footer class="page-footer">
        <div class="footer-left">2026 &copy; Armor Fire HRMS</div>
        <div class="footer-right">Designed &amp; Developed by Ocean Infotech</div>
    </footer>

    <!-- Delete confirmation popup -->
    <div id="confirmDeleteModal" class="confirm-modal" hidden aria-hidden="true" role="dialog" aria-labelledby="confirmDeleteTitle">
        <div class="confirm-modal-backdrop"></div>
        <div class="confirm-modal-box">
            <div class="confirm-modal-icon">
                <i class="fa-solid fa-triangle-exclamation"></i>
            </div>
            <h3 id="confirmDeleteTitle">Are you sure?</h3>
            <p class="confirm-modal-msg">Are you sure you want to delete this record?</p>
            <div class="confirm-modal-actions">
                <button type="button" class="btn-secondary confirm-cancel">Cancel</button>
                <button type="button" class="btn-primary confirm-yes">
                    <i class="fa-solid fa-trash"></i> Yes, Delete
                </button>
            </div>
        </div>
    </div>

    <script src="<?php echo app_url('assets/js/dashboard.js'); ?>?v=<?php echo (int) @filemtime(__DIR__ . '/../assets/js/dashboard.js'); ?>"></script>
    <script src="<?php echo app_url('assets/js/header_clock.js'); ?>?v=<?php echo (int) @filemtime(__DIR__ . '/../assets/js/header_clock.js'); ?>"></script>
    <script src="<?php echo app_url('assets/js/header_notify.js'); ?>?v=<?php echo (int) @filemtime(__DIR__ . '/../assets/js/header_notify.js'); ?>"></script>
    <script src="<?php echo app_url('assets/js/confirm_delete.js'); ?>?v=<?php echo (int) @filemtime(__DIR__ . '/../assets/js/confirm_delete.js'); ?>"></script>
    <?php if (!empty($useSidebar)): ?>
        <script src="<?php echo app_url('assets/js/sidebar.js'); ?>?v=<?php echo (int) @filemtime(__DIR__ . '/../assets/js/sidebar.js'); ?>"></script>
    <?php endif; ?>
    <?php if (!empty($showHeaderEmpSearch)): ?>
        <script src="<?php echo app_url('assets/js/hr_employee_search.js'); ?>?v=<?php echo (int) @filemtime(__DIR__ . '/../assets/js/hr_employee_search.js'); ?>"></script>
    <?php endif; ?>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="<?php echo app_url('assets/js/date_format.js'); ?>?v=<?php echo (int) @filemtime(__DIR__ . '/../assets/js/date_format.js'); ?>"></script>
    <?php if (!empty($extraJs) && is_array($extraJs)): ?>
        <?php foreach ($extraJs as $js): ?>
            <?php
            // jQuery already loaded globally above — skip duplicates
            if (strpos($js, 'jquery') !== false && strpos($js, 'dataTables') === false) {
                continue;
            }
            $src = (strpos($js, 'http') === 0) ? $js : app_url($js);
            // Preserve ?v= cache-bust if already present; otherwise add filemtime for local assets
            if (strpos($js, 'http') !== 0 && strpos($js, '?') === false) {
                $local = __DIR__ . '/../' . ltrim(explode('?', $js)[0], '/');
                $src .= '?v=' . (int) @filemtime($local);
            }
            ?>
            <script src="<?php echo htmlspecialchars($src); ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>
    <?php if (!empty($staffLeaveApplyToasts) && is_array($staffLeaveApplyToasts)): ?>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
        <script>
            window.STAFF_LEAVE_APPLY_TOASTS = <?php echo json_encode($staffLeaveApplyToasts, JSON_UNESCAPED_UNICODE); ?>;
        </script>
        <script src="<?php echo app_url('assets/js/staff_leave_toast.js'); ?>?v=<?php echo (int) @filemtime(__DIR__ . '/../assets/js/staff_leave_toast.js'); ?>"></script>
    <?php endif; ?>
    <script src="<?php echo app_url('assets/js/select2_init.js'); ?>?v=<?php echo (int) @filemtime(__DIR__ . '/../assets/js/select2_init.js'); ?>"></script>
    <?php
    // Global shift-wise KPI hour reminder for employee login (any page)
    $kpiReminderEmpId = (int) ($_SESSION['employee_id'] ?? 0);
    $enableKpiReminder = $kpiReminderEmpId > 0
        && function_exists('isEmployee')
        && isEmployee()
        && empty($disableKpiReminder);
    if ($enableKpiReminder):
    ?>
    <script>
        window.KPI_REMINDER = {
            dueUrl: <?php echo json_encode(app_url('employee/ajax_kpi_due.php')); ?>,
            hourUrl: <?php echo json_encode(app_url('employee/ajax_kpi_hour.php')); ?>,
            kpiUrl: <?php echo json_encode(app_url('employee/kpi.php')); ?>,
            today: <?php echo json_encode(date('Y-m-d')); ?>
        };
    </script>
    <script src="<?php echo app_url('assets/js/kpi_reminder.js'); ?>?v=<?php echo (int) @filemtime(__DIR__ . '/../assets/js/kpi_reminder.js'); ?>"></script>
    <?php endif; ?>
</body>
</html>
