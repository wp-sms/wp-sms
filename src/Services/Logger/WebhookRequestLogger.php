<?php

namespace WPSmsTwoWay\Services\Logger;

use Monolog\Logger;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Processor\WebProcessor;
use WPSmsTwoWay\Services\Gateway\GatewayManager;

class WebhookRequestLogger extends Logger
{
    /**
     * Initialize incoming messages logger
     *
     * @param Backyard\Plugin $container
     * @param string $logsPath
     * @param string $logsDays
     * @return void
     */
    public static function init($container, $logsPath, $logsDays)
    {
        $rotatingHandler = new RotatingFileHandler($container->basePath($logsPath).'/incoming_requests/requests.log', (int)$logsDays, Logger::INFO);
        $webProcessor = new WebProcessor();

        $container->share(self::class)
            ->addArgument('incoming_webhook_request')
            ->addMethodCall('pushHandler', [$rotatingHandler])
            ->addMethodCall('pushProcessor', [$webProcessor])
            ->addMethodCall('pushProcessor', [[self::class, 'processor']]);
    }

    /**
     * Add incoming request log entry
     *
     * Just for convenience
     *
     * @param string $status
     * @param \WP_REST_Request $request
     * @param array $context
     * @return void
     */
    public function addEntry(string $status, \WP_REST_Request $request, array $context = [])
    {
        $context['request'] = $request;
        $this->info($status, $context);
    }

    /**
     * Add extra data to log records
     *
     * @param array $record
     * @return array $record
     */
    public static function processor($record)
    {
        $plugin = WPSmsTwoWay()->getPlugin();
        $request = $record['context']['request'] ?? null;

        if (!$request or !$request instanceof \WP_REST_Request) {
            return $record;
        }
        unset($record['context']['request']);

        $record['extra']['request_params']   = $request->get_params();
        $record['extra']['request_headers']  = $request->get_headers();
        $record['extra']['$_SERVER'] = $_SERVER;
        $record['context']['active_gateway'] = $plugin->get(GatewayManager::class)->getCurrentGateway()->name;

        return $record;
    }
}
