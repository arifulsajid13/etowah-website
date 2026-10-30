<?php
/*
 * Daily invoice reminders. Run once a day from hPanel → Advanced → Cron Jobs:
 *   php /home/<user>/domains/<domain>/public_html/cron/invoice-reminders.php
 * Sends one reminder per invoice for each configured day (before the due date and after it),
 * never twice, and only while "Email reminders automatically" is on in Settings → Payments & Invoices.
 * Command line only: the web server refuses this folder (.htaccess) and the script checks again.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Forbidden');
}

require dirname(__DIR__) . '/app/bootstrap.php';
require APP . '/auth.php'; // log_activity()
require APP . '/payments.php';

$logFile = storage_path('logs/invoice-reminders.log');
$log = function (string $line) use ($logFile) {
    $line = '[' . now() . '] ' . $line;
    echo $line, "\n";
    @file_put_contents($logFile, $line . "\n", FILE_APPEND);
};

if (setting('invoice_reminders_enabled') !== '1') {
    $log('Automatic reminders are off. Nothing sent.');
    exit(0);
}

$before = (int) setting('invoice_reminder_before_days');
$after = array_map('intval', array_filter(explode(',', setting('invoice_reminder_overdue_days')), 'ctype_digit'));
$today = new DateTimeImmutable('today');
$sent = 0;
$failed = 0;

$invoices = db_all("SELECT * FROM invoices WHERE status IN ('sent','viewed','partially_paid','overdue') AND total_cents > amount_paid_cents AND customer_email <> ''");
foreach ($invoices as $invoice) {
    $days = (int) $today->diff(new DateTimeImmutable($invoice['due_date']))->format('%r%a'); // > 0 = due in N days
    $kind = null;
    if ($before > 0 && $days === $before) {
        $kind = 'before-' . $before;
    } elseif ($days < 0 && in_array(-$days, $after, true)) {
        $kind = 'overdue-' . (-$days);
    }
    if ($kind === null) {
        continue;
    }
    // Claim the reminder first, so a second run on the same day cannot send it again.
    if (db_run('INSERT IGNORE INTO invoice_reminders (invoice_id, kind, sent_at) VALUES (?, ?, NOW())', [$invoice['id'], $kind])->rowCount() === 0) {
        continue;
    }
    if (invoice_mail_reminder($invoice)) {
        $sent++;
        log_activity('Automatic reminder sent (' . $kind . ')', 'invoice', (int) $invoice['id'], null);
        $log('Sent ' . $kind . ' reminder for ' . $invoice['number'] . ' to ' . $invoice['customer_email'] . '.');
    } else {
        $failed++;
        db_run('DELETE FROM invoice_reminders WHERE invoice_id = ? AND kind = ?', [$invoice['id'], $kind]); // try again next run
        $log('FAILED to send ' . $kind . ' reminder for ' . $invoice['number'] . ' (see php-error.log).');
    }
}
$log("Done: {$sent} sent, {$failed} failed, " . count($invoices) . ' unpaid invoices checked.');
