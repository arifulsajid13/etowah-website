<?php
require __DIR__ . '/inc/init.php';

$user = require_admin();

const ADMIN_ROUTES = [
    'overview', 'inquiries', 'inquiry', 'projects', 'project', 'jobs', 'job',
    'applications', 'application', 'pages', 'page', 'settings', 'users', 'import',
    'invoices', 'invoice', 'invoice_edit', 'payments',
];

$route = get_str('p');
if (!in_array($route, ADMIN_ROUTES, true)) {
    $route = 'overview';
}
require __DIR__ . '/inc/views/' . $route . '.php';
