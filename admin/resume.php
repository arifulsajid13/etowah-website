<?php
require __DIR__ . '/inc/init.php';

require_admin();

$app = db_one('SELECT id, resume_file, resume_original_name, resume_size FROM applications WHERE id = ?', [get_int('id')]);
$path = $app && preg_match('/^[a-f0-9]{32}$/', $app['resume_file']) ? storage_path('resumes/' . $app['resume_file']) : '';
if ($path === '' || !is_file($path)) {
    http_response_code(404);
    exit('Resume not found.');
}

$name = $app['resume_original_name'] !== '' ? $app['resume_original_name'] : 'resume';
$ascii = preg_replace('/[^A-Za-z0-9._ -]+/', '_', $name);

log_activity('Downloaded resume', 'application', (int) $app['id']);

header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $ascii . '"; filename*=UTF-8\'\'' . rawurlencode($name));
header('Content-Length: ' . filesize($path));
header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: default-src 'none'; sandbox");
readfile($path);
