<?php
declare(strict_types=1);

/*
 * One-time installation wizard.
 * Refuses to run once app/config.php or app/install.lock exists.
 */

define('ROOT', dirname(__DIR__));
define('APP', ROOT . '/app');
define('INSTALL_LOCK', APP . '/install.lock');

ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);
date_default_timezone_set('America/New_York');

require APP . '/helpers.php';

$GLOBALS['ETOWAH_CONFIG'] = [];
send_security_headers(false);
header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store');

if (is_file(APP . '/config.php') || is_file(INSTALL_LOCK)) {
    http_response_code(403);
    install_page('Already installed', '<p class="notice notice--error">This website is already installed, so the installer is switched off.</p>'
        . '<p>For safety, delete the <code>install</code> folder in hPanel → File Manager.</p><p><a class="btn btn--primary" href="/admin/">Go to the dashboard</a></p>');
    exit;
}

@mkdir(ROOT . '/storage/logs', 0750, true);
ini_set('error_log', ROOT . '/storage/logs/php-error.log');

require APP . '/db.php';
require APP . '/settings.php';
require APP . '/migrate.php';
require APP . '/seed.php';
require APP . '/auth.php';
require APP . '/mail.php';

const CODE_FILE = ROOT . '/storage/install-code.txt';
const ATTEMPTS_FILE = ROOT . '/storage/install-attempts.txt';

session_start_secure();
$state = &$_SESSION['install'];
$state ??= ['step' => 0];
$error = '';
$notice = '';

// Owner check: an 8-character code that only someone with File Manager access can read.
if (!is_file(CODE_FILE)) {
    install_new_code();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valid()) {
        $error = 'Your session expired. Please try that step again.';
    } else {
        $action = post_str('action');
        try {
            switch ($action) {
                case 'code':
                    $attempts = (int) @file_get_contents(ATTEMPTS_FILE);
                    $code = strtoupper(preg_replace('/\s+/', '', post_str('code')));
                    if (hash_equals(trim((string) file_get_contents(CODE_FILE)), $code)) {
                        @unlink(ATTEMPTS_FILE);
                        session_regenerate_id(true);
                        $state['step'] = 1;
                    } else {
                        $attempts++;
                        if ($attempts >= 10) {
                            install_new_code();
                            $attempts = 0;
                            $error = 'Too many wrong codes, so a new code was created. Open storage/install-code.txt again to read it.';
                        } else {
                            $error = 'That code is not right. Open the file storage/install-code.txt in hPanel File Manager and copy the 8 characters exactly.';
                        }
                        file_put_contents(ATTEMPTS_FILE, (string) $attempts);
                    }
                    break;

                case 'requirements':
                    install_require_step(1);
                    if (install_requirements_ok()) {
                        $state['step'] = 2;
                    } else {
                        $error = 'Some requirements are still red. Fix them in hPanel, then press “Check again”.';
                    }
                    break;

                case 'database':
                    install_require_step(2);
                    $db = [
                        'host' => post_str('db_host') ?: 'localhost',
                        'port' => (int) post_str('db_port') ?: 3306,
                        'name' => post_str('db_name'),
                        'user' => post_str('db_user'),
                        'pass' => (string) ($_POST['db_pass'] ?? ''),
                    ];
                    $state['db_form'] = array_diff_key($db, ['pass' => 1]);
                    if ($db['name'] === '' || $db['user'] === '') {
                        $error = 'Enter the database name and the database user name.';
                        break;
                    }
                    try {
                        $pdo = db_connect($db);
                        $version = (string) $pdo->query('SELECT VERSION()')->fetchColumn();
                        $state['db'] = $db;
                        $state['step'] = 3;
                        $notice = 'Connected to the database (MySQL ' . $version . ').';
                    } catch (PDOException $e) {
                        $error = install_db_error($e);
                    }
                    break;

                case 'mail_test':
                case 'mail_save':
                    install_require_step(3);
                    $mail = [
                        'transport'  => 'smtp',
                        'host'       => post_str('smtp_host'),
                        'port'       => (int) post_str('smtp_port') ?: 465,
                        'encryption' => pick_value(post_str('smtp_encryption'), ['ssl', 'tls', 'none'], 'ssl'),
                        'username'   => post_str('smtp_user'),
                        'password'   => (string) ($_POST['smtp_pass'] ?? ''),
                        'from'       => post_str('smtp_from') ?: 'no-reply@etowahconstruction.com',
                        'from_name'  => 'Etowah Construction Group',
                    ];
                    $state['mail_form'] = array_diff_key($mail, ['password' => 1]) + ['test_to' => post_str('test_to')];
                    if ($mail['host'] === '' || !valid_email($mail['from'])) {
                        $error = 'Enter the SMTP server and a valid “From” address.';
                        break;
                    }
                    if ($action === 'mail_test') {
                        $to = post_str('test_to');
                        if (!valid_email($to)) {
                            $error = 'Enter the email address that should receive the test.';
                            break;
                        }
                        $ok = mail_send_with($mail, [$to], 'Test email from your Etowah website', "This is a test email from the Etowah website installer.\n\nIf you received it, email notifications are working.");
                        if ($ok) {
                            $notice = 'Test email sent to ' . $to . '. Check that inbox (and the spam folder).';
                        } else {
                            $error = 'The test email could not be sent: ' . ($GLOBALS['MAIL_LAST_ERROR'] ?? 'unknown error') . '. Check the server, port, encryption, user name, and password shown in hPanel → Emails.';
                        }
                        $state['mail'] = $mail;
                        break;
                    }
                    $state['mail'] = $mail;
                    $state['step'] = 4;
                    break;

                case 'mail_skip':
                    install_require_step(3);
                    $state['mail'] = ['transport' => 'mail', 'from' => 'no-reply@etowahconstruction.com', 'from_name' => 'Etowah Construction Group'];
                    $state['step'] = 4;
                    break;

                case 'finish':
                    install_require_step(4);
                    $site = rtrim(post_str('site_url'), '/');
                    $name = post_str('admin_name');
                    $email = strtolower(post_str('admin_email'));
                    $password = (string) ($_POST['admin_password'] ?? '');
                    $state['site_form'] = ['site_url' => $site, 'admin_name' => $name, 'admin_email' => $email];
                    if (!preg_match('#^https?://[a-z0-9.-]+(:\d+)?$#i', $site)) {
                        $error = 'Enter the website address like https://etowahconstruction.com (no folder or trailing slash).';
                    } elseif ($name === '' || str_length($name) > 120) {
                        $error = 'Enter your name.';
                    } elseif (!valid_email($email)) {
                        $error = 'Enter a valid email address for your dashboard login.';
                    } elseif ($problem = password_problem($password)) {
                        $error = 'Password: ' . $problem;
                    } elseif ($password !== (string) ($_POST['admin_password_confirm'] ?? '')) {
                        $error = 'The two passwords don’t match.';
                    }
                    if ($error === '') {
                        install_finish($state, $site, $name, $email, $password);
                        $_SESSION = [];
                        session_destroy();
                        install_page('Installation complete', install_done_html($site));
                        exit;
                    }
                    break;
            }
        } catch (Throwable $e) {
            log_error('Installer: ' . $e->getMessage());
            $error = 'Something went wrong: ' . $e->getMessage();
        }
    }
}

install_page('Install the Etowah website', install_step_html($state, $error, $notice));

/* ---------------------------------------------------------------------------------------------- */

function pick_value(string $value, array $allowed, string $default): string
{
    return in_array($value, $allowed, true) ? $value : $default;
}

function install_new_code(): void
{
    @mkdir(dirname(CODE_FILE), 0750, true);
    $alphabet = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
    $code = '';
    for ($i = 0; $i < 8; $i++) {
        $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
    }
    file_put_contents(CODE_FILE, $code . "\n");
    @chmod(CODE_FILE, 0600);
}

function install_require_step(int $step): void
{
    if (($_SESSION['install']['step'] ?? 0) < $step) {
        throw new RuntimeException('Please complete the earlier steps first.');
    }
}

/** Folder for private files: above the web root when possible, otherwise /storage (deny-all). */
function install_storage_path(): string
{
    $outside = rtrim(str_replace('\\', '/', dirname(ROOT)), '/') . '/etowah-storage';
    if (is_dir($outside) ? is_writable($outside) : is_writable(dirname(ROOT))) {
        return $outside;
    }
    return ROOT . '/storage';
}

function install_requirements(): array
{
    $mb = fn(string $v) => (int) preg_replace('/\D/', '', $v) * (stripos($v, 'G') !== false ? 1024 : (stripos($v, 'K') !== false ? 1 / 1024 : 1));
    $storage = install_storage_path();
    return [
        ['PHP 8.1 or newer', version_compare(PHP_VERSION, '8.1.0', '>='), 'You have PHP ' . PHP_VERSION . '. In hPanel → Advanced → PHP Configuration, choose PHP 8.2 or newer.', true],
        ['MySQL driver (pdo_mysql)', extension_loaded('pdo_mysql'), 'In hPanel → PHP Configuration → PHP Extensions, tick “pdo_mysql”.', true],
        ['Image processing (GD with WebP)', function_exists('imagewebp') && function_exists('imagecreatefromjpeg'), 'In hPanel → PHP Extensions, tick “gd”.', true],
        ['File type detection (fileinfo)', extension_loaded('fileinfo'), 'In hPanel → PHP Extensions, tick “fileinfo”.', true],
        ['Text handling (mbstring)', extension_loaded('mbstring'), 'In hPanel → PHP Extensions, tick “mbstring”.', true],
        ['Encryption (openssl)', extension_loaded('openssl'), 'In hPanel → PHP Extensions, tick “openssl”.', true],
        ['The app folder can be written to', is_writable(APP), 'In File Manager, right-click the “app” folder → Permissions → set 755.', true],
        ['The uploads folder can be written to', is_writable(ROOT . '/uploads'), 'In File Manager, right-click the “uploads” folder → Permissions → set 755.', true],
        ['Private storage can be written to', is_dir($storage) ? is_writable($storage) : is_writable(dirname($storage)), 'In File Manager, set the “storage” folder permissions to 755.', true],
        ['Upload limit at least 12 MB', $mb((string) ini_get('upload_max_filesize')) >= 12 && $mb((string) ini_get('post_max_size')) >= 14,
            'Recommended: in hPanel → PHP Configuration → PHP Options, set upload_max_filesize to 12M and post_max_size to 14M. (You can continue without this, but large photos may fail.)', false],
    ];
}

function install_requirements_ok(): bool
{
    foreach (install_requirements() as [, $ok, , $required]) {
        if ($required && !$ok) {
            return false;
        }
    }
    return true;
}

function install_db_error(PDOException $e): string
{
    $msg = $e->getMessage();
    if (str_contains($msg, '1045')) {
        return 'The database user name or password is wrong. Copy them again from hPanel → Databases → MySQL Databases.';
    }
    if (str_contains($msg, '1049')) {
        return 'That database name does not exist. In hPanel the full name usually starts with your account prefix, like u123456789_etowah.';
    }
    if (str_contains($msg, '1044')) {
        return 'This user is not allowed to use that database. In hPanel, make sure the user was created for this database.';
    }
    if (str_contains($msg, '2002') || str_contains($msg, '2005')) {
        return 'Could not reach the database server. On Hostinger the host is usually “localhost”.';
    }
    return 'Could not connect to the database: ' . $msg;
}

function install_finish(array $state, string $site, string $name, string $email, string $password): void
{
    $pdo = db_connect($state['db']);
    run_schema($pdo);
    seed_database($pdo);

    $existing = $pdo->prepare('SELECT id FROM users WHERE email = ?');
    $existing->execute([$email]);
    if ($id = $existing->fetchColumn()) {
        $pdo->prepare('UPDATE users SET name = ?, password_hash = ? WHERE id = ?')->execute([$name, password_hash_secure($password), $id]);
    } else {
        $pdo->prepare('INSERT INTO users (name, email, password_hash, created_at) VALUES (?, ?, ?, NOW())')->execute([$name, $email, password_hash_secure($password)]);
    }

    $storage = install_storage_path();
    foreach (['', '/logs', '/resumes', '/sessions'] as $sub) {
        if (!is_dir($storage . $sub) && !@mkdir($storage . $sub, 0750, true)) {
            throw new RuntimeException('Could not create the private folder ' . $storage . $sub . '. Check folder permissions.');
        }
    }
    if (!is_file($storage . '/.htaccess')) {
        @copy(ROOT . '/storage/.htaccess', $storage . '/.htaccess');
    }

    $config = [
        'db'           => $state['db'],
        'mail'         => $state['mail'],
        'site_url'     => $site,
        'app_secret'   => bin2hex(random_bytes(32)),
        'storage_path' => $storage,
        'installed_at' => date('c'),
    ];
    $php = "<?php\n// Written by the installer on " . date('Y-m-d H:i') . ". Keep this file private and never upload over it.\nreturn "
        . var_export($config, true) . ";\n";
    if (file_put_contents(APP . '/config.php', $php, LOCK_EX) === false) {
        throw new RuntimeException('Could not write app/config.php. Set the “app” folder permissions to 755 and try again.');
    }
    @chmod(APP . '/config.php', 0600);
    file_put_contents(INSTALL_LOCK, 'Installed ' . date('c') . "\n");
    @unlink(CODE_FILE);
    @unlink(ATTEMPTS_FILE);
}

function install_done_html(string $site): string
{
    return '<p class="notice">The website is installed and your dashboard account is ready.</p>'
        . '<p class="notice">The site is <strong>hidden from search engines</strong> for now (good for a demo). When it goes live on the real domain, turn this off in the dashboard under <strong>Site Settings</strong>.</p>'
        . '<p class="notice notice--error"><strong>Delete the /install folder now (hPanel → File Manager).</strong> The installer is already switched off, but the folder should not stay on the server.</p>'
        . '<div class="button-row"><a class="btn btn--primary" href="/admin/">Open the dashboard</a><a class="btn btn--ghost" href="' . e($site) . '/">View the website</a></div>';
}

function install_step_html(array $state, string $error, string $notice): string
{
    $step = (int) $state['step'];
    $titles = ['Owner check', 'Requirements', 'Database', 'Email', 'Website and admin'];
    $html = '<ol class="install-steps">';
    foreach ($titles as $i => $t) {
        $html .= '<li class="' . ($i === $step ? 'is-current' : ($i < $step ? 'is-done' : '')) . '">' . e($t) . '</li>';
    }
    $html .= '</ol>';
    if ($error !== '') {
        $html .= '<p class="notice notice--error" role="alert">' . e($error) . '</p>';
    }
    if ($notice !== '') {
        $html .= '<p class="notice" role="status">' . e($notice) . '</p>';
    }
    $csrf = csrf_field();
    $v = fn(string $group, string $key, string $default = '') => e((string) ($state[$group][$key] ?? $default));

    if ($step === 0) {
        return $html . '<h2>Step 1 · Prove you own this website</h2>'
            . '<p>To stop strangers from installing first, this installer created a secret code.</p>'
            . '<ol class="howto"><li>Open <strong>hPanel → Files → File Manager</strong>.</li><li>Go into <strong>public_html → storage</strong>.</li><li>Open the file <strong>install-code.txt</strong> and copy the 8 characters.</li></ol>'
            . '<form method="post" class="stack">' . $csrf . '<input type="hidden" name="action" value="code">'
            . '<div class="field"><label for="code">Install code</label><input id="code" name="code" type="text" maxlength="12" autocomplete="off" required autofocus></div>'
            . '<button class="btn btn--primary" type="submit">Continue</button></form>';
    }

    if ($step === 1) {
        $html .= '<h2>Step 2 · Check the server</h2><ul class="checks">';
        foreach (install_requirements() as [$name, $ok, $fix, $required]) {
            $html .= '<li class="' . ($ok ? 'is-ok' : 'is-bad') . '"><span class="check-dot" aria-hidden="true"></span><span class="check-name">' . e($name)
                . (!$required ? ' <span class="optional">(recommended)</span>' : '') . '</span><span class="check-value">' . ($ok ? 'OK' : 'Needs fixing') . '</span></li>';
            if (!$ok) {
                $html .= '<li class="check-fix">' . e($fix) . '</li>';
            }
        }
        $html .= '</ul><p class="hint">Private files (resumes, logs) will be stored in: <code>' . e(install_storage_path()) . '</code></p>';
        return $html . '<form method="post" class="button-row">' . $csrf . '<input type="hidden" name="action" value="requirements">'
            . '<button class="btn btn--primary" type="submit">' . (install_requirements_ok() ? 'Continue' : 'Check again') . '</button></form>';
    }

    if ($step === 2) {
        return $html . '<h2>Step 3 · Connect the database</h2>'
            . '<p>Use the 4 values you wrote down when you created the database in <strong>hPanel → Databases → MySQL Databases</strong>.</p>'
            . '<form method="post" class="stack" autocomplete="off">' . $csrf . '<input type="hidden" name="action" value="database">'
            . '<div class="field"><label for="db_host">Database host</label><input id="db_host" name="db_host" type="text" value="' . $v('db_form', 'host', 'localhost') . '" required><p class="hint">On Hostinger this is almost always “localhost”.</p></div>'
            . '<div class="field"><label for="db_name">Database name</label><input id="db_name" name="db_name" type="text" value="' . $v('db_form', 'name') . '" required placeholder="u123456789_etowah"></div>'
            . '<div class="field"><label for="db_user">Database user</label><input id="db_user" name="db_user" type="text" value="' . $v('db_form', 'user') . '" required placeholder="u123456789_admin"></div>'
            . '<div class="field"><label for="db_pass">Database password</label><input id="db_pass" name="db_pass" type="password" autocomplete="new-password"></div>'
            . '<input type="hidden" name="db_port" value="' . $v('db_form', 'port', '3306') . '">'
            . '<button class="btn btn--primary" type="submit">Test connection and continue</button></form>';
    }

    if ($step === 3) {
        $enc = (string) ($state['mail_form']['encryption'] ?? 'ssl');
        $opt = fn(string $val, string $label) => '<option value="' . $val . '"' . ($enc === $val ? ' selected' : '') . '>' . $label . '</option>';
        return $html . '<h2>Step 4 · Email notifications</h2>'
            . '<p>The website emails you when someone sends the contact form or applies for a job. Use the <strong>no-reply@</strong> mailbox from <strong>hPanel → Emails</strong>; the SMTP details are under “Connect apps &amp; devices”.</p>'
            . '<form method="post" class="stack" autocomplete="off">' . $csrf
            . '<div class="field"><label for="smtp_host">SMTP server</label><input id="smtp_host" name="smtp_host" type="text" value="' . $v('mail_form', 'host', 'smtp.hostinger.com') . '"></div>'
            . '<div class="form-grid"><div class="field"><label for="smtp_port">Port</label><input id="smtp_port" name="smtp_port" type="number" value="' . $v('mail_form', 'port', '465') . '"></div>'
            . '<div class="field"><label for="smtp_encryption">Encryption</label><select id="smtp_encryption" name="smtp_encryption">' . $opt('ssl', 'SSL (port 465)') . $opt('tls', 'TLS / STARTTLS (port 587)') . $opt('none', 'None') . '</select></div></div>'
            . '<div class="field"><label for="smtp_user">SMTP user name</label><input id="smtp_user" name="smtp_user" type="text" value="' . $v('mail_form', 'username', 'no-reply@etowahconstruction.com') . '"><p class="hint">Usually the full mailbox address.</p></div>'
            . '<div class="field"><label for="smtp_pass">SMTP password</label><input id="smtp_pass" name="smtp_pass" type="password" autocomplete="new-password"><p class="hint">The mailbox password. Re-enter it if you send a test first.</p></div>'
            . '<div class="field"><label for="smtp_from">“From” address</label><input id="smtp_from" name="smtp_from" type="email" value="' . $v('mail_form', 'from', 'no-reply@etowahconstruction.com') . '"></div>'
            . '<div class="field"><label for="test_to">Send a test to</label><input id="test_to" name="test_to" type="email" value="' . $v('mail_form', 'test_to') . '" placeholder="you@example.com"></div>'
            . '<div class="button-row"><button class="btn btn--ghost" type="submit" name="action" value="mail_test">Send test email</button>'
            . '<button class="btn btn--primary" type="submit" name="action" value="mail_save">Save and continue</button></div></form>'
            . '<form method="post" class="skip-form">' . $csrf . '<input type="hidden" name="action" value="mail_skip">'
            . '<button class="btn btn--ghost btn--sm" type="submit">Skip for now (use PHP mail)</button>'
            . '<p class="hint">PHP mail works on Hostinger but is more likely to land in spam.</p></form>';
    }

    $detected = (is_https() ? 'https' : 'http') . '://' . preg_replace('/[^a-z0-9.:-]/i', '', (string) ($_SERVER['HTTP_HOST'] ?? 'localhost'));
    return $html . '<h2>Step 5 · Website and your login</h2>'
        . '<form method="post" class="stack">' . $csrf . '<input type="hidden" name="action" value="finish">'
        . '<div class="field"><label for="site_url">Website address</label><input id="site_url" name="site_url" type="url" value="' . $v('site_form', 'site_url', $detected) . '" required><p class="hint">Detected automatically. Use the address people will type, starting with https://. If you are on a temporary Hostinger domain now, change this later in app/config.php or ask your developer.</p></div>'
        . '<div class="field"><label for="admin_name">Your name</label><input id="admin_name" name="admin_name" type="text" value="' . $v('site_form', 'admin_name') . '" required autocomplete="name"></div>'
        . '<div class="field"><label for="admin_email">Your email (for signing in)</label><input id="admin_email" name="admin_email" type="email" value="' . $v('site_form', 'admin_email') . '" required autocomplete="username"></div>'
        . '<div class="field"><label for="admin_password">Password</label><input id="admin_password" name="admin_password" type="password" minlength="12" required autocomplete="new-password" data-strength><p class="hint">At least 12 characters. A short sentence works well. <span class="strength" data-strength-for="admin_password"></span></p></div>'
        . '<div class="field"><label for="admin_password_confirm">Repeat password</label><input id="admin_password_confirm" name="admin_password_confirm" type="password" minlength="12" required autocomplete="new-password"></div>'
        . '<button class="btn btn--primary" type="submit">Install the website</button></form>';
}

function install_page(string $title, string $body): void
{
    ?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex,nofollow">
  <title><?= e($title) ?> · Etowah</title>
  <link rel="icon" type="image/png" href="/assets/images/logo/logo-icon.png">
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Archivo:wght@700;800&family=Inter:wght@400;500;600&display=swap">
  <link rel="stylesheet" href="/admin/assets/admin.css">
  <link rel="stylesheet" href="/install/install.css">
  <script src="/admin/assets/admin.js" defer></script>
</head>
<body class="login-page">
  <main class="login-card install-card">
    <img class="login-logo" src="/assets/images/logo/logo-horizontal.png" alt="Etowah Construction Group" width="739" height="128">
    <h1><?= e($title) ?></h1>
    <?= $body ?>
  </main>
</body>
</html>
<?php
}
