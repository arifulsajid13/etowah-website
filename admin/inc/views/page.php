<?php
defined('ETOWAH_ADMIN') || exit;
$key = get_str('key');
$isGlobal = $key === 'global';
if (!$isGlobal && !isset(PAGES[$key])) {
    redirect(admin_url('pages'));
}
$label = $isGlobal ? 'Site-wide images' : PAGES[$key][1];
$path = $isGlobal ? '/' : PAGES[$key][0];
$errors = [];

if (is_post()) {
    require_post();
    $action = post_str('action');

    if ($action === 'meta' && !$isGlobal) {
        $title = post_str('meta_title');
        $description = post_str('meta_description');
        if ($title === '' || str_length($title) > 200) {
            $errors['meta_title'] = 'Enter a page title (it can be up to 200 characters, but 60 or fewer is best).';
        }
        if ($description === '' || str_length($description) > 400) {
            $errors['meta_description'] = 'Enter a description (160 characters or fewer is best).';
        }
        if (!$errors) {
            db_run('UPDATE pages SET meta_title = ?, meta_description = ? WHERE page_key = ?', [$title, $description, $key]);
            log_activity('Updated SEO for ' . $label, 'page');
            flash('success', 'Search settings saved.');
            redirect(admin_url('page', ['key' => $key]));
        }
    }

    if ($action === 'image') {
        $slot = db_one('SELECT * FROM page_images WHERE id = ? AND page_key = ?', [post_int('image_id'), $key]);
        if ($slot) {
            $alt = mb_substr(post_str('alt'), 0, 255);
            $maxWidth = in_array($slot['slot'], ['hero', 'cta-bg'], true) ? 1920 : 1600;
            $upload = admin_image_upload('image_' . $slot['id'], 'pages', $maxWidth);
            if (isset($upload['error'])) {
                redirect(admin_url('page', ['key' => $key]) . '#slot-' . $slot['id']);
            }
            if ($upload) {
                db_run('UPDATE page_images SET path = ?, width = ?, height = ?, alt = ?, is_placeholder = 0 WHERE id = ?', [$upload['path'], $upload['width'], $upload['height'], $alt, $slot['id']]);
                image_delete_upload($slot['path']);
                log_activity('Replaced ' . $label . ' image: ' . $slot['label'], 'page_image', (int) $slot['id']);
                flash('success', 'Photo replaced. It is live on the website now.');
            } else {
                db_run('UPDATE page_images SET alt = ? WHERE id = ?', [$alt, $slot['id']]);
                log_activity('Updated ' . $label . ' image description', 'page_image', (int) $slot['id']);
                flash('success', 'Photo description saved.');
            }
        }
        redirect(admin_url('page', ['key' => $key]));
    }
}

$meta = $isGlobal ? null : page_meta($key);
if ($meta && $errors) {
    $meta['meta_title'] = post_str('meta_title');
    $meta['meta_description'] = post_str('meta_description');
}
$slots = db_all('SELECT * FROM page_images WHERE page_key = ? ORDER BY slot = \'hero\' DESC, id', [$key]);
$err = fn(string $k) => isset($errors[$k]) ? '<p class="field-error">' . e($errors[$k]) . '</p>' : '';

admin_start($label . ' — Pages & SEO', 'pages');
?>
        <p class="back"><a href="<?= e(admin_url('pages')) ?>">← All pages</a><?php if (!$isGlobal): ?> · <a href="<?= e($path) ?>" target="_blank" rel="noopener">View page <?= admin_icon('external') ?></a><?php endif; ?></p>
        <p class="notice notice--info">Page text is not editable here. For wording changes, contact your web developer.</p>
<?php if ($meta): ?>
        <section class="panel">
          <div class="panel-head"><h2>Search engine listing</h2></div>
          <div class="detail-grid detail-grid--even">
            <form method="post" class="stack">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="meta">
              <div class="field<?= isset($errors['meta_title']) ? ' has-error' : '' ?>">
                <label for="m-title">Page title</label>
                <input id="m-title" name="meta_title" type="text" maxlength="200" required value="<?= e($meta['meta_title']) ?>" data-counter="60" data-preview="title">
                <p class="hint">Shown as the blue link in Google and on the browser tab. Aim for 60 characters or fewer. <span class="counter" data-counter-for="m-title"></span></p>
                <?= $err('meta_title') ?>
              </div>
              <div class="field<?= isset($errors['meta_description']) ? ' has-error' : '' ?>">
                <label for="m-description">Description</label>
                <textarea id="m-description" name="meta_description" rows="4" maxlength="400" required data-counter="160" data-preview="description"><?= e($meta['meta_description']) ?></textarea>
                <p class="hint">The short summary under the link in Google. Aim for 160 characters or fewer. <span class="counter" data-counter-for="m-description"></span></p>
                <?= $err('meta_description') ?>
              </div>
              <button class="btn btn--primary" type="submit">Save search settings</button>
            </form>
            <div>
              <p class="label">Google preview</p>
              <div class="serp" aria-label="Search result preview">
                <span class="serp-url"><?= e(preg_replace('#^https?://#', '', rtrim(site_url($path), '/'))) ?></span>
                <span class="serp-title" data-preview-title><?= e($meta['meta_title']) ?></span>
                <span class="serp-desc" data-preview-description><?= e($meta['meta_description']) ?></span>
              </div>
              <p class="hint">Google may shorten long titles and descriptions, or rewrite them.</p>
            </div>
          </div>
        </section>
<?php endif; ?>

        <section class="panel">
          <div class="panel-head"><h2>Photos on this page</h2></div>
<?php if ($slots): ?>
          <div class="slot-list">
<?php foreach ($slots as $slot): ?>
            <form method="post" enctype="multipart/form-data" class="slot" id="slot-<?= (int) $slot['id'] ?>">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="image">
              <input type="hidden" name="image_id" value="<?= (int) $slot['id'] ?>">
              <div class="slot-media">
                <img src="<?= e(media_url($slot['path'])) ?>" alt="" width="320" height="180" loading="lazy">
              </div>
              <div class="slot-body">
                <h3><?= e($slot['label']) ?> <?php if ($slot['is_placeholder']): ?><span class="badge badge--warning">Placeholder</span><?php endif; ?></h3>
                <p class="hint">Current size: <?= (int) $slot['width'] ?> × <?= (int) $slot['height'] ?> px. <?= $slot['slot'] === 'hero' || $slot['slot'] === 'cta-bg' ? 'Use a wide landscape photo, ideally 1920 px wide.' : 'Use a landscape photo at least 1200 px wide.' ?></p>
                <div class="field">
                  <label for="img-<?= (int) $slot['id'] ?>">Replace image</label>
                  <input id="img-<?= (int) $slot['id'] ?>" name="image_<?= (int) $slot['id'] ?>" type="file" accept="image/jpeg,image/png,image/webp">
                </div>
<?php if ($slot['slot'] !== 'cta-bg'): ?>
                <div class="field">
                  <label for="alt-<?= (int) $slot['id'] ?>">Photo description (alt text)</label>
                  <input id="alt-<?= (int) $slot['id'] ?>" name="alt" type="text" maxlength="255" value="<?= e($slot['alt']) ?>">
                </div>
<?php else: ?>
                <input type="hidden" name="alt" value="">
                <p class="hint">This background is decorative, so it needs no description. A dark overlay is added on top automatically.</p>
<?php endif; ?>
                <button class="btn btn--primary btn--sm" type="submit">Save photo</button>
              </div>
            </form>
<?php endforeach; ?>
          </div>
<?php else: ?>
          <?= empty_state('This page has no replaceable photos') ?>
<?php endif; ?>
        </section>
<?php admin_end(); ?>
