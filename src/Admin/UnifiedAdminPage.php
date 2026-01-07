<?php

namespace WP_SMS\Admin;

use WP_SMS\Components\Singleton;
use WP_SMS\Option;
use WP_SMS\Gateway;
use WP_SMS\Newsletter;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Unified Admin Page - React-based admin interface for all WP-SMS pages
 *
 * This class provides a unified React application that handles:
 * - Send SMS
 * - Outbox
 * - Subscribers
 * - Groups
 * - Privacy (GDPR)
 * - Settings (all existing settings pages)
 */
class UnifiedAdminPage extends Singleton
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

        add_filter('wp_sms_admin_menu_list', [$this, 'registerMenu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
    }

    /**
     * Register the menu item
     *
     * @param array $list
     * @return array
     */
    public function registerMenu($list)
    {
        $list['unified-admin'] = [
            'sub'      => 'wp-sms',
            'title'    => __('Dashboard', 'wp-sms'),
            'name'     => __('Dashboard', 'wp-sms') . ' <span style="background:#1d4ed8;color:white;padding:2px 6px;border-radius:3px;font-size:10px;margin-left:5px;">Beta</span>',
            'cap'      => 'wpsms_sendsms',
            'page_url' => 'unified-admin',
            'callback' => __CLASS__,
            'priority' => 0, // First in menu
        ];

        return $list;
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
        echo '<style>
            .wrap { max-width: none !important; margin: 0 !important; padding: 0 !important; }
            .wrap > h1:first-child { display: none; }
            .notice, .updated, .error, .is-dismissible { display: none !important; }
            #wpfooter { display: none !important; }
            #wpbody-content { padding: 0 !important; }
            #wpcontent { padding: 0 !important; }
            /* Hide chatbox by default - React Preview button will toggle it */
            .wpsms-chatbox { display: none !important; }
            .wpsms-chatbox.wpsms-chatbox--visible { display: block !important; }
        </style>';
        echo '<div id="wpsms-settings-root" class="wpsms-settings-app"></div>';
    }

    /**
     * Enqueue assets for the admin page
     *
     * @param string $hook
     */
    public function enqueueAssets($hook)
    {
        if (strpos($hook, 'unified-admin') === false) {
            return;
        }

        $distPath = WP_SMS_DIR . 'assets/dist/settings/';
        $distUrl = WP_SMS_URL . 'assets/dist/settings/';

        // Check for Vite manifest
        $manifestPath = $distPath . '.vite/manifest.json';

        // Use dev server if WPSMS_SETTINGS_DEV is defined or manifest doesn't exist
        $useDevServer = defined('WPSMS_SETTINGS_DEV') && WPSMS_SETTINGS_DEV;

        if (!$useDevServer && file_exists($manifestPath)) {
            // Production build
            $this->enqueueProductionAssets($manifestPath, $distUrl);
        } else {
            // Development mode - use Vite dev server
            $this->enqueueDevelopmentAssets();
        }

        // Localize script data
        wp_localize_script('wpsms-unified-admin', 'wpSmsSettings', $this->getLocalizedData());
    }

    /**
     * Enqueue production assets from Vite build
     *
     * @param string $manifestPath
     * @param string $distUrl
     */
    private function enqueueProductionAssets($manifestPath, $distUrl)
    {
        $manifest = json_decode(file_get_contents($manifestPath), true);

        // Find the main entry file
        $mainEntry = null;
        foreach ($manifest as $key => $entry) {
            if (isset($entry['isEntry']) && $entry['isEntry']) {
                $mainEntry = $entry;
                break;
            }
        }

        if (!$mainEntry) {
            return;
        }

        // Enqueue CSS
        if (isset($mainEntry['css'])) {
            foreach ($mainEntry['css'] as $index => $cssFile) {
                wp_enqueue_style(
                    'wpsms-unified-admin' . ($index > 0 ? '-' . $index : ''),
                    $distUrl . $cssFile,
                    [],
                    WP_SMS_VERSION
                );
            }
        }

        // Enqueue JS
        wp_enqueue_script(
            'wpsms-unified-admin',
            $distUrl . $mainEntry['file'],
            [],
            WP_SMS_VERSION . '.' . filemtime(WP_SMS_DIR . 'assets/dist/settings/' . $mainEntry['file']),
            true
        );

        // Add type="module" to the script tag for ESM
        add_filter('script_loader_tag', function ($tag, $handle) {
            if ($handle === 'wpsms-unified-admin') {
                return str_replace(' src', ' type="module" src', $tag);
            }
            return $tag;
        }, 10, 2);
    }

    /**
     * Enqueue development assets from Vite dev server
     */
    private function enqueueDevelopmentAssets()
    {
        $viteDevServerUrl = 'http://localhost:5177';
        $localizedData = $this->getLocalizedData();

        // Inject localized data and Vite scripts directly in footer
        add_action('admin_footer', function () use ($viteDevServerUrl, $localizedData) {
            ?>
            <script>
                window.wpSmsSettings = <?php echo wp_json_encode($localizedData); ?>;
            </script>
            <script type="module">
                import RefreshRuntime from '<?php echo esc_url($viteDevServerUrl); ?>/@react-refresh'
                RefreshRuntime.injectIntoGlobalHook(window)
                window.$RefreshReg$ = () => {}
                window.$RefreshSig$ = () => (type) => type
                window.__vite_plugin_react_preamble_installed__ = true
            </script>
            <script type="module" src="<?php echo esc_url($viteDevServerUrl); ?>/@vite/client"></script>
            <script type="module" src="<?php echo esc_url($viteDevServerUrl); ?>/main.jsx"></script>
            <?php
        });

        // Register empty script for wp_localize_script compatibility
        wp_register_script('wpsms-unified-admin', '', [], null, true);
        wp_enqueue_script('wpsms-unified-admin');
    }

    /**
     * Get localized data for the React app
     *
     * @return array
     */
    private function getLocalizedData()
    {
        return [
            'apiUrl'        => rest_url('wpsms/v1/'),
            'nonce'         => wp_create_nonce('wp_rest'),
            'settings'      => $this->maskSensitiveSettings(Option::getOptions()),
            'proSettings'   => $this->maskSensitiveSettings(Option::getOptions(true)),
            'addons'        => $this->getActiveAddons(),
            'gateways'      => Gateway::gateway(),
            'gateway'       => $this->getGatewayCapabilities(),
            'adminUrl'      => admin_url(),
            'siteUrl'       => site_url(),
            'version'       => WP_SMS_VERSION,
            'i18n'          => $this->getTranslations(),
            // Dynamic data for multi-select fields
            'countries'     => $this->getCountries(),
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
            // Extended data for unified admin pages
            'stats'         => $this->getStats(),
            'capabilities'  => $this->getUserCapabilities(),
            'features'      => $this->getFeatureFlags(),
        ];
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
     * Get countries list
     *
     * @return array
     */
    private function getCountries()
    {
        if (function_exists('wp_sms_countries')) {
            return wp_sms_countries()->getCountries();
        }
        return [];
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

        foreach ($postTypes as $postType) {
            $result[$postType->name] = $postType->label;
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
                $variables = [];
                if (class_exists('WP_SMS\\Notification\\NotificationFactory')) {
                    $notificationVariables = \WP_SMS\Notification\NotificationFactory::getForminator($formId)->getVariables();
                    foreach ($notificationVariables as $key => $value) {
                        preg_match("/(%field-|%)(.+)*\%/", $key, $match);
                        $label = isset($match[2]) ? $match[2] : $key;
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

        // Use the same validation logic as NewSettingsPage
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

    /**
     * Get translations for the React app
     *
     * @return array
     */
    private function getTranslations()
    {
        return [
            // Common
            'save'           => __('Save Changes', 'wp-sms'),
            'saving'         => __('Saving...', 'wp-sms'),
            'saved'          => __('Changes saved', 'wp-sms'),
            'error'          => __('Error saving changes', 'wp-sms'),
            'unsavedChanges' => __('You have unsaved changes', 'wp-sms'),
            'discard'        => __('Discard', 'wp-sms'),
            'cancel'         => __('Cancel', 'wp-sms'),
            'confirm'        => __('Confirm', 'wp-sms'),
            'loading'        => __('Loading...', 'wp-sms'),
            'search'         => __('Search...', 'wp-sms'),
            'noResults'      => __('No results found', 'wp-sms'),
            'required'       => __('This field is required', 'wp-sms'),
            'invalid'        => __('Invalid value', 'wp-sms'),

            // Actions
            'add'            => __('Add', 'wp-sms'),
            'edit'           => __('Edit', 'wp-sms'),
            'delete'         => __('Delete', 'wp-sms'),
            'resend'         => __('Resend', 'wp-sms'),
            'export'         => __('Export', 'wp-sms'),
            'import'         => __('Import', 'wp-sms'),
            'Browse'         => __('Browse', 'wp-sms'),
            'Test'           => __('Test', 'wp-sms'),
            'Switch'         => __('Switch', 'wp-sms'),
            'Disconnect'     => __('Disconnect', 'wp-sms'),

            // Navigation
            'Send SMS'       => __('Send SMS', 'wp-sms'),
            'Outbox'         => __('Outbox', 'wp-sms'),
            'Subscribers'    => __('Subscribers', 'wp-sms'),
            'Groups'         => __('Groups', 'wp-sms'),
            'Settings'       => __('Settings', 'wp-sms'),
            'Overview'       => __('Overview', 'wp-sms'),
            'Gateway'        => __('Gateway', 'wp-sms'),
            'Phone'          => __('Phone', 'wp-sms'),
            'Message Button' => __('Message Button', 'wp-sms'),
            'Notifications'  => __('Notifications', 'wp-sms'),
            'Newsletter'     => __('Newsletter', 'wp-sms'),
            'Integrations'   => __('Integrations', 'wp-sms'),
            'Advanced'       => __('Advanced', 'wp-sms'),
            'Privacy'        => __('Privacy', 'wp-sms'),
            'Documentation'  => __('Documentation', 'wp-sms'),
            'Support'        => __('Support', 'wp-sms'),

            // Sidebar
            'Gateway Connected'       => __('Gateway Connected', 'wp-sms'),
            'Gateway not configured'  => __('Gateway not configured', 'wp-sms'),
            "What's New"              => __("What's New", 'wp-sms'),
            'Enjoying WSMS?'          => __('Enjoying WSMS?', 'wp-sms'),

            // Header
            'All-in-One'              => __('All-in-One', 'wp-sms'),
            'License:'                => __('License:', 'wp-sms'),
            'Upgrade'                 => __('Upgrade', 'wp-sms'),
            'Upgrade to All-in-One'   => __('Upgrade to All-in-One', 'wp-sms'),

            // Footer
            'Made with'                   => __('Made with', 'wp-sms'),
            'for the WordPress community' => __('for the WordPress community', 'wp-sms'),

            // Gateway page
            'SMS Gateway'             => __('SMS Gateway', 'wp-sms'),
            'Search gateways...'      => __('Search gateways...', 'wp-sms'),
            'No gateways found'       => __('No gateways found', 'wp-sms'),
            'Selected Gateway'        => __('Selected Gateway', 'wp-sms'),
            'Switch to'               => __('Switch to', 'wp-sms'),
            'Are you sure?'           => __('Are you sure?', 'wp-sms'),
            'No Gateway Selected'     => __('No Gateway Selected', 'wp-sms'),
            'Choose a provider from the list above' => __('Choose a provider from the list above', 'wp-sms'),
            'Capabilities:'           => __('Capabilities:', 'wp-sms'),
            'Flash SMS'               => __('Flash SMS', 'wp-sms'),
            'Bulk Send'               => __('Bulk Send', 'wp-sms'),
            'MMS'                     => __('MMS', 'wp-sms'),
            'Incoming SMS'            => __('Incoming SMS', 'wp-sms'),
            'Gateway Guide'           => __('Gateway Guide', 'wp-sms'),
            'Setup instructions for'  => __('Setup instructions for', 'wp-sms'),
            'View Full Documentation' => __('View Full Documentation', 'wp-sms'),
            'Credentials'             => __('Credentials', 'wp-sms'),
            'API credentials for'     => __('API credentials for', 'wp-sms'),
            'Test Connection'         => __('Test Connection', 'wp-sms'),
            'Testing...'              => __('Testing...', 'wp-sms'),
            'Verify your credentials are working' => __('Verify your credentials are working', 'wp-sms'),
            'API Response'            => __('API Response', 'wp-sms'),
            'Raw response from the gateway for debugging' => __('Raw response from the gateway for debugging', 'wp-sms'),
            'Gateway Response:'       => __('Gateway Response:', 'wp-sms'),
            'Delivery Settings'       => __('Delivery Settings', 'wp-sms'),
            'Configure how messages are processed and delivered' => __('Configure how messages are processed and delivered', 'wp-sms'),
            'Delivery Method'         => __('Delivery Method', 'wp-sms'),
            'How SMS messages are processed and sent.' => __('How SMS messages are processed and sent.', 'wp-sms'),
            'Select method'           => __('Select method', 'wp-sms'),
            'Instant — Send immediately when triggered' => __('Instant — Send immediately when triggered', 'wp-sms'),
            'Background — Process in background (reduces page load time)' => __('Background — Process in background (reduces page load time)', 'wp-sms'),
            'Queue — Add to queue for batch processing' => __('Queue — Add to queue for batch processing', 'wp-sms'),
            'Message Formatting'      => __('Message Formatting', 'wp-sms'),
            'Enable Unicode'          => __('Enable Unicode', 'wp-sms'),
            'Required for non-Latin characters (Arabic, Chinese, emoji). May reduce characters per SMS.' => __('Required for non-Latin characters (Arabic, Chinese, emoji). May reduce characters per SMS.', 'wp-sms'),
            'Auto-format Numbers'     => __('Auto-format Numbers', 'wp-sms'),
            'Automatically remove spaces and special characters from phone numbers before sending.' => __('Automatically remove spaces and special characters from phone numbers before sending.', 'wp-sms'),
            'Country Restrictions'    => __('Country Restrictions', 'wp-sms'),
            'Limit SMS delivery to specific countries' => __('Limit SMS delivery to specific countries', 'wp-sms'),
            'Restrict to Specific Countries' => __('Restrict to Specific Countries', 'wp-sms'),
            'Only send SMS to phone numbers from selected countries.' => __('Only send SMS to phone numbers from selected countries.', 'wp-sms'),
            'Allowed Countries'       => __('Allowed Countries', 'wp-sms'),
            'Select countries...'     => __('Select countries...', 'wp-sms'),
            'Search countries...'     => __('Search countries...', 'wp-sms'),
            'SMS will only be sent to numbers from these countries.' => __('SMS will only be sent to numbers from these countries.', 'wp-sms'),
            'Credit Display'          => __('Credit Display', 'wp-sms'),
            'Show Credit in Menu'     => __('Show Credit in Menu', 'wp-sms'),
            'Display your SMS credit balance in the WordPress admin menu bar.' => __('Display your SMS credit balance in the WordPress admin menu bar.', 'wp-sms'),
            'Show Credit on Send Page' => __('Show Credit on Send Page', 'wp-sms'),
            'Display your remaining SMS credits when composing messages.' => __('Display your remaining SMS credits when composing messages.', 'wp-sms'),
            'Setup Wizard'            => __('Setup Wizard', 'wp-sms'),
            'Re-run the guided setup to update your gateway configuration' => __('Re-run the guided setup to update your gateway configuration', 'wp-sms'),
            'Re-run Wizard'           => __('Re-run Wizard', 'wp-sms'),

            // Gateway tips
            'Need help choosing a gateway?' => __('Need help choosing a gateway?', 'wp-sms'),
            'Consider factors like coverage area, pricing, and API features. Most gateways offer free trial credits to test before committing.' => __('Consider factors like coverage area, pricing, and API features. Most gateways offer free trial credits to test before committing.', 'wp-sms'),
            'Select your SMS service provider. Configure credentials below after selecting.' => __('Select your SMS service provider. Configure credentials below after selecting.', 'wp-sms'),
            'Save your changes to see capabilities and configure credentials for this gateway.' => __('Save your changes to see capabilities and configure credentials for this gateway.', 'wp-sms'),
            'Click'                   => __('Click', 'wp-sms'),
            'to verify your gateway credentials are working correctly.' => __('to verify your gateway credentials are working correctly.', 'wp-sms'),
            "Gateway connection verified successfully. You're ready to send SMS!" => __("Gateway connection verified successfully. You're ready to send SMS!", 'wp-sms'),
            'Connection test failed. Please check your credentials and try again.' => __('Connection test failed. Please check your credentials and try again.', 'wp-sms'),
            'Queue mode requires a cron job to process messages. Configure WP-Cron or set up a real cron job for reliable delivery.' => __('Queue mode requires a cron job to process messages. Configure WP-Cron or set up a real cron job for reliable delivery.', 'wp-sms'),

            // Overview page
            'Welcome to WSMS!'        => __('Welcome to WSMS!', 'wp-sms'),
            'Complete these steps to start sending SMS messages from your WordPress site.' => __('Complete these steps to start sending SMS messages from your WordPress site.', 'wp-sms'),
            'Configure SMS Gateway'   => __('Configure SMS Gateway', 'wp-sms'),
            'Connected to'            => __('Connected to', 'wp-sms'),
            'Select your SMS provider' => __('Select your SMS provider', 'wp-sms'),
            'Set Admin Mobile Number' => __('Set Admin Mobile Number', 'wp-sms'),
            'Add your phone for test messages' => __('Add your phone for test messages', 'wp-sms'),
            'Test Your Connection'    => __('Test Your Connection', 'wp-sms'),
            'Gateway is working'      => __('Gateway is working', 'wp-sms'),
            'Verify credentials work' => __('Verify credentials work', 'wp-sms'),
            "You're all set!"         => __("You're all set!", 'wp-sms'),
            'Your SMS gateway is configured and ready to send messages.' => __('Your SMS gateway is configured and ready to send messages.', 'wp-sms'),
            'Head to'                 => __('Head to', 'wp-sms'),
            'to send your first message.' => __('to send your first message.', 'wp-sms'),
            'Gateway Status'          => __('Gateway Status', 'wp-sms'),
            'Current SMS gateway connection' => __('Current SMS gateway connection', 'wp-sms'),
            'Connected'               => __('Connected', 'wp-sms'),
            'Credit:'                 => __('Credit:', 'wp-sms'),
            'Connection failed'       => __('Connection failed', 'wp-sms'),
            'Click to test'           => __('Click to test', 'wp-sms'),
            'Configure a gateway to send SMS' => __('Configure a gateway to send SMS', 'wp-sms'),
            'Configuration'           => __('Configuration', 'wp-sms'),
            'Quick access to main settings' => __('Quick access to main settings', 'wp-sms'),
            'Quick Links'             => __('Quick Links', 'wp-sms'),
            'Not configured'          => __('Not configured', 'wp-sms'),
            'Admin Mobile'            => __('Admin Mobile', 'wp-sms'),
            'Not set'                 => __('Not set', 'wp-sms'),
            'Enabled'                 => __('Enabled', 'wp-sms'),
            'Disabled'                => __('Disabled', 'wp-sms'),
            'SMS Gateways Available'  => __('SMS Gateways Available', 'wp-sms'),
            'Providers from around the world' => __('Providers from around the world', 'wp-sms'),
            'Pro'                     => __('Pro', 'wp-sms'),
            'Upgrade to WSMS Pro'     => __('Upgrade to WSMS Pro', 'wp-sms'),
            'OTP authentication, WooCommerce integration, and more' => __('OTP authentication, WooCommerce integration, and more', 'wp-sms'),
            'Learn More'              => __('Learn More', 'wp-sms'),

            // SendSms page
            'No SMS gateway configured.' => __('No SMS gateway configured.', 'wp-sms'),
            'You need to set up a gateway before you can send messages.' => __('You need to set up a gateway before you can send messages.', 'wp-sms'),
            'Configure Gateway'       => __('Configure Gateway', 'wp-sms'),
            'Credit'                  => __('Credit', 'wp-sms'),
            'Format:'                 => __('Format:', 'wp-sms'),
            'Compose Message'         => __('Compose Message', 'wp-sms'),
            'Sender ID'               => __('Sender ID', 'wp-sms'),
            'Type your message here...' => __('Type your message here...', 'wp-sms'),
            'Flash'                   => __('Flash', 'wp-sms'),
            'Media'                   => __('Media', 'wp-sms'),
            'Media URL (https://...)' => __('Media URL (https://...)', 'wp-sms'),
            'Recipients'              => __('Recipients', 'wp-sms'),
            "This gateway doesn't support bulk SMS. Only the first number will receive the message." => __("This gateway doesn't support bulk SMS. Only the first number will receive the message.", 'wp-sms'),
            'Recipients:'             => __('Recipients:', 'wp-sms'),
            'Segments:'               => __('Segments:', 'wp-sms'),
            'Total:'                  => __('Total:', 'wp-sms'),
            'Configure gateway first' => __('Configure gateway first', 'wp-sms'),
            'Add message and recipients' => __('Add message and recipients', 'wp-sms'),
            'Enter a message'         => __('Enter a message', 'wp-sms'),
            'Add recipients'          => __('Add recipients', 'wp-sms'),
            'Selected groups/roles have no subscribers' => __('Selected groups/roles have no subscribers', 'wp-sms'),
            'Checking recipients...'  => __('Checking recipients...', 'wp-sms'),
            'Review & Send'           => __('Review & Send', 'wp-sms'),

            // Old keys for backwards compatibility
            'sendSms'        => __('Send SMS', 'wp-sms'),
            'sending'        => __('Sending...', 'wp-sms'),
            'sent'           => __('Message sent successfully', 'wp-sms'),
            'recipients'     => __('Recipients', 'wp-sms'),
            'message'        => __('Message', 'wp-sms'),
            'characters'     => __('characters', 'wp-sms'),
            'segments'       => __('segments', 'wp-sms'),
            'outbox'         => __('Outbox', 'wp-sms'),
            'date'           => __('Date', 'wp-sms'),
            'sender'         => __('Sender', 'wp-sms'),
            'recipient'      => __('Recipient', 'wp-sms'),
            'status'         => __('Status', 'wp-sms'),
            'success'        => __('Success', 'wp-sms'),
            'failed'         => __('Failed', 'wp-sms'),
            'subscribers'    => __('Subscribers', 'wp-sms'),
            'subscriber'     => __('Subscriber', 'wp-sms'),
            'name'           => __('Name', 'wp-sms'),
            'mobile'         => __('Mobile', 'wp-sms'),
            'group'          => __('Group', 'wp-sms'),
            'active'         => __('Active', 'wp-sms'),
            'inactive'       => __('Inactive', 'wp-sms'),
            'groups'         => __('Groups', 'wp-sms'),
            'groupName'      => __('Group Name', 'wp-sms'),
            'subscriberCount' => __('Subscriber Count', 'wp-sms'),
            'privacy'        => __('Privacy', 'wp-sms'),
            'gdprCompliance' => __('GDPR Compliance', 'wp-sms'),
            'exportData'     => __('Export Data', 'wp-sms'),
            'deleteData'     => __('Delete Data', 'wp-sms'),
            'confirmDelete'  => __('Are you sure you want to delete this?', 'wp-sms'),
            'confirmResend'  => __('Are you sure you want to resend this message?', 'wp-sms'),

            // SMS Campaigns page
            'SMS Campaigns'   => __('SMS Campaigns', 'wp-sms'),
            'Create targeted SMS marketing campaigns based on customer behavior.' => __('Create targeted SMS marketing campaigns based on customer behavior.', 'wp-sms'),
            'Create targeted SMS marketing campaigns based on customer behavior. Set conditions, schedule delivery, and track results.' => __('Create targeted SMS marketing campaigns based on customer behavior. Set conditions, schedule delivery, and track results.', 'wp-sms'),
            'WooCommerce Pro Add-on Required' => __('WooCommerce Pro Add-on Required', 'wp-sms'),
            'Install and activate the WP SMS WooCommerce Pro add-on to access SMS Campaigns.' => __('Install and activate the WP SMS WooCommerce Pro add-on to access SMS Campaigns.', 'wp-sms'),
            'New Campaign'    => __('New Campaign', 'wp-sms'),
            'Create Campaign' => __('Create Campaign', 'wp-sms'),
            'Edit Campaign'   => __('Edit Campaign', 'wp-sms'),
            'Update Campaign' => __('Update Campaign', 'wp-sms'),
            'Delete Campaign' => __('Delete Campaign', 'wp-sms'),
            'Campaign Title'  => __('Campaign Title', 'wp-sms'),
            'Enter campaign title...' => __('Enter campaign title...', 'wp-sms'),
            'Conditions'      => __('Conditions', 'wp-sms'),
            'Add Condition'   => __('Add Condition', 'wp-sms'),
            'No conditions added. Campaign will match all orders.' => __('No conditions added. Campaign will match all orders.', 'wp-sms'),
            'Select type...'  => __('Select type...', 'wp-sms'),
            'Select value...' => __('Select value...', 'wp-sms'),
            'is'              => __('is', 'wp-sms'),
            'is not'          => __('is not', 'wp-sms'),
            'When to Send'    => __('When to Send', 'wp-sms'),
            'Select when to send...' => __('Select when to send...', 'wp-sms'),
            'Immediately'     => __('Immediately', 'wp-sms'),
            'Specific Date'   => __('Specific Date', 'wp-sms'),
            'After Placing Order' => __('After Placing Order', 'wp-sms'),
            'After Order'     => __('After Order', 'wp-sms'),
            'Minutes'         => __('Minutes', 'wp-sms'),
            'Hours'           => __('Hours', 'wp-sms'),
            'Days'            => __('Days', 'wp-sms'),
            'after order is placed' => __('after order is placed', 'wp-sms'),
            'Message Content' => __('Message Content', 'wp-sms'),
            'Enter your SMS message...' => __('Enter your SMS message...', 'wp-sms'),
            'Available variables:' => __('Available variables:', 'wp-sms'),
            'No message content' => __('No message content', 'wp-sms'),
            'Search campaigns...' => __('Search campaigns...', 'wp-sms'),
            'All Statuses'    => __('All Statuses', 'wp-sms'),
            'Campaigns'       => __('Campaigns', 'wp-sms'),
            'Showing %d of %d campaigns' => __('Showing %d of %d campaigns', 'wp-sms'),
            'No campaigns found' => __('No campaigns found', 'wp-sms'),
            'No campaigns yet' => __('No campaigns yet', 'wp-sms'),
            'Create your first SMS campaign to get started.' => __('Create your first SMS campaign to get started.', 'wp-sms'),
            'Campaign'        => __('Campaign', 'wp-sms'),
            'Schedule'        => __('Schedule', 'wp-sms'),
            'Queue'           => __('Queue', 'wp-sms'),
            'Queue Status'    => __('Queue Status', 'wp-sms'),
            'Created'         => __('Created', 'wp-sms'),
            'View'            => __('View', 'wp-sms'),
            'Update your SMS campaign settings.' => __('Update your SMS campaign settings.', 'wp-sms'),
            'Create a new targeted SMS marketing campaign.' => __('Create a new targeted SMS marketing campaign.', 'wp-sms'),
            'Campaign details and configuration' => __('Campaign details and configuration', 'wp-sms'),
            'Close'           => __('Close', 'wp-sms'),
            'Are you sure you want to delete "%s"? This action cannot be undone.' => __('Are you sure you want to delete "%s"? This action cannot be undone.', 'wp-sms'),
            'Page %d of %d'   => __('Page %d of %d', 'wp-sms'),
            'Order Status'    => __('Order Status', 'wp-sms'),
            'Coupon Code'     => __('Coupon Code', 'wp-sms'),
            'Product'         => __('Product', 'wp-sms'),
            'Product Type'    => __('Product Type', 'wp-sms'),
            'API request failed' => __('API request failed', 'wp-sms'),
            'Trashed'         => __('Trashed', 'wp-sms'),
            'Processing'      => __('Processing', 'wp-sms'),
            'Completed'       => __('Completed', 'wp-sms'),

            // Cart Abandonment page
            'Cart Abandonment' => __('Cart Abandonment', 'wp-sms'),
            'Cart Abandonment Recovery' => __('Cart Abandonment Recovery', 'wp-sms'),
            'Recover abandoned carts with automated SMS reminders.' => __('Recover abandoned carts with automated SMS reminders.', 'wp-sms'),
            'Recover lost sales by automatically sending SMS reminders to customers who abandoned their shopping carts.' => __('Recover lost sales by automatically sending SMS reminders to customers who abandoned their shopping carts.', 'wp-sms'),
            'Install and activate the WP SMS WooCommerce Pro add-on to access Cart Abandonment features.' => __('Install and activate the WP SMS WooCommerce Pro add-on to access Cart Abandonment features.', 'wp-sms'),
            'Recoverable Carts' => __('Recoverable Carts', 'wp-sms'),
            'Recovered Carts' => __('Recovered Carts', 'wp-sms'),
            'Recoverable Revenue' => __('Recoverable Revenue', 'wp-sms'),
            'SMS Sent'        => __('SMS Sent', 'wp-sms'),
            'in queue'        => __('in queue', 'wp-sms'),
            'Abandoned Carts' => __('Abandoned Carts', 'wp-sms'),
            'View and manage abandoned shopping carts' => __('View and manage abandoned shopping carts', 'wp-sms'),
            'Refresh'         => __('Refresh', 'wp-sms'),
            'Search by phone number...' => __('Search by phone number...', 'wp-sms'),
            'Filter by type'  => __('Filter by type', 'wp-sms'),
            'All Carts'       => __('All Carts', 'wp-sms'),
            'Abandoned Only'  => __('Abandoned Only', 'wp-sms'),
            'Recovered Only'  => __('Recovered Only', 'wp-sms'),
            'Time period'     => __('Time period', 'wp-sms'),
            'All Time'        => __('All Time', 'wp-sms'),
            'Today'           => __('Today', 'wp-sms'),
            'Yesterday'       => __('Yesterday', 'wp-sms'),
            'Last Week'       => __('Last Week', 'wp-sms'),
            'Last Month'      => __('Last Month', 'wp-sms'),
            'Apply'           => __('Apply', 'wp-sms'),
            'Customer'        => __('Customer', 'wp-sms'),
            'Cart Total'      => __('Cart Total', 'wp-sms'),
            'Recovered'       => __('Recovered', 'wp-sms'),
            'SMS Status'      => __('SMS Status', 'wp-sms'),
            'No abandoned carts found' => __('No abandoned carts found', 'wp-sms'),
            'Guest'           => __('Guest', 'wp-sms'),
            'Yes'             => __('Yes', 'wp-sms'),
            'No'              => __('No', 'wp-sms'),
            'Delete Abandoned Cart' => __('Delete Abandoned Cart', 'wp-sms'),
            'Are you sure you want to delete this abandoned cart record? This action cannot be undone.' => __('Are you sure you want to delete this abandoned cart record? This action cannot be undone.', 'wp-sms'),
            'Cart deleted successfully' => __('Cart deleted successfully', 'wp-sms'),
            'Failed to delete cart' => __('Failed to delete cart', 'wp-sms'),
            'Failed to load cart abandonment data' => __('Failed to load cart abandonment data', 'wp-sms'),
            'Deleting...'     => __('Deleting...', 'wp-sms'),
            'Not Scheduled'   => __('Not Scheduled', 'wp-sms'),
            'Not Sent'        => __('Not Sent', 'wp-sms'),
            'In Queue'        => __('In Queue', 'wp-sms'),
            'Sent'            => __('Sent', 'wp-sms'),
            'Request failed'  => __('Request failed', 'wp-sms'),

            // Advanced page
            'Webhooks'        => __('Webhooks', 'wp-sms'),
            'Integrate with external services via webhook notifications' => __('Integrate with external services via webhook notifications', 'wp-sms'),
            'Outgoing SMS Webhook' => __('Outgoing SMS Webhook', 'wp-sms'),
            'Called after each SMS is sent. Enter one URL per line.' => __('Called after each SMS is sent. Enter one URL per line.', 'wp-sms'),
            'New Subscriber Webhook' => __('New Subscriber Webhook', 'wp-sms'),
            'Called when someone subscribes to your SMS newsletter.' => __('Called when someone subscribes to your SMS newsletter.', 'wp-sms'),
            'Incoming SMS Webhook' => __('Incoming SMS Webhook', 'wp-sms'),
            'Called when you receive an SMS reply. Requires Two-Way SMS add-on.' => __('Called when you receive an SMS reply. Requires Two-Way SMS add-on.', 'wp-sms'),
            'Message Storage'  => __('Message Storage', 'wp-sms'),
            'Configure message logging and automatic cleanup' => __('Configure message logging and automatic cleanup', 'wp-sms'),
            'Log Sent Messages' => __('Log Sent Messages', 'wp-sms'),
            'Save all sent SMS messages in the Outbox for tracking.' => __('Save all sent SMS messages in the Outbox for tracking.', 'wp-sms'),
            'Log sent messages' => __('Log sent messages', 'wp-sms'),
            'Auto-delete Sent Messages' => __('Auto-delete Sent Messages', 'wp-sms'),
            'Outbox retention period' => __('Outbox retention period', 'wp-sms'),
            'Select retention period' => __('Select retention period', 'wp-sms'),
            'After 30 days'   => __('After 30 days', 'wp-sms'),
            'After 90 days'   => __('After 90 days', 'wp-sms'),
            'After 180 days'  => __('After 180 days', 'wp-sms'),
            'After 365 days'  => __('After 365 days', 'wp-sms'),
            'Keep forever'    => __('Keep forever', 'wp-sms'),
            'Automatically remove old messages from the Outbox.' => __('Automatically remove old messages from the Outbox.', 'wp-sms'),
            'Log Received Messages' => __('Log Received Messages', 'wp-sms'),
            'Save incoming SMS messages in the Inbox.' => __('Save incoming SMS messages in the Inbox.', 'wp-sms'),
            'Log received messages' => __('Log received messages', 'wp-sms'),
            'Auto-delete Received Messages' => __('Auto-delete Received Messages', 'wp-sms'),
            'Inbox retention period' => __('Inbox retention period', 'wp-sms'),
            'Automatically remove old messages from the Inbox.' => __('Automatically remove old messages from the Inbox.', 'wp-sms'),
            'Admin Notifications' => __('Admin Notifications', 'wp-sms'),
            'Configure email reports and plugin notifications' => __('Configure email reports and plugin notifications', 'wp-sms'),
            'Weekly Statistics Email' => __('Weekly Statistics Email', 'wp-sms'),
            'Receive weekly SMS usage reports via email.' => __('Receive weekly SMS usage reports via email.', 'wp-sms'),
            'Enable weekly statistics email' => __('Enable weekly statistics email', 'wp-sms'),
            'Error Notifications' => __('Error Notifications', 'wp-sms'),
            'Email admin when SMS sending fails.' => __('Email admin when SMS sending fails.', 'wp-sms'),
            'Enable error notifications' => __('Enable error notifications', 'wp-sms'),
            'Plugin Notifications' => __('Plugin Notifications', 'wp-sms'),
            'Show update notices and announcements in the admin area.' => __('Show update notices and announcements in the admin area.', 'wp-sms'),
            'Enable plugin notifications' => __('Enable plugin notifications', 'wp-sms'),
            'Usage Analytics' => __('Usage Analytics', 'wp-sms'),
            'Share anonymous usage data to help improve WSMS.' => __('Share anonymous usage data to help improve WSMS.', 'wp-sms'),
            'Enable usage analytics' => __('Enable usage analytics', 'wp-sms'),
            'Additional Add-on Settings' => __('Additional Add-on Settings', 'wp-sms'),

            // Integrations page
            'Active'          => __('Active', 'wp-sms'),
            'Inactive'        => __('Inactive', 'wp-sms'),
            'Not Installed'   => __('Not Installed', 'wp-sms'),
            'Unknown'         => __('Unknown', 'wp-sms'),
            'Activate'        => __('Activate', 'wp-sms'),
            'Install'         => __('Install', 'wp-sms'),
            'Contact Form 7'  => __('Contact Form 7', 'wp-sms'),
            'Send SMS notifications when Contact Form 7 forms are submitted.' => __('Send SMS notifications when Contact Form 7 forms are submitted.', 'wp-sms'),
            'Adds an "SMS Notification" tab to the Contact Form 7 editor.' => __('Adds an "SMS Notification" tab to the Contact Form 7 editor.', 'wp-sms'),
            'Gravity Forms'   => __('Gravity Forms', 'wp-sms'),
            'Formidable Forms' => __('Formidable Forms', 'wp-sms'),
            'Forminator'      => __('Forminator', 'wp-sms'),
            'WooCommerce'     => __('WooCommerce', 'wp-sms'),
            'Elementor Forms' => __('Elementor Forms', 'wp-sms'),
            'Automatic support via add-on' => __('Automatic support via add-on', 'wp-sms'),
            'Available via WooCommerce add-on' => __('Available via WooCommerce add-on', 'wp-sms'),
            'Available via Elementor add-on' => __('Available via Elementor add-on', 'wp-sms'),
            'Form Plugin Integration' => __('Form Plugin Integration', 'wp-sms'),
            'Configure SMS notifications for form submissions' => __('Configure SMS notifications for form submissions', 'wp-sms'),
            'Enable'          => __('Enable', 'wp-sms'),
            'is required to use these settings.' => __('is required to use these settings.', 'wp-sms'),
            'Additional Integrations' => __('Additional Integrations', 'wp-sms'),
            'Other plugins supported through WSMS add-ons' => __('Other plugins supported through WSMS add-ons', 'wp-sms'),

            // Newsletter page
            'SMS Newsletter Configuration' => __('SMS Newsletter Configuration', 'wp-sms'),
            'Configure how visitors subscribe to your SMS notifications' => __('Configure how visitors subscribe to your SMS notifications', 'wp-sms'),
            'Show Groups in Form' => __('Show Groups in Form', 'wp-sms'),
            'Let subscribers choose which groups to join.' => __('Let subscribers choose which groups to join.', 'wp-sms'),
            'Show groups in form' => __('Show groups in form', 'wp-sms'),
            'Available Groups' => __('Available Groups', 'wp-sms'),
            'All groups'      => __('All groups', 'wp-sms'),
            'Search groups...' => __('Search groups...', 'wp-sms'),
            'Which groups subscribers can choose from. Leave empty for all groups.' => __('Which groups subscribers can choose from. Leave empty for all groups.', 'wp-sms'),
            'Allow Multiple Groups' => __('Allow Multiple Groups', 'wp-sms'),
            'Let subscribers join more than one group at a time.' => __('Let subscribers join more than one group at a time.', 'wp-sms'),
            'Allow multiple groups' => __('Allow multiple groups', 'wp-sms'),
            'Default Group'   => __('Default Group', 'wp-sms'),
            'Default group'   => __('Default group', 'wp-sms'),
            'Select a group'  => __('Select a group', 'wp-sms'),
            'All'             => __('All', 'wp-sms'),
            'Automatically add new subscribers to this group.' => __('Automatically add new subscribers to this group.', 'wp-sms'),
            'Require SMS Verification' => __('Require SMS Verification', 'wp-sms'),
            'Subscribers must verify their phone number via SMS code.' => __('Subscribers must verify their phone number via SMS code.', 'wp-sms'),
            'Require SMS verification' => __('Require SMS verification', 'wp-sms'),
            'Welcome SMS'     => __('Welcome SMS', 'wp-sms'),
            'Set up automatic SMS messages for new subscribers' => __('Set up automatic SMS messages for new subscribers', 'wp-sms'),
            'Send Welcome Message' => __('Send Welcome Message', 'wp-sms'),
            'Automatically send a welcome SMS to new subscribers.' => __('Automatically send a welcome SMS to new subscribers.', 'wp-sms'),
            'Send welcome message' => __('Send welcome message', 'wp-sms'),
            'Welcome Message' => __('Welcome Message', 'wp-sms'),
            'Welcome to our newsletter! Thanks for subscribing.' => __('Welcome to our newsletter! Thanks for subscribing.', 'wp-sms'),
            'Variables:'      => __('Variables:', 'wp-sms'),
            'Form Appearance' => __('Form Appearance', 'wp-sms'),
            'Customize the look of your subscription form' => __('Customize the look of your subscription form', 'wp-sms'),
            'Disable Default Styles' => __('Disable Default Styles', 'wp-sms'),
            'Remove plugin CSS to use your own form styling.' => __('Remove plugin CSS to use your own form styling.', 'wp-sms'),
            'Disable default styles' => __('Disable default styles', 'wp-sms'),
            'GDPR Compliance' => __('GDPR Compliance', 'wp-sms'),
            'Ensure your newsletter form is GDPR-compliant' => __('Ensure your newsletter form is GDPR-compliant', 'wp-sms'),
            'Privacy Consent Checkbox' => __('Privacy Consent Checkbox', 'wp-sms'),
            'Require subscribers to accept your privacy policy.' => __('Require subscribers to accept your privacy policy.', 'wp-sms'),
            'Enable privacy consent' => __('Enable privacy consent', 'wp-sms'),
            'Consent Message' => __('Consent Message', 'wp-sms'),
            'I agree to receive SMS messages and accept the privacy policy.' => __('I agree to receive SMS messages and accept the privacy policy.', 'wp-sms'),
            'Text shown next to the consent checkbox.' => __('Text shown next to the consent checkbox.', 'wp-sms'),
            'Privacy Page'    => __('Privacy Page', 'wp-sms'),
            'Select a page'   => __('Select a page', 'wp-sms'),
            'Link to your privacy policy page. Typically set in Settings → Privacy.' => __('Link to your privacy policy page. Typically set in Settings → Privacy.', 'wp-sms'),
            'Tip: Enable GDPR features in the Privacy settings page to show a consent checkbox on all forms.' => __('Tip: Enable GDPR features in the Privacy settings page to show a consent checkbox on all forms.', 'wp-sms'),
        ];
    }
}
