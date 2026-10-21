<?php
require __DIR__ . '/app/bootstrap.php';

$projects = projects_published();

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

<?php if ($projects): ?>
    <section class="section" aria-labelledby="list-title">
      <div class="container">
        <div class="section-head reveal">
          <p class="eyebrow">OUR PROJECTS</p>
          <h2 id="list-title">Proven in the field.</h2>
        </div>
        <div class="project-grid project-grid--3" data-stagger>
<?php foreach ($projects as $p) echo project_card(project_page_card($p, 'project-card')); ?>
        </div>
      </div>
    </section>
<?php endif; ?>
  </main>
<?php layout_end(); ?>
