<?php

declare(strict_types=1);

namespace Yepr\Plugin\Task\LanguageMenuModules\Tests\Support;

use Yepr\Plugin\Task\LanguageMenuModules\Plan\Plan;
use Yepr\Plugin\Task\LanguageMenuModules\Site\Snapshot;
use Yepr\Plugin\Task\LanguageMenuModules\Store\ModuleStore;

/**
 * A ModuleStore on a SiteFixture, writing a plan the way the database store does, with
 * the same transaction: when the check before the commit finds problems, the fixture is
 * put back as it was.
 */
final class MemoryModuleStore implements ModuleStore
{
    public int $writes = 0;

    public function __construct(public SiteFixture $site)
    {
    }

    public function snapshot(): Snapshot
    {
        return $this->site->snapshot();
    }

    public function assetRows(): array
    {
        // Root 1, com_modules 2 under it, one asset per module under that.
        $rows    = [['id' => 1, 'parent_id' => 0, 'lft' => 0, 'rgt' => 3 + 2 * \count($this->site->modules), 'level' => 0]];
        $rows[]  = ['id' => 2, 'parent_id' => 1, 'lft' => 1, 'rgt' => 2 + 2 * \count($this->site->modules), 'level' => 1];
        $counter = 2;

        foreach ($this->site->modules as $module) {
            $rows[]   = ['id' => (int) $module['asset_id'], 'parent_id' => 2, 'lft' => $counter, 'rgt' => $counter + 1, 'level' => 2];
            $counter += 2;
        }

        return $rows;
    }

    public function moduleAssetProblems(array $ids): array
    {
        return [];
    }

    public function apply(Plan $plan, \Closure $beforeCommit): array
    {
        $before = clone $this->site;
        $site   = $this->site;
        $ids    = [];
        $next   = max([0, ...array_keys($site->modules)]) + 1;

        foreach ($plan->updates as $id => $columns) {
            $site->modules[$id] = array_merge($site->modules[$id], $columns);
        }

        foreach ($plan->newModules as $new) {
            $site->modules[$next] = array_merge($new->row, [
                'id'       => $next,
                'asset_id' => 1000 + $next,
                'ordering' => $plan->newOrderings[$new->ref()] ?? 0,
            ]);
            $site->pages[$next] = $new->pages;
            $ids[$new->ref()]   = $next++;
        }

        foreach ($plan->addPages as $id => $pages) {
            $site->pages[$id] = array_merge($site->pages[$id] ?? [], $pages);
        }

        foreach ($plan->orderings as $id => $ordering) {
            $site->modules[$id]['ordering'] = $ordering;
        }

        foreach ($plan->homes as $id) {
            $site->homes[$id] = true;
        }

        $problems = $beforeCommit($ids);

        if ($problems !== []) {
            $this->site = $before;

            throw new \RuntimeException('nothing was changed, the check after writing failed: ' . implode('; ', $problems));
        }

        $this->writes++;

        return $ids;
    }
}
