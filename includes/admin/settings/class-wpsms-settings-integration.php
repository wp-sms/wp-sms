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
            'contact_form7'        => __('Contact Form 7', 'wp-sms'),
            // pro tabs
            'pro_buddypress'       => __('BuddyPress', 'wp-sms'),
            'pro_woocommerce'      => __('WooCommerce', 'wp-sms'),
            'pro_gravity_forms'    => __('Gravity Forms', 'wp-sms'),
            'pro_quform'           => __('Quform', 'wp-sms'),
            'pro_edd'              => __('Easy Digital Downloads', 'wp-sms'),
            'pro_wp_job_manager'   => __('WP Job Manager', 'wp-sms'),
            'pro_awesome_support'  => __('Awesome Support', 'wp-sms'),
            'pro_ultimate_members' => __('Ultimate Member', 'wp-sms')
        ]);
    }



    
}
