<?php // phpcs:ignore WordPress.Files.FileName

namespace WPSmsTwoWay\Services\Webhook;

use League\Container\ServiceProvider\AbstractServiceProvider;
use League\Container\ServiceProvider\BootableServiceProviderInterface;
use Backyard\Contracts\BootablePluginProviderInterface;
use Backyard\RestApi\Route;

class WebhookServiceProvider extends AbstractServiceProvider implements BootableServiceProviderInterface, BootablePluginProviderInterface
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
        Webhook::class,
    ];

    /**
     * Boot the service provider when added to the container
     *
     * @throws Exception when the container does not have the Backyard\RestApi\Route definition
     * @return void
     */
    public function boot()
    {
        $container = $this->getContainer();

        if (! $container->has('route')) {
            throw new \Exception('Webhook service provider needs the container to have Backyard\RestApi\Route definition.');
        }
    }

    /**
     * Add the webhook functionality to the container
     *
     * @return void
     */
    public function register()
    {
        $container = $this->getContainer();

        $container->share(Webhook::class);
    }

    /**
     * @return void
     */
    public function bootPlugin()
    {
    }
}
