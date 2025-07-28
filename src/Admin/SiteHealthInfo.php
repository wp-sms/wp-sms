<?php

namespace WP_SMS\Admin;

use WP_SMS\Utils\OptionUtil;
use WP_SMS\Option;
use WPSmsWooPro\Core\Helper as WooProHelper;

class SiteHealthInfo
{
    const DEBUG_INFO_SLUG = 'wp_sms';

    public function register()
    {
        add_filter('debug_information', array($this, 'addSmsPluginInfo'));
    }

    public function addSmsPluginInfo($info)
    {
        $info[self::DEBUG_INFO_SLUG] = array(
            'label'       => esc_html__('WP SMS', 'wp-sms'),
            'description' => esc_html__('This section provides debug information about your WP SMS plugin settings.', 'wp-sms'),
            'fields'      => $this->getSmsSettings(),
        );

        return $info;
    }

    protected function getSmsSettings()
    {
        $settings = array();

        $yesNo = function ($value) {
            return $value ? __('Enabled', 'wp-sms') : __('Disabled', 'wp-sms');
        };

        $raw = function ($key, $default = null) {
            return OptionUtil::get($key, $default);
        };

        $woo = function ($key, $default = null) {
            return \WPSmsWooPro\Core\Helper::getOption($key, $default);
        };

        $toCamelCase = function ($string) {
            return ucwords(str_replace('_', ' ', $string));
        };


        $settings['plugin_version'] = array(
            'label' => __('Plugin Version', 'wp-sms'),
            'value' => defined('WP_SMS_VERSION') ? WP_SMS_VERSION : 'N/A',
        );

        $settings['db_version'] = array(
            'label' => __('Database Version', 'wp-sms'),
            'value' => OptionUtil::getOptionGroup('db', 'version', '0.0.0'),
        );

        $settings['mobile_field_source'] = array(
            'label' => __('Mobile Number Field Source', 'wp-sms'),
            'value' => $toCamelCase($raw('add_mobile_field', 'Not Set')),
        );

        $settings['mobile_field_mandatory'] = array(
            'label' => __('Mobile Field Mandatory Status', 'wp-sms'),
            'value' => $raw('optional_mobile_field') === '0' ? 'Required' : 'Optional',
        );

        $settings['international_number_input'] = array(
            'label' => __('International Number Input', 'wp-sms'),
            'value' => $yesNo($raw('international_mobile')),
        );

        $settings['only_countries'] = array(
            'label' => __('Only Countries', 'wp-sms'),
            'value' => implode(', ', (array)$raw('international_mobile_only_countries')) ?: 'Not Set',
        );

        $settings['preferred_countries'] = array(
            'label' => __('Preferred Countries', 'wp-sms'),
            'value' => implode(', ', (array)$raw('international_mobile_preferred_countries')) ?: 'Not Set',
        );

        $settings['gateway_name'] = array(
            'label' => __('SMS Gateway Choose the Gateway', 'wp-sms'),
            'value' => $toCamelCase($raw('gateway_name', 'Not Configured')),
        );

        $settings['gateway_status'] = array(
            'label' => __('SMS Gateway Status', 'wp-sms'),
            'value' => $raw('gateway_name') ? 'Configured' : 'Not Configured',
        );

        $settings['gateway_credit'] = array(
            'label' => __('SMS Gateway Balance / Credit', 'wp-sms'),
            'value' => get_option('wp_sms_credit') ?: 'Not Available',
        );

        $settings['incoming_message'] = array(
            'label' => __('SMS Gateway Incoming Message', 'wp-sms'),
            'value' => $yesNo($raw('new_incoming_sms_webhook')),
        );

        $settings['send_bulk_sms'] = array(
            'label' => __('SMS Gateway Send Bulk SMS', 'wp-sms'),
            'value' => $yesNo($raw('bulk_sms_feature_enabled')),
        );

        $settings['send_mms'] = array(
            'label' => __('SMS Gateway Send MMS', 'wp-sms'),
            'value' => $yesNo($raw('mms_feature_enabled')),
        );

        $settings['delivery_method'] = array(
            'label' => __('SMS Gateway Delivery Method', 'wp-sms'),
            'value' => $toCamelCase($raw('sms_delivery_method', 'Not Set')),
        );

        $settings['unicode_messaging'] = array(
            'label' => __('SMS Gateway Unicode Messaging', 'wp-sms'),
            'value' => $yesNo($raw('send_unicode')),
        );

        $settings['number_formatting'] = array(
            'label' => __('SMS Gateway Number Formatting', 'wp-sms'),
            'value' => $yesNo($raw('clean_numbers')),
        );

        $settings['restrict_to_local'] = array(
            'label' => __('SMS Gateway Restrict to Local Numbers', 'wp-sms'),
            'value' => $yesNo($raw('send_only_local_numbers')),
        );

        $settings['group_visibility'] = array(
            'label' => __('SMS Newsletter Group Visibility in Form', 'wp-sms'),
            'value' => $yesNo($raw('newsletter_form_groups')),
        );

        $settings['group_selection'] = array(
            'label' => __('SMS Newsletter Group Selection', 'wp-sms'),
            'value' => $yesNo($raw('newsletter_form_multiple_select')),
        );

        $settings['subscription_confirmation'] = array(
            'label' => __('SMS Newsletter Subscription Confirmation', 'wp-sms'),
            'value' => $yesNo($raw('newsletter_form_verify')),
        );

        $settings['message_button'] = array(
            'label' => __('Message Button Status', 'wp-sms'),
            'value' => $yesNo($raw('chatbox_message_button')),
        );

        $settings['performance_reports'] = array(
            'label' => __('SMS Performance Reports', 'wp-sms'),
            'value' => $yesNo($raw('report_wpsms_statistics')),
        );

        $settings['shorten_urls'] = array(
            'label' => __('Shorten URLs', 'wp-sms'),
            'value' => $yesNo($raw('short_url_status')),
        );

        $settings['recaptcha'] = array(
            'label' => __('Google reCAPTCHA Integration', 'wp-sms'),
            'value' => $yesNo($raw('g_recaptcha_status')),
        );

        $settings['login_with_sms'] = array(
            'label' => __('Login With SMS', 'wp-sms'),
            'value' => $yesNo(Option::getOption('login_sms')),
        );

        $settings['two_factor'] = array(
            'label' => __('Two-Factor Authentication with SMS', 'wp-sms'),
            'value' => $yesNo(Option::getOption('mobile_verify')),
        );

        $settings['cf7_metabox'] = array(
            'label' => __('Contact Form 7 Metabox', 'wp-sms'),
            'value' => $yesNo($raw('cf7_metabox')),
        );

        // WooCommerce Pro Add-ons
        $wooProFields = array(
            'cart_abandonment_recovery_status'        => __('WooPro: Cart Abandonment Recovery', 'wp-sms'),
            'cart_abandonment_threshold'              => __('WooPro: Cart abandonment threshold', 'wp-sms'),
            'cart_overwrite_number_during_checkout'   => __('WooPro: Cart abandonment Overwrite mobile number', 'wp-sms'),
            'cart_create_coupon'                      => __('WooPro: Cart abandonment Create coupon', 'wp-sms'),
            'cart_abandonment_send_sms_time_interval' => __('WooPro: Cart abandonment Send sms after', 'wp-sms'),
            'login_with_sms_status'                   => __('WooPro: Show Button in Login Page', 'wp-sms'),
            'login_with_sms_forgot_status'            => __('WooPro: Show Button in Forgot Password Page', 'wp-sms'),
            'reset_password_status'                   => __('WooPro: Enable SMS Password Reset', 'wp-sms'),
            'checkout_confirmation_checkbox_enabled'  => __('WooPro: Confirmation Checkbox', 'wp-sms'),
            'checkout_mobile_verification_enabled'    => __('WooPro: Enable Mobile Verification', 'wp-sms'),
        );

        foreach ($wooProFields as $key => $label) {
            $raw   = $woo($key);
            $value = '';

            if ($key === 'cart_overwrite_number_during_checkout') {
                $value = ($raw === 'skip')
                    ? __('Do not update', 'wp-sms')
                    : __('Update phone number', 'wp-sms');
            } elseif (in_array($key, array('cart_abandonment_threshold', 'cart_abandonment_send_sms_time_interval'), true)) {
                if (is_array($raw)) {
                    $parts = array();
                    if (!empty($raw['days']) && $raw['days'] !== '0') {
                        $parts[] = $raw['days'] . ' ' . _n('day', 'days', (int)$raw['days'], 'wp-sms');
                    }
                    if (!empty($raw['hours']) && $raw['hours'] !== '0') {
                        $parts[] = $raw['hours'] . ' ' . _n('hour', 'hours', (int)$raw['hours'], 'wp-sms');
                    }
                    if (!empty($raw['minutes']) && $raw['minutes'] !== '0') {
                        $parts[] = $raw['minutes'] . ' ' . _n('minute', 'minutes', (int)$raw['minutes'], 'wp-sms');
                    }
                    $value = $parts ? implode(', ', $parts) : 'Not Set';
                } else {
                    $value = 'Not Set';
                }
            } elseif (in_array($raw, array(true, '1', 1, 'yes'), true)) {
                $value = __('Enabled', 'wp-sms');
            } elseif (in_array($raw, array(false, '0', 0, 'no'), true)) {
                $value = __('Disabled', 'wp-sms');
            } elseif ($raw === null || $raw === '') {
                $value = __('Not Set', 'wp-sms');
            } else {
                $value = (string)$raw;
            }

            $settings['woo_' . $key] = array(
                'label' => $label,
                'value' => $value,
            );
        }


        return $settings;
    }
}
