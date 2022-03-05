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
     * @param $roleId
     * @return array
     */
    public static function getUsersMobileNumbers($roleId = false)
    {
        $mobileFieldKey = self::getUserMobileFieldName();
        $args           = array(
            'meta_query'  => array(
                array(
                    'key'     => $mobileFieldKey,
                    'value'   => '',
                    'compare' => '!=',
                ),
            ),
            'count_total' => 'false'
        );

        if ($roleId) {
            $args['role'] = $roleId;
        }

        $users         = get_users($args);
        $mobileNumbers = [];

        foreach ($users as $user) {
            if (isset($user->$mobileFieldKey)) {
                $mobileNumbers[] = $user->$mobileFieldKey;
            }
        }

        return $mobileNumbers;
    }

    /**
     * @param $message
     * @return array|string|string[]|null
     */
    public static function makeUrlsShorterByMessage($message)
    {
        $regex = "/(http|https)\:\/\/[a-zA-Z0-9\-\.]+\.[a-zA-Z]{2,3}(\/\S*)?/";

        return preg_replace_callback($regex, function ($url) {
            return wp_sms_shorturl($url[0]);
        }, $message);
    }
}
