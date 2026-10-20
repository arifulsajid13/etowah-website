<?php
require __DIR__ . '/app/bootstrap.php';

layout_start('markets');
?>

  <main id="main" tabindex="-1">
    <section class="hero">
      <?= hero_image('markets') ?>
      <div class="container hero-content">
        <div class="hero-inner">
          <nav class="breadcrumb" aria-label="Breadcrumb"><ol><li><a href="/">Home</a></li><li aria-current="page">Markets</li></ol></nav>
          <h1>Markets We Serve</h1>
          <p class="hero-sub">Municipal, commercial, residential, and roadway projects across Georgia and Tennessee.</p>
        </div>
      </div>
    </section>

    <section class="section" aria-labelledby="markets-intro-title">
      <div class="container">
        <div class="intro-center reveal">
          <p class="eyebrow">WHO WE BUILD FOR</p>
          <h2 id="markets-intro-title">Different clients. One capable team.</h2>
          <p>Etowah Construction Group has completed municipal, commercial, residential, and roadway projects since launch. Whatever the market, the approach is the same: complete civil scope, careful execution, and critical connections done right.</p>
        </div>
        <nav class="jump-strip reveal" aria-label="Jump to a market">
          <ul>
            <li><a class="jump-tab" href="#municipal">Municipal Water &amp; Wastewater</a></li>
            <li><a class="jump-tab" href="#commercial">Commercial Development</a></li>
            <li><a class="jump-tab" href="#residential">Residential Development</a></li>
            <li><a class="jump-tab" href="#roadway">Roadway Infrastructure</a></li>
          </ul>
        </nav>
      </div>
    </section>

    <section class="section market" id="municipal" aria-labelledby="municipal-title">
      <div class="container market-row">
        <div class="market-media reveal"><?= slot_image('markets', 'municipal') ?></div>
        <div class="market-text reveal">
          <p class="eyebrow">MUNICIPAL</p>
          <h2 id="municipal-title">Water &amp; wastewater infrastructure.</h2>
          <p>We build and rehabilitate pump stations and underground water and sewer systems for public agencies, including deep specialty excavation and utility tie-ins within public roadways.</p>
          <ul class="checklist">
            <li><span class="check-badge"><svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg></span>New pump station construction and rehabilitation</li>
            <li><span class="check-badge"><svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg></span>Bypass pumping to keep systems operating</li>
            <li><span class="check-badge"><svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg></span>24-inch restrained-joint ductile iron</li>
          </ul>
          <dl class="related">
<?php if ($related = projects_for_market('municipal')): ?>
            <div>
              <dt>Related projects</dt>
              <dd>
<?php foreach ($related as $p): ?>
              <a class="related-link" href="/projects#<?= e($p['slug']) ?>"><?= e($p['title']) ?></a>
<?php endforeach; ?>
              </dd>
            </div>
<?php endif; ?>
            <div>
              <dt>Related services</dt>
              <dd>
              <a class="text-link" href="/pump-stations">Pump Stations <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M5 12h14"/><path d="M13 6l6 6-6 6"/></svg></a>
              <a class="text-link" href="/underground-utilities">Underground Utilities <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M5 12h14"/><path d="M13 6l6 6-6 6"/></svg></a>
              </dd>
            </div>
          </dl>
        </div>
      </div>
    </section>

    <section class="section market section--alt" id="commercial" aria-labelledby="commercial-title">
      <div class="container market-row market-row--flip">
        <div class="market-media reveal"><?= slot_image('markets', 'commercial') ?></div>
        <div class="market-text reveal">
          <p class="eyebrow">COMMERCIAL</p>
          <h2 id="commercial-title">Complete sitework for commercial development.</h2>
          <p>From clearing and grading through utilities, retaining walls, stormwater retention, curbing, and asphalt paving, we deliver the full civil scope for commercial sites.</p>
          <ul class="checklist">
            <li><span class="check-badge"><svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg></span>Full-site packages from clearing to paving</li>
            <li><span class="check-badge"><svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg></span>Sewer, water, and storm utilities</li>
            <li><span class="check-badge"><svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg></span>Underground stormwater retention</li>
          </ul>
          <dl class="related">
<?php if ($related = projects_for_market('commercial')): ?>
            <div>
              <dt>Related projects</dt>
              <dd>
<?php foreach ($related as $p): ?>
              <a class="related-link" href="/projects#<?= e($p['slug']) ?>"><?= e($p['title']) ?></a>
<?php endforeach; ?>
              </dd>
            </div>
<?php endif; ?>
            <div>
              <dt>Related services</dt>
              <dd>
              <a class="text-link" href="/site-development">Site Development <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M5 12h14"/><path d="M13 6l6 6-6 6"/></svg></a>
              <a class="text-link" href="/underground-utilities">Underground Utilities <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M5 12h14"/><path d="M13 6l6 6-6 6"/></svg></a>
              <a class="text-link" href="/roadways-stormwater">Roadways &amp; Stormwater <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M5 12h14"/><path d="M13 6l6 6-6 6"/></svg></a>
              </dd>
            </div>
          </dl>
        </div>
      </div>
    </section>

    <section class="section market" id="residential" aria-labelledby="residential-title">
      <div class="container market-row">
        <div class="market-media reveal"><?= slot_image('markets', 'residential') ?></div>
        <div class="market-text reveal">
          <p class="eyebrow">RESIDENTIAL</p>
          <h2 id="residential-title">Site development for neighborhoods.</h2>
          <p>We prepare residential communities from raw land to finished streets: clearing, grading, water distribution, storm drainage, curb and gutter, and asphalt paving.</p>
          <ul class="checklist">
            <li><span class="check-badge"><svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg></span>Two-phase single-family neighborhood development</li>
            <li><span class="check-badge"><svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg></span>Approximately 85,000 CY of mass dirt movement</li>
            <li><span class="check-badge"><svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg></span>Water distribution and storm drainage</li>
          </ul>
          <dl class="related">
<?php if ($related = projects_for_market('residential')): ?>
            <div>
              <dt>Related projects</dt>
              <dd>
<?php foreach ($related as $p): ?>
              <a class="related-link" href="/projects#<?= e($p['slug']) ?>"><?= e($p['title']) ?></a>
<?php endforeach; ?>
              </dd>
            </div>
<?php endif; ?>
            <div>
              <dt>Related services</dt>
              <dd>
              <a class="text-link" href="/site-development">Site Development <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M5 12h14"/><path d="M13 6l6 6-6 6"/></svg></a>
              <a class="text-link" href="/underground-utilities">Underground Utilities <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M5 12h14"/><path d="M13 6l6 6-6 6"/></svg></a>
              </dd>
            </div>
          </dl>
        </div>
      </div>
    </section>

    <section class="section market section--alt" id="roadway" aria-labelledby="roadway-title">
      <div class="container market-row market-row--flip">
        <div class="market-media reveal"><?= slot_image('markets', 'roadway') ?></div>
        <div class="market-text reveal">
          <p class="eyebrow">ROADWAY</p>
          <h2 id="roadway-title">Roadway infrastructure.</h2>
          <p>We complete asphalt paving and DOT-road work, along with curb and gutter and utility tie-ins within public roadways.</p>
          <ul class="checklist">
            <li><span class="check-badge"><svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg></span>5.5 miles of asphalt across multiple DOT roads</li>
            <li><span class="check-badge"><svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg></span>Curb and gutter</li>
            <li><span class="check-badge"><svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg></span>Public-roadway utility tie-ins</li>
          </ul>
          <dl class="related">
<?php if ($related = projects_for_market('roadway')): ?>
            <div>
              <dt>Related projects</dt>
              <dd>
<?php foreach ($related as $p): ?>
              <a class="related-link" href="/projects#<?= e($p['slug']) ?>"><?= e($p['title']) ?></a>
<?php endforeach; ?>
              </dd>
            </div>
<?php endif; ?>
            <div>
              <dt>Related services</dt>
              <dd>
              <a class="text-link" href="/roadways-stormwater">Roadways &amp; Stormwater <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M5 12h14"/><path d="M13 6l6 6-6 6"/></svg></a>
              </dd>
            </div>
          </dl>
        </div>
      </div>
    </section>
  </main>
<?php layout_end(); ?>
