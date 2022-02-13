<?php // phpcs:ignore WordPress.Files.FileName

namespace WPSmsTwoWay\Services\RestApi;

use Backyard\Contracts\BootablePluginProviderInterface;
use League\Container\ServiceProvider\AbstractServiceProvider;
use League\Container\ServiceProvider\BootableServiceProviderInterface;

/**
 * Registers the REST API wrapper into the plugin
 */
class RestApiServiceProvider extends AbstractServiceProvider implements BootableServiceProviderInterface, BootablePluginProviderInterface
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
        'route'
    ];

    /**
     * @return void
     */
    public function boot()
    {
    }

    /**
     * Register the route service into the plugin
     *
     * @return void
     */
    public function register()
    {
        $this->getContainer()
            ->add('route', Route::class);
    }

    /**
     * Register a the route macro to the plugin
     *
     * @return void
     */
    public function bootPlugin()
    {
        $instance = $this;

        $this->getContainer()::macro(
            'route',
            function () use ($instance) {
                return $instance->getContainer()->get('route');
            }
        );
    }
}
