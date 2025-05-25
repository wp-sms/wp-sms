<?php

namespace WP_SMS\Admin\AnonymizedUsageData;

use WP_SMS\Admin\LicenseManagement\LicenseHelper;
use WP_SMS\Utils\OptionUtil;
use WP_SMS\Utils\OptionUtil as Option;
use WP_SMS\Components\DBUtil as DB;

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

        if (empty($wpdb->is_mysql) || empty($wpdb->use_mysqli) || empty($wpdb->dbh)) {
            return null;
        }

        $serverInfo = mysqli_get_server_info($wpdb->dbh);
        if (!$serverInfo) {
            return null;
        }

        return preg_match('/\d+\.\d+/', $serverInfo, $matches) ? $matches[0] : '';
    }

    /**
     * Get the database type.
     *
     * @return string|null
     */
    public static function getDatabaseType()
    {
        global $wpdb;

        if (empty($wpdb->is_mysql) || empty($wpdb->use_mysqli)) {
            return null;
        }

        $serverInfo = mysqli_get_server_info($wpdb->dbh);

        if (!$serverInfo) {
            return null;
        }

        return str_contains($serverInfo, 'MariaDB') ? 'MariaDB' : (str_contains($serverInfo, 'MySQL') ? 'MySQL' : 'Unknown');
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
        $pluginSettings = self::processSettings(self::getSettings());

        return [
            'main' => $pluginSettings,
        ];
    }

    /**
     * Processes raw settings by extracting relevant values.
     *
     * @param array $rawSettings
     *
     * @return array
     */
    private static function processSettings(array $rawSettings): array
    {
        $processedSettings = [];

        foreach ($rawSettings as $key => $setting) {
            $processedSettings[$key] = $setting['debug'] ?? $setting['value'] ?? null;
        }

        return $processedSettings;
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
        $settings = [
            // Basic Information
            'version'                               => [
                'label' => esc_html__('Version', 'wp-sms'),
                'value' => WP_SMS_VERSION,
            ],

            // Mobile Field Settings
            'adminMobileNumber'                     => [
                'label' => esc_html__('Admin Mobile Number', 'wp-sms'),
                'value' => OptionUtil::get('admin_mobile_number'),
                'debug' => OptionUtil::get('admin_mobile_number'),
            ],
            'addMobileField'                        => [
                'label' => esc_html__('Add Mobile Field', 'wp-sms'),
                'value' => OptionUtil::get('add_mobile_field'),
                'debug' => OptionUtil::get('add_mobile_field'),
            ],
            'mobileFieldMandatoryStatus'            => [
                'label' => esc_html__('Mobile Field Mandatory Status', 'wp-sms'),
                'value' => OptionUtil::get('optional_mobile_field') === '0' ? 'Required' : 'Optional',
                'debug' => OptionUtil::get('optional_mobile_field'),
            ],

            // GDPR Settings
            'gdprCompliance'                        => [
                'label' => esc_html__('GDPR Compliance Enhancements', 'wp-sms'),
                'value' => OptionUtil::get('gdpr_compliance') ? 'Enabled' : 'Disabled',
                'debug' => OptionUtil::get('gdpr_compliance'),
            ],
            'gdprCheckboxDefault'                   => [
                'label' => esc_html__('GDPR Checkbox Default', 'wp-sms'),
                'value' => ucfirst(OptionUtil::get('newsletter_form_gdpr_confirm_checkbox')),
                'debug' => OptionUtil::get('newsletter_form_gdpr_confirm_checkbox'),
            ],

            // Display Settings
            'accountCreditInMenu'                   => [
                'label' => esc_html__('Admin Menu Display', 'wp-sms'),
                'value' => OptionUtil::get('account_credit_in_menu') ? 'Enabled' : 'Disabled',
                'debug' => OptionUtil::get('account_credit_in_menu'),
            ],
            'accountCreditInSendsms'                => [
                'label' => esc_html__('SMS Page Display', 'wp-sms'),
                'value' => OptionUtil::get('account_credit_in_sendsms') ? 'Enabled' : 'Disabled',
                'debug' => OptionUtil::get('account_credit_in_sendsms'),
            ],
            'disableDefaultFormStyling'             => [
                'label' => esc_html__('Disable Default Form Styling', 'wp-sms'),
                'value' => OptionUtil::get('disable_style_in_front') ? 'Enabled' : 'Disabled',
                'debug' => OptionUtil::get('disable_style_in_front'),
            ],

            // SMS Gateway Settings
            'gatewayName'                           => [
                'label' => esc_html__('Gateway Name', 'wp-sms'),
                'value' => OptionUtil::get('gateway_name'),
                'debug' => OptionUtil::get('gateway_name'),
            ],
            'smsDeliveryMethod'                     => [
                'label' => esc_html__('Delivery Method', 'wp-sms'),
                'value' => OptionUtil::get('sms_delivery_method'),
                'debug' => OptionUtil::get('sms_delivery_method'),
            ],
            'unicodeMessaging'                      => [
                'label' => esc_html__('Unicode Messaging', 'wp-sms'),
                'value' => OptionUtil::get('send_unicode') ? 'Enabled' : 'Disabled',
                'debug' => OptionUtil::get('send_unicode'),
            ],
            'numberFormatting'                      => [
                'label' => esc_html__('Number Formatting', 'wp-sms'),
                'value' => OptionUtil::get('clean_numbers') ? 'Enabled' : 'Disabled',
                'debug' => OptionUtil::get('clean_numbers'),
            ],
            'restrictToLocalNumbers'                => [
                'label' => esc_html__('Restrict to Local Numbers', 'wp-sms'),
                'value' => OptionUtil::get('send_only_local_numbers') ? 'Enabled' : 'Disabled',
                'debug' => OptionUtil::get('send_only_local_numbers'),
            ],
            'internationalMobile'                   => [
                'label' => esc_html__('International Mobile', 'wp-sms'),
                'value' => OptionUtil::get('international_mobile') ? 'Enabled' : 'Disabled',
                'debug' => OptionUtil::get('international_mobile'),
            ],
            'internationalMobileOnlyCountries'      => [
                'label' => esc_html__('International Mobile Only Countries', 'wp-sms'),
                'value' => implode(', ', (array)OptionUtil::get('international_mobile_only_countries')),
                'debug' => OptionUtil::get('international_mobile_only_countries'),
            ],
            'internationalMobilePreferredCountries' => [
                'label' => esc_html__('International Mobile Preferred Countries', 'wp-sms'),
                'value' => implode(', ', (array)OptionUtil::get('international_mobile_preferred_countries')),
                'debug' => OptionUtil::get('international_mobile_preferred_countries'),
            ],
            'mobileCountyCode'                      => [
                'label' => esc_html__('Mobile County Code', 'wp-sms'),
                'value' => OptionUtil::get('mobile_county_code'),
                'debug' => OptionUtil::get('mobile_county_code'),
            ],

            // Newsletter Settings
            'groupVisibilityInForm'                 => [
                'label' => esc_html__('Group Visibility in Form', 'wp-sms'),
                'value' => OptionUtil::get('newsletter_form_groups') ? 'Enabled' : 'Disabled',
                'debug' => OptionUtil::get('newsletter_form_groups'),
            ],
            'subscriptionConfirmation'              => [
                'label' => esc_html__('Subscription Confirmation', 'wp-sms'),
                'value' => OptionUtil::get('newsletter_form_verify') ? 'Enabled' : 'Disabled',
                'debug' => OptionUtil::get('newsletter_form_verify'),
            ],
            'welcomeSmsStatus'                      => [
                'label' => esc_html__('Welcome SMS Status', 'wp-sms'),
                'value' => OptionUtil::get('newsletter_form_welcome') ? 'Enabled' : 'Disabled',
                'debug' => OptionUtil::get('newsletter_form_welcome'),
            ],

            // Notification Settings
            'newPostAlertsStatus'                   => [
                'label' => esc_html__('New Post Alerts Status', 'wp-sms'),
                'value' => OptionUtil::get('notif_publish_new_post') ? 'Enabled' : 'Disabled',
                'debug' => OptionUtil::get('notif_publish_new_post'),
            ],
            'notificationRecipients'                => [
                'label' => esc_html__('Notification Recipients', 'wp-sms'),
                'value' => ucfirst(OptionUtil::get('notif_publish_new_post_receiver')),
                'debug' => OptionUtil::get('notif_publish_new_post_receiver'),
            ],
            'forceSend'                             => [
                'label' => esc_html__('Force Send', 'wp-sms'),
                'value' => OptionUtil::get('notif_publish_new_post_force') ? 'Enabled' : 'Disabled',
                'debug' => OptionUtil::get('notif_publish_new_post_force'),
            ],
            'sendMms'                               => [
                'label' => esc_html__('Send MMS', 'wp-sms'),
                'value' => OptionUtil::get('notif_publish_new_send_mms') ? 'Enabled' : 'Disabled',
                'debug' => OptionUtil::get('notif_publish_new_send_mms'),
            ],
            'postContentWordsLimit'                 => [
                'label' => esc_html__('Post Content Words Limit', 'wp-sms'),
                'value' => OptionUtil::get('notif_publish_new_post_words_count'),
                'debug' => OptionUtil::get('notif_publish_new_post_words_count'),
            ],
            'postAuthorNotificationStatus'          => [
                'label' => esc_html__('Post Author Notification Status', 'wp-sms'),
                'value' => OptionUtil::get('notif_publish_new_post_author') ? 'Enabled' : 'Disabled',
                'debug' => OptionUtil::get('notif_publish_new_post_author'),
            ],
            'postAuthorNotificationTitle'           => [
                'label' => esc_html__('Post Author Notification Title', 'wp-sms'),
                'value' => OptionUtil::get('notif_publish_new_post_author_title'),
                'debug' => OptionUtil::get('notif_publish_new_post_author_title'),
            ],

            // Message Button Settings
            'messageButtonStatus'                   => [
                'label' => esc_html__('Message Button Status', 'wp-sms'),
                'value' => OptionUtil::get('chatbox_message_button') ? 'Enabled' : 'Disabled',
                'debug' => OptionUtil::get('chatbox_message_button'),
            ],
            'buttonPosition'                        => [
                'label' => esc_html__('Button Position', 'wp-sms'),
                'value' => ucwords(str_replace('_', ' ', OptionUtil::get('chatbox_button_position'))),
                'debug' => OptionUtil::get('chatbox_button_position'),
            ],
            'disableWpSmsLogo'                      => [
                'label' => esc_html__('Disable WP SMS Logo', 'wp-sms'),
                'value' => OptionUtil::get('chatbox_disable_logo') ? 'Enabled' : 'Disabled',
                'debug' => OptionUtil::get('chatbox_disable_logo'),
            ],
            'resourceLinksStatus'                   => [
                'label' => esc_html__('Resource Links Status', 'wp-sms'),
                'value' => OptionUtil::get('chatbox_links_enabled') ? 'Enabled' : 'Disabled',
                'debug' => OptionUtil::get('chatbox_links_enabled'),
            ],

            // Reporting Settings
            'reportWpsmsStatistics'                 => [
                'label' => esc_html__('SMS Performance Reports', 'wp-sms'),
                'value' => OptionUtil::get('report_wpsms_statistics') ? 'Enabled' : 'Disabled',
                'debug' => OptionUtil::get('report_wpsms_statistics'),
            ],
            'notifyErrorsToAdminEmail'              => [
                'label' => esc_html__('SMS Transmission Error Alerts', 'wp-sms'),
                'value' => OptionUtil::get('notify_errors_to_admin_email') ? 'Enabled' : 'Disabled',
                'debug' => OptionUtil::get('notify_errors_to_admin_email'),
            ],

            // URL Shortener
            'shortUrlStatus'                        => [
                'label' => esc_html__('Shorten URLs', 'wp-sms'),
                'value' => OptionUtil::get('short_url_status') ? 'Enabled' : 'Disabled',
                'debug' => OptionUtil::get('short_url_status'),
            ],

            // Webhooks
            'outgoingSmsWebhook'                    => [
                'label' => esc_html__('Outgoing SMS Webhook', 'wp-sms'),
                'value' => !empty(OptionUtil::get('new_sms_webhook')) ? 'Configured' : 'Not configured',
                'debug' => OptionUtil::get('new_sms_webhook'),
            ],
            'subscriberRegistrationWebhook'         => [
                'label' => esc_html__('Subscriber Registration Webhook', 'wp-sms'),
                'value' => !empty(OptionUtil::get('new_subscriber_webhook')) ? 'Configured' : 'Not configured',
                'debug' => OptionUtil::get('new_subscriber_webhook'),
            ],
            'incomingSmsWebhook'                    => [
                'label' => esc_html__('Incoming SMS Webhook', 'wp-sms'),
                'value' => !empty(OptionUtil::get('new_incoming_sms_webhook')) ? 'Configured' : 'Not configured',
                'debug' => OptionUtil::get('new_incoming_sms_webhook'),
            ],

            // reCAPTCHA
            'recaptchaStatus'                       => [
                'label' => esc_html__('Google reCAPTCHA Status', 'wp-sms'),
                'value' => OptionUtil::get('g_recaptcha_status') ? 'Enabled' : 'Disabled',
                'debug' => OptionUtil::get('g_recaptcha_status'),
            ],
        ];


        return $settings;
    }
}