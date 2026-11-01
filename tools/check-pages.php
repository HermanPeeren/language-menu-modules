<?php

/**
 * Ask the development site which menu modules it shows on real pages.
 *
 *   php tools/check-pages.php [base-url]   # default http://localhost/language-menu-modules/joomla/
 *
 * Needs the stand-in template of docs/development.md in joomla/templates/joomla: it
 * writes, in an HTML comment, the modules Joomla selects for position-1 and position-8,
 * so this sees the language filter and the page assignments at work. For every language
 * it fetches the home page and pages of each side menu branch, on level 1, 2 and the
 * deepest level, and checks: the page is in that language, position-1 has that
 * language's top menu and nothing else of mod_menu, position-8 has exactly the side
 * menu of the branch, and the language switcher offers every language.
 */

declare(strict_types=1);

$base = rtrim($argv[1] ?? 'http://localhost/language-menu-modules/joomla/', '/') . '/';
$site = \dirname(__DIR__) . '/joomla';

\define('_JEXEC', 1);
require $site . '/configuration.php';

$config        = array_map('strval', get_object_vars(new JConfig()));
[$host, $port] = array_pad(explode(':', $config['host'], 2), 2, '3306');
$pdo           = new PDO(
    \sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $config['db']),
    $config['user'],
    $config['password'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);
$p = $config['dbprefix'];

/**
 * @return list<array<string, mixed>>
 */
$select = static function (string $sql, array $args = []) use ($pdo): array {
    $statement = $pdo->prepare($sql);
    $statement->execute($args);

    /** @var list<array<string, mixed>> */
    return $statement->fetchAll();
};

$languages = array_column($select("SELECT lang_code, sef FROM {$p}languages WHERE published = 1 ORDER BY lang_code"), 'sef', 'lang_code');
$menuModules = [];

foreach ($select("SELECT id, title, language, params FROM {$p}modules WHERE module = 'mod_menu' AND client_id = 0 AND published = 1") as $row) {
    $menuModules[(int) $row['id']] = $row;
}

const BRANCHES = ['user-guide', 'tutorials', 'how-to-guides', 'help-pages-references', 'contributors'];

$fetch = static function (string $url): ?array {
    $html = @file_get_contents($url, false, stream_context_create(['http' => ['follow_location' => 1, 'ignore_errors' => true]]));

    if ($html === false || !preg_match('/<!--LMM(.*)LMM-->/s', $html, $m)) {
        return null;
    }

    $data = json_decode($m[1], true);

    return \is_array($data) ? $data : null;
};

$problems = [];
$pages    = 0;

foreach ($languages as $language => $sef) {
    $menu = $language === 'en-GB' ? 'mainmenu' : 'mainmenu-' . strtolower($language);

    // The pages to look at: per branch its level 1 item, its first level 2 item and its
    // deepest item; plus the home page.
    $targets = [['home', $base . 'index.php/' . ($language === 'en-GB' ? '' : $sef . '/'), null]];

    foreach (BRANCHES as $i => $path) {
        $top = $select(
            "SELECT t.id, t.lft, t.rgt FROM {$p}menu s
              JOIN {$p}associations a1 ON a1.id = s.id AND a1.context = 'com_menus.item'
              JOIN {$p}associations a2 ON a2.`key` = a1.`key` AND a2.context = a1.context
              JOIN {$p}menu t ON t.id = a2.id
             WHERE s.menutype = 'mainmenu' AND s.level = 1 AND s.path = ? AND t.menutype = ?",
            [$path, $menu]
        )[0] ?? null;

        if ($top === null) {
            $problems[] = "{$language}: no level 1 item for {$path}";

            continue;
        }

        $inBranch = $select(
            "SELECT id, level, path FROM {$p}menu WHERE menutype = ? AND lft BETWEEN ? AND ? AND published = 1 AND access = 1
              AND type = 'component' ORDER BY level, lft",
            [$menu, $top['lft'], $top['rgt']]
        );
        // SEF URLs: a non-SEF one is redirected to the SEF URL of the current language.
        $picked = [];

        foreach ($inBranch as $row) {
            if ((int) $row['level'] === 1 || ((int) $row['level'] === 2 && \count($picked) < 2)) {
                $picked[(int) $row['id']] = $row['path'];
            }
        }

        if ($inBranch !== []) {
            $picked[(int) end($inBranch)['id']] = end($inBranch)['path'];
        }

        foreach ($picked as $id => $itemPath) {
            $targets[] = [BRANCHES[$i], $base . 'index.php/' . ($language === 'en-GB' ? '' : $sef . '/') . $itemPath, $id];
        }
    }

    foreach ($targets as [$branch, $url, $id]) {
        $pages++;
        $data = $fetch($url);
        $what = \sprintf('%s %s %s', $language, $branch, $id ?? '');

        if ($data === null) {
            $problems[] = "{$what}: no module list in {$url}";

            continue;
        }

        if ($data['lang'] !== $language) {
            $problems[] = "{$what}: the page is in {$data['lang']}";
        }

        $menus = static fn (array $list): array => array_values(array_filter(
            array_map(static fn (string $entry): int => (int) $entry, $list),
            static fn (int $module): bool => isset($menuModules[$module])
        ));

        $top  = $menus($data['position-1'] ?? []);
        $side = $menus($data['position-8'] ?? []);

        $ownTop = \count($top) === 1 && $menuModules[$top[0]]['language'] === $language
            && json_decode($menuModules[$top[0]]['params'], true)['menutype'] === $menu;

        if (!$ownTop) {
            $problems[] = "{$what}: position-1 shows " . json_encode($data['position-1'] ?? [], JSON_UNESCAPED_UNICODE);
        }

        $branchOf = $branch === 'home' ? 'user-guide' : $branch;

        if (\count($side) !== 1 || $menuModules[$side[0]]['language'] !== $language) {
            $problems[] = "{$what}: position-8 shows " . json_encode($data['position-8'] ?? [], JSON_UNESCAPED_UNICODE);
        } elseif (($data['render'][$side[0]] ?? 0) === 0 && $branchOf !== 'contributors') {
            $problems[] = "{$what}: side menu {$side[0]} renders no items";
        }

        $offered = substr_count((string) ($data['switcher'] ?? ''), '<li');

        if ($offered < \count($languages)) {
            $problems[] = \sprintf('%s: the language switcher offers %d of %d languages', $what, $offered, \count($languages));
        }
    }
}

if ($problems === []) {
    echo "OK: {$pages} pages in " . \count($languages) . " languages show their own top menu and the side menu of their branch.\n";
    exit(0);
}

echo implode("\n", $problems) . "\n";
exit(1);
