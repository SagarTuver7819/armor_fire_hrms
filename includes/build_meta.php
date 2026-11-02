<?php
/**
 * Bump BUILD_ID whenever attendance/payroll day logic ships to live.
 * Attendance Report shows this so we can confirm deploy reflected.
 */
if (!defined('HRMS_BUILD_ID')) {
    define('HRMS_BUILD_ID', '2026-11-02-r9-assy2-aashish-split');
}
