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
     * @return array
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
                ? self::getWoocommerceProSetting()['woocommerce_pro'] ?: []
                : 'Not Active',
            'twoWay'     => $pluginHandler->isPluginActive('wp-sms-two-way')
                ? self::getTwoWayIntegrationSetting()['two_way'] ?: []
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

    public static function getMainSettings()
    {
        $settings = [
            // Basic Information
            'version'                             => [
                'label' => 'Version',
                'value' => WP_SMS_VERSION,
            ],

            // Mobile Field Settings
            'addMobileField'                      => [
                'label' => 'Add Mobile Field',
                'value' => OptionUtil::get('add_mobile_field') ?: 'Not Set',
                'debug' => OptionUtil::get('add_mobile_field'),
            ],
            'mobileFieldMandatoryStatus'          => [
                'label' => 'Mobile Field Mandatory Status',
                'value' => OptionUtil::get('optional_mobile_field') === '0' ? 'Required' : 'Optional',
                'debug' => OptionUtil::get('optional_mobile_field'),
            ],

            // GDPR Settings
            'gdprCompliance'                      => [
                'label' => 'GDPR Compliance Enhancements',
                'value' => OptionUtil::get('gdpr_compliance') ? 'Enabled' : 'Disabled',
                'debug' => OptionUtil::get('gdpr_compliance'),
            ],
            'gdprCheckboxDefault'                 => [
                'label' => 'GDPR Checkbox Default',
                'value' => ucfirst(OptionUtil::get('newsletter_form_gdpr_confirm_checkbox')) ?: 'Not Set',
                'debug' => OptionUtil::get('newsletter_form_gdpr_confirm_checkbox'),
            ],

            // Display Settings
            'accountCreditInMenu'                 => [
                'label' => 'Admin Menu Display',
                'value' => OptionUtil::get('account_credit_in_menu') ? 'Enabled' : 'Disabled',
                'debug' => OptionUtil::get('account_credit_in_menu'),
            ],
            'accountCreditInSendsms'              => [
                'label' => 'SMS Page Display',
                'value' => OptionUtil::get('account_credit_in_sendsms') ? 'Enabled' : 'Disabled',
                'debug' => OptionUtil::get('account_credit_in_sendsms'),
            ],

            // SMS Gateway Settings
            'gatewayName'                         => [
                'label' => 'Gateway Name',
                'value' => OptionUtil::get('gateway_name') ?: 'Not configured',
                'debug' => OptionUtil::get('gateway_name'),
            ],
            'smsDeliveryMethod'                   => [
                'label' => 'Delivery Method',
                'value' => OptionUtil::get('sms_delivery_method') ?: 'Not configured',
                'debug' => OptionUtil::get('sms_delivery_method'),
            ],
            'unicodeMessaging'                    => [
                'label' => 'Unicode Messaging',
                'value' => OptionUtil::get('send_unicode') ? 'Enabled' : 'Disabled',
                'debug' => OptionUtil::get('send_unicode'),
            ],
            'numberFormatting'                    => [
                'label' => 'Number Formatting',
                'value' => OptionUtil::get('clean_numbers') ? 'Enabled' : 'Disabled',
                'debug' => OptionUtil::get('clean_numbers'),
            ],
            'restrictToLocalNumbers'              => [
                'label' => 'Restrict to Local Numbers',
                'value' => OptionUtil::get('send_only_local_numbers') ? 'Enabled' : 'Disabled',
                'debug' => OptionUtil::get('send_only_local_numbers'),
            ],
            'restrictToLocalNumbersCountries'     => [
                'label' => 'Allowed Countries for SMS',
                'value' => implode(', ', (array)OptionUtil::get('only_local_numbers_countries')) ?: 'Not Set',
                'debug' => OptionUtil::get('only_local_numbers_countries'),
            ],
            'internationalMobile'                 => [
                'label' => 'International Mobile',
                'value' => OptionUtil::get('international_mobile') ? 'Enabled' : 'Disabled',
                'debug' => OptionUtil::get('international_mobile'),
            ],
            'internationalMobileOnlyCountries'    => [
                'label' => 'International Mobile Only Countries',
                'value' => implode(', ', (array)OptionUtil::get('international_mobile_only_countries')) ?: 'Not Set',
                'debug' => OptionUtil::get('international_mobile_only_countries'),
            ],
            'preferredLocalNumbersCountries'      => [
                'label' => 'International Preferred Countries',
                'value' => implode(', ', (array)OptionUtil::get('international_mobile_preferred_countries')) ?: 'Not Set',
                'debug' => OptionUtil::get('international_mobile_preferred_countries'),
            ],
            'mobileCountyCode'                    => [
                'label' => 'Mobile County Code',
                'value' => OptionUtil::get('mobile_county_code') ?: 'Not Set',
                'debug' => OptionUtil::get('mobile_county_code'),
            ],

            // Newsletter Settings
            'groupVisibilityInForm'               => [
                'label' => 'Group Visibility in Form',
                'value' => OptionUtil::get('newsletter_form_groups') ? 'Enabled' : 'Disabled',
                'debug' => OptionUtil::get('newsletter_form_groups'),
            ],
            'subscriptionConfirmation'            => [
                'label' => 'Subscription Confirmation',
                'value' => OptionUtil::get('newsletter_form_verify') ? 'Enabled' : 'Disabled',
                'debug' => OptionUtil::get('newsletter_form_verify'),
            ],
            'welcomeSmsStatus'                    => [
                'label' => 'Welcome SMS Status',
                'value' => OptionUtil::get('newsletter_form_welcome') ? 'Enabled' : 'Disabled',
                'debug' => OptionUtil::get('newsletter_form_welcome'),
            ],
            'formMultipleGroupSelect'             => [
                'label' => 'Group Selection',
                'value' => OptionUtil::get('newsletter_form_multiple_select') ? 'Enabled' : 'Disabled',
                'debug' => OptionUtil::get('newsletter_form_multiple_select'),
            ],

            // Notification Settings
            'newPostAlertsStatus'                 => [
                'label' => 'New Post Alerts Status',
                'value' => OptionUtil::get('notif_publish_new_post') ? 'Enabled' : 'Disabled',
                'debug' => OptionUtil::get('notif_publish_new_post'),
            ],
            'newPostAlertsNotificationRecipients' => [
                'label' => 'New Post Alerts Notification Recipients',
                'value' => ucfirst(OptionUtil::get('notif_publish_new_post_receiver')) ?: 'Not Set',
                'debug' => OptionUtil::get('notif_publish_new_post_receiver'),
            ],
            'newPostAlertsForceSend'              => [
                'label' => 'New Post Alerts Force Send',
                'value' => OptionUtil::get('notif_publish_new_post_force') ? 'Enabled' : 'Disabled',
                'debug' => OptionUtil::get('notif_publish_new_post_force'),
            ],
            'newPostAlertsSendMms'                => [
                'label' => 'New Post Alerts Send MMS',
                'value' => OptionUtil::get('notif_publish_new_send_mms') ? 'Enabled' : 'Disabled',
                'debug' => OptionUtil::get('notif_publish_new_send_mms'),
            ],
            'newPostAlertsPostContentWordsLimit'  => [
                'label' => 'New Post Alerts Post Content Words Limit',
                'value' => OptionUtil::get('notif_publish_new_post_words_count') ?: 10,
                'debug' => OptionUtil::get('notif_publish_new_post_words_count'),
            ],
            'postAuthorNotificationStatus'        => [
                'label' => 'Post Author Notification Status',
                'value' => OptionUtil::get('notif_publish_new_post_author') ? 'Enabled' : 'Disabled',
                'debug' => OptionUtil::get('notif_publish_new_post_author'),
            ],

            // Message Button Settings
            'messageButtonStatus'                 => [
                'label' => 'Message Button Status',
                'value' => OptionUtil::get('chatbox_message_button') ? 'Enabled' : 'Disabled',
                'debug' => OptionUtil::get('chatbox_message_button'),
            ],
            'buttonPosition'                      => [
                'label' => 'Button Position',
                'value' => ucwords(str_replace('_', ' ', OptionUtil::get('chatbox_button_position'))) ?: 'Not Set',
                'debug' => OptionUtil::get('chatbox_button_position'),
            ],
            'disableWpSmsLogo'                    => [
                'label' => 'Disable WP SMS Logo',
                'value' => OptionUtil::get('chatbox_disable_logo') ? 'Enabled' : 'Disabled',
                'debug' => OptionUtil::get('chatbox_disable_logo'),
            ],
            'resourceLinksStatus'                 => [
                'label' => 'Resource Links Status',
                'value' => OptionUtil::get('chatbox_links_enabled') ? 'Enabled' : 'Disabled',
                'debug' => OptionUtil::get('chatbox_links_enabled'),
            ],

            // Reporting Settings
            'reportWpsmsStatistics'               => [
                'label' => 'SMS Performance Reports',
                'value' => OptionUtil::get('report_wpsms_statistics') ? 'Enabled' : 'Disabled',
                'debug' => OptionUtil::get('report_wpsms_statistics'),
            ],
            'notifyErrorsToAdminEmail'            => [
                'label' => 'SMS Transmission Error Alerts',
                'value' => OptionUtil::get('notify_errors_to_admin_email') ? 'Enabled' : 'Disabled',
                'debug' => OptionUtil::get('notify_errors_to_admin_email'),
            ],

            // URL Shortener
            'shortUrlStatus'                      => [
                'label' => 'Shorten URLs',
                'value' => OptionUtil::get('short_url_status') ? 'Enabled' : 'Disabled',
                'debug' => OptionUtil::get('short_url_status'),
            ],

            // Webhooks
            'outgoingSmsWebhook'                  => [
                'label' => 'Outgoing SMS Webhook',
                'value' => !empty(OptionUtil::get('new_sms_webhook')) ? 'Configured' : 'Not configured',
                'debug' => OptionUtil::get('new_sms_webhook'),
            ],
            'subscriberRegistrationWebhook'       => [
                'label' => 'Subscriber Registration Webhook',
                'value' => !empty(OptionUtil::get('new_subscriber_webhook')) ? 'Configured' : 'Not configured',
                'debug' => OptionUtil::get('new_subscriber_webhook'),
            ],
            'incomingSmsWebhook'                  => [
                'label' => 'Incoming SMS Webhook',
                'value' => !empty(OptionUtil::get('new_incoming_sms_webhook')) ? 'Configured' : 'Not configured',
                'debug' => OptionUtil::get('new_incoming_sms_webhook'),
            ],

            // reCAPTCHA
            'recaptchaStatus'                     => [
                'label' => 'Google reCAPTCHA Status',
                'value' => OptionUtil::get('g_recaptcha_status') ? 'Enabled' : 'Disabled',
                'debug' => OptionUtil::get('g_recaptcha_status'),
            ],

            // Pro: Login & Two-Factor Authentication (2FA)
            'loginWithSms'                        => [
                'label' => 'Login With SMS',
                'value' => WPSmsOptionsManager::getOption('login_sms', true) ? 'Enabled' : 'Disabled',
                'debug' => WPSmsOptionsManager::getOption('login_sms', true),
            ],
            'autoRegisterOnLogin'                 => [
                'label' => 'Create User on SMS Login',
                'value' => WPSmsOptionsManager::getOption('register_sms', true) ? 'Enabled' : 'Disabled',
                'debug' => WPSmsOptionsManager::getOption('register_sms', true),
            ],
            'twoFactorStatus'                     => [
                'label' => 'Two-Factor Authentication via SMS',
                'value' => WPSmsOptionsManager::getOption('mobile_verify', true) ? 'Enabled' : 'Disabled',
                'debug' => WPSmsOptionsManager::getOption('mobile_verify', true),
            ],
            'twoFactorPolicy'                     => [
                'label' => '2FA Policy',
                'value' => ucfirst(str_replace('_', ' ', WPSmsOptionsManager::getOption('mobile_verify_method', true))),
                'debug' => WPSmsOptionsManager::getOption('mobile_verify_method', true),
            ],

            // Integrations
            'contactForm7Integration'             => [
                'label' => 'Contact Form 7 Integration',
                'value' => OptionUtil::get('cf7_metabox') ? 'Enabled' : 'Disabled',
                'debug' => OptionUtil::get('cf7_metabox'),
            ],
        ];

        if (class_exists('RGFormsModel')) {
            $gfSettings = [
                'gfIntegrationActive' => [
                    'label' => 'Gravity Forms Integration Active',
                    'value' => 'Enabled',
                    'debug' => true
                ]
            ];

            $settings = array_merge($settings, $gfSettings);
        }

        if (class_exists('Quform_Repository')) {
            $qfSettings = [
                'qfIntegrationActive' => [
                    'label' => 'Quform Integration Active',
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
                    'label' => 'Easy Digital Downloads Integration Active',
                    'value' => 'Yes',
                    'debug' => true
                ],
                'eddMobileField'       => [
                    'label' => 'Mobile Field Enabled',
                    'value' => WPSmsOptionsManager::getOption('edd_mobile_field', true) ? 'Enabled' : 'Disabled',
                    'debug' => WPSmsOptionsManager::getOption('edd_mobile_field', true)
                ],
                'eddNotifyOrder'       => [
                    'label' => 'New Order Notifications',
                    'value' => WPSmsOptionsManager::getOption('edd_notify_order_enable', true) ? 'Enabled' : 'Disabled',
                    'debug' => WPSmsOptionsManager::getOption('edd_notify_order_enable', true)
                ],
                'eddNotifyCustomer'    => [
                    'label' => 'Customer Notifications',
                    'value' => WPSmsOptionsManager::getOption('edd_notify_customer_enable', true) ? 'Enabled' : 'Disabled',
                    'debug' => WPSmsOptionsManager::getOption('edd_notify_customer_enable', true)
                ]
            ];

            $settings = array_merge($settings, $eddSettings);
        }

        if (class_exists('WP_Job_Manager')) {
            $jobSettings = [
                'jobManagerIntegrationActive' => [
                    'label' => 'WP Job Manager Integration Active',
                    'value' => 'Yes',
                    'debug' => true
                ],
                'jobMobileFieldEnabled'       => [
                    'label' => 'Job Mobile Field Enabled',
                    'value' => WPSmsOptionsManager::getOption('job_mobile_field', true) ? 'Enabled' : 'Disabled',
                    'debug' => WPSmsOptionsManager::getOption('job_mobile_field', true)
                ],
                'jobDisplayMobileEnabled'     => [
                    'label' => 'Display Mobile Number Enabled',
                    'value' => WPSmsOptionsManager::getOption('job_display_mobile_number', true) ? 'Enabled' : 'Disabled',
                    'debug' => WPSmsOptionsManager::getOption('job_display_mobile_number', true)
                ],
                'jobNewJobNotification'       => [
                    'label' => 'New Job Notification Status',
                    'value' => WPSmsOptionsManager::getOption('job_notify_status', true) ? 'Enabled' : 'Disabled',
                    'debug' => WPSmsOptionsManager::getOption('job_notify_status', true)
                ],
                'jobNotificationReceiverType' => [
                    'label' => 'New Job Notification Receiver Type',
                    'value' => WPSmsOptionsManager::getOption('job_notify_receiver', true),
                    'debug' => WPSmsOptionsManager::getOption('job_notify_receiver', true)
                ],
                'jobEmployerNotification'     => [
                    'label' => 'Employer Notification Status',
                    'value' => WPSmsOptionsManager::getOption('job_notify_employer_status', true) ? 'Enabled' : 'Disabled',
                    'debug' => WPSmsOptionsManager::getOption('job_notify_employer_status', true)
                ]
            ];

            $settings = array_merge($settings, $jobSettings);
        }

        if (class_exists('Awesome_Support')) {
            $asSettings = [
                'asIntegrationActive'        => [
                    'label' => 'Awesome Support Integration Active',
                    'value' => 'Yes',
                    'debug' => true
                ],
                'asNewTicketNotification'    => [
                    'label' => 'New Ticket Notification',
                    'value' => WPSmsOptionsManager::getOption('as_notify_open_ticket_status', true) ? 'Enabled' : 'Disabled',
                    'debug' => WPSmsOptionsManager::getOption('as_notify_open_ticket_status', true)
                ],
                'asAdminReplyNotification'   => [
                    'label' => 'Admin Reply Notification',
                    'value' => WPSmsOptionsManager::getOption('as_notify_admin_reply_ticket_status', true) ? 'Enabled' : 'Disabled',
                    'debug' => WPSmsOptionsManager::getOption('as_notify_admin_reply_ticket_status', true)
                ],
                'asUserReplyNotification'    => [
                    'label' => 'User Reply Notification',
                    'value' => WPSmsOptionsManager::getOption('as_notify_user_reply_ticket_status', true) ? 'Enabled' : 'Disabled',
                    'debug' => WPSmsOptionsManager::getOption('as_notify_user_reply_ticket_status', true)
                ],
                'asStatusUpdateNotification' => [
                    'label' => 'Status Update Notification',
                    'value' => WPSmsOptionsManager::getOption('as_notify_update_ticket_status', true) ? 'Enabled' : 'Disabled',
                    'debug' => WPSmsOptionsManager::getOption('as_notify_update_ticket_status', true)
                ],
                'asTicketCloseNotification'  => [
                    'label' => 'Ticket Close Notification',
                    'value' => WPSmsOptionsManager::getOption('as_notify_close_ticket_status', true) ? 'Enabled' : 'Disabled',
                    'debug' => WPSmsOptionsManager::getOption('as_notify_close_ticket_status', true)
                ]
            ];

            $settings = array_merge($settings, $asSettings);
        }

        if (function_exists('um_user')) {
            $umSettings = [
                'umIntegrationActive'    => [
                    'label' => 'Ultimate Member Integration Active',
                    'value' => 'Yes',
                    'debug' => true
                ],
                'umApprovalNotification' => [
                    'label' => 'User Approval Notification',
                    'value' => WPSmsOptionsManager::getOption('um_send_sms_after_approval', true) ? 'Enabled' : 'Disabled',
                    'debug' => WPSmsOptionsManager::getOption('um_send_sms_after_approval', true)
                ]
            ];

            $settings = array_merge($settings, $umSettings);
        }

        if (function_exists('is_plugin_active') && is_plugin_active('formidable/formidable.php')) {
            $formidableSettings = [
                'formidablePluginStatus'  => [
                    'label' => 'Formidable Plugin Active',
                    'value' => 'Active',
                    'debug' => true
                ],
                'formidableMetaboxStatus' => [
                    'label' => 'Formidable Metabox',
                    'value' => WPSmsOptionsManager::getOption('formidable_metabox', true) ? 'Enabled' : 'Disabled',
                    'debug' => WPSmsOptionsManager::getOption('formidable_metabox', true)
                ]
            ];

            $settings = array_merge($settings, $formidableSettings);
        }

        if (class_exists('Forminator')) {
            $forminatorSettings = [
                'forminatorPluginStatus' => [
                    'label' => 'Forminator Plugin Active',
                    'value' => 'Active',
                    'debug' => true
                ],
            ];

            $settings = array_merge($settings, $forminatorSettings);
        }

        if (function_exists('buddypress')) {
            $bpSettings = [
                'bpWelcomeSmsStatus'           => [
                    'label' => 'BuddyPress: Welcome SMS',
                    'value' => WPSmsOptionsManager::getOption('bp_welcome_notification_enable', true) ? 'Enabled' : 'Disabled',
                    'debug' => WPSmsOptionsManager::getOption('bp_welcome_notification_enable', true),
                ],
                'bpMentionNotification'        => [
                    'label' => 'BuddyPress: Mention Alerts',
                    'value' => WPSmsOptionsManager::getOption('bp_mention_enable', true) ? 'Enabled' : 'Disabled',
                    'debug' => WPSmsOptionsManager::getOption('bp_mention_enable', true),
                ],
                'bpMentionMessageBody'         => [
                    'label' => 'BuddyPress: Mention Message Body',
                    'value' => !empty(WPSmsOptionsManager::getOption('bp_mention_message', true)) ? 'Customized' : 'Empty',
                    'debug' => 'Hidden for privacy',
                ],
                'bpPrivateMessageNotification' => [
                    'label' => 'BuddyPress: Private Messages',
                    'value' => WPSmsOptionsManager::getOption('bp_private_message_enable', true) ? 'Enabled' : 'Disabled',
                    'debug' => WPSmsOptionsManager::getOption('bp_private_message_enable', true),
                ],
                'bpActivityReplyNotification'  => [
                    'label' => 'BuddyPress: Activity Replies',
                    'value' => WPSmsOptionsManager::getOption('bp_comments_activity_enable', true) ? 'Enabled' : 'Disabled',
                    'debug' => WPSmsOptionsManager::getOption('bp_comments_activity_enable', true),
                ],
                'bpActivityReplyMessageBody'   => [
                    'label' => 'BuddyPress: Activity Reply Message',
                    'value' => !empty(WPSmsOptionsManager::getOption('bp_comments_activity_message', true)) ? 'Customized' : 'Empty',
                    'debug' => 'Hidden for privacy',
                ],
                'bpCommentReplyNotification'   => [
                    'label' => 'BuddyPress: Comment Replies',
                    'value' => WPSmsOptionsManager::getOption('bp_comments_reply_enable', true) ? 'Enabled' : 'Disabled',
                    'debug' => WPSmsOptionsManager::getOption('bp_comments_reply_enable', true),
                ]
            ];

            $settings = array_merge($settings, $bpSettings);
        }

        if (class_exists('WooCommerce')) {
            $wcSettings = [
                'wcMetaBoxEnable'                       => [
                    'label' => 'Order Meta Box Status',
                    'value' => WPSmsOptionsManager::getOption('wc_meta_box_enable', true) ? 'Enabled' : 'Disabled',
                    'debug' => WPSmsOptionsManager::getOption('wc_meta_box_enable', true),
                ],
                'wcNotifyProductEnable'                 => [
                    'label' => 'New Product Notification Status',
                    'value' => WPSmsOptionsManager::getOption('wc_notify_product_enable', true) ? 'Enabled' : 'Disabled',
                    'debug' => WPSmsOptionsManager::getOption('wc_notify_product_enable', true),
                ],
                'wcNotifyOrderEnable'                   => [
                    'label' => 'New Order Notification Status',
                    'value' => WPSmsOptionsManager::getOption('wc_notify_order_enable', true) ? 'Enabled' : 'Disabled',
                    'debug' => WPSmsOptionsManager::getOption('wc_notify_order_enable', true),
                ],
                'wcNotifyCustomerEnable'                => [
                    'label' => 'Customer Order Notification Status',
                    'value' => WPSmsOptionsManager::getOption('wc_notify_customer_enable', true) ? 'Enabled' : 'Disabled',
                    'debug' => WPSmsOptionsManager::getOption('wc_notify_customer_enable', true),
                ],
                'wcNotifyStockEnable'                   => [
                    'label' => 'Low Stock Notification Status',
                    'value' => WPSmsOptionsManager::getOption('wc_notify_stock_enable', true) ? 'Enabled' : 'Disabled',
                    'debug' => WPSmsOptionsManager::getOption('wc_notify_stock_enable', true),
                ],
                'wcCheckoutConfirmationCheckboxEnabled' => [
                    'label' => 'Checkout Confirmation Checkbox Status',
                    'value' => WPSmsOptionsManager::getOption('wc_checkout_confirmation_checkbox_enabled', true) ? 'Enabled' : 'Disabled',
                    'debug' => WPSmsOptionsManager::getOption('wc_checkout_confirmation_checkbox_enabled', true),
                ],
                'wcNotifyStatusEnable'                  => [
                    'label' => 'Order Status Notification Status',
                    'value' => WPSmsOptionsManager::getOption('wc_notify_status_enable', true) ? 'Enabled' : 'Disabled',
                    'debug' => WPSmsOptionsManager::getOption('wc_notify_status_enable', true),
                ],
                'wcNotifyByStatusEnable'                => [
                    'label' => 'Specific Order Status Notification Status',
                    'value' => WPSmsOptionsManager::getOption('wc_notify_by_status_enable', true) ? 'Enabled' : 'Disabled',
                    'debug' => WPSmsOptionsManager::getOption('wc_notify_by_status_enable', true),
                ],
                'wcNotifyByStatusContentCount'          => [
                    'label' => 'Number of Configured Status Notifications',
                    'value' => is_array(WPSmsOptionsManager::getOption('wc_notify_by_status_content', true)) ? count(WPSmsOptionsManager::getOption('wc_notify_by_status_content', true)) : 0,
                    'debug' => is_array(WPSmsOptionsManager::getOption('wc_notify_by_status_content', true)) ? count(WPSmsOptionsManager::getOption('wc_notify_by_status_content', true)) : 0,
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

            $bookingCalendarSettings[$alias] = array(
                'label' => esc_html($label),
                'value' => $value,
                'debug' => $raw,
            );
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

            $bookingPressSettings[$alias] = array(
                'label' => esc_html($label),
                'value' => $value,
                'debug' => $raw,
            );
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

            $wooAppointmentsSettings[$alias] = array(
                'label' => esc_html($label),
                'value' => $value,
                'debug' => $raw,
            );
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
        $integrations = [];

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

            $fluentCrmSettings[$alias] = [
                'label' => esc_html($label),
                'value' => $value,
                'debug' => $raw,
            ];
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

            $fluentSupportSettings[$alias] = [
                'label' => esc_html($label),
                'value' => $value,
                'debug' => $raw,
            ];
        }

        $integrations['fluent_crm']     = $fluentCrmSettings;
        $integrations['fluent_support'] = $fluentSupportSettings;

        return $integrations;
    }

    /**
     * Retrieves settings for Memberships integration in structured format.
     *
     * @return array
     */
    public static function getMembershipsIntegrationSetting()
    {
        $integrations = [];

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

            $paidSettings[$alias] = [
                'label' => esc_html($label),
                'value' => $value,
                'debug' => $raw,
            ];
        }

        $integrations['paid_memberships_pro'] = $paidSettings;

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

            $simpleSettings[$alias] = [
                'label' => esc_html($label),
                'value' => $value,
                'debug' => $raw,
            ];
        }

        $integrations['simple_membership'] = $simpleSettings;

        return $integrations;
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
                        $d = (int)$raw['days'];
                        $parts[] = $d . ' ' . ($d === 1 ? 'day' : 'days');
                    }
                    if (!empty($raw['hours']) && $raw['hours'] !== '0') {
                        $h = (int)$raw['hours'];
                        $parts[] = $h . ' ' . ($h === 1 ? 'hour' : 'hours');
                    }
                    if (!empty($raw['minutes']) && $raw['minutes'] !== '0') {
                        $m = (int)$raw['minutes'];
                        $parts[] = $m . ' ' . ($m === 1 ? 'minute' : 'minutes');
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
