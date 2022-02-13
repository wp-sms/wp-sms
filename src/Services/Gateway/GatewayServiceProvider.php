<?php // phpcs:ignore WordPress.Files.FileName

namespace WPSmsTwoWay\Services\Gateway;

use Backyard\Contracts\BootablePluginProviderInterface;
use Backyard\Exceptions\MissingConfigurationException;
use League\Container\ServiceProvider\AbstractServiceProvider;
use League\Container\ServiceProvider\BootableServiceProviderInterface;

class GatewayServiceProvider extends AbstractServiceProvider implements BootableServiceProviderInterface, BootablePluginProviderInterface
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
        GatewayManager::class,
    ];


    /**
     * @return void
     */
    public function boot()
    {
    }


    /**
     * Register the GatewayManager into the plugin
     *
     * @return void
     */
    public function register()
    {
        $this->getContainer()
            ->share(GatewayManager::class);
    }

    /**
     * Boot the main functionality on the init hook
     *
     * @return void
     */
    public function bootPlugin()
    {
        add_action('init', function () {
            $this->getContainer()->get(GatewayManager::class)->init();
        }, 1);
    }
}
