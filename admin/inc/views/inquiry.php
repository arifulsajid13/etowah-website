<?php
defined('ETOWAH_ADMIN') || exit;
$id = get_int('id');
$row = db_one('SELECT * FROM inquiries WHERE id = ?', [$id]);
if (!$row) {
    flash('error', 'That inquiry no longer exists.');
    redirect(admin_url('inquiries'));
}

if (is_post()) {
    require_post();
    if (post_str('action') === 'delete') {
        db_run('DELETE FROM inquiries WHERE id = ?', [$id]);
        log_activity('Deleted inquiry from ' . mb_substr($row['name'], 0, 40), 'inquiry', $id);
        flash('success', 'Inquiry deleted.');
        redirect(admin_url('inquiries'));
    }
    $status = pick(post_str('status'), INQUIRY_STATUSES, $row['status']);
    $notes = mb_substr(post_str('notes'), 0, 10000);
    // "Contacted" stamps the first contact time once.
    $contactedAt = $row['contacted_at'] ?: ($status === 'contacted' ? now() : null);
    db_run('UPDATE inquiries SET status = ?, notes = ?, contacted_at = ? WHERE id = ?', [$status, $notes, $contactedAt, $id]);
    log_activity('Updated inquiry (' . INQUIRY_STATUSES[$status] . ')', 'inquiry', $id);
    flash('success', 'Inquiry saved.');
    redirect(admin_url('inquiry', ['id' => $id]));
}

$fields = [
    'Email'            => '<a href="mailto:' . e($row['email']) . '">' . e($row['email']) . '</a>',
    'Phone'            => $row['phone'] !== '' ? '<a href="' . e(tel_href($row['phone'])) . '">' . e($row['phone']) . '</a>' : '—',
    'Company'          => e($row['company'] ?: '—'),
    'Project type'     => e($row['project_type'] ?: '—'),
    'Project location' => e($row['project_location'] ?: '—'),
    'Received'         => e(fmt_date($row['created_at'])),
    'First contacted'  => e(fmt_date($row['contacted_at'])),
];

admin_start('Inquiry from ' . $row['name'], 'inquiries');
?>
        <p class="back"><a href="<?= e(admin_url('inquiries')) ?>">← All inquiries</a></p>
        <div class="detail-grid">
          <section class="panel">
            <div class="panel-head">
              <h2><?= e($row['name']) ?></h2>
              <?= status_badge($row['status'], INQUIRY_STATUSES) ?>
            </div>
            <dl class="facts">
<?php foreach ($fields as $label => $html): ?>
              <div><dt><?= e($label) ?></dt><dd><?= $html ?></dd></div>
<?php endforeach; ?>
            </dl>
            <h3 class="subhead">Message</h3>
            <div class="message-box"><?= nl2br(e($row['message'])) ?></div>
            <div class="button-row">
              <a class="btn btn--primary" href="mailto:<?= e($row['email']) ?>?subject=<?= rawurlencode('Re: Your project inquiry') ?>">Reply by email</a>
<?php if ($row['phone'] !== ''): ?>
              <a class="btn btn--ghost" href="<?= e(tel_href($row['phone'])) ?>">Call <?= e($row['phone']) ?></a>
<?php endif; ?>
            </div>
          </section>

          <section class="panel">
            <div class="panel-head"><h2>Follow-up</h2></div>
            <form method="post" class="stack">
              <?= csrf_field() ?>
              <div class="field">
                <label for="i-status">Status</label>
                <select id="i-status" name="status">
<?php foreach (INQUIRY_STATUSES as $k => $label): ?>
                  <option value="<?= $k ?>"<?= sel($row['status'] === $k) ?>><?= $label ?></option>
<?php endforeach; ?>
                </select>
                <p class="hint">Choosing “Contacted” records the date you first replied.</p>
              </div>
              <div class="field">
                <label for="i-notes">Private notes</label>
                <textarea id="i-notes" name="notes" rows="6" maxlength="10000" placeholder="Call summary, quote amount, next steps…"><?= e((string) $row['notes']) ?></textarea>
                <p class="hint">Only dashboard users can see these notes.</p>
              </div>
              <button class="btn btn--primary" type="submit">Save</button>
            </form>
            <form method="post" class="danger-zone" data-confirm="Delete the inquiry from <?= e($row['name']) ?>? This cannot be undone.">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="delete">
              <button class="btn btn--danger btn--sm" type="submit">Delete inquiry</button>
            </form>
          </section>
        </div>
<?php admin_end(); ?>
