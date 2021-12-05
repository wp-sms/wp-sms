<?php

namespace WP_SMS;

if (!defined('ABSPATH')) {
    exit;
} // No direct access allowed ;)

class Settings
{
    public $setting_name;
    public $options = array();

    public function __construct()
    {
        $this->setting_name = 'wpsms_settings';
        $this->get_settings();
        $this->options = get_option($this->setting_name);

        if (empty($this->options)) {
            update_option($this->setting_name, array());
        }

        add_action('admin_menu', array($this, 'add_settings_menu'), 11);

        if (isset($_GET['page']) and $_GET['page'] == 'wp-sms-settings' or isset($_POST['option_page']) and $_POST['option_page'] == 'wpsms_settings') {
            add_action('admin_init', array($this, 'register_settings'));
        }

        // Check License Code
        if (isset($_POST['submit']) and isset($_REQUEST['option_page']) and $_REQUEST['option_page'] == 'wpsms_settings' and strpos(wp_get_referer(), 'tab=licenses')) {
            add_filter('pre_update_option_' . $this->setting_name, array($this, 'check_license_key'), 10, 2);
        }

        add_filter('wp_sms_licenses_settings', array($this, 'modifyLicenseSettings'));
    }

    /**
     * Add WP SMS Professional Package admin page settings
     * */
    public function add_settings_menu()
    {
        add_submenu_page('wp-sms', __('Settings', 'wp-sms'), __('Settings', 'wp-sms'), 'wpsms_setting', 'wp-sms-settings', array(
            $this,
            'render_settings'
        ), 6);
    }

    /**
     * Gets saved settings from WP core
     *
     * @return          array
     * @since           2.0
     */
    public function get_settings()
    {
        $settings = get_option($this->setting_name);
        if (!$settings) {
            update_option($this->setting_name, array(
                'rest_api_status' => 1,
            ));
        }

        return apply_filters('wpsms_get_settings', $settings);
    }

    /**
     * Registers settings in WP core
     *
     * @return          void
     * @since           2.0
     */
    public function register_settings()
    {
        if (false == get_option($this->setting_name)) {
            add_option($this->setting_name);
        }

        foreach ($this->get_registered_settings() as $tab => $settings) {
            add_settings_section(
                'wpsms_settings_' . $tab,
                __return_null(),
                '__return_false',
                'wpsms_settings_' . $tab
            );

            if (empty($settings)) {
                return;
            }

            foreach ($settings as $option) {
                $name = isset($option['name']) ? $option['name'] : '';

                add_settings_field(
                    'wpsms_settings[' . $option['id'] . ']',
                    $name,
                    array($this, $option['type'] . '_callback'),
                    'wpsms_settings_' . $tab,
                    'wpsms_settings_' . $tab,
                    array(
                        'id'          => isset($option['id']) ? $option['id'] : null,
                        'desc'        => !empty($option['desc']) ? $option['desc'] : '',
                        'name'        => isset($option['name']) ? $option['name'] : null,
                        'after_input' => isset($option['after_input']) ? $option['after_input'] : null,
                        'section'     => $tab,
                        'size'        => isset($option['size']) ? $option['size'] : null,
                        'options'     => isset($option['options']) ? $option['options'] : '',
                        'std'         => isset($option['std']) ? $option['std'] : '',
                        'attributes'  => isset($option['attributes']) ? $option['attributes'] : [],
                    )
                );

                register_setting($this->setting_name, $this->setting_name, array($this, 'settings_sanitize'));
            }
        }
    }

    /**
     * Gets settings tabs
     *
     * @return              array Tabs list
     * @since               2.0
     */
    public function get_tabs()
    {
        $tabs = array(
            'general'       => __('General', 'wp-sms'),
            'gateway'       => __('Gateway', 'wp-sms'),
            'newsletter'    => __('SMS Newsletter', 'wp-sms'),
            'feature'       => __('Features', 'wp-sms'),
            'notifications' => __('Notifications', 'wp-sms'),
            'integration'   => __('Integration', 'wp-sms'),
            'licenses'      => __('Licenses', 'wp-sms'),
            'two_way'       => __('Two Way', 'wp-sms'),
        );

        return $tabs;
    }

    /**
     * Sanitizes and saves settings after submit
     *
     * @param array $input Settings input
     *
     * @return              array New settings
     * @since               2.0
     *
     */
    public function settings_sanitize($input = array())
    {
        if (empty($_POST['_wp_http_referer'])) {
            return $input;
        }

        parse_str($_POST['_wp_http_referer'], $referrer);

        $settings = $this->get_registered_settings();
        $tab      = isset($referrer['tab']) ? $referrer['tab'] : 'wp';

        $input = $input ? $input : array();
        $input = apply_filters('wpsms_settings_' . $tab . '_sanitize', $input);

        // Loop through each setting being saved and pass it through a sanitization filter
        foreach ($input as $key => $value) {

            // Get the setting type (checkbox, select, etc)
            $type = isset($settings[$tab][$key]['type']) ? $settings[$tab][$key]['type'] : false;

            if ($type) {
                // Field type specific filter
                $input[$key] = apply_filters('wpsms_settings_sanitize_' . $type, $value, $key);
            }

            // General filter
            $input[$key] = apply_filters('wpsms_settings_sanitize', $value, $key);
        }

        // Loop through the whitelist and unset any that are empty for the tab being saved
        if (!empty($settings[$tab])) {
            foreach ($settings[$tab] as $key => $value) {

                // settings used to have numeric keys, now they have keys that match the option ID. This ensures both methods work
                if (is_numeric($key)) {
                    $key = $value['id'];
                }

                if (empty($input[$key])) {
                    unset($this->options[$key]);
                }
            }
        }

        // Merge our new settings with the existing
        $output = array_merge($this->options, $input);

        add_settings_error('wpsms-notices', '', __('Settings updated', 'wp-sms'), 'updated');

        return $output;
    }

    /**
     * Get settings fields
     *
     * @return          array Fields
     * @since           2.0
     */
    public function get_registered_settings()
    {
        $options = array(
            'enable'  => __('Enable', 'wp-sms'),
            'disable' => __('Disable', 'wp-sms')
        );

        $settings = apply_filters('wp_sms_registered_settings', array(
            /**
             * General tab
             */
            'general'       => apply_filters('wp_sms_general_settings', array(
                'admin_title'         => array(
                    'id'   => 'admin_title',
                    'name' => __('Mobile', 'wp-sms'),
                    'type' => 'header'
                ),
                'admin_mobile_number' => array(
                    'id'   => 'admin_mobile_number',
                    'name' => __('Admin mobile number', 'wp-sms'),
                    'type' => 'text',
                    'desc' => __('Admin mobile number for get any sms notifications', 'wp-sms')
                ),
                'mobile_county_code'  => array(
                    'id'   => 'mobile_county_code',
                    'name' => __('Mobile country code', 'wp-sms'),
                    'type' => 'text',
                    'desc' => __('Enter your mobile country code for prefix numbers. For example if you enter +1 The final number will be +19999999999', 'wp-sms')
                ),
                'admin_title_privacy' => array(
                    'id'   => 'admin_title_privacy',
                    'name' => __('Privacy', 'wp-sms'),
                    'type' => 'header'
                ),
                'gdpr_compliance'     => array(
                    'id'      => 'gdpr_compliance',
                    'name'    => __('GDPR Enhancements', 'wp-sms'),
                    'type'    => 'checkbox',
                    'options' => $options,
                    'desc'    => __('Enable GDPR related features in this page. Read our GDPR documentation to learn more.', 'wp-sms'),
                ),
            )),

            /**
             * Gateway tab
             */
            'gateway'       => apply_filters('wp_sms_gateway_settings', array(
                // Gateway
                'gayeway_title'             => array(
                    'id'   => 'gayeway_title',
                    'name' => __('Gateway configuration', 'wp-sms'),
                    'type' => 'header'
                ),
                'gateway_name'              => array(
                    'id'      => 'gateway_name',
                    'name'    => __('Gateway name', 'wp-sms'),
                    'type'    => 'advancedselect',
                    'options' => Gateway::gateway(),
                    'desc'    => __('Please select your gateway.', 'wp-sms')
                ),
                'gateway_help'              => array(
                    'id'      => 'gateway_help',
                    'name'    => __('Gateway description', 'wp-sms'),
                    'type'    => 'html',
                    'options' => Gateway::help(),
                ),
                'gateway_username'          => array(
                    'id'   => 'gateway_username',
                    'name' => __('API username', 'wp-sms'),
                    'type' => 'text',
                    'desc' => __('Enter API username of gateway', 'wp-sms')
                ),
                'gateway_password'          => array(
                    'id'   => 'gateway_password',
                    'name' => __('API password', 'wp-sms'),
                    'type' => 'text',
                    'desc' => __('Enter API password of gateway', 'wp-sms')
                ),
                'gateway_sender_id'         => array(
                    'id'   => 'gateway_sender_id',
                    'name' => __('Sender number', 'wp-sms'),
                    'type' => 'text',
                    'std'  => Gateway::from(),
                    'desc' => __('Sender number or sender ID', 'wp-sms')
                ),
                'gateway_key'               => array(
                    'id'   => 'gateway_key',
                    'name' => __('API key', 'wp-sms'),
                    'type' => 'text',
                    'desc' => __('Enter API key of gateway', 'wp-sms')
                ),
                // Gateway status
                'gateway_status_title'      => array(
                    'id'   => 'gateway_status_title',
                    'name' => __('Gateway status', 'wp-sms'),
                    'type' => 'header'
                ),
                'account_credit'            => array(
                    'id'      => 'account_credit',
                    'name'    => __('Status', 'wp-sms'),
                    'type'    => 'html',
                    'options' => Gateway::status(),
                ),
                'account_response'          => array(
                    'id'      => 'account_response',
                    'name'    => __('Result request', 'wp-sms'),
                    'type'    => 'html',
                    'options' => Gateway::response(),
                ),
                'bulk_send'                 => array(
                    'id'      => 'bulk_send',
                    'name'    => __('Bulk send', 'wp-sms'),
                    'type'    => 'html',
                    'options' => Gateway::bulk_status(),
                ),
                // Account credit
                'account_credit_title'      => array(
                    'id'   => 'account_credit_title',
                    'name' => __('Account balance', 'wp-sms'),
                    'type' => 'header'
                ),
                'account_credit_in_menu'    => array(
                    'id'      => 'account_credit_in_menu',
                    'name'    => __('Show in admin menu', 'wp-sms'),
                    'type'    => 'checkbox',
                    'options' => $options,
                    'desc'    => __('Show your account credit in admin menu.', 'wp-sms')
                ),
                'account_credit_in_sendsms' => array(
                    'id'      => 'account_credit_in_sendsms',
                    'name'    => __('Show in send SMS page', 'wp-sms'),
                    'type'    => 'checkbox',
                    'options' => $options,
                    'desc'    => __('Show your account credit in send SMS page.', 'wp-sms')
                ),
                // Message header
                'message_title'             => array(
                    'id'   => 'message_title',
                    'name' => __('Message options', 'wp-sms'),
                    'type' => 'header'
                ),
                'send_unicode'              => array(
                    'id'      => 'send_unicode',
                    'name'    => __('Send as Unicode', 'wp-sms'),
                    'type'    => 'checkbox',
                    'options' => $options,
                    'desc'    => __('You can send SMS messages using Unicode for non-English characters (such as Persian, Arabic, Chinese or Cyrillic characters).', 'wp-sms')
                ),
                'clean_numbers'             => array(
                    'id'      => 'clean_numbers',
                    'name'    => __('Clean Numbers', 'wp-sms'),
                    'type'    => 'checkbox',
                    'options' => $options,
                    'desc'    => __('If you would like to remove space before sending to API, just enable this option.', 'wp-sms')
                ),
            )),

            /**
             * SMS Newsletter tab
             */
            'newsletter'    => apply_filters('wp_sms_newsletter_settings', array(
                // SMS Newsletter
                'newsletter_title'                => array(
                    'id'   => 'newsletter_title',
                    'name' => __('SMS Newsletter', 'wp-sms'),
                    'type' => 'header',
                ),
                'newsletter_form_groups'          => array(
                    'id'   => 'newsletter_form_groups',
                    'name' => __('Show Groups', 'wp-sms'),
                    'type' => 'checkbox',
                    'desc' => __('Enable showing Groups on Form.', 'wp-sms')
                ),
                'newsletter_form_verify'          => array(
                    'id'   => 'newsletter_form_verify',
                    'name' => __('Verify Subscriber', 'wp-sms'),
                    'type' => 'checkbox',
                    'desc' => __('Verified subscribe with the activation code', 'wp-sms')
                ),
                'welcome'                         => array(
                    'id'   => 'welcome',
                    'name' => __('Welcome SMS', 'wp-sms'),
                    'type' => 'header'
                ),
                'newsletter_form_welcome'         => array(
                    'id'   => 'newsletter_form_welcome',
                    'name' => __('Status', 'wp-sms'),
                    'type' => 'checkbox',
                    'desc' => __('Enable or Disable welcome SMS.', 'wp-sms')
                ),
                'newsletter_form_welcome_text'    => array(
                    'id'   => 'newsletter_form_welcome_text',
                    'name' => __('SMS text', 'wp-sms'),
                    'type' => 'textarea',
                    'desc' => sprintf(__('Subscribe name: %s, Subscribe mobile: %s', 'wp-sms'), '<code>%subscribe_name%</code>', '<code>%subscribe_mobile%</code>')
                ),
                'mobile_terms'                    => array(
                    'id'   => 'mobile_terms',
                    'name' => __('Mobile Number Terms', 'wp-sms'),
                    'type' => 'header'
                ),
                'mobile_terms_field_place_holder' => array(
                    'id'   => 'mobile_terms_field_place_holder',
                    'name' => __('Field Placeholder', 'wp-sms'),
                    'type' => 'text'
                ),
                'mobile_terms_minimum'            => array(
                    'id'   => 'mobile_terms_minimum',
                    'name' => __('Minimum number', 'wp-sms'),
                    'type' => 'number'
                ),
                'mobile_terms_maximum'            => array(
                    'id'   => 'mobile_terms_maximum',
                    'name' => __('Maximum number', 'wp-sms'),
                    'type' => 'number'
                ),
                //Style Setting
                'style'                           => array(
                    'id'   => 'style',
                    'name' => __('Style', 'wp-sms'),
                    'type' => 'header'
                ),
                'disable_style_in_front'          => array(
                    'id'   => 'disable_style_in_front',
                    'name' => __('Disable Frontend Style', 'wp-sms'),
                    'type' => 'checkbox',
                    'desc' => __('Disable loading Style from Frontend.', 'wp-sms')
                ),
            )),

            /**
             * Feature tab
             */
            'feature'       => apply_filters('wp_sms_feature_settings', array(
                'mobile_field'                             => array(
                    'id'   => 'mobile_field',
                    'name' => __('Mobile field', 'wp-sms'),
                    'type' => 'header'
                ),
                'add_mobile_field'                         => array(
                    'id'      => 'add_mobile_field',
                    'name'    => __('Add Mobile number field', 'wp-sms'),
                    'type'    => 'checkbox',
                    'options' => $options,
                    'desc'    => __('Add Mobile number to user profile and register form.', 'wp-sms')
                ),
                'international_mobile_title'               => array(
                    'id'   => 'international_mobile_title',
                    'name' => __('International Telephone Input', 'wp-sms'),
                    'type' => 'header'
                ),
                'international_mobile'                     => array(
                    'id'      => 'international_mobile',
                    'name'    => __('Enable for mobile fields', 'wp-sms'),
                    'type'    => 'checkbox',
                    'options' => $options,
                    'desc'    => __('Make mobile input fields in whole plugin to intel tel input.', 'wp-sms')
                ),
                'international_mobile_only_countries'      => array(
                    'id'      => 'international_mobile_only_countries',
                    'name'    => __('Only Countries', 'wp-sms'),
                    'type'    => 'countryselect',
                    'options' => $this->get_countries_list(),
                    'desc'    => __('In the dropdown, display only the countries you specify.', 'wp-sms')
                ),
                'international_mobile_preferred_countries' => array(
                    'id'      => 'international_mobile_preferred_countries',
                    'name'    => __('Preferred Countries', 'wp-sms'),
                    'type'    => 'countryselect',
                    'options' => $this->get_countries_list(),
                    'desc'    => __('Specify the countries to appear at the top of the list.', 'wp-sms')
                ),
                'international_mobile_auto_hide'           => array(
                    'id'      => 'international_mobile_auto_hide',
                    'name'    => __('Auto hide dial code', 'wp-sms'),
                    'type'    => 'checkbox',
                    'options' => $options,
                    'desc'    => __('If there is just a dial code in the input: remove it on blur or submit, and re-add it on focus.<br>Requires National mode to be deactivate', 'wp-sms')
                ),
                'international_mobile_national_mode'       => array(
                    'id'      => 'international_mobile_national_mode',
                    'name'    => __('National mode', 'wp-sms'),
                    'type'    => 'checkbox',
                    'options' => $options,
                    'desc'    => __('Allow users to enter national numbers (and not have to think about international dial codes).', 'wp-sms')
                ),
                'international_mobile_separate_dial_code'  => array(
                    'id'      => 'international_mobile_separate_dial_code',
                    'name'    => __('Separate dial code', 'wp-sms'),
                    'type'    => 'checkbox',
                    'options' => $options,
                    'desc'    => __('Display the country dial code next to the selected flag so it\'s not part of the typed number.<br>Note: this will disable National mode because technically we are dealing with international numbers, but with the dial code separated.', 'wp-sms')
                ),
                'rest_api'                                 => array(
                    'id'   => 'rest_api',
                    'name' => __('REST API', 'wp-sms'),
                    'type' => 'header'
                ),
                'rest_api_status'                          => array(
                    'id'      => 'rest_api_status',
                    'name'    => __('REST API status', 'wp-sms'),
                    'type'    => 'checkbox',
                    'options' => $options,
                    'desc'    => __('Add WP-SMS endpoints to the WP Rest API', 'wp-sms')
                ),
            )),

            /**
             * Notifications tab
             */
            'notifications' => apply_filters('wp_sms_notifications_settings', array(
                // Publish new post
                'notif_publish_new_post_title'            => array(
                    'id'   => 'notif_publish_new_post_title',
                    'name' => __('Published new posts', 'wp-sms'),
                    'type' => 'header'
                ),
                'notif_publish_new_post'                  => array(
                    'id'      => 'notif_publish_new_post',
                    'name'    => __('Status', 'wp-sms'),
                    'type'    => 'checkbox',
                    'options' => $options,
                    'desc'    => __('Send an SMS to subscribers When published new posts.', 'wp-sms')
                ),
                'notif_publish_new_post_type'             => array(
                    'id'      => 'notif_publish_new_post_type',
                    'name'    => __('Post Types', 'wp-sms'),
                    'type'    => 'multiselect',
                    'options' => $this->get_list_post_type(array('show_ui' => 1)),
                    'desc'    => __('Select post types that you want to use this option.', 'wp-sms')
                ),
                'notif_publish_new_post_words_count'      => array(
                    'id'   => 'notif_publish_new_post_words_count',
                    'name' => __('Post content words count', 'wp-sms'),
                    'type' => 'number',
                    'desc' => __('The number of word for cropping in send post notification. Default : 10', 'wp-sms')
                ),
                'notif_publish_new_post_template'         => array(
                    'id'   => 'notif_publish_new_post_template',
                    'name' => __('Message body', 'wp-sms'),
                    'type' => 'textarea',
                    'desc' => __('Enter the contents of the sms message.', 'wp-sms') . '<br>' .
                        sprintf(
                            __('Post title: %s, Post content: %s, Post url: %s, Post date: %s', 'wp-sms'),
                            '<code>%post_title%</code>',
                            '<code>%post_content%</code>',
                            '<code>%post_url%</code>',
                            '<code>%post_date%</code>'
                        )
                ),
                // Publish new post
                'notif_publish_new_post_author_title'     => array(
                    'id'   => 'notif_publish_new_post_author_title',
                    'name' => __('Author of the post', 'wp-sms'),
                    'type' => 'header'
                ),
                'notif_publish_new_post_author'           => array(
                    'id'      => 'notif_publish_new_post_author',
                    'name'    => __('Status', 'wp-sms'),
                    'type'    => 'checkbox',
                    'options' => $options,
                    'desc'    => __('Send an SMS to the author of the post when that post is published.<br>Make sure the "Add Mobile number field" option is enabled in the Settings > Features', 'wp-sms')
                ),
                'notif_publish_new_post_author_post_type' => array(
                    'id'      => 'notif_publish_new_post_author_post_type',
                    'name'    => __('Post Types', 'wp-sms'),
                    'type'    => 'multiselect',
                    'options' => $this->get_list_post_type(array('show_ui' => 1)),
                    'desc'    => __('Select post types that you want to use this option.', 'wp-sms')
                ),
                'notif_publish_new_post_author_template'  => array(
                    'id'   => 'notif_publish_new_post_author_template',
                    'name' => __('Message body', 'wp-sms'),
                    'type' => 'textarea',
                    'desc' => __('Enter the contents of the sms message.', 'wp-sms') . '<br>' .
                        sprintf(
                            __('Post title: %s, Post content: %s, Post url: %s, Post date: %s', 'wp-sms'),
                            '<code>%post_title%</code>',
                            '<code>%post_content%</code>',
                            '<code>%post_url%</code>',
                            '<code>%post_date%</code>'
                        )
                ),
                // Publish new wp version
                'notif_publish_new_wpversion_title'       => array(
                    'id'   => 'notif_publish_new_wpversion_title',
                    'name' => __('The new release of WordPress', 'wp-sms'),
                    'type' => 'header'
                ),
                'notif_publish_new_wpversion'             => array(
                    'id'      => 'notif_publish_new_wpversion',
                    'name'    => __('Status', 'wp-sms'),
                    'type'    => 'checkbox',
                    'options' => $options,
                    'desc'    => __('Send an SMS to you When the new release of WordPress.', 'wp-sms')
                ),
                // Register new user
                'notif_register_new_user_title'           => array(
                    'id'   => 'notif_register_new_user_title',
                    'name' => __('Register a new user', 'wp-sms'),
                    'type' => 'header'
                ),
                'notif_register_new_user'                 => array(
                    'id'      => 'notif_register_new_user',
                    'name'    => __('Status', 'wp-sms'),
                    'type'    => 'checkbox',
                    'options' => $options,
                    'desc'    => __('Send an SMS to you and user when register on wordpress.', 'wp-sms')
                ),
                'notif_register_new_user_admin_template'  => array(
                    'id'   => 'notif_register_new_user_admin_template',
                    'name' => __('Message body for admin', 'wp-sms'),
                    'type' => 'textarea',
                    'desc' => __('Enter the contents of the sms message.', 'wp-sms') . '<br>' .
                        sprintf(
                            __('User login: %s, User email: %s, Register date: %s', 'wp-sms'),
                            '<code>%user_login%</code>',
                            '<code>%user_email%</code>',
                            '<code>%date_register%</code>'
                        )
                ),
                'notif_register_new_user_template'        => array(
                    'id'   => 'notif_register_new_user_template',
                    'name' => __('Message body for user', 'wp-sms'),
                    'type' => 'textarea',
                    'desc' => __('Enter the contents of the sms message.', 'wp-sms') . '<br>' .
                        sprintf(
                            __('User login: %s, User email: %s, Register date: %s', 'wp-sms'),
                            '<code>%user_login%</code>',
                            '<code>%user_email%</code>',
                            '<code>%date_register%</code>'
                        )
                ),
                // New comment
                'notif_new_comment_title'                 => array(
                    'id'   => 'notif_new_comment_title',
                    'name' => __('New comment', 'wp-sms'),
                    'type' => 'header'
                ),
                'notif_new_comment'                       => array(
                    'id'      => 'notif_new_comment',
                    'name'    => __('Status', 'wp-sms'),
                    'type'    => 'checkbox',
                    'options' => $options,
                    'desc'    => __('Send an SMS to you When get a new comment.', 'wp-sms')
                ),
                'notif_new_comment_template'              => array(
                    'id'   => 'notif_new_comment_template',
                    'name' => __('Message body', 'wp-sms'),
                    'type' => 'textarea',
                    'desc' => __('Enter the contents of the sms message.', 'wp-sms') . '<br>' .
                        sprintf(
                            __('Comment author: %s, Author email: %s, Author url: %s, Author IP: %s, Comment date: %s, Comment content: %s, Comment URL: %s', 'wp-sms'),
                            '<code>%comment_author%</code>',
                            '<code>%comment_author_email%</code>',
                            '<code>%comment_author_url%</code>',
                            '<code>%comment_author_IP%</code>',
                            '<code>%comment_date%</code>',
                            '<code>%comment_content%</code>',
                            '<code>%comment_url%</code>'
                        )
                ),
                // User login
                'notif_user_login_title'                  => array(
                    'id'   => 'notif_user_login_title',
                    'name' => __('User login', 'wp-sms'),
                    'type' => 'header'
                ),
                'notif_user_login'                        => array(
                    'id'      => 'notif_user_login',
                    'name'    => __('Status', 'wp-sms'),
                    'type'    => 'checkbox',
                    'options' => $options,
                    'desc'    => __('Send an SMS to you When user is login.', 'wp-sms')
                ),
                'notif_user_login_template'               => array(
                    'id'   => 'notif_user_login_template',
                    'name' => __('Message body', 'wp-sms'),
                    'type' => 'textarea',
                    'desc' => __('Enter the contents of the sms message.', 'wp-sms') . '<br>' .
                        sprintf(
                            __('Username: %s, Nickname: %s', 'wp-sms'),
                            '<code>%username_login%</code>',
                            '<code>%display_name%</code>'
                        )
                ),
            )),

            /**
             * Integration  tab
             */
            'integration'   => apply_filters('wp_sms_integration_settings', array(
                // Contact form 7
                'cf7_title'                    => array(
                    'id'   => 'cf7_title',
                    'name' => __('Contact Form 7', 'wp-sms'),
                    'type' => 'header'
                ),
                'cf7_metabox'                  => array(
                    'id'      => 'cf7_metabox',
                    'name'    => __('SMS meta box', 'wp-sms'),
                    'type'    => 'checkbox',
                    'options' => $options,
                    'desc'    => __('Added Wordpress SMS meta box to Contact form 7 plugin when enable this option.', 'wp-sms')
                ),
                // Woocommerce
                'wc_title'                     => array(
                    'id'   => 'wc_title',
                    'name' => __('WooCommerce', 'wp-sms'),
                    'type' => 'header'
                ),
                'wc_notif_new_order'           => array(
                    'id'      => 'wc_notif_new_order',
                    'name'    => __('New order', 'wp-sms'),
                    'type'    => 'checkbox',
                    'options' => $options,
                    'desc'    => __('Send an SMS to you When get new order.', 'wp-sms')
                ),
                'wc_notif_new_order_template'  => array(
                    'id'   => 'wc_notif_new_order_template',
                    'name' => __('Message body', 'wp-sms'),
                    'type' => 'textarea',
                    'desc' => __('Enter the contents of the sms message.', 'wp-sms') . '<br>' .
                        sprintf(
                            __('Order ID: %s, Order status: %s', 'wp-sms'),
                            '<code>%order_id%</code>',
                            '<code>%status%</code>'
                        )
                ),
                // EDD
                'edd_title'                    => array(
                    'id'   => 'edd_title',
                    'name' => __('Easy Digital Downloads', 'wp-sms'),
                    'type' => 'header'
                ),
                'edd_notif_new_order'          => array(
                    'id'      => 'edd_notif_new_order',
                    'name'    => __('New order', 'wp-sms'),
                    'type'    => 'checkbox',
                    'options' => $options,
                    'desc'    => __('Send an SMS to you When get new order.', 'wp-sms')
                ),
                'edd_notif_new_order_template' => array(
                    'id'   => 'edd_notif_new_order_template',
                    'name' => __('Message body', 'wp-sms'),
                    'type' => 'textarea',
                    'desc' => __('Enter the contents of the message.', 'wp-sms') . '<br>' .
                        sprintf(
                            __('Customer email: %s, Customer name: %s, Customer last name: %s', 'wp-sms'),
                            '<code>%edd_email%</code>',
                            '<code>%edd_first%</code>',
                            '<code>%edd_last%</code>'
                        )
                ),
            )),

            /*
             * Licenses tab
             */
            'licenses'      => apply_filters('wp_sms_licenses_settings', array()),

        ));


        // Check the GDPR is enabled.
        if (Option::getOption('gdpr_compliance')) {
            $settings['newsletter']['newsletter_gdpr'] = array(
                'id'   => 'newsletter_gdpr',
                'name' => __('GDPR Compliance', 'wp-sms'),
                'type' => 'header'
            );

            $settings['newsletter']['newsletter_form_gdpr_text'] = array(
                'id'   => 'newsletter_form_gdpr_text',
                'name' => __('Confirmation text', 'wp-sms'),
                'type' => 'textarea'
            );

            $settings['newsletter']['newsletter_form_gdpr_confirm_checkbox'] = array(
                'id'      => 'newsletter_form_gdpr_confirm_checkbox',
                'name'    => __('Confirmation Checkbox status', 'wp-sms'),
                'type'    => 'select',
                'options' => array('checked' => 'Checked', 'unchecked' => 'Unchecked'),
                'desc'    => __('Checked or Unchecked GDPR checkbox as default form load.', 'wp-sms')
            );
        } else {
            $settings['newsletter']['newsletter_gdpr'] = array(
                'id'   => 'gdpr_notify',
                'name' => __('GDPR Compliance', 'wp-sms'),
                'type' => 'notice',
                'desc' => __('To get more option for GDPR, you should enable that in the general tab.', 'wp-sms'),
            );
        }
        return $settings;
    }

    private function isCurrentTab($tab)
    {
        return isset($_REQUEST['page']) && $_REQUEST['page'] == 'wp-sms-settings' && isset($_REQUEST['tab']) && $_REQUEST['tab'] == $tab;
    }

    /*
     * Activate Icon
     */
    public function getLicenseStatusIcon($addOnKey)
    {
        $constantLicenseKey = wp_sms_generate_constant_license($addOnKey);
        $licenseKey         = isset($this->options["license_{$addOnKey}_key"]) ? $this->options["license_{$addOnKey}_key"] : null;
        $licenseStatus      = isset($this->options["license_{$addOnKey}_status"]) ? $this->options["license_{$addOnKey}_status"] : null;
        $updateOption       = false;

        if (($constantLicenseKey && $this->isCurrentTab('licenses') && $this->checkRemoteLicenseByAddOnKeyAndLicense($addOnKey, $constantLicenseKey)) or $licenseStatus and $licenseKey) {
            $item = array('icon' => 'yes', 'text' => 'Active!', 'color' => '#1eb514');

            if ($constantLicenseKey) {
                $this->options["license_{$addOnKey}_status"] = true;
                $updateOption                                = true;
            }
        } else {
            $item                                        = array('icon' => 'no', 'text' => 'Inactive!', 'color' => '#ff0000');
            $this->options["license_{$addOnKey}_status"] = false;
            $updateOption                                = true;
        }

        if ($updateOption && empty($_POST)) {
            update_option($this->setting_name, $this->options);
        }

        return '<span style="color: ' . $item['color'] . '">&nbsp;&nbsp;<span class="dashicons dashicons-' . $item['icon'] . '" style="vertical-align: -4px;"></span>' . __($item['text'], 'wp-sms') . '</span>';
    }

    /*
     * Check license key
     */
    public function check_license_key($value, $oldValue)
    {
        foreach (wp_sms_get_addons() as $addOnKey => $addOn) {
            $constantLicenseKey       = wp_sms_generate_constant_license($addOnKey);
            $generateLicenseStatusKey = "license_{$addOnKey}_status";
            $licenseKey               = null;

            // Check what type license in use
            if ($constantLicenseKey) {
                $licenseKey = $constantLicenseKey;
            } elseif (isset($_POST['wpsms_settings']["license_{$addOnKey}_key"])) {
                $licenseKey = sanitize_text_field($_POST['wpsms_settings']["license_{$addOnKey}_key"]);
            }

            if (!$licenseKey) {
                $value[$generateLicenseStatusKey] = false;
                continue;
            }

            if ($this->checkRemoteLicenseByAddOnKeyAndLicense($addOnKey, $licenseKey)) {
                $value[$generateLicenseStatusKey] = true;
            } else {
                $value[$generateLicenseStatusKey] = false;
            }
        }

        return $value;
    }

    /**
     * Check the license with server
     *
     * @param $addOnKey
     * @param $licenseKey
     * @return bool|void
     */
    private function checkRemoteLicenseByAddOnKeyAndLicense($addOnKey, $licenseKey)
    {
        $response = wp_remote_get(add_query_arg(array(
            'plugin-name' => $addOnKey,
            'license_key' => $licenseKey,
            'website'     => get_bloginfo('url'),
        ), WP_SMS_SITE . '/wp-json/plugins/v1/validate'));

        if (is_wp_error($response)) {
            return;
        }

        $response = json_decode($response['body']);

        if (isset($response->status) and $response->status == 200) {
            return true;
        }
    }

    public function header_callback($args)
    {
        echo '<hr/>';
    }

    public function html_callback($args)
    {
        echo wp_kses_post($args['options']);
    }

    public function notice_callback($args)
    {
        echo wp_kses_post($args['options']);
    }

    public function checkbox_callback($args)
    {
        $checked = isset($this->options[$args['id']]) ? checked(1, $this->options[$args['id']], false) : '';
        $html    = sprintf('<input type="checkbox" id="wpsms_settings[%1$s]" name="wpsms_settings[%1$s]" value="1" %2$s /><label for="wpsms_settings[%1$s]"> ' . __('Active', 'wp-sms') . '</label><p class="description">%3$s</p>', esc_attr($args['id']), esc_attr($checked), wp_kses_post($args['desc']));
        echo $html;
    }

    public function multicheck_callback($args)
    {
        $html = '';
        foreach ($args['options'] as $key => $value) {
            $option_name = $args['id'] . '-' . $key;
            $this->checkbox_callback([
                'id'   => $option_name,
                'desc' => $value
            ]);
            echo '<br>';
        }

        echo $html;
    }

    public function radio_callback($args)
    {
        $html = '';
        foreach ($args['options'] as $key => $option) :
            $checked = false;

        if (isset($this->options[$args['id']]) && $this->options[$args['id']] == $key) {
            $checked = true;
        } elseif (isset($args['std']) && $args['std'] == $key && !isset($this->options[$args['id']])) {
            $checked = true;
        }
        $html .= sprintf('<input name="wpsms_settings[%1$s]"" id="wpsms_settings[%1$s][%2$s]" type="radio" value="%2$s" %3$s /><label for="wpsms_settings[%1$s][%2$s]">%4$s</label>&nbsp;&nbsp;', esc_attr($args['id']), esc_attr($key), checked(true, $checked, false), $option);
        endforeach;
        $html .= sprintf('<p class="description">%1$s</p>', wp_kses_post($args['desc']));
        echo $html;
    }

    public function text_callback($args)
    {
        if (isset($this->options[$args['id']]) and $this->options[$args['id']]) {
            $value = $this->options[$args['id']];
        } else {
            $value = isset($args['std']) ? $args['std'] : '';
        }

        $after_input = (isset($args['after_input']) && !is_null($args['after_input'])) ? $args['after_input'] : '';
        $size        = (isset($args['size']) && !is_null($args['size'])) ? $args['size'] : 'regular';
        $attributes  = (isset($args['attributes']) && !is_null($args['attributes'])) ? implode('', $args['attributes']) : '';
        $html        = sprintf('<input type="text" class="%1$s-text" id="wpsms_settings[%2$s]" name="wpsms_settings[%2$s]" value="%3$s" %4$s />%5$s<p class="description">%6$s</p>', esc_attr($size), esc_attr($args['id']), esc_attr(stripslashes($value)), esc_attr($attributes), $after_input, wp_kses_post($args['desc']));
        echo $html;
    }

    public function number_callback($args)
    {
        if (isset($this->options[$args['id']])) {
            $value = $this->options[$args['id']];
        } else {
            $value = isset($args['std']) ? $args['std'] : '';
        }

        $max  = isset($args['max']) ? $args['max'] : 999999;
        $min  = isset($args['min']) ? $args['min'] : 0;
        $step = isset($args['step']) ? $args['step'] : 1;

        $size = (isset($args['size']) && !is_null($args['size'])) ? $args['size'] : 'regular';
        $html = sprintf('<input type="number" step="%1$s" max="%2$s" min="%3$s" class="%4$s-text" id="wpsms_settings[%5$s]" name="wpsms_settings[%5$s]" value="%6$s"/><p class="description"> %7$s</p>', esc_attr($step), esc_attr($max), esc_attr($min), esc_attr($size), esc_attr($args['id']), esc_attr(stripslashes($value)), wp_kses_post($args['desc']));
        echo $html;
    }

    public function textarea_callback($args)
    {
        if (isset($this->options[$args['id']])) {
            $value = $this->options[$args['id']];
        } else {
            $value = isset($args['std']) ? $args['std'] : '';
        }

        $html = sprintf('<textarea class="large-text" cols="50" rows="5" id="wpsms_settings[%1$s]" name="wpsms_settings[%1$s]">%2$s</textarea><p class="description"> %3$s</p>', esc_attr($args['id']), esc_textarea(stripslashes($value)), wp_kses_post($args['desc']));
        echo $html;
    }

    public function password_callback($args)
    {
        if (isset($this->options[$args['id']])) {
            $value = $this->options[$args['id']];
        } else {
            $value = isset($args['std']) ? $args['std'] : '';
        }

        $size = (isset($args['size']) && !is_null($args['size'])) ? $args['size'] : 'regular';
        $html = sprintf('<input type="password" class="%1$s-text" id="wpsms_settings[%2$s]" name="wpsms_settings[%2$s]" value="%3$s"/><p class="description"> %4$s</p>', esc_attr($size), esc_attr($args['id']), esc_attr($value), wp_kses_post($args['desc']));

        echo $html;
    }

    public function missing_callback($args)
    {
        echo '&ndash;';

        return false;
    }


    public function select_callback($args)
    {
        if (isset($this->options[$args['id']])) {
            $value = $this->options[$args['id']];
        } else {
            $value = isset($args['std']) ? $args['std'] : '';
        }

        $html = sprintf('<select id="wpsms_settings[%1$s]" name="wpsms_settings[%1$s]">', esc_attr($args['id']));

        foreach ($args['options'] as $option => $name) {
            $selected = selected($option, $value, false);
            $html     .= sprintf('<option value="%1$s" %2$s>%3$s</option>', esc_attr($option), esc_attr($selected), $name);
        }

        $html .= sprintf('</select><p class="description"> %1$s</p>', wp_kses_post($args['desc']));

        echo $html;
    }

    public function multiselect_callback($args)
    {
        if (isset($this->options[$args['id']])) {
            $value = $this->options[$args['id']];
        } else {
            $value = isset($args['std']) ? $args['std'] : '';
        }

        $html     = sprintf('<select id="wpsms_settings[%1$s]" name="wpsms_settings[%1$s][]" multiple="true" class="chosen-select"/>', esc_attr($args['id']));
        $selected = '';

        foreach ($args['options'] as $k => $name) :
            foreach ($name as $option => $name) :
                if (isset($value) and is_array($value)) {
                    if (in_array($option, $value)) {
                        $selected = " selected='selected'";
                    } else {
                        $selected = '';
                    }
                }
        $html .= sprintf('<option value="%1$s" %2$s>%3$s</option>', esc_attr($option), esc_attr($selected), $name);
        endforeach;
        endforeach;

        $html .= sprintf('</select><p class="description"> %1$s</p>', wp_kses_post($args['desc']));

        echo $html;
    }

    public function countryselect_callback($args)
    {
        if (isset($this->options[$args['id']])) {
            $value = $this->options[$args['id']];
        } else {
            $value = isset($args['std']) ? $args['std'] : '';
        }

        $html     = sprintf('<select id="wpsms_settings[%1$s]" name="wpsms_settings[%1$s][]" multiple="true" class="chosen-select"/>', esc_attr($args['id']));
        $selected = '';

        foreach ($args['options'] as $option => $country) :
            if (isset($value) and is_array($value)) {
                if (in_array($country['code'], $value)) {
                    $selected = " selected='selected'";
                } else {
                    $selected = '';
                }
            }
        $html .= sprintf('<option value="%1$s" %2$s>%3$s</option>', esc_attr($country['code']), esc_attr($selected), $country['name']);
        endforeach;

        $html .= sprintf('</select><p class="description"> %1$s</p>', wp_kses_post($args['desc']));

        echo $html;
    }

    public function advancedselect_callback($args)
    {
        if (isset($this->options[$args['id']])) {
            $value = $this->options[$args['id']];
        } else {
            $value = isset($args['std']) ? $args['std'] : '';
        }

        if (is_rtl()) {
            $class_name = 'chosen-select chosen-rtl';
        } else {
            $class_name = 'chosen-select';
        }

        $html = sprintf('<select class="%1$s" id="wpsms_settings[%2$s]" name="wpsms_settings[%2$s]">', esc_attr($class_name), esc_attr($args['id']));

        foreach ($args['options'] as $key => $v) {
            $html .= '<optgroup label="' . ucfirst(str_replace('_', ' ', $key)) . '">';

            foreach ($v as $option => $name) :

                $disabled = '';
            if (!defined('WP_SMS_PRO_VERSION') && array_column(Gateway::$proGateways, $option)) {
                $disabled = ' disabled';
                $name     .= '<span> ' . __('- (Pro Pack)', 'wp-sms') . '</span>';
            }
            $selected = selected($option, $value, false);
            $html     .= sprintf('<option value="%1$s" %2$s %3$s>%4$s</option>', esc_attr($option), esc_attr($selected), esc_attr($disabled), ucfirst($name));
            endforeach;

            $html .= '</optgroup>';
        }

        $html .= sprintf('</select><p class="description"> %1$s</p>', wp_kses_post($args['desc']));

        echo $html;
    }

    public function color_select_callback($args)
    {
        if (isset($this->options[$args['id']])) {
            $value = $this->options[$args['id']];
        } else {
            $value = isset($args['std']) ? $args['std'] : '';
        }

        $html = sprintf('<select id="wpsms_settings[%1$s]" name="wpsms_settings[%1$s]">', esc_attr($args['id']));

        foreach ($args['options'] as $option => $color) :
            $selected = selected($option, $value, false);
        $html     .= esc_attr('<option value="%1$s" %2$s>%3$s</option>', esc_attr($option), esc_attr($selected), $color['label']);
        endforeach;

        $html .= sprintf('</select><p class="description"> %1$s</p>', wp_kses_post($args['desc']));

        echo $html;
    }

    public function rich_editor_callback($args)
    {
        global $wp_version;

        if (isset($this->options[$args['id']])) {
            $value = $this->options[$args['id']];
        } else {
            $value = isset($args['std']) ? $args['std'] : '';
        }

        if ($wp_version >= 3.3 && function_exists('wp_editor')) {
            $html = wp_editor(stripslashes($value), 'wpsms_settings[' . $args['id'] . ']', array('textarea_name' => 'wpsms_settings[' . $args['id'] . ']'));
        } else {
            $html = sprintf('<textarea class="large-text" rows="10" id="wpsms_settings[%1$s]" name="wpsms_settings[%1$s]">' . esc_textarea(stripslashes($value)) . '</textarea>', esc_attr($args['id']));
        }

        $html .= sprintf('<p class="description"> %1$s</p>', wp_kses_post($args['desc']));

        echo $html;
    }

    public function upload_callback($args)
    {
        if (isset($this->options[$args['id']])) {
            $value = $this->options[$args['id']];
        } else {
            $value = isset($args['std']) ? $args['std'] : '';
        }

        $size = (isset($args['size']) && !is_null($args['size'])) ? $args['size'] : 'regular';
        $html = sprintf('<input type="text" class="%1$s-text wpsms_upload_field" id="wpsms_settings[%2$s]" name="wpsms_settings[%2$s]" value="%3$s"/><span>&nbsp;<input type="button" class="wpsms_settings_upload_button button-secondary" value="%4$s"/></span><p class="description"> %5$s</p>', esc_attr($size), esc_attr($args['id']), esc_attr(stripslashes($value)), __('Upload File', 'wpsms'), wp_kses_post($args['desc']));

        echo $html;
    }

    public function color_callback($args)
    {
        if (isset($this->options[$args['id']])) {
            $value = $this->options[$args['id']];
        } else {
            $value = isset($args['std']) ? $args['std'] : '';
        }

        $default = isset($args['std']) ? $args['std'] : '';
        $html    = sprintf('<input type="text" class="wpsms-color-picker" id="wpsms_settings[%1$s]" name="wpsms_settings[%1$s]" value="%2$s" data-default-color="%3$s" /><p class="description"> %4$s</p>', esc_attr($args['id']), esc_attr($value), esc_attr($default), wp_kses_post($args['desc']));

        echo $html;
    }

    public function render_settings()
    {
        $active_tab = isset($_GET['tab']) && array_key_exists($_GET['tab'], $this->get_tabs()) ? sanitize_text_field($_GET['tab']) : 'general';

        ob_start(); ?>
        <div class="wrap wpsms-wrap wpsms-settings-wrap">
            <?php require_once WP_SMS_DIR . 'includes/templates/header.php'; ?>
            <div class="wpsms-wrap__main">
                <?php do_action('wp_sms_settings_page'); ?>
                <h2><?php _e('Settings', 'wp-sms') ?></h2>
                <div class="wpsms-tab-group">
                    <ul class="wpsms-tab">
                        <?php
                        foreach ($this->get_tabs() as $tab_id => $tab_name) {
                            $tab_url = add_query_arg(array(
                                'settings-updated' => false,
                                'tab'              => $tab_id
                            ));

                            $active = $active_tab == $tab_id ? 'active' : '';

                            echo '<li><a href="' . esc_url($tab_url) . '" title="' . esc_attr($tab_name) . '" class="' . $active . '">';
                            echo $tab_name;
                            echo '</a></li>';
                        } ?>
                    </ul>
                    <?php echo settings_errors('wpsms-notices'); ?>
                    <div class="wpsms-tab-content wpsms_<?= $tab_id ?>_settings_tab">
                        <form method="post" action="options.php">
                            <table class="form-table">
                                <?php
                                settings_fields($this->setting_name);
        do_settings_fields('wpsms_settings_' . $active_tab, 'wpsms_settings_' . $active_tab); ?>
                            </table>
                            <?php submit_button(); ?>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <?php
        echo ob_get_clean();
    }

    /*
     * Get list Post Type
     */
    public function get_list_post_type($args = array())
    {
        // vars
        $post_types = array();

        // extract special arg
        $exclude   = array();
        $exclude[] = 'attachment';
        $exclude[] = 'acf-field'; //Advance custom field
        $exclude[] = 'acf-field-group'; //Advance custom field Group
        $exclude[] = 'vc4_templates'; //Visual composer
        $exclude[] = 'vc_grid_item'; //Visual composer Grid
        $exclude[] = 'acf'; //Advance custom field Basic
        $exclude[] = 'wpcf7_contact_form'; //contact 7 Post Type
        $exclude[] = 'shop_order'; //WooCommerce Shop Order
        $exclude[] = 'shop_coupon'; //WooCommerce Shop coupon

        // get post type objects
        $objects = get_post_types($args, 'objects');
        foreach ($objects as $k => $object) {
            if (in_array($k, $exclude)) {
                continue;
            }
            if ($object->_builtin && !$object->public) {
                continue;
            }
            $post_types[] = array($object->cap->publish_posts . '|' . $object->name => $object->label);
        }

        // return
        return $post_types;
    }

    /**
     * Get countries list
     *
     * @return array|mixed|object
     */
    public function get_countries_list()
    {
        // Load countries list file
        $file   = WP_SMS_DIR . 'assets/countries.json';
        $file   = file_get_contents($file);
        $result = json_decode($file, true);

        return $result;
    }

    /**
     * Modify license setting page and render add-ons settings
     *
     * @param $settings
     * @return array
     */
    public function modifyLicenseSettings($settings)
    {
        foreach (wp_sms_get_addons() as $addOnKey => $addOn) {

            // license title
            $settings["license_{$addOnKey}_title"] = array(
                'id'   => "license_{$addOnKey}_title",
                'name' => $addOn,
                'type' => 'header',
            );

            // license key
            $settings["license_{$addOnKey}_key"] = array(
                'id'          => "license_{$addOnKey}_key",
                'name'        => __('License Key', 'wp-sms'),
                'type'        => 'text',
                'after_input' => $this->getLicenseStatusIcon($addOnKey),
                'desc'        => sprintf(__('The license key is used for access to automatic update and support, to get the licenses, please go to <a href="%s" target="_blank">your account</a>.<br /><br />- Need help to enter your license? <a href="%s" target="_blank">Click here</a> to get information.<br />- Having a problem with your license? <a href="%s" target="_blank">Click here</a> for troubleshooting.', 'wp-sms'), esc_url(WP_SMS_SITE . '/my-account/orders/'), esc_url(WP_SMS_SITE . '/resources/troubleshoot-license-activation-issues/'), esc_url(WP_SMS_SITE . '/resources/troubleshoot-license-activation-issues/')),
            );
        }

        return $settings;
    }
}

new Settings();
