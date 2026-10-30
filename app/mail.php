<?php
declare(strict_types=1);

/* Outgoing email through PHPMailer (SMTP, or PHP mail() when SMTP was skipped). */

require_once APP . '/vendor/PHPMailer/src/Exception.php';
require_once APP . '/vendor/PHPMailer/src/PHPMailer.php';
require_once APP . '/vendor/PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;

/**
 * Send a plain-text email, with an optional HTML version. Returns true on success; failures are logged, never shown.
 * $mail: transport (smtp|mail|log), host, port, encryption (tls|ssl|none), username, password, from, from_name.
 */
function mail_send_with(array $mail, array $to, string $subject, string $body, ?string $replyTo = null, string $replyName = '', string $html = ''): bool
{
    $to = array_values(array_filter($to, 'valid_email'));
    $from = (string) ($mail['from'] ?? '');
    $fromName = (string) ($mail['from_name'] ?? 'Etowah Construction Group');
    foreach ([$subject, $from, $fromName, $replyName, (string) $replyTo] as $headerValue) {
        if (has_line_break($headerValue)) {
            log_error('Mail refused: line break in a header value.');
            return false;
        }
    }
    if (!$to || !valid_email($from) || ($replyTo !== null && !valid_email($replyTo))) {
        log_error('Mail refused: missing or invalid address.');
        return false;
    }

    $transport = $mail['transport'] ?? 'mail';
    if ($transport === 'log') {
        // Local testing only: write the message to the private log instead of sending it.
        $line = sprintf("[%s] To: %s | Reply-To: %s | Subject: %s\n%s\n\n", now(), implode(', ', $to), (string) $replyTo, $subject, $body);
        return file_put_contents(storage_path('logs/mail.log'), $line, FILE_APPEND) !== false;
    }

    $mailer = new PHPMailer(true);
    try {
        $mailer->CharSet = PHPMailer::CHARSET_UTF8;
        if ($transport === 'smtp') {
            $mailer->isSMTP();
            $mailer->Host = (string) $mail['host'];
            $mailer->Port = (int) $mail['port'];
            $mailer->SMTPAuth = ($mail['username'] ?? '') !== '';
            $mailer->Username = (string) ($mail['username'] ?? '');
            $mailer->Password = (string) ($mail['password'] ?? '');
            $enc = $mail['encryption'] ?? 'tls';
            $mailer->SMTPSecure = $enc === 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : ($enc === 'tls' ? PHPMailer::ENCRYPTION_STARTTLS : '');
            $mailer->SMTPAutoTLS = $enc !== 'none';
            $mailer->Timeout = 15;
        } else {
            $mailer->isMail();
        }
        $mailer->setFrom($from, $fromName);
        foreach ($to as $address) {
            $mailer->addAddress($address);
        }
        if ($replyTo !== null) {
            $mailer->addReplyTo($replyTo, $replyName);
        }
        $mailer->Subject = $subject;
        if ($html !== '') {
            $mailer->isHTML(true);
            $mailer->Body = $html;
            $mailer->AltBody = $body;
        } else {
            $mailer->isHTML(false);
            $mailer->Body = $body;
        }
        $mailer->send();
        return true;
    } catch (Throwable $e) {
        $GLOBALS['MAIL_LAST_ERROR'] = $mailer->ErrorInfo ?: $e->getMessage();
        log_error('Mail failed: ' . $GLOBALS['MAIL_LAST_ERROR']);
        return false;
    }
}

/** Send using the configured mail settings. */
function mail_send(array $to, string $subject, string $body, ?string $replyTo = null, string $replyName = '', string $html = ''): bool
{
    return mail_send_with((array) config('mail', []), $to, $subject, $body, $replyTo, $replyName, $html);
}

/** Keep visitor-supplied text out of email headers. */
function mail_header_safe(string $value): string
{
    return trim(preg_replace('/[\r\n\t]+/', ' ', $value));
}
