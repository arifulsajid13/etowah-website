<?php
require __DIR__ . '/app/bootstrap.php';

$home = projects_on_home();

layout_start('home');
?>

  <main id="main" tabindex="-1">
    <section class="hero hero--home">
      <?= hero_image('home') ?>
      <div class="container hero-content">
        <div class="hero-inner">
          <p class="hero-eyebrow">CARTERSVILLE, GEORGIA</p>
          <h1>Built from<br> the ground up.</h1>
          <p class="hero-sub">Site development, underground utilities, pump stations, and roadway construction.</p>
          <div class="hero-actions">
            <a class="btn btn-primary" href="/contact">Discuss Your Project <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M9 6l6 6-6 6"/></svg></a>
            <a class="btn btn-secondary" href="/projects">Explore Our Work <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M9 6l6 6-6 6"/></svg></a>
          </div>
        </div>
      </div>
    </section>

    <section class="stats" aria-label="Company highlights">
      <div class="container">
        <ul class="stats-list">
<?php foreach (['stat-license', 'stat-road', 'stat-earth', 'stat-depth'] as $i => $statIcon): ?>
          <li class="stat"><?= icon($statIcon) ?><div><span class="stat-label"><?= e(setting('stat' . ($i + 1) . '_label')) ?></span><span class="stat-value"><?= e(setting('stat' . ($i + 1) . '_value')) ?></span></div></li>
<?php endforeach; ?>
        </ul>
      </div>
    </section>

    <section class="section section--alt" aria-labelledby="capabilities-title">
      <div class="container">
        <div class="section-head reveal">
          <p class="eyebrow">OUR CAPABILITIES</p>
          <h2 id="capabilities-title">Complete civil scope. One capable team.</h2>
        </div>
        <div class="card-grid" data-stagger>
          <a class="cap-card" href="/site-development">
            <svg class="icon" viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><rect x="4" y="34" width="26" height="8" rx="4"/><path d="M8 34v-9h5l3-6h7v15"/><path d="M17 19v6h6"/><path d="M23 26l10-15 9 7"/><path d="M42 18l2 10h-7z"/></svg>
            <h3>Site Development</h3>
            <p>Clearing, grading and mass earthwork.</p>
          </a>
          <a class="cap-card" href="/underground-utilities">
            <svg class="icon" viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><ellipse cx="10" cy="24" rx="5" ry="10"/><ellipse cx="10" cy="24" rx="2" ry="4.5"/><path d="M10 14h22"/><path d="M10 34h22"/><rect x="32" y="11" width="5" height="26" rx="1"/><path d="M37 17h7"/><path d="M37 31h7"/></svg>
            <h3>Underground Utilities</h3>
            <p>Water, sewer and storm pipe.</p>
          </a>
          <a class="cap-card" href="/pump-stations">
            <svg class="icon" viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><rect x="5" y="20" width="17" height="14" rx="1.5"/><path d="M10 20v14"/><path d="M15 20v14"/><path d="M22 27h3"/><circle cx="32" cy="27" r="7"/><circle cx="32" cy="27" r="2"/><path d="M32 20v-7h10"/><path d="M42 9v8"/><path d="M4 41h40"/><path d="M9 34v7"/><path d="M32 34v7"/></svg>
            <h3>Pump Stations</h3>
            <p>New construction, rehabilitation and bypass pumping.</p>
          </a>
          <a class="cap-card" href="/roadways-stormwater">
            <svg class="icon" viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M17 6L6 42"/><path d="M31 6l11 36"/><path d="M24 8v5"/><path d="M24 19v6"/><path d="M24 31v7"/></svg>
            <h3>Roadways &amp; Stormwater</h3>
            <p>Asphalt, curb and gutter and underground retention.</p>
          </a>
        </div>
      </div>
    </section>

<?php if ($home): ?>
    <section class="section" aria-labelledby="projects-title">
      <div class="container">
        <div class="section-head reveal">
          <p class="eyebrow">SELECTED PROJECTS</p>
          <h2 id="projects-title">Experience where it matters.</h2>
        </div>
<?php if (isset($home['card1']) || isset($home['card2'])): ?>
        <div class="project-grid" data-stagger>
<?php foreach (['card1', 'card2'] as $slot): if (!isset($home[$slot])) continue; $p = $home[$slot]; ?>
<?= project_card([
    'class' => 'project-card project-card--plain',
    'href'  => project_url($p),
    'image' => project_image($p),
    'meta'  => project_place($p),
    'title' => $p['title'],
    'text'  => $p['summary'],
]) ?>
<?php endforeach; ?>
        </div>
<?php endif; ?>
<?php if (isset($home['banner'])): $p = $home['banner']; ?>
        <a class="project-banner reveal" href="<?= e(project_url($p)) ?>">
          <div class="banner-media"><?= image_html(project_image($p)) ?></div>
          <div class="banner-body">
            <div>
              <p class="meta"><?= e(upper(project_place($p))) ?></p>
              <h3><?= e($p['title']) ?></h3>
            </div>
            <?= icon('chevron-right') ?>

          </div>
        </a>
<?php endif; ?>
      </div>
    </section>
<?php endif; ?>
  </main>
<?php layout_end(); ?>
