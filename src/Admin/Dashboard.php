<?php

namespace WP_SMS\Admin;

use WP_SMS\Components\Singleton;
use WP_SMS\Option;
use WP_SMS\Newsletter;
use WP_SMS\Admin\LicenseManagement\LicenseHelper;
use WP_SMS\Admin\LicenseManagement\Plugin\PluginHelper;
use WP_SMS\Admin\LicenseManagement\Plugin\PluginHandler;
use WP_SMS\Admin\ModalHandler\Modal;
use WP_SMS\Notice\NoticeManager;
use WP_SMS\Utils\OptionUtil;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Dashboard - React-based admin interface for all WP-SMS pages
 *
 * This class provides the main dashboard React application that handles:
 * - Send SMS
 * - Outbox
 * - Subscribers
 * - Groups
 * - Privacy (GDPR)
 * - Settings (all existing settings pages)
 */
class Dashboard extends Singleton
{
    /**
     * Database instance
     *
     * @var \wpdb
     */
    private $db;

    /**
     * Table prefix
     *
     * @var string
     */
    private $tb_prefix;

    /**
     * Sensitive fields that should be masked before sending to frontend
     *
     * @var array
     */
    private $sensitiveFields = [
        'gateway_password',
        'gateway_key',
    ];

    /**
     * Initialize the admin page
     */
    public function init()
    {
        global $wpdb;
        $this->db = $wpdb;
        $this->tb_prefix = $wpdb->prefix;

        // Asset enqueueing is handled by DashboardHandler via AssetsFactory.
    }

    /**
     * Static instance method required by MenuUtil
     *
     * @return static
     */
    public static function instance()
    {
        return parent::getInstance();
    }

    /**
     * Render the admin page
     */
    public function view()
    {
        // Hide WordPress admin notices, footer, and add full-width container
        // Note: Height constraint is set here (not in external CSS) because the body class
        // that WordPress generates for admin pages may be missing in RTL mode.
        echo '<style>
            .wrap { max-width: none !important; margin: 0 !important; padding: 0 !important; }
            .wrap > h1:first-child { display: none; }
            .notice, .updated, .error, .is-dismissible { display: none !important; }
            #wpfooter { display: none !important; }
            #wpcontent { padding: 0 !important; }
            #wpbody-content { padding: 0 !important; }
            #wpbody-content > .clear { display: none !important; }
            /* Hide chatbox by default - React Preview button will toggle it */
            .wpsms-chatbox { display: none !important; }
            .wpsms-chatbox.wpsms-chatbox--visible { display: block !important; }
            /* Fixed positioning - dashboard stays in viewport regardless of page scroll */
            #wpsms-settings-root {
                position: fixed !important;
                top: 32px !important;
                left: 160px !important;
                right: 0 !important;
                bottom: 0 !important;
                overflow: hidden !important;
                display: flex !important;
                flex-direction: column !important;
            }
            /* Collapsed sidebar */
            body.folded #wpsms-settings-root {
                left: 36px !important;
            }
            /* Auto-fold at 960px (must come before the 782px rule so mobile wins) */
            @media screen and (max-width: 960px) {
                body:not(.folded) #wpsms-settings-root {
                    left: 36px !important;
                }
            }
            /* Mobile - sidebar is hidden/overlay (repeat high-specificity selector to override 960px rule) */
            @media screen and (max-width: 782px) {
                #wpsms-settings-root,
                body:not(.folded) #wpsms-settings-root {
                    top: 46px !important;
                    left: 0 !important;
                }
            }
            /* RTL support - sidebar is on the right */
            body.rtl #wpsms-settings-root {
                left: 0 !important;
                right: 160px !important;
            }
            body.rtl.folded #wpsms-settings-root {
                right: 36px !important;
            }
            @media screen and (max-width: 960px) {
                body.rtl:not(.folded) #wpsms-settings-root {
                    right: 36px !important;
                }
            }
            @media screen and (max-width: 782px) {
                body.rtl #wpsms-settings-root,
                body.rtl:not(.folded) #wpsms-settings-root {
                    right: 0 !important;
                }
            }
        </style>';
        echo sprintf(
            '<div id="wpsms-settings-root" class="wpsms-settings-app" dir="%s"></div>',
            is_rtl() ? 'rtl' : 'ltr'
        );
    }

    /**
     * Get localized data for the React app.
     * Public so DashboardHandler can access it.
     *
     * @return array
     */
    public function getLocalizedData()
    {
        return [
            'apiUrl'        => rest_url('wpsms/v1/'),
            'nonce'         => wp_create_nonce('wp_rest'),
            'ajaxUrl'       => admin_url('admin-ajax.php'),
            'ajaxUrls'      => [
                'recipientCounts' => \WP_SMS\Controller\RecipientCountsAjax::url(),
            ],
            'ajaxNonces'    => [
                'numberMigration' => wp_create_nonce('wp_sms_number_migration'),
                'testGateway'     => wp_create_nonce('wp_sms_test_gateway'),
            ],
            'settings'      => $this->maskSensitiveSettings($this->getSettingsWithDefaults()),
            'proSettings'   => $this->maskSensitiveSettings(Option::getOptions(true)),
            'addons'        => $this->getActiveAddons(),
            'addonDashboardSupport' => $this->getAddonDashboardSupport(),
            'gateway'       => $this->getGatewayCapabilities(),
            'adminUrl'      => admin_url(),
            'siteUrl'       => site_url(),
            'timezone'      => wp_timezone_string(),
            'dateFormat'    => get_option('date_format', 'F j, Y'),
            'timeFormat'    => get_option('time_format', 'g:i a'),
            'version'       => WP_SMS_VERSION,
            // Dynamic data for multi-select fields
            'countries'     => $this->getCountries(),              // Full country objects for filters
            'countriesByCode' => $this->getCountriesByCode(),      // ISO codes for countryselect multiselects
            'countriesByDialCode' => $this->getCountriesByDialCode(), // Dial codes for select/multiselect
            'postTypes'     => $this->getPostTypes(),
            'taxonomies'    => $this->getTaxonomiesWithTerms(),
            'roles'         => $this->getUserRoles(),
            'groups'        => $this->getNewsletterGroups(),
            // Add-on settings schema for dynamic rendering
            'addonSettings' => $this->getAddonSettingsSchema(),
            // Add-on option values
            'addonValues'   => $this->getAddonOptionValues(),
            // Third-party plugin status for integrations
            'thirdPartyPlugins' => $this->getThirdPartyPluginStatus(),
            // Forminator forms data for dynamic settings
            'forminatorForms' => $this->getForminatorFormsData(),
            // Gravity Forms data for dynamic settings (Pro)
            'gravityForms' => $this->getGravityFormsData(),
            // Quform data for dynamic settings (Pro)
            'quformForms' => $this->getQuformFormsData(),
            // Fluent Forms data for dynamic settings (Fluent add-on)
            'fluentForms' => apply_filters('wpsms_admin_localized_fluent_forms_data', ['isActive' => false, 'forms' => []]),
            // Extended data for dashboard pages
            'stats'         => $this->getStats(),
            'capabilities'  => $this->getUserCapabilities(),
            'features'      => $this->getFeatureFlags(),
            // License data for header badge
            'license'       => $this->getLicenseData(),
            // Additional recipient types for Send SMS page (filterable by add-ons)
            'additionalRecipientTypes' => $this->getAdditionalRecipientTypes(),
            // Additional mobile field sources from add-ons (e.g., PMPro phone fields)
            'mobileFieldSources' => apply_filters('wpsms_mobile_field_sources', []),
            // Admin notices for React dashboard banner
            'adminNotices' => $this->getAdminNotices(),
            // Plugin base URL for asset references
            'pluginUrl'    => WP_SMS_URL,
            // All-in-One modal data
            'aioModal'     => $this->getAioModalData(),
        ];
    }

    /**
     * Collect active admin notices for the React dashboard
     *
     * Reads static notices registered by NoticeManager and the anonymous data
     * opt-in notice.  Each notice is normalized to a standard shape that the
     * React AdminNotices component can render.
     *
     * @return array
     */
    private function getAdminNotices()
    {
        $result = [];

        // 1. Static notices from NoticeManager
        $manager          = NoticeManager::getInstance();
        $registeredNotices = $manager->getRegisteredNotices();
        $dismissedStatic  = get_option('wpsms_notices', []);

        if (!is_array($dismissedStatic)) {
            $dismissedStatic = [];
        }

        foreach ($registeredNotices as $id => $notice) {
            // Already dismissed
            if (!empty($dismissedStatic[$id])) {
                continue;
            }

            // Skip page-conditional notices that target non-dashboard pages
            if (!empty($notice['url']) && strpos($notice['url'], 'page=wsms') === false) {
                continue;
            }

            // Extract tab from notice URL for page-conditional rendering in the SPA
            $showOnTab = null;
            if (!empty($notice['url'])) {
                $parsed = [];
                parse_str(wp_parse_url($notice['url'], PHP_URL_QUERY) ?: '', $parsed);
                if (!empty($parsed['tab'])) {
                    $showOnTab = sanitize_text_field($parsed['tab']);
                }
            }

            $isActivation = strpos($id, 'wp_sms_') === 0 && strpos($id, '_activation') !== false;

            // Strip inline action/dismiss links from the message — the React
            // component renders its own dismiss control and action buttons.
            $message = $notice['message'];
            if ($isActivation) {
                // Activation notices embed buttons as <a> tags inside a wrapper span;
                // strip the entire action wrapper and any remaining links.
                $message = preg_replace('/<span[^>]*wpsms-admin-notice__action[^>]*>.*?<\/span>/is', '', $message);
            }
            $message = preg_replace('/<a[^>]*(?:wpsms_dismiss|class=["\'].*?button)[^>]*>.*?<\/a>/is', '', $message);
            $message = wp_kses_post(trim(strip_tags($message, '<strong><em><br><b><i><a>')));

            // Activation notices become action-type with a "Launch Setup Wizard" button
            if ($isActivation) {
                $result[] = [
                    'id'           => $id,
                    'type'         => 'action',
                    'variant'      => 'info',
                    'message'      => $message,
                    'title'        => null,
                    'dismissible'  => true,
                    'dismissStore' => 'static',
                    'link'         => null,
                    'showOnTab'    => null,
                    'actions'      => [
                        [
                            'label'    => __('Launch Setup Wizard', 'wp-sms'),
                            'navigate' => 'wizard',
                        ],
                    ],
                ];
                continue;
            }

            $result[] = [
                'id'           => $id,
                'type'         => 'simple',
                'variant'      => 'warning',
                'message'      => $message,
                'title'        => null,
                'dismissible'  => (bool) $notice['dismiss'],
                'dismissStore' => 'static',
                'link'         => !empty($notice['url']) ? admin_url($notice['url']) : null,
                'showOnTab'    => $showOnTab,
                'actions'      => [],
            ];
        }

        // 2. Anonymous data sharing notice
        $installationTime    = get_option('wp_sms_installation_time');
        $dismissedHandler    = get_option('wp_sms_dismissed_notices', []);

        if (!is_array($dismissedHandler)) {
            $dismissedHandler = [];
        }

        if (
            current_user_can('manage_options') &&
            !OptionUtil::get('share_anonymous_data') &&
            !in_array('share_anonymous_data', $dismissedHandler) &&
            $installationTime &&
            (time() > $installationTime + 7 * DAY_IN_SECONDS)
        ) {
            $result[] = [
                'id'           => 'share_anonymous_data',
                'type'         => 'action',
                'variant'      => 'info',
                'message'      => __('Help us improve by sharing anonymous usage data. No personal or sensitive information is collected.', 'wp-sms'),
                'title'        => __('Help Us Improve WSMS!', 'wp-sms'),
                'dismissible'  => true,
                'dismissStore' => 'handler',
                'link'         => null,
                'showOnTab'    => null,
                'actions'      => [
                    [
                        'label'       => __('Enable Share Anonymous Data', 'wp-sms'),
                        'action_type' => 'update_option',
                        'option'      => 'share_anonymous_data',
                        'value'       => '1',
                    ],
                    [
                        'label' => __('Learn More', 'wp-sms'),
                        'url'   => 'https://wsms.io/docs/share-anonymous-data/?utm_source=wp-sms&utm_medium=link&utm_campaign=doc',
                    ],
                ],
            ];
        }

        // 3a. Default Country Code missing — only when international input is OFF. Sites that
        // exclusively use the flag-picker widget don't need a default CC and should not be
        // pestered. Without it, server-side normalization for local-format input cannot succeed,
        // and admins typically only discover the misconfiguration when the migration wizard
        // refuses to run.
        $internationalInput = (bool) OptionUtil::get('international_mobile');
        $defaultCountryCode = OptionUtil::get('mobile_county_code');

        if (
            current_user_can('manage_options') &&
            !$internationalInput &&
            (empty($defaultCountryCode) || $defaultCountryCode === '0') &&
            !in_array('default_country_code_missing', $dismissedHandler)
        ) {
            $result[] = [
                'id'           => 'default_country_code_missing',
                'type'         => 'action',
                'variant'      => 'warning',
                'message'      => __('Set your default country so we know how to interpret phone numbers without an international prefix. Without it, locally-formatted numbers cannot be normalized and SMS delivery may fail.', 'wp-sms'),
                'title'        => __('Default Country Code is not configured', 'wp-sms'),
                'dismissible'  => false,
                'dismissStore' => 'handler',
                'link'         => null,
                'showOnTab'    => null,
                'actions'      => [
                    [
                        'label'    => __('Configure now', 'wp-sms'),
                        'navigate' => 'phone',
                    ],
                ],
            ];
        }

        // 3b. Recent phone number normalization failures — converts silent integration
        // failures into something admins can actually act on. Hidden when the failure log
        // is empty so we don't add noise on healthy sites. The "dismiss" button (rendered
        // automatically by the React notice component) clears the underlying log via a hook
        // in the dismissNotice REST handler — see AdminNoticesApi::dismissNotice.
        $recentFailures = \WP_SMS\Helper::getRecentNormalizationFailures();
        if (
            current_user_can('manage_options') &&
            !empty($recentFailures)
        ) {
            $preview = implode('<br>', array_map(
                function ($failure) {
                    // renderPhoneHtml wraps in <bdi> so RTL admin layouts keep the leading
                    // `+` on the left of the digits.
                    return sprintf(
                        '<code>%s</code> (%s)',
                        \WP_SMS\Helper::renderPhoneHtml($failure['original_value']),
                        esc_html($failure['source'])
                    );
                },
                array_slice($recentFailures, 0, 5)
            ));

            $result[] = [
                'id'           => 'recent_phone_failures',
                'type'         => 'simple',
                'variant'      => 'warning',
                /* translators: 1: count, 2: HTML preview list */
                'message'      => sprintf(
                    _n(
                        '%1$d phone number could not be normalized recently and may not have been delivered:<br>%2$s<br><em>Dismiss this notice to clear the log. Captured values are stored only in WordPress options on this site.</em>',
                        '%1$d phone numbers could not be normalized recently and may not have been delivered:<br>%2$s<br><em>Dismiss this notice to clear the log. Captured values are stored only in WordPress options on this site.</em>',
                        count($recentFailures),
                        'wp-sms'
                    ),
                    count($recentFailures),
                    $preview
                ),
                'title'        => __('Recent phone normalization failures', 'wp-sms'),
                'dismissible'  => true,
                'dismissStore' => 'handler',
                'link'         => null,
                'showOnTab'    => null,
                'actions'      => [],
            ];
        }

        // 3. Number migration notice — show if there are local numbers without country code.
        //
        // Dismissal is time-bounded: if the admin dismissed the notice more than 7 days ago
        // AND there's still local-format data, the notice comes back. This catches the case
        // where the admin imported a CSV with local numbers after dismissing, or was just
        // never going to get around to it and needs a nudge.
        if (current_user_can('manage_options')) {
            $isDismissed      = in_array('number_migration', $dismissedHandler, true);
            $dismissedAt      = (int) get_option('wpsms_number_migration_notice_dismissed_at', 0);
            $sevenDaysSeconds = 7 * DAY_IN_SECONDS;

            if ($isDismissed && $dismissedAt > 0 && (time() - $dismissedAt) > $sevenDaysSeconds) {
                // Re-show: drop it from the dismissed list and clear the timestamp.
                $dismissedHandler = array_values(array_diff($dismissedHandler, ['number_migration']));
                update_option('wp_sms_dismissed_notices', $dismissedHandler);
                delete_option('wpsms_number_migration_notice_dismissed_at');
                $isDismissed = false;
            }

            if (!$isDismissed) {
                // Quick check: any subscriber numbers without + prefix? (cached for 1 hour)
                $localNumberCount = get_transient('wpsms_local_number_count');
                if ($localNumberCount === false) {
                    global $wpdb;
                    $localNumberCount = (int) $wpdb->get_var(
                        "SELECT COUNT(*) FROM {$wpdb->prefix}sms_subscribes WHERE mobile NOT LIKE '+%'"
                    );
                    set_transient('wpsms_local_number_count', $localNumberCount, HOUR_IN_SECONDS);
                }

                if ($localNumberCount > 0) {
                    $result[] = [
                        'id'           => 'number_migration',
                        'type'         => 'action',
                        'variant'      => 'info',
                        /* translators: %d: number of affected phone numbers */
                        'title'        => sprintf(
                            _n(
                                'Improve delivery reliability for %d phone number',
                                'Improve delivery reliability for %d phone numbers',
                                $localNumberCount,
                                'wp-sms'
                            ),
                            $localNumberCount
                        ),
                        'message'      => __(
                            'Some of your stored numbers are in local format, which can cause delivery failures on modern SMS gateways. We can add the country code for you — it takes under a minute, includes a preview step, and everything is backed up and reversible.',
                            'wp-sms'
                        ),
                        'dismissible'  => true,
                        'dismissStore' => 'handler',
                        'link'         => null,
                        'showOnTab'    => null,
                        'actions'      => [
                            [
                                'label'    => __('Review and fix', 'wp-sms'),
                                'navigate' => 'migration-wizard',
                            ],
                        ],
                    ];
                }
            }
        }

        /**
         * Filter admin notices shown in the React dashboard.
         *
         * @param array $result Normalized notice array
         */
        $result = apply_filters('wpsms_react_admin_notices', $result);

        // Sanitize all messages after the filter to prevent XSS from third-party add-ons
        foreach ($result as &$notice) {
            if (!empty($notice['message'])) {
                $notice['message'] = wp_kses_post($notice['message']);
            }
        }

        return $result;
    }

    /**
     * Get dashboard statistics
     *
     * @return array
     */
    private function getStats()
    {
        return [
            'subscribers' => [
                'total'  => $this->getSubscriberCount(),
                'active' => $this->getSubscriberCount(true),
            ],
            'groups'  => $this->getGroupCount(),
            'outbox'  => [
                'total'   => $this->getOutboxCount(),
                'success' => $this->getOutboxCount('success'),
                'failed'  => $this->getOutboxCount('failed'),
            ],
            'credit'  => $this->getGatewayCredit(),
        ];
    }

    /**
     * Get subscriber count
     *
     * @param bool $activeOnly
     * @return int
     */
    private function getSubscriberCount($activeOnly = false)
    {
        $table = $this->tb_prefix . 'sms_subscribes';
        $where = $activeOnly ? "WHERE status = '1'" : '';
        return (int) $this->db->get_var("SELECT COUNT(*) FROM {$table} {$where}");
    }

    /**
     * Get group count
     *
     * @return int
     */
    private function getGroupCount()
    {
        $table = $this->tb_prefix . 'sms_subscribes_group';
        return (int) $this->db->get_var("SELECT COUNT(*) FROM {$table}");
    }

    /**
     * Get outbox message count
     *
     * @param string|null $status
     * @return int
     */
    private function getOutboxCount($status = null)
    {
        $table = $this->tb_prefix . 'sms_send';
        $where = '';
        if ($status) {
            $where = $this->db->prepare("WHERE status = %s", $status);
        }
        return (int) $this->db->get_var("SELECT COUNT(*) FROM {$table} {$where}");
    }

    /**
     * Get gateway credit balance
     *
     * @return mixed
     */
    private function getGatewayCredit()
    {
        global $sms;
        if (isset($sms) && method_exists($sms, 'GetCredit')) {
            try {
                return $sms->GetCredit();
            } catch (\Exception $e) {
                return null;
            }
        }
        return null;
    }

    /**
     * Get active gateway capabilities
     *
     * @return array
     */
    private function getGatewayCapabilities()
    {
        try {
            global $sms;

            // Initialize gateway if not already done
            if (!$sms || !is_object($sms)) {
                if (function_exists('wp_sms_initial_gateway')) {
                    $sms = wp_sms_initial_gateway();
                }
            }

            if (!$sms || !is_object($sms)) {
                return [
                    'flash'           => '',
                    'supportMedia'    => false,
                    'supportIncoming' => false,
                    'bulk_send'       => false,
                    'validateNumber'  => '',
                    'from'            => '',
                    'gatewayFields'   => [],
                    'help'            => '',
                    'documentUrl'     => '',
                ];
            }

            // Build gateway fields array
            $gatewayFields = [];
            if (!empty($sms->gatewayFields) && is_array($sms->gatewayFields)) {
                foreach ($sms->gatewayFields as $key => $field) {
                    if (!is_array($field)) {
                        continue;
                    }
                    $gatewayFields[$key] = [
                        'id'          => $field['id'] ?? '',
                        'name'        => $field['name'] ?? '',
                        'desc'        => $field['desc'] ?? '',
                        'placeholder' => $field['place_holder'] ?? '',
                        'type'        => $field['type'] ?? 'text',
                        'options'     => $field['options'] ?? [],
                        'className'   => $field['className'] ?? '',
                    ];
                }
            }

            // Sanitize help text
            $help = '';
            if (!empty($sms->help) && $sms->help !== false) {
                $help = wp_kses_post($sms->help);
            }

            return [
                'flash'           => $sms->flash ?? '',
                'supportMedia'    => $sms->supportMedia ?? false,
                'supportIncoming' => $sms->supportIncoming ?? false,
                'bulk_send'       => $sms->bulk_send ?? false,
                'validateNumber'  => $sms->validateNumber ?? '',
                'from'            => $sms->from ?? '',
                'gatewayFields'   => $gatewayFields,
                'help'            => $help,
                'documentUrl'     => is_string($sms->documentUrl ?? '') ? ($sms->documentUrl ?? '') : '',
            ];
        } catch (\Exception $e) {
            return [
                'flash'           => '',
                'supportMedia'    => false,
                'supportIncoming' => false,
                'bulk_send'       => false,
                'validateNumber'  => '',
                'from'            => '',
                'gatewayFields'   => [],
                'help'            => '',
                'documentUrl'     => '',
            ];
        }
    }

    /**
     * Get user capabilities for the current user
     *
     * @return array
     */
    private function getUserCapabilities()
    {
        return [
            'canSendSms'          => current_user_can('wpsms_sendsms'),
            'canViewOutbox'       => current_user_can('wpsms_outbox'),
            'canViewInbox'        => current_user_can('wpsms_inbox'),
            'canManageSubscribers' => current_user_can('wpsms_subscribers'),
            'canManageSettings'   => current_user_can('wpsms_setting'),
            'canManageOptions'    => current_user_can('manage_options'),
        ];
    }

    /**
     * Get license data for the header badge
     *
     * Follows the same logic as the legacy PHP template in:
     * /includes/templates/admin/partials/license-status.php
     *
     * @return array
     */
    private function getLicenseData()
    {
        $isPremium      = (bool) LicenseHelper::isPremiumLicenseAvailable();
        $hasValidLicense = LicenseHelper::isValidLicenseAvailable();
        $licensedCount  = count(PluginHelper::getLicensedPlugins());
        $totalPlugins   = count(PluginHelper::$plugins);

        return [
            'isPremium'      => $isPremium,
            'hasValidLicense' => $hasValidLicense,
            'licensedCount'  => $licensedCount,
            'totalPlugins'   => $totalPlugins,
        ];
    }

    /**
     * Get All-in-One modal data for the React dashboard
     *
     * @return array
     */
    private function getAioModalData()
    {
        $pluginHandler = new PluginHandler();
        $isPremium     = (bool) LicenseHelper::isPremiumLicenseAvailable();

        $addons = [];
        foreach (PluginHelper::$plugins as $slug => $title) {
            $addons[] = [
                'slug'        => $slug,
                'title'       => $title,
                'isActive'    => $pluginHandler->isPluginActive($slug),
                'isInstalled' => $pluginHandler->isPluginInstalled($slug),
                'hasLicense'  => LicenseHelper::isPluginLicenseValid($slug),
            ];
        }

        return [
            'seen'      => Modal::hasBeenSeen('welcome-premium'),
            'isPremium' => $isPremium,
            'addons'    => $addons,
        ];
    }

    /**
     * Get feature flags
     *
     * @return array
     */
    private function getFeatureFlags()
    {
        $isProActive = is_plugin_active('wp-sms-pro/wp-sms-pro.php');

        // Check if wizard was completed (uses same option as legacy wizard)
        $activationNoticeShown = get_option('wp_sms_wp-sms-onboarding_activation_notice_shown', false);

        return [
            'gdprEnabled'           => Option::getOption('gdpr_compliance') === '1',
            'twoWayEnabled'         => is_plugin_active('wp-sms-two-way/wp-sms-two-way.php'),
            'scheduledSms'          => class_exists('WP_SMS\Pro\Scheduled'),
            'isProActive'           => $isProActive,
            'hasProAddon'           => $isProActive, // Alias for sidebar navigation
            'isWooActive'           => class_exists('WooCommerce'),
            'isBuddyPressActive'    => class_exists('BuddyPress'),
            // Wizard completion flag
            'wizardCompleted'       => (bool) $activationNoticeShown,
        ];
    }

    /**
     * Get additional recipient types for Send SMS page
     *
     * This allows add-ons to register additional recipient types like WooCommerce Customers
     * and BuddyPress Users via the 'wpsms_additional_recipient_types' filter.
     *
     * Each type should have:
     * - id: Unique identifier (e.g., 'wooCustomers', 'buddyPressUsers')
     * - label: Display label
     * - icon: Icon name from lucide-react (e.g., 'ShoppingCart', 'UserCircle')
     * - apiType: The type parameter for the RecipientCountsAjax endpoint (e.g., 'wc-customers', 'bp-users')
     * - isActive: Whether the required plugin is active
     *
     * @return array
     */
    private function getAdditionalRecipientTypes()
    {
        /**
         * Filter to register additional recipient types for Send SMS page
         *
         * @param array $types Array of recipient type definitions
         */
        return apply_filters('wpsms_additional_recipient_types', []);
    }

    /**
     * Get settings with default values applied
     *
     * Extracts defaults from the legacy settings registration (get_registered_settings)
     * and applies them for keys that don't exist in stored settings.
     * Also normalizes numeric values to strings for consistent React comparison.
     *
     * @return array
     */
    private function getSettingsWithDefaults()
    {
        $storedSettings = Option::getOptions();

        // Get defaults from the registered settings (same source as legacy page)
        $defaults = $this->getRegisteredDefaults();

        // Apply defaults only for keys that don't exist in stored settings
        foreach ($defaults as $key => $defaultValue) {
            if (!array_key_exists($key, $storedSettings)) {
                $storedSettings[$key] = $defaultValue;
            }
        }

        // Normalize all numeric values to strings for consistent React comparison
        // Legacy PHP may store integer 1, but React expects string '1'
        foreach ($storedSettings as $key => $value) {
            if (is_int($value) || is_float($value)) {
                $storedSettings[$key] = (string) $value;
            }
        }

        return $storedSettings;
    }

    /**
     * Extract default values from the legacy registered settings
     *
     * Reads the 'std' (standard/default) values from class-wpsms-settings.php
     * to ensure React interface uses the same defaults as the legacy page.
     *
     * @return array Key-value pairs of setting IDs and their default values
     */
    private function getRegisteredDefaults()
    {
        $defaults = [];

        // Instantiate the legacy settings class to get registered settings
        if (!class_exists('WP_SMS\\Admin\\Settings\\Settings')) {
            return $defaults;
        }

        $settingsInstance = new \WP_SMS\Admin\Settings\Settings();

        // Use reflection to call the protected method, or check if it's public
        if (!method_exists($settingsInstance, 'get_registered_settings')) {
            return $defaults;
        }

        $registeredSettings = $settingsInstance->get_registered_settings();

        // Extract 'std' values from all tabs and fields
        foreach ($registeredSettings as $tab => $fields) {
            if (!is_array($fields)) {
                continue;
            }

            foreach ($fields as $fieldKey => $field) {
                if (!is_array($field)) {
                    continue;
                }

                // Get the field ID (either from 'id' key or the array key itself)
                $fieldId = isset($field['id']) ? $field['id'] : $fieldKey;

                // If 'std' (default) is set, add it to defaults
                if (isset($field['std']) && $field['std'] !== '') {
                    $defaults[$fieldId] = $field['std'];
                }
            }
        }

        return $defaults;
    }

    /**
     * Mask sensitive fields in settings array
     *
     * @param array $settings
     * @return array
     */
    private function maskSensitiveSettings($settings)
    {
        if (!is_array($settings)) {
            return $settings;
        }

        foreach ($this->sensitiveFields as $field) {
            if (isset($settings[$field]) && !empty($settings[$field])) {
                $settings[$field] = '••••••••';
            }
        }

        return $settings;
    }

    /**
     * Get full countries list as array of objects
     *
     * Returns the full countries array with all properties (code, name, dialCode, etc.)
     * for use in Subscribers.jsx filtering and other components.
     *
     * @return array Array of country objects with code, name, dialCode, etc.
     */
    private function getCountries()
    {
        if (!function_exists('wp_sms_countries')) {
            return [];
        }

        // Return full countries array for components that need the full data
        // (e.g., Subscribers.jsx needs code and name for filtering)
        return wp_sms_countries()->getCountries();
    }

    /**
     * Get countries keyed by ISO code for countryselect multiselects
     *
     * Returns countries keyed by ISO country code (e.g., 'US', 'GB')
     * to match the legacy countryselect_callback which stores country codes.
     *
     * @return array Format: ['US' => 'United States', 'GB' => 'United Kingdom', ...]
     */
    private function getCountriesByCode()
    {
        if (!function_exists('wp_sms_countries')) {
            return [];
        }

        // Legacy countryselect_callback uses getCountries() and stores $country['code']
        $countries = wp_sms_countries()->getCountries();
        $result = [];

        foreach ($countries as $country) {
            if (isset($country['code']) && isset($country['name'])) {
                $result[$country['code']] = $country['name'];
            }
        }

        return $result;
    }

    /**
     * Get countries list keyed by dial code for single select fields
     *
     * Returns countries keyed by dial code (e.g., '+1', '+44')
     * to match the legacy mobile_county_code select which uses getCountriesMerged().
     *
     * @return array Format: ['+1' => 'United States (+1)', '+44' => 'United Kingdom (+44)', ...]
     */
    private function getCountriesByDialCode()
    {
        if (!function_exists('wp_sms_countries')) {
            return [];
        }

        // Legacy uses getCountriesMerged() which returns ['dialCode' => 'fullInfo', ...]
        return wp_sms_countries()->getCountriesMerged();
    }

    /**
     * Get post types with show_ui enabled
     *
     * @return array
     */
    private function getPostTypes()
    {
        $postTypes = get_post_types(['show_ui' => true], 'objects');
        $result = [];

        // Exclude list matching legacy settings
        $exclude = [
            'attachment',
            'acf-field',
            'acf-field-group',
            'vc4_templates',
            'vc_grid_item',
            'acf',
            'wpcf7_contact_form',
            'shop_order',
            'shop_coupon',
        ];

        foreach ($postTypes as $postType) {
            if (in_array($postType->name, $exclude)) {
                continue;
            }
            if ($postType->_builtin && !$postType->public) {
                continue;
            }
            // Use legacy format: capability|slug => label
            // This matches the format stored by legacy settings
            $key = $postType->cap->publish_posts . '|' . $postType->name;
            $result[$key] = $postType->label;
        }

        return $result;
    }

    /**
     * Get taxonomies with their terms
     *
     * @return array
     */
    private function getTaxonomiesWithTerms()
    {
        $taxonomies = get_taxonomies(['show_ui' => true], 'objects');
        $result = [];

        foreach ($taxonomies as $taxonomy) {
            $terms = get_terms([
                'taxonomy'   => $taxonomy->name,
                'hide_empty' => false,
                'number'     => 100,
            ]);

            if (!is_wp_error($terms) && !empty($terms)) {
                $termList = [];
                foreach ($terms as $term) {
                    $termList[$term->term_id] = $term->name;
                }

                $result[$taxonomy->name] = [
                    'label' => $taxonomy->label,
                    'terms' => $termList,
                ];
            }
        }

        return $result;
    }

    /**
     * Get WordPress user roles
     *
     * @return array
     */
    private function getUserRoles()
    {
        return wp_roles()->get_names();
    }

    /**
     * Get newsletter subscriber groups with counts
     *
     * @return array
     */
    private function getNewsletterGroups()
    {
        if (!class_exists('WP_SMS\\Newsletter')) {
            return [];
        }

        $groups = Newsletter::getGroups();
        if (!is_array($groups)) {
            return [];
        }

        $result = [];
        foreach ($groups as $group) {
            if (isset($group->ID) && isset($group->name)) {
                $count = Newsletter::getTotal($group->ID);
                $result[] = [
                    'id'    => (int) $group->ID,
                    'name'  => $group->name,
                    'count' => (int) $count,
                ];
            }
        }

        return $result;
    }

    /**
     * Get active add-ons
     *
     * @return array
     */
    private function getActiveAddons()
    {
        $addons = [
            'pro'         => 'wp-sms-pro/wp-sms-pro.php',
            'woocommerce' => 'wp-sms-woocommerce-pro/wp-sms-woocommerce-pro.php',
            'two-way'     => 'wp-sms-two-way/wp-sms-two-way.php',
            'elementor'   => 'wp-sms-elementor-form/wp-sms-elementor-form.php',
            'membership'  => 'wp-sms-membership-integrations/wp-sms-membership-integrations.php',
            'booking'     => 'wp-sms-booking-integrations/wp-sms-booking-integrations.php',
            'fluent'      => 'wp-sms-fluent-integrations/wp-sms-fluent-integrations.php',
            'otp'         => 'wp-sms-otp-mfa/wp-sms-otp-mfa.php',
        ];

        $active = [];
        foreach ($addons as $key => $plugin) {
            $active[$key] = is_plugin_active($plugin);
        }

        return $active;
    }

    /**
     * Check which add-ons support the new React dashboard.
     *
     * Updated add-ons opt in by hooking into the 'wpsms_addon_dashboard_support'
     * filter and setting their key to true. Old add-ons that haven't been updated
     * won't hook in, so their key stays false — allowing the dashboard to show a
     * friendly "update required" message instead of broken error screens.
     *
     * @return array<string, bool>
     */
    private function getAddonDashboardSupport()
    {
        $support = apply_filters('wpsms_addon_dashboard_support', []);

        $knownAddons = ['pro', 'woocommerce', 'two-way'];
        $result = [];
        foreach ($knownAddons as $key) {
            $result[$key] = !empty($support[$key]);
        }

        return $result;
    }

    /**
     * Get third-party plugin status for integrations page
     *
     * Checks whether integration-related plugins are installed and active.
     *
     * @return array Plugin status information
     */
    private function getThirdPartyPluginStatus()
    {
        $plugins = [
            // Form plugins (free version support)
            'contact-form-7' => [
                'file'       => 'contact-form-7/wp-contact-form-7.php',
                'name'       => 'Contact Form 7',
                'wpOrgSlug'  => 'contact-form-7',
            ],
            'formidable' => [
                'file'       => 'formidable/formidable.php',
                'name'       => 'Formidable Forms',
                'wpOrgSlug'  => 'formidable',
            ],
            'forminator' => [
                'file'       => 'forminator/forminator.php',
                'name'       => 'Forminator',
                'wpOrgSlug'  => 'forminator',
            ],

            // WP SMS Pro pack integrations
            'gravity-forms' => [
                'file'       => 'gravityforms/gravityforms.php',
                'name'       => 'Gravity Forms',
                'wpOrgSlug'  => null,
                'externalUrl' => 'https://www.gravityforms.com/',
            ],
            'quform' => [
                'file'       => 'quform/quform.php',
                'name'       => 'Quform',
                'wpOrgSlug'  => null,
                'externalUrl' => 'https://www.quform.com/',
            ],
            'woocommerce' => [
                'file'       => 'woocommerce/woocommerce.php',
                'name'       => 'WooCommerce',
                'wpOrgSlug'  => 'woocommerce',
            ],
            'buddypress' => [
                'file'       => 'buddypress/bp-loader.php',
                'name'       => 'BuddyPress',
                'wpOrgSlug'  => 'buddypress',
            ],
            'easy-digital-downloads' => [
                'file'       => 'easy-digital-downloads/easy-digital-downloads.php',
                'name'       => 'Easy Digital Downloads',
                'wpOrgSlug'  => 'easy-digital-downloads',
            ],
            'wp-job-manager' => [
                'file'       => 'wp-job-manager/wp-job-manager.php',
                'name'       => 'WP Job Manager',
                'wpOrgSlug'  => 'wp-job-manager',
            ],
            'awesome-support' => [
                'file'       => 'awesome-support/awesome-support.php',
                'name'       => 'Awesome Support',
                'wpOrgSlug'  => 'awesome-support',
            ],
            'ultimate-member' => [
                'file'       => 'ultimate-member/ultimate-member.php',
                'name'       => 'Ultimate Member',
                'wpOrgSlug'  => 'ultimate-member',
            ],

            // Separate add-on integrations
            'elementor' => [
                'file'       => 'elementor/elementor.php',
                'name'       => 'Elementor',
                'wpOrgSlug'  => 'elementor',
            ],
            'elementor-pro' => [
                'file'       => 'elementor-pro/elementor-pro.php',
                'name'       => 'Elementor Pro',
                'wpOrgSlug'  => null,
                'externalUrl' => 'https://elementor.com/pro/',
            ],
            'fluent-crm' => [
                'file'       => 'fluent-crm/fluent-crm.php',
                'name'       => 'Fluent CRM',
                'wpOrgSlug'  => 'fluent-crm',
            ],
            'fluentform' => [
                'file'       => 'fluentform/fluentform.php',
                'name'       => 'Fluent Forms',
                'wpOrgSlug'  => 'fluentform',
            ],
            'fluent-support' => [
                'file'       => 'fluent-support/fluent-support.php',
                'name'       => 'Fluent Support',
                'wpOrgSlug'  => 'fluent-support',
            ],
            'paid-memberships-pro' => [
                'file'       => 'paid-memberships-pro/paid-memberships-pro.php',
                'name'       => 'Paid Memberships Pro',
                'wpOrgSlug'  => 'paid-memberships-pro',
            ],
            'simple-membership' => [
                'file'       => 'simple-membership/simple-wp-membership.php',
                'name'       => 'Simple Membership',
                'wpOrgSlug'  => 'simple-membership',
            ],
            'bookingpress' => [
                'file'       => 'bookingpress-appointment-booking/bookingpress-appointment-booking.php',
                'name'       => 'BookingPress',
                'wpOrgSlug'  => 'bookingpress-appointment-booking',
            ],
            'woocommerce-appointments' => [
                'file'       => 'woocommerce-appointments/woocommerce-appointments.php',
                'name'       => 'WooCommerce Appointments',
                'wpOrgSlug'  => null,
                'externalUrl' => 'https://woocommerce.com/products/woocommerce-appointments/',
            ],
            'woocommerce-bookings' => [
                'file'       => 'woocommerce-bookings/woocommerce-bookings.php',
                'name'       => 'WooCommerce Bookings',
                'wpOrgSlug'  => null,
                'externalUrl' => 'https://woocommerce.com/products/woocommerce-bookings/',
            ],
            'booking' => [
                'file'       => 'booking/wpdev-booking.php',
                'name'       => 'Booking Calendar',
                'wpOrgSlug'  => 'booking',
            ],
        ];

        $result = [];
        $installedPlugins = get_plugins();

        foreach ($plugins as $key => $plugin) {
            $isInstalled = isset($installedPlugins[$plugin['file']]);
            $isActive = is_plugin_active($plugin['file']);

            // Determine status
            if ($isActive) {
                $status = 'active';
            } elseif ($isInstalled) {
                $status = 'inactive';
            } else {
                $status = 'not_installed';
            }

            // Build action URL based on status
            $actionUrl = '';
            if ($status === 'inactive') {
                $actionUrl = admin_url('plugins.php');
            } elseif ($status === 'not_installed') {
                if (!empty($plugin['wpOrgSlug'])) {
                    $actionUrl = admin_url('plugin-install.php?s=' . urlencode($plugin['name']) . '&tab=search&type=term');
                } elseif (!empty($plugin['externalUrl'])) {
                    $actionUrl = $plugin['externalUrl'];
                }
            }

            $result[$key] = [
                'name'      => $plugin['name'],
                'status'    => $status,
                'actionUrl' => $actionUrl,
                'isExternal' => !empty($plugin['externalUrl']) && $status === 'not_installed',
            ];
        }

        return $result;
    }

    /**
     * Get Forminator forms data for React settings
     *
     * Returns forms list with their fields and notification variables
     * for dynamic rendering in the React integrations page.
     *
     * @return array
     */
    private function getForminatorFormsData()
    {
        $result = [
            'isActive' => false,
            'forms'    => [],
        ];

        if (!class_exists('Forminator') || !class_exists('Forminator_API')) {
            return $result;
        }

        $result['isActive'] = true;

        try {
            $forms = \Forminator_API::get_forms(null, 1, 100, 'publish');

            if (empty($forms)) {
                return $result;
            }

            foreach ($forms as $form) {
                $formId = $form->id;
                $formFields = [];

                // Get form fields
                $fields = \Forminator_API::get_form_fields($formId);
                if (!empty($fields)) {
                    foreach ($fields as $field) {
                        $formFields[$field->slug] = $field->raw['field_label'] ?? $field->slug;
                    }
                }

                // Get notification variables
                $variables = [
                    ['key' => '%site_name%', 'label' => 'Site Name'],
                    ['key' => '%site_url%', 'label' => 'Site URL'],
                ];
                if (class_exists('WP_SMS\\Notification\\NotificationFactory')) {
                    $notificationVariables = \WP_SMS\Notification\NotificationFactory::getForminator($formId)->getVariables();
                    foreach ($notificationVariables as $key => $value) {
                        // Skip base variables already added above
                        if (in_array($key, ['%site_name%', '%site_url%'])) {
                            continue;
                        }
                        // Field variables: use the form field label from $formFields
                        preg_match("/^%field-(.+)%$/", $key, $match);
                        $label = isset($match[1]) && isset($formFields[$match[1]])
                            ? $formFields[$match[1]]
                            : (isset($match[1]) ? $match[1] : $key);
                        $variables[] = [
                            'key'   => $key,
                            'label' => $label,
                        ];
                    }
                }

                $result['forms'][] = [
                    'id'        => $formId,
                    'name'      => $form->name,
                    'fields'    => $formFields,
                    'variables' => $variables,
                ];
            }
        } catch (\Exception $e) {
            // Forminator not properly set up, return empty
        }

        return $result;
    }

    /**
     * Get Gravity Forms data for React settings
     *
     * Returns forms list with their fields and notification variables
     * for dynamic rendering in the React integrations page.
     *
     * @return array
     */
    private function getGravityFormsData()
    {
        $result = [
            'isActive' => false,
            'forms'    => [],
        ];

        if (!class_exists('RGFormsModel')) {
            return $result;
        }

        $result['isActive'] = true;

        try {
            $forms = \RGFormsModel::get_forms(null, 'title');

            if (empty($forms)) {
                return $result;
            }

            foreach ($forms as $form) {
                $formId = $form->id;
                $formFields = [];

                // Get form fields (excluding non-input types)
                $fields = \WP_SMS\Gravityforms::get_field($formId);
                if (!empty($fields) && is_array($fields)) {
                    foreach ($fields as $fieldId => $label) {
                        $formFields[(string)$fieldId] = $label;
                    }
                }

                // Build notification variables
                $variables = [
                    ['key' => '%title%', 'label' => 'Form Title'],
                    ['key' => '%ip%', 'label' => 'IP Address'],
                    ['key' => '%source_url%', 'label' => 'Source URL'],
                    ['key' => '%user_agent%', 'label' => 'User Agent'],
                ];

                // Add field variables
                if (!empty($fields) && is_array($fields)) {
                    foreach ($fields as $fieldId => $label) {
                        $variables[] = [
                            'key'   => "%field-{$fieldId}%",
                            'label' => $label,
                        ];
                    }
                }

                // hasFields indicates whether "Send to field" section should be shown
                $hasFields = !empty($formFields);

                $result['forms'][] = [
                    'id'        => $formId,
                    'name'      => $form->title,
                    'fields'    => $formFields,
                    'variables' => $variables,
                    'hasFields' => $hasFields,
                ];
            }
        } catch (\Exception $e) {
            // Gravity Forms not properly set up, return empty
        }

        return $result;
    }

    /**
     * Get Quform data for React settings
     *
     * Returns forms list with their fields and notification variables
     * for dynamic rendering in the React integrations page.
     *
     * @return array
     */
    private function getQuformFormsData()
    {
        $result = [
            'isActive' => false,
            'forms'    => [],
        ];

        if (!class_exists('Quform_Repository')) {
            return $result;
        }

        $result['isActive'] = true;

        try {
            $quform = new \Quform_Repository();
            $forms = $quform->allForms();

            if (empty($forms)) {
                return $result;
            }

            foreach ($forms as $form) {
                $formId = $form['id'];
                $formFields = [];

                // Get form fields (excluding non-input types)
                $fields = \WP_SMS\Quform::get_fields($formId);
                if (!empty($fields) && is_array($fields)) {
                    foreach ($fields as $fieldId => $label) {
                        $formFields[(string)$fieldId] = $label;
                    }
                }

                // Build notification variables
                $variables = [
                    ['key' => '%post_title%', 'label' => 'Post Title'],
                    ['key' => '%form_url%', 'label' => 'Form URL'],
                    ['key' => '%referring_url%', 'label' => 'Referring URL'],
                ];

                // Add field variables
                if (!empty($fields) && is_array($fields)) {
                    foreach ($fields as $fieldId => $label) {
                        $variables[] = [
                            'key'   => "%field-{$fieldId}%",
                            'label' => $label,
                        ];
                    }
                }

                // Check if form has elements (for "Send to field" feature)
                // Legacy code uses $form['elements'] directly for this check
                $hasElements = !empty($form['elements']);

                $result['forms'][] = [
                    'id'          => $formId,
                    'name'        => $form['name'],
                    'fields'      => $formFields,
                    'variables'   => $variables,
                    'hasElements' => $hasElements,
                ];
            }
        } catch (\Exception $e) {
            // Quform not properly set up, return empty
        }

        return $result;
    }

    /**
     * Get add-on settings schema from filter
     *
     * @return array
     */
    private function getAddonSettingsSchema()
    {
        $schemas = apply_filters('wpsms_addon_settings_schema', []);
        return $this->validateAddonSchemas($schemas);
    }

    /**
     * Validate and sanitize add-on schemas
     *
     * @param array $schemas
     * @return array
     */
    private function validateAddonSchemas($schemas)
    {
        if (!is_array($schemas)) {
            return [];
        }

        // Validate addon settings schema
        // For brevity, just return sanitized schemas
        $validated = [];
        foreach ($schemas as $addonSlug => $schema) {
            if (!is_array($schema) || empty($schema['fields'])) {
                continue;
            }
            $validated[sanitize_key($addonSlug)] = $schema;
        }

        return $validated;
    }

    /**
     * Get add-on option values from database
     *
     * Loads option values for all registered add-on fields,
     * converting WooCommerce 'yes'/'no' strings to boolean.
     *
     * @return array
     */
    private function getAddonOptionValues()
    {
        $schemas = apply_filters('wpsms_addon_settings_schema', []);
        $values = [];

        foreach ($schemas as $addonSlug => $schema) {
            // Check if addon provides pre-loaded values (for add-ons using legacy storage like wpsms_settings array)
            // This allows add-ons to read values from their own storage and provide them directly
            if (!empty($schema['data']['currentValues']) && is_array($schema['data']['currentValues'])) {
                $values[sanitize_key($addonSlug)] = $schema['data']['currentValues'];
                continue;
            }

            if (empty($schema['fields']) || !is_array($schema['fields'])) {
                continue;
            }

            $addonValues = [];
            foreach ($schema['fields'] as $field) {
                if (empty($field['id'])) {
                    continue;
                }

                $optionKey = $field['id'];
                $fieldType = $field['type'] ?? 'text';
                $default = $field['default'] ?? null;

                $value = get_option($optionKey, $default);

                // Convert 'yes'/'no' to boolean for switch/checkbox fields (WooCommerce compatibility)
                if (in_array($fieldType, ['switch', 'checkbox'], true)) {
                    if ($value === 'yes') {
                        $value = true;
                    } elseif ($value === 'no' || $value === '' || $value === null) {
                        $value = false;
                    } else {
                        $value = (bool) $value;
                    }
                }

                $addonValues[$optionKey] = $value;
            }

            if (!empty($addonValues)) {
                $values[sanitize_key($addonSlug)] = $addonValues;
            }
        }

        return $values;
    }

}
