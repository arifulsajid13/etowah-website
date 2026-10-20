<?php
$isService = isset(SERVICES[$active]);
$current = fn(string $key) => $active === $key ? ' aria-current="page"' : '';
$navItems = ['markets' => 'Markets', 'projects' => 'Projects', 'about' => 'About', 'careers' => 'Careers', 'contact' => 'Contact'];
?>

  <header class="site-header">
    <div class="container header-inner">
      <a class="brand" href="/">
        <img src="/assets/images/logo/logo-horizontal.png" alt="Etowah Construction Group" width="739" height="128">
      </a>

      <button class="nav-toggle" type="button" aria-expanded="false" aria-controls="site-nav" aria-label="Open menu">
        <?= icon('menu', 'icon icon-menu') ?>

        <?= icon('close', 'icon icon-close') ?>

      </button>

      <nav class="site-nav" id="site-nav" aria-label="Main">
        <ul class="nav-list">
          <li class="nav-item has-dropdown">
            <button class="nav-link dropdown-toggle<?= $isService ? ' is-active' : '' ?>" type="button" aria-expanded="false" aria-controls="services-menu">
              <span class="nav-label">Services</span>
              <?= icon('chevron-down') ?>

            </button>
            <ul class="dropdown" id="services-menu">
<?php foreach (SERVICES as $key => $label): ?>
            <li><a href="/<?= $key ?>"<?= $current($key) ?>><?= e($label) ?></a></li>
<?php endforeach; ?>
            </ul>
          </li>
<?php foreach ($navItems as $key => $label): ?>
          <li class="nav-item"><a class="nav-link" href="/<?= $key ?>"<?= $current($key) ?>><span class="nav-label"><?= $label ?></span></a></li>
<?php endforeach; ?>
        </ul>
        <a class="btn btn-primary nav-cta" href="/contact">Discuss Your Project <?= icon('chevron-right') ?></a>
      </nav>
    </div>
  </header>
