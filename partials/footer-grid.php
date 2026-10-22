<?php
$phone = setting('phone');
$email = setting('email');
$license = setting('license_text');
$licenseNo = setting('license_number');
?>
      <div class="footer-grid">
        <div class="footer-col footer-col--brand">
          <a class="footer-brand" href="/">
            <img src="/assets/images/logo/logo-horizontal.png" alt="Etowah Construction Group" width="739" height="128" loading="lazy" decoding="async">
          </a>
          <p class="footer-text"><?= e($license) ?>. License No. <?= e($licenseNo) ?>.</p>
          <p class="footer-text"><?= e(setting('markets_line')) ?></p>
        </div>
        <nav class="footer-col" aria-labelledby="footer-menu-heading">
          <h2 class="footer-heading" id="footer-menu-heading">Menu</h2>
          <ul class="footer-links">
            <li><a href="/">Home</a></li>
            <li><a href="/markets">Markets</a></li>
            <li><a href="/projects">Projects</a></li>
            <li><a href="/about">About</a></li>
            <li><a href="/careers">Careers</a></li>
            <li><a href="/contact">Contact</a></li>
          </ul>
        </nav>
        <nav class="footer-col" aria-labelledby="footer-services-heading">
          <h2 class="footer-heading" id="footer-services-heading">Services</h2>
          <ul class="footer-links">
<?php foreach (SERVICES as $key => $label): ?>
            <li><a href="/<?= $key ?>"><?= e($label) ?></a></li>
<?php endforeach; ?>
          </ul>
        </nav>
        <div class="footer-col">
          <h2 class="footer-heading">Contact</h2>
          <ul class="footer-contact">
<?php if (setting('contact_name') !== ''): ?>
            <li><?= icon('user') ?><span><?= e(setting('contact_name')) ?></span></li>
<?php endif; ?>
            <li><?= icon('phone') ?><a href="<?= e(tel_href($phone)) ?>"><?= e($phone) ?></a></li>
            <li><?= icon('mail') ?><a href="mailto:<?= e($email) ?>"><?= e($email) ?></a></li>
            <li><?= icon('pin') ?><span><?= e(setting('city_state')) ?></span></li>
          </ul>
        </div>
      </div>

      <p class="footer-legal">© <?= date('Y') ?> <?= e(setting('company_name')) ?> · <?= e($license) ?> · License No. <?= e($licenseNo) ?></p>
