<?php

/**
 * php-cs-fixer configuration.
 *
 * PSR-12 plus a few rules, for tests, build and tools. src/ follows Joomla's
 * conventions instead (tabs), which phpcs checks; this fixer would turn them
 * into spaces, so it does not touch src/. Run it with `composer cs-fix`; it
 * rewrites files, so read the diff.
 */

declare(strict_types=1);

$finder = PhpCsFixer\Finder::create()
    ->in([__DIR__ . '/tests', __DIR__ . '/build', __DIR__ . '/tools'])
    ->exclude(['cypress'])
    ->name('*.php')
    ->ignoreDotFiles(true)
    ->ignoreVCS(true);

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(false)
    ->setRules([
        '@PSR12'                      => true,
        'array_syntax'                => ['syntax' => 'short'],
        'binary_operator_spaces'      => [
            'default'   => 'single_space',
            'operators' => ['=>' => 'align_single_space_minimal', '=' => 'align_single_space_minimal'],
        ],
        'no_unused_imports'           => true,
        'ordered_imports'             => ['sort_algorithm' => 'alpha'],
        'single_quote'                => true,
        'trailing_comma_in_multiline' => true,
        'no_trailing_whitespace'      => true,
        'single_line_empty_body'      => false,
    ])
    ->setFinder($finder);
