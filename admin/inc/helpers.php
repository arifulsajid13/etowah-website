<?php
declare(strict_types=1);
defined('ETOWAH_ADMIN') || exit;

/* Small helpers shared by the dashboard screens. */

const PER_PAGE = 20;

const INQUIRY_STATUSES = ['new' => 'New', 'contacted' => 'Contacted', 'quoted' => 'Quoted', 'won' => 'Won', 'lost' => 'Lost', 'spam' => 'Spam'];
const APPLICATION_STATUSES = ['new' => 'New', 'reviewing' => 'Reviewing', 'interview' => 'Interview', 'hired' => 'Hired', 'rejected' => 'Rejected'];
const JOB_STATUSES = ['draft' => 'Draft', 'open' => 'Open', 'closed' => 'Closed'];
const HOME_POSITIONS = ['none' => 'Not on the homepage', 'card1' => 'Homepage card 1 (left)', 'card2' => 'Homepage card 2 (right)', 'banner' => 'Homepage wide banner'];

function admin_url(string $page, array $params = []): string
{
    $query = http_build_query(['p' => $page] + array_filter($params, fn($v) => $v !== '' && $v !== null));
    return '/admin/?' . $query;
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'msg' => $message];
}

function flash_take(): ?array
{
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $flash;
}

/** Every state change must be a POST with a valid CSRF token. */
function require_post(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        http_response_code(405);
        exit('Method not allowed');
    }
    if (!csrf_valid()) {
        http_response_code(400);
        echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="robots" content="noindex,nofollow"><title>Session expired</title></head>'
            . '<body><h1>Your session expired</h1>'
            . '<p>For your security, this form can only be sent once from a fresh page. <a href="/admin/">Go back to the dashboard</a> and try again.</p></body></html>';
        exit;
    }
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST';
}

function post_int(string $key): int
{
    return (int) post_str($key);
}

function get_int(string $key): int
{
    return (int) get_str($key);
}

/** Choose a value from an allowlist, falling back to $default. */
function pick(string $value, array $allowed, string $default = ''): string
{
    return array_key_exists($value, $allowed) ? $value : $default;
}

function fmt_date(?string $datetime, bool $withTime = true): string
{
    if (!$datetime) {
        return '—';
    }
    $ts = strtotime($datetime);
    return $withTime ? date('M j, Y · g:i A', $ts) : date('M j, Y', $ts);
}

function time_ago(?string $datetime): string
{
    if (!$datetime) {
        return '—';
    }
    $diff = time() - strtotime($datetime);
    if ($diff < 60) {
        return 'just now';
    }
    foreach ([86400 * 30 => 'month', 86400 * 7 => 'week', 86400 => 'day', 3600 => 'hour', 60 => 'minute'] as $secs => $unit) {
        if ($diff >= $secs) {
            $n = intdiv($diff, $secs);
            return $n . ' ' . $unit . ($n === 1 ? '' : 's') . ' ago';
        }
    }
    return 'just now';
}

function status_badge(string $status, array $labels): string
{
    return '<span class="badge badge--' . e($status) . '">' . e($labels[$status] ?? ucfirst($status)) . '</span>';
}

/** Selected / checked attribute helpers. */
function sel(bool $on): string
{
    return $on ? ' selected' : '';
}

function chk(bool $on): string
{
    return $on ? ' checked' : '';
}

/** Page numbers for a result set. Returns [offset, pages, current]. */
function paginate(int $total, int $page): array
{
    $pages = max(1, (int) ceil($total / PER_PAGE));
    $page = min(max(1, $page), $pages);
    return [($page - 1) * PER_PAGE, $pages, $page];
}

function pagination_html(int $pages, int $current, string $route, array $params): string
{
    if ($pages <= 1) {
        return '';
    }
    $html = '<nav class="pagination" aria-label="Pages">';
    if ($current > 1) {
        $html .= '<a class="btn btn--ghost btn--sm" href="' . e(admin_url($route, $params + ['page' => $current - 1])) . '">← Previous</a>';
    }
    $html .= '<span class="pagination-info">Page ' . $current . ' of ' . $pages . '</span>';
    if ($current < $pages) {
        $html .= '<a class="btn btn--ghost btn--sm" href="' . e(admin_url($route, $params + ['page' => $current + 1])) . '">Next →</a>';
    }
    return $html . '</nav>';
}

/** Make a slug unique within a table by adding -2, -3, … */
function unique_slug(string $table, string $slug, int $exceptId = 0): string
{
    $slug = slugify($slug);
    $base = $slug;
    $n = 2;
    while ((int) db_value("SELECT COUNT(*) FROM {$table} WHERE slug = ? AND id <> ?", [$slug, $exceptId]) > 0) {
        $slug = $base . '-' . $n++;
    }
    return $slug;
}

/** A LIKE pattern with wildcards escaped. */
function like(string $term): string
{
    return '%' . addcslashes($term, '%_\\') . '%';
}

const GALLERY_MAX = 12;
const VIDEO_GALLERY_MAX = 3;
// Projects that still need a main-photo description or a meta description.
const PROJECT_SEO_MISSING = "(image_alt = '' OR seo_description IS NULL OR seo_description = '')";

/** Files from a multiple file input (name="field[]") as a list of single-file arrays, empty slots removed. */
function uploaded_files(string $field): array
{
    $raw = $_FILES[$field] ?? null;
    if (!is_array($raw) || !is_array($raw['name'] ?? null)) {
        return [];
    }
    $files = [];
    foreach (array_keys($raw['name']) as $i) {
        if (is_array($raw['name'][$i]) || $raw['error'][$i] === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        $files[] = [
            'name'     => (string) $raw['name'][$i],
            'type'     => (string) $raw['type'][$i],
            'tmp_name' => (string) $raw['tmp_name'][$i],
            'error'    => (int) $raw['error'][$i],
            'size'     => (int) $raw['size'][$i],
        ];
    }
    return $files;
}

/** Turn an uploaded image into stored image data, or flash the problem and return null. */
function admin_image_upload(string $field, string $type, int $maxWidth): ?array
{
    $file = $_FILES[$field] ?? null;
    if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    try {
        return image_store_upload($file, $type, $maxWidth);
    } catch (RuntimeException $e) {
        flash('error', $e->getMessage());
        return ['error' => true];
    }
}

/** Current inquiry filters from the query string. */
function inquiry_filters(): array
{
    return [
        'q'      => mb_substr(get_str('q'), 0, 100),
        'status' => pick(get_str('status'), INQUIRY_STATUSES),
        'sort'   => get_str('sort') === 'oldest' ? 'oldest' : '',
    ];
}

/** WHERE clause, params, and ORDER BY for inquiry filters (shared by the list and the CSV export). */
function inquiry_query(array $f): array
{
    $where = ['1 = 1'];
    $params = [];
    if ($f['status'] !== '') {
        $where[] = 'status = ?';
        $params[] = $f['status'];
    }
    if ($f['q'] !== '') {
        $where[] = '(name LIKE ? OR email LIKE ? OR company LIKE ? OR phone LIKE ? OR message LIKE ? OR project_location LIKE ?)';
        array_push($params, ...array_fill(0, 6, like($f['q'])));
    }
    $order = $f['sort'] === 'oldest' ? 'created_at ASC, id ASC' : 'created_at DESC, id DESC';
    return [implode(' AND ', $where), $params, $order];
}

function application_filters(): array
{
    return [
        'q'      => mb_substr(get_str('q'), 0, 100),
        'status' => pick(get_str('status'), APPLICATION_STATUSES),
        'job'    => get_str('job') === 'general' ? 'general' : (string) max(0, get_int('job') ?: 0),
    ];
}

function application_query(array $f): array
{
    $where = ['1 = 1'];
    $params = [];
    if ($f['status'] !== '') {
        $where[] = 'a.status = ?';
        $params[] = $f['status'];
    }
    if ($f['job'] === 'general') {
        $where[] = 'a.job_id IS NULL';
    } elseif ((int) $f['job'] > 0) {
        $where[] = 'a.job_id = ?';
        $params[] = (int) $f['job'];
    }
    if ($f['q'] !== '') {
        $where[] = '(a.name LIKE ? OR a.email LIKE ? OR a.phone LIKE ? OR a.message LIKE ?)';
        array_push($params, ...array_fill(0, 4, like($f['q'])));
    }
    return [implode(' AND ', $where), $params, 'a.created_at DESC, a.id DESC'];
}

/** Number of placeholder photos still on the site (projects + page images). */
function placeholder_counts(): array
{
    return [
        'projects' => (int) db_value('SELECT COUNT(*) FROM projects WHERE is_placeholder_image = 1'),
        'images'   => (int) db_value('SELECT COUNT(*) FROM page_images WHERE is_placeholder = 1'),
        'seo'      => (int) db_value('SELECT COUNT(*) FROM projects WHERE ' . PROJECT_SEO_MISSING),
    ];
}
