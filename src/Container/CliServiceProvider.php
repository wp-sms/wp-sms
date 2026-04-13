<?php

namespace WSms\Container;

use WP_CLI;
use WSms\Cli\CommandRegistry;

defined('ABSPATH') || exit;

/**
 * Registers every WP-CLI command listed in {@see CommandRegistry}.
 *
 * Does nothing outside WP-CLI requests, so adding commands has zero cost
 * on regular web traffic. New commands are added by appending to
 * `CommandRegistry::commands()` — this provider picks them up automatically.
 *
 * @since 8.0
 */
class CliServiceProvider implements ServiceProvider
{
    /**
     * {@inheritDoc}
     */
    public function register(ServiceContainer $container): void
    {
        // Commands resolve their own dependencies at invocation time; nothing
        // to bind into the container here.
    }

    /**
     * {@inheritDoc}
     */
    public function boot(ServiceContainer $container): void
    {
        if (! defined('WP_CLI') || ! WP_CLI) {
            return;
        }

        foreach (CommandRegistry::commands() as $name => $class) {
            WP_CLI::add_command($name, $class);
        }
    }
}
