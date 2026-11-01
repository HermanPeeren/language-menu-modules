<?php

/**
 * @package     LanguageMenuModules
 *
 * @copyright   Copyright (C) 2026 Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

namespace Yepr\Plugin\Task\LanguageMenuModules\Store;

use Yepr\Plugin\Task\LanguageMenuModules\Plan\Plan;
use Yepr\Plugin\Task\LanguageMenuModules\Site\Snapshot;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Where the plan is read from and written to.
 */
interface ModuleStore
{
	public function snapshot(): Snapshot;

	/**
	 * The nested set of #__assets.
	 *
	 * @return  list<array{id: int, parent_id: int, lft: int, rgt: int, level: int}>
	 */
	public function assetRows(): array;

	/**
	 * Modules whose asset_id does not point at their own asset (com_modules.module.<id>).
	 *
	 * @param   list<int>  $ids  The modules to check.
	 *
	 * @return  list<string>
	 */
	public function moduleAssetProblems(array $ids): array;

	/**
	 * Write the whole plan in one transaction. $beforeCommit runs inside it, after all
	 * writes, and sees them; when it returns problems, everything is rolled back.
	 *
	 * @param   \Closure(array<string, int>): list<string>  $beforeCommit  Gets the ids of the new modules.
	 *
	 * @return  array<string, int>  The ids of the new modules, by their ref.
	 *
	 * @throws  \RuntimeException  When writing fails or $beforeCommit finds problems; nothing is changed then.
	 */
	public function apply(Plan $plan, \Closure $beforeCommit): array;
}
