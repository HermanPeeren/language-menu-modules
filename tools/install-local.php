<?php

/**
 * Install the package this repository just built into a local Joomla.
 *
 *   php tools/install-local.php            # into ./joomla
 *   php tools/install-local.php ../site
 *
 * Through Joomla's own CLI, which needs no login and no browser. The package
 * is found by the version in the manifest, so it is always the current build.
 */

declare(strict_types=1);

$root = \dirname(__DIR__);
$site = $argv[1] ?? $root . '/joomla';

$xml = simplexml_load_file($root . '/src/languagemenumodules.xml');

if ($xml === false) {
    fwrite(STDERR, "Cannot read the manifest.\n");
    exit(1);
}

$package = \sprintf('%s/build/%s-%s.zip', $root, trim((string) $xml->name), trim((string) $xml->version));

if (!is_file($package)) {
    fwrite(STDERR, "No package at {$package}. Run `composer build` first.\n");
    exit(1);
}

$cli = $site . '/cli/joomla.php';

if (!is_file($cli)) {
    fwrite(STDERR, "No Joomla CLI at {$cli}.\n");
    exit(1);
}

passthru(\sprintf('php %s extension:install --path=%s', escapeshellarg($cli), escapeshellarg($package)), $status);

exit($status);
