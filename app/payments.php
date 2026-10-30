<?php
declare(strict_types=1);

/*
 * Online payments through hosted pages only (Stripe Checkout, Authorize.net Accept Hosted): card and bank
 * details never reach this server. Only a verified webhook marks money as received; the browser's return
 * to the site never changes a payment.
 */

require_once APP . '/invoice-mail.php';

const STRIPE_API = 'https://api.stripe.com';
const STRIPE_SIGNATURE_TOLERANCE = 300; // seconds
const STRIPE_MIN_CENTS = 50;
const STRIPE_EVENTS = ['checkout.session.completed', 'checkout.session.async_payment_succeeded', 'checkout.session.async_payment_failed', 'charge.refunded'];
const AUTHNET_EVENTS = ['net.authorize.payment.authcapture.created', 'net.authorize.payment.refund.created', 'net.authorize.payment.void.created'];
// Authorize.net transaction states that mean no money was taken.
const AUTHNET_FAILED_STATES = ['declined', 'voided', 'expired', 'generalError', 'communicationError', 'failedReview', 'settlementError', 'returnedItem', 'couldNotVoid'];

/* HTTP ------------------------------------------------------------------------ */

/** Returns [HTTP status, body]. Throws on a network error. Never logs request bodies (they may hold keys). */
function http_call(string $method, string $url, array $headers, ?string $body = null): array
{
    if (!function_exists('curl_init')) {
        throw new RuntimeException('The PHP cURL extension is not available.');
    }
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_PROTOCOLS      => CURLPROTO_HTTPS,
    ]);
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    }
    $response = curl_exec($ch);
    if ($response === false) {
        $error = curl_error($ch);
        curl_close($ch);
        throw new RuntimeException('Could not reach the payment provider (' . $error . ').');
    }
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    return [$status, (string) $response];
}

/* Deferred work: emails are sent after the webhook response, so the gateway gets its 200 quickly. */

function payments_after(callable $fn): void
{
    $GLOBALS['PAYMENTS_AFTER'][] = $fn;
}

function payments_run_after(): void
{
    foreach ($GLOBALS['PAYMENTS_AFTER'] ?? [] as $fn) {
        try {
            $fn();
        } catch (Throwable $e) {
            log_error('Payment follow-up failed: ' . $e->getMessage());
        }
    }
    $GLOBALS['PAYMENTS_AFTER'] = [];
}

/* Shared payment changes --------------------------------------------------------- */

function payment_get(int $id): ?array
{
    return db_one('SELECT * FROM payments WHERE id = ?', [$id]);
}

/** Mark a payment as received, update the invoice, and queue the receipt and the company notification. */
function payment_mark_succeeded(int $paymentId, int $amountCents, ?string $txn, string $note = ''): ?array
{
    $result = db_transaction(function (PDO $pdo) use ($paymentId, $amountCents, $txn, $note) {
        $stmt = $pdo->prepare('SELECT * FROM payments WHERE id = ? FOR UPDATE');
        $stmt->execute([$paymentId]);
        $payment = $stmt->fetch();
        if (!$payment || $payment['status'] === 'succeeded' || $payment['status'] === 'refunded') {
            return null; // already applied
        }
        $pdo->prepare("UPDATE payments SET status = 'succeeded', amount_cents = ?, gateway_txn = COALESCE(?, gateway_txn), completed_at = NOW(),
                       reference_note = IF(? = '', reference_note, ?) WHERE id = ?")
            ->execute([$amountCents, $txn, $note, mb_substr($note, 0, 255), $paymentId]);
        $invoice = invoice_recalc($pdo, (int) $payment['invoice_id']);
        return [$invoice, payment_get_in($pdo, $paymentId)];
    });
    if (!$result) {
        return null;
    }
    [$invoice, $payment] = $result;
    log_activity('Payment received ' . money((int) $payment['amount_cents']) . ' (' . (PAYMENT_GATEWAYS[$payment['gateway']] ?? '') . ')', 'invoice', (int) $invoice['id'], null);
    payments_after(fn() => invoice_mail_receipt($invoice, $payment));
    payments_after(fn() => invoice_mail_admin_payment($invoice, $payment));
    return $invoice;
}

function payment_get_in(PDO $pdo, int $id): array
{
    $stmt = $pdo->prepare('SELECT * FROM payments WHERE id = ?');
    $stmt->execute([$id]);
    return $stmt->fetch();
}

/** A payment that will not arrive (ACH failure, mismatch, void invoice). The invoice returns to its unpaid status. */
function payment_mark_failed(int $paymentId, string $note, ?string $txn = null): ?array
{
    return db_transaction(function (PDO $pdo) use ($paymentId, $note, $txn) {
        $stmt = $pdo->prepare('SELECT * FROM payments WHERE id = ? FOR UPDATE');
        $stmt->execute([$paymentId]);
        $payment = $stmt->fetch();
        if (!$payment || $payment['status'] !== 'pending') {
            return null;
        }
        $pdo->prepare("UPDATE payments SET status = 'failed', gateway_txn = COALESCE(?, gateway_txn), reference_note = ? WHERE id = ?")
            ->execute([$txn, mb_substr($note, 0, 255), $paymentId]);
        return invoice_recalc($pdo, (int) $payment['invoice_id']);
    });
}

/** ACH submitted but not cleared yet: keep the payment pending with its transaction id, so the invoice shows "processing". */
function payment_mark_processing(int $paymentId, string $txn): ?array
{
    return db_transaction(function (PDO $pdo) use ($paymentId, $txn) {
        $stmt = $pdo->prepare('SELECT * FROM payments WHERE id = ? FOR UPDATE');
        $stmt->execute([$paymentId]);
        $payment = $stmt->fetch();
        if (!$payment || $payment['status'] !== 'pending') {
            return null;
        }
        $pdo->prepare('UPDATE payments SET gateway_txn = ? WHERE id = ?')->execute([$txn, $paymentId]);
        return invoice_recalc($pdo, (int) $payment['invoice_id']);
    });
}

/**
 * Record a refund as its own row with a negative amount. $refundKey identifies the refund at the gateway,
 * so the same refund is never recorded twice. Returns the updated invoice, or null when nothing changed.
 */
function payment_record_refund(array $original, int $amountCents, string $refundKey, string $note): ?array
{
    if ($amountCents <= 0) {
        return null;
    }
    $invoice = db_transaction(function (PDO $pdo) use ($original, $amountCents, $refundKey, $note) {
        // Lock the original payment so two deliveries of the same refund cannot both insert.
        $pdo->prepare('SELECT id FROM payments WHERE id = ? FOR UPDATE')->execute([$original['id']]);
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM payments WHERE gateway = ? AND status = 'refunded' AND gateway_ref = ?");
        $stmt->execute([$original['gateway'], $refundKey]);
        if ((int) $stmt->fetchColumn() > 0) {
            return null;
        }
        $pdo->prepare("INSERT INTO payments (invoice_id, gateway, method, amount_cents, status, gateway_ref, gateway_txn, reference_note, created_at, completed_at)
                       VALUES (?, ?, ?, ?, 'refunded', ?, ?, ?, NOW(), NOW())")
            ->execute([$original['invoice_id'], $original['gateway'], $original['method'], -$amountCents, $refundKey, $original['gateway_txn'], mb_substr($note, 0, 255)]);
        return invoice_recalc($pdo, (int) $original['invoice_id']);
    });
    if ($invoice) {
        log_activity('Refund recorded ' . money($amountCents) . ' (' . (PAYMENT_GATEWAYS[$original['gateway']] ?? '') . ')', 'invoice', (int) $invoice['id'], null);
        payments_after(fn() => invoice_mail_admin_alert($invoice, 'Refund recorded', 'A refund of ' . money($amountCents) . ' was made in ' . (PAYMENT_GATEWAYS[$original['gateway']] ?? 'the payment gateway') . '. The invoice was updated.'));
    }
    return $invoice;
}

/** Store a webhook event once. Returns false when it was already processed (the event is then ignored). */
function gateway_event_claim(string $gateway, string $eventId, string $type, string $payload): bool
{
    db_run('INSERT IGNORE INTO gateway_events (gateway, event_id, type, payload, received_at) VALUES (?, ?, ?, ?, NOW())', [$gateway, $eventId, mb_substr($type, 0, 100), $payload]);
    return db_value('SELECT processed_at FROM gateway_events WHERE event_id = ?', [$eventId]) === null;
}

function gateway_event_done(string $eventId, ?int $invoiceId): void
{
    db_run('UPDATE gateway_events SET processed_at = NOW(), invoice_id = ? WHERE event_id = ?', [$invoiceId, $eventId]);
}

/** Stop a payment for a void invoice from being applied, and ask the company to refund it at the gateway. */
function payment_reject_void(array $invoice, array $payment, int $amountCents, ?string $txn): void
{
    payment_mark_failed((int) $payment['id'], 'Received after the invoice was voided. Refund it at the gateway.', $txn);
    log_error('Payment ' . (int) $payment['id'] . ' arrived for void invoice ' . $invoice['number'] . '.');
    payments_after(fn() => invoice_mail_admin_alert($invoice, 'Payment received for a void invoice',
        'A payment of ' . money($amountCents) . ' arrived for this invoice after it was voided. It was not applied. Please refund it in ' . (PAYMENT_GATEWAYS[$payment['gateway']] ?? 'the gateway') . '.'));
}

function payment_reject_mismatch(array $invoice, array $payment, int $amountCents, ?string $txn, string $detail): void
{
    payment_mark_failed((int) $payment['id'], 'Amount did not match the checkout. Review at the gateway.', $txn);
    log_error('Payment ' . (int) $payment['id'] . ' for invoice ' . $invoice['number'] . ' not applied: ' . $detail);
    payments_after(fn() => invoice_mail_admin_alert($invoice, 'Payment needs review',
        'A payment of ' . money($amountCents) . ' arrived, but it does not match the amount of the checkout (' . money((int) $payment['amount_cents']) . '). It was not applied automatically. Check it in ' . (PAYMENT_GATEWAYS[$payment['gateway']] ?? 'the gateway') . ' and record it by hand if it is correct.'));
}

function payment_create_pending(array $invoice, string $gateway, string $method, int $amountCents): int
{
    db_run("INSERT INTO payments (invoice_id, gateway, method, amount_cents, status, created_at) VALUES (?, ?, ?, ?, 'pending', NOW())",
        [$invoice['id'], $gateway, $method, $amountCents]);
    return (int) db()->lastInsertId();
}

/* Stripe ---------------------------------------------------------------------- */

/** Call the Stripe API. Throws RuntimeException with Stripe's message (never the key). */
function stripe_api(string $method, string $path, array $params = [], ?string $key = null, ?string $idempotencyKey = null): array
{
    $key ??= stripe_secret_key();
    if ($key === '') {
        throw new RuntimeException('No Stripe secret key is saved for the current mode.');
    }
    $headers = ['Authorization: Bearer ' . $key, 'Stripe-Version: 2024-06-20'];
    $body = null;
    $url = STRIPE_API . $path;
    if ($method === 'GET') {
        $url .= $params ? '?' . http_build_query($params) : '';
    } else {
        $headers[] = 'Content-Type: application/x-www-form-urlencoded';
        $body = http_build_query($params);
    }
    if ($idempotencyKey !== null) {
        $headers[] = 'Idempotency-Key: ' . $idempotencyKey;
    }
    [$status, $response] = http_call($method, $url, $headers, $body);
    $data = json_decode($response, true);
    if (!is_array($data)) {
        throw new RuntimeException('Stripe returned an unreadable answer (HTTP ' . $status . ').');
    }
    if ($status >= 400) {
        throw new RuntimeException('Stripe: ' . (string) ($data['error']['message'] ?? 'request failed (HTTP ' . $status . ')'));
    }
    return $data;
}

/** Create a Checkout Session for the balance due and return its URL. $method: card | ach. */
function stripe_start_checkout(array $invoice, string $method): string
{
    $amount = invoice_balance($invoice);
    if ($amount < STRIPE_MIN_CENTS) {
        throw new RuntimeException('The balance is below the online payment minimum of ' . money(STRIPE_MIN_CENTS) . '.');
    }
    $paymentId = payment_create_pending($invoice, 'stripe', $method, $amount);
    $metadata = ['invoice_id' => (string) $invoice['id'], 'invoice_number' => $invoice['number'], 'payment_id' => (string) $paymentId];
    $params = [
        'mode'                 => 'payment',
        'payment_method_types' => [$method === 'ach' ? 'us_bank_account' : 'card'],
        'line_items'           => [[
            'quantity'   => 1,
            'price_data' => ['currency' => 'usd', 'unit_amount' => $amount, 'product_data' => ['name' => 'Invoice ' . $invoice['number']]],
        ]],
        'client_reference_id'  => $invoice['number'],
        'metadata'             => $metadata,
        'payment_intent_data'  => ['metadata' => $metadata, 'description' => 'Invoice ' . $invoice['number']],
        'success_url'          => invoice_pay_url($invoice, '/return'),
        'cancel_url'           => invoice_pay_url($invoice),
    ];
    if (valid_email($invoice['customer_email'])) {
        $params['customer_email'] = $invoice['customer_email'];
    }
    try {
        $session = stripe_api('POST', '/v1/checkout/sessions', $params, null, 'etowah-checkout-' . $paymentId);
    } catch (RuntimeException $e) {
        db_run("UPDATE payments SET status = 'failed', reference_note = 'Checkout could not be started' WHERE id = ?", [$paymentId]);
        throw $e;
    }
    db_run('UPDATE payments SET gateway_ref = ? WHERE id = ?', [(string) $session['id'], $paymentId]);
    return (string) $session['url'];
}

/** Verify a Stripe-Signature header: HMAC-SHA256 over "{t}.{payload}", timestamp within 5 minutes. */
function stripe_signature_valid(string $payload, string $header, string $secret, ?int $now = null): bool
{
    if ($secret === '' || $header === '') {
        return false;
    }
    $timestamp = null;
    $signatures = [];
    foreach (explode(',', $header) as $part) {
        [$k, $v] = array_pad(explode('=', trim($part), 2), 2, '');
        if ($k === 't' && ctype_digit($v)) {
            $timestamp = (int) $v;
        } elseif ($k === 'v1') {
            $signatures[] = $v;
        }
    }
    if ($timestamp === null || !$signatures || abs(($now ?? time()) - $timestamp) > STRIPE_SIGNATURE_TOLERANCE) {
        return false;
    }
    $expected = hash_hmac('sha256', $timestamp . '.' . $payload, $secret);
    foreach ($signatures as $signature) {
        if (hash_equals($expected, $signature)) {
            return true;
        }
    }
    return false;
}

/** Apply a verified Stripe event. Returns the invoice id it concerned, if any. */
function stripe_handle_event(array $event): ?int
{
    $type = (string) ($event['type'] ?? '');
    $object = $event['data']['object'] ?? [];
    if (!is_array($object)) {
        return null;
    }

    if (str_starts_with($type, 'checkout.session.')) {
        $payment = db_one("SELECT * FROM payments WHERE gateway = 'stripe' AND gateway_ref = ?", [(string) ($object['id'] ?? '')]);
        if (!$payment) {
            return null; // a session from another app on the same Stripe account
        }
        $invoice = invoice_get((int) $payment['invoice_id']);
        if (!$invoice || (string) ($object['metadata']['invoice_id'] ?? '') !== (string) $invoice['id']) {
            log_error('Stripe session ' . $payment['gateway_ref'] . ' does not match its invoice.');
            return null;
        }
        $amount = (int) ($object['amount_total'] ?? 0);
        $txn = isset($object['payment_intent']) && is_string($object['payment_intent']) ? $object['payment_intent'] : null;

        if ($type === 'checkout.session.async_payment_failed') {
            if ($updated = payment_mark_failed((int) $payment['id'], 'Bank payment failed', $txn)) {
                log_activity('Bank (ACH) payment failed ' . money($amount), 'invoice', (int) $invoice['id'], null);
                payments_after(fn() => invoice_mail_admin_alert($updated, 'Bank (ACH) payment failed',
                    'The bank payment of ' . money($amount) . ' did not go through. The invoice is unpaid again. You may want to contact the customer.'));
            }
            return (int) $invoice['id'];
        }

        $paid = $type === 'checkout.session.async_payment_succeeded' || ($object['payment_status'] ?? '') === 'paid';
        if ($type === 'checkout.session.completed' && ($object['payment_status'] ?? '') === 'unpaid') {
            // Bank payment submitted; it clears in a few business days (async_payment_succeeded / _failed follow).
            if ($txn && $invoice['status'] !== 'void' && payment_mark_processing((int) $payment['id'], $txn)) {
                log_activity('Bank (ACH) payment submitted ' . money($amount) . ', processing', 'invoice', (int) $invoice['id'], null);
            }
            return (int) $invoice['id'];
        }
        if (!$paid) {
            return (int) $invoice['id'];
        }
        if ($invoice['status'] === 'void') {
            payment_reject_void($invoice, $payment, $amount, $txn);
        } elseif ($amount !== (int) $payment['amount_cents'] || strtolower((string) ($object['currency'] ?? '')) !== 'usd') {
            payment_reject_mismatch($invoice, $payment, $amount, $txn, 'Stripe amount ' . $amount . ' ' . ($object['currency'] ?? '?'));
        } else {
            payment_mark_succeeded((int) $payment['id'], $amount, $txn);
        }
        return (int) $invoice['id'];
    }

    if ($type === 'charge.refunded') {
        $intent = is_string($object['payment_intent'] ?? null) ? $object['payment_intent'] : '';
        $original = $intent !== '' ? db_one("SELECT * FROM payments WHERE gateway = 'stripe' AND gateway_txn = ? AND status = 'succeeded' ORDER BY id LIMIT 1", [$intent]) : null;
        if (!$original) {
            return null;
        }
        // amount_refunded is the running total for the charge; record only the part not recorded yet.
        $already = -(int) db_value("SELECT COALESCE(SUM(amount_cents), 0) FROM payments WHERE gateway = 'stripe' AND status = 'refunded' AND gateway_txn = ?", [$intent]);
        $new = (int) ($object['amount_refunded'] ?? 0) - $already;
        payment_record_refund($original, $new, 'refund:' . (string) ($event['id'] ?? ''), 'Refunded in Stripe');
        return (int) $original['invoice_id'];
    }
    return null;
}

/** Simple authenticated call for the "Test connection" button. Returns a short description. */
function stripe_test_connection(string $key): string
{
    $balance = stripe_api('GET', '/v1/balance', [], $key);
    return 'Connected to Stripe (' . (!empty($balance['livemode']) ? 'live' : 'test') . ' mode).';
}

/* Authorize.net ---------------------------------------------------------------- */

function authnet_api_url(?bool $live = null): string
{
    return ($live ?? authnet_live()) ? 'https://api.authorize.net/xml/v1/request.api' : 'https://apitest.authorize.net/xml/v1/request.api';
}

function authnet_hosted_url(): string
{
    return authnet_live() ? 'https://accept.authorize.net/payment/payment' : 'https://test.authorize.net/payment/payment';
}

/**
 * Call the Authorize.net JSON API. $body is everything after merchantAuthentication, in schema order
 * (Authorize.net checks the element order). Throws RuntimeException with the gateway's message.
 */
function authnet_api(string $request, array $body, ?array $credentials = null, ?bool $live = null): array
{
    [$login, $key] = $credentials ?? [setting('authnet_login_id'), secret_setting('authnet_transaction_key')];
    if ($login === '' || $key === '') {
        throw new RuntimeException('The Authorize.net API Login ID or Transaction Key is not saved.');
    }
    $payload = [$request => ['merchantAuthentication' => ['name' => $login, 'transactionKey' => $key]] + $body];
    [$status, $response] = http_call('POST', authnet_api_url($live), ['Content-Type: application/json'], json_encode($payload, JSON_UNESCAPED_SLASHES));
    $data = json_decode(preg_replace('/^\xEF\xBB\xBF/', '', $response), true); // the API answers with a BOM
    if (!is_array($data)) {
        throw new RuntimeException('Authorize.net returned an unreadable answer (HTTP ' . $status . ').');
    }
    if (($data['messages']['resultCode'] ?? '') !== 'Ok') {
        throw new RuntimeException('Authorize.net: ' . (string) ($data['messages']['message'][0]['text'] ?? 'request failed'));
    }
    return $data;
}

/** Get an Accept Hosted token for the balance due. Returns [form URL, token]. */
function authnet_start_checkout(array $invoice): array
{
    $amount = invoice_balance($invoice);
    if ($amount <= 0) {
        throw new RuntimeException('Nothing is due on this invoice.');
    }
    $paymentId = payment_create_pending($invoice, 'authorizenet', 'card', $amount);
    $refId = 'P' . $paymentId;
    $returnOptions = [
        'showReceipt'   => false,
        'url'           => invoice_pay_url($invoice, '/return'),
        'urlText'       => 'Continue',
        'cancelUrl'     => invoice_pay_url($invoice),
        'cancelUrlText' => 'Cancel',
    ];
    $customer = valid_email($invoice['customer_email']) ? ['email' => $invoice['customer_email']] : null;
    $transaction = [
        'transactionType' => 'authCaptureTransaction',
        'amount'          => money_plain($amount),
        'order'           => ['invoiceNumber' => mb_substr($invoice['number'], 0, 20), 'description' => mb_substr('Invoice ' . $invoice['number'], 0, 255)],
    ];
    if ($customer) {
        $transaction['customer'] = $customer;
    }
    $settings = [
        ['settingName' => 'hostedPaymentReturnOptions', 'settingValue' => json_encode($returnOptions, JSON_UNESCAPED_SLASHES)],
        ['settingName' => 'hostedPaymentButtonOptions', 'settingValue' => json_encode(['text' => 'Pay ' . money($amount)])],
        ['settingName' => 'hostedPaymentOrderOptions', 'settingValue' => json_encode(['show' => true, 'merchantName' => setting('company_name')])],
        ['settingName' => 'hostedPaymentPaymentOptions', 'settingValue' => json_encode(['cardCodeRequired' => true, 'showCreditCard' => true, 'showBankAccount' => true])],
        ['settingName' => 'hostedPaymentCustomerOptions', 'settingValue' => json_encode(['showEmail' => true, 'requiredEmail' => false])],
    ];
    try {
        $data = authnet_api('getHostedPaymentPageRequest', ['refId' => $refId, 'transactionRequest' => $transaction, 'hostedPaymentSettings' => ['setting' => $settings]]);
    } catch (RuntimeException $e) {
        db_run("UPDATE payments SET status = 'failed', reference_note = 'Checkout could not be started' WHERE id = ?", [$paymentId]);
        throw $e;
    }
    db_run('UPDATE payments SET gateway_ref = ? WHERE id = ?', [$refId, $paymentId]);
    return [authnet_hosted_url(), (string) $data['token']];
}

/** Verify X-ANET-Signature: "sha512=" + HMAC-SHA512 of the raw body with the Signature Key. */
function authnet_signature_valid(string $payload, string $header, string $signatureKey): bool
{
    if ($signatureKey === '' || !preg_match('/^sha512=([a-f0-9]{128})$/i', trim($header), $m)) {
        return false;
    }
    return hash_equals(strtoupper(hash_hmac('sha512', $payload, $signatureKey)), strtoupper($m[1]));
}

function authnet_get_transaction(string $transId): array
{
    $data = authnet_api('getTransactionDetailsRequest', ['transId' => $transId]);
    return (array) ($data['transaction'] ?? []);
}

/** Amount of an Authorize.net transaction in cents (settled amount when known). */
function authnet_amount(array $txn): int
{
    $value = (string) ($txn['settleAmount'] ?? '');
    if ($value === '' || (float) $value <= 0) {
        $value = (string) ($txn['authAmount'] ?? '0');
    }
    return parse_money(number_format((float) $value, 2, '.', '')) ?? 0;
}

/**
 * Apply a verified Authorize.net event. $fetch(string $transId): array returns the transaction details
 * (getTransactionDetailsRequest); the amount and invoice number are always confirmed from there.
 */
function authnet_handle_event(array $event, callable $fetch): ?int
{
    $type = (string) ($event['eventType'] ?? '');
    $transId = (string) ($event['payload']['id'] ?? '');
    if ($transId === '' || !in_array($type, AUTHNET_EVENTS, true)) {
        return null;
    }

    if ($type === 'net.authorize.payment.authcapture.created') {
        if (db_value("SELECT COUNT(*) FROM payments WHERE gateway = 'authorizenet' AND gateway_txn = ? AND status IN ('succeeded','refunded')", [$transId])) {
            return null;
        }
        $txn = $fetch($transId);
        if ((string) ($txn['responseCode'] ?? '') !== '1' || in_array((string) ($txn['transactionStatus'] ?? ''), AUTHNET_FAILED_STATES, true)) {
            return null;
        }
        $invoice = db_one('SELECT * FROM invoices WHERE number = ?', [(string) ($txn['order']['invoiceNumber'] ?? '')]);
        if (!$invoice) {
            return null; // not one of ours (another app on the same account)
        }
        $amount = authnet_amount($txn);
        $method = isset($txn['payment']['bankAccount']) ? 'ach' : 'card';
        $payment = db_one("SELECT * FROM payments WHERE invoice_id = ? AND gateway = 'authorizenet' AND status = 'pending' AND gateway_txn IS NULL ORDER BY id DESC LIMIT 1", [$invoice['id']]);
        if (!$payment) {
            // No checkout opened from our pay page (e.g. charged in the Authorize.net dashboard with this invoice number).
            $paymentId = payment_create_pending($invoice, 'authorizenet', $method, $amount);
            $payment = payment_get($paymentId);
        }
        db_run('UPDATE payments SET method = ? WHERE id = ?', [$method, $payment['id']]);
        if ($invoice['status'] === 'void') {
            payment_reject_void($invoice, $payment, $amount, $transId);
        } elseif ($amount !== (int) $payment['amount_cents']) {
            payment_reject_mismatch($invoice, $payment, $amount, $transId, 'Authorize.net amount ' . $amount);
        } else {
            payment_mark_succeeded((int) $payment['id'], $amount, $transId);
        }
        return (int) $invoice['id'];
    }

    if ($type === 'net.authorize.payment.refund.created') {
        $txn = $fetch($transId);
        $original = db_one("SELECT * FROM payments WHERE gateway = 'authorizenet' AND gateway_txn = ? AND status = 'succeeded'", [(string) ($txn['refTransId'] ?? '')]);
        if (!$original) {
            return null;
        }
        payment_record_refund($original, authnet_amount($txn), 'refund:' . $transId, 'Refunded in Authorize.net');
        return (int) $original['invoice_id'];
    }

    // void.created: the voided transaction is the payment itself (a void cancels it before it settles).
    $original = db_one("SELECT * FROM payments WHERE gateway = 'authorizenet' AND gateway_txn = ? AND status = 'succeeded'", [$transId]);
    if (!$original) {
        $txn = $fetch($transId);
        $original = db_one("SELECT * FROM payments WHERE gateway = 'authorizenet' AND gateway_txn = ? AND status = 'succeeded'", [(string) ($txn['refTransId'] ?? '')]);
    }
    if (!$original) {
        return null;
    }
    $refunded = -(int) db_value("SELECT COALESCE(SUM(amount_cents), 0) FROM payments WHERE gateway = 'authorizenet' AND status = 'refunded' AND gateway_txn = ?", [$original['gateway_txn']]);
    payment_record_refund($original, (int) $original['amount_cents'] - $refunded, 'void:' . $transId, 'Voided in Authorize.net');
    return (int) $original['invoice_id'];
}

function authnet_test_connection(string $login, string $key, bool $live): string
{
    authnet_api('authenticateTestRequest', [], [$login, $key], $live);
    return 'Connected to Authorize.net (' . ($live ? 'live' : 'sandbox') . ').';
}

/* Webhook endpoint helpers ------------------------------------------------------- */

/** Send the HTTP answer now and keep running (emails go out afterwards). */
function webhook_respond(int $code, string $message): void
{
    http_response_code($code);
    header('Content-Type: text/plain; charset=utf-8');
    header('Cache-Control: no-store');
    header('X-Robots-Tag: noindex, nofollow');
    echo $message;
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    } elseif (function_exists('litespeed_finish_request')) {
        litespeed_finish_request();
    }
}
