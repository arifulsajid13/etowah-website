<?php
declare(strict_types=1);

/* Site settings, page meta, and page image slots. */

const SETTING_DEFAULTS = [
    'company_name'             => 'Etowah Construction Group LLC',
    'contact_name'             => '',   // empty = the name row is hidden everywhere
    'phone'                    => '(470) 982-4069',
    'email'                    => 'ECG@etowahconstruction.com',
    'city_state'               => 'Cartersville, Georgia',
    'license_text'             => 'Licensed in the State of Georgia',
    'license_number'           => 'UC002776',
    'markets_line'             => 'Municipal • Commercial • Residential • Roadway',
    'notify_emails'            => 'ECG@etowahconstruction.com',
    'notify_emails_jobs'       => '',   // empty = job applications go to notify_emails
    'google_site_verification' => '',
    'site_url'                 => '',   // empty = use site_url from app/config.php
    'hide_from_search'         => '1',  // new installs start hidden (demo); switch off at go-live
    'stat1_label' => 'Licensed in Georgia', 'stat1_value' => 'UC002776',
    'stat2_label' => 'Roadway Asphalt',     'stat2_value' => '5.5 miles',
    'stat3_label' => 'Mass Earthwork',      'stat3_value' => '~85,000 CY',
    'stat4_label' => 'Deep Excavation',     'stat4_value' => 'Up to 35 ft',
    // Invoices and online payments. Keys listed in SECRET_SETTINGS are stored encrypted (app/crypto.php).
    'invoice_prefix'                => 'ECG-',
    'invoice_next_number'           => '1001',
    'invoice_default_terms'         => 'Net 30',
    'invoice_default_tax_rate'      => '0',
    'invoice_tax_label'             => 'Sales tax',
    'invoice_footer_note'           => 'Thank you for your business.',
    'invoice_payable_to'            => "Etowah Construction Group LLC\nCartersville, Georgia",
    'invoice_notify_email'          => 'ECG@etowahconstruction.com',
    'invoice_reminders_enabled'     => '0',
    'invoice_reminder_before_days'  => '3',
    'invoice_reminder_overdue_days' => '1,7,14',
    'stripe_enabled'                => '0',
    'stripe_mode'                   => 'test',
    'stripe_secret_key_test'        => '',
    'stripe_secret_key_live'        => '',
    'stripe_webhook_secret_test'    => '',
    'stripe_webhook_secret_live'    => '',
    'stripe_allow_card'             => '1',
    'stripe_allow_ach'              => '1',
    'authnet_enabled'               => '0',
    'authnet_mode'                  => 'sandbox',
    'authnet_login_id'              => '',
    'authnet_transaction_key'       => '',
    'authnet_signature_key'         => '',
];

/** Payment gateway secrets: encrypted at rest, shown masked, never logged. */
const SECRET_SETTINGS = [
    'stripe_secret_key_test', 'stripe_secret_key_live', 'stripe_webhook_secret_test', 'stripe_webhook_secret_live',
    'authnet_transaction_key', 'authnet_signature_key',
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

/** List of notification addresses from a comma-separated setting (contact inquiries by default). */
function notify_emails(string $key = 'notify_emails'): array
{
    $list = array_filter(array_map('trim', explode(',', setting($key))), 'valid_email');
    return array_values(array_unique($list));
}

/** Addresses for job application emails; falls back to the contact inquiry list when none are set. */
function notify_emails_jobs(): array
{
    return notify_emails('notify_emails_jobs') ?: notify_emails();
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
