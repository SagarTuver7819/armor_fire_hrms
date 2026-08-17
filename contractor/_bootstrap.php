<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/contractor_helper.php';
requireLogin();

$connBoot = getDBConnection();
ensureContractorTables($connBoot);
$connBoot->close();
