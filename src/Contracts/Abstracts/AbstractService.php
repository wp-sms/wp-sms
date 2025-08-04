<?php

namespace WP_SMS\Contracts\Abstracts;

use WP_SMS\Contracts\Interfaces\ServiceInterface;
use WP_SMS\Contracts\Interfaces\HasRestEndpointsInterface;
use WP_SMS\Contracts\Interfaces\HasShortcodesInterface;
use WP_SMS\Contracts\Interfaces\HasFiltersInterface;
use WP_SMS\Contracts\Interfaces\HasCronJobsInterface;

abstract class AbstractService implements ServiceInterface
{
    final public function init(): void
    {
        try {
            $this->boot();

            if ($this instanceof HasFiltersInterface) {
                $this->registerHooks();
            }

            if ($this instanceof HasRestEndpointsInterface) {
                add_action('rest_api_init', [$this, 'registerRestRoutes']);
            }

            if ($this instanceof HasShortcodesInterface) {
                $this->registerShortcodes();
            }

        } catch (\Throwable $e) {
            $this->logError($e);
        }
    }

    abstract protected function boot(): void;

    abstract public function getSlug(): string;

    protected function logError(\Throwable $e): void
    {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf(
                '[WP_SMS] Service "%s" failed to initialize: %s',
                static::class,
                $e->getMessage()
            ));
        }
    }
}
