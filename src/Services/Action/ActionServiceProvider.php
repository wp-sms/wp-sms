<?php // phpcs:ignore WordPress.Files.FileName

namespace WPSmsTwoWay\Services\Action;

use Backyard\Contracts\BootablePluginProviderInterface;
use Backyard\Exceptions\MissingConfigurationException;
use League\Container\ServiceProvider\AbstractServiceProvider;
use League\Container\ServiceProvider\BootableServiceProviderInterface;

class ActionServiceProvider extends AbstractServiceProvider implements BootableServiceProviderInterface, BootablePluginProviderInterface
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
        ActionManager::class
    ];


    /**
     * @return void
     */
    public function boot()
    {
        $this->getContainer()
            ->share(ActionManager::class);
    }


    /**
     * @return void
     */
    public function register()
    {
    }

    /**
     * @return void
     */
    public function bootPlugin()
    {
    }
}
