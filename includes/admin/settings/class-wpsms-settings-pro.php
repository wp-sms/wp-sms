<?php

namespace WP_SMS;

if (!defined('ABSPATH')) {
    exit;
} // No direct access allowed ;)

class Settings_Pro
{
    public $setting_name;
    public $options = array();

    public function __construct()
    {
        $this->setting_name = 'wps_pp_settings';
        $this->options      = get_option($this->setting_name);

        if (empty($this->options)) {
            update_option($this->setting_name, array());
        }

        add_action('admin_menu', array($this, 'add_settings_menu'), 11);

        if (isset($_GET['page']) and $_GET['page'] == 'wp-sms-pro' or isset($_POST['option_page']) and $_POST['option_page'] == 'wps_pp_settings') {
            add_action('admin_init', array($this, 'register_settings'));
        }
    }

    /**
     * Add Professional Package options
     * */
    public function add_settings_menu()
    {
        add_submenu_page('wp-sms', __('Professional', 'wp-sms'), '<span style="color:#FF7600">' . __('Professional', 'wp-sms') . '</span>', 'wpsms_setting', 'wp-sms-pro', array(
            $this,
            'render_settings'
        ));
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
        if (empty($settings)) {
            update_option($this->setting_name, array());
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
                'wps_pp_settings_' . $tab,
                __return_null(),
                '__return_false',
                'wps_pp_settings_' . $tab
            );

            if (empty($settings)) {
                return;
            }

            foreach ($settings as $option) {
                $name = isset($option['name']) ? $option['name'] : '';

                add_settings_field(
                    'wps_pp_settings[' . $option['id'] . ']',
                    $name,
                    array($this, $option['type'] . '_callback'),
                    'wps_pp_settings_' . $tab,
                    'wps_pp_settings_' . $tab,
                    array(
                        'id'          => isset($option['id']) ? $option['id'] : null,
                        'desc'        => !empty($option['desc']) ? $option['desc'] : '',
                        'name'        => isset($option['name']) ? $option['name'] : null,
                        'after_input' => isset($option['after_input']) ? $option['after_input'] : null,
                        'section'     => $tab,
                        'size'        => isset($option['size']) ? $option['size'] : null,
                        'options'     => isset($option['options']) ? $option['options'] : '',
                        'std'         => isset($option['std']) ? $option['std'] : '',
                        'doc'         => isset($option['doc']) ? $option['doc'] : ''
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
            'wp'  => __('WordPress', 'wp-sms'),
            'bp'  => __('BuddyPress', 'wp-sms'),
            'wc'  => __('WooCommerce', 'wp-sms'),
            'gf'  => __('Gravity Forms', 'wp-sms'),
            'qf'  => __('Quform', 'wp-sms'),
            'edd' => __('Easy Digital Downloads', 'wp-sms'),
            'job' => __('WP Job Manager', 'wp-sms'),
            'as'  => __('Awesome Support', 'wp-sms'),
            'um'  => __('Ultimate Members', 'wp-sms'),
        );

        // Check what version of WP-Pro using? if not new version, don't show tabs
        if (defined('WP_SMS_PRO_VERSION') and version_compare(WP_SMS_PRO_VERSION, "2.4.2", "<=")) {
            return array();
        }

        return apply_filters('wpsms_pro_settings_tabs', $tabs);
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
        $input = apply_filters('wps_pp_settings_' . $tab . '_sanitize', $input);

        // Loop through each setting being saved and pass it through a sanitization filter
        foreach ($input as $key => $value) {

            // Get the setting type (checkbox, select, etc)
            $type = isset($settings[$tab][$key]['type']) ? $settings[$tab][$key]['type'] : false;

            if ($type) {
                // Field type specific filter
                $input[$key] = apply_filters('wps_pp_settings_sanitize_' . $type, $value, $key);
            }

            // General filter
            $input[$key] = apply_filters('wps_pp_settings_sanitize', $value, $key);
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

        $groups              = Newsletter::getGroups();
        $subscribe_groups[0] = __('All', 'wp-sms');

        if ($groups) {
            foreach ($groups as $group) {
                $subscribe_groups[$group->ID] = $group->name;
            }
        }

        $gf_forms   = array();
        $qf_forms   = array();
        $um_options = array();

        // Set BuddyPress settings
        if (class_exists('BuddyPress')) {
            $buddypress_settings = array(
                'bp_fields'                    => array(
                    'id'   => 'bp_fields',
                    'name' => __('Fields', 'wp-sms'),
                    'type' => 'header'
                ),
                'bp_mobile_field'              => array(
                    'id'      => 'bp_mobile_field',
                    'name'    => __('Mobile field', 'wp-sms'),
                    'type'    => 'checkbox',
                    'options' => $options,
                    'desc'    => __('Add mobile field to profile page', 'wp-sms')
                ),
                'mentions'                     => array(
                    'id'   => 'mentions',
                    'name' => __('Mentions', 'wp-sms'),
                    'type' => 'header'
                ),
                'bp_mention_enable'            => array(
                    'id'      => 'bp_mention_enable',
                    'name'    => __('Send SMS', 'wp-sms'),
                    'type'    => 'checkbox',
                    'options' => $options,
                    'desc'    => __('Send SMS to user when someone mentioned. for example @admin', 'wp-sms')
                ),
                'bp_mention_message'           => array(
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
                'comments_activity'            => array(
                    'id'   => 'comments_activity',
                    'name' => __('User activity comments', 'wp-sms'),
                    'type' => 'header'
                ),
                'bp_comments_activity_enable'  => array(
                    'id'      => 'bp_comments_activity_enable',
                    'name'    => __('Send SMS', 'wp-sms'),
                    'type'    => 'checkbox',
                    'options' => $options,
                    'desc'    => __('Send SMS to user when the user get a reply on activity', 'wp-sms')
                ),
                'bp_comments_activity_message' => array(
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
                'comments'                     => array(
                    'id'   => 'comments',
                    'name' => __('User reply comments', 'wp-sms'),
                    'type' => 'header'
                ),
                'bp_comments_reply_enable'     => array(
                    'id'      => 'bp_comments_reply_enable',
                    'name'    => __('Send SMS', 'wp-sms'),
                    'type'    => 'checkbox',
                    'options' => $options,
                    'desc'    => __('Send SMS to user when the user get a reply on comment', 'wp-sms')
                ),
                'bp_comments_reply_message'    => array(
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
                    'desc' => __('BuddyPress should be enable to run this tab.', 'wp-sms'),
                ));
        }

        // Set WooCommerce settings
        if (class_exists('WooCommerce')) {
            $wc_settings = array(
                'wc_fields'                   => array(
                    'id'   => 'wc_fields',
                    'name' => __('General', 'wp-sms'),
                    'type' => 'header'
                ),
                'wc_mobile_field'             => array(
                    'id'      => 'wc_mobile_field',
                    'name'    => __('Choose the field', 'wp-sms'),
                    'type'    => 'select',
                    'options' => array(
                        'disable'            => __('Disable (No field)', 'wp-sms'),
                        'add_new_field'      => __('Add a new field in the checkout form', 'wp-sms'),
                        'used_current_field' => __('Use the current phone field in the bill', 'wp-sms'),
                    ),
                    'desc'    => __('Choose from which field you get numbers for sending SMS.', 'wp-sms')
                ),
                'wc_meta_box'                 => array(
                    'id'   => 'wc_meta_box',
                    'name' => __('Order Meta Box', 'wp-sms'),
                    'type' => 'header'
                ),
                'wc_meta_box_enable'          => array(
                    'id'      => 'wc_meta_box_enable',
                    'name'    => __('Status', 'wp-sms'),
                    'type'    => 'checkbox',
                    'options' => $options,
                    'desc'    => __('Enable send SMS meta box on Orders.<br>Note: You must choose the mobile field first if disable Meta Box will not appear too.', 'wp-sms')
                ),
                'wc_otp'                      => array(
                    'id'   => 'wc_otp',
                    'name' => __('OTP Verification', 'wp-sms'),
                    'type' => 'header',
                    'desc' => __('By enabling this option the customers should verify their mobile number while placing the order.', 'wp-sms'),
                    'doc' => '/resources/secure-login-with-one-time-password-otp/',
                ),
                'wc_otp_enable'               => array(
                    'id'      => 'wc_otp_enable',
                    'name'    => __('Status', 'wp-sms'),
                    'type'    => 'checkbox',
                    'options' => $options,
                    'desc'    => __('Enable OTP Verification for placing the order during the checkout.<br>Note: You must choose the mobile field first if disable OTP will not working  too.', 'wp-sms')
                ),
                'wc_otp_countries_whitelist'  => array(
                    'id'      => 'wc_otp_countries_whitelist',
                    'name'    => __('Countries Whitelist', 'wp-sms'),
                    'type'    => 'countryselect',
                    'options' => $this->getCountriesList(),
                    'desc'    => __('Specify the countries to enable OTP.', 'wp-sms')
                ),
                'wc_otp_max_retry'            => array(
                    'id'   => 'wc_otp_max_retry',
                    'name' => __('Max SMS retries', 'wp-sms'),
                    'type' => 'text',
                    'desc' => __('For no limits, set it to : 0', 'wp-sms')
                ),
                'wc_otp_max_time_limit'       => array(
                    'id'   => 'wc_otp_max_time_limit',
                    'name' => __('Retries expire time in Hours', 'wp-sms'),
                    'type' => 'text',
                    'desc' => __('This option working when a user reached max retries and need a period time for start again retry cycle.<br>For no limits, set it to : 0', 'wp-sms')
                ),
                'wc_otp_text'                 => array(
                    'id'   => 'wc_otp_text',
                    'name' => __('SMS text', 'wp-sms'),
                    'type' => 'textarea',
                    'desc' => sprintf(__('e.g: Your Verification Code: %s', 'wp-sms'), '<code>%otp_code%</code>')
                ),
                'wc_notify_product'           => array(
                    'id'   => 'wc_notify_product',
                    'name' => __('Notify for new product', 'wp-sms'),
                    'type' => 'header'
                ),
                'wc_notify_product_enable'    => array(
                    'id'      => 'wc_notify_product_enable',
                    'name'    => __('Send SMS', 'wp-sms'),
                    'type'    => 'checkbox',
                    'options' => $options,
                    'desc'    => __('Send SMS when publish new a product', 'wp-sms')
                ),
                'wc_notify_product_receiver'  => array(
                    'id'      => 'wc_notify_product_receiver',
                    'name'    => __('SMS receiver', 'wp-sms'),
                    'type'    => 'select',
                    'options' => array(
                        'subscriber' => __('Subscribe users', 'wp-sms'),
                        'users'      => __('Customers (Users)', 'wp-sms')
                    ),
                    'desc'    => __('Please select the receiver of sms', 'wp-sms')
                ),
                'wc_notify_product_cat'       => array(
                    'id'      => 'wc_notify_product_cat',
                    'name'    => __('Subscribe group', 'wp-sms'),
                    'type'    => 'select',
                    'options' => $subscribe_groups,
                    'desc'    => __('If you select the Subscribe users, can select the group for send sms', 'wp-sms')
                ),
                'wc_notify_product_message'   => array(
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
                'wc_notify_order'             => array(
                    'id'   => 'wc_notify_order',
                    'name' => __('Notify for new order', 'wp-sms'),
                    'type' => 'header'
                ),
                'wc_notify_order_enable'      => array(
                    'id'      => 'wc_notify_order_enable',
                    'name'    => __('Send SMS', 'wp-sms'),
                    'type'    => 'checkbox',
                    'options' => $options,
                    'desc'    => __('Send SMS when submit new order', 'wp-sms')
                ),
                'wc_notify_order_receiver'    => array(
                    'id'   => 'wc_notify_order_receiver',
                    'name' => __('SMS receiver', 'wp-sms'),
                    'type' => 'text',
                    'desc' => __('Please enter mobile number for get sms. You can separate the numbers with the Latin comma.', 'wp-sms')
                ),
                'wc_notify_order_message'     => array(
                    'id'   => 'wc_notify_order_message',
                    'name' => __('Message body', 'wp-sms'),
                    'type' => 'textarea',
                    'desc' => __('Enter the contents of the SMS message.', 'wp-sms') . '<br>' .
                        sprintf(
                            __('Billing First Name: %s, Billing Company: %s, Billing Address: %s, Billing Phone Number: %s, Order ID: %s, Order number: %s, Order Total: %s, Order edit URL: %s, Order status: %s', 'wp-sms'),
                            '<code>%billing_first_name%</code>',
                            '<code>%billing_company%</code>',
                            '<code>%billing_address%</code>',
                            '<code>%billing_phone%</code>',
                            '<code>%order_id%</code>',
                            '<code>%order_number%</code>',
                            '<code>%order_total%</code>',
                            '<code>%order_edit_url%</code>',
                            '<code>%status%</code>'
                        )
                ),
                'wc_notify_customer'          => array(
                    'id'   => 'wc_notify_customer',
                    'name' => __('Notify to customer order', 'wp-sms'),
                    'type' => 'header'
                ),
                'wc_notify_customer_enable'   => array(
                    'id'      => 'wc_notify_customer_enable',
                    'name'    => __('Send SMS', 'wp-sms'),
                    'type'    => 'checkbox',
                    'options' => $options,
                    'desc'    => __('Send SMS to customer when submit the order', 'wp-sms')
                ),
                'wc_notify_customer_message'  => array(
                    'id'   => 'wc_notify_customer_message',
                    'name' => __('Message body', 'wp-sms'),
                    'type' => 'textarea',
                    'desc' => __('Enter the contents of the SMS message.', 'wp-sms') . '<br>' .
                        sprintf(
                            __('Order ID: %s, Order number: %s, Order status: %s, Order Total: %s, Customer name: %s, Customer family: %s, Order view URL: %s, Order payment URL: %s', 'wp-sms'),
                            '<code>%order_id%</code>',
                            '<code>%order_number%</code>',
                            '<code>%status%</code>',
                            '<code>%order_total%</code>',
                            '<code>%billing_first_name%</code>',
                            '<code>%billing_last_name%</code>',
                            '<code>%order_view_url%</code>',
                            '<code>%order_pay_url%</code>'
                        )
                ),
                'wc_notify_stock'             => array(
                    'id'   => 'wc_notify_stock',
                    'name' => __('Notify of stock', 'wp-sms'),
                    'type' => 'header'
                ),
                'wc_notify_stock_enable'      => array(
                    'id'      => 'wc_notify_stock_enable',
                    'name'    => __('Send SMS', 'wp-sms'),
                    'type'    => 'checkbox',
                    'options' => $options,
                    'desc'    => __('Send SMS when stock is low', 'wp-sms')
                ),
                'wc_notify_stock_receiver'    => array(
                    'id'   => 'wc_notify_stock_receiver',
                    'name' => __('SMS receiver', 'wp-sms'),
                    'type' => 'text',
                    'desc' => __('Please enter mobile number for get sms. You can separate the numbers with the Latin comma.', 'wp-sms')
                ),
                'wc_notify_stock_message'     => array(
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
                'wc_notify_status'            => array(
                    'id'   => 'wc_notify_status',
                    'name' => __('Notify of status', 'wp-sms'),
                    'type' => 'header'
                ),
                'wc_notify_status_enable'     => array(
                    'id'      => 'wc_notify_status_enable',
                    'name'    => __('Send SMS', 'wp-sms'),
                    'type'    => 'checkbox',
                    'options' => $options,
                    'desc'    => __('Send SMS to customer when status is changed', 'wp-sms')
                ),
                'wc_notify_status_message'    => array(
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
                'wc_notify_by_status'         => array(
                    'id'   => 'wc_notify_by_status',
                    'name' => __('Notify by status', 'wp-sms'),
                    'type' => 'header'
                ),
                'wc_notify_by_status_enable'  => array(
                    'id'      => 'wc_notify_by_status_enable',
                    'name'    => __('Send SMS', 'wp-sms'),
                    'type'    => 'checkbox',
                    'options' => $options,
                    'desc'    => __('Send SMS to customer by order status', 'wp-sms')
                ),
                'wc_notify_by_status_content' => array(
                    'id'   => 'wc_notify_by_status_content',
                    'name' => __('Order Status & Message', 'wp-sms'),
                    'type' => 'repeater',
                    'desc' => __('Add Order Status & Write Message Body Per Order Status', 'wp-sms')
                ),
            );
        } else {
            $wc_settings = array(
                'wc_fields' => array(
                    'id'   => 'wc_fields',
                    'name' => __('Not active', 'wp-sms'),
                    'type' => 'notice',
                    'desc' => __('WooCommerce should be enable to run this tab.', 'wp-sms'),
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
                ),
            );
        } else {
            $edd_settings = array(
                'edd_fields' => array(
                    'id'   => 'edd_fields',
                    'name' => __('Not active', 'wp-sms'),
                    'type' => 'notice',
                    'desc' => __('Easy Digital Downloads should be enable to run this tab.', 'wp-sms'),
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
                ),
            );
        } else {
            $job_settings = array(
                'job_fields' => array(
                    'id'   => 'job_fields',
                    'name' => __('Not active', 'wp-sms'),
                    'type' => 'notice',
                    'desc' => __('Job Manager should be enable to run this tab.', 'wp-sms'),
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
                ),
            );
        } else {
            $as_settings = array(
                'as_notify_new_ticket' => array(
                    'id'   => 'as_notify_new_ticket',
                    'name' => __('Not active', 'wp-sms'),
                    'type' => 'notice',
                    'desc' => __('Awesome Support should be enable to run this tab.', 'wp-sms'),
                ));
        }


        // Get Gravityforms
        if (class_exists('RGFormsModel')) {
            $forms = \RGFormsModel::get_forms(null, 'title');

            foreach ($forms as $form):
                $more_fields = '';
            $form_fields = Gravityforms::get_field($form->id);
            if (is_array($form_fields) && count($form_fields)) {
                $more_fields = ', ' . __('Fields', 'wp-sms') . ' : ';
                foreach ($form_fields as $key => $value) {
                    $more_fields .= "<code>%{$value}%</code>, ";
                }
            }
            $gf_forms['gf_notify_form_' . $form->id]          = array(
                    'id'   => 'gf_notify_form_' . $form->id,
                    'name' => sprintf(__('Form notifications (%s)', 'wp-sms'), $form->title),
                    'type' => 'header',
                    'desc' => sprintf(__('By enabling this option you can send SMS notification once the %s form is submitted', 'wp-sms'), $form->title),
                    'doc' => '/resources/integrate-wp-sms-pro-with-gravity-forms/',
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
            endforeach;
        } else {
            $gf_forms['gf_notify_form'] = array(
                'id'   => 'gf_notify_form',
                'name' => __('Not active', 'wp-sms'),
                'type' => 'notice',
                'desc' => __('Gravityforms should be enable to run this tab', 'wp-sms'),
            );
        }

        // Get Ultimate Members
        if (function_exists('um_user')) {
            $um_options['um_field'] = array(
                'id'   => 'um_field',
                'name' => __('Mobile number field', 'wp-sms'),
                'type' => 'checkbox',
                'desc' => __('Sync Mobile number from Ultimate Members mobile number form field.', 'wp-sms'),
            );
            $um_options['um_sync_previous_members'] = array(
                'id'   => 'um_sync_previous_members' ,
                'name' => __('Sync old member too?'),
                'type' => 'checkbox',
                'desc' => __('Sync the old mobile numbers which registered before enabling the previous option in Ultimate Members.', 'wp-sms')
            );
            $um_options['um_sync_field_name'] = array(
                'id'     => 'um_sync_field_name' ,
                'name'   => __('Select the purpose field in registration form'),
                'type'   => 'select',
                'options'=> $this->get_um_register_form_fields(),
                'std'    => 'mobile_number',
                'desc'   => __('Select the field from ultimate member register form that you want to be synced(Default is "Mobile Number").', 'wp-sms')
            );
        } else {
            $um_options['um_notify_form'] = array(
                'id'   => 'um_notify_form',
                'name' => __('Not active', 'wp-sms'),
                'type' => 'notice',
                'desc' => __('Ultimate Members should be enable to run this tab', 'wp-sms'),
            );
        }

        // Get quforms
        if (class_exists('Quform_Repository')) {
            $quform = new \Quform_Repository();
            $forms  = $quform->allForms();

            if ($forms) {
                foreach ($forms as $form):
                    $qf_forms['qf_notify_form_' . $form['id']]          = array(
                        'id'   => 'qf_notify_form_' . $form['id'],
                        'name' => sprintf(__('Form notifications: (%s)', 'wp-sms'), $form['name']),
                        'type' => 'header',
                        'desc' => sprintf(__('By enabling this option you can send SMS notification once the %s form is submitted', 'wp-sms'), $form['name']),
                        'doc' => '/resources/integrate-wp-sms-pro-with-quform/',
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
                                __('Form name: %s, Form url: %s, Referring url: %s', 'wp-sms'),
                                '<code>%post_title%</code>',
                                '<code>%form_url%</code>',
                                '<code>%referring_url%</code>'
                            )
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
                            'options' => Quform::get_fields($form['id']),
                            'desc'    => __('Select the field of your form.', 'wp-sms')
                        );
                    $qf_forms['qf_notify_message_field_form_' . $form['id']]  = array(
                            'id'   => 'qf_notify_message_field_form_' . $form['id'],
                            'name' => __('Message body', 'wp-sms'),
                            'type' => 'textarea',
                            'desc' => __('Enter your message content.', 'wp-sms') . '<br>' .
                                sprintf(
                                    __('Form name: %s, Form url: %s, Referring url: %s', 'wp-sms'),
                                    '<code>%post_title%</code>',
                                    '<code>%form_url%</code>',
                                    '<code>%referring_url%</code>'
                                )
                        );
                }
                endforeach;
            } else {
                $qf_forms['qf_notify_form'] = array(
                    'id'   => 'qf_notify_form',
                    'name' => __('No data', 'wp-sms'),
                    'type' => 'notice',
                    'desc' => __('There is no form available on Quform plugin, please first add your forms.', 'wp-sms'),
                );
            }
        } else {
            $qf_forms['qf_notify_form'] = array(
                'id'   => 'qf_notify_form',
                'name' => __('Not active', 'wp-sms'),
                'type' => 'notice',
                'desc' => __('Quform should be enable to run this tab', 'wp-sms'),
            );
        }

        $settings = apply_filters('wp_sms_pro_registered_settings', array(
            // Options for wordpress tab
            'wp'  => apply_filters('wp_sms_pro_wp_settings', array(
                'login_title'            => array(
                    'id'   => 'login_title',
                    'name' => __('Login', 'wp-sms'),
                    'type' => 'header'
                ),
                'login_sms'              => array(
                    'id'      => 'login_sms',
                    'name'    => __('Login with mobile', 'wp-sms'),
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
                'mobile_verify'          => array(
                    'id'      => 'mobile_verify',
                    'name'    => __('Login with OTP status', 'wp-sms'),
                    'type'    => 'checkbox',
                    'options' => $options,
                    'desc'    => __('Verify mobile number in the login form. This feature is only compatible with WordPress default form.<br>The <code>manage_options</code> caps don\'t need to verify in the login form.', 'wp-sms'),
                ),
                'mobile_verify_optional' => array(
                    'id'      => 'mobile_verify_optional',
                    'name'    => __('Should be optional?', 'wp-sms'),
                    'type'    => 'checkbox',
                    'options' => $options,
                    'desc'    => __('If you would like to make the mobile number field option, please enable the option.', 'wp-sms'),
                ),
                'mobile_verify_method'   => array(
                    'id'      => 'mobile_verify_method',
                    'name'    => __('OTP Method', 'wp-sms'),
                    'type'    => 'select',
                    'options' => array(
                        'optional'  => __('Optional - Users can enable/disable it in their profile', 'wp-sms'),
                        'force_all' => __('Enable for All Users', 'wp-sms')
                    ),
                    'desc'    => __('Choose from which what OTP method you want to use.', 'wp-sms')
                ),
                'mobile_verify_runtime'  => array(
                    'id'      => 'mobile_verify_runtime',
                    'name'    => __('OTP run-time', 'wp-sms'),
                    'type'    => 'select',
                    'options' => array(
                        'once_time'  => __('Just once', 'wp-sms'),
                        'every_time' => __('Everytime', 'wp-sms')
                    ),
                    'desc'    => __('Choose from which what OTP run-time you want to use.', 'wp-sms')
                ),
                'mobile_verify_message'  => array(
                    'id'   => 'mobile_verify_message',
                    'name' => __('Message content', 'wp-sms'),
                    'type' => 'textarea',
                    'desc' => __('Enter the contents of the OTP SMS message.', 'wp-sms') . '<br>' .
                        sprintf(
                            __('Mobile code: %s, User name: %s, First Name: %s, Last Name: %s', 'wp-sms'),
                            '<code>%otp%</code>',
                            '<code>%user_name%</code>',
                            '<code>%first_name%</code>',
                            '<code>%last_name%</code>'
                        )
                ),
            )),
            // Options for BuddyPress tab
            'bp'  => apply_filters('wp_sms_pro_bp_settings', $buddypress_settings),
            // Options for Woocommerce tab
            'wc'  => apply_filters('wp_sms_pro_wc_settings', $wc_settings),
            // Options for Gravityforms tab
            'gf'  => apply_filters('wp_sms_pro_gf_settings', $gf_forms),
            // Options for Quform tab
            'qf'  => apply_filters('wp_sms_pro_qf_settings', $qf_forms),
            // Options for Easy Digital Downloads tab
            'edd' => apply_filters('wp_sms_pro_edd_settings', $edd_settings),
            // Options for WP Job Manager tab
            'job' => apply_filters('wp_sms_job_settings', $job_settings),
            // Options for Awesome Support
            'as'  => apply_filters('wp_sms_as_settings', $as_settings),
            'um'  => apply_filters('wp_sms_pro_um_settings', $um_options),
        ));

        return $settings;
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

        echo "<div class='wpsms-settings-header-field'>{$html}</div><hr/>";
    }

    public function html_callback($args)
    {
        echo sprintf('%s', $args['options']);
    }

    public function notice_callback($args)
    {
        echo sprintf('%s', $args['desc']);
    }

    public function checkbox_callback($args)
    {
        $checked = isset($this->options[$args['id']]) ? checked(1, $this->options[$args['id']], false) : '';
        $html    = sprintf('<input type="checkbox" id="wps_pp_settings[%1$s]" name="wps_pp_settings[%1$s]" value="1" %2$s /><label for="wps_pp_settings[%1$s]"> ' . __('Active', 'wp-sms') . '</label><p class="description">%3$s</p>', esc_attr($args['id']), esc_attr($checked), $args['desc']);
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
        $html .= sprintf('<input name="wps_pp_settings[%1$s]"" id="wps_pp_settings[%1$s][%2$s]" type="radio" value="%2$s" %3$s /><label for="wps_pp_settings[%1$s][%2$s]">%4$s</label>&nbsp;&nbsp;', esc_attr($args['id']), esc_attr($key), checked(true, $checked, false), $option);
        endforeach;
        $html .= sprintf('<p class="description">%1$s</p>', $args['desc']);
        echo $html;
    }

    public function text_callback($args)
    {
        $id = $args['id'];

        if (!empty($this->options[$id])) {
            $value = $this->options[$id];
        } else {
            $value = isset($args['std']) ? $args['std'] : '';
        }

        $disabled    = $this->checkDefinedLicenseActive($id, $value) ? 'disabled' : '';
        $size        = (isset($args['size']) && !is_null($args['size'])) ? $args['size'] : 'regular';
        $after_input = (isset($args['after_input']) && !is_null($args['after_input'])) ? $args['after_input'] : '';
        $html        = sprintf('<input type="text" class="%1$s-text" id="wps_pp_settings[%2$s]" name="wps_pp_settings[%2$s]" value="%3$s" %4$s />%5$s<p class="description"> %6$s</p>', esc_attr($size), esc_attr($args['id']), esc_attr(stripslashes($value)), $disabled, $after_input, $args['desc']);
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
        $html = sprintf('<input type="number" step="%1$s" max="%2$s" min="%3$s" class="%4$s-text" id="wps_pp_settings[%5$s]" name="wps_pp_settings[%5$s]" value="%6$s"/><p class="description"> %7$s</p>', esc_attr($step), esc_attr($max), esc_attr($min), esc_attr($size), esc_attr($args['id']), esc_attr(stripslashes($value)), $args['desc']);
        echo $html;
    }

    public function textarea_callback($args)
    {
        if (isset($this->options[$args['id']])) {
            $value = $this->options[$args['id']];
        } else {
            $value = isset($args['std']) ? $args['std'] : '';
        }

        $html = sprintf('<textarea class="large-text" cols="50" rows="5" id="wps_pp_settings[%1$s]" name="wps_pp_settings[%1$s]">%2$s</textarea><p class="description"> %3$s</p>', esc_attr($args['id']), esc_textarea(stripslashes($value)), $args['desc']);
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

        $html = sprintf('<input type="password" class="%1$s-text" id="wps_pp_settings[%2$s]" name="wps_pp_settings[%2$s]" value="%3$s"/><p class="description"> %4$s</p>', esc_attr($size), esc_attr($args['id']), esc_attr($value), $args['desc']);

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

        $html = sprintf('<select id="wps_pp_settings[%1$s]" name="wps_pp_settings[%1$s]">', esc_attr($args['id']));

        foreach ($args['options'] as $option => $name) {
            $selected = selected($option, $value, false);
            $html     .= sprintf('<option value="%1$s" %2$s>%3$s</option>', esc_attr($option), esc_attr($selected), $name);
        }

        $html .= sprintf('</select><p class="description"> %1$s</p>', $args['desc']);

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
        $html       = sprintf('<select class="%1$s" id="wps_pp_settings[%2$s]" name="wps_pp_settings[%2$s]"/>', esc_attr($class_name), esc_attr($args['id']));

        foreach ($args['options'] as $key => $v) {
            $html .= sprintf('<optgroup label="%1$s">', ucfirst($key));

            foreach ($v as $option => $name) :
                $selected = selected($option, $value, false);
            $html     .= sprintf('<option value="%1$s" %2$s>%3$s</option>', esc_attr($option), esc_attr($selected), ucfirst($name));
            endforeach;

            $html .= '</optgroup>';
        }

        $html .= sprintf('</select><p class="description"> %1$s</p>', $args['desc']);

        echo $html;
    }

    public function color_select_callback($args)
    {
        if (isset($this->options[$args['id']])) {
            $value = $this->options[$args['id']];
        } else {
            $value = isset($args['std']) ? $args['std'] : '';
        }

        $html = sprintf('<select id="wps_pp_settings[%1$s]" name="wps_pp_settings[%1$s]">', esc_attr($args['id']));

        foreach ($args['options'] as $option => $color) :
            $selected = selected($option, $value, false);
        $html     .= esc_attr('<option value="%1$s" %2$s>%3$s</option>', esc_attr($option), esc_attr($selected), $color['label']);
        endforeach;

        $html .= sprintf('</select><p class="description"> %1$s</p>', $args['desc']);

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
            $html = wp_editor(stripslashes($value), 'wps_pp_settings[' . $args['id'] . ']', array('textarea_name' => 'wps_pp_settings[' . $args['id'] . ']'));
        } else {
            $html = sprintf('<textarea class="large-text" rows="10" id="wps_pp_settings[%1$s]" name="wps_pp_settings[%1$s]">' . esc_textarea(stripslashes($value)) . '</textarea>', esc_attr($args['id']));
        }

        $html .= sprintf('<p class="description"> %1$s</p>', $args['desc']);

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
        $html = sprintf('<input type="text" class="%1$s-text wpsms_upload_field" id="wps_pp_settings[%2$s]" name="wps_pp_settings[%2$s]" value="%3$s"/><span>&nbsp;<input type="button" class="wps_pp_settings_upload_button button-secondary" value="%4$s"/></span><p class="description"> %5$s</p>', esc_attr($size), esc_attr($args['id']), esc_attr(stripslashes($value)), __('Upload File', 'wpsms'), $args['desc']);

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

        $html = sprintf('<input type="text" class="wpsms-color-picker" id="wps_pp_settings[%1$s]" name="wps_pp_settings[%1$s]" value="%2$s" data-default-color="%3$s" /><p class="description"> %4$s</p>', esc_attr($args['id']), esc_attr($value), esc_attr($default), $args['desc']);

        echo $html;
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
			<div data-repeater-list="wps_pp_settings[<?php echo $args['id'] ?>]">
				<?php if (is_array($value) && count($value)) { ?>
					<?php foreach ($value as $data) { ?>
						<?php $order_status = isset($data['order_status']) ? $data['order_status'] : '' ?>
						<?php $notify_status = isset($data['notify_status']) ? $data['notify_status'] : '' ?>
						<?php $message = isset($data['message']) ? $data['message'] : '' ?>
						<div class="repeater-item" data-repeater-item>
							<div style="display: block; width: 100%; margin-bottom: 15px; border-bottom: 1px solid #ccc;">
								<div style="display: block; width: 48%; float: left; margin-bottom: 15px;">
									<select name="order_status" style="display: block; width: 100%;">
										<option value="">- Please Choose -</option>
										<?php foreach ($order_statuses as $status_key => $status_name) { ?>
											<?php $key = str_replace('wc-', '', $status_key) ?>
											<option value="<?php echo $key ?>" <?php echo ($order_status == $key) ? 'selected' : '' ?>><?php echo $status_name ?></option>
										<?php } ?>
									</select>
									<p class="description">Please choose an order status</p>
								</div>
								<div style="display: block; width: 48%; float: right; margin-bottom: 15px;">
									<select name="notify_status" style="display: block; width: 100%;">
										<option value="">- Please Choose -</option>
										<option value="1" <?php echo ($notify_status == '1') ? 'selected' : '' ?>>Enable</option>
										<option value="2" <?php echo ($notify_status == '2') ? 'selected' : '' ?>>Disable</option>
									</select>
									<p class="description">Please select notify status</p>
								</div>
								<div style="display: block; width: 100%; margin-bottom: 15px;">
									<textarea name="message" rows="3" style="display: block; width: 100%;"><?php echo $message ?></textarea>
									<p class="description">Enter the contents of the SMS message.</p>
									<p class="description"><?php echo sprintf(__('Order status: %s, Order number: %s, Customer name: %s, Customer family: %s, Order view URL: %s, Order payment URL: %s', 'wp-sms'), '<code>%status%</code>', '<code>%order_number%</code>', '<code>%customer_first_name%</code>', '<code>%customer_last_name%</code>', '<code>%order_view_url%</code>', '<code>%order_pay_url%</code>') ?></p>
								</div>
								<div>
									<input type="button" value="Delete" class="button" style="margin-bottom: 15px;" data-repeater-delete />
								</div>
							</div>
						</div>
					<?php } ?>
				<?php } else { ?>
					<div class="repeater-item" data-repeater-item>
						<div style="display: block; width: 100%; margin-bottom: 15px; border-bottom: 1px solid #ccc;">
							<div style="display: block; width: 48%; float: left; margin-bottom: 15px;">
								<select name="order_status" style="display: block; width: 100%;">
									<option value="">- Please Choose -</option>
									<?php foreach ($order_statuses as $status_key => $status_name) { ?>
										<?php $key = str_replace('wc-', '', $status_key) ?>
										<option value="<?php echo $key ?>"><?php echo $status_name ?></option>
									<?php } ?>
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
								<p class="description"><?php echo sprintf(__('Order status: %s, Order number: %s, Customer name: %s, Customer family: %s, Order view URL: %s, Order payment URL: %s', 'wp-sms'), '<code>%status%</code>', '<code>%order_number%</code>', '<code>%customer_first_name%</code>', '<code>%customer_last_name%</code>', '<code>%order_view_url%</code>', '<code>%order_pay_url%</code>') ?></p>
							</div>
							<div>
								<input type="button" value="Delete" class="button" style="margin-bottom: 15px;" data-repeater-delete />
							</div>
						</div>
					</div>
				<?php } ?>
			</div>
			<div style="margin: 10px 0;">
				<input type="button" value="Add another order status" class="button button-primary" data-repeater-create />
			</p>
		</div>
		<?php
        echo ob_get_clean();
    }

    public function countryselect_callback($args)
    {
        if (isset($this->options[$args['id']])) {
            $value = $this->options[$args['id']];
        } else {
            $value = isset($args['std']) ? $args['std'] : '';
        }

        $html     = sprintf('<select id="wps_pp_settings[%1$s]" name="wps_pp_settings[%1$s][]" multiple="true" class="js-wpsms-select2"/>', esc_attr($args['id']));
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

        $html .= sprintf('</select><p class="description"> %1$s</p>', $args['desc']);

        echo $html;
    }


    public function render_settings()
    {
        $active_tab = isset($_GET['tab']) && array_key_exists($_GET['tab'], $this->get_tabs()) ? sanitize_text_field($_GET['tab']) : 'wp';

        ob_start(); ?>
        <div class="wrap wpsms-wrap wpsms-pro-settings-wrap">
            <?php require_once WP_SMS_DIR . 'includes/templates/header.php'; ?>
            <div class="wpsms-wrap__main">
                <?php do_action('wp_sms_pro_settings_page'); ?>
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
                    <div class="wpsms-tab-content">
                        <form method="post" action="options.php">
                            <table class="form-table">
                                <?php
                                settings_fields($this->setting_name);
        do_settings_fields('wps_pp_settings_' . $active_tab, 'wps_pp_settings_' . $active_tab); ?>
                            </table>
                            <?php ($active_tab == 'general' && defined('WP_SMS_PRO_LICENSE')) ? '' : submit_button(); ?>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <?php
        echo ob_get_clean();
    }

    /**
     * Get countries list
     *
     * @return array|mixed|object
     */
    public function getCountriesList()
    {
        // Load countries list file
        $file   = WP_SMS_DIR . 'assets/countries.json';
        $file   = file_get_contents($file);
        $result = json_decode($file, true);

        return $result;
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
                foreach($form_fields[0] as $field){
                    if( isset($field['title']) && isset($field['metakey']) ){
                        $return_value[ $field['metakey'] ] = $field['title'];
                    }
                }
                return $return_value;
            }
        }
        return [];
    }

    /**
     * @param $field
     * @param $value
     *
     * @return bool
     */
    private function checkDefinedLicenseActive($field, &$value)
    {
        if ($field == 'license_key' && defined('WP_SMS_PRO_LICENSE')) {
            $value = '';
            return true;
        }
        return false;
    }
}

new Settings_Pro();
