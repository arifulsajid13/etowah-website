<?php
declare(strict_types=1);

/* Invoice and payment emails: plain readable HTML with a text version. */

require_once APP . '/mail.php';
require_once APP . '/invoices.php';

/**
 * Build [text, html] for a simple message.
 * $rows: label => value. $button: [label, url] or null.
 */
function invoice_email_body(string $heading, array $paragraphs, array $rows = [], ?array $button = null, array $after = []): array
{
    $company = setting('company_name');
    $text = $heading . "\n\n";
    foreach ($paragraphs as $p) {
        $text .= $p . "\n\n";
    }
    foreach ($rows as $label => $value) {
        $text .= $label . ': ' . $value . "\n";
    }
    if ($rows) {
        $text .= "\n";
    }
    if ($button) {
        $text .= $button[0] . ":\n" . $button[1] . "\n\n";
    }
    foreach ($after as $p) {
        $text .= $p . "\n\n";
    }
    $text .= '— ' . $company . "\n" . setting('phone') . ' · ' . setting('email') . "\n";

    $font = "font-family:Arial,Helvetica,sans-serif;";
    $html = '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width"><title>' . e($heading) . '</title></head>'
        . '<body style="margin:0;padding:0;background:#f3f4f6;">'
        . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f3f4f6;"><tr><td align="center" style="padding:24px 12px;">'
        . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:560px;background:#ffffff;border-top:4px solid #262F36;">'
        . '<tr><td style="padding:28px 28px 8px;' . $font . 'color:#262F36;font-size:13px;font-weight:bold;letter-spacing:.06em;text-transform:uppercase;">' . e($company) . '</td></tr>'
        . '<tr><td style="padding:0 28px;' . $font . 'color:#111827;font-size:22px;font-weight:bold;line-height:1.3;">' . e($heading) . '</td></tr>';
    foreach ($paragraphs as $p) {
        $html .= '<tr><td style="padding:14px 28px 0;' . $font . 'color:#374151;font-size:15px;line-height:1.6;">' . e($p) . '</td></tr>';
    }
    if ($rows) {
        $html .= '<tr><td style="padding:18px 28px 0;"><table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-top:1px solid #e5e7eb;">';
        foreach ($rows as $label => $value) {
            $html .= '<tr><td style="padding:9px 0;border-bottom:1px solid #e5e7eb;' . $font . 'color:#6b7280;font-size:14px;">' . e($label) . '</td>'
                . '<td align="right" style="padding:9px 0;border-bottom:1px solid #e5e7eb;' . $font . 'color:#111827;font-size:14px;font-weight:bold;">' . e($value) . '</td></tr>';
        }
        $html .= '</table></td></tr>';
    }
    if ($button) {
        $html .= '<tr><td style="padding:24px 28px 0;"><a href="' . e($button[1]) . '" style="display:inline-block;padding:13px 22px;background:#262F36;color:#ffffff;' . $font . 'font-size:15px;font-weight:bold;text-decoration:none;">' . e($button[0]) . '</a>'
            . '<p style="margin:12px 0 0;' . $font . 'color:#6b7280;font-size:12px;line-height:1.5;word-break:break-all;">Or copy this link: ' . e($button[1]) . '</p></td></tr>';
    }
    foreach ($after as $p) {
        $html .= '<tr><td style="padding:14px 28px 0;' . $font . 'color:#374151;font-size:14px;line-height:1.6;">' . e($p) . '</td></tr>';
    }
    $html .= '<tr><td style="padding:28px;' . $font . 'color:#6b7280;font-size:12px;line-height:1.6;">' . e($company) . '<br>' . e(setting('phone')) . ' · ' . e(setting('email')) . '</td></tr>'
        . '</table></td></tr></table></body></html>';
    return [$text, $html];
}

function invoice_customer_greeting(array $invoice): string
{
    $name = trim($invoice['customer_name']);
    return $name !== '' ? 'Hello ' . $name . ',' : 'Hello,';
}

function invoice_due_line(array $invoice): string
{
    $due = date('F j, Y', strtotime($invoice['due_date']));
    return $invoice['due_date'] < date('Y-m-d') ? 'This invoice was due on ' . $due . '.' : 'This invoice is due on ' . $due . '.';
}

/** Invoice email to the customer (first send and resend). */
function invoice_mail_sent(array $invoice): bool
{
    $paragraphs = [
        invoice_customer_greeting($invoice),
        'Here is invoice ' . $invoice['number'] . ' from ' . setting('company_name') . '. You can view it, save a PDF copy and pay online using the secure link below.',
    ];
    [$text, $html] = invoice_email_body('Invoice ' . $invoice['number'], $paragraphs, [
        'Invoice'    => $invoice['number'],
        'Amount due' => money(invoice_balance($invoice)),
        'Due date'   => date('F j, Y', strtotime($invoice['due_date'])),
    ], ['View and pay invoice', invoice_pay_url($invoice)], ['Questions about this invoice? Reply to this email or call ' . setting('phone') . '.']);
    return mail_send([$invoice['customer_email']], mail_header_safe('Invoice ' . $invoice['number'] . ' from ' . setting('company_name')), $text, setting('email'), setting('company_name'), $html);
}

function invoice_mail_reminder(array $invoice): bool
{
    $paragraphs = [
        invoice_customer_greeting($invoice),
        'This is a friendly reminder about invoice ' . $invoice['number'] . '. ' . invoice_due_line($invoice),
    ];
    [$text, $html] = invoice_email_body('Payment reminder: invoice ' . $invoice['number'], $paragraphs, [
        'Invoice'     => $invoice['number'],
        'Balance due' => money(invoice_balance($invoice)),
        'Due date'    => date('F j, Y', strtotime($invoice['due_date'])),
    ], ['View and pay invoice', invoice_pay_url($invoice)], ['If you have already paid, thank you, and please ignore this reminder.']);
    return mail_send([$invoice['customer_email']], mail_header_safe('Reminder: invoice ' . $invoice['number'] . ' from ' . setting('company_name')), $text, setting('email'), setting('company_name'), $html);
}

function payment_method_label(array $payment): string
{
    return PAYMENT_METHODS[$payment['method']] ?? 'Other';
}

/** Receipt to the customer after a payment succeeded. */
function invoice_mail_receipt(array $invoice, array $payment): bool
{
    if (!valid_email($invoice['customer_email'])) {
        return false;
    }
    $balance = invoice_balance($invoice);
    [$text, $html] = invoice_email_body('Payment received — thank you', [
        invoice_customer_greeting($invoice),
        'We received your payment for invoice ' . $invoice['number'] . '.',
    ], [
        'Invoice'           => $invoice['number'],
        'Amount paid'       => money((int) $payment['amount_cents']),
        'Method'            => payment_method_label($payment),
        'Date'              => date('F j, Y', strtotime($payment['completed_at'] ?: now())),
        'Remaining balance' => money($balance),
    ], ['View invoice', invoice_pay_url($invoice)], [$balance > 0 ? 'The remaining balance can be paid using the same link.' : 'This invoice is now paid in full.']);
    return mail_send([$invoice['customer_email']], mail_header_safe('Receipt for invoice ' . $invoice['number']), $text, setting('email'), setting('company_name'), $html);
}

/** Payment notification to the company. */
function invoice_mail_admin_payment(array $invoice, array $payment): bool
{
    $who = trim($invoice['customer_name'] . ($invoice['customer_company'] !== '' ? ' (' . $invoice['customer_company'] . ')' : ''));
    [$text, $html] = invoice_email_body('Payment received: ' . money((int) $payment['amount_cents']), [
        ($who !== '' ? $who : $invoice['customer_email']) . ' paid invoice ' . $invoice['number'] . '.',
    ], [
        'Invoice'     => $invoice['number'],
        'Amount'      => money((int) $payment['amount_cents']),
        'Method'      => payment_method_label($payment) . ' · ' . (PAYMENT_GATEWAYS[$payment['gateway']] ?? $payment['gateway']),
        'Balance now' => money(invoice_balance($invoice)),
        'Status'      => INVOICE_STATUSES[$invoice['status']] ?? $invoice['status'],
    ], ['Open in dashboard', invoice_admin_link((int) $invoice['id'])]);
    return mail_send(invoice_notify_address(), mail_header_safe('Payment received — invoice ' . $invoice['number']), $text, null, '', $html);
}

/** General alert to the company (ACH failure, refunds, payment for a void invoice, amount mismatch). */
function invoice_mail_admin_alert(array $invoice, string $subject, string $message): bool
{
    [$text, $html] = invoice_email_body($subject, [$message], [
        'Invoice'  => $invoice['number'],
        'Customer' => trim($invoice['customer_name'] . ' ' . $invoice['customer_email']),
        'Balance'  => money(invoice_balance($invoice)),
    ], ['Open in dashboard', invoice_admin_link((int) $invoice['id'])]);
    return mail_send(invoice_notify_address(), mail_header_safe($subject . ' — invoice ' . $invoice['number']), $text, null, '', $html);
}
