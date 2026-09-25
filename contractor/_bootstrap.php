<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/contractor_helper.php';
require_once __DIR__ . '/../includes/permission_helper.php';
requireLogin();
requireAccess('contractor', 'view');

$connBoot = getDBConnection();
ensureContractorTables($connBoot);
$connBoot->close();
