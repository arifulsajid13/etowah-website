<?php
/*
 * Public invoice pages (all noindex, never in the sitemap):
 *   /pay                   lookup form (invoice number + email)
 *   /pay/{token}           the invoice with its pay buttons
 *   /pay/{token}/print     printable version ("Save as PDF" in the browser)
 *   /pay/{token}/return    where the gateways send the customer back; it only reads the invoice
 *   /pay/{token}/checkout  POST: start a Stripe Checkout or Authorize.net Accept Hosted payment
 */
require __DIR__ . '/app/bootstrap.php';
require APP . '/auth.php';
require APP . '/ratelimit.php';
require APP . '/payments.php';

header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store, private');
header('X-LiteSpeed-Cache-Control: no-cache');

/** Strict CSP; the only outside destinations are the two hosted payment pages. */
function pay_headers(): void
{
    header('Referrer-Policy: same-origin');
    header('X-Frame-Options: DENY');
    header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data:; connect-src 'self'; "
        . "form-action 'self' https://checkout.stripe.com https://accept.authorize.net https://test.authorize.net; frame-ancestors 'none'; base-uri 'self'; object-src 'none'");
}

function pay_start(string $title): void
{
    pay_headers();
    layout_start('home', [
        'title'       => $title . ' | ' . setting('company_name'),
        'description' => 'Invoice payment',
        'active'      => '',
        'noindex'     => true,
        'styles'      => ['css/pay.css'],
        'scripts'     => ['js/pay.js'],
    ]);
}

function pay_end(): void
{
    layout_end(['footer' => 'compact', 'footer_style' => false]);
}

function pay_message_page(int $code, string $title, string $text): void
{
    http_response_code($code);
    pay_start($title);
    ?>
  <main id="main" tabindex="-1">
    <section class="section section--alt pay-section">
      <div class="container pay-narrow">
        <div class="form-card">
          <h1 class="pay-heading"><?= e($title) ?></h1>
          <p><?= e($text) ?></p>
        </div>
      </div>
    </section>
  </main>
<?php
    pay_end();
    exit;
}

function pay_contact_line(): string
{
    return 'Please contact us at ' . setting('phone') . '.';
}

// Every /pay request counts toward a per-visitor limit.
if (!rate_limit_allows('pay', 120, 600)) {
    pay_message_page(429, 'Too many requests', 'Please wait a few minutes and try again. ' . pay_contact_line());
}
rate_limit_record('pay');

$token = get_str('token');
$view = get_str('view');

/* Lookup form ------------------------------------------------------------------- */
if ($token === '' && $view === '') {
    csrf_token();
    $error = '';
    $number = '';
    $email = '';
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        $number = mb_substr(post_str('number'), 0, 40);
        $email = mb_substr(post_str('email'), 0, 190);
        $allowed = rate_limit_allows('pay_lookup', 10, 900);
        rate_limit_record('pay_lookup');
        $match = null;
        if ($allowed && form_guard_passes('pay-lookup') && $number !== '' && valid_email($email)) {
            $match = db_one("SELECT token FROM invoices WHERE number = ? AND customer_email = ? AND status <> 'draft'", [$number, $email]);
        }
        if ($match) {
            redirect('/pay/' . $match['token'], 303);
        }
        // One message for every failure (wrong number, wrong email, rate limit, bot check), so numbers cannot be probed.
        $error = 'We couldn’t find an invoice with that number and email address. Check both and try again, or contact us at ' . setting('phone') . '.';
    }
    pay_start('Pay an Invoice');
    ?>
  <main id="main" tabindex="-1">
    <section class="section section--alt pay-section">
      <div class="container pay-narrow">
        <div class="form-card">
          <p class="eyebrow">Online payment</p>
          <h1 class="pay-heading">Pay an invoice</h1>
          <p>Enter the invoice number and the email address the invoice was sent to.</p>
<?php if ($error !== ''): ?>
          <div class="form-status is-error" role="alert"><?= e($error) ?></div>
<?php endif; ?>
          <form method="post" action="/pay" class="pay-lookup">
<?= form_guard_fields('pay-lookup') ?>
            <div class="form-grid">
              <div class="field field--full">
                <label for="pay-number">Invoice number<span class="req" aria-hidden="true">*</span></label>
                <input id="pay-number" name="number" type="text" maxlength="40" required autocomplete="off" placeholder="<?= e(setting('invoice_prefix')) ?>1001" value="<?= e($number) ?>">
              </div>
              <div class="field field--full">
                <label for="pay-email">Email address<span class="req" aria-hidden="true">*</span></label>
                <input id="pay-email" name="email" type="email" maxlength="190" required autocomplete="email" inputmode="email" value="<?= e($email) ?>">
              </div>
            </div>
            <div class="form-actions">
              <button class="btn btn-primary" type="submit">Find invoice</button>
            </div>
          </form>
        </div>
      </div>
    </section>
  </main>
<?php
    pay_end();
    exit;
}

/* Invoice by token -------------------------------------------------------------- */
$invoice = invoice_by_token($token);
admin_session();
$isAdmin = current_user() !== null;
// Drafts are private until sent; a signed-in dashboard user can still preview and print them.
if (!$invoice || ($invoice['status'] === 'draft' && !$isAdmin) || !in_array($view, ['', 'print', 'return', 'checkout'], true)) {
    not_found();
}
$items = invoice_items((int) $invoice['id']);

/* Start a payment (POST) ---------------------------------------------------------- */
if ($view === 'checkout') {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        redirect('/pay/' . $invoice['token'], 303);
    }
    $choice = post_str('gateway');
    $ok = csrf_valid() && rate_limit_allows('pay_checkout', 10, 900);
    rate_limit_record('pay_checkout');
    $pending = (int) db_value("SELECT COUNT(*) FROM payments WHERE invoice_id = ? AND status = 'pending' AND gateway_txn IS NOT NULL", [$invoice['id']]);
    if (!$ok || !invoice_can_pay_online($invoice) || $pending > 0) {
        redirect('/pay/' . $invoice['token'] . '?e=1', 303);
    }
    try {
        if (in_array($choice, ['stripe_card', 'stripe_ach'], true) && in_array(substr($choice, 7), stripe_methods(), true)) {
            redirect(stripe_start_checkout($invoice, substr($choice, 7)), 303);
        }
        if ($choice === 'authnet' && authnet_ready()) {
            [$formUrl, $hostedToken] = authnet_start_checkout($invoice);
            pay_start('Continue to payment');
            ?>
  <main id="main" tabindex="-1">
    <section class="section section--alt pay-section">
      <div class="container pay-narrow">
        <div class="form-card">
          <h1 class="pay-heading">Taking you to the secure payment page…</h1>
          <p>You are being sent to Authorize.net to pay invoice <?= e($invoice['number']) ?>. If nothing happens, select the button below.</p>
          <form method="post" action="<?= e($formUrl) ?>" data-auto-submit>
            <input type="hidden" name="token" value="<?= e($hostedToken) ?>">
            <button class="btn btn-primary" type="submit">Continue to secure payment</button>
          </form>
        </div>
      </div>
    </section>
  </main>
<?php
            pay_end();
            exit;
        }
    } catch (Throwable $e) {
        log_error('Checkout for invoice ' . $invoice['number'] . ' could not start: ' . $e->getMessage());
    }
    redirect('/pay/' . $invoice['token'] . '?e=1', 303);
}

/* Printable invoice ------------------------------------------------------------- */
if ($view === 'print') {
    pay_headers();
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <title>Invoice <?= e($invoice['number']) ?> · <?= e(setting('company_name')) ?></title>
  <link rel="icon" type="image/png" href="/assets/images/logo/logo-icon.png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Archivo:wght@700;800&family=Inter:wght@400;500;600&display=swap">
  <link rel="stylesheet" href="<?= e(asset('css/pay.css')) ?>">
  <script src="<?= e(asset('js/pay.js')) ?>" defer></script>
</head>
<body class="print-page">
  <div class="print-toolbar">
    <a href="/pay/<?= e($invoice['token']) ?>">← Back to invoice</a>
    <button type="button" class="print-btn" data-print>Print / Save as PDF</button>
  </div>
<?php require ROOT . '/partials/invoice-doc.php'; ?>
</body>
</html>
<?php
    exit;
}

/* Return from the gateway: read only -------------------------------------------- */
if ($view === 'return') {
    $round = max(0, min(6, (int) get_str('r')));
    $paid = $invoice['status'] === 'paid';
    if (!$paid && $round < 6) {
        // The webhook usually lands within seconds; look again a few times.
        header('Refresh: 5; url=/pay/' . $invoice['token'] . '/return?r=' . ($round + 1));
    }
    pay_start($paid ? 'Payment received' : 'Payment processing');
    ?>
  <main id="main" tabindex="-1">
    <section class="section section--alt pay-section">
      <div class="container pay-narrow">
        <div class="form-card pay-result pay-result--<?= $paid ? 'paid' : 'processing' ?>" role="status">
          <p class="eyebrow">Invoice <?= e($invoice['number']) ?></p>
<?php if ($paid): ?>
          <h1 class="pay-heading">Payment received</h1>
          <p>Thank you. Invoice <?= e($invoice['number']) ?> is paid in full. A receipt has been emailed to you.</p>
<?php else: ?>
          <h1 class="pay-heading">Payment is processing</h1>
          <p>Thank you. Payment is processing. You’ll get an email when it clears.</p>
          <p class="pay-small">Card payments usually confirm within a minute. Bank (ACH) payments take a few business days.</p>
<?php endif; ?>
          <div class="form-actions"><a class="btn btn-primary" href="/pay/<?= e($invoice['token']) ?>">View invoice</a></div>
        </div>
      </div>
    </section>
  </main>
<?php
    pay_end();
    exit;
}

/* The invoice page ---------------------------------------------------------------- */
if (!$isAdmin && $invoice['viewed_at'] === null && $invoice['status'] !== 'draft') {
    // First view by the customer. Dashboard users previewing the page do not count.
    db_run("UPDATE invoices SET viewed_at = NOW(), status = IF(status = 'sent', 'viewed', status) WHERE id = ? AND viewed_at IS NULL", [$invoice['id']]);
    log_activity('Invoice viewed by the customer', 'invoice', (int) $invoice['id'], null);
    $invoice = invoice_get((int) $invoice['id']);
}
csrf_token();

$balance = invoice_balance($invoice);
$processing = (int) db_value("SELECT COUNT(*) FROM payments WHERE invoice_id = ? AND status = 'pending' AND gateway_txn IS NOT NULL", [$invoice['id']]) > 0;
$canPay = invoice_can_pay_online($invoice) && !$processing;
$options = [];
if ($canPay) {
    $stripe = $balance >= STRIPE_MIN_CENTS ? stripe_methods() : [];
    if (in_array('ach', $stripe, true)) {
        $options['stripe_ach'] = 'Pay by bank (ACH)';
    }
    if (in_array('card', $stripe, true)) {
        $options['stripe_card'] = 'Pay by card';
    }
    if (authnet_ready()) {
        $options['authnet'] = 'Pay with Authorize.net';
    }
}
$statusNotes = [
    'paid'       => 'This invoice is paid in full. Thank you.',
    'void'       => 'This invoice has been voided and cannot be paid.',
    'refunded'   => 'This invoice was refunded.',
    'processing' => 'A payment for this invoice is processing. You’ll get an email when it clears.',
];
$note = $statusNotes[$invoice['status']] ?? ($processing ? $statusNotes['processing'] : '');

pay_start('Invoice ' . $invoice['number']);
?>
  <main id="main" tabindex="-1">
    <section class="section section--alt pay-section">
      <div class="container pay-layout">
<?php if ($isAdmin && $invoice['status'] === 'draft'): ?>
        <p class="form-status pay-full">Preview of a draft. Customers cannot open this page until the invoice is sent.</p>
<?php endif; ?>
<?php if (get_str('e') === '1'): ?>
        <div class="form-status is-error pay-full" role="alert">We couldn’t start the payment. Please try again, or contact us at <?= e(setting('phone')) ?>.</div>
<?php endif; ?>
<?php require ROOT . '/partials/invoice-doc.php'; ?>

        <aside class="pay-panel" aria-labelledby="pay-panel-title">
          <h2 class="pay-panel-title" id="pay-panel-title">Balance due</h2>
          <p class="pay-amount"><?= e(money($invoice['status'] === 'void' ? 0 : $balance)) ?></p>
          <p class="pay-small">Due <?= e(date('F j, Y', strtotime($invoice['due_date']))) ?></p>
<?php if ($note !== ''): ?>
          <p class="pay-note pay-note--<?= e($invoice['status']) ?>"><?= e($note) ?></p>
<?php elseif ($canPay && $options): ?>
          <form method="post" action="/pay/<?= e($invoice['token']) ?>/checkout" class="pay-buttons">
            <?= csrf_field() ?>
<?php foreach ($options as $value => $label): ?>
            <button class="btn btn-primary" type="submit" name="gateway" value="<?= e($value) ?>"><?= e($label) ?></button>
<?php endforeach; ?>
          </form>
<?php if (isset($options['stripe_ach'])): ?>
          <p class="pay-small">Bank (ACH) payments take a few business days to clear.</p>
<?php endif; ?>
          <p class="pay-small">You’ll pay on a secure page hosted by the payment provider. Your card and bank details never reach our website.</p>
<?php elseif ($canPay): ?>
          <p class="pay-note">Online payment is not available. Please contact us at <?= e(setting('phone')) ?>.</p>
<?php endif; ?>
          <p class="pay-links"><a href="/pay/<?= e($invoice['token']) ?>/print" target="_blank" rel="noopener">Print or save as PDF</a></p>
        </aside>
      </div>
    </section>
  </main>
<?php
pay_end();
