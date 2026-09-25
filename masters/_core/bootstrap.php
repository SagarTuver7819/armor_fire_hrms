<?php
/**
 * Shared bootstrap for master CRUD pages
 * Expects $masterKey to be set by the folder wrapper.
 */

if (!isset($masterKey) || $masterKey === '') {
    http_response_code(500);
    die('Master key not set.');
}

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/master_helper.php';

requireLogin();
require_once __DIR__ . '/../../includes/permission_helper.php';
requireAccess('masters', 'view');

$master = getMasterConfig($masterKey);
if (!$master) {
    http_response_code(404);
    die('Master not found.');
}

ensureMasterTables();

if (!empty($master['fill_department_options'])) {
    $deptOpts = [];
    if (($master['key'] ?? '') === 'holidays') {
        $deptOpts['0'] = 'All Departments';
    } else {
        $deptOpts[''] = 'Select Department';
    }
    foreach (getActiveMasterRows('departments', 'sort_order ASC, department_name ASC') as $d) {
        $deptOpts[(string) $d['id']] = (string) $d['department_name'];
    }
    foreach ($master['fields'] as $fi => $field) {
        if (($field['name'] ?? '') === 'department_id') {
            $master['fields'][$fi]['options'] = $deptOpts;
        }
    }
}

if (!empty($master['fill_state_options'])) {
    $stateOpts = ['' => 'Select State'];
    foreach (getActiveMasterRows('assigned_states', 'sort_order ASC, name ASC') as $s) {
        $stateOpts[(string) $s['id']] = (string) $s['name'];
    }
    foreach ($master['fields'] as $fi => $field) {
        if (($field['name'] ?? '') === 'state_id') {
            $master['fields'][$fi]['options'] = $stateOpts;
        }
    }
}

$masterBase = 'masters/' . $master['folder'];
$masterListUrl = app_url($masterBase . '/index.php');
$masterAddUrl  = app_url($masterBase . '/edit.php');
$masterSaveUrl = app_url($masterBase . '/save.php');
$masterAjaxUrl = app_url($masterBase . '/ajax_list.php');
$mastersHubUrl = app_url('masters/index.php');
