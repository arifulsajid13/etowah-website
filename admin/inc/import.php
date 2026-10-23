<?php
defined('ETOWAH_ADMIN') || exit;

/*
 * Project import package (built by tools/build-project-import.php): projects.json + media/ in the private import folder.
 * The package is uploaded with hPanel File Manager, so there is no browser upload limit.
 *
 * Safe to run more than once: projects match by slug, media by file path, and nothing is duplicated.
 * Never overwrites the client's own edits: a text field is only filled when it is empty or still holds the original text,
 * a main photo or page photo is only replaced while it is a placeholder, and a homepage spot only moves while it holds its original project.
 * A photo that replaces one from an earlier package (listed in "replaces", e.g. an enhanced version) swaps the file in place,
 * keeping the description and order; the old file is deleted after the import.
 */

const IMPORT_MEDIA_DIRS = ['projects', 'project-gallery', 'project-video', 'pages'];
const IMPORT_FILL_FIELDS = ['location' => 120, 'summary' => 300, 'description' => 5000, 'seo_title' => 70, 'seo_description' => 170, 'client_name' => 200];
const IMPORT_MEDIA_FILE = '#^media/[a-f0-9]{32}\.(jpg|webp|mp4)$#';

/** The folder the page tells the client to use: the import folder inside the site's storage folder. */
function import_dir(): string
{
    return import_dirs()[0];
}

/**
 * Folders searched for a package, in order: the storage folder in use, then the two standard places the installer can
 * choose (the configured path may differ from where the client was told to upload, e.g. after the installer fell back
 * to public_html/storage, or after the domain folder was renamed).
 */
function import_dirs(): array
{
    $dirs = [storage_path('import'), rtrim(dirname(ROOT), '/\\') . '/etowah-storage/import', ROOT . '/storage/import'];
    return array_values(array_unique(array_map(fn($d) => rtrim(str_replace('\\', '/', $d), '/'), $dirs)));
}

/** Entries directly in a folder: name => ['dir' => bool, 'size' => int]. scandir, not glob: no pattern characters, any case. */
function import_entries(string $dir): array
{
    $out = [];
    foreach (@scandir($dir) ?: [] as $name) {
        if ($name === '.' || $name === '..') {
            continue;
        }
        $path = "$dir/$name";
        $isDir = @is_dir($path);
        $out[$name] = ['dir' => $isDir, 'size' => $isDir ? 0 : (int) @filesize($path)];
    }
    return $out;
}

/** The package zip in a folder: any *.zip (any case); with several, the newest etowah-projects-import*.zip, else the newest. */
function import_pick_zip(string $dir): ?string
{
    $zips = [];
    foreach (import_entries($dir) as $name => $info) {
        if (!$info['dir'] && preg_match('/\.zip$/i', $name)) {
            $zips[$name] = (int) @filemtime("$dir/$name") + (stripos($name, 'etowah-projects-import') === 0 ? PHP_INT_MAX >> 1 : 0);
        }
    }
    if (!$zips) {
        return null;
    }
    arsort($zips);
    return $dir . '/' . array_key_first($zips);
}

/**
 * Find the package. Looks in each import folder for projects.json, then one folder down (File Manager's "Extract"
 * usually creates a subfolder named after the zip), then for a .zip to unpack.
 * Returns ['base' => import folder, 'dir' => folder with projects.json (after unpacking), 'zip' => zip path|null] or null.
 */
function import_locate(): ?array
{
    foreach (import_dirs() as $base) {
        if (!@is_dir($base) || !@is_readable($base)) {
            continue;
        }
        // Already unpacked (by the preview or by File Manager): the package zip, if still there, is deleted with it.
        $packageZip = import_pick_zip($base);
        if ($packageZip !== null && stripos(basename($packageZip), 'etowah-projects-import') !== 0) {
            $packageZip = null; // never touch an unrelated zip
        }
        if (is_file("$base/projects.json")) {
            return ['base' => $base, 'dir' => $base, 'zip' => $packageZip];
        }
        foreach (import_entries($base) as $name => $info) {
            if ($info['dir'] && is_file("$base/$name/projects.json")) {
                return ['base' => $base, 'dir' => "$base/$name", 'zip' => $packageZip];
            }
        }
        $zip = import_pick_zip($base);
        if ($zip !== null) {
            return ['base' => $base, 'dir' => $base, 'zip' => $zip];
        }
    }
    return null;
}

/** Unpack the located zip into its folder (only projects.json and media/ files; nothing else is written). */
function import_unpack_zip(array $loc): ?string
{
    $dir = $loc['dir'];
    if ($loc['zip'] === null || is_file("$dir/projects.json")) {
        return null;
    }
    if (!class_exists('ZipArchive')) {
        return 'The server cannot open .zip files (the PHP zip extension is off). Extract ' . basename($loc['zip']) . ' in File Manager (right-click → Extract), or turn on “zip” in hPanel → PHP Configuration → PHP Extensions.';
    }
    $zip = new ZipArchive();
    if ($zip->open($loc['zip']) !== true) {
        return basename($loc['zip']) . ' could not be opened. It may not have finished uploading: upload it again.';
    }
    if ($zip->locateName('projects.json') === false) {
        $zip->close();
        return basename($loc['zip']) . ' is not an import package (no projects.json inside). Remove it from the folder.';
    }
    if (!is_dir("$dir/media") && !@mkdir("$dir/media", 0700, true)) {
        $zip->close();
        return 'The import folder is not writable, so the zip could not be unpacked. Check the folder permissions in File Manager (755).';
    }
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = (string) $zip->getNameIndex($i);
        if ($name !== 'projects.json' && !preg_match(IMPORT_MEDIA_FILE, $name)) {
            continue;
        }
        $in = $zip->getStream($name);
        $out = @fopen("$dir/$name", 'wb');
        if (!$in || !$out) {
            $zip->close();
            return 'The zip could not be unpacked. Check that the import folder is writable (755).';
        }
        stream_copy_to_stream($in, $out);
        fclose($in);
        fclose($out);
    }
    $zip->close();
    return null;
}

/** Read and check projects.json from a located package. Returns [package|null, problems]. */
function import_load(?array $loc): array
{
    if ($loc === null) {
        return [null, []];
    }
    $problem = import_unpack_zip($loc);
    if ($problem !== null) {
        return [null, [$problem]];
    }
    $dir = $loc['dir'];
    if (!is_file($dir . '/projects.json')) {
        return [null, []];
    }
    $data = json_decode((string) file_get_contents($dir . '/projects.json'), true);
    if (!is_array($data) || ($data['format'] ?? '') !== 'etowah-projects-import' || (int) ($data['version'] ?? 0) !== 1 || !is_array($data['projects'] ?? null)) {
        return [null, ['projects.json is not an Etowah project import package (or it is from a newer version).']];
    }

    $problems = [];
    $media = function (array $m, string $where) use ($dir, &$problems): bool {
        $files = [$m['file'] ?? ''];
        if (($m['type'] ?? 'image') === 'video') {
            $files[] = $m['video'] ?? '';
        }
        foreach ($files as $file) {
            if (!preg_match(IMPORT_MEDIA_FILE, (string) $file) || !is_file($dir . '/' . $file)) {
                $problems[] = "$where: missing file " . ($file ?: '(none)') . '.';
                return false;
            }
        }
        if (!in_array($m['dir'] ?? '', IMPORT_MEDIA_DIRS, true) || !@getimagesize($dir . '/' . $m['file'])
            || (isset($m['video']) && substr((string) file_get_contents($dir . '/' . $m['video'], false, null, 0, 12), 4, 4) !== 'ftyp')) {
            $problems[] = "$where: " . basename((string) $m['file']) . ' is not a valid photo or video.';
            return false;
        }
        return true;
    };
    foreach ($data['projects'] as $i => $p) {
        $slug = (string) ($p['slug'] ?? '');
        $where = $slug ?: 'Project ' . ($i + 1);
        if (!preg_match('/^[a-z0-9-]{1,100}$/', $slug) || !in_array($p['mode'] ?? '', ['new', 'update'], true)) {
            $problems[] = "$where: invalid slug or mode.";
            continue;
        }
        if ($p['mode'] === 'new') {
            $np = $p['project'] ?? [];
            if (trim((string) ($np['title'] ?? '')) === '' || !isset(MARKETS[$np['market'] ?? '']) || trim((string) ($np['summary'] ?? '')) === ''
                || array_diff((array) ($np['services'] ?? []), array_keys(SERVICES))) {
                $problems[] = "$where: title, summary, market, or services are missing or invalid.";
            }
        }
        foreach ((array) ($p['fill'] ?? []) as $field => $rule) {
            if (!isset(IMPORT_FILL_FIELDS[$field]) || str_length((string) ($rule['value'] ?? '')) > IMPORT_FILL_FIELDS[$field]) {
                $problems[] = "$where: field “{$field}” cannot be imported.";
            }
        }
        $media(($p['main'] ?? []) + ['type' => 'image'], $where);
        foreach ((array) ($p['gallery'] ?? []) as $m) {
            $media((array) $m, $where);
        }
    }
    foreach ((array) ($data['pages'] ?? []) as $s) {
        $where = 'Page photo ' . ($s['page'] ?? '?') . '/' . ($s['slot'] ?? '?');
        if (!preg_match('/^[a-z0-9-]{1,40}$/', (string) ($s['page'] ?? '')) || !preg_match('/^[a-z0-9-]{1,40}$/', (string) ($s['slot'] ?? ''))
            || ($s['dir'] ?? '') !== 'pages' || str_length((string) ($s['alt'] ?? '')) > 255) {
            $problems[] = "$where: invalid page, slot, or description.";
            continue;
        }
        $media((array) $s + ['type' => 'image'], $where);
    }
    return [$data, $problems];
}

/**
 * What an import would do, from the current database. Used for the preview and, unchanged, for the run.
 * Returns ['projects' => [...], 'home' => [...], 'totals' => [...]].
 */
function import_plan(array $data): array
{
    $plan = ['projects' => [], 'home' => [], 'pages' => [], 'totals' => ['new' => 0, 'updated' => 0, 'photos' => 0, 'videos' => 0, 'upgraded' => 0, 'pages' => 0]];
    foreach ($data['projects'] as $p) {
        $current = db_one('SELECT * FROM projects WHERE slug = ?', [$p['slug']]);
        $item = ['slug' => $p['slug'], 'src' => $p, 'current' => $current, 'fill' => [], 'kept' => [], 'main' => null, 'add' => [], 'upgrade' => [], 'skipped' => 0, 'notes' => []];
        if (!$current && $p['mode'] === 'update') {
            $item['action'] = 'missing';
            $item['notes'][] = 'Not found on this site, so it was left out.';
            $plan['projects'][] = $item;
            continue;
        }
        $item['action'] = $current ? 'update' : 'new';
        $item['title'] = $current['title'] ?? $p['project']['title'];

        foreach ((array) ($p['fill'] ?? []) as $field => $rule) {
            $now = trim((string) ($current[$field] ?? ''));
            if ($now === trim((string) $rule['value'])) {
                continue;
            }
            if ($now === '' || in_array($now, (array) ($rule['if'] ?? []), true)) {
                $item['fill'][$field] = (string) $rule['value'];
            } else {
                $item['kept'][] = $field;
            }
        }

        $main = $p['main'];
        $mainPath = 'uploads/' . $main['dir'] . '/' . basename($main['file']);
        if (!$current) {
            $item['main'] = $main + ['path' => $mainPath];
        } elseif ($current['image_path'] !== $mainPath) {
            if (in_array($current['image_path'], (array) ($main['replaces'] ?? []), true)) {
                // A newer version of the photo an earlier package imported (e.g. enhanced): swap the file, keep the description.
                $item['main'] = $main + ['path' => $mainPath, 'old' => $current['image_path']];
            } elseif ((int) $current['is_placeholder_image'] === 1 || $current['image_path'] === '') {
                $item['main'] = $main + ['path' => $mainPath];
            } else {
                $item['notes'][] = 'Main photo kept: this project already has a real photo.';
            }
        }

        $have = $current ? db_all('SELECT id, media_type, path FROM project_images WHERE project_id = ?', [$current['id']]) : [];
        $paths = array_column($have, 'path');
        $ids = array_column($have, 'id', 'path');
        $room = [
            'image' => GALLERY_MAX - count(array_filter($have, fn($r) => $r['media_type'] === 'image')),
            'video' => VIDEO_GALLERY_MAX - count(array_filter($have, fn($r) => $r['media_type'] === 'video')),
        ];
        foreach ((array) $p['gallery'] as $m) {
            $type = ($m['type'] ?? 'image') === 'video' ? 'video' : 'image';
            $path = 'uploads/' . $m['dir'] . '/' . basename($m['file']);
            if (in_array($path, $paths, true)) {
                continue;
            }
            $old = array_values(array_intersect((array) ($m['replaces'] ?? []), $paths))[0] ?? null;
            if ($old !== null) {
                $item['upgrade'][] = $m + ['type' => $type, 'path' => $path, 'id' => (int) $ids[$old], 'old' => $old];
                continue;
            }
            if ($room[$type] <= 0) {
                $item['skipped']++;
                continue;
            }
            $room[$type]--;
            $item['add'][] = $m + ['type' => $type, 'path' => $path];
        }
        if ($item['skipped']) {
            $item['notes'][] = $item['skipped'] . ' gallery item(s) left out: the gallery is full (' . GALLERY_MAX . ' photos, ' . VIDEO_GALLERY_MAX . ' videos).';
        }

        $changes = $item['fill'] || $item['main'] || $item['add'] || $item['upgrade'];
        if ($item['action'] === 'update' && !$changes) {
            $item['action'] = 'unchanged';
        }
        if ($item['action'] === 'new') {
            $plan['totals']['new']++;
        } elseif ($item['action'] === 'update') {
            $plan['totals']['updated']++;
        }
        $mainUpgrade = isset($item['main']['old']);
        $plan['totals']['upgraded'] += ($mainUpgrade ? 1 : 0) + count($item['upgrade']);
        $plan['totals']['photos'] += ($item['main'] && !$mainUpgrade ? 1 : 0) + count(array_filter($item['add'], fn($m) => $m['type'] === 'image'));
        $plan['totals']['videos'] += count(array_filter($item['add'], fn($m) => $m['type'] === 'video'));
        $plan['projects'][] = $item;
    }

    // Homepage spots move only while they still hold the project they started with (or nothing).
    $holders = [];
    foreach (db_all("SELECT slug, title, home_position FROM projects WHERE home_position <> 'none'") as $row) {
        $holders[$row['home_position']] = $row;
    }
    $incoming = array_column(array_filter($plan['projects'], fn($i) => $i['action'] !== 'missing'), 'title', 'slug');
    foreach ((array) ($data['home'] ?? []) as $slot => $slug) {
        if (!isset(HOME_POSITIONS[$slot]) || $slot === 'none') {
            continue;
        }
        $holder = $holders[$slot] ?? null;
        $title = $incoming[$slug] ?? (string) db_value('SELECT title FROM projects WHERE slug = ?', [$slug]);
        if ($title === '' || ($holder['slug'] ?? '') === $slug) {
            continue;
        }
        $original = ($data['home_defaults'][$slot] ?? '') === ($holder['slug'] ?? '');
        $plan['home'][] = ['slot' => $slot, 'slug' => $slug, 'title' => $title, 'from' => $holder['title'] ?? '', 'apply' => !$holder || $original];
    }

    // Page photos replace only placeholders (a photo the client uploaded in the dashboard is kept),
    // or a photo from an earlier package that this one supersedes.
    foreach ((array) ($data['pages'] ?? []) as $s) {
        $row = db_one('SELECT id, label, path, is_placeholder FROM page_images WHERE page_key = ? AND slot = ?', [$s['page'], $s['slot']]);
        $path = 'uploads/pages/' . basename($s['file']);
        $entry = $s + ['path' => $path, 'label' => $row['label'] ?? "{$s['page']}/{$s['slot']}", 'id' => (int) ($row['id'] ?? 0)];
        if (!$row) {
            $entry['action'] = 'missing';
        } elseif ($row['path'] === $path) {
            $entry['action'] = 'same';
        } elseif (in_array($row['path'], (array) ($s['replaces'] ?? []), true)) {
            $entry['action'] = 'upgrade';
            $entry['old'] = $row['path'];
            $plan['totals']['upgraded']++;
        } elseif ((int) $row['is_placeholder'] === 1) {
            $entry['action'] = 'replace';
            $plan['totals']['pages']++;
        } else {
            $entry['action'] = 'kept';
        }
        $plan['pages'][] = $entry;
    }
    return $plan;
}

/** Copy media into uploads/ and write the database in one transaction. Returns a summary line. Throws on failure. */
function import_run(array $plan, string $dir): string
{
    @set_time_limit(300);
    $copied = [];
    $copy = function (string $file, string $dest) use ($dir, &$copied): void {
        $target = ROOT . '/' . $dest;
        if (is_file($target)) {
            return;
        }
        if (!is_dir(dirname($target)) && !@mkdir(dirname($target), 0755, true)) {
            throw new RuntimeException('The uploads folder is not writable. Check folder permissions in hPanel.');
        }
        if (!@copy($dir . '/' . $file, $target)) {
            throw new RuntimeException('Could not copy ' . basename($file) . ' into uploads/.');
        }
        @chmod($target, 0644);
        $copied[] = $target;
    };
    $copyMedia = function (array $m) use ($copy, $dir): void {
        $copy($m['file'], $m['path']);
        $webp = preg_replace('/\.jpg$/', '.webp', $m['file']);
        if (is_file($dir . '/' . $webp)) {
            $copy($webp, webp_twin($m['path']));
        }
        if (!empty($m['video'])) {
            $copy($m['video'], 'uploads/' . $m['dir'] . '/' . basename($m['video']));
        }
    };

    $pages = array_filter($plan['pages'] ?? [], fn($s) => in_array($s['action'], ['replace', 'upgrade'], true));
    $superseded = [];
    $pdo = db();
    try {
        foreach ($plan['projects'] as $item) {
            if ($item['main']) {
                $copyMedia($item['main']);
            }
            array_map($copyMedia, $item['add']);
            array_map($copyMedia, $item['upgrade']);
        }
        array_map($copyMedia, $pages);

        $pdo->beginTransaction();
        foreach ($plan['projects'] as $item) {
            if (in_array($item['action'], ['missing', 'unchanged'], true)) {
                continue;
            }
            if ($item['action'] === 'new') {
                $np = $item['src']['project'];
                $order = import_sort_order((string) ($item['src']['sort_after'] ?? ''));
                $main = $item['main'];
                db_run('INSERT INTO projects (title, slug, status, client_name, location, market, primary_service, summary, description, seo_title, seo_description,
                        image_path, image_alt, image_width, image_height, is_placeholder_image, home_position, sort_order, published, created_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, \'none\', ?, 1, NOW())', [
                    mb_substr($np['title'], 0, 200), $item['slug'], ($np['status'] ?? '') === 'current' ? 'current' : 'completed',
                    ($np['client_name'] ?? '') ?: null, ($np['location'] ?? '') ?: null, $np['market'],
                    isset(SERVICES[$np['primary_service'] ?? '']) ? $np['primary_service'] : null,
                    mb_substr($np['summary'], 0, 300), (string) ($np['description'] ?? ''),
                    ($np['seo_title'] ?? '') ?: null, ($np['seo_description'] ?? '') ?: null,
                    $main['path'], mb_substr($main['alt'], 0, 255), (int) $main['width'], (int) $main['height'], $order,
                ]);
                $projectId = (int) $pdo->lastInsertId();
                foreach ((array) $np['services'] as $service) {
                    $serviceOrder = (int) db_value('SELECT COALESCE(MAX(sort_order), 0) + 10 FROM project_services WHERE service_key = ?', [$service]);
                    db_run('INSERT INTO project_services (project_id, service_key, sort_order) VALUES (?, ?, ?)', [$projectId, $service, $serviceOrder]);
                }
            } else {
                $projectId = (int) $item['current']['id'];
                foreach ($item['fill'] as $field => $value) {
                    db_run("UPDATE projects SET {$field} = ? WHERE id = ?", [$value, $projectId]); // field names come from IMPORT_FILL_FIELDS
                }
                if ($item['main'] && isset($item['main']['old'])) {
                    $m = $item['main'];
                    db_run('UPDATE projects SET image_path = ?, image_width = ?, image_height = ? WHERE id = ?', [$m['path'], (int) $m['width'], (int) $m['height'], $projectId]);
                    $superseded[] = $m['old'];
                } elseif ($item['main']) {
                    $m = $item['main'];
                    db_run('UPDATE projects SET image_path = ?, image_alt = ?, image_width = ?, image_height = ?, is_placeholder_image = 0 WHERE id = ?',
                        [$m['path'], mb_substr($m['alt'], 0, 255), (int) $m['width'], (int) $m['height'], $projectId]);
                }
            }
            // Newer versions of earlier imported gallery photos: same row (description and order kept), new file.
            foreach ($item['upgrade'] as $m) {
                db_run('UPDATE project_images SET path = ?, width = ?, height = ? WHERE id = ?', [$m['path'], (int) $m['width'], (int) $m['height'], $m['id']]);
                $superseded[] = $m['old'];
            }
            foreach ($item['add'] as $m) {
                $order = (int) db_value('SELECT COALESCE(MAX(sort_order), 0) + 10 FROM project_images WHERE project_id = ?', [$projectId]);
                $isVideo = $m['type'] === 'video';
                db_run('INSERT INTO project_images (project_id, media_type, path, video_path, title, upload_date, duration, alt, width, height, sort_order, created_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())', [
                    $projectId, $m['type'], $m['path'],
                    $isVideo ? 'uploads/' . $m['dir'] . '/' . basename($m['video']) : null,
                    $isVideo ? mb_substr((string) $m['title'], 0, 200) : null,
                    $isVideo && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($m['upload_date'] ?? '')) ? $m['upload_date'] : null,
                    $isVideo ? ((int) ($m['duration'] ?? 0) ?: null) : null,
                    mb_substr((string) $m['alt'], 0, 255), (int) $m['width'], (int) $m['height'], $order,
                ]);
            }
        }
        foreach ($plan['home'] as $h) {
            if ($h['apply']) {
                db_run('UPDATE projects SET home_position = \'none\' WHERE home_position = ?', [$h['slot']]);
                db_run('UPDATE projects SET home_position = ? WHERE slug = ?', [$h['slot'], $h['slug']]);
            }
        }
        foreach ($pages as $s) {
            if ($s['action'] === 'upgrade') {
                db_run('UPDATE page_images SET path = ?, width = ?, height = ? WHERE id = ?', [$s['path'], (int) $s['width'], (int) $s['height'], $s['id']]);
                $superseded[] = $s['old'];
            } else {
                db_run('UPDATE page_images SET path = ?, alt = ?, width = ?, height = ?, is_placeholder = 0 WHERE id = ? AND is_placeholder = 1',
                    [$s['path'], mb_substr((string) $s['alt'], 0, 255), (int) $s['width'], (int) $s['height'], $s['id']]);
            }
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        foreach ($copied as $file) {
            @unlink($file);
        }
        throw $e instanceof RuntimeException ? $e : new RuntimeException('The import failed and nothing was changed. ' . $e->getMessage());
    }

    // Files of photos that were swapped for newer versions (import-owned uploads only; never /assets).
    foreach ($superseded as $old) {
        image_delete_upload($old);
    }

    $t = $plan['totals'];
    return sprintf('%d new project%s, %d updated, %d photo%s and %d video%s added, %d page photo%s replaced, %d photo%s updated to a newer version.',
        $t['new'], $t['new'] === 1 ? '' : 's', $t['updated'], $t['photos'], $t['photos'] === 1 ? '' : 's', $t['videos'], $t['videos'] === 1 ? '' : 's',
        $t['pages'], $t['pages'] === 1 ? '' : 's', $t['upgraded'], $t['upgraded'] === 1 ? '' : 's');
}

/** Sort order right after another project when there is room before the next one; otherwise at the end. */
function import_sort_order(string $after): int
{
    $base = $after !== '' ? db_value('SELECT sort_order FROM projects WHERE slug = ?', [$after]) : null;
    if ($base !== null && $base !== false) {
        $next = db_value('SELECT MIN(sort_order) FROM projects WHERE sort_order > ?', [(int) $base]);
        if ($next === null || (int) $base + 1 < (int) $next) {
            return (int) $base + 1;
        }
    }
    return (int) db_value('SELECT COALESCE(MAX(sort_order), 0) + 10 FROM projects');
}

/**
 * Delete the package: projects.json, the package's media files, the zip that was used, and an extraction subfolder
 * once it is empty. Other files in the folder are left alone. The import folder itself stays.
 */
function import_clear(array $loc): void
{
    $dir = $loc['dir'];
    foreach (import_entries("$dir/media") as $name => $info) {
        if (!$info['dir'] && preg_match(IMPORT_MEDIA_FILE, "media/$name")) {
            @unlink("$dir/media/$name");
        }
    }
    @rmdir("$dir/media");
    @unlink("$dir/projects.json");
    if ($loc['zip'] !== null) {
        @unlink($loc['zip']);
    }
    if ($dir !== $loc['base']) {
        @rmdir($dir); // only succeeds when nothing else is in it
    }
}

/** What PHP sees, for the diagnostics box on the Import page. */
function import_diagnostics(?array $loc): array
{
    $basedir = (string) ini_get('open_basedir');
    $allowed = function (string $path) use ($basedir): string {
        if ($basedir === '') {
            return 'no restriction';
        }
        foreach (explode(PATH_SEPARATOR, $basedir) as $root) {
            $root = rtrim(str_replace('\\', '/', $root), '/');
            if ($root !== '' && str_starts_with($path . '/', $root . '/')) {
                return 'inside';
            }
        }
        return 'OUTSIDE (PHP cannot read it)';
    };
    $folders = [];
    foreach (import_dirs() as $i => $dir) {
        $exists = @is_dir($dir);
        $folders[] = [
            'path'     => $dir,
            'role'     => $i === 0 ? 'import folder in use (from storage_path)' : 'also searched',
            'real'     => $exists ? (string) @realpath($dir) : '',
            'exists'   => $exists,
            'readable' => $exists && @is_readable($dir),
            'writable' => $exists && @is_writable($dir),
            'basedir'  => $allowed($dir),
            'entries'  => $exists ? array_slice(import_entries($dir), 0, 40, true) : [],
        ];
    }
    if (function_exists('posix_geteuid') && function_exists('posix_getpwuid')) {
        $user = (posix_getpwuid(posix_geteuid())['name'] ?? '?') . ' (uid ' . posix_geteuid() . ')';
    } else {
        $user = get_current_user() . ' (uid ' . getmyuid() . ', script owner)';
    }
    $configured = rtrim(str_replace('\\', '/', (string) config('storage_path', ROOT . '/storage')), '/');
    return [
        'folders'    => $folders,
        'found'      => $loc,
        'zip'        => class_exists('ZipArchive'),
        'user'       => $user,
        'basedir'    => $basedir === '' ? '(none)' : $basedir,
        'configured' => $configured . (@is_dir($configured) ? '' : '  (does not exist)'),
        'storage'    => str_replace('\\', '/', storage_path()),
    ];
}
