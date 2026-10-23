<?php
/*
 * Builds dist/etowah-projects-import.zip from tools/project-import/manifest.php and the originals in client-media/.
 *   php -c tools/dev-php.ini tools/build-project-import.php
 * Needs GD (JPEG + WebP), exif, zip, and ffmpeg/ffprobe for videos (FFMPEG / FFPROBE env vars, or on PATH).
 *
 * Every published file is re-encoded: EXIF orientation applied, then all metadata dropped (GD writes none;
 * ffmpeg runs with -map_metadata -1). The zip holds projects.json + media/; original files are never included.
 */

$root = dirname(__DIR__);
$manifest = require __DIR__ . '/project-import/manifest.php';
$out = $root . '/dist/etowah-projects-import.zip';
$ffmpeg = getenv('FFMPEG') ?: 'ffmpeg';
$ffprobe = getenv('FFPROBE') ?: 'ffprobe';

const MAIN_MAX_WIDTH = 1920;
const GALLERY_MAX_SIDE = 1600;
const VIDEO_MAX_HEIGHT = 1080;
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

$stage = sys_get_temp_dir() . '/etowah-import-' . getmypid();
@mkdir($stage . '/media', 0755, true);

$json = [
    'format'        => 'etowah-projects-import',
    'version'       => 1,
    'built'         => date('Y-m-d'),
    'home'          => $manifest['home'],
    'home_defaults' => $manifest['home_defaults'],
    'projects'      => [],
];
$counts = ['images' => 0, 'videos' => 0];

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
    $img = process_image("$src/{$main['file']}", $main['crop'] ?? null, MAIN_MAX_WIDTH, null, $stage, media_name($p['slug'], $main['file'], 'main'));
    $entry['main'] = ['dir' => 'projects', 'file' => $img['file'], 'width' => $img['width'], 'height' => $img['height'], 'alt' => $main['alt']];
    $counts['images']++;

    $entry['gallery'] = [];
    foreach ($p['gallery'] as $g) {
        check_text($p['slug'], ['alt' => $g['alt']] + (isset($g['title']) ? ['title' => $g['title']] : []));
        $name = media_name($p['slug'], $g['file'], 'gallery');
        if (($g['type'] ?? 'image') === 'video') {
            $entry['gallery'][] = ['type' => 'video', 'dir' => 'project-video'] + process_video("$src/{$g['file']}", $g, $stage, $name);
            $counts['videos']++;
            continue;
        }
        $img = process_image("$src/{$g['file']}", $g['crop'] ?? null, null, GALLERY_MAX_SIDE, $stage, $name);
        $entry['gallery'][] = ['type' => 'image', 'dir' => 'project-gallery', 'file' => $img['file'], 'width' => $img['width'], 'height' => $img['height'], 'alt' => $g['alt']];
        $counts['images']++;
    }
    $json['projects'][] = $entry;
    echo "  {$p['slug']}: main + " . count($entry['gallery']) . " gallery items\n";
}

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

printf("Built %s (%d projects, %d images, %d videos, %.1f MB)\n", $out, count($json['projects']), $counts['images'], $counts['videos'], filesize($out) / 1048576);

// ---------------------------------------------------------------------------

function fail(string $message): never
{
    fwrite(STDERR, $message . "\n");
    exit(1);
}

/** Stable 32-hex name, so a rebuilt package imports onto the same paths (the import skips files it already has). */
function media_name(string $slug, string $file, string $role): string
{
    return md5("etowah-import|$slug|$file|$role");
}

function check_text(string $slug, array $fields): void
{
    foreach ($fields as $key => $value) {
        if (is_string($value) && isset(LIMITS[$key]) && mb_strlen($value) > LIMITS[$key]) {
            fail("$slug: $key is " . mb_strlen($value) . ' characters (limit ' . LIMITS[$key] . "): $value");
        }
    }
}

/** Upright, cropped, resized JPG (q80) + WebP (q78), the same settings as image_store_upload(). */
function process_image(string $path, ?array $crop, ?int $maxWidth, ?int $maxSide, string $stage, string $name): array
{
    $src = @imagecreatefromjpeg($path) ?: fail("Cannot read $path");
    $exif = @exif_read_data($path);
    $rotate = [3 => 180, 6 => -90, 8 => 90][(int) ($exif['Orientation'] ?? 1)] ?? 0;
    if ($rotate) {
        $src = imagerotate($src, $rotate, 0);
    }
    return encode_image($src, $crop, $maxWidth, $maxSide, $stage, $name);
}

function encode_image(GdImage $src, ?array $crop, ?int $maxWidth, ?int $maxSide, string $stage, string $name): array
{
    [$l, $t, $r, $b] = $crop ?? [0, 0, 1, 1];
    $w = imagesx($src);
    $h = imagesy($src);
    $cx = (int) round($l * $w);
    $cy = (int) round($t * $h);
    $cw = (int) round(($r - $l) * $w);
    $ch = (int) round(($b - $t) * $h);
    $scale = min(1, $maxWidth ? $maxWidth / $cw : 1, $maxSide ? $maxSide / max($cw, $ch) : 1);
    $nw = (int) round($cw * $scale);
    $nh = (int) round($ch * $scale);
    $dst = imagecreatetruecolor($nw, $nh);
    imagecopyresampled($dst, $src, 0, 0, $cx, $cy, $nw, $nh, $cw, $ch);
    imageinterlace($dst, true);
    imagejpeg($dst, "$stage/media/$name.jpg", 80) || fail("Cannot write $name.jpg");
    imagewebp($dst, "$stage/media/$name.webp", 78) || fail("Cannot write $name.webp");
    $leftover = @exif_read_data("$stage/media/$name.jpg");
    if ($leftover && array_intersect_key($leftover, array_flip(['GPSLatitude', 'DateTimeOriginal', 'Make', 'Model', 'Orientation']))) {
        fail("Metadata survived in $name.jpg");
    }
    return ['file' => "media/$name.jpg", 'width' => $nw, 'height' => $nh];
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
    $poster = encode_image(@imagecreatefrompng($frame) ?: fail("No poster frame for $path"), null, null, GALLERY_MAX_SIDE, $stage, $name);
    unlink($frame);

    return [
        'file' => $poster['file'], 'width' => $poster['width'], 'height' => $poster['height'],
        'video' => "media/$name.mp4", 'title' => $g['title'], 'alt' => $g['alt'],
        'upload_date' => $uploadDate, 'duration' => $duration,
        'kbps' => (int) round(filesize($mp4) * 8 / 1000 / max(1, $duration)),
    ];
}
