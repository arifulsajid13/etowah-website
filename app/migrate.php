<?php
declare(strict_types=1);

/*
 * Database upgrades for sites installed with an older version.
 * The current version is stored in settings.schema_version; fresh installs get it from the seed.
 */

const SCHEMA_VERSION = 3;

/** Run any pending upgrades once. Called from bootstrap on every request (one cheap settings lookup). */
function migrate_if_needed(): void
{
    $current = (int) setting('schema_version');
    if ($current >= SCHEMA_VERSION) {
        return;
    }
    try {
        run_migrations(db(), $current);
        settings_all(true);
    } catch (Throwable $e) {
        log_error('Database upgrade failed: ' . $e->getMessage());
    }
}

function run_migrations(PDO $pdo, int $from): void
{
    if ($from < 2) {
        // v2: project SEO fields, project galleries, Site URL and search-engine settings.
        foreach (['seo_title' => 'VARCHAR(70) NULL AFTER description', 'seo_description' => 'VARCHAR(170) NULL AFTER seo_title'] as $column => $definition) {
            if (!db_column_exists($pdo, 'projects', $column)) {
                $pdo->exec("ALTER TABLE projects ADD COLUMN {$column} {$definition}");
            }
        }
        $pdo->exec("CREATE TABLE IF NOT EXISTS project_images (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            project_id INT UNSIGNED NOT NULL,
            path VARCHAR(255) NOT NULL,
            alt VARCHAR(255) NOT NULL DEFAULT '',
            width INT UNSIGNED NOT NULL DEFAULT 0,
            height INT UNSIGNED NOT NULL DEFAULT 0,
            sort_order INT NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_pi_project (project_id, sort_order),
            CONSTRAINT fk_pi_project FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        // An existing site may already be live, so it stays visible to search engines after the upgrade.
        $pdo->exec("INSERT IGNORE INTO settings (k, v) VALUES ('hide_from_search', '0'), ('site_url', '')");
    }
    if ($from < 3) {
        // v3: the public site shows no project status. Only seeded copy that was never edited is updated.
        $pdo->prepare('UPDATE pages SET meta_description = ? WHERE page_key = ? AND meta_description = ?')->execute([
            'Site development, underground utility, pump station, and roadway projects across Georgia and Tennessee.',
            'projects',
            'Current and completed site development, underground utility, pump station, and roadway projects in Georgia and Tennessee.',
        ]);
        $pdo->prepare("UPDATE projects SET description = CONCAT('Completed', SUBSTRING(description, 11)) WHERE slug = ? AND description LIKE 'Completing all site work scopes,%'")
            ->execute(['alpharetta-commercial']);
    }
    $stmt = $pdo->prepare("INSERT INTO settings (k, v) VALUES ('schema_version', ?) ON DUPLICATE KEY UPDATE v = VALUES(v)");
    $stmt->execute([(string) SCHEMA_VERSION]);
}

function db_column_exists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
    $stmt->execute([$table, $column]);
    return (int) $stmt->fetchColumn() > 0;
}
