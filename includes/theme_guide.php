<?php
/**
 * Theme tokens + when to use sidebar
 * Include is optional — CSS already uses :root in style.css
 *
 * SETUP RULE (Armor Fire HRMS):
 * ------------------------------------------------------------
 * 1) DASHBOARD (boxes only, NO sidebar)
 *    - Full-width card grid: Masters + Department Workspace + Reports
 *    - Department box → department.php (module boxes) → Join Employee list
 *    - File: dashboard.php  → do NOT set $useSidebar
 *
 * 2) WORKSPACE / GRID / CRUD / FORM pages (WITH sidebar)
 *    Before include header.php set:
 *      $useSidebar = true;
 *      $sidebarMode = 'department' | 'masters' | 'employees';
 *      $sidebarDeptId = 5;                 // when inside a department
 *      $sidebarActive = 'modules' | 'join_employee' | 'hub' | master key | 'all_employees' | 'settings';
 *
 * 3) Navigation flow (manufacturing)
 *    Dashboard boxes → department.php (module boxes + sidebar)
 *    Join Employee box → employees/index.php (grid + sidebar)
 *    employees/view.php & edit.php → sidebar ON
 *    Sidebar → Department submenu → department.php (boxes, not direct list)
 *    Masters box → masters/index.php (hub boxes + sidebar)
 *
 * Brand colors (keep consistent):
 *    --brand: #F58220
 *    --ink:   #1a2332
 *    --page:  #f0f3f7
 */
