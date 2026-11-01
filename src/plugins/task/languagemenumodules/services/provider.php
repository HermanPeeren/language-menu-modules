<?php

/**
 * @package     LanguageMenuModules
 *
 * @copyright   Copyright (C) 2026 Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Cache\CacheControllerFactoryInterface;
use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Database\DatabaseInterface;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Yepr\Plugin\Task\LanguageMenuModules\Extension\LanguageMenuModules;
use Yepr\Plugin\Task\LanguageMenuModules\Store\DatabaseModuleStore;
use Yepr\Plugin\Task\LanguageMenuModules\Store\ModuleStore;

/**
 * The composition root: the only place the plugin's services are put together.
 *
 * Every service is shared and built on first use, and the plugin itself is a lazy
 * proxy (on PHP 8.4 and later): a page that runs no task builds none of it.
 */
return new class () implements ServiceProviderInterface {
	public function register(Container $container): void
	{
		$container->share(
			ModuleStore::class,
			static fn (Container $container): ModuleStore => new DatabaseModuleStore($container->get(DatabaseInterface::class))
		);

		$container->set(
			PluginInterface::class,
			$container->lazy(LanguageMenuModules::class, function (Container $container) {
				$plugin = new LanguageMenuModules(
					(array) PluginHelper::getPlugin('task', 'languagemenumodules'),
					$container->get(ModuleStore::class),
					$container->get(CacheControllerFactoryInterface::class)
				);
				$plugin->setApplication(Factory::getApplication());

				return $plugin;
			})
		);
	}
};
