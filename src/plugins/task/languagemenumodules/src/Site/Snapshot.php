<?php

/**
 * @package     LanguageMenuModules
 *
 * @copyright   Copyright (C) 2026 Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

namespace Yepr\Plugin\Task\LanguageMenuModules\Site;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * What the plan is made from: the site's menus, their associations, the modules and the
 * pages each module is assigned to, read at one moment.
 *
 * Module assignments are as in #__modules_menu: 0 is "on all pages", a negative id is
 * "on all pages except this one", a positive id is "on this page".
 */
final class Snapshot
{
	/**
	 * @var array<int, MenuItem>
	 */
	private array $items = [];

	/**
	 * @var array<int, Module>
	 */
	private array $modules = [];

	/**
	 * Association key per menu item id.
	 *
	 * @var array<int, string>
	 */
	private array $keyOf = [];

	/**
	 * Menu item ids per association key and language.
	 *
	 * @var array<string, array<string, int>>
	 */
	private array $groups = [];

	/**
	 * @param   list<string>             $languages    The codes of the published content languages.
	 * @param   list<MenuItem>           $items        The site's menu items.
	 * @param   array<int, string>       $associations The com_menus.item association key per menu item id.
	 * @param   list<Module>             $modules      All modules.
	 * @param   array<int, list<int>>    $assignments  The #__modules_menu rows per module id.
	 */
	public function __construct(
		public readonly array $languages,
		array $items,
		array $associations,
		array $modules,
		private readonly array $assignments
	) {
		foreach ($items as $item) {
			$this->items[$item->id] = $item;
		}

		foreach ($modules as $module) {
			$this->modules[$module->id] = $module;
		}

		foreach ($associations as $id => $key) {
			if (!isset($this->items[$id])) {
				continue;
			}

			$this->keyOf[$id]                                    = $key;
			$this->groups[$key][$this->items[$id]->language] = $id;
		}
	}

	public function item(int $id): ?MenuItem
	{
		return $this->items[$id] ?? null;
	}

	public function module(int $id): ?Module
	{
		return $this->modules[$id] ?? null;
	}

	/**
	 * @return  array<int, Module>
	 */
	public function modules(): array
	{
		return $this->modules;
	}

	/**
	 * @return  list<int>
	 */
	public function assignments(int $moduleId): array
	{
		return $this->assignments[$moduleId] ?? [];
	}

	/**
	 * The menu item associated with $id in $language: $id itself when it is in that
	 * language already, null when there is none.
	 */
	public function translation(int $id, string $language): ?int
	{
		$item = $this->items[$id] ?? null;

		if ($item === null) {
			return null;
		}

		if ($item->language === $language) {
			return $id;
		}

		$key = $this->keyOf[$id] ?? null;

		return $key === null ? null : ($this->groups[$key][$language] ?? null);
	}

	/**
	 * The pages of a menu: its items that have a page and are not trashed, in menu order.
	 *
	 * @return  list<int>
	 */
	public function menuPages(string $menutype): array
	{
		$found = array_filter(
			$this->items,
			static fn (MenuItem $item): bool => $item->menutype === $menutype && $item->isPage() && !$item->isTrashed()
		);

		uasort($found, static fn (MenuItem $a, MenuItem $b): int => $a->lft <=> $b->lft);

		return array_keys($found);
	}

	/**
	 * The pages of a branch: the item and its descendants that have a page and are not
	 * trashed, in menu order.
	 *
	 * @return  list<int>
	 */
	public function branchPages(int $id): array
	{
		$top = $this->items[$id] ?? null;

		if ($top === null) {
			return [];
		}

		$found = array_filter(
			$this->items,
			static fn (MenuItem $item): bool => $top->contains($item) && $item->isPage() && !$item->isTrashed()
		);

		uasort($found, static fn (MenuItem $a, MenuItem $b): int => $a->lft <=> $b->lft);

		return array_keys($found);
	}

	/**
	 * The level 1 item above an item, or the item itself on level 1.
	 */
	public function topOf(int $id): ?MenuItem
	{
		$item = $this->items[$id] ?? null;

		while ($item !== null && $item->level > 1) {
			$item = $this->items[$item->parentId] ?? null;
		}

		return $item;
	}

	/**
	 * The home item of a language on the site: not trashed, home, in that language.
	 */
	public function homeOf(string $language): ?MenuItem
	{
		foreach ($this->items as $item) {
			if ($item->home && $item->language === $language && !$item->isTrashed()) {
				return $item;
			}
		}

		return null;
	}
}
