<?php
declare(strict_types=1);

/* Read-only content queries used by the public pages. */

/** Admin only: the public site shows no project status. */
function project_status_label(string $status): string
{
    return $status === 'current' ? 'Current' : 'Completed';
}

/** Location, or the market name when a project has no location. */
function project_place(array $project): string
{
    return $project['location'] ?: MARKETS[$project['market']];
}

function projects_published(): array
{
    return db_all('SELECT * FROM projects WHERE published = 1 ORDER BY sort_order, id');
}

/** Published projects keyed by home position (card1, card2, banner). */
function projects_on_home(): array
{
    $out = [];
    foreach (db_all("SELECT * FROM projects WHERE published = 1 AND home_position <> 'none' ORDER BY sort_order, id") as $row) {
        $out[$row['home_position']] ??= $row;
    }
    return $out;
}

/** Up to $limit published projects linked to a service, with the card title/text for that page. */
function projects_for_service(string $service, int $limit = 3): array
{
    return db_all(
        'SELECT p.*, COALESCE(NULLIF(ps.card_title, \'\'), p.title) AS card_title,
                COALESCE(NULLIF(ps.card_summary, \'\'), p.summary) AS card_summary
           FROM project_services ps
           JOIN projects p ON p.id = ps.project_id
          WHERE ps.service_key = ? AND p.published = 1
          ORDER BY ps.sort_order, p.sort_order, p.id
          LIMIT ' . (int) $limit,
        [$service]
    );
}

function projects_for_market(string $market): array
{
    return db_all('SELECT id, title, slug FROM projects WHERE published = 1 AND market = ? ORDER BY sort_order, id', [$market]);
}

function project_url(array $project): string
{
    return '/projects/' . $project['slug'];
}

function project_published_by_slug(string $slug): ?array
{
    return $slug === '' ? null : db_one('SELECT * FROM projects WHERE published = 1 AND slug = ?', [$slug]);
}

/** Service keys linked to a project, in the site's service order. */
function project_service_keys(int $projectId): array
{
    $keys = array_column(db_all('SELECT service_key FROM project_services WHERE project_id = ?', [$projectId]), 'service_key');
    return array_values(array_intersect(array_keys(SERVICES), $keys));
}

/** Gallery images in the shape image_html() expects. */
function project_gallery(int $projectId): array
{
    return db_all('SELECT id, path, alt, width, height FROM project_images WHERE project_id = ? ORDER BY sort_order, id', [$projectId]);
}

/** Up to $limit other published projects in the same market. */
function projects_related(array $project, int $limit = 3): array
{
    return db_all(
        'SELECT * FROM projects WHERE published = 1 AND market = ? AND id <> ? ORDER BY sort_order, id LIMIT ' . (int) $limit,
        [$project['market'], $project['id']]
    );
}

/** SEO title, or "{title} | {location} | Etowah Construction Group". */
function project_meta_title(array $project): string
{
    $custom = trim((string) ($project['seo_title'] ?? ''));
    if ($custom !== '') {
        return $custom;
    }
    return implode(' | ', array_filter([trim($project['title']), trim((string) $project['location']), 'Etowah Construction Group'], 'strlen'));
}

function project_meta_description(array $project): string
{
    $custom = trim((string) ($project['seo_description'] ?? ''));
    return $custom !== '' ? $custom : trim((string) $project['summary']);
}

function jobs_open(): array
{
    return db_all("SELECT * FROM jobs WHERE status = 'open' ORDER BY posted_at DESC, id DESC");
}

function job_open_by_slug(string $slug): ?array
{
    return db_one("SELECT * FROM jobs WHERE status = 'open' AND slug = ?", [$slug]);
}

/** "LOCATION • EMPLOYMENT TYPE" line for a job, skipping empty parts. */
function job_meta_line(array $job): string
{
    return upper(implode(' • ', array_filter([$job['location'], $job['employment_type']], 'strlen')));
}
