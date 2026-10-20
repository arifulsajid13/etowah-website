<?php
defined('ETOWAH_ADMIN') || exit;
$errors = [];
$form = ['name' => '', 'email' => ''];

if (is_post()) {
    require_post();
    $action = post_str('action');

    if ($action === 'add') {
        $form = ['name' => post_str('name'), 'email' => strtolower(post_str('email'))];
        $password = (string) ($_POST['password'] ?? '');
        if ($form['name'] === '' || str_length($form['name']) > 120) {
            $errors['name'] = 'Enter a name.';
        }
        if (!valid_email($form['email'])) {
            $errors['email'] = 'Enter a valid email address.';
        } elseif (db_value('SELECT id FROM users WHERE email = ?', [$form['email']])) {
            $errors['email'] = 'Someone already uses this email address.';
        }
        if ($problem = password_problem($password)) {
            $errors['password'] = $problem;
        }
        if (!$errors) {
            db_run('INSERT INTO users (name, email, password_hash, created_at) VALUES (?, ?, ?, NOW())', [$form['name'], $form['email'], password_hash_secure($password)]);
            log_activity('Added user ' . $form['email'], 'user', (int) db()->lastInsertId());
            flash('success', 'User added. Share the temporary password with them privately and ask them to change it after signing in.');
            redirect(admin_url('users'));
        }
    }

    if ($action === 'remove') {
        $target = db_one('SELECT id, email FROM users WHERE id = ?', [post_int('id')]);
        $total = (int) db_value('SELECT COUNT(*) FROM users');
        if (!$target) {
            flash('error', 'That user no longer exists.');
        } elseif ((int) $target['id'] === (int) $user['id']) {
            flash('error', 'You can’t remove yourself. Ask another user to do it.');
        } elseif ($total <= 1) {
            flash('error', 'You can’t remove the last user.');
        } else {
            db_run('DELETE FROM users WHERE id = ?', [$target['id']]);
            log_activity('Removed user ' . $target['email'], 'user', (int) $target['id']);
            flash('success', 'User removed.');
        }
        redirect(admin_url('users'));
    }

    if ($action === 'password') {
        $current = (string) ($_POST['current_password'] ?? '');
        $next = (string) ($_POST['new_password'] ?? '');
        $hash = (string) db_value('SELECT password_hash FROM users WHERE id = ?', [$user['id']]);
        if (!password_verify($current, $hash)) {
            $errors['current_password'] = 'Your current password is not correct.';
        } elseif ($problem = password_problem($next)) {
            $errors['new_password'] = $problem;
        } elseif ($next !== (string) ($_POST['confirm_password'] ?? '')) {
            $errors['confirm_password'] = 'The two new passwords don’t match.';
        }
        if (!$errors) {
            db_run('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash_secure($next), $user['id']]);
            session_regenerate_id(true);
            log_activity('Changed own password', 'user', (int) $user['id']);
            flash('success', 'Your password has been changed.');
            redirect(admin_url('users'));
        }
    }
}

$users = db_all('SELECT id, name, email, last_login_at, created_at FROM users ORDER BY name');
$activity = db_all('SELECT l.action, l.created_at, u.name, u.email FROM activity_log l LEFT JOIN users u ON u.id = l.user_id ORDER BY l.created_at DESC, l.id DESC LIMIT 50');
$err = fn(string $k) => isset($errors[$k]) ? '<p class="field-error">' . e($errors[$k]) . '</p>' : '';
$cls = fn(string $k) => isset($errors[$k]) ? ' has-error' : '';

admin_start('Users', 'users');
?>
        <section class="panel">
          <div class="panel-head"><h2>People who can sign in</h2></div>
          <table class="table">
            <thead><tr><th scope="col">Name</th><th scope="col">Email</th><th scope="col">Last sign-in</th><th scope="col"><span class="visually-hidden">Actions</span></th></tr></thead>
            <tbody>
<?php foreach ($users as $u): $self = (int) $u['id'] === (int) $user['id']; ?>
              <tr>
                <td data-label="Name"><strong><?= e($u['name']) ?></strong><?= $self ? ' <span class="badge badge--ok">You</span>' : '' ?></td>
                <td data-label="Email"><?= e($u['email']) ?></td>
                <td data-label="Last sign-in"><?= e($u['last_login_at'] ? time_ago($u['last_login_at']) : 'Never') ?></td>
                <td data-label="Actions">
<?php if (!$self && count($users) > 1): ?>
                  <form method="post" class="inline-form" data-confirm="Remove <?= e($u['name']) ?>? They will no longer be able to sign in.">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="remove">
                    <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                    <button class="btn btn--danger btn--sm" type="submit">Remove</button>
                  </form>
<?php endif; ?>
                </td>
              </tr>
<?php endforeach; ?>
            </tbody>
          </table>
        </section>

        <div class="two-col">
          <section class="panel">
            <div class="panel-head"><h2>Add a user</h2></div>
            <form method="post" class="stack" autocomplete="off">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="add">
              <div class="field<?= $cls('name') ?>">
                <label for="u-name">Name</label>
                <input id="u-name" name="name" type="text" maxlength="120" required value="<?= e($form['name']) ?>">
                <?= $err('name') ?>
              </div>
              <div class="field<?= $cls('email') ?>">
                <label for="u-email">Email</label>
                <input id="u-email" name="email" type="email" maxlength="190" required value="<?= e($form['email']) ?>">
                <?= $err('email') ?>
              </div>
              <div class="field<?= $cls('password') ?>">
                <label for="u-password">Temporary password</label>
                <input id="u-password" name="password" type="text" minlength="<?= PASSWORD_MIN_LENGTH ?>" required autocomplete="new-password" data-strength>
                <p class="hint">At least <?= PASSWORD_MIN_LENGTH ?> characters. Give it to them privately; they can change it on this page. <span class="strength" data-strength-for="u-password"></span></p>
                <?= $err('password') ?>
              </div>
              <button class="btn btn--primary" type="submit">Add user</button>
            </form>
          </section>

          <section class="panel">
            <div class="panel-head"><h2>Change your password</h2></div>
            <form method="post" class="stack">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="password">
              <input type="text" name="username" value="<?= e($user['email']) ?>" autocomplete="username" hidden>
              <div class="field<?= $cls('current_password') ?>">
                <label for="pw-current">Current password</label>
                <input id="pw-current" name="current_password" type="password" required autocomplete="current-password">
                <?= $err('current_password') ?>
              </div>
              <div class="field<?= $cls('new_password') ?>">
                <label for="pw-new">New password</label>
                <input id="pw-new" name="new_password" type="password" minlength="<?= PASSWORD_MIN_LENGTH ?>" required autocomplete="new-password" data-strength>
                <p class="hint">At least <?= PASSWORD_MIN_LENGTH ?> characters. A short sentence is easy to remember and hard to guess. <span class="strength" data-strength-for="pw-new"></span></p>
                <?= $err('new_password') ?>
              </div>
              <div class="field<?= $cls('confirm_password') ?>">
                <label for="pw-confirm">Repeat new password</label>
                <input id="pw-confirm" name="confirm_password" type="password" required autocomplete="new-password">
                <?= $err('confirm_password') ?>
              </div>
              <button class="btn btn--primary" type="submit">Change password</button>
            </form>
          </section>
        </div>

        <section class="panel">
          <div class="panel-head"><h2>Recent activity</h2></div>
<?php if ($activity): ?>
          <table class="table">
            <thead><tr><th scope="col">When</th><th scope="col">Who</th><th scope="col">What</th></tr></thead>
            <tbody>
<?php foreach ($activity as $a): ?>
              <tr>
                <td data-label="When"><?= e(fmt_date($a['created_at'])) ?></td>
                <td data-label="Who"><?= e($a['name'] ?? 'Removed user') ?></td>
                <td data-label="What"><?= e($a['action']) ?></td>
              </tr>
<?php endforeach; ?>
            </tbody>
          </table>
<?php else: ?>
          <?= empty_state('No activity yet') ?>
<?php endif; ?>
        </section>
<?php admin_end(); ?>
