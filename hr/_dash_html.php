
require_once __DIR__ . '/../includes/header.php';
?>

<main class="dashboard-main hr-dash hr-dash-clean">
    <section class="hr-clean-hero">
        <div>
            <p class="hr-clean-eyebrow">HR Workspace · <?php echo htmlspecialchars($roleLabel); ?></p>
            <h1><?php echo htmlspecialchars($greet); ?>, <?php echo htmlspecialchars($userName); ?></h1>
            <p class="hr-clean-sub"><?php echo htmlspecialchars(date('l, d F Y')); ?> · People, attendance, leave &amp; voice in one place</p>
        </div>
        <div class="hr-clean-actions">
            <a class="btn-primary" href="<?php echo app_url('dashboard.php#department-workspace'); ?>"><i class="fa-solid fa-user-plus"></i> Add Employee</a>
            <a class="btn-ghost" href="<?php echo app_url('attendance/manual.php'); ?>"><i class="fa-solid fa-pen-to-square"></i> Manual Attendance</a>
            <a class="btn-ghost" href="<?php echo app_url('leave/index.php'); ?>"><i class="fa-solid fa-scale-balanced"></i> Leave Desk</a>
            <a class="btn-ghost" href="<?php echo app_url('employee_voice/index.php'); ?>"><i class="fa-solid fa-comments"></i> Voice</a>
            <a class="btn-ghost" href="<?php echo app_url('circulars/edit.php'); ?>"><i class="fa-solid fa-file-circle-plus"></i> Circular</a>
            <a class="btn-ghost" href="<?php echo app_url('policies/edit.php'); ?>"><i class="fa-solid fa-scroll"></i> Policy</a>
        </div>
    </section>

    <section class="hr-clean-kpi">
        <a class="hr-clean-kpi-card" href="<?php echo app_url('employees/index.php'); ?>">
            <i class="fa-solid fa-users" style="color:#2563eb;"></i>
            <div><span>Active</span><strong><?php echo number_format($kpi['active']); ?></strong></div>
        </a>
        <a class="hr-clean-kpi-card" href="<?php echo app_url('attendance/report.php?show=1&month=' . $month . '&year=' . $year); ?>">
            <i class="fa-solid fa-user-check" style="color:#059669;"></i>
            <div><span>Present</span><strong><?php echo number_format($kpi['present_today']); ?></strong></div>
        </a>
        <div class="hr-clean-kpi-card">
            <i class="fa-solid fa-user-xmark" style="color:#dc2626;"></i>
            <div><span>Absent</span><strong><?php echo number_format($kpi['absent_today']); ?></strong></div>
        </div>
        <a class="hr-clean-kpi-card" href="<?php echo app_url('leave/index.php?status=Pending'); ?>">
            <i class="fa-solid fa-clock" style="color:#d97706;"></i>
            <div><span>Leave Pending</span><strong><?php echo number_format($kpi['pending_leave']); ?></strong></div>
        </a>
        <div class="hr-clean-kpi-card">
            <i class="fa-solid fa-user-plus" style="color:#0d9488;"></i>
            <div><span>Joiners</span><strong><?php echo number_format($kpi['joiners']); ?></strong></div>
        </div>
        <a class="hr-clean-kpi-card" href="<?php echo app_url('employee_voice/index.php'); ?>">
            <i class="fa-solid fa-comments" style="color:#d2232a;"></i>
            <div><span>Voice Open</span><strong><?php echo number_format($kpi['voice_open']); ?></strong></div>
        </a>
        <a class="hr-clean-kpi-card" href="<?php echo app_url('employees/exit_list.php'); ?>">
            <i class="fa-solid fa-door-open" style="color:#7c3aed;"></i>
            <div><span>Exits</span><strong><?php echo number_format($kpi['exits']); ?></strong></div>
        </a>
        <a class="hr-clean-kpi-card" href="<?php echo app_url('hr/kpi.php'); ?>">
            <i class="fa-solid fa-clipboard-check" style="color:#be185d;"></i>
            <div><span>KPI Today</span><strong><?php echo number_format($kpi['kpi_submitted_today']); ?></strong></div>
        </a>
    </section>

    <div class="hr-clean-grid">
        <div class="hr-clean-main">
            <section class="hr-clean-card">
                <div class="hr-clean-card-head">
                    <div>
                        <h2>Department Attendance</h2>
                        <p>Today · <?php echo htmlspecialchars(formatDateDisplay($today)); ?> · <?php echo (int) $presencePct; ?>% present</p>
                    </div>
                    <a class="btn-ghost" href="<?php echo app_url('attendance/report.php?show=1&month=' . $month . '&year=' . $year); ?>">Full Report</a>
                </div>
                <?php if (!$deptAttendance): ?>
                    <div class="hr-clean-empty">No department headcount found.</div>
                <?php else: ?>
                <div class="table-wrap">
                    <table class="data-table hr-dept-att-table">
                        <thead>
                            <tr>
                                <th>Department</th>
                                <th class="num">Head</th>
                                <th class="num">Present</th>
                                <th class="num">Absent</th>
                                <th class="num">Leave</th>
                                <th class="num">WO/Hol</th>
                                <th>Presence</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($deptAttendance as $d):
                            $color = $d['icon_color'] ?: '#d2232a';
                            $icon = $d['icon_class'] ?: 'fa-building';
                            $pct = (int) $d['pct'];
                        ?>
                            <tr>
                                <td>
                                    <a class="hr-dept-link" href="<?php echo app_url('department.php?id=' . (int) $d['id']); ?>">
                                        <span class="hr-dept-ico" style="background:<?php echo htmlspecialchars($color); ?>">
                                            <i class="fa-solid <?php echo htmlspecialchars($icon); ?>"></i>
                                        </span>
                                        <strong><?php echo htmlspecialchars($d['department_name']); ?></strong>
                                    </a>
                                </td>
                                <td class="num"><?php echo (int) $d['headcount']; ?></td>
                                <td class="num ok"><?php echo (int) $d['present_c']; ?></td>
                                <td class="num bad"><?php echo (int) $d['absent_c']; ?></td>
                                <td class="num"><?php echo (int) $d['leave_c']; ?></td>
                                <td class="num muted"><?php echo (int) $d['wo_c'] + (int) $d['hol_c']; ?></td>
                                <td>
                                    <div class="hr-bar" title="<?php echo $pct; ?>%">
                                        <span style="width:<?php echo $pct; ?>%;background:<?php echo htmlspecialchars($color); ?>"></span>
                                    </div>
                                    <small><?php echo $pct; ?>%</small>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </section>

            <div class="hr-clean-split">
                <section class="hr-clean-card">
                    <div class="hr-clean-card-head">
                        <div>
                            <h2>Monthly Join</h2>
                            <p><?php echo htmlspecialchars($monthLabel); ?> · <?php echo count($monthlyJoinEmployees); ?></p>
                        </div>
                        <a class="btn-ghost" href="<?php echo app_url('employees/index.php'); ?>">All</a>
                    </div>
                    <?php if (!$monthlyJoinEmployees): ?>
                        <div class="hr-clean-empty">No joiners this month.</div>
                    <?php else: ?>
                        <ul class="hr-clean-list">
                            <?php foreach (array_slice($monthlyJoinEmployees, 0, 8) as $je): ?>
                            <li>
                                <a href="<?php echo app_url('employees/view.php?id=' . (int) $je['id']); ?>">
                                    <strong><?php echo htmlspecialchars((string) ($je['employee_name'] ?? '—')); ?></strong>
                                    <span><?php echo htmlspecialchars(trim(($je['employee_code'] ?? '') . ' · ' . ($je['department_name'] ?? '—'))); ?> · <?php echo htmlspecialchars(formatDateDisplay($je['date_of_joining'] ?? '')); ?></span>
                                </a>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </section>

                <section class="hr-clean-card">
                    <div class="hr-clean-card-head">
                        <div>
                            <h2>Monthly Left</h2>
                            <p><?php echo htmlspecialchars($monthLabel); ?> · <?php echo count($monthlyLeftEmployees); ?></p>
                        </div>
                        <a class="btn-ghost" href="<?php echo app_url('employees/exit_list.php'); ?>">Exit list</a>
                    </div>
                    <?php if (!$monthlyLeftEmployees): ?>
                        <div class="hr-clean-empty">No exits this month.</div>
                    <?php else: ?>
                        <ul class="hr-clean-list">
                            <?php foreach (array_slice($monthlyLeftEmployees, 0, 8) as $le): ?>
                            <li>
                                <a href="<?php echo app_url('employees/view.php?id=' . (int) $le['id']); ?>">
                                    <strong><?php echo htmlspecialchars((string) ($le['employee_name'] ?? '—')); ?></strong>
                                    <span><?php echo htmlspecialchars(trim(($le['employee_code'] ?? '') . ' · ' . ($le['department_name'] ?? '—'))); ?> · <?php echo htmlspecialchars(formatDateDisplay($le['date_of_exit'] ?? '')); ?></span>
                                </a>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </section>
            </div>

            <div class="hr-clean-split">
                <section class="hr-clean-card">
                    <div class="hr-clean-card-head">
                        <div>
                            <h2>Policies</h2>
                            <p><?php echo (int) $recentPoliciesTotal; ?> total</p>
                        </div>
                        <a class="btn-ghost" href="<?php echo app_url('policies/index.php'); ?>">View all</a>
                    </div>
                    <?php if (!$recentPolicies): ?>
                        <div class="hr-clean-empty">No policies yet.</div>
                    <?php else: ?>
                        <ul class="hr-clean-list">
                            <?php foreach (array_slice($recentPolicies, 0, 6) as $p): ?>
                            <li>
                                <a href="<?php echo app_url('policies/view.php?id=' . (int) $p['id']); ?>">
                                    <strong><?php echo htmlspecialchars((string) ($p['title'] ?? 'Policy')); ?></strong>
                                    <span><?php echo htmlspecialchars(formatDateDisplay($p['policy_date'] ?? ($p['added_date'] ?? ''))); ?></span>
                                </a>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </section>

                <section class="hr-clean-card">
                    <div class="hr-clean-card-head">
                        <div>
                            <h2>Circulars</h2>
                            <p><?php echo (int) $recentCircularsTotal; ?> total</p>
                        </div>
                        <a class="btn-ghost" href="<?php echo app_url('circulars/index.php'); ?>">View all</a>
                    </div>
                    <?php if (!$recentCirculars): ?>
                        <div class="hr-clean-empty">No circulars yet.</div>
                    <?php else: ?>
                        <ul class="hr-clean-list">
                            <?php foreach (array_slice($recentCirculars, 0, 6) as $c): ?>
                            <li>
                                <a href="<?php echo app_url('circulars/view.php?id=' . (int) $c['id']); ?>">
                                    <strong><?php echo htmlspecialchars((string) ($c['title'] ?? 'Circular')); ?></strong>
                                    <span><?php echo htmlspecialchars(formatDateDisplay($c['circular_date'] ?? ($c['added_date'] ?? ''))); ?></span>
                                </a>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </section>
            </div>
        </div>

        <aside class="hr-clean-side">
            <section class="hr-clean-card">
                <div class="hr-clean-card-head">
                    <div>
                        <h2>Leave Queue</h2>
                        <p><?php echo count($pendingLeaves); ?> pending</p>
                    </div>
                    <a class="btn-ghost" href="<?php echo app_url('leave/index.php?status=Pending'); ?>">All</a>
                </div>
                <?php if (!$pendingLeaves): ?>
                    <div class="hr-clean-empty">No pending leaves.</div>
                <?php else: ?>
                    <ul class="hr-clean-list">
                        <?php foreach ($pendingLeaves as $lr): ?>
                        <li>
                            <a href="<?php echo app_url('leave/index.php?status=Pending'); ?>">
                                <strong><?php echo htmlspecialchars((string) ($lr['employee_name'] ?? '—')); ?></strong>
                                <span><?php echo htmlspecialchars(trim(($lr['code'] ? $lr['code'] . ' · ' : '') . formatDateDisplay($lr['from_date'] ?? ''))); ?> · <?php echo number_format((float) $lr['days'], 1); ?>d</span>
                            </a>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </section>

            <section class="hr-clean-card">
                <div class="hr-clean-card-head">
                    <div>
                        <h2>Employee Voice</h2>
                        <p><?php echo (int) $kpi['voice_open']; ?> open</p>
                    </div>
                    <a class="btn-ghost" href="<?php echo app_url('employee_voice/index.php'); ?>">All</a>
                </div>
                <?php if (!$voiceTickets): ?>
                    <div class="hr-clean-empty">No open tickets.</div>
                <?php else: ?>
                    <ul class="hr-clean-list">
                        <?php foreach ($voiceTickets as $vt):
                            $meta = $evTypes[$vt['module_type']] ?? null;
                        ?>
                        <li>
                            <a href="<?php echo app_url('employee_voice/view.php?id=' . (int) $vt['id']); ?>">
                                <strong><?php echo htmlspecialchars((string) ($vt['ticket_no'] ?? '')); ?></strong>
                                <span><?php echo htmlspecialchars(($meta['short'] ?? '') . ' · ' . ($vt['status'] ?? '') . ' · ' . ($vt['employee_name'] ?: '—')); ?></span>
                            </a>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </section>

            <section class="hr-clean-card">
                <div class="hr-clean-card-head">
                    <div>
                        <h2>Birthdays</h2>
                        <p>Next 45 days · <?php echo (int) $upcomingBirthdaysTotal; ?></p>
                    </div>
                </div>
                <?php if (!$upcomingBirthdays): ?>
                    <div class="hr-clean-empty">No upcoming birthdays.</div>
                <?php else: ?>
                    <ul class="hr-clean-list">
                        <?php foreach (array_slice($upcomingBirthdays, 0, 8) as $b):
                            $when = ((int) ($b['in_days'] ?? 0) === 0) ? 'Today' : (((int) $b['in_days'] === 1) ? 'Tomorrow' : ('In ' . (int) $b['in_days'] . ' days'));
                        ?>
                        <li>
                            <a href="<?php echo app_url('employees/view.php?id=' . (int) $b['id']); ?>">
                                <strong><?php echo htmlspecialchars((string) ($b['employee_name'] ?? '—')); ?></strong>
                                <span><?php echo htmlspecialchars(formatDateDisplay($b['next_on'] ?? '') . ' · ' . $when); ?><?php if (!empty($b['department_name'])): ?> · <?php echo htmlspecialchars($b['department_name']); ?><?php endif; ?></span>
                            </a>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </section>

            <section class="hr-clean-card">
                <div class="hr-clean-card-head">
                    <div>
                        <h2>Anniversaries</h2>
                        <p>Next 30 days · <?php echo (int) $workAnniversariesTotal; ?></p>
                    </div>
                </div>
                <?php if (!$workAnniversaries): ?>
                    <div class="hr-clean-empty">No upcoming anniversaries.</div>
                <?php else: ?>
                    <ul class="hr-clean-list">
                        <?php foreach (array_slice($workAnniversaries, 0, 8) as $a):
                            $when = ((int) ($a['in_days'] ?? 0) === 0) ? 'Today' : (((int) $a['in_days'] === 1) ? 'Tomorrow' : ('In ' . (int) $a['in_days'] . ' days'));
                        ?>
                        <li>
                            <a href="<?php echo app_url('employees/view.php?id=' . (int) $a['id']); ?>">
                                <strong><?php echo htmlspecialchars((string) ($a['employee_name'] ?? '—')); ?></strong>
                                <span><?php echo htmlspecialchars((int) ($a['years'] ?? 0) . ' yr · ' . $when); ?><?php if (!empty($a['department_name'])): ?> · <?php echo htmlspecialchars($a['department_name']); ?><?php endif; ?></span>
                            </a>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </section>

            <section class="hr-clean-card">
                <div class="hr-clean-card-head">
                    <div>
                        <h2>Holidays</h2>
                        <p>Next 30 days</p>
                    </div>
                    <a class="btn-ghost" href="<?php echo app_url('masters/holidays/index.php'); ?>">Manage</a>
                </div>
                <?php if (!$upcomingHolidays): ?>
                    <div class="hr-clean-empty">No holidays soon.</div>
                <?php else: ?>
                    <ul class="hr-clean-list">
                        <?php foreach ($upcomingHolidays as $h): ?>
                        <li>
                            <div>
                                <strong><?php echo htmlspecialchars((string) ($h['title'] ?? 'Holiday')); ?></strong>
                                <span><?php echo htmlspecialchars(function_exists('holidayFormatDateRangeDisplay') ? holidayFormatDateRangeDisplay($h['holiday_date'] ?? '', $h['holiday_to_date'] ?? '') : formatDateDisplay($h['holiday_date'] ?? '')); ?></span>
                            </div>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </section>

            <section class="hr-clean-card">
                <div class="hr-clean-card-head">
                    <div>
                        <h2>Quick Links</h2>
                        <p>Daily HR work</p>
                    </div>
                </div>
                <div class="hr-clean-links">
                    <a href="<?php echo app_url('employees/index.php'); ?>"><i class="fa-solid fa-users"></i> Employees</a>
                    <a href="<?php echo app_url('attendance/import.php'); ?>"><i class="fa-solid fa-file-import"></i> Import Att.</a>
                    <a href="<?php echo app_url('leave/apply.php'); ?>"><i class="fa-solid fa-plus"></i> Apply Leave</a>
                    <a href="<?php echo app_url('hr/kpi.php'); ?>"><i class="fa-solid fa-clipboard-list"></i> KPI</a>
                    <a href="<?php echo app_url('payroll/register.php'); ?>"><i class="fa-solid fa-table"></i> Salary</a>
                    <a href="<?php echo app_url('dashboard.php'); ?>"><i class="fa-solid fa-building"></i> Workspace</a>
                </div>
            </section>
        </aside>
    </div>

    <section class="hr-clean-card" style="margin-top:14px;">
        <div class="hr-clean-card-head">
            <div>
                <h2>Employee Matrix</h2>
                <p>Department / HR / Payroll Heads</p>
            </div>
            <?php if ($canManageHeads): ?>
            <div style="display:flex;gap:8px;flex-wrap:wrap;">
                <a class="btn-ghost" href="<?php echo app_url('roles/assign.php'); ?>">Assign Head</a>
                <a class="btn-primary" href="<?php echo app_url('roles/department_heads.php'); ?>">Set Heads</a>
            </div>
            <?php endif; ?>
        </div>
        <div class="table-wrap emp-matrix-wrap">
            <table class="data-table emp-matrix-table">
                <thead>
                    <tr>
                        <th>Sr</th>
                        <th>Desk</th>
                        <th>Code</th>
                        <th>Name</th>
                        <th>Designation</th>
                        <th>Department</th>
                        <th>Mobile</th>
                        <th>Email</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$employeeMatrix): ?>
                    <tr><td colspan="8" class="emp-matrix-empty">No heads set yet.</td></tr>
                <?php else: foreach ($employeeMatrix as $i => $row):
                    $headCode = strtoupper(trim((string) ($row['head_role_code'] ?? 'DEPT_HEAD')));
                    $rowTone = $headCode === 'HR_HEAD' ? 'is-hr' : ($headCode === 'PAYROLL_HEAD' ? 'is-pay' : 'is-dept');
                    $desk = trim((string) ($row['desk_no'] ?? ''));
                    $code = trim((string) ($row['employee_code'] ?? ''));
                    $name = trim((string) ($row['employee_name'] ?? ''));
                    $desig = trim((string) ($row['designation'] ?? ''));
                    $dept = trim((string) ($row['department_name'] ?? ''));
                    $mobile = trim((string) ($row['office_mobile'] ?? ''));
                    $mail = trim((string) ($row['office_email'] ?? ''));
                ?>
                    <tr class="emp-matrix-row <?php echo $rowTone; ?>">
                        <td><?php echo $i + 1; ?></td>
                        <td><?php echo $desk !== '' ? htmlspecialchars($desk) : '—'; ?></td>
                        <td><?php echo $code !== '' ? htmlspecialchars($code) : '—'; ?></td>
                        <td><strong><?php echo htmlspecialchars($name !== '' ? $name : '—'); ?></strong></td>
                        <td><?php echo $desig !== '' ? htmlspecialchars($desig) : '—'; ?></td>
                        <td><?php echo $dept !== '' ? htmlspecialchars($dept) : '—'; ?></td>
                        <td><?php echo $mobile !== '' ? htmlspecialchars($mobile) : '—'; ?></td>
                        <td><?php echo $mail !== '' ? htmlspecialchars($mail) : '—'; ?></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
