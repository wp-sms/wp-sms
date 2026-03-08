<?php

namespace WP_SMS\Components;

if (!defined('ABSPATH')) exit;

class Assets
{
    /**
     * The asset directory relative to plugin root.
     *
     * @var string
     */
    private static $assetDir = 'public';

    /**
     * Plugin URL.
     *
     * @var string
     */
    private static $pluginUrl;

    /**
     * Plugin directory path.
     *
     * @var string
     */
    private static $pluginDir;

    /**
     * Plugin version.
     *
     * @var string
     */
    private static $version;

    /**
     * Whether init() has been called.
     *
     * @var bool
     */
    private static $initialized = false;

    /**
     * Bootstrap static properties from plugin constants.
     *
     * @return void
     */
    public static function init()
    {
        if (self::$initialized) {
            return;
        }

        self::$pluginUrl = WP_SMS_URL;
        self::$pluginDir = WP_SMS_DIR;
        self::$version   = WP_SMS_VERSION;
        self::$initialized = true;
    }

    /**
     * Enqueue a script.
     *
     * @param string $handle The script handle.
     * @param string $src The source URL of the script.
     * @param array $deps An array of script dependencies.
     * @param array $localize An array of data to be localized.
     * @param bool $inFooter Whether to enqueue the script in the footer.
     * @return void
     * @example Assets::script('admin', 'dist/admin.js', ['jquery'], ['foo' => 'bar'], true);
     */
    public static function script($handle, $src, $deps = [], $localize = [], $inFooter = false)
    {
        $object = self::getObject($handle);
        $handle = self::getHandle($handle);

        wp_enqueue_script($handle, self::getSrc($src), $deps, self::getVersion(), $inFooter);

        if ($localize) {
            self::localize($handle, $object, $localize);
        }
    }

    /**
     * Register a script.
     *
     * @param string $handle The script handle.
     * @param string $src The source URL of the script.
     * @param array $deps An array of script dependencies.
     * @param string|null $version Optional. The version of the script. Defaults to plugin version.
     * @param bool $inFooter Whether to enqueue the script in the footer.
     * @return void
     * @example Assets::registerScript('chartjs', 'js/chart.min.js', [], '3.7.1');
     */
    public static function registerScript($handle, $src, $deps = [], $version = null, $inFooter = false)
    {
        $handle = self::getHandle($handle);

        if ($version === null) {
            $version = self::getVersion();
        }

        wp_register_script($handle, self::getSrc($src), $deps, $version, $inFooter);
    }

    /**
     * Enqueue a style.
     *
     * @param string $handle The style handle.
     * @param string $src The source URL of the style.
     * @param array $deps An array of style dependencies.
     * @param string $media The context which style needs to be loaded: all, print, or screen
     * @return void
     * @example Assets::style('admin', 'dist/admin.css', ['jquery'], 'all');
     */
    public static function style($handle, $src, $deps = [], $media = 'all')
    {
        wp_enqueue_style(self::getHandle($handle), self::getSrc($src), $deps, self::getVersion(), $media);
    }

    /**
     * Check if a script has been enqueued.
     *
     * @param string $handle The script handle (without prefix).
     * @return bool
     */
    public static function isScriptEnqueued($handle)
    {
        return wp_script_is(self::getHandle($handle), 'enqueued');
    }

    /**
     * Localize a script with data.
     *
     * @param string $handle The script handle (already prefixed or raw).
     * @param string $name The JavaScript object name.
     * @param array $data The data to localize.
     * @return void
     */
    public static function localize($handle, $name, $data)
    {
        $data = apply_filters("wp_sms_localize_{$handle}", $data);

        wp_localize_script($handle, $name, $data);
    }

    /**
     * Get the handle for the script/style.
     *
     * @param string $handle The script/style handle.
     * @return string
     */
    private static function getHandle($handle)
    {
        $handle = sprintf('wp-sms-%s', strtolower($handle));

        return apply_filters('wp_sms_assets_handle', $handle);
    }

    /**
     * Get the source URL for the script/style.
     *
     * @param string $src The source path relative to the asset directory.
     * @return string
     */
    private static function getSrc($src)
    {
        self::ensureInitialized();

        return self::$pluginUrl . self::$assetDir . '/' . $src;
    }

    /**
     * Get the filesystem path for an asset.
     *
     * @param string $src The source path relative to the asset directory.
     * @return string
     */
    public static function getFilePath($src)
    {
        self::ensureInitialized();

        return self::$pluginDir . self::$assetDir . '/' . $src;
    }

    /**
     * Check if an asset file exists on disk.
     *
     * @param string $src The source path relative to the asset directory.
     * @return bool
     */
    private static function fileExists($src)
    {
        return file_exists(self::getFilePath($src));
    }

    /**
     * Get the plugin version.
     *
     * @return string
     */
    private static function getVersion()
    {
        self::ensureInitialized();

        return self::$version;
    }

    /**
     * Ensure the class is initialized.
     *
     * @return void
     */
    private static function ensureInitialized()
    {
        if (!self::$initialized) {
            self::init();
        }
    }

    /**
     * Get the object name for script localization.
     *
     * @param string $handle The script handle.
     * @return string
     */
    private static function getObject($handle)
    {
        $parts          = explode('-', $handle);
        $camelCaseParts = array_map('ucfirst', $parts);

        return 'WP_Sms_' . implode('_', $camelCaseParts) . '_Object';
    }
}
