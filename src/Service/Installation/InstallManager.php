<?php

namespace WSms\Service\Installation;

defined('ABSPATH') || exit;

/**
 * Handles plugin activation and deactivation routines.
 *
 * @since 8.0
 */
class InstallManager
{
    /**
     * Run on plugin activation.
     *
     * Creates database tables, sets default options, schedules cron events, etc.
     *
     * @param bool $networkWide Whether the plugin is being activated network-wide.
     * @return void
     */
    public static function activate(bool $networkWide): void
    {
        // Run installation routines (create tables, set default options, etc.)
    }

    /**
     * Run on plugin deactivation.
     *
     * Clears scheduled cron events and transient caches.
     *
     * @return void
     */
    public static function deactivate(): void
    {
        // Cleanup on deactivation (clear cron jobs, etc.)
    }
}
