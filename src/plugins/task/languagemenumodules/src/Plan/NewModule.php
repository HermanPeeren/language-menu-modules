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
 * A module to create: its row for #__modules (without id, asset_id and ordering, which
 * the store and the plan's ordering give it) and the pages it is assigned to.
 */
final class NewModule
{
	/**
	 * @param   array<string, mixed>  $row    The columns of #__modules.
	 * @param   list<int>             $pages  The #__modules_menu rows: [0] for all pages.
	 */
	public function __construct(
		public readonly string $kind,
		public readonly string $language,
		public readonly array $row,
		public readonly array $pages
	) {
	}

	/**
	 * How the plan's ordering refers to this module before it has an id.
	 */
	public function ref(): string
	{
		return $this->kind . '/' . $this->language;
	}

	public function title(): string
	{
		return \is_scalar($this->row['title'] ?? null) ? (string) $this->row['title'] : '';
	}
}
