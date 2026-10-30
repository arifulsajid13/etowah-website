<?php
// Served as /robots.txt (see .htaccess) so the Sitemap line uses the configured site URL.
require __DIR__ . '/app/bootstrap.php';

header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: public, max-age=86400');
echo "User-agent: *\n";
if (search_hidden()) {
    // "Hide site from search engines" is on (demo / temporary domain).
    echo "Disallow: /\n";
    exit;
}
echo "Disallow: /admin/\n";
echo "Disallow: /install/\n";
echo "Disallow: /app/\n";
echo "Disallow: /storage/\n";
echo "Disallow: /pay\n";
echo "Disallow: /webhooks/\n";
echo "\n";
echo 'Sitemap: ', site_url('/sitemap.xml'), "\n";
