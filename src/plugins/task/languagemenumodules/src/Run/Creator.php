<?php

/**
 * @package     LanguageMenuModules
 *
 * @copyright   Copyright (C) 2026 Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

namespace Yepr\Plugin\Task\LanguageMenuModules\Run;

use Yepr\Plugin\Task\LanguageMenuModules\Check\NestedSet;
use Yepr\Plugin\Task\LanguageMenuModules\Plan\Plan;
use Yepr\Plugin\Task\LanguageMenuModules\Plan\Planner;
use Yepr\Plugin\Task\LanguageMenuModules\Site\Snapshot;
use Yepr\Plugin\Task\LanguageMenuModules\Store\ModuleStore;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * One run: read the site, plan, log the plan, and unless it is a dry run, write it.
 *
 * Before the transaction commits, it checks what it wrote: #__assets is still a whole
 * nested set, every new module has its own asset, and planning again on the written
 * state finds nothing more to do. If any of that fails, nothing is written.
 */
final class Creator
{
	/**
	 * @param   \Closure(string, string): void  $log         A message and its priority: info, warning, error.
	 * @param   \Closure(): void                $cleanCache  Called after a successful write.
	 */
	public function __construct(
		private readonly ModuleStore $store,
		private readonly Planner $planner,
		private readonly \Closure $log,
		private readonly \Closure $cleanCache
	) {
	}

	/**
	 * @throws  \RuntimeException  When the site is not as expected or writing fails; nothing is changed then.
	 */
	public function run(bool $dryRun, bool $completePages, bool $fixSource, bool $setHomes): void
	{
		$site   = $this->store->snapshot();
		$broken = NestedSet::problems($this->store->assetRows());

		if ($broken !== [] && !$dryRun) {
			throw new \RuntimeException('#__assets is not a whole nested set before this run, so nothing is added to it: ' . implode('; ', $broken));
		}

		foreach ($broken as $problem) {
			$this->log('#__assets: ' . $problem, 'warning');
		}

		$plan = $this->planner->plan($site, $completePages, $fixSource, $setHomes);

		$this->report($site, $plan);

		if ($plan->isEmpty()) {
			$this->log('Nothing to do: every language has its menu modules.', 'info');

			return;
		}

		if ($dryRun) {
			$this->log('Dry run: nothing was written.', 'info');

			return;
		}

		$ids = $this->store->apply($plan, function (array $ids) use ($completePages, $fixSource, $setHomes): array {
			$problems = NestedSet::problems($this->store->assetRows());
			$problems = array_merge($problems, $this->store->moduleAssetProblems(array_values($ids)));
			$again    = $this->planner->plan($this->store->snapshot(), $completePages, $fixSource, $setHomes);

			if (!$again->isEmpty()) {
				$problems[] = \sprintf(
					'planning again still finds %d modules to make, %d to add pages to, %d to change, %d to reorder and %d home pages',
					\count($again->newModules),
					\count($again->addPages),
					\count($again->updates),
					\count($again->orderings),
					\count($again->homes)
				);
			}

			return $problems;
		});

		foreach ($ids as $ref => $id) {
			$this->log(\sprintf('Made module %d (%s).', $id, $ref), 'info');
		}

		$this->log(\sprintf(
			'Written: %d new modules, pages added to %d modules, %d modules changed, %d reordered, %d home pages.',
			\count($plan->newModules),
			\count($plan->addPages),
			\count($plan->updates),
			\count($plan->orderings),
			\count($plan->homes)
		), 'info');

		($this->cleanCache)();
	}

	private function report(Snapshot $site, Plan $plan): void
	{
		foreach ($plan->warnings as $warning) {
			$this->log($warning, 'warning');
		}

		foreach ($plan->newModules as $new) {
			$this->log(\sprintf(
				'New module %s: "%s", %s, ordering %d, %s.',
				$new->ref(),
				$new->title(),
				\is_scalar($new->row['position'] ?? null) ? (string) $new->row['position'] : '',
				$plan->newOrderings[$new->ref()] ?? 0,
				$new->pages === [0] ? 'on all pages' : \sprintf('on %d pages', \count($new->pages))
			), 'info');
		}

		foreach ($plan->addPages as $id => $pages) {
			$titles = array_map(static fn (int $page): string => $site->item($page)->title ?? (string) $page, \array_slice($pages, 0, 5));

			$this->log(\sprintf(
				'Module %d "%s" (%s): add %d pages of its menu it is not on yet: %s%s.',
				$id,
				$site->module($id)->title ?? '',
				$site->module($id)->language ?? '',
				\count($pages),
				implode(', ', $titles),
				\count($pages) > 5 ? ', ...' : ''
			), 'info');
		}

		foreach ($plan->updates as $id => $columns) {
			$changes = [];

			foreach ($columns as $column => $value) {
				$changes[] = \sprintf('%s "%s" becomes "%s"', $column, $site->module($id)->row[$column] ?? '', $value);
			}

			$this->log(\sprintf('Module %d: %s.', $id, implode(', ', $changes)), 'info');
		}

		if ($plan->orderings !== []) {
			$this->log(\sprintf('%d existing modules get a new ordering in their position.', \count($plan->orderings)), 'info');
		}

		foreach ($plan->homes as $language => $id) {
			$this->log(\sprintf('%s: home page becomes menu item %d "%s".', $language, $id, $site->item($id)->title ?? ''), 'info');
		}
	}

	private function log(string $message, string $priority): void
	{
		($this->log)($message, $priority);
	}
}
