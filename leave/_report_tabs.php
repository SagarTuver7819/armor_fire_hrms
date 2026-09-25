<?php
/**
 * Shared sub-nav for Leave reports
 * Expects $sidebarActive: leave_encashment | coff_history | coff_report | dl_report
 */
$leaveRptActive = $sidebarActive ?? '';
?>
<nav class="leave-rpt-tabs" aria-label="Leave reports">
    <a href="<?php echo app_url('leave/encashment.php'); ?>"
       class="leave-rpt-tab <?php echo $leaveRptActive === 'leave_encashment' ? 'active' : ''; ?>">
        <i class="fa-solid fa-money-bill-wave"></i> Leave Encashment
    </a>
    <a href="<?php echo app_url('leave/coff_history.php'); ?>"
       class="leave-rpt-tab <?php echo $leaveRptActive === 'coff_history' ? 'active' : ''; ?>">
        <i class="fa-solid fa-clock-rotate-left"></i> C-Off History
    </a>
    <a href="<?php echo app_url('leave/coff_report.php'); ?>"
       class="leave-rpt-tab <?php echo $leaveRptActive === 'coff_report' ? 'active' : ''; ?>">
        <i class="fa-solid fa-file-invoice"></i> C-Off Report
    </a>
    <a href="<?php echo app_url('leave/dl_report.php'); ?>"
       class="leave-rpt-tab <?php echo $leaveRptActive === 'dl_report' ? 'active' : ''; ?>">
        <i class="fa-solid fa-briefcase"></i> Duty Leave
    </a>
    <a href="<?php echo app_url('leave/lwp_report.php'); ?>"
       class="leave-rpt-tab <?php echo $leaveRptActive === 'lwp_report' ? 'active' : ''; ?>">
        <i class="fa-solid fa-user-xmark"></i> LWP Report
    </a>
</nav>
