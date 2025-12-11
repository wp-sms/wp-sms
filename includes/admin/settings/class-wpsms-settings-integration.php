<?php

namespace WP_SMS;

use WP_SMS\Notification\NotificationFactory;

if (!defined('ABSPATH')) {
    exit;
} // No direct access allowed ;)

class SettingsIntegration extends Settings
{

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Gets settings tabs
     *
     * @return              array Tabs list
     * @since               2.0
     */
    public function get_tabs()
    {
        return apply_filters('wp_sms_registered_integration_tabs', [
            'contact_form7'        => esc_html__('Contact Form 7', 'wp-sms'),
        ]);
    }
}
