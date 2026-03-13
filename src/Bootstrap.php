<?php

namespace WSms;

use WSms\Container\ServiceContainer;
use WSms\Container\CoreServiceProvider;
use WSms\Container\AdminServiceProvider;
use WSms\Container\AuthServiceProvider;
use WSms\Container\MfaServiceProvider;
use WSms\Container\SocialServiceProvider;
use WSms\Container\AuditServiceProvider;
use WSms\Container\RestServiceProvider;
use WSms\Service\Installation\InstallManager;

defined('ABSPATH') || exit;

/**
 * WSMS plugin bootstrap.
 *
 * Initializes the service container, registers lifecycle hooks,
 * and wires up service providers on `plugins_loaded`.
 *
 * @since 8.0
 */
class Bootstrap
{
    /** @var bool Whether the plugin has already been initialized. */
    private static bool $initialized = false;

    /** @var ServiceContainer|null Cached container instance. */
    private static ?ServiceContainer $container = null;

    /** @var array<class-string<\WSms\Container\ServiceProvider>> Service providers to register. */
    private static array $providers = [
        CoreServiceProvider::class,
        AdminServiceProvider::class,
        AuthServiceProvider::class,
        MfaServiceProvider::class,
        SocialServiceProvider::class,
        AuditServiceProvider::class,
        RestServiceProvider::class,
    ];

    /**
     * Entry point — called once from the main plugin file.
     *
     * Registers activation/deactivation hooks and defers full
     * setup until `plugins_loaded` (priority 10).
     *
     * @return void
     */
    public static function init(): void
    {
        if (self::$initialized) {
            return;
        }

        self::$initialized = true;

        self::registerLifecycleHooks();

        add_action('plugins_loaded', [__CLASS__, 'setup'], 10);
    }

    /**
     * Run on `plugins_loaded` — loads text domain, boots services,
     * and fires the `wp_sms_loaded` action.
     *
     * @return void
     */
    public static function setup(): void
    {
        add_action('init', [__CLASS__, 'loadTextdomain']);

        self::initializeServices();

        /**
         * Fires after WSMS core is fully loaded.
         *
         * Premium and third-party code should hook here.
         *
         * @since 8.0
         */
        do_action('wp_sms_loaded');
    }

    /**
     * Return the service container (creates it on first call).
     *
     * @return ServiceContainer
     */
    public static function container(): ServiceContainer
    {
        if (self::$container === null) {
            self::$container = ServiceContainer::getInstance();
        }

        return self::$container;
    }

    /**
     * Shorthand to fetch a service from the container.
     *
     * @param string $id Service identifier.
     * @return mixed|null
     */
    public static function get(string $id)
    {
        return self::container()->get($id);
    }

    /**
     * Load the plugin text domain for i18n.
     *
     * @return void
     */
    public static function loadTextdomain(): void
    {
        load_plugin_textdomain(
            'wp-sms',
            false,
            dirname(plugin_basename(WP_SMS_MAIN_FILE)) . '/public/languages'
        );
    }

    /**
     * Activation callback.
     *
     * @param bool $networkWide Whether the plugin is being activated network-wide.
     * @return void
     */
    public static function activate(bool $networkWide): void
    {
        InstallManager::activate($networkWide);
    }

    /**
     * Deactivation callback.
     *
     * @return void
     */
    public static function deactivate(): void
    {
        InstallManager::deactivate();
    }

    /**
     * Register activation and deactivation hooks with WordPress.
     *
     * @return void
     */
    private static function registerLifecycleHooks(): void
    {
        register_activation_hook(WP_SMS_MAIN_FILE, [__CLASS__, 'activate']);
        register_deactivation_hook(WP_SMS_MAIN_FILE, [__CLASS__, 'deactivate']);
    }

    /**
     * Instantiate, register, and boot all service providers.
     *
     * @return void
     */
    private static function initializeServices(): void
    {
        $container = self::container();

        $providers = [];
        foreach (self::$providers as $providerClass) {
            $provider = new $providerClass();
            $provider->register($container);
            $providers[] = $provider;
        }

        foreach ($providers as $provider) {
            $provider->boot($container);
        }
    }
}
