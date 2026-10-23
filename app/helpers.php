<?php
declare(strict_types=1);

/* Small shared helpers. No database access here, so the installer can use them too. */

const SERVICES = [
    'site-development'      => 'Site Development',
    'underground-utilities' => 'Underground Utilities',
    'pump-stations'         => 'Pump Stations',
    'roadways-stormwater'   => 'Roadways & Stormwater',
];

const MARKETS = [
    'municipal'   => 'Municipal',
    'commercial'  => 'Commercial',
    'residential' => 'Residential',
    'roadway'     => 'Roadway',
];

const EMPLOYMENT_TYPES = ['Full-time', 'Part-time', 'Contract', 'Seasonal', 'Internship', 'Other'];

/** Stored jobs.experience_range => [admin label, public label, minimum months or null]. NULL in the database means not specified. */
const EXPERIENCE_RANGES = [
    'entry' => ['Entry level / no experience', 'Entry level', null],
    '0-1'   => ['0–1 Years', '0–1 Years', 0],
    '1-2'   => ['1–2 Years', '1–2 Years', 12],
    '2-4'   => ['2–4 Years', '2–4 Years', 24],
    '3-5'   => ['3–5 Years', '3–5 Years', 36],
    '5+'    => ['5+ Years', '5+ Years', 60],
    '10+'   => ['10+ Years', '10+ Years', 120],
];

/** Stored jobs.experience_level; only used with a range in years. */
const EXPERIENCE_LEVELS = ['preferred' => 'Preferred', 'required' => 'Required'];

/** Escape for HTML text and attribute values. */
function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Read a value from app/config.php using a dotted path, e.g. config('db.host'). */
function config(string $path, $default = null)
{
    $value = $GLOBALS['ETOWAH_CONFIG'] ?? [];
    foreach (explode('.', $path) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return $default;
        }
        $value = $value[$part];
    }
    return $value;
}

function is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
        || (($_SERVER['SERVER_PORT'] ?? '') === '443');
}

/** Absolute URL on the configured site, e.g. site_url('/careers'). */
function site_url(string $path = '/'): string
{
    // The Site URL setting (dashboard) wins over app/config.php once the site is running.
    $override = defined('ETOWAH_RUNTIME') && function_exists('setting') ? setting('site_url') : '';
    $base = rtrim($override !== '' ? $override : (string) config('site_url', ''), '/');
    return $base . '/' . ltrim($path, '/');
}

/** Root-relative URL for a file in /assets with a cache-busting version. */
function asset(string $path): string
{
    $file = ROOT . '/assets/' . ltrim($path, '/');
    $version = is_file($file) ? (string) filemtime($file) : '1';
    return '/assets/' . ltrim($path, '/') . '?v=' . $version;
}

/** Root-relative URL for a stored media path ("assets/..." or "uploads/..."). */
function media_url(string $path): string
{
    return '/' . ltrim($path, '/');
}

function storage_path(string $sub = ''): string
{
    $base = rtrim((string) config('storage_path', ROOT . '/storage'), '/\\');
    return $sub === '' ? $base : $base . '/' . ltrim($sub, '/');
}

/** "(470) 982-4069" → "tel:+14709824069". */
function tel_href(string $phone): string
{
    $digits = preg_replace('/\D+/', '', $phone);
    if (strlen($digits) === 10) {
        $digits = '1' . $digits;
    }
    return 'tel:+' . $digits;
}

function upper(string $value): string
{
    return function_exists('mb_strtoupper') ? mb_strtoupper($value, 'UTF-8') : strtoupper($value);
}

function str_length(string $value): int
{
    return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
}

function now(): string
{
    return date('Y-m-d H:i:s');
}

function redirect(string $url, int $code = 302): void
{
    header('Location: ' . $url, true, $code);
    exit;
}

function json_out(array $data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

/** Per-visitor hash, never the raw IP. */
function ip_hash(): string
{
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
    return hash('sha256', $ip . (string) config('app_secret', ''));
}

function slugify(string $text): string
{
    $text = strtolower(trim($text));
    $text = str_replace('&', ' and ', $text);
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);
    $text = trim((string) $text, '-');
    return substr($text, 0, 100) ?: 'item';
}

/** Shorten plain text on a word boundary. */
function excerpt(string $text, int $length = 160): string
{
    $text = trim(preg_replace('/\s+/', ' ', preg_replace('/^\s*-\s+/m', '', $text)));
    if (str_length($text) <= $length) {
        return $text;
    }
    $cut = mb_substr($text, 0, $length, 'UTF-8');
    $space = mb_strrpos($cut, ' ', 0, 'UTF-8');
    if ($space !== false && $space > $length * 0.6) {
        $cut = mb_substr($cut, 0, $space, 'UTF-8');
    }
    return rtrim($cut, " ,.;:-") . '…';
}

/**
 * Plain text from the database → safe HTML.
 * A blank line starts a new paragraph; lines starting with "- " become a bullet list.
 */
function plain_text_html(string $text, string $indent = ''): string
{
    $text = str_replace(["\r\n", "\r"], "\n", trim($text));
    if ($text === '') {
        return '';
    }
    $html = [];
    foreach (preg_split('/\n\s*\n/', $text) as $block) {
        $lines = array_values(array_filter(array_map('trim', explode("\n", $block)), 'strlen'));
        $buffer = [];
        $list = [];
        $flushText = function () use (&$buffer, &$html, $indent) {
            if ($buffer) {
                $html[] = $indent . '<p>' . implode('<br>', array_map('e', $buffer)) . '</p>';
                $buffer = [];
            }
        };
        $flushList = function () use (&$list, &$html, $indent) {
            if ($list) {
                $items = array_map(fn($item) => $indent . '  <li>' . e($item) . '</li>', $list);
                $html[] = $indent . "<ul>\n" . implode("\n", $items) . "\n" . $indent . '</ul>';
                $list = [];
            }
        };
        foreach ($lines as $line) {
            if (strncmp($line, '- ', 2) === 0) {
                $flushText();
                $list[] = trim(substr($line, 2));
            } else {
                $flushList();
                $buffer[] = $line;
            }
        }
        $flushText();
        $flushList();
    }
    return implode("\n", $html);
}

/** Header values (email headers, file names) must never contain line breaks. */
function has_line_break(string $value): bool
{
    return strpbrk($value, "\r\n") !== false;
}

function valid_email(string $email): bool
{
    return strlen($email) <= 190 && !has_line_break($email) && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/** Trimmed string from $_POST (never an array). */
function post_str(string $key): string
{
    $value = $_POST[$key] ?? '';
    return is_string($value) ? trim($value) : '';
}

function get_str(string $key): string
{
    $value = $_GET[$key] ?? '';
    return is_string($value) ? trim($value) : '';
}

function send_security_headers(bool $admin): void
{
    if (headers_sent()) {
        return;
    }
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    if (is_https()) {
        header('Strict-Transport-Security: max-age=31536000');
    }
    if ($admin) {
        header('X-Frame-Options: DENY');
        header('X-Robots-Tag: noindex, nofollow');
        header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data:; connect-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'self'; object-src 'none'");
    } else {
        header('X-Frame-Options: SAMEORIGIN');
    }
}

/** Write a line to the private error log. */
function log_error(string $message): void
{
    error_log('[etowah] ' . $message);
}
