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
    $urls[] = [site_url($path), max($dates), []];
}
// Project pages list their real photos (main photo unless it is a placeholder, then gallery photos) as image:image entries.
$galleryPhotos = [];
foreach (db_all("SELECT project_id, path FROM project_images WHERE media_type = 'image' ORDER BY sort_order, id") as $row) {
    $galleryPhotos[$row['project_id']][] = $row['path'];
}
foreach (projects_published() as $project) {
    $real = $project['image_path'] !== '' && !$project['is_placeholder_image'];
    $photos = array_merge($real ? [$project['image_path']] : [], $galleryPhotos[$project['id']] ?? []);
    $urls[] = [site_url(project_url($project)), $project['updated_at'], array_map(fn($path) => site_url(media_url($path)), $photos)];
}
foreach (jobs_open() as $job) {
    $urls[] = [site_url('/careers/' . $job['slug']), $job['updated_at'], []];
}

header('Content-Type: application/xml; charset=utf-8');
header('Cache-Control: public, max-age=3600');
echo '<?xml version="1.0" encoding="UTF-8"?>', "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">', "\n";
foreach ($urls as [$loc, $lastmod, $images]) {
    echo '  <url><loc>', e($loc), '</loc>';
    if ($lastmod) {
        echo '<lastmod>', date('Y-m-d', strtotime($lastmod)), '</lastmod>';
    }
    foreach ($images as $image) {
        echo '<image:image><image:loc>', e($image), '</image:loc></image:image>';
    }
    echo "</url>\n";
}
echo '</urlset>', "\n";
