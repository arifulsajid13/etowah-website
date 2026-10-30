<?php
declare(strict_types=1);

/*
 * Invoices: money helpers, totals, numbering, balances and status.
 * All amounts are integer cents (USD). Totals are always recalculated here, never taken from a form.
 */

require_once APP . '/crypto.php';

const INVOICE_STATUSES = [
    'draft'          => 'Draft',
    'sent'           => 'Sent',
    'viewed'         => 'Viewed',
    'processing'     => 'Processing',
    'partially_paid' => 'Partially paid',
    'paid'           => 'Paid',
    'overdue'        => 'Overdue',
    'void'           => 'Void',
    'refunded'       => 'Refunded',
];

/** Payment terms => days after the issue date. */
const INVOICE_TERMS = ['Due on receipt' => 0, 'Net 7' => 7, 'Net 10' => 10, 'Net 15' => 15, 'Net 30' => 30, 'Net 45' => 45, 'Net 60' => 60];

const PAYMENT_METHODS = ['card' => 'Card', 'ach' => 'Bank (ACH)', 'check' => 'Check', 'cash' => 'Cash', 'wire' => 'Wire transfer', 'other' => 'Other'];
const MANUAL_PAYMENT_METHODS = ['check', 'cash', 'wire', 'other'];
const PAYMENT_GATEWAYS = ['stripe' => 'Stripe', 'authorizenet' => 'Authorize.net', 'manual' => 'Recorded manually'];

/** Statuses that can still receive a payment. */
const INVOICE_PAYABLE = ['sent', 'viewed', 'partially_paid', 'overdue'];

const INVOICE_MAX_ITEMS = 100;
const INVOICE_MAX_CENTS = 99999999999; // $999,999,999.99

/** 123456 → "$1,234.56" */
function money(int $cents): string
{
    $sign = $cents < 0 ? '-' : '';
    $cents = abs($cents);
    return $sign . '$' . number_format(intdiv($cents, 100)) . '.' . str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
}

/** 123456 → "1234.56" (form fields and gateway amounts). */
function money_plain(int $cents): string
{
    $sign = $cents < 0 ? '-' : '';
    $cents = abs($cents);
    return $sign . intdiv($cents, 100) . '.' . str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
}

/** "$1,234.5" → 123450. Null when not a valid non-negative amount with up to 2 decimals. No floats involved. */
function parse_money(string $value): ?int
{
    $value = str_replace([',', '$', ' '], '', trim($value));
    if (!preg_match('/^(\d{1,9})(?:\.(\d{0,2}))?$/', $value, $m) && !preg_match('/^()\.(\d{1,2})$/', $value, $m)) {
        return null;
    }
    return (int) ($m[1] ?: '0') * 100 + (int) str_pad($m[2] ?? '', 2, '0');
}

/** Quantity "2.5" → 250 (hundredths). Null when invalid or zero. */
function parse_quantity(string $value): ?int
{
    $value = str_replace(',', '', trim($value));
    if (!preg_match('/^(\d{1,7})(?:\.(\d{0,2}))?$/', $value, $m)) {
        return null;
    }
    $hundredths = (int) $m[1] * 100 + (int) str_pad($m[2] ?? '', 2, '0');
    return $hundredths > 0 ? $hundredths : null;
}

/** Tax rate "7.25" (percent) → 7250 (thousandths of a percent). Null when invalid or over 100%. */
function parse_tax_rate(string $value): ?int
{
    $value = rtrim(trim($value), '%');
    if ($value === '') {
        return 0;
    }
    if (!preg_match('/^(\d{1,3})(?:\.(\d{0,3}))?$/', $value, $m)) {
        return null;
    }
    $rate = (int) $m[1] * 1000 + (int) str_pad($m[2] ?? '', 3, '0');
    return $rate <= 100000 ? $rate : null;
}

/** DECIMAL(6,3) from the database → thousandths. */
function tax_rate_units(string $decimal): int
{
    return parse_tax_rate($decimal) ?? 0;
}

/** 7250 → "7.25" */
function tax_rate_label(int $thousandths): string
{
    $text = intdiv($thousandths, 1000) . '.' . str_pad((string) ($thousandths % 1000), 3, '0', STR_PAD_LEFT);
    return rtrim(rtrim($text, '0'), '.');
}

/** Quantity in hundredths → "2.5" */
function quantity_label(int $hundredths): string
{
    $text = intdiv($hundredths, 100) . '.' . str_pad((string) ($hundredths % 100), 2, '0', STR_PAD_LEFT);
    return rtrim(rtrim($text, '0'), '.');
}

/** Rounded half up, integers only. */
function div_round(int $numerator, int $denominator): int
{
    return intdiv($numerator + intdiv($denominator, 2), $denominator);
}

/**
 * Totals for a list of lines [['quantity' => hundredths, 'unit_price_cents' => int], ...] and a tax rate in thousandths.
 * Returns [lines with line_total_cents, subtotal, tax, total].
 */
function invoice_calc(array $lines, int $taxRate): array
{
    $subtotal = 0;
    foreach ($lines as &$line) {
        $line['line_total_cents'] = div_round($line['quantity'] * $line['unit_price_cents'], 100);
        $subtotal += $line['line_total_cents'];
    }
    unset($line);
    $tax = div_round($subtotal * $taxRate, 100000);
    return [$lines, $subtotal, $tax, $subtotal + $tax];
}

/** Due date for an issue date (Y-m-d) and payment terms. */
function invoice_due_from_terms(string $issueDate, string $terms): string
{
    $days = INVOICE_TERMS[$terms] ?? 30;
    return date('Y-m-d', strtotime($issueDate . ' +' . $days . ' days'));
}

function valid_date(string $value): bool
{
    $d = DateTime::createFromFormat('!Y-m-d', $value);
    return $d !== false && $d->format('Y-m-d') === $value;
}

function invoice_new_token(): string
{
    return bin2hex(random_bytes(32));
}

function invoice_token_valid(string $token): bool
{
    return (bool) preg_match('/^[a-f0-9]{64}$/', $token);
}

function invoice_get(int $id): ?array
{
    return db_one('SELECT * FROM invoices WHERE id = ?', [$id]);
}

function invoice_by_token(string $token): ?array
{
    return invoice_token_valid($token) ? db_one('SELECT * FROM invoices WHERE token = ?', [$token]) : null;
}

function invoice_items(int $invoiceId): array
{
    return db_all('SELECT * FROM invoice_items WHERE invoice_id = ? ORDER BY sort, id', [$invoiceId]);
}

function invoice_payments(int $invoiceId): array
{
    return db_all('SELECT * FROM payments WHERE invoice_id = ? ORDER BY COALESCE(completed_at, created_at), id', [$invoiceId]);
}

function invoice_balance(array $invoice): int
{
    return max(0, (int) $invoice['total_cents'] - (int) $invoice['amount_paid_cents']);
}

/** Past the due date with money still owed (drafts, void and refunded invoices are never overdue). */
function invoice_is_overdue(array $invoice): bool
{
    return in_array($invoice['status'], ['sent', 'viewed', 'partially_paid', 'overdue'], true)
        && invoice_balance($invoice) > 0
        && $invoice['due_date'] < date('Y-m-d');
}

/** SQL condition matching invoice_is_overdue(). */
const INVOICE_OVERDUE_SQL = "(status IN ('sent','viewed','partially_paid','overdue') AND total_cents > amount_paid_cents AND due_date < CURDATE())";

/** Status shown to people: the stored status, or "overdue" when that applies. */
function invoice_display_status(array $invoice): string
{
    return invoice_is_overdue($invoice) ? 'overdue' : $invoice['status'];
}

function invoice_can_pay_online(array $invoice): bool
{
    return in_array($invoice['status'], INVOICE_PAYABLE, true) && invoice_balance($invoice) > 0;
}

function invoice_pay_url(array $invoice, string $suffix = ''): string
{
    return site_url('/pay/' . $invoice['token'] . $suffix);
}

function invoice_admin_link(int $id): string
{
    return site_url('/admin/?p=invoice&id=' . $id);
}

/**
 * Take the next invoice number ("ECG-1001"). Must run inside a transaction: the counter row is locked,
 * so two people saving at once never get the same number, and a number is never handed out twice.
 */
function invoice_take_number(PDO $pdo): string
{
    $prefix = setting('invoice_prefix');
    $pdo->exec("INSERT IGNORE INTO settings (k, v) VALUES ('invoice_next_number', '" . (int) SETTING_DEFAULTS['invoice_next_number'] . "')");
    $next = max(1, (int) $pdo->query("SELECT v FROM settings WHERE k = 'invoice_next_number' FOR UPDATE")->fetchColumn());
    $exists = $pdo->prepare('SELECT COUNT(*) FROM invoices WHERE number = ?');
    while (true) {
        $number = $prefix . $next;
        $exists->execute([$number]);
        if ((int) $exists->fetchColumn() === 0) {
            break;
        }
        $next++;
    }
    $pdo->prepare("UPDATE settings SET v = ? WHERE k = 'invoice_next_number'")->execute([(string) ($next + 1)]);
    return $number;
}

/**
 * Recalculate amount_paid_cents, status and paid_at from the payments table. Call inside the same transaction as the
 * payment change. Refund rows carry a negative amount, so the sum is the money actually kept.
 */
function invoice_recalc(PDO $pdo, int $invoiceId): array
{
    $stmt = $pdo->prepare('SELECT * FROM invoices WHERE id = ? FOR UPDATE');
    $stmt->execute([$invoiceId]);
    $invoice = $stmt->fetch();
    if (!$invoice) {
        throw new RuntimeException('Invoice not found.');
    }
    $stmt = $pdo->prepare("SELECT
            COALESCE(SUM(CASE WHEN status IN ('succeeded','refunded') THEN amount_cents ELSE 0 END), 0) AS paid,
            SUM(status = 'refunded') AS refunds,
            SUM(status = 'pending' AND gateway_txn IS NOT NULL) AS processing,
            MAX(CASE WHEN status = 'succeeded' THEN COALESCE(completed_at, created_at) END) AS last_paid
        FROM payments WHERE invoice_id = ?");
    $stmt->execute([$invoiceId]);
    $sum = $stmt->fetch();
    $paid = (int) $sum['paid'];
    $total = (int) $invoice['total_cents'];

    $status = match (true) {
        $invoice['status'] === 'void'           => 'void',
        $total > 0 && $paid >= $total           => 'paid',
        (int) $sum['refunds'] > 0 && $paid <= 0 => 'refunded',
        (int) $sum['processing'] > 0            => 'processing',
        $paid > 0                               => 'partially_paid',
        $invoice['viewed_at'] !== null          => 'viewed',
        $invoice['sent_at'] !== null            => 'sent',
        default                                 => 'draft',
    };
    $paidAt = $status === 'paid' ? ($invoice['paid_at'] ?: ($sum['last_paid'] ?: now())) : null;
    $pdo->prepare('UPDATE invoices SET amount_paid_cents = ?, status = ?, paid_at = ? WHERE id = ?')
        ->execute([$paid, $status, $paidAt, $invoiceId]);
    return array_merge($invoice, ['amount_paid_cents' => $paid, 'status' => $status, 'paid_at' => $paidAt]);
}

/** Run $fn inside a transaction and return its result. */
function db_transaction(callable $fn)
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $result = $fn($pdo);
        $pdo->commit();
        return $result;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

/* Gateways ------------------------------------------------------------------ */

function stripe_live(): bool
{
    return setting('stripe_mode') === 'live';
}

function stripe_secret_key(): string
{
    return secret_setting(stripe_live() ? 'stripe_secret_key_live' : 'stripe_secret_key_test');
}

function stripe_webhook_secret(): string
{
    return secret_setting(stripe_live() ? 'stripe_webhook_secret_live' : 'stripe_webhook_secret_test');
}

/** Stripe is offered only when switched on and both keys for the current mode are saved. */
function stripe_ready(): bool
{
    return setting('stripe_enabled') === '1' && stripe_secret_key() !== '' && stripe_webhook_secret() !== '';
}

/** Stripe payment methods offered to customers: subset of ['card', 'ach']. */
function stripe_methods(): array
{
    if (!stripe_ready()) {
        return [];
    }
    return array_keys(array_filter(['card' => setting('stripe_allow_card') === '1', 'ach' => setting('stripe_allow_ach') === '1']));
}

function authnet_live(): bool
{
    return setting('authnet_mode') === 'live';
}

function authnet_ready(): bool
{
    return setting('authnet_enabled') === '1' && setting('authnet_login_id') !== ''
        && secret_setting('authnet_transaction_key') !== '' && secret_setting('authnet_signature_key') !== '';
}

/** Address for payment notifications to the company. */
function invoice_notify_address(): array
{
    $address = setting('invoice_notify_email');
    return valid_email($address) ? [$address] : notify_emails();
}
