<?php // phpcs:ignore WordPress.Files.FileName

namespace WPSmsTwoWay\Services\Command;

use Backyard\Contracts\BootablePluginProviderInterface;
use Backyard\Exceptions\MissingConfigurationException;
use League\Container\ServiceProvider\AbstractServiceProvider;
use League\Container\ServiceProvider\BootableServiceProviderInterface;

class CommandServiceProvider extends AbstractServiceProvider implements BootableServiceProviderInterface, BootablePluginProviderInterface
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
    ];


    /**
     * @return void
     */
    public function boot()
    {
    }


    /**
     * @return void
     */
    public function register()
    {
    }

    /**
     * When the plugin is booted, register register the command post type.
     *
     * @return void
     */
    public function bootPlugin()
    {
        CommandPostTypeManager::register();
    }
}
