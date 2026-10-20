<?php
defined('ETOWAH_ADMIN') || exit;
$count = fn(string $sql) => (int) db_value($sql);

$staleInquiries = $count("SELECT COUNT(*) FROM inquiries WHERE status = 'new' AND created_at < NOW() - INTERVAL 48 HOUR");
$staleApplications = $count("SELECT COUNT(*) FROM applications WHERE status = 'new' AND created_at < NOW() - INTERVAL 48 HOUR");
$placeholders = placeholder_counts();

$inq7 = $count('SELECT COUNT(*) FROM inquiries WHERE created_at >= NOW() - INTERVAL 7 DAY');
$inqPrev = $count('SELECT COUNT(*) FROM inquiries WHERE created_at >= NOW() - INTERVAL 14 DAY AND created_at < NOW() - INTERVAL 7 DAY');
$app7 = $count('SELECT COUNT(*) FROM applications WHERE created_at >= NOW() - INTERVAL 7 DAY');
$appPrev = $count('SELECT COUNT(*) FROM applications WHERE created_at >= NOW() - INTERVAL 14 DAY AND created_at < NOW() - INTERVAL 7 DAY');
$openJobs = $count("SELECT COUNT(*) FROM jobs WHERE status = 'open'");
$awaiting = $count("SELECT COUNT(*) FROM inquiries WHERE status = 'new'") + $count("SELECT COUNT(*) FROM applications WHERE status = 'new'");

$latestInquiries = db_all('SELECT id, name, company, project_type, status, created_at FROM inquiries ORDER BY created_at DESC, id DESC LIMIT 5');
$latestApplications = db_all('SELECT a.id, a.name, a.status, a.created_at, j.title AS job_title FROM applications a LEFT JOIN jobs j ON j.id = a.job_id ORDER BY a.created_at DESC, a.id DESC LIMIT 5');

$attention = [];
if ($staleInquiries) {
    $attention[] = [admin_url('inquiries', ['status' => 'new']), $staleInquiries . ' new ' . ($staleInquiries === 1 ? 'inquiry has' : 'inquiries have') . ' waited more than 48 hours for a reply'];
}
if ($staleApplications) {
    $attention[] = [admin_url('applications', ['status' => 'new']), $staleApplications . ' new ' . ($staleApplications === 1 ? 'application has' : 'applications have') . ' waited more than 48 hours'];
}
if ($placeholders['projects']) {
    $attention[] = [admin_url('projects', ['photos' => 'placeholder']), $placeholders['projects'] . ' ' . ($placeholders['projects'] === 1 ? 'project is' : 'projects are') . ' still using a placeholder photo'];
}
if ($placeholders['images']) {
    $attention[] = [admin_url('pages'), $placeholders['images'] . ' page ' . ($placeholders['images'] === 1 ? 'image is' : 'images are') . ' still marked as placeholder'];
}

$trend = function (int $now, int $prev): string {
    if ($now === $prev) {
        return '<span class="trend">Same as the previous 7 days</span>';
    }
    $up = $now > $prev;
    return '<span class="trend trend--' . ($up ? 'up' : 'down') . '">' . ($up ? '▲ ' : '▼ ') . abs($now - $prev) . ' vs. previous 7 days (' . $prev . ')</span>';
};

admin_start('Overview', 'overview');
?>
        <section class="panel attention" aria-labelledby="attention-title">
          <div class="panel-head">
            <h2 id="attention-title"><?= admin_icon('alert') ?> Needs attention</h2>
          </div>
<?php if ($attention): ?>
          <ul class="attention-list">
<?php foreach ($attention as [$href, $text]): ?>
            <li><a href="<?= e($href) ?>"><span><?= e($text) ?></span><?= admin_icon('arrow') ?></a></li>
<?php endforeach; ?>
          </ul>
<?php else: ?>
          <p class="all-clear"><?= admin_icon('check') ?> All caught up — nothing needs your attention right now.</p>
<?php endif; ?>
        </section>

        <section class="stat-grid" aria-label="Summary">
          <a class="stat-card" href="<?= e(admin_url('inquiries')) ?>">
            <span class="stat-label">Inquiries · last 7 days</span>
            <span class="stat-value"><?= $inq7 ?></span>
            <?= $trend($inq7, $inqPrev) ?>
          </a>
          <a class="stat-card" href="<?= e(admin_url('applications')) ?>">
            <span class="stat-label">Applications · last 7 days</span>
            <span class="stat-value"><?= $app7 ?></span>
            <?= $trend($app7, $appPrev) ?>
          </a>
          <a class="stat-card" href="<?= e(admin_url('jobs')) ?>">
            <span class="stat-label">Open positions</span>
            <span class="stat-value"><?= $openJobs ?></span>
            <span class="trend">Shown on the Careers page</span>
          </a>
          <a class="stat-card" href="<?= e(admin_url('inquiries', ['status' => 'new'])) ?>">
            <span class="stat-label">Awaiting reply</span>
            <span class="stat-value"><?= $awaiting ?></span>
            <span class="trend">Inquiries + applications marked “New”</span>
          </a>
        </section>

        <div class="two-col">
          <section class="panel" aria-labelledby="latest-inquiries">
            <div class="panel-head">
              <h2 id="latest-inquiries">Latest inquiries</h2>
              <a class="panel-link" href="<?= e(admin_url('inquiries')) ?>">View all</a>
            </div>
<?php if ($latestInquiries): ?>
            <ul class="item-list">
<?php foreach ($latestInquiries as $row): ?>
              <li>
                <a href="<?= e(admin_url('inquiry', ['id' => $row['id']])) ?>">
                  <span class="item-main"><strong><?= e($row['name']) ?></strong><?php if ($row['company'] !== ''): ?> · <?= e($row['company']) ?><?php endif; ?></span>
                  <span class="item-sub"><?= e($row['project_type'] ?: 'General inquiry') ?> · <?= e(time_ago($row['created_at'])) ?></span>
                </a>
                <?= status_badge($row['status'], INQUIRY_STATUSES) ?>
              </li>
<?php endforeach; ?>
            </ul>
<?php else: ?>
            <?= empty_state('No inquiries yet', 'Messages sent from the Contact page will appear here.') ?>
<?php endif; ?>
          </section>

          <section class="panel" aria-labelledby="latest-applications">
            <div class="panel-head">
              <h2 id="latest-applications">Latest applications</h2>
              <a class="panel-link" href="<?= e(admin_url('applications')) ?>">View all</a>
            </div>
<?php if ($latestApplications): ?>
            <ul class="item-list">
<?php foreach ($latestApplications as $row): ?>
              <li>
                <a href="<?= e(admin_url('application', ['id' => $row['id']])) ?>">
                  <span class="item-main"><strong><?= e($row['name']) ?></strong></span>
                  <span class="item-sub"><?= e($row['job_title'] ?? 'General application') ?> · <?= e(time_ago($row['created_at'])) ?></span>
                </a>
                <?= status_badge($row['status'], APPLICATION_STATUSES) ?>
              </li>
<?php endforeach; ?>
            </ul>
<?php else: ?>
            <?= empty_state('No applications yet', 'Applications sent from the Careers page will appear here.') ?>
<?php endif; ?>
          </section>
        </div>
<?php admin_end(); ?>
