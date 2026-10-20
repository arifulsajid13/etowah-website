<?php
require __DIR__ . '/app/bootstrap.php';

$pageKey = 'roadways-stormwater';

layout_start($pageKey);
?>

  <main id="main" tabindex="-1">
    <section class="hero">
      <?= hero_image('roadways-stormwater') ?>
      <div class="container hero-content">
        <div class="hero-inner">
          <nav class="breadcrumb" aria-label="Breadcrumb"><ol><li><a href="/">Home</a></li><li>Services</li><li aria-current="page">Roadways &amp; Stormwater</li></ol></nav>
          <h1>Roadways &amp; Stormwater</h1>
          <p class="hero-sub">Asphalt paving, curb and gutter, and underground stormwater systems.</p>
          <div class="hero-actions">
            <a class="btn btn-primary" href="/contact">Discuss Your Project <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M9 6l6 6-6 6"/></svg></a>
          </div>
        </div>
      </div>
    </section>

    <section class="section" aria-labelledby="intro-title">
      <div class="container intro-grid">
        <div class="intro-text reveal">
          <p class="eyebrow">ROADWAY &amp; STORMWATER</p>
          <h2 id="intro-title">Finished surfaces.<br> Managed water.</h2>
          <p>Etowah Construction Group completes asphalt paving, curb and gutter, DOT-road work, and underground stormwater retention for municipal, commercial, and residential projects.</p>
        </div>
        <ul class="scope-panel on-dark reveal">
            <li class="scope-row">
              <svg class="icon" viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><circle cx="12" cy="34" r="7"/><circle cx="36" cy="34" r="7"/><path d="M12 27h24"/><path d="M18 27V15h11l3 12"/><path d="M4 43h40"/></svg>
              <h3>Asphalt Paving</h3>
              <span class="vline" aria-hidden="true"></span>
              <p>Asphalt paving for DOT roads, commercial sites, and residential streets.</p>
            </li>
            <li class="scope-row">
              <svg class="icon" viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M4 32h13c2 2.5 6 2.5 8 0h5V19h14"/><path d="M4 41h40"/><path d="M30 19v22"/></svg>
              <h3>Curb &amp; Gutter</h3>
              <span class="vline" aria-hidden="true"></span>
              <p>Curb and gutter installation and public-roadway tie-ins.</p>
            </li>
            <li class="scope-row">
              <svg class="icon" viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M4 13h40"/><path d="M10 13l2-4"/><path d="M22 13l2-4"/><path d="M34 13l2-4"/><rect x="8" y="21" width="32" height="19" rx="9.5"/><path d="M14 31c2.5-2 5-2 7.5 0s5 2 7.5 0 4-2 5 0"/></svg>
              <h3>Stormwater Retention</h3>
              <span class="vline" aria-hidden="true"></span>
              <p>Underground retention systems, storm piping, and drainage.</p>
            </li>
        </ul>
      </div>
    </section>

    <section class="section section--alt" aria-labelledby="scope-title">
      <div class="container">
        <div class="section-head reveal">
          <p class="eyebrow">OUR SCOPE</p>
          <h2 id="scope-title">From subgrade to final surface.</h2>
        </div>
        <div class="card-grid card-grid--3" data-stagger>
          <div class="cap-card">
            <svg class="icon" viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M17 6L6 42"/><path d="M31 6l11 36"/><path d="M24 8v5"/><path d="M24 19v6"/><path d="M24 31v7"/></svg>
            <h3>Roadways</h3>
            <p>Asphalt paving and DOT-road work.</p>
          </div>
          <div class="cap-card">
            <svg class="icon" viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M4 32h13c2 2.5 6 2.5 8 0h5V19h14"/><path d="M4 41h40"/><path d="M30 19v22"/></svg>
            <h3>Curb &amp; Gutter</h3>
            <p>Curb and gutter for commercial and residential sites.</p>
          </div>
          <div class="cap-card">
            <svg class="icon" viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M4 13h40"/><path d="M10 13l2-4"/><path d="M22 13l2-4"/><path d="M34 13l2-4"/><rect x="8" y="21" width="32" height="19" rx="9.5"/><path d="M14 31c2.5-2 5-2 7.5 0s5 2 7.5 0 4-2 5 0"/></svg>
            <h3>Underground Retention</h3>
            <p>Underground stormwater retention and storm piping.</p>
          </div>
        </div>
      </div>
    </section>

    <section class="proof" aria-labelledby="proof-title">
      <div class="proof-media"><?= slot_image($pageKey, 'proof') ?></div>
      <div class="proof-content reveal">
        <p class="eyebrow">PUBLIC ROADWAY WORK</p>
        <h2 id="proof-title">Ready for public roads.</h2>
        <ul class="checklist">
            <li><span class="check-badge"><svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg></span>5.5 miles of asphalt across multiple DOT roads</li>
            <li><span class="check-badge"><svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg></span>Public-roadway utility tie-ins</li>
            <li><span class="check-badge"><svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg></span>52,000 cubic feet of underground retention</li>
        </ul>
        <hr class="proof-divider">
        <p>Roadway and stormwater work that completes the full civil scope — after clearing, grading, and utilities.</p>
        <a class="text-link" href="/site-development">Explore Site Development <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M5 12h14"/><path d="M13 6l6 6-6 6"/></svg></a>
      </div>
    </section>

<?php service_experience($pageKey, 'ROADWAY EXPERIENCE'); ?>
  </main>
<?php layout_end(['cta_title' => 'Let’s discuss your roadway project.']); ?>
