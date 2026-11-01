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
 * Everything one run writes, worked out before anything is written.
 */
final class Plan
{
	/**
	 * @param   list<NewModule>                       $newModules   Modules to create.
	 * @param   array<int, list<int>>                 $addPages     Per existing module: pages to add to its assignment.
	 * @param   array<int, array<string, string>>     $updates      Per existing module: columns (title, language) to change.
	 * @param   array<int, int>                       $orderings    Per existing module: its new ordering, only where it changes.
	 * @param   array<string, int>                    $newOrderings Per new module (by ref): its ordering.
	 * @param   array<string, int>                    $homes        Per language: the menu item to make its home.
	 * @param   list<string>                          $warnings     What could not be done, or looks wrong.
	 */
	public function __construct(
		public readonly array $newModules,
		public readonly array $addPages,
		public readonly array $updates,
		public readonly array $orderings,
		public readonly array $newOrderings,
		public readonly array $homes,
		public readonly array $warnings
	) {
	}

	/**
	 * Nothing to write. Warnings do not count: they are not changes.
	 */
	public function isEmpty(): bool
	{
		return $this->newModules === []
			&& $this->addPages === []
			&& $this->updates === []
			&& $this->orderings === []
			&& $this->homes === [];
	}
}
