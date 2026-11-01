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
 * One row of #__menu on the site side, with what the plan needs of it.
 */
final class MenuItem
{
	public function __construct(
		public readonly int $id,
		public readonly int $parentId,
		public readonly int $lft,
		public readonly int $rgt,
		public readonly int $level,
		public readonly string $menutype,
		public readonly string $type,
		public readonly string $title,
		public readonly string $language,
		public readonly int $published,
		public readonly bool $home
	) {
	}

	public function isTrashed(): bool
	{
		return $this->published === -2;
	}

	/**
	 * Does the item have a page of its own, that a module can be shown on? A heading, a
	 * separator and a URL have none, and an alias shows the page of the item it points to.
	 */
	public function isPage(): bool
	{
		return !\in_array($this->type, ['heading', 'separator', 'url', 'alias'], true);
	}

	/**
	 * Is $other this item or one of its descendants?
	 */
	public function contains(self $other): bool
	{
		return $other->menutype === $this->menutype && $other->lft >= $this->lft && $other->rgt <= $this->rgt;
	}
}
