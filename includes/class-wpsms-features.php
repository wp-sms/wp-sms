<?php

namespace WP_SMS;

if (!defined('ABSPATH')) {
    exit;
} // Exit if accessed directly

class Features
{
    /**
     * WP_SMS_Features constructor.
     */
    public function __construct()
    {
        $this->add_wpsms_user_profile_fields_group();

        if (wp_sms_get_option('international_mobile')) {
            add_action('wp_enqueue_scripts', array($this, 'load_international_input'), 999999);
            add_action('admin_enqueue_scripts', array($this, 'load_international_input'), 999999);
            add_action('login_enqueue_scripts', array($this, 'load_international_input'), 999999);
        }
    }

    /**
     * Add WPSMS fields to user profile
     *
     * @return void
     */
    private function add_wpsms_user_profile_fields_group()
    {
        $renderFields = function ($user) {
            $fields = apply_filters('wp_sms_user_profile_fields', [], $user->ID);
            if (empty($fields)) {
                return;
            }

            $args = [
                'fields' => $fields,
            ];

            echo Helper::loadTemplate('admin/user-profile-fields.php', $args); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        };

        add_action('show_user_profile', $renderFields);
        add_action('edit_user_profile', $renderFields);
    }

    /**
     * Register the JavaScript for the admin area.
     *
     * @since    1.2.0
     */
    public function load_international_input()
    {
        //Register IntelTelInput Assets
        wp_enqueue_style('wpsms-intel-tel-input', WP_SMS_URL . 'assets/css/intlTelInput.min.css', true, '24.5.0');
        wp_enqueue_script('wpsms-intel-tel-input', WP_SMS_URL . 'assets/js/intel/intlTelInput.min.js', array('jquery'), '24.5.0', true);
        wp_enqueue_script('wpsms-intel-script', WP_SMS_URL . 'assets/js/intel/intel-script.js', true, WP_SMS_VERSION, true);

        // Localize the IntelTelInput
        $tel_intel_vars             = array();
        $only_countries_option      = Option::getOption('international_mobile_only_countries');
        $preferred_countries_option = Option::getOption('international_mobile_preferred_countries');

        if ($only_countries_option) {
             $tel_intel_vars['only_countries'] = $only_countries_option;
        } else {
            $tel_intel_vars['only_countries'] = '';
        }

        if ($preferred_countries_option) {
            $tel_intel_vars['preferred_countries'] = $preferred_countries_option;
        } else {
            $tel_intel_vars['preferred_countries'] = '';
        }

        $tel_intel_vars['util_js'] = WP_SMS_URL . 'assets/js/intel/utils.js';

        $tel_intel_vars['mobile_field_id']        = Helper::getWooCommerceCheckoutMobileField();
        $tel_intel_vars['add_mobile_field']       = Option::getOption('add_mobile_field');
        $tel_intel_vars['wc_ship_to_destination'] = get_option('woocommerce_ship_to_destination');

        wp_localize_script('wpsms-intel-script', 'wp_sms_intel_tel_input', $tel_intel_vars);
    }
}

new Features();
