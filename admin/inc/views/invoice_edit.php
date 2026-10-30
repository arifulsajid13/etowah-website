<?php
defined('ETOWAH_ADMIN') || exit;
$id = get_int('id');
$invoice = $id ? invoice_get($id) : null;
if ($id && !$invoice) {
    flash('error', 'That invoice no longer exists.');
    redirect(admin_url('invoices'));
}
if ($invoice && in_array($invoice['status'], ['void', 'paid', 'refunded'], true)) {
    flash('info', 'Paid, refunded and void invoices can no longer be edited.');
    redirect(admin_url('invoice', ['id' => $id]));
}
// Only drafts are fully editable. After sending, the amounts are locked; contact details, due date and notes can still change.
$full = !$invoice || $invoice['status'] === 'draft';
$customerFields = [
    'customer_name'    => ['Customer name', 160],
    'customer_company' => ['Company', 160],
    'customer_email'   => ['Email', 190],
    'customer_phone'   => ['Phone', 40],
];

$today = date('Y-m-d');
$source = $invoice;
if (!$invoice && ($dup = get_int('dup')) && ($copy = invoice_get($dup))) {
    // "Duplicate as new draft": same customer, items, tax and notes; new dates and number on save.
    $source = array_merge($copy, ['issue_date' => $today, 'due_date' => invoice_due_from_terms($today, $copy['terms'])]);
}
$values = $source ?: [
    'customer_name' => '', 'customer_company' => '', 'customer_email' => '', 'customer_phone' => '', 'billing_address' => '',
    'project_ref' => '', 'issue_date' => $today, 'terms' => setting('invoice_default_terms'),
    'due_date' => invoice_due_from_terms($today, setting('invoice_default_terms')),
    'tax_rate' => setting('invoice_default_tax_rate'), 'notes' => '',
];
$values['tax_rate'] = tax_rate_label(parse_tax_rate((string) $values['tax_rate']) ?? 0);
$lines = [];
foreach ($source ? invoice_items((int) $source['id']) : [] as $item) {
    $lines[] = ['description' => $item['description'], 'quantity' => quantity_label(parse_quantity((string) $item['quantity']) ?? 100), 'price' => money_plain((int) $item['unit_price_cents'])];
}
$errors = [];

if (is_post()) {
    require_post();
    $new = [];
    $parsed = [];
    $subtotal = $tax = $grand = $taxRate = 0;
    foreach ($customerFields as $key => [$label, $max]) {
        $new[$key] = mb_substr(post_str($key), 0, $max);
    }
    $new['billing_address'] = mb_substr(post_str('billing_address'), 0, 1000);
    $new['project_ref'] = mb_substr(post_str('project_ref'), 0, 200);
    $new['notes'] = mb_substr(post_str('notes'), 0, 5000);
    $new['due_date'] = post_str('due_date');
    if ($new['customer_name'] === '' && $new['customer_company'] === '') {
        $errors['customer_name'] = 'Enter the customer’s name or company.';
    }
    if (!valid_email($new['customer_email'])) {
        $errors['customer_email'] = 'Enter a valid email address. The invoice and receipts are sent there.';
    }

    if ($full) {
        $new['issue_date'] = post_str('issue_date');
        $new['terms'] = array_key_exists(post_str('terms'), INVOICE_TERMS) ? post_str('terms') : 'Net 30';
        if (!valid_date($new['issue_date'])) {
            $errors['issue_date'] = 'Choose the issue date.';
            $new['issue_date'] = $today;
        }
        if ($new['due_date'] === '') {
            $new['due_date'] = invoice_due_from_terms($new['issue_date'], $new['terms']);
        }
        $taxRate = parse_tax_rate(post_str('tax_rate'));
        if ($taxRate === null) {
            $errors['tax_rate'] = 'Enter the tax rate as a percentage, e.g. 7 or 7.25 (0 for no tax).';
            $taxRate = 0;
        }
        $new['tax_rate'] = tax_rate_label($taxRate);

        // Line items: totals are always recalculated here, never taken from the form.
        $descriptions = (array) ($_POST['item_description'] ?? []);
        $quantities = (array) ($_POST['item_quantity'] ?? []);
        $prices = (array) ($_POST['item_price'] ?? []);
        $lines = [];
        $parsed = [];
        foreach (array_keys($descriptions) as $i) {
            $desc = is_string($descriptions[$i]) ? mb_substr(trim($descriptions[$i]), 0, 500) : '';
            $qty = is_string($quantities[$i] ?? null) ? trim($quantities[$i]) : '';
            $price = is_string($prices[$i] ?? null) ? trim($prices[$i]) : '';
            if ($desc === '' && $price === '' && ($qty === '' || $qty === '1')) {
                continue; // empty row
            }
            $lines[] = ['description' => $desc, 'quantity' => $qty, 'price' => $price];
            $q = parse_quantity($qty);
            $p = parse_money($price);
            if ($desc === '' || $q === null || $p === null) {
                $errors['items'] = 'Each line needs a description, a quantity above 0, and a price (e.g. 1250.00).';
                continue;
            }
            $parsed[] = ['description' => $desc, 'quantity' => $q, 'unit_price_cents' => $p];
        }
        if (!$lines) {
            $errors['items'] = 'Add at least one line item.';
        } elseif (count($lines) > INVOICE_MAX_ITEMS) {
            $errors['items'] = 'Use ' . INVOICE_MAX_ITEMS . ' lines or fewer.';
        }
        [$parsed, $subtotal, $tax, $grand] = invoice_calc($parsed, $taxRate);
        if (!isset($errors['items']) && $grand <= 0) {
            $errors['items'] = 'The invoice total must be more than $0.00.';
        } elseif ($grand > INVOICE_MAX_CENTS) {
            $errors['items'] = 'The invoice total is too large.';
        }
    } else {
        $new['issue_date'] = $invoice['issue_date'];
    }
    if (!valid_date($new['due_date'])) {
        $errors['due_date'] = 'Choose the due date.';
    } elseif ($new['due_date'] < $new['issue_date']) {
        $errors['due_date'] = 'The due date cannot be before the issue date.';
    }

    if (!$errors) {
        $common = [$new['customer_name'], $new['customer_company'], $new['customer_email'], $new['customer_phone'], $new['billing_address'], $new['project_ref'], $new['due_date'], $new['notes']];
        if (!$invoice) {
            $saved = db_transaction(function (PDO $pdo) use ($new, $common, $parsed, $subtotal, $tax, $grand, $taxRate) {
                $number = invoice_take_number($pdo);
                $pdo->prepare('INSERT INTO invoices (number, token, customer_name, customer_company, customer_email, customer_phone, billing_address, project_ref, due_date, notes,
                                   issue_date, terms, tax_rate, subtotal_cents, tax_cents, total_cents, status, created_by, created_at)
                               VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, \'draft\', ?, NOW())')
                    ->execute(array_merge([$number, invoice_new_token()], $common, [$new['issue_date'], $new['terms'], tax_rate_label($taxRate), $subtotal, $tax, $grand, (int) $_SESSION['uid']]));
                $newId = (int) $pdo->lastInsertId();
                invoice_save_items($pdo, $newId, $parsed);
                return [$newId, $number];
            });
            [$id, $number] = $saved;
            log_activity('Created invoice ' . $number . (get_int('dup') ? ' (duplicate)' : ''), 'invoice', $id);
            flash('success', 'Invoice ' . $number . ' saved as a draft. Send it when you are ready.');
        } else {
            db_transaction(function (PDO $pdo) use ($id, $new, $common, $parsed, $subtotal, $tax, $grand, $taxRate, $full) {
                $pdo->prepare('UPDATE invoices SET customer_name = ?, customer_company = ?, customer_email = ?, customer_phone = ?, billing_address = ?, project_ref = ?, due_date = ?, notes = ? WHERE id = ?')
                    ->execute(array_merge($common, [$id]));
                if ($full) {
                    $pdo->prepare('UPDATE invoices SET issue_date = ?, terms = ?, tax_rate = ?, subtotal_cents = ?, tax_cents = ?, total_cents = ? WHERE id = ?')
                        ->execute([$new['issue_date'], $new['terms'], tax_rate_label($taxRate), $subtotal, $tax, $grand, $id]);
                    $pdo->prepare('DELETE FROM invoice_items WHERE invoice_id = ?')->execute([$id]);
                    invoice_save_items($pdo, $id, $parsed);
                }
                invoice_recalc($pdo, $id);
            });
            log_activity('Edited invoice ' . $invoice['number'], 'invoice', $id);
            flash('success', 'Invoice ' . $invoice['number'] . ' saved.');
        }
        redirect(admin_url('invoice', ['id' => $id]));
    }
    $values = array_merge($values, $new);
}

function invoice_save_items(PDO $pdo, int $invoiceId, array $lines): void
{
    $stmt = $pdo->prepare('INSERT INTO invoice_items (invoice_id, sort, description, quantity, unit_price_cents, line_total_cents) VALUES (?, ?, ?, ?, ?, ?)');
    foreach (array_values($lines) as $i => $line) {
        $stmt->execute([$invoiceId, $i, $line['description'], quantity_label($line['quantity']), $line['unit_price_cents'], $line['line_total_cents']]);
    }
}

if (!$lines) {
    $lines[] = ['description' => '', 'quantity' => '1', 'price' => ''];
}
// Previous customers (latest invoice per email) for the "reuse" picker.
$customers = $full ? db_all('SELECT customer_name, customer_company, customer_email, customer_phone, billing_address FROM invoices
                              WHERE id IN (SELECT MAX(id) FROM invoices WHERE customer_email <> \'\' GROUP BY customer_email) ORDER BY customer_company, customer_name LIMIT 300') : [];
$err = fn(string $k) => isset($errors[$k]) ? '<p class="field-error">' . e($errors[$k]) . '</p>' : '';
$cls = fn(string $k) => isset($errors[$k]) ? ' has-error' : '';
$ro = $full ? '' : ' disabled';

admin_start($invoice ? 'Edit invoice ' . $invoice['number'] : 'New invoice', 'invoices');
?>
        <p class="back"><a href="<?= e($invoice ? admin_url('invoice', ['id' => $id]) : admin_url('invoices')) ?>">← <?= $invoice ? 'Back to invoice ' . e($invoice['number']) : 'All invoices' ?></a></p>
<?php if ($errors): ?>
        <p class="notice notice--error" role="alert">Please fix the highlighted fields below.</p>
<?php endif; ?>
<?php if (!$full): ?>
        <p class="notice">This invoice has been sent, so its amounts are locked. You can still change the customer’s details, the due date and the notes. To change amounts, void it and duplicate it as a new draft.</p>
<?php endif; ?>
        <form method="post" class="edit-form" data-invoice-form novalidate>
          <?= csrf_field() ?>
          <div class="detail-grid">
            <div class="side-stack">
              <section class="panel">
                <div class="panel-head"><h2>Customer</h2></div>
<?php if ($customers): ?>
                <div class="field">
                  <label for="inv-pick">Reuse a previous customer <span class="optional">(optional)</span></label>
                  <select id="inv-pick" data-customer-pick>
                    <option value="">Choose a customer…</option>
<?php foreach ($customers as $c): ?>
                    <option value="1" data-customer="<?= e(json_encode($c, JSON_UNESCAPED_UNICODE)) ?>"><?= e(trim(($c['customer_company'] !== '' ? $c['customer_company'] . ' — ' : '') . $c['customer_name'] . ' · ' . $c['customer_email'])) ?></option>
<?php endforeach; ?>
                  </select>
                  <p class="hint">Fills in the fields below. You can still change them.</p>
                </div>
<?php endif; ?>
                <div class="form-grid">
<?php foreach ($customerFields as $key => [$label, $max]): ?>
                  <div class="field<?= $cls($key) ?>">
                    <label for="inv-<?= $key ?>"><?= e($label) ?><?= in_array($key, ['customer_company', 'customer_phone'], true) ? ' <span class="optional">(optional)</span>' : '' ?></label>
                    <input id="inv-<?= $key ?>" name="<?= $key ?>" type="<?= $key === 'customer_email' ? 'email' : ($key === 'customer_phone' ? 'tel' : 'text') ?>" maxlength="<?= $max ?>" value="<?= e((string) $values[$key]) ?>"<?= $key === 'customer_email' ? ' required' : '' ?>>
                    <?= $err($key) ?>
                  </div>
<?php endforeach; ?>
                  <div class="field field--full">
                    <label for="inv-billing_address">Billing address <span class="optional">(optional)</span></label>
                    <textarea id="inv-billing_address" name="billing_address" rows="3" maxlength="1000"><?= e((string) $values['billing_address']) ?></textarea>
                  </div>
                  <div class="field field--full">
                    <label for="inv-project_ref">Project reference <span class="optional">(optional)</span></label>
                    <input id="inv-project_ref" name="project_ref" type="text" maxlength="200" value="<?= e((string) $values['project_ref']) ?>" placeholder="e.g. Bartow pump station, PO 4471">
                  </div>
                </div>
              </section>

              <section class="panel">
                <div class="panel-head"><h2>Line items</h2></div>
                <div class="field<?= $cls('items') ?>">
                  <?= $err('items') ?>
                  <table class="items-table" data-items>
                    <thead><tr><th scope="col">Description</th><th scope="col" class="num">Qty</th><th scope="col" class="num">Unit price ($)</th><th scope="col" class="num">Amount</th><th scope="col"><span class="visually-hidden">Actions</span></th></tr></thead>
                    <tbody data-item-rows>
<?php foreach ($lines as $line): ?>
                      <tr data-item>
                        <td data-label="Description"><textarea name="item_description[]" rows="1" maxlength="500" aria-label="Description"<?= $ro ?>><?= e((string) $line['description']) ?></textarea></td>
                        <td data-label="Qty" class="num"><input name="item_quantity[]" type="text" inputmode="decimal" maxlength="12" value="<?= e((string) $line['quantity']) ?>" aria-label="Quantity" data-item-qty<?= $ro ?>></td>
                        <td data-label="Unit price" class="num"><input name="item_price[]" type="text" inputmode="decimal" maxlength="16" value="<?= e((string) $line['price']) ?>" aria-label="Unit price in dollars" data-item-price<?= $ro ?>></td>
                        <td data-label="Amount" class="num" data-item-total>—</td>
                        <td class="item-actions">
<?php if ($full): ?>
                          <button class="icon-btn icon-btn--sm" type="button" data-item-up aria-label="Move line up"><?= admin_icon('up') ?></button>
                          <button class="icon-btn icon-btn--sm" type="button" data-item-down aria-label="Move line down"><?= admin_icon('down') ?></button>
                          <button class="icon-btn icon-btn--sm" type="button" data-item-remove aria-label="Remove line"><?= admin_icon('close') ?></button>
<?php endif; ?>
                        </td>
                      </tr>
<?php endforeach; ?>
                    </tbody>
                  </table>
<?php if ($full): ?>
                  <button class="btn btn--ghost btn--sm" type="button" data-item-add>+ Add line</button>
                  <p class="hint">Totals update as you type and are checked again when you save.</p>
<?php endif; ?>
                </div>
                <dl class="invoice-sums" data-sums data-tax-label="<?= e(setting('invoice_tax_label') ?: 'Tax') ?>">
                  <div><dt>Subtotal</dt><dd data-sum-subtotal>—</dd></div>
                  <div><dt data-sum-tax-label>Tax</dt><dd data-sum-tax>—</dd></div>
                  <div class="is-total"><dt>Total</dt><dd data-sum-total>—</dd></div>
                </dl>
              </section>
            </div>

            <div class="side-stack">
              <section class="panel">
                <div class="panel-head"><h2>Dates and terms</h2></div>
                <div class="stack">
                  <div class="field<?= $cls('issue_date') ?>">
                    <label for="inv-issue">Issue date</label>
                    <input id="inv-issue" name="issue_date" type="date" value="<?= e((string) $values['issue_date']) ?>" required data-issue-date<?= $ro ?>>
                    <?= $err('issue_date') ?>
                  </div>
                  <div class="field">
                    <label for="inv-terms">Payment terms</label>
                    <select id="inv-terms" name="terms" data-terms<?= $ro ?>>
<?php foreach (INVOICE_TERMS as $label => $days): ?>
                      <option value="<?= e($label) ?>" data-days="<?= $days ?>"<?= sel($values['terms'] === $label) ?>><?= e($label) ?></option>
<?php endforeach; ?>
                    </select>
                  </div>
                  <div class="field<?= $cls('due_date') ?>">
                    <label for="inv-due">Due date</label>
                    <input id="inv-due" name="due_date" type="date" value="<?= e((string) $values['due_date']) ?>" required data-due-date>
                    <p class="hint">Set from the issue date and terms. You can change it.</p>
                    <?= $err('due_date') ?>
                  </div>
                  <div class="field<?= $cls('tax_rate') ?>">
                    <label for="inv-tax"><?= e(setting('invoice_tax_label') ?: 'Tax') ?> rate (%)</label>
                    <input id="inv-tax" name="tax_rate" type="text" inputmode="decimal" maxlength="7" value="<?= e((string) $values['tax_rate']) ?>" data-tax-rate<?= $ro ?>>
                    <p class="hint">0 for no tax.</p>
                    <?= $err('tax_rate') ?>
                  </div>
                </div>
              </section>
              <section class="panel">
                <div class="panel-head"><h2>Notes</h2></div>
                <div class="field">
                  <label for="inv-notes">Notes for the customer <span class="optional">(optional)</span></label>
                  <textarea id="inv-notes" name="notes" rows="5" maxlength="5000" placeholder="e.g. Payment for work completed through October 31."><?= e((string) $values['notes']) ?></textarea>
                  <p class="hint">Shown on the invoice.</p>
                </div>
              </section>
              <div class="button-row button-row--sticky">
                <button class="btn btn--primary" type="submit"><?= $invoice ? 'Save invoice' : 'Save as draft' ?></button>
                <a class="btn btn--ghost" href="<?= e($invoice ? admin_url('invoice', ['id' => $id]) : admin_url('invoices')) ?>">Cancel</a>
              </div>
            </div>
          </div>
        </form>
<?php if ($full): ?>
        <template id="item-row-template">
          <tr data-item>
            <td data-label="Description"><textarea name="item_description[]" rows="1" maxlength="500" aria-label="Description"></textarea></td>
            <td data-label="Qty" class="num"><input name="item_quantity[]" type="text" inputmode="decimal" maxlength="12" value="1" aria-label="Quantity" data-item-qty></td>
            <td data-label="Unit price" class="num"><input name="item_price[]" type="text" inputmode="decimal" maxlength="16" value="" aria-label="Unit price in dollars" data-item-price></td>
            <td data-label="Amount" class="num" data-item-total>—</td>
            <td class="item-actions">
              <button class="icon-btn icon-btn--sm" type="button" data-item-up aria-label="Move line up"><?= admin_icon('up') ?></button>
              <button class="icon-btn icon-btn--sm" type="button" data-item-down aria-label="Move line down"><?= admin_icon('down') ?></button>
              <button class="icon-btn icon-btn--sm" type="button" data-item-remove aria-label="Remove line"><?= admin_icon('close') ?></button>
            </td>
          </tr>
        </template>
<?php endif; ?>
<?php admin_end(); ?>
