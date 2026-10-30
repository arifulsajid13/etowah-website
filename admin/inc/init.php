<?php
declare(strict_types=1);

/* Loaded by every dashboard endpoint. */

define('ETOWAH_ADMIN', true);

require dirname(__DIR__, 2) . '/app/bootstrap.php';
require APP . '/auth.php';
require __DIR__ . '/helpers.php';
require __DIR__ . '/layout.php';

send_security_headers(true); // includes Cache-Control: no-store
