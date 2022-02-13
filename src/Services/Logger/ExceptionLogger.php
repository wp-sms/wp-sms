<?php

namespace WPSmsTwoWay\Services\Logger;

use WPSmsTwoWay\Services\Gateway\GatewayManager;
use Monolog\Logger;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Formatter\HtmlFormatter;

class ExceptionLogger extends Logger
{
    /**
     * Initialize exception logger
     *
     * @param Backyard\Plugin $container
     * @param string $logsPath
     * @param string $logsDays
     * @return void
     */
    public static function init($container, $logsPath, $logsDays)
    {
        $handler   = new RotatingFileHandler($container->basePath($logsPath).'/exceptions/exception.html', (int)$logsDays, Logger::INFO);
        $handler->setFormatter(new HtmlFormatter);

        $container->share(self::class)
            ->addArgument('default_channel')
            ->addMethodCall('pushHandler', [$handler]);
    }
}
