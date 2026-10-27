<?php
require __DIR__ . '/app/bootstrap.php';

layout_start('about');
?>

  <main id="main" tabindex="-1">
    <section class="hero">
      <?= hero_image('about') ?>
      <div class="container hero-content">
        <div class="hero-inner">
          <nav class="breadcrumb" aria-label="Breadcrumb"><ol><li><a href="/">Home</a></li><li aria-current="page">About</li></ol></nav>
          <h1>About Etowah</h1>
          <p class="hero-sub">A licensed Georgia utility construction company based in Cartersville.</p>
        </div>
      </div>
    </section>

    <section class="section" aria-labelledby="who-title">
      <div class="container split">
        <div class="split-text reveal">
          <p class="eyebrow">WHO WE ARE</p>
          <h2 id="who-title">From clearing to final surface.</h2>
          <p>Established in May 2024, Etowah Construction Group delivers site development, underground utility, roadway, and pump-station construction for municipal, commercial, and residential clients.</p>
          <p>Our work includes full-site packages, large-diameter water infrastructure, pump-station rehabilitation, DOT-road paving, deep excavation, bypass operations, and critical public-roadway tie-ins.</p>
          <dl class="credentials">
            <div><dt>Licensed Utility Contractor</dt><dd>State of Georgia</dd></div>
            <div><dt>License No.</dt><dd><?= e(setting('license_number')) ?></dd></div>
            <div><dt>Based in</dt><dd>Cartersville, GA</dd></div>
          </dl>
        </div>
        <div class="split-media reveal"><?= slot_image('about', 'team') ?></div>
      </div>
    </section>

    <section class="section section--alt" aria-labelledby="why-title">
      <div class="container">
        <div class="section-head reveal">
          <p class="eyebrow">WHY CLIENTS CHOOSE US</p>
          <h2 id="why-title">Built on capability.</h2>
        </div>
        <div class="card-grid card-grid--3" data-stagger>
          <div class="cap-card">
            <span class="why-num">01</span>
            <h3>Complete civil scope.</h3>
            <p>Experience from clearing and mass grading through utilities, curbing, and asphalt.</p>
          </div>
          <div class="cap-card">
            <span class="why-num">02</span>
            <h3>Complex utility execution.</h3>
            <p>Pump stations, large-diameter ductile iron, bypass operations, deep wet wells, and critical tie-ins.</p>
          </div>
          <div class="cap-card">
            <span class="why-num">03</span>
            <h3>Cross-market experience.</h3>
            <p>Municipal, commercial, residential, and roadway projects delivered since launch.</p>
          </div>
        </div>
      </div>
    </section>

    <section class="section" aria-labelledby="core-title">
      <div class="container">
        <div class="section-head reveal">
          <p class="eyebrow">CORE CAPABILITIES</p>
          <h2 id="core-title">Representative scope.</h2>
        </div>
        <ul class="spec-list reveal">
          <li>
            <a class="spec-row" href="/site-development">
              <h3 class="spec-name">Site Development</h3>
              <span class="spec-scope">Clearing, grading, mass earthwork, site preparation, and retaining walls</span>
              <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M9 6l6 6-6 6"/></svg>
            </a>
          </li>
          <li>
            <a class="spec-row" href="/underground-utilities">
              <h3 class="spec-name">Underground Utilities</h3>
              <span class="spec-scope">Storm, sanitary sewer, and water pipe; 24-inch restrained-joint ductile iron</span>
              <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M9 6l6 6-6 6"/></svg>
            </a>
          </li>
          <li>
            <a class="spec-row" href="/pump-stations">
              <h3 class="spec-name">Pump Stations</h3>
              <span class="spec-scope">New construction and rehabilitation; bypass pumping, wet wells, pumps, process piping, and electrical equipment</span>
              <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M9 6l6 6-6 6"/></svg>
            </a>
          </li>
          <li>
            <a class="spec-row" href="/roadways-stormwater">
              <h3 class="spec-name">Roadways &amp; Finishes</h3>
              <span class="spec-scope">Curb and gutter, asphalt paving, DOT-road work, and public-roadway tie-ins</span>
              <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M9 6l6 6-6 6"/></svg>
            </a>
          </li>
          <li>
            <a class="spec-row" href="/roadways-stormwater">
              <h3 class="spec-name">Stormwater &amp; Specialty Work</h3>
              <span class="spec-scope">Underground retention systems, deep specialty excavations, demolition, water vaults, water meters, and critical utility connections</span>
              <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M9 6l6 6-6 6"/></svg>
            </a>
          </li>
        </ul>
      </div>
    </section>

    <section class="markets on-dark" aria-labelledby="markets-title">
      <div class="container">
        <h2 class="eyebrow eyebrow--dark" id="markets-title">MARKETS SERVED</h2>
        <ul class="markets-list" data-stagger>
          <li><a class="market-link" href="/markets#municipal"><svg class="icon" viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M24 5S11 19 11 28.5a13 13 0 0 0 26 0C37 19 24 5 24 5z"/><path d="M18 30a6 6 0 0 0 6 6"/></svg><span>Municipal water and wastewater</span></a></li>
          <li><a class="market-link" href="/markets#commercial"><svg class="icon" viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M5 42h38"/><path d="M8 42V20h32v22"/><path d="M5 20l4-11h30l4 11"/><path d="M20 42V30h8v12"/><path d="M12 25h5"/><path d="M31 25h5"/></svg><span>Commercial development</span></a></li>
          <li><a class="market-link" href="/markets#residential"><svg class="icon" viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M5 22L24 7l19 15"/><path d="M10 18v24h28V18"/><path d="M20 42V30h8v12"/></svg><span>Residential development</span></a></li>
          <li><a class="market-link" href="/markets#roadway"><svg class="icon" viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M17 6L6 42"/><path d="M31 6l11 36"/><path d="M24 8v5"/><path d="M24 19v6"/><path d="M24 31v7"/></svg><span>Roadway infrastructure</span></a></li>
        </ul>
        <p class="markets-more"><a class="text-link text-link--light" href="/markets">Explore our markets <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M5 12h14"/><path d="M13 6l6 6-6 6"/></svg></a></p>
      </div>
    </section>
  </main>
<?php layout_end(); ?>
