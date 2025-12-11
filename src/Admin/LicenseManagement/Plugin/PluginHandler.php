<?php

namespace WP_SMS\Admin\LicenseManagement\Plugin;

use Exception;

if (!defined('ABSPATH')) exit;

/**
 * Plugin handler for WP SMS add-ons operations.
 *
 * Manages add-on operations including download, installation,
 * activation, deactivation of add-ons based on user requests.
 */
class PluginHandler
{
    /**
     * Returns WP SMS add-on file path.
     *
     * @param string $pluginSlug
     *
     * @return string
     */
    public function getPluginFile($pluginSlug)
    {
        return "$pluginSlug/$pluginSlug.php";
    }

    /**
     * Checks if the WP SMS add-on is installed?
     *
     * @param string $pluginSlug
     *
     * @return bool
     */
    public function isPluginInstalled($pluginSlug)
    {
        return file_exists(WP_PLUGIN_DIR . DIRECTORY_SEPARATOR . $this->getPluginFile($pluginSlug));
    }

    /**
     * Checks if the WP SMS add-on is active?
     *
     * @param string $pluginSlug
     *
     * @return bool
     */
    public function isPluginActive($pluginSlug)
    {
        return $this->isPluginInstalled($pluginSlug) && is_plugin_active(plugin_basename($this->getPluginFile($pluginSlug)));
    }

    /**
     * Returns WP SMS add-on's full metadata.
     *
     * @param string $pluginSlug
     *
     * @return array
     *
     * @throws Exception
     */
    public function getPluginData($pluginSlug)
    {
        if (!$this->isPluginInstalled($pluginSlug)) {
            throw new Exception(__('Plugin is not installed!', 'wp-sms'));
        }

        return get_plugin_data(WP_PLUGIN_DIR . DIRECTORY_SEPARATOR . $this->getPluginFile($pluginSlug));
    }

    /**
     * Retrieves a list of installed wp-sms add-ons (plugins that starts with wp-sms- prefix).
     *
     * @return array Array of plugin data, keyed by plugin file name. See get_plugin_data().
     */
    public function getInstalledPlugins()
    {
        $result  = [];
        $plugins = get_plugins();

        foreach ($plugins as $pluginFile => $pluginData) {
            // If not wp-sms add-on, skip
            if (strpos($pluginFile, 'wp-sms-') !== 0) continue;

            $result[$pluginFile] = $pluginData;
        }

        return $result;
    }
}
