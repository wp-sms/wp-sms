<?php

namespace WP_SMS;

use Forminator_API;
use WP_SMS\Components\View;
use WP_SMS\Notification\NotificationFactory;
use WP_SMS\Services\Forminator\Forminator;
use WP_SMS\Admin\LicenseManagement\LicenseHelper;
use WP_SMS\Utils\PluginHelper;
use WP_SMS\Utils\Request;

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
    private $pluginIntegrationsTabs = [];
    private $active_tab;

    /**
     * @return string
     */
    private function getCurrentOptionName()
    {
        if (isset($_REQUEST['tab']) && in_array($_REQUEST['tab'], $this->pluginIntegrationsTabs)) {
            return $this->optionNames['pro'];
        }

        if (isset($_POST['option_page']) && $_POST['option_page'] == 'wps_pp_settings') {
            return $this->optionNames['pro'];
        }

        return $this->optionNames['main'];
    }

    public function __construct()
    {
        $this->applyPluginIntegrationsFilter();

        $this->setting_name = $this->getCurrentOptionName();

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

        if (isset($_POST['submit']) && isset($_REQUEST['option_page']) && $_POST['option_page'] == 'wpsms_settings' && strpos(wp_get_referer(), 'tab=gateway')) {
            add_filter('pre_update_option_wpsms_settings', [$this, 'updateGateWayVersion'], 10, 2);
        }
    }

    /**
     * Applies a filter to modify the list of plugin integration tabs.
     *
     * @return void
     */
    private function applyPluginIntegrationsFilter()
    {
        $this->pluginIntegrationsTabs = apply_filters('plugin_integrations_tabs', $this->pluginIntegrationsTabs);
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
                'report_wpsms_statistics'      => 1,
                'display_notifications'        => 1,
                'store_outbox_messages'        => 1,
                'outbox_retention_days'        => 90,
                'store_inbox_messages'         => 1,
                'inbox_retention_days'         => 90,
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

                $has_label_for = in_array($option['type'], ['text', 'select', 'textarea', 'number']);

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
                        'label_for'   => $has_label_for ? esc_attr($this->setting_name) . '[' . esc_attr($optionId) . ']' : null,
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
            'advanced'       => esc_html__('Advanced Options', 'wp-sms'),

            /*
             * Pro Pack tabs
             */
            'integrations'   => esc_html__('Integrations', 'wp-sms'),
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
        if (!empty($referrer['tab'])) {
            $tab = $referrer['tab'];
        } elseif (Request::has('wpsms_active_tab')) {
            $tab = Request::get('wpsms_active_tab');
        } else {
            $tab = 'general';
        }

        $input = $input ? $input : array();
        // Handle unchecked checkboxes: if checkbox wasn't submitted, user unchecked it
        if (!empty($settings[$tab])) {
            foreach ($settings[$tab] as $s_key => $field) {

                // Support numeric keys (legacy)
                if (is_numeric($s_key)) {
                    $s_key = $field['id'];
                }

                $type = isset($field['type']) ? $field['type'] : false;

                if ($type === 'checkbox') {
                    // If checkbox key not in POST, user unchecked it — mark as empty so it will be unset later
                    if (!array_key_exists($s_key, $input)) {
                        $input[$s_key] = '';
                    }
                }
            }
        }
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

        // Merge our new settings with the existing
        $output = array_merge($this->options, $input);

        if (!empty($settings[$tab])) {
            foreach ($settings[$tab] as $field_key => $field) {
                if (is_numeric($field_key)) {
                    $field_key = $field['id'];
                }

                $type = isset($field['type']) ? $field['type'] : false;

                if ($type === 'checkbox') {
                    $wasSubmitted = array_key_exists($field_key, $input);

                    if (!$wasSubmitted) {
                        unset($output[$field_key]);
                        continue;
                    }

                    if (empty($input[$field_key])) {
                        unset($output[$field_key]);
                        continue;
                    }

                    $output[$field_key] = $input[$field_key];
                } else {
                    if (array_key_exists($field_key, $input) && $input[$field_key] === '') {
                        unset($output[$field_key]);
                    }
                }
            }
        }

        add_settings_error(
            'wpsms-notices',
            '',
            esc_html__('Settings Successfully Saved.', 'wp-sms'),
            'notice-success wpsms-admin-notice'
        );
        $this->options = $output;
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

        $retentionOptions = [
            30  => __('30 days', 'wp-sms'),
            90  => __('90 days', 'wp-sms'),
            180 => __('180 days', 'wp-sms'),
            365 => __('365 days', 'wp-sms'),
            0   => __('Keep forever', 'wp-sms'),
        ];

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
                    'attributes' => ['class' => 'js-wpsms-select2', 'aria-label' => esc_html__('Country Code Prefix', 'wp-sms')],
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
                    'id'           => 'gateway_username',
                    'name'         => esc_html__('API Username', 'wp-sms'),
                    'type'         => 'text',
                    'place_holder' => esc_html__('e.g., YourGatewayUsername123', 'wp-sms'),
                    'desc'         => esc_html__('Enter the username provided by your SMS gateway.', 'wp-sms')
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
                    'desc' => esc_html__('This is the number or sender ID displayed on recipients’ devices.
It might be a phone number (e.g., +1 555 123 4567) or an alphanumeric ID if supported by your gateway.', 'wp-sms')
                ),
                'gateway_key'                  => array(
                    'id'   => 'gateway_key',
                    'name' => esc_html__('API Key', 'wp-sms'),
                    'type' => 'text',
                    'desc' => esc_html__('Enter API key of gateway', 'wp-sms')
                ),
                're_run_setup_wizard'          => array(
                    'id'      => 're_run_setup_wizard',
                    'name'    => esc_html__('WP SMS Setup Wizard', 'wp-sms'),
                    'type'    => 'html',
                    'options' => '
                        <div>
                            <a href="' . admin_url('admin.php?page=wp-sms&path=wp-sms-onboarding') . '" target="_blank" class="button button-primary">' . esc_html__('Re-run Setup Wizard', 'wp-sms') . '</a><br>
                        </div>
                    ',
                    'desc'    => esc_html__('Need to debug or update your gateway settings? Relaunch the WP SMS Setup Wizard for a guided, step-by-step process. This will help you verify your credentials, test sending/receiving capabilities, and ensure everything is running smoothly.', 'wp-sms')
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
                    'name'    => esc_html__('Incoming Message', 'wp-sms'),
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
                'display_notifications_header' => array(
                    'id'   => 'display_notifications_header',
                    'name' => esc_html__('Plugin Notifications', 'wp-sms'),
                    'type' => 'header'
                ),
                'display_notifications'        => array(
                    'id'      => 'display_notifications',
                    'name'    => esc_html__('WP SMS Notifications', 'wp-sms'),
                    'type'    => 'checkbox',
                    'options' => $options,
                    'default' => true,
                    'desc'    => esc_html__('Display important notifications inside the plugin about new versions, feature updates, news, and special offers.', 'wp-sms')
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
                'share_anonymous_data_header'  => array(
                    'id'   => 'share_anonymous_data_header',
                    'name' => esc_html__('Anonymous Usage Data', 'wp-sms'),
                    'type' => 'header',
                ),
                'share_anonymous_data'         => array(
                    'id'      => 'share_anonymous_data',
                    'name'    => esc_html__('Share Anonymous Data', 'wp-sms'),
                    'type'    => 'checkbox',
                    'options' => $options,
                    'desc'    => __('Sends non-personal, anonymized data to help us improve WP SMS. No personal or identifying information is collected or shared. <a href="https://wp-sms-pro.com/resources/sharing-your-data-with-us/?utm_source=wp-sms&utm_medium=link&utm_campaign=settings" target="_blank">Learn More</a>.', 'wp-sms'),
                ),
                'store_outbox_messages_header' => [
                    'id'   => 'store_outbox_messages_header',
                    'name' => esc_html__('Message Storage & Cleanup', 'wp-sms'),
                    'type' => 'header',
                ],
                'store_outbox_messages'        => [
                    'id'      => 'store_outbox_messages',
                    'name'    => esc_html__('Store Outbox Messages', 'wp-sms'),
                    'type'    => 'checkbox',
                    'options' => $options,
                    'desc'    => esc_html__('If disabled, new SMS will not be logged in the Outbox.', 'wp-sms'),
                ],
                'outbox_retention_days'        => [
                    'id'        => 'outbox_retention_days',
                    'name'      => esc_html__('Delete Outbox Messages Older Than', 'wp-sms'),
                    'type'      => 'select',
                    'className' => 'js-wpsms-show_if_store_outbox_messages_enabled',
                    'options'   => $retentionOptions,
                    'desc'      => esc_html__('Runs daily at 00:00 (site time). Choose how long to retain Outbox messages.', 'wp-sms')
                ],
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
            /**
             * @deprecated This filter is no longer used and has no replacement.
             */
            'pro_wordpress'        => apply_filters('wp_sms_pro_wp_settings', []),
            /**
             * @deprecated This filter is no longer used and has no replacement.
             */
            'pro_buddypress'       => apply_filters('wp_sms_pro_bp_settings', []),
            /**
             * @deprecated This filter is no longer used and has no replacement.
             */
            'pro_woocommerce'      => apply_filters('wp_sms_pro_wc_settings', []),
            /**
             * @deprecated This filter is no longer used and has no replacement.
             */
            'pro_gravity_forms'    => apply_filters('wp_sms_pro_gf_settings', []),
            /**
             * @deprecated This filter is no longer used and has no replacement.
             */
            'pro_quform'           => apply_filters('wp_sms_pro_qf_settings', []),
            /**
             * @deprecated This filter is no longer used and has no replacement.
             */
            'pro_edd'              => apply_filters('wp_sms_pro_edd_settings', []),
            /**
             * @deprecated This filter is no longer used and has no replacement.
             */
            'pro_wp_job_manager'   => apply_filters('wp_sms_job_settings', []),
            /**
             * @deprecated This filter is no longer used and has no replacement.
             */
            'pro_awesome_support'  => apply_filters('wp_sms_as_settings', []),
            /**
             * @deprecated This filter is no longer used and has no replacement.
             */
            'pro_ultimate_members' => apply_filters('wp_sms_pro_um_settings', []),

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
        echo sprintf('<p class="description">%1$s</p>', wp_kses_post($args['desc']));
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

        $html     = sprintf('<select id="' . esc_attr($this->setting_name) . '[%1$s]" aria-label="' . esc_attr($this->setting_name) . '[%1$s][]" name="' . esc_attr($this->setting_name) . '[%1$s][]" multiple="true" class="js-wpsms-select2"/>', esc_attr($args['id']));
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

        $html     = sprintf('<select id="' . esc_attr($this->setting_name) . '[%1$s]" aria-label="' . esc_attr($this->setting_name) . '[%1$s][]" name="' . esc_attr($this->setting_name) . '[%1$s][]" multiple="true" class="js-wpsms-select2"/>', esc_attr($args['id']));
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
        $html       = sprintf('<select class="%1$s" id="' . esc_attr($this->setting_name) . '[%2$s]"  aria-label="' . esc_attr($this->setting_name) . '[%2$s]" name="' . esc_attr($this->setting_name) . '[%2$s]">', esc_attr($class_name), esc_attr($args['id']));
        foreach ($args['options'] as $key => $v) {
            $html .= sprintf('<optgroup data-options="" label="%1$s">', ucfirst(str_replace('_', ' ', $key)));

            foreach ($v as $option => $name) {
                $options = apply_filters('wp_sms_gateway_select_item_options', [
                    'option'   => $option,
                    'name'     => $name,
                    'selected' => $option == $value,
                    'disabled' => array_column(Gateway::$proGateways, $option) ? true : false,
                ]);

                if ($options['disabled']) {
                    $options['name'] .= '<span> ' . esc_html__('- (All-in-One Required)', 'wp-sms') . '</span>';
                }

                $html .= sprintf('<option value="%1$s" %2$s %3$s>%4$s</option>', esc_attr($options['option']), selected($options['selected'], true, false), disabled($options['disabled'], true, false), ucfirst($options['name']));
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
        $html     = sprintf('<select id="' . esc_attr($this->setting_name) . '[%1$s]" name="' . esc_attr($this->setting_name) . '[%1$s][]" multiple="true" aria-label="' . esc_attr($this->setting_name) . '[%1$s][]" class="js-wpsms-select2"/>', esc_attr($args['id']));
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
            esc_html__('Upload File', 'wp-sms'),
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
     * @throws \Exception
     */
    public function render_settings($default = "general", $args = array())
    {
        $this->active_tab = isset($_GET['tab']) && array_key_exists($_GET['tab'], $this->get_tabs()) ? sanitize_text_field($_GET['tab']) : $default;
        $args             = wp_parse_args($args, [
            'setting'  => true,
            'template' => '' //must be a callable function
        ]);
        $args             = apply_filters('wp_sms_settings_render_' . $this->active_tab, $args);
        ob_start(); ?>
        <div class="wrap wpsms-wrap wpsms-settings-wrap">
            <?php echo isset($args['header_template']) ? Helper::loadTemplate($args['header_template']) : Helper::loadTemplate('header.php'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            View::load('components/objects/share-anonymous-notice');
            ?>
            <div class="wpsms-wrap__top">
                <?php do_action('wp_sms_settings_page');

                if (isset($args['title'])) {
                    echo '<h2>' . esc_html($args['title']) . '</h2>';
                }
                ?>
            </div>
            <div class="wp-header-end"></div>
            <?php settings_errors('wpsms-notices'); ?>
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

                            $active            = $this->active_tab == $tab_id ? 'active' : '';
                            $ariaDisabled      = $this->active_tab != $tab_id ? ' aria-disabled="true"' : '';
                            $isIntegrationsTab = in_array($tab_id, $this->pluginIntegrationsTabs) ? ' is-pro-tab' : '';

                            $tabUrl = ($tab_id == 'integrations') ? esc_url(WP_SMS_ADMIN_URL . 'admin.php?page=wp-sms-integrations') : esc_url($tab_url);
                            echo '<li class="tab-' . esc_attr($tab_id) . esc_attr($isIntegrationsTab) . '"><a href="' . esc_url($tabUrl) . '" title="' . esc_attr($tab_name) . '" class="' . esc_attr($active) . '"' . $ariaDisabled . '>';
                            echo esc_html($tab_name);
                            echo '</a></li>';
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
                        <li>
                            <?php
                            if (apply_filters('wp_sms_enable_upgrade_notice', true)) :
                                $isIntegrationsPage = isset($_GET['page']) && $_GET['page'] === 'wp-sms-integrations';
                                $noticeConfig       = $isIntegrationsPage ? [
                                    'link'      => 'https://wp-sms-pro.com/pricing/?utm_source=wp-sms&utm_medium=link&utm_campaign=integrations',
                                    'link_text' => esc_html__('Upgrade to unlock everything.', 'wp-sms'),
                                    'title'     => sprintf(
                                    /* translators: %s: Plugin name (WP SMS All-in-One) */
                                        esc_html__('Full integration support is available in %s, including WooCommerce, BuddyPress, Gravity Forms and more.', 'wp-sms'),
                                        '<strong>' . esc_html__('WP SMS All-in-One', 'wp-sms') . '</strong>'
                                    )
                                ] : [
                                    'link'      => 'https://wp-sms-pro.com/pricing/?utm_source=wp-sms&utm_medium=link&utm_campaign=settings',
                                    'link_text' => esc_html__('Upgrade to unlock everything.', 'wp-sms'),
                                    'title'     => sprintf(
                                    /* translators: %s: Plugin name (WP SMS All-in-One) */
                                        esc_html__('Some settings are only available in %s, including extended field support, syncing options, and more advanced configuration.', 'wp-sms'),
                                        '<strong>' . esc_html__('WP SMS All-in-One', 'wp-sms') . '</strong>'
                                    )
                                ];

                                View::load("components/objects/notice-all-in-one", $noticeConfig);
                            endif;
                            ?>
                        </li>
                    </ul>


                    <div class="wpsms-tab-content <?php echo esc_attr($this->active_tab) . '_settings_tab' ?>">
                        <?php
                        if (strpos($this->active_tab, 'addon_') !== false) {
                            do_action("wp_sms_{$this->active_tab}_before_content_render");
                        }

                        if (strpos($this->active_tab, 'pro_') !== false) {
                            do_action("wp_sms_pro_before_content_render");
                        }
                        ?>
                        <div class="wpsms-tab-content__box">
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
            <input type="hidden" name="wpsms_active_tab" value="<?php echo esc_attr($this->active_tab); ?>">
            <?php submit_button(); ?>
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

    /**
     * Updates the gateway version inside the plugin settings array before saving.
     *
     * @param array $newValue The new settings array that will be saved into the database.
     * @param array $oldValue The previous settings array stored in the database.
     *
     * @return array The modified settings array including the updated `gateway_version` key.
     */
    public function updateGateWayVersion($newValue, $oldValue)
    {
        global $sms;

        if (is_null($sms) && function_exists('wp_sms_initial_gateway')) {
            $sms = wp_sms_initial_gateway();
        }

        $currentVer = (isset($sms->version) && $sms->version !== '') ? (string)$sms->version : '1.0';

        $newValue['gateway_version'] = $currentVer;

        return $newValue;
    }
}