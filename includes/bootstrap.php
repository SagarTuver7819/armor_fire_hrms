<?php
/**
 * Shared bootstrap — include once at top of pages
 * Keeps Core PHP simple for all developers.
 *
 * Usage:
 *   require_once __DIR__ . '/includes/bootstrap.php';
 *   // or from subfolder:
 *   require_once __DIR__ . '/../includes/bootstrap.php';
 */

require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/company.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/auth.php';
