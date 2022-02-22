<?php

namespace WP_SMS;

class Helper
{
    public static function getPluginAssetUrl($assetName, $plugin = 'wp-sms')
    {
        return plugins_url($plugin) . "/assets/{$assetName}";
    }

    public static function getAssetPath($asset)
    {
        return plugin_dir_path(__FILE__) . $asset;
    }

    /**
     * @param $template
     * @param array $parameters
     * @return false|string|void
     */
    public static function loadTemplate($template, $parameters = [])
    {
        $templatePath = plugin_dir_path(__FILE__) . "templates/{$template}";

        if (file_exists($templatePath)) {
            ob_start();

            extract($parameters);
            require plugin_dir_path(__FILE__) . "templates/{$template}";

            return ob_get_clean();
        }
    }
}
