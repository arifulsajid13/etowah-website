<?php
require __DIR__ . '/inc/init.php';

admin_session();
if (is_post() && csrf_valid()) {
    log_activity('Signed out', 'user', isset($_SESSION['uid']) ? (int) $_SESSION['uid'] : null);
    logout();
}
redirect('/admin/login.php', 303);
