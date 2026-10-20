<?php
declare(strict_types=1);

/*
 * Loaded first by every public page, form handler, and admin screen.
 * Sets up error logging, loads the server-side config written by the installer,
 * and pulls in the shared helpers.
 */

define('ROOT', dirname(__DIR__));
define('APP', __DIR__);
define('INSTALL_LOCK', APP . '/install.lock');

ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);
date_default_timezone_set('America/New_York');
if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
}

require APP . '/helpers.php';

$configFile = APP . '/config.php';
if (!is_file($configFile)) {
    if (is_file(INSTALL_LOCK)) {
        http_response_code(503);
        header('Content-Type: text/html; charset=utf-8');
        header('Retry-After: 3600');
        echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="robots" content="noindex">'
            . '<title>Site configuration missing</title></head><body style="font-family:system-ui,sans-serif;padding:40px">'
            . '<h1>Site configuration missing</h1><p>The file <code>app/config.php</code> could not be found. '
            . 'Restore it from your backup to bring the site back online.</p></body></html>';
        exit;
    }
    header('Location: /install/', true, 302);
    exit;
}

$GLOBALS['ETOWAH_CONFIG'] = require $configFile;

$logDir = storage_path('logs');
if (!is_dir($logDir)) {
    @mkdir($logDir, 0750, true);
}
ini_set('error_log', $logDir . '/php-error.log');

require APP . '/db.php';
require APP . '/settings.php';
require APP . '/migrate.php';
require APP . '/images.php';
require APP . '/content.php';
require APP . '/render.php';

define('ETOWAH_RUNTIME', true);
migrate_if_needed();

send_security_headers(false);
if (search_hidden()) {
    header('X-Robots-Tag: noindex, nofollow');
}
