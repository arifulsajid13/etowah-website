<?php
declare(strict_types=1);

/* Page layout: head + header before <main>, CTA band / footer after it. */

require_once ROOT . '/partials/icons.php';

/**
 * Print everything up to (not including) <main>.
 * $opt: active (nav key), title, description, path (canonical), og_image, jsonld (extra objects), noindex.
 */
function layout_start(string $pageKey, array $opt = []): void
{
    $meta = page_meta($pageKey);
    $hero = page_image($pageKey, 'hero');

    $title = $opt['title'] ?? $meta['meta_title'];
    $description = $opt['description'] ?? $meta['meta_description'];
    $canonical = site_url($opt['path'] ?? (PAGES[$pageKey][0] ?? '/'));
    $ogImage = $opt['og_image'] ?? ($hero ? site_url(media_url($hero['path'])) : '');
    $active = $opt['active'] ?? $pageKey;
    $noindex = !empty($opt['noindex']);

    $jsonld = array_merge([organization_jsonld()], $opt['jsonld'] ?? []);
    $verification = setting('google_site_verification');

    require ROOT . '/partials/head.php';
    require ROOT . '/partials/header.php';
}

/** $opt: footer ('cta' | 'compact'), cta_title, cta_button. */
function layout_end(array $opt = []): void
{
    $ctaTitle = $opt['cta_title'] ?? 'Let’s discuss your next project.';
    $ctaButton = $opt['cta_button'] ?? 'Get in Touch';
    $footerStyle = footer_background_style();
    if (($opt['footer'] ?? 'cta') === 'compact') {
        require ROOT . '/partials/footer-only.php';
    } else {
        require ROOT . '/partials/cta-footer.php';
    }
}

/** Inline background override when the CTA background has been replaced from the dashboard. */
function footer_background_style(): string
{
    $img = page_image('global', 'cta-bg');
    if (!$img || !is_uploaded_image($img['path'])) {
        return '';
    }
    $jpg = media_url($img['path']);
    $layers = 'linear-gradient(rgba(30, 37, 43, .88), rgba(30, 37, 43, .88))';
    $style = 'background-image: ' . $layers . ', url(\'' . $jpg . '\');';
    if (is_file(ROOT . '/' . webp_twin($img['path']))) {
        $style .= ' background-image: ' . $layers . ', image-set(url(\'' . media_url(webp_twin($img['path'])) . '\') type(\'image/webp\'), url(\'' . $jpg . '\') type(\'image/jpeg\'));';
    }
    return ' style="' . e($style) . '"';
}

function organization_jsonld(): array
{
    [$city, $region] = array_pad(array_map('trim', explode(',', setting('city_state'), 2)), 2, '');
    $regions = ['georgia' => 'GA', 'tennessee' => 'TN', 'alabama' => 'AL'];
    $region = $regions[strtolower($region)] ?? $region;

    $org = [
        '@context'  => 'https://schema.org',
        '@type'     => 'GeneralContractor',
        'name'      => setting('company_name'),
        'url'       => site_url('/'),
        'logo'      => site_url('/assets/images/logo/logo-horizontal.png'),
        'telephone' => setting('phone'),
        'email'     => setting('email'),
        'address'   => array_filter([
            '@type'           => 'PostalAddress',
            'addressLocality' => $city,
            'addressRegion'   => $region,
            'addressCountry'  => 'US',
        ], 'strlen'),
    ];
    return array_filter($org, fn($v) => $v !== '' && $v !== []);
}

function jsonld_script(array $data): string
{
    $json = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP);
    return '<script type="application/ld+json">' . $json . '</script>';
}

/** Hero <img> (or <picture>) for a page. */
function hero_image(string $pageKey): string
{
    $img = page_image($pageKey, 'hero');
    return $img ? image_html($img, ['class' => 'hero-media', 'priority' => true]) : '';
}

/** Lazy-loaded image for a non-hero slot. */
function slot_image(string $pageKey, string $slot): string
{
    $img = page_image($pageKey, $slot);
    return $img ? image_html($img) : '';
}

/**
 * Render one project card using partials/project-card.php.
 * $card: element (a|article), class, href, id, image, meta, title, text, tag_service.
 */
function project_card(array $card): string
{
    ob_start();
    require ROOT . '/partials/project-card.php';
    return ob_get_clean();
}

/** Card data for a project on the Projects page (full description, client name, service tag). */
function project_page_card(array $p, string $class): array
{
    $meta = array_filter([project_status_label($p['status']), (string) $p['location'], MARKETS[$p['market']]], 'strlen');
    $text = trim((string) $p['description']);
    if (!empty($p['client_name'])) {
        $text = $p['client_name'] . '. ' . $text;
    }
    return [
        'element'     => 'article',
        'class'       => $class,
        'id'          => $p['slug'],
        'image'       => project_image($p),
        'meta'        => implode(' • ', $meta),
        'title'       => $p['title'],
        'text'        => $text,
        'tag_service' => (string) $p['primary_service'],
    ];
}

/** "Experience" section on a service page; hidden when no published project is linked. */
function service_experience(string $service, string $eyebrow): void
{
    $projects = projects_for_service($service, 3);
    if (!$projects) {
        return;
    }
    $grid = count($projects) === 3 ? 'project-grid project-grid--3' : 'project-grid';
    echo <<<HTML
    <section class="section" aria-labelledby="experience-title">
      <div class="container">
        <div class="section-head reveal">
          <p class="eyebrow">{$eyebrow}</p>
          <h2 id="experience-title">Proven in the field.</h2>
        </div>
        <div class="{$grid}" data-stagger>

HTML;
    foreach ($projects as $p) {
        echo project_card([
            'href'  => '/projects#' . $p['slug'],
            'image' => project_image($p),
            'meta'  => project_place($p) . ' • ' . project_status_label($p['status']),
            'title' => $p['card_title'],
            'text'  => $p['card_summary'],
        ]);
    }
    echo <<<HTML
        </div>
      </div>
    </section>

HTML;
}

/** The 404 page, then stop. */
function not_found(): void
{
    require ROOT . '/404.php';
    exit;
}
