<?php

/**
 * Check the menu modules of a site: does every content language have its top menu and
 * its five side menus, on the right pages, in the right order, with a home page, and is
 * #__assets still whole?
 *
 *   php tools/verify-modules.php            # the site in ./joomla
 *   php tools/verify-modules.php ../site    # another Joomla, by its configuration.php
 *
 * Read only. It shares no code with the plugin: it reads the tables with PDO and checks
 * the outcome its own way, so a mistake in the plugin is not repeated here. Exit code 0
 * when everything holds, 1 when something does not; each problem is printed.
 */

declare(strict_types=1);

$site = $argv[1] ?? \dirname(__DIR__) . '/joomla';

if (!is_file($site . '/configuration.php')) {
    fwrite(STDERR, "No configuration.php in {$site}.\n");
    exit(1);
}

\define('_JEXEC', 1);
require $site . '/configuration.php';

$config = array_map('strval', get_object_vars(new JConfig()));

[$host, $port] = array_pad(explode(':', $config['host'], 2), 2, '3306');
$pdo           = new PDO(
    \sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $config['db']),
    $config['user'],
    $config['password'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);
$prefix = $config['dbprefix'];

/**
 * @return list<array<string, mixed>>
 */
$select = static function (string $sql) use ($pdo): array {
    $statement = $pdo->query($sql);

    if ($statement === false) {
        throw new RuntimeException('Query failed: ' . $sql);
    }

    /** @var list<array<string, mixed>> */
    return $statement->fetchAll();
};

$problems = [];
$fail     = static function (string $message) use (&$problems): void {
    $problems[] = $message;
};

// The source: the English main menu and the level 1 items the side menus are for, by
// the path of their alias, so this does not rely on the ids the plugin was given.
const SOURCE_LANGUAGE = 'en-GB';
const SOURCE_MENU     = 'mainmenu';
const SIDE_MENUS      = [
    'explanations' => 'user-guide',
    'tutorials'    => 'tutorials',
    'howto'        => 'how-to-guides',
    'adminpages'   => 'help-pages-references',
    'contributors' => 'contributors',
];

// 1. #__assets: walking the tree in lft order gives back exactly the stored numbers.
$assets   = [];
$children = [];

foreach ($select("SELECT id, parent_id, lft, rgt, level, name FROM {$prefix}assets") as $row) {
    $assets[(int) $row['id']] = $row;

    if ((int) $row['parent_id'] !== 0) {
        $children[(int) $row['parent_id']][] = (int) $row['id'];
    }
}

foreach ($children as &$ids) {
    usort($ids, static fn (int $a, int $b): int => (int) $assets[$a]['lft'] <=> (int) $assets[$b]['lft']);
}

unset($ids);

$roots = array_keys(array_filter($assets, static fn (array $row): bool => (int) $row['parent_id'] === 0));

if (\count($roots) !== 1) {
    $fail(\sprintf('#__assets has %d roots', \count($roots)));
} else {
    $counter = (int) $assets[$roots[0]]['lft'];
    $visited = 0;
    $walk    = static function (int $id, int $level) use (&$walk, &$counter, &$visited, $assets, $children, $fail): void {
        $visited++;

        if ((int) $assets[$id]['lft'] !== $counter || (int) $assets[$id]['level'] !== $level) {
            $fail(\sprintf('asset %d has lft %d level %d, should be %d and %d', $id, $assets[$id]['lft'], $assets[$id]['level'], $counter, $level));
        }

        $counter++;

        foreach ($children[$id] ?? [] as $child) {
            $walk($child, $level + 1);
        }

        if ((int) $assets[$id]['rgt'] !== $counter) {
            $fail(\sprintf('asset %d has rgt %d, should be %d', $id, $assets[$id]['rgt'], $counter));
        }

        $counter++;
    };
    $walk($roots[0], 0);

    if ($visited !== \count($assets)) {
        $fail(\sprintf('%d assets are not under the root', \count($assets) - $visited));
    }
}

$assetByName = array_column($assets, 'id', 'name');
$component   = $assetByName['com_modules'] ?? null;

// 2. Every module has its own asset under com_modules.
$modules = [];

foreach ($select("SELECT * FROM {$prefix}modules") as $row) {
    $modules[(int) $row['id']] = $row;
    $asset                     = $assets[(int) $row['asset_id']] ?? null;

    if ($asset === null || $asset['name'] !== 'com_modules.module.' . $row['id'] || (int) $asset['parent_id'] !== (int) $component) {
        $fail(\sprintf('module %d "%s" has no asset of its own under com_modules (asset_id %d)', $row['id'], $row['title'], $row['asset_id']));
    }
}

foreach ($assetByName as $name => $id) {
    if (preg_match('/^com_modules\.module\.(\d+)$/', (string) $name, $m) && !isset($modules[(int) $m[1]])) {
        echo \sprintf("note: asset %d %s belongs to no module\n", $id, $name);
    }
}

$pages = [];

foreach ($select("SELECT moduleid, menuid FROM {$prefix}modules_menu") as $row) {
    $pages[(int) $row['moduleid']][] = (int) $row['menuid'];
}

// The menus and their associations.
$items = [];

$columns = 'id, parent_id, lft, rgt, level, menutype, type, title, path, language, published, home';

foreach ($select("SELECT {$columns} FROM {$prefix}menu WHERE client_id = 0 AND id > 1") as $row) {
    $items[(int) $row['id']] = $row;
}

$keyOf = [];
$group = [];

foreach ($select("SELECT id, `key` FROM {$prefix}associations WHERE context = 'com_menus.item'") as $row) {
    if (isset($items[(int) $row['id']])) {
        $keyOf[(int) $row['id']]                                       = (string) $row['key'];
        $group[(string) $row['key']][$items[(int) $row['id']]['language']] = (int) $row['id'];
    }
}

$translate = static fn (int $id, string $language): ?int => $items[$id]['language'] === $language
    ? $id
    : (isset($keyOf[$id]) ? ($group[$keyOf[$id]][$language] ?? null) : null);

// A heading, separator, URL or alias has no page of its own to show a module on.
$isPage = static fn (array $item): bool => (int) $item['published'] !== -2
    && !\in_array($item['type'], ['heading', 'separator', 'url', 'alias'], true);

$branch = static function (int $top) use ($items, $isPage): array {
    $t = $items[$top];

    return array_keys(array_filter(
        $items,
        static fn (array $item): bool => $item['menutype'] === $t['menutype']
            && (int) $item['lft'] >= (int) $t['lft'] && (int) $item['rgt'] <= (int) $t['rgt'] && $isPage($item)
    ));
};

$sourceTops = [];

foreach (SIDE_MENUS as $kind => $path) {
    foreach ($items as $id => $item) {
        if ($item['menutype'] === SOURCE_MENU && (int) $item['level'] === 1 && $item['path'] === $path) {
            $sourceTops[$kind] = $id;
        }
    }

    if (!isset($sourceTops[$kind])) {
        $fail("the source menu has no level 1 item {$path}");
    }
}

$sourceHome = null;

foreach ($items as $id => $item) {
    if ((int) $item['home'] === 1 && $item['language'] === SOURCE_LANGUAGE) {
        $sourceHome = $id;
    }
}

// 3. Per language: one top menu and one of each side menu, published, in that language.
$languages = array_column($select("SELECT lang_code FROM {$prefix}languages WHERE published = 1 ORDER BY lang_code"), 'lang_code');
$kindOf    = [];

foreach ($languages as $language) {
    $menu = $language === SOURCE_LANGUAGE ? SOURCE_MENU : SOURCE_MENU . '-' . strtolower((string) $language);
    $mine = array_filter(
        $modules,
        static fn (array $m): bool => $m['module'] === 'mod_menu' && (int) $m['client_id'] === 0 && (int) $m['published'] === 1
            && (json_decode((string) $m['params'], true)['menutype'] ?? '') === $menu
    );

    $main = [];
    $side = [];

    foreach ($mine as $id => $m) {
        $params = json_decode((string) $m['params'], true);

        if ($m['language'] !== $language) {
            $fail(\sprintf('%s: module %d "%s" shows %s but has language %s', $language, $id, $m['title'], $menu, $m['language']));
        }

        if ((int) $params['startLevel'] === 1) {
            $main[] = $id;
        } else {
            $side[] = $id;
        }
    }

    if (\count($main) !== 1) {
        $fail(\sprintf('%s: %d published top menu modules for %s', $language, \count($main), $menu));
    }

    foreach ($main as $id) {
        $params = json_decode((string) $modules[$id]['params'], true);
        $should = array_keys(array_filter($items, static fn (array $item): bool => $item['menutype'] === $menu && $isPage($item)));
        $on     = $pages[$id] ?? [];

        if ($modules[$id]['position'] !== 'position-1') {
            $fail(\sprintf('%s: top menu %d is on %s', $language, $id, $modules[$id]['position']));
        }

        if (!str_contains((string) ($params['class_sfx'] ?? ''), 'topmenu')) {
            $fail(\sprintf('%s: top menu %d has class_sfx "%s"', $language, $id, $params['class_sfx'] ?? ''));
        }

        if ((int) $params['endLevel'] !== 1) {
            $fail(\sprintf('%s: top menu %d ends at level %d', $language, $id, $params['endLevel']));
        }

        if (!\in_array(0, $on, true) && array_diff($should, $on) !== []) {
            $fail(\sprintf('%s: top menu %d misses %d pages of its menu', $language, $id, \count(array_diff($should, $on))));
        }

        $kindOf[$id] = ['main', $language];
    }

    $seen = [];

    foreach ($side as $id) {
        $on    = $pages[$id] ?? [];
        $found = null;

        foreach ($sourceTops as $kind => $sourceTop) {
            $top = $translate($sourceTop, (string) $language);

            if ($top !== null && \in_array($top, $on, true)) {
                $found = [$kind, $top];
            }
        }

        if ($found === null) {
            $fail(\sprintf('%s: side menu %d "%s" is on no level 1 item of a side menu', $language, $id, $modules[$id]['title']));

            continue;
        }

        [$kind, $top] = $found;
        $seen[$kind][] = $id;
        $kindOf[$id]   = [$kind, $language];
        $missing       = array_diff($branch($top), $on);

        if ($missing !== []) {
            $fail(\sprintf('%s: %s menu %d misses %d pages of its branch', $language, $kind, $id, \count($missing)));
        }

        foreach ($on as $page) {
            if ($page <= 0 || !isset($items[$page]) || $items[$page]['language'] !== $language) {
                $fail(\sprintf('%s: %s menu %d is assigned to page %d, which is not a page of %s', $language, $kind, $id, $page, $language));
            }
        }

        if ($modules[$id]['position'] !== 'position-8') {
            $fail(\sprintf('%s: %s menu %d is on %s', $language, $kind, $id, $modules[$id]['position']));
        }

        if ((int) $modules[$id]['showtitle'] === 1 && preg_match('/[a-z]{2}-[A-Z]{2}/', (string) $modules[$id]['title'])) {
            $fail(\sprintf('%s: %s menu %d shows the title "%s", with a language in it', $language, $kind, $id, $modules[$id]['title']));
        }
    }

    foreach (array_keys(SIDE_MENUS) as $kind) {
        if (\count($seen[$kind] ?? []) !== 1) {
            $fail(\sprintf('%s: %d %s menu modules', $language, \count($seen[$kind] ?? []), $kind));
        }
    }

    // 4. The home page.
    $homes = array_keys(array_filter($items, static fn (array $item): bool => (int) $item['home'] === 1 && $item['language'] === $language));

    if (\count($homes) !== 1) {
        $fail(\sprintf('%s: %d home pages', $language, \count($homes)));
    } elseif ($sourceHome !== null && $translate($sourceHome, (string) $language) !== $homes[0]) {
        $fail(\sprintf('%s: home page %d is not the translation of the source home page %d', $language, $homes[0], $sourceHome));
    } elseif ((int) $items[$homes[0]]['published'] !== 1 || $items[$homes[0]]['menutype'] !== $menu) {
        $fail(\sprintf('%s: home page %d is unpublished or not in %s', $language, $homes[0], $menu));
    }
}

// 5. The order: in each position the menu modules of a kind are together, kind after
// kind, and within a kind in the order of their language codes.
$kinds = array_merge(['main'], array_keys(SIDE_MENUS));

foreach (['position-1', 'position-8'] as $position) {
    $here = array_filter($modules, static fn (array $m): bool => (int) $m['client_id'] === 0 && $m['position'] === $position);

    uasort($here, static fn (array $a, array $b): int => [(int) $a['ordering'], (int) $a['id']] <=> [(int) $b['ordering'], (int) $b['id']]);

    $orderings = array_map('intval', array_column($here, 'ordering'));

    if (\count($orderings) !== \count(array_unique($orderings))) {
        $fail("{$position}: two modules share an ordering");
    }

    $sequence = [];

    foreach (array_keys($here) as $id) {
        if (isset($kindOf[$id])) {
            $sequence[] = $kindOf[$id];
        }
    }

    $sorted = $sequence;

    usort($sorted, static fn (array $a, array $b): int => [array_search($a[0], $kinds, true), $a[1]] <=> [array_search($b[0], $kinds, true), $b[1]]);

    if ($sequence !== $sorted) {
        $fail("{$position}: the menu modules are not kind by kind in the order of their language codes");
    }

    echo $position . ': ' . implode(' | ', array_map(
        static fn (array $m): string => $m['ordering'] . ' ' . $m['title'] . ' (' . $m['language'] . ')',
        $here
    )) . "\n\n";
}

if ($problems === []) {
    echo \sprintf("OK: %d languages, each with a top menu, five side menus and a home page; #__assets is whole.\n", \count($languages));
    exit(0);
}

echo implode("\n", $problems) . "\n";
exit(1);
