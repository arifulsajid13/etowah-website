<?php
declare(strict_types=1);

/* Dashboard sign-in, sessions, and the activity log. */

require_once APP . '/csrf.php';

const LOGIN_MAX_FAILURES = 5;      // per IP + email
const LOGIN_MAX_PER_IP = 20;       // per IP, any email
const LOGIN_WINDOW_MINUTES = 15;
const PASSWORD_MIN_LENGTH = 12;

function password_hash_secure(string $password): string
{
    if (defined('PASSWORD_ARGON2ID')) {
        $hash = password_hash($password, PASSWORD_ARGON2ID);
        if (is_string($hash)) {
            return $hash;
        }
    }
    return password_hash($password, PASSWORD_BCRYPT);
}

/** Start the admin session and enforce the 2-hour idle timeout. */
function admin_session(): void
{
    session_start_secure();
    if (isset($_SESSION['uid'])) {
        if (time() - (int) ($_SESSION['last_seen'] ?? 0) > SESSION_IDLE_SECONDS) {
            logout();
            session_start_secure();
            $_SESSION['flash'] = ['type' => 'info', 'msg' => 'You were signed out after 2 hours of inactivity.'];
            return;
        }
        $_SESSION['last_seen'] = time();
    }
}

function current_user(): ?array
{
    static $user = false;
    if ($user === false) {
        $user = null;
        if (!empty($_SESSION['uid'])) {
            $user = db_one('SELECT id, name, email, last_login_at, created_at FROM users WHERE id = ?', [(int) $_SESSION['uid']]);
            if (!$user) {
                logout();
            }
        }
    }
    return $user;
}

/** Stop unless someone is signed in. */
function require_admin(): array
{
    admin_session();
    $user = current_user();
    if (!$user) {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
            $_SESSION['login_next'] = $_SERVER['REQUEST_URI'] ?? '/admin/';
        }
        redirect('/admin/login.php');
    }
    return $user;
}

function login_locked(string $email): bool
{
    $ip = ip_hash();
    $perPair = (int) db_value(
        'SELECT COUNT(*) FROM login_attempts WHERE ip_hash = ? AND email = ? AND created_at > NOW() - INTERVAL ' . LOGIN_WINDOW_MINUTES . ' MINUTE',
        [$ip, $email]
    );
    $perIp = (int) db_value(
        'SELECT COUNT(*) FROM login_attempts WHERE ip_hash = ? AND created_at > NOW() - INTERVAL ' . LOGIN_WINDOW_MINUTES . ' MINUTE',
        [$ip]
    );
    return $perPair >= LOGIN_MAX_FAILURES || $perIp >= LOGIN_MAX_PER_IP;
}

/** Returns the user on success, null on failure or lockout (callers show one generic message). */
function attempt_login(string $email, string $password): ?array
{
    $email = strtolower(trim($email));
    if (random_int(1, 20) === 1) {
        db_run('DELETE FROM login_attempts WHERE created_at < NOW() - INTERVAL 1 DAY');
    }
    if (login_locked($email)) {
        return null;
    }
    $user = db_one('SELECT * FROM users WHERE email = ?', [$email]);
    // Verify against a dummy hash when the email is unknown so timing doesn't reveal valid addresses.
    $hash = $user['password_hash'] ?? '$2y$10$GMK4iHiSmWsBqFad3VYEFe0Mo1C4.46v0sfkUOVN.0DbO.X50ezoi';
    $ok = password_verify($password, $hash) && $user;
    if (!$ok) {
        db_run('INSERT INTO login_attempts (ip_hash, email, created_at) VALUES (?, ?, NOW())', [ip_hash(), $email]);
        return null;
    }

    db_run('DELETE FROM login_attempts WHERE ip_hash = ? AND email = ?', [ip_hash(), $email]);
    if (password_needs_rehash($user['password_hash'], defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT)) {
        db_run('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash_secure($password), $user['id']]);
    }
    db_run('UPDATE users SET last_login_at = NOW() WHERE id = ?', [$user['id']]);

    session_regenerate_id(true);
    $_SESSION['uid'] = (int) $user['id'];
    $_SESSION['last_seen'] = time();
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
    log_activity('Signed in', 'user', (int) $user['id'], (int) $user['id']);
    return $user;
}

function logout(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start_secure();
    }
    $_SESSION = [];
    $params = session_get_cookie_params();
    setcookie(session_name(), '', [
        'expires'  => time() - 42000,
        'path'     => $params['path'],
        'secure'   => $params['secure'],
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_destroy();
}

function log_activity(string $action, string $entity = '', ?int $entityId = null, ?int $userId = null): void
{
    $userId ??= isset($_SESSION['uid']) ? (int) $_SESSION['uid'] : null;
    try {
        db_run('INSERT INTO activity_log (user_id, action, entity, entity_id, created_at) VALUES (?, ?, ?, ?, NOW())', [$userId, $action, $entity, $entityId]);
    } catch (Throwable $e) {
        log_error('Activity log failed: ' . $e->getMessage());
    }
}

/** Why a new password is not acceptable, or null when it is fine. */
function password_problem(string $password): ?string
{
    if (str_length($password) < PASSWORD_MIN_LENGTH) {
        return 'Use at least ' . PASSWORD_MIN_LENGTH . ' characters.';
    }
    if (preg_match('/^(.)\1+$/', $password)) {
        return 'Please choose a less predictable password.';
    }
    return null;
}
