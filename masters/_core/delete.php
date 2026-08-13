<?php
/**
 * Shared Master Soft Delete
 * Requires $masterKey set before include.
 */

require_once __DIR__ . '/bootstrap.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id > 0) {
    softDeleteMasterRow($master['table'], $id);
}

header('Location: ' . $masterListUrl . '?msg=deleted');
exit;
