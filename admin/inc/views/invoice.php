<?php
defined('ETOWAH_ADMIN') || exit;
$id = get_int('id');
$invoice = invoice_get($id);
if (!$invoice) {
    flash('error', 'That invoice no longer exists.');
    redirect(admin_url('invoices'));
}
$self = admin_url('invoice', ['id' => $id]);
$closed = in_array($invoice['status'], ['void', 'paid', 'refunded'], true);
$canRemind = in_array($invoice['status'], INVOICE_PAYABLE, true) && invoice_balance($invoice) > 0;
$errors = [];

if (is_post()) {
    require_post();
    $action = post_str('action');

    if ($action === 'send') {
        if ($closed) {
            flash('error', 'A paid, refunded or void invoice cannot be sent.');
        } elseif (!valid_email($invoice['customer_email'])) {
            flash('error', 'Add a valid customer email address first (Edit invoice).');
        } elseif ((int) $invoice['total_cents'] <= 0) {
            flash('error', 'The invoice total must be more than $0.00.');
        } elseif (!invoice_mail_sent($invoice)) {
            flash('error', 'The email could not be sent. Check the email settings, or copy the pay link and send it yourself.');
        } else {
            $first = $invoice['sent_at'] === null;
            db_transaction(function (PDO $pdo) use ($id) {
                $pdo->prepare('UPDATE invoices SET sent_at = NOW() WHERE id = ?')->execute([$id]);
                invoice_recalc($pdo, $id);
            });
            log_activity(($first ? 'Sent invoice ' : 'Resent invoice ') . $invoice['number'], 'invoice', $id);
            flash('success', 'Invoice emailed to ' . $invoice['customer_email'] . '.');
        }
        redirect($self);
    }

    if ($action === 'remind') {
        if (!$canRemind || !valid_email($invoice['customer_email'])) {
            flash('error', 'Reminders can only be sent for sent or overdue invoices with a balance.');
        } elseif (!invoice_mail_reminder($invoice)) {
            flash('error', 'The reminder could not be sent. Check the email settings.');
        } else {
            log_activity('Sent reminder for invoice ' . $invoice['number'], 'invoice', $id);
            flash('success', 'Reminder emailed to ' . $invoice['customer_email'] . '.');
        }
        redirect($self);
    }

    if ($action === 'payment') {
        $amount = parse_money(post_str('amount'));
        $method = in_array(post_str('method'), MANUAL_PAYMENT_METHODS, true) ? post_str('method') : '';
        $date = post_str('paid_on');
        $reference = mb_substr(post_str('reference'), 0, 255);
        $balance = invoice_balance($invoice);
        if (in_array($invoice['status'], ['void', 'refunded'], true)) {
            $errors['payment'] = 'Payments cannot be recorded on a void or refunded invoice.';
        } elseif ($amount === null || $amount <= 0) {
            $errors['amount'] = 'Enter the amount received, e.g. 1250.00.';
        } elseif ($amount > $balance) {
            $errors['amount'] = 'The amount is more than the balance due (' . money($balance) . ').';
        }
        if ($method === '') {
            $errors['method'] = 'Choose how it was paid.';
        }
        if (!valid_date($date) || $date > date('Y-m-d')) {
            $errors['paid_on'] = 'Choose the date the payment was received (not in the future).';
        }
        if (!$errors) {
            $completed = $date . ' ' . ($date === date('Y-m-d') ? date('H:i:s') : '12:00:00');
            [$updated, $payment] = db_transaction(function (PDO $pdo) use ($id, $amount, $method, $reference, $completed) {
                $pdo->prepare("INSERT INTO payments (invoice_id, gateway, method, amount_cents, status, reference_note, created_by, created_at, completed_at)
                               VALUES (?, 'manual', ?, ?, 'succeeded', ?, ?, NOW(), ?)")
                    ->execute([$id, $method, $amount, $reference, (int) $_SESSION['uid'], $completed]);
                $paymentId = (int) $pdo->lastInsertId();
                return [invoice_recalc($pdo, $id), payment_get_in($pdo, $paymentId)];
            });
            log_activity('Recorded ' . PAYMENT_METHODS[$method] . ' payment ' . money($amount) . ' on ' . $invoice['number'], 'invoice', $id);
            $mailed = isset($_POST['send_receipt']) && invoice_mail_receipt($updated, $payment);
            flash('success', 'Payment of ' . money($amount) . ' recorded.' . ($mailed ? ' A receipt was emailed to the customer.' : ''));
            redirect($self);
        }
    }

    if ($action === 'void') {
        $processing = (int) db_value("SELECT COUNT(*) FROM payments WHERE invoice_id = ? AND status = 'pending' AND gateway_txn IS NOT NULL", [$id]);
        if ($invoice['status'] === 'void') {
            flash('info', 'This invoice is already void.');
        } elseif ((int) $invoice['amount_paid_cents'] > 0 || $processing) {
            flash('error', 'This invoice has payments. Refund them in the payment gateway first (or for manual payments, return the money), then void it.');
        } else {
            db_run("UPDATE invoices SET status = 'void' WHERE id = ?", [$id]);
            log_activity('Voided invoice ' . $invoice['number'], 'invoice', $id);
            flash('success', 'Invoice ' . $invoice['number'] . ' is void. Its pay link no longer accepts payments.');
        }
        redirect($self);
    }
}

$items = invoice_items($id);
$payments = invoice_payments($id);
$balance = invoice_balance($invoice);
$status = invoice_display_status($invoice);
$overpaid = (int) $invoice['amount_paid_cents'] - (int) $invoice['total_cents'];
$timeline = db_all("SELECT a.action, a.created_at, u.name FROM activity_log a LEFT JOIN users u ON u.id = a.user_id
                     WHERE a.entity = 'invoice' AND a.entity_id = ? ORDER BY a.created_at, a.id", [$id]);
$payLink = invoice_pay_url($invoice);
$err = fn(string $k) => isset($errors[$k]) ? '<p class="field-error">' . e($errors[$k]) . '</p>' : '';
$cls = fn(string $k) => isset($errors[$k]) ? ' has-error' : '';
$paymentStatusLabel = function (array $p): string {
    if ($p['status'] === 'pending') {
        return $p['gateway_txn'] === null ? 'Checkout opened, not finished' : 'Processing';
    }
    return ['succeeded' => 'Received', 'failed' => 'Failed', 'refunded' => 'Refund'][$p['status']] ?? $p['status'];
};

admin_start('Invoice ' . $invoice['number'], 'invoices');
?>
        <p class="back"><a href="<?= e(admin_url('invoices')) ?>">← All invoices</a></p>
<?php if ($errors): ?>
        <p class="notice notice--error" role="alert"><?= e($errors['payment'] ?? 'The payment was not recorded. Please fix the highlighted fields.') ?></p>
<?php endif; ?>
        <div class="detail-grid">
          <div class="side-stack">
            <section class="panel">
              <div class="panel-head">
                <h2><?= e($invoice['number']) ?> · <?= e($invoice['customer_company'] ?: $invoice['customer_name']) ?></h2>
                <?= invoice_badge($invoice) ?>
              </div>
              <div class="invoice-summary">
                <div><span class="stat-label">Total</span><span class="summary-value"><?= e(money((int) $invoice['total_cents'])) ?></span></div>
                <div><span class="stat-label">Paid</span><span class="summary-value"><?= e(money((int) $invoice['amount_paid_cents'])) ?></span></div>
                <div><span class="stat-label">Balance due</span><span class="summary-value<?= $status === 'overdue' ? ' is-overdue' : '' ?>"><?= e(money($invoice['status'] === 'void' ? 0 : $balance)) ?></span></div>
              </div>
<?php if ($overpaid > 0): ?>
              <p class="notice">The customer paid <?= e(money($overpaid)) ?> more than the invoice total. Refund the difference in the payment gateway if needed.</p>
<?php endif; ?>
              <dl class="facts">
                <div><dt>Customer</dt><dd><?= e($invoice['customer_name'] ?: '—') ?><?php if ($invoice['customer_company'] !== ''): ?><br><?= e($invoice['customer_company']) ?><?php endif; ?></dd></div>
                <div><dt>Email</dt><dd><?= $invoice['customer_email'] !== '' ? '<a href="mailto:' . e($invoice['customer_email']) . '">' . e($invoice['customer_email']) . '</a>' : '—' ?></dd></div>
                <div><dt>Phone</dt><dd><?= $invoice['customer_phone'] !== '' ? '<a href="' . e(tel_href($invoice['customer_phone'])) . '">' . e($invoice['customer_phone']) . '</a>' : '—' ?></dd></div>
                <div><dt>Billing address</dt><dd><?= nl2br(e(trim((string) $invoice['billing_address']) ?: '—')) ?></dd></div>
                <div><dt>Issue date</dt><dd><?= e(fmt_date($invoice['issue_date'], false)) ?></dd></div>
                <div><dt>Due date</dt><dd><?= e(fmt_date($invoice['due_date'], false)) ?><?= $invoice['terms'] !== '' ? ' · ' . e($invoice['terms']) : '' ?></dd></div>
<?php if ($invoice['project_ref'] !== ''): ?>
                <div><dt>Project</dt><dd><?= e($invoice['project_ref']) ?></dd></div>
<?php endif; ?>
              </dl>

              <h3 class="subhead">Items</h3>
              <table class="table">
                <thead><tr><th scope="col">Description</th><th scope="col" class="num">Qty</th><th scope="col" class="num">Unit price</th><th scope="col" class="num">Amount</th></tr></thead>
                <tbody>
<?php foreach ($items as $item): ?>
                  <tr>
                    <td data-label="Description"><?= nl2br(e($item['description'])) ?></td>
                    <td data-label="Qty" class="num"><?= e(quantity_label(parse_quantity((string) $item['quantity']) ?? 0)) ?></td>
                    <td data-label="Unit price" class="num"><?= e(money((int) $item['unit_price_cents'])) ?></td>
                    <td data-label="Amount" class="num"><?= e(money((int) $item['line_total_cents'])) ?></td>
                  </tr>
<?php endforeach; ?>
                </tbody>
              </table>
              <dl class="invoice-sums">
                <div><dt>Subtotal</dt><dd><?= e(money((int) $invoice['subtotal_cents'])) ?></dd></div>
                <div><dt><?= e(setting('invoice_tax_label') ?: 'Tax') ?> (<?= e(tax_rate_label(tax_rate_units((string) $invoice['tax_rate']))) ?>%)</dt><dd><?= e(money((int) $invoice['tax_cents'])) ?></dd></div>
                <div class="is-total"><dt>Total</dt><dd><?= e(money((int) $invoice['total_cents'])) ?></dd></div>
              </dl>
<?php if (trim((string) $invoice['notes']) !== ''): ?>
              <h3 class="subhead">Notes</h3>
              <div class="message-box"><?= nl2br(e($invoice['notes'])) ?></div>
<?php endif; ?>
            </section>

            <section class="panel">
              <div class="panel-head"><h2>Payment history</h2></div>
<?php if ($payments): ?>
              <table class="table">
                <thead><tr><th scope="col">Date</th><th scope="col">How</th><th scope="col" class="num">Amount</th><th scope="col">Status</th><th scope="col">Reference</th></tr></thead>
                <tbody>
<?php foreach ($payments as $p): ?>
                  <tr>
                    <td data-label="Date"><?= e(fmt_date($p['completed_at'] ?: $p['created_at'])) ?></td>
                    <td data-label="How"><?= e(PAYMENT_METHODS[$p['method']] ?? $p['method']) ?><span class="cell-sub"><?= e(PAYMENT_GATEWAYS[$p['gateway']] ?? $p['gateway']) ?></span></td>
                    <td data-label="Amount" class="num"><?= e(money((int) $p['amount_cents'])) ?></td>
                    <td data-label="Status"><span class="badge badge--pay-<?= e($p['status']) ?>"><?= e($paymentStatusLabel($p)) ?></span></td>
                    <td data-label="Reference"><?= e($p['reference_note'] ?: ($p['gateway_txn'] ?? '') ?: '—') ?></td>
                  </tr>
<?php endforeach; ?>
                </tbody>
              </table>
<?php else: ?>
              <?= empty_state('No payments yet', 'Online payments appear here automatically. Record checks, cash and wires with “Record a payment”.') ?>
<?php endif; ?>
            </section>

            <section class="panel">
              <div class="panel-head"><h2>Timeline</h2></div>
              <ol class="timeline">
<?php foreach ($timeline as $t): ?>
                <li><span class="timeline-what"><?= e($t['action']) ?></span><span class="timeline-when"><?= e(fmt_date($t['created_at'])) ?><?= $t['name'] ? ' · ' . e($t['name']) : '' ?></span></li>
<?php endforeach; ?>
<?php if ($invoice['viewed_at'] && !array_filter($timeline, fn($t) => str_contains($t['action'], 'viewed'))): ?>
                <li><span class="timeline-what">Invoice viewed by the customer</span><span class="timeline-when"><?= e(fmt_date($invoice['viewed_at'])) ?></span></li>
<?php endif; ?>
              </ol>
            </section>
          </div>

          <div class="side-stack">
            <section class="panel">
              <div class="panel-head"><h2>Actions</h2></div>
              <div class="action-list">
<?php if (!$closed): ?>
                <form method="post"<?= $invoice['sent_at'] ? ' data-confirm="Email invoice ' . e($invoice['number']) . ' to ' . e($invoice['customer_email']) . ' again?" data-confirm-ok="Yes, resend"' : '' ?>>
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="send">
                  <button class="btn btn--primary btn--block" type="submit"><?= $invoice['sent_at'] ? 'Resend invoice' : 'Send invoice' ?></button>
                  <p class="hint">Emails <?= e($invoice['customer_email'] ?: 'the customer') ?> the invoice and the pay link.<?= $invoice['sent_at'] ? ' Last sent ' . e(fmt_date($invoice['sent_at'])) . '.' : '' ?></p>
                </form>
<?php endif; ?>
<?php if ($canRemind): ?>
                <form method="post" data-confirm="Email a payment reminder to <?= e($invoice['customer_email']) ?>?" data-confirm-ok="Send reminder">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="remind">
                  <button class="btn btn--ghost btn--block" type="submit">Send reminder</button>
                </form>
<?php endif; ?>
                <div class="field">
                  <label for="pay-link">Pay link</label>
                  <div class="copy-row">
                    <input id="pay-link" type="text" value="<?= e($payLink) ?>" readonly>
                    <button class="btn btn--ghost btn--sm" type="button" data-copy="pay-link"><?= admin_icon('copy') ?><span>Copy</span></button>
                  </div>
                  <p class="hint"><?= $invoice['status'] === 'draft' ? 'Customers can open this link once the invoice is sent.' : 'Anyone with this link can view and pay the invoice.' ?></p>
                </div>
                <a class="btn btn--ghost btn--block" href="/pay/<?= e($invoice['token']) ?>/print" target="_blank" rel="noopener"><?= admin_icon('print') ?><span>Print / Save as PDF</span></a>
<?php if (!$closed): ?>
                <a class="btn btn--ghost btn--block" href="<?= e(admin_url('invoice_edit', ['id' => $id])) ?>">Edit invoice</a>
<?php endif; ?>
                <a class="btn btn--ghost btn--block" href="<?= e(admin_url('invoice_edit', ['dup' => $id])) ?>">Duplicate as new draft</a>
              </div>
            </section>

<?php if (!in_array($invoice['status'], ['void', 'refunded', 'paid'], true)): ?>
            <section class="panel" id="record-payment">
              <div class="panel-head"><h2>Record a payment</h2></div>
              <form method="post" class="stack">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="payment">
                <div class="field<?= $cls('amount') ?>">
                  <label for="mp-amount">Amount ($)</label>
                  <input id="mp-amount" name="amount" type="text" inputmode="decimal" maxlength="16" required value="<?= e(is_post() && $errors ? post_str('amount') : money_plain($balance)) ?>">
                  <?= $err('amount') ?>
                </div>
                <div class="field<?= $cls('method') ?>">
                  <label for="mp-method">Method</label>
                  <select id="mp-method" name="method" required>
<?php foreach (MANUAL_PAYMENT_METHODS as $m): ?>
                    <option value="<?= $m ?>"<?= sel(post_str('method') === $m) ?>><?= e(PAYMENT_METHODS[$m]) ?></option>
<?php endforeach; ?>
                  </select>
                  <?= $err('method') ?>
                </div>
                <div class="field<?= $cls('paid_on') ?>">
                  <label for="mp-date">Date received</label>
                  <input id="mp-date" name="paid_on" type="date" max="<?= date('Y-m-d') ?>" required value="<?= e(is_post() && $errors ? post_str('paid_on') : date('Y-m-d')) ?>">
                  <?= $err('paid_on') ?>
                </div>
                <div class="field">
                  <label for="mp-ref">Reference <span class="optional">(optional)</span></label>
                  <input id="mp-ref" name="reference" type="text" maxlength="255" placeholder="e.g. Check #1042" value="<?= e(is_post() && $errors ? post_str('reference') : '') ?>">
                </div>
                <label class="check"><input type="checkbox" name="send_receipt" value="1" checked> <span>Email a receipt to the customer</span></label>
                <button class="btn btn--primary" type="submit">Record payment</button>
              </form>
            </section>
<?php endif; ?>

<?php if ($invoice['status'] !== 'void'): ?>
            <section class="panel">
              <form method="post" data-confirm="Void invoice <?= e($invoice['number']) ?>? The customer will no longer be able to pay it. This cannot be undone." data-confirm-ok="Yes, void it">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="void">
                <button class="btn btn--danger btn--sm" type="submit">Void invoice</button>
                <p class="hint">Use this for an invoice sent by mistake. The number is not reused.</p>
              </form>
            </section>
<?php endif; ?>
          </div>
        </div>
<?php admin_end(); ?>
