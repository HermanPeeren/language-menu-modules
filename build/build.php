<?php

/**
 * Assembles the installable plugin package.
 *
 *   php build/build.php   -> build/plg_task_languagemenumodules-<version>.zip
 *
 * The repository already mirrors the installed layout, so this zips `src/`:
 * the manifest and install script at the root, the plugin under
 * plugins/task/languagemenumodules/, where the manifest's folder= points.
 */

declare(strict_types=1);

$root     = \dirname(__DIR__);
$manifest = simplexml_load_file($root . '/src/languagemenumodules.xml');

if ($manifest === false) {
    fwrite(STDERR, "Cannot read the manifest.\n");
    exit(1);
}

$version = trim((string) $manifest->version);
$name    = trim((string) $manifest->name);

if ($version === '' || $name === '') {
    fwrite(STDERR, "The manifest needs both a version and a name.\n");
    exit(1);
}

$zipPath = $root . '/build/' . $name . '-' . $version . '.zip';

if (is_file($zipPath)) {
    unlink($zipPath);
}

$zip = new ZipArchive();

if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::EXCL) !== true) {
    fwrite(STDERR, "Cannot create {$zipPath}.\n");
    exit(1);
}

$source = $root . '/src';
$files  = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS));
$added  = 0;

foreach ($files as $file) {
    if (!$file instanceof SplFileInfo || !$file->isFile()) {
        continue;
    }

    // Forward slashes always: a backslash in a zip entry becomes part of a
    // file name on Linux, and the plugin then fails to load.
    $entry = str_replace('\\', '/', substr($file->getPathname(), \strlen($source) + 1));

    $zip->addFile($file->getPathname(), $entry);
    $added++;
}

$zip->close();

printf("%s\n  %d files, %.1f KB\n", basename($zipPath), $added, filesize($zipPath) / 1024);
