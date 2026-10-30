<?php
/*
 * Stripe webhook: https://{site}/webhooks/stripe.php
 * Events: checkout.session.completed, checkout.session.async_payment_succeeded,
 *         checkout.session.async_payment_failed, charge.refunded
 * No CSRF here: the Stripe-Signature header (HMAC with the webhook signing secret) proves the sender instead.
 */
require dirname(__DIR__) . '/app/bootstrap.php';
require_once APP . '/auth.php'; // log_activity()
require_once APP . '/payments.php';

header('X-Robots-Tag: noindex, nofollow');
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    webhook_respond(405, 'Method not allowed');
    exit;
}
$payload = (string) file_get_contents('php://input', false, null, 0, 1048576);
if (!stripe_signature_valid($payload, (string) ($_SERVER['HTTP_STRIPE_SIGNATURE'] ?? ''), stripe_webhook_secret())) {
    log_error('Stripe webhook rejected: invalid or old signature.');
    webhook_respond(400, 'Invalid signature');
    exit;
}
$event = json_decode($payload, true);
$eventId = is_array($event) ? (string) ($event['id'] ?? '') : '';
if ($eventId === '' || !str_starts_with($eventId, 'evt_')) {
    webhook_respond(400, 'Invalid event');
    exit;
}

try {
    if (!in_array((string) ($event['type'] ?? ''), STRIPE_EVENTS, true) || !gateway_event_claim('stripe', $eventId, (string) $event['type'], $payload)) {
        webhook_respond(200, 'Ignored');
        exit;
    }
    $invoiceId = stripe_handle_event($event);
    gateway_event_done($eventId, $invoiceId);
} catch (Throwable $e) {
    // 500 makes Stripe retry later; the event is not marked processed.
    log_error('Stripe webhook ' . $eventId . ' failed: ' . $e->getMessage());
    webhook_respond(500, 'Error');
    exit;
}
webhook_respond(200, 'OK');
payments_run_after();
