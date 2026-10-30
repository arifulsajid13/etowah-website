<?php
defined('ETOWAH_ADMIN') || exit;
$self = admin_url('payments');
$errors = [];
$values = settings_all();
// Secret fields: [setting key, label, expected prefix pattern, hint]
$secretFields = [
    'stripe_secret_key_test'     => ['Secret key (test)', '/^(sk|rk)_test_[A-Za-z0-9]{10,}$/', 'Starts with sk_test_'],
    'stripe_secret_key_live'     => ['Secret key (live)', '/^(sk|rk)_live_[A-Za-z0-9]{10,}$/', 'Starts with sk_live_'],
    'stripe_webhook_secret_test' => ['Webhook signing secret (test)', '/^whsec_[A-Za-z0-9]{10,}$/', 'Starts with whsec_'],
    'stripe_webhook_secret_live' => ['Webhook signing secret (live)', '/^whsec_[A-Za-z0-9]{10,}$/', 'Starts with whsec_'],
    'authnet_transaction_key'    => ['Transaction Key', '/^[A-Za-z0-9]{16}$/', '16 letters and numbers'],
    'authnet_signature_key'      => ['Signature Key', '/^[A-Fa-f0-9]{128}$/', '128 characters (0–9, A–F)'],
];

if (is_post()) {
    require_post();
    $action = post_str('action');

    if ($action === 'test_stripe' || $action === 'test_authnet') {
        try {
            $message = $action === 'test_stripe'
                ? stripe_test_connection(stripe_secret_key())
                : authnet_test_connection(setting('authnet_login_id'), secret_setting('authnet_transaction_key'), authnet_live());
            flash('success', $message);
        } catch (Throwable $e) {
            flash('error', 'Connection failed: ' . $e->getMessage());
        }
        log_activity($action === 'test_stripe' ? 'Tested the Stripe connection' : 'Tested the Authorize.net connection', 'settings');
        redirect($self);
    }

    $new = [
        'invoice_prefix'                => strtoupper(post_str('invoice_prefix')),
        'invoice_next_number'           => post_str('invoice_next_number'),
        'invoice_default_terms'         => array_key_exists(post_str('invoice_default_terms'), INVOICE_TERMS) ? post_str('invoice_default_terms') : 'Net 30',
        'invoice_tax_label'             => mb_substr(post_str('invoice_tax_label'), 0, 40),
        'invoice_payable_to'            => mb_substr(post_str('invoice_payable_to'), 0, 500),
        'invoice_footer_note'           => mb_substr(post_str('invoice_footer_note'), 0, 500),
        'invoice_notify_email'          => post_str('invoice_notify_email'),
        'invoice_reminders_enabled'     => isset($_POST['invoice_reminders_enabled']) ? '1' : '0',
        'invoice_reminder_before_days'  => post_str('invoice_reminder_before_days'),
        'stripe_enabled'                => isset($_POST['stripe_enabled']) ? '1' : '0',
        'stripe_mode'                   => post_str('stripe_mode') === 'live' ? 'live' : 'test',
        'stripe_allow_card'             => isset($_POST['stripe_allow_card']) ? '1' : '0',
        'stripe_allow_ach'              => isset($_POST['stripe_allow_ach']) ? '1' : '0',
        'authnet_enabled'               => isset($_POST['authnet_enabled']) ? '1' : '0',
        'authnet_mode'                  => post_str('authnet_mode') === 'live' ? 'live' : 'sandbox',
        'authnet_login_id'              => post_str('authnet_login_id'),
    ];
    if (!preg_match('/^[A-Z0-9-]{0,10}$/', $new['invoice_prefix'])) {
        $errors['invoice_prefix'] = 'Use up to 10 letters, numbers and dashes, e.g. ECG-';
    }
    if (!ctype_digit($new['invoice_next_number']) || (int) $new['invoice_next_number'] < 1 || strlen($new['invoice_next_number']) > 9) {
        $errors['invoice_next_number'] = 'Enter a whole number, e.g. 1001.';
    }
    $rate = parse_tax_rate(post_str('invoice_default_tax_rate'));
    if ($rate === null) {
        $errors['invoice_default_tax_rate'] = 'Enter a percentage, e.g. 7 or 7.25 (0 for no tax).';
    }
    $new['invoice_default_tax_rate'] = tax_rate_label($rate ?? 0);
    if ($new['invoice_tax_label'] === '') {
        $new['invoice_tax_label'] = 'Tax';
    }
    if ($new['invoice_payable_to'] === '') {
        $errors['invoice_payable_to'] = 'Enter the company name and address shown on invoices.';
    }
    if (!valid_email($new['invoice_notify_email'])) {
        $errors['invoice_notify_email'] = 'Enter a valid email address.';
    }
    if (!ctype_digit($new['invoice_reminder_before_days']) || (int) $new['invoice_reminder_before_days'] > 30) {
        $errors['invoice_reminder_before_days'] = 'Enter a number of days from 0 to 30 (0 = no reminder before the due date).';
    }
    $overdue = array_values(array_unique(array_filter(array_map('trim', explode(',', post_str('invoice_reminder_overdue_days'))), 'strlen')));
    if (array_filter($overdue, fn($d) => !ctype_digit($d) || (int) $d < 1 || (int) $d > 365) || count($overdue) > 10) {
        $errors['invoice_reminder_overdue_days'] = 'Enter up to 10 day numbers separated by commas, e.g. 1, 7, 14.';
    }
    sort($overdue, SORT_NUMERIC);
    $new['invoice_reminder_overdue_days'] = implode(',', $overdue);
    if ($new['authnet_login_id'] !== '' && !preg_match('/^[A-Za-z0-9]{6,25}$/', $new['authnet_login_id'])) {
        $errors['authnet_login_id'] = 'The API Login ID is letters and numbers only.';
    }

    // Secrets: an empty field keeps the saved value; "Remove" clears it.
    $secrets = [];
    foreach ($secretFields as $key => [$label, $pattern, $hint]) {
        $value = post_str($key);
        if (isset($_POST['clear_' . $key])) {
            $secrets[$key] = '';
        } elseif ($value !== '') {
            if (!preg_match($pattern, $value)) {
                $errors[$key] = 'That does not look like a ' . $label . ' (' . $hint . ').';
            } elseif (!crypto_available()) {
                $errors[$key] = 'Keys cannot be saved: the PHP sodium extension is missing on this server.';
            } else {
                $secrets[$key] = secret_encrypt($value);
            }
        }
    }

    if (!$errors) {
        $changed = [];
        foreach (['stripe_enabled' => 'Stripe', 'authnet_enabled' => 'Authorize.net'] as $k => $name) {
            if ($new[$k] !== setting($k)) {
                $changed[] = ($new[$k] === '1' ? 'Turned on ' : 'Turned off ') . $name;
            }
        }
        foreach (['stripe_mode' => 'Stripe', 'authnet_mode' => 'Authorize.net'] as $k => $name) {
            if ($new[$k] !== setting($k)) {
                $changed[] = 'Switched ' . $name . ' to ' . $new[$k] . ' mode';
            }
        }
        foreach ($secrets as $key => $value) {
            $changed[] = ($value === '' ? 'Removed ' : 'Saved ') . $secretFields[$key][0];
        }
        settings_save($new + $secrets);
        settings_all(true);
        foreach ($changed as $line) {
            log_activity(mb_substr($line, 0, 60), 'settings');
        }
        log_activity('Updated payment and invoice settings', 'settings');
        flash('success', 'Payment and invoice settings saved.');
        redirect($self);
    }
    $values = array_merge($values, $new, ['invoice_reminder_overdue_days' => post_str('invoice_reminder_overdue_days')]);
}

$stripeReady = stripe_ready();
$authnetReady = authnet_ready();
$stripeWebhook = site_url('/webhooks/stripe.php');
$authnetWebhook = site_url('/webhooks/authorizenet.php');
$cronCommand = (is_file('/usr/bin/php') ? '/usr/bin/php ' : 'php ') . str_replace('\\', '/', ROOT) . '/cron/invoice-reminders.php';
$err = fn(string $k) => isset($errors[$k]) ? '<p class="field-error">' . e($errors[$k]) . '</p>' : '';
$cls = fn(string $k) => isset($errors[$k]) ? ' has-error' : '';
$secretInput = function (string $key) use ($secretFields, $err, $cls) {
    [$label, , $hint] = $secretFields[$key];
    $saved = secret_setting($key);
    $html = '<div class="field' . $cls($key) . '"><label for="s-' . $key . '">' . e($label) . '</label>'
        . '<input id="s-' . $key . '" name="' . $key . '" type="password" autocomplete="new-password" spellcheck="false" placeholder="' . e($saved !== '' ? 'Saved: ' . secret_mask($saved) : $hint) . '">'
        . '<p class="hint">' . ($saved !== '' ? 'Saved (ending in ' . e(substr($saved, -4)) . '). Leave empty to keep it.' : 'Not saved yet.') . '</p>';
    if ($saved !== '') {
        $html .= '<label class="check check--small"><input type="checkbox" name="clear_' . $key . '" value="1"> <span>Remove this key</span></label>';
    }
    return $html . $err($key) . '</div>';
};
$readyBadge = fn(bool $enabled, bool $ready) => $ready
    ? '<span class="badge badge--ok">Active</span>'
    : ($enabled ? '<span class="badge badge--warning">On, but keys missing</span>' : '<span class="badge badge--closed">Off</span>');

admin_start('Site Settings', 'settings');
?>
        <?= settings_tabs('payments') ?>
<?php if ($errors): ?>
        <p class="notice notice--error" role="alert">Please fix the highlighted fields below.</p>
<?php endif; ?>
<?php if (!crypto_available()): ?>
        <p class="notice notice--error">The PHP sodium extension is not available, so payment keys cannot be stored safely. Ask Hostinger support to enable “sodium” for your PHP version.</p>
<?php endif; ?>
<?php if (!function_exists('curl_init')): ?>
        <p class="notice notice--error">The PHP cURL extension is not available, so online payments cannot work. Enable “curl” in hPanel → PHP Configuration.</p>
<?php endif; ?>
        <form method="post" class="edit-form" autocomplete="off">
          <?= csrf_field() ?>
          <div class="detail-grid detail-grid--even">
            <div class="side-stack">
              <section class="panel">
                <div class="panel-head"><h2>Invoice defaults</h2></div>
                <div class="form-grid">
                  <div class="field<?= $cls('invoice_prefix') ?>">
                    <label for="s-prefix">Number prefix</label>
                    <input id="s-prefix" name="invoice_prefix" type="text" maxlength="10" value="<?= e($values['invoice_prefix']) ?>">
                    <p class="hint">e.g. ECG- gives ECG-1001.</p>
                    <?= $err('invoice_prefix') ?>
                  </div>
                  <div class="field<?= $cls('invoice_next_number') ?>">
                    <label for="s-next">Next number</label>
                    <input id="s-next" name="invoice_next_number" type="text" inputmode="numeric" maxlength="9" value="<?= e($values['invoice_next_number']) ?>">
                    <p class="hint">Used numbers are always skipped, so a number is never reused.</p>
                    <?= $err('invoice_next_number') ?>
                  </div>
                  <div class="field">
                    <label for="s-terms">Default terms</label>
                    <select id="s-terms" name="invoice_default_terms">
<?php foreach (INVOICE_TERMS as $label => $days): ?>
                      <option value="<?= e($label) ?>"<?= sel($values['invoice_default_terms'] === $label) ?>><?= e($label) ?></option>
<?php endforeach; ?>
                    </select>
                  </div>
                  <div class="field<?= $cls('invoice_default_tax_rate') ?>">
                    <label for="s-tax">Default tax rate (%)</label>
                    <input id="s-tax" name="invoice_default_tax_rate" type="text" inputmode="decimal" maxlength="7" value="<?= e($values['invoice_default_tax_rate']) ?>">
                    <?= $err('invoice_default_tax_rate') ?>
                  </div>
                  <div class="field field--full">
                    <label for="s-taxlabel">Tax label</label>
                    <input id="s-taxlabel" name="invoice_tax_label" type="text" maxlength="40" value="<?= e($values['invoice_tax_label']) ?>">
                    <p class="hint">Shown on invoices, e.g. “Sales tax”.</p>
                  </div>
                  <div class="field field--full<?= $cls('invoice_payable_to') ?>">
                    <label for="s-payto">Payable to (company name and address)</label>
                    <textarea id="s-payto" name="invoice_payable_to" rows="4" maxlength="500"><?= e($values['invoice_payable_to']) ?></textarea>
                    <?= $err('invoice_payable_to') ?>
                  </div>
                  <div class="field field--full">
                    <label for="s-footer">Footer note <span class="optional">(optional)</span></label>
                    <textarea id="s-footer" name="invoice_footer_note" rows="2" maxlength="500"><?= e($values['invoice_footer_note']) ?></textarea>
                    <p class="hint">Printed at the bottom of every invoice, e.g. “Thank you for your business.”</p>
                  </div>
                  <div class="field field--full<?= $cls('invoice_notify_email') ?>">
                    <label for="s-notify">Send payment notifications to</label>
                    <input id="s-notify" name="invoice_notify_email" type="email" maxlength="190" value="<?= e($values['invoice_notify_email']) ?>">
                    <?= $err('invoice_notify_email') ?>
                  </div>
                </div>
              </section>

              <section class="panel">
                <div class="panel-head"><h2>Automatic reminders</h2></div>
                <label class="check"><input type="checkbox" name="invoice_reminders_enabled" value="1"<?= chk($values['invoice_reminders_enabled'] === '1') ?>> <span><strong>Email reminders automatically</strong><span class="hint">Needs a daily Cron Job in hPanel (see below).</span></span></label>
                <div class="form-grid">
                  <div class="field<?= $cls('invoice_reminder_before_days') ?>">
                    <label for="s-before">Days before the due date</label>
                    <input id="s-before" name="invoice_reminder_before_days" type="text" inputmode="numeric" maxlength="2" value="<?= e($values['invoice_reminder_before_days']) ?>">
                    <?= $err('invoice_reminder_before_days') ?>
                  </div>
                  <div class="field<?= $cls('invoice_reminder_overdue_days') ?>">
                    <label for="s-overdue">Days after the due date</label>
                    <input id="s-overdue" name="invoice_reminder_overdue_days" type="text" maxlength="60" value="<?= e($values['invoice_reminder_overdue_days']) ?>">
                    <p class="hint">Comma-separated, e.g. 1, 7, 14.</p>
                    <?= $err('invoice_reminder_overdue_days') ?>
                  </div>
                </div>
                <h3 class="subhead">Cron Job (hPanel → Advanced → Cron Jobs)</h3>
                <p class="hint">Choose “Custom”, run it once a day (e.g. every day at 8:00), and use this command:</p>
                <div class="copy-row">
                  <input id="cron-cmd" type="text" value="<?= e($cronCommand) ?>" readonly>
                  <button class="btn btn--ghost btn--sm" type="button" data-copy="cron-cmd"><?= admin_icon('copy') ?><span>Copy</span></button>
                </div>
              </section>
            </div>

            <div class="side-stack">
              <section class="panel">
                <div class="panel-head"><h2>Stripe</h2><?= $readyBadge($values['stripe_enabled'] === '1', $stripeReady) ?></div>
                <label class="check"><input type="checkbox" name="stripe_enabled" value="1"<?= chk($values['stripe_enabled'] === '1') ?>> <span><strong>Accept payments with Stripe</strong><span class="hint">Card and bank (ACH) payments on Stripe’s secure Checkout page.</span></span></label>
                <fieldset class="radio-list">
                  <legend class="label">Mode</legend>
                  <label class="check"><input type="radio" name="stripe_mode" value="test"<?= chk($values['stripe_mode'] !== 'live') ?>> <span>Test (no real money)</span></label>
                  <label class="check"><input type="radio" name="stripe_mode" value="live"<?= chk($values['stripe_mode'] === 'live') ?>> <span>Live</span></label>
                </fieldset>
                <div class="form-grid">
                  <div class="field field--full">
                    <span class="label">Payment methods</span>
                    <label class="check"><input type="checkbox" name="stripe_allow_card" value="1"<?= chk($values['stripe_allow_card'] === '1') ?>> <span>Card</span></label>
                    <label class="check"><input type="checkbox" name="stripe_allow_ach" value="1"<?= chk($values['stripe_allow_ach'] === '1') ?>> <span>Bank account (ACH)</span></label>
                  </div>
                  <?= $secretInput('stripe_secret_key_test') ?>
                  <?= $secretInput('stripe_webhook_secret_test') ?>
                  <?= $secretInput('stripe_secret_key_live') ?>
                  <?= $secretInput('stripe_webhook_secret_live') ?>
                </div>
                <h3 class="subhead">Webhook</h3>
                <p class="hint">In Stripe → Developers → Webhooks, add an endpoint with this URL (once in test mode and once in live mode):</p>
                <div class="copy-row">
                  <input id="stripe-webhook" type="text" value="<?= e($stripeWebhook) ?>" readonly>
                  <button class="btn btn--ghost btn--sm" type="button" data-copy="stripe-webhook"><?= admin_icon('copy') ?><span>Copy</span></button>
                </div>
                <p class="hint">Select these events:</p>
                <ul class="plain-list code-list">
<?php foreach (STRIPE_EVENTS as $event): ?>
                  <li><code><?= e($event) ?></code></li>
<?php endforeach; ?>
                </ul>
              </section>

              <section class="panel">
                <div class="panel-head"><h2>Authorize.net</h2><?= $readyBadge($values['authnet_enabled'] === '1', $authnetReady) ?></div>
                <label class="check"><input type="checkbox" name="authnet_enabled" value="1"<?= chk($values['authnet_enabled'] === '1') ?>> <span><strong>Accept payments with Authorize.net</strong><span class="hint">Card and eCheck payments on Authorize.net’s secure hosted page.</span></span></label>
                <fieldset class="radio-list">
                  <legend class="label">Mode</legend>
                  <label class="check"><input type="radio" name="authnet_mode" value="sandbox"<?= chk($values['authnet_mode'] !== 'live') ?>> <span>Sandbox (test account, no real money)</span></label>
                  <label class="check"><input type="radio" name="authnet_mode" value="live"<?= chk($values['authnet_mode'] === 'live') ?>> <span>Live</span></label>
                </fieldset>
                <div class="form-grid">
                  <div class="field field--full<?= $cls('authnet_login_id') ?>">
                    <label for="s-authnet-login">API Login ID</label>
                    <input id="s-authnet-login" name="authnet_login_id" type="text" maxlength="25" spellcheck="false" value="<?= e($values['authnet_login_id']) ?>">
                    <p class="hint">The sandbox and live accounts have different keys. Enter the ones for the mode above.</p>
                    <?= $err('authnet_login_id') ?>
                  </div>
                  <?= $secretInput('authnet_transaction_key') ?>
                  <?= $secretInput('authnet_signature_key') ?>
                </div>
                <h3 class="subhead">Webhook</h3>
                <p class="hint">In Authorize.net → Account → Webhooks, add an endpoint with this URL:</p>
                <div class="copy-row">
                  <input id="authnet-webhook" type="text" value="<?= e($authnetWebhook) ?>" readonly>
                  <button class="btn btn--ghost btn--sm" type="button" data-copy="authnet-webhook"><?= admin_icon('copy') ?><span>Copy</span></button>
                </div>
                <p class="hint">Select these events:</p>
                <ul class="plain-list code-list">
<?php foreach (AUTHNET_EVENTS as $event): ?>
                  <li><code><?= e($event) ?></code></li>
<?php endforeach; ?>
                </ul>
              </section>

              <div class="button-row button-row--sticky">
                <button class="btn btn--primary" type="submit">Save payment settings</button>
              </div>
            </div>
          </div>
        </form>

        <section class="panel">
          <div class="panel-head"><h2>Test the connection</h2></div>
          <p class="hint">Uses the saved keys for the selected mode. Save your changes first.</p>
          <div class="button-row">
            <form method="post" class="inline-form">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="test_stripe">
              <button class="btn btn--ghost" type="submit">Test Stripe (<?= e(setting('stripe_mode')) ?>)</button>
            </form>
            <form method="post" class="inline-form">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="test_authnet">
              <button class="btn btn--ghost" type="submit">Test Authorize.net (<?= e(setting('authnet_mode')) ?>)</button>
            </form>
          </div>
        </section>
<?php admin_end(); ?>
