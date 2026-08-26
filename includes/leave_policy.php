<?php
/**
 * Leave Policy PDF — file path helpers
 */

function leavePolicyUploadDir()
{
    return dirname(__DIR__) . '/assets/uploads/policies';
}

function leavePolicyRelativePath()
{
    return 'assets/uploads/policies/leave_policy.pdf';
}

function leavePolicyAbsolutePath()
{
    return leavePolicyUploadDir() . '/leave_policy.pdf';
}

function leavePolicyExists()
{
    $path = leavePolicyAbsolutePath();
    return is_file($path) && filesize($path) > 0;
}

function leavePolicyEnsureDir()
{
    $dir = leavePolicyUploadDir();
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $keep = $dir . '/.gitkeep';
    if (!is_file($keep)) {
        file_put_contents($keep, '');
    }
    return $dir;
}
