<?php

/**
 * @package     LanguageMenuModules
 *
 * @copyright   Copyright (C) 2026 Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

namespace Yepr\Plugin\Task\LanguageMenuModules\Extension;

use Joomla\CMS\Cache\CacheControllerFactoryInterface;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Component\Scheduler\Administrator\Event\ExecuteTaskEvent;
use Joomla\Component\Scheduler\Administrator\Task\Status as TaskStatus;
use Joomla\Component\Scheduler\Administrator\Traits\TaskPluginTrait;
use Joomla\Event\SubscriberInterface;
use Yepr\Plugin\Task\LanguageMenuModules\Plan\Blueprint;
use Yepr\Plugin\Task\LanguageMenuModules\Plan\Planner;
use Yepr\Plugin\Task\LanguageMenuModules\Run\Creator;
use Yepr\Plugin\Task\LanguageMenuModules\Store\ModuleStore;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Offers the task type "Language menu modules: create" to the Task Scheduler.
 *
 * The work is in Creator; this class hands it the task's parameters and turns the
 * outcome into an exit code.
 */
final class LanguageMenuModules extends CMSPlugin implements SubscriberInterface
{
	use TaskPluginTrait;

	/**
	 * @var array<string, array<string, string>>
	 */
	protected const TASKS_MAP = [
		'languagemenumodules.create' => [
			'langConstPrefix' => 'PLG_TASK_LANGUAGEMENUMODULES_CREATE',
			'form'            => 'create',
			'method'          => 'create',
		],
	];

	/**
	 * The cache groups that hold modules, menus and languages.
	 */
	private const CACHE_GROUPS = ['com_modules', 'mod_menu', 'com_menus', 'com_languages', 'mod_languages', 'page'];

	/**
	 * @var boolean
	 */
	protected $autoloadLanguage = true;

	/**
	 * @param   array<string, mixed>  $config  The plugin's row, from PluginHelper::getPlugin().
	 */
	public function __construct(
		array $config,
		private readonly ModuleStore $store,
		private readonly CacheControllerFactoryInterface $caches
	) {
		parent::__construct($config);
	}

	/**
	 * @return  array<string, string>
	 */
	public static function getSubscribedEvents(): array
	{
		return [
			'onTaskOptionsList'    => 'advertiseRoutines',
			'onExecuteTask'        => 'standardRoutineHandler',
			'onContentPrepareForm' => 'enhanceTaskItemForm',
		];
	}

	/**
	 * The create routine.
	 *
	 * @return  integer  A TaskStatus exit code.
	 */
	protected function create(ExecuteTaskEvent $event): int
	{
		$params = $event->getArgument('params');
		$params = \is_object($params) ? $params : (object) [];

		$creator = new Creator(
			$this->store,
			new Planner(Blueprint::userGuide()),
			function (string $message, string $priority): void {
				$this->logTask($message, $priority);
			},
			function (): void {
				$this->cleanCache();
			}
		);

		try {
			$creator->run(
				(bool) ($params->dry_run ?? true),
				(bool) ($params->complete_pages ?? true),
				(bool) ($params->fix_source ?? true),
				(bool) ($params->set_homes ?? true)
			);
		} catch (\RuntimeException $e) {
			$this->logTask(\sprintf(Text::_('PLG_TASK_LANGUAGEMENUMODULES_LOG_FAILED'), $e->getMessage()), 'error');

			return TaskStatus::KNOCKOUT;
		}

		return TaskStatus::OK;
	}

	/**
	 * The site's cache of modules, menus and languages, so the new modules show at once.
	 */
	private function cleanCache(): void
	{
		foreach (self::CACHE_GROUPS as $group) {
			$this->caches->createCacheController('callback', [
				'defaultgroup' => $group,
				'cachebase'    => JPATH_SITE . '/cache',
			])->clean();
		}
	}
}
