<?php

namespace WSms\Container;

defined('ABSPATH') || exit;

/**
 * Contract for service providers.
 *
 * Providers are registered in Bootstrap::$providers and go through
 * two phases: register() (bind factories) then boot() (wire hooks).
 *
 * @since 8.0
 */
interface ServiceProvider
{
    /**
     * Bind factories and singletons into the container.
     *
     * @param ServiceContainer $container
     * @return void
     */
    public function register(ServiceContainer $container): void;

    /**
     * Boot services (resolve from container, add hooks, etc.).
     *
     * Called after all providers have been registered.
     *
     * @param ServiceContainer $container
     * @return void
     */
    public function boot(ServiceContainer $container): void;
}
