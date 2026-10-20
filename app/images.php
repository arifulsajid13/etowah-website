<?php
declare(strict_types=1);

/* Image output (<img> / <picture>) and dashboard image uploads. */

const IMAGE_MAX_BYTES = 10 * 1024 * 1024;
const IMAGE_MIME_TYPES = [
    'image/jpeg' => IMAGETYPE_JPEG,
    'image/png'  => IMAGETYPE_PNG,
    'image/webp' => IMAGETYPE_WEBP,
];

/** True when a stored path points at a dashboard upload (which has a .webp twin). */
function is_uploaded_image(string $path): bool
{
    return strncmp($path, 'uploads/', 8) === 0;
}

function webp_twin(string $path): string
{
    return preg_replace('/\.jpg$/', '.webp', $path);
}

/**
 * Render an image from a stored row.
 * $img: path, alt, width, height. Options: class, priority (hero: no lazy-load, fetchpriority=high).
 */
function image_html(array $img, array $opt = []): string
{
    $path = (string) ($img['path'] ?? '');
    if ($path === '') {
        return '';
    }
    $attrs = '';
    if (!empty($opt['class'])) {
        $attrs .= ' class="' . e($opt['class']) . '"';
    }
    $attrs .= ' src="' . e(media_url($path)) . '" alt="' . e((string) ($img['alt'] ?? '')) . '"';
    if (!empty($img['width']) && !empty($img['height'])) {
        $attrs .= ' width="' . (int) $img['width'] . '" height="' . (int) $img['height'] . '"';
    }
    $attrs .= !empty($opt['priority']) ? ' fetchpriority="high"' : ' loading="lazy"';
    $attrs .= ' decoding="async"';
    $tag = '<img' . $attrs . '>';

    if (is_uploaded_image($path) && is_file(ROOT . '/' . webp_twin($path))) {
        return '<picture><source srcset="' . e(media_url(webp_twin($path))) . '" type="image/webp">' . $tag . '</picture>';
    }
    return $tag;
}

/** Image row for a project, in the shape image_html() expects. */
function project_image(array $project): array
{
    return [
        'path'   => $project['image_path'],
        'alt'    => $project['image_alt'],
        'width'  => $project['image_width'],
        'height' => $project['image_height'],
    ];
}

/**
 * Validate, re-encode (strips EXIF), resize, and store an uploaded image as JPG + WebP.
 * Returns ['path' => 'uploads/{type}/{name}.jpg', 'width' => int, 'height' => int].
 * Throws RuntimeException with a message that is safe to show to the dashboard user.
 */
function image_store_upload(array $file, string $type, int $maxWidth): array
{
    if (!isset($file['error']) || is_array($file['error'])) {
        throw new RuntimeException('Please choose an image to upload.');
    }
    if ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE) {
        throw new RuntimeException('That image is too large. The limit is 10 MB.');
    }
    if ($file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
        throw new RuntimeException('The image did not upload. Please try again.');
    }
    if ($file['size'] <= 0 || $file['size'] > IMAGE_MAX_BYTES) {
        throw new RuntimeException('That image is too large. The limit is 10 MB.');
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    $info = @getimagesize($file['tmp_name']);
    if (!isset(IMAGE_MIME_TYPES[$mime]) || !$info || $info[2] !== IMAGE_MIME_TYPES[$mime]) {
        throw new RuntimeException('Please upload a JPG, PNG, or WebP image.');
    }
    [$w, $h] = $info;
    if ($w < 200 || $h < 150) {
        throw new RuntimeException('That image is too small. Please use a photo at least 1200 pixels wide.');
    }
    if ($w * $h > 50000000) {
        throw new RuntimeException('That image has too many pixels to process. Please resize it below 7000 × 7000.');
    }
    if (!function_exists('imagecreatetruecolor')) {
        throw new RuntimeException('Image processing (GD) is not available on this server.');
    }

    @ini_set('memory_limit', '512M');
    $src = match ($mime) {
        'image/jpeg' => @imagecreatefromjpeg($file['tmp_name']),
        'image/png'  => @imagecreatefrompng($file['tmp_name']),
        'image/webp' => @imagecreatefromwebp($file['tmp_name']),
    };
    if (!$src) {
        throw new RuntimeException('That image could not be read. Please try a different file.');
    }

    // Phone photos store rotation in EXIF; apply it before EXIF is discarded.
    if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
        $exif = @exif_read_data($file['tmp_name']);
        $rotate = [3 => 180, 6 => -90, 8 => 90][(int) ($exif['Orientation'] ?? 1)] ?? 0;
        if ($rotate) {
            $rotated = imagerotate($src, $rotate, 0);
            if ($rotated) {
                imagedestroy($src);
                $src = $rotated;
            }
        }
    }

    $w = imagesx($src);
    $h = imagesy($src);
    $newW = min($w, $maxWidth);
    $newH = (int) round($h * $newW / $w);

    // Flatten onto white so transparent PNG/WebP areas don't turn black in the JPG.
    $dst = imagecreatetruecolor($newW, $newH);
    imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $newW, $newH, $w, $h);
    imagedestroy($src);

    $type = preg_replace('/[^a-z0-9-]/', '', $type) ?: 'misc';
    $dir = ROOT . '/uploads/' . $type;
    if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
        imagedestroy($dst);
        throw new RuntimeException('The uploads folder is not writable. Check folder permissions in hPanel.');
    }
    $name = bin2hex(random_bytes(16));
    $jpg = $dir . '/' . $name . '.jpg';
    $webp = $dir . '/' . $name . '.webp';
    imageinterlace($dst, true);
    $ok = imagejpeg($dst, $jpg, 80);
    if ($ok && function_exists('imagewebp')) {
        $ok = imagewebp($dst, $webp, 78);
    }
    imagedestroy($dst);
    if (!$ok) {
        @unlink($jpg);
        @unlink($webp);
        throw new RuntimeException('The image could not be saved. Please try again.');
    }

    return ['path' => 'uploads/' . $type . '/' . $name . '.jpg', 'width' => $newW, 'height' => $newH];
}

/** Remove a previously uploaded image (JPG + WebP). Never touches /assets. */
function image_delete_upload(string $path): void
{
    if (!is_uploaded_image($path) || !preg_match('#^uploads/[a-z0-9-]+/[a-f0-9]{32}\.jpg$#', $path)) {
        return;
    }
    @unlink(ROOT . '/' . $path);
    @unlink(ROOT . '/' . webp_twin($path));
}
