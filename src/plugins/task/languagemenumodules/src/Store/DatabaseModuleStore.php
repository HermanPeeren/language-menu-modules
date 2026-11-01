<?php

/**
 * @package     LanguageMenuModules
 *
 * @copyright   Copyright (C) 2026 Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

namespace Yepr\Plugin\Task\LanguageMenuModules\Store;

use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Yepr\Plugin\Task\LanguageMenuModules\Plan\Plan;
use Yepr\Plugin\Task\LanguageMenuModules\Site\MenuItem;
use Yepr\Plugin\Task\LanguageMenuModules\Site\Module;
use Yepr\Plugin\Task\LanguageMenuModules\Site\Snapshot;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * The plan on #__modules, #__modules_menu, #__assets and #__menu, written straight into
 * the tables in one transaction.
 *
 * What saving a module in the backend does, without the form: a row in #__modules, its
 * pages in #__modules_menu, and an asset com_modules.module.<id> as the last child of
 * the com_modules asset, with empty rules so it inherits everything. #__assets is a
 * nested set, so the new assets get the numbers right after the last module asset and
 * everything after them moves up.
 */
final class DatabaseModuleStore implements ModuleStore
{
	public function __construct(private readonly DatabaseInterface $db)
	{
	}

	public function snapshot(): Snapshot
	{
		$db = $this->db;

		$query = $db->getQuery(true)
			->select($db->quoteName('lang_code'))
			->from($db->quoteName('#__languages'))
			->where($db->quoteName('published') . ' = 1')
			->order($db->quoteName('lang_code'));

		$languages = array_values(array_map('strval', $db->setQuery($query)->loadColumn()));

		$query = $db->getQuery(true)
			->select($db->quoteName(['id', 'parent_id', 'lft', 'rgt', 'level', 'menutype', 'type', 'title', 'language', 'published', 'home']))
			->from($db->quoteName('#__menu'))
			->where($db->quoteName('client_id') . ' = 0')
			->where($db->quoteName('id') . ' > 1');

		$items = [];

		foreach ($db->setQuery($query)->loadAssocList() as $row) {
			$items[] = new MenuItem(
				(int) $row['id'],
				(int) $row['parent_id'],
				(int) $row['lft'],
				(int) $row['rgt'],
				(int) $row['level'],
				(string) $row['menutype'],
				(string) $row['type'],
				(string) $row['title'],
				(string) $row['language'],
				(int) $row['published'],
				(int) $row['home'] === 1
			);
		}

		$context = 'com_menus.item';
		$query   = $db->getQuery(true)
			->select($db->quoteName(['id', 'key']))
			->from($db->quoteName('#__associations'))
			->where($db->quoteName('context') . ' = :context')
			->bind(':context', $context);

		$associations = [];

		foreach ($db->setQuery($query)->loadAssocList() as $row) {
			$associations[(int) $row['id']] = (string) $row['key'];
		}

		$query = $db->getQuery(true)
			->select('*')
			->from($db->quoteName('#__modules'));

		$modules = [];

		foreach ($db->setQuery($query)->loadAssocList() as $row) {
			$params = json_decode((string) $row['params'], true);

			$modules[] = new Module(
				(int) $row['id'],
				(int) $row['asset_id'],
				(string) $row['title'],
				(string) $row['position'],
				(int) $row['ordering'],
				(int) $row['published'],
				(string) $row['module'],
				(int) $row['client_id'],
				(string) $row['language'],
				(int) $row['showtitle'],
				\is_array($params) ? $params : [],
				$row
			);
		}

		$query = $db->getQuery(true)
			->select($db->quoteName(['moduleid', 'menuid']))
			->from($db->quoteName('#__modules_menu'));

		$assignments = [];

		foreach ($db->setQuery($query)->loadAssocList() as $row) {
			$assignments[(int) $row['moduleid']][] = (int) $row['menuid'];
		}

		return new Snapshot($languages, $items, $associations, $modules, $assignments);
	}

	public function assetRows(): array
	{
		$db    = $this->db;
		$query = $db->getQuery(true)
			->select($db->quoteName(['id', 'parent_id', 'lft', 'rgt', 'level']))
			->from($db->quoteName('#__assets'));

		$rows = [];

		foreach ($db->setQuery($query)->loadAssocList() as $row) {
			$rows[] = [
				'id'        => (int) $row['id'],
				'parent_id' => (int) $row['parent_id'],
				'lft'       => (int) $row['lft'],
				'rgt'       => (int) $row['rgt'],
				'level'     => (int) $row['level'],
			];
		}

		return $rows;
	}

	public function moduleAssetProblems(array $ids): array
	{
		if ($ids === []) {
			return [];
		}

		$db    = $this->db;
		$query = $db->getQuery(true)
			->select($db->quoteName(['m.id', 'm.asset_id', 'a.name', 'a.parent_id']))
			->select('(SELECT ' . $db->quoteName('id') . ' FROM ' . $db->quoteName('#__assets')
				. ' WHERE ' . $db->quoteName('name') . " = 'com_modules') AS " . $db->quoteName('component'))
			->from($db->quoteName('#__modules', 'm'))
			->join('LEFT', $db->quoteName('#__assets', 'a'), $db->quoteName('a.id') . ' = ' . $db->quoteName('m.asset_id'))
			->whereIn($db->quoteName('m.id'), $ids);

		$rows     = $db->setQuery($query)->loadAssocList();
		$problems = [];

		foreach ($rows as $row) {
			if ((string) $row['name'] !== 'com_modules.module.' . $row['id'] || (int) $row['parent_id'] !== (int) $row['component']) {
				$problems[] = \sprintf('module %d has asset %d "%s", not its own under com_modules', $row['id'], $row['asset_id'], $row['name']);
			}
		}

		if (\count($rows) !== \count($ids)) {
			$problems[] = 'not every new module is in #__modules';
		}

		return $problems;
	}

	public function apply(Plan $plan, \Closure $beforeCommit): array
	{
		$db  = $this->db;
		$ids = [];

		$db->transactionStart();

		try {
			foreach ($plan->updates as $id => $columns) {
				$this->update('#__modules', $id, $columns);

				if (isset($columns['title'])) {
					$name  = 'com_modules.module.' . $id;
					$title = $columns['title'];
					$query = $db->getQuery(true)
						->update($db->quoteName('#__assets'))
						->set($db->quoteName('title') . ' = :title')
						->where($db->quoteName('name') . ' = :name')
						->bind(':title', $title)
						->bind(':name', $name);

					$db->setQuery($query)->execute();
				}
			}

			if ($plan->newModules !== []) {
				$ids = $this->createModules($plan);
			}

			foreach ($plan->addPages as $id => $pages) {
				$this->assign($id, $pages);
			}

			foreach ($plan->orderings as $id => $ordering) {
				$this->update('#__modules', $id, ['ordering' => $ordering]);
			}

			foreach ($plan->homes as $language => $id) {
				// A language has one home page: Joomla's menu item table does the same.
				$query = $db->getQuery(true)
					->update($db->quoteName('#__menu'))
					->set($db->quoteName('home') . ' = 0')
					->where($db->quoteName('client_id') . ' = 0')
					->where($db->quoteName('home') . ' = 1')
					->where($db->quoteName('language') . ' = :language')
					->bind(':language', $language);

				$db->setQuery($query)->execute();

				$this->update('#__menu', $id, ['home' => 1]);
			}

			$problems = $beforeCommit($ids);

			if ($problems !== []) {
				throw new \RuntimeException('the check after writing failed: ' . implode('; ', $problems));
			}

			$db->transactionCommit();
		} catch (\Throwable $e) {
			$db->transactionRollback();

			throw new \RuntimeException('nothing was changed, ' . $e->getMessage(), 0, $e);
		}

		return $ids;
	}

	/**
	 * @return  array<string, int>
	 */
	private function createModules(Plan $plan): array
	{
		$db = $this->db;

		// Locked until the commit, as is the rest of what this transaction writes.
		$query = $db->getQuery(true)
			->select($db->quoteName(['id', 'rgt', 'level']))
			->from($db->quoteName('#__assets'))
			->where($db->quoteName('name') . ' = ' . $db->quote('com_modules'));

		$parent = $db->setQuery($query . ' FOR UPDATE')->loadAssoc();

		if (!\is_array($parent)) {
			throw new \RuntimeException('there is no com_modules asset');
		}

		$right = (int) $parent['rgt'];
		$width = 2 * \count($plan->newModules);

		// Make room right before the rgt of com_modules: everything from there moves up.
		$query = $db->getQuery(true)
			->update($db->quoteName('#__assets'))
			->set($db->quoteName('rgt') . ' = ' . $db->quoteName('rgt') . ' + :width')
			->where($db->quoteName('rgt') . ' >= :right')
			->bind(':width', $width, ParameterType::INTEGER)
			->bind(':right', $right, ParameterType::INTEGER);

		$db->setQuery($query)->execute();

		$query = $db->getQuery(true)
			->update($db->quoteName('#__assets'))
			->set($db->quoteName('lft') . ' = ' . $db->quoteName('lft') . ' + :width')
			->where($db->quoteName('lft') . ' > :right')
			->bind(':width', $width, ParameterType::INTEGER)
			->bind(':right', $right, ParameterType::INTEGER);

		$db->setQuery($query)->execute();

		$ids = [];
		$lft = $right;

		foreach ($plan->newModules as $new) {
			$module           = (object) $new->row;
			$module->ordering = $plan->newOrderings[$new->ref()] ?? 0;
			$module->asset_id = 0;

			$db->insertObject('#__modules', $module);

			$id = (int) $db->insertid();

			$asset = (object) [
				'parent_id' => (int) $parent['id'],
				'lft'       => $lft,
				'rgt'       => $lft + 1,
				'level'     => (int) $parent['level'] + 1,
				'name'      => 'com_modules.module.' . $id,
				'title'     => $new->title(),
				'rules'     => '{}',
			];

			$db->insertObject('#__assets', $asset);

			$this->update('#__modules', $id, ['asset_id' => (int) $db->insertid()]);
			$this->assign($id, $new->pages);

			$ids[$new->ref()] = $id;
			$lft += 2;
		}

		return $ids;
	}

	/**
	 * @param   list<int>  $pages
	 */
	private function assign(int $moduleId, array $pages): void
	{
		$db = $this->db;

		foreach (array_chunk($pages, 200) as $chunk) {
			$query = $db->getQuery(true)
				->insert($db->quoteName('#__modules_menu'))
				->columns($db->quoteName(['moduleid', 'menuid']));

			foreach ($chunk as $page) {
				$query->values($moduleId . ', ' . (int) $page);
			}

			$db->setQuery($query)->execute();
		}
	}

	/**
	 * @param   array<string, int|string>  $columns
	 */
	private function update(string $table, int $id, array $columns): void
	{
		$db    = $this->db;
		$query = $db->getQuery(true)
			->update($db->quoteName($table))
			->where($db->quoteName('id') . ' = :id')
			->bind(':id', $id, ParameterType::INTEGER);

		foreach ($columns as $column => $value) {
			$query->set($db->quoteName($column) . ' = ' . (\is_int($value) ? $value : $db->quote($value)));
		}

		$db->setQuery($query)->execute();
	}
}
