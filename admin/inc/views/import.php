<?php
defined('ETOWAH_ADMIN') || exit;
require_once __DIR__ . '/../import.php';

$dir = import_dir();
if (!is_dir($dir)) {
    @mkdir($dir, 0755, true); // so the folder is there to find in File Manager
}

if (is_post()) {
    require_post();
    $loc = import_locate();
    [$data, $problems] = import_load($loc);
    if (!$data || $problems) {
        flash('error', 'Nothing was imported: the package has problems. See the list below.');
        redirect(admin_url('import'));
    }
    try {
        $summary = import_run(import_plan($data), $loc['dir']);
    } catch (RuntimeException $e) {
        log_error('Project import failed: ' . $e->getMessage());
        flash('error', $e->getMessage() . ' The package was kept, so you can try again.');
        redirect(admin_url('import'));
    }
    import_clear($loc);
    log_activity('Imported projects: ' . $summary, 'project');
    flash('success', 'Import finished: ' . $summary . ' The package files were deleted from the import folder.');
    redirect(admin_url('projects'));
}

$loc = import_locate();
[$data, $problems] = import_load($loc);
$diag = import_diagnostics($loc);
$plan = $data ? import_plan($data) : null;
$fieldLabels = ['location' => 'Location', 'summary' => 'Short summary', 'description' => 'Full description', 'seo_title' => 'SEO title', 'seo_description' => 'Meta description', 'client_name' => 'Client name'];
$pageLabels = ['replace' => ['Replaces the placeholder', 'badge--new'], 'upgrade' => ['Newer version', 'badge--reviewing'], 'same' => ['Already imported', 'badge--ok'],
    'kept' => ['Kept your photo', 'badge--closed'], 'missing' => ['Slot not found', 'badge--closed']];
$actionLabels = ['new' => ['New project', 'badge--new'], 'update' => ['Update', 'badge--reviewing'], 'unchanged' => ['Already imported', 'badge--ok'], 'missing' => ['Not found', 'badge--closed']];

admin_start('Import projects', 'projects');
?>
        <p class="back"><a href="<?= e(admin_url('projects')) ?>">← All projects</a></p>
        <div class="page-actions">
          <p class="lead">Adds projects, photos, and videos from an import package. Nothing changes until you press <strong>Import</strong>. Text you edited in the dashboard, real main photos, and homepage choices you made are never overwritten.</p>
        </div>
<?php foreach ($problems as $problem): ?>
        <p class="notice notice--error" role="alert"><?= e($problem) ?></p>
<?php endforeach; ?>
<?php if (!$data): ?>
        <section class="panel">
          <div class="panel-head"><h2>No import package found</h2></div>
          <ol class="steps">
            <li>In hPanel, open <strong>File Manager</strong>.</li>
            <li>Go to this folder (it is private and not reachable from the website):<br><code><?= e(str_replace('\\', '/', $dir)) ?></code></li>
            <li>Upload <strong>etowah-projects-import.zip</strong> into it. You don’t need to extract it.</li>
            <li>Come back to this page and reload it to see the preview.</li>
          </ol>
          <p class="hint">The other standard folders are checked too (see Diagnostics below), and a package extracted into its own subfolder is found as well.</p>
        </section>
<?php else: $t = $plan['totals']; ?>
        <section class="panel">
          <div class="panel-head">
            <h2>Preview</h2>
            <span class="hint">Package built <?= e((string) ($data['built'] ?? '')) ?></span>
          </div>
          <p class="hint">Found in <code><?= e($loc['dir']) ?></code><?= $loc['zip'] ? ' (package zip: ' . e(basename($loc['zip'])) . ')' : '' ?></p>
          <p><strong><?= $t['new'] ?></strong> new project<?= $t['new'] === 1 ? '' : 's' ?> · <strong><?= $t['updated'] ?></strong> updated · <strong><?= $t['photos'] ?></strong> photo<?= $t['photos'] === 1 ? '' : 's' ?> and <strong><?= $t['videos'] ?></strong> video<?= $t['videos'] === 1 ? '' : 's' ?> to add · <strong><?= $t['pages'] ?></strong> page photo<?= $t['pages'] === 1 ? '' : 's' ?> to replace<?php if ($t['upgraded']): ?> · <strong><?= $t['upgraded'] ?></strong> photo<?= $t['upgraded'] === 1 ? '' : 's' ?> updated to a newer version<?php endif; ?></p>
          <table class="table">
            <thead><tr><th scope="col">Project</th><th scope="col">Action</th><th scope="col">What changes</th></tr></thead>
            <tbody>
<?php foreach ($plan['projects'] as $item): [$label, $badge] = $actionLabels[$item['action']]; ?>
              <tr>
                <td data-label="Project"><strong><?= e($item['title'] ?? $item['slug']) ?></strong><span class="cell-sub">/projects/<?= e($item['slug']) ?></span></td>
                <td data-label="Action"><span class="badge <?= $badge ?>"><?= $label ?></span></td>
                <td data-label="What changes">
<?php if ($item['main'] && !isset($item['main']['old'])): ?>
                  <span class="cell-sub">Main photo: <?= $item['action'] === 'new' ? 'added' : 'replaces the placeholder' ?></span>
<?php endif; ?>
<?php $upgrades = count($item['upgrade']) + (isset($item['main']['old']) ? 1 : 0); if ($upgrades): ?>
                  <span class="cell-sub"><?= $upgrades ?> photo<?= $upgrades === 1 ? '' : 's' ?> updated to a newer version (descriptions and order kept)</span>
<?php endif; ?>
<?php if ($item['add']): $v = count(array_filter($item['add'], fn($m) => $m['type'] === 'video')); $ph = count($item['add']) - $v; ?>
                  <span class="cell-sub">Gallery: <?= $ph ?> photo<?= $ph === 1 ? '' : 's' ?><?= $v ? ', ' . $v . ' video' . ($v === 1 ? '' : 's') : '' ?> added</span>
<?php endif; ?>
<?php if ($item['fill']): ?>
                  <span class="cell-sub">Filled in: <?= e(implode(', ', array_map(fn($f) => $fieldLabels[$f], array_keys($item['fill'])))) ?></span>
<?php endif; ?>
<?php if ($item['kept']): ?>
                  <span class="cell-sub">Kept your edited text: <?= e(implode(', ', array_map(fn($f) => $fieldLabels[$f], $item['kept']))) ?></span>
<?php endif; ?>
<?php foreach ($item['notes'] as $note): ?>
                  <span class="cell-sub"><?= e($note) ?></span>
<?php endforeach; ?>
<?php if ($item['action'] === 'unchanged'): ?>
                  <span class="cell-sub">Nothing to add.</span>
<?php endif; ?>
                </td>
              </tr>
<?php endforeach; ?>
            </tbody>
          </table>
<?php if ($plan['home']): ?>
          <h3 class="subhead">Homepage</h3>
          <ul class="plain-list">
<?php foreach ($plan['home'] as $h): ?>
            <li><?= e(HOME_POSITIONS[$h['slot']]) ?>: <?= $h['apply'] ? '<strong>' . e($h['title']) . '</strong>' . ($h['from'] !== '' ? ' (instead of ' . e($h['from']) . ')' : '') : 'kept as ' . e($h['from']) . ' — you changed it in the dashboard' ?></li>
<?php endforeach; ?>
          </ul>
<?php endif; ?>
<?php if ($plan['pages']): ?>
          <h3 class="subhead">Page photos</h3>
          <table class="table">
            <thead><tr><th scope="col">Page</th><th scope="col">Photo</th><th scope="col">Action</th></tr></thead>
            <tbody>
<?php foreach ($plan['pages'] as $s): [$label, $badge] = $pageLabels[$s['action']]; ?>
              <tr>
                <td data-label="Page"><?= e(PAGES[$s['page']][1] ?? ($s['page'] === 'global' ? 'All pages' : ucfirst($s['page']))) ?></td>
                <td data-label="Photo"><?= e($s['label']) ?><span class="cell-sub"><?= e($s['alt']) ?></span></td>
                <td data-label="Action"><span class="badge <?= $badge ?>"><?= $label ?></span></td>
              </tr>
<?php endforeach; ?>
            </tbody>
          </table>
<?php endif; ?>
<?php if (!$problems && ($t['new'] || $t['updated'] || $t['pages'] || $t['upgraded'] || array_filter($plan['home'], fn($h) => $h['apply']))): ?>
          <form method="post" class="button-row" data-confirm="Import these projects, photos, and videos now? The package files are deleted afterwards." data-confirm-ok="Yes, import">
            <?= csrf_field() ?>
            <button class="btn btn--primary" type="submit">Import</button>
          </form>
<?php elseif (!$problems): ?>
          <form method="post" class="button-row">
            <?= csrf_field() ?>
            <p class="hint">Everything in this package is already on the website. Press the button to delete the package files.</p>
            <button class="btn btn--sm" type="submit">Finish and delete the package</button>
          </form>
<?php endif; ?>
        </section>
<?php endif; ?>
        <details class="panel diagnostics"<?= !$data || $problems ? ' open' : '' ?>>
          <summary><strong>Diagnostics</strong> <span class="hint">what the server sees</span></summary>
          <dl class="diag-list">
            <dt>Package</dt><dd><?= $loc ? 'found in <code>' . e($loc['dir']) . '</code>' . ($loc['zip'] ? ' (zip: ' . e(basename($loc['zip'])) . ')' : '') : 'not found in any of the folders below' ?></dd>
            <dt>Storage folder in use</dt><dd><code><?= e($diag['storage']) ?></code></dd>
            <dt>Storage folder in config.php</dt><dd><code><?= e($diag['configured']) ?></code></dd>
            <dt>Zip support (ZipArchive)</dt><dd><?= $diag['zip'] ? 'available' : 'NOT available — extract the zip in File Manager instead' ?></dd>
            <dt>PHP runs as</dt><dd><?= e($diag['user']) ?></dd>
            <dt>open_basedir</dt><dd><code><?= e($diag['basedir']) ?></code></dd>
          </dl>
<?php foreach ($diag['folders'] as $f): ?>
          <h3 class="subhead"><code><?= e($f['path']) ?></code> <span class="hint">— <?= e($f['role']) ?></span></h3>
          <p class="hint">
            Exists: <strong><?= $f['exists'] ? 'yes' : 'no' ?></strong> · Readable: <strong><?= $f['readable'] ? 'yes' : 'no' ?></strong> · Writable: <strong><?= $f['writable'] ? 'yes' : 'no' ?></strong> · open_basedir: <?= e($f['basedir']) ?>
<?php if ($f['real'] !== '' && $f['real'] !== $f['path']): ?>
            · Real path: <code><?= e($f['real']) ?></code>
<?php endif; ?>
          </p>
<?php if ($f['exists']): ?>
<?php if ($f['entries']): ?>
          <ul class="plain-list diag-files">
<?php foreach ($f['entries'] as $name => $info): ?>
            <li><code><?= e($name) ?><?= $info['dir'] ? '/' : '' ?></code><?= $info['dir'] ? '' : ' — ' . e(number_format($info['size'])) . ' bytes' ?></li>
<?php endforeach; ?>
          </ul>
<?php else: ?>
          <p class="hint">The folder is empty (as PHP sees it).</p>
<?php endif; ?>
<?php endif; ?>
<?php endforeach; ?>
        </details>
<?php admin_end(); ?>
