<?php

\defined('_JEXEC') or die;

use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Joomla\Event\DispatcherInterface;
use Joomla\Plugin\System\ExtraVote\Extension\ExtraVote;

return new class implements ServiceProviderInterface {

	/**
	 * Registers the service provider with a DI container.
	 *
	 * @param   Container  $container  The DI container.
	 *
	 * @since   1.0.0
	 */
	public function register(Container $container): void
	{
		$container->set(PluginInterface::class,
			function (Container $container) {
				$plugin  = PluginHelper::getPlugin('system', 'extravote');
				$subject = $container->get(DispatcherInterface::class);

				$plugin = new ExtraVote($subject, (array) $plugin);
				$plugin->setApplication(Factory::getApplication());

				return $plugin;
			}
		);
	}
};
