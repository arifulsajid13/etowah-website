<?php
/*
 * Authorize.net webhook: https://{site}/webhooks/authorizenet.php
 * Events: net.authorize.payment.authcapture.created, net.authorize.payment.refund.created,
 *         net.authorize.payment.void.created
 * No CSRF here: the X-ANET-Signature header (HMAC-SHA512 with the Signature Key) proves the sender instead.
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
if (!authnet_signature_valid($payload, (string) ($_SERVER['HTTP_X_ANET_SIGNATURE'] ?? ''), secret_setting('authnet_signature_key'))) {
    log_error('Authorize.net webhook rejected: invalid signature.');
    webhook_respond(401, 'Invalid signature');
    exit;
}
$event = json_decode($payload, true);
$eventId = is_array($event) ? (string) ($event['notificationId'] ?? '') : '';
if ($eventId === '') {
    webhook_respond(400, 'Invalid event');
    exit;
}

try {
    if (!in_array((string) ($event['eventType'] ?? ''), AUTHNET_EVENTS, true) || !gateway_event_claim('authorizenet', 'anet_' . $eventId, (string) $event['eventType'], $payload)) {
        webhook_respond(200, 'Ignored');
        exit;
    }
    $invoiceId = authnet_handle_event($event, 'authnet_get_transaction');
    gateway_event_done('anet_' . $eventId, $invoiceId);
} catch (Throwable $e) {
    // Authorize.net retries when it does not get a 200; the event is not marked processed.
    log_error('Authorize.net webhook ' . $eventId . ' failed: ' . $e->getMessage());
    webhook_respond(500, 'Error');
    exit;
}
webhook_respond(200, 'OK');
payments_run_after();
