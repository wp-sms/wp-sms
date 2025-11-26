<?php

namespace WP_SMS\Utils;

use WP_SMS\Option;

if (!defined('ABSPATH')) exit;

class PluginHelper
{
    /**
     * Check if a plugin is installed
     *
     * @param string $pluginSlug
     * @return bool
     */
    public static function isPluginInstalled($pluginSlug)
    {
        include_once(ABSPATH . 'wp-admin/includes/plugin.php');

        return is_plugin_active($pluginSlug);
    }
}
