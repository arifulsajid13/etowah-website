<?php
declare(strict_types=1);

/* Site settings, page meta, and page image slots. */

const SETTING_DEFAULTS = [
    'company_name'             => 'Etowah Construction Group LLC',
    'contact_name'             => 'Kenny Wallis',
    'phone'                    => '678-763-8047',
    'email'                    => 'Kenny@etowahconstruction.com',
    'city_state'               => 'Cartersville, Georgia',
    'license_text'             => 'Licensed Utility Contractor, State of Georgia',
    'license_number'           => 'UC002776',
    'markets_line'             => 'Municipal • Commercial • Residential • Roadway',
    'notify_emails'            => 'Kenny@etowahconstruction.com',
    'google_site_verification' => '',
    'site_url'                 => '',   // empty = use site_url from app/config.php
    'hide_from_search'         => '1',  // new installs start hidden (demo); switch off at go-live
    'stat1_label' => 'Licensed in Georgia', 'stat1_value' => 'UC002776',
    'stat2_label' => 'Roadway Asphalt',     'stat2_value' => '5.5 miles',
    'stat3_label' => 'Mass Earthwork',      'stat3_value' => '~85,000 CY',
    'stat4_label' => 'Deep Excavation',     'stat4_value' => 'Up to 35 ft',
];

/** Public pages: key => [clean path, label used in the dashboard]. */
const PAGES = [
    'home'                  => ['/', 'Home'],
    'site-development'      => ['/site-development', 'Site Development'],
    'underground-utilities' => ['/underground-utilities', 'Underground Utilities'],
    'pump-stations'         => ['/pump-stations', 'Pump Stations'],
    'roadways-stormwater'   => ['/roadways-stormwater', 'Roadways & Stormwater'],
    'markets'               => ['/markets', 'Markets'],
    'projects'              => ['/projects', 'Projects'],
    'about'                 => ['/about', 'About'],
    'careers'               => ['/careers', 'Careers'],
    'contact'               => ['/contact', 'Contact'],
];

function settings_all(bool $refresh = false): array
{
    static $cache = null;
    if ($cache === null || $refresh) {
        $cache = SETTING_DEFAULTS;
        foreach (db_all('SELECT k, v FROM settings') as $row) {
            $cache[$row['k']] = (string) $row['v'];
        }
    }
    return $cache;
}

function setting(string $key): string
{
    return (string) (settings_all()[$key] ?? '');
}

function settings_save(array $values): void
{
    $stmt = db()->prepare('INSERT INTO settings (k, v) VALUES (?, ?) ON DUPLICATE KEY UPDATE v = VALUES(v)');
    foreach ($values as $k => $v) {
        $stmt->execute([$k, (string) $v]);
    }
}

/** True while the “Hide site from search engines” setting is on. */
function search_hidden(): bool
{
    return setting('hide_from_search') === '1';
}

/** List of notification addresses from the comma-separated setting. */
function notify_emails(): array
{
    $list = array_filter(array_map('trim', explode(',', setting('notify_emails'))), 'valid_email');
    return array_values(array_unique($list));
}

function page_meta(string $key): array
{
    $row = db_one('SELECT meta_title, meta_description, updated_at FROM pages WHERE page_key = ?', [$key]);
    return $row ?: ['meta_title' => '', 'meta_description' => '', 'updated_at' => null];
}

/** All image slots, keyed "page/slot", loaded once per request. */
function page_images_all(): array
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        foreach (db_all('SELECT * FROM page_images') as $row) {
            $cache[$row['page_key'] . '/' . $row['slot']] = $row;
        }
    }
    return $cache;
}

function page_image(string $page, string $slot): ?array
{
    return page_images_all()[$page . '/' . $slot] ?? null;
}
