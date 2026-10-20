<?php
require __DIR__ . '/app/bootstrap.php';

$pageKey = 'underground-utilities';

layout_start($pageKey);
?>

  <main id="main" tabindex="-1">
    <section class="hero">
      <?= hero_image('underground-utilities') ?>
      <div class="container hero-content">
        <div class="hero-inner">
          <nav class="breadcrumb" aria-label="Breadcrumb"><ol><li><a href="/">Home</a></li><li>Services</li><li aria-current="page">Underground Utilities</li></ol></nav>
          <h1>Underground Utilities</h1>
          <p class="hero-sub">Water, sewer, and storm infrastructure built for your site.</p>
          <div class="hero-actions">
            <a class="btn btn-primary" href="/contact">Discuss Your Project <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M9 6l6 6-6 6"/></svg></a>
          </div>
        </div>
      </div>
    </section>

    <section class="section" aria-labelledby="intro-title">
      <div class="container intro-grid">
        <div class="intro-text reveal">
          <p class="eyebrow">UTILITY CONSTRUCTION</p>
          <h2 id="intro-title">Critical infrastructure.<br> Capable execution.</h2>
          <p>Etowah Construction Group installs underground water, sanitary sewer, and storm systems for municipal, commercial, and residential projects.</p>
        </div>
        <ul class="scope-panel on-dark reveal">
            <li class="scope-row">
              <svg class="icon" viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><ellipse cx="10" cy="24" rx="5" ry="10"/><ellipse cx="10" cy="24" rx="2" ry="4.5"/><path d="M10 14h22"/><path d="M10 34h22"/><rect x="32" y="11" width="5" height="26" rx="1"/><path d="M37 17h7"/><path d="M37 31h7"/></svg>
              <h3>Water Distribution</h3>
              <span class="vline" aria-hidden="true"></span>
              <p>Installation of water mains, valves, hydrants, and service connections.</p>
            </li>
            <li class="scope-row">
              <svg class="icon" viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><ellipse cx="16" cy="24" rx="9" ry="13"/><ellipse cx="16" cy="24" rx="4" ry="6.5"/><path d="M16 11h18c5 0 9 5.8 9 13s-4 13-9 13H16"/></svg>
              <h3>Sanitary Sewer</h3>
              <span class="vline" aria-hidden="true"></span>
              <p>Sewer mains, manholes, wet wells and utility tie-ins.</p>
            </li>
            <li class="scope-row">
              <svg class="icon" viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><rect x="6" y="13" width="36" height="22" rx="2"/><path d="M12 18v12"/><path d="M18 18v12"/><path d="M24 18v12"/><path d="M30 18v12"/><path d="M36 18v12"/></svg>
              <h3>Storm Drainage</h3>
              <span class="vline" aria-hidden="true"></span>
              <p>Storm piping, inlets, underground retention, and outfall structures.</p>
            </li>
        </ul>
      </div>
    </section>

    <section class="section section--alt" aria-labelledby="scope-title">
      <div class="container">
        <div class="section-head reveal">
          <p class="eyebrow">OUR SCOPE</p>
          <h2 id="scope-title">Connected systems. Complete sitework.</h2>
        </div>
        <div class="card-grid card-grid--3" data-stagger>
          <div class="cap-card">
            <svg class="icon" viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><ellipse cx="10" cy="24" rx="5" ry="10"/><ellipse cx="10" cy="24" rx="2" ry="4.5"/><path d="M10 14h22"/><path d="M10 34h22"/><rect x="32" y="11" width="5" height="26" rx="1"/><path d="M37 17h7"/><path d="M37 31h7"/></svg>
            <h3>Water Utilities</h3>
            <p>Water distribution, water vaults, meters, and large-diameter ductile iron piping.</p>
          </div>
          <div class="cap-card">
            <svg class="icon" viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><ellipse cx="24" cy="11" rx="15" ry="5"/><path d="M9 11v25c0 2.8 6.7 5 15 5s15-2.2 15-5V11"/><path d="M15 11h18"/></svg>
            <h3>Sanitary Sewer</h3>
            <p>Sewer piping, wet wells, bypass pumping, and utility connections.</p>
          </div>
          <div class="cap-card">
            <svg class="icon" viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><rect x="6" y="13" width="36" height="22" rx="2"/><path d="M12 18v12"/><path d="M18 18v12"/><path d="M24 18v12"/><path d="M30 18v12"/><path d="M36 18v12"/></svg>
            <h3>Storm Systems</h3>
            <p>Storm piping, underground retention, and drainage infrastructure.</p>
          </div>
        </div>
      </div>
    </section>

    <section class="proof" aria-labelledby="proof-title">
      <div class="proof-media"><?= slot_image($pageKey, 'proof') ?></div>
      <div class="proof-content reveal">
        <p class="eyebrow">COMPLEX UTILITY WORK</p>
        <h2 id="proof-title">Ready for demanding connections.</h2>
        <ul class="checklist">
            <li><span class="check-badge"><svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg></span>24-inch restrained-joint ductile iron</li>
            <li><span class="check-badge"><svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg></span>Deep specialty excavation</li>
            <li><span class="check-badge"><svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg></span>Public-roadway utility tie-ins</li>
        </ul>
        <hr class="proof-divider">
        <p>Experience with pump-station piping, bypass operations, and critical utility connections.</p>
        <a class="text-link" href="/pump-stations">Explore Pump Station Services <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M5 12h14"/><path d="M13 6l6 6-6 6"/></svg></a>
      </div>
    </section>

<?php service_experience($pageKey, 'UTILITY PROJECT EXPERIENCE'); ?>
  </main>
<?php layout_end(['cta_title' => 'Let’s discuss your utility project.']); ?>
