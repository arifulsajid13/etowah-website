<?php
defined('ETOWAH_ADMIN') || exit;
$fields = [
    'contact_name'   => ['Contact name', 120, 'Shown in the contact band, footer, and Contact page. Leave empty to hide the name there.'],
    'phone'          => ['Phone', 40, 'Shown everywhere on the site, e.g. (470) 982-4069. Phone links are created automatically. The Contact page’s Google description (Pages & SEO) mentions the number too — update it there if it changes.'],
    'email'          => ['Email', 190, 'The public email address shown on the site.'],
    'city_state'     => ['City and state', 120, 'e.g. Cartersville, Georgia'],
    'license_text'   => ['License text', 160, 'e.g. Licensed in the State of Georgia'],
    'license_number' => ['License number', 40, ''],
    'markets_line'   => ['Markets line (footer)', 160, 'Short line under the logo in the footer.'],
];
$optional = ['contact_name'];
$errors = [];
$values = settings_all();

if (is_post()) {
    require_post();
    $new = [];
    foreach ($fields as $key => [$label, $max]) {
        $new[$key] = post_str($key);
        if (str_length($new[$key]) > $max) {
            $errors[$key] = $label . ' can be up to ' . $max . ' characters.';
        } elseif ($new[$key] === '' && !in_array($key, $optional, true)) {
            $errors[$key] = $label . ' is required (up to ' . $max . ' characters).';
        }
    }
    if (!isset($errors['email']) && !valid_email($new['email'])) {
        $errors['email'] = 'Enter a valid email address.';
    }
    for ($i = 1; $i <= 4; $i++) {
        foreach (['label', 'value'] as $part) {
            $k = "stat{$i}_{$part}";
            $new[$k] = post_str($k);
            if ($new[$k] === '' || str_length($new[$k]) > 40) {
                $errors[$k] = 'Required, up to 40 characters.';
            }
        }
    }
    // Contact inquiries need at least one address; job applications may be empty (they then use the first list).
    foreach (['notify_emails' => true, 'notify_emails_jobs' => false] as $key => $required) {
        $emails = array_values(array_filter(array_map('trim', explode(',', post_str($key))), 'strlen'));
        $bad = array_filter($emails, fn($a) => !valid_email($a));
        if (!$emails && $required) {
            $errors[$key] = 'Add at least one email address to receive notifications.';
        } elseif ($bad) {
            $errors[$key] = 'These addresses are not valid: ' . implode(', ', $bad);
        } elseif (count($emails) > 10) {
            $errors[$key] = 'Use 10 addresses or fewer.';
        }
        $new[$key] = implode(', ', array_unique($emails));
    }

    // Accept either the bare code or the whole <meta> tag Google shows.
    $verification = post_str('google_site_verification');
    if (preg_match('/content=["\']([^"\']+)["\']/', $verification, $m)) {
        $verification = $m[1];
    }
    if ($verification !== '' && !preg_match('/^[A-Za-z0-9_\-]{10,100}$/', $verification)) {
        $errors['google_site_verification'] = 'That doesn’t look like a Google verification code. Paste only the code (letters, numbers, - and _).';
    }
    $new['google_site_verification'] = $verification;

    // Site URL: scheme + host (+ optional port), no folder, no trailing slash.
    $siteUrl = rtrim(post_str('site_url'), '/');
    if (!preg_match('#^https?://[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)*(:\d{1,5})?$#i', $siteUrl)) {
        $errors['site_url'] = 'Enter the address like https://etowahconstruction.com — no folder and no slash at the end.';
    }
    $new['site_url'] = strtolower($siteUrl);
    $new['hide_from_search'] = isset($_POST['hide_from_search']) ? '1' : '0';

    if (!$errors) {
        if ($new['hide_from_search'] !== setting('hide_from_search')) {
            log_activity($new['hide_from_search'] === '1' ? 'Hid the site from search engines' : 'Made the site visible to search engines', 'settings');
        }
        settings_save($new);
        log_activity('Updated site settings', 'settings');
        flash('success', 'Settings saved. Changes are live on every page.');
        redirect(admin_url('settings'));
    }
    $values = array_merge($values, $new);
}

// System health
$logFile = storage_path('logs/php-error.log');
$logLines = [];
if (is_readable($logFile)) {
    $size = filesize($logFile);
    $fh = fopen($logFile, 'r');
    fseek($fh, max(0, $size - 20000));
    $logLines = array_slice(array_filter(explode("\n", (string) stream_get_contents($fh)), 'strlen'), -20);
    fclose($fh);
}
$dbOk = true;
try {
    db_value('SELECT 1');
} catch (Throwable $e) {
    $dbOk = false;
}
$checks = [
    ['PHP version', PHP_VERSION, version_compare(PHP_VERSION, '8.1.0', '>=')],
    ['WebP image support', function_exists('imagewebp') ? 'Available' : 'Missing — photos are saved as JPG only', function_exists('imagewebp')],
    ['Database', $dbOk ? 'Connected' : 'Not reachable', $dbOk],
    ['Private storage writable', is_writable(storage_path()) ? 'Yes' : 'No', is_writable(storage_path())],
    ['Uploads folder writable', is_writable(ROOT . '/uploads') ? 'Yes' : 'No', is_writable(ROOT . '/uploads')],
    ['Email sending', ['smtp' => 'SMTP (' . config('mail.host', '') . ')', 'mail' => 'PHP mail()', 'log' => 'Test log only'][config('mail.transport', 'mail')] ?? 'PHP mail()', config('mail.transport') !== 'log'],
];
$err = fn(string $k) => isset($errors[$k]) ? '<p class="field-error">' . e($errors[$k]) . '</p>' : '';
$cls = fn(string $k) => isset($errors[$k]) ? ' has-error' : '';

admin_start('Site Settings', 'settings');
?>
        <?= settings_tabs('settings') ?>
<?php if ($errors): ?>
        <p class="notice notice--error" role="alert">Please fix the highlighted fields below.</p>
<?php endif; ?>
        <form method="post" class="edit-form">
          <?= csrf_field() ?>
          <div class="detail-grid">
            <div class="side-stack">
              <section class="panel">
                <div class="panel-head"><h2>Contact details</h2></div>
                <div class="form-grid">
<?php foreach ($fields as $key => [$label, $max, $hint]): ?>
                  <div class="field<?= $cls($key) ?>">
<?php $isOptional = in_array($key, $optional, true); ?>
                    <label for="s-<?= $key ?>"><?= e($label) ?><?= $isOptional ? ' <span class="optional">(optional)</span>' : '' ?></label>
                    <input id="s-<?= $key ?>" name="<?= $key ?>" type="<?= $key === 'email' ? 'email' : 'text' ?>" maxlength="<?= $max ?>"<?= $isOptional ? '' : ' required' ?> value="<?= e($values[$key] ?? '') ?>">
<?php if ($hint !== ''): ?>
                    <p class="hint"><?= e($hint) ?></p>
<?php endif; ?>
                    <?= $err($key) ?>
                  </div>
<?php endforeach; ?>
                </div>
              </section>

              <section class="panel">
                <div class="panel-head"><h2>Homepage highlights</h2></div>
                <p class="hint">The four figures in the dark bar under the homepage photo. The icons stay the same.</p>
                <div class="stat-fields">
<?php for ($i = 1; $i <= 4; $i++): ?>
                  <fieldset class="stat-field">
                    <legend>Highlight <?= $i ?></legend>
                    <div class="field<?= $cls("stat{$i}_label") ?>">
                      <label for="s-stat<?= $i ?>-label">Label</label>
                      <input id="s-stat<?= $i ?>-label" name="stat<?= $i ?>_label" type="text" maxlength="40" required value="<?= e($values["stat{$i}_label"] ?? '') ?>">
                      <?= $err("stat{$i}_label") ?>
                    </div>
                    <div class="field<?= $cls("stat{$i}_value") ?>">
                      <label for="s-stat<?= $i ?>-value">Value</label>
                      <input id="s-stat<?= $i ?>-value" name="stat<?= $i ?>_value" type="text" maxlength="40" required value="<?= e($values["stat{$i}_value"] ?? '') ?>">
                      <?= $err("stat{$i}_value") ?>
                    </div>
                  </fieldset>
<?php endfor; ?>
                </div>
              </section>
            </div>

            <div class="side-stack">
              <section class="panel">
                <div class="panel-head"><h2>Notifications</h2></div>
                <div class="field<?= $cls('notify_emails') ?>">
                  <label for="s-notify">Send contact inquiries to</label>
                  <textarea id="s-notify" name="notify_emails" rows="3" required><?= e($values['notify_emails'] ?? '') ?></textarea>
                  <p class="hint">One or more email addresses, separated by commas.</p>
                  <?= $err('notify_emails') ?>
                </div>
                <div class="field<?= $cls('notify_emails_jobs') ?>">
                  <label for="s-notify-jobs">Send job applications to <span class="optional">(optional)</span></label>
                  <textarea id="s-notify-jobs" name="notify_emails_jobs" rows="3"><?= e($values['notify_emails_jobs'] ?? '') ?></textarea>
                  <p class="hint">One or more email addresses, separated by commas. Leave empty to send applications to the contact inquiry addresses.</p>
                  <?= $err('notify_emails_jobs') ?>
                </div>
              </section>
              <section class="panel">
                <div class="panel-head"><h2>Website address and search engines</h2></div>
                <div class="field<?= $cls('site_url') ?>">
                  <label for="s-site-url">Site URL</label>
                  <input id="s-site-url" name="site_url" type="url" maxlength="200" required value="<?= e($values['site_url'] !== '' ? $values['site_url'] : rtrim((string) config('site_url', ''), '/')) ?>" placeholder="https://etowahconstruction.com">
                  <p class="hint">The address of this website. Used for links in emails, Google (canonical links, sitemap, robots.txt), and sharing previews. Change it here when you move from the temporary domain to the real one.</p>
                  <?= $err('site_url') ?>
                </div>
                <label class="check"><input type="checkbox" name="hide_from_search" value="1"<?= chk(($values['hide_from_search'] ?? '1') === '1') ?>> <span><strong>Hide site from search engines</strong><span class="hint">Keep this on while showing a demo on a temporary domain. Turn it off when the site goes live on the real domain, or Google won’t list it.</span></span></label>
              </section>
              <section class="panel">
                <div class="panel-head"><h2>Google Search Console</h2></div>
                <div class="field<?= $cls('google_site_verification') ?>">
                  <label for="s-gsv">Verification code <span class="optional">(optional)</span></label>
                  <input id="s-gsv" name="google_site_verification" type="text" maxlength="200" value="<?= e($values['google_site_verification'] ?? '') ?>">
                  <p class="hint">In Search Console choose “HTML tag” and paste the code (or the whole tag). Leave empty if you don’t use it.</p>
                  <?= $err('google_site_verification') ?>
                </div>
              </section>
              <div class="button-row button-row--sticky">
                <button class="btn btn--primary" type="submit">Save settings</button>
              </div>

              <section class="panel">
                <div class="panel-head"><h2>System</h2></div>
                <ul class="checks">
<?php foreach ($checks as [$name, $value, $ok]): ?>
                  <li class="<?= $ok ? 'is-ok' : 'is-bad' ?>"><span class="check-dot" aria-hidden="true"></span><span class="check-name"><?= e($name) ?></span><span class="check-value"><?= e($value) ?></span></li>
<?php endforeach; ?>
                </ul>
                <h3 class="subhead">Recent errors (last 20 lines)</h3>
<?php if ($logLines): ?>
                <pre class="log"><?= e(implode("\n", $logLines)) ?></pre>
<?php else: ?>
                <p class="hint">No errors logged. 👍</p>
<?php endif; ?>
              </section>
            </div>
          </div>
        </form>
<?php admin_end(); ?>
