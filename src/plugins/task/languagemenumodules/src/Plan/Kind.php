<?php

/**
 * @package     LanguageMenuModules
 *
 * @copyright   Copyright (C) 2026 Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

namespace Yepr\Plugin\Task\LanguageMenuModules\Plan;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * One kind of menu module that every language gets: the top menu, or one of the side
 * menus.
 *
 * A kind is defined by two modules that are on the site already: the module of the
 * source language, and the template, a module of a translated language that was made
 * by hand. A new module copies the template, with its own menu and language. The top
 * menu shows level 1 of the language's main menu; a side menu shows, from level 2 on,
 * the branch below one level 1 item, and is only shown on the pages of that branch.
 */
final class Kind
{
	/**
	 * When no title is given for a language: "Mainmenu xx-XX".
	 */
	public const TITLE_MAINMENU = 'mainmenu';

	/**
	 * When no title is given for a language: the title of the level 1 item.
	 */
	public const TITLE_TOP_ITEM = 'top';

	/**
	 * When no title is given for a language: the title of the level 1 item after its
	 * last " / ", so "Explanations" from "User Guide / Explanations".
	 */
	public const TITLE_TOP_ITEM_LAST_PART = 'top-last-part';

	/**
	 * @param   string                 $key               A name for the logs: main, explanations, ...
	 * @param   int                    $sourceModuleId    The module of the source language.
	 * @param   int                    $templateModuleId  The module that new modules copy.
	 * @param   bool                   $isMain            The top menu (level 1) or a side menu.
	 * @param   array<string, string>  $titles            Module titles per language code.
	 * @param   string                 $fallbackTitle     How to title a language that is not in $titles.
	 */
	public function __construct(
		public readonly string $key,
		public readonly int $sourceModuleId,
		public readonly int $templateModuleId,
		public readonly bool $isMain,
		public readonly array $titles,
		public readonly string $fallbackTitle
	) {
	}
}
