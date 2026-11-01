<?php

/**
 * The Joomla constants the plugin reads.
 *
 * Joomla defines these when an application boots, so they exist at runtime but
 * in no file an analyser reads. Only the ones this plugin uses are here.
 */

declare(strict_types=1);

// The Joomla version, which the install script checks.
\define('JVERSION', '6.2.0');

// The site folder, whose cache the plugin cleans.
\define('JPATH_SITE', __DIR__ . '/../joomla');

// The class of a site's configuration.php, which tools/verify-modules.php reads.
if (!class_exists('JConfig')) {
    // phpcs:ignore PSR1.Classes.ClassDeclaration.MissingNamespace, Squiz.Classes.ValidClassName.NotCamelCaps
    final class JConfig
    {
        public string $host     = '';
        public string $db       = '';
        public string $user     = '';
        public string $password = '';
        public string $dbprefix = '';
    }
}
