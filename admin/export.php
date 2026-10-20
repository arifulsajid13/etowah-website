<?php
require __DIR__ . '/inc/init.php';

require_admin();

$type = get_str('type');
if ($type === 'inquiries') {
    [$where, $params, $order] = inquiry_query(inquiry_filters());
    $rows = db_all("SELECT id, created_at, status, name, company, email, phone, project_type, project_location, message, contacted_at, notes
                      FROM inquiries WHERE {$where} ORDER BY {$order}", $params);
    $header = ['ID', 'Received', 'Status', 'Name', 'Company', 'Email', 'Phone', 'Project type', 'Project location', 'Message', 'First contacted', 'Notes'];
} elseif ($type === 'applications') {
    [$where, $params, $order] = application_query(application_filters());
    $rows = db_all("SELECT a.id, a.created_at, a.status, COALESCE(j.title, 'General') AS position, a.name, a.email, a.phone, a.message, a.resume_original_name, a.notes
                      FROM applications a LEFT JOIN jobs j ON j.id = a.job_id WHERE {$where} ORDER BY {$order}", $params);
    $header = ['ID', 'Received', 'Status', 'Position', 'Name', 'Email', 'Phone', 'Message', 'Resume file', 'Notes'];
} else {
    http_response_code(404);
    exit('Unknown export');
}

log_activity('Exported ' . $type . ' (' . count($rows) . ' rows)', $type);

/** Spreadsheet apps run cells that start with = + - @ as formulas; prefix them so they stay text. */
$safe = fn($value) => preg_match('/^[=+\-@\t\r]/', (string) $value) ? "'" . $value : (string) $value;

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="etowah-' . $type . '-' . date('Y-m-d') . '.csv"');
header('X-Content-Type-Options: nosniff');
$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel shows accents and dashes correctly
fputcsv($out, $header);
foreach ($rows as $row) {
    fputcsv($out, array_map($safe, array_values($row)));
}
fclose($out);
