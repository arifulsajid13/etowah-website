<?php
defined('ETOWAH_ADMIN') || exit;
$f = application_filters();
[$where, $params, $order] = application_query($f);
$total = (int) db_value("SELECT COUNT(*) FROM applications a WHERE {$where}", $params);
[$offset, $pages, $page] = paginate($total, get_int('page'));
$rows = db_all("SELECT a.id, a.name, a.email, a.phone, a.status, a.created_at, j.title AS job_title
                  FROM applications a LEFT JOIN jobs j ON j.id = a.job_id
                 WHERE {$where} ORDER BY {$order} LIMIT " . PER_PAGE . " OFFSET {$offset}", $params);
$jobs = db_all('SELECT id, title, status FROM jobs ORDER BY title');
$filtered = $f['q'] !== '' || $f['status'] !== '' || $f['job'] !== '0';
$query = array_filter($f, fn($v) => $v !== '' && $v !== '0');

admin_start('Applications', 'applications');
?>
        <form class="filters" method="get" action="/admin/">
          <input type="hidden" name="p" value="applications">
          <div class="field field--grow">
            <label for="f-q">Search</label>
            <input id="f-q" name="q" type="search" value="<?= e($f['q']) ?>" placeholder="Name, email, phone, message…">
          </div>
          <div class="field">
            <label for="f-job">Job</label>
            <select id="f-job" name="job">
              <option value="">All jobs</option>
              <option value="general"<?= sel($f['job'] === 'general') ?>>General applications</option>
<?php foreach ($jobs as $job): ?>
              <option value="<?= (int) $job['id'] ?>"<?= sel($f['job'] === (string) $job['id']) ?>><?= e($job['title']) ?><?= $job['status'] !== 'open' ? ' (' . JOB_STATUSES[$job['status']] . ')' : '' ?></option>
<?php endforeach; ?>
            </select>
          </div>
          <div class="field">
            <label for="f-status">Status</label>
            <select id="f-status" name="status">
              <option value="">All statuses</option>
<?php foreach (APPLICATION_STATUSES as $k => $label): ?>
              <option value="<?= $k ?>"<?= sel($f['status'] === $k) ?>><?= $label ?></option>
<?php endforeach; ?>
            </select>
          </div>
          <div class="filters-actions">
            <button class="btn btn--primary" type="submit">Apply</button>
<?php if ($filtered): ?>
            <a class="btn btn--ghost" href="<?= e(admin_url('applications')) ?>">Clear</a>
<?php endif; ?>
          </div>
        </form>

        <section class="panel">
          <div class="panel-head">
            <h2><?= $total ?> <?= $total === 1 ? 'application' : 'applications' ?><?= $filtered ? ' found' : '' ?></h2>
<?php if ($total): ?>
            <a class="btn btn--ghost btn--sm" href="<?= e('/admin/export.php?' . http_build_query(['type' => 'applications'] + $query)) ?>"><?= admin_icon('download') ?><span>Export CSV</span></a>
<?php endif; ?>
          </div>
<?php if ($rows): ?>
          <table class="table">
            <thead><tr><th scope="col">Applicant</th><th scope="col">Position</th><th scope="col">Received</th><th scope="col">Status</th></tr></thead>
            <tbody>
<?php foreach ($rows as $row): ?>
              <tr>
                <td data-label="Applicant"><a class="row-link" href="<?= e(admin_url('application', ['id' => $row['id']])) ?>"><strong><?= e($row['name']) ?></strong></a><span class="cell-sub"><?= e($row['email']) ?></span></td>
                <td data-label="Position"><?= e($row['job_title'] ?? 'General application') ?></td>
                <td data-label="Received"><?= e(fmt_date($row['created_at'])) ?></td>
                <td data-label="Status"><?= status_badge($row['status'], APPLICATION_STATUSES) ?></td>
              </tr>
<?php endforeach; ?>
            </tbody>
          </table>
          <?= pagination_html($pages, $page, 'applications', $query) ?>
<?php elseif ($filtered): ?>
          <?= empty_state('No applications match these filters', 'Try a different search word, job, or status.', admin_url('applications'), 'Clear filters') ?>
<?php else: ?>
          <?= empty_state('No applications yet', 'Applications sent from the Careers page appear here, with the resume ready to download.') ?>
<?php endif; ?>
        </section>
<?php admin_end(); ?>
