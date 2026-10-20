<?php
require __DIR__ . '/app/bootstrap.php';

$urls = [];
$updated = [];
foreach (db_all('SELECT page_key, updated_at FROM pages') as $row) {
    $updated[$row['page_key']] = $row['updated_at'];
}
$latestProject = (string) db_value('SELECT MAX(updated_at) FROM projects WHERE published = 1');
$latestJob = (string) db_value("SELECT MAX(updated_at) FROM jobs WHERE status = 'open'");

foreach (PAGES as $key => [$path]) {
    $dates = [$updated[$key] ?? ''];
    if (in_array($key, ['home', 'projects', 'markets', 'site-development', 'underground-utilities', 'pump-stations', 'roadways-stormwater'], true)) {
        $dates[] = $latestProject;
    }
    if ($key === 'careers') {
        $dates[] = $latestJob;
    }
    $urls[] = [site_url($path), max($dates)];
}
foreach (projects_published() as $project) {
    $urls[] = [site_url(project_url($project)), $project['updated_at']];
}
foreach (jobs_open() as $job) {
    $urls[] = [site_url('/careers/' . $job['slug']), $job['updated_at']];
}

header('Content-Type: application/xml; charset=utf-8');
header('Cache-Control: public, max-age=3600');
echo '<?xml version="1.0" encoding="UTF-8"?>', "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">', "\n";
foreach ($urls as [$loc, $lastmod]) {
    echo '  <url><loc>', e($loc), '</loc>';
    if ($lastmod) {
        echo '<lastmod>', date('Y-m-d', strtotime($lastmod)), '</lastmod>';
    }
    echo "</url>\n";
}
echo '</urlset>', "\n";
