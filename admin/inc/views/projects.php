<?php
defined('ETOWAH_ADMIN') || exit;
if (is_post()) {
    require_post();
    $id = post_int('id');
    $project = db_one('SELECT id, title, published FROM projects WHERE id = ?', [$id]);
    if ($project && post_str('action') === 'toggle') {
        $published = $project['published'] ? 0 : 1;
        db_run('UPDATE projects SET published = ? WHERE id = ?', [$published, $id]);
        log_activity(($published ? 'Published' : 'Unpublished') . ' project ' . mb_substr($project['title'], 0, 40), 'project', $id);
        flash('success', '“' . $project['title'] . '” is now ' . ($published ? 'shown on' : 'hidden from') . ' the website.');
    } elseif ($project && post_str('action') === 'move') {
        // Renumber in the current order, then swap with the neighbour.
        $ids = array_map('intval', array_column(db_all('SELECT id FROM projects ORDER BY sort_order, id'), 'id'));
        $pos = array_search($id, $ids, true);
        $swap = post_str('dir') === 'up' ? $pos - 1 : $pos + 1;
        if ($pos !== false && isset($ids[$swap])) {
            [$ids[$pos], $ids[$swap]] = [$ids[$swap], $ids[$pos]];
            $stmt = db()->prepare('UPDATE projects SET sort_order = ? WHERE id = ?');
            foreach ($ids as $i => $pid) {
                $stmt->execute([($i + 1) * 10, $pid]);
            }
            log_activity('Reordered projects', 'project', $id);
            flash('success', 'Order updated.');
        }
    }
    redirect(admin_url('projects', ['photos' => get_str('photos'), 'seo' => get_str('seo')]));
}

$placeholderOnly = get_str('photos') === 'placeholder';
$seoOnly = get_str('seo') === 'missing';
$filtered = $placeholderOnly || $seoOnly;
$rows = db_all('SELECT id, title, slug, status, market, home_position, published, image_path, image_alt, is_placeholder_image,
                       ' . PROJECT_SEO_MISSING . ' AS seo_missing FROM projects'
    . ($placeholderOnly ? ' WHERE is_placeholder_image = 1' : ($seoOnly ? ' WHERE ' . PROJECT_SEO_MISSING : '')) . ' ORDER BY sort_order, id');
$last = count($rows) - 1;

admin_start('Projects', 'projects');
?>
        <div class="page-actions">
          <p class="lead">Projects appear on the Projects page, the matching service pages, the Markets page, and (if chosen) the homepage. The order here is the order on the website.</p>
          <div class="button-row">
            <a class="btn" href="<?= e(admin_url('import')) ?>">Import projects</a>
            <a class="btn btn--primary" href="<?= e(admin_url('project', ['id' => 'new'])) ?>">+ Add project</a>
          </div>
        </div>
<?php if ($placeholderOnly): ?>
        <p class="notice notice--info">Showing only projects that still use a placeholder photo. <a href="<?= e(admin_url('projects')) ?>">Show all projects</a></p>
<?php elseif ($seoOnly): ?>
        <p class="notice notice--info">Showing projects that are missing a main-photo description or an SEO meta description. Open each one and fill in the <strong>Search engines (SEO)</strong> section. <a href="<?= e(admin_url('projects')) ?>">Show all projects</a></p>
<?php endif; ?>
        <section class="panel">
<?php if ($rows): ?>
          <table class="table table--projects">
            <thead><tr><th scope="col">Photo</th><th scope="col">Project</th><th scope="col">Status</th><th scope="col">Market</th><th scope="col">Homepage</th><th scope="col">On website</th><th scope="col">Order</th></tr></thead>
            <tbody>
<?php foreach ($rows as $i => $row): ?>
              <tr>
                <td data-label="Photo">
<?php if ($row['image_path'] !== ''): ?>
                  <img class="thumb" src="<?= e(media_url($row['image_path'])) ?>" alt="" width="96" height="64" loading="lazy">
<?php else: ?>
                  <span class="thumb thumb--empty">No photo</span>
<?php endif; ?>
<?php if ($row['is_placeholder_image']): ?>
                  <span class="badge badge--warning">Placeholder</span>
<?php endif; ?>
                </td>
                <td data-label="Project"><a class="row-link" href="<?= e(admin_url('project', ['id' => $row['id']])) ?>"><strong><?= e($row['title']) ?></strong></a><span class="cell-sub">/projects/<?= e($row['slug']) ?></span><?php if ($row['seo_missing']): ?><span class="badge badge--warning">SEO to finish</span><?php endif; ?></td>
                <td data-label="Status"><?= e(project_status_label($row['status'])) ?></td>
                <td data-label="Market"><?= e(MARKETS[$row['market']]) ?></td>
                <td data-label="Homepage"><?= $row['home_position'] === 'none' ? '—' : e(['card1' => 'Card 1', 'card2' => 'Card 2', 'banner' => 'Banner'][$row['home_position']]) ?></td>
                <td data-label="On website">
                  <form method="post" class="inline-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="toggle">
                    <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                    <button class="switch<?= $row['published'] ? ' is-on' : '' ?>" type="submit" role="switch" aria-checked="<?= $row['published'] ? 'true' : 'false' ?>" aria-label="Show “<?= e($row['title']) ?>” on the website"><span class="switch-track"></span><span class="switch-text"><?= $row['published'] ? 'Shown' : 'Hidden' ?></span></button>
                  </form>
                </td>
                <td data-label="Order">
                  <div class="order-btns">
<?php foreach (['up' => 'Move up', 'down' => 'Move down'] as $dir => $label): $disabled = ($dir === 'up' && $i === 0) || ($dir === 'down' && $i === $last) || $filtered; ?>
                    <form method="post" class="inline-form">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="move">
                      <input type="hidden" name="dir" value="<?= $dir ?>">
                      <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                      <button class="icon-btn icon-btn--sm" type="submit" aria-label="<?= $label ?>: <?= e($row['title']) ?>"<?= $disabled ? ' disabled' : '' ?>><?= admin_icon($dir) ?></button>
                    </form>
<?php endforeach; ?>
                  </div>
                </td>
              </tr>
<?php endforeach; ?>
            </tbody>
          </table>
<?php elseif ($seoOnly): ?>
          <?= empty_state('SEO is complete', 'Every project has a photo description and a meta description.', admin_url('projects'), 'Show all projects') ?>
<?php elseif ($placeholderOnly): ?>
          <?= empty_state('Every project has a real photo', 'Nice work — no project is using a placeholder photo.', admin_url('projects'), 'Show all projects') ?>
<?php else: ?>
          <?= empty_state('No projects yet', 'Add your first project to show it on the website.', admin_url('project', ['id' => 'new']), 'Add project') ?>
<?php endif; ?>
        </section>
<?php admin_end(); ?>
