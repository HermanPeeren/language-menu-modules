<?php

/**
 * What the suite needs before it can load any of the plugin's classes.
 *
 * Every Joomla source file opens with `defined('_JEXEC') or die;`. Under
 * PHPUnit that constant is not defined, so loading such a class would `die`
 * and end the run without a word. Joomla's own test suites define it too.
 *
 * The unit tests need nothing else from Joomla: the classes they cover take
 * their HTTP transport and their cursor store as interfaces.
 */

declare(strict_types=1);

// phpcs:disable PSR1.Files.SideEffects
require __DIR__ . '/../vendor/autoload.php';

if (!\defined('_JEXEC')) {
    \define('_JEXEC', 1);
}
// phpcs:enable PSR1.Files.SideEffects
