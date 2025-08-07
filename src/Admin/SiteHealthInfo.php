<?php

namespace WP_SMS\Admin;

use WP_SMS\Admin\LicenseManagement\LicenseHelper;
use WP_SMS\Admin\LicenseManagement\Plugin\PluginHandler;
use WP_SMS\Gateway;
use WP_SMS\Option as WPSmsOptionsManager;
use WP_SMS\Utils\OptionUtil;
use WP_SMS\Option;

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
        global $sms;
        $settings = array();

        $pluginHandler = new PluginHandler();

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
            'value' => get_option('wp_sms_db_version', 'Not Set'),
        );

        $settings['mobile_field_source'] = array(
            'label' => __('Mobile Number Field Source', 'wp-sms'),
            'value' => $toCamelCase($raw('add_mobile_field', 'Not Set')),
        );

        $settings['mobile_field_mandatory'] = array(
            'label' => __('Mobile Field Mandatory Status', 'wp-sms'),
            'value' => $raw('optional_mobile_field') === '0' ? 'Required' : 'Optional',
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
            'label' => __('SMS Gateway Name', 'wp-sms'),
            'value' => $toCamelCase($raw('gateway_name', 'Not Configured')),
        );

        $settings['gateway_status'] = array(
            'label' => __('SMS Gateway Status', 'wp-sms'),
            'value' => Gateway::status(true) ? 'Active' : 'Inactive',
        );

        $settings['incoming_message'] = array(
            'label' => __('SMS Gateway Incoming Message', 'wp-sms'),
            'value' => $yesNo($sms->supportIncoming),
        );

        $settings['send_bulk_sms'] = array(
            'label' => __('SMS Gateway Send Bulk SMS', 'wp-sms'),
            'value' => $yesNo($sms->bulk_send),
        );

        $settings['send_mms'] = array(
            'label' => __('SMS Gateway Send MMS', 'wp-sms'),
            'value' => $yesNo($sms->supportMedia),
        );

        $deliveryOptions = array(
            'api_direct_send' => esc_html__('Send SMS Instantly: Activates immediate dispatch of messages via API upon request.', 'wp-sms'),
            'api_async_send'  => esc_html__('Scheduled SMS Delivery: Configures API to send messages at predetermined times.', 'wp-sms'),
            'api_queued_send' => esc_html__('Batch SMS Queue: Lines up messages for grouped sending, enhancing efficiency for bulk dispatch.', 'wp-sms'),
        );

        $deliveryKey   = $raw('sms_delivery_method', 'not_set');
        $deliveryLabel = $deliveryOptions[$deliveryKey] ?? __('Not Set', 'wp-sms');

        $settings['delivery_method'] = array(
            'label' => __('SMS Gateway Delivery Method', 'wp-sms'),
            'value' => $deliveryLabel,
        );

        $settings['unicode_messaging'] = array(
            'label' => __('SMS Gateway Unicode Messaging', 'wp-sms'),
            'value' => $yesNo($raw('send_unicode')),
        );

        $settings['number_formatting'] = array(
            'label' => __('SMS Gateway Number Formatting', 'wp-sms'),
            'value' => $yesNo($raw('clean_numbers')),
        );

        $restrictLocal    = $raw('send_only_local_numbers');
        $allowedCountries = (array)$raw('only_local_numbers_countries');

        $restrictText = $yesNo($restrictLocal);

        if ($restrictLocal && !empty($allowedCountries) && $allowedCountries[0]) {
            $restrictText .= ' — ' . implode(', ', $allowedCountries);
        }

        $settings['restrict_to_local'] = array(
            'label' => __('SMS Gateway Restrict to Local Numbers', 'wp-sms'),
            'value' => $restrictText,
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
            'value' => $yesNo(WPSmsOptionsManager::getOption('login_sms', \true)),
        );

        $settings['two_factor'] = array(
            'label' => __('Two-Factor Authentication with SMS', 'wp-sms'),
            'value' => $yesNo(WPSmsOptionsManager::getOption('mobile_verify', \true)),
        );

        $settings['auto_register_on_login'] = array(
            'label' => __('Create User on SMS Login', 'wp-sms'),
            'value' => $yesNo(WPSmsOptionsManager::getOption('register_sms', \true)),
        );

        $settings['cf7_metabox'] = array(
            'label' => __('Contact Form 7 Metabox', 'wp-sms'),
            'value' => $yesNo($raw('cf7_metabox')),
        );

        return array_merge($settings, $this->getIntegrationSettings());

    }

    protected function getIntegrationSettings()
    {
        $pluginHandler = new PluginHandler();
        $yesNo         = function ($val) {
            return in_array($val, [true, '1', 1, 'yes'], true) ? __('Enabled', 'wp-sms') : __('Disabled', 'wp-sms');
        };
        $settings      = [];

        // Gravity Forms
        if (class_exists('RGFormsModel') && LicenseHelper::isPluginLicensedAndActive()) {
            $settings['gravityforms_integration'] = [
                'label' => __('Gravity Forms Integration', 'wp-sms'),
                'value' => __('Enabled', 'wp-sms')
            ];
        }

        // Quform
        if (class_exists('Quform_Repository') && LicenseHelper::isPluginLicensedAndActive()) {
            $settings['quform_integration'] = [
                'label' => __('Quform Integration', 'wp-sms'),
                'value' => __('Enabled', 'wp-sms')
            ];
        }

        // EDD
        if (class_exists('Easy_Digital_Downloads') && LicenseHelper::isPluginLicensedAndActive()) {
            $settings['edd_integration'] = [
                'label' => __('EDD Integration', 'wp-sms'),
                'value' => __('Enabled', 'wp-sms')
            ];

            $settings['edd_mobile_field'] = [
                'label' => __('EDD Mobile Field', 'wp-sms'),
                'value' => $yesNo(WPSmsOptionsManager::getOption('edd_mobile_field', true))
            ];

            $settings['edd_notify_order'] = [
                'label' => __('EDD New Order Notifications', 'wp-sms'),
                'value' => $yesNo(WPSmsOptionsManager::getOption('edd_notify_order_enable', true))
            ];

            $settings['edd_notify_customer'] = [
                'label' => __('EDD Customer Notifications', 'wp-sms'),
                'value' => $yesNo(WPSmsOptionsManager::getOption('edd_notify_customer_enable', true))
            ];
        }

        // Job Manager
        if (class_exists('WP_Job_Manager') && LicenseHelper::isPluginLicensedAndActive()) {
            $settings['job_manager_integration'] = [
                'label' => __('WP Job Manager Integration', 'wp-sms'),
                'value' => __('Enabled', 'wp-sms')
            ];

            $settings['job_mobile_field'] = [
                'label' => __('Job Mobile Field', 'wp-sms'),
                'value' => $yesNo(WPSmsOptionsManager::getOption('job_mobile_field', true))
            ];

            $settings['job_display_mobile'] = [
                'label' => __('Display Mobile Number', 'wp-sms'),
                'value' => $yesNo(WPSmsOptionsManager::getOption('job_display_mobile_number', true))
            ];

            $settings['job_new_job_notification'] = [
                'label' => __('New Job Notification', 'wp-sms'),
                'value' => $yesNo(WPSmsOptionsManager::getOption('job_notify_status', true))
            ];

            $settings['job_notification_receiver'] = [
                'label' => __('Job Notification Receiver', 'wp-sms'),
                'value' => WPSmsOptionsManager::getOption('job_notify_receiver', true)
            ];

            $settings['job_employer_notification'] = [
                'label' => __('Employer Notification', 'wp-sms'),
                'value' => $yesNo(WPSmsOptionsManager::getOption('job_notify_employer_status', true))
            ];
        }

        // Awesome Support
        if (class_exists('Awesome_Support') && LicenseHelper::isPluginLicensedAndActive()) {
            $settings['awesome_support_integration'] = [
                'label' => __('Awesome Support Integration', 'wp-sms'),
                'value' => __('Enabled', 'wp-sms')
            ];

            $settings['as_new_ticket_notification'] = [
                'label' => __('New Ticket Notification', 'wp-sms'),
                'value' => $yesNo(WPSmsOptionsManager::getOption('as_notify_open_ticket_status', true))
            ];

            $settings['as_admin_reply_notification'] = [
                'label' => __('Admin Reply Notification', 'wp-sms'),
                'value' => $yesNo(WPSmsOptionsManager::getOption('as_notify_admin_reply_ticket_status', true))
            ];

            $settings['as_user_reply_notification'] = [
                'label' => __('User Reply Notification', 'wp-sms'),
                'value' => $yesNo(WPSmsOptionsManager::getOption('as_notify_user_reply_ticket_status', true))
            ];

            $settings['as_status_update_notification'] = [
                'label' => __('Status Update Notification', 'wp-sms'),
                'value' => $yesNo(WPSmsOptionsManager::getOption('as_notify_update_ticket_status', true))
            ];

            $settings['as_ticket_close_notification'] = [
                'label' => __('Ticket Close Notification', 'wp-sms'),
                'value' => $yesNo(WPSmsOptionsManager::getOption('as_notify_close_ticket_status', true))
            ];
        }

        // Ultimate Member
        if (function_exists('um_user') && LicenseHelper::isPluginLicensedAndActive()) {
            $settings['ultimate_member_integration'] = [
                'label' => __('Ultimate Member Integration', 'wp-sms'),
                'value' => __('Enabled', 'wp-sms')
            ];

            $settings['um_approval_notification'] = [
                'label' => __('Ultimate Member User Approval Notification', 'wp-sms'),
                'value' => $yesNo(WPSmsOptionsManager::getOption('um_send_sms_after_approval', true))
            ];
        }

        // Formidable Forms
        if (function_exists('is_plugin_active') && is_plugin_active('formidable/formidable.php')) {
            $settings['formidable_integration'] = [
                'label' => __('Formidable Integration', 'wp-sms'),
                'value' => __('Enabled', 'wp-sms')
            ];

            $settings['formidable_metabox'] = [
                'label' => __('Formidable Metabox', 'wp-sms'),
                'value' => $yesNo(WPSmsOptionsManager::getOption('formidable_metabox'))
            ];
        }

        // Forminator
        if (class_exists('Forminator') && LicenseHelper::isPluginLicensedAndActive()) {
            $settings['forminator_integration'] = [
                'label' => __('Forminator Integration', 'wp-sms'),
                'value' => __('Enabled', 'wp-sms')
            ];
        }

        // BuddyPress
        if (function_exists('buddypress') && LicenseHelper::isPluginLicensedAndActive()) {
            $settings['buddypress_integration'] = [
                'label' => __('BuddyPress Integration', 'wp-sms'),
                'value' => __('Enabled', 'wp-sms')
            ];

            $settings['bp_welcome_sms'] = [
                'label' => __('BuddyPress Welcome SMS', 'wp-sms'),
                'value' => $yesNo(WPSmsOptionsManager::getOption('bp_welcome_notification_enable', true))
            ];

            $settings['bp_mention_notification'] = [
                'label' => __('BuddyPress Mention Alerts', 'wp-sms'),
                'value' => $yesNo(WPSmsOptionsManager::getOption('bp_mention_enable', true))
            ];

            $settings['bp_private_message_notification'] = [
                'label' => __('BuddyPress Private Messages', 'wp-sms'),
                'value' => $yesNo(WPSmsOptionsManager::getOption('bp_private_message_enable', true))
            ];

            $settings['bp_activity_reply_notification'] = [
                'label' => __('BuddyPress Activity Replies', 'wp-sms'),
                'value' => $yesNo(WPSmsOptionsManager::getOption('bp_comments_activity_enable', true))
            ];

            $settings['bp_comment_reply_notification'] = [
                'label' => __('BuddyPress Comment Replies', 'wp-sms'),
                'value' => $yesNo(WPSmsOptionsManager::getOption('bp_comments_reply_enable', true))
            ];
        }

        // WooCommerce
        if (class_exists('WooCommerce') && LicenseHelper::isPluginLicensedAndActive()) {
            $settings['woocommerce_integration'] = [
                'label' => __('WooCommerce Integration', 'wp-sms'),
                'value' => __('Enabled', 'wp-sms')
            ];

            $settings['wc_meta_box'] = [
                'label' => __('WooCommerce Order Meta Box', 'wp-sms'),
                'value' => $yesNo(WPSmsOptionsManager::getOption('wc_meta_box_enable', true))
            ];

            $settings['wc_notify_product'] = [
                'label' => __('WooCommerce New Product Notification', 'wp-sms'),
                'value' => $yesNo(WPSmsOptionsManager::getOption('wc_notify_product_enable', true))
            ];

            $settings['wc_notify_order'] = [
                'label' => __('WooCommerce New Order Notification', 'wp-sms'),
                'value' => $yesNo(WPSmsOptionsManager::getOption('wc_notify_order_enable', true))
            ];

            $settings['wc_notify_customer'] = [
                'label' => __('WooCommerce Customer Order Notification', 'wp-sms'),
                'value' => $yesNo(WPSmsOptionsManager::getOption('wc_notify_customer_enable', true))
            ];

            $settings['wc_notify_stock'] = [
                'label' => __('WooCommerce Low Stock Notification', 'wp-sms'),
                'value' => $yesNo(WPSmsOptionsManager::getOption('wc_notify_stock_enable', true))
            ];

            $settings['wc_checkout_confirmation'] = [
                'label' => __('WooCommerce Checkout Confirmation Checkbox', 'wp-sms'),
                'value' => $yesNo(WPSmsOptionsManager::getOption('wc_checkout_confirmation_checkbox_enabled', true))
            ];

            $settings['wc_notify_status'] = [
                'label' => __('WooCommerce Order Status Notification', 'wp-sms'),
                'value' => $yesNo(WPSmsOptionsManager::getOption('wc_notify_status_enable', true))
            ];

            $settings['wc_notify_by_status'] = [
                'label' => __('WooCommerce Specific Order Status Notification', 'wp-sms'),
                'value' => $yesNo(WPSmsOptionsManager::getOption('wc_notify_by_status_enable', true))
            ];

            $statusCount                        = is_array(WPSmsOptionsManager::getOption('wc_notify_by_status_content', true)) ?
                count(WPSmsOptionsManager::getOption('wc_notify_by_status_content', true)) : 0;
            $settings['wc_notify_status_count'] = [
                'label' => __('WooCommerce Configured Status Notifications', 'wp-sms'),
                'value' => $statusCount
            ];
        }

        // WooCommerce Pro
        if ($pluginHandler->isPluginActive('wp-sms-woocommerce-pro')) {
            $settings = array_merge($settings, $this->getWooProSettings());
        }


        if ($pluginHandler->isPluginActive('wp-sms-two-way')) {
            $twoWay   = self::getTwoWayIntegrationSetting()['two_way'] ?? [];
            $settings = array_merge($settings, $twoWay);
        }

        if ($pluginHandler->isPluginActive('wp-sms-fluent-integrations')) {
            $fluent   = self::getFluentIntegrationSetting() ?? [];
            $settings = array_merge($settings, $fluent);
        }

        if ($pluginHandler->isPluginActive('wp-sms-membership-integrations')) {
            $membership = self::getMembershipsIntegrationSetting();
            $settings   = array_merge($settings, $membership);
        }

        if ($pluginHandler->isPluginActive('wp-sms-booking-integrations')) {
            $booking  = self::getBookingIntegrationSetting();
            $settings = array_merge($settings, $booking);
        }

        return $settings;
    }

    private function getWooProSettings()
    {
        $woo = function ($key, $default = null) {
            return \WPSmsWooPro\Core\Helper::getOption($key, $default);
        };

        $wooProFields = array(
            'cart_abandonment_recovery_status'                    => __('WooPro: Cart Abandonment Recovery', 'wp-sms'),
            'cart_abandonment_threshold'                          => __('WooPro: Cart abandonment threshold', 'wp-sms'),
            'cart_overwrite_number_during_checkout'               => __('WooPro: Cart abandonment Overwrite mobile number', 'wp-sms'),
            'cart_create_coupon'                                  => __('WooPro: Cart abandonment Create coupon', 'wp-sms'),
            'cart_abandonment_send_sms_time_interval'             => __('WooPro: Cart abandonment Send sms after', 'wp-sms'),
            'login_with_sms_status'                               => __('WooPro: Show Button in Login Page', 'wp-sms'),
            'login_with_sms_forgot_status'                        => __('WooPro: Show Button in Forgot Password Page', 'wp-sms'),
            'reset_password_status'                               => __('WooPro: Enable SMS Password Reset', 'wp-sms'),
            'checkout_confirmation_checkbox_enabled'              => __('WooPro: Confirmation Checkbox', 'wp-sms'),
            'checkout_mobile_verification_enabled'                => __('WooPro: Enable Mobile Verification', 'wp-sms'),
            'register_user_via_sms_status'                        => __('WooPro: Automatic Registration via SMS', 'wp-sms'),
            'checkout_mobile_verification_skip_logged_in_enabled' => __('WooPro: Skip Verification for Logged-In Users', 'wp-sms'),
            'checkout_mobile_verification_countries_whitelist'    => __('WooPro: Required Countries for Mobile Verification', 'wp-sms'),
        );

        foreach ($wooProFields as $key => $label) {
            $raw   = $woo($key);
            $value = '';
            if ($key === 'register_user_via_sms_status' || $key === 'checkout_mobile_verification_skip_logged_in_enabled') {
                $value = in_array($raw, array(true, '1', 1, 'yes'), true)
                    ? __('Enabled', 'wp-sms')
                    : __('Disabled', 'wp-sms');
            } elseif ($key === 'checkout_mobile_verification_countries_whitelist') {
                $value = is_array($raw) && !empty($raw)
                    ? implode(', ', $raw)
                    : __('Not Set', 'wp-sms');
            } else if ($key === 'cart_overwrite_number_during_checkout') {
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

    /**
     * Retrieves anonymized settings for supported booking integrations in structured format.
     *
     * @return array
     */
    public static function getBookingIntegrationSetting()
    {
        $settings = [];

        $options = array(
            // === Booking Calendar ===
            'booking_calendar_notif_admin_new_booking'              => __('Booking Calendar: Admin New Booking Notification', 'wp-sms'),
            'booking_calendar_notif_customer_new_booking'           => __('Booking Calendar: Customer New Booking Notification', 'wp-sms'),
            'booking_calendar_notif_customer_booking_approved'      => __('Booking Calendar: Booking Approved Notification', 'wp-sms'),
            'booking_calendar_notif_customer_booking_cancelled'     => __('Booking Calendar: Booking Cancelled Notification', 'wp-sms'),

            // === BookingPress ===
            'bookingpress_notif_admin_approved_appointment'         => __('BookingPress: Admin Approved Appointment', 'wp-sms'),
            'bookingpress_notif_customer_approved_appointment'      => __('BookingPress: Customer Approved Appointment', 'wp-sms'),
            'bookingpress_notif_admin_pending_appointment'          => __('BookingPress: Admin Pending Appointment', 'wp-sms'),
            'bookingpress_notif_customer_pending_appointment'       => __('BookingPress: Customer Pending Appointment', 'wp-sms'),
            'bookingpress_notif_admin_rejected_appointment'         => __('BookingPress: Admin Rejected Appointment', 'wp-sms'),
            'bookingpress_notif_customer_rejected_appointment'      => __('BookingPress: Customer Rejected Appointment', 'wp-sms'),
            'bookingpress_notif_admin_cancelled_appointment'        => __('BookingPress: Admin Cancelled Appointment', 'wp-sms'),
            'bookingpress_notif_customer_cancelled_appointment'     => __('BookingPress: Customer Cancelled Appointment', 'wp-sms'),

            // === Woo Appointments ===
            'woo_appointments_notif_admin_new_appointment'          => __('Woo Appointments: Admin New Appointment', 'wp-sms'),
            'woo_appointments_notif_admin_cancelled_appointment'    => __('Woo Appointments: Admin Cancelled Appointment', 'wp-sms'),
            'woo_appointments_notif_customer_cancelled_appointment' => __('Woo Appointments: Customer Cancelled Appointment', 'wp-sms'),
            'woo_appointments_notif_admin_rescheduled_appointment'  => __('Woo Appointments: Admin Rescheduled Appointment', 'wp-sms'),
            'woo_appointments_notif_customer_confirmed_appointment' => __('Woo Appointments: Customer Confirmed Appointment', 'wp-sms'),

            // === Woo Bookings ===
            'woo_bookings_notif_admin_new_booking'                  => __('Woo Bookings: Admin New Booking', 'wp-sms'),
            'woo_bookings_notif_admin_cancelled_booking'            => __('Woo Bookings: Admin Cancelled Booking', 'wp-sms'),
            'woo_bookings_notif_customer_cancelled_booking'         => __('Woo Bookings: Customer Cancelled Booking', 'wp-sms'),
            'woo_bookings_notif_customer_confirmed_booking'         => __('Woo Bookings: Customer Confirmed Booking', 'wp-sms'),
        );

        foreach ($options as $key => $label) {
            $raw   = OptionUtil::get($key);
            $value = (is_bool($raw) || $raw === '0' || $raw === '1')
                ? ($raw ? __('Enabled', 'wp-sms') : __('Disabled', 'wp-sms'))
                : (is_string($raw) ? $raw : __('Not Set', 'wp-sms'));

            $settings[] = [
                'label' => esc_html($label),
                'value' => $value,
            ];
        }

        return $settings;
    }

    /**
     * Retrieves anonymized settings for FluentCRM integration in structured format.
     *
     * @return array
     */
    public static function getFluentIntegrationSetting()
    {
        $settings = [];

        $options = [
            // FluentCRM
            'fluent_crm_notif_contact_subscribed'    => __('FluentCRM: Contact Subscribed Notification', 'wp-sms'),
            'fluent_crm_notif_contact_unsubscribed'  => __('FluentCRM: Contact Unsubscribed Notification', 'wp-sms'),
            'fluent_crm_notif_contact_pending'       => __('FluentCRM: Contact Pending Notification', 'wp-sms'),

            // Fluent Support
            'fluent_support_notif_ticket_created'    => __('Fluent Support: Ticket Created', 'wp-sms-fluent-integrations'),
            'fluent_support_notif_customer_response' => __('Fluent Support: Customer Response', 'wp-sms-fluent-integrations'),
            'fluent_support_notif_agent_assigned'    => __('Fluent Support: Agent Assigned', 'wp-sms-fluent-integrations'),
            'fluent_support_notif_ticket_closed'     => __('Fluent Support: Ticket Closed', 'wp-sms-fluent-integrations'),
        ];

        foreach ($options as $key => $label) {
            $raw   = \WP_SMS\Utils\OptionUtil::get($key);
            $value = (is_bool($raw) || $raw === '0' || $raw === '1')
                ? ($raw ? __('Enabled', 'wp-sms') : __('Disabled', 'wp-sms'))
                : (is_string($raw) ? $raw : __('Not Set', 'wp-sms'));

            $settings[] = [
                'label' => esc_html($label),
                'value' => $value,
            ];
        }

        return $settings;
    }

    /**
     * Retrieves settings for Memberships integration in structured format.
     *
     * @return array
     */
    public static function getMembershipsIntegrationSetting()
    {
        $settings = [];

        $options = [
            // Paid Memberships Pro
            'pmpro_notif_user_registered'       => __('Paid Memberships Pro: User Registered Notification', 'wp-sms'),
            'pmpro_notif_membership_confirmed'  => __('Paid Memberships Pro: Membership Confirmed Notification', 'wp-sms'),
            'pmpro_notif_membership_cancelled'  => __('Paid Memberships Pro: Membership Cancelled Notification', 'wp-sms'),
            'pmpro_notif_membership_expired'    => __('Paid Memberships Pro: Membership Expired Notification', 'wp-sms'),

            // Simple Membership
            'sm_notif_admin_user_registered'    => __('Simple Membership: Admin Notified on User Registration', 'wp-sms'),
            'sm_notif_membership_level_updated' => __('Simple Membership: Membership Level Updated', 'wp-sms'),
            'sm_notif_membership_expired'       => __('Simple Membership: Membership Expired', 'wp-sms'),
            'sm_notif_membership_cancelled'     => __('Simple Membership: Membership Cancelled', 'wp-sms'),
            'sm_notif_admin_payment_recieved'   => __('Simple Membership: Payment Received (Admin)', 'wp-sms'),
        ];

        foreach ($options as $key => $label) {
            $raw   = \WP_SMS\Utils\OptionUtil::get($key);
            $value = (is_bool($raw) || $raw === '0' || $raw === '1')
                ? ($raw ? __('Enabled', 'wp-sms') : __('Disabled', 'wp-sms'))
                : (is_string($raw) ? $raw : __('Not Set', 'wp-sms'));

            $settings[] = [
                'label' => esc_html($label),
                'value' => $value,
            ];
        }

        return $settings;
    }

    /**
     * Retrieves settings for Two-Way integration in structured format.
     *
     * @return array
     */
    public static function getTwoWayIntegrationSetting()
    {
        $settings = [];

        $options = [
            'notif_new_inbox_message' => __('Two-Way: Forward Incoming SMS to Admin', 'wp-sms'),
            'email_new_inbox_message' => __('Two-Way: Forward Incoming SMS to Email', 'wp-sms'),
        ];

        foreach ($options as $key => $label) {
            $raw   = \WP_SMS\Utils\OptionUtil::get($key);
            $value = (is_bool($raw) || $raw === '0' || $raw === '1')
                ? ($raw ? __('Enabled', 'wp-sms') : __('Disabled', 'wp-sms'))
                : (is_string($raw) ? $raw : __('Not Set', 'wp-sms'));

            $settings[] = [
                'label' => esc_html($label),
                'value' => $value,
            ];
        }

        return $settings;
    }
}
