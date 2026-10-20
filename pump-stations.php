<?php
require __DIR__ . '/app/bootstrap.php';

$pageKey = 'pump-stations';

layout_start($pageKey);
?>

  <main id="main" tabindex="-1">
    <section class="hero">
      <?= hero_image('pump-stations') ?>
      <div class="container hero-content">
        <div class="hero-inner">
          <nav class="breadcrumb" aria-label="Breadcrumb"><ol><li><a href="/">Home</a></li><li>Services</li><li aria-current="page">Pump Stations</li></ol></nav>
          <h1>Pump Stations</h1>
          <p class="hero-sub">New construction and rehabilitation for water and wastewater systems.</p>
          <div class="hero-actions">
            <a class="btn btn-primary" href="/contact">Discuss Your Project <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M9 6l6 6-6 6"/></svg></a>
          </div>
        </div>
      </div>
    </section>

    <section class="section" aria-labelledby="intro-title">
      <div class="container intro-grid">
        <div class="intro-text reveal">
          <p class="eyebrow">PUMP STATION CONSTRUCTION</p>
          <h2 id="intro-title">Complex systems.<br> Careful execution.</h2>
          <p>Etowah Construction Group builds and rehabilitates water and sewage pump stations — from bypass pumping and demolition through wet wells, pumps, process piping, and electrical equipment.</p>
        </div>
        <ul class="scope-panel on-dark reveal">
            <li class="scope-row">
              <svg class="icon" viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M5 42h38"/><path d="M9 42V22l15-10 15 10v20"/><path d="M20 42V31h8v11"/><path d="M24 4v4"/></svg>
              <h3>New Construction</h3>
              <span class="vline" aria-hidden="true"></span>
              <p>New pump stations, wet wells, pumps, and process piping.</p>
            </li>
            <li class="scope-row">
              <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M14.5 5.5a4 4 0 0 0-5.2 5.2L4 16l4 4 5.3-5.3a4 4 0 0 0 5.2-5.2l-2.6 2.6-2.8-.6-.6-2.8z"/></svg>
              <h3>Rehabilitation</h3>
              <span class="vline" aria-hidden="true"></span>
              <p>Demolition of existing equipment, wet well rehabilitation, and new pumps.</p>
            </li>
            <li class="scope-row">
              <svg class="icon" viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M4 34h12"/><path d="M32 34h12"/><path d="M16 30v8"/><path d="M32 30v8"/><path d="M10 34V16h28v18"/><path d="M22 12l4 4-4 4"/></svg>
              <h3>Bypass Pumping</h3>
              <span class="vline" aria-hidden="true"></span>
              <p>Temporary bypass operations that keep systems running during construction.</p>
            </li>
        </ul>
      </div>
    </section>

    <section class="section section--alt" aria-labelledby="scope-title">
      <div class="container">
        <div class="section-head reveal">
          <p class="eyebrow">OUR SCOPE</p>
          <h2 id="scope-title">Every component. One accountable team.</h2>
        </div>
        <div class="card-grid card-grid--3" data-stagger>
          <div class="cap-card">
            <svg class="icon" viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><ellipse cx="24" cy="10" rx="14" ry="4.5"/><path d="M10 10v28c0 2.5 6.3 4.5 14 4.5s14-2 14-4.5V10"/><path d="M10 27c0 2.5 6.3 4.5 14 4.5s14-2 14-4.5"/></svg>
            <h3>Wet Wells</h3>
            <p>Deep specialty excavation, wet well construction, and rehabilitation.</p>
          </div>
          <div class="cap-card">
            <svg class="icon" viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><rect x="5" y="20" width="17" height="14" rx="1.5"/><path d="M10 20v14"/><path d="M15 20v14"/><path d="M22 27h3"/><circle cx="32" cy="27" r="7"/><circle cx="32" cy="27" r="2"/><path d="M32 20v-7h10"/><path d="M42 9v8"/><path d="M4 41h40"/><path d="M9 34v7"/><path d="M32 34v7"/></svg>
            <h3>Pumps &amp; Process Piping</h3>
            <p>Pumps, interior station piping, and large-diameter ductile iron.</p>
          </div>
          <div class="cap-card">
            <svg class="icon" viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M27 4L12 27h11l-3 17 16-24H25z"/></svg>
            <h3>Electrical Equipment</h3>
            <p>Installation of pump station electrical equipment.</p>
          </div>
        </div>
      </div>
    </section>

    <section class="proof" aria-labelledby="proof-title">
      <div class="proof-media"><?= slot_image($pageKey, 'proof') ?></div>
      <div class="proof-content reveal">
        <p class="eyebrow">DEEP EXCAVATION</p>
        <h2 id="proof-title">Built for depth and difficulty.</h2>
        <ul class="checklist">
            <li><span class="check-badge"><svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg></span>Specialty excavation up to 35 feet</li>
            <li><span class="check-badge"><svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg></span>Bypass pumping operations</li>
            <li><span class="check-badge"><svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg></span>Utility tie-ins within public roadways</li>
        </ul>
        <hr class="proof-divider">
        <p>Pump station work for the City of Chattanooga and the Bartow County Water Department — from complete rebuilds to station rehabilitation.</p>
        <a class="text-link" href="/underground-utilities">Explore Underground Utilities <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M5 12h14"/><path d="M13 6l6 6-6 6"/></svg></a>
      </div>
    </section>

<?php service_experience($pageKey, 'PUMP STATION EXPERIENCE'); ?>
  </main>
<?php layout_end(['cta_title' => 'Let’s discuss your pump station project.']); ?>
