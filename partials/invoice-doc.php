<?php
/*
 * The invoice itself, shared by the pay page and the printable version.
 * Needs: $invoice, $items. Shows no internal ids.
 */
$status = invoice_display_status($invoice);
$balance = invoice_balance($invoice);
$taxRate = tax_rate_units((string) $invoice['tax_rate']);
$billing = trim((string) $invoice['billing_address']);
?>
        <article class="invoice-doc" aria-labelledby="invoice-title">
          <header class="invoice-band">
            <img class="invoice-logo" src="/assets/images/logo/logo-horizontal.png" alt="<?= e(setting('company_name')) ?>" width="739" height="128">
            <div class="invoice-band-meta">
              <h1 class="invoice-title" id="invoice-title">Invoice <?= e($invoice['number']) ?></h1>
              <span class="invoice-status invoice-status--<?= e($status) ?>"><?= e(INVOICE_STATUSES[$status] ?? $status) ?></span>
            </div>
          </header>

          <div class="invoice-parties">
            <div>
              <h2 class="invoice-label">From</h2>
              <p><?= nl2br(e(setting('invoice_payable_to'))) ?></p>
              <p class="invoice-contact"><?= e(setting('phone')) ?><br><?= e(setting('email')) ?></p>
            </div>
            <div>
              <h2 class="invoice-label">Bill to</h2>
              <p>
<?php if ($invoice['customer_name'] !== ''): ?>
                <strong><?= e($invoice['customer_name']) ?></strong><br>
<?php endif; ?>
<?php if ($invoice['customer_company'] !== ''): ?>
                <?= e($invoice['customer_company']) ?><br>
<?php endif; ?>
<?php if ($billing !== ''): ?>
                <?= nl2br(e($billing)) ?><br>
<?php endif; ?>
                <?= e($invoice['customer_email']) ?>
              </p>
            </div>
            <dl class="invoice-dates">
              <div><dt>Invoice number</dt><dd><?= e($invoice['number']) ?></dd></div>
              <div><dt>Issue date</dt><dd><?= e(date('M j, Y', strtotime($invoice['issue_date']))) ?></dd></div>
              <div><dt>Due date</dt><dd><?= e(date('M j, Y', strtotime($invoice['due_date']))) ?></dd></div>
<?php if ($invoice['terms'] !== ''): ?>
              <div><dt>Terms</dt><dd><?= e($invoice['terms']) ?></dd></div>
<?php endif; ?>
<?php if ($invoice['project_ref'] !== ''): ?>
              <div><dt>Project</dt><dd><?= e($invoice['project_ref']) ?></dd></div>
<?php endif; ?>
            </dl>
          </div>

          <table class="invoice-items">
            <thead>
              <tr><th scope="col">Description</th><th scope="col" class="num">Qty</th><th scope="col" class="num">Unit price</th><th scope="col" class="num">Amount</th></tr>
            </thead>
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

          <dl class="invoice-totals">
            <div><dt>Subtotal</dt><dd><?= e(money((int) $invoice['subtotal_cents'])) ?></dd></div>
<?php if ($taxRate > 0 || (int) $invoice['tax_cents'] !== 0): ?>
            <div><dt><?= e(setting('invoice_tax_label') ?: 'Tax') ?> (<?= e(tax_rate_label($taxRate)) ?>%)</dt><dd><?= e(money((int) $invoice['tax_cents'])) ?></dd></div>
<?php endif; ?>
            <div class="is-total"><dt>Total</dt><dd><?= e(money((int) $invoice['total_cents'])) ?></dd></div>
<?php if ((int) $invoice['amount_paid_cents'] !== 0): ?>
            <div><dt>Amount paid</dt><dd>−<?= e(money((int) $invoice['amount_paid_cents'])) ?></dd></div>
<?php endif; ?>
            <div class="is-balance"><dt>Balance due</dt><dd><?= e(money($invoice['status'] === 'void' ? 0 : $balance)) ?></dd></div>
          </dl>

<?php if (trim((string) $invoice['notes']) !== ''): ?>
          <div class="invoice-notes">
            <h2 class="invoice-label">Notes</h2>
            <p><?= nl2br(e($invoice['notes'])) ?></p>
          </div>
<?php endif; ?>
<?php if (setting('invoice_footer_note') !== ''): ?>
          <p class="invoice-footer-note"><?= nl2br(e(setting('invoice_footer_note'))) ?></p>
<?php endif; ?>
        </article>
