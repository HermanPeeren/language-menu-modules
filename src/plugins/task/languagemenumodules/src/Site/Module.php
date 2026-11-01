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
 * One row of #__modules.
 *
 * $row is the whole row as read, so a new module can be a copy of an existing one with
 * a few columns changed, whatever columns the table has.
 */
final class Module
{
	/**
	 * @param   array<string, mixed>   $params  The decoded params.
	 * @param   array<string, mixed>   $row     The row as it is in the table.
	 */
	public function __construct(
		public readonly int $id,
		public readonly int $assetId,
		public readonly string $title,
		public readonly string $position,
		public readonly int $ordering,
		public readonly int $published,
		public readonly string $module,
		public readonly int $clientId,
		public readonly string $language,
		public readonly int $showtitle,
		public readonly array $params,
		public readonly array $row
	) {
	}

	public function isTrashed(): bool
	{
		return $this->published === -2;
	}

	public function isSiteMenuModule(): bool
	{
		return $this->module === 'mod_menu' && $this->clientId === 0;
	}

	public function menutype(): string
	{
		return \is_scalar($this->params['menutype'] ?? null) ? (string) $this->params['menutype'] : '';
	}

	public function startLevel(): int
	{
		return \is_numeric($this->params['startLevel'] ?? null) ? (int) $this->params['startLevel'] : 1;
	}
}
