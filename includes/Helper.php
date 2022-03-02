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

    /**
     * @return mixed|void|null
     */
    public static function getUserMobileFieldName()
    {
        return apply_filters('wp_sms_user_mobile_field', 'mobile');
    }

    /**
     * @param $userId
     * @return mixed
     */
    public static function getUserMobileNumberByUserId($userId)
    {
        // get from the user meta
        $mobileNumber = get_user_meta($userId, self::getUserMobileFieldName(), true);

        return apply_filters('wp_sms_user_mobile_number', $mobileNumber, $userId);
    }

    /**
     * Get all Fully Qualified Class Names existing in a directory
     *
     * @see https://stackoverflow.com/a/27440555
     * @param string $path
     * @return array
     */
    public static function findAllClassesInDir(string $path): array
    {
        $fqcns = [];

        if (!is_dir($path)) {
            return $fqcns;
        }

        $allFiles = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path));
        $phpFiles = new \RegexIterator($allFiles, '/\.php$/');
        foreach ($phpFiles as $phpFile) {
            $path = $phpFile->getRealPath();
            $content = file_get_contents($path);
            $tokens = token_get_all($content);
            $namespace = '';
            for ($index = 0; isset($tokens[$index]); $index++) {
                if (!isset($tokens[$index][0])) {
                    continue;
                }
                if (T_NAMESPACE === $tokens[$index][0]) {
                    $index += 2; // Skip namespace keyword and whitespace
                    while (isset($tokens[$index]) && is_array($tokens[$index])) {
                        $namespace .= $tokens[$index++][1];
                    }
                }
                if (T_CLASS === $tokens[$index][0] && T_WHITESPACE === $tokens[$index + 1][0] && T_STRING === $tokens[$index + 2][0]) {
                    $index += 2; // Skip class keyword and whitespace
                    $fqcns[$path] = $namespace.'\\'.$tokens[$index][1];
                    break;
                }
            }
        }
        return $fqcns;
    }
}
