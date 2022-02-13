<?php // phpcs:ignore WordPress.Files.FileName

namespace WPSmsTwoWay\Services\Logger;

use Backyard\Contracts\BootablePluginProviderInterface;
use Backyard\Exceptions\MissingConfigurationException;
use League\Container\ServiceProvider\AbstractServiceProvider;
use League\Container\ServiceProvider\BootableServiceProviderInterface;

/**
 * Registers the logging functionality into the plugin.
 */
class LoggerServiceProvider extends AbstractServiceProvider implements BootableServiceProviderInterface, BootablePluginProviderInterface
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
        WebhookRequestLogger::class,
        ExceptionLogger::class,
    ];

    /**
     * Check logger requirements.
     *
     * @return void
     * @throws MissingConfigurationException When the plugin configuration is missing the env, logs_path and logs_days specification.
     */
    public function boot()
    {
        $container = $this->getContainer();
        $logsPath  = $container->config('logs_path');
        $logsDays  = $container->config('logs_days');

        if (! $logsPath) {
            throw new MissingConfigurationException('Logger service provider requires "logs_path" to be configured.');
        }
        
        if (! $logsDays) {
            throw new MissingConfigurationException('Logger service provider requires "logs_days" to be configured.');
        }
    }

    /**
     * @return void
     */
    public function register()
    {
        $container = $this->getContainer();
        $logsPath  = $container->config('logs_path');
        $logsDays  = $container->config('logs_days');

        WebhookRequestLogger::init($container, $logsPath, $logsDays);
        ExceptionLogger::init($container, $logsPath, $logsDays);
    }

    /**
     * When the plugin is booted, init logger classes
     *
     * @return void
     */
    public function bootPlugin()
    {
    }
}
