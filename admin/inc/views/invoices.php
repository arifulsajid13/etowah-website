<?php
defined('ETOWAH_ADMIN') || exit;
$f = invoice_filters();
[$where, $params, $order] = invoice_query($f);
$total = (int) db_value("SELECT COUNT(*) FROM invoices WHERE {$where}", $params);
[$offset, $pages, $page] = paginate($total, get_int('page'));
$rows = db_all("SELECT id, number, customer_name, customer_company, customer_email, issue_date, due_date, total_cents, amount_paid_cents, status
                  FROM invoices WHERE {$where} ORDER BY {$order} LIMIT " . PER_PAGE . " OFFSET {$offset}", $params);
$filtered = $f['q'] !== '' || $f['status'] !== '';
$statusOptions = ['open' => 'Unpaid (all)'] + array_diff_key(INVOICE_STATUSES, ['overdue' => 1]) + ['overdue' => 'Overdue'];

admin_start('Invoices', 'invoices');
?>
        <div class="page-actions">
          <p class="lead">Create invoices, email them to customers, and track payments. Customers pay online through a secure link.</p>
          <a class="btn btn--primary" href="<?= e(admin_url('invoice_edit')) ?>">New invoice</a>
        </div>

        <form class="filters" method="get" action="/admin/">
          <input type="hidden" name="p" value="invoices">
          <div class="field field--grow">
            <label for="f-q">Search</label>
            <input id="f-q" name="q" type="search" value="<?= e($f['q']) ?>" placeholder="Number, customer, company, email, project…">
          </div>
          <div class="field">
            <label for="f-status">Status</label>
            <select id="f-status" name="status">
              <option value="">All statuses</option>
<?php foreach ($statusOptions as $k => $label): ?>
              <option value="<?= $k ?>"<?= sel($f['status'] === $k) ?>><?= e($label) ?></option>
<?php endforeach; ?>
            </select>
          </div>
          <div class="filters-actions">
            <button class="btn btn--primary" type="submit">Apply</button>
<?php if ($filtered): ?>
            <a class="btn btn--ghost" href="<?= e(admin_url('invoices')) ?>">Clear</a>
<?php endif; ?>
          </div>
        </form>

        <section class="panel">
          <div class="panel-head">
            <h2><?= $total ?> <?= $total === 1 ? 'invoice' : 'invoices' ?><?= $filtered ? ' found' : '' ?></h2>
<?php if ($total): ?>
            <a class="btn btn--ghost btn--sm" href="<?= e('/admin/export.php?' . http_build_query(['type' => 'invoices'] + array_filter($f))) ?>"><?= admin_icon('download') ?><span>Export CSV</span></a>
<?php endif; ?>
          </div>
<?php if ($rows): ?>
          <table class="table table--invoices">
            <thead><tr><th scope="col">Number</th><th scope="col">Customer</th><th scope="col">Issued</th><th scope="col">Due</th><th scope="col" class="num">Total</th><th scope="col" class="num">Balance</th><th scope="col">Status</th></tr></thead>
            <tbody>
<?php foreach ($rows as $row): ?>
              <tr>
                <td data-label="Number"><a class="row-link" href="<?= e(admin_url('invoice', ['id' => $row['id']])) ?>"><strong><?= e($row['number']) ?></strong></a></td>
                <td data-label="Customer"><?= e($row['customer_name'] ?: $row['customer_email'] ?: '—') ?><?php if ($row['customer_company'] !== ''): ?><span class="cell-sub"><?= e($row['customer_company']) ?></span><?php endif; ?></td>
                <td data-label="Issued"><?= e(fmt_date($row['issue_date'], false)) ?></td>
                <td data-label="Due"><?= e(fmt_date($row['due_date'], false)) ?></td>
                <td data-label="Total" class="num"><?= e(money((int) $row['total_cents'])) ?></td>
                <td data-label="Balance" class="num"><?= e(money($row['status'] === 'void' ? 0 : invoice_balance($row))) ?></td>
                <td data-label="Status"><?= invoice_badge($row) ?></td>
              </tr>
<?php endforeach; ?>
            </tbody>
          </table>
          <?= pagination_html($pages, $page, 'invoices', array_filter($f)) ?>
<?php elseif ($filtered): ?>
          <?= empty_state('No invoices match these filters', 'Try a different search word or status.', admin_url('invoices'), 'Clear filters') ?>
<?php else: ?>
          <?= empty_state('No invoices yet', 'Create your first invoice, then send it to the customer by email. They can pay online with the link in the email.', admin_url('invoice_edit'), 'New invoice') ?>
<?php endif; ?>
        </section>
<?php admin_end(); ?>
