<?php
declare(strict_types=1);

/*
 * Encryption at rest for payment gateway secrets (libsodium secretbox).
 * The key is derived from app_secret in app/config.php, so a database copy alone cannot reveal the keys.
 * Changing app_secret makes saved secrets unreadable: they must then be entered again in the dashboard.
 */

const SECRET_PREFIX = 'enc1:';

function secret_key(): string
{
    $appSecret = (string) config('app_secret', '');
    if ($appSecret === '') {
        throw new RuntimeException('app_secret is missing from app/config.php.');
    }
    return hash_hkdf('sha256', $appSecret, SODIUM_CRYPTO_SECRETBOX_KEYBYTES, 'etowah-settings-secrets-v1');
}

function crypto_available(): bool
{
    return function_exists('sodium_crypto_secretbox');
}

function secret_encrypt(string $plain): string
{
    if ($plain === '') {
        return '';
    }
    if (!crypto_available()) {
        throw new RuntimeException('The PHP sodium extension is not available.');
    }
    $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
    $key = secret_key();
    $box = sodium_crypto_secretbox($plain, $nonce, $key);
    sodium_memzero($key);
    return SECRET_PREFIX . base64_encode($nonce . $box);
}

/** The plain value, or '' when empty or unreadable (unreadable values are logged without their content). */
function secret_decrypt(string $stored): string
{
    if ($stored === '' || !str_starts_with($stored, SECRET_PREFIX) || !crypto_available()) {
        return '';
    }
    $raw = base64_decode(substr($stored, strlen(SECRET_PREFIX)), true);
    if ($raw === false || strlen($raw) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
        return '';
    }
    $key = secret_key();
    $plain = sodium_crypto_secretbox_open(substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), $key);
    sodium_memzero($key);
    if ($plain === false) {
        log_error('A saved payment secret could not be decrypted (was app_secret changed?). Enter it again in Settings → Payments & Invoices.');
        return '';
    }
    return $plain;
}

/** Decrypted value of a SECRET_SETTINGS key. */
function secret_setting(string $key): string
{
    return secret_decrypt(setting($key));
}

/** "••••••••abcd" for display; never the full value. */
function secret_mask(string $plain): string
{
    if ($plain === '') {
        return '';
    }
    return str_repeat('•', 8) . (strlen($plain) > 8 ? substr($plain, -4) : '');
}
