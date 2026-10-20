
  <footer class="site-footer"<?= $footerStyle ?>>
    <div class="container">
      <div class="cta-row">
        <h2 class="cta-title"><?= e($ctaTitle) ?></h2>
        <span class="cta-divider" aria-hidden="true"></span>
        <ul class="cta-contact">
          <li><?= icon('user') ?><span><?= e(setting('contact_name')) ?></span></li>
          <li><?= icon('phone') ?><a href="<?= e(tel_href(setting('phone'))) ?>"><?= e(setting('phone')) ?></a></li>
          <li><?= icon('mail') ?><a href="mailto:<?= e(setting('email')) ?>"><?= e(setting('email')) ?></a></li>
        </ul>
        <a class="btn btn-primary" href="/contact"><?= e($ctaButton) ?> <?= icon('chevron-right') ?></a>
      </div>

<?php require ROOT . '/partials/footer-grid.php'; ?>
    </div>
  </footer>
</body>
</html>
