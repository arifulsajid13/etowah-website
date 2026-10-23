<?php
/*
 * Photo processing shared by tools/build-project-import.php (and its --samples mode).
 *
 * Every published photo: EXIF orientation → optional straighten → crop → resize (ImageMagick, Lanczos),
 * then a light, measured enhancement, then JPG q80 + WebP q78 written by GD (the same settings as the dashboard),
 * so no metadata survives.
 *
 * Enhancement (allowed edits only — nothing is added, removed, or retouched):
 *   - levels: black/white points from the 0.3% / 99.7% luminance percentiles, applied equally to R, G and B
 *     (colour balance unchanged), limited to at most 5% black and 8% white clipping range;
 *   - exposure: a midtone gamma moving the mean 40% of the way towards 0.45, limited to 0.93–1.2;
 *   - gentle contrast: sigmoidal 1.5 around 50%;
 *   - mild unsharp mask at the final size (0x0.7, amount 0.4, threshold 8% so flat textures like gravel are not sharpened);
 *   - light noise reduction (-enhance before resizing) only for photos marked 'denoise' (dim interiors).
 * Upscaling is never needed: every crop is at least as large as its target size (checked; the build fails otherwise).
 */

function magick(): string
{
    static $bin = null;
    $bin ??= getenv('MAGICK') ?: 'magick';
    return $bin;
}

// Arguments go straight to the process (no shell): on Windows, escapeshellarg() would turn % and ! into spaces.
function run_magick(array $args): void
{
    $proc = proc_open(array_merge([magick()], $args), [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (!is_resource($proc)) {
        throw new RuntimeException('Cannot start ImageMagick (' . magick() . ').');
    }
    $out = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    if (proc_close($proc) !== 0) {
        throw new RuntimeException('ImageMagick failed: ' . trim($out));
    }
}

/** Width and height after EXIF orientation. */
function upright_size(string $path): array
{
    [$w, $h] = getimagesize($path) ?: throw new RuntimeException("Cannot read $path");
    $o = (int) (@exif_read_data($path)['Orientation'] ?? 1);
    return $o >= 5 ? [$h, $w] : [$w, $h];
}

/**
 * Upright, straightened, cropped and resized copy as PNG. Returns [width, height].
 * $crop: [left, top, right, bottom] fractions of the upright (straightened) photo.
 * $size: ['width' => n] (landscape max width) or ['side' => n] (longest side) or ['exact' => [w, h]].
 */
function render_png(string $src, ?array $crop, float $rotate, array $size, bool $denoise, string $out): array
{
    [$w, $h] = upright_size($src);
    $args = [$src, '-auto-orient'];
    if ($rotate != 0.0) {
        // Rotate, then keep the largest centred rectangle without empty corners.
        $rad = deg2rad(abs($rotate));
        $k = cos($rad) - sin($rad) * max($w / $h, $h / $w);
        $args = array_merge($args, ['-background', 'black', '-rotate', (string) $rotate, '-gravity', 'center',
            '-crop', (int) floor($w * $k) . 'x' . (int) floor($h * $k) . '+0+0', '+repage', '-gravity', 'northwest']);
        [$w, $h] = [(int) floor($w * $k), (int) floor($h * $k)];
    }
    [$l, $t, $r, $b] = $crop ?? [0, 0, 1, 1];
    $cw = (int) round(($r - $l) * $w);
    $ch = (int) round(($b - $t) * $h);
    $args = array_merge($args, ['-crop', "{$cw}x{$ch}+" . (int) round($l * $w) . '+' . (int) round($t * $h), '+repage']);
    if ($denoise) {
        $args[] = '-enhance';
    }
    if (isset($size['exact'])) {
        [$nw, $nh] = $size['exact'];
    } else {
        $scale = isset($size['width']) ? $size['width'] / $cw : $size['side'] / max($cw, $ch);
        $scale = min(1, $scale);
        [$nw, $nh] = [(int) round($cw * $scale), (int) round($ch * $scale)];
    }
    if ($nw > $cw || $nh > $ch) {
        throw new RuntimeException(basename($src) . ": crop {$cw}x{$ch} is smaller than the target {$nw}x{$nh} (would need upscaling).");
    }
    run_magick(array_merge($args, ['-filter', 'Lanczos', '-resize', "{$nw}x{$nh}!", '-strip', 'PNG24:' . $out]));
    return [$nw, $nh];
}

/** Luminance percentiles and mean of a PNG, measured on a small copy. */
function luminance_stats(string $png): array
{
    $im = imagecreatefrompng($png);
    $sw = 400;
    $sh = max(1, (int) round(imagesy($im) * $sw / imagesx($im)));
    $s = imagecreatetruecolor($sw, $sh);
    imagecopyresampled($s, $im, 0, 0, 0, 0, $sw, $sh, imagesx($im), imagesy($im));
    $hist = array_fill(0, 256, 0);
    $sum = 0;
    for ($y = 0; $y < $sh; $y++) {
        for ($x = 0; $x < $sw; $x++) {
            $c = imagecolorat($s, $x, $y);
            $l = (int) round((($c >> 16) & 255) * .2126 + (($c >> 8) & 255) * .7152 + ($c & 255) * .0722);
            $hist[$l]++;
            $sum += $l;
        }
    }
    $n = $sw * $sh;
    $pct = function (float $p) use ($hist, $n): float {
        $acc = 0;
        foreach ($hist as $v => $count) {
            $acc += $count;
            if ($acc >= $p * $n) {
                return $v / 255;
            }
        }
        return 1.0;
    };
    return ['lo' => $pct(0.003), 'hi' => $pct(0.997), 'mean' => $sum / $n / 255];
}

/** Apply the light enhancement to a PNG in place. Returns a short description of what was applied. */
function enhance_png(string $png): string
{
    $s = luminance_stats($png);
    $black = min($s['lo'], 0.05);
    $white = max($s['hi'], 0.92);
    $mean = max(0.02, min(0.98, ($s['mean'] - $black) / max(0.01, $white - $black)));
    $gamma = log($mean) / log(0.45);
    $gamma = max(0.93, min(1.2, 1 + ($gamma - 1) * 0.4));
    run_magick([$png, '-level', sprintf('%.2f%%,%.2f%%,%.3f', $black * 100, $white * 100, $gamma),
        '-sigmoidal-contrast', '1.5x50%', '-unsharp', '0x0.7+0.4+0.08', '-strip', 'PNG24:' . $png]);
    return sprintf('levels %.1f–%.1f%%, gamma %.2f', $black * 100, $white * 100, $gamma);
}

/** JPG q80 + WebP q78 from a PNG (GD writes no metadata). */
function encode_png(string $png, string $dir, string $name): array
{
    $im = imagecreatefrompng($png) ?: throw new RuntimeException("Cannot read $png");
    imageinterlace($im, true);
    imagejpeg($im, "$dir/$name.jpg", 80) || throw new RuntimeException("Cannot write $name.jpg");
    imagewebp($im, "$dir/$name.webp", 78) || throw new RuntimeException("Cannot write $name.webp");
    $leftover = @exif_read_data("$dir/$name.jpg");
    if ($leftover && array_intersect_key($leftover, array_flip(['GPSLatitude', 'DateTimeOriginal', 'Make', 'Model', 'Orientation']))) {
        throw new RuntimeException("Metadata survived in $name.jpg");
    }
    return ['width' => imagesx($im), 'height' => imagesy($im)];
}
