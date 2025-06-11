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
                ? ['woocommerce_pro' => self::getWoocommerceProSetting()['woocommerce_pro'] ?: []]
                : 'Not Active',
            'twoWay'     => $pluginHandler->isPluginActive('wp-sms-two-way')
                ? self::getTwoWayIntegrationSetting()['two_way'] ?: []
                : 'Not Active',
            'fluent'     => $pluginHandler->isPluginActive('wp-sms-fluent-integrations')
                ? ['fluentCrm' => self::getFluentIntegrationSetting()['fluent_crm'] ?: []]
                : 'Not Active',
            'membership' => $pluginHandler->isPluginActive('wp-sms-membership-integrations')
                ? ['paidMembershipsPro' => self::getPaidMembershipsIntegrationSetting()['paid_memberships_pro'] ?: []]
                : 'Not Active',
            'booking'    => $pluginHandler->isPluginActive('wp-sms-booking-integrations')
                ? ['booking' => self::getBookingIntegrationSetting()]
                : 'Not Active',
        ];

        return [
            'main'   => $mainSettings,
            'addons' => $addons,
        ];
    }

    public static function getMainSettings()
    {
        $settings = [
            // Basic Information
            'version'                             => [
                'label' => esc_html__('Version', 'wp-sms'),
                'value' => WP_SMS_VERSION,
            ],

            // Mobile Field Settings
            'addMobileField'                      => [
                'label' => esc_html__('Add Mobile Field', 'wp-sms'),
                'value' => OptionUtil::get('add_mobile_field') ?: 'Not Set',
                'debug' => OptionUtil::get('add_mobile_field'),
            ],
            'mobileFieldMandatoryStatus'          => [
                'label' => esc_html__('Mobile Field Mandatory Status', 'wp-sms'),
                'value' => OptionUtil::get('optional_mobile_field') === '0' ? 'Required' : 'Optional',
                'debug' => OptionUtil::get('optional_mobile_field'),
            ],

            // GDPR Settings
            'gdprCompliance'                      => [
                'label' => esc_html__('GDPR Compliance Enhancements', 'wp-sms'),
                'value' => OptionUtil::get('gdpr_compliance') ? 'Enabled' : 'Disabled',
                'debug' => OptionUtil::get('gdpr_compliance'),
            ],
            'gdprCheckboxDefault'                 => [
                'label' => esc_html__('GDPR Checkbox Default', 'wp-sms'),
                'value' => ucfirst(OptionUtil::get('newsletter_form_gdpr_confirm_checkbox')) ?: 'Not Set',
                'debug' => OptionUtil::get('newsletter_form_gdpr_confirm_checkbox'),
            ],

            // Display Settings
            'accountCreditInMenu'                 => [
                'label' => esc_html__('Admin Menu Display', 'wp-sms'),
                'value' => OptionUtil::get('account_credit_in_menu') ? 'Enabled' : 'Disabled',
                'debug' => OptionUtil::get('account_credit_in_menu'),
            ],
            'accountCreditInSendsms'              => [
                'label' => esc_html__('SMS Page Display', 'wp-sms'),
                'value' => OptionUtil::get('account_credit_in_sendsms') ? 'Enabled' : 'Disabled',
                'debug' => OptionUtil::get('account_credit_in_sendsms'),
            ],

            // SMS Gateway Settings
            'gatewayName'                         => [
                'label' => esc_html__('Gateway Name', 'wp-sms'),
                'value' => OptionUtil::get('gateway_name') ?: 'Not configured',
                'debug' => OptionUtil::get('gateway_name'),
            ],
            'smsDeliveryMethod'                   => [
                'label' => esc_html__('Delivery Method', 'wp-sms'),
                'value' => OptionUtil::get('sms_delivery_method') ?: 'Not configured',
                'debug' => OptionUtil::get('sms_delivery_method'),
            ],
            'unicodeMessaging'                    => [
                'label' => esc_html__('Unicode Messaging', 'wp-sms'),
                'value' => OptionUtil::get('send_unicode') ? 'Enabled' : 'Disabled',
                'debug' => OptionUtil::get('send_unicode'),
            ],
            'numberFormatting'                    => [
                'label' => esc_html__('Number Formatting', 'wp-sms'),
                'value' => OptionUtil::get('clean_numbers') ? 'Enabled' : 'Disabled',
                'debug' => OptionUtil::get('clean_numbers'),
            ],
            'restrictToLocalNumbers'              => [
                'label' => esc_html__('Restrict to Local Numbers', 'wp-sms'),
                'value' => OptionUtil::get('send_only_local_numbers') ? 'Enabled' : 'Disabled',
                'debug' => OptionUtil::get('send_only_local_numbers'),
            ],
            'restrictToLocalNumbersCountries'     => [
                'label' => esc_html__('Allowed Countries for SMS', 'wp-sms'),
                'value' => implode(', ', (array)OptionUtil::get('only_local_numbers_countries')) ?: 'Not Set',
                'debug' => OptionUtil::get('only_local_numbers_countries'),
            ],
            'internationalMobile'                 => [
                'label' => esc_html__('International Mobile', 'wp-sms'),
                'value' => OptionUtil::get('international_mobile') ? 'Enabled' : 'Disabled',
                'debug' => OptionUtil::get('international_mobile'),
            ],
            'internationalMobileOnlyCountries'    => [
                'label' => esc_html__('International Mobile Only Countries', 'wp-sms'),
                'value' => implode(', ', (array)OptionUtil::get('international_mobile_only_countries')) ?: 'Not Set',
                'debug' => OptionUtil::get('international_mobile_only_countries'),
            ],
            'preferredLocalNumbersCountries'      => [
                'label' => esc_html__('International Preferred Countries', 'wp-sms'),
                'value' => implode(', ', (array)OptionUtil::get('international_mobile_preferred_countries')) ?: 'Not Set',
                'debug' => OptionUtil::get('international_mobile_preferred_countries'),
            ],
            'mobileCountyCode'                    => [
                'label' => esc_html__('Mobile County Code', 'wp-sms'),
                'value' => OptionUtil::get('mobile_county_code') ?: 'Not Set',
                'debug' => OptionUtil::get('mobile_county_code'),
            ],

            // Newsletter Settings
            'groupVisibilityInForm'               => [
                'label' => esc_html__('Group Visibility in Form', 'wp-sms'),
                'value' => OptionUtil::get('newsletter_form_groups') ? 'Enabled' : 'Disabled',
                'debug' => OptionUtil::get('newsletter_form_groups'),
            ],
            'subscriptionConfirmation'            => [
                'label' => esc_html__('Subscription Confirmation', 'wp-sms'),
                'value' => OptionUtil::get('newsletter_form_verify') ? 'Enabled' : 'Disabled',
                'debug' => OptionUtil::get('newsletter_form_verify'),
            ],
            'welcomeSmsStatus'                    => [
                'label' => esc_html__('Welcome SMS Status', 'wp-sms'),
                'value' => OptionUtil::get('newsletter_form_welcome') ? 'Enabled' : 'Disabled',
                'debug' => OptionUtil::get('newsletter_form_welcome'),
            ],
            'formMultipleGroupSelect'             => [
                'label' => esc_html__('Group Selection', 'wp-sms'),
                'value' => OptionUtil::get('newsletter_form_multiple_select') ? 'Enabled' : 'Disabled',
                'debug' => OptionUtil::get('newsletter_form_multiple_select'),
            ],

            // Notification Settings
            'newPostAlertsStatus'                 => [
                'label' => esc_html__('New Post Alerts Status', 'wp-sms'),
                'value' => OptionUtil::get('notif_publish_new_post') ? 'Enabled' : 'Disabled',
                'debug' => OptionUtil::get('notif_publish_new_post'),
            ],
            'newPostAlertsNotificationRecipients' => [
                'label' => esc_html__('New Post Alerts Notification Recipients', 'wp-sms'),
                'value' => ucfirst(OptionUtil::get('notif_publish_new_post_receiver')) ?: 'Not Set',
                'debug' => OptionUtil::get('notif_publish_new_post_receiver'),
            ],
            'newPostAlertsForceSend'              => [
                'label' => esc_html__('New Post Alerts Force Send', 'wp-sms'),
                'value' => OptionUtil::get('notif_publish_new_post_force') ? 'Enabled' : 'Disabled',
                'debug' => OptionUtil::get('notif_publish_new_post_force'),
            ],
            'newPostAlertsSendMms'                => [
                'label' => esc_html__('New Post Alerts Send MMS', 'wp-sms'),
                'value' => OptionUtil::get('notif_publish_new_send_mms') ? 'Enabled' : 'Disabled',
                'debug' => OptionUtil::get('notif_publish_new_send_mms'),
            ],
            'newPostAlertsPostContentWordsLimit'  => [
                'label' => esc_html__('New Post Alerts Post Content Words Limit', 'wp-sms'),
                'value' => OptionUtil::get('notif_publish_new_post_words_count') ?: 10,
                'debug' => OptionUtil::get('notif_publish_new_post_words_count'),
            ],
            'postAuthorNotificationStatus'        => [
                'label' => esc_html__('Post Author Notification Status', 'wp-sms'),
                'value' => OptionUtil::get('notif_publish_new_post_author') ? 'Enabled' : 'Disabled',
                'debug' => OptionUtil::get('notif_publish_new_post_author'),
            ],

            // Message Button Settings
            'messageButtonStatus'                 => [
                'label' => esc_html__('Message Button Status', 'wp-sms'),
                'value' => OptionUtil::get('chatbox_message_button') ? 'Enabled' : 'Disabled',
                'debug' => OptionUtil::get('chatbox_message_button'),
            ],
            'buttonPosition'                      => [
                'label' => esc_html__('Button Position', 'wp-sms'),
                'value' => ucwords(str_replace('_', ' ', OptionUtil::get('chatbox_button_position'))) ?: 'Not Set',
                'debug' => OptionUtil::get('chatbox_button_position'),
            ],
            'disableWpSmsLogo'                    => [
                'label' => esc_html__('Disable WP SMS Logo', 'wp-sms'),
                'value' => OptionUtil::get('chatbox_disable_logo') ? 'Enabled' : 'Disabled',
                'debug' => OptionUtil::get('chatbox_disable_logo'),
            ],
            'resourceLinksStatus'                 => [
                'label' => esc_html__('Resource Links Status', 'wp-sms'),
                'value' => OptionUtil::get('chatbox_links_enabled') ? 'Enabled' : 'Disabled',
                'debug' => OptionUtil::get('chatbox_links_enabled'),
            ],

            // Reporting Settings
            'reportWpsmsStatistics'               => [
                'label' => esc_html__('SMS Performance Reports', 'wp-sms'),
                'value' => OptionUtil::get('report_wpsms_statistics') ? 'Enabled' : 'Disabled',
                'debug' => OptionUtil::get('report_wpsms_statistics'),
            ],
            'notifyErrorsToAdminEmail'            => [
                'label' => esc_html__('SMS Transmission Error Alerts', 'wp-sms'),
                'value' => OptionUtil::get('notify_errors_to_admin_email') ? 'Enabled' : 'Disabled',
                'debug' => OptionUtil::get('notify_errors_to_admin_email'),
            ],

            // URL Shortener
            'shortUrlStatus'                      => [
                'label' => esc_html__('Shorten URLs', 'wp-sms'),
                'value' => OptionUtil::get('short_url_status') ? 'Enabled' : 'Disabled',
                'debug' => OptionUtil::get('short_url_status'),
            ],

            // Webhooks
            'outgoingSmsWebhook'                  => [
                'label' => esc_html__('Outgoing SMS Webhook', 'wp-sms'),
                'value' => !empty(OptionUtil::get('new_sms_webhook')) ? 'Configured' : 'Not configured',
                'debug' => OptionUtil::get('new_sms_webhook'),
            ],
            'subscriberRegistrationWebhook'       => [
                'label' => esc_html__('Subscriber Registration Webhook', 'wp-sms'),
                'value' => !empty(OptionUtil::get('new_subscriber_webhook')) ? 'Configured' : 'Not configured',
                'debug' => OptionUtil::get('new_subscriber_webhook'),
            ],
            'incomingSmsWebhook'                  => [
                'label' => esc_html__('Incoming SMS Webhook', 'wp-sms'),
                'value' => !empty(OptionUtil::get('new_incoming_sms_webhook')) ? 'Configured' : 'Not configured',
                'debug' => OptionUtil::get('new_incoming_sms_webhook'),
            ],

            // reCAPTCHA
            'recaptchaStatus'                     => [
                'label' => esc_html__('Google reCAPTCHA Status', 'wp-sms'),
                'value' => OptionUtil::get('g_recaptcha_status') ? 'Enabled' : 'Disabled',
                'debug' => OptionUtil::get('g_recaptcha_status'),
            ],

            // Pro: Login & Two-Factor Authentication (2FA)
            'loginWithSms'                        => [
                'label' => esc_html__('Login With SMS', 'wp-sms'),
                'value' => WPSmsOptionsManager::getOption('login_sms', \true) ? 'Enabled' : 'Disabled',
                'debug' => WPSmsOptionsManager::getOption('login_sms', \true),
            ],
            'autoRegisterOnLogin'                 => [
                'label' => esc_html__('Create User on SMS Login', 'wp-sms'),
                'value' => WPSmsOptionsManager::getOption('register_sms', \true) ? 'Enabled' : 'Disabled',
                'debug' => WPSmsOptionsManager::getOption('register_sms', \true),
            ],
            'twoFactorStatus'                     => [
                'label' => esc_html__('Two-Factor Authentication via SMS', 'wp-sms'),
                'value' => WPSmsOptionsManager::getOption('mobile_verify', \true) ? 'Enabled' : 'Disabled',
                'debug' => WPSmsOptionsManager::getOption('mobile_verify', \true),
            ],
            'twoFactorPolicy'                     => [
                'label' => esc_html__('2FA Policy', 'wp-sms'),
                'value' => ucfirst(str_replace('_', ' ', WPSmsOptionsManager::getOption('mobile_verify_method', \true))),
                'debug' => WPSmsOptionsManager::getOption('mobile_verify_method', \true),
            ],


            // Integrations
            'contactForm7Integration'             => [
                'label' => esc_html__('Contact Form 7 Integration', 'wp-sms'),
                'value' => OptionUtil::get('cf7_metabox') ? 'Enabled' : 'Disabled',
                'debug' => OptionUtil::get('cf7_metabox'),
            ],
        ];
        if (class_exists('RGFormsModel')) {
            $gfSettings = [
                'gfIntegrationActive' => [
                    'label' => esc_html__('Gravity Forms Integration Active', 'wp-sms'),
                    'value' => 'Enabled',
                    'debug' => true
                ]
            ];

            $settings = array_merge($settings, $gfSettings);
        }

        if (class_exists('Quform_Repository')) {


            $qfSettings = [
                'qfIntegrationActive' => [
                    'label' => esc_html__('Quform Integration Active', 'wp-sms'),
                    'value' => 'Yes',
                    'debug' => true
                ]
            ];

            $settings = array_merge($settings, $qfSettings);
        }

        // Add EDD settings if EDD is active
        if (class_exists('Easy_Digital_Downloads')) {
            $eddSettings = [
                'eddIntegrationActive' => [
                    'label' => esc_html__('Easy Digital Downloads Integration Active', 'wp-sms'),
                    'value' => 'Yes',
                    'debug' => true
                ],
                'eddMobileField'       => [
                    'label' => esc_html__('Mobile Field Enabled', 'wp-sms'),
                    'value' => WPSmsOptionsManager::getOption('edd_mobile_field', \true) ? 'Enabled' : 'Disabled',
                    'debug' => WPSmsOptionsManager::getOption('edd_mobile_field', \true)
                ],
                'eddNotifyOrder'       => [
                    'label' => esc_html__('New Order Notifications', 'wp-sms'),
                    'value' => WPSmsOptionsManager::getOption('edd_notify_order_enable', \true) ? 'Enabled' : 'Disabled',
                    'debug' => WPSmsOptionsManager::getOption('edd_notify_order_enable', \true)
                ],
                'eddNotifyCustomer'    => [
                    'label' => esc_html__('Customer Notifications', 'wp-sms'),
                    'value' => WPSmsOptionsManager::getOption('edd_notify_customer_enable', \true) ? 'Enabled' : 'Disabled',
                    'debug' => WPSmsOptionsManager::getOption('edd_notify_customer_enable', \true)
                ]
            ];

            // Merge EDD settings with existing settings
            $settings = array_merge($settings, $eddSettings);
        }

        if (class_exists('WP_Job_Manager')) {
            $jobSettings = [
                'jobManagerIntegrationActive' => [
                    'label' => esc_html__('WP Job Manager Integration Active', 'wp-sms'),
                    'value' => 'Yes',
                    'debug' => true
                ],
                'jobMobileFieldEnabled'       => [
                    'label' => esc_html__('Job Mobile Field Enabled', 'wp-sms'),
                    'value' => WPSmsOptionsManager::getOption('job_mobile_field', \true) ? 'Enabled' : 'Disabled',
                    'debug' => WPSmsOptionsManager::getOption('job_mobile_field', \true)
                ],
                'jobDisplayMobileEnabled'     => [
                    'label' => esc_html__('Display Mobile Number Enabled', 'wp-sms'),
                    'value' => WPSmsOptionsManager::getOption('job_display_mobile_number', \true) ? 'Enabled' : 'Disabled',
                    'debug' => WPSmsOptionsManager::getOption('job_display_mobile_number', \true)
                ],
                'jobNewJobNotification'       => [
                    'label' => esc_html__('New Job Notification Status', 'wp-sms'),
                    'value' => WPSmsOptionsManager::getOption('job_notify_status', \true) ? 'Enabled' : 'Disabled',
                    'debug' => WPSmsOptionsManager::getOption('job_notify_status', \true)
                ],
                'jobNotificationReceiverType' => [
                    'label' => esc_html__('New Job Notification Receiver Type', 'wp-sms'),
                    'value' => WPSmsOptionsManager::getOption('job_notify_receiver', \true),
                    'debug' => WPSmsOptionsManager::getOption('job_notify_receiver', \true)
                ],
                'jobEmployerNotification'     => [
                    'label' => esc_html__('Employer Notification Status', 'wp-sms'),
                    'value' => WPSmsOptionsManager::getOption('job_notify_employer_status', \true) ? 'Enabled' : 'Disabled',
                    'debug' => WPSmsOptionsManager::getOption('job_notify_employer_status', \true)
                ]
            ];

            // Merge Job Manager settings with existing settings
            $settings = array_merge($settings, $jobSettings);
        }

        if (class_exists('Awesome_Support')) {
            $asSettings = [
                'asIntegrationActive'        => [
                    'label' => esc_html__('Awesome Support Integration Active', 'wp-sms'),
                    'value' => 'Yes',
                    'debug' => true
                ],
                'asNewTicketNotification'    => [
                    'label' => esc_html__('New Ticket Notification', 'wp-sms'),
                    'value' => WPSmsOptionsManager::getOption('as_notify_open_ticket_status', \true) ? 'Enabled' : 'Disabled',
                    'debug' => WPSmsOptionsManager::getOption('as_notify_open_ticket_status', \true)
                ],
                'asAdminReplyNotification'   => [
                    'label' => esc_html__('Admin Reply Notification', 'wp-sms'),
                    'value' => WPSmsOptionsManager::getOption('as_notify_admin_reply_ticket_status', \true) ? 'Enabled' : 'Disabled',
                    'debug' => WPSmsOptionsManager::getOption('as_notify_admin_reply_ticket_status', \true)
                ],
                'asUserReplyNotification'    => [
                    'label' => esc_html__('User Reply Notification', 'wp-sms'),
                    'value' => WPSmsOptionsManager::getOption('as_notify_user_reply_ticket_status', \true) ? 'Enabled' : 'Disabled',
                    'debug' => WPSmsOptionsManager::getOption('as_notify_user_reply_ticket_status', \true)
                ],
                'asStatusUpdateNotification' => [
                    'label' => esc_html__('Status Update Notification', 'wp-sms'),
                    'value' => WPSmsOptionsManager::getOption('as_notify_update_ticket_status', \true) ? 'Enabled' : 'Disabled',
                    'debug' => WPSmsOptionsManager::getOption('as_notify_update_ticket_status', \true)
                ],
                'asTicketCloseNotification'  => [
                    'label' => esc_html__('Ticket Close Notification', 'wp-sms'),
                    'value' => WPSmsOptionsManager::getOption('as_notify_close_ticket_status', \true) ? 'Enabled' : 'Disabled',
                    'debug' => WPSmsOptionsManager::getOption('as_notify_close_ticket_status', \true)
                ]
            ];

            // Merge Awesome Support settings with existing settings
            $settings = array_merge($settings, $asSettings);
        }

        if (function_exists('um_user')) {
            $umSettings = [
                'umIntegrationActive'    => [
                    'label' => esc_html__('Ultimate Member Integration Active', 'wp-sms'),
                    'value' => 'Yes',
                    'debug' => true
                ],
                'umApprovalNotification' => [
                    'label' => esc_html__('User Approval Notification', 'wp-sms'),
                    'value' => WPSmsOptionsManager::getOption('um_send_sms_after_approval', \true) ? 'Enabled' : 'Disabled',
                    'debug' => WPSmsOptionsManager::getOption('um_send_sms_after_approval', \true)
                ]
            ];

            // Merge Ultimate Member settings with existing settings
            $settings = array_merge($settings, $umSettings);
        }

        if (function_exists('is_plugin_active') && is_plugin_active('formidable/formidable.php')) {
            $formidableSettings = [
                'formidablePluginStatus'  => [
                    'label' => esc_html__('Formidable Plugin Active', 'wp-sms'),
                    'value' => 'Active',
                    'debug' => true
                ],
                'formidableMetaboxStatus' => [
                    'label' => esc_html__('Formidable Metabox', 'wp-sms'),
                    'value' => WPSmsOptionsManager::getOption('formidable_metabox', \true) ? 'Enabled' : 'Disabled',
                    'debug' => WPSmsOptionsManager::getOption('formidable_metabox', \true)
                ]
            ];

            // Merge Formidable settings with existing settings
            $settings = array_merge($settings, $formidableSettings);
        }

        if (class_exists('Forminator')) {
            $forminatorSettings = [
                'forminatorPluginStatus' => [
                    'label' => esc_html__('Forminator Plugin Active', 'wp-sms'),
                    'value' => 'Active',
                    'debug' => true
                ],
            ];

            // Merge Forminator settings with existing settings
            $settings = array_merge($settings, $forminatorSettings);
        }

        if (function_exists('buddypress')) {
            $bpSettings = [
                'bpWelcomeSmsStatus'           => [
                    'label' => esc_html__('BuddyPress: Welcome SMS', 'wp-sms'),
                    'value' => WPSmsOptionsManager::getOption('bp_welcome_notification_enable', \true) ? 'Enabled' : 'Disabled',
                    'debug' => WPSmsOptionsManager::getOption('bp_welcome_notification_enable', \true),
                ],
                'bpMentionNotification'        => [
                    'label' => esc_html__('BuddyPress: Mention Alerts', 'wp-sms'),
                    'value' => WPSmsOptionsManager::getOption('bp_mention_enable', \true) ? 'Enabled' : 'Disabled',
                    'debug' => WPSmsOptionsManager::getOption('bp_mention_enable', \true),
                ],
                'bpMentionMessageBody'         => [
                    'label' => esc_html__('BuddyPress: Mention Message Body', 'wp-sms'),
                    'value' => !empty(WPSmsOptionsManager::getOption('bp_mention_message', \true)) ? 'Customized' : 'Empty',
                    'debug' => 'Hidden for privacy',
                ],
                'bpPrivateMessageNotification' => [
                    'label' => esc_html__('BuddyPress: Private Messages', 'wp-sms'),
                    'value' => WPSmsOptionsManager::getOption('bp_private_message_enable', \true) ? 'Enabled' : 'Disabled',
                    'debug' => WPSmsOptionsManager::getOption('bp_private_message_enable', \true),
                ],
                'bpActivityReplyNotification'  => [
                    'label' => esc_html__('BuddyPress: Activity Replies', 'wp-sms'),
                    'value' => WPSmsOptionsManager::getOption('bp_comments_activity_enable', \true) ? 'Enabled' : 'Disabled',
                    'debug' => WPSmsOptionsManager::getOption('bp_comments_activity_enable', \true),
                ],
                'bpActivityReplyMessageBody'   => [
                    'label' => esc_html__('BuddyPress: Activity Reply Message', 'wp-sms'),
                    'value' => !empty(WPSmsOptionsManager::getOption('bp_comments_activity_message', \true)) ? 'Customized' : 'Empty',
                    'debug' => 'Hidden for privacy',
                ],
                'bpCommentReplyNotification'   => [
                    'label' => esc_html__('BuddyPress: Comment Replies', 'wp-sms'),
                    'value' => WPSmsOptionsManager::getOption('bp_comments_reply_enable', \true) ? 'Enabled' : 'Disabled',
                    'debug' => WPSmsOptionsManager::getOption('bp_comments_reply_enable', \true),
                ]
            ];

            $settings = array_merge($settings, $bpSettings);
        }

        if (class_exists('WooCommerce')) {
            $wcSettings = [
                'wcMetaBoxEnable'                       => [
                    'label' => esc_html__('Order Meta Box Status', 'wp-sms'),
                    'value' => WPSmsOptionsManager::getOption('wc_meta_box_enable', \true) ? 'Enabled' : 'Disabled',
                    'debug' => WPSmsOptionsManager::getOption('wc_meta_box_enable', \true),
                ],
                'wcNotifyProductEnable'                 => [
                    'label' => esc_html__('New Product Notification Status', 'wp-sms'),
                    'value' => WPSmsOptionsManager::getOption('wc_notify_product_enable', \true) ? 'Enabled' : 'Disabled',
                    'debug' => WPSmsOptionsManager::getOption('wc_notify_product_enable', \true),
                ],
                'wcNotifyOrderEnable'                   => [
                    'label' => esc_html__('New Order Notification Status', 'wp-sms'),
                    'value' => WPSmsOptionsManager::getOption('wc_notify_order_enable', \true) ? 'Enabled' : 'Disabled',
                    'debug' => WPSmsOptionsManager::getOption('wc_notify_order_enable', \true),
                ],
                'wcNotifyCustomerEnable'                => [
                    'label' => esc_html__('Customer Order Notification Status', 'wp-sms'),
                    'value' => WPSmsOptionsManager::getOption('wc_notify_customer_enable', \true) ? 'Enabled' : 'Disabled',
                    'debug' => WPSmsOptionsManager::getOption('wc_notify_customer_enable', \true),
                ],
                'wcNotifyStockEnable'                   => [
                    'label' => esc_html__('Low Stock Notification Status', 'wp-sms'),
                    'value' => WPSmsOptionsManager::getOption('wc_notify_stock_enable', \true) ? 'Enabled' : 'Disabled',
                    'debug' => WPSmsOptionsManager::getOption('wc_notify_stock_enable', \true),
                ],
                'wcCheckoutConfirmationCheckboxEnabled' => [
                    'label' => esc_html__('Checkout Confirmation Checkbox Status', 'wp-sms'),
                    'value' => WPSmsOptionsManager::getOption('wc_checkout_confirmation_checkbox_enabled', \true) ? 'Enabled' : 'Disabled',
                    'debug' => WPSmsOptionsManager::getOption('wc_checkout_confirmation_checkbox_enabled', \true),
                ],
                'wcNotifyStatusEnable'                  => [
                    'label' => esc_html__('Order Status Notification Status', 'wp-sms'),
                    'value' => WPSmsOptionsManager::getOption('wc_notify_status_enable', \true) ? 'Enabled' : 'Disabled',
                    'debug' => WPSmsOptionsManager::getOption('wc_notify_status_enable', \true),
                ],
                'wcNotifyByStatusEnable'                => [
                    'label' => esc_html__('Specific Order Status Notification Status', 'wp-sms'),
                    'value' => WPSmsOptionsManager::getOption('wc_notify_by_status_enable', \true) ? 'Enabled' : 'Disabled',
                    'debug' => WPSmsOptionsManager::getOption('wc_notify_by_status_enable', \true),
                ],
                'wcNotifyByStatusContentCount'          => [
                    'label' => esc_html__('Number of Configured Status Notifications', 'wp-sms'),
                    'value' => is_array(WPSmsOptionsManager::getOption('wc_notify_by_status_content', \true)) ? count(WPSmsOptionsManager::getOption('wc_notify_by_status_content', \true)) : 0,
                    'debug' => is_array(WPSmsOptionsManager::getOption('wc_notify_by_status_content', \true)) ? count(WPSmsOptionsManager::getOption('wc_notify_by_status_content', \true)) : 0,
                ]
            ];


            $settings = array_merge($settings, $wcSettings);
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
        $integrations = array();

        // === Booking Calendar ===
        $bookingCalendarOptions = array(
            'booking_calendar_notif_customer_mobile_field'      => __('Booking Calendar: Customer Mobile Field', 'wp-sms'),
            'booking_calendar_notif_admin_new_booking'          => __('Booking Calendar: Admin New Booking Notification', 'wp-sms'),
            'booking_calendar_notif_customer_new_booking'       => __('Booking Calendar: Customer New Booking Notification', 'wp-sms'),
            'booking_calendar_notif_customer_booking_approved'  => __('Booking Calendar: Booking Approved Notification', 'wp-sms'),
            'booking_calendar_notif_customer_booking_cancelled' => __('Booking Calendar: Booking Cancelled Notification', 'wp-sms'),
        );

        $bookingCalendarSettings = array();

        foreach ($bookingCalendarOptions as $key => $label) {
            $raw   = OptionUtil::get($key);
            $value = (is_bool($raw) || $raw === '0' || $raw === '1') ? ($raw ? 'Enabled' : 'Disabled') : (is_string($raw) ? $raw : 'Not Set');

            $alias = preg_replace_callback('/_([a-z])/', function ($matches) {
                return strtoupper($matches[1]);
            }, $key);

            $bookingCalendarSettings[$alias] = array(
                'label' => esc_html($label),
                'value' => $value,
                'debug' => $raw,
            );
        }

        // === BookingPress ===
        $bookingPressOptions = array(
            'bookingpress_notif_admin_approved_appointment'     => __('BookingPress: Admin Approved Appointment', 'wp-sms'),
            'bookingpress_notif_customer_approved_appointment'  => __('BookingPress: Customer Approved Appointment', 'wp-sms'),
            'bookingpress_notif_admin_pending_appointment'      => __('BookingPress: Admin Pending Appointment', 'wp-sms'),
            'bookingpress_notif_customer_pending_appointment'   => __('BookingPress: Customer Pending Appointment', 'wp-sms'),
            'bookingpress_notif_admin_rejected_appointment'     => __('BookingPress: Admin Rejected Appointment', 'wp-sms'),
            'bookingpress_notif_customer_rejected_appointment'  => __('BookingPress: Customer Rejected Appointment', 'wp-sms'),
            'bookingpress_notif_admin_cancelled_appointment'    => __('BookingPress: Admin Cancelled Appointment', 'wp-sms'),
            'bookingpress_notif_customer_cancelled_appointment' => __('BookingPress: Customer Cancelled Appointment', 'wp-sms'),
        );

        $bookingPressSettings = array();

        foreach ($bookingPressOptions as $key => $label) {
            $raw   = OptionUtil::get($key);
            $value = (is_bool($raw) || $raw === '0' || $raw === '1') ? ($raw ? 'Enabled' : 'Disabled') : (is_string($raw) ? $raw : 'Not Set');

            $alias = preg_replace_callback('/_([a-z])/', function ($matches) {
                return strtoupper($matches[1]);
            }, $key);

            $bookingPressSettings[$alias] = array(
                'label' => esc_html($label),
                'value' => $value,
                'debug' => $raw,
            );
        }

        // === Woo Appointments ===
        $wooAppointmentsOptions = array(
            'woo_appointments_notif_admin_new_appointment'          => __('Woo Appointments: Admin New Appointment', 'wp-sms'),
            'woo_appointments_notif_admin_cancelled_appointment'    => __('Woo Appointments: Admin Cancelled Appointment', 'wp-sms'),
            'woo_appointments_notif_customer_cancelled_appointment' => __('Woo Appointments: Customer Cancelled Appointment', 'wp-sms'),
            'woo_appointments_notif_admin_rescheduled_appointment'  => __('Woo Appointments: Admin Rescheduled Appointment', 'wp-sms'),
            'woo_appointments_notif_customer_confirmed_appointment' => __('Woo Appointments: Customer Confirmed Appointment', 'wp-sms'),
        );

        $wooAppointmentsSettings = array();

        foreach ($wooAppointmentsOptions as $key => $label) {
            $raw   = OptionUtil::get($key);
            $value = (is_bool($raw) || $raw === '0' || $raw === '1') ? ($raw ? 'Enabled' : 'Disabled') : (is_string($raw) ? $raw : 'Not Set');

            $alias = preg_replace_callback('/_([a-z])/', function ($matches) {
                return strtoupper($matches[1]);
            }, $key);

            $wooAppointmentsSettings[$alias] = array(
                'label' => esc_html($label),
                'value' => $value,
                'debug' => $raw,
            );
        }

        // === Woo Bookings ===
        $wooBookingsOptions = array(
            'woo_bookings_notif_admin_new_booking'          => __('Woo Bookings: Admin New Booking', 'wp-sms'),
            'wooBookingsNotifAdminNewBooking'               => __('Woo Bookings: Admin Cancelled Booking', 'wp-sms'),
            'woo_bookings_notif_customer_cancelled_booking' => __('Woo Bookings: Customer Cancelled Booking', 'wp-sms'),
            'woo_bookings_notif_customer_confirmed_booking' => __('Woo Bookings: Customer Confirmed Booking', 'wp-sms'),
        );

        $wooBookingsSettings = array();

        foreach ($wooBookingsOptions as $key => $label) {
            $raw   = OptionUtil::get($key);
            $value = (is_bool($raw) || $raw === '0' || $raw === '1') ? ($raw ? 'Enabled' : 'Disabled') : (is_string($raw) ? $raw : 'Not Set');

            $alias = preg_replace_callback('/_([a-z])/', function ($matches) {
                return strtoupper($matches[1]);
            }, $key);

            $wooBookingsSettings[$alias] = array(
                'label' => esc_html($label),
                'value' => $value,
                'debug' => $raw,
            );
        }

        $integrations['booking_calendar'] = $bookingCalendarSettings;
        $integrations['booking_press']    = $bookingPressSettings;
        $integrations['woo_appointments'] = $wooAppointmentsSettings;
        $integrations['woo_bookings']     = $wooBookingsSettings;

        return $integrations;
    }

    /**
     * Retrieves anonymized settings for FluentCRM integration in structured format.
     *
     * @return array
     */
    public static function getFluentIntegrationSetting()
    {
        $integrations = array();

        // === FluentCRM ===
        $fluentCrmOptions = array(
            'fluent_crm_notif_contact_subscribed'   => __('FluentCRM: Contact Subscribed Notification', 'wp-sms'),
            'fluent_crm_notif_contact_unsubscribed' => __('FluentCRM: Contact Unsubscribed Notification', 'wp-sms'),
            'fluent_crm_notif_contact_pending'      => __('FluentCRM: Contact Pending Notification', 'wp-sms'),
        );

        $fluentCrmSettings = array();

        foreach ($fluentCrmOptions as $key => $label) {
            $raw = \WP_SMS\Utils\OptionUtil::get($key);

            $value = (is_bool($raw) || $raw === '0' || $raw === '1') ? ($raw ? 'Enabled' : 'Disabled') : (is_string($raw) ? $raw : 'Not Set');

            $alias = preg_replace_callback('/_([a-z])/', function ($matches) {
                return strtoupper($matches[1]);
            }, $key);

            $fluentCrmSettings[$alias] = array(
                'label' => esc_html($label),
                'value' => $value,
                'debug' => $raw,
            );
        }

        $integrations['fluent_crm'] = $fluentCrmSettings;

        return $integrations;
    }

    /**
     * Retrieves settings for Paid Memberships Pro integration in structured format.
     *
     * @return array
     */
    public static function getPaidMembershipsIntegrationSetting()
    {
        $integration = array();

        $options = array(
            'pmpro_notif_user_registered'      => __('Paid Memberships Pro: User Registered Notification', 'wp-sms'),
            'pmpro_notif_membership_confirmed' => __('Paid Memberships Pro: Membership Confirmed Notification', 'wp-sms'),
            'pmpro_notif_membership_cancelled' => __('Paid Memberships Pro: Membership Cancelled Notification', 'wp-sms'),
            'pmpro_notif_membership_expired'   => __('Paid Memberships Pro: Membership Expired Notification', 'wp-sms'),
        );

        foreach ($options as $key => $label) {
            $raw = \WP_SMS\Utils\OptionUtil::get($key);

            $value = (is_bool($raw) || $raw === '0' || $raw === '1') ? ($raw ? 'Enabled' : 'Disabled') : (is_string($raw) ? $raw : 'Not Set');

            $alias = preg_replace_callback('/_([a-z])/', function ($matches) {
                return strtoupper($matches[1]);
            }, $key);

            $integration[$alias] = array(
                'label' => esc_html($label),
                'value' => $value,
                'debug' => $raw,
            );
        }

        return array('paid_memberships_pro' => $integration);
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
            'notif_new_inbox_message' => __('Two-Way: Forward Incoming SMS to Admin', 'wp-sms'),
            'email_new_inbox_message' => __('Two-Way: Forward Incoming SMS to Email', 'wp-sms'),
        );

        foreach ($options as $key => $label) {
            $raw = \WP_SMS\Utils\OptionUtil::get($key);

            $value = (is_bool($raw) || $raw === '0' || $raw === '1') ? ($raw ? 'Enabled' : 'Disabled') : (is_string($raw) ? $raw : 'Not Set');

            $alias = preg_replace_callback('/_([a-z])/', function ($matches) {
                return strtoupper($matches[1]);
            }, $key);

            $integration[$alias] = array(
                'label' => esc_html($label),
                'value' => $value,
                'debug' => $raw,
            );
        }

        return array('two_way' => $integration);
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
            'login_with_sms_status'                               => __('My Account: Login via SMS (Login Page)', 'wp-sms-woocommerce-pro'),
            'login_with_sms_forgot_status'                        => __('My Account: Login via SMS (Forgot Password Page)', 'wp-sms-woocommerce-pro'),
            'register_user_via_sms_status'                        => __('My Account: Automatic Registration via SMS', 'wp-sms-woocommerce-pro'),
            'reset_password_status'                               => __('My Account: Enable SMS Password Reset', 'wp-sms-woocommerce-pro'),

            // Cart
            'cart_abandonment_recovery_status'                    => __('Cart: Abandonment Recovery Status', 'wp-sms-woocommerce-pro'),
            'cart_create_coupon'                                  => __('Cart: Auto-Create Coupon for Recovery', 'wp-sms-woocommerce-pro'),
            'cart_overwrite_number_during_checkout'               => __('Cart: Overwrite Phone Number on Checkout', 'wp-sms-woocommerce-pro'),
            'cart_abandonment_threshold'                          => __('Cart: Cart abandonment threshold', 'wp-sms-woocommerce-pro'),
            'cart_abandonment_send_sms_time_interval'             => __('Cart: SMS Send Delay Configured', 'wp-sms-woocommerce-pro'),

            // Checkout
            'checkout_confirmation_checkbox_enabled'              => __('Checkout: Confirmation Checkbox Enabled', 'wp-sms-woocommerce-pro'),
            'checkout_mobile_verification_enabled'                => __('Checkout: Mobile Verification Enabled', 'wp-sms-woocommerce-pro'),
            'checkout_mobile_verification_skip_logged_in_enabled' => __('Checkout: Skip Verification for Logged-In Users', 'wp-sms-woocommerce-pro'),

            // Notifications – Admin
            'new_order_admin_enabled'                             => __('Notification: New Order (Admin)', 'wp-sms-woocommerce-pro'),
            'new_order_admin_by_status_enabled'                   => __('Notification: Order by Status (Admin)', 'wp-sms-woocommerce-pro'),
            'product_stock_admin_enabled'                         => __('Notification: Low Product Stock (Admin)', 'wp-sms-woocommerce-pro'),

            // Notifications – Customer
            'new_order_customer_enabled'                          => __('Notification: New Order (Customer)', 'wp-sms-woocommerce-pro'),
            'new_product_customer_enabled'                        => __('Notification: New Product (Customer)', 'wp-sms-woocommerce-pro'),
            'order_cancelled_customer_enabled'                    => __('Notification: Order Cancelled (Customer)', 'wp-sms-woocommerce-pro'),
            'order_failed_customer_enabled'                       => __('Notification: Order Failed (Customer)', 'wp-sms-woocommerce-pro'),
            'order_onhold_customer_enabled'                       => __('Notification: Order On Hold (Customer)', 'wp-sms-woocommerce-pro'),
            'order_processing_customer_enabled'                   => __('Notification: Order Processing (Customer)', 'wp-sms-woocommerce-pro'),
            'order_completed_customer_enabled'                    => __('Notification: Order Completed (Customer)', 'wp-sms-woocommerce-pro'),
            'order_refunded_customer_enabled'                     => __('Notification: Order Refunded (Customer)', 'wp-sms-woocommerce-pro'),
        ];

        foreach ($fields as $key => $label) {
            $raw = \WPSmsWooPro\Core\Helper::getOption($key);

            if ($key === 'cart_overwrite_number_during_checkout') {
                $value = $raw === 'skip'
                    ? __('Do not update', 'wp-sms-woocommerce-pro')
                    : __('Update phone number', 'wp-sms-woocommerce-pro');
            } elseif ($key === 'cart_abandonment_send_sms_time_interval' || $key === 'cart_abandonment_threshold') {
                if (is_array($raw)) {
                    $parts = [];

                    if (!empty($raw['days']) && $raw['days'] !== '0') {
                        $parts[] = $raw['days'] . ' ' . _n('day', 'days', (int)$raw['days'], 'wp-sms-woocommerce-pro');
                    }
                    if (!empty($raw['hours']) && $raw['hours'] !== '0') {
                        $parts[] = $raw['hours'] . ' ' . _n('hour', 'hours', (int)$raw['hours'], 'wp-sms-woocommerce-pro');
                    }
                    if (!empty($raw['minutes']) && $raw['minutes'] !== '0') {
                        $parts[] = $raw['minutes'] . ' ' . _n('minute', 'minutes', (int)$raw['minutes'], 'wp-sms-woocommerce-pro');
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

            $integration[$alias] = [
                'label' => esc_html($label),
                'value' => $value,
                'debug' => $raw,
            ];
        }

        return ['woocommerce_pro' => $integration];
    }

}