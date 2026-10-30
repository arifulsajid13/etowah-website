<?php
declare(strict_types=1);
defined('ETOWAH_ADMIN') || exit;

/* Dashboard page frame: sidebar, top bar, toast, and confirm dialog. */

function admin_icon(string $name): string
{
    static $paths = [
        'overview'  => '<rect x="3" y="3" width="7" height="9" rx="1"/><rect x="14" y="3" width="7" height="5" rx="1"/><rect x="14" y="12" width="7" height="9" rx="1"/><rect x="3" y="16" width="7" height="5" rx="1"/>',
        'inquiries' => '<path d="M4 4h16v12H8l-4 4z"/><path d="M8 9h8"/><path d="M8 12h5"/>',
        'projects'  => '<path d="M3 7h18v13H3z"/><path d="M8 7V4h8v3"/><path d="M3 12h18"/>',
        'careers'   => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c.8-3.6 3.3-5.5 6.5-5.5s5.7 1.9 6.5 5.5"/><circle cx="17" cy="9" r="2.5"/><path d="M17 14.5c2.3 0 3.9 1.4 4.5 4"/>',
        'pages'     => '<path d="M6 3h9l4 4v14H6z"/><path d="M14 3v5h5"/><circle cx="11.5" cy="14" r="2.5"/><path d="M13.3 15.8L16 18.5"/>',
        'settings'  => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z"/>',
        'invoices'  => '<path d="M6 3h12v18l-3-2-3 2-3-2-3 2z"/><path d="M9 8h6"/><path d="M9 12h6"/><path d="M9 16h3"/>',
        'copy'      => '<rect x="8" y="8" width="12" height="12" rx="1"/><path d="M16 8V4H4v12h4"/>',
        'print'     => '<path d="M7 9V3h10v6"/><rect x="3" y="9" width="18" height="8" rx="1"/><path d="M7 14h10v7H7z"/>',
        'users'     => '<circle cx="12" cy="8" r="4"/><path d="M4 20c1-4 4-6 8-6s7 2 8 6"/>',
        'logout'    => '<path d="M15 4h4v16h-4"/><path d="M10 8l-4 4 4 4"/><path d="M6 12h10"/>',
        'sun'       => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>',
        'moon'      => '<path d="M20 14.5A8 8 0 1 1 9.5 4a6.5 6.5 0 0 0 10.5 10.5z"/>',
        'menu'      => '<path d="M4 7h16"/><path d="M4 12h16"/><path d="M4 17h16"/>',
        'close'     => '<path d="M6 6l12 12"/><path d="M18 6L6 18"/>',
        'alert'     => '<path d="M12 3l9.5 17h-19z"/><path d="M12 10v4"/><path d="M12 17.5v.5"/>',
        'external'  => '<path d="M14 4h6v6"/><path d="M20 4l-9 9"/><path d="M18 14v6H4V6h6"/>',
        'up'        => '<path d="M6 15l6-6 6 6"/>',
        'down'      => '<path d="M6 9l6 6 6-6"/>',
        'download'  => '<path d="M12 4v11"/><path d="M7 10l5 5 5-5"/><path d="M5 20h14"/>',
        'check'     => '<path d="M5 12.5l4.5 4.5L19 7.5"/>',
        'arrow'     => '<path d="M5 12h14"/><path d="M13 6l6 6-6 6"/>',
    ];
    return '<svg class="aicon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . ($paths[$name] ?? '') . '</svg>';
}

function admin_asset(string $file): string
{
    $path = ROOT . '/admin/assets/' . $file;
    return '/admin/assets/' . $file . '?v=' . (is_file($path) ? filemtime($path) : '1');
}

/** $section: overview | inquiries | projects | jobs | applications | invoices | pages | settings | users */
function admin_start(string $title, string $section): void
{
    $user = current_user();
    $flash = flash_take();
    $newInquiries = (int) db_value("SELECT COUNT(*) FROM inquiries WHERE status = 'new'");
    $newApplications = (int) db_value("SELECT COUNT(*) FROM applications WHERE status = 'new'");
    $overdueInvoices = (int) db_value('SELECT COUNT(*) FROM invoices WHERE ' . INVOICE_OVERDUE_SQL);
    $nav = [
        ['overview', 'Overview', 'overview', admin_url('overview'), 0],
        ['inquiries', 'Inquiries', 'inquiries', admin_url('inquiries'), $newInquiries],
        ['projects', 'Projects', 'projects', admin_url('projects'), 0],
        ['jobs', 'Careers', 'careers', admin_url('jobs'), $newApplications],
        ['invoices', 'Invoices', 'invoices', admin_url('invoices'), $overdueInvoices],
        ['pages', 'Pages & SEO', 'pages', admin_url('pages'), 0],
        ['settings', 'Site Settings', 'settings', admin_url('settings'), 0],
        ['users', 'Users', 'users', admin_url('users'), 0],
    ];
    $careers = in_array($section, ['jobs', 'applications'], true);
    ?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex,nofollow">
  <title><?= e($title) ?> · Etowah Dashboard</title>
  <link rel="icon" type="image/png" href="/assets/images/logo/logo-icon.png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Archivo:wght@700;800&family=Inter:wght@400;500;600&display=swap">
  <link rel="stylesheet" href="<?= e(admin_asset('admin.css')) ?>">
  <script src="<?= e(admin_asset('theme.js')) ?>"></script>
  <script src="<?= e(admin_asset('admin.js')) ?>" defer></script>
</head>
<body>
  <a class="skip-link" href="#content">Skip to content</a>
  <div class="shell">
    <aside class="sidebar" id="sidebar" aria-label="Dashboard">
      <div class="sidebar-head">
        <a class="sidebar-logo" href="<?= e(admin_url('overview')) ?>"><img src="/assets/images/logo/logo-horizontal.png" alt="Etowah Construction Group — Dashboard" width="739" height="128"></a>
        <button class="icon-btn sidebar-close" type="button" data-drawer-close aria-label="Close menu"><?= admin_icon('close') ?></button>
      </div>
      <nav>
        <ul class="side-nav">
<?php foreach ($nav as [$key, $label, $icon, $href, $count]):
    $active = $key === $section || ($key === 'jobs' && $careers); ?>
          <li>
            <a class="side-link" href="<?= e($href) ?>"<?= $active ? ' aria-current="page"' : '' ?>><?= admin_icon($icon) ?><span><?= e($label) ?></span><?php if ($count): ?><span class="side-count" title="<?= $count ?> <?= $key === 'invoices' ? 'overdue' : 'new' ?>"><?= $count ?></span><?php endif; ?></a>
<?php if ($key === 'jobs'): ?>
            <ul class="side-sub">
              <li><a href="<?= e(admin_url('jobs')) ?>"<?= $section === 'jobs' ? ' aria-current="page"' : '' ?>>Jobs</a></li>
              <li><a href="<?= e(admin_url('applications')) ?>"<?= $section === 'applications' ? ' aria-current="page"' : '' ?>>Applications</a></li>
            </ul>
<?php endif; ?>
          </li>
<?php endforeach; ?>
        </ul>
      </nav>
      <a class="side-view-site" href="/" target="_blank" rel="noopener"><?= admin_icon('external') ?><span>View website</span></a>
    </aside>
    <div class="drawer-backdrop" data-drawer-close hidden></div>

    <div class="main">
      <header class="topbar">
        <button class="icon-btn menu-btn" type="button" data-drawer-open aria-controls="sidebar" aria-expanded="false" aria-label="Open menu"><?= admin_icon('menu') ?></button>
        <h1 class="topbar-title"><?= e($title) ?></h1>
<?php if (search_hidden()): ?>
        <a class="topbar-flag" href="<?= e(admin_url('settings')) ?>" title="Search engines are told not to list this site. Change this in Site Settings.">Hidden from search engines</a>
<?php endif; ?>
        <div class="topbar-user">
          <span class="topbar-email" title="Signed in as <?= e($user['email']) ?>"><?= e($user['email']) ?></span>
          <button class="icon-btn" type="button" data-theme-toggle aria-label="Switch to light theme"><span class="theme-dark"><?= admin_icon('sun') ?></span><span class="theme-light"><?= admin_icon('moon') ?></span></button>
          <form method="post" action="/admin/logout.php" class="inline-form">
            <?= csrf_field() ?>
            <button class="btn btn--ghost btn--sm" type="submit"><?= admin_icon('logout') ?><span>Log out</span></button>
          </form>
        </div>
      </header>

      <main class="content" id="content" tabindex="-1">
<?php
    if ($flash) {
        echo '<div class="toast toast--' . e($flash['type']) . '" role="status" data-toast>' . e($flash['msg'])
            . '<button class="toast-close" type="button" aria-label="Dismiss" data-toast-close>' . admin_icon('close') . '</button></div>';
    }
}

function admin_end(): void
{
    ?>
      </main>
    </div>
  </div>

  <dialog class="confirm" id="confirm-dialog" aria-labelledby="confirm-title">
    <form method="dialog">
      <h2 class="confirm-title" id="confirm-title">Are you sure?</h2>
      <p class="confirm-text" data-confirm-text></p>
      <div class="confirm-actions">
        <button class="btn btn--ghost" value="cancel">Cancel</button>
        <button class="btn btn--danger" value="ok" data-confirm-ok>Confirm</button>
      </div>
    </form>
  </dialog>
</body>
</html>
<?php
}

/** Empty-state block. */
function empty_state(string $title, string $text = '', string $actionHref = '', string $actionLabel = ''): string
{
    $html = '<div class="empty"><p class="empty-title">' . e($title) . '</p>';
    if ($text !== '') {
        $html .= '<p class="empty-text">' . e($text) . '</p>';
    }
    if ($actionHref !== '') {
        $html .= '<a class="btn btn--primary btn--sm" href="' . e($actionHref) . '">' . e($actionLabel) . '</a>';
    }
    return $html . '</div>';
}
