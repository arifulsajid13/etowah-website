<?php
require __DIR__ . '/app/bootstrap.php';

$pageKey = 'site-development';

layout_start($pageKey);
?>

  <main id="main" tabindex="-1">
    <section class="hero">
      <?= hero_image('site-development') ?>
      <div class="container hero-content">
        <div class="hero-inner">
          <nav class="breadcrumb" aria-label="Breadcrumb"><ol><li><a href="/">Home</a></li><li>Services</li><li aria-current="page">Site Development</li></ol></nav>
          <h1>Site Development</h1>
          <p class="hero-sub">Clearing, grading, and earthwork that prepare your site for what comes next.</p>
          <div class="hero-actions">
            <a class="btn btn-primary" href="/contact">Discuss Your Project <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M9 6l6 6-6 6"/></svg></a>
          </div>
        </div>
      </div>
    </section>

    <section class="section" aria-labelledby="intro-title">
      <div class="container intro-grid">
        <div class="intro-text reveal">
          <p class="eyebrow">SITE DEVELOPMENT</p>
          <h2 id="intro-title">Prepared ground.<br> Solid foundation.</h2>
          <p>Etowah Construction Group delivers clearing, grading, mass earthwork, and site preparation for municipal, commercial, and residential projects — taking raw land to a site ready for utilities and paving.</p>
        </div>
        <ul class="scope-panel on-dark reveal">
            <li class="scope-row">
              <svg class="icon" viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M24 5l-10 14h6l-8 11h24l-8-11h6z"/><path d="M24 30v10"/><path d="M8 42h32"/></svg>
              <h3>Clearing &amp; Demolition</h3>
              <span class="vline" aria-hidden="true"></span>
              <p>Site clearing, demolition, and preparation for new construction.</p>
            </li>
            <li class="scope-row">
              <svg class="icon" viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><rect x="4" y="32" width="26" height="9" rx="4.5"/><path d="M8 32v-9h9l3-6h6v15"/><path d="M30 28h6"/><path d="M36 20c2.5 6 2.5 15 0 21h7V20z"/></svg>
              <h3>Grading &amp; Earthwork</h3>
              <span class="vline" aria-hidden="true"></span>
              <p>Mass dirt movement, cut and fill, and final grading.</p>
            </li>
            <li class="scope-row">
              <svg class="icon" viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M5 42h38"/><path d="M5 42V32h12V22h12V12h14v30"/><path d="M5 37h12"/><path d="M17 32h12"/><path d="M17 27h12"/><path d="M29 22h14"/><path d="M29 17h14"/><path d="M11 32v5"/><path d="M23 22v5"/><path d="M23 32v5"/><path d="M36 12v5"/><path d="M36 22v5"/><path d="M11 37v5"/><path d="M36 32v5"/></svg>
              <h3>Retaining Walls</h3>
              <span class="vline" aria-hidden="true"></span>
              <p>Retaining wall construction for grade changes and site support.</p>
            </li>
        </ul>
      </div>
    </section>

    <section class="section section--alt" aria-labelledby="scope-title">
      <div class="container">
        <div class="section-head reveal">
          <p class="eyebrow">OUR SCOPE</p>
          <h2 id="scope-title">One crew. From clearing to finish grade.</h2>
        </div>
        <div class="card-grid card-grid--3" data-stagger>
          <div class="cap-card">
            <svg class="icon" viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M24 5l-10 14h6l-8 11h24l-8-11h6z"/><path d="M24 30v10"/><path d="M8 42h32"/></svg>
            <h3>Clearing &amp; Site Prep</h3>
            <p>Clearing, demolition, and site preparation for new development.</p>
          </div>
          <div class="cap-card">
            <svg class="icon" viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><rect x="4" y="32" width="26" height="9" rx="4.5"/><path d="M8 32v-9h9l3-6h6v15"/><path d="M30 28h6"/><path d="M36 20c2.5 6 2.5 15 0 21h7V20z"/></svg>
            <h3>Mass Earthwork</h3>
            <p>Large-scale dirt movement, cut and fill, and grading.</p>
          </div>
          <div class="cap-card">
            <svg class="icon" viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M5 42h38"/><path d="M5 42V32h12V22h12V12h14v30"/><path d="M5 37h12"/><path d="M17 32h12"/><path d="M17 27h12"/><path d="M29 22h14"/><path d="M29 17h14"/><path d="M11 32v5"/><path d="M23 22v5"/><path d="M23 32v5"/><path d="M36 12v5"/><path d="M36 22v5"/><path d="M11 37v5"/><path d="M36 32v5"/></svg>
            <h3>Retaining Walls</h3>
            <p>Retaining wall construction for commercial and residential sites.</p>
          </div>
        </div>
      </div>
    </section>

    <section class="proof" aria-labelledby="proof-title">
      <div class="proof-media"><?= slot_image($pageKey, 'proof') ?></div>
      <div class="proof-content reveal">
        <p class="eyebrow">FULL SITE PACKAGES</p>
        <h2 id="proof-title">Complete sitework, start to finish.</h2>
        <ul class="checklist">
            <li><span class="check-badge"><svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg></span>~85,000 CY of mass earthwork on a single project</li>
            <li><span class="check-badge"><svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg></span>Two-phase residential site development</li>
            <li><span class="check-badge"><svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg></span>Full-site commercial packages</li>
        </ul>
        <hr class="proof-divider">
        <p>Our crews carry projects from clearing and mass grading through utilities, curb and gutter, and asphalt paving — one team for the complete civil scope.</p>
        <a class="text-link" href="/underground-utilities">Explore Underground Utilities <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M5 12h14"/><path d="M13 6l6 6-6 6"/></svg></a>
      </div>
    </section>

<?php service_experience($pageKey, 'SITE DEVELOPMENT EXPERIENCE'); ?>
  </main>
<?php layout_end(['cta_title' => 'Let’s discuss your site development project.']); ?>
