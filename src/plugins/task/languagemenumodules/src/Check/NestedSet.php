<?php

/**
 * @package     LanguageMenuModules
 *
 * @copyright   Copyright (C) 2026 Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

namespace Yepr\Plugin\Task\LanguageMenuModules\Check;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Is a nested set whole? For #__assets, which this plugin adds to.
 *
 * Whole means: one root; walking the tree from the root, children in the order of their
 * lft, numbers every node exactly with the lft, rgt and level it has. Then no two nodes
 * overlap, no number is skipped or used twice, and every parent_id agrees with the
 * numbers.
 */
final class NestedSet
{
	/**
	 * @param   list<array{id: int, parent_id: int, lft: int, rgt: int, level: int}>  $rows
	 *
	 * @return  list<string>  The problems, at most a dozen; none when the set is whole.
	 */
	public static function problems(array $rows): array
	{
		$problems = [];
		$children = [];
		$byId     = [];
		$roots    = [];

		foreach ($rows as $row) {
			$byId[$row['id']] = $row;

			if ($row['parent_id'] === 0) {
				$roots[] = $row['id'];
			} else {
				$children[$row['parent_id']][] = $row['id'];
			}
		}

		if (\count($roots) !== 1) {
			return [\sprintf('%d roots instead of one', \count($roots))];
		}

		foreach ($children as $parent => &$ids) {
			if (!isset($byId[$parent])) {
				$problems[] = \sprintf('%d rows have parent %d, which does not exist', \count($ids), $parent);
			}

			usort($ids, static fn (int $a, int $b): int => $byId[$a]['lft'] <=> $byId[$b]['lft']);
		}

		unset($ids);

		$counter = $byId[$roots[0]]['lft'];
		$seen    = 0;
		$stack   = [[$roots[0], 0, false]];

		while ($stack !== [] && \count($problems) < 12) {
			[$id, $level, $closing] = array_pop($stack);
			$row                    = $byId[$id];

			if ($closing) {
				if ($row['rgt'] !== $counter) {
					$problems[] = \sprintf('node %d has rgt %d, should be %d', $id, $row['rgt'], $counter);
				}

				$counter++;

				continue;
			}

			$seen++;

			if ($row['lft'] !== $counter) {
				$problems[] = \sprintf('node %d has lft %d, should be %d', $id, $row['lft'], $counter);
			}

			if ($row['level'] !== $level) {
				$problems[] = \sprintf('node %d has level %d, should be %d', $id, $row['level'], $level);
			}

			$counter++;
			$stack[] = [$id, $level, true];

			foreach (array_reverse($children[$id] ?? []) as $child) {
				$stack[] = [$child, $level + 1, false];
			}
		}

		if ($problems === [] && $seen !== \count($rows)) {
			$problems[] = \sprintf('%d rows are not under the root', \count($rows) - $seen);
		}

		return $problems;
	}
}
