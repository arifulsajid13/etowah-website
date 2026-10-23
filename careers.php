<?php
require __DIR__ . '/app/bootstrap.php';
require APP . '/csrf.php';

$slug = get_str('slug');
$job = null;
if ($slug !== '') {
    $job = job_open_by_slug($slug);
    if (!$job) {
        not_found();
    }
}

csrf_token(); // start the session before any output
$ctaTitle = 'Interested in joining our team?';

if ($job):
    layout_start('careers', [
        'title'       => $job['title'] . ' | Careers | Etowah Construction Group',
        'description' => excerpt((string) $job['description'], 155) ?: page_meta('careers')['meta_description'],
        'path'        => '/careers/' . $job['slug'],
        'jsonld'      => [job_posting_jsonld($job)],
    ]);
    $applyJobId = (int) $job['id'];
    $applyTitle = 'Apply for this position';
?>

  <main id="main" tabindex="-1">
    <section class="hero">
      <?= hero_image('careers') ?>
      <div class="container hero-content">
        <div class="hero-inner">
          <nav class="breadcrumb" aria-label="Breadcrumb"><ol><li><a href="/">Home</a></li><li><a href="/careers">Careers</a></li><li aria-current="page"><?= e($job['title']) ?></li></ol></nav>
          <h1><?= e($job['title']) ?></h1>
<?php if (($meta = job_meta_line($job, true)) !== ''): ?>
          <p class="hero-sub"><?= e($meta) ?></p>
<?php endif; ?>
        </div>
      </div>
    </section>

    <section class="section section--alt" aria-labelledby="job-title">
      <div class="container job-detail">
        <div class="job-content reveal">
          <p class="eyebrow">ABOUT THE ROLE</p>
          <h2 id="job-title"><?= e($job['title']) ?></h2>
<?= plain_text_html((string) $job['description'], '          ') ?>

        </div>
<?php require ROOT . '/partials/apply-form.php'; ?>
      </div>
    </section>
  </main>
<?php
    layout_end(['cta_title' => $ctaTitle]);
    return;
endif;

$jobs = jobs_open();

layout_start('careers');
?>

  <main id="main" tabindex="-1">
    <section class="hero">
      <?= hero_image('careers') ?>
      <div class="container hero-content">
        <div class="hero-inner">
          <nav class="breadcrumb" aria-label="Breadcrumb"><ol><li><a href="/">Home</a></li><li aria-current="page">Careers</li></ol></nav>
          <h1>Careers</h1>
          <p class="hero-sub">Build your career with a team that builds from the ground up.</p>
        </div>
      </div>
    </section>

    <section class="section" aria-labelledby="careers-intro-title">
      <div class="container">
        <div class="intro-center reveal">
          <p class="eyebrow">JOIN OUR TEAM</p>
          <h2 id="careers-intro-title">Build with Etowah.</h2>
          <p>Etowah Construction Group delivers site development, underground utility, roadway, and pump-station construction for municipal, commercial, and residential clients. When we have openings, you will find them here.</p>
        </div>
      </div>
    </section>

    <section class="section section--alt" aria-labelledby="openings-title">
      <div class="container">
        <div class="section-head reveal">
          <p class="eyebrow">OPEN POSITIONS</p>
          <h2 id="openings-title">Current openings.</h2>
        </div>
<?php if ($jobs): ?>
        <div class="job-list" data-stagger>
<?php foreach ($jobs as $listed): ?>
          <a class="cap-card job-card" href="/careers/<?= e($listed['slug']) ?>">
<?php if (($meta = job_meta_line($listed)) !== ''): ?>
            <p class="meta"><?= e($meta) ?></p>
<?php endif; ?>
            <h3><?= e($listed['title']) ?></h3>
            <p><?= e(excerpt((string) $listed['description'], 160)) ?></p>
            <span class="text-link">View &amp; Apply <?= icon('arrow-right') ?></span>
          </a>
<?php endforeach; ?>
        </div>
<?php else: ?>
        <div class="job-empty reveal">
          <h3>There are no open positions right now.</h3>
          <p>Interested in working with Etowah? Send us your details below.</p>
        </div>
        <div class="apply-block">
<?php $applyJobId = 0; $applyTitle = 'Send us your details.'; require ROOT . '/partials/apply-form.php'; ?>
        </div>
<?php endif; ?>
      </div>
    </section>
  </main>
<?php layout_end(['cta_title' => $ctaTitle]); ?>
