<?php
declare(strict_types=1);

/* Sessions, CSRF tokens, and the anti-spam fields used by the public forms. */

const SESSION_IDLE_SECONDS = 7200;

function session_start_secure(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.gc_maxlifetime', (string) SESSION_IDLE_SECONDS);
    ini_set('session.gc_probability', '1');
    ini_set('session.gc_divisor', '100');
    $dir = storage_path('sessions');
    if (is_dir($dir) || @mkdir($dir, 0700, true)) {
        session_save_path($dir);
    }
    session_name('etowah_sid');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function csrf_token(): string
{
    session_start_secure();
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function csrf_valid(): bool
{
    session_start_secure();
    $token = $_POST['csrf'] ?? '';
    return is_string($token) && !empty($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $token);
}

/** Signed timestamp, so a submit can be rejected when it comes back in under 3 seconds. */
function form_time_token(string $form): string
{
    $ts = (string) time();
    return $ts . '.' . hash_hmac('sha256', $form . '|' . $ts, (string) config('app_secret'));
}

function form_time_valid(string $form, int $minSeconds = 3, int $maxSeconds = 86400): bool
{
    $token = post_str('form_ts');
    if (!preg_match('/^(\d{10})\.([a-f0-9]{64})$/', $token, $m)) {
        return false;
    }
    $expected = hash_hmac('sha256', $form . '|' . $m[1], (string) config('app_secret'));
    $age = time() - (int) $m[1];
    return hash_equals($expected, $m[2]) && $age >= $minSeconds && $age <= $maxSeconds;
}

/** CSRF token, time token, and honeypot field for a public form. */
function form_guard_fields(string $form, string $indent = '            '): string
{
    return $indent . csrf_field() . "\n"
        . $indent . '<input type="hidden" name="form_ts" value="' . e(form_time_token($form)) . '">' . "\n"
        . $indent . '<div class="hp-field" aria-hidden="true"><label for="' . $form . '-website">Leave this field empty</label>'
        . '<input id="' . $form . '-website" name="website" type="text" tabindex="-1" autocomplete="off"></div>' . "\n";
}

/** True when the request passes every bot / forgery check for a public form. */
function form_guard_passes(string $form): bool
{
    $reason = match (true) {
        post_str('website') !== '' => 'honeypot filled',
        !csrf_valid()              => 'CSRF token missing or expired',
        !form_time_valid($form)    => 'sent too fast or page too old',
        default                    => null,
    };
    if ($reason !== null) {
        // Logged without any visitor data, so rejected submissions can be diagnosed later.
        log_error("Form '{$form}' rejected: {$reason}.");
    }
    return $reason === null;
}
