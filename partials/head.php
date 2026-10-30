<!DOCTYPE html>
<html lang="en" class="no-js">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($title) ?></title>
  <meta name="description" content="<?= e($description) ?>">
<?php if ($noindex || search_hidden()): ?>
  <meta name="robots" content="noindex, nofollow">
<?php endif; ?>
<?php if (!$noindex): ?>
  <link rel="canonical" href="<?= e($canonical) ?>">
<?php endif; ?>
  <meta property="og:type" content="website">
  <meta property="og:site_name" content="Etowah Construction Group">
  <meta property="og:title" content="<?= e($title) ?>">
  <meta property="og:description" content="<?= e($description) ?>">
  <meta property="og:url" content="<?= e($canonical) ?>">
<?php if ($ogImage !== ''): ?>
  <meta property="og:image" content="<?= e($ogImage) ?>">
<?php endif; ?>
<?php if ($verification !== ''): ?>
  <meta name="google-site-verification" content="<?= e($verification) ?>">
<?php endif; ?>
  <meta name="theme-color" content="#262F36">
  <link rel="icon" type="image/png" href="/assets/images/logo/logo-icon.png">
  <link rel="apple-touch-icon" href="/assets/images/logo/logo-icon.png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Archivo:wght@700;800&family=Inter:wght@400;500;600&display=swap">
  <link rel="stylesheet" href="<?= e(asset('css/style.css')) ?>">
  <script src="<?= e(asset('js/main.js')) ?>" defer></script>
<?php foreach ($jsonld as $data): ?>
  <?= jsonld_script($data) ?>

<?php endforeach; ?>
</head>
<body>
  <a class="skip-link" href="#main">Skip to content</a>
