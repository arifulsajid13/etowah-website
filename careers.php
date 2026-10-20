<?php
require __DIR__ . '/app/bootstrap.php';

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
<?php foreach ($jobs as $job): ?>
          <a class="cap-card job-card" href="/careers/<?= e($job['slug']) ?>">
<?php if (($meta = job_meta_line($job)) !== ''): ?>
            <p class="meta"><?= e($meta) ?></p>
<?php endif; ?>
            <h3><?= e($job['title']) ?></h3>
            <p><?= e(excerpt((string) $job['description'], 160)) ?></p>
            <span class="text-link">View &amp; Apply <?= icon('arrow-right') ?></span>
          </a>
<?php endforeach; ?>
        </div>
<?php else: ?>
        <div class="job-empty reveal">
          <h3>There are no open positions right now.</h3>
          <p>Interested in working with Etowah? Send us your details below.</p>
        </div>
<?php endif; ?>
      </div>
    </section>
  </main>
<?php layout_end(['cta_title' => 'Interested in joining our team?']); ?>
