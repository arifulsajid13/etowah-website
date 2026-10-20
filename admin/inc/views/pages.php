<?php
defined('ETOWAH_ADMIN') || exit;
$meta = [];
foreach (db_all('SELECT page_key, meta_title, meta_description, updated_at FROM pages') as $row) {
    $meta[$row['page_key']] = $row;
}
$imageCounts = [];
foreach (db_all('SELECT page_key, COUNT(*) AS total, SUM(is_placeholder) AS placeholders FROM page_images GROUP BY page_key') as $row) {
    $imageCounts[$row['page_key']] = $row;
}
$lengthNote = function (string $text, int $max): string {
    $len = str_length($text);
    if ($len === 0) {
        return '<span class="badge badge--warning">Missing</span>';
    }
    return $len > $max ? '<span class="badge badge--warning">' . $len . ' — long</span>' : '<span class="badge badge--ok">' . $len . '</span>';
};

admin_start('Pages & SEO', 'pages');
?>
        <p class="lead">Edit how each page appears in Google (title and description) and replace its photos. The page text itself is not edited here — contact your web developer for wording changes.</p>
        <section class="panel">
          <table class="table">
            <thead><tr><th scope="col">Page</th><th scope="col">Title length</th><th scope="col">Description length</th><th scope="col">Photos</th></tr></thead>
            <tbody>
<?php foreach (PAGES as $key => [$path, $label]): $m = $meta[$key] ?? ['meta_title' => '', 'meta_description' => '']; $img = $imageCounts[$key] ?? ['total' => 0, 'placeholders' => 0]; ?>
              <tr>
                <td data-label="Page"><a class="row-link" href="<?= e(admin_url('page', ['key' => $key])) ?>"><strong><?= e($label) ?></strong></a><span class="cell-sub"><?= e($path) ?></span></td>
                <td data-label="Title length"><?= $lengthNote($m['meta_title'], 60) ?></td>
                <td data-label="Description length"><?= $lengthNote($m['meta_description'], 160) ?></td>
                <td data-label="Photos"><?= (int) $img['total'] ?><?php if ($img['placeholders']): ?> <span class="badge badge--warning"><?= (int) $img['placeholders'] ?> placeholder</span><?php endif; ?></td>
              </tr>
<?php endforeach; $g = $imageCounts['global'] ?? ['total' => 0, 'placeholders' => 0]; ?>
              <tr>
                <td data-label="Page"><a class="row-link" href="<?= e(admin_url('page', ['key' => 'global'])) ?>"><strong>Site-wide images</strong></a><span class="cell-sub">Contact band background on every page</span></td>
                <td data-label="Title length">—</td>
                <td data-label="Description length">—</td>
                <td data-label="Photos"><?= (int) $g['total'] ?><?php if ($g['placeholders']): ?> <span class="badge badge--warning"><?= (int) $g['placeholders'] ?> placeholder</span><?php endif; ?></td>
              </tr>
            </tbody>
          </table>
        </section>
<?php admin_end(); ?>
