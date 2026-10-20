<?php
/*
 * Builds dist/etowah-hostinger.zip: exactly what goes into Hostinger's public_html.
 * Usage: php tools/build-package.php
 */

$root = dirname(__DIR__);
$out = $root . '/dist/etowah-hostinger.zip';

// Paths (relative, forward slashes) that never go into the package.
$excludeDirs = ['.git', 'dist', 'docs', 'reference', 'tools', '.claude', '.vscode', 'node_modules'];
$excludeFiles = ['.gitignore', '.gitattributes', 'wrangler.jsonc', '.assetsignore', 'app/config.php', 'app/install.lock', 'storage/install-code.txt', 'storage/install-attempts.txt'];

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
        $zip->addEmptyDir($rel);
        $zip->setExternalAttributesName($rel . '/', ZipArchive::OPSYS_UNIX, (040755 << 16));
        continue;
    }
    $zip->addFile($file->getPathname(), $rel);
    $zip->setExternalAttributesName($rel, ZipArchive::OPSYS_UNIX, (0100644 << 16));
    $count++;
}
$zip->close();

printf("Built %s (%d files, %.1f MB)\n", str_replace('\\', '/', $out), $count, filesize($out) / 1048576);
