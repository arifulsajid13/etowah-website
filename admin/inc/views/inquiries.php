<?php
defined('ETOWAH_ADMIN') || exit;
$f = inquiry_filters();
[$where, $params, $order] = inquiry_query($f);
$total = (int) db_value("SELECT COUNT(*) FROM inquiries WHERE {$where}", $params);
[$offset, $pages, $page] = paginate($total, get_int('page'));
$rows = db_all("SELECT id, name, company, email, phone, project_type, status, created_at FROM inquiries WHERE {$where} ORDER BY {$order} LIMIT " . PER_PAGE . " OFFSET {$offset}", $params);
$filtered = $f['q'] !== '' || $f['status'] !== '';

admin_start('Inquiries', 'inquiries');
?>
        <form class="filters" method="get" action="/admin/">
          <input type="hidden" name="p" value="inquiries">
          <div class="field field--grow">
            <label for="f-q">Search</label>
            <input id="f-q" name="q" type="search" value="<?= e($f['q']) ?>" placeholder="Name, email, company, message…">
          </div>
          <div class="field">
            <label for="f-status">Status</label>
            <select id="f-status" name="status">
              <option value="">All statuses</option>
<?php foreach (INQUIRY_STATUSES as $k => $label): ?>
              <option value="<?= $k ?>"<?= sel($f['status'] === $k) ?>><?= $label ?></option>
<?php endforeach; ?>
            </select>
          </div>
          <div class="field">
            <label for="f-sort">Date</label>
            <select id="f-sort" name="sort">
              <option value="">Newest first</option>
              <option value="oldest"<?= sel($f['sort'] === 'oldest') ?>>Oldest first</option>
            </select>
          </div>
          <div class="filters-actions">
            <button class="btn btn--primary" type="submit">Apply</button>
<?php if ($filtered): ?>
            <a class="btn btn--ghost" href="<?= e(admin_url('inquiries')) ?>">Clear</a>
<?php endif; ?>
          </div>
        </form>

        <section class="panel">
          <div class="panel-head">
            <h2><?= $total ?> <?= $total === 1 ? 'inquiry' : 'inquiries' ?><?= $filtered ? ' found' : '' ?></h2>
<?php if ($total): ?>
            <a class="btn btn--ghost btn--sm" href="<?= e('/admin/export.php?' . http_build_query(['type' => 'inquiries'] + array_filter($f))) ?>"><?= admin_icon('download') ?><span>Export CSV</span></a>
<?php endif; ?>
          </div>
<?php if ($rows): ?>
          <table class="table">
            <thead><tr><th scope="col">Name</th><th scope="col">Contact</th><th scope="col">Project type</th><th scope="col">Received</th><th scope="col">Status</th></tr></thead>
            <tbody>
<?php foreach ($rows as $row): $href = admin_url('inquiry', ['id' => $row['id']]); ?>
              <tr>
                <td data-label="Name"><a class="row-link" href="<?= e($href) ?>"><strong><?= e($row['name']) ?></strong></a><?php if ($row['company'] !== ''): ?><span class="cell-sub"><?= e($row['company']) ?></span><?php endif; ?></td>
                <td data-label="Contact"><?= e($row['email']) ?><?php if ($row['phone'] !== ''): ?><span class="cell-sub"><?= e($row['phone']) ?></span><?php endif; ?></td>
                <td data-label="Project type"><?= e($row['project_type'] ?: '—') ?></td>
                <td data-label="Received"><?= e(fmt_date($row['created_at'])) ?></td>
                <td data-label="Status"><?= status_badge($row['status'], INQUIRY_STATUSES) ?></td>
              </tr>
<?php endforeach; ?>
            </tbody>
          </table>
          <?= pagination_html($pages, $page, 'inquiries', array_filter($f)) ?>
<?php elseif ($filtered): ?>
          <?= empty_state('No inquiries match these filters', 'Try a different search word or status.', admin_url('inquiries'), 'Clear filters') ?>
<?php else: ?>
          <?= empty_state('No inquiries yet', 'When someone sends the form on the Contact page, it appears here and you get an email.') ?>
<?php endif; ?>
        </section>
<?php admin_end(); ?>
