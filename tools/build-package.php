<?php
/*
 * Builds the upload packages in dist/.
 *   php tools/build-package.php          → etowah-hostinger.zip: exactly what goes into Hostinger's public_html (new install).
 *   php tools/build-package.php update   → etowah-update.zip: code files only, for updating an already-installed site.
 *     Leaves out the installer, config, uploads, storage, images, and the other non-code files that are already on the server.
 *     Database changes run by themselves on the first page load after upload (app/migrate.php).
 */

$root = dirname(__DIR__);
$update = ($argv[1] ?? '') === 'update';
$out = $root . '/dist/' . ($update ? 'etowah-update.zip' : 'etowah-hostinger.zip');

// Paths (relative, forward slashes) that never go into a package.
// client-media/ holds the client's original photos and videos: never published.
$excludeDirs = ['.git', 'dist', 'docs', 'reference', 'tools', 'client-media', 'demo-static', '.claude', '.vscode', 'node_modules'];
$excludeFiles = ['.gitignore', '.gitattributes', 'wrangler.jsonc', '.assetsignore', 'app/config.php', 'app/install.lock', 'storage/install-code.txt', 'storage/install-attempts.txt'];
if ($update) {
    // uploads/ stays in: the rule below keeps only uploads/.htaccess, so its file-type rules reach the live site.
    array_push($excludeDirs, 'install', 'storage');
}
$codeTypes = ['php', 'js', 'css', 'sql', 'htaccess'];

if (!class_exists('ZipArchive')) {
    fwrite(STDERR, "The zip extension is required.\n");
    exit(1);
}
@mkdir(dirname($out), 0755, true);
@unlink($out);

$zip = new ZipArchive();
if ($zip->open($out, ZipArchive::CREATE) !== true) {
    fwrite(STDERR, "Cannot create $out\n");
    exit(1);
}

$count = 0;
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
foreach ($it as $file) {
    $rel = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
    $top = explode('/', $rel)[0];
    if (in_array($top, $excludeDirs, true) || in_array($rel, $excludeFiles, true)) {
        continue;
    }
    if (str_ends_with(strtolower($rel), '.md')) {
        continue;
    }
    // Runtime folders ship empty, with only their protection files.
    if (preg_match('#^(storage|uploads)/#', $rel) && !in_array($rel, ['storage/.htaccess', 'storage/.gitkeep', 'uploads/.htaccess'], true)) {
        continue;
    }
    if ($file->isDir()) {
        // The update zip only creates folders that hold files, so it adds nothing empty to the live site.
        if (!$update) {
            $zip->addEmptyDir($rel);
            $zip->setExternalAttributesName($rel . '/', ZipArchive::OPSYS_UNIX, (040755 << 16));
        }
        continue;
    }
    if ($update && !in_array(strtolower(pathinfo($rel, PATHINFO_EXTENSION) ?: ltrim(basename($rel), '.')), $codeTypes, true)) {
        continue;
    }
    $zip->addFile($file->getPathname(), $rel);
    $zip->setExternalAttributesName($rel, ZipArchive::OPSYS_UNIX, (0100644 << 16));
    $count++;
}
$zip->close();

printf("Built %s (%d files, %.1f MB)\n", str_replace('\\', '/', $out), $count, filesize($out) / 1048576);
