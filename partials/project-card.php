<?php
$element = $card['element'] ?? 'a';
$attrs = ' class="' . e($card['class'] ?? 'project-card') . '"';
if (!empty($card['href'])) {
    $attrs .= ' href="' . e($card['href']) . '"';
}
if (!empty($card['id'])) {
    $attrs .= ' id="' . e($card['id']) . '"';
}
$service = $card['tag_service'] ?? '';
?>
          <<?= $element . $attrs ?>>
            <div class="project-media"><?= image_html($card['image']) ?></div>
            <div class="project-body">
              <p class="meta"><?= e(upper($card['meta'])) ?></p>
              <h3><?= e($card['title']) ?></h3>
              <p><?= e($card['text']) ?></p>
<?php if ($service !== '' && isset(SERVICES[$service])): ?>
              <a class="tag" href="/<?= $service ?>"><?= e(SERVICES[$service]) ?> <?= icon('chevron-right') ?></a>
<?php endif; ?>
<?php if (!empty($card['link'])): ?>
              <a class="text-link project-link" href="<?= e($card['link']) ?>">View project<span class="visually-hidden">: <?= e($card['title']) ?></span> <?= icon('arrow-right') ?></a>
<?php endif; ?>
            </div>
          </<?= $element ?>>
