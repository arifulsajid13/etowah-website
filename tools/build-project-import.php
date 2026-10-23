<?php
/*
 * Builds dist/etowah-projects-import.zip from tools/project-import/manifest.php and the originals in client-media/.
 *   php -c tools/dev-php.ini tools/build-project-import.php            → the import package
 *   php -c tools/dev-php.ini tools/build-project-import.php --samples  → before/after comparisons in docs/enhance-samples/
 * Needs GD (JPEG + WebP), exif, zip, ImageMagick 7 (MAGICK env var or `magick` on PATH),
 * and ffmpeg/ffprobe for videos (FFMPEG / FFPROBE env vars, or on PATH).
 *
 * Photos: see tools/project-import/media.php (orientation, crop, resize, light enhancement, JPG + WebP without metadata).
 * Videos are re-encoded with ffmpeg (-map_metadata -1); their posters are a frame of the video and are not enhanced.
 * The zip holds projects.json + media/; original files are never included.
 *
 * File names are stable hashes. Revision 2 (enhanced photos) uses new names, and each photo lists the revision-1 path it
 * replaces, so a site that already imported the first package gets the enhanced files without duplicates.
 */

require __DIR__ . '/project-import/media.php';

$root = dirname(__DIR__);
$manifest = require __DIR__ . '/project-import/manifest.php';
$out = $root . '/dist/etowah-projects-import.zip';
$ffmpeg = getenv('FFMPEG') ?: 'ffmpeg';
$ffprobe = getenv('FFPROBE') ?: 'ffprobe';

const MAIN_MAX_WIDTH = 1920;
const GALLERY_MAX_SIDE = 1600;
const PAGE_WIDTHS = ['hero' => 1920, 'cta-bg' => 1920]; // other page slots: 1600, like the dashboard
const VIDEO_MAX_HEIGHT = 1080;
const MEDIA_REV = 2;
const LIMITS = ['summary' => 160, 'seo_title' => 60, 'seo_description' => 160, 'title' => 200, 'alt' => 255];

foreach (['imagewebp', 'exif_read_data'] as $fn) {
    if (!function_exists($fn)) {
        fail("PHP function $fn() is missing (enable gd and exif).");
    }
}
if (!class_exists('ZipArchive')) {
    fail('The zip extension is required.');
}
ini_set('memory_limit', '2G');
check_usage($manifest);

if (in_array('--samples', $argv, true)) {
    build_samples($root, $manifest);
    exit(0);
}

$stage = sys_get_temp_dir() . '/etowah-import-' . getmypid();
@mkdir($stage . '/media', 0755, true);

$json = [
    'format'        => 'etowah-projects-import',
    'version'       => 1,
    'built'         => date('Y-m-d'),
    'home'          => $manifest['home'],
    'home_defaults' => $manifest['home_defaults'],
    'projects'      => [],
    'pages'         => [],
];
$counts = ['images' => 0, 'videos' => 0, 'pages' => 0];

foreach ($manifest['projects'] as $p) {
    $src = $root . '/client-media/' . $p['folder'];
    if (!is_dir($src)) {
        fail("Missing folder client-media/{$p['folder']}");
    }
    $entry = ['slug' => $p['slug'], 'mode' => $p['mode']];
    if ($p['mode'] === 'new') {
        check_text($p['slug'], $p['project']);
        $entry['project'] = $p['project'];
        $entry['sort_after'] = $p['sort_after'] ?? null;
    } else {
        $fill = [];
        foreach ($p['fill'] as $field => $rule) {
            check_text($p['slug'], [$field => $rule['value']]);
            $fill[$field] = ['value' => $rule['value'], 'if' => $rule['if'] ?? []];
        }
        $entry['fill'] = $fill;
    }

    $main = $p['main'];
    check_text($p['slug'], ['alt' => $main['alt']]);
    $img = photo("$src/{$main['file']}", $main, ['width' => MAIN_MAX_WIDTH], $stage, media_name($p['slug'], $main['file'], 'main'));
    $entry['main'] = ['dir' => 'projects', 'file' => $img['file'], 'width' => $img['width'], 'height' => $img['height'], 'alt' => $main['alt'],
        'replaces' => ['uploads/projects/' . media_name($p['slug'], $main['file'], 'main', 1) . '.jpg']];
    $counts['images']++;

    $entry['gallery'] = [];
    foreach ($p['gallery'] as $g) {
        check_text($p['slug'], ['alt' => $g['alt']] + (isset($g['title']) ? ['title' => $g['title']] : []));
        if (($g['type'] ?? 'image') === 'video') {
            // Videos are unchanged since revision 1, so they keep their names.
            $entry['gallery'][] = ['type' => 'video', 'dir' => 'project-video'] + process_video("$src/{$g['file']}", $g, $stage, media_name($p['slug'], $g['file'], 'gallery', 1));
            $counts['videos']++;
            continue;
        }
        $img = photo("$src/{$g['file']}", $g, ['side' => GALLERY_MAX_SIDE], $stage, media_name($p['slug'], $g['file'], 'gallery'));
        $entry['gallery'][] = ['type' => 'image', 'dir' => 'project-gallery', 'file' => $img['file'], 'width' => $img['width'], 'height' => $img['height'], 'alt' => $g['alt'],
            'replaces' => ['uploads/project-gallery/' . media_name($p['slug'], $g['file'], 'gallery', 1) . '.jpg']];
        $counts['images']++;
    }
    $json['projects'][] = $entry;
    echo "  {$p['slug']}: main + " . count($entry['gallery']) . " gallery items\n";
}

foreach ($manifest['pages'] as $s) {
    check_text("{$s['page']}/{$s['slot']}", ['alt' => $s['alt']]);
    $img = photo("$root/client-media/{$s['folder']}/{$s['file']}", $s, ['width' => PAGE_WIDTHS[$s['slot']] ?? 1600], $stage,
        media_name('page', "{$s['page']}|{$s['slot']}|{$s['file']}", 'page'));
    $json['pages'][] = ['page' => $s['page'], 'slot' => $s['slot'], 'dir' => 'pages', 'file' => $img['file'],
        'width' => $img['width'], 'height' => $img['height'], 'alt' => $s['alt'], 'replaces' => []];
    $counts['pages']++;
}
echo '  page images: ' . $counts['pages'] . "\n";

file_put_contents($stage . '/projects.json', json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

@mkdir(dirname($out), 0755, true);
@unlink($out);
$zip = new ZipArchive();
if ($zip->open($out, ZipArchive::CREATE) !== true) {
    fail("Cannot create $out");
}
$zip->addFile($stage . '/projects.json', 'projects.json');
foreach (glob($stage . '/media/*') as $file) {
    $zip->addFile($file, 'media/' . basename($file));
    $zip->setCompressionName('media/' . basename($file), ZipArchive::CM_STORE); // already compressed
}
$zip->close();
array_map('unlink', glob($stage . '/media/*'));
@rmdir($stage . '/media');
@unlink($stage . '/projects.json');
@rmdir($stage);

printf("Built %s (%d projects, %d project photos, %d page photos, %d videos, %.1f MB)\n",
    $out, count($json['projects']), $counts['images'], $counts['pages'], $counts['videos'], filesize($out) / 1048576);

// ---------------------------------------------------------------------------

function fail(string $message): never
{
    fwrite(STDERR, $message . "\n");
    exit(1);
}

/** Stable 32-hex name. Revision 1 is the first package's naming; later revisions get new names. */
function media_name(string $slug, string $file, string $role, int $rev = MEDIA_REV): string
{
    return $rev === 1 ? md5("etowah-import|$slug|$file|$role") : md5("etowah-import|r$rev|$slug|$file|$role");
}

function check_text(string $slug, array $fields): void
{
    foreach ($fields as $key => $value) {
        if (is_string($value) && isset(LIMITS[$key]) && mb_strlen($value) > LIMITS[$key]) {
            fail("$slug: $key is " . mb_strlen($value) . ' characters (limit ' . LIMITS[$key] . "): $value");
        }
    }
}

/** Each source photo at most twice across the site (project main or gallery, plus page slots). */
function check_usage(array $manifest): void
{
    $uses = [];
    foreach ($manifest['projects'] as $p) {
        foreach (array_merge([$p['main']], $p['gallery']) as $m) {
            $uses[$p['folder'] . '/' . $m['file']][] = $p['slug'];
        }
    }
    foreach ($manifest['pages'] as $s) {
        $uses[$s['folder'] . '/' . $s['file']][] = "{$s['page']}/{$s['slot']}";
    }
    foreach ($uses as $photo => $where) {
        if (count($where) > 2) {
            fail("$photo is used " . count($where) . ' times (' . implode(', ', $where) . '); the limit is 2.');
        }
    }
}

/** Processed, enhanced photo written as media/{name}.jpg + .webp. */
function photo(string $path, array $m, array $size, string $stage, string $name): array
{
    if (!is_file($path)) {
        fail("Missing $path");
    }
    $png = "$stage/$name.png";
    try {
        render_png($path, $m['crop'] ?? null, (float) ($m['rotate'] ?? 0), $size, !empty($m['denoise']), $png);
        enhance_png($png);
        $img = encode_png($png, "$stage/media", $name);
    } catch (RuntimeException $e) {
        fail($e->getMessage());
    } finally {
        @unlink($png);
    }
    return ['file' => "media/$name.jpg"] + $img;
}

/** Before/after comparisons (same crop and size; left untouched, right enhanced) in docs/enhance-samples/. */
function build_samples(string $root, array $manifest): void
{
    $dir = $root . '/docs/enhance-samples';
    @mkdir($dir, 0755, true);
    $picks = [
        'twigs-mass-excavation' => ['Twigs Farmers Market', 'logo_2025-12-13_10-25-40.jpeg', []],
        'blue-ridge-wet-well'   => ['Blue Ridge', 'logo_2026-04-22_17-14-44-631.jpeg', ['denoise' => true]],
        'reliant-paving'        => ['Reliant Market District', 'logo_2026-06-21_11-57-30-961.jpeg', ['crop' => [0, 0.16, 1, 0.66]]],
    ];
    foreach ($picks as $slug => [$folder, $file, $opts]) {
        $src = "$root/client-media/$folder/$file";
        $tmp = sys_get_temp_dir() . "/etowah-sample-$slug";
        render_png($src, $opts['crop'] ?? null, 0, ['side' => 1000], false, "$tmp-before.png");
        render_png($src, $opts['crop'] ?? null, 0, ['side' => 1000], !empty($opts['denoise']), "$tmp-after.png");
        $applied = enhance_png("$tmp-after.png") . (!empty($opts['denoise']) ? ', light noise reduction' : '');
        $b = imagecreatefrompng("$tmp-before.png");
        $a = imagecreatefrompng("$tmp-after.png");
        [$w, $h] = [imagesx($b), imagesy($b)];
        $sheet = imagecreatetruecolor($w * 2 + 12, $h + 40);
        imagefill($sheet, 0, 0, imagecolorallocate($sheet, 24, 28, 32));
        imagecopy($sheet, $b, 0, 40, 0, 0, $w, $h);
        imagecopy($sheet, $a, $w + 12, 40, 0, 0, $w, $h);
        $white = imagecolorallocate($sheet, 255, 255, 255);
        imagestring($sheet, 5, 10, 12, 'BEFORE (original, resized)', $white);
        imagestring($sheet, 5, $w + 22, 12, 'AFTER: ' . str_replace('–', '-', $applied), $white); // GD's built-in font is ASCII only
        imagejpeg($sheet, "$dir/$slug.jpg", 88);
        unlink("$tmp-before.png");
        unlink("$tmp-after.png");
        echo "  $dir/$slug.jpg — $applied\n";
    }
}

/** H.264 MP4 (max 1080p, ~2–4 Mbps, faststart, no metadata), optional AAC audio, poster from one frame. */
function process_video(string $path, array $g, string $stage, string $name): array
{
    global $ffmpeg, $ffprobe;
    $probe = json_decode((string) shell_exec(escapeshellarg($ffprobe) . ' -v error -print_format json -show_format -show_streams ' . escapeshellarg($path)), true)
        ?: fail("ffprobe could not read $path");
    $created = $probe['format']['tags']['creation_time'] ?? '';
    $uploadDate = $created !== '' ? gmdate('Y-m-d', strtotime($created)) : date('Y-m-d');

    $mp4 = "$stage/media/$name.mp4";
    $audio = !empty($g['audio']) ? '-map 0:a:0? -c:a aac -b:a 128k -ac 2' : '-an';
    $cmd = escapeshellarg($ffmpeg) . ' -v error -y -i ' . escapeshellarg($path)
        . ' -map 0:v:0 ' . $audio . ' -map_metadata -1 -map_chapters -1 -dn -sn'
        . " -vf \"scale=-2:'min(" . VIDEO_MAX_HEIGHT . ",ih)'\" -c:v libx264 -preset slow -profile:v high -pix_fmt yuv420p"
        . ' -crf 23 -maxrate 2500k -bufsize 5000k -movflags +faststart -fflags +bitexact -flags:v +bitexact '
        . escapeshellarg($mp4);
    exec($cmd, $o, $code);
    if ($code !== 0 || !is_file($mp4)) {
        fail("ffmpeg failed for $path");
    }
    $outProbe = json_decode((string) shell_exec(escapeshellarg($ffprobe) . ' -v error -print_format json -show_format ' . escapeshellarg($mp4)), true);
    $tags = $outProbe['format']['tags'] ?? [];
    if (isset($tags['location']) || isset($tags['creation_time']) || isset($tags['com.apple.quicktime.location.ISO6709'])) {
        fail("Metadata survived in $name.mp4");
    }
    $duration = (int) round((float) ($outProbe['format']['duration'] ?? 0));

    $frame = "$stage/$name-poster.png";
    exec(escapeshellarg($ffmpeg) . ' -v error -y -ss ' . (float) ($g['poster_at'] ?? 1) . ' -i ' . escapeshellarg($mp4) . ' -frames:v 1 ' . escapeshellarg($frame), $o, $code);
    if ($code !== 0 || !is_file($frame)) {
        fail("No poster frame for $path");
    }
    try {
        run_magick([$frame, '-resize', GALLERY_MAX_SIDE . 'x' . GALLERY_MAX_SIDE . '>', '-strip', 'PNG24:' . $frame]);
        $poster = encode_png($frame, "$stage/media", $name);
    } catch (RuntimeException $e) {
        fail($e->getMessage());
    } finally {
        @unlink($frame);
    }

    return [
        'file' => "media/$name.jpg", 'width' => $poster['width'], 'height' => $poster['height'],
        'video' => "media/$name.mp4", 'title' => $g['title'], 'alt' => $g['alt'],
        'upload_date' => $uploadDate, 'duration' => $duration,
        'kbps' => (int) round(filesize($mp4) * 8 / 1000 / max(1, $duration)),
    ];
}
