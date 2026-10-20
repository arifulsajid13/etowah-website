<?php
if (!defined('ROOT')) {
    require __DIR__ . '/app/bootstrap.php';
}

http_response_code(404);

layout_start('home', [
    'title'       => 'Page Not Found | Etowah Construction Group',
    'description' => 'The page you were looking for could not be found.',
    'active'      => '',
    'noindex'     => true,
]);
?>

  <main id="main" tabindex="-1">
    <section class="hero hero--short">
      <?= hero_image('contact') ?>
      <div class="container hero-content">
        <div class="hero-inner">
          <p class="hero-eyebrow">ERROR 404</p>
          <h1>Page not found</h1>
          <p class="hero-sub">The page you were looking for has moved or no longer exists.</p>
          <div class="hero-actions">
            <a class="btn btn-primary" href="/">Back to Home <?= icon('chevron-right') ?></a>
          </div>
        </div>
      </div>
    </section>
  </main>
<?php layout_end(); ?>
