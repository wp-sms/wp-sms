<?php

namespace WSms\Branding;

use WSms\Container\ServiceContainer;
use WSms\Container\ServiceProvider;

defined('ABSPATH') || exit;

class BrandingServiceProvider implements ServiceProvider
{
    public function register(ServiceContainer $container): void
    {
        $container->register('branding.repository', fn() => new BrandingRepository());
    }

    public function boot(ServiceContainer $container): void
    {
        // No hooks needed — consumers pull from the repository.
    }
}
