<?php

namespace WSms\Cli;

defined('ABSPATH') || exit;

/**
 * Central registry of all WP-CLI commands provided by WSMS.
 *
 * To add a new command, append an entry to the array returned by {@see commands()}.
 * Each key is the full command name (e.g. "wsms seed") and the value is the
 * class-string of a command class compatible with `WP_CLI::add_command()` —
 * typically a class with `__invoke(array $args, array $assoc_args)`.
 *
 * The registry is consumed by {@see \WSms\Container\CliServiceProvider}, which
 * registers every listed command on boot when running under WP-CLI.
 */
class CommandRegistry
{
    /**
     * @return array<string, class-string> Command name => handler class.
     */
    public static function commands(): array
    {
        return [
            'wsms seed' => SeedCommand::class,
        ];
    }
}
