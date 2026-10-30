<?php
/*
 * Local tests for invoices and payments. Not part of any package (tools/ is never uploaded).
 *
 *   php tools/test-payments.php http://127.0.0.1:8000
 *
 * Needs: the site running at that address against a TEST database (name ending in "_test"),
 * mail transport "log" in app/config.php, and the PHP curl + sodium extensions.
 * Webhooks are sent over HTTP with signatures computed from sample payloads, exactly as Stripe and
 * Authorize.net would send them. Authorize.net's transaction lookup is replaced by saved sample answers.
 * Everything the test creates (invoices, a temporary admin, events, settings) is removed or restored at the end.
 */
if (PHP_SAPI !== 'cli') {
    exit('CLI only');
}
$base = rtrim($argv[1] ?? 'http://127.0.0.1:8000', '/');

require dirname(__DIR__) . '/app/bootstrap.php';
require APP . '/auth.php';
require APP . '/payments.php';

if (!str_ends_with((string) config('db.name'), '_test') || config('mail.transport') !== 'log') {
    fwrite(STDERR, "Refusing to run: use a database whose name ends in _test and mail transport 'log'.\n");
    exit(1);
}

$pass = 0;
$fail = 0;
function check(string $name, bool $ok, string $detail = ''): void
{
    global $pass, $fail;
    $ok ? $pass++ : $fail++;
    echo ($ok ? "  ok   " : "  FAIL ") . $name . ($ok || $detail === '' ? '' : "  → {$detail}") . "\n";
}

/** [status, headers, body] */
function http(string $method, string $url, $body = null, array $headers = [], ?string $jar = null): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_CUSTOMREQUEST => $method, CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_HTTPHEADER => $headers, CURLOPT_TIMEOUT => 30]);
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, is_array($body) ? http_build_query($body) : $body);
    }
    if ($jar) {
        curl_setopt($ch, CURLOPT_COOKIEJAR, $jar);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $jar);
    }
    $raw = (string) curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $size = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);
    return [$status, substr($raw, 0, $size), substr($raw, $size)];
}

function hidden(string $html, string $name): string
{
    return preg_match('/name="' . preg_quote($name, '/') . '" value="([^"]*)"/', $html, $m) ? html_entity_decode($m[1]) : '';
}

function mail_count(): int
{
    $file = storage_path('logs/mail.log');
    return is_file($file) ? substr_count((string) file_get_contents($file), "] To: ") : 0;
}

function fresh(int $id): array
{
    return invoice_get($id);
}

const MARK = 'TEST-PAYMENTS';
$whsec = 'whsec_' . bin2hex(random_bytes(16));
$sigKey = strtoupper(bin2hex(random_bytes(64)));

// Keep the settings this test changes, to restore them at the end.
$touched = ['stripe_enabled', 'stripe_mode', 'stripe_secret_key_test', 'stripe_webhook_secret_test', 'stripe_allow_card', 'stripe_allow_ach',
    'authnet_enabled', 'authnet_mode', 'authnet_login_id', 'authnet_transaction_key', 'authnet_signature_key', 'invoice_next_number', 'invoice_reminders_enabled'];
$saved = [];
foreach ($touched as $k) {
    $saved[$k] = db_value('SELECT v FROM settings WHERE k = ?', [$k]);
}
$tempUser = null;
$jar = tempnam(sys_get_temp_dir(), 'etw');

function make_invoice(string $status = 'sent', array $lines = [[200, 125000], [100, 9999]], int $tax = 7250): array
{
    return db_transaction(function (PDO $pdo) use ($status, $lines, $tax) {
        $rows = array_map(fn($l) => ['quantity' => $l[0], 'unit_price_cents' => $l[1]], $lines);
        [$rows, $sub, $taxC, $total] = invoice_calc($rows, $tax);
        $number = invoice_take_number($pdo);
        $pdo->prepare("INSERT INTO invoices (number, token, customer_name, customer_email, project_ref, issue_date, due_date, terms, tax_rate, subtotal_cents, tax_cents, total_cents, status, sent_at)
                       VALUES (?, ?, 'Test Customer', 'paytest@example.com', ?, CURDATE(), CURDATE() + INTERVAL 30 DAY, 'Net 30', ?, ?, ?, ?, ?, ?)")
            ->execute([$number, invoice_new_token(), MARK, tax_rate_label($tax), $sub, $taxC, $total, $status, $status === 'draft' ? null : now()]);
        $id = (int) $pdo->lastInsertId();
        foreach ($rows as $i => $r) {
            $pdo->prepare('INSERT INTO invoice_items (invoice_id, sort, description, quantity, unit_price_cents, line_total_cents) VALUES (?, ?, ?, ?, ?, ?)')
                ->execute([$id, $i, 'Line ' . ($i + 1), quantity_label($r['quantity']), $r['unit_price_cents'], $r['line_total_cents']]);
        }
        return invoice_get($id);
    });
}

/** A pending payment as stripe_start_checkout() would leave it, without calling Stripe. */
function fake_stripe_checkout(array $invoice, string $method): string
{
    $paymentId = payment_create_pending($invoice, 'stripe', $method, invoice_balance($invoice));
    $session = 'cs_test_' . bin2hex(random_bytes(12));
    db_run('UPDATE payments SET gateway_ref = ? WHERE id = ?', [$session, $paymentId]);
    return $session;
}

function stripe_session(array $invoice, string $session, string $paymentStatus, ?int $amount = null, string $pi = ''): array
{
    return ['id' => $session, 'object' => 'checkout.session', 'amount_total' => $amount ?? invoice_balance($invoice), 'currency' => 'usd',
        'payment_status' => $paymentStatus, 'payment_intent' => $pi ?: 'pi_test_' . substr($session, 8, 12),
        'metadata' => ['invoice_id' => (string) $invoice['id'], 'invoice_number' => $invoice['number']]];
}

function stripe_send(string $type, array $object, ?string $eventId = null, ?int $ts = null, ?string $secret = null): array
{
    global $base, $whsec;
    $payload = json_encode(['id' => $eventId ?? 'evt_test_' . bin2hex(random_bytes(10)), 'object' => 'event', 'type' => $type, 'livemode' => false, 'data' => ['object' => $object]]);
    $ts ??= time();
    $sig = hash_hmac('sha256', $ts . '.' . $payload, $secret ?? $whsec);
    return http('POST', $base . '/webhooks/stripe.php', $payload, ['Content-Type: application/json', "Stripe-Signature: t={$ts},v1={$sig}"]);
}

try {
    echo "Unit checks\n";
    check('parse_money("1,234.5") = 123450', parse_money('1,234.5') === 123450);
    check('parse_money(".99") = 99', parse_money('.99') === 99);
    check('parse_money rejects "-5", "1.999", "abc"', parse_money('-5') === null && parse_money('1.999') === null && parse_money('abc') === null);
    check('parse_quantity("2.5") = 250, rejects 0', parse_quantity('2.5') === 250 && parse_quantity('0') === null);
    check('parse_tax_rate("7.25") = 7250, rejects 101', parse_tax_rate('7.25') === 7250 && parse_tax_rate('101') === null);
    [$l, $sub, $tax, $total] = invoice_calc([['quantity' => 250, 'unit_price_cents' => 3333], ['quantity' => 100, 'unit_price_cents' => 1]], 7250);
    check('invoice_calc rounds lines and tax half up (8333 + 1, tax 604)', $l[0]['line_total_cents'] === 8333 && $sub === 8334 && $tax === 604 && $total === 8938, "$sub/$tax/$total");
    check('money(-123456) = "-$1,234.56"', money(-123456) === '-$1,234.56');
    $enc = secret_encrypt('sk_test_abc123secret');
    check('secret_encrypt round trip, value not stored in clear', secret_decrypt($enc) === 'sk_test_abc123secret' && !str_contains($enc, 'abc123'));
    check('secret_mask shows last 4 only', secret_mask('sk_test_abc123secret') === '••••••••cret');
    $payload = '{"id":"evt_1"}';
    $t = time();
    check('Stripe signature: valid', stripe_signature_valid($payload, "t={$t},v1=" . hash_hmac('sha256', "{$t}.{$payload}", 'whsec_x'), 'whsec_x'));
    check('Stripe signature: wrong secret rejected', !stripe_signature_valid($payload, "t={$t},v1=" . hash_hmac('sha256', "{$t}.{$payload}", 'whsec_y'), 'whsec_x'));
    $old = $t - 301;
    check('Stripe signature: older than 5 minutes rejected', !stripe_signature_valid($payload, "t={$old},v1=" . hash_hmac('sha256', "{$old}.{$payload}", 'whsec_x'), 'whsec_x'));
    check('Stripe signature: tampered payload rejected', !stripe_signature_valid('{"id":"evt_2"}', "t={$t},v1=" . hash_hmac('sha256', "{$t}.{$payload}", 'whsec_x'), 'whsec_x'));
    check('Authorize.net signature: valid (any case)', authnet_signature_valid($payload, 'sha512=' . strtolower(hash_hmac('sha512', $payload, $sigKey)), $sigKey));
    check('Authorize.net signature: wrong key rejected', !authnet_signature_valid($payload, 'sha512=' . hash_hmac('sha512', $payload, 'X' . $sigKey), $sigKey));
    check('Token: 64 hex characters', (bool) preg_match('/^[a-f0-9]{64}$/', invoice_new_token()));

    // Gateways on, with test keys saved encrypted.
    settings_save([
        'stripe_enabled' => '1', 'stripe_mode' => 'test', 'stripe_allow_card' => '1', 'stripe_allow_ach' => '1',
        'stripe_secret_key_test' => secret_encrypt('sk_test_' . str_repeat('x', 24)), 'stripe_webhook_secret_test' => secret_encrypt($whsec),
        'authnet_enabled' => '1', 'authnet_mode' => 'sandbox', 'authnet_login_id' => 'TestLogin1',
        'authnet_transaction_key' => secret_encrypt('ABCDEFGH12345678'), 'authnet_signature_key' => secret_encrypt($sigKey),
    ]);
    settings_all(true);

    echo "\nStripe card payment\n";
    $inv = make_invoice();
    check('Invoice totals: 2 × $1,250.00 + $99.99, 7.25% tax = $2,788.49', (int) $inv['total_cents'] === 278849, money((int) $inv['total_cents']));
    $session = fake_stripe_checkout($inv, 'card');
    $mails = mail_count();
    $eventId = 'evt_test_' . bin2hex(random_bytes(10));
    [$code] = stripe_send('checkout.session.completed', stripe_session($inv, $session, 'paid'), $eventId);
    $after = fresh((int) $inv['id']);
    check('Webhook answers 200', $code === 200, (string) $code);
    check('Invoice paid, amount_paid = total, paid_at set', $after['status'] === 'paid' && (int) $after['amount_paid_cents'] === 278849 && $after['paid_at'] !== null, $after['status']);
    check('Receipt + company notification emailed', mail_count() - $mails === 2, (string) (mail_count() - $mails));
    [$code, , $body] = stripe_send('checkout.session.completed', stripe_session($inv, $session, 'paid'), $eventId);
    check('Same event again is ignored (idempotent)', $code === 200 && trim($body) === 'Ignored' && (int) fresh((int) $inv['id'])['amount_paid_cents'] === 278849, trim($body));
    [$code] = stripe_send('checkout.session.async_payment_succeeded', stripe_session($inv, $session, 'paid'));
    check('A second success event for the same session adds nothing', (int) fresh((int) $inv['id'])['amount_paid_cents'] === 278849);

    echo "\nStripe refunds\n";
    $pi = (string) db_value("SELECT gateway_txn FROM payments WHERE gateway_ref = ?", [$session]);
    $charge = ['id' => 'ch_test_1', 'object' => 'charge', 'payment_intent' => $pi, 'amount_refunded' => 78849];
    stripe_send('charge.refunded', $charge);
    $after = fresh((int) $inv['id']);
    check('Partial refund → partially_paid, paid $2,000.00', $after['status'] === 'partially_paid' && (int) $after['amount_paid_cents'] === 200000, $after['status'] . ' ' . $after['amount_paid_cents']);
    stripe_send('charge.refunded', ['amount_refunded' => 278849] + $charge);
    $after = fresh((int) $inv['id']);
    check('Full refund (running total) → refunded, paid $0', $after['status'] === 'refunded' && (int) $after['amount_paid_cents'] === 0, $after['status'] . ' ' . $after['amount_paid_cents']);
    check('Two refund rows recorded, not three', (int) db_value("SELECT COUNT(*) FROM payments WHERE invoice_id = ? AND status = 'refunded'", [$inv['id']]) === 2);

    echo "\nStripe bank (ACH) payment\n";
    $inv = make_invoice();
    $session = fake_stripe_checkout($inv, 'ach');
    stripe_send('checkout.session.completed', stripe_session($inv, $session, 'unpaid'));
    $after = fresh((int) $inv['id']);
    check('Completed + unpaid → processing, nothing paid yet', $after['status'] === 'processing' && (int) $after['amount_paid_cents'] === 0, $after['status']);
    stripe_send('checkout.session.async_payment_succeeded', stripe_session($inv, $session, 'paid'));
    check('async_payment_succeeded → paid', fresh((int) $inv['id'])['status'] === 'paid');

    $inv = make_invoice('viewed');
    db_run('UPDATE invoices SET viewed_at = NOW() WHERE id = ?', [$inv['id']]);
    $session = fake_stripe_checkout($inv, 'ach');
    stripe_send('checkout.session.completed', stripe_session($inv, $session, 'unpaid'));
    $mails = mail_count();
    stripe_send('checkout.session.async_payment_failed', stripe_session($inv, $session, 'unpaid'));
    $after = fresh((int) $inv['id']);
    check('async_payment_failed → back to viewed, payment failed', $after['status'] === 'viewed' && db_value('SELECT status FROM payments WHERE gateway_ref = ?', [$session]) === 'failed', $after['status']);
    check('ACH failure emailed to the company', mail_count() - $mails === 1);

    echo "\nStripe safety checks\n";
    $inv = make_invoice();
    $session = fake_stripe_checkout($inv, 'card');
    stripe_send('checkout.session.completed', stripe_session($inv, $session, 'paid', 100));
    check('Amount mismatch → not applied, flagged for review', fresh((int) $inv['id'])['status'] === 'sent' && db_value('SELECT status FROM payments WHERE gateway_ref = ?', [$session]) === 'failed');
    $session = fake_stripe_checkout($inv, 'card');
    db_run("UPDATE invoices SET status = 'void' WHERE id = ?", [$inv['id']]);
    stripe_send('checkout.session.completed', stripe_session($inv, $session, 'paid'));
    $after = fresh((int) $inv['id']);
    check('Payment for a void invoice → not applied, stays void', $after['status'] === 'void' && (int) $after['amount_paid_cents'] === 0);
    [$code] = stripe_send('checkout.session.completed', stripe_session($inv, $session, 'paid'), null, null, 'whsec_wrong');
    check('Wrong signing secret → 400', $code === 400, (string) $code);
    [$code] = stripe_send('checkout.session.completed', stripe_session($inv, $session, 'paid'), null, time() - 600);
    check('Timestamp 10 minutes old → 400', $code === 400, (string) $code);
    [$code, , $body] = stripe_send('checkout.session.completed', ['id' => 'cs_test_unknown', 'payment_status' => 'paid', 'amount_total' => 100, 'metadata' => []]);
    check('Session from another app → 200, ignored', $code === 200);
    [$code] = http('POST', $base . '/webhooks/stripe.php', 'not json', ['Stripe-Signature: t=' . time() . ',v1=' . hash_hmac('sha256', time() . '.not json', $whsec)]);
    check('Signed but invalid JSON → 400', $code === 400, (string) $code);

    echo "\nAuthorize.net\n";
    $inv = make_invoice();
    $paymentId = payment_create_pending($inv, 'authorizenet', 'card', invoice_balance($inv));
    $txns = [
        '60001' => ['transId' => '60001', 'responseCode' => 1, 'transactionStatus' => 'capturedPendingSettlement', 'authAmount' => 2788.49, 'settleAmount' => 2788.49,
                    'order' => ['invoiceNumber' => $inv['number']], 'payment' => ['creditCard' => ['cardNumber' => 'XXXX1111']]],
        '60002' => ['transId' => '60002', 'refTransId' => '60001', 'responseCode' => 1, 'transactionStatus' => 'refundPendingSettlement', 'authAmount' => 788.49, 'settleAmount' => 788.49],
    ];
    $fetch = fn(string $id) => $txns[$id] ?? [];
    $event = ['notificationId' => 'test-' . bin2hex(random_bytes(6)), 'eventType' => 'net.authorize.payment.authcapture.created', 'payload' => ['entityName' => 'transaction', 'id' => '60001', 'responseCode' => 1, 'authAmount' => 2788.49]];
    $mails = mail_count();
    payments_run_after();
    authnet_handle_event($event, $fetch);
    payments_run_after();
    $after = fresh((int) $inv['id']);
    check('authcapture.created (amount + invoice number confirmed from transaction details) → paid', $after['status'] === 'paid' && (int) $after['amount_paid_cents'] === 278849, $after['status']);
    check('The pending checkout row was completed (not a second row)', db_value('SELECT status FROM payments WHERE id = ?', [$paymentId]) === 'succeeded' && (int) db_value('SELECT COUNT(*) FROM payments WHERE invoice_id = ?', [$inv['id']]) === 1);
    check('Receipt + notification emailed', mail_count() - $mails === 2);
    authnet_handle_event($event, $fetch);
    check('Same capture again adds nothing', (int) fresh((int) $inv['id'])['amount_paid_cents'] === 278849);
    authnet_handle_event(['notificationId' => 'x', 'eventType' => 'net.authorize.payment.refund.created', 'payload' => ['id' => '60002']], $fetch);
    payments_run_after();
    check('refund.created → partially_paid $2,000.00', fresh((int) $inv['id'])['status'] === 'partially_paid' && (int) fresh((int) $inv['id'])['amount_paid_cents'] === 200000);
    authnet_handle_event(['notificationId' => 'y', 'eventType' => 'net.authorize.payment.void.created', 'payload' => ['id' => '60001']], $fetch);
    payments_run_after();
    check('void.created → refunded, $0 kept', fresh((int) $inv['id'])['status'] === 'refunded' && (int) fresh((int) $inv['id'])['amount_paid_cents'] === 0, fresh((int) $inv['id'])['status']);

    $inv2 = make_invoice();
    payment_create_pending($inv2, 'authorizenet', 'card', invoice_balance($inv2));
    $txns['60003'] = ['transId' => '60003', 'responseCode' => 1, 'transactionStatus' => 'capturedPendingSettlement', 'authAmount' => 10.00, 'order' => ['invoiceNumber' => $inv2['number']]];
    authnet_handle_event(['notificationId' => 'z', 'eventType' => 'net.authorize.payment.authcapture.created', 'payload' => ['id' => '60003']], $fetch);
    payments_run_after();
    check('Authorize.net amount mismatch → not applied', fresh((int) $inv2['id'])['status'] === 'sent');
    $txns['60004'] = ['transId' => '60004', 'responseCode' => 2, 'transactionStatus' => 'declined', 'authAmount' => 2788.49, 'order' => ['invoiceNumber' => $inv2['number']]];
    authnet_handle_event(['notificationId' => 'w', 'eventType' => 'net.authorize.payment.authcapture.created', 'payload' => ['id' => '60004']], $fetch);
    check('Declined transaction → ignored', fresh((int) $inv2['id'])['status'] === 'sent');

    $body = json_encode(['notificationId' => 'test-http-' . bin2hex(random_bytes(4)), 'eventType' => 'net.authorize.customer.created', 'payload' => ['id' => '1']]);
    [$code] = http('POST', $base . '/webhooks/authorizenet.php', $body, ['Content-Type: application/json', 'X-ANET-Signature: sha512=' . hash_hmac('sha512', $body, 'wrong')]);
    check('HTTP: wrong X-ANET-Signature → 401', $code === 401, (string) $code);
    [$code, , $resp] = http('POST', $base . '/webhooks/authorizenet.php', $body, ['Content-Type: application/json', 'X-ANET-Signature: sha512=' . strtoupper(hash_hmac('sha512', $body, $sigKey))]);
    check('HTTP: valid signature, other event type → 200 Ignored', $code === 200 && trim($resp) === 'Ignored', $code . ' ' . trim($resp));

    echo "\nPay pages\n";
    $inv = make_invoice();
    $url = $base . '/pay/' . $inv['token'];
    [$code, $headers, $html] = http('GET', $url);
    check('Sent invoice page 200, noindex, strict CSP with gateway form-action', $code === 200 && stripos($headers, 'X-Robots-Tag: noindex') !== false && str_contains($headers, "form-action 'self' https://checkout.stripe.com https://accept.authorize.net"));
    check('Shows the three pay buttons and the ACH note', str_contains($html, 'Pay by bank (ACH)') && str_contains($html, 'Pay by card') && str_contains($html, 'Pay with Authorize.net') && str_contains($html, 'few business days'));
    check('No internal invoice id in the page', !str_contains($html, 'invoice_id') && !preg_match('/[?&]id=' . $inv['id'] . '\b/', $html));
    $after = fresh((int) $inv['id']);
    check('First view → viewed, viewed_at set', $after['status'] === 'viewed' && $after['viewed_at'] !== null, $after['status']);
    check('Amounts shown: total $2,788.49', str_contains($html, '$2,788.49'));
    [$code, , $html] = http('GET', $url . '/print');
    check('Print page 200 with print button', $code === 200 && str_contains($html, 'data-print') && str_contains($html, 'invoice-doc'));
    [$code, , $html] = http('GET', $url . '/return');
    check('Return page (unpaid) says processing and changes nothing', str_contains($html, 'Payment is processing') && fresh((int) $inv['id'])['status'] === 'viewed');
    db_run("INSERT INTO payments (invoice_id, gateway, method, amount_cents, status, completed_at) VALUES (?, 'manual', 'check', ?, 'succeeded', NOW())", [$inv['id'], $inv['total_cents']]);
    db_transaction(fn(PDO $pdo) => invoice_recalc($pdo, (int) $inv['id']));
    [, , $html] = http('GET', $url);
    check('Paid invoice: status shown, no pay buttons', str_contains($html, 'paid in full') && !str_contains($html, 'Pay by card'));
    [, , $html] = http('GET', $url . '/return');
    check('Return page (paid) says Payment received', str_contains($html, 'Payment received'));
    $void = make_invoice();
    db_run("UPDATE invoices SET status = 'void' WHERE id = ?", [$void['id']]);
    [, , $html] = http('GET', $base . '/pay/' . $void['token']);
    check('Void invoice: voided note, no pay buttons', str_contains($html, 'has been voided') && !str_contains($html, 'Pay by card'));
    $draft = make_invoice('draft');
    [$code] = http('GET', $base . '/pay/' . $draft['token']);
    check('Draft invoice → 404 for customers', $code === 404, (string) $code);
    [$code] = http('GET', $base . '/pay/' . str_repeat('a', 64));
    check('Unknown token → 404', $code === 404, (string) $code);
    [$code] = http('POST', $url . '/checkout', ['gateway' => 'stripe_card']);
    check('Checkout POST without CSRF → back to the invoice with an error', $code === 303);
    settings_save(['stripe_enabled' => '0', 'authnet_enabled' => '0']);
    $none = make_invoice();
    [, , $html] = http('GET', $base . '/pay/' . $none['token']);
    check('No gateway enabled → "Online payment is not available. Please contact us at {phone}."', str_contains($html, 'Online payment is not available. Please contact us at ' . e(setting('phone'))));
    settings_save(['stripe_enabled' => '1', 'stripe_allow_ach' => '0', 'authnet_enabled' => '0']);
    [, , $html] = http('GET', $base . '/pay/' . $none['token']);
    check('Card only: no ACH button and no ACH note', str_contains($html, 'Pay by card') && !str_contains($html, 'Pay by bank') && !str_contains($html, 'few business days'));
    settings_save(['stripe_webhook_secret_test' => '']);
    [, , $html] = http('GET', $base . '/pay/' . $none['token']);
    check('Stripe on but webhook secret missing → not offered', !str_contains($html, 'Pay by card'));

    echo "\nLookup form\n";
    $lookup = function (string $number, string $email) use ($base, $jar) {
        [, , $page] = http('GET', $base . '/pay', null, [], $jar);
        sleep(3); // the form rejects submissions sent within 3 seconds
        return http('POST', $base . '/pay', ['csrf' => hidden($page, 'csrf'), 'form_ts' => hidden($page, 'form_ts'), HONEYPOT_FIELD => '', 'number' => $number, 'email' => $email], [], $jar);
    };
    [$code, $headers] = $lookup(strtolower($none['number']), 'PAYTEST@example.com');
    check('Number + email (any case) → 303 to the pay page', $code === 303 && str_contains($headers, '/pay/' . $none['token']), (string) $code);
    [$code, , $html] = $lookup($none['number'], 'someone@example.com');
    $generic = 'couldn’t find an invoice with that number and email';
    check('Wrong email → same generic error', $code === 200 && str_contains($html, $generic));
    [$code, , $html] = $lookup('ECG-999999', 'paytest@example.com');
    check('Unknown number → same generic error', $code === 200 && str_contains($html, $generic));
    [$code, , $html] = $lookup($draft['number'], 'paytest@example.com');
    check('Draft number → same generic error', $code === 200 && str_contains($html, $generic));

    echo "\nDashboard\n";
    $pw = bin2hex(random_bytes(12));
    db_run('INSERT INTO users (name, email, password_hash) VALUES (?, ?, ?)', ['Payments Test', 'paytest-admin@example.com', password_hash_secure($pw)]);
    $tempUser = (int) db()->lastInsertId();
    [, , $page] = http('GET', $base . '/admin/login.php', null, [], $jar);
    [$code] = http('POST', $base . '/admin/login.php', ['csrf' => hidden($page, 'csrf'), 'email' => 'paytest-admin@example.com', 'password' => $pw], [], $jar);
    check('Temporary admin signs in', $code === 302);
    [$code, , $page] = http('GET', $base . '/admin/?p=invoice_edit', null, [], $jar);
    check('New invoice form loads', $code === 200 && str_contains($page, 'data-invoice-form'));
    $csrf = hidden($page, 'csrf');
    $form = ['csrf' => $csrf, 'customer_name' => 'Dash Test', 'customer_company' => 'Acme <b>Co</b>', 'customer_email' => 'paytest@example.com', 'customer_phone' => '',
        'billing_address' => "1 Main St\nCartersville, GA", 'project_ref' => MARK, 'issue_date' => date('Y-m-d'), 'terms' => 'Net 15', 'due_date' => '', 'tax_rate' => '7',
        'notes' => 'Thanks', 'item_description' => ['Grading', 'Pipe'], 'item_quantity' => ['3', '1.5'], 'item_price' => ['1,000.00', '200'], 'subtotal_cents' => '1', 'total_cents' => '1'];
    [$code, $headers] = http('POST', $base . '/admin/?p=invoice_edit', http_build_query($form), [], $jar);
    $new = db_one('SELECT * FROM invoices WHERE customer_name = ? ORDER BY id DESC LIMIT 1', ['Dash Test']);
    check('Create: saved as draft with a number, totals recalculated (posted totals ignored)', $new && $new['status'] === 'draft' && (int) $new['total_cents'] === 353100 && str_starts_with($new['number'], setting('invoice_prefix')), $new ? $new['total_cents'] : 'none');
    check('Create: due date from terms (Net 15)', $new && $new['due_date'] === date('Y-m-d', strtotime('+15 days')));
    $form['item_price'] = ['abc', '200'];
    [$code, , $page] = http('POST', $base . '/admin/?p=invoice_edit&id=' . $new['id'], http_build_query($form), [], $jar);
    check('Edit: invalid price is rejected with a message', str_contains($page, 'Each line needs a description'));
    $form['item_price'] = ['1,100.00', '200'];
    http('POST', $base . '/admin/?p=invoice_edit&id=' . $new['id'], http_build_query($form), [], $jar);
    check('Edit draft: new totals saved', (int) fresh((int) $new['id'])['total_cents'] === 385200);
    [, , $page] = http('GET', $base . '/admin/?p=invoice&id=' . $new['id'], null, [], $jar);
    check('Detail page escapes customer input', str_contains($page, 'Acme &lt;b&gt;Co&lt;/b&gt;') && !str_contains($page, 'Acme <b>Co</b>'));
    $csrf = hidden($page, 'csrf');
    $mails = mail_count();
    http('POST', $base . '/admin/?p=invoice&id=' . $new['id'], ['csrf' => $csrf, 'action' => 'send'], [], $jar);
    check('Send: emailed, status sent, sent_at set', mail_count() - $mails === 1 && fresh((int) $new['id'])['status'] === 'sent' && fresh((int) $new['id'])['sent_at'] !== null);
    $log = (string) file_get_contents(storage_path('logs/mail.log'));
    check('Invoice email has the pay link', str_contains($log, '/pay/' . $new['token']));
    [$code, , $page] = http('POST', $base . '/admin/?p=invoice_edit&id=' . $new['id'], http_build_query(['item_price' => ['1', '1']] + $form), [], $jar);
    check('Sent invoice: amounts locked (edit keeps the total)', (int) fresh((int) $new['id'])['total_cents'] === 385200);
    $mails = mail_count();
    http('POST', $base . '/admin/?p=invoice&id=' . $new['id'], ['csrf' => $csrf, 'action' => 'remind'], [], $jar);
    check('Reminder emailed', mail_count() - $mails === 1);
    http('POST', $base . '/admin/?p=invoice&id=' . $new['id'], ['csrf' => $csrf, 'action' => 'payment', 'amount' => '999999', 'method' => 'check', 'paid_on' => date('Y-m-d'), 'reference' => ''], [], $jar);
    check('Manual payment above the balance is refused', (int) fresh((int) $new['id'])['amount_paid_cents'] === 0);
    http('POST', $base . '/admin/?p=invoice&id=' . $new['id'], ['csrf' => $csrf, 'action' => 'payment', 'amount' => '1000', 'method' => 'check', 'paid_on' => date('Y-m-d'), 'reference' => 'Check #1042', 'send_receipt' => '1'], [], $jar);
    check('Manual payment $1,000 → partially_paid', fresh((int) $new['id'])['status'] === 'partially_paid' && (int) fresh((int) $new['id'])['amount_paid_cents'] === 100000);
    http('POST', $base . '/admin/?p=invoice&id=' . $new['id'], ['csrf' => $csrf, 'action' => 'void'], [], $jar);
    check('Void refused while payments exist', fresh((int) $new['id'])['status'] === 'partially_paid');
    [$code, , $page] = http('GET', $base . '/admin/?p=invoice_edit&dup=' . $new['id'], null, [], $jar);
    check('Duplicate opens a prefilled new draft', $code === 200 && str_contains($page, 'Grading') && str_contains($page, 'Save as draft'));
    $other = make_invoice();
    http('POST', $base . '/admin/?p=invoice&id=' . $other['id'], ['csrf' => $csrf, 'action' => 'void'], [], $jar);
    check('Void (no payments) → void', fresh((int) $other['id'])['status'] === 'void');
    [, , $html] = http('GET', $base . '/pay/' . $other['token']);
    check('Voided invoice cannot be paid from the pay page', !str_contains($html, 'Pay by card') && str_contains($html, 'voided'));
    [$code] = http('POST', $base . '/admin/?p=invoice&id=' . $other['id'], ['action' => 'send'], [], $jar);
    check('Dashboard POST without CSRF → 400', $code === 400, (string) $code);
    check('Every dashboard action was logged', (int) db_value("SELECT COUNT(*) FROM activity_log WHERE entity = 'invoice' AND entity_id = ? AND user_id = ?", [$new['id'], $tempUser]) >= 5);
    [$code, $headers, $csv] = http('GET', $base . '/admin/export.php?type=invoices&q=' . MARK, null, [], $jar);
    check('CSV export', $code === 200 && str_contains($headers, 'text/csv') && str_contains($csv, $new['number']));
    [$code, , $page] = http('GET', $base . '/admin/?p=invoices&status=overdue', null, [], $jar);
    check('List with overdue filter loads', $code === 200);
    [$code, , $page] = http('GET', $base . '/admin/', null, [], $jar);
    check('Overview shows Outstanding / Overdue / Paid this month', str_contains($page, 'Outstanding') && str_contains($page, 'Paid this month'));
    [$code, , $page] = http('GET', $base . '/admin/?p=payments', null, [], $jar);
    check('Payments settings: keys masked, never shown in full', $code === 200 && str_contains($page, '••••••••') && !str_contains($page, 'ABCDEFGH12345678') && !str_contains($page, $sigKey));
    check('Payments settings: webhook URLs and event lists shown', str_contains($page, '/webhooks/stripe.php') && str_contains($page, 'checkout.session.async_payment_failed') && str_contains($page, 'net.authorize.payment.void.created'));
    $settingsForm = [];
    foreach (['invoice_prefix', 'invoice_next_number', 'invoice_default_tax_rate', 'invoice_tax_label', 'invoice_notify_email', 'invoice_reminder_before_days', 'invoice_reminder_overdue_days', 'authnet_login_id'] as $k) {
        $settingsForm[$k] = hidden($page, $k) !== '' ? hidden($page, $k) : setting($k);
    }
    $settingsForm += ['csrf' => hidden($page, 'csrf'), 'invoice_default_terms' => 'Net 30', 'invoice_payable_to' => setting('invoice_payable_to'), 'invoice_footer_note' => setting('invoice_footer_note'),
        'stripe_mode' => 'test', 'authnet_mode' => 'sandbox', 'stripe_enabled' => '1', 'authnet_enabled' => '1', 'stripe_allow_card' => '1',
        'stripe_secret_key_test' => '', 'authnet_transaction_key' => '', 'authnet_signature_key' => ''];
    http('POST', $base . '/admin/?p=payments', $settingsForm, [], $jar);
    settings_all(true);
    check('Saving with empty key fields keeps the saved keys', secret_setting('authnet_transaction_key') === 'ABCDEFGH12345678' && secret_setting('authnet_signature_key') === $sigKey);
    http('POST', $base . '/admin/?p=payments', ['stripe_secret_key_test' => 'not-a-key'] + $settingsForm, [], $jar);
    settings_all(true);
    check('An invalid key is refused, the saved one stays', secret_setting('stripe_secret_key_test') === 'sk_test_' . str_repeat('x', 24));
    check('Keys stored encrypted in the database', str_starts_with((string) db_value("SELECT v FROM settings WHERE k = 'authnet_signature_key'"), 'enc1:'));
    $errorLog = (string) @file_get_contents(storage_path('logs/php-error.log'));
    check('No secret appears in the error log', !str_contains($errorLog, $sigKey) && !str_contains($errorLog, 'ABCDEFGH12345678') && !str_contains($errorLog, $whsec));

    echo "\nReminder cron\n";
    $cron = make_invoice();
    db_run('UPDATE invoices SET due_date = CURDATE() - INTERVAL 7 DAY WHERE id = ?', [$cron['id']]);
    settings_save(['invoice_reminders_enabled' => '1', 'invoice_reminder_overdue_days' => '1,7,14']);
    $php = escapeshellarg(PHP_BINARY) . (php_ini_loaded_file() ? ' -c ' . escapeshellarg(php_ini_loaded_file()) : '');
    $mails = mail_count();
    $out = (string) shell_exec($php . ' ' . escapeshellarg(ROOT . '/cron/invoice-reminders.php'));
    check('Cron sends the 7-days-overdue reminder', str_contains($out, 'overdue-7') && str_contains($out, $cron['number']) && mail_count() > $mails, trim($out));
    $mails = mail_count();
    shell_exec($php . ' ' . escapeshellarg(ROOT . '/cron/invoice-reminders.php'));
    check('Running it again the same day sends nothing new for it', !db_value("SELECT COUNT(*) FROM invoice_reminders WHERE invoice_id = ? AND kind <> 'overdue-7'", [$cron['id']]) && mail_count() === $mails);
    [$code] = http('GET', $base . '/cron/invoice-reminders.php');
    check('Cron script refused over the web', $code === 403, (string) $code);
} finally {
    // Clean up everything this test created.
    $ids = array_column(db_all('SELECT id FROM invoices WHERE project_ref = ? OR customer_email = ?', [MARK, 'paytest@example.com']), 'id');
    if ($ids) {
        $in = implode(',', array_map('intval', $ids));
        db_run("DELETE FROM gateway_events WHERE invoice_id IN ({$in})");
        db_run("DELETE FROM activity_log WHERE entity = 'invoice' AND entity_id IN ({$in})");
        db_run("DELETE FROM invoices WHERE id IN ({$in})");
    }
    db_run("DELETE FROM gateway_events WHERE event_id LIKE 'evt_test_%' OR event_id LIKE 'anet_test-%'");
    if ($tempUser) {
        db_run('DELETE FROM activity_log WHERE user_id = ?', [$tempUser]);
        db_run('DELETE FROM users WHERE id = ?', [$tempUser]);
    }
    db_run('DELETE FROM rate_limits WHERE bucket LIKE ?', ['pay%']);
    foreach ($saved as $k => $v) {
        $v === null ? db_run('DELETE FROM settings WHERE k = ?', [$k]) : db_run('UPDATE settings SET v = ? WHERE k = ?', [$v, $k]);
    }
    @unlink($jar);
}
echo "\n{$pass} passed, {$fail} failed\n";
exit($fail ? 1 : 0);
