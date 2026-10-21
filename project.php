<?php
require __DIR__ . '/app/bootstrap.php';

$p = project_published_by_slug(get_str('slug'));
if (!$p) {
    not_found();
}

$services = project_service_keys((int) $p['id']);
$gallery = project_gallery((int) $p['id']);
$related = projects_related($p, 3);
$hero = project_image($p);
$market = MARKETS[$p['market']];
$meta = implode(' • ', array_filter([(string) $p['location'], $market], 'strlen'));

layout_start('projects', [
    'title'       => project_meta_title($p),
    'description' => project_meta_description($p),
    'path'        => project_url($p),
    'og_image'    => $hero['path'] !== '' ? site_url(media_url($hero['path'])) : null,
    'jsonld'      => [breadcrumb_jsonld([['Home', '/'], ['Projects', '/projects'], [$p['title'], project_url($p)]])],
]);
?>

  <main id="main" tabindex="-1">
    <section class="hero">
      <?= image_html($hero, ['class' => 'hero-media', 'priority' => true]) ?>
      <div class="container hero-content">
        <div class="hero-inner">
          <nav class="breadcrumb" aria-label="Breadcrumb"><ol><li><a href="/">Home</a></li><li><a href="/projects">Projects</a></li><li aria-current="page"><?= e($p['title']) ?></li></ol></nav>
          <h1><?= e($p['title']) ?></h1>
          <p class="hero-sub"><?= e(upper($meta)) ?></p>
        </div>
      </div>
    </section>

    <section class="section" aria-labelledby="overview-title">
      <div class="container">
        <dl class="credentials project-facts reveal">
<?php if (!empty($p['client_name'])): ?>
          <div><dt>Client</dt><dd><?= e($p['client_name']) ?></dd></div>
<?php endif; ?>
<?php if (!empty($p['location'])): ?>
          <div><dt>Location</dt><dd><?= e($p['location']) ?></dd></div>
<?php endif; ?>
          <div><dt>Market</dt><dd><a href="/markets#<?= e($p['market']) ?>"><?= e($market) ?></a></dd></div>
<?php if ($services): ?>
          <div><dt>Services</dt><dd><?= implode(', ', array_map(fn($s) => '<a href="/' . $s . '">' . e(SERVICES[$s]) . '</a>', $services)) ?></dd></div>
<?php endif; ?>
        </dl>
        <div class="project-overview reveal">
          <p class="eyebrow">PROJECT OVERVIEW</p>
          <h2 id="overview-title">About this project.</h2>
<?= plain_text_html(trim((string) $p['description']) !== '' ? (string) $p['description'] : (string) $p['summary'], '          ') ?>

        </div>
      </div>
    </section>
<?php if ($gallery): ?>

    <section class="section section--alt" aria-labelledby="gallery-title">
      <div class="container">
        <div class="section-head reveal">
          <p class="eyebrow">PROJECT PHOTOS</p>
          <h2 id="gallery-title">Project gallery.</h2>
        </div>
        <ul class="gallery-grid" data-gallery data-stagger>
<?php foreach ($gallery as $i => $img): $webp = is_file(ROOT . '/' . webp_twin($img['path'])) ? media_url(webp_twin($img['path'])) : ''; ?>
          <li><button class="gallery-item" type="button" data-full="<?= e(media_url($img['path'])) ?>" data-webp="<?= e($webp) ?>" data-alt="<?= e($img['alt']) ?>" data-width="<?= (int) $img['width'] ?>" data-height="<?= (int) $img['height'] ?>" aria-label="Open photo <?= $i + 1 ?> of <?= count($gallery) ?>: <?= e($img['alt']) ?>"><?= image_html($img) ?></button></li>
<?php endforeach; ?>
        </ul>
      </div>
    </section>

    <dialog class="lightbox" aria-label="Photo viewer" data-lightbox>
      <div class="lightbox-inner">
        <figure class="lightbox-figure">
          <picture><source type="image/webp" data-lightbox-source><img src="" alt="" data-lightbox-img></picture>
          <figcaption class="lightbox-caption"><span data-lightbox-caption></span> <span class="lightbox-count" data-lightbox-count></span></figcaption>
        </figure>
        <button class="lightbox-btn lightbox-prev" type="button" data-lightbox-prev aria-label="Previous photo"><?= icon('chevron-right') ?></button>
        <button class="lightbox-btn lightbox-next" type="button" data-lightbox-next aria-label="Next photo"><?= icon('chevron-right') ?></button>
        <button class="lightbox-btn lightbox-close" type="button" data-lightbox-close aria-label="Close photo viewer"><?= icon('close') ?></button>
      </div>
    </dialog>
<?php endif; ?>
<?php if ($related): ?>

    <section class="section<?= $gallery ? '' : ' section--alt' ?>" aria-labelledby="related-title">
      <div class="container">
        <div class="section-head reveal">
          <p class="eyebrow">RELATED PROJECTS</p>
          <h2 id="related-title">More <?= e(strtolower($market)) ?> work.</h2>
        </div>
        <div class="project-grid<?= count($related) === 3 ? ' project-grid--3' : '' ?>" data-stagger>
<?php foreach ($related as $r): ?>
<?= project_card([
    'href'  => project_url($r),
    'image' => project_image($r),
    'meta'  => project_place($r),
    'title' => $r['title'],
    'text'  => $r['summary'],
]) ?>
<?php endforeach; ?>
        </div>
      </div>
    </section>
<?php endif; ?>
  </main>
<?php layout_end(); ?>
