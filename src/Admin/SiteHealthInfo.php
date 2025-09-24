<?php

namespace WP_SMS\Admin;

use WP_SMS\Admin\LicenseManagement\LicenseHelper;
use WP_SMS\Admin\LicenseManagement\Plugin\PluginHandler;
use WP_SMS\Gateway;
use WP_SMS\Option as WPSmsOptionsManager;
use WP_SMS\Utils\OptionUtil;

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
            return $value ? 'Enabled' : 'Disabled';
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
            'label' => 'Plugin Version',
            'value' => defined('WP_SMS_VERSION') ? WP_SMS_VERSION : 'N/A',
        );

        $settings['db_version'] = array(
            'label' => 'Database Version',
            'value' => get_option('wp_sms_db_version', 'Not Set'),
        );

        $settings['mobile_field_source'] = array(
            'label' => 'Mobile Number Field Source',
            'value' => $toCamelCase($raw('add_mobile_field', 'Not Set')),
        );

        $settings['mobile_field_mandatory'] = array(
            'label' => 'Mobile Field Mandatory Status',
            'value' => $raw('optional_mobile_field') === '0' ? 'Required' : 'Optional',
        );

        $settings['only_countries'] = array(
            'label' => 'Only Countries',
            'value' => implode(', ', (array)$raw('international_mobile_only_countries')) ?: 'Not Set',
        );

        $settings['preferred_countries'] = array(
            'label' => 'Preferred Countries',
            'value' => implode(', ', (array)$raw('international_mobile_preferred_countries')) ?: 'Not Set',
        );

        $settings['gateway_name'] = array(
            'label' => 'SMS Gateway Name',
            'value' => $toCamelCase($raw('gateway_name', 'Not Configured')),
        );

        $settings['gateway_status'] = array(
            'label' => 'SMS Gateway Status',
            'value' => Gateway::status(true) ? 'Active' : 'Inactive',
        );

        $settings['incoming_message'] = array(
            'label' => 'SMS Gateway Incoming Message',
            'value' => $yesNo($sms->supportIncoming),
        );

        $settings['send_bulk_sms'] = array(
            'label' => 'SMS Gateway Send Bulk SMS',
            'value' => $yesNo($sms->bulk_send),
        );

        $settings['send_mms'] = array(
            'label' => 'SMS Gateway Send MMS',
            'value' => $yesNo($sms->supportMedia),
        );

        $deliveryOptions = array(
            'api_direct_send' => 'Send SMS Instantly: Activates immediate dispatch of messages via API upon request.',
            'api_async_send'  => 'Scheduled SMS Delivery: Configures API to send messages at predetermined times.',
            'api_queued_send' => 'Batch SMS Queue: Lines up messages for grouped sending, enhancing efficiency for bulk dispatch.',
        );

        $deliveryKey   = $raw('sms_delivery_method', 'not_set');
        $deliveryLabel = $deliveryOptions[$deliveryKey] ?? 'Not Set';

        $settings['delivery_method'] = array(
            'label' => 'SMS Gateway Delivery Method',
            'value' => $deliveryLabel,
        );

        $settings['unicode_messaging'] = array(
            'label' => 'SMS Gateway Unicode Messaging',
            'value' => $yesNo($raw('send_unicode')),
        );

        $settings['number_formatting'] = array(
            'label' => 'SMS Gateway Number Formatting',
            'value' => $yesNo($raw('clean_numbers')),
        );

        $restrictLocal    = $raw('send_only_local_numbers');
        $allowedCountries = (array)$raw('only_local_numbers_countries');

        $restrictText = $yesNo($restrictLocal);

        if ($restrictLocal && !empty($allowedCountries) && $allowedCountries[0]) {
            $restrictText .= ' — ' . implode(', ', $allowedCountries);
        }

        $settings['restrict_to_local'] = array(
            'label' => 'SMS Gateway Restrict to Local Numbers',
            'value' => $restrictText,
        );


        $settings['group_visibility'] = array(
            'label' => 'SMS Newsletter Group Visibility in Form',
            'value' => $yesNo($raw('newsletter_form_groups')),
        );

        $settings['group_selection'] = array(
            'label' => 'SMS Newsletter Group Selection',
            'value' => $yesNo($raw('newsletter_form_multiple_select')),
        );

        $settings['subscription_confirmation'] = array(
            'label' => 'SMS Newsletter Subscription Confirmation',
            'value' => $yesNo($raw('newsletter_form_verify')),
        );

        $settings['message_button'] = array(
            'label' => 'Message Button Status',
            'value' => $yesNo($raw('chatbox_message_button')),
        );

        $settings['performance_reports'] = array(
            'label' => 'SMS Performance Reports',
            'value' => $yesNo($raw('report_wpsms_statistics')),
        );

        $settings['shorten_urls'] = array(
            'label' => 'Shorten URLs',
            'value' => $yesNo($raw('short_url_status')),
        );

        $settings['recaptcha'] = array(
            'label' => 'Google reCAPTCHA Integration',
            'value' => $yesNo($raw('g_recaptcha_status')),
        );

        $settings['login_with_sms'] = array(
            'label' => 'Login With SMS',
            'value' => $yesNo(WPSmsOptionsManager::getOption('login_sms', true)),
        );

        $settings['two_factor'] = array(
            'label' => 'Two-Factor Authentication with SMS',
            'value' => $yesNo(WPSmsOptionsManager::getOption('mobile_verify', true)),
        );

        $settings['auto_register_on_login'] = array(
            'label' => 'Create User on SMS Login',
            'value' => $yesNo(WPSmsOptionsManager::getOption('register_sms', true)),
        );

        $settings['cf7_metabox'] = array(
            'label' => 'Contact Form 7 Metabox',
            'value' => $yesNo($raw('cf7_metabox')),
        );

        return array_merge($settings, $this->getIntegrationSettings());

    }

    protected function getIntegrationSettings()
    {
        $pluginHandler = new PluginHandler();
        $yesNo         = function ($val) {
            return in_array($val, [true, '1', 1, 'yes'], true) ? 'Enabled' : 'Disabled';
        };
        $settings      = [];

        // Gravity Forms
        if (class_exists('RGFormsModel') && LicenseHelper::isPluginLicensedAndActive()) {
            $settings['gravityforms_integration'] = [
                'label' => 'Gravity Forms Integration',
                'value' => 'Enabled',
            ];
        }

        // Quform
        if (class_exists('Quform_Repository') && LicenseHelper::isPluginLicensedAndActive()) {
            $settings['quform_integration'] = [
                'label' => 'Quform Integration',
                'value' => 'Enabled',
            ];
        }

        // EDD
        if (class_exists('Easy_Digital_Downloads') && LicenseHelper::isPluginLicensedAndActive()) {
            $settings['edd_integration'] = [
                'label' => 'EDD Integration',
                'value' => 'Enabled',
            ];

            $settings['edd_mobile_field'] = [
                'label' => 'EDD Mobile Field',
                'value' => $yesNo(WPSmsOptionsManager::getOption('edd_mobile_field', true))
            ];

            $settings['edd_notify_order'] = [
                'label' => 'EDD New Order Notifications',
                'value' => $yesNo(WPSmsOptionsManager::getOption('edd_notify_order_enable', true))
            ];

            $settings['edd_notify_customer'] = [
                'label' => 'EDD Customer Notifications',
                'value' => $yesNo(WPSmsOptionsManager::getOption('edd_notify_customer_enable', true))
            ];
        }

        // Job Manager
        if (class_exists('WP_Job_Manager') && LicenseHelper::isPluginLicensedAndActive()) {
            $settings['job_manager_integration'] = [
                'label' => 'WP Job Manager Integration',
                'value' => 'Enabled',
            ];

            $settings['job_mobile_field'] = [
                'label' => 'Job Mobile Field',
                'value' => $yesNo(WPSmsOptionsManager::getOption('job_mobile_field', true))
            ];

            $settings['job_display_mobile'] = [
                'label' => 'Display Mobile Number',
                'value' => $yesNo(WPSmsOptionsManager::getOption('job_display_mobile_number', true))
            ];

            $settings['job_new_job_notification'] = [
                'label' => 'New Job Notification',
                'value' => $yesNo(WPSmsOptionsManager::getOption('job_notify_status', true))
            ];

            $settings['job_notification_receiver'] = [
                'label' => 'Job Notification Receiver',
                'value' => WPSmsOptionsManager::getOption('job_notify_receiver', true)
            ];

            $settings['job_employer_notification'] = [
                'label' => 'Employer Notification',
                'value' => $yesNo(WPSmsOptionsManager::getOption('job_notify_employer_status', true))
            ];
        }

        // Awesome Support
        if (class_exists('Awesome_Support') && LicenseHelper::isPluginLicensedAndActive()) {
            $settings['awesome_support_integration'] = [
                'label' => 'Awesome Support Integration',
                'value' => 'Enabled',
            ];

            $settings['as_new_ticket_notification'] = [
                'label' => 'New Ticket Notification',
                'value' => $yesNo(WPSmsOptionsManager::getOption('as_notify_open_ticket_status', true))
            ];

            $settings['as_admin_reply_notification'] = [
                'label' => 'Admin Reply Notification',
                'value' => $yesNo(WPSmsOptionsManager::getOption('as_notify_admin_reply_ticket_status', true))
            ];

            $settings['as_user_reply_notification'] = [
                'label' => 'User Reply Notification',
                'value' => $yesNo(WPSmsOptionsManager::getOption('as_notify_user_reply_ticket_status', true))
            ];

            $settings['as_status_update_notification'] = [
                'label' => 'Status Update Notification',
                'value' => $yesNo(WPSmsOptionsManager::getOption('as_notify_update_ticket_status', true))
            ];

            $settings['as_ticket_close_notification'] = [
                'label' => 'Ticket Close Notification',
                'value' => $yesNo(WPSmsOptionsManager::getOption('as_notify_close_ticket_status', true))
            ];
        }

        // Ultimate Member
        if (function_exists('um_user') && LicenseHelper::isPluginLicensedAndActive()) {
            $settings['ultimate_member_integration'] = [
                'label' => 'Ultimate Member Integration',
                'value' => 'Enabled',
            ];

            $settings['um_approval_notification'] = [
                'label' => 'Ultimate Member User Approval Notification',
                'value' => $yesNo(WPSmsOptionsManager::getOption('um_send_sms_after_approval', true))
            ];
        }

        // Formidable Forms
        if (function_exists('is_plugin_active') && is_plugin_active('formidable/formidable.php')) {
            $settings['formidable_integration'] = [
                'label' => 'Formidable Integration',
                'value' => 'Enabled',
            ];

            $settings['formidable_metabox'] = [
                'label' => 'Formidable Metabox',
                'value' => $yesNo(WPSmsOptionsManager::getOption('formidable_metabox'))
            ];
        }

        // Forminator
        if (class_exists('Forminator') && LicenseHelper::isPluginLicensedAndActive()) {
            $settings['forminator_integration'] = [
                'label' => 'Forminator Integration',
                'value' => 'Enabled',
            ];
        }

        // BuddyPress
        if (function_exists('buddypress') && LicenseHelper::isPluginLicensedAndActive()) {
            $settings['buddypress_integration'] = [
                'label' => 'BuddyPress Integration',
                'value' => 'Enabled',
            ];

            $settings['bp_welcome_sms'] = [
                'label' => 'BuddyPress Welcome SMS',
                'value' => $yesNo(WPSmsOptionsManager::getOption('bp_welcome_notification_enable', true))
            ];

            $settings['bp_mention_notification'] = [
                'label' => 'BuddyPress Mention Alerts',
                'value' => $yesNo(WPSmsOptionsManager::getOption('bp_mention_enable', true))
            ];

            $settings['bp_private_message_notification'] = [
                'label' => 'BuddyPress Private Messages',
                'value' => $yesNo(WPSmsOptionsManager::getOption('bp_private_message_enable', true))
            ];

            $settings['bp_activity_reply_notification'] = [
                'label' => 'BuddyPress Activity Replies',
                'value' => $yesNo(WPSmsOptionsManager::getOption('bp_comments_activity_enable', true))
            ];

            $settings['bp_comment_reply_notification'] = [
                'label' => 'BuddyPress Comment Replies',
                'value' => $yesNo(WPSmsOptionsManager::getOption('bp_comments_reply_enable', true))
            ];
        }

        // WooCommerce
        if (class_exists('WooCommerce') && LicenseHelper::isPluginLicensedAndActive()) {
            $settings['woocommerce_integration'] = [
                'label' => 'WooCommerce Integration',
                'value' => 'Enabled',
            ];

            $settings['wc_meta_box'] = [
                'label' => 'WooCommerce Order Meta Box',
                'value' => $yesNo(WPSmsOptionsManager::getOption('wc_meta_box_enable', true))
            ];

            $settings['wc_notify_product'] = [
                'label' => 'WooCommerce New Product Notification',
                'value' => $yesNo(WPSmsOptionsManager::getOption('wc_notify_product_enable', true))
            ];

            $settings['wc_notify_order'] = [
                'label' => 'WooCommerce New Order Notification',
                'value' => $yesNo(WPSmsOptionsManager::getOption('wc_notify_order_enable', true))
            ];

            $settings['wc_notify_customer'] = [
                'label' => 'WooCommerce Customer Order Notification',
                'value' => $yesNo(WPSmsOptionsManager::getOption('wc_notify_customer_enable', true))
            ];

            $settings['wc_notify_stock'] = [
                'label' => 'WooCommerce Low Stock Notification',
                'value' => $yesNo(WPSmsOptionsManager::getOption('wc_notify_stock_enable', true))
            ];

            $settings['wc_checkout_confirmation'] = [
                'label' => 'WooCommerce Checkout Confirmation Checkbox',
                'value' => $yesNo(WPSmsOptionsManager::getOption('wc_checkout_confirmation_checkbox_enabled', true))
            ];

            $settings['wc_notify_status'] = [
                'label' => 'WooCommerce Order Status Notification',
                'value' => $yesNo(WPSmsOptionsManager::getOption('wc_notify_status_enable', true))
            ];

            $settings['wc_notify_by_status'] = [
                'label' => 'WooCommerce Specific Order Status Notification',
                'value' => $yesNo(WPSmsOptionsManager::getOption('wc_notify_by_status_enable', true))
            ];

            $statusCount                        = is_array(WPSmsOptionsManager::getOption('wc_notify_by_status_content', true)) ?
                count(WPSmsOptionsManager::getOption('wc_notify_by_status_content', true)) : 0;
            $settings['wc_notify_status_count'] = [
                'label' => 'WooCommerce Configured Status Notifications',
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
            'cart_abandonment_recovery_status'                    => 'WooPro: Cart Abandonment Recovery',
            'cart_abandonment_threshold'                          => 'WooPro: Cart abandonment threshold',
            'cart_overwrite_number_during_checkout'               => 'WooPro: Cart abandonment Overwrite mobile number',
            'cart_create_coupon'                                  => 'WooPro: Cart abandonment Create coupon',
            'cart_abandonment_send_sms_time_interval'             => 'WooPro: Cart abandonment Send sms after',
            'login_with_sms_status'                               => 'WooPro: Show Button in Login Page',
            'login_with_sms_forgot_status'                        => 'WooPro: Show Button in Forgot Password Page',
            'reset_password_status'                               => 'WooPro: Enable SMS Password Reset',
            'checkout_confirmation_checkbox_enabled'              => 'WooPro: Confirmation Checkbox',
            'checkout_mobile_verification_enabled'                => 'WooPro: Enable Mobile Verification',
            'register_user_via_sms_status'                        => 'WooPro: Automatic Registration via SMS',
            'checkout_mobile_verification_skip_logged_in_enabled' => 'WooPro: Skip Verification for Logged-In Users',
            'checkout_mobile_verification_countries_whitelist'    => 'WooPro: Required Countries for Mobile Verification',
        );

        foreach ($wooProFields as $key => $label) {
            $raw   = $woo($key);
            $value = '';
            if ($key === 'register_user_via_sms_status' || $key === 'checkout_mobile_verification_skip_logged_in_enabled') {
                $value = in_array($raw, array(true, '1', 1, 'yes'), true)
                    ? 'Enabled'
                    : 'Disabled';
            } elseif ($key === 'checkout_mobile_verification_countries_whitelist') {
                $value = is_array($raw) && !empty($raw)
                    ? implode(', ', $raw)
                    : 'Not Set';
            } else if ($key === 'cart_overwrite_number_during_checkout') {
                $value = ($raw === 'skip')
                    ? 'Do not update'
                    : 'Update phone number';
            } elseif (in_array($key, array('cart_abandonment_threshold', 'cart_abandonment_send_sms_time_interval'), true)) {
                if (is_array($raw)) {
                    $parts = array();
                    if (!empty($raw['days']) && $raw['days'] !== '0') {
                        $d = (int) $raw['days'];
                        $parts[] = $d . ' ' . ($d === 1 ? 'day' : 'days');
                    }
                    if (!empty($raw['hours']) && $raw['hours'] !== '0') {
                        $h = (int) $raw['hours'];
                        $parts[] = $h . ' ' . ($h === 1 ? 'hour' : 'hours');
                    }
                    if (!empty($raw['minutes']) && $raw['minutes'] !== '0') {
                        $m = (int) $raw['minutes'];
                        $parts[] = $m . ' ' . ($m === 1 ? 'minute' : 'minutes');
                    }
                    $value = $parts ? implode(', ', $parts) : 'Not Set';
                } else {
                    $value = 'Not Set';
                }
            } elseif (in_array($raw, array(true, '1', 1, 'yes'), true)) {
                $value = 'Enabled';
            } elseif (in_array($raw, array(false, '0', 0, 'no'), true)) {
                $value = 'Disabled';
            } elseif ($raw === null || $raw === '') {
                $value = 'Not Set';
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
            'booking_calendar_notif_admin_new_booking'              => 'Booking Calendar: Admin New Booking Notification',
            'booking_calendar_notif_customer_new_booking'           => 'Booking Calendar: Customer New Booking Notification',
            'booking_calendar_notif_customer_booking_approved'      => 'Booking Calendar: Booking Approved Notification',
            'booking_calendar_notif_customer_booking_cancelled'     => 'Booking Calendar: Booking Cancelled Notification',

            // === BookingPress ===
            'bookingpress_notif_admin_approved_appointment'         => 'BookingPress: Admin Approved Appointment',
            'bookingpress_notif_customer_approved_appointment'      => 'BookingPress: Customer Approved Appointment',
            'bookingpress_notif_admin_pending_appointment'          => 'BookingPress: Admin Pending Appointment',
            'bookingpress_notif_customer_pending_appointment'       => 'BookingPress: Customer Pending Appointment',
            'bookingpress_notif_admin_rejected_appointment'         => 'BookingPress: Admin Rejected Appointment',
            'bookingpress_notif_customer_rejected_appointment'      => 'BookingPress: Customer Rejected Appointment',
            'bookingpress_notif_admin_cancelled_appointment'        => 'BookingPress: Admin Cancelled Appointment',
            'bookingpress_notif_customer_cancelled_appointment'     => 'BookingPress: Customer Cancelled Appointment',

            // === Woo Appointments ===
            'woo_appointments_notif_admin_new_appointment'          => 'Woo Appointments: Admin New Appointment',
            'woo_appointments_notif_admin_cancelled_appointment'    => 'Woo Appointments: Admin Cancelled Appointment',
            'woo_appointments_notif_customer_cancelled_appointment' => 'Woo Appointments: Customer Cancelled Appointment',
            'woo_appointments_notif_admin_rescheduled_appointment'  => 'Woo Appointments: Admin Rescheduled Appointment',
            'woo_appointments_notif_customer_confirmed_appointment' => 'Woo Appointments: Customer Confirmed Appointment',

            // === Woo Bookings ===
            'woo_bookings_notif_admin_new_booking'                  => 'Woo Bookings: Admin New Booking',
            'woo_bookings_notif_admin_cancelled_booking'            => 'Woo Bookings: Admin Cancelled Booking',
            'woo_bookings_notif_customer_cancelled_booking'         => 'Woo Bookings: Customer Cancelled Booking',
            'woo_bookings_notif_customer_confirmed_booking'         => 'Woo Bookings: Customer Confirmed Booking',
        );

        foreach ($options as $key => $label) {
            $raw   = OptionUtil::get($key);
            $value = (is_bool($raw) || $raw === '0' || $raw === '1')
                ? ($raw ? 'Enabled' : 'Disabled')
                : (is_string($raw) ? $raw : 'Not Set');

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
            'fluent_crm_notif_contact_subscribed'    => 'FluentCRM: Contact Subscribed Notification',
            'fluent_crm_notif_contact_unsubscribed'  => 'FluentCRM: Contact Unsubscribed Notification',
            'fluent_crm_notif_contact_pending'       => 'FluentCRM: Contact Pending Notification',

            // Fluent Support
            'fluent_support_notif_ticket_created'    => 'Fluent Support: Ticket Created',
            'fluent_support_notif_customer_response' => 'Fluent Support: Customer Response',
            'fluent_support_notif_agent_assigned'    => 'Fluent Support: Agent Assigned',
            'fluent_support_notif_ticket_closed'     => 'Fluent Support: Ticket Closed',
        ];

        foreach ($options as $key => $label) {
            $raw   = \WP_SMS\Utils\OptionUtil::get($key);
            $value = (is_bool($raw) || $raw === '0' || $raw === '1')
                ? ($raw ? 'Enabled' : 'Disabled')
                : (is_string($raw) ? $raw : 'Not Set');

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
            'pmpro_notif_user_registered'       => 'Paid Memberships Pro: User Registered Notification',
            'pmpro_notif_membership_confirmed'  => 'Paid Memberships Pro: Membership Confirmed Notification',
            'pmpro_notif_membership_cancelled'  => 'Paid Memberships Pro: Membership Cancelled Notification',
            'pmpro_notif_membership_expired'    => 'Paid Memberships Pro: Membership Expired Notification',

            // Simple Membership
            'sm_notif_admin_user_registered'    => 'Simple Membership: Admin Notified on User Registration',
            'sm_notif_membership_level_updated' => 'Simple Membership: Membership Level Updated',
            'sm_notif_membership_expired'       => 'Simple Membership: Membership Expired',
            'sm_notif_membership_cancelled'     => 'Simple Membership: Membership Cancelled',
            'sm_notif_admin_payment_recieved'   => 'Simple Membership: Payment Received (Admin)',
        ];

        foreach ($options as $key => $label) {
            $raw   = \WP_SMS\Utils\OptionUtil::get($key);
            $value = (is_bool($raw) || $raw === '0' || $raw === '1')
                ? ($raw ? 'Enabled' : 'Disabled')
                : (is_string($raw) ? $raw : 'Not Set');

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
            'notif_new_inbox_message' => 'Two-Way: Forward Incoming SMS to Admin',
            'email_new_inbox_message' => 'Two-Way: Forward Incoming SMS to Email',
        ];

        foreach ($options as $key => $label) {
            $raw   = \WP_SMS\Utils\OptionUtil::get($key);
            $value = (is_bool($raw) || $raw === '0' || $raw === '1')
                ? ($raw ? 'Enabled' : 'Disabled')
                : (is_string($raw) ? $raw : 'Not Set');

            $settings[] = [
                'label' => esc_html($label),
                'value' => $value,
            ];
        }

        return $settings;
    }
}
