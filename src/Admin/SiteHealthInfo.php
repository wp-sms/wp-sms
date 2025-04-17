<?php

namespace WP_SMS\Admin;

/**
 * Class SiteHealthInfo
 *
 * @package WP_SMS\Admin
 */
class SiteHealthInfo
{
    /**
     * Slug for the WP SMS debug information section.
     */
    const DEBUG_INFO_SLUG = 'wp_sms';

    public function register()
    {
        add_filter('debug_information', [$this, 'addSmsInfo']);
    }

    /**
     * Add WP SMS debug information to the Site Health Info page.
     *
     * @param array $info
     *
     * @return array
     */
    public function addSmsInfo($info)
    {
        $allSettings = $this->getPluginSettings();

        $info[self::DEBUG_INFO_SLUG] = [
            'label'       => esc_html__('WP SMS', 'wp-sms'),
            'description' => esc_html__('This section contains debug information about your WP SMS settings to help you troubleshoot issues.', 'wp-sms'),
            'fields'      => $allSettings,
        ];

        return $info;
    }

    /**
     * Get plugin settings.
     *
     * @return array
     */
    public function getPluginSettings()
    {
        $settings = [
            // Basic plugin info
            'version' => [
                'label' => esc_html__('Version', 'wp-sms'),
                'value' => WP_SMS_VERSION,
            ],
        ];

        return $settings;
    }

}