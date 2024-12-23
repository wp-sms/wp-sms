<?php

namespace WP_SMS;

use Forminator_API;
use WP_SMS\Notification\NotificationFactory;
use WP_SMS\Services\Forminator\Forminator;

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
    private $wooProIsInstalled;

    private $active_tab;
    private $contentRestricted;

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
        $this->setting_name      = $this->getCurrentOptionName();
        $this->proIsInstalled    = Version::pro_is_active();
        $this->wooProIsInstalled = Version::pro_is_installed('wp-sms-woocommerce-pro/wp-sms-woocommerce-pro.php');

        $this->get_settings();
        $this->options = get_option($this->setting_name);

        if (empty($this->options)) {
            update_option($this->setting_name, array());
        }

        // wp-sms-intgration added as part of the hole setting but in diffrent submenu
        if (isset($_GET['page']) and in_array($_GET['page'], ['wp-sms-settings', 'wp-sms-integrations']) or isset($_POST['option_page']) and in_array($_POST['option_page'], $this->optionNames)) {
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

        // Set default options
        if (!$settings) {
            update_option($this->setting_name, array(
                'add_mobile_field'             => 'add_mobile_field_in_profile',
                'notify_errors_to_admin_email' => 1,
                'report_wpsms_statistics'      => 1
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
                        'class'       => isset($option['className']) ? $option['className'] . " tr-{$option['type']} {$readonly} " : "tr-{$option['type']} {$readonly} ",
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
            'general'        => esc_html__('General', 'wp-sms'),
            'gateway'        => esc_html__('SMS Gateway', 'wp-sms'),
            'newsletter'     => esc_html__('SMS Newsletter', 'wp-sms'),
            'notifications'  => esc_html__('Notifications', 'wp-sms'),
            'message_button' => esc_html__('Message Button', 'wp-sms'),
            'advanced'       => esc_html__('Advanced', 'wp-sms'),

            /*
             * Licenses tab
             */
            'licenses'       => esc_html__('Licenses', 'wp-sms'),

            /*
             * Pro Pack tabs
             */
            'pro_wordpress'  => esc_html__('2FA & Login', 'wp-sms'),
            'integrations'   => esc_html__('Integrations', 'wp-sms'),
            // 'pro_buddypress'       => esc_html__('BuddyPress', 'wp-sms'),
            // 'pro_woocommerce'      => esc_html__('WooCommerce', 'wp-sms'),
            // 'pro_gravity_forms'    => esc_html__('Gravity Forms', 'wp-sms'),
            // 'pro_quform'           => esc_html__('Quform', 'wp-sms'),
            // 'pro_edd'              => esc_html__('Easy Digital Downloads', 'wp-sms'),
            // 'pro_wp_job_manager'   => esc_html__('WP Job Manager', 'wp-sms'),
            // 'pro_awesome_support'  => esc_html__('Awesome Support', 'wp-sms'),
            // 'pro_ultimate_members' => esc_html__('Ultimate Member', 'wp-sms')

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

        add_settings_error('wpsms-notices', '', esc_html__('Settings updated', 'wp-sms'), 'updated');
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
            'enable'  => esc_html__('Enable', 'wp-sms'),
            'disable' => esc_html__('Disable', 'wp-sms')
        );

        /*
         * Pro Pack fields
         */
        $groups              = Newsletter::getGroups();
        $subscribe_groups[0] = esc_html__('All', 'wp-sms');

        if ($groups) {
            foreach ($groups as $group) {
                $subscribe_groups[$group->ID] = $group->name;
            }
        }


        $gf_forms               = array();
        $qf_forms               = array();
        $um_options             = array();
        $pro_wordpress_settings = array(
            'login_title'           => array(
                'id'   => 'login_title',
                'name' => esc_html__('Login With SMS', 'wp-sms'),
                'type' => 'header'
            ),
            'login_sms'             => array(
                'id'      => 'login_sms',
                'name'    => esc_html__('Status', 'wp-sms'),
                'type'    => 'checkbox',
                'options' => $options,
                'desc'    => esc_html__('Allows users to log in with a verification code sent via SMS.', 'wp-sms'),
            ),
            'login_sms_message'     => array(
                'id'   => 'login_sms_message',
                'name' => esc_html__('Message body', 'wp-sms'),
                'type' => 'textarea',
                'desc' => esc_html__('Specify the SMS message format for login verification. Variables: ', 'wp-sms') . '<br>' .
                    sprintf(
                    // translators: %1$s: Mobile code, %2$s: Username, %3$s: Full name, %4$s: Site name, %5$s: Site URL
                        esc_html__('%1$s (Verification Code), %2$s (Username), %3$s (Full Name), %4$s (Website Name), %5$s (Website Url)', 'wp-sms'),
                        '<code>%code%</code>',
                        '<code>%user_name%</code>',
                        '<code>%full_name%</code>',
                        '<code>%site_name%</code>',
                        '<code>%site_url%</code>'
                    )
            ),
            'register_sms'          => array(
                'id'      => 'register_sms',
                'name'    => esc_html__('User Account Creation on Login', 'wp-sms'),
                'type'    => 'checkbox',
                'options' => $options,
                'desc'    => esc_html__('If a user logs in with SMS and does not have an existing account, a new account is created automatically.', 'wp-sms'),
            ),
            'otp_title'             => array(
                'id'   => 'otp_title',
                'name' => esc_html__('Two-Factor Authentication with SMS', 'wp-sms'),
                'type' => 'header'
            ),
            'mobile_verify'         => array(
                'id'      => 'mobile_verify',
                'name'    => esc_html__('Status', 'wp-sms'),
                'type'    => 'checkbox',
                'options' => $options,
                'desc'    => __('Allows for SMS verification as part of the login process.', 'wp-sms'),
            ),
            'mobile_verify_method'  => array(
                'id'      => 'mobile_verify_method',
                'name'    => esc_html__('Authentication Policy', 'wp-sms'),
                'type'    => 'select',
                'options' => array(
                    'optional'  => esc_html__('Optional - Users can enable/disable it in their profile', 'wp-sms'),
                    'force_all' => esc_html__('Enable for All Users', 'wp-sms')
                ),
                'desc'    => esc_html__('Select whether two-factor authentication is a user-toggled feature within their profile settings or a mandatory security measure for all accounts.', 'wp-sms')
            ),
            'mobile_verify_message' => array(
                'id'   => 'mobile_verify_message',
                'name' => esc_html__('Message Content', 'wp-sms'),
                'type' => 'textarea',
                'desc' => esc_html__('Set the SMS message format for two-factor authentication. Variables: ', 'wp-sms') . '<br>' .
                    sprintf(
                    // translators: %1$s: Mobile code, %2$s: Username, %3$s: First name, %4$s: Last name
                        esc_html__('%1$s (One-Time Password), %2$s (Username), %3$s (First Name), %4$s (Last Name).', 'wp-sms'),
                        '<code>%otp%</code>',
                        '<code>%user_name%</code>',
                        '<code>%first_name%</code>',
                        '<code>%last_name%</code>'
                    )
            )
        );

        // Set BuddyPress settings
        if (class_exists('BuddyPress')) {
            $buddypress_settings = array(
                'bp_welcome_notification'         => array(
                    'id'   => 'bp_welcome_notification',
                    'name' => esc_html__('Welcome Notification', 'wp-sms'),
                    'type' => 'header',
                    'desc' => esc_html__('By enabling this option you can send welcome SMS to new BuddyPress users'),
                ),
                'bp_welcome_notification_enable'  => array(
                    'id'      => 'bp_welcome_notification_enable',
                    'name'    => esc_html__('Status', 'wp-sms'),
                    'type'    => 'checkbox',
                    'options' => $options,
                    'desc'    => esc_html__('Send an SMS to user when register on BuddyPress.', 'wp-sms')
                ),
                'bp_welcome_notification_message' => array(
                    'id'   => 'bp_welcome_notification_message',
                    'name' => esc_html__('Message body', 'wp-sms'),
                    'type' => 'textarea',
                    'desc' => esc_html__('Enter the contents of the SMS message.', 'wp-sms') . '<br>' .
                        sprintf(
                        // translators: %1$s: User login, %2$s: User email, %3$s: User display name
                            esc_html__('User login: %1$s, User email: %2$s, User display name: %3$s', 'wp-sms'),
                            '<code>%user_login%</code>',
                            '<code>%user_email%</code>',
                            '<code>%display_name%</code>'
                        )
                ),
                'mentions'                        => array(
                    'id'   => 'mentions',
                    'name' => esc_html__('Mention Notification', 'wp-sms'),
                    'type' => 'header',
                ),
                'bp_mention_enable'               => array(
                    'id'      => 'bp_mention_enable',
                    'name'    => esc_html__('Send SMS', 'wp-sms'),
                    'type'    => 'checkbox',
                    'options' => $options,
                    'desc'    => esc_html__('Send SMS to user when someone mentioned. for example @admin', 'wp-sms')
                ),
                'bp_mention_message'              => array(
                    'id'   => 'bp_mention_message',
                    'name' => esc_html__('Message body', 'wp-sms'),
                    'type' => 'textarea',
                    'desc' => esc_html__('Enter the contents of the SMS message.', 'wp-sms') . '<br>' .
                        sprintf(
                        // translators: %1$s: Display name, %2$s: Profile link, %3$s: Time, %4$s: Message, %5$s: Receiver display name
                            esc_html__('Posted user display name: %1$s, User profile permalink: %2$s, Time: %3$s, Message: %4$s, Receiver user display name: %5$s', 'wp-sms'),
                            '<code>%posted_user_display_name%</code>',
                            '<code>%primary_link%</code>',
                            '<code>%time%</code>',
                            '<code>%message%</code>',
                            '<code>%receiver_user_display_name%</code>'
                        )
                ),
                'private_message'                 => array(
                    'id'   => 'private_message',
                    'name' => esc_html__('Private Message Notification', 'wp-sms'),
                    'type' => 'header',
                ),
                'bp_private_message_enable'       => array(
                    'id'      => 'bp_private_message_enable',
                    'name'    => esc_html__('Send SMS', 'wp-sms'),
                    'type'    => 'checkbox',
                    'options' => $options,
                    'desc'    => esc_html__('Send SMS notification when user received a private message', 'wp-sms')
                ),
                'bp_private_message_content'      => array(
                    'id'   => 'bp_private_message_content',
                    'name' => esc_html__('Message body', 'wp-sms'),
                    'type' => 'textarea',
                    'desc' => esc_html__('Enter the contents of the SMS message.', 'wp-sms') . '<br>' .
                        sprintf(
                        // translators: %1$s: Sender name, %2$s: Subject, %3$s: Message, %4$s: Message URL
                            esc_html__('Sender display name: %1$s, Subject: %2$s, Message: %3$s, Message URL: %4$s', 'wp-sms'),
                            '<code>%sender_display_name%</code>',
                            '<code>%subject%</code>',
                            '<code>%message%</code>',
                            '<code>%message_url%</code>'
                        )
                ),
                'comments_activity'               => array(
                    'id'   => 'comments_activity',
                    'name' => esc_html__('User activity comments', 'wp-sms'),
                    'type' => 'header'
                ),
                'bp_comments_activity_enable'     => array(
                    'id'      => 'bp_comments_activity_enable',
                    'name'    => esc_html__('Send SMS', 'wp-sms'),
                    'type'    => 'checkbox',
                    'options' => $options,
                    'desc'    => esc_html__('Send SMS to user when the user get a reply on activity', 'wp-sms')
                ),
                'bp_comments_activity_message'    => array(
                    'id'   => 'bp_comments_activity_message',
                    'name' => esc_html__('Message body', 'wp-sms'),
                    'type' => 'textarea',
                    'desc' => esc_html__('Enter the contents of the SMS message.', 'wp-sms') . '<br>' .
                        sprintf(
                        // translators: %1$s: Display name, %2$s: Comment, %3$s: Receiver name
                            esc_html__('Posted user display name: %1$s, Comment content: %2$s, Receiver user display name: %3$s', 'wp-sms'),
                            '<code>%posted_user_display_name%</code>',
                            '<code>%comment%</code>',
                            '<code>%receiver_user_display_name%</code>'
                        )
                ),
                'comments'                        => array(
                    'id'   => 'comments',
                    'name' => esc_html__('User reply comments', 'wp-sms'),
                    'type' => 'header'
                ),
                'bp_comments_reply_enable'        => array(
                    'id'      => 'bp_comments_reply_enable',
                    'name'    => esc_html__('Send SMS', 'wp-sms'),
                    'type'    => 'checkbox',
                    'options' => $options,
                    'desc'    => esc_html__('Send SMS to user when the user get a reply on comment', 'wp-sms')
                ),
                'bp_comments_reply_message'       => array(
                    'id'   => 'bp_comments_reply_message',
                    'name' => esc_html__('Message body', 'wp-sms'),
                    'type' => 'textarea',
                    'desc' => esc_html__('Enter the contents of the SMS message.', 'wp-sms') . '<br>' .
                        sprintf(
                        // translators: %1$s: Display name, %2$s: Comment, %3$s: Receiver name
                            esc_html__('Posted user display name: %1$s, Comment content: %2$s, Receiver user display name: %3$s', 'wp-sms'),
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
                    'name' => esc_html__('Not active', 'wp-sms'),
                    'type' => 'notice',
                    'desc' => esc_html__('BuddyPress plugin should be installed to show the options.', 'wp-sms'),
                ));
        }

        // Set WooCommerce settings
        if (class_exists('WooCommerce')) {
            $wc_settings = array(
                'wc_meta_box'                               => array(
                    'id'   => 'wc_meta_box',
                    'name' => esc_html__('Order Meta Box', 'wp-sms'),
                    'type' => 'header'
                ),
                'wc_meta_box_enable'                        => array(
                    'id'      => 'wc_meta_box_enable',
                    'name'    => esc_html__('Status', 'wp-sms'),
                    'type'    => 'checkbox',
                    'options' => $options,
                    'desc'    => esc_html__('Enable send SMS meta box on Orders.<br>Note: You must choose the mobile field first if disable Meta Box will not appear too.', 'wp-sms')
                ),
                'wc_notify_product'                         => array(
                    'id'   => 'wc_notify_product',
                    'name' => esc_html__('Notify for new product', 'wp-sms'),
                    'type' => 'header',
                    'desc' => esc_html__('Check the document for get more information about message variables', 'wp-sms'),
                    'doc'  => '/resources/woocommerce-sms-variables-and-order-meta/'
                ),
                'wc_notify_product_enable'                  => array(
                    'id'      => 'wc_notify_product_enable',
                    'name'    => esc_html__('Send SMS', 'wp-sms'),
                    'type'    => 'checkbox',
                    'options' => $options,
                    'desc'    => esc_html__('Send SMS when publish new a product', 'wp-sms')
                ),
                'wc_notify_product_receiver'                => array(
                    'id'      => 'wc_notify_product_receiver',
                    'name'    => esc_html__('SMS receiver', 'wp-sms'),
                    'type'    => 'select',
                    'options' => array(
                        'subscriber' => esc_html__('Subscriber', 'wp-sms'),
                        'users'      => esc_html__('Users', 'wp-sms')
                    ),
                    'desc'    => esc_html__('Please select the receiver of SMS', 'wp-sms')
                ),
                'wc_notify_product_cat'                     => array(
                    'id'        => 'wc_notify_product_cat',
                    'name'      => esc_html__('Subscribe group', 'wp-sms'),
                    'type'      => 'select',
                    'options'   => $subscribe_groups,
                    'className' => 'js-wpsms-show_if_wc_notify_product_receiver_equal_subscriber',
                    'desc'      => esc_html__('If you select the Subscribe users, can select the group for send sms', 'wp-sms')
                ),
                'wc_notify_product_roles'                   => array(
                    'id'        => 'wc_notify_product_roles',
                    'name'      => esc_html__('Specific roles', 'wp-sms'),
                    'type'      => 'multiselect',
                    'options'   => $this->getRoles(),
                    'className' => 'js-wpsms-show_if_wc_notify_product_receiver_equal_users',
                    'desc'      => esc_html__('Select the role of the user you want to receive the SMS.', 'wp-sms')
                ),
                'wc_notify_product_message'                 => array(
                    'id'   => 'wc_notify_product_message',
                    'name' => esc_html__('Message body', 'wp-sms'),
                    'type' => 'textarea',
                    'desc' => esc_html__('Enter the contents of the SMS message.', 'wp-sms') . '<br>' . NotificationFactory::getWooCommerceProduct()->printVariables()
                ),
                'wc_notify_order'                           => array(
                    'id'   => 'wc_notify_order',
                    'name' => esc_html__('Notify for new order', 'wp-sms'),
                    'type' => 'header',
                    'desc' => esc_html__('Check the document for get more information about message variables', 'wp-sms'),
                    'doc'  => '/resources/woocommerce-sms-variables-and-order-meta/'
                ),
                'wc_notify_order_enable'                    => array(
                    'id'      => 'wc_notify_order_enable',
                    'name'    => esc_html__('Send SMS', 'wp-sms'),
                    'type'    => 'checkbox',
                    'options' => $options,
                    'desc'    => esc_html__('Send SMS when submit new order', 'wp-sms')
                ),
                'wc_notify_order_receiver'                  => array(
                    'id'   => 'wc_notify_order_receiver',
                    'name' => esc_html__('SMS receiver', 'wp-sms'),
                    'type' => 'text',
                    'desc' => esc_html__('Please enter mobile number for get sms. You can separate the numbers with the Latin comma.', 'wp-sms')
                ),
                'wc_notify_order_message'                   => array(
                    'id'   => 'wc_notify_order_message',
                    'name' => esc_html__('Message body', 'wp-sms'),
                    'type' => 'textarea',
                    'desc' => esc_html__('Enter the contents of the SMS message.', 'wp-sms') . '<br>' . NotificationFactory::getWooCommerceOrder()->printVariables()
                ),
                'wc_notify_customer'                        => array(
                    'id'   => 'wc_notify_customer',
                    'name' => esc_html__('Notify to customer order', 'wp-sms'),
                    'type' => 'header',
                    'desc' => esc_html__('Check the document for get more information about message variables', 'wp-sms'),
                    'doc'  => '/resources/woocommerce-sms-variables-and-order-meta/'
                ),
                'wc_notify_customer_enable'                 => array(
                    'id'      => 'wc_notify_customer_enable',
                    'name'    => esc_html__('Send SMS', 'wp-sms'),
                    'type'    => 'checkbox',
                    'options' => $options,
                    'desc'    => esc_html__('Send SMS to customer when submit the order', 'wp-sms')
                ),
                'wc_notify_customer_message'                => array(
                    'id'   => 'wc_notify_customer_message',
                    'name' => esc_html__('Message body', 'wp-sms'),
                    'type' => 'textarea',
                    'desc' => esc_html__('Enter the contents of the SMS message.', 'wp-sms') . '<br>' . NotificationFactory::getWooCommerceOrder()->printVariables()
                ),
                'wc_notify_stock'                           => array(
                    'id'   => 'wc_notify_stock',
                    'name' => esc_html__('Notify of stock', 'wp-sms'),
                    'type' => 'header',
                    'desc' => esc_html__('Check the document for get more information about message variables', 'wp-sms'),
                    'doc'  => '/resources/woocommerce-sms-variables-and-order-meta/'
                ),
                'wc_notify_stock_enable'                    => array(
                    'id'      => 'wc_notify_stock_enable',
                    'name'    => esc_html__('Send SMS', 'wp-sms'),
                    'type'    => 'checkbox',
                    'options' => $options,
                    'desc'    => esc_html__('Send SMS when stock is low', 'wp-sms')
                ),
                'wc_notify_stock_receiver'                  => array(
                    'id'   => 'wc_notify_stock_receiver',
                    'name' => esc_html__('SMS receiver', 'wp-sms'),
                    'type' => 'text',
                    'desc' => esc_html__('Please enter mobile number for get sms. You can separate the numbers with the Latin comma.', 'wp-sms')
                ),
                'wc_notify_stock_message'                   => array(
                    'id'   => 'wc_notify_stock_message',
                    'name' => esc_html__('Message body', 'wp-sms'),
                    'type' => 'textarea',
                    'desc' => esc_html__('Enter the contents of the SMS message.', 'wp-sms') . '<br>' . NotificationFactory::getWooCommerceProduct()->printVariables()
                ),
                'wc_checkout_confirmation_checkbox'         => array(
                    'id'   => 'wc_checkout_confirmation_checkbox',
                    'name' => esc_html__('Confirmation Checkbox', 'wp-sms'),
                    'type' => 'header'
                ),
                'wc_checkout_confirmation_checkbox_enabled' => array(
                    'id'      => 'wc_checkout_confirmation_checkbox_enabled',
                    'name'    => esc_html__('Status', 'wp-sms'),
                    'type'    => 'checkbox',
                    'options' => $options,
                    'desc'    => esc_html__('Show the checkbox on the checkout for the customer to confirm receiving notification via SMS.', 'wp-sms')
                ),
                'wc_notify_status'                          => array(
                    'id'   => 'wc_notify_status',
                    'name' => esc_html__('Notify of order status', 'wp-sms'),
                    'type' => 'header',
                    'desc' => esc_html__('Check the document for get more information about message variables', 'wp-sms'),
                    'doc'  => '/resources/woocommerce-sms-variables-and-order-meta/'
                ),
                'wc_notify_status_enable'                   => array(
                    'id'      => 'wc_notify_status_enable',
                    'name'    => esc_html__('Send SMS', 'wp-sms'),
                    'type'    => 'checkbox',
                    'options' => $options,
                    'desc'    => esc_html__('Send SMS to customer when status is changed', 'wp-sms')
                ),
                'wc_notify_status_message'                  => array(
                    'id'   => 'wc_notify_status_message',
                    'name' => esc_html__('Message body', 'wp-sms'),
                    'type' => 'textarea',
                    'desc' => esc_html__('Enter the contents of the SMS message.', 'wp-sms') . '<br>' . NotificationFactory::getWooCommerceOrder()->printVariables()
                ),
                'wc_notify_by_status'                       => array(
                    'id'   => 'wc_notify_by_status',
                    'name' => esc_html__('Notify of specific order status', 'wp-sms'),
                    'type' => 'header',
                    'desc' => esc_html__('Check the document for get more information about message variables', 'wp-sms'),
                    'doc'  => '/resources/woocommerce-sms-variables-and-order-meta/'
                ),
                'wc_notify_by_status_enable'                => array(
                    'id'      => 'wc_notify_by_status_enable',
                    'name'    => esc_html__('Send SMS', 'wp-sms'),
                    'type'    => 'checkbox',
                    'options' => $options,
                    'desc'    => esc_html__('Send SMS to customer by order status', 'wp-sms')
                ),
                'wc_notify_by_status_content'               => array(
                    'id'      => 'wc_notify_by_status_content',
                    'name'    => esc_html__('Order Status & Message', 'wp-sms'),
                    'type'    => 'repeater',
                    'desc'    => esc_html__('Add Order Status & Write Message Body Per Order Status', 'wp-sms'),
                    'options' => [
                        'template'       => 'admin/fields/field-wc-status-repeater.php',
                        'order_statuses' => wc_get_order_statuses(),
                        'variables'      => NotificationFactory::getWooCommerceOrder()->printVariables()
                    ]
                )
            );
        } else {
            $wc_settings = array(
                'wc_fields' => array(
                    'id'   => 'wc_fields',
                    'name' => esc_html__('Not active', 'wp-sms'),
                    'type' => 'notice',
                    'desc' => esc_html__('WooCommerce plugin should be installed to show the options.', 'wp-sms')
                ));
        }

        // Set Easy Digital Downloads settings
        if (class_exists('Easy_Digital_Downloads')) {
            $edd_settings = array(
                'edd_fields'                  => array(
                    'id'   => 'edd_fields',
                    'name' => esc_html__('Fields', 'wp-sms'),
                    'type' => 'header'
                ),
                'edd_mobile_field'            => array(
                    'id'      => 'edd_mobile_field',
                    'name'    => esc_html__('Mobile field', 'wp-sms'),
                    'type'    => 'checkbox',
                    'options' => $options,
                    'desc'    => esc_html__('Add mobile field to checkout page', 'wp-sms')
                ),
                'edd_notify_order'            => array(
                    'id'   => 'edd_notify_order',
                    'name' => esc_html__('Notify for new order', 'wp-sms'),
                    'type' => 'header'
                ),
                'edd_notify_order_enable'     => array(
                    'id'      => 'edd_notify_order_enable',
                    'name'    => esc_html__('Send SMS', 'wp-sms'),
                    'type'    => 'checkbox',
                    'options' => $options,
                    'desc'    => esc_html__('Send SMS to number when a payment is marked as complete.', 'wp-sms')
                ),
                'edd_notify_order_receiver'   => array(
                    'id'   => 'edd_notify_order_receiver',
                    'name' => esc_html__('SMS receiver', 'wp-sms'),
                    'type' => 'text',
                    'desc' => esc_html__('Please enter mobile number for get sms. You can separate the numbers with the Latin comma.', 'wp-sms')
                ),
                'edd_notify_order_message'    => array(
                    'id'   => 'edd_notify_order_message',
                    'name' => esc_html__('Message body', 'wp-sms'),
                    'type' => 'textarea',
                    'desc' => esc_html__('Enter the contents of the SMS message.', 'wp-sms') . '<br>' .
                        sprintf(
                        // translators: %1$s: Email, %2$s: First name, %3$s: Last name
                            esc_html__('Email: %1$s, First name: %2$s, Last name: %3$s', 'wp-sms'),
                            '<code>%edd_email%</code>',
                            '<code>%edd_first%</code>',
                            '<code>%edd_last%</code>'
                        )
                ),
                'edd_notify_customer'         => array(
                    'id'   => 'edd_notify_customer',
                    'name' => esc_html__('Notify to customer order', 'wp-sms'),
                    'type' => 'header'
                ),
                'edd_notify_customer_enable'  => array(
                    'id'      => 'edd_notify_customer_enable',
                    'name'    => esc_html__('Send SMS', 'wp-sms'),
                    'type'    => 'checkbox',
                    'options' => $options,
                    'desc'    => esc_html__('Send SMS to customer when a payment is marked as complete.', 'wp-sms')
                ),
                'edd_notify_customer_message' => array(
                    'id'   => 'edd_notify_customer_message',
                    'name' => esc_html__('Message body', 'wp-sms'),
                    'type' => 'textarea',
                    'desc' => esc_html__('Enter the contents of the SMS message.', 'wp-sms') . '<br>' .
                        sprintf(
                        // translators: %1$s: Email, %2$s: First name, %3$s: Last name
                            esc_html__('Email: %1$s, First name: %2$s, Last name: %3$s', 'wp-sms'),
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
                    'name' => esc_html__('Not active', 'wp-sms'),
                    'type' => 'notice',
                    'desc' => esc_html__('Easy Digital Downloads plugin should be installed to show the options.', 'wp-sms')
                ));
        }

        // Set Jobs settings
        if (class_exists('WP_Job_Manager')) {
            $job_settings = array(
                'job_fields'                      => array(
                    'id'   => 'job_fields',
                    'name' => esc_html__('Mobile field', 'wp-sms'),
                    'type' => 'header'
                ),
                'job_mobile_field'                => array(
                    'id'      => 'job_mobile_field',
                    'name'    => esc_html__('Mobile field', 'wp-sms'),
                    'type'    => 'checkbox',
                    'options' => $options,
                    'desc'    => esc_html__('Add Mobile field to Post a job form', 'wp-sms')
                ),
                'job_display_mobile_number'       => array(
                    'id'      => 'job_display_mobile_number',
                    'name'    => esc_html__('Display Mobile', 'wp-sms'),
                    'type'    => 'checkbox',
                    'options' => $options,
                    'desc'    => esc_html__('Display Mobile number on the single job page', 'wp-sms')
                ),
                'job_notify'                      => array(
                    'id'   => 'job_notify',
                    'name' => esc_html__('Notify for new job', 'wp-sms'),
                    'type' => 'header'
                ),
                'job_notify_status'               => array(
                    'id'      => 'job_notify_status',
                    'name'    => esc_html__('Send SMS', 'wp-sms'),
                    'type'    => 'checkbox',
                    'options' => $options,
                    'desc'    => esc_html__('Send SMS when submit new job', 'wp-sms')
                ),
                'job_notify_receiver'             => array(
                    'id'      => 'job_notify_receiver',
                    'name'    => esc_html__('SMS receiver', 'wp-sms'),
                    'type'    => 'select',
                    'options' => array(
                        'subscriber' => esc_html__('Subscriber(s)', 'wp-sms'),
                        'number'     => esc_html__('Number(s)', 'wp-sms')
                    ),
                    'desc'    => esc_html__('Please select the SMS receiver(s).', 'wp-sms')
                ),
                'job_notify_receiver_subscribers' => array(
                    'id'        => 'job_notify_receiver_subscribers',
                    'name'      => esc_html__('Subscribe group', 'wp-sms'),
                    'type'      => 'select',
                    'options'   => $subscribe_groups,
                    'className' => 'js-wpsms-show_if_job_notify_receiver_equal_subscriber',
                    'desc'      => esc_html__('Please select the group of subscribers that you want to receive the SMS.', 'wp-sms')
                ),
                'job_notify_receiver_numbers'     => array(
                    'id'        => 'job_notify_receiver_numbers',
                    'name'      => esc_html__('Number(s)', 'wp-sms'),
                    'type'      => 'text',
                    'className' => 'js-wpsms-show_if_job_notify_receiver_equal_number',
                    'desc'      => esc_html__('Please enter mobile number for get sms. You can separate the numbers with the Latin comma.', 'wp-sms')
                ),
                'job_notify_message'              => array(
                    'id'   => 'job_notify_message',
                    'name' => esc_html__('Message body', 'wp-sms'),
                    'type' => 'textarea',
                    'desc' => esc_html__('Enter the contents of the SMS message.', 'wp-sms') . '<br>' .
                        sprintf(
                        // translators: %1$s: Job ID, %2$s: Job Title, %3$s: Job Description, %4$s: Job Location, %5$s: Job Type, %6$s: Company Mobile, %7$s: Company Name, %8$s: Company Website
                            esc_html__('Job ID: %1$s, Job Title: %2$s, Job Description: %3$s, Job Location: %4$s, Job Type: %5$s, Company Mobile: %6$s, Company Name: %7$s, Company Website: %8$s', 'wp-sms'),
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
                'job_notify_employer'             => array(
                    'id'   => 'job_notify_employer',
                    'name' => esc_html__('Notify to Employer', 'wp-sms'),
                    'type' => 'header'
                ),
                'job_notify_employer_status'      => array(
                    'id'      => 'job_notify_employer_status',
                    'name'    => esc_html__('Send SMS', 'wp-sms'),
                    'type'    => 'checkbox',
                    'options' => $options,
                    'desc'    => esc_html__('Send SMS to employer when the job approved', 'wp-sms')
                ),
                'job_notify_employer_message'     => array(
                    'id'   => 'job_notify_employer_message',
                    'name' => esc_html__('Message body', 'wp-sms'),
                    'type' => 'textarea',
                    'desc' => esc_html__('Enter the contents of the SMS message.', 'wp-sms') . '<br>' .
                        sprintf(
                        // translators: %1$s: Job ID, %2$s: Job Title, %3$s: Job Description, %4$s: Job Location, %5$s: Job Type, %6$s: Company Mobile, %7$s: Company Name, %8$s: Company Website
                            esc_html__('Job ID: %1$s, Job Title: %2$s, Job Description: %3$s, Job Location: %4$s, Job Type: %5$s, Company Mobile: %6$s, Company Name: %7$s, Company Website: %8$s', 'wp-sms'),
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
                    'name' => esc_html__('Not active', 'wp-sms'),
                    'type' => 'notice',
                    'desc' => esc_html__('Job Manager plugin should be installed to show the options.', 'wp-sms')
                ));
        }

        // Set Awesome settings
        if (class_exists('Awesome_Support')) {
            $as_settings = array(
                'as_notify_new_ticket'                 => array(
                    'id'   => 'as_notify_new_ticket',
                    'name' => esc_html__('Notify for new ticket', 'wp-sms'),
                    'type' => 'header'
                ),
                'as_notify_open_ticket_status'         => array(
                    'id'      => 'as_notify_open_ticket_status',
                    'name'    => esc_html__('Send SMS', 'wp-sms'),
                    'type'    => 'checkbox',
                    'options' => $options,
                    'desc'    => esc_html__('Send SMS to admin when the user opened a new ticket.', 'wp-sms')
                ),
                'as_notify_open_ticket_message'        => array(
                    'id'   => 'as_notify_open_ticket_message',
                    'name' => esc_html__('Message body', 'wp-sms'),
                    'type' => 'textarea',
                    'desc' => esc_html__('Enter the contents of the SMS message.', 'wp-sms') . '<br>' . NotificationFactory::getAwesomeSupportTicket()->printVariables()
                ),
                'as_notify_admin_reply_ticket'         => array(
                    'id'   => 'as_notify_admin_reply_ticket',
                    'name' => esc_html__('Notify admin for get reply', 'wp-sms'),
                    'type' => 'header'
                ),
                'as_notify_admin_reply_ticket_status'  => array(
                    'id'      => 'as_notify_admin_reply_ticket_status',
                    'name'    => esc_html__('Send SMS', 'wp-sms'),
                    'type'    => 'checkbox',
                    'options' => $options,
                    'desc'    => esc_html__('Send SMS to admin when the user replied the ticket.', 'wp-sms')
                ),
                'as_notify_admin_reply_ticket_message' => array(
                    'id'   => 'as_notify_admin_reply_ticket_message',
                    'name' => esc_html__('Message body', 'wp-sms'),
                    'type' => 'textarea',
                    'desc' => esc_html__('Enter the contents of the SMS message.', 'wp-sms') . '<br>' . NotificationFactory::getAwesomeSupportTicket()->printVariables()
                ),
                'as_notify_user_reply_ticket'          => array(
                    'id'   => 'as_notify_user_reply_ticket',
                    'name' => esc_html__('Notify user for get reply', 'wp-sms'),
                    'type' => 'header'
                ),
                'as_notify_user_reply_ticket_status'   => array(
                    'id'      => 'as_notify_user_reply_ticket_status',
                    'name'    => esc_html__('Send SMS', 'wp-sms'),
                    'type'    => 'checkbox',
                    'options' => $options,
                    'desc'    => esc_html__('Send SMS to user when the admin replied the ticket. Please make sure the "Add Mobile number field" option is enabled in the Settings > Features', 'wp-sms')
                ),
                'as_notify_user_reply_ticket_message'  => array(
                    'id'   => 'as_notify_user_reply_ticket_message',
                    'name' => esc_html__('Message body', 'wp-sms'),
                    'type' => 'textarea',
                    'desc' => esc_html__('Enter the contents of the SMS message.', 'wp-sms') . '<br>' . NotificationFactory::getAwesomeSupportTicket()->printVariables()
                ),
                'as_notify_update_ticket'              => array(
                    'id'   => 'as_notify_update_ticket',
                    'name' => esc_html__('Notify user for the ticket status update', 'wp-sms'),
                    'type' => 'header'
                ),
                'as_notify_update_ticket_status'       => array(
                    'id'      => 'as_notify_update_ticket_status',
                    'name'    => esc_html__('Send SMS', 'wp-sms'),
                    'type'    => 'checkbox',
                    'options' => $options,
                    'desc'    => esc_html__('Send SMS to user when the ticket status updates', 'wp-sms')
                ),
                'as_notify_update_ticket_message'      => array(
                    'id'   => 'as_notify_update_ticket_message',
                    'name' => esc_html__('Message body', 'wp-sms'),
                    'type' => 'textarea',
                    'desc' => esc_html__('Enter the contents of the SMS message.', 'wp-sms') . '<br>' . NotificationFactory::getAwesomeSupportTicket()->printVariables()
                ),
                'as_notify_close_ticket'               => array(
                    'id'   => 'as_notify_close_ticket',
                    'name' => esc_html__('Notify user when the ticket is closed', 'wp-sms'),
                    'type' => 'header'
                ),
                'as_notify_close_ticket_status'        => array(
                    'id'      => 'as_notify_close_ticket_status',
                    'name'    => esc_html__('Send SMS', 'wp-sms'),
                    'type'    => 'checkbox',
                    'options' => $options,
                    'desc'    => esc_html__('Send SMS to user when the ticket is closed', 'wp-sms')
                ),
                'as_notify_close_ticket_message'       => array(
                    'id'   => 'as_notify_close_ticket_message',
                    'name' => esc_html__('Message body', 'wp-sms'),
                    'type' => 'textarea',
                    'desc' => esc_html__('Enter the contents of the SMS message.', 'wp-sms') . '<br>' . NotificationFactory::getAwesomeSupportTicket()->printVariables()
                )
            );
        } else {
            $as_settings = array(
                'as_notify_new_ticket' => array(
                    'id'   => 'as_notify_new_ticket',
                    'name' => esc_html__('Not active', 'wp-sms'),
                    'type' => 'notice',
                    'desc' => esc_html__('Awesome Support plugin should be installed to show the options.', 'wp-sms')
                ));
        }

        // Get Gravityforms
        if (class_exists('RGFormsModel')) {
            $forms       = \RGFormsModel::get_forms(null, 'title');
            $more_fields = '';

            if (empty($forms)) {
                $gf_forms['gf_notify_form'] = array(
                    'id'   => 'gf_notify_form',
                    'name' => esc_html__('No data', 'wp-sms'),
                    'type' => 'notice',
                    'desc' => esc_html__('There is no form available on Gravity Forms plugin, please first add your forms.', 'wp-sms')
                );
            }

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
                    // translators: %s: Form title
                    'name' => sprintf(esc_html__('Form notifications (%s)', 'wp-sms'), $form->title),
                    'type' => 'header',
                    // translators: %s: Form title
                    'desc' => sprintf(esc_html__('By enabling this option you can send SMS notification once the %s form is submitted', 'wp-sms'), $form->title),
                    'doc'  => '/resources/integrate-wp-sms-pro-with-gravity-forms/',
                );
                $gf_forms['gf_notify_enable_form_' . $form->id]   = array(
                    'id'      => 'gf_notify_enable_form_' . $form->id,
                    'name'    => esc_html__('Send SMS to a number', 'wp-sms'),
                    'type'    => 'checkbox',
                    'options' => $options,
                );
                $gf_forms['gf_notify_receiver_form_' . $form->id] = array(
                    'id'   => 'gf_notify_receiver_form_' . $form->id,
                    'name' => esc_html__('Phone number(s)', 'wp-sms'),
                    'type' => 'text',
                    'desc' => esc_html__('Enter the mobile number(s) to receive SMS, to separate numbers, use the latin comma.', 'wp-sms')
                );
                $gf_forms['gf_notify_message_form_' . $form->id]  = array(
                    'id'   => 'gf_notify_message_form_' . $form->id,
                    'name' => esc_html__('Message body', 'wp-sms'),
                    'type' => 'textarea',
                    'desc' => esc_html__('Enter your message content.', 'wp-sms') . '<br>' .
                        sprintf(
                        // translators: %1$s: Form title, %2$s: IP address, %3$s: Form url, %4$s: User agent, %5$s: Content form
                            esc_html__('Form name: %1$s, IP: %2$s, Form url: %3$s, User agent: %4$s, Content form: %5$s', 'wp-sms'),
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
                        'name'    => esc_html__('Send SMS to field', 'wp-sms'),
                        'type'    => 'checkbox',
                        'options' => $options,
                    );
                    $gf_forms['gf_notify_receiver_field_form_' . $form->id] = array(
                        'id'      => 'gf_notify_receiver_field_form_' . $form->id,
                        'name'    => esc_html__('A field of the form', 'wp-sms'),
                        'type'    => 'select',
                        'options' => Gravityforms::get_field($form->id),
                        'desc'    => esc_html__('Select the field of your form.', 'wp-sms')
                    );
                    $gf_forms['gf_notify_message_field_form_' . $form->id]  = array(
                        'id'   => 'gf_notify_message_field_form_' . $form->id,
                        'name' => esc_html__('Message body', 'wp-sms'),
                        'type' => 'textarea',
                        'desc' => esc_html__('Enter your message content.', 'wp-sms') . '<br>' .
                            sprintf(
                            // translators: %1$s: Form title, %2$s: IP address, %3$s: Form url, %4$s: User agent, %5$s: Content form
                                esc_html__('Form name: %1$s, IP: %2$s, Form url: %3$s, User agent: %4$s, Content form: %5$s', 'wp-sms'),
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
                'name' => esc_html__('Not active', 'wp-sms'),
                'type' => 'notice',
                'desc' => esc_html__('Gravity Forms plugin should be enable to run this tab', 'wp-sms')
            );
        }

        // Get Ultimate Member
        if (function_exists('um_user')) {
            $um_options['um_notification_header']     = array(
                'id'   => 'um_notification_header',
                'name' => esc_html__('Notification', 'wp-sms'),
                'type' => 'header',
                'doc'  => '/resources/ultimate-member-and-wp-sms-integration/',
            );
            $um_options['um_send_sms_after_approval'] = array(
                'id'   => 'um_send_sms_after_approval',
                'name' => esc_html__('Send SMS after approval', 'wp-sms'),
                'type' => 'checkbox',
                'desc' => esc_html__('Send SMS after the user is approved', 'wp-sms'),
            );
            $um_options['um_message_body']            = array(
                'id'   => 'um_message_body',
                'name' => esc_html__('Message body', 'wp-sms'),
                'type' => 'textarea',
                'desc' => esc_html__('Enter the contents of the SMS message.', 'wp-sms') . '<br>' . NotificationFactory::getUser()->printVariables()
            );
        } else {
            $um_options['um_notify_form'] = array(
                'id'   => 'um_notify_form',
                'name' => esc_html__('Not active', 'wp-sms'),
                'type' => 'notice',
                'desc' => esc_html__('Ultimate Member plugin should be enable to run this tab', 'wp-sms')
            );
        }

        // Get Quform
        if (class_exists('Quform_Repository')) {
            $quform = new \Quform_Repository();
            $forms  = $quform->allForms();

            if ($forms) {
                foreach ($forms as $form):
                    $form_fields    = Quform::get_fields($form['id']);
                    $more_qf_fields = ', ';
                    if (is_array($form_fields) && count($form_fields)) {
                        foreach ($form_fields as $key => $value) {
                            $more_qf_fields .= "Field {$value}: <code>%field-{$key}%</code>, ";
                        }
                        $more_qf_fields = rtrim($more_qf_fields, ', ');
                    }

                    $qf_forms['qf_notify_form_' . $form['id']]          = array(
                        'id'   => 'qf_notify_form_' . $form['id'],
                        // translators: %s: Form name
                        'name' => sprintf(esc_html__('Form notifications: (%s)', 'wp-sms'), $form['name']),
                        'type' => 'header',
                        // translators: %s: Form name
                        'desc' => sprintf(esc_html__('By enabling this option you can send SMS notification once the %s form is submitted', 'wp-sms'), $form['name']),
                        'doc'  => '/resources/integrate-wp-sms-pro-with-quform/',
                    );
                    $qf_forms['qf_notify_enable_form_' . $form['id']]   = array(
                        'id'      => 'qf_notify_enable_form_' . $form['id'],
                        'name'    => esc_html__('Send SMS to a number', 'wp-sms'),
                        'type'    => 'checkbox',
                        'options' => $options,
                    );
                    $qf_forms['qf_notify_receiver_form_' . $form['id']] = array(
                        'id'   => 'qf_notify_receiver_form_' . $form['id'],
                        'name' => esc_html__('Phone number(s)', 'wp-sms'),
                        'type' => 'text',
                        'desc' => esc_html__('Enter the mobile number(s) to receive SMS, to separate numbers, use the latin comma.', 'wp-sms')
                    );
                    $qf_forms['qf_notify_message_form_' . $form['id']]  = array(
                        'id'   => 'qf_notify_message_form_' . $form['id'],
                        'name' => esc_html__('Message body', 'wp-sms'),
                        'type' => 'textarea',
                        'desc' => esc_html__('Enter your message content.', 'wp-sms') . '<br>' .
                            sprintf(
                            // translators: %1$s: Form name, %2$s: Form URL, %3$s: Referring URL, %4$s: Form content
                                esc_html__('Form name: %1$s, Form url: %2$s, Referring url: %3$s, Form content: %4$s', 'wp-sms'),
                                '<code>%post_title%</code>',
                                '<code>%form_url%</code>',
                                '<code>%referring_url%</code>',
                                '<code>%content%</code>'
                            ) . $more_qf_fields
                    );

                    if ($form['elements']) {
                        $qf_forms['qf_notify_enable_field_form_' . $form['id']]   = array(
                            'id'      => 'qf_notify_enable_field_form_' . $form['id'],
                            'name'    => esc_html__('Send SMS to field', 'wp-sms'),
                            'type'    => 'checkbox',
                            'options' => $options,
                        );
                        $qf_forms['qf_notify_receiver_field_form_' . $form['id']] = array(
                            'id'      => 'qf_notify_receiver_field_form_' . $form['id'],
                            'name'    => esc_html__('A field of the form', 'wp-sms'),
                            'type'    => 'select',
                            'options' => $form_fields,
                            'desc'    => esc_html__('Select the field of your form.', 'wp-sms')
                        );
                        $qf_forms['qf_notify_message_field_form_' . $form['id']]  = array(
                            'id'   => 'qf_notify_message_field_form_' . $form['id'],
                            'name' => esc_html__('Message body', 'wp-sms'),
                            'type' => 'textarea',
                            'desc' => esc_html__('Enter your message content.', 'wp-sms') . '<br>' .
                                sprintf(
                                // translators: %1$s: Form name, %2$s: Form URL, %3$s: Referring URL, %4$s: Form content
                                    esc_html__('Form name: %1$s, Form url: %2$s, Referring url: %3$s, Form content: %4$s', 'wp-sms'),
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
                    'name' => esc_html__('No data', 'wp-sms'),
                    'type' => 'notice',
                    'desc' => esc_html__('There is no form available on Quform plugin, please first add your forms.', 'wp-sms')
                );
            }
        } else {
            $qf_forms['qf_notify_form'] = array(
                'id'   => 'qf_notify_form',
                'name' => esc_html__('Not active', 'wp-sms'),
                'type' => 'notice',
                'desc' => esc_html__('Quform plugin should be enable to run this tab', 'wp-sms')
            );
        }

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


        $settings = apply_filters('wp_sms_registered_settings', array(
            /**
             * General fields
             */
            'general'        => apply_filters('wp_sms_general_settings', array(
                'admin_title'                              => array(
                    'id'   => 'admin_title',
                    'name' => esc_html__('Administrator Notifications', 'wp-sms'),
                    'type' => 'header'
                ),
                'admin_mobile_number'                      => array(
                    'id'   => 'admin_mobile_number',
                    'name' => esc_html__('Admin Mobile Number', 'wp-sms'),
                    'type' => 'text',
                    'desc' => esc_html__('Mobile number where the administrator will receive notifications.', 'wp-sms')
                ),
                'mobile_field'                             => array(
                    'id'   => 'mobile_field',
                    'name' => esc_html__('Mobile Field Configuration', 'wp-sms'),
                    'type' => 'header'
                ),
                'add_mobile_field'                         => array(
                    'id'      => 'add_mobile_field',
                    'name'    => esc_html__('Mobile Number Field Source', 'wp-sms'),
                    'type'    => 'advancedselect',
                    'options' => [
                        'WordPress'   => [
                            'disable'                     => esc_html__('Disable', 'wp-sms'),
                            'add_mobile_field_in_profile' => esc_html__('Insert a mobile number field into user profiles', 'wp-sms')
                        ],
                        'WooCommerce' => [
                            'add_mobile_field_in_wc_billing' => esc_html__('Add a mobile number field to billing and checkout pages', 'wp-sms'),
                            'use_phone_field_in_wc_billing'  => esc_html__('Use the existing billing phone field', 'wp-sms')
                        ]
                    ],
                    'desc'    => esc_html__('Create a new mobile number field or use an existing phone field.', 'wp-sms')
                ),
                'um_sync_field_name'                       => array(
                    'id'        => 'um_sync_field_name',
                    'name'      => esc_html__('Select the Existing Field', 'wp-sms'),
                    'type'      => 'select',
                    'options'   => $this->get_um_register_form_fields(),
                    'std'       => 'mobile_number',
                    'className' => 'js-wpsms-show_if_add_mobile_field_equal_use_ultimate_member_mobile_field',
                    'desc'      => esc_html__('Select the field from ultimate member register form that you want to be synced(Default is "Mobile Number").', 'wp-sms')
                ),
                'um_sync_previous_members'                 => array(
                    'id'        => 'um_sync_previous_members',
                    'name'      => esc_html__('Sync Old Members Too?', 'wp-sms'),
                    'type'      => 'checkbox',
                    'className' => 'js-wpsms-show_if_add_mobile_field_equal_use_ultimate_member_mobile_field',
                    'desc'      => esc_html__('Sync the old mobile numbers which registered before enabling the previous option in Ultimate Member.', 'wp-sms')
                ),
                'bp_mobile_field_id'                       => array(
                    'id'        => 'bp_mobile_field_id',
                    'name'      => esc_html__('Select the Existing Field', 'wp-sms'),
                    'type'      => 'advancedselect',
                    'options'   => $buddyPressProfileFields,
                    'className' => 'js-wpsms-show_if_add_mobile_field_equal_use_buddypress_mobile_field',
                    'desc'      => esc_html__('Select the BuddyPress field', 'wp-sms')
                ),
                'bp_sync_fields'                           => array(
                    'id'        => 'bp_sync_fields',
                    'name'      => esc_html__('Sync Fields', 'wp-sms'),
                    'type'      => 'checkbox',
                    'className' => 'js-wpsms-show_if_add_mobile_field_equal_use_buddypress_mobile_field',
                    'desc'      => esc_html__('Sync and compatibility the BuddyPress mobile numbers with plugin.', 'wp-sms')
                ),
                'optional_mobile_field'                    => array(
                    'id'      => 'optional_mobile_field',
                    'name'    => esc_html__('Mobile Field Mandatory Status', 'wp-sms'),
                    'type'    => 'select',
                    'options' => array(
                        '0'        => esc_html__('Required', 'wp-sms'),
                        'optional' => esc_html__('Optional', 'wp-sms')
                    ),
                    'desc'    => esc_html__('Set the mobile number field as optional or required.', 'wp-sms')
                ),
                'mobile_terms_field_place_holder'          => array(
                    'id'   => 'mobile_terms_field_place_holder',
                    'name' => esc_html__('Mobile Field Placeholder', 'wp-sms'),
                    'type' => 'text',
                    'desc' => esc_html__('Enter a sample format for the mobile number that users will see. Example: "e.g., +1234567890".', 'wp-sms')
                ),
                'international_mobile'                     => array(
                    'id'      => 'international_mobile',
                    'name'    => esc_html__('International Number Input', 'wp-sms'),
                    'type'    => 'checkbox',
                    'options' => $options,
                    'desc'    => esc_html__('Add a flag dropdown for international format support in the mobile number input field.', 'wp-sms')
                ),
                'international_mobile_only_countries'      => array(
                    'id'        => 'international_mobile_only_countries',
                    'name'      => esc_html__('Only Countries', 'wp-sms'),
                    'type'      => 'countryselect',
                    'className' => 'js-wpsms-show_if_international_mobile_enabled',
                    'options'   => wp_sms_countries()->getCountries(),
                    'desc'      => esc_html__('In the dropdown, display only the countries you specify.', 'wp-sms')
                ),
                'international_mobile_preferred_countries' => array(
                    'id'        => 'international_mobile_preferred_countries',
                    'name'      => esc_html__('Preferred Countries', 'wp-sms'),
                    'type'      => 'countryselect',
                    'className' => 'js-wpsms-show_if_international_mobile_enabled',
                    'options'   => wp_sms_countries()->getCountries(),
                    'desc'      => esc_html__('Specify the countries to appear at the top of the list.', 'wp-sms')
                ),
                'mobile_county_code'                       => array(
                    'id'         => 'mobile_county_code',
                    'name'       => esc_html__('Country Code Prefix', 'wp-sms'),
                    'type'       => 'select',
                    'className'  => 'js-wpsms-show_if_international_mobile_disabled',
                    'desc'       => esc_html__('If the user\'s mobile number requires a country code, select it from the list. If the number is not specific to any country, select \'No country code (Global / Local)\'.', 'wp-sms'),
                    'options'    => array_merge(['0' => esc_html__('No country code (Global / Local)', 'wp-sms')], wp_sms_countries()->getCountriesMerged()),
                    'attributes' => ['class' => 'js-wpsms-select2'],
                ),
                'mobile_terms_minimum'                     => array(
                    'id'        => 'mobile_terms_minimum',
                    'name'      => esc_html__('Minimum Length Number', 'wp-sms'),
                    'type'      => 'number',
                    'className' => 'js-wpsms-show_if_international_mobile_disabled',
                    'desc'      => esc_html__('Specify the shortest allowed mobile number.', 'wp-sms'),
                ),
                'mobile_terms_maximum'                     => array(
                    'id'        => 'mobile_terms_maximum',
                    'name'      => esc_html__('Maximum Length Number', 'wp-sms'),
                    'type'      => 'number',
                    'className' => 'js-wpsms-show_if_international_mobile_disabled',
                    'desc'      => esc_html__('Specify the longest allowed mobile number.', 'wp-sms'),
                ),
                'admin_title_privacy'                      => array(
                    'id'   => 'admin_title_privacy',
                    'name' => $this->renderOptionHeader(
                        esc_html__('Data Protection Settings', 'wp-sms'),
                        esc_html__('Enhance user privacy with GDPR-focused settings. Activate to ensure compliance with data protection regulations and provide users with transparency and control over their personal information.', 'wp-sms')
                    ),
                    'type' => 'header',
                ),
                'gdpr_compliance'                          => array(
                    'id'      => 'gdpr_compliance',
                    'name'    => esc_html__('GDPR Compliance Enhancements', 'wp-sms'),
                    'type'    => 'checkbox',
                    'options' => $options,
                    'desc'    => esc_html__('Implements GDPR adherence by enabling user data export and deletion via mobile number and adding a consent checkbox for SMS newsletter subscriptions.', 'wp-sms')
                ),
            )),

            /**
             * Gateway fields
             */
            'gateway'        => apply_filters('wp_sms_gateway_settings', array(
                // Gateway
                'gateway_title'                => array(
                    'id'   => 'gateway_title',
                    'name' => esc_html__('SMS Gateway Setup', 'wp-sms'),
                    'type' => 'header'
                ),
                'gateway_name'                 => array(
                    'id'      => 'gateway_name',
                    'name'    => esc_html__('Choose the Gateway', 'wp-sms'),
                    'type'    => 'advancedselect',
                    'options' => Gateway::gateway(),
                    'desc'    => esc_html__('Select your preferred SMS Gateway to send messages.', 'wp-sms')
                ),
                'gateway_help'                 => array(
                    'id'      => 'gateway_help',
                    'name'    => esc_html__('Gateway Guide', 'wp-sms'),
                    'type'    => 'html',
                    'options' => Gateway::help(),
                ),
                'gateway_username'             => array(
                    'id'   => 'gateway_username',
                    'name' => esc_html__('API Username', 'wp-sms'),
                    'type' => 'text',
                    'desc' => esc_html__('Enter API username of gateway', 'wp-sms')
                ),
                'gateway_password'             => array(
                    'id'   => 'gateway_password',
                    'name' => esc_html__('API Password', 'wp-sms'),
                    'type' => 'text',
                    'desc' => esc_html__('Enter API password of gateway', 'wp-sms')
                ),
                'gateway_sender_id'            => array(
                    'id'   => 'gateway_sender_id',
                    'name' => esc_html__('Sender ID/Number', 'wp-sms'),
                    'type' => 'text',
                    'std'  => Gateway::from(),
                    'desc' => esc_html__('Sender number or sender ID', 'wp-sms')
                ),
                'gateway_key'                  => array(
                    'id'   => 'gateway_key',
                    'name' => esc_html__('API Key', 'wp-sms'),
                    'type' => 'text',
                    'desc' => esc_html__('Enter API key of gateway', 'wp-sms')
                ),
                // Gateway status
                'gateway_status_title'         => array(
                    'id'   => 'gateway_status_title',
                    'name' => esc_html__('Gateway Overview', 'wp-sms'),
                    'type' => 'header'
                ),
                'account_credit'               => array(
                    'id'      => 'account_credit',
                    'name'    => esc_html__('Status', 'wp-sms'),
                    'type'    => 'html',
                    'options' => Gateway::status(),
                ),
                'account_response'             => array(
                    'id'      => 'account_response',
                    'name'    => esc_html__('Balance / Credit', 'wp-sms'),
                    'type'    => 'html',
                    'options' => Gateway::response(),
                ),
                'incoming_message'             => array(
                    'id'      => 'incoming_message',
                    'name'    => esc_html__('Incoming Message'),
                    'type'    => 'html',
                    'options' => Gateway::incoming_message_status(),
                ),
                'bulk_send'                    => array(
                    'id'      => 'bulk_send',
                    'name'    => esc_html__('Send Bulk SMS', 'wp-sms'),
                    'type'    => 'html',
                    'options' => Gateway::bulk_status(),
                ),
                'media_support'                => array(
                    'id'      => 'media_support',
                    'name'    => esc_html__('Send MMS', 'wp-sms'),
                    'type'    => 'html',
                    'options' => Gateway::mms_status(),
                ),
                // Account credit
                'account_credit_title'         => array(
                    'id'   => 'account_credit_title',
                    'name' => esc_html__('Account Balance Visibility', 'wp-sms'),
                    'type' => 'header'
                ),
                'account_credit_in_menu'       => array(
                    'id'      => 'account_credit_in_menu',
                    'name'    => esc_html__('Admin Menu Display', 'wp-sms'),
                    'type'    => 'checkbox',
                    'options' => $options,
                    'desc'    => esc_html__('Shows account credit in the admin menu.', 'wp-sms')
                ),
                'account_credit_in_sendsms'    => array(
                    'id'      => 'account_credit_in_sendsms',
                    'name'    => esc_html__('SMS Page Display', 'wp-sms'),
                    'type'    => 'checkbox',
                    'options' => $options,
                    'desc'    => esc_html__('Displays account credit on the SMS sending page.', 'wp-sms')
                ),
                // Message header
                'message_title'                => array(
                    'id'   => 'message_title',
                    'name' => esc_html__('SMS Dispatch & Number Optimization', 'wp-sms'),
                    'type' => 'header'
                ),
                'sms_delivery_method'          => array(
                    'id'      => 'sms_delivery_method',
                    'name'    => esc_html__('Delivery Method', 'wp-sms'),
                    'type'    => 'select',
                    'options' => array(
                        'api_direct_send' => esc_html__('Send SMS Instantly: Activates immediate dispatch of messages via API upon request.', 'wp-sms'),
                        'api_async_send'  => esc_html__('Scheduled SMS Delivery: Configures API to send messages at predetermined times.', 'wp-sms'),
                        'api_queued_send' => esc_html__('Batch SMS Queue: Lines up messages for grouped sending, enhancing efficiency for bulk dispatch.', 'wp-sms'),
                    ),
                    'desc'    => esc_html__('Select the dispatch method for SMS messages: instant send via API, delayed send at set times, or batch send for large recipient lists. For lists exceeding 20 recipients, batch sending is automatically selected.', 'wp-sms')
                ),
                'send_unicode'                 => array(
                    'id'      => 'send_unicode',
                    'name'    => esc_html__('Unicode Messaging', 'wp-sms'),
                    'type'    => 'checkbox',
                    'options' => $options,
                    'desc'    => esc_html__('Send messages in languages that use non-English characters, like Persian, Arabic, Chinese, or Cyrillic.', 'wp-sms')
                ),
                'clean_numbers'                => array(
                    'id'      => 'clean_numbers',
                    'name'    => esc_html__('Number Formatting', 'wp-sms'),
                    'type'    => 'checkbox',
                    'options' => $options,
                    'desc'    => esc_html__('Strips spaces from phone numbers before sending.', 'wp-sms')
                ),
                'send_only_local_numbers'      => array(
                    'id'      => 'send_only_local_numbers',
                    'name'    => esc_html__('Restrict to Local Numbers', 'wp-sms'),
                    'type'    => 'checkbox',
                    'options' => $options,
                    'desc'    => esc_html__('Send messages to numbers within the same country to avoid international fees.', 'wp-sms')
                ),
                'only_local_numbers_countries' => array(
                    'id'        => 'only_local_numbers_countries',
                    'name'      => esc_html__('Allowed Countries for SMS', 'wp-sms'),
                    'type'      => 'multiselect',
                    'options'   => array_map(function ($key, $value) {
                        return [$key => $value];
                    }, array_keys(wp_sms_countries()->getCountriesMerged()), wp_sms_countries()->getCountriesMerged()),
                    'className' => 'js-wpsms-show_if_send_only_local_numbers_enabled',
                    'desc'      => esc_html__('Specify countries allowed for SMS delivery. Only listed countries will receive messages.', 'wp-sms')
                )
            )),

            /**
             * SMS Newsletter fields
             */
            'newsletter'     => apply_filters('wp_sms_newsletter_settings', array(
                // SMS Newsletter
                'newsletter_title'                 => array(
                    'id'   => 'newsletter_title',
                    'name' => $this->renderOptionHeader(
                        esc_html__('SMS Newsletter Configuration', 'wp-sms'),
                        esc_html__('Configure how visitors subscribe to your SMS notifications.', 'wp-sms')
                    ),
                    'type' => 'header',
                    'doc'  => '/resources/add-sms-subscriber-form/'
                ),
                'newsletter_form_groups'           => array(
                    'id'   => 'newsletter_form_groups',
                    'name' => esc_html__('Group Visibility in Form', 'wp-sms'),
                    'type' => 'checkbox',
                    'desc' => esc_html__('Show available groups on the subscription form.', 'wp-sms')
                ),
                'newsletter_form_multiple_select'  => array(
                    'id'        => 'newsletter_form_multiple_select',
                    'name'      => esc_html__('Group Selection', 'wp-sms'),
                    'type'      => 'checkbox',
                    'className' => 'js-wpsms-show_if_newsletter_form_groups_enabled',
                    'desc'      => esc_html__('Allow subscribers to join multiple groups from the form.', 'wp-sms')
                ),
                'newsletter_form_specified_groups' => array(
                    'id'        => 'newsletter_form_specified_groups',
                    'name'      => esc_html__('Groups to Display', 'wp-sms'),
                    'type'      => 'multiselect',
                    'options'   => array_map(function ($value) {
                        return [$value->ID => $value->name];
                    }, Newsletter::getGroups()),
                    'className' => 'js-wpsms-show_if_newsletter_form_groups_enabled',
                    'desc'      => esc_html__('Choose which groups appear on the subscription form.', 'wp-sms')
                ),
                'newsletter_form_default_group'    => array(
                    'id'        => 'newsletter_form_default_group',
                    'name'      => esc_html__('Default Group for New Subscribers', 'wp-sms'),
                    'type'      => 'select',
                    'options'   => $subscribe_groups,
                    'className' => 'js-wpsms-show_if_newsletter_form_groups_enabled',
                    'desc'      => esc_html__('Set a group that all new subscribers will join by default.', 'wp-sms')
                ),
                'newsletter_form_verify'           => array(
                    'id'   => 'newsletter_form_verify',
                    'name' => esc_html__('Subscription Confirmation', 'wp-sms'),
                    'type' => 'checkbox',
                    'desc' => esc_html__('Subscribers must enter a code received by SMS to complete their subscription.', 'wp-sms')
                ),
                'welcome'                          => array(
                    'id'   => 'welcome',
                    'name' => $this->renderOptionHeader(
                        esc_html__('Welcome SMS Setup', 'wp-sms'),
                        esc_html__('Set up automatic SMS messages for new subscribers.', 'wp-sms')
                    ),
                    'type' => 'header',
                    'doc'  => '/resources/send-welcome-sms-to-new-subscribers/',
                ),
                'newsletter_form_welcome'          => array(
                    'id'   => 'newsletter_form_welcome',
                    'name' => esc_html__('Status', 'wp-sms'),
                    'type' => 'checkbox',
                    'desc' => esc_html__('Sends a welcome SMS to new subscribers when they sign up.', 'wp-sms')
                ),
                'newsletter_form_welcome_text'     => array(
                    'id'   => 'newsletter_form_welcome_text',
                    'name' => esc_html__('Welcome Message Content', 'wp-sms'),
                    'type' => 'textarea',
                    'desc' => esc_html__('Customize the SMS message sent to new subscribers. Use placeholders for personalized details: ', 'wp-sms') . '<br>' . NotificationFactory::getSubscriber()->printVariables()
                ),
                //Style Setting
                'style'                            => array(
                    'id'   => 'style',
                    'name' => esc_html__('Appearance Customization', 'wp-sms'),
                    'type' => 'header'
                ),
                'disable_style_in_front'           => array(
                    'id'   => 'disable_style_in_front',
                    'name' => esc_html__('Disable Default Form Styling', 'wp-sms'),
                    'type' => 'checkbox',
                    'desc' => esc_html__('Remove the plugin\'s default styling from the subscription form if preferred.', 'wp-sms')
                )
            )),

            /**
             * Message button setting fields
             */
            'message_button' => apply_filters('wp_sms_message_button_settings', array(
                // Message Button Configuration
                'chatbox'                   => array(
                    'id'   => 'chatbox',
                    'name' => esc_html__('Message Button Configuration', 'wp-sms'),
                    'type' => 'header',
                ),
                'chatbox_message_button'    => array(
                    'id'      => 'chatbox_message_button',
                    'name'    => esc_html__('Message Button', 'wp-sms'),
                    'type'    => 'checkbox',
                    'options' => $options,
                    'desc'    => sprintf(__('Switch on to display the Message Button on your site or off to hide it. <a href="#" class="js-wpsms-chatbox-preview">Preview</a>', 'wp-sms'))
                ),
                'chatbox_title'             => array(
                    'id'   => 'chatbox_title',
                    'name' => esc_html__('Title', 'wp-sms'),
                    'type' => 'text',
                    'desc' => esc_html__('Main title for your chatbox, e.g., \'Chat with Us!\'', 'wp-sms')
                ),
                // Button settings
                'chatbox_button'            => array(
                    'id'   => 'chatbox_button',
                    'name' => esc_html__('Button Appearance', 'wp-sms'),
                    'type' => 'header',
                ),
                'chatbox_button_text'       => array(
                    'id'   => 'chatbox_button_text',
                    'name' => esc_html__('Text', 'wp-sms'),
                    'type' => 'text',
                    'desc' => esc_html__('The message displayed on the chat button, e.g., \'Talk to Us\'', 'wp-sms')
                ),
                'chatbox_button_position'   => array(
                    'id'      => 'chatbox_button_position',
                    'name'    => esc_html__('Position', 'wp-sms'),
                    'type'    => 'select',
                    'options' => array(
                        'bottom_right' => esc_html__('Bottom Right', 'wp-sms'),
                        'bottom_left'  => esc_html__('Bottom Left', 'wp-sms'),
                    ),
                    'desc'    => esc_html__('Choose where the chat button appears on your site.', 'wp-sms')
                ),
                // Team member settings
                'chatbox_team_member'       => array(
                    'id'   => 'chatbox_team_member',
                    'name' => esc_html__('Support Team Profiles', 'wp-sms'),
                    'type' => 'header',
                ),
                'chatbox_team_members'      => array(
                    'id'      => 'chatbox_team_members',
                    'name'    => esc_html__('Team Members', 'wp-sms'),
                    'type'    => 'repeater',
                    'options' => [
                        'template' => 'admin/fields/field-team-member-repeater.php',
                    ],
                ),
                // Additional settings
                'chatbox_miscellaneous'     => array(
                    'id'   => 'chatbox_miscellaneous',
                    'name' => esc_html__('Additional Chatbox Options', 'wp-sms'),
                    'type' => 'header',
                ),
                'chatbox_color'             => array(
                    'id'   => 'chatbox_color',
                    'name' => esc_html__('Chatbox Color', 'wp-sms'),
                    'type' => 'color',
                    'desc' => esc_html__('Choose your chat button\'s background color and header color.', 'wp-sms')
                ),
                'chatbox_text_color'        => array(
                    'id'   => 'chatbox_text_color',
                    'name' => esc_html__('Chatbox Text Color', 'wp-sms'),
                    'type' => 'color',
                    'desc' => esc_html__('Select the color for your button and header text.', 'wp-sms')
                ),
                'chatbox_footer_text'       => array(
                    'id'   => 'chatbox_footer_text',
                    'name' => esc_html__('Footer Text', 'wp-sms'),
                    'type' => 'text',
                    'desc' => esc_html__('Text displayed in the chatbox footer, such as \'Chat with us on WhatsApp for instant support!\'', 'wp-sms')
                ),
                'chatbox_footer_text_color' => array(
                    'id'   => 'chatbox_footer_text_color',
                    'name' => esc_html__('Footer Text Color', 'wp-sms'),
                    'type' => 'color',
                    'desc' => esc_html__('Select your footer text color.', 'wp-sms')
                ),
                'chatbox_footer_link_title' => array(
                    'id'   => 'chatbox_footer_link_title',
                    'name' => esc_html__('Footer Link Title', 'wp-sms'),
                    'type' => 'text',
                    'desc' => esc_html__('Include a link for more information in the chatbox footer, e.g., \'Related Articles\'', 'wp-sms')
                ),
                'chatbox_footer_link_url'   => array(
                    'id'   => 'chatbox_footer_link_url',
                    'name' => esc_html__('Footer Link URL', 'wp-sms'),
                    'type' => 'text',
                    'desc' => esc_html__('Enter the URL of the chatbox footer link.', 'wp-sms')
                ),
                'chatbox_animation_effect'  => array(
                    'id'      => 'chatbox_animation_effect',
                    'name'    => esc_html__('Animation Effect', 'wp-sms'),
                    'type'    => 'select',
                    'options' => array(
                        ''      => esc_html__('None', 'wp-sms'),
                        'fade'  => esc_html__('Fade In', 'wp-sms'),
                        'slide' => esc_html__('Slide Up', 'wp-sms'),
                    ),
                    'desc'    => esc_html__('Choose an effect for the chatbox\'s entry or hover state.', 'wp-sms')
                ),
                'chatbox_disable_logo'      => array(
                    'id'      => 'chatbox_disable_logo',
                    'name'    => esc_html__('Disable WP SMS Logo', 'wp-sms'),
                    'type'    => 'checkbox',
                    'options' => $options,
                    'desc'    => esc_html__('Check this box to disable the WP SMS logo in the footer of the chatbox.', 'wp-sms')
                ),
                // Informational link settings
                'chatbox_link'              => array(
                    'id'   => 'chatbox_link',
                    'name' => esc_html__('Informational Links', 'wp-sms'),
                    'type' => 'header',
                ),
                'chatbox_links_enabled'     => array(
                    'id'      => 'chatbox_links_enabled',
                    'name'    => esc_html__('Resource Links', 'wp-sms'),
                    'type'    => 'checkbox',
                    'options' => $options,
                    'desc'    => esc_html__('Turn on to show resource links in the chatbox.', 'wp-sms')
                ),
                'chatbox_links_title'       => array(
                    'id'   => 'chatbox_links_title',
                    'name' => esc_html__('Section Title', 'wp-sms'),
                    'type' => 'text',
                    'desc' => esc_html__('The heading for your resource links, e.g., \'Quick Links\'', 'wp-sms')
                ),
                'chatbox_links'             => array(
                    'id'      => 'chatbox_links',
                    'name'    => esc_html__('Links', 'wp-sms'),
                    'type'    => 'repeater',
                    'options' => [
                        'template' => 'admin/fields/field-resource-link-repeater.php',
                    ],
                ),
            )),

            /**
             * Feature fields
             */
            'advanced'       => apply_filters('wp_sms_feature_settings', array(
                'admin_reports'                => array(
                    'id'   => 'admin_reports',
                    'name' => esc_html__('Administrative Reporting', 'wp-sms'),
                    'type' => 'header'
                ),
                'report_wpsms_statistics'      => array(
                    'id'      => 'report_wpsms_statistics',
                    'name'    => esc_html__('SMS Performance Reports', 'wp-sms'),
                    'type'    => 'checkbox',
                    'options' => $options,
                    'desc'    => esc_html__('Sends weekly SMS performance statistics to the admin email.', 'wp-sms')
                ),
                'notify_errors_to_admin_email' => array(
                    'id'      => 'notify_errors_to_admin_email',
                    'name'    => esc_html__('SMS Transmission Error Alerts', 'wp-sms'),
                    'type'    => 'checkbox',
                    'options' => $options,
                    'desc'    => esc_html__('Notifies the admin email upon SMS transmission failures.', 'wp-sms')
                ),
                'short_url'                    => array(
                    'id'   => 'short_url',
                    'name' => !$this->proIsInstalled ? esc_html__('URL Shortening via Bitly (Pro)', 'wp-sms') : esc_html__('URL Shortening via Bitly', 'wp-sms'),
                    'type' => 'header',
                ),
                'short_url_status'             => array(
                    'id'       => 'short_url_status',
                    'name'     => esc_html__('Shorten URLs', 'wp-sms'),
                    'type'     => 'checkbox',
                    'options'  => $options,
                    'desc'     => __('Converts all URLs to shortened versions using <a href="https://bitly.com/" target="_blank">Bitly.com</a>.', 'wp-sms'),
                    'readonly' => !$this->proIsInstalled
                ),
                'short_url_api_token'          => array(
                    'id'       => 'short_url_api_token',
                    'name'     => esc_html__('Bitly API Key', 'wp-sms'),
                    'type'     => 'text',
                    'desc'     => __('Enter your Bitly API key here. Obtain it from <a href="https://app.bitly.com/settings/api/" target="_blank">Bitly API Settings</a>.', 'wp-sms'),
                    'readonly' => !$this->proIsInstalled
                ),
                'webhooks'                     => array(
                    'id'   => 'webhooks',
                    'name' => $this->renderOptionHeader(
                        esc_html__('Webhooks Configuration', 'wp-sms'),
                        esc_html__('Set up your system’s Webhook URLs to integrate with external services.', 'wp-sms')
                    ),
                    'type' => 'header',
                    'doc'  => '/resources/webhooks/'
                ),
                'new_sms_webhook'              => array(
                    'id'   => 'new_sms_webhook',
                    'name' => esc_html__('Outgoing SMS Webhook', 'wp-sms'),
                    'type' => 'textarea',
                    'desc' => esc_html__('Configure the Webhook URL to which notifications are sent after an SMS dispatch from your system. Please enter a secure URL (HTTPS).', 'wp-sms'),
                ),
                'new_subscriber_webhook'       => array(
                    'id'   => 'new_subscriber_webhook',
                    'name' => esc_html__('Subscriber Registration Webhook', 'wp-sms'),
                    'type' => 'textarea',
                    'desc' => esc_html__('Provide the Webhook URL that will be triggered when a new subscriber registers. Ensure the URL uses the HTTPS protocol.', 'wp-sms'),
                ),
                'new_incoming_sms_webhook'     => array(
                    'id'   => 'new_incoming_sms_webhook',
                    'name' => esc_html__('Incoming SMS Handling Webhook', 'wp-sms'),
                    'type' => 'textarea',
                    'desc' => __('Define the Webhook URL for the "<a href="https://wp-sms-pro.com/product/wp-sms-two-way/?utm_source=wp-sms&utm_medium=link&utm_campaign=settings" target="_blank">Two-Way SMS</a>" add-on that handles incoming SMS messages. Only secure HTTPS URLs are accepted.', 'wp-sms') . '<br><br /><i>' . esc_html__('Please provide each Webhook URL on a separate line if you\'re setting up more than one.', 'wp-sms') . '</i>',
                ),
                'g_recaptcha'                  => array(
                    'id'   => 'g_recaptcha',
                    'name' => $this->renderOptionHeader(
                        !$this->proIsInstalled ? esc_html__('Google reCAPTCHA Integration (Pro / WooCommerce Pro)', 'wp-sms') : esc_html__('Google reCAPTCHA Integration', 'wp-sms'),
                        esc_html__('Enhance your system\'s security by activating Google reCAPTCHA. This tool prevents spam and abuse by ensuring that only genuine users can initiate request-SMS actions. Upon activation, every SMS request will be secured with reCAPTCHA verification.', 'wp-sms')
                    ),
                    'type' => 'header',
                ),
                'g_recaptcha_status'           => array(
                    'id'       => 'g_recaptcha_status',
                    'name'     => esc_html__('Activate', 'wp-sms'),
                    'type'     => 'checkbox',
                    'options'  => $options,
                    'desc'     => esc_html__('Use Google reCAPTCHA for your SMS requests.', 'wp-sms'),
                    'readonly' => !$this->proIsInstalled && !$this->wooProIsInstalled
                ),
                'g_recaptcha_site_key'         => array(
                    'id'       => 'g_recaptcha_site_key',
                    'name'     => esc_html__('Site Key', 'wp-sms'),
                    'type'     => 'text',
                    'desc'     => esc_html__('Enter your unique site key provided by Google reCAPTCHA. This public key is used in the HTML code of your site to display the reCAPTCHA widget. ', 'wp-sms') . '<a href="https://www.google.com/recaptcha/admin" target="_blank">Get your site key</a>.',
                    'readonly' => !$this->proIsInstalled && !$this->wooProIsInstalled
                ),
                'g_recaptcha_secret_key'       => array(
                    'id'       => 'g_recaptcha_secret_key',
                    'name'     => esc_html__('Secret Key', 'wp-sms'),
                    'type'     => 'text',
                    'desc'     => esc_html__('Insert your secret key here. This private key is used for communication between your server and the reCAPTCHA server. ', 'wp-sms') . '<a href="https://www.google.com/recaptcha/admin" target="_blank">Access your secret key</a>.' . '<br />' . esc_html__('Remember, both keys are necessary and should be kept confidential. The site key can be included in your web pages, but the secret key should never be exposed publicly.', 'wp-sms'),
                    'readonly' => !$this->proIsInstalled && !$this->wooProIsInstalled
                ),
            )),

            /**
             * Notifications fields
             */
            'notifications'  => apply_filters('wp_sms_notifications_settings', array(
                // Publish new post
                'notif_publish_new_post_title'            => array(
                    'id'   => 'notif_publish_new_post_title',
                    'name' => $this->renderOptionHeader(
                        esc_html__('New Post Alerts', 'wp-sms'),
                        esc_html__('Configure SMS notifications to inform subscribers about newly published content.', 'wp-sms')
                    ),
                    'type' => 'header'
                ),
                'notif_publish_new_post'                  => array(
                    'id'      => 'notif_publish_new_post',
                    'name'    => esc_html__('Status', 'wp-sms'),
                    'type'    => 'checkbox',
                    'options' => $options,
                    'desc'    => esc_html__('Send SMS for new posts.', 'wp-sms')
                ),
                'notif_publish_new_post_type'             => array(
                    'id'      => 'notif_publish_new_post_type',
                    'name'    => esc_html__('Post Types', 'wp-sms'),
                    'type'    => 'multiselect',
                    'options' => $this->get_list_post_type(array('show_ui' => 1)),
                    'desc'    => esc_html__('Specify which types of content trigger notifications.', 'wp-sms')
                ),
                'notif_publish_new_taxonomy_and_term'     => array(
                    'id'      => 'notif_publish_new_taxonomy_and_term',
                    'name'    => esc_html__('Taxonomies and Terms', 'wp-sms'),
                    'type'    => 'advancedmultiselect',
                    'options' => $this->getTaxonomiesAndTerms(),
                    'desc'    => esc_html__('Choose categories or tags to associate with alerts.', 'wp-sms')
                ),
                'notif_publish_new_post_receiver'         => array(
                    'id'      => 'notif_publish_new_post_receiver',
                    'name'    => esc_html__('Notification Recipients', 'wp-sms'),
                    'type'    => 'select',
                    'options' => array(
                        'subscriber' => esc_html__('Subscribers', 'wp-sms'),
                        'numbers'    => esc_html__('Individual Numbers', 'wp-sms'),
                        'users'      => esc_html__('User Roles', 'wp-sms')
                    ),
                    'desc'    => esc_html__('Select who receives notifications.', 'wp-sms')
                ),
                'notif_publish_new_post_default_group'    => array(
                    'id'        => 'notif_publish_new_post_default_group',
                    'name'      => esc_html__('Subscribe Group', 'wp-sms'),
                    'type'      => 'select',
                    'options'   => $subscribe_groups,
                    'className' => 'js-wpsms-show_if_notif_publish_new_post_receiver_equal_subscriber',
                    'desc'      => esc_html__('Set the default group to receive notifications.', 'wp-sms')
                ),
                'notif_publish_new_post_users'            => array(
                    'id'        => 'notif_publish_new_post_users',
                    'name'      => esc_html__('Specific Roles', 'wp-sms'),
                    'type'      => 'multiselect',
                    'options'   => $this->getRoles(),
                    'className' => 'js-wpsms-show_if_notif_publish_new_post_receiver_equal_users',
                    'desc'      => esc_html__('Assign SMS alerts to specific WordPress user roles.', 'wp-sms')
                ),
                'notif_publish_new_post_numbers'          => array(
                    'id'        => 'notif_publish_new_post_numbers',
                    'name'      => esc_html__('Individual Numbers', 'wp-sms'),
                    'type'      => 'text',
                    'className' => 'js-wpsms-show_if_notif_publish_new_post_receiver_equal_numbers',
                    'desc'      => esc_html__('Enter mobile number(s) here to receive SMS alerts. For multiple numbers, separate them with commas.', 'wp-sms')
                ),
                'notif_publish_new_post_force'            => array(
                    'id'      => 'notif_publish_new_post_force',
                    'name'    => esc_html__('Force Send', 'wp-sms'),
                    'type'    => 'checkbox',
                    'options' => $options,
                    'desc'    => esc_html__('Use to send notifications without additional confirmation during publishing. Compatible with WP-REST API.', 'wp-sms')
                ),
                'notif_publish_new_send_mms'              => array(

                    'id'      => 'notif_publish_new_send_mms',
                    'name'    => esc_html__('Send MMS', 'wp-sms'),
                    'type'    => 'checkbox',
                    'options' => $options,
                    'desc'    => esc_html__('Sends the featured image of the post as an MMS if supported by your SMS gateway.', 'wp-sms')
                ),
                'notif_publish_new_post_template'         => array(
                    'id'   => 'notif_publish_new_post_template',
                    'name' => esc_html__('Message Body', 'wp-sms'),
                    'type' => 'textarea',
                    'desc' => esc_html__('Define the SMS format.', 'wp-sms') . '<br>' . NotificationFactory::getPost()->printVariables()
                ),
                'notif_publish_new_post_words_count'      => array(
                    'id'   => 'notif_publish_new_post_words_count',
                    'name' => esc_html__('Post Content Words Limit', 'wp-sms'),
                    'type' => 'number',
                    'desc' => esc_html__('Set maximum word count for post excerpts in notifications. Default: 10.', 'wp-sms')
                ),
                // Publish new post
                'notif_publish_new_post_author_title'     => array(
                    'id'   => 'notif_publish_new_post_author_title',
                    'name' => $this->renderOptionHeader(
                        esc_html__('Post Author Notification', 'wp-sms'),
                        esc_html__('Set up notifications for post authors when their content is published. Ensure the mobile number field is added to user profiles under Settings > General > Mobile Number Field Source.', 'wp-sms')
                    ),
                    'type' => 'header'
                ),
                'notif_publish_new_post_author'           => array(
                    'id'      => 'notif_publish_new_post_author',
                    'name'    => esc_html__('Status', 'wp-sms'),
                    'type'    => 'checkbox',
                    'options' => $options,
                    'desc'    => esc_html__('Alerts post authors via SMS after publishing their posts.', 'wp-sms')
                ),
                'notif_publish_new_post_author_post_type' => array(
                    'id'      => 'notif_publish_new_post_author_post_type',
                    'name'    => esc_html__('Post Types', 'wp-sms'),
                    'type'    => 'multiselect',
                    'options' => $this->get_list_post_type(array('show_ui' => 1)),
                    'desc'    => esc_html__('Define which content types trigger author notifications.', 'wp-sms')
                ),
                'notif_publish_new_post_author_template'  => array(
                    'id'   => 'notif_publish_new_post_author_template',
                    'name' => esc_html__('Message Body', 'wp-sms'),
                    'type' => 'textarea',
                    'desc' => esc_html__('Customize the SMS message to authors using placeholders for post details.', 'wp-sms') . '<br>' . NotificationFactory::getPost()->printVariables()
                ),
                // Publish new wp version
                'notif_publish_new_wpversion_title'       => array(
                    'id'   => 'notif_publish_new_wpversion_title',
                    'name' => $this->renderOptionHeader(
                        esc_html__('The new release of WordPress', 'wp-sms'),
                        esc_html__('Configure notifications to be sent via SMS to the Admin Mobile Number regarding new releases of WordPress.', 'wp-sms')
                    ),
                    'type' => 'header'
                ),
                'notif_publish_new_wpversion'             => array(
                    'id'      => 'notif_publish_new_wpversion',
                    'name'    => esc_html__('Status', 'wp-sms'),
                    'type'    => 'checkbox',
                    'options' => $options,
                    'desc'    => esc_html__('Notifications for new WordPress releases.', 'wp-sms')
                ),
                // Register new user
                'notif_register_new_user_title'           => array(
                    'id'   => 'notif_register_new_user_title',
                    'name' => $this->renderOptionHeader(
                        esc_html__('Register a new user', 'wp-sms'),
                        esc_html__('Set up SMS notifications for admin and new user upon registration.', 'wp-sms')
                    ),
                    'type' => 'header'
                ),
                'notif_register_new_user'                 => array(
                    'id'      => 'notif_register_new_user',
                    'name'    => esc_html__('Status', 'wp-sms'),
                    'type'    => 'checkbox',
                    'options' => $options,
                    'desc'    => esc_html__('SMS notifications for new user registrations.', 'wp-sms')
                ),
                'notif_register_new_user_admin_template'  => array(
                    'id'   => 'notif_register_new_user_admin_template',
                    'name' => esc_html__('Message Body for Admin', 'wp-sms'),
                    'type' => 'textarea',
                    'desc' => esc_html__('Customize the SMS template sent to the Admin Mobile Number for new user registrations using placeholders for user details.', 'wp-sms') . '<br>' . NotificationFactory::getUser()->printVariables()
                ),
                'notif_register_new_user_template'        => array(
                    'id'   => 'notif_register_new_user_template',
                    'name' => esc_html__('Message Body for User', 'wp-sms'),
                    'type' => 'textarea',
                    'desc' => esc_html__('Customize the SMS template sent to the user upon registration using placeholders for personal details.', 'wp-sms') . '<br>' . NotificationFactory::getUser()->printVariables()
                ),
                // New comment
                'notif_new_comment_title'                 => array(
                    'id'   => 'notif_new_comment_title',
                    'name' => $this->renderOptionHeader(
                        esc_html__('New Comment Notification', 'wp-sms'),
                        esc_html__('Receive SMS alerts on the Admin Mobile Number when a new comment is posted.', 'wp-sms')
                    ),
                    'type' => 'header'
                ),
                'notif_new_comment'                       => array(
                    'id'      => 'notif_new_comment',
                    'name'    => esc_html__('Status', 'wp-sms'),
                    'type'    => 'checkbox',
                    'options' => $options,
                    'desc'    => esc_html__('Receiving SMS alerts on the Admin Mobile Number for each new comment.', 'wp-sms')
                ),
                'notif_new_comment_template'              => array(
                    'id'   => 'notif_new_comment_template',
                    'name' => esc_html__('Message Body', 'wp-sms'),
                    'type' => 'textarea',
                    'desc' => esc_html__('Create the SMS message for new comment alerts. Include details using placeholders:', 'wp-sms') . '<br>' . NotificationFactory::getComment()->printVariables()
                ),
                // User login
                'notif_user_login_title'                  => array(
                    'id'   => 'notif_user_login_title',
                    'name' => $this->renderOptionHeader(
                        esc_html__('User Login Notification', 'wp-sms'),
                        esc_html__('Configure SMS notifications to be sent to the Admin Mobile Number whenever a user logs in.', 'wp-sms')
                    ),
                    'type' => 'header'
                ),
                'notif_user_login'                        => array(
                    'id'      => 'notif_user_login',
                    'name'    => esc_html__('Status', 'wp-sms'),
                    'type'    => 'checkbox',
                    'options' => $options,
                    'desc'    => esc_html__('SMS notifications to be sent to the Admin Mobile Number on user login.', 'wp-sms')
                ),
                'notif_user_login_roles'                  => array(
                    'id'      => 'notif_user_login_roles',
                    'name'    => esc_html__('Specific Roles', 'wp-sms'),
                    'type'    => 'multiselect',
                    'options' => $this->getRoles(),
                    'desc'    => esc_html__('Choose user roles that trigger login notifications.', 'wp-sms')
                ),
                'notif_user_login_template'               => array(
                    'id'   => 'notif_user_login_template',
                    'name' => esc_html__('Message Body', 'wp-sms'),
                    'type' => 'textarea',
                    'desc' => esc_html__('Format the SMS message sent upon user login. Utilize placeholders to include user details:', 'wp-sms') . '<br>' . NotificationFactory::getUser()->printVariables()
                )
            )),

            /**
             * Contact form 7 fields
             */
            'contact_form7'  => apply_filters('wp_sms_contact_form7_settings', array(
                'cf7_title'   => array(
                    'id'   => 'cf7_title',
                    'name' => esc_html__('SMS Notification Metabox', 'wp-sms'),
                    'type' => 'header',
                    'doc'  => '/resources/integrate-wp-sms-with-contact-form-7/',
                    'desc' => esc_html__('By this option you can add SMS notification tools in all edit forms.', 'wp-sms'),
                ),
                'cf7_metabox' => array(
                    'id'      => 'cf7_metabox',
                    'name'    => esc_html__('Status', 'wp-sms'),
                    'type'    => 'checkbox',
                    'options' => $options,
                    'desc'    => esc_html__('This option adds SMS Notification tab in the edit forms.', 'wp-sms')
                ),
            )),

            'formidable' => apply_filters('wp_sms_formidable_settings', []),

            'forminator'           => apply_filters('wp_sms_forminator_settings', [], $options),

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
                'name' => $this->renderOptionHeader(
                    esc_html__('Data Protection Settings', 'wp-sms'),
                    esc_html__('Set up how you comply with data protection regulations', 'wp-sms')
                ),
                'type' => 'header'
            );

            $settings['newsletter']['newsletter_form_gdpr_text'] = array(
                'id'   => 'newsletter_form_gdpr_text',
                'name' => esc_html__('Consent Text', 'wp-sms'),
                'type' => 'textarea',
                'desc' => esc_html__('Provide a clear message that informs subscribers how their data will be used and that their consent is required. For example: "I agree to receive SMS notifications and understand that my data will be handled according to the privacy policy."', 'wp-sms'),
            );

            $settings['newsletter']['newsletter_form_gdpr_confirm_checkbox'] = array(
                'id'      => 'newsletter_form_gdpr_confirm_checkbox',
                'name'    => esc_html__('Checkbox Default', 'wp-sms'),
                'type'    => 'select',
                'options' => array('checked' => 'Checked', 'unchecked' => 'Unchecked'),
                'desc'    => esc_html__('Leave the consent checkbox unchecked by default to comply with privacy laws, which require active, explicit consent from users.', 'wp-sms')
            );
        } else {
            $settings['newsletter']['newsletter_gdpr'] = array(
                'id'   => 'gdpr_notify',
                'name' => esc_html__('GDPR Compliance', 'wp-sms'),
                'type' => 'notice',
                'desc' => esc_html__('To get more option for GDPR, you should enable that in the general tab.', 'wp-sms')
            );
        }

        return $settings;
    }

    private function isCurrentTab($tab)
    {
        return isset($_REQUEST['page']) && in_array($_REQUEST['page'], ['wp-sms-settings', 'wp-sms-integrations']) && isset($_REQUEST['tab']) && $_REQUEST['tab'] == $tab;
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
            $status = esc_html__('Activated', 'wp-sms');
            $type   = 'active';

            if ($constantLicenseKey) {
                $this->options["license_{$addOnKey}_status"] = true;
                $updateOption                                = true;
            }
        } else {
            $status                                      = esc_html__('Deactivated', 'wp-sms');
            $type                                        = 'inactive';
            $this->options["license_{$addOnKey}_status"] = false;
            $updateOption                                = true;
        }

        if ($updateOption && empty($_POST)) {
            update_option($this->setting_name, $this->options);
        }

        return Helper::loadTemplate('admin/label-button.php', array(
            'type'  => $type,
            'label' => $status
        ));
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
            $html .= '<div class="wpsms-settings-description-title">' . $args['desc'] . '</div>';
        }

        if ($args['doc']) {
            $documentUrl = WP_SMS_SITE . $args['doc'];
            $html        .= sprintf('<div class="wpsms-settings-description-header"><a href="%s" target="_blank">%s <span class="dashicons dashicons-external"></span></a></div>', esc_url($documentUrl), esc_html__('Documentation', 'wp-sms'));
        }

        echo "<div class='wpsms-settings-header-field'>{$html}</div>"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    public function repeater_callback($args)
    {
        if (isset($this->options[$args['id']])) {
            $value = $this->options[$args['id']];
        } else {
            $value = isset($args['std']) ? $args['std'] : '';
        }

        $args = [
            'args'    => $args,
            'value'   => $value,
            'options' => $args['options'],
        ];

        echo Helper::loadTemplate($args['options']['template'], $args); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    public function html_callback($args)
    {
        echo wp_kses_normalize_entities($args['options']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    public function notice_callback($args)
    {
        echo sprintf('%s', wp_kses_post($args['desc']));
    }

    public function checkbox_callback($args)
    {
        $checked = isset($this->options[$args['id']]) ? checked(1, $this->options[$args['id']], false) : '';
        $html    = sprintf('<input type="checkbox" id="' . esc_attr($this->setting_name) . '[%1$s]" name="' . esc_attr($this->setting_name) . '[%1$s]" value="1" %2$s /><label for="' . esc_attr($this->setting_name) . '[%1$s]"> ' . esc_html__('Active', 'wp-sms') . '</label><p class="description">%3$s</p>', esc_attr($args['id']), esc_attr($checked), wp_kses_post($args['desc']));
        echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
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

        echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
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
            $html .= sprintf('<input name="' . esc_attr($this->setting_name) . '[%1$s]"" id="' . esc_attr($this->setting_name) . '[%1$s][%2$s]" type="radio" value="%2$s" %3$s /><label for="' . esc_attr($this->setting_name) . '[%1$s][%2$s]">%4$s</label>&nbsp;&nbsp;', esc_attr($args['id']), esc_attr($key), checked(true, $checked, false), $option);
        endforeach;
        $html .= sprintf('<p class="description">%1$s</p>', wp_kses_post($args['desc']));
        echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
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
        $html        = sprintf('<input dir="auto" type="text" class="%1$s-text" id="' . esc_attr($this->setting_name) . '[%2$s]" name="' . esc_attr($this->setting_name) . '[%2$s]" value="%3$s"/>%4$s<p class="description">%5$s</p>', esc_attr($size), esc_attr($args['id']), esc_attr(stripslashes($value)), $after_input, wp_kses_post($args['desc']));
        echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
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
        $html = sprintf('<input dir="auto" type="number" step="%1$s" max="%2$s" min="%3$s" class="%4$s-text" id="' . esc_attr($this->setting_name) . '[%5$s]" name="' . esc_attr($this->setting_name) . '[%5$s]" value="%6$s"/><p class="description"> %7$s</p>', esc_attr($step), esc_attr($max), esc_attr($min), esc_attr($size), esc_attr($args['id']), esc_attr(stripslashes($value)), wp_kses_post($args['desc']));
        echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    public function textarea_callback($args)
    {
        if (isset($this->options[$args['id']])) {
            $value = $this->options[$args['id']];
        } else {
            $value = isset($args['std']) ? $args['std'] : '';
        }

        $html = sprintf('<textarea dir="auto" class="large-text" cols="50" rows="5" id="' . esc_attr($this->setting_name) . '[%1$s]" name="' . esc_attr($this->setting_name) . '[%1$s]">%2$s</textarea><div class="description"> %3$s</div>', esc_attr($args['id']), esc_textarea(stripslashes($value)), wp_kses_post($args['desc']));
        echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    public function password_callback($args)
    {
        if (isset($this->options[$args['id']])) {
            $value = $this->options[$args['id']];
        } else {
            $value = isset($args['std']) ? $args['std'] : '';
        }

        $size = (isset($args['size']) && !is_null($args['size'])) ? $args['size'] : 'regular';
        $html = sprintf('<input type="password" class="%1$s-text" id="' . esc_attr($this->setting_name) . '[%2$s]" name="' . esc_attr($this->setting_name) . '[%2$s]" value="%3$s"/><p class="description"> %4$s</p>', esc_attr($size), esc_attr($args['id']), esc_attr($value), wp_kses_post($args['desc']));

        echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
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

        $html = sprintf('<select id="' . esc_attr($this->setting_name) . '[%1$s]" name="' . esc_attr($this->setting_name) . '[%1$s]" %2$s>', esc_attr($args['id']), implode(' ', $attributes));

        foreach ($args['options'] as $option => $name) {
            $selected = selected($option, $value, false);
            $html     .= sprintf('<option value="%1$s" %2$s>%3$s</option>', esc_attr($option), esc_attr($selected), $name);
        }

        $html .= sprintf('</select><p class="description"> %1$s</p>', wp_kses_post($args['desc']));

        echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    public function multiselect_callback($args)
    {
        if (isset($this->options[$args['id']])) {
            $value = $this->options[$args['id']];
        } else {
            $value = isset($args['std']) ? $args['std'] : '';
        }

        $html     = sprintf('<select id="' . esc_attr($this->setting_name) . '[%1$s]" name="' . esc_attr($this->setting_name) . '[%1$s][]" multiple="true" class="js-wpsms-select2"/>', esc_attr($args['id']));
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

        echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    public function countryselect_callback($args)
    {
        if (isset($this->options[$args['id']])) {
            $value = $this->options[$args['id']];
        } else {
            $value = isset($args['std']) ? $args['std'] : '';
        }

        $html     = sprintf('<select id="' . esc_attr($this->setting_name) . '[%1$s]" name="' . esc_attr($this->setting_name) . '[%1$s][]" multiple="true" class="js-wpsms-select2"/>', esc_attr($args['id']));
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

        echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    public function advancedselect_callback($args)
    {
        if (isset($this->options[$args['id']])) {
            $value = $this->options[$args['id']];
        } else {
            $value = isset($args['std']) ? $args['std'] : '';
        }

        $class_name = 'js-wpsms-select2';
        $html       = sprintf('<select class="%1$s" id="' . esc_attr($this->setting_name) . '[%2$s]" name="' . esc_attr($this->setting_name) . '[%2$s]">', esc_attr($class_name), esc_attr($args['id']));

        foreach ($args['options'] as $key => $v) {
            $html .= sprintf('<optgroup data-options="" label="%1$s">', ucfirst(str_replace('_', ' ', $key)));

            foreach ($v as $option => $name) {
                $disabled = '';

                if (!$this->proIsInstalled && array_column(Gateway::$proGateways, $option)) {
                    $disabled = ' disabled';
                    $name     .= '<span> ' . esc_html__('- (Pro Pack)', 'wp-sms') . '</span>';
                }

                $selected = selected($option, $value, false);
                $html     .= sprintf('<option value="%1$s" %2$s %3$s>%4$s</option>', esc_attr($option), esc_attr($selected), esc_attr($disabled), ucfirst($name));
            }

            $html .= '</optgroup>';
        }

        $html .= sprintf('</select><p class="description"> %1$s</p>', wp_kses_post($args['desc']));

        echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    public function advancedmultiselect_callback($args)
    {
        if (isset($this->options[$args['id']])) {
            $value = $this->options[$args['id']];
        } else {
            $value = isset($args['std']) ? $args['std'] : '';
        }

//        $class_name = 'js-wpsms-select2';
        $html     = sprintf('<select id="' . esc_attr($this->setting_name) . '[%1$s]" name="' . esc_attr($this->setting_name) . '[%1$s][]" multiple="true" class="js-wpsms-select2"/>', esc_attr($args['id']));
        $selected = '';

        foreach ($args['options'] as $k => $v) :
            $html .= sprintf('<optgroup data-options="" label="%1$s">', ucfirst(str_replace('_', ' ', $k)));

            foreach ($v as $option => $name) :
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

        echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

    }

    public function color_select_callback($args)
    {
        if (isset($this->options[$args['id']])) {
            $value = $this->options[$args['id']];
        } else {
            $value = isset($args['std']) ? $args['std'] : '';
        }

        $html = sprintf('<select id="' . esc_attr($this->setting_name) . '[%1$s]" name="' . esc_attr($this->setting_name) . '[%1$s]">', esc_attr($args['id']));

        foreach ($args['options'] as $option => $color) :
            $selected = selected($option, $value, false);
            $html     .= esc_attr('<option value="%1$s" %2$s>%3$s</option>', esc_attr($option), esc_attr($selected), $color['label']);
        endforeach;

        $html .= sprintf('</select><p class="description"> %1$s</p>', wp_kses_post($args['desc']));

        echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
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
            $html = sprintf('<textarea class="large-text" rows="10" id="' . esc_attr($this->setting_name) . '[%1$s]" name="' . esc_attr($this->setting_name) . '[%1$s]">' . esc_textarea(stripslashes($value)) . '</textarea>', esc_attr($args['id']));
        }

        $html .= sprintf('<p class="description"> %1$s</p>', wp_kses_post($args['desc']));

        echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    public function upload_callback($args)
    {
        if (isset($this->options[$args['id']])) {
            $value = $this->options[$args['id']];
        } else {
            $value = isset($args['std']) ? $args['std'] : '';
        }

        $size = (isset($args['size']) && !is_null($args['size'])) ? $args['size'] : 'regular';
        $html = sprintf(
            '<input type="text" class="%1$s-text ' . esc_attr($this->setting_name) . '_upload_field" id="' . esc_attr($this->setting_name) . '[%2$s]" name="' . esc_attr($this->setting_name) . '[%2$s]" value="%3$s"/><span>&nbsp;<input type="button" class="' . esc_attr($this->setting_name) . '_upload_button button button-secondary" data-target="' . esc_attr($this->setting_name) . '[%2$s]" value="%4$s"/></span><p class="description"> %5$s</p>',
            esc_attr($size),
            esc_attr($args['id']),
            esc_attr(stripslashes($value)),
            esc_html__('Upload File', 'wpsms'),
            wp_kses_post($args['desc'])
        );

        echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    public function color_callback($args)
    {
        if (isset($this->options[$args['id']])) {
            $value = $this->options[$args['id']];
        } else {
            $value = isset($args['std']) ? $args['std'] : '';
        }

        $default = isset($args['std']) ? $args['std'] : '';
        $html    = sprintf('<input type="text" class="wpsms-color-picker" id="' . esc_attr($this->setting_name) . '[%1$s]" name="' . esc_attr($this->setting_name) . '[%1$s]" value="%2$s" data-default-color="%3$s" /><p class="description"> %4$s</p>', esc_attr($args['id']), esc_attr($value), esc_attr($default), wp_kses_post($args['desc']));

        echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }


    /**
     * args[] : header_template
     */
    public function render_settings($default = "general", $args = array())
    {
        $this->active_tab        = isset($_GET['tab']) && array_key_exists($_GET['tab'], $this->get_tabs()) ? sanitize_text_field($_GET['tab']) : $default;
        $this->contentRestricted = in_array($this->active_tab, $this->proTabs) && !$this->proIsInstalled;
        $args                    = wp_parse_args($args, [
            'setting'  => true,
            'template' => '' //must be a callable function
        ]);
        $args                    = apply_filters('wp_sms_settings_render_' . $this->active_tab, $args);
        ob_start(); ?>
        <div class="wrap wpsms-wrap wpsms-settings-wrap">
            <?php echo isset($args['header_template']) ? Helper::loadTemplate($args['header_template']) : Helper::loadTemplate('header.php'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            ?>
            <div class="wpsms-wrap__top">
                <?php do_action('wp_sms_settings_page');

                if (isset($args['title'])) {
                    echo '<h2>' . esc_html($args['title']) . '</h2>';
                }
                ?>
            </div>
            <div class="wp-header-end"></div>
            <?php echo settings_errors('wpsms-notices'); ?>
            <div class="wpsms-wrap__main">
                <div class="wpsms-tab-group">
                    <ul class="wpsms-tab">
                        <?php
                        $addOns = array_filter($this->get_tabs(), function ($t, $id) {
                            if (strpos($id, 'addon_') !== false) return $t;
                        }, ARRAY_FILTER_USE_BOTH);

                        $tabCheck = function ($tab_id, $tab_name) {
                            $tab_url = add_query_arg(array(
                                'settings-updated' => false,
                                'tab'              => $tab_id
                            ));

                            $active      = $this->active_tab == $tab_id ? 'active' : '';
                            $isProTab    = in_array($tab_id, $this->proTabs) ? ' is-pro-tab' : '';
                            $proLockIcon = '';

                            if ($isProTab) {
                                if (!$this->proIsInstalled) {
                                    $proLockIcon = '</a><span class="pro-not-installed"><a href="' . esc_url(WP_SMS_SITE) . '/buy" target="_blank">PRO</a></span></li>';
                                }
                            }
                            $tabUrl = ($tab_id == 'integrations') ? esc_url(WP_SMS_ADMIN_URL . 'admin.php?page=wp-sms-integrations') : esc_url($tab_url);
                            echo '<li class="tab-' . esc_attr($tab_id) . esc_attr($isProTab) . '"><a href="' . $tabUrl . '" title="' . esc_attr($tab_name) . '" class="' . esc_attr($active) . '">';
                            echo esc_html($tab_name);
                            echo '</a>' . $proLockIcon . '</li>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                        };

                        foreach ($this->get_tabs() as $tab_id => $tab_name) {

                            // Skip showing licenses in side tabs
                            if ($tab_id == 'licenses') {
                                continue;
                            }

                            if (array_key_exists($tab_id, $addOns)) continue;

                            $tabCheck($tab_id, $tab_name);
                        }

                        // Show Add-Ons label
                        if ($addOns) {
                            echo '<li class="tab-section-header">' . esc_html__('ADD-ONS', 'wp-sms') . '</li>';

                            foreach ($addOns as $tab_id => $tab_name) {
                                $tabCheck($tab_id, $tab_name);
                            }
                        }

                        ?>

                        <?php if (isset($_GET['page']) and in_array($_GET['page'], ['wp-sms-integrations'])) {
                            echo \WP_SMS\Helper::loadTemplate('zapier-section.php'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                        } ?>
                    </ul>

                    <div class="wpsms-tab-content<?php echo esc_attr($this->contentRestricted) ? ' pro-not-installed' : ''; ?> <?php echo esc_attr($this->active_tab) . '_settings_tab' ?>">
                        <?php
                        if (isset($args['setting']) && $args['setting'] == true) {
                            $this->renderWpSetting();
                        } else if (isset($args['template']) && $args['template'] != "") {
                            call_user_func($args['template'], []);
                        }
                        ?>

                    </div>
                </div>
            </div>
        </div>
        <?php
        echo ob_get_clean(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    private function isActiveTab()
    {

    }

    private function renderWpSetting()
    {
        ?>
        <form method="post" action="options.php">
            <table class="form-table">
                <?php
                settings_fields($this->setting_name);
                do_settings_fields("{$this->setting_name}_{$this->active_tab}", "{$this->setting_name}_{$this->active_tab}"); ?>
            </table>

            <?php
            if (!$this->contentRestricted) {
                submit_button();
            } ?>
        </form>
        <?php
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
     * Return a list of public taxonomies and terms which are not empty
     *
     * @return array
     */
    public function getTaxonomiesAndTerms()
    {
        $result     = [];
        $taxonomies = get_taxonomies(array(
            'public' => true,
        ));

        foreach ($taxonomies as $taxonomy) {

            $terms = get_terms(array(
                'taxonomy'   => $taxonomy,
                'hide_empty' => false,
                'orderby'    => 'name',
                'order'      => 'ASC',
                'public'     => true,
            ));

            if (isset($terms)) {
                foreach ($terms as $term) {
                    $result[$taxonomy][$term->term_id] = ucfirst($term->name);
                }
            }

        }

        return $result;
    }

    public function getRoles()
    {
        $wpsms_list_of_role = Helper::getListOfRoles();
        $roles              = [];

        foreach ($wpsms_list_of_role as $key_item => $val_item) {
            $roles[] = [$key_item => $val_item['name']];
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

        $return_value = array();
        foreach ($ultimate_member_forms as $form) {
            $form_role = get_post_meta($form->ID, '_um_mode');

            if (in_array('register', $form_role)) {
                $form_fields = get_post_meta($form->ID, '_um_custom_fields');

                foreach ($form_fields[0] as $field) {
                    if (isset($field['title']) && isset($field['metakey'])) {
                        $return_value[$field['metakey']] = $field['title'];
                    }
                }
            }
        }
        return $return_value;
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
                'name' => esc_html__('No Pro Pack or Add-On found', 'wp-sms'),
                'desc' => sprintf('If you have already installed the Pro Pack or Add-On(s) but the license field is not showing-up, get and install the latest version through <a href="%s" target="_blank">your account</a> again.', esc_url(WP_SMS_SITE . '/my-account/orders/?utm_source=wp-sms&utm_medium=link&utm_campaign=account'))
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
                'desc' => esc_html__('License key is used to get access to automatic updates and support.', 'wp-sms')
            );

            // license key
            $settings["license_{$addOnKey}_key"] = array(
                'id'          => "license_{$addOnKey}_key",
                'name'        => esc_html__('License Key', 'wp-sms'),
                'type'        => 'text',
                'after_input' => $this->getLicenseStatusIcon($addOnKey),
                // translators: %s: Account link
                'desc'        => sprintf(__('To get the license, please go to <a href="%s" target="_blank">your account</a>.', 'wp-sms'), esc_url(WP_SMS_SITE . '/my-account/orders/?utm_source=wp-sms&utm_medium=link&utm_campaign=account'))
            );
        }

        return $settings;
    }

    /**
     * This private method is used to render the header of an option.
     * It accepts two parameters: the title of the option and an optional tooltip.
     * If a tooltip is provided, it is appended to the title inside a span with the class "tooltip".
     * The method returns the final title string.
     *
     * @param string $title The title of the option.
     * @param string|bool $tooltip Optional. The tooltip to be appended to the title. Default is false.
     * @return string The final title string.
     */
    private function renderOptionHeader($title, $tooltip = false)
    {
        // Check if a tooltip is provided
        if ($tooltip) {
            // If a tooltip is provided, append it to the title inside a span with the class "tooltip"
            $title .= '&nbsp;' . sprintf('<span class="wpsms-tooltip" title="%s"><i class="wpsms-tooltip-icon"></i></span>', $tooltip);
        }

        // Return the final title string
        return $title;
    }
}
