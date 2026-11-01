<?php

/**
 * @package     LanguageMenuModules
 *
 * @copyright   Copyright (C) 2026 Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

namespace Yepr\Plugin\Task\LanguageMenuModules\Plan;

use Yepr\Plugin\Task\LanguageMenuModules\Site\Module;
use Yepr\Plugin\Task\LanguageMenuModules\Site\Snapshot;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Works out what a site needs so that every language has the menu modules of the
 * blueprint. It only reads the snapshot; the store writes the plan.
 *
 * A language counts when it is a published content language and its main menu
 * (mainmenu-xx-xx) has items. Per language and kind of module, a module is only made
 * when that language has none of that kind yet, so a second run makes nothing and the
 * modules made by hand stay as they are.
 */
final class Planner
{
	public function __construct(private readonly Blueprint $blueprint)
	{
	}

	/**
	 * @param   bool  $completePages  Add the missing pages of their branch to the modules that exist already.
	 * @param   bool  $fixSource      Give the source modules the source language, and the top menu its title.
	 * @param   bool  $setHomes       Make the translation of the source home page the home page of every language that has none.
	 *
	 * @throws  \RuntimeException  When the blueprint's modules or menus are not on the site.
	 */
	public function plan(Snapshot $site, bool $completePages = true, bool $fixSource = true, bool $setHomes = true): Plan
	{
		$bp       = $this->blueprint;
		$warnings = [];

		$sourceTops = [];
		$extras     = [];

		foreach ($bp->kinds as $kind) {
			$source   = $this->definingModule($site, $kind->sourceModuleId, $kind->key, 'source');
			$template = $this->definingModule($site, $kind->templateModuleId, $kind->key, 'template');

			if ($source->menutype() !== $bp->sourceMenu) {
				throw new \RuntimeException(\sprintf(
					'the %s source module %d shows menu "%s", not "%s"',
					$kind->key,
					$source->id,
					$source->menutype(),
					$bp->sourceMenu
				));
			}

			if ($kind->isMain) {
				continue;
			}

			$sourceTops[$kind->key] = $this->topOfModule($site, $source, $kind->key);

			// What the template is assigned to outside its own branch, like the search
			// results page: the new modules are assigned to the same pages in their language.
			$templateTop = $site->translation($sourceTops[$kind->key], $template->language);
			$templateTop = $templateTop === null ? null : $site->item($templateTop);

			if ($templateTop === null) {
				throw new \RuntimeException(\sprintf(
					'the %s template module %d is in %s, which has no translation of menu item %d',
					$kind->key,
					$template->id,
					$template->language,
					$sourceTops[$kind->key]
				));
			}

			$extras[$kind->key] = [];

			foreach ($site->assignments($template->id) as $page) {
				$item = $site->item($page);

				if ($item !== null && !$templateTop->contains($item)) {
					$extras[$kind->key][] = $page;
				}
			}
		}

		// The languages.
		$targets = [];

		foreach ($site->languages as $language) {
			if ($language === $bp->sourceLanguage) {
				continue;
			}

			if ($site->menuPages($bp->menuOf($language)) === []) {
				$warnings[] = \sprintf('%s: content language without a menu "%s"; skipped', $language, $bp->menuOf($language));

				continue;
			}

			$targets[] = $language;
		}

		sort($targets, SORT_STRING);

		$existing = $this->existingModules($site, $targets, $sourceTops, $warnings);

		// New modules.
		$new = [];

		foreach ($targets as $language) {
			foreach ($bp->kinds as $kind) {
				$found = $existing[$kind->key][$language] ?? [];

				if (\count($found) > 1) {
					$warnings[] = \sprintf(
						'%s: %d %s modules (%s); none made, check which one should go',
						$language,
						\count($found),
						$kind->key,
						implode(', ', $found)
					);
				}

				if ($found !== []) {
					continue;
				}

				$module = $this->newModule($site, $kind, $language, $sourceTops[$kind->key] ?? 0, $extras[$kind->key] ?? [], $warnings);

				if ($module !== null) {
					$new[] = $module;
				}
			}
		}

		// Pages missing from the modules that exist.
		$addPages = [];

		if ($completePages) {
			foreach ($bp->kinds as $kind) {
				foreach ($existing[$kind->key] ?? [] as $language => $ids) {
					foreach ($ids as $id) {
						$missing = $this->missingPages($site, $kind, $id, $language, $sourceTops[$kind->key] ?? 0);

						if ($missing !== []) {
							$addPages[$id] = $missing;
						}
					}
				}
			}
		}

		// The source modules.
		$updates = [];

		if ($fixSource) {
			foreach ($bp->kinds as $kind) {
				$source = $site->module($kind->sourceModuleId);

				if ($source === null) {
					continue;
				}

				if ($source->language !== $bp->sourceLanguage) {
					$updates[$source->id]['language'] = $bp->sourceLanguage;
				}

				if ($kind->isMain && $source->title !== $bp->sourceMainTitle) {
					$updates[$source->id]['title'] = $bp->sourceMainTitle;
				}
			}
		}

		[$orderings, $newOrderings] = $this->orderings($site, $existing, $new);

		// Home pages.
		$homes = [];

		if ($setHomes) {
			$sourceHome = $site->homeOf($bp->sourceLanguage);

			if ($sourceHome === null) {
				$warnings[] = \sprintf('%s has no home page, so no language gets one', $bp->sourceLanguage);
			} else {
				foreach ($targets as $language) {
					if ($site->homeOf($language) !== null) {
						continue;
					}

					$home = $site->translation($sourceHome->id, $language);
					$item = $home === null ? null : $site->item($home);

					if ($item === null || $item->published !== 1) {
						$warnings[] = \sprintf(
							'%s: no published translation of the home page "%s" (%d); no home page set',
							$language,
							$sourceHome->title,
							$sourceHome->id
						);

						continue;
					}

					$homes[$language] = $item->id;
				}
			}
		}

		return new Plan($new, $addPages, $updates, $orderings, $newOrderings, $homes, $warnings);
	}

	private function definingModule(Snapshot $site, int $id, string $kind, string $role): Module
	{
		$module = $site->module($id);

		if ($module === null || !$module->isSiteMenuModule() || $module->isTrashed()) {
			throw new \RuntimeException(\sprintf('the %s module of the %s menus, %d, is not a site menu module on this site', $role, $kind, $id));
		}

		return $module;
	}

	/**
	 * The level 1 item of the source menu whose branch a side menu module of the source
	 * language is assigned to: the one most of its pages are in.
	 */
	private function topOfModule(Snapshot $site, Module $module, string $kind): int
	{
		$count = [];

		foreach ($site->assignments($module->id) as $page) {
			$top = $page > 0 ? $site->topOf($page) : null;

			if ($top !== null && $top->menutype === $module->menutype()) {
				$count[$top->id] = ($count[$top->id] ?? 0) + 1;
			}
		}

		if ($count === []) {
			throw new \RuntimeException(\sprintf(
				'the %s source module %d is assigned to no page of menu "%s", so its branch is unknown',
				$kind,
				$module->id,
				$module->menutype()
			));
		}

		arsort($count);

		return (int) array_key_first($count);
	}

	/**
	 * The menu modules each language has already, per kind.
	 *
	 * The source and template modules are what the blueprint says. Any other menu module
	 * that shows a translated main menu is of the top menu kind when it starts at level 1,
	 * else of the side menu whose branch most of its pages are in.
	 *
	 * @param   list<string>         $targets
	 * @param   array<string, int>   $sourceTops
	 * @param   list<string>         $warnings
	 *
	 * @return  array<string, array<string, list<int>>>
	 */
	private function existingModules(Snapshot $site, array $targets, array $sourceTops, array &$warnings): array
	{
		$bp       = $this->blueprint;
		$existing = [];
		$defining = [];

		foreach ($bp->kinds as $kind) {
			$template = $site->module($kind->templateModuleId);

			$existing[$kind->key][$bp->sourceLanguage] = [$kind->sourceModuleId];

			if ($template !== null) {
				$existing[$kind->key][$template->language][] = $template->id;
			}

			$defining[$kind->sourceModuleId]   = true;
			$defining[$kind->templateModuleId] = true;
		}

		$languageOf = [];

		foreach ($targets as $language) {
			$languageOf[$bp->menuOf($language)] = $language;
		}

		foreach ($site->modules() as $module) {
			if (isset($defining[$module->id]) || !$module->isSiteMenuModule() || $module->isTrashed()) {
				continue;
			}

			$language = $languageOf[$module->menutype()] ?? null;

			if ($language === null) {
				continue;
			}

			$kind = $this->kindOf($site, $module, $language, $sourceTops);

			if ($kind === null) {
				$warnings[] = \sprintf(
					'%s: module %d "%s" shows menu "%s" but is none of the menu modules made here; left alone',
					$language,
					$module->id,
					$module->title,
					$module->menutype()
				);

				continue;
			}

			if ($module->language !== $language) {
				$warnings[] = \sprintf(
					'%s: module %d "%s" shows menu "%s" but has language "%s"',
					$language,
					$module->id,
					$module->title,
					$module->menutype(),
					$module->language
				);
			}

			$existing[$kind][$language][] = $module->id;
		}

		return $existing;
	}

	/**
	 * @param   array<string, int>  $sourceTops
	 */
	private function kindOf(Snapshot $site, Module $module, string $language, array $sourceTops): ?string
	{
		if ($module->startLevel() <= 1) {
			foreach ($this->blueprint->kinds as $kind) {
				if ($kind->isMain) {
					return $kind->key;
				}
			}

			return null;
		}

		$best  = null;
		$count = 0;

		foreach ($sourceTops as $key => $sourceTop) {
			$top = $site->translation($sourceTop, $language);
			$top = $top === null ? null : $site->item($top);

			if ($top === null) {
				continue;
			}

			$n = 0;

			foreach ($site->assignments($module->id) as $page) {
				$item = $page > 0 ? $site->item($page) : null;

				if ($item !== null && $top->contains($item)) {
					$n++;
				}
			}

			if ($n > $count) {
				$best  = $key;
				$count = $n;
			}
		}

		return $best;
	}

	/**
	 * A copy of the kind's template for one language.
	 *
	 * @param   list<int>     $extras    The template's pages outside its branch.
	 * @param   list<string>  $warnings
	 */
	private function newModule(Snapshot $site, Kind $kind, string $language, int $sourceTop, array $extras, array &$warnings): ?NewModule
	{
		$template = $site->module($kind->templateModuleId);
		$source   = $site->module($kind->sourceModuleId);

		if ($template === null || $source === null) {
			return null;
		}

		$params             = $template->params;
		$params['menutype'] = $this->blueprint->menuOf($language);
		$row                = $template->row;
		$top                = null;

		if ($kind->isMain) {
			// The top menu shows no title, whatever the template does.
			$row['showtitle'] = $source->showtitle;
			$pages            = [0];

			// The class by which the site template styles the top menu.
			$suffix = \is_scalar($params['class_sfx'] ?? null) ? (string) $params['class_sfx'] : '';

			if (!str_contains($suffix, 'topmenu')) {
				$params['class_sfx'] = ' topmenu';
			}
		} else {
			$topId = $site->translation($sourceTop, $language);
			$top   = $topId === null ? null : $site->item($topId);

			if ($top === null || $top->isTrashed()) {
				$warnings[] = \sprintf('%s: no translation of menu item %d, so no %s module', $language, $sourceTop, $kind->key);

				return null;
			}

			$pages = $site->branchPages($top->id);

			foreach ($extras as $extra) {
				$page = $site->translation($extra, $language);

				if ($page === null) {
					$warnings[] = \sprintf(
						'%s: the %s module is not assigned to menu item %d, which has no translation',
						$language,
						$kind->key,
						$extra
					);

					continue;
				}

				$pages[] = $page;
			}

			$pages = array_values(array_unique($pages));
		}

		unset($row['id'], $row['asset_id'], $row['ordering']);

		$row['title']            = $this->title($kind, $language, $top->title ?? '');
		$row['language']         = $language;
		$row['params']           = json_encode($params, JSON_THROW_ON_ERROR);
		$row['checked_out']      = null;
		$row['checked_out_time'] = null;

		return new NewModule($kind->key, $language, $row, $pages);
	}

	private function title(Kind $kind, string $language, string $topTitle): string
	{
		if (isset($kind->titles[$language])) {
			return $kind->titles[$language];
		}

		return match ($kind->fallbackTitle) {
			Kind::TITLE_MAINMENU          => 'Mainmenu ' . $language,
			Kind::TITLE_TOP_ITEM_LAST_PART => trim((string) strrchr('/' . $topTitle, '/'), '/ '),
			default                       => $topTitle,
		};
	}

	/**
	 * The pages a module that exists misses: of the top menu, the pages of its menu; of a
	 * side menu, the pages of its branch. Only for a module assigned to chosen pages: one
	 * on all pages, or on all but some, is left alone.
	 *
	 * @return  list<int>
	 */
	private function missingPages(Snapshot $site, Kind $kind, int $id, string $language, int $sourceTop): array
	{
		$pages = $site->assignments($id);

		if ($pages === [] || min($pages) <= 0) {
			return [];
		}

		if ($kind->isMain) {
			$should = $site->menuPages($this->blueprint->menuOf($language));
		} else {
			$top    = $site->translation($sourceTop, $language);
			$should = $top === null ? [] : $site->branchPages($top);
		}

		return array_values(array_diff($should, $pages));
	}

	/**
	 * The modules of each kind together, kind after kind as in the blueprint, and within a
	 * kind in the order of their language codes. The group takes the place of its first
	 * module in the position; the other modules of the position keep their order.
	 *
	 * @param   array<string, array<string, list<int>>>  $existing
	 * @param   list<NewModule>                          $new
	 *
	 * @return  array{0: array<int, int>, 1: array<string, int>}
	 */
	private function orderings(Snapshot $site, array $existing, array $new): array
	{
		$rank    = array_flip(array_map(static fn (Kind $kind): string => $kind->key, $this->blueprint->kinds));
		$members = [];

		foreach ($existing as $kind => $perLanguage) {
			foreach ($perLanguage as $language => $ids) {
				foreach ($ids as $id) {
					$module = $site->module($id);

					if ($module !== null) {
						$members[$module->position][] = [$rank[$kind], $language, $id];
					}
				}
			}
		}

		foreach ($new as $module) {
			$position = \is_scalar($module->row['position'] ?? null) ? (string) $module->row['position'] : '';

			$members[$position][] = [$rank[$module->kind], $module->language, $module->ref()];
		}

		$orderings    = [];
		$newOrderings = [];

		foreach ($members as $position => $group) {
			usort($group, static fn (array $a, array $b): int => [$a[0], $a[1], (string) $a[2]] <=> [$b[0], $b[1], (string) $b[2]]);

			$inGroup = [];

			foreach ($group as $member) {
				$inGroup[(string) $member[2]] = true;
			}

			$rows = array_filter(
				$site->modules(),
				static fn (Module $module): bool => $module->clientId === 0 && $module->position === (string) $position
			);

			usort($rows, static fn (Module $a, Module $b): int => [$a->ordering, $a->id] <=> [$b->ordering, $b->id]);

			$order  = [];
			$placed = false;

			foreach ($rows as $row) {
				if (!isset($inGroup[(string) $row->id])) {
					$order[] = $row->id;

					continue;
				}

				if (!$placed) {
					array_push($order, ...array_column($group, 2));
					$placed = true;
				}
			}

			if (!$placed) {
				array_push($order, ...array_column($group, 2));
			}

			foreach ($order as $i => $ref) {
				if (\is_string($ref)) {
					$newOrderings[$ref] = $i + 1;

					continue;
				}

				if ($site->module($ref)?->ordering !== $i + 1) {
					$orderings[$ref] = $i + 1;
				}
			}
		}

		return [$orderings, $newOrderings];
	}
}
