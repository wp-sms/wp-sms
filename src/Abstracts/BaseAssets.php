<?php

namespace WP_SMS\Abstracts;

if (!defined('ABSPATH')) exit;

abstract class BaseAssets
{
    /**
     * Handle prefix for scripts/styles.
     *
     * @var string
     */
    protected $prefix = 'wp-sms';

    /**
     * Context identifier (e.g. admin, frontend, dashboard).
     *
     * @var string
     */
    protected $context = '';

    /**
     * Current asset handle being processed.
     *
     * @var string
     */
    protected $assetHandle = '';

    /**
     * Asset directory relative to plugin root.
     *
     * @var string
     */
    protected $assetDir = 'public';

    /**
     * Plugin URL.
     *
     * @var string
     */
    protected $pluginUrl;

    /**
     * Plugin directory path.
     *
     * @var string
     */
    protected $pluginDir;

    /**
     * Asset version.
     *
     * @var string
     */
    protected $assetVersion;

    /**
     * Set the asset directory.
     *
     * @param string $dir
     * @return void
     */
    protected function setAssetDir(string $dir)
    {
        $this->assetDir = $dir;
    }

    /**
     * Get the asset directory.
     *
     * @return string
     */
    public function getAssetDir()
    {
        return $this->assetDir;
    }

    /**
     * Set the context.
     *
     * @param string $context
     * @return void
     */
    protected function setContext(string $context)
    {
        $this->context = $context;
    }

    /**
     * Get the context.
     *
     * @return string
     */
    public function getContext()
    {
        return $this->context;
    }

    /**
     * Set the handle prefix.
     *
     * @param string $prefix
     * @return void
     */
    protected function setPrefix(string $prefix)
    {
        $this->prefix = $prefix;
    }

    /**
     * Get the handle prefix.
     *
     * @return string
     */
    public function getPrefix()
    {
        return $this->prefix;
    }

    /**
     * Get a namespaced asset handle.
     *
     * @param string $suffix Optional suffix to append.
     * @return string Handle in the format {prefix}-{context}[-{suffix}]
     */
    protected function getAssetHandle(string $suffix = '')
    {
        $handle = $this->prefix . '-' . $this->context;

        if ($suffix) {
            $handle .= '-' . $suffix;
        }

        return $handle;
    }

    /**
     * Get the version string for cache busting.
     *
     * @param string|false $version Optional specific version.
     * @return string
     */
    protected function getVersion($version = false)
    {
        if ($version) {
            return $version;
        }

        return $this->assetVersion;
    }

    /**
     * Get URL or filesystem path for an asset file.
     *
     * @param string $fileName The filename relative to the asset directory.
     * @param bool $relativePath If true, returns filesystem path instead of URL.
     * @return string
     */
    protected function getUrl(string $fileName, bool $relativePath = false)
    {
        if ($relativePath) {
            return $this->pluginDir . $this->assetDir . '/' . $fileName;
        }

        return $this->pluginUrl . $this->assetDir . '/' . $fileName;
    }

    /**
     * Get base localization data shared across contexts.
     *
     * @param string $hook The current admin page hook.
     * @return array
     */
    protected function getLocalizedData(string $hook = '')
    {
        return [
            'ajaxUrl'  => admin_url('admin-ajax.php'),
            'adminUrl' => admin_url(),
            'nonce'    => wp_create_nonce('wp_rest'),
            'version'  => $this->assetVersion,
        ];
    }

    /**
     * Constructor — must be implemented by child classes.
     */
    abstract public function __construct();

    /**
     * Enqueue styles.
     *
     * @return void
     */
    abstract public function styles();

    /**
     * Enqueue scripts.
     *
     * @param string $hook The current admin page hook.
     * @return void
     */
    abstract public function scripts(string $hook = '');
}
