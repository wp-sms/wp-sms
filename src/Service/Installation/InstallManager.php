<?php

namespace WSms\Service\Installation;

use WSms\Auth\SettingsRepository;
use WSms\Branding\BrandingRepository;
use WSms\Database\CleanupScheduler;
use WSms\Database\Migrator;
use WSms\Migration\MigrationStateManager;
use WSms\Migration\MigrationStatus;
use WSms\Onboarding\OnboardingManager;
use WSms\PhoneRestriction\DatabaseUpdater;

defined('ABSPATH') || exit;

class InstallManager
{
    /**
     * Run on plugin activation.
     *
     * @param bool $networkWide Whether the plugin is being activated network-wide.
     */
    public static function activate(bool $networkWide): void
    {
        if ($networkWide && is_multisite()) {
            self::forEachSite([static::class, 'activateSingleSite']);
        } else {
            static::activateSingleSite();
        }
    }

    /**
     * Run on plugin deactivation.
     *
     * @param bool $networkDeactivating Whether the plugin is being deactivated network-wide.
     */
    public static function deactivate(bool $networkDeactivating = false): void
    {
        if ($networkDeactivating && is_multisite()) {
            self::forEachSite([static::class, 'deactivateSingleSite']);
        } else {
            static::deactivateSingleSite();
        }
    }

    /**
     * Create tables and defaults when a new site is added to the network.
     *
     * @param \WP_Site $newSite The new site object.
     */
    public static function onNewSiteCreated(\WP_Site $newSite): void
    {
        if (!static::isNetworkActive()) {
            return;
        }

        switch_to_blog((int) $newSite->blog_id);
        static::activateSingleSite();
        restore_current_blog();
    }

    /**
     * Drop tables and clean up when a site is removed from the network.
     *
     * @param \WP_Site $oldSite The site being removed.
     */
    public static function onSiteDeleted(\WP_Site $oldSite): void
    {
        if (!static::isNetworkActive()) {
            return;
        }

        switch_to_blog((int) $oldSite->blog_id);
        Migrator::dropTables();
        static::deactivateSingleSite();
        delete_option(SettingsRepository::OPTION_KEY);
        delete_option(BrandingRepository::OPTION_KEY);
        restore_current_blog();
    }

    /**
     * Activate the plugin on the current (switched) site.
     */
    protected static function activateSingleSite(): void
    {
        Migrator::createTables();

        update_option('wsms_flush_rewrite', '1', true);

        if (!as_has_scheduled_action(CleanupScheduler::HOOK_NAME, [], CleanupScheduler::AS_GROUP)) {
            as_schedule_recurring_action(time(), DAY_IN_SECONDS, CleanupScheduler::HOOK_NAME, [], CleanupScheduler::AS_GROUP);
        }

        add_option(BrandingRepository::OPTION_KEY, BrandingRepository::DEFAULTS, '', false);

        add_option(SettingsRepository::OPTION_KEY, SettingsRepository::DEFAULTS, '', false);

        add_option(OnboardingManager::OPTION_KEY, OnboardingManager::DEFAULTS, '', false);

        // Schedule redirect to onboarding wizard on first activation.
        set_transient('wsms_onboarding_redirect', true, 30);
    }

    /**
     * Deactivate the plugin on the current (switched) site.
     */
    protected static function deactivateSingleSite(): void
    {
        as_unschedule_all_actions(CleanupScheduler::HOOK_NAME, [], CleanupScheduler::AS_GROUP);
        as_unschedule_all_actions(DatabaseUpdater::CRON_HOOK, [], DatabaseUpdater::AS_GROUP);
        delete_option(BrandingRepository::OPTION_KEY);

        // Pause any running migration so it can resume on reactivation.
        $stateManager = new MigrationStateManager();
        $status = $stateManager->getStatus();
        if ($status === MigrationStatus::Running) {
            try {
                $stateManager->transitionStatus(MigrationStatus::Paused);
            } catch (\Throwable $e) {
                // Best effort — don't block deactivation.
            }
        }
    }

    /**
     * Check if the plugin is network-activated.
     *
     * Uses get_site_option() directly instead of is_plugin_active_for_network()
     * because the latter requires wp-admin/includes/plugin.php, which is not
     * loaded in all contexts where wp_initialize_site fires.
     */
    protected static function isNetworkActive(): bool
    {
        if (!is_multisite()) {
            return false;
        }

        $plugins = get_site_option('active_sitewide_plugins', []);
        return isset($plugins[plugin_basename(WP_SMS_MAIN_FILE)]);
    }

    /**
     * Run a callback on every site in the network.
     */
    private static function forEachSite(callable $callback): void
    {
        $siteIds = get_sites(['fields' => 'ids', 'number' => 0]);
        foreach ($siteIds as $siteId) {
            switch_to_blog($siteId);
            $callback();
            restore_current_blog();
        }
    }
}
