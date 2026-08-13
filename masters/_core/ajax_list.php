<?php
/**
 * Shared Master DataTables JSON
 * Requires $masterKey set before include.
 */

require_once __DIR__ . '/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
echo json_encode(masterAjaxListJson($master));
