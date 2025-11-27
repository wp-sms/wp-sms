<?php

namespace WP_SMS\Admin\AnonymizedUsageData;

use WP_SMS\Admin\LicenseManagement\LicenseHelper;
use WP_SMS\Admin\LicenseManagement\Plugin\PluginHandler;
use WP_SMS\Gravityforms;
use WP_SMS\Option as WPSmsOptionsManager;
use WP_SMS\Quform;
use WP_SMS\Utils\OptionUtil;
use WP_SMS\Utils\OptionUtil as Option;
use WP_SMS\Components\DBUtil as DB;

if (!defined('ABSPATH')) exit;

class AnonymizedUsageDataProvider
{
    /**
     * Retrieves the URL for the current site where the front end is accessible.
     *
     * @return string
     */
    public static function getHomeUrl()
    {
        $url = self::getCleanDomain(home_url());

        return self::hashDomain($url);
    }

    /**
     * Get the WordPress version.
     *
     * @return string
     */
    public static function getWordPressVersion()
    {
        return get_bloginfo('version');
    }

    /**
     * Get the PHP version.
     *
     * @return string|null
     */
    public static function getPhpVersion()
    {
        if (function_exists('phpversion')) {
            $versionParts = explode('.', phpversion());
            return "$versionParts[0].$versionParts[1]";
        }

        return null;
    }

    /**
     * Get the plugin version.
     *
     * @return string
     */
    public static function getPluginVersion()
    {
        return WP_SMS_VERSION;
    }

    /**
     * Get the database version.
     *
     * @return string|null
     */
    public static function getDatabaseVersion()
    {
        global $wpdb;

        if (empty($wpdb) || empty($wpdb->is_mysql)) {
            return null;
        }

        if (method_exists($wpdb, 'db_version')) {
            $version = $wpdb->db_version();
        } else {
            return null;
        }

        if (empty($version)) {
            return null;
        }

        return preg_match('/\d+\.\d+/', $version, $matches) ? $matches[0] : '';
    }

    /**
     * Get the database type.
     *
     * @return string|null
     */
    public static function getDatabaseType()
    {
        global $wpdb;

        if (empty($wpdb) || empty($wpdb->is_mysql)) {
            return null;
        }

        if (!method_exists($wpdb, 'db_server_info')) {
            return null;
        }

        $serverInfo = $wpdb->db_server_info();

        if (!$serverInfo) {
            return null;
        }

        $serverInfoLower = strtolower($serverInfo);

        if (strpos($serverInfoLower, 'mariadb') !== false) {
            return 'MariaDB';
        }

        if (strpos($serverInfoLower, 'mysql') !== false) {
            return 'MySQL';
        }

        return 'Unknown';
    }

    /**
     * Get the plugin slug.
     *
     * @return string
     */
    public static function getPluginSlug()
    {
        return basename(dirname(WP_SMS_MAIN_FILE));
    }

    /**
     * Retrieves the software information of the web server.
     *
     * @return string
     */
    public static function getServerSoftware()
    {
        if (!empty($_SERVER['SERVER_SOFTWARE'])) {
            return $_SERVER['SERVER_SOFTWARE']; // @phpcs:ignore
        }

        return 'Unknown';
    }

    /**
     * Retrieves server information.
     *
     * @return array
     */
    public static function getServerInfo()
    {
        return [
            'webserver'     => self::getServerSoftware(),
            'database_type' => self::getDatabaseType(),
        ];
    }

    /**
     * Get clean domain
     *
     * @param string $url
     *
     * @return string
     */
    public static function getCleanDomain(string $url): string
    {
        if (!preg_match('/^https?:\/\//', $url)) {
            $url = 'https://' . $url; // Default to HTTPS if no scheme
        }

        $parsedUrl = parse_url($url);
        $host      = preg_replace('/^www\./', '', $parsedUrl['host'] ?? '');
        $path      = $parsedUrl['path'] ?? '';

        return $host . $path;
    }

    /**
     * Hashes a URL using the SHA-256 algorithm and returns the first 40 characters.
     *
     * @param string $domain
     *
     * @return string
     */
    public static function hashDomain($domain)
    {
        return substr(hash('sha256', $domain), 0, 40);
    }

    /**
     * Get the current theme info, theme name and version.
     *
     * @return array
     */
    public static function getThemeInfo()
    {
        $themeData = wp_get_theme();

        return array(
            'slug' => $themeData->get_stylesheet(),
        );
    }

    /**
     * Get all plugins grouped into activated or not.
     *
     * @return array
     */
    public static function getAllPlugins()
    {
        $activePluginsKeys = get_option('active_plugins', array());

        $pluginFolders = array_map(function ($plugin) {
            return explode('/', $plugin)[0];
        }, $activePluginsKeys);

        return array(
            'activated_plugins' => $pluginFolders,
        );
    }

    /**
     * Retrieves plugin settings
     *
     * @return array
     */
    public static function getPluginSettings()
    {
        $settings = self::getSettings();

        return [
            'main'   => self::processSettings($settings['main']),
            'addons' => array_map([self::class, 'processSettings'], $settings['addons']),
        ];
    }

    /**
     * Processes raw settings by extracting relevant values.
     *
     * @param string|array $rawSettings
     *
     * @return string|array
     */
    private static function processSettings($rawSettings)
    {
        $processed = [];
        if (!is_array($rawSettings)) {
            return $rawSettings;
        }
        foreach ($rawSettings as $key => $setting) {
            if (is_array($setting) && (isset($setting['value']) || isset($setting['debug']))) {
                $processed[$key] = isset($setting['value'])
                    ? $setting['value']
                    : (isset($setting['debug']) ? $setting['debug'] : null);
            } elseif (is_array($setting)) {
                $processed[$key] = self::processSettings($setting);
            } else {
                $processed[$key] = $setting;
            }
        }

        return $processed;
    }

    /**
     * Retrieves the timezone string.
     *
     * @return string
     */
    public static function getTimezone()
    {
        $timezone = get_option('timezone_string');

        if (!empty($timezone)) {
            return $timezone;
        }

        $gmt_offset = get_option('gmt_offset');
        return 'UTC' . ($gmt_offset >= 0 ? '+' : '') . $gmt_offset;
    }

    /**
     * Retrieves the current locale.
     *
     * @return string
     */
    public static function getLocale()
    {
        return get_locale();
    }

    /**
     * Retrieves the status, type, and associated products of all licenses.
     *
     * This method fetches all licenses using the LicenseHelper and returns an array
     * containing the 'status', 'type', and 'products' for each license.
     *
     * @return array An array of license details, where each element contains:
     *          - 'status' (string): The status of the license.
     *          - 'type' (string): The type of the license.
     *          - 'products' (array): The products associated with the license.
     */
    public static function getLicensesInfo()
    {
        $rawLicenses = LicenseHelper::getLicenses('all');
        $licenses    = [];

        foreach ($rawLicenses as $k => $v) {
            $licenses[] = [
                'status'   => $v['status'],
                'type'     => $v['type'],
                'products' => $v['products'],
            ];
        }

        return $licenses;
    }

    /**
     * Retrieves the payload data
     *
     * @return array
     */
    public static function getPayload()
    {
        return [
            'plugin_database_version_legacy' => get_option('wp_sms_plugin_version'),
            'plugin_database_version'        => Option::getOptionGroup('db', 'version', '0.0.0'),
            'jobs'                           => Option::getOptionGroup('jobs'),
            'dismissed_notices'              => Option::getOptionGroup('dismissed_notices'),
        ];
    }

    public static function getSettings()
    {
        $mainSettings  = self::getMainSettings();
        $pluginHandler = new PluginHandler();

        $addons = [
            'wooPro'     => $pluginHandler->isPluginActive('wp-sms-woocommerce-pro')
                ? self::getWoocommerceProSetting() ?: []
                : 'Not Active',
            'twoWay'     => $pluginHandler->isPluginActive('wp-sms-two-way')
                ? self::getTwoWayIntegrationSetting() ?: []
                : 'Not Active',
            'fluent'     => $pluginHandler->isPluginActive('wp-sms-fluent-integrations')
                ? self::getFluentIntegrationSetting() ?: []
                : 'Not Active',
            'membership' => $pluginHandler->isPluginActive('wp-sms-membership-integrations')
                ? self::getMembershipsIntegrationSetting()
                : 'Not Active',
            'booking'    => $pluginHandler->isPluginActive('wp-sms-booking-integrations')
                ? self::getBookingIntegrationSetting()
                : 'Not Active',
        ];

        return [
            'main'   => $mainSettings,
            'addons' => $addons,
        ];
    }

    /**
     * Retrieve and compile all main WP SMS plugin settings.
     *
     * @return array
     */
    public static function getMainSettings()
    {
        $settings = [
            // Basic Information
            'version'                             => WP_SMS_VERSION,

            // Mobile Field Settings
            'addMobileField'                      => OptionUtil::get('add_mobile_field') ?: 'Not Set',
            'mobileFieldMandatoryStatus'          => OptionUtil::get('optional_mobile_field') !== 'optional' ? 'Required' : 'Optional',

            // GDPR Settings
            'gdprCompliance'                      => OptionUtil::get('gdpr_compliance') ? 'Enabled' : 'Disabled',
            'gdprCheckboxDefault'                 => ucfirst(OptionUtil::get('newsletter_form_gdpr_confirm_checkbox')) ?: 'Not Set',

            // Display Settings
            'accountCreditInMenu'                 => OptionUtil::get('account_credit_in_menu') ? 'Enabled' : 'Disabled',
            'accountCreditInSendsms'              => OptionUtil::get('account_credit_in_sendsms') ? 'Enabled' : 'Disabled',

            // SMS Gateway Settings
            'gatewayName'                         => OptionUtil::get('gateway_name') ?: 'Not configured',
            'smsDeliveryMethod'                   => OptionUtil::get('sms_delivery_method') ?: 'Not configured',
            'unicodeMessaging'                    => OptionUtil::get('send_unicode') ? 'Enabled' : 'Disabled',
            'numberFormatting'                    => OptionUtil::get('clean_numbers') ? 'Enabled' : 'Disabled',
            'restrictToLocalNumbers'              => OptionUtil::get('send_only_local_numbers') ? 'Enabled' : 'Disabled',
            'restrictToLocalNumbersCountries'     => implode(', ', (array)OptionUtil::get('only_local_numbers_countries')) ?: 'Not Set',
            'internationalMobile'                 => OptionUtil::get('international_mobile') ? 'Enabled' : 'Disabled',
            'internationalMobileOnlyCountries'    => implode(', ', (array)OptionUtil::get('international_mobile_only_countries')) ?: 'Not Set',
            'preferredLocalNumbersCountries'      => implode(', ', (array)OptionUtil::get('international_mobile_preferred_countries')) ?: 'Not Set',
            'mobileCountyCode'                    => OptionUtil::get('mobile_county_code') ?: 'Not Set',

            // Newsletter Settings
            'groupVisibilityInForm'               => OptionUtil::get('newsletter_form_groups') ? 'Enabled' : 'Disabled',
            'subscriptionConfirmation'            => OptionUtil::get('newsletter_form_verify') ? 'Enabled' : 'Disabled',
            'welcomeSmsStatus'                    => OptionUtil::get('newsletter_form_welcome') ? 'Enabled' : 'Disabled',
            'formMultipleGroupSelect'             => OptionUtil::get('newsletter_form_multiple_select') ? 'Enabled' : 'Disabled',

            // Notification Settings
            'newPostAlertsStatus'                 => OptionUtil::get('notif_publish_new_post') ? 'Enabled' : 'Disabled',
            'newPostAlertsNotificationRecipients' => ucfirst(OptionUtil::get('notif_publish_new_post_receiver')) ?: 'Not Set',
            'newPostAlertsForceSend'              => OptionUtil::get('notif_publish_new_post_force') ? 'Enabled' : 'Disabled',
            'newPostAlertsSendMms'                => OptionUtil::get('notif_publish_new_send_mms') ? 'Enabled' : 'Disabled',
            'newPostAlertsPostContentWordsLimit'  => OptionUtil::get('notif_publish_new_post_words_count') ?: 10,
            'postAuthorNotificationStatus'        => OptionUtil::get('notif_publish_new_post_author') ? 'Enabled' : 'Disabled',

            // Message Button Settings
            'messageButtonStatus'                 => OptionUtil::get('chatbox_message_button') ? 'Enabled' : 'Disabled',
            'buttonPosition'                      => ucwords(str_replace('_', ' ', OptionUtil::get('chatbox_button_position'))) ?: 'Not Set',
            'disableWpSmsLogo'                    => OptionUtil::get('chatbox_disable_logo') ? 'Enabled' : 'Disabled',
            'resourceLinksStatus'                 => OptionUtil::get('chatbox_links_enabled') ? 'Enabled' : 'Disabled',

            // Reporting Settings
            'reportWpsmsStatistics'               => OptionUtil::get('report_wpsms_statistics') ? 'Enabled' : 'Disabled',
            'notifyErrorsToAdminEmail'            => OptionUtil::get('notify_errors_to_admin_email') ? 'Enabled' : 'Disabled',

            // URL Shortener
            'shortUrlStatus'                      => OptionUtil::get('short_url_status') ? 'Enabled' : 'Disabled',

            // Webhooks
            'outgoingSmsWebhook'                  => !empty(OptionUtil::get('new_sms_webhook')) ? 'Configured' : 'Not configured',
            'subscriberRegistrationWebhook'       => !empty(OptionUtil::get('new_subscriber_webhook')) ? 'Configured' : 'Not configured',
            'incomingSmsWebhook'                  => !empty(OptionUtil::get('new_incoming_sms_webhook')) ? 'Configured' : 'Not configured',

            // reCAPTCHA
            'recaptchaStatus'                     => OptionUtil::get('g_recaptcha_status') ? 'Enabled' : 'Disabled',

            // Pro: Login & Two-Factor Authentication (2FA)
            'loginWithSms'                        => WPSmsOptionsManager::getOption('login_sms', true) ? 'Enabled' : 'Disabled',
            'autoRegisterOnLogin'                 => WPSmsOptionsManager::getOption('register_sms', true) ? 'Enabled' : 'Disabled',
            'twoFactorStatus'                     => WPSmsOptionsManager::getOption('mobile_verify', true) ? 'Enabled' : 'Disabled',
            'twoFactorPolicy'                     => ucfirst(str_replace('_', ' ', WPSmsOptionsManager::getOption('mobile_verify_method', true))),

            // Integrations
            'contactForm7Integration'             => OptionUtil::get('cf7_metabox') ? 'Enabled' : 'Disabled',
        ];

        if (class_exists('RGFormsModel')) {
            $settings['gfIntegrationActive'] = 'Enabled';
        }

        if (class_exists('Quform_Repository')) {
            $settings['qfIntegrationActive'] = 'Yes';
        }

        if (class_exists('Easy_Digital_Downloads')) {
            $settings['eddIntegrationActive'] = 'Yes';
            $settings['eddMobileField']       = WPSmsOptionsManager::getOption('edd_mobile_field', true) ? 'Enabled' : 'Disabled';
            $settings['eddNotifyOrder']       = WPSmsOptionsManager::getOption('edd_notify_order_enable', true) ? 'Enabled' : 'Disabled';
            $settings['eddNotifyCustomer']    = WPSmsOptionsManager::getOption('edd_notify_customer_enable', true) ? 'Enabled' : 'Disabled';
        }

        if (class_exists('WP_Job_Manager')) {
            $settings['jobManagerIntegrationActive'] = 'Yes';
            $settings['jobMobileFieldEnabled']       = WPSmsOptionsManager::getOption('job_mobile_field', true) ? 'Enabled' : 'Disabled';
            $settings['jobDisplayMobileEnabled']     = WPSmsOptionsManager::getOption('job_display_mobile_number', true) ? 'Enabled' : 'Disabled';
            $settings['jobNewJobNotification']       = WPSmsOptionsManager::getOption('job_notify_status', true) ? 'Enabled' : 'Disabled';
            $settings['jobNotificationReceiverType'] = WPSmsOptionsManager::getOption('job_notify_receiver', true);
            $settings['jobEmployerNotification']     = WPSmsOptionsManager::getOption('job_notify_employer_status', true) ? 'Enabled' : 'Disabled';
        }

        if (class_exists('Awesome_Support')) {
            $settings['asIntegrationActive']        = 'Yes';
            $settings['asNewTicketNotification']    = WPSmsOptionsManager::getOption('as_notify_open_ticket_status', true) ? 'Enabled' : 'Disabled';
            $settings['asAdminReplyNotification']   = WPSmsOptionsManager::getOption('as_notify_admin_reply_ticket_status', true) ? 'Enabled' : 'Disabled';
            $settings['asUserReplyNotification']    = WPSmsOptionsManager::getOption('as_notify_user_reply_ticket_status', true) ? 'Enabled' : 'Disabled';
            $settings['asStatusUpdateNotification'] = WPSmsOptionsManager::getOption('as_notify_update_ticket_status', true) ? 'Enabled' : 'Disabled';
            $settings['asTicketCloseNotification']  = WPSmsOptionsManager::getOption('as_notify_close_ticket_status', true) ? 'Enabled' : 'Disabled';
        }

        if (function_exists('um_user')) {
            $settings['umIntegrationActive']    = 'Yes';
            $settings['umApprovalNotification'] = WPSmsOptionsManager::getOption('um_send_sms_after_approval', true) ? 'Enabled' : 'Disabled';
        }

        if (function_exists('is_plugin_active') && is_plugin_active('formidable/formidable.php')) {
            $settings['formidablePluginStatus']  = 'Active';
            $settings['formidableMetaboxStatus'] = WPSmsOptionsManager::getOption('formidable_metabox', true) ? 'Enabled' : 'Disabled';
        }

        if (class_exists('Forminator')) {
            $settings['forminatorPluginStatus'] = 'Active';
        }

        if (function_exists('buddypress')) {
            $settings['bpWelcomeSmsStatus']           = WPSmsOptionsManager::getOption('bp_welcome_notification_enable', true) ? 'Enabled' : 'Disabled';
            $settings['bpMentionNotification']        = WPSmsOptionsManager::getOption('bp_mention_enable', true) ? 'Enabled' : 'Disabled';
            $settings['bpMentionMessageBody']         = !empty(WPSmsOptionsManager::getOption('bp_mention_message', true)) ? 'Customized' : 'Empty';
            $settings['bpPrivateMessageNotification'] = WPSmsOptionsManager::getOption('bp_private_message_enable', true) ? 'Enabled' : 'Disabled';
            $settings['bpActivityReplyNotification']  = WPSmsOptionsManager::getOption('bp_comments_activity_enable', true) ? 'Enabled' : 'Disabled';
            $settings['bpActivityReplyMessageBody']   = !empty(WPSmsOptionsManager::getOption('bp_comments_activity_message', true)) ? 'Customized' : 'Empty';
            $settings['bpCommentReplyNotification']   = WPSmsOptionsManager::getOption('bp_comments_reply_enable', true) ? 'Enabled' : 'Disabled';
        }

        if (class_exists('WooCommerce')) {
            $settings['wcMetaBoxEnable']                       = WPSmsOptionsManager::getOption('wc_meta_box_enable', true) ? 'Enabled' : 'Disabled';
            $settings['wcNotifyProductEnable']                 = WPSmsOptionsManager::getOption('wc_notify_product_enable', true) ? 'Enabled' : 'Disabled';
            $settings['wcNotifyOrderEnable']                   = WPSmsOptionsManager::getOption('wc_notify_order_enable', true) ? 'Enabled' : 'Disabled';
            $settings['wcNotifyCustomerEnable']                = WPSmsOptionsManager::getOption('wc_notify_customer_enable', true) ? 'Enabled' : 'Disabled';
            $settings['wcNotifyStockEnable']                   = WPSmsOptionsManager::getOption('wc_notify_stock_enable', true) ? 'Enabled' : 'Disabled';
            $settings['wcCheckoutConfirmationCheckboxEnabled'] = WPSmsOptionsManager::getOption('wc_checkout_confirmation_checkbox_enabled', true) ? 'Enabled' : 'Disabled';
            $settings['wcNotifyStatusEnable']                  = WPSmsOptionsManager::getOption('wc_notify_status_enable', true) ? 'Enabled' : 'Disabled';
            $settings['wcNotifyByStatusEnable']                = WPSmsOptionsManager::getOption('wc_notify_by_status_enable', true) ? 'Enabled' : 'Disabled';
            $settings['wcNotifyByStatusContentCount']          = is_array(WPSmsOptionsManager::getOption('wc_notify_by_status_content', true)) ? count(WPSmsOptionsManager::getOption('wc_notify_by_status_content', true)) : 0;
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
        // === Booking Calendar ===
        $bookingCalendarOptions = array(
            'booking_calendar_notif_admin_new_booking'          => 'Booking Calendar: Admin New Booking Notification',
            'booking_calendar_notif_customer_new_booking'       => 'Booking Calendar: Customer New Booking Notification',
            'booking_calendar_notif_customer_booking_approved'  => 'Booking Calendar: Booking Approved Notification',
            'booking_calendar_notif_customer_booking_cancelled' => 'Booking Calendar: Booking Cancelled Notification',
        );

        $bookingCalendarSettings = array();

        foreach ($bookingCalendarOptions as $key => $label) {
            $raw   = OptionUtil::get($key);
            $value = (is_bool($raw) || $raw === '0' || $raw === '1') ? ($raw ? 'Enabled' : 'Disabled') : (is_string($raw) ? $raw : 'Not Set');

            $alias = preg_replace_callback('/_([a-z])/', function ($matches) {
                return strtoupper($matches[1]);
            }, $key);

            $bookingCalendarSettings[$alias] = $value;
        }

        // === BookingPress ===
        $bookingPressOptions = array(
            'bookingpress_notif_admin_approved_appointment'     => 'BookingPress: Admin Approved Appointment',
            'bookingpress_notif_customer_approved_appointment'  => 'BookingPress: Customer Approved Appointment',
            'bookingpress_notif_admin_pending_appointment'      => 'BookingPress: Admin Pending Appointment',
            'bookingpress_notif_customer_pending_appointment'   => 'BookingPress: Customer Pending Appointment',
            'bookingpress_notif_admin_rejected_appointment'     => 'BookingPress: Admin Rejected Appointment',
            'bookingpress_notif_customer_rejected_appointment'  => 'BookingPress: Customer Rejected Appointment',
            'bookingpress_notif_admin_cancelled_appointment'    => 'BookingPress: Admin Cancelled Appointment',
            'bookingpress_notif_customer_cancelled_appointment' => 'BookingPress: Customer Cancelled Appointment',
        );

        $bookingPressSettings = array();

        foreach ($bookingPressOptions as $key => $label) {
            $raw   = OptionUtil::get($key);
            $value = (is_bool($raw) || $raw === '0' || $raw === '1') ? ($raw ? 'Enabled' : 'Disabled') : (is_string($raw) ? $raw : 'Not Set');

            $alias = preg_replace_callback('/_([a-z])/', function ($matches) {
                return strtoupper($matches[1]);
            }, $key);

            $bookingPressSettings[$alias] = $value;
        }

        // === Woo Appointments ===
        $wooAppointmentsOptions = array(
            'woo_appointments_notif_admin_new_appointment'          => 'Woo Appointments: Admin New Appointment',
            'woo_appointments_notif_admin_cancelled_appointment'    => 'Woo Appointments: Admin Cancelled Appointment',
            'woo_appointments_notif_customer_cancelled_appointment' => 'Woo Appointments: Customer Cancelled Appointment',
            'woo_appointments_notif_admin_rescheduled_appointment'  => 'Woo Appointments: Admin Rescheduled Appointment',
            'woo_appointments_notif_customer_confirmed_appointment' => 'Woo Appointments: Customer Confirmed Appointment',
        );

        $wooAppointmentsSettings = array();

        foreach ($wooAppointmentsOptions as $key => $label) {
            $raw   = OptionUtil::get($key);
            $value = (is_bool($raw) || $raw === '0' || $raw === '1') ? ($raw ? 'Enabled' : 'Disabled') : (is_string($raw) ? $raw : 'Not Set');

            $alias = preg_replace_callback('/_([a-z])/', function ($matches) {
                return strtoupper($matches[1]);
            }, $key);

            $wooAppointmentsSettings[$alias] = $value;
        }

        // === Woo Bookings ===
        $wooBookingsOptions = array(
            'woo_bookings_notif_admin_new_booking'          => 'Woo Bookings: Admin New Booking',
            'woo_bookings_notif_admin_cancelled_booking'    => 'Woo Bookings: Admin Cancelled Booking',
            'woo_bookings_notif_customer_cancelled_booking' => 'Woo Bookings: Customer Cancelled Booking',
            'woo_bookings_notif_customer_confirmed_booking' => 'Woo Bookings: Customer Confirmed Booking',
        );

        $wooBookingsSettings = array();

        foreach ($wooBookingsOptions as $key => $label) {
            $raw   = OptionUtil::get($key);
            $value = (is_bool($raw) || $raw === '0' || $raw === '1') ? ($raw ? 'Enabled' : 'Disabled') : (is_string($raw) ? $raw : 'Not Set');

            $alias = preg_replace_callback('/_([a-z])/', function ($matches) {
                return strtoupper($matches[1]);
            }, $key);

            $wooBookingsSettings[$alias] = $value;
        }

        return array_merge($bookingCalendarSettings, $bookingPressSettings, $wooAppointmentsSettings, $wooBookingsSettings);
    }

    /**
     * Retrieves anonymized settings for FluentCRM integration in structured format.
     *
     * @return array
     */
    public static function getFluentIntegrationSetting()
    {
        $fluentCrmOptions = [
            'fluent_crm_notif_contact_subscribed'   => 'FluentCRM: Contact Subscribed Notification',
            'fluent_crm_notif_contact_unsubscribed' => 'FluentCRM: Contact Unsubscribed Notification',
            'fluent_crm_notif_contact_pending'      => 'FluentCRM: Contact Pending Notification',
        ];

        $fluentCrmSettings = [];
        foreach ($fluentCrmOptions as $key => $label) {
            $raw = \WP_SMS\Utils\OptionUtil::get($key);

            $value = (is_bool($raw) || $raw === '0' || $raw === '1')
                ? ($raw ? 'Enabled' : 'Disabled')
                : (is_string($raw) ? $raw : 'Not Set');

            $alias = preg_replace_callback('/_([a-z])/', function ($matches) {
                return strtoupper($matches[1]);
            }, $key);

            $fluentCrmSettings[$alias] = $value;
        }

        $fluentSupportOptions = [
            'fluent_support_notif_ticket_created'    => 'Fluent Support: Ticket Created',
            'fluent_support_notif_customer_response' => 'Fluent Support: Customer Response',
            'fluent_support_notif_agent_assigned'    => 'Fluent Support: Agent Assigned',
            'fluent_support_notif_ticket_closed'     => 'Fluent Support: Ticket Closed',
        ];

        $fluentSupportSettings = [];
        foreach ($fluentSupportOptions as $key => $label) {
            $raw = \WP_SMS\Utils\OptionUtil::get($key);

            $value = (is_bool($raw) || $raw === '0' || $raw === '1')
                ? ($raw ? 'Enabled' : 'Disabled')
                : (is_string($raw) ? $raw : 'Not Set');

            $alias = preg_replace_callback('/_([a-z])/', function ($matches) {
                return strtoupper($matches[1]);
            }, $key);

            $fluentSupportSettings[$alias] = $value;
        }

        return array_merge($fluentCrmSettings, $fluentSupportSettings);
    }

    /**
     * Retrieves settings for Memberships integration in structured format.
     *
     * @return array
     */
    public static function getMembershipsIntegrationSetting()
    {
        // === Paid Memberships Pro ===
        $paidMembershipOptions = [
            'pmpro_notif_user_registered'      => 'Paid Memberships Pro: User Registered Notification',
            'pmpro_notif_membership_confirmed' => 'Paid Memberships Pro: Membership Confirmed Notification',
            'pmpro_notif_membership_cancelled' => 'Paid Memberships Pro: Membership Cancelled Notification',
            'pmpro_notif_membership_expired'   => 'Paid Memberships Pro: Membership Expired Notification',
        ];

        $paidSettings = [];
        foreach ($paidMembershipOptions as $key => $label) {
            $raw = \WP_SMS\Utils\OptionUtil::get($key);

            $value = (is_bool($raw) || $raw === '0' || $raw === '1')
                ? ($raw ? 'Enabled' : 'Disabled')
                : (is_string($raw) ? $raw : 'Not Set');

            $alias = preg_replace_callback('/_([a-z])/', fn($m) => strtoupper($m[1]), $key);

            $paidSettings[$alias] = $value;
        }

        // === Simple Membership ===
        $simpleMembershipOptions = [
            'sm_notif_admin_user_registered'    => 'Simple Membership: Admin Notified on User Registration',
            'sm_notif_membership_level_updated' => 'Simple Membership: Membership Level Updated',
            'sm_notif_membership_expired'       => 'Simple Membership: Membership Expired',
            'sm_notif_membership_cancelled'     => 'Simple Membership: Membership Cancelled',
            'sm_notif_admin_payment_recieved'   => 'Simple Membership: Payment Received (Admin)',
        ];

        $simpleSettings = [];
        foreach ($simpleMembershipOptions as $key => $label) {
            $raw = \WP_SMS\Utils\OptionUtil::get($key);

            $value = (is_bool($raw) || $raw === '0' || $raw === '1')
                ? ($raw ? 'Enabled' : 'Disabled')
                : (is_string($raw) ? $raw : 'Not Set');

            $alias = preg_replace_callback('/_([a-z])/', fn($m) => strtoupper($m[1]), $key);

            $simpleSettings[$alias] = $value;
        }

        return array_merge($paidSettings, $simpleSettings);
    }

    /**
     * Retrieves settings for Two-Way integration in structured format.
     *
     * @return array
     */
    public static function getTwoWayIntegrationSetting()
    {
        $integration = array();

        $options = array(
            'notif_new_inbox_message' => 'Two-Way: Forward Incoming SMS to Admin',
            'email_new_inbox_message' => 'Two-Way: Forward Incoming SMS to Email',
        );

        foreach ($options as $key => $label) {
            $raw = \WP_SMS\Utils\OptionUtil::get($key);

            $value = (is_bool($raw) || $raw === '0' || $raw === '1') ? ($raw ? 'Enabled' : 'Disabled') : (is_string($raw) ? $raw : 'Not Set');

            $alias = preg_replace_callback('/_([a-z])/', function ($matches) {
                return strtoupper($matches[1]);
            }, $key);

            $integration[$alias] = $value;
        }

        return $integration;
    }

    /**
     * Get WooCommerce Pro integration settings for WP SMS.
     *
     * @return array
     */
    public static function getWoocommerceProSetting()
    {
        $integration = [];

        $fields = [
            // My Account
            'login_with_sms_status'                               => 'My Account: Login via SMS (Login Page)',
            'login_with_sms_forgot_status'                        => 'My Account: Login via SMS (Forgot Password Page)',
            'register_user_via_sms_status'                        => 'My Account: Automatic Registration via SMS',
            'reset_password_status'                               => 'My Account: Enable SMS Password Reset',

            // Cart
            'cart_abandonment_recovery_status'                    => 'Cart: Abandonment Recovery Status',
            'cart_create_coupon'                                  => 'Cart: Auto-Create Coupon for Recovery',
            'cart_overwrite_number_during_checkout'               => 'Cart: Overwrite Phone Number on Checkout',
            'cart_abandonment_threshold'                          => 'Cart: Cart abandonment threshold',
            'cart_abandonment_send_sms_time_interval'             => 'Cart: SMS Send Delay Configured',

            // Checkout
            'checkout_confirmation_checkbox_enabled'              => 'Checkout: Confirmation Checkbox Enabled',
            'checkout_mobile_verification_enabled'                => 'Checkout: Mobile Verification Enabled',
            'checkout_mobile_verification_skip_logged_in_enabled' => 'Checkout: Skip Verification for Logged-In Users',

            // Notifications – Admin
            'new_order_admin_enabled'                             => 'Notification: New Order (Admin)',
            'new_order_admin_by_status_enabled'                   => 'Notification: Order by Status (Admin)',
            'product_stock_admin_enabled'                         => 'Notification: Low Product Stock (Admin)',

            // Notifications – Customer
            'new_order_customer_enabled'                          => 'Notification: New Order (Customer)',
            'new_product_customer_enabled'                        => 'Notification: New Product (Customer)',
            'order_cancelled_customer_enabled'                    => 'Notification: Order Cancelled (Customer)',
            'order_failed_customer_enabled'                       => 'Notification: Order Failed (Customer)',
            'order_onhold_customer_enabled'                       => 'Notification: Order On Hold (Customer)',
            'order_processing_customer_enabled'                   => 'Notification: Order Processing (Customer)',
            'order_completed_customer_enabled'                    => 'Notification: Order Completed (Customer)',
            'order_refunded_customer_enabled'                     => 'Notification: Order Refunded (Customer)',
        ];

        foreach ($fields as $key => $label) {
            $raw = \WPSmsWooPro\Core\Helper::getOption($key);

            if ($key === 'cart_overwrite_number_during_checkout') {
                $value = $raw === 'skip'
                    ? 'Do not update'
                    : 'Update phone number';
            } elseif ($key === 'cart_abandonment_send_sms_time_interval' || $key === 'cart_abandonment_threshold') {
                if (is_array($raw)) {
                    $parts = [];

                    if (!empty($raw['days']) && $raw['days'] !== '0') {
                        $parts[] = $raw['days'] . ' day' . ($raw['days'] > 1 ? 's' : '');
                    }
                    if (!empty($raw['hours']) && $raw['hours'] !== '0') {
                        $parts[] = $raw['hours'] . ' hour' . ($raw['hours'] > 1 ? 's' : '');
                    }
                    if (!empty($raw['minutes']) && $raw['minutes'] !== '0') {
                        $parts[] = $raw['minutes'] . ' minute' . ($raw['minutes'] > 1 ? 's' : '');
                    }

                    $value = $parts ? implode(', ', $parts) : 'Not Set';
                } else {
                    $value = 'Not Set';
                }
            } elseif (in_array($raw, [true, '1', 1, 'yes'], true)) {
                $value = 'Enabled';
            } elseif (in_array($raw, [false, '0', 0, 'no'], true)) {
                $value = 'Disabled';
            } elseif ($raw === null || $raw === '') {
                $value = 'Not Set';
            } else {
                $value = (string)$raw;
            }

            $alias = preg_replace_callback('/_([a-z])/', function ($matches) {
                return strtoupper($matches[1]);
            }, $key);

            $integration[$alias] = $value;
        }

        return $integration;
    }
}