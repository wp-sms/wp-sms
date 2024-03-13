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
            // pro tabs
            'pro_buddypress'       => esc_html__('BuddyPress', 'wp-sms'),
            'pro_woocommerce'      => esc_html__('WooCommerce', 'wp-sms'),
            'pro_gravity_forms'    => esc_html__('Gravity Forms', 'wp-sms'),
            'pro_quform'           => esc_html__('Quform', 'wp-sms'),
            'pro_edd'              => esc_html__('Easy Digital Downloads', 'wp-sms'),
            'pro_wp_job_manager'   => esc_html__('WP Job Manager', 'wp-sms'),
            'pro_awesome_support'  => esc_html__('Awesome Support', 'wp-sms'),
            'pro_ultimate_members' => esc_html__('Ultimate Member', 'wp-sms')
        ]);
    }
}
