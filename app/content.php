<?php
declare(strict_types=1);

/* Read-only content queries used by the public pages. */

function project_status_label(string $status): string
{
    return $status === 'current' ? 'Current' : 'Completed';
}

/** Location, or the market name when a project has no location. */
function project_place(array $project): string
{
    return $project['location'] ?: MARKETS[$project['market']];
}

function projects_published(?string $status = null): array
{
    if ($status !== null) {
        return db_all('SELECT * FROM projects WHERE published = 1 AND status = ? ORDER BY sort_order, id', [$status]);
    }
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
