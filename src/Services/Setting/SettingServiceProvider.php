<?php

namespace WPSmsTwoWay\Services\Setting;

use Backyard\Contracts\BootablePluginProviderInterface;
use WPSmsTwoWay\Container\ServiceProvider;

/**
 * Class SettingServiceProvider
 * @package WPSmsTwoWay\Setting
 */
class SettingServiceProvider extends ServiceProvider implements BootablePluginProviderInterface
{
    /**
     * The provided array is a way to let the container
     * know that a service is provided by this service
     * provider. Every service that is registered via
     * this service provider must have an alias added
     * to this array or it will be ignored.
     *
     * @var array
     */
    protected $provides = [
        AdminMenuManager::class,
        InboxPage::class,
    ];

    /**
     * Register the settings functionality within the plugin's container.
     *
     * @return void
     */
    public function register()
    {
        $this->_bindAdminMenuManager();

        $this->getContainer()
            ->share(InboxPage::class)
            ->addMethodCall('prepare_items');
    }

    public function _bindAdminMenuManager()
    {
        $application = $this->getContainer();
        $application
            ->add(AdminMenuManager::class)
            ->addArgument($application);
    }

    /**
     * Register methods within the plugin container after the plugins_loaded hook.
     * Load notices via the hook.
     *
     * @return void
     */
    public function bootPlugin()
    {
        $this->getContainer()->get(AdminMenuManager::class);
    }
}
