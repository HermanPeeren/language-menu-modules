<?php

declare(strict_types=1);

namespace Yepr\Plugin\Task\LanguageMenuModules\Tests\Support;

use Yepr\Plugin\Task\LanguageMenuModules\Plan\Blueprint;
use Yepr\Plugin\Task\LanguageMenuModules\Plan\Kind;
use Yepr\Plugin\Task\LanguageMenuModules\Site\MenuItem;
use Yepr\Plugin\Task\LanguageMenuModules\Site\Module;
use Yepr\Plugin\Task\LanguageMenuModules\Site\Snapshot;

/**
 * A small User Guide: the same menu in English, German and French, the English modules,
 * the German ones made by hand from them, and no French modules yet.
 *
 * Each menu:
 *
 *   Guide            the home page in English and German
 *     Guide 1
 *     Guide 2
 *       Guide 2a     added after the modules were made: they miss it
 *   Tutorials
 *     Tutorial 1
 *     Tutorial group a heading, no page
 *   Contributors
 *     Contributor 1
 *   Search results
 *
 * Menu item ids are 100 * language number + key: English 101..110, German 201..210,
 * French 301..310.
 */
final class SiteFixture
{
    public const LANGUAGES = [1 => 'en-GB', 2 => 'de-DE', 3 => 'fr-FR'];

    /**
     * The menu, as [key, level, published, type] in tree order.
     */
    private const MENU = [
        [1, 1, 1, 'component'],  // Guide
        [2, 2, 1, 'component'],  // Guide 1
        [3, 2, 1, 'component'],  // Guide 2
        [4, 3, 1, 'component'],  // Guide 2a
        [5, 1, 1, 'component'],  // Tutorials
        [6, 2, 1, 'component'],  // Tutorial 1
        [10, 2, 1, 'heading'],   // Tutorial group
        [7, 1, 1, 'component'],  // Contributors
        [8, 2, 1, 'component'],  // Contributor 1
        [9, 1, 1, 'component'],  // Search results
    ];

    private const TITLES = [
        1 => 'Guide / Explanations', 2 => 'Guide 1', 3 => 'Guide 2', 4 => 'Guide 2a', 5 => 'Tutorials',
        6 => 'Tutorial 1', 7 => 'Contributors', 8 => 'Contributor 1', 9 => 'Search results', 10 => 'Tutorial group',
    ];

    /**
     * @var array<int, array<string, mixed>>
     */
    public array $modules = [];

    /**
     * @var array<int, list<int>>
     */
    public array $pages = [];

    /**
     * @var list<string>
     */
    public array $languages = ['de-DE', 'en-GB', 'fr-FR'];

    /**
     * Languages whose menu is left out.
     *
     * @var list<string>
     */
    public array $withoutMenu = [];

    /**
     * @var array<int, bool>
     */
    public array $homes = [101 => true, 201 => true];

    public function __construct()
    {
        $menu = static fn (string $language): string => $language === 'en-GB' ? 'mainmenu' : 'mainmenu-' . strtolower($language);

        // English.
        $this->module(1, 'Main Menu', 'position-1', 2, '*', 0, $menu('en-GB'), 1, ['class_sfx' => ' topmenu'], [0]);
        $this->module(2, 'Explanations', 'position-8', 2, 'en-GB', 1, $menu('en-GB'), 2, [], [101, 102, 103]);
        $this->module(3, 'Tutorials', 'position-8', 3, 'en-GB', 1, $menu('en-GB'), 2, [], [105, 106]);
        $this->module(4, 'Contributors menu', 'position-8', 4, '*', 0, $menu('en-GB'), 2, ['base' => 107], [107, 108]);

        // German, by hand.
        $this->module(11, 'Mainmenu de-DE', 'position-1', 3, 'de-DE', 1, $menu('de-DE'), 1, ['class_sfx' => ' topmenu'], [201, 202, 203, 205]);
        $this->module(12, 'Erklärungen', 'position-8', 5, 'de-DE', 1, $menu('de-DE'), 2, [], [201, 202, 203, 209]);
        $this->module(13, 'Tutorials', 'position-8', 6, 'de-DE', 1, $menu('de-DE'), 2, [], [205, 206]);
        $this->module(14, 'Mitwirkende', 'position-8', 7, 'de-DE', 0, $menu('de-DE'), 2, [], [207, 208]);

        // Others on the same positions.
        $this->module(8, 'Search', 'position-8', 1, '*', 1, '', 0, [], [0], 'mod_finder');
        $this->module(9, 'language switcher', 'position-1', 4, '*', 0, '', 0, [], [0], 'mod_languages');
        $this->module(10, 'Main Menu (2)', 'position-1', 1, '*', 0, $menu('en-GB'), 1, [], [0], 'mod_menu', 0);
    }

    /**
     * @param   array<string, mixed>  $params
     * @param   list<int>             $pages
     */
    public function module(
        int $id,
        string $title,
        string $position,
        int $ordering,
        string $language,
        int $showtitle,
        string $menutype,
        int $startLevel,
        array $params,
        array $pages,
        string $type = 'mod_menu',
        int $published = 1
    ): void {
        $params = array_merge(
            $type === 'mod_menu' ? ['menutype' => $menutype, 'base' => '', 'startLevel' => $startLevel, 'endLevel' => $startLevel === 1 ? 1 : 0] : [],
            $params
        );

        $this->modules[$id] = [
            'id'        => $id,
            'asset_id'  => 1000 + $id,
            'title'     => $title,
            'note'      => '',
            'content'   => null,
            'ordering'  => $ordering,
            'position'  => $position,
            'published' => $published,
            'module'    => $type,
            'access'    => 1,
            'showtitle' => $showtitle,
            'params'    => json_encode($params),
            'client_id' => 0,
            'language'  => $language,
        ];
        $this->pages[$id] = $pages;
    }

    public static function blueprint(): Blueprint
    {
        return new Blueprint('en-GB', 'mainmenu', 'Mainmenu', [
            new Kind('main', 1, 11, true, [], Kind::TITLE_MAINMENU),
            new Kind('explanations', 2, 12, false, ['fr-FR' => 'Explications'], Kind::TITLE_TOP_ITEM_LAST_PART),
            new Kind('tutorials', 3, 13, false, [], Kind::TITLE_TOP_ITEM),
            new Kind('contributors', 4, 14, false, [], Kind::TITLE_TOP_ITEM),
        ]);
    }

    public function snapshot(): Snapshot
    {
        $items        = [];
        $associations = [];

        foreach (self::LANGUAGES as $n => $language) {
            if (\in_array($language, $this->withoutMenu, true)) {
                continue;
            }

            $menutype = $language === 'en-GB' ? 'mainmenu' : 'mainmenu-' . strtolower($language);
            $numbers  = $this->numbers();

            foreach (self::MENU as [$key, $level, $published, $type]) {
                $id      = 100 * $n + $key;
                $parent  = $this->parentKey($key);
                $items[] = new MenuItem(
                    $id,
                    $parent === 0 ? 1 : 100 * $n + $parent,
                    $numbers[$key][0],
                    $numbers[$key][1],
                    $level,
                    $menutype,
                    $type,
                    self::TITLES[$key] . ($language === 'en-GB' ? '' : ' ' . substr($language, 0, 2)),
                    $language,
                    $published,
                    $this->homes[$id] ?? false
                );
                $associations[$id] = 'key' . $key;
            }
        }

        $modules = [];

        foreach ($this->modules as $row) {
            $modules[] = new Module(
                (int) $row['id'],
                (int) $row['asset_id'],
                (string) $row['title'],
                (string) $row['position'],
                (int) $row['ordering'],
                (int) $row['published'],
                (string) $row['module'],
                (int) $row['client_id'],
                (string) $row['language'],
                (int) $row['showtitle'],
                (array) json_decode((string) $row['params'], true),
                $row
            );
        }

        return new Snapshot($this->languages, $items, $associations, $modules, $this->pages);
    }

    private function parentKey(int $key): int
    {
        $stack = [];

        foreach (self::MENU as [$k, $level]) {
            $stack[$level] = $k;

            if ($k === $key) {
                return $stack[$level - 1] ?? 0;
            }
        }

        return 0;
    }

    /**
     * The lft and rgt of each key within its menu.
     *
     * @return array<int, array{0: int, 1: int}>
     */
    private function numbers(): array
    {
        $lft     = [];
        $rgt     = [];
        $open    = [];
        $counter = 1;

        foreach (self::MENU as [$key, $level]) {
            while ($open !== [] && $open[array_key_last($open)][1] >= $level) {
                $rgt[array_pop($open)[0]] = $counter++;
            }

            $lft[$key] = $counter++;
            $open[]    = [$key, $level];
        }

        while ($open !== []) {
            $rgt[array_pop($open)[0]] = $counter++;
        }

        $numbers = [];

        foreach ($lft as $key => $left) {
            $numbers[$key] = [$left, $rgt[$key] ?? 0];
        }

        return $numbers;
    }
}
