<?php
require __DIR__ . '/inc/init.php';

admin_session();
if (current_user()) {
    redirect('/admin/');
}

$error = '';
$email = '';
if (is_post()) {
    $email = post_str('email');
    $password = (string) ($_POST['password'] ?? '');
    if (!csrf_valid()) {
        $error = 'Your session expired. Please try again.';
    } elseif (strlen($password) > 1024 || !attempt_login($email, $password)) {
        $error = 'That email and password don’t match, or sign-in is paused after too many attempts. Please wait 15 minutes and try again.';
    } else {
        $next = (string) ($_SESSION['login_next'] ?? '/admin/');
        unset($_SESSION['login_next']);
        // Only same-site dashboard paths are allowed as the return address.
        redirect(preg_match('#^/admin/[^\s\\\\]*$#', $next) && !str_starts_with($next, '//') ? $next : '/admin/');
    }
}
$flash = flash_take();
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex,nofollow">
  <title>Sign in · Etowah Dashboard</title>
  <link rel="icon" type="image/png" href="/assets/images/logo/logo-icon.png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Archivo:wght@700;800&family=Inter:wght@400;500;600&display=swap">
  <link rel="stylesheet" href="<?= e(admin_asset('admin.css')) ?>">
  <script src="<?= e(admin_asset('theme.js')) ?>"></script>
</head>
<body class="login-page">
  <main class="login-card">
    <img class="login-logo" src="/assets/images/logo/logo-horizontal.png" alt="Etowah Construction Group" width="739" height="128">
    <h1>Dashboard sign in</h1>
<?php if ($flash): ?>
    <p class="notice notice--info" role="status"><?= e($flash['msg']) ?></p>
<?php endif; ?>
<?php if ($error !== ''): ?>
    <p class="notice notice--error" role="alert"><?= e($error) ?></p>
<?php endif; ?>
    <form method="post" action="/admin/login.php" class="stack">
      <?= csrf_field() ?>
      <div class="field">
        <label for="login-email">Email</label>
        <input id="login-email" name="email" type="email" autocomplete="username" required value="<?= e($email) ?>" autofocus>
      </div>
      <div class="field">
        <label for="login-password">Password</label>
        <input id="login-password" name="password" type="password" autocomplete="current-password" required>
      </div>
      <button class="btn btn--primary btn--block" type="submit">Sign in</button>
    </form>
    <p class="login-help">Forgot your password? Ask another dashboard user to set a temporary one for you on the Users page.</p>
    <p class="login-back"><a href="/">← Back to the website</a></p>
  </main>
</body>
</html>
