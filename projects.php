<?php
require __DIR__ . '/app/bootstrap.php';

$current = projects_published('current');
$completed = projects_published('completed');

layout_start('projects');
?>

  <main id="main" tabindex="-1">
    <section class="hero">
      <?= hero_image('projects') ?>
      <div class="container hero-content">
        <div class="hero-inner">
          <nav class="breadcrumb" aria-label="Breadcrumb"><ol><li><a href="/">Home</a></li><li aria-current="page">Projects</li></ol></nav>
          <h1>Our Projects</h1>
          <p class="hero-sub">Municipal, commercial, residential, and roadway work across Georgia and Tennessee.</p>
        </div>
      </div>
    </section>

<?php if ($current): ?>
    <section class="section" aria-labelledby="current-title">
      <div class="container">
        <div class="section-head reveal">
          <p class="eyebrow">CURRENT PROJECTS</p>
          <h2 id="current-title">Underway now.</h2>
        </div>
        <div class="project-grid" data-stagger>
<?php foreach ($current as $p) echo project_card(project_page_card($p, 'project-card project-card--large')); ?>
        </div>
      </div>
    </section>

<?php endif; ?>
<?php if ($completed): ?>
    <section class="section section--alt" aria-labelledby="completed-title">
      <div class="container">
        <div class="section-head reveal">
          <p class="eyebrow">COMPLETED PROJECTS</p>
          <h2 id="completed-title">Proven in the field.</h2>
        </div>
        <div class="project-grid project-grid--3" data-stagger>
<?php foreach ($completed as $p) echo project_card(project_page_card($p, 'project-card')); ?>
        </div>
      </div>
    </section>
<?php endif; ?>
  </main>
<?php layout_end(); ?>
