<?php
defined('ETOWAH_ADMIN') || exit;
$id = get_int('id');
$job = $id ? db_one('SELECT * FROM jobs WHERE id = ?', [$id]) : null;
if ($id && !$job) {
    flash('error', 'That job no longer exists.');
    redirect(admin_url('jobs'));
}
$appCount = $job ? (int) db_value('SELECT COUNT(*) FROM applications WHERE job_id = ?', [$id]) : 0;
$errors = [];

if (is_post()) {
    require_post();

    if (post_str('action') === 'delete' && $job) {
        if ($appCount > 0) {
            flash('error', 'This job has applications, so it can’t be deleted. Set its status to “Closed” instead — it will disappear from the website and the applications stay on file.');
            redirect(admin_url('job', ['id' => $id]));
        }
        db_run('DELETE FROM jobs WHERE id = ?', [$id]);
        log_activity('Deleted job ' . mb_substr($job['title'], 0, 40), 'job', $id);
        flash('success', 'Job deleted.');
        redirect(admin_url('jobs'));
    }

    $in = [
        'title'           => post_str('title'),
        'slug'            => post_str('slug'),
        'location'        => post_str('location'),
        'employment_type' => in_array(post_str('employment_type'), EMPLOYMENT_TYPES, true) ? post_str('employment_type') : '',
        'description'     => post_str('description'),
        'status'          => pick(post_str('status'), JOB_STATUSES, 'draft'),
    ];
    if ($in['title'] === '' || str_length($in['title']) > 200) {
        $errors['title'] = 'Enter a job title (up to 200 characters).';
    }
    if (str_length($in['location']) > 120) {
        $errors['location'] = 'Keep the location to 120 characters or fewer.';
    }
    if ($in['status'] === 'open' && trim($in['description']) === '') {
        $errors['description'] = 'Add a description before opening this job.';
    } elseif (str_length($in['description']) > 10000) {
        $errors['description'] = 'Keep the description to 10,000 characters or fewer.';
    }

    if (!$errors) {
        $slug = unique_slug('jobs', $in['slug'] !== '' ? $in['slug'] : $in['title'], $id);
        // The first time a job is opened, record the posting date.
        $postedAt = $job['posted_at'] ?? null;
        if ($in['status'] === 'open' && !$postedAt) {
            $postedAt = now();
        }
        $params = [$in['title'], $slug, $in['location'], $in['employment_type'], $in['description'], $in['status'], $postedAt];
        if ($job) {
            db_run('UPDATE jobs SET title = ?, slug = ?, location = ?, employment_type = ?, description = ?, status = ?, posted_at = ? WHERE id = ?', array_merge($params, [$id]));
        } else {
            db_run('INSERT INTO jobs (title, slug, location, employment_type, description, status, posted_at, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())', $params);
            $id = (int) db()->lastInsertId();
        }
        log_activity(($job ? 'Updated' : 'Added') . ' job ' . mb_substr($in['title'], 0, 40) . ' (' . JOB_STATUSES[$in['status']] . ')', 'job', $id);
        flash('success', $in['status'] === 'open' ? 'Saved — this job is live on the Careers page.' : 'Saved. This job is not shown on the website (status: ' . JOB_STATUSES[$in['status']] . ').');
        redirect(admin_url('job', ['id' => $id]));
    }
    $job = array_merge($job ?? ['id' => 0, 'posted_at' => null], $in);
}

$j = $job ?? ['id' => 0, 'title' => '', 'slug' => '', 'location' => 'Cartersville, GA', 'employment_type' => 'Full-time', 'description' => '', 'status' => 'draft', 'posted_at' => null];
$isNew = empty($j['id']);
$err = fn(string $k) => isset($errors[$k]) ? '<p class="field-error">' . e($errors[$k]) . '</p>' : '';

admin_start($isNew ? 'Add job' : 'Edit job', 'jobs');
?>
        <p class="back"><a href="<?= e(admin_url('jobs')) ?>">← All jobs</a></p>
<?php if ($errors): ?>
        <p class="notice notice--error" role="alert">Please fix the highlighted fields below.</p>
<?php endif; ?>
        <form method="post" class="edit-form">
          <?= csrf_field() ?>
          <div class="detail-grid">
            <section class="panel">
              <div class="panel-head"><h2>Job posting</h2>
<?php if (!$isNew && $j['status'] === 'open'): ?>
                <a class="panel-link" href="/careers/<?= e($j['slug']) ?>" target="_blank" rel="noopener">View on site <?= admin_icon('external') ?></a>
<?php endif; ?>
              </div>
              <div class="form-grid">
                <div class="field field--full<?= isset($errors['title']) ? ' has-error' : '' ?>">
                  <label for="j-title">Job title</label>
                  <input id="j-title" name="title" type="text" maxlength="200" required value="<?= e($j['title']) ?>" data-slug-source="#j-slug" placeholder="e.g. Equipment Operator">
                  <?= $err('title') ?>
                </div>
                <div class="field field--full">
                  <label for="j-slug">Web address</label>
                  <div class="input-prefix"><span>/careers/</span><input id="j-slug" name="slug" type="text" maxlength="100" pattern="[a-z0-9\-]*" value="<?= e($j['slug']) ?>"<?= $isNew ? ' data-auto-slug' : '' ?>></div>
                  <p class="hint">Filled in from the title. Lowercase letters, numbers, and dashes only.</p>
                </div>
                <div class="field<?= isset($errors['location']) ? ' has-error' : '' ?>">
                  <label for="j-location">Location</label>
                  <input id="j-location" name="location" type="text" maxlength="120" value="<?= e($j['location']) ?>" placeholder="Cartersville, GA">
                  <?= $err('location') ?>
                </div>
                <div class="field">
                  <label for="j-type">Employment type</label>
                  <select id="j-type" name="employment_type">
<?php foreach (EMPLOYMENT_TYPES as $type): ?>
                    <option<?= sel($j['employment_type'] === $type) ?>><?= e($type) ?></option>
<?php endforeach; ?>
                  </select>
                </div>
                <div class="field field--full<?= isset($errors['description']) ? ' has-error' : '' ?>">
                  <label for="j-description">Description</label>
                  <textarea id="j-description" name="description" rows="14" maxlength="10000"><?= e((string) $j['description']) ?></textarea>
                  <p class="hint">Plain text. Leave a blank line between paragraphs. Start a line with “- ” (dash and space) to make a bullet point.</p>
                  <?= $err('description') ?>
                </div>
              </div>
            </section>

            <div class="side-stack">
              <section class="panel">
                <div class="panel-head"><h2>Status</h2></div>
                <fieldset class="radio-list">
                  <legend class="visually-hidden">Status</legend>
                  <label class="check"><input type="radio" name="status" value="draft"<?= chk($j['status'] === 'draft') ?>> <span><strong>Draft</strong><span class="hint">Not on the website yet.</span></span></label>
                  <label class="check"><input type="radio" name="status" value="open"<?= chk($j['status'] === 'open') ?>> <span><strong>Open</strong><span class="hint">Shown on the Careers page and accepting applications.</span></span></label>
                  <label class="check"><input type="radio" name="status" value="closed"<?= chk($j['status'] === 'closed') ?>> <span><strong>Closed</strong><span class="hint">Removed from the website. Applications are kept.</span></span></label>
                </fieldset>
<?php if ($j['posted_at']): ?>
                <p class="hint">First posted <?= e(fmt_date($j['posted_at'], false)) ?>.</p>
<?php endif; ?>
                <div class="button-row">
                  <button class="btn btn--primary" type="submit"><?= $isNew ? 'Add job' : 'Save changes' ?></button>
                </div>
              </section>
<?php if (!$isNew): ?>
              <section class="panel">
                <div class="panel-head"><h2>Applications</h2></div>
                <p><a href="<?= e(admin_url('applications', ['job' => $j['id']])) ?>"><?= $appCount ?> <?= $appCount === 1 ? 'application' : 'applications' ?> for this job</a></p>
              </section>
<?php endif; ?>
            </div>
          </div>
        </form>
<?php if (!$isNew): ?>
<?php if ($appCount === 0): ?>
        <form method="post" class="danger-zone" data-confirm="Delete the job “<?= e($j['title']) ?>”? This cannot be undone.">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="delete">
          <button class="btn btn--danger btn--sm" type="submit">Delete job</button>
        </form>
<?php else: ?>
        <p class="hint danger-zone">This job has applications, so it can’t be deleted. Set it to <strong>Closed</strong> to take it off the website.</p>
<?php endif; ?>
<?php endif; ?>
<?php admin_end(); ?>
