<?php
defined('ETOWAH_ADMIN') || exit;
$id = get_int('id');
$project = $id ? db_one('SELECT * FROM projects WHERE id = ?', [$id]) : null;
if ($id && !$project) {
    flash('error', 'That project no longer exists.');
    redirect(admin_url('projects'));
}
$services = [];
if ($project) {
    foreach (db_all('SELECT * FROM project_services WHERE project_id = ?', [$id]) as $row) {
        $services[$row['service_key']] = $row;
    }
}
$errors = [];

if (is_post()) {
    require_post();

    if (post_str('action') === 'delete' && $project) {
        image_delete_upload($project['image_path']);
        db_run('DELETE FROM projects WHERE id = ?', [$id]);
        log_activity('Deleted project ' . mb_substr($project['title'], 0, 40), 'project', $id);
        flash('success', 'Project deleted.');
        redirect(admin_url('projects'));
    }

    $in = [
        'title'           => post_str('title'),
        'slug'            => post_str('slug'),
        'status'          => pick(post_str('status'), ['current' => 1, 'completed' => 1], 'completed'),
        'client_name'     => post_str('client_name'),
        'location'        => post_str('location'),
        'market'          => pick(post_str('market'), MARKETS, 'municipal'),
        'primary_service' => pick(post_str('primary_service'), SERVICES),
        'summary'         => post_str('summary'),
        'description'     => post_str('description'),
        'image_alt'       => post_str('image_alt'),
        'home_position'   => pick(post_str('home_position'), HOME_POSITIONS, 'none'),
        'published'       => isset($_POST['published']) ? 1 : 0,
    ];
    $checked = array_values(array_intersect(array_keys(SERVICES), (array) ($_POST['services'] ?? [])));

    if ($in['title'] === '' || str_length($in['title']) > 200) {
        $errors['title'] = 'Enter a project title (up to 200 characters).';
    }
    if ($in['summary'] === '') {
        $errors['summary'] = 'Write a short summary for the project cards.';
    } elseif (str_length($in['summary']) > 300) {
        $errors['summary'] = 'Keep the summary to 300 characters or fewer.';
    }
    if (str_length($in['client_name']) > 200 || str_length($in['location']) > 120) {
        $errors['location'] = 'Client name or location is too long.';
    }
    if (str_length($in['description']) > 5000) {
        $errors['description'] = 'Keep the full description to 5,000 characters or fewer.';
    }
    if (str_length($in['image_alt']) > 255) {
        $errors['image_alt'] = 'Keep the photo description to 255 characters or fewer.';
    }
    if ($in['primary_service'] === '' && $checked) {
        $in['primary_service'] = $checked[0];
    }

    $upload = null;
    if (!$errors) {
        $upload = admin_image_upload('image', 'projects', 1600);
        if (isset($upload['error'])) {
            $errors['image'] = flash_take()['msg'] ?? 'The photo could not be uploaded.';
            $upload = null;
        }
    }
    $hasImage = $upload || ($project && $project['image_path'] !== '');
    if (!$errors && $hasImage && $in['image_alt'] === '') {
        $errors['image_alt'] = 'Describe the photo in a few words (this helps search engines and screen readers).';
    }
    if (!$errors && $in['published'] && !$hasImage) {
        $errors['image'] = 'Add a photo before showing this project on the website (or untick “Show on website”).';
    }

    if ($errors && $upload) {
        image_delete_upload($upload['path']);
    }

    if (!$errors) {
        $slug = unique_slug('projects', $in['slug'] !== '' ? $in['slug'] : $in['title'], $id);
        $params = [
            $in['title'], $slug, $in['status'], $in['client_name'] ?: null, $in['location'] ?: null, $in['market'],
            $in['primary_service'] ?: null, $in['summary'], $in['description'], $in['image_alt'], $in['home_position'], $in['published'],
        ];
        $pdo = db();
        $pdo->beginTransaction();
        if ($project) {
            db_run('UPDATE projects SET title = ?, slug = ?, status = ?, client_name = ?, location = ?, market = ?, primary_service = ?,
                    summary = ?, description = ?, image_alt = ?, home_position = ?, published = ? WHERE id = ?', array_merge($params, [$id]));
        } else {
            $order = (int) db_value('SELECT COALESCE(MAX(sort_order), 0) + 10 FROM projects');
            db_run('INSERT INTO projects (title, slug, status, client_name, location, market, primary_service, summary, description,
                    image_alt, home_position, published, sort_order, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())', array_merge($params, [$order]));
            $id = (int) $pdo->lastInsertId();
        }
        if ($upload) {
            db_run('UPDATE projects SET image_path = ?, image_width = ?, image_height = ?, is_placeholder_image = 0 WHERE id = ?', [$upload['path'], $upload['width'], $upload['height'], $id]);
            if ($project) {
                image_delete_upload($project['image_path']);
            }
        }
        // Only one project can hold each homepage spot.
        if ($in['home_position'] !== 'none') {
            db_run("UPDATE projects SET home_position = 'none' WHERE home_position = ? AND id <> ?", [$in['home_position'], $id]);
        }
        db_run('DELETE FROM project_services WHERE project_id = ?', [$id]);
        $insert = $pdo->prepare('INSERT INTO project_services (project_id, service_key, sort_order, card_title, card_summary) VALUES (?, ?, ?, ?, ?)');
        foreach ($checked as $service) {
            $order = isset($services[$service]) ? (int) $services[$service]['sort_order']
                : (int) db_value('SELECT COALESCE(MAX(sort_order), 0) + 10 FROM project_services WHERE service_key = ?', [$service]);
            $cardTitle = mb_substr(post_str('card_title_' . $service), 0, 200);
            $cardSummary = mb_substr(post_str('card_summary_' . $service), 0, 300);
            $insert->execute([$id, $service, $order, $cardTitle ?: null, $cardSummary ?: null]);
        }
        $pdo->commit();

        log_activity(($project ? 'Updated' : 'Added') . ' project ' . mb_substr($in['title'], 0, 40) . ($upload ? ' (new photo)' : ''), 'project', $id);
        flash('success', $project ? 'Project saved.' : 'Project added.');
        redirect(admin_url('project', ['id' => $id]));
    }

    // Re-show what was typed.
    $project = array_merge($project ?? ['id' => 0, 'image_path' => '', 'is_placeholder_image' => 1], $in);
    $services = [];
    foreach ($checked as $service) {
        $services[$service] = ['card_title' => post_str('card_title_' . $service), 'card_summary' => post_str('card_summary_' . $service)];
    }
}

$p = $project ?? [
    'id' => 0, 'title' => '', 'slug' => '', 'status' => 'completed', 'client_name' => '', 'location' => '', 'market' => 'commercial',
    'primary_service' => '', 'summary' => '', 'description' => '', 'image_path' => '', 'image_alt' => '', 'is_placeholder_image' => 1,
    'home_position' => 'none', 'published' => 1,
];
$isNew = empty($p['id']);
$homeUsed = [];
foreach (db_all("SELECT id, title, home_position FROM projects WHERE home_position <> 'none'") as $row) {
    if ((int) $row['id'] !== (int) $p['id']) {
        $homeUsed[$row['home_position']] = $row['title'];
    }
}
$err = fn(string $k) => isset($errors[$k]) ? '<p class="field-error">' . e($errors[$k]) . '</p>' : '';

admin_start($isNew ? 'Add project' : 'Edit project', 'projects');
?>
        <p class="back"><a href="<?= e(admin_url('projects')) ?>">← All projects</a></p>
<?php if ($errors): ?>
        <p class="notice notice--error" role="alert">Please fix the highlighted fields below.</p>
<?php endif; ?>
        <form method="post" enctype="multipart/form-data" class="edit-form" data-project-form>
          <?= csrf_field() ?>
          <div class="detail-grid">
            <section class="panel">
              <div class="panel-head"><h2>Project details</h2>
<?php if (!$isNew && $p['published']): ?>
                <a class="panel-link" href="/projects#<?= e($p['slug']) ?>" target="_blank" rel="noopener">View on site <?= admin_icon('external') ?></a>
<?php endif; ?>
              </div>
              <div class="form-grid">
                <div class="field field--full<?= isset($errors['title']) ? ' has-error' : '' ?>">
                  <label for="p-title">Title</label>
                  <input id="p-title" name="title" type="text" maxlength="200" required value="<?= e($p['title']) ?>" data-slug-source="#p-slug">
                  <?= $err('title') ?>
                </div>
                <div class="field field--full">
                  <label for="p-slug">Web address</label>
                  <div class="input-prefix"><span>/projects#</span><input id="p-slug" name="slug" type="text" maxlength="100" pattern="[a-z0-9\-]*" value="<?= e($p['slug']) ?>"<?= $isNew ? ' data-auto-slug' : '' ?>></div>
                  <p class="hint">Filled in from the title. Lowercase letters, numbers, and dashes only. Changing it breaks old links to this project.</p>
                </div>
                <div class="field">
                  <label for="p-status">Status</label>
                  <select id="p-status" name="status">
                    <option value="current"<?= sel($p['status'] === 'current') ?>>Current (underway)</option>
                    <option value="completed"<?= sel($p['status'] === 'completed') ?>>Completed</option>
                  </select>
                </div>
                <div class="field">
                  <label for="p-market">Market</label>
                  <select id="p-market" name="market">
<?php foreach (MARKETS as $k => $label): ?>
                    <option value="<?= $k ?>"<?= sel($p['market'] === $k) ?>><?= $label ?></option>
<?php endforeach; ?>
                  </select>
                  <p class="hint">Lists the project under this market on the Markets page.</p>
                </div>
                <div class="field">
                  <label for="p-client">Client name <span class="optional">(optional)</span></label>
                  <input id="p-client" name="client_name" type="text" maxlength="200" value="<?= e((string) $p['client_name']) ?>">
                  <p class="hint">Shown before the description on the Projects page, e.g. “City of Chattanooga”.</p>
                </div>
                <div class="field<?= isset($errors['location']) ? ' has-error' : '' ?>">
                  <label for="p-location">Location <span class="optional">(optional)</span></label>
                  <input id="p-location" name="location" type="text" maxlength="120" value="<?= e((string) $p['location']) ?>" placeholder="Cartersville, GA">
                  <?= $err('location') ?>
                </div>
                <div class="field field--full<?= isset($errors['summary']) ? ' has-error' : '' ?>">
                  <label for="p-summary">Short summary</label>
                  <textarea id="p-summary" name="summary" rows="3" maxlength="300" required data-counter="300"><?= e($p['summary']) ?></textarea>
                  <p class="hint">One sentence for the homepage and service-page cards. <span class="counter" data-counter-for="p-summary"></span></p>
                  <?= $err('summary') ?>
                </div>
                <div class="field field--full<?= isset($errors['description']) ? ' has-error' : '' ?>">
                  <label for="p-description">Full description</label>
                  <textarea id="p-description" name="description" rows="5" maxlength="5000"><?= e((string) $p['description']) ?></textarea>
                  <p class="hint">Shown on the Projects page.</p>
                  <?= $err('description') ?>
                </div>
              </div>

              <h3 class="subhead">Services</h3>
              <p class="hint">Tick each service page this project should appear on (up to 3 projects show per page).</p>
              <div class="service-list">
<?php foreach (SERVICES as $key => $label): $on = isset($services[$key]); ?>
                <div class="service-item">
                  <label class="check"><input type="checkbox" name="services[]" value="<?= $key ?>"<?= chk($on) ?> data-service-toggle="svc-<?= $key ?>"> <?= e($label) ?></label>
                  <details class="service-custom" id="svc-<?= $key ?>"<?= $on ? '' : ' hidden' ?>>
                    <summary>Customize the card on the <?= e($label) ?> page (optional)</summary>
                    <div class="field">
                      <label for="ct-<?= $key ?>">Card title</label>
                      <input id="ct-<?= $key ?>" name="card_title_<?= $key ?>" type="text" maxlength="200" value="<?= e((string) ($services[$key]['card_title'] ?? '')) ?>" placeholder="Leave empty to use the project title">
                    </div>
                    <div class="field">
                      <label for="cs-<?= $key ?>">Card text</label>
                      <textarea id="cs-<?= $key ?>" name="card_summary_<?= $key ?>" rows="2" maxlength="300" placeholder="Leave empty to use the short summary"><?= e((string) ($services[$key]['card_summary'] ?? '')) ?></textarea>
                    </div>
                  </details>
                </div>
<?php endforeach; ?>
              </div>
              <div class="field">
                <label for="p-primary">Main service</label>
                <select id="p-primary" name="primary_service">
                  <option value="">— First ticked service —</option>
<?php foreach (SERVICES as $key => $label): ?>
                  <option value="<?= $key ?>"<?= sel($p['primary_service'] === $key) ?>><?= e($label) ?></option>
<?php endforeach; ?>
                </select>
                <p class="hint">Shown as the small link tag under the project on the Projects page.</p>
              </div>
            </section>

            <div class="side-stack">
              <section class="panel">
                <div class="panel-head"><h2>Photo</h2>
<?php if ($p['image_path'] !== '' && $p['is_placeholder_image']): ?>
                  <span class="badge badge--warning">Placeholder</span>
<?php endif; ?>
                </div>
<?php if ($p['image_path'] !== ''): ?>
                <img class="preview" src="<?= e(media_url($p['image_path'])) ?>" alt="" width="400" height="267">
<?php endif; ?>
<?php if ($p['image_path'] !== '' && $p['is_placeholder_image']): ?>
                <p class="hint">This is a placeholder photo. Replace it with a real photo of this project.</p>
<?php endif; ?>
                <div class="field<?= isset($errors['image']) ? ' has-error' : '' ?>">
                  <label for="p-image"><?= $p['image_path'] !== '' ? 'Replace photo' : 'Upload photo' ?></label>
                  <input id="p-image" name="image" type="file" accept="image/jpeg,image/png,image/webp">
                  <p class="hint">JPG, PNG, or WebP up to 10 MB. Landscape photos at least 1200 px wide work best; they are shown in a 3:2 frame.</p>
                  <?= $err('image') ?>
                </div>
                <div class="field<?= isset($errors['image_alt']) ? ' has-error' : '' ?>">
                  <label for="p-alt">Photo description (alt text)</label>
                  <input id="p-alt" name="image_alt" type="text" maxlength="255" value="<?= e($p['image_alt']) ?>" placeholder="e.g. Excavator digging a utility trench beside a road">
                  <p class="hint">Describe what the photo shows, for search engines and visitors using screen readers.</p>
                  <?= $err('image_alt') ?>
                </div>
              </section>

              <section class="panel">
                <div class="panel-head"><h2>Where it shows</h2></div>
                <div class="field">
                  <label for="p-home">Homepage</label>
                  <select id="p-home" name="home_position" data-home-used="<?= e(json_encode($homeUsed, JSON_UNESCAPED_UNICODE)) ?>">
<?php foreach (HOME_POSITIONS as $k => $label): ?>
                    <option value="<?= $k ?>"<?= sel($p['home_position'] === $k) ?>><?= e($label) ?><?= isset($homeUsed[$k]) ? ' — now: ' . e($homeUsed[$k]) : '' ?></option>
<?php endforeach; ?>
                  </select>
                  <p class="hint">The homepage “Selected Projects” area has two cards and one wide banner.</p>
                </div>
                <label class="check"><input type="checkbox" name="published" value="1"<?= chk((bool) $p['published']) ?>> Show on website</label>
                <div class="button-row">
                  <button class="btn btn--primary" type="submit"><?= $isNew ? 'Add project' : 'Save changes' ?></button>
                </div>
              </section>
            </div>
          </div>
        </form>
<?php if (!$isNew): ?>
        <form method="post" class="danger-zone" data-confirm="Delete “<?= e($p['title']) ?>”? It will be removed from every page of the website. This cannot be undone.">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="delete">
          <button class="btn btn--danger btn--sm" type="submit">Delete project</button>
        </form>
<?php endif; ?>
<?php admin_end(); ?>
