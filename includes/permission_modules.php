<?php
/**
 * Modules available in Role & Responsibility matrix.
 * Company Settings + DB Sync stay Admin-only (not listed here).
 */

function getPermissionModules()
{
    return [
        'employees' => [
            'key'   => 'employees',
            'label' => 'Employees',
            'icon'  => 'fa-users',
        ],
        'circulars' => [
            'key'   => 'circulars',
            'label' => 'Circulars',
            'icon'  => 'fa-file-circle-plus',
        ],
        'policies' => [
            'key'   => 'policies',
            'label' => 'Policies',
            'icon'  => 'fa-scroll',
        ],
        'attendance' => [
            'key'   => 'attendance',
            'label' => 'Attendance',
            'icon'  => 'fa-calendar-check',
        ],
        'leave' => [
            'key'   => 'leave',
            'label' => 'Leave',
            'icon'  => 'fa-plane-departure',
        ],
        'payroll' => [
            'key'   => 'payroll',
            'label' => 'Payroll / Salary',
            'icon'  => 'fa-money-check-dollar',
        ],
        'masters' => [
            'key'   => 'masters',
            'label' => 'Masters',
            'icon'  => 'fa-database',
        ],
        'contractor' => [
            'key'   => 'contractor',
            'label' => 'Contractor',
            'icon'  => 'fa-helmet-safety',
        ],
        'departments' => [
            'key'   => 'departments',
            'label' => 'Departments Workspace',
            'icon'  => 'fa-building',
        ],
    ];
}

function getPermissionActions()
{
    return [
        'view'   => 'View',
        'add'    => 'Add',
        'edit'   => 'Edit',
        'delete' => 'Delete',
    ];
}

function isValidPermissionModule($module)
{
    $modules = getPermissionModules();
    return isset($modules[$module]);
}

function isValidPermissionAction($action)
{
    return in_array($action, ['view', 'add', 'edit', 'delete'], true);
}
