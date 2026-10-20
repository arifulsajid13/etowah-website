<?php
defined('ETOWAH_ADMIN') || exit;
$id = get_int('id');
$row = db_one('SELECT a.*, j.title AS job_title, j.slug AS job_slug, j.status AS job_status FROM applications a LEFT JOIN jobs j ON j.id = a.job_id WHERE a.id = ?', [$id]);
if (!$row) {
    flash('error', 'That application no longer exists.');
    redirect(admin_url('applications'));
}

if (is_post()) {
    require_post();
    if (post_str('action') === 'delete') {
        if (preg_match('/^[a-f0-9]{32}$/', $row['resume_file'])) {
            @unlink(storage_path('resumes/' . $row['resume_file']));
        }
        db_run('DELETE FROM applications WHERE id = ?', [$id]);
        log_activity('Deleted application from ' . mb_substr($row['name'], 0, 40), 'application', $id);
        flash('success', 'Application and resume deleted.');
        redirect(admin_url('applications'));
    }
    $status = pick(post_str('status'), APPLICATION_STATUSES, $row['status']);
    db_run('UPDATE applications SET status = ?, notes = ? WHERE id = ?', [$status, mb_substr(post_str('notes'), 0, 10000), $id]);
    log_activity('Updated application (' . APPLICATION_STATUSES[$status] . ')', 'application', $id);
    flash('success', 'Application saved.');
    redirect(admin_url('application', ['id' => $id]));
}

$size = (int) $row['resume_size'];
$sizeText = $size >= 1048576 ? number_format($size / 1048576, 1) . ' MB' : max(1, (int) round($size / 1024)) . ' KB';
$resumeExists = preg_match('/^[a-f0-9]{32}$/', $row['resume_file']) && is_file(storage_path('resumes/' . $row['resume_file']));

admin_start('Application from ' . $row['name'], 'applications');
?>
        <p class="back"><a href="<?= e(admin_url('applications')) ?>">← All applications</a></p>
        <div class="detail-grid">
          <section class="panel">
            <div class="panel-head">
              <h2><?= e($row['name']) ?></h2>
              <?= status_badge($row['status'], APPLICATION_STATUSES) ?>
            </div>
            <dl class="facts">
              <div><dt>Position</dt><dd><?php if ($row['job_id']): ?><a href="<?= e(admin_url('job', ['id' => $row['job_id']])) ?>"><?= e($row['job_title']) ?></a><?php else: ?>General application<?php endif; ?></dd></div>
              <div><dt>Email</dt><dd><a href="mailto:<?= e($row['email']) ?>"><?= e($row['email']) ?></a></dd></div>
              <div><dt>Phone</dt><dd><?= $row['phone'] !== '' ? '<a href="' . e(tel_href($row['phone'])) . '">' . e($row['phone']) . '</a>' : '—' ?></dd></div>
              <div><dt>Received</dt><dd><?= e(fmt_date($row['created_at'])) ?></dd></div>
            </dl>
            <h3 class="subhead">Message</h3>
            <div class="message-box"><?= trim((string) $row['message']) !== '' ? nl2br(e($row['message'])) : '<span class="muted">No message.</span>' ?></div>
            <h3 class="subhead">Resume</h3>
<?php if ($resumeExists): ?>
            <a class="btn btn--primary" href="/admin/resume.php?id=<?= (int) $row['id'] ?>"><?= admin_icon('download') ?><span>Download resume</span></a>
            <p class="hint"><?= e($row['resume_original_name']) ?> · <?= e($sizeText) ?>. Open downloaded files only if you trust them.</p>
<?php else: ?>
            <p class="notice notice--error">The resume file is missing from storage.</p>
<?php endif; ?>
          </section>

          <section class="panel">
            <div class="panel-head"><h2>Follow-up</h2></div>
            <form method="post" class="stack">
              <?= csrf_field() ?>
              <div class="field">
                <label for="a-status">Status</label>
                <select id="a-status" name="status">
<?php foreach (APPLICATION_STATUSES as $k => $label): ?>
                  <option value="<?= $k ?>"<?= sel($row['status'] === $k) ?>><?= $label ?></option>
<?php endforeach; ?>
                </select>
              </div>
              <div class="field">
                <label for="a-notes">Private notes</label>
                <textarea id="a-notes" name="notes" rows="6" maxlength="10000" placeholder="Interview notes, references, next steps…"><?= e((string) $row['notes']) ?></textarea>
                <p class="hint">Only dashboard users can see these notes.</p>
              </div>
              <button class="btn btn--primary" type="submit">Save</button>
            </form>
            <form method="post" class="danger-zone" data-confirm="Delete the application from <?= e($row['name']) ?>? The resume file is deleted too. This cannot be undone.">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="delete">
              <button class="btn btn--danger btn--sm" type="submit">Delete application</button>
            </form>
          </section>
        </div>
<?php admin_end(); ?>
