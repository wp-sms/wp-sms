<?php

namespace WP_SMS;

if (!defined('ABSPATH')) {
    exit;
} // No direct access allowed ;)

class Settings
{
    public $setting_name;
    public $options = array();
    private $optionNames = [
        'main' => 'wpsms_settings',
        'pro'  => 'wps_pp_settings'
    ];
    private $proTabs = [
        'pro_wordpress',
        'pro_buddypress',
        'pro_woocommerce',
        'pro_gravity_forms',
        'pro_quform',
        'pro_edd',
        'pro_wp_job_manager',
        'pro_awesome_support',
        'pro_ultimate_members'
    ];
    private $proIsInstalled;

    /**
     * @return string
     */
    private function getCurrentOptionName()
    {
        if (isset($_REQUEST['tab']) && in_array($_REQUEST['tab'], $this->proTabs)) {
            return $this->optionNames['pro'];
        }

        if (isset($_POST['option_page']) && $_POST['option_page'] == 'wps_pp_settings') {
            return $this->optionNames['pro'];
        }

        return $this->optionNames['main'];
    }

    public function __construct()
    {
        $this->setting_name   = $this->getCurrentOptionName();
        $this->proIsInstalled = Version::pro_is_active();

        $this->get_settings();
        $this->options = get_option($this->setting_name);

        if (empty($this->options)) {
            update_option($this->setting_name, array());
        }

        if (isset($_GET['page']) and $_GET['page'] == 'wp-sms-settings' or isset($_POST['option_page']) and in_array($_POST['option_page'], $this->optionNames)) {
            add_action('admin_init', array($this, 'register_settings'));
        }

        // Check License Code
        if (isset($_POST['submit']) and isset($_REQUEST['option_page']) and in_array($_POST['option_page'], $this->optionNames) and strpos(wp_get_referer(), 'tab=licenses')) {
            add_filter('pre_update_option_' . $this->setting_name, array($this, 'check_license_key'), 10, 2);
        }

        add_filter('wp_sms_licenses_settings', array($this, 'modifyLicenseSettings'));
    }

    /**
     * Gets saved settings from WP core
     *
     * @return array
     * @since 2.0
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
            add_settings_section("{$this->setting_name}_{$tab}", __return_null(), '__return_false', "{$this->setting_name}_{$tab}");

            if (empty($settings)) {
                continue;
            }

            foreach ($settings as $option) {
                $name     = isset($option['name']) ? $option['name'] : '';
                $optionId = $option['id'];
                $readonly = (isset($option['readonly']) && $option['readonly'] == true) ? 'wpsms-pro-feature' : '';

                add_settings_field(
                    "$this->setting_name[$optionId]",
                    $name,
                    array($this, "{$option['type']}_callback"),
                    "{$this->setting_name}_{$tab}",
                    "{$this->setting_name}_{$tab}",
                    array(
                        'id'          => $optionId ? $optionId : null,
                        'desc'        => !empty($option['desc']) ? $option['desc'] : '',
                        'name'        => isset($option['name']) ? $option['name'] : null,
                        'after_input' => isset($option['after_input']) ? $option['after_input'] : null,
                        'section'     => $tab,
                        'size'        => isset($option['size']) ? $option['size'] : null,
                        'options'     => isset($option['options']) ? $option['options'] : '',
                        'std'         => isset($option['std']) ? $option['std'] : '',
                        'doc'         => isset($option['doc']) ? $option['doc'] : '',
                        'class'       => "tr-{$option['type']} {$readonly}",
                        'label_for'   => true,
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
            /*
             * Main plugin tabs
             */
            'general'              => __('General', 'wp-sms'),
            'gateway'              => __('SMS Gateway', 'wp-sms'),
            'newsletter'           => __('SMS Newsletter', 'wp-sms'),
            'notifications'        => __('Notifications', 'wp-sms'),
            'advanced'             => __('Advanced', 'wp-sms'),
            'contact_form7'        => __('Contact Form 7', 'wp-sms'),

            /*
             * Pro Pack tabs
             */
            'pro_wordpress'        => __('2FA & Login', 'wp-sms'),
            'pro_buddypress'       => __('BuddyPress', 'wp-sms'),
            'pro_woocommerce'      => __('WooCommerce', 'wp-sms'),
            'pro_gravity_forms'    => __('Gravity Forms', 'wp-sms'),
            'pro_quform'           => __('Quform', 'wp-sms'),
            'pro_edd'              => __('Easy Digital Downloads', 'wp-sms'),
            'pro_wp_job_manager'   => __('WP Job Manager', 'wp-sms'),
            'pro_awesome_support'  => __('Awesome Support', 'wp-sms'),
            'pro_ultimate_members' => __('Ultimate Members', 'wp-sms'),

            'licenses' => __('Licenses', 'wp-sms')
        );

        return apply_filters('wp_sms_registered_tabs', $tabs);
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
        $tab      = isset($referrer['tab']) ? $referrer['tab'] : 'general';

        $input = $input ? $input : array();
        $input = apply_filters("{$this->setting_name}_{$tab}_sanitize", $input);

        // Loop through each setting being saved and pass it through a sanitization filter
        foreach ($input as $key => $value) {
            // Get the setting type (checkbox, select, etc)
            $type = isset($settings[$tab][$key]['type']) ? $settings[$tab][$key]['type'] : false;

            if ($type) {
                // Field type specific filter
                $input[$key] = apply_filters("{$this->setting_name}_sanitize_{$type}", $value, $key);
            }

            // General filter
            $input[$key] = apply_filters("{$this->setting_name}_sanitize", $value, $key);
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

        /*
         * Pro Pack fields
         */
        $groups              = Newsletter::getGroups();
        $subscribe_groups[0] = __('All', 'wp-sms');

        if ($groups) {
            foreach ($groups as $group) {
                $subscribe_groups[$group->ID] = $group->name;
            }
        }

        $gf_forms               = array();
        $qf_forms               = array();
        $um_options             = array();
        $pro_wordpress_settings = array(
            'login_title'            => array(
                'id'   => 'login_title',
                'name' => __('Login With SMS', 'wp-sms'),
                'type' => 'header'
            ),
            'login_sms'              => array(
                'id'      => 'login_sms',
                'name'    => __('Status', 'wp-sms'),
                'type'    => 'checkbox',
                'options' => $options,
                'desc'    => __('This option adds login with SMS in the login form.', 'wp-sms'),
            ),
            'login_sms_message'      => array(
                'id'   => 'login_sms_message',
                'name' => __('Message body', 'wp-sms'),
                'type' => 'textarea',
                'desc' => __('Enter the contents of the SMS message.', 'wp-sms') . '<br>' .
                    sprintf(
                        __('Mobile code: %s, User name: %s, Full Name: %s, Site Name: %s, Site Url: %s', 'wp-sms'),
                        '<code>%code%</code>',
                        '<code>%user_name%</code>',
                        '<code>%full_name%</code>',
                        '<code>%site_name%</code>',
                        '<code>%site_url%</code>'
                    )
            ),
            'otp_title'            => array(
                'id'   => 'otp_title',
                'name' => __('Two-Factor Authentication SMS', 'wp-sms'),
                'type' => 'header'
            ),
            'mobile_verify'          => array(
                'id'      => 'mobile_verify',
                'name'    => __('Status', 'wp-sms'),
                'type'    => 'checkbox',
                'options' => $options,
                'desc'    => __('Verify mobile number in the login form. This feature is only compatible with WordPress default form.<br>The <code>manage_options</code> caps don\'t need to verify in the login form.', 'wp-sms'),
            ),
            'mobile_verify_optional' => array(
                'id'      => 'mobile_verify_optional',
                'name'    => __('Should be optional?', 'wp-sms'),
                'type'    => 'checkbox',
                'options' => $options,
                'desc'    => __('If you would like to make the mobile number field optional, please enable the option.', 'wp-sms'),
            ),
            'mobile_verify_method'   => array(
                'id'      => 'mobile_verify_method',
                'name'    => __('Method', 'wp-sms'),
                'type'    => 'select',
                'options' => array(
                    'optional'  => __('Optional - Users can enable/disable it in their profile', 'wp-sms'),
                    'force_all' => __('Enable for All Users', 'wp-sms')
                ),
                'desc'    => __('Choose from which what 2FA method you want to use.', 'wp-sms')
            ),
            'mobile_verify_runtime'  => array(
                'id'      => 'mobile_verify_runtime',
                'name'    => __('Run Time', 'wp-sms'),
                'type'    => 'select',
                'options' => array(
                    'once_time'  => __('Just once', 'wp-sms'),
                    'every_time' => __('Everytime', 'wp-sms')
                ),
                'desc'    => __('Choose from which what 2FA run-time you want to use.', 'wp-sms')
            ),
            'mobile_verify_message'  => array(
                'id'   => 'mobile_verify_message',
                'name' => __('Message content', 'wp-sms'),
                'type' => 'textarea',
                'desc' => __('Enter the contents of the 2FA SMS message.', 'wp-sms') . '<br>' .
                    sprintf(
                        __('Mobile code: %s, User name: %s, First Name: %s, Last Name: %s', 'wp-sms'),
                        '<code>%otp%</code>',
                        '<code>%user_name%</code>',
                        '<code>%first_name%</code>',
                        '<code>%last_name%</code>'
                    )
            )
        );

        // Set BuddyPress settings
        if (class_exists('BuddyPress')) {
            $buddyPressProfileFields = [];
            if (function_exists('bp_xprofile_get_groups')) {
                $buddyPressProfileGroups = bp_xprofile_get_groups(['fetch_fields' => true]);

                foreach ($buddyPressProfileGroups as $buddyPressProfileGroup) {
                    if (isset($buddyPressProfileGroup->fields)) {
                        foreach ($buddyPressProfileGroup->fields as $field) {
                            $buddyPressProfileFields[$buddyPressProfileGroup->name][$field->id] = $field->name;
                        }
                    }
                }
            }

            $buddypress_settings = array(
                'bp_fields'                       => array(
                    'id'   => 'bp_fields',
                    'name' => __('General', 'wp-sms'),
                    'type' => 'header'
                ),
                'bp_mobile_field'                 => array(
                    'id'      => 'bp_mobile_field',
                    'name'    => __('Choose the field', 'wp-sms'),
                    'type'    => 'select',
                    'options' => array(
                        'disable'            => __('Disable (No field)', 'wp-sms'),
                        'add_new_field'      => __('Add a new mobile field to profile page', 'wp-sms'),
                        'used_current_field' => __('Use the exists field', 'wp-sms'),
                    ),
                    'desc'    => __('Choose from which field you would like to use for mobile field.', 'wp-sms')
                ),
                'bp_mobile_field_id'              => array(
                    'id'      => 'bp_mobile_field_id',
                    'name'    => __('Choose the exists field', 'wp-sms'),
                    'type'    => 'advancedselect',
                    'options' => $buddyPressProfileFields,
                    'desc'    => __('Select the BuddyPress field', 'wp-sms')
                ),
                'bp_sync_fields'                  => array(
                    'id'   => 'bp_sync_fields',
                    'name' => __('Sync fields'),
                    'type' => 'checkbox',
                    'desc' => __('Sync and compatibility the BuddyPress mobile numbers with plugin.', 'wp-sms')
                ),
                'bp_welcome_notification'         => array(
                    'id'   => 'bp_welcome_notification',
                    'name' => __('Welcome Notification', 'wp-sms'),
                    'type' => 'header',
                    'desc' => __('By enabling this option you can send welcome SMS to new BuddyPress users'),
                ),
                'bp_welcome_notification_enable'  => array(
                    'id'      => 'bp_welcome_notification_enable',
                    'name'    => __('Status', 'wp-sms'),
                    'type'    => 'checkbox',
                    'options' => $options,
                    'desc'    => __('Send an SMS to user when register on BuddyPress.', 'wp-sms')
                ),
                'bp_welcome_notification_message' => array(
                    'id'   => 'bp_welcome_notification_message',
                    'name' => __('Message body', 'wp-sms'),
                    'type' => 'textarea',
                    'desc' => __('Enter the contents of the SMS message.', 'wp-sms') . '<br>' .
                        sprintf(
                            __('User login: %s, User email: %s, User display name: %s', 'wp-sms'),
                            '<code>%user_login%</code>',
                            '<code>%user_email%</code>',
                            '<code>%display_name%</code>'
                        )
                ),
                'mentions'                        => array(
                    'id'   => 'mentions',
                    'name' => __('Mention Notification', 'wp-sms'),
                    'type' => 'header',
                ),
                'bp_mention_enable'               => array(
                    'id'      => 'bp_mention_enable',
                    'name'    => __('Send SMS', 'wp-sms'),
                    'type'    => 'checkbox',
                    'options' => $options,
                    'desc'    => __('Send SMS to user when someone mentioned. for example @admin', 'wp-sms')
                ),
                'bp_mention_message'              => array(
                    'id'   => 'bp_mention_message',
                    'name' => __('Message body', 'wp-sms'),
                    'type' => 'textarea',
                    'desc' => __('Enter the contents of the SMS message.', 'wp-sms') . '<br>' .
                        sprintf(
                            __('Posted user display name: %s, User profile permalink: %s, Time: %s, Message: %s, Receiver user display name: %s', 'wp-sms'),
                            '<code>%posted_user_display_name%</code>',
                            '<code>%primary_link%</code>',
                            '<code>%time%</code>',
                            '<code>%message%</code>',
                            '<code>%receiver_user_display_name%</code>'
                        )
                ),
                'private_message'                        => array(
                    'id'   => 'private_message',
                    'name' => __('Private Message Notification', 'wp-sms'),
                    'type' => 'header',
                ),
                'bp_private_message_enable'               => array(
                    'id'      => 'bp_private_message_enable',
                    'name'    => __('Send SMS', 'wp-sms'),
                    'type'    => 'checkbox',
                    'options' => $options,
                    'desc'    => __('Send SMS notification when user received a private message', 'wp-sms')
                ),
                'bp_private_message_content'              => array(
                    'id'   => 'bp_private_message_content',
                    'name' => __('Message body', 'wp-sms'),
                    'type' => 'textarea',
                    'desc' => __('Enter the contents of the SMS message.', 'wp-sms') . '<br>' .
                        sprintf(
                            __('Sender display name: %s, Subject: %s, Message: %s, Message URL: %s', 'wp-sms'),
                            '<code>%sender_display_name%</code>',
                            '<code>%subject%</code>',
                            '<code>%message%</code>',
                            '<code>%message_url%</code>'
                        )
                ),
                'comments_activity'               => array(
                    'id'   => 'comments_activity',
                    'name' => __('User activity comments', 'wp-sms'),
                    'type' => 'header'
                ),
                'bp_comments_activity_enable'     => array(
                    'id'      => 'bp_comments_activity_enable',
                    'name'    => __('Send SMS', 'wp-sms'),
                    'type'    => 'checkbox',
                    'options' => $options,
                    'desc'    => __('Send SMS to user when the user get a reply on activity', 'wp-sms')
                ),
                'bp_comments_activity_message'    => array(
                    'id'   => 'bp_comments_activity_message',
                    'name' => __('Message body', 'wp-sms'),
                    'type' => 'textarea',
                    'desc' => __('Enter the contents of the SMS message.', 'wp-sms') . '<br>' .
                        sprintf(
                            __('Posted user display name: %s, Comment content: %s, Receiver user display name: %s', 'wp-sms'),
                            '<code>%posted_user_display_name%</code>',
                            '<code>%comment%</code>',
                            '<code>%receiver_user_display_name%</code>'
                        )
                ),
                'comments'                        => array(
                    'id'   => 'comments',
                    'name' => __('User reply comments', 'wp-sms'),
                    'type' => 'header'
                ),
                'bp_comments_reply_enable'        => array(
                    'id'      => 'bp_comments_reply_enable',
                    'name'    => __('Send SMS', 'wp-sms'),
                    'type'    => 'checkbox',
                    'options' => $options,
                    'desc'    => __('Send SMS to user when the user get a reply on comment', 'wp-sms')
                ),
                'bp_comments_reply_message'       => array(
                    'id'   => 'bp_comments_reply_message',
                    'name' => __('Message body', 'wp-sms'),
                    'type' => 'textarea',
                    'desc' => __('Enter the contents of the SMS message.', 'wp-sms') . '<br>' .
                        sprintf(
                            __('Posted user display name: %s, Comment content: %s, Receiver user display name: %s', 'wp-sms'),
                            '<code>%posted_user_display_name%</code>',
                            '<code>%comment%</code>',
                            '<code>%receiver_user_display_name%</code>'
                        )
                )
            );
        } else {
            $buddypress_settings = array(
                'bp_fields' => array(
                    'id'   => 'bp_fields',
                    'name' => __('Not active', 'wp-sms'),
                    'type' => 'notice',
                    'desc' => __('BuddyPress should be installed to show the options.', 'wp-sms'),
                ));
        }

        // Set WooCommerce settings
        if (class_exists('WooCommerce')) {
            $wc_settings = array(
                'wc_fields'                    => array(
                    'id'   => 'wc_fields',
                    'name' => __('General', 'wp-sms'),
                    'type' => 'header'
                ),
                'wc_mobile_field'              => array(
                    'id'      => 'wc_mobile_field',
                    'name'    => __('Choose the Mobile field', 'wp-sms'),
                    'type'    => 'select',
                    'options' => array(
                        'disable'            => __('Disable (No field)', 'wp-sms'),
                        'add_new_field'      => __('Add a New Mobile field in the checkout form', 'wp-sms'),
                        'used_current_field' => __('Use the Billing Phone field', 'wp-sms'),
                    ),
                    'desc'    => __('Choose from which field you get numbers for sending SMS.', 'wp-sms')
                ),
                'wc_mobile_field_optional'           => array(
                    'id'      => 'wc_mobile_field_optional',
                    'name'    => __('Should be optional?', 'wp-sms'),
                    'type'    => 'checkbox',
                    'options' => $options,
                    'desc'    => __('If you would like to make the mobile number field optional, please enable the option.', 'wp-sms')
                ),
                'wc_meta_box'                  => array(
                    'id'   => 'wc_meta_box',
                    'name' => __('Order Meta Box', 'wp-sms'),
                    'type' => 'header'
                ),
                'wc_meta_box_enable'           => array(
                    'id'      => 'wc_meta_box_enable',
                    'name'    => __('Status', 'wp-sms'),
                    'type'    => 'checkbox',
                    'options' => $options,
                    'desc'    => __('Enable send SMS meta box on Orders.<br>Note: You must choose the mobile field first if disable Meta Box will not appear too.', 'wp-sms')
                ),
                'wc_otp'                       => array(
                    'id'   => 'wc_otp',
                    'name' => __('Mobile Verification', 'wp-sms'),
                    'type' => 'header',
                    'desc' => __('By enabling this option the customers should verify their mobile number while placing the order.', 'wp-sms'),
                    'doc'  => '/resources/secure-login-with-one-time-password-otp/',
                ),
                'wc_otp_enable'                => array(
                    'id'      => 'wc_otp_enable',
                    'name'    => __('Status', 'wp-sms'),
                    'type'    => 'checkbox',
                    'options' => $options,
                    'desc'    => __('Enable mobile verification for placing the order during the checkout. make sure the <b>General » Mobile field</b> is enabled in advance.', 'wp-sms')
                ),
                'wc_otp_countries_whitelist'   => array(
                    'id'      => 'wc_otp_countries_whitelist',
                    'name'    => __('Countries Whitelist', 'wp-sms'),
                    'type'    => 'countryselect',
                    'options' => $this->getCountriesList(),
                    'desc'    => __('Specify the countries to verify the numbers.', 'wp-sms')
                ),
                'wc_otp_max_retry'             => array(
                    'id'   => 'wc_otp_max_retry',
                    'name' => __('Max SMS retries', 'wp-sms'),
                    'type' => 'text',
                    'desc' => __('For no limits, set it to : 0', 'wp-sms')
                ),
                'wc_otp_max_time_limit'        => array(
                    'id'   => 'wc_otp_max_time_limit',
                    'name' => __('Retries expire time in Hours', 'wp-sms'),
                    'type' => 'text',
                    'desc' => __('This option working when a user reached max retries and need a period time for start again retry cycle.<br>For no limits, set it to : 0', 'wp-sms')
                ),
                'wc_disable_exists_validation' => array(
                    'id'      => 'wc_disable_exists_validation',
                    'name'    => __('Disable exists number validation', 'wp-sms'),
                    'type'    => 'checkbox',
                    'options' => $options,
                    'desc'    => __('By enabling this option, the customers who are not logged-in (guest) can use any number', 'wp-sms')
                ),
                'wc_otp_text'                  => array(
                    'id'   => 'wc_otp_text',
                    'name' => __('SMS text', 'wp-sms'),
                    'type' => 'textarea',
                    'desc' => sprintf(__('e.g: Your Verification Code: %s', 'wp-sms'), '<code>%otp_code%</code>')
                ),
                'wc_notify_product'            => array(
                    'id'   => 'wc_notify_product',
                    'name' => __('Notify for new product', 'wp-sms'),
                    'type' => 'header'
                ),
                'wc_notify_product_enable'     => array(
                    'id'      => 'wc_notify_product_enable',
                    'name'    => __('Send SMS', 'wp-sms'),
                    'type'    => 'checkbox',
                    'options' => $options,
                    'desc'    => __('Send SMS when publish new a product', 'wp-sms')
                ),
                'wc_notify_product_receiver'   => array(
                    'id'      => 'wc_notify_product_receiver',
                    'name'    => __('SMS receiver', 'wp-sms'),
                    'type'    => 'select',
                    'options' => array(
                        'subscriber' => __('Subscriber', 'wp-sms'),
                        'users'      => __('Users', 'wp-sms')
                    ),
                    'desc'    => __('Please select the receiver of SMS', 'wp-sms')
                ),
                'wc_notify_product_cat'        => array(
                    'id'      => 'wc_notify_product_cat',
                    'name'    => __('Subscribe group', 'wp-sms'),
                    'type'    => 'select',
                    'options' => $subscribe_groups,
                    'desc'    => __('If you select the Subscribe users, can select the group for send sms', 'wp-sms')
                ),
                'wc_notify_product_roles'   => array(
                    'id'      => 'wc_notify_product_roles',
                    'name'    => __('Specific roles', 'wp-sms'),
                    'type'    => 'multiselect',
                    'options' => $this->getRoles(),
                    'desc'    => __('Select the role of the user you want to receive the SMS.', 'wp-sms')
                ),
                'wc_notify_product_message'    => array(
                    'id'   => 'wc_notify_product_message',
                    'name' => __('Message body', 'wp-sms'),
                    'type' => 'textarea',
                    'desc' => __('Enter the contents of the SMS message.', 'wp-sms') . '<br>' .
                        sprintf(
                            __('Product title: %s, Product url: %s, Product date: %s, Product price: %s', 'wp-sms'),
                            '<code>%product_title%</code>',
                            '<code>%product_url%</code>',
                            '<code>%product_date%</code>',
                            '<code>%product_price%</code>'
                        )
                ),
                'wc_notify_order'              => array(
                    'id'   => 'wc_notify_order',
                    'name' => __('Notify for new order', 'wp-sms'),
                    'type' => 'header'
                ),
                'wc_notify_order_enable'       => array(
                    'id'      => 'wc_notify_order_enable',
                    'name'    => __('Send SMS', 'wp-sms'),
                    'type'    => 'checkbox',
                    'options' => $options,
                    'desc'    => __('Send SMS when submit new order', 'wp-sms')
                ),
                'wc_notify_order_receiver'     => array(
                    'id'   => 'wc_notify_order_receiver',
                    'name' => __('SMS receiver', 'wp-sms'),
                    'type' => 'text',
                    'desc' => __('Please enter mobile number for get sms. You can separate the numbers with the Latin comma.', 'wp-sms')
                ),
                'wc_notify_order_message'      => array(
                    'id'   => 'wc_notify_order_message',
                    'name' => __('Message body', 'wp-sms'),
                    'type' => 'textarea',
                    'desc' => __('Enter the contents of the SMS message.', 'wp-sms') . '<br>' .
                        sprintf(
                            __('Billing First Name: %s, Billing Company: %s, Billing Address: %s, Billing Phone Number: %s, Order ID: %s, Order number: %s, Order Total: %s, Order Total Currency: %s, Order Total Currency Symbol: %s, Order edit URL: %s, Order Items: %s, Order status: %s', 'wp-sms'),
                            '<code>%billing_first_name%</code>',
                            '<code>%billing_company%</code>',
                            '<code>%billing_address%</code>',
                            '<code>%billing_phone%</code>',
                            '<code>%order_id%</code>',
                            '<code>%order_number%</code>',
                            '<code>%order_total%</code>',
                            '<code>%order_total_currency%</code>',
                            '<code>%order_total_currency_symbol%</code>',
                            '<code>%order_edit_url%</code>',
                            '<code>%order_items%</code>',
                            '<code>%status%</code>'
                        )
                ),
                'wc_notify_customer'           => array(
                    'id'   => 'wc_notify_customer',
                    'name' => __('Notify to customer order', 'wp-sms'),
                    'type' => 'header'
                ),
                'wc_notify_customer_enable'    => array(
                    'id'      => 'wc_notify_customer_enable',
                    'name'    => __('Send SMS', 'wp-sms'),
                    'type'    => 'checkbox',
                    'options' => $options,
                    'desc'    => __('Send SMS to customer when submit the order', 'wp-sms')
                ),
                'wc_notify_customer_message'   => array(
                    'id'   => 'wc_notify_customer_message',
                    'name' => __('Message body', 'wp-sms'),
                    'type' => 'textarea',
                    'desc' => __('Enter the contents of the SMS message.', 'wp-sms') . '<br>' .
                        sprintf(
                            __('Order ID: %s, Order number: %s, Order status: %s, Order Items: %s, Order Total: %s, Order Total Currency: %s, Order Total Currency Symbol: %s, Customer name: %s, Customer family: %s, Order view URL: %s, Order payment URL: %s', 'wp-sms'),
                            '<code>%order_id%</code>',
                            '<code>%order_number%</code>',
                            '<code>%status%</code>',
                            '<code>%order_items%</code>',
                            '<code>%order_total%</code>',
                            '<code>%order_total_currency%</code>',
                            '<code>%order_total_currency_symbol%</code>',
                            '<code>%billing_first_name%</code>',
                            '<code>%billing_last_name%</code>',
                            '<code>%order_view_url%</code>',
                            '<code>%order_pay_url%</code>'
                        )
                ),
                'wc_notify_stock'              => array(
                    'id'   => 'wc_notify_stock',
                    'name' => __('Notify of stock', 'wp-sms'),
                    'type' => 'header'
                ),
                'wc_notify_stock_enable'       => array(
                    'id'      => 'wc_notify_stock_enable',
                    'name'    => __('Send SMS', 'wp-sms'),
                    'type'    => 'checkbox',
                    'options' => $options,
                    'desc'    => __('Send SMS when stock is low', 'wp-sms')
                ),
                'wc_notify_stock_receiver'     => array(
                    'id'   => 'wc_notify_stock_receiver',
                    'name' => __('SMS receiver', 'wp-sms'),
                    'type' => 'text',
                    'desc' => __('Please enter mobile number for get sms. You can separate the numbers with the Latin comma.', 'wp-sms')
                ),
                'wc_notify_stock_message'      => array(
                    'id'   => 'wc_notify_stock_message',
                    'name' => __('Message body', 'wp-sms'),
                    'type' => 'textarea',
                    'desc' => __('Enter the contents of the SMS message.', 'wp-sms') . '<br>' .
                        sprintf(
                            __('Product ID: %s, Product name: %s', 'wp-sms'),
                            '<code>%product_id%</code>',
                            '<code>%product_name%</code>'
                        )
                ),
                'wc_notify_status'             => array(
                    'id'   => 'wc_notify_status',
                    'name' => __('Notify of status', 'wp-sms'),
                    'type' => 'header'
                ),
                'wc_notify_status_enable'      => array(
                    'id'      => 'wc_notify_status_enable',
                    'name'    => __('Send SMS', 'wp-sms'),
                    'type'    => 'checkbox',
                    'options' => $options,
                    'desc'    => __('Send SMS to customer when status is changed', 'wp-sms')
                ),
                'wc_notify_status_message'     => array(
                    'id'   => 'wc_notify_status_message',
                    'name' => __('Message body', 'wp-sms'),
                    'type' => 'textarea',
                    'desc' => __('Enter the contents of the SMS message.', 'wp-sms') . '<br>' .
                        sprintf(
                            __('Order status: %s, Order number: %s, Customer name: %s, Customer family: %s, Order view URL: %s, Order payment URL: %s', 'wp-sms'),
                            '<code>%status%</code>',
                            '<code>%order_number%</code>',
                            '<code>%customer_first_name%</code>',
                            '<code>%customer_last_name%</code>',
                            '<code>%order_view_url%</code>',
                            '<code>%order_pay_url%</code>'
                        )
                ),
                'wc_notify_by_status'          => array(
                    'id'   => 'wc_notify_by_status',
                    'name' => __('Notify by status', 'wp-sms'),
                    'type' => 'header'
                ),
                'wc_notify_by_status_enable'   => array(
                    'id'      => 'wc_notify_by_status_enable',
                    'name'    => __('Send SMS', 'wp-sms'),
                    'type'    => 'checkbox',
                    'options' => $options,
                    'desc'    => __('Send SMS to customer by order status', 'wp-sms')
                ),
                'wc_notify_by_status_content'  => array(
                    'id'   => 'wc_notify_by_status_content',
                    'name' => __('Order Status & Message', 'wp-sms'),
                    'type' => 'repeater',
                    'desc' => __('Add Order Status & Write Message Body Per Order Status', 'wp-sms')
                )
            );
        } else {
            $wc_settings = array(
                'wc_fields' => array(
                    'id'   => 'wc_fields',
                    'name' => __('Not active', 'wp-sms'),
                    'type' => 'notice',
                    'desc' => __('WooCommerce should be installed to show the options.', 'wp-sms')
                ));
        }

        // Set Easy Digital Downloads settings
        if (class_exists('Easy_Digital_Downloads')) {
            $edd_settings = array(
                'edd_fields'                  => array(
                    'id'   => 'edd_fields',
                    'name' => __('Fields', 'wp-sms'),
                    'type' => 'header'
                ),
                'edd_mobile_field'            => array(
                    'id'      => 'edd_mobile_field',
                    'name'    => __('Mobile field', 'wp-sms'),
                    'type'    => 'checkbox',
                    'options' => $options,
                    'desc'    => __('Add mobile field to checkout page', 'wp-sms')
                ),
                'edd_notify_order'            => array(
                    'id'   => 'edd_notify_order',
                    'name' => __('Notify for new order', 'wp-sms'),
                    'type' => 'header'
                ),
                'edd_notify_order_enable'     => array(
                    'id'      => 'edd_notify_order_enable',
                    'name'    => __('Send SMS', 'wp-sms'),
                    'type'    => 'checkbox',
                    'options' => $options,
                    'desc'    => __('Send SMS to number when a payment is marked as complete.', 'wp-sms')
                ),
                'edd_notify_order_receiver'   => array(
                    'id'   => 'edd_notify_order_receiver',
                    'name' => __('SMS receiver', 'wp-sms'),
                    'type' => 'text',
                    'desc' => __('Please enter mobile number for get sms. You can separate the numbers with the Latin comma.', 'wp-sms')
                ),
                'edd_notify_order_message'    => array(
                    'id'   => 'edd_notify_order_message',
                    'name' => __('Message body', 'wp-sms'),
                    'type' => 'textarea',
                    'desc' => __('Enter the contents of the SMS message.', 'wp-sms') . '<br>' .
                        sprintf(
                            __('Email: %s, First name: %s, Last name: %s', 'wp-sms'),
                            '<code>%edd_email%</code>',
                            '<code>%edd_first%</code>',
                            '<code>%edd_last%</code>'
                        )
                ),
                'edd_notify_customer'         => array(
                    'id'   => 'edd_notify_customer',
                    'name' => __('Notify to customer order', 'wp-sms'),
                    'type' => 'header'
                ),
                'edd_notify_customer_enable'  => array(
                    'id'      => 'edd_notify_customer_enable',
                    'name'    => __('Send SMS', 'wp-sms'),
                    'type'    => 'checkbox',
                    'options' => $options,
                    'desc'    => __('Send SMS to customer when a payment is marked as complete.', 'wp-sms')
                ),
                'edd_notify_customer_message' => array(
                    'id'   => 'edd_notify_customer_message',
                    'name' => __('Message body', 'wp-sms'),
                    'type' => 'textarea',
                    'desc' => __('Enter the contents of the SMS message.', 'wp-sms') . '<br>' .
                        sprintf(
                            __('Email: %s, First name: %s, Last name: %s', 'wp-sms'),
                            '<code>%edd_email%</code>',
                            '<code>%edd_first%</code>',
                            '<code>%edd_last%</code>'
                        )
                )
            );
        } else {
            $edd_settings = array(
                'edd_fields' => array(
                    'id'   => 'edd_fields',
                    'name' => __('Not active', 'wp-sms'),
                    'type' => 'notice',
                    'desc' => __('Easy Digital Downloads should be installed to show the options.', 'wp-sms')
                ));
        }

        // Set Jobs settings
        if (class_exists('WP_Job_Manager')) {
            $job_settings = array(
                'job_fields'                  => array(
                    'id'   => 'job_fields',
                    'name' => __('Mobile field', 'wp-sms'),
                    'type' => 'header'
                ),
                'job_mobile_field'            => array(
                    'id'      => 'job_mobile_field',
                    'name'    => __('Mobile field', 'wp-sms'),
                    'type'    => 'checkbox',
                    'options' => $options,
                    'desc'    => __('Add Mobile field to Post a job form', 'wp-sms')
                ),
                'job_display_mobile_number'   => array(
                    'id'      => 'job_display_mobile_number',
                    'name'    => __('Display Mobile', 'wp-sms'),
                    'type'    => 'checkbox',
                    'options' => $options,
                    'desc'    => __('Display Mobile number on the single job page', 'wp-sms')
                ),
                'job_notify'                  => array(
                    'id'   => 'job_notify',
                    'name' => __('Notify for new job', 'wp-sms'),
                    'type' => 'header'
                ),
                'job_notify_status'           => array(
                    'id'      => 'job_notify_status',
                    'name'    => __('Send SMS', 'wp-sms'),
                    'type'    => 'checkbox',
                    'options' => $options,
                    'desc'    => __('Send SMS when submit new job', 'wp-sms')
                ),
                'job_notify_receiver'         => array(
                    'id'   => 'job_notify_receiver',
                    'name' => __('SMS receiver', 'wp-sms'),
                    'type' => 'select',
                    'options' => array(
                        'subscriber' => __('Subscriber(s)', 'wp-sms'),
                        'number'      => __('Number(s)', 'wp-sms')
                    ),
                    'desc' => __('Please select the SMS receiver(s).', 'wp-sms')
                ),
                'job_notify_receiver_subscribers'        => array(
                    'id'      => 'job_notify_receiver_subscribers',
                    'name'    => __('Subscribe group', 'wp-sms'),
                    'type'    => 'select',
                    'options' => $subscribe_groups,
                    'desc'    => __('Please select the group of subscribers that you want to receive the SMS.', 'wp-sms')
                ),
                'job_notify_receiver_numbers'         => array(
                    'id'   => 'job_notify_receiver_numbers',
                    'name' => __('Number(s)', 'wp-sms'),
                    'type' => 'text',
                    'desc' => __('Please enter mobile number for get sms. You can separate the numbers with the Latin comma.', 'wp-sms')
                ),
                'job_notify_message'          => array(
                    'id'   => 'job_notify_message',
                    'name' => __('Message body', 'wp-sms'),
                    'type' => 'textarea',
                    'desc' => __('Enter the contents of the SMS message.', 'wp-sms') . '<br>' .
                        sprintf(
                            __('Job ID: %s, Job Title: %s, Job Description: %s, Job Location: %s, Job Type: %s, Company Mobile: %s, Company Name: %s, Company Website: %s', 'wp-sms'),
                            '<code>%job_id%</code>',
                            '<code>%job_title%</code>',
                            '<code>%job_description%</code>',
                            '<code>%job_location%</code>',
                            '<code>%job_type%</code>',
                            '<code>%job_mobile%</code>',
                            '<code>%company_name%</code>',
                            '<code>%website%</code>'
                        )
                ),
                'job_notify_employer'         => array(
                    'id'   => 'job_notify_employer',
                    'name' => __('Notify to Employer', 'wp-sms'),
                    'type' => 'header'
                ),
                'job_notify_employer_status'  => array(
                    'id'      => 'job_notify_employer_status',
                    'name'    => __('Send SMS', 'wp-sms'),
                    'type'    => 'checkbox',
                    'options' => $options,
                    'desc'    => __('Send SMS to employer when the job approved', 'wp-sms')
                ),
                'job_notify_employer_message' => array(
                    'id'   => 'job_notify_employer_message',
                    'name' => __('Message body', 'wp-sms'),
                    'type' => 'textarea',
                    'desc' => __('Enter the contents of the SMS message.', 'wp-sms') . '<br>' .
                        sprintf(
                            __('Job ID: %s, Job Title: %s, Job Description: %s, Job Location: %s, Job Type: %s, Company Name: %s, Company Website: %s', 'wp-sms'),
                            '<code>%job_id%</code>',
                            '<code>%job_title%</code>',
                            '<code>%job_description%</code>',
                            '<code>%job_location%</code>',
                            '<code>%job_type%</code>',
                            '<code>%job_mobile%</code>',
                            '<code>%company_name%</code>',
                            '<code>%website%</code>'
                        )
                )
            );
        } else {
            $job_settings = array(
                'job_fields' => array(
                    'id'   => 'job_fields',
                    'name' => __('Not active', 'wp-sms'),
                    'type' => 'notice',
                    'desc' => __('Job Manager should be installed to show the options.', 'wp-sms')
                ));
        }

        // Set Awesome settings
        if (class_exists('Awesome_Support')) {
            $as_settings = array(
                'as_notify_new_ticket'                 => array(
                    'id'   => 'as_notify_new_ticket',
                    'name' => __('Notify for new ticket', 'wp-sms'),
                    'type' => 'header'
                ),
                'as_notify_open_ticket_status'         => array(
                    'id'      => 'as_notify_open_ticket_status',
                    'name'    => __('Send SMS', 'wp-sms'),
                    'type'    => 'checkbox',
                    'options' => $options,
                    'desc'    => __('Send SMS to admin when the user opened a new ticket.', 'wp-sms')
                ),
                'as_notify_open_ticket_message'        => array(
                    'id'   => 'as_notify_open_ticket_message',
                    'name' => __('Message body', 'wp-sms'),
                    'type' => 'textarea',
                    'desc' => __('Enter the contents of the SMS message.', 'wp-sms') . '<br>' .
                        sprintf(
                            __('Ticket Content: %s, Ticket Title: %s, Created by: %s', 'wp-sms'),
                            '<code>%ticket_content%</code>',
                            '<code>%ticket_title%</code>',
                            '<code>%ticket_username%</code>'
                        )
                ),
                'as_notify_admin_reply_ticket'         => array(
                    'id'   => 'as_notify_admin_reply_ticket',
                    'name' => __('Notify admin for get reply', 'wp-sms'),
                    'type' => 'header'
                ),
                'as_notify_admin_reply_ticket_status'  => array(
                    'id'      => 'as_notify_admin_reply_ticket_status',
                    'name'    => __('Send SMS', 'wp-sms'),
                    'type'    => 'checkbox',
                    'options' => $options,
                    'desc'    => __('Send SMS to admin when the user replied the ticket.', 'wp-sms')
                ),
                'as_notify_admin_reply_ticket_message' => array(
                    'id'   => 'as_notify_admin_reply_ticket_message',
                    'name' => __('Message body', 'wp-sms'),
                    'type' => 'textarea',
                    'desc' => __('Enter the contents of the SMS message.', 'wp-sms') . '<br>' .
                        sprintf(
                            __('Ticket Content: %s, Ticket Title: %s, Replied by: %s', 'wp-sms'),
                            '<code>%reply_content%</code>',
                            '<code>%reply_title%</code>',
                            '<code>%reply_username%</code>'
                        )
                ),
                'as_notify_user_reply_ticket'          => array(
                    'id'   => 'as_notify_user_reply_ticket',
                    'name' => __('Notify user for get reply', 'wp-sms'),
                    'type' => 'header'
                ),
                'as_notify_user_reply_ticket_status'   => array(
                    'id'      => 'as_notify_user_reply_ticket_status',
                    'name'    => __('Send SMS', 'wp-sms'),
                    'type'    => 'checkbox',
                    'options' => $options,
                    'desc'    => __('Send SMS to user when the admin replied the ticket. Please make sure the "Add Mobile number field" option is enabled in the Settings > Features', 'wp-sms')
                ),
                'as_notify_user_reply_ticket_message'  => array(
                    'id'   => 'as_notify_user_reply_ticket_message',
                    'name' => __('Message body', 'wp-sms'),
                    'type' => 'textarea',
                    'desc' => __('Enter the contents of the SMS message.', 'wp-sms') . '<br>' .
                        sprintf(
                            __('Ticket Content: %s, Ticket Title: %s, Created by: %s', 'wp-sms'),
                            '<code>%reply_content%</code>',
                            '<code>%reply_title%</code>',
                            '<code>%reply_username%</code>'
                        )
                )
            );
        } else {
            $as_settings = array(
                'as_notify_new_ticket' => array(
                    'id'   => 'as_notify_new_ticket',
                    'name' => __('Not active', 'wp-sms'),
                    'type' => 'notice',
                    'desc' => __('Awesome Support should be installed to show the options.', 'wp-sms')
                ));
        }

        // Get Gravityforms
        if (class_exists('RGFormsModel')) {
            $forms = \RGFormsModel::get_forms(null, 'title');
            $more_fields = '';

            foreach ($forms as $form) {
                $form_fields = Gravityforms::get_field($form->id);

                if (is_array($form_fields) && count($form_fields)) {
                    $more_fields = ', ';
                    foreach ($form_fields as $key => $value) {
                        $more_fields .= "Field {$value}: <code>%field-{$key}%</code>, ";
                    }

                    $more_fields = rtrim($more_fields, ', ');
                }

                $gf_forms['gf_notify_form_' . $form->id]          = array(
                    'id'   => 'gf_notify_form_' . $form->id,
                    'name' => sprintf(__('Form notifications (%s)', 'wp-sms'), $form->title),
                    'type' => 'header',
                    'desc' => sprintf(__('By enabling this option you can send SMS notification once the %s form is submitted', 'wp-sms'), $form->title),
                    'doc'  => '/resources/integrate-wp-sms-pro-with-gravity-forms/',
                );
                $gf_forms['gf_notify_enable_form_' . $form->id]   = array(
                    'id'      => 'gf_notify_enable_form_' . $form->id,
                    'name'    => __('Send SMS to a number', 'wp-sms'),
                    'type'    => 'checkbox',
                    'options' => $options,
                );
                $gf_forms['gf_notify_receiver_form_' . $form->id] = array(
                    'id'   => 'gf_notify_receiver_form_' . $form->id,
                    'name' => __('Phone number(s)', 'wp-sms'),
                    'type' => 'text',
                    'desc' => __('Enter the mobile number(s) to receive SMS, to separate numbers, use the latin comma.', 'wp-sms')
                );
                $gf_forms['gf_notify_message_form_' . $form->id]  = array(
                    'id'   => 'gf_notify_message_form_' . $form->id,
                    'name' => __('Message body', 'wp-sms'),
                    'type' => 'textarea',
                    'desc' => __('Enter your message content.', 'wp-sms') . '<br>' .
                        sprintf(
                            __('Form name: %s, IP: %s, Form url: %s, User agent: %s, Content form: %s', 'wp-sms'),
                            '<code>%title%</code>',
                            '<code>%ip%</code>',
                            '<code>%source_url%</code>',
                            '<code>%user_agent%</code>',
                            '<code>%content%</code>'
                        ) . $more_fields
                );

                if (Gravityforms::get_field($form->id)) {
                    $gf_forms['gf_notify_enable_field_form_' . $form->id]   = array(
                        'id'      => 'gf_notify_enable_field_form_' . $form->id,
                        'name'    => __('Send SMS to field', 'wp-sms'),
                        'type'    => 'checkbox',
                        'options' => $options,
                    );
                    $gf_forms['gf_notify_receiver_field_form_' . $form->id] = array(
                        'id'      => 'gf_notify_receiver_field_form_' . $form->id,
                        'name'    => __('A field of the form', 'wp-sms'),
                        'type'    => 'select',
                        'options' => Gravityforms::get_field($form->id),
                        'desc'    => __('Select the field of your form.', 'wp-sms')
                    );
                    $gf_forms['gf_notify_message_field_form_' . $form->id]  = array(
                        'id'   => 'gf_notify_message_field_form_' . $form->id,
                        'name' => __('Message body', 'wp-sms'),
                        'type' => 'textarea',
                        'desc' => __('Enter your message content.', 'wp-sms') . '<br>' .
                            sprintf(
                                __('Form name: %s, IP: %s, Form url: %s, User agent: %s, Content form: %s', 'wp-sms'),
                                '<code>%title%</code>',
                                '<code>%ip%</code>',
                                '<code>%source_url%</code>',
                                '<code>%user_agent%</code>',
                                '<code>%content%</code>'
                            ) . $more_fields
                    );
                }
            }
        } else {
            $gf_forms['gf_notify_form'] = array(
                'id'   => 'gf_notify_form',
                'name' => __('Not active', 'wp-sms'),
                'type' => 'notice',
                'desc' => __('Gravity Forms should be enable to run this tab', 'wp-sms')
            );
        }

        // Get Ultimate Members
        if (function_exists('um_user')) {
            $um_options['um_field_header']          = array(
                'id'   => 'um_field_header',
                'name' => __('General', 'wp-sms'),
                'type' => 'header'
            );
            $um_options['um_field']                 = array(
                'id'   => 'um_field',
                'name' => __('Mobile number field', 'wp-sms'),
                'type' => 'checkbox',
                'desc' => __('Sync Mobile number from Ultimate Members mobile number form field.', 'wp-sms'),
            );
            $um_options['um_sync_field_name']       = array(
                'id'      => 'um_sync_field_name',
                'name'    => __('Select the purpose field in registration form'),
                'type'    => 'select',
                'options' => $this->get_um_register_form_fields(),
                'std'     => 'mobile_number',
                'desc'    => __('Select the field from ultimate member register form that you want to be synced(Default is "Mobile Number").', 'wp-sms')
            );
            $um_options['um_sync_previous_members'] = array(
                'id'   => 'um_sync_previous_members',
                'name' => __('Sync old member too?'),
                'type' => 'checkbox',
                'desc' => __('Sync the old mobile numbers which registered before enabling the previous option in Ultimate Members.', 'wp-sms')
            );
        } else {
            $um_options['um_notify_form'] = array(
                'id'   => 'um_notify_form',
                'name' => __('Not active', 'wp-sms'),
                'type' => 'notice',
                'desc' => __('Ultimate Members should be enable to run this tab', 'wp-sms')
            );
        }

        // Get Quform
        if (class_exists('Quform_Repository')) {
            $quform = new \Quform_Repository();
            $forms  = $quform->allForms();

            if ($forms) {
                foreach ($forms as $form):
                    $form_fields = Quform::get_fields($form['id']);
                $more_qf_fields = ', ';
                if (is_array($form_fields) && count($form_fields)) {
                    foreach ($form_fields as $key => $value) {
                        $more_qf_fields .= "Field {$value}: <code>%field-{$key}%</code>, ";
                    }
                    $more_qf_fields = rtrim($more_qf_fields, ', ');
                }

                $qf_forms['qf_notify_form_' . $form['id']]          = array(
                        'id'   => 'qf_notify_form_' . $form['id'],
                        'name' => sprintf(__('Form notifications: (%s)', 'wp-sms'), $form['name']),
                        'type' => 'header',
                        'desc' => sprintf(__('By enabling this option you can send SMS notification once the %s form is submitted', 'wp-sms'), $form['name']),
                        'doc'  => '/resources/integrate-wp-sms-pro-with-quform/',
                    );
                $qf_forms['qf_notify_enable_form_' . $form['id']]   = array(
                        'id'      => 'qf_notify_enable_form_' . $form['id'],
                        'name'    => __('Send SMS to a number', 'wp-sms'),
                        'type'    => 'checkbox',
                        'options' => $options,
                    );
                $qf_forms['qf_notify_receiver_form_' . $form['id']] = array(
                        'id'   => 'qf_notify_receiver_form_' . $form['id'],
                        'name' => __('Phone number(s)', 'wp-sms'),
                        'type' => 'text',
                        'desc' => __('Enter the mobile number(s) to receive SMS, to separate numbers, use the latin comma.', 'wp-sms')
                    );
                $qf_forms['qf_notify_message_form_' . $form['id']]  = array(
                        'id'   => 'qf_notify_message_form_' . $form['id'],
                        'name' => __('Message body', 'wp-sms'),
                        'type' => 'textarea',
                        'desc' => __('Enter your message content.', 'wp-sms') . '<br>' .
                            sprintf(
                                __('Form name: %s, Form url: %s, Referring url: %s, Form content: %s', 'wp-sms'),
                                '<code>%post_title%</code>',
                                '<code>%form_url%</code>',
                                '<code>%referring_url%</code>',
                                '<code>%content%</code>'
                            ) . $more_qf_fields
                    );

                if ($form['elements']) {
                    $qf_forms['qf_notify_enable_field_form_' . $form['id']]   = array(
                            'id'      => 'qf_notify_enable_field_form_' . $form['id'],
                            'name'    => __('Send SMS to field', 'wp-sms'),
                            'type'    => 'checkbox',
                            'options' => $options,
                        );
                    $qf_forms['qf_notify_receiver_field_form_' . $form['id']] = array(
                            'id'      => 'qf_notify_receiver_field_form_' . $form['id'],
                            'name'    => __('A field of the form', 'wp-sms'),
                            'type'    => 'select',
                            'options' => $form_fields,
                            'desc'    => __('Select the field of your form.', 'wp-sms')
                        );
                    $qf_forms['qf_notify_message_field_form_' . $form['id']]  = array(
                            'id'   => 'qf_notify_message_field_form_' . $form['id'],
                            'name' => __('Message body', 'wp-sms'),
                            'type' => 'textarea',
                            'desc' => __('Enter your message content.', 'wp-sms') . '<br>' .
                                sprintf(
                                    __('Form name: %s, Form url: %s, Referring url: %s, Form content: %s', 'wp-sms'),
                                    '<code>%post_title%</code>',
                                    '<code>%form_url%</code>',
                                    '<code>%referring_url%</code>',
                                    '<code>%content%</code>'
                                ) . $more_qf_fields
                        );
                }
                endforeach;
            } else {
                $qf_forms['qf_notify_form'] = array(
                    'id'   => 'qf_notify_form',
                    'name' => __('No data', 'wp-sms'),
                    'type' => 'notice',
                    'desc' => __('There is no form available on Quform plugin, please first add your forms.', 'wp-sms')
                );
            }
        } else {
            $qf_forms['qf_notify_form'] = array(
                'id'   => 'qf_notify_form',
                'name' => __('Not active', 'wp-sms'),
                'type' => 'notice',
                'desc' => __('Quform should be enable to run this tab', 'wp-sms')
            );
        }

        $settings = apply_filters('wp_sms_registered_settings', array(
            /**
             * General fields
             */
            'general'              => apply_filters('wp_sms_general_settings', array(
                'admin_title'         => array(
                    'id'   => 'admin_title',
                    'name' => __('Administrator', 'wp-sms'),
                    'type' => 'header'
                ),
                'admin_mobile_number' => array(
                    'id'   => 'admin_mobile_number',
                    'name' => __('Admin Mobile Number', 'wp-sms'),
                    'type' => 'text',
                    'desc' => __('Admin mobile number for get any sms notifications', 'wp-sms')
                ),
                'mobile_county_code'  => array(
                    'id'   => 'mobile_county_code',
                    'name' => __('Country Code Prefix', 'wp-sms'),
                    'type' => 'select',
                    'desc' => __('Choices the mobile country code if you want to append that code before the numbers while sending the SMS, you can leave it if the recipients is not belong to a specific country', 'wp-sms'),
                    'options' => array_merge(['0' => __('No country code', 'wp-sms')], wp_sms_get_countries()),
                    'attributes' => ['class' => 'js-wpsms-select2'],
                ),
                'mobile_field'                             => array(
                    'id'   => 'mobile_field',
                    'name' => __('Mobile field', 'wp-sms'),
                    'type' => 'header'
                ),
                'add_mobile_field'                         => array(
                    'id'      => 'add_mobile_field',
                    'name'    => __('Add Mobile Number Field', 'wp-sms'),
                    'type'    => 'checkbox',
                    'options' => $options,
                    'desc'    => __('Add Mobile number to user profile and register form.', 'wp-sms')
                ),
                'mobile_terms_field_place_holder'  => array(
                    'id'   => 'mobile_terms_field_place_holder',
                    'name' => __('Mobile Field Placeholder', 'wp-sms'),
                    'type' => 'text',
                    'desc' => __('Help your clients to enter their mobile number in a correct format by choosing a proper placeholder.', 'wp-sms')
                ),
                'international_mobile'                     => array(
                    'id'      => 'international_mobile',
                    'name'    => __('International Telephone Input', 'wp-sms'),
                    'type'    => 'checkbox',
                    'options' => $options,
                    'desc'    => __('Adds a flag dropdown to any mobile number input field', 'wp-sms')
                ),
                'international_mobile_only_countries'      => array(
                    'id'      => 'international_mobile_only_countries',
                    'name'    => __('Only Countries', 'wp-sms'),
                    'type'    => 'countryselect',
                    'options' => $this->getCountriesList(),
                    'desc'    => __('In the dropdown, display only the countries you specify.', 'wp-sms')
                ),
                'international_mobile_preferred_countries' => array(
                    'id'      => 'international_mobile_preferred_countries',
                    'name'    => __('Preferred Countries', 'wp-sms'),
                    'type'    => 'countryselect',
                    'options' => $this->getCountriesList(),
                    'desc'    => __('Specify the countries to appear at the top of the list.', 'wp-sms')
                ),
                'mobile_terms_minimum'             => array(
                    'id'   => 'mobile_terms_minimum',
                    'name' => __('Minimum Length Number', 'wp-sms'),
                    'type' => 'number'
                ),
                'mobile_terms_maximum'             => array(
                    'id'   => 'mobile_terms_maximum',
                    'name' => __('Maximum Length Number', 'wp-sms'),
                    'type' => 'number'
                ),
                'admin_title_privacy' => array(
                    'id'   => 'admin_title_privacy',
                    'name' => __('Privacy', 'wp-sms'),
                    'type' => 'header',
                    'doc'  => '/6064/gdpr-compliant-in-wp-sms/',
                    'desc' => __('GDPR Compliant', 'wp-sms'),
                ),
                'gdpr_compliance'     => array(
                    'id'      => 'gdpr_compliance',
                    'name'    => __('GDPR Enhancements', 'wp-sms'),
                    'type'    => 'checkbox',
                    'options' => $options,
                    'desc'    => __('Enable GDPR related features in this page.', 'wp-sms')
                ),
            )),

            /**
             * Gateway fields
             */
            'gateway'              => apply_filters('wp_sms_gateway_settings', array(
                // Gateway
                'gateway_title'             => array(
                    'id'   => 'gateway_title',
                    'name' => __('SMS Gateway Configuration', 'wp-sms'),
                    'type' => 'header'
                ),
                'gateway_name'              => array(
                    'id'      => 'gateway_name',
                    'name'    => __('Choose the Gateway', 'wp-sms'),
                    'type'    => 'advancedselect',
                    'options' => Gateway::gateway(),
                    'desc'    => __('Select the SMS Gateway from which you want to send the SMS.', 'wp-sms')
                ),
                'gateway_help'              => array(
                    'id'      => 'gateway_help',
                    'name'    => __('Gateway Notice', 'wp-sms'),
                    'type'    => 'html',
                    'options' => Gateway::help(),
                ),
                'gateway_username'          => array(
                    'id'   => 'gateway_username',
                    'name' => __('API Username', 'wp-sms'),
                    'type' => 'text',
                    'desc' => __('Enter API username of gateway', 'wp-sms')
                ),
                'gateway_password'          => array(
                    'id'   => 'gateway_password',
                    'name' => __('API Password', 'wp-sms'),
                    'type' => 'text',
                    'desc' => __('Enter API password of gateway', 'wp-sms')
                ),
                'gateway_sender_id'         => array(
                    'id'   => 'gateway_sender_id',
                    'name' => __('Sender ID/Number', 'wp-sms'),
                    'type' => 'text',
                    'std'  => Gateway::from(),
                    'desc' => __('Sender number or sender ID', 'wp-sms')
                ),
                'gateway_key'               => array(
                    'id'   => 'gateway_key',
                    'name' => __('API Key', 'wp-sms'),
                    'type' => 'text',
                    'desc' => __('Enter API key of gateway', 'wp-sms')
                ),
                // Gateway status
                'gateway_status_title'      => array(
                    'id'   => 'gateway_status_title',
                    'name' => __('Gateway Status', 'wp-sms'),
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
                    'name'    => __('Balance', 'wp-sms'),
                    'type'    => 'html',
                    'options' => Gateway::response(),
                ),
                'incoming_message'          => array(
                    'id'      => 'incoming_message',
                    'name'    => __('Support Incoming Message?'),
                    'type'    => 'html',
                    'options' => Gateway::incoming_message_status(),
                ),
                'bulk_send'                 => array(
                    'id'      => 'bulk_send',
                    'name'    => __('Send bulk SMS?', 'wp-sms'),
                    'type'    => 'html',
                    'options' => Gateway::bulk_status(),
                ),
                'media_support'             => array(
                    'id'      => 'media_support',
                    'name'    => __('Send MMS?', 'wp-sms'),
                    'type'    => 'html',
                    'options' => Gateway::mms_status(),
                ),
                // Account credit
                'account_credit_title'      => array(
                    'id'   => 'account_credit_title',
                    'name' => __('Account Balance', 'wp-sms'),
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
                    'name' => __('Message Options', 'wp-sms'),
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
                    'desc'    => __('You can enable this option to remove spaces from numbers before sending them to API.', 'wp-sms')
                )
            )),

            /**
             * SMS Newsletter fields
             */
            'newsletter'           => apply_filters('wp_sms_newsletter_settings', array(
                // SMS Newsletter
                'newsletter_title'                 => array(
                    'id'   => 'newsletter_title',
                    'name' => __('SMS Newsletter', 'wp-sms'),
                    'type' => 'header',
                ),
                'newsletter_form_groups'           => array(
                    'id'   => 'newsletter_form_groups',
                    'name' => __('Show Groups', 'wp-sms'),
                    'type' => 'checkbox',
                    'desc' => __('Enable showing Groups on Form.', 'wp-sms')
                ),
                'newsletter_form_specified_groups' => array(
                    'id'      => 'newsletter_form_specified_groups',
                    'name'    => __('Display groups', 'wp-sms'),
                    'type'    => 'multiselect',
                    'options' => array_map(function ($value) {
                        return [$value->ID => $value->name];
                    }, Newsletter::getGroups()),
                    'desc'    => __('Select which groups should be showed in the SMS newsletter form.', 'wp-sms')
                ),
                'newsletter_form_default_group' => array(
                    'id'      => 'newsletter_form_default_group',
                    'name'    => __('Default group', 'wp-sms'),
                    'type'    => 'select',
                    'options' => $subscribe_groups,
                    'desc'    => __('Choice the default group', 'wp-sms')
                ),
                'newsletter_form_verify'           => array(
                    'id'   => 'newsletter_form_verify',
                    'name' => __('Verify Subscriber', 'wp-sms'),
                    'type' => 'checkbox',
                    'desc' => __('Subscribers will receive an activation code by SMS', 'wp-sms')
                ),
                'welcome'                          => array(
                    'id'   => 'welcome',
                    'name' => __('Welcome SMS', 'wp-sms'),
                    'type' => 'header',
                    'desc' => __('By enabling this option you can send welcome SMS to subscribers'),
                    'doc'  => '/resources/send-welcome-sms-to-new-subscribers/',
                ),
                'newsletter_form_welcome'          => array(
                    'id'   => 'newsletter_form_welcome',
                    'name' => __('Status', 'wp-sms'),
                    'type' => 'checkbox',
                    'desc' => __('Enable or Disable welcome SMS.', 'wp-sms')
                ),
                'newsletter_form_welcome_text'     => array(
                    'id'   => 'newsletter_form_welcome_text',
                    'name' => __('SMS text', 'wp-sms'),
                    'type' => 'textarea',
                    'desc' => sprintf(__('Subscriber name: %s, Subscriber mobile: %s<br><br>if you would like to send unsubscribe link, check out the document.', 'wp-sms'), '<code>%subscribe_name%</code>', '<code>%subscribe_mobile%</code>'),
                ),
                //Style Setting
                'style'                            => array(
                    'id'   => 'style',
                    'name' => __('Style', 'wp-sms'),
                    'type' => 'header'
                ),
                'disable_style_in_front'           => array(
                    'id'   => 'disable_style_in_front',
                    'name' => __('Disable Frontend Style', 'wp-sms'),
                    'type' => 'checkbox',
                    'desc' => __('Check this to disable all included styling of SMS Newsletter form elements.', 'wp-sms')
                )
            )),

            /**
             * Feature fields
             */
            'advanced'              => apply_filters('wp_sms_feature_settings', array(
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
                    'desc'    => __('Add WP SMS endpoints to the WP Rest API', 'wp-sms')
                ),
                'short_url'                                 => array(
                    'id'   => 'short_url',
                    'name' => __('Bitly Short URL API', 'wp-sms'),
                    'type' => 'header',
                ),
                'short_url_status'                          => array(
                    'id'      => 'short_url_status',
                    'name'    => __('Make the URLs Shorter?', 'wp-sms'),
                    'type'    => 'checkbox',
                    'options' => $options,
                    'desc'    => __('By enabling this option, all URLs will be shorter by Bitly.com', 'wp-sms'),
                    'readonly' => !$this->proIsInstalled
                ),
                'short_url_api_token'                          => array(
                    'id'      => 'short_url_api_token',
                    'name'    => __('Access Token', 'wp-sms'),
                    'type'    => 'text',
                    'desc'    => __('Please enter your Bitly Access token here, you can get it from <a href="https://app.bitly.com/settings/api/">https://app.bitly.com/settings/api/</a>', 'wp-sms'),
                    'readonly' => !$this->proIsInstalled
                ),
            )),

            /**
             * Notifications fields
             */
            'notifications'        => apply_filters('wp_sms_notifications_settings', array(
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
                'notif_publish_new_post_receiver'   => array(
                    'id'      => 'notif_publish_new_post_receiver',
                    'name'    => __('Send Notification to?', 'wp-sms'),
                    'type'    => 'select',
                    'options' => array(
                        'subscriber' => __('Subscribers', 'wp-sms'),
                        'numbers'      => __('Number(s)', 'wp-sms')
                    ),
                    'desc'    => __('Please select the receiver of SMS Notification', 'wp-sms')
                ),
                'notif_publish_new_post_default_group'                  => array(
                    'id'      => 'notif_publish_new_post_default_group',
                    'name'    => __('Subscribe group', 'wp-sms'),
                    'type'    => 'select',
                    'options' => $subscribe_groups,
                    'desc'    => __('Choice the default group to send the SMS', 'wp-sms')
                ),
                'notif_publish_new_post_numbers'   => array(
                    'id'      => 'notif_publish_new_post_numbers',
                    'name'    => __('Number(s)', 'wp-sms'),
                    'type'    => 'text',
                    'desc'    => __('Please enter mobile number for get sms. You can separate the numbers with the Latin comma.', 'wp-sms')
                ),
                'notif_publish_new_post_force'                  => array(
                    'id'      => 'notif_publish_new_post_force',
                    'name'    => __('Force to Send?', 'wp-sms'),
                    'type'    => 'checkbox',
                    'options' => $options,
                    'desc'    => __('By enabling this option you don\'t need to enable it while publishing every time, this option make it compatible with WP-REST API as well.', 'wp-sms')
                ),
                'notif_publish_new_send_mms'                  => array(
                    'id'      => 'notif_publish_new_send_mms',
                    'name'    => __('Send MMS?', 'wp-sms'),
                    'type'    => 'checkbox',
                    'options' => $options,
                    'desc'    => __('By enabling this option, the post featured image will be sent as an MMS if your gateway supports it', 'wp-sms')
                ),
                'notif_publish_new_post_template'         => array(
                    'id'   => 'notif_publish_new_post_template',
                    'name' => __('Message body', 'wp-sms'),
                    'type' => 'textarea',
                    'desc' => __('Enter the contents of the sms message.', 'wp-sms') . '<br>' .
                        sprintf(
                            __('Post title: %s, Post content: %s, Post url: %s, Post date: %s, Post featured image URL: %s', 'wp-sms'),
                            '<code>%post_title%</code>',
                            '<code>%post_content%</code>',
                            '<code>%post_url%</code>',
                            '<code>%post_date%</code>',
                            '<code>%post_thumbnail%</code>'
                        )
                ),
                'notif_publish_new_post_words_count'      => array(
                    'id'   => 'notif_publish_new_post_words_count',
                    'name' => __('Post content words count', 'wp-sms'),
                    'type' => 'number',
                    'desc' => __('The number of word for cropping in send post notification. Default : 10', 'wp-sms')
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
                    'desc'    => __('Send an SMS to you and user when register on WordPress.', 'wp-sms')
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
                'notif_user_login_roles'   => array(
                    'id'      => 'notif_user_login_roles',
                    'name'    => __('Specific roles', 'wp-sms'),
                    'type'    => 'multiselect',
                    'options' => $this->getRoles(),
                    'desc'    => __('Select the roles of the user that you want to get notification while login.', 'wp-sms')
                ),
                'notif_user_login_template'               => array(
                    'id'   => 'notif_user_login_template',
                    'name' => __('Message body', 'wp-sms'),
                    'type' => 'textarea',
                    'desc' => __('Enter the contents of the sms message.', 'wp-sms') . '<br>' .
                        sprintf(
                            __('Username: %s, Display name: %s', 'wp-sms'),
                            '<code>%username_login%</code>',
                            '<code>%display_name%</code>'
                        )
                )
            )),

            /**
             * Contact form 7 fields
             */
            'contact_form7'          => apply_filters('wp_sms_contact_form7_settings', array(
                'cf7_title'                    => array(
                    'id'   => 'cf7_title',
                    'name' => __('SMS Notification Metabox', 'wp-sms'),
                    'type' => 'header',
                    'doc'  => '/resources/integrate-wp-sms-with-contact-form-7/',
                    'desc' => __('By this option you can add SMS notification tools in all edit forms.', 'wp-sms'),
                ),
                'cf7_metabox'                  => array(
                    'id'      => 'cf7_metabox',
                    'name'    => __('Status', 'wp-sms'),
                    'type'    => 'checkbox',
                    'options' => $options,
                    'desc'    => __('This option adds SMS Notification tab in the edit forms.', 'wp-sms')
                ),
            )),

            /*
             * Pro fields
             */
            'pro_wordpress'        => apply_filters('wp_sms_pro_wp_settings', $pro_wordpress_settings),
            'pro_buddypress'       => apply_filters('wp_sms_pro_bp_settings', $buddypress_settings),
            'pro_woocommerce'      => apply_filters('wp_sms_pro_wc_settings', $wc_settings),
            'pro_gravity_forms'    => apply_filters('wp_sms_pro_gf_settings', $gf_forms),
            'pro_quform'           => apply_filters('wp_sms_pro_qf_settings', $qf_forms),
            'pro_edd'              => apply_filters('wp_sms_pro_edd_settings', $edd_settings),
            'pro_wp_job_manager'   => apply_filters('wp_sms_job_settings', $job_settings),
            'pro_awesome_support'  => apply_filters('wp_sms_as_settings', $as_settings),
            'pro_ultimate_members' => apply_filters('wp_sms_pro_um_settings', $um_options),

            /*
             * License fields
             * @note Don't move up this line, the pro fields doesn't load, weird indeed!
             */
            'licenses'             => apply_filters('wp_sms_licenses_settings', array())
        ));

        /*
         * GDPR fields
         */
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
                'desc' => __('To get more option for GDPR, you should enable that in the general tab.', 'wp-sms')
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

        if (($constantLicenseKey && $this->isCurrentTab('licenses') && wp_sms_check_remote_license($addOnKey, $constantLicenseKey)) or $licenseStatus and $licenseKey) {
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
            } elseif (isset($_POST[$this->setting_name]["license_{$addOnKey}_key"])) {
                $licenseKey = sanitize_text_field($_POST[$this->setting_name]["license_{$addOnKey}_key"]);
            }

            if (!$licenseKey) {
                $value[$generateLicenseStatusKey] = false;
                continue;
            }

            if (wp_sms_check_remote_license($addOnKey, $licenseKey)) {
                $value[$generateLicenseStatusKey] = true;
            } else {
                $value[$generateLicenseStatusKey] = false;
            }
        }

        return $value;
    }

    public function header_callback($args)
    {
        $html = '';
        if (isset($args['desc'])) {
            $html .= $args['desc'];
        }

        if ($args['doc']) {
            $documentUrl = WP_SMS_SITE . $args['doc'];
            $html        .= sprintf('<div class="wpsms-settings-description-header"><a href="%s" target="_blank">document <span class="dashicons dashicons-external"></span></a></div>', $documentUrl);
        }

        echo "<div class='wpsms-settings-header-field'>{$html}</div>";
    }

    public function repeater_callback($args)
    {
        if (isset($this->options[ $args['id'] ])) {
            $value = $this->options[ $args['id'] ];
        } else {
            $value = isset($args['std']) ? $args['std'] : '';
        }

        $order_statuses = wc_get_order_statuses();
        ob_start(); ?>
<div class="repeater">
    <div
        data-repeater-list="wps_pp_settings[<?php echo $args['id'] ?>]">
        <?php if (is_array($value) && count($value)) : ?>
        <?php foreach ($value as $data) : ?>
        <?php $order_status = isset($data['order_status']) ? $data['order_status'] : '' ?>
        <?php $notify_status = isset($data['notify_status']) ? $data['notify_status'] : '' ?>
        <?php $message = isset($data['message']) ? $data['message'] : '' ?>

        <div class="repeater-item" data-repeater-item>
            <div style="display: block; width: 100%; margin-bottom: 15px; border-bottom: 1px solid #ccc;">
                <div style="display: block; width: 48%; float: left; margin-bottom: 15px;">
                    <select name="order_status" style="display: block; width: 100%;">
                        <option value="">- Please Choose -</option>
                        <?php foreach ($order_statuses as $status_key => $status_name) : ?>
                        <?php $key = str_replace('wc-', '', $status_key) ?>
                        <option value="<?php echo $key ?>" <?php echo ($order_status == $key) ? 'selected' : '' ?>><?php echo $status_name ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description">Please choose an order status</p>
                </div>
                <div style="display: block; width: 48%; float: right; margin-bottom: 15px;">
                    <select name="notify_status" style="display: block; width: 100%;">
                        <option value="">- Please Choose -</option>
                        <option value="1" <?php echo ($notify_status == '1') ? 'selected' : '' ?>>Enable
                        </option>
                        <option value="2" <?php echo ($notify_status == '2') ? 'selected' : '' ?>>Disable
                        </option>
                    </select>
                    <p class="description">Please select notify status</p>
                </div>
                <div style="display: block; width: 100%; margin-bottom: 15px;">
                    <textarea name="message" rows="3"
                        style="display: block; width: 100%;"><?php echo $message ?></textarea>
                    <p class="description">Enter the contents of the SMS message.</p>
                    <p class="description"><?php echo sprintf(__('Order status: %s, Order Items: %s, Order number: %s, Order Total: %s, Order Total Currency: %s, Order Total Currency Symbol: %s, Customer name: %s, Customer family: %s, Order view URL: %s, Order payment URL: %s', 'wp-sms'), '<code>%status%</code>', '<code>%order_items%</code>', '<code>%order_number%</code>', '<code>%order_total%</code>', '<code>%order_total_currency%</code>','<code>%order_total_currency_symbol%</code>', '<code>%customer_first_name%</code>', '<code>%customer_last_name%</code>', '<code>%order_view_url%</code>', '<code>%order_pay_url%</code>') ?>
                    </p>
                </div>
                <div>
                    <input type="button" value="Delete" class="button" style="margin-bottom: 15px;"
                        data-repeater-delete />
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        <?php else : ?>
        <div class="repeater-item" data-repeater-item>
            <div style="display: block; width: 100%; margin-bottom: 15px; border-bottom: 1px solid #ccc;">
                <div style="display: block; width: 48%; float: left; margin-bottom: 15px;">
                    <select name="order_status" style="display: block; width: 100%;">
                        <option value="">- Please Choose -</option>
                        <?php foreach ($order_statuses as $status_key => $status_name) : ?>
                        <?php $key = str_replace('wc-', '', $status_key) ?>
                        <option value="<?php echo $key ?>"><?php echo $status_name ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description">Please choose an order status</p>
                </div>
                <div style="display: block; width: 48%; float: right; margin-bottom: 15px;">
                    <select name="notify_status" style="display: block; width: 100%;">
                        <option value="">- Please Choose -</option>
                        <option value="1">Enable</option>
                        <option value="2">Disable</option>
                    </select>
                    <p class="description">Please select notify status</p>
                </div>
                <div style="display: block; width: 100%; margin-bottom: 15px;">
                    <textarea name="message" rows="3" style="display: block; width: 100%;"></textarea>
                    <p class="description">Enter the contents of the SMS message.</p>
                    <p class="description"><?php echo sprintf(__('Order status: %s, Order Items: %s, Order number: %s, Order Total: %s, Order Total Currency: %s, Order Total Currency Symbol: %s, Customer name: %s, Customer family: %s, Order view URL: %s, Order payment URL: %s', 'wp-sms'), '<code>%status%</code>', '<code>%order_items%</code>', '<code>%order_number%</code>', '<code>%order_total%</code>', '<code>%order_total_currency%</code>', '<code>%order_total_currency_symbol%</code>', '<code>%customer_first_name%</code>', '<code>%customer_last_name%</code>', '<code>%order_view_url%</code>', '<code>%order_pay_url%</code>') ?>
                    </p>
                </div>
                <div>
                    <input type="button" value="Delete" class="button" style="margin-bottom: 15px;"
                        data-repeater-delete />
                </div>
            </div>
        </div>
        <?php endif ?>
    </div>
    <div style="margin: 10px 0;">
        <input type="button" value="Add another order status" class="button button-primary" data-repeater-create />
        </p>
    </div>
    <?php
        echo ob_get_clean();
    }

    public function html_callback($args)
    {
        echo wp_kses_post($args['options']);
    }

    public function notice_callback($args)
    {
        echo sprintf('%s', $args['desc']);
    }

    public function checkbox_callback($args)
    {
        $checked = isset($this->options[$args['id']]) ? checked(1, $this->options[$args['id']], false) : '';
        $html    = sprintf('<input type="checkbox" id="' . $this->setting_name . '[%1$s]" name="' . $this->setting_name . '[%1$s]" value="1" %2$s /><label for="' . $this->setting_name . '[%1$s]"> ' . __('Active', 'wp-sms') . '</label><p class="description">%3$s</p>', esc_attr($args['id']), esc_attr($checked), wp_kses_post($args['desc']));
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
        $html .= sprintf('<input name="' . $this->setting_name . '[%1$s]"" id="' . $this->setting_name . '[%1$s][%2$s]" type="radio" value="%2$s" %3$s /><label for="' . $this->setting_name . '[%1$s][%2$s]">%4$s</label>&nbsp;&nbsp;', esc_attr($args['id']), esc_attr($key), checked(true, $checked, false), $option);
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
        $html        = sprintf('<input dir="auto" type="text" class="%1$s-text" id="' . $this->setting_name . '[%2$s]" name="' . $this->setting_name . '[%2$s]" value="%3$s"/>%4$s<p class="description">%5$s</p>', esc_attr($size), esc_attr($args['id']), esc_attr(stripslashes($value)), $after_input, wp_kses_post($args['desc']));
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
        $html = sprintf('<input dir="auto" type="number" step="%1$s" max="%2$s" min="%3$s" class="%4$s-text" id="' . $this->setting_name . '[%5$s]" name="' . $this->setting_name . '[%5$s]" value="%6$s"/><p class="description"> %7$s</p>', esc_attr($step), esc_attr($max), esc_attr($min), esc_attr($size), esc_attr($args['id']), esc_attr(stripslashes($value)), wp_kses_post($args['desc']));
        echo $html;
    }

    public function textarea_callback($args)
    {
        if (isset($this->options[$args['id']])) {
            $value = $this->options[$args['id']];
        } else {
            $value = isset($args['std']) ? $args['std'] : '';
        }

        $html = sprintf('<textarea dir="auto" class="large-text" cols="50" rows="5" id="' . $this->setting_name . '[%1$s]" name="' . $this->setting_name . '[%1$s]">%2$s</textarea><div class="description"> %3$s</div>', esc_attr($args['id']), esc_textarea(stripslashes($value)), wp_kses_post($args['desc']));
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
        $html = sprintf('<input type="password" class="%1$s-text" id="' . $this->setting_name . '[%2$s]" name="' . $this->setting_name . '[%2$s]" value="%3$s"/><p class="description"> %4$s</p>', esc_attr($size), esc_attr($args['id']), esc_attr($value), wp_kses_post($args['desc']));

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

        $attributes = array_map(function ($key, $value) {
            return sprintf('%s="%s"', $key, $value);
        }, array_keys($args['attributes']), array_values($args['attributes']));

        $html = sprintf('<select id="' . $this->setting_name . '[%1$s]" name="' . $this->setting_name . '[%1$s]" %2$s>', esc_attr($args['id']), implode(' ', $attributes));

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

        $html     = sprintf('<select id="' . $this->setting_name . '[%1$s]" name="' . $this->setting_name . '[%1$s][]" multiple="true" class="js-wpsms-select2"/>', esc_attr($args['id']));
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

        $html     = sprintf('<select id="' . $this->setting_name . '[%1$s]" name="' . $this->setting_name . '[%1$s][]" multiple="true" class="js-wpsms-select2"/>', esc_attr($args['id']));
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

        $class_name = 'js-wpsms-select2';
        $html       = sprintf('<select class="%1$s" id="' . $this->setting_name . '[%2$s]" name="' . $this->setting_name . '[%2$s]">', esc_attr($class_name), esc_attr($args['id']));

        foreach ($args['options'] as $key => $v) {
            $html .= '<optgroup label="' . ucfirst(str_replace('_', ' ', $key)) . '">';

            foreach ($v as $option => $name) {
                $disabled = '';

                if (!$this->proIsInstalled && array_column(Gateway::$proGateways, $option)) {
                    $disabled = ' disabled';
                    $name     .= '<span> ' . __('- (Pro Pack)', 'wp-sms') . '</span>';
                }

                $selected = selected($option, $value, false);
                $html     .= sprintf('<option value="%1$s" %2$s %3$s>%4$s</option>', esc_attr($option), esc_attr($selected), esc_attr($disabled), ucfirst($name));
            }

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

        $html = sprintf('<select id="' . $this->setting_name . '[%1$s]" name="' . $this->setting_name . '[%1$s]">', esc_attr($args['id']));

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

        $id = $args['id'];

        if (isset($this->options[$id])) {
            $value = $this->options[$id];
        } else {
            $value = isset($args['std']) ? $args['std'] : '';
        }

        if ($wp_version >= 3.3 && function_exists('wp_editor')) {
            $html = wp_editor(stripslashes($value), "$this->setting_name[$id]", array('textarea_name' => "$this->setting_name[$id]"));
        } else {
            $html = sprintf('<textarea class="large-text" rows="10" id="' . $this->setting_name . '[%1$s]" name="' . $this->setting_name . '[%1$s]">' . esc_textarea(stripslashes($value)) . '</textarea>', esc_attr($args['id']));
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
        $html = sprintf('<input type="text" class="%1$s-text wpsms_upload_field" id="' . $this->setting_name . '[%2$s]" name="' . $this->setting_name . '[%2$s]" value="%3$s"/><span>&nbsp;<input type="button" class="' . $this->setting_name . '_upload_button button-secondary" value="%4$s"/></span><p class="description"> %5$s</p>', esc_attr($size), esc_attr($args['id']), esc_attr(stripslashes($value)), __('Upload File', 'wpsms'), wp_kses_post($args['desc']));

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
        $html    = sprintf('<input type="text" class="wpsms-color-picker" id="' . $this->setting_name . '[%1$s]" name="' . $this->setting_name . '[%1$s]" value="%2$s" data-default-color="%3$s" /><p class="description"> %4$s</p>', esc_attr($args['id']), esc_attr($value), esc_attr($default), wp_kses_post($args['desc']));

        echo $html;
    }

    public function render_settings()
    {
        $active_tab        = isset($_GET['tab']) && array_key_exists($_GET['tab'], $this->get_tabs()) ? sanitize_text_field($_GET['tab']) : 'general';
        $contentRestricted = in_array($active_tab, $this->proTabs) && !$this->proIsInstalled;
        ob_start(); ?>
    <div class="wrap wpsms-wrap wpsms-settings-wrap">
        <?php require_once WP_SMS_DIR . 'includes/templates/header.php'; ?>
        <div class="wpsms-wrap__main">
            <?php do_action('wp_sms_settings_page'); ?>
            <h2><?php _e('Settings', 'wp-sms') ?>
            </h2>
            <div class="wpsms-tab-group">
                <ul class="wpsms-tab">
                    <?php
                        foreach ($this->get_tabs() as $tab_id => $tab_name) {
                            $tab_url = add_query_arg(array(
                                'settings-updated' => false,
                                'tab'              => $tab_id
                            ));

                            $active      = $active_tab == $tab_id ? 'active' : '';
                            $IsProTab    = in_array($tab_id, $this->proTabs) ? ' is-pro-tab' : '';
                            $proLockIcon = '';

                            if ($IsProTab) {
                                if (!$this->proIsInstalled) {
                                    $proLockIcon = '</a><span class="pro-not-installed"><a href="' . WP_SMS_SITE . '/buy" target="_blank"><span class="dashicons dashicons-lock"></span> Pro</a></span></li>';
                                }
                            }

                            echo '<li class="tab-' . $tab_id . $IsProTab . '"><a href="' . esc_url($tab_url) . '" title="' . esc_attr($tab_name) . '" class="' . $active . '">';
                            echo $tab_name;
                            echo '</a>' . $proLockIcon . '</li>';
                        } ?>

                    <li class="tab-link"><a target="_blank"
                            href="<?php echo WP_SMS_SITE; ?>/documentation/"><?php _e('Documentation', 'wp-sms'); ?></a>
                    </li>
                    <li class="tab-link"><a target="_blank"
                            href="<?php echo WP_SMS_SITE; ?>/gateways/add-new/"><?php _e('Suggest / Add your gateway', 'wp-sms'); ?></a>
                    </li>
                    <li class="tab-link"><a target="_blank"
                            href="<?php echo WP_SMS_SITE; ?>"><?php _e('Plugin website', 'wp-sms'); ?></a>
                    </li>
                    <li class="tab-company-logo"><a target="_blank"
                            href="https://veronalabs.com/?utm_source=wp_sms&utm_medium=display&utm_campaign=wordpress"><img
                                src="<?php echo plugins_url('wp-sms/assets/images/veronalabs.svg'); ?>" /></a>
                    </li>
                </ul>
                <?php echo settings_errors('wpsms-notices'); ?>
                <div
                    class="wpsms-tab-content<?php echo $contentRestricted ? ' pro-not-installed' : ''; ?> <?php echo $active_tab.'_settings_tab'?>">
                    <form method="post" action="options.php">
                        <table class="form-table">
                            <?php
                                settings_fields($this->setting_name);
        do_settings_fields("{$this->setting_name}_{$active_tab}", "{$this->setting_name}_{$active_tab}"); ?>
                        </table>

                        <?php
                            if (!$contentRestricted) {
                                submit_button();
                            } ?>
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

    public function getRoles()
    {
        $roles = [];
        foreach (get_editable_roles() as $key => $role) {
            $roles[] = [$key => $role['name']];
        }

        return $roles;
    }

    /**
     * Get ultimate-member's register form fields
     *
     * @return array
     */
    public function get_um_register_form_fields()
    {
        $ultimate_member_forms = get_posts(['post_type' => 'um_form']);

        foreach ($ultimate_member_forms as $form) {
            $form_role = get_post_meta($form->ID, '_um_core');

            if (in_array('register', $form_role)) {
                $form_fields = get_post_meta($form->ID, '_um_custom_fields');

                $return_value = [];
                foreach ($form_fields[0] as $field) {
                    if (isset($field['title']) && isset($field['metakey'])) {
                        $return_value[$field['metakey']] = $field['title'];
                    }
                }
                return $return_value;
            }
        }
        return [];
    }

    /**
     * Get countries list
     *
     * @return array|mixed|object
     */
    public function getCountriesList()
    {
        // Load countries list file
        $file = WP_SMS_DIR . 'assets/countries.json';
        $file = file_get_contents($file);

        return json_decode($file, true);
    }

    /**
     * Modify license setting page and render add-ons settings
     *
     * @param $settings
     * @return array
     */
    public function modifyLicenseSettings($settings)
    {
        if (!wp_sms_get_addons()) {
            $settings["license_title"] = array(
                'id'   => "license_title",
                'type' => 'notice',
                'name' => __('No Pro Pack or Add-On found', 'wp-sms'),
                'desc' => sprintf('If you have already installed the Pro Pack or Add-On(s) but the license field is not showing-up, get and install the latest version through <a href="%s" target="_blank">your account</a> again.', esc_url(WP_SMS_SITE . '/my-account/orders/'))
            );

            return $settings;
        }

        foreach (wp_sms_get_addons() as $addOnKey => $addOn) {

            // license title
            $settings["license_{$addOnKey}_title"] = array(
                'id'   => "license_{$addOnKey}_title",
                'name' => $addOn,
                'type' => 'header',
                'doc'  => '/resources/troubleshoot-license-activation-issues/',
                'desc' => __('License key is used to get access to automatic updates and support.', 'wp-sms')
            );

            // license key
            $settings["license_{$addOnKey}_key"] = array(
                'id'          => "license_{$addOnKey}_key",
                'name'        => __('License Key', 'wp-sms'),
                'type'        => 'text',
                'after_input' => $this->getLicenseStatusIcon($addOnKey),
                'desc'        => sprintf(__('To get the license, please go to <a href="%s" target="_blank">your account</a>.', 'wp-sms'), esc_url(WP_SMS_SITE . '/my-account/orders/'), esc_url(WP_SMS_SITE . '/resources/troubleshoot-license-activation-issues/'), esc_url(WP_SMS_SITE . '/resources/troubleshoot-license-activation-issues/'))
            );
        }

        return $settings;
    }
}
