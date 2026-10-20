<?php
defined('ETOWAH_ADMIN') || exit;
$rows = db_all('SELECT j.*, (SELECT COUNT(*) FROM applications a WHERE a.job_id = j.id) AS apps,
                       (SELECT COUNT(*) FROM applications a WHERE a.job_id = j.id AND a.status = \'new\') AS new_apps
                  FROM jobs j ORDER BY FIELD(j.status, \'open\', \'draft\', \'closed\'), j.updated_at DESC');
$general = (int) db_value('SELECT COUNT(*) FROM applications WHERE job_id IS NULL');

admin_start('Jobs', 'jobs');
?>
        <div class="page-actions">
          <p class="lead">Only jobs marked <strong>Open</strong> appear on the Careers page. Use <strong>Draft</strong> while you write a posting and <strong>Closed</strong> when it’s filled.</p>
          <a class="btn btn--primary" href="<?= e(admin_url('job', ['id' => 'new'])) ?>">+ Add job</a>
        </div>
        <section class="panel">
<?php if ($rows): ?>
          <table class="table">
            <thead><tr><th scope="col">Job</th><th scope="col">Status</th><th scope="col">Posted</th><th scope="col">Applications</th><th scope="col"><span class="visually-hidden">Links</span></th></tr></thead>
            <tbody>
<?php foreach ($rows as $row): ?>
              <tr>
                <td data-label="Job"><a class="row-link" href="<?= e(admin_url('job', ['id' => $row['id']])) ?>"><strong><?= e($row['title']) ?></strong></a><span class="cell-sub"><?= e(implode(' · ', array_filter([$row['location'], $row['employment_type']], 'strlen')) ?: '—') ?></span></td>
                <td data-label="Status"><?= status_badge($row['status'], JOB_STATUSES) ?></td>
                <td data-label="Posted"><?= e(fmt_date($row['posted_at'], false)) ?></td>
                <td data-label="Applications"><a href="<?= e(admin_url('applications', ['job' => $row['id']])) ?>"><?= (int) $row['apps'] ?></a><?php if ($row['new_apps']): ?> <span class="badge badge--new"><?= (int) $row['new_apps'] ?> new</span><?php endif; ?></td>
                <td data-label="Links"><?php if ($row['status'] === 'open'): ?><a class="panel-link" href="/careers/<?= e($row['slug']) ?>" target="_blank" rel="noopener">View on site <?= admin_icon('external') ?></a><?php endif; ?></td>
              </tr>
<?php endforeach; ?>
            </tbody>
          </table>
<?php else: ?>
          <?= empty_state('No jobs yet', 'While there are no open jobs, the Careers page invites people to send their details anyway.', admin_url('job', ['id' => 'new']), 'Add your first job') ?>
<?php endif; ?>
        </section>
<?php if ($general): ?>
        <p class="hint"><a href="<?= e(admin_url('applications', ['job' => 'general'])) ?>"><?= $general ?> general <?= $general === 1 ? 'application' : 'applications' ?></a> (not for a specific job).</p>
<?php endif; ?>
<?php admin_end(); ?>
